<?php
// Copyright © 2024–2026 Jasper Ford
// SPDX-License-Identifier: AGPL-3.0-or-later
// Author: Jasper Ford
// See NOTICE file for attribution and acknowledgements.

namespace Tests\Laravel\Feature\Commands;

use App\Console\Commands\ResendStuckVerificationEmails;
use App\Core\TenantContext;
use App\Services\EmailDispatchService;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Illuminate\Support\Facades\DB;
use Tests\Laravel\TestCase;

class ResendStuckVerificationEmailsTest extends TestCase
{
    use DatabaseTransactions;

    public function test_failed_recovery_send_preserves_existing_verification_token(): void
    {
        $tenantId = 998;
        $userId = $this->seedPendingUser($tenantId);
        $oldToken = $this->seedVerificationToken($userId, $tenantId);

        app()->instance(EmailDispatchService::class, new RecoveryFailingEmailDispatchService());

        $this->assertFalse($this->sendOne($userId, $tenantId));

        $this->assertSame(1, DB::table('email_verification_tokens')
            ->where('user_id', $userId)
            ->where('tenant_id', $tenantId)
            ->count());
        $this->assertTrue(DB::table('email_verification_tokens')->where('token', $oldToken)->exists());
    }

    public function test_successful_recovery_send_replaces_existing_verification_token(): void
    {
        $tenantId = 997;
        $userId = $this->seedPendingUser($tenantId);
        $oldToken = $this->seedVerificationToken($userId, $tenantId);

        app()->instance(EmailDispatchService::class, new RecoverySuccessfulEmailDispatchService());

        $this->assertTrue($this->sendOne($userId, $tenantId));

        $this->assertSame(1, DB::table('email_verification_tokens')
            ->where('user_id', $userId)
            ->where('tenant_id', $tenantId)
            ->count());
        $this->assertFalse(DB::table('email_verification_tokens')->where('token', $oldToken)->exists());
    }

    private function seedPendingUser(int $tenantId): int
    {
        DB::table('tenants')->insertOrIgnore([
            'id' => $tenantId,
            'name' => 'Recovery Test Tenant ' . $tenantId,
            'slug' => 'recovery-test-' . $tenantId,
            'domain' => null,
            'is_active' => true,
            'depth' => 0,
            'allows_subtenants' => false,
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        return (int) DB::table('users')->insertGetId([
            'tenant_id' => $tenantId,
            'email' => 'recovery-test-' . $tenantId . '-' . uniqid() . '@example.test',
            'first_name' => 'Recovery',
            'name' => 'Recovery Test',
            'password_hash' => password_hash('x', PASSWORD_DEFAULT),
            'password' => 'unused',
            'status' => 'pending',
            'role' => 'member',
            'is_approved' => 0,
            'email_verified_at' => null,
            'preferred_language' => 'en',
            'created_at' => now(),
            'updated_at' => now(),
        ]);
    }

    private function seedVerificationToken(int $userId, int $tenantId): string
    {
        $token = 'previous-token-' . $tenantId . '-' . $userId;

        DB::table('email_verification_tokens')->insert([
            'user_id' => $userId,
            'tenant_id' => $tenantId,
            'token' => $token,
            'expires_at' => now()->addDay(),
            'created_at' => now(),
        ]);

        return $token;
    }

    private function sendOne(int $userId, int $tenantId): bool
    {
        TenantContext::setById($tenantId);

        $user = DB::table('users')
            ->where('id', $userId)
            ->where('tenant_id', $tenantId)
            ->first();

        $method = new \ReflectionMethod(ResendStuckVerificationEmails::class, 'sendOne');
        $method->setAccessible(true);

        return (bool) $method->invoke(new ResendStuckVerificationEmails(), $user);
    }
}

class RecoveryFailingEmailDispatchService extends EmailDispatchService
{
    public function send(string $to, string $subject, string $body, array $options = []): bool
    {
        return false;
    }
}

class RecoverySuccessfulEmailDispatchService extends EmailDispatchService
{
    public function send(string $to, string $subject, string $body, array $options = []): bool
    {
        return true;
    }
}
