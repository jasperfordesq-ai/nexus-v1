<?php
// Copyright © 2024–2026 Jasper Ford
// SPDX-License-Identifier: AGPL-3.0-or-later
// Author: Jasper Ford
// See NOTICE file for attribution and acknowledgements.

declare(strict_types=1);

namespace Tests\Laravel\Unit\Models;

use App\Models\User;
use App\Services\TokenService;
use Illuminate\Database\QueryException;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use ReflectionMethod;
use Tests\Laravel\TestCase;

/**
 * User::moveTenant() Contract Tests
 *
 * Contract and lifecycle tests for tenant moves.
 */
class UserMoveTenantTest extends TestCase
{
    use DatabaseTransactions;

    // ==========================================
    // Method Existence & Signature Tests
    // ==========================================

    public function testMoveTenantMethodExists(): void
    {
        $this->assertTrue(
            method_exists(User::class, 'moveTenant'),
            'User::moveTenant() method should exist'
        );
    }

    public function testMoveTenantIsPublicStatic(): void
    {
        $method = new ReflectionMethod(User::class, 'moveTenant');

        $this->assertTrue($method->isPublic(), 'moveTenant should be public');
        $this->assertTrue($method->isStatic(), 'moveTenant should be static');
    }

    public function testMoveTenantParameterCount(): void
    {
        $method = new ReflectionMethod(User::class, 'moveTenant');
        $params = $method->getParameters();

        $this->assertCount(2, $params, 'moveTenant should have 2 parameters');
    }

    public function testMoveTenantParameterNames(): void
    {
        $method = new ReflectionMethod(User::class, 'moveTenant');
        $params = $method->getParameters();

        $this->assertEquals('userId', $params[0]->getName());
        $this->assertEquals('newTenantId', $params[1]->getName());
    }

    public function testMoveTenantParameterTypes(): void
    {
        $method = new ReflectionMethod(User::class, 'moveTenant');
        $params = $method->getParameters();

        $this->assertEquals('int', $params[0]->getType()->getName());
        $this->assertEquals('int', $params[1]->getType()->getName());
    }

    public function testMoveTenantBothParametersRequired(): void
    {
        $method = new ReflectionMethod(User::class, 'moveTenant');
        $params = $method->getParameters();

        $this->assertFalse($params[0]->isOptional(), 'userId should be required');
        $this->assertFalse($params[1]->isOptional(), 'newTenantId should be required');
    }

    public function testMoveTenantReturnType(): void
    {
        $method = new ReflectionMethod(User::class, 'moveTenant');
        $returnType = $method->getReturnType();

        $this->assertNotNull($returnType, 'moveTenant should declare a return type');
        // array{success,moved,failed} — the shape the super-admin move
        // endpoints consume (was bool before 8fa15107b).
        $this->assertEquals('array', $returnType->getName());
    }

    public function testMoveTenantSourceUpdatesTenantId(): void
    {
        $method = new ReflectionMethod(User::class, 'moveTenant');
        $lines = file($method->getFileName());
        $source = implode('', array_slice(
            $lines,
            $method->getStartLine() - 1,
            $method->getEndLine() - $method->getStartLine() + 1
        ));

        $this->assertStringContainsString('tenant_id', $source,
            'moveTenant should update tenant_id');
        $this->assertStringContainsString('newTenantId', $source,
            'moveTenant should use the newTenantId parameter');
    }

    public function testMoveTenantRevokesPasskeysBeforeChangingTenant(): void
    {
        $user = User::factory()->forTenant($this->testTenantId)->create();
        $credentialId = $this->insertPasskey((int) $user->id, $this->testTenantId);

        $result = User::moveTenant((int) $user->id, 999);

        $this->assertSame(['success' => true, 'moved' => 1, 'failed' => []], $result);
        $this->assertDatabaseHas('users', ['id' => $user->id, 'tenant_id' => 999]);
        $this->assertDatabaseMissing('webauthn_credentials', [
            'user_id' => $user->id,
            'credential_id' => $credentialId,
        ]);
    }

    public function testFailedTenantMoveRollsBackPasskeyRevocation(): void
    {
        $user = User::factory()->forTenant($this->testTenantId)->create();
        $credentialId = $this->insertPasskey((int) $user->id, $this->testTenantId);

        try {
            User::moveTenant((int) $user->id, 2_000_000_000);
            $this->fail('Moving to a tenant that does not exist should fail.');
        } catch (QueryException) {
            // The users.tenant_id foreign key rejects the move. The credential
            // deletion must be rolled back by the same transaction.
        }

        $this->assertDatabaseHas('users', [
            'id' => $user->id,
            'tenant_id' => $this->testTenantId,
        ]);
        $this->assertDatabaseHas('webauthn_credentials', [
            'user_id' => $user->id,
            'tenant_id' => $this->testTenantId,
            'credential_id' => $credentialId,
        ]);
    }

    public function testPasskeyOnlyUserMoveFailsWithRecoveryRequirement(): void
    {
        $user = User::factory()->forTenant($this->testTenantId)->create();
        DB::table('users')->where('id', $user->id)->update([
            'password' => null,
            'password_hash' => null,
        ]);
        $credentialId = $this->insertPasskey((int) $user->id, $this->testTenantId);

        $result = User::moveTenant((int) $user->id, 999);

        $this->assertSame([
            'success' => false,
            'moved' => 0,
            'failed' => ['passkey_recovery_required'],
        ], $result);
        $this->assertDatabaseHas('users', [
            'id' => $user->id,
            'tenant_id' => $this->testTenantId,
        ]);
        $this->assertDatabaseHas('webauthn_credentials', [
            'user_id' => $user->id,
            'credential_id' => $credentialId,
        ]);
    }

    public function testTenantMoveRevokesJwtAndSanctumSessions(): void
    {
        $user = User::factory()->forTenant($this->testTenantId)->create();
        $tokenService = app(TokenService::class);
        $accessToken = $tokenService->generateToken(
            (int) $user->id,
            $this->testTenantId
        );
        $user->createToken('tenant-move-regression');

        $result = User::moveTenant((int) $user->id, 999);

        $this->assertSame(['success' => true, 'moved' => 1, 'failed' => []], $result);
        $this->assertNull($tokenService->validateToken($accessToken));
        $this->assertDatabaseMissing('personal_access_tokens', [
            'tokenable_type' => User::class,
            'tokenable_id' => $user->id,
        ]);
    }

    private function insertPasskey(int $userId, int $tenantId): string
    {
        $credentialId = 'tenant-move-' . bin2hex(random_bytes(16));
        $data = [
            'user_id' => $userId,
            'tenant_id' => $tenantId,
            'credential_id' => $credentialId,
            'public_key' => 'test-public-key',
            'sign_count' => 0,
            'created_at' => now(),
        ];

        // Keep this lifecycle regression executable before and after the
        // hardening migration is applied to a developer's existing test DB.
        if (Schema::hasColumn('webauthn_credentials', 'user_handle')) {
            $data['user_handle'] = rtrim(strtr(base64_encode(hash(
                'sha256',
                $userId . ':' . $tenantId,
                true
            )), '+/', '-_'), '=');
        }

        DB::table('webauthn_credentials')->insert($data);

        return $credentialId;
    }
}
