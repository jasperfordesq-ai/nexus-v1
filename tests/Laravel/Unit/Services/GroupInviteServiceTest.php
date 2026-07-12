<?php
// Copyright © 2024–2026 Jasper Ford
// SPDX-License-Identifier: AGPL-3.0-or-later
// Author: Jasper Ford
// See NOTICE file for attribution and acknowledgements.

namespace Tests\Laravel\Unit\Services;

use App\Core\TenantContext;
use App\Exceptions\SafeguardingPolicyException;
use App\Models\User;
use App\Services\GroupConfigurationService;
use App\Services\GroupAuditService;
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
        $this->assertArrayHasKey('invite_url', $result);
        $this->assertArrayHasKey('expires_at', $result);
        $token = basename((string) parse_url($result['invite_url'], PHP_URL_PATH));
        $this->assertEquals(40, strlen($token));

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
        $audit = DB::table('group_audit_log')
            ->where('group_id', $groupId)
            ->where('action', GroupAuditService::ACTION_INVITE_REVOKED)
            ->sole();
        $this->assertSame($ownerId, (int) $audit->user_id);
        $this->assertStringNotContainsString(str_repeat('a', 40), (string) $audit->details);
        $details = json_decode((string) $audit->details, true, 512, JSON_THROW_ON_ERROR);
        $this->assertSame($inviteId, (int) $details['invite_id']);
        $this->assertArrayNotHasKey('token', $details);
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

    public function test_email_invite_is_bound_to_the_recipient_account(): void
    {
        $ownerId = $this->seedUser();
        $recipientId = $this->seedUser();
        $wrongUserId = $this->seedUser();
        $groupId = $this->seedGroup($ownerId);
        $email = (string) DB::table('users')->where('id', $recipientId)->value('email');
        $token = str_repeat('b', 40);
        $this->seedInvite($groupId, $ownerId, $token, 'email', $email);

        $this->assertNull($this->service->acceptInvite($token, $wrongUserId));
        $this->assertSame('EMAIL_MISMATCH', $this->service->getErrors()[0]['code']);
        $this->assertDatabaseMissing('group_members', [
            'tenant_id' => $this->testTenantId,
            'group_id' => $groupId,
            'user_id' => $wrongUserId,
        ]);
        $this->assertDatabaseHas('group_invites', [
            'tenant_id' => $this->testTenantId,
            'token' => $token,
            'status' => GroupInviteService::STATUS_PENDING,
        ]);
    }

    public function test_email_acceptance_is_atomic_idempotent_and_reconciles_cached_count(): void
    {
        $ownerId = $this->seedUser();
        $recipientId = $this->seedUser();
        $groupId = $this->seedGroup($ownerId);
        DB::table('groups')->where('id', $groupId)->update(['cached_member_count' => 99]);
        $email = (string) DB::table('users')->where('id', $recipientId)->value('email');
        $token = str_repeat('c', 40);
        $inviteId = $this->seedInvite($groupId, $ownerId, $token, 'email', $email);
        $this->assertSame($this->testTenantId, (int) TenantContext::getId());
        $this->assertTrue(DB::table('group_invites')
            ->where('tenant_id', $this->testTenantId)
            ->where('token', $token)
            ->exists());
        $this->assertNotNull(DB::transaction(fn () => DB::table('group_invites')
            ->where('tenant_id', $this->testTenantId)
            ->where('token', $token)
            ->lockForUpdate()
            ->first()));

        $first = $this->service->acceptInvite($token, $recipientId);
        $this->assertNotNull($first, json_encode($this->service->getErrors(), JSON_THROW_ON_ERROR));
        $this->assertSame($this->testTenantId, (int) TenantContext::getId());
        $this->assertDatabaseHas('group_invites', ['id' => $inviteId, 'token' => $token]);
        $second = $this->service->acceptInvite($token, $recipientId);
        $this->assertNotNull($second, json_encode($this->service->getErrors(), JSON_THROW_ON_ERROR));

        $this->assertSame('joined', $first['action']);
        $this->assertSame('already_member', $second['action']);
        $this->assertDatabaseHas('group_invites', [
            'id' => $inviteId,
            'status' => GroupInviteService::STATUS_ACCEPTED,
            'accepted_by' => $recipientId,
        ]);
        $this->assertSame(1, DB::table('group_members')
            ->where('tenant_id', $this->testTenantId)
            ->where('group_id', $groupId)
            ->where('user_id', $recipientId)
            ->where('status', 'active')
            ->count());
        $this->assertSame(1, (int) DB::table('groups')->where('id', $groupId)->value('cached_member_count'));
        $audits = DB::table('group_audit_log')
            ->where('group_id', $groupId)
            ->where('action', GroupAuditService::ACTION_MEMBER_JOINED)
            ->get();
        $this->assertCount(1, $audits, 'Idempotent invite acceptance must not duplicate the member audit.');
        $this->assertSame($recipientId, (int) $audits->first()->user_id);
        $this->assertStringNotContainsString($token, (string) $audits->first()->details);
        $details = json_decode((string) $audits->first()->details, true, 512, JSON_THROW_ON_ERROR);
        $this->assertSame('invite_acceptance', $details['source']);
        $this->assertSame($inviteId, (int) $details['invite_id']);
        $this->assertSame($recipientId, (int) $details['target_user_id']);
    }

    public function test_acceptance_enforces_group_and_user_capacity(): void
    {
        $ownerId = $this->seedUser();
        $existingMemberId = $this->seedUser();
        $recipientId = $this->seedUser();
        $groupId = $this->seedGroup($ownerId);
        DB::table('group_members')->insert([
            'tenant_id' => $this->testTenantId,
            'group_id' => $groupId,
            'user_id' => $existingMemberId,
            'role' => 'member',
            'status' => 'active',
            'created_at' => now(),
            'updated_at' => now(),
        ]);
        $token = str_repeat('d', 40);
        $this->seedInvite($groupId, $ownerId, $token);

        GroupConfigurationService::set(GroupConfigurationService::CONFIG_MAX_MEMBERS_PER_GROUP, 1);
        $this->assertNull($this->service->acceptInvite($token, $recipientId));
        $this->assertSame('CAPACITY_FULL', $this->service->getErrors()[0]['code']);
        $this->assertSame(1, (int) DB::table('groups')->where('id', $groupId)->value('cached_member_count'));

        GroupConfigurationService::set(GroupConfigurationService::CONFIG_MAX_MEMBERS_PER_GROUP, 500);
        $otherGroupId = $this->seedGroup($ownerId);
        DB::table('group_members')->insert([
            'tenant_id' => $this->testTenantId,
            'group_id' => $otherGroupId,
            'user_id' => $recipientId,
            'role' => 'member',
            'status' => 'active',
            'created_at' => now(),
            'updated_at' => now(),
        ]);
        GroupConfigurationService::set(GroupConfigurationService::CONFIG_MAX_GROUPS_PER_USER, 1);
        $this->assertNull($this->service->acceptInvite($token, $recipientId));
        $this->assertSame('MEMBERSHIP_LIMIT_REACHED', $this->service->getErrors()[0]['code']);
        GroupConfigurationService::set(GroupConfigurationService::CONFIG_MAX_GROUPS_PER_USER, 10);
    }

    public function test_revoked_expired_and_inactive_parent_states_are_rejected(): void
    {
        $ownerId = $this->seedUser();
        $recipientId = $this->seedUser();
        $groupId = $this->seedGroup($ownerId);

        $revoked = str_repeat('e', 40);
        $this->seedInvite($groupId, $ownerId, $revoked, 'link', null, GroupInviteService::STATUS_REVOKED);
        $this->assertNull($this->service->acceptInvite($revoked, $recipientId));
        $this->assertSame('REVOKED', $this->service->getErrors()[0]['code']);

        $expired = str_repeat('f', 40);
        $this->seedInvite($groupId, $ownerId, $expired, 'link', null, GroupInviteService::STATUS_PENDING, now()->subMinute());
        $this->assertNull($this->service->acceptInvite($expired, $recipientId));
        $this->assertSame('EXPIRED', $this->service->getErrors()[0]['code']);
        $this->assertDatabaseHas('group_invites', ['token' => $expired, 'status' => GroupInviteService::STATUS_EXPIRED]);

        $inactive = str_repeat('g', 40);
        $this->seedInvite($groupId, $ownerId, $inactive);
        DB::table('groups')->where('id', $groupId)->update(['status' => 'archived', 'is_active' => 0]);
        $this->assertNull($this->service->acceptInvite($inactive, $recipientId));
        $this->assertSame('GROUP_UNAVAILABLE', $this->service->getErrors()[0]['code']);
    }

    public function test_token_redemption_is_tenant_scoped(): void
    {
        $recipientId = $this->seedUser();
        $foreignTenantId = $this->seedForeignTenant();
        $foreignOwner = User::factory()->forTenant($foreignTenantId)->create([
            'status' => 'active',
            'is_approved' => true,
        ]);
        TenantContext::setById($foreignTenantId);
        $foreignGroupId = DB::table('groups')->insertGetId([
            'tenant_id' => $foreignTenantId,
            'owner_id' => $foreignOwner->id,
            'name' => 'Foreign invite group',
            'visibility' => 'public',
            'status' => 'active',
            'is_active' => 1,
            'created_at' => now(),
            'updated_at' => now(),
        ]);
        $token = str_repeat('h', 40);
        DB::table('group_invites')->insert([
            'tenant_id' => $foreignTenantId,
            'group_id' => $foreignGroupId,
            'invited_by' => $foreignOwner->id,
            'invite_type' => 'link',
            'token' => $token,
            'status' => GroupInviteService::STATUS_PENDING,
            'expires_at' => now()->addDay(),
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        TenantContext::setById($this->testTenantId);
        $this->assertNull($this->service->acceptInvite($token, $recipientId));
        $this->assertSame('NOT_FOUND', $this->service->getErrors()[0]['code']);
    }

    public function test_pending_invite_limit_is_enforced_under_the_group_parent(): void
    {
        $ownerId = $this->seedUser();
        $groupId = $this->seedGroup($ownerId);
        foreach (range(1, GroupInviteService::MAX_PENDING_INVITES) as $index) {
            $this->seedInvite($groupId, $ownerId, substr(hash('sha256', "invite-{$index}"), 0, 40));
        }

        $this->assertNull($this->service->createLink($groupId, $ownerId));
        $this->assertSame('INVITE_LIMIT_REACHED', $this->service->getErrors()[0]['code']);
    }

    private function seedInvite(
        int $groupId,
        int $ownerId,
        string $token,
        string $type = 'link',
        ?string $email = null,
        string $status = GroupInviteService::STATUS_PENDING,
        mixed $expiresAt = null,
    ): int {
        return (int) DB::table('group_invites')->insertGetId([
            'tenant_id' => $this->testTenantId,
            'group_id' => $groupId,
            'invited_by' => $ownerId,
            'invite_type' => $type,
            'email' => $email,
            'token' => $token,
            'status' => $status,
            'expires_at' => $expiresAt ?? now()->addDay(),
            'created_at' => now(),
            'updated_at' => now(),
        ]);
    }

    private function seedForeignTenant(): int
    {
        $tenant = (array) DB::table('tenants')->where('id', $this->testTenantId)->first();
        unset($tenant['id']);
        $tenant['name'] = 'Foreign Group Invite Tenant';
        $tenant['slug'] = 'foreign-group-invite-' . bin2hex(random_bytes(4));
        $tenant['domain'] = null;
        $tenant['created_at'] = now();
        $tenant['updated_at'] = now();

        return (int) DB::table('tenants')->insertGetId($tenant);
    }
}
