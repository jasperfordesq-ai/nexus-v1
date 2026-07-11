<?php
// Copyright © 2024–2026 Jasper Ford
// SPDX-License-Identifier: AGPL-3.0-or-later
// Author: Jasper Ford
// See NOTICE file for attribution and acknowledgements.

namespace Tests\Laravel\Unit\Services;

use App\Core\TenantContext;
use App\Exceptions\SafeguardingPolicyException;
use App\Models\User;
use App\Services\GroupInviteService;
use App\Services\SafeguardingInteractionPolicy;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Illuminate\Support\Facades\DB;
use Mockery;
use Tests\Laravel\TestCase;

/**
 * Unit tests for GroupInviteService.
 *
 * These run against real seeded rows (DatabaseTransactions). The service's
 * permission gate (createLink / sendEmailInvites / revokeInvite) now delegates
 * to GroupService::canModify(), which mixes Eloquent (User::find /
 * Group::query()->find) with a DB query — a chain that cannot be faithfully
 * reproduced with Mockery DB-facade expectations. Seeding a genuine group +
 * owner exercises the real authorisation path instead.
 */
class GroupInviteServiceTest extends TestCase
{
    use DatabaseTransactions;

    private GroupInviteService $service;

    protected function setUp(): void
    {
        parent::setUp();
        $this->service = new GroupInviteService();
    }

    /**
     * Create a real, active user under the test tenant. Re-pins the tenant
     * context in case a model observer reset it during creation.
     */
    private function seedUser(): int
    {
        $user = User::factory()->forTenant($this->testTenantId)->create([
            'status'      => 'active',
            'is_approved' => true,
        ]);
        TenantContext::setById($this->testTenantId);

        return (int) $user->id;
    }

    /**
     * Seed a group owned by $ownerId. The owner passes GroupService::canModify().
     */
    private function seedGroup(int $ownerId): int
    {
        return DB::table('groups')->insertGetId([
            'tenant_id'  => $this->testTenantId,
            'owner_id'   => $ownerId,
            'name'       => 'Invite service unit-test group',
            'visibility' => 'public',
            'status'     => 'active',
            'is_active'  => 1,
            'created_at' => now(),
            'updated_at' => now(),
        ]);
    }

    public function test_createLink_returns_null_when_user_cannot_invite(): void
    {
        $ownerId = $this->seedUser();
        $outsiderId = $this->seedUser(); // not owner, not a member
        $groupId = $this->seedGroup($ownerId);

        $result = $this->service->createLink($groupId, $outsiderId);

        $this->assertNull($result);
        $errors = $this->service->getErrors();
        $this->assertCount(1, $errors);
        $this->assertEquals('FORBIDDEN', $errors[0]['code']);
    }

    public function test_createLink_returns_invite_data_on_success(): void
    {
        $ownerId = $this->seedUser();
        $groupId = $this->seedGroup($ownerId);

        $result = $this->service->createLink($groupId, $ownerId);

        $this->assertIsArray($result);
        $this->assertArrayHasKey('id', $result);
        $this->assertArrayHasKey('token', $result);
        $this->assertArrayHasKey('invite_url', $result);
        $this->assertArrayHasKey('expires_at', $result);
        $this->assertEquals(40, strlen($result['token']));

        // The invite row persisted as a pending link under the test tenant.
        $this->assertTrue(
            DB::table('group_invites')
                ->where('id', $result['id'])
                ->where('tenant_id', $this->testTenantId)
                ->where('group_id', $groupId)
                ->where('invite_type', 'link')
                ->where('status', GroupInviteService::STATUS_PENDING)
                ->exists()
        );
    }

    public function test_acceptInvite_returns_null_when_token_not_found(): void
    {
        $result = $this->service->acceptInvite('definitely-not-a-real-token', $this->seedUser());

        $this->assertNull($result);
        $errors = $this->service->getErrors();
        $this->assertCount(1, $errors);
        $this->assertEquals('NOT_FOUND', $errors[0]['code']);
    }

    public function test_revokeInvite_returns_true_on_success(): void
    {
        $ownerId = $this->seedUser();
        $groupId = $this->seedGroup($ownerId);

        $inviteId = DB::table('group_invites')->insertGetId([
            'tenant_id'   => $this->testTenantId,
            'group_id'    => $groupId,
            'invited_by'  => $ownerId,
            'invite_type' => 'link',
            'token'       => str_repeat('a', 40),
            'status'      => GroupInviteService::STATUS_PENDING,
            'expires_at'  => now()->addDays(14),
            'created_at'  => now(),
            'updated_at'  => now(),
        ]);

        $result = $this->service->revokeInvite($groupId, $inviteId, $ownerId);

        $this->assertTrue($result);
        $this->assertEmpty($this->service->getErrors());
        $this->assertEquals(
            GroupInviteService::STATUS_REVOKED,
            DB::table('group_invites')->where('id', $inviteId)->value('status')
        );
    }

    public function test_revokeInvite_returns_false_when_invite_not_found(): void
    {
        $ownerId = $this->seedUser();
        $groupId = $this->seedGroup($ownerId);

        // No invite row with id 999999 → update affects 0 rows.
        $result = $this->service->revokeInvite($groupId, 999999, $ownerId);

        $this->assertFalse($result);
        $errors = $this->service->getErrors();
        $this->assertCount(1, $errors);
        $this->assertEquals('NOT_FOUND', $errors[0]['code']);
    }

    public function test_sendEmailInvites_returns_empty_when_user_cannot_invite(): void
    {
        $ownerId = $this->seedUser();
        $outsiderId = $this->seedUser();
        $groupId = $this->seedGroup($ownerId);

        $result = $this->service->sendEmailInvites($groupId, $outsiderId, ['test@example.com']);

        $this->assertEmpty($result);
        $errors = $this->service->getErrors();
        $this->assertCount(1, $errors);
        $this->assertEquals('FORBIDDEN', $errors[0]['code']);
    }

    public function test_email_invite_safeguarding_denial_writes_no_invite(): void
    {
        $ownerId = $this->seedUser();
        $recipientId = $this->seedUser();
        $groupId = $this->seedGroup($ownerId);
        $recipientEmail = (string) DB::table('users')->where('id', $recipientId)->value('email');

        $policy = Mockery::mock(SafeguardingInteractionPolicy::class);
        $policy->shouldReceive('assertLocalContactAllowed')
            ->once()
            ->with($ownerId, $recipientId, $this->testTenantId, 'group_email_invitation')
            ->andThrow(new SafeguardingPolicyException('VETTING_REQUIRED', 'Vetting required'));
        $this->app->instance(SafeguardingInteractionPolicy::class, $policy);

        try {
            $this->service->sendEmailInvites($groupId, $ownerId, [$recipientEmail], 'Must not persist');
            $this->fail('Expected safeguarding denial');
        } catch (SafeguardingPolicyException $e) {
            $this->assertSame('VETTING_REQUIRED', $e->reasonCode);
        }

        $this->assertDatabaseMissing('group_invites', [
            'tenant_id' => $this->testTenantId,
            'group_id' => $groupId,
            'email' => $recipientEmail,
        ]);
    }

    public function test_status_constants_are_defined(): void
    {
        $this->assertEquals('pending', GroupInviteService::STATUS_PENDING);
        $this->assertEquals('accepted', GroupInviteService::STATUS_ACCEPTED);
        $this->assertEquals('expired', GroupInviteService::STATUS_EXPIRED);
        $this->assertEquals('revoked', GroupInviteService::STATUS_REVOKED);
    }
}
