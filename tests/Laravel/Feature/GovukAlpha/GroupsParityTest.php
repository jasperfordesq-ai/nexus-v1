<?php
// Copyright © 2024–2026 Jasper Ford
// SPDX-License-Identifier: AGPL-3.0-or-later
// Author: Jasper Ford
// See NOTICE file for attribution and acknowledgements.

namespace Tests\Laravel\Feature\GovukAlpha;

use App\Core\TenantContext;
use App\Models\User;
use Illuminate\Support\Facades\DB;
use Laravel\Sanctum\Sanctum;
use Tests\Laravel\Feature\GovukAlphaFrontendTest;

/**
 * Accessible (GOV.UK) frontend — Groups parity coverage.
 *
 * Covers the three React-parity gaps added by the GroupsParity trait:
 *   - Invite members (link / email / revoke) — owner/admin only.
 *   - Per-group notification preferences — members + admins.
 *   - Avatar + cover image management — owner/admin only.
 *
 * Extends the same base as GovukAlphaFrontendTest so it inherits the tenant
 * setup, superglobal scrubbing and cache flush. Private helpers are re-declared
 * here (PHP cannot call a parent's private methods).
 */
class GroupsParityTest extends GovukAlphaFrontendTest
{
    private function groupsParityEnableFeature(): void
    {
        $row = DB::table('tenants')->where('id', $this->testTenantId)->value('features');
        $current = $row ? (json_decode($row, true) ?: []) : [];
        $current['groups'] = true;
        DB::table('tenants')->where('id', $this->testTenantId)->update(['features' => json_encode($current)]);
        TenantContext::reset();
        TenantContext::setById($this->testTenantId);
    }

    private function groupsParityUser(array $overrides = []): User
    {
        $user = User::factory()->forTenant($this->testTenantId)->create(array_merge([
            'status' => 'active',
            'is_approved' => true,
        ], $overrides));

        Sanctum::actingAs($user, ['*']);

        return $user;
    }

    private function groupsParityCreateGroup(int $ownerId, array $overrides = []): int
    {
        $groupId = DB::table('groups')->insertGetId(array_merge([
            'tenant_id' => $this->testTenantId,
            'owner_id' => $ownerId,
            'name' => 'Community Gardeners',
            'description' => 'A group for local gardeners.',
            'visibility' => 'public',
            'created_at' => now(),
            'updated_at' => now(),
        ], $overrides));

        // The owner is also an active admin member.
        DB::table('group_members')->insert([
            'tenant_id' => $this->testTenantId,
            'group_id' => $groupId,
            'user_id' => $ownerId,
            'status' => 'active',
            'role' => 'owner',
            'joined_at' => now(),
            'created_at' => now(),
        ]);

        return $groupId;
    }

    private function groupsParityAddMember(int $groupId, int $userId, string $role = 'member', string $status = 'active'): void
    {
        DB::table('group_members')->insert([
            'tenant_id' => $this->testTenantId,
            'group_id' => $groupId,
            'user_id' => $userId,
            'status' => $status,
            'role' => $role,
            'joined_at' => now(),
            'created_at' => now(),
        ]);
    }

    // ================================================================
    // Auth gating
    // ================================================================

    public function test_groups_invite_page_requires_login(): void
    {
        $this->groupsParityEnableFeature();
        $owner = User::factory()->forTenant($this->testTenantId)->create(['status' => 'active', 'is_approved' => true]);
        $groupId = $this->groupsParityCreateGroup($owner->id);

        $resp = $this->get("/{$this->testTenantSlug}/accessible/groups/{$groupId}/invite");

        $resp->assertRedirect("/{$this->testTenantSlug}/accessible/login?status=auth-required");
    }

    public function test_groups_notifications_page_requires_login(): void
    {
        $this->groupsParityEnableFeature();
        $owner = User::factory()->forTenant($this->testTenantId)->create(['status' => 'active', 'is_approved' => true]);
        $groupId = $this->groupsParityCreateGroup($owner->id);

        $resp = $this->get("/{$this->testTenantSlug}/accessible/groups/{$groupId}/notifications");

        $resp->assertRedirect("/{$this->testTenantSlug}/accessible/login?status=auth-required");
    }

    public function test_groups_image_page_requires_login(): void
    {
        $this->groupsParityEnableFeature();
        $owner = User::factory()->forTenant($this->testTenantId)->create(['status' => 'active', 'is_approved' => true]);
        $groupId = $this->groupsParityCreateGroup($owner->id);

        $resp = $this->get("/{$this->testTenantSlug}/accessible/groups/{$groupId}/image");

        $resp->assertRedirect("/{$this->testTenantSlug}/accessible/login?status=auth-required");
    }

    // ================================================================
    // Invite members
    // ================================================================

    public function test_groups_invite_page_renders_for_owner(): void
    {
        $this->groupsParityEnableFeature();
        $owner = $this->groupsParityUser();
        $groupId = $this->groupsParityCreateGroup($owner->id);

        $resp = $this->get("/{$this->testTenantSlug}/accessible/groups/{$groupId}/invite");

        $resp->assertOk();
        $resp->assertSee(__('govuk_alpha_groups.invite.title'));
        $resp->assertSee(__('govuk_alpha_groups.invite.link_heading'));
        $resp->assertSee(__('govuk_alpha_groups.invite.email_heading'));
    }

    public function test_groups_invite_page_forbids_non_admin_member(): void
    {
        $this->groupsParityEnableFeature();
        $owner = User::factory()->forTenant($this->testTenantId)->create(['status' => 'active', 'is_approved' => true]);
        $groupId = $this->groupsParityCreateGroup($owner->id);
        $member = $this->groupsParityUser();
        $this->groupsParityAddMember($groupId, $member->id);

        $resp = $this->get("/{$this->testTenantSlug}/accessible/groups/{$groupId}/invite");

        $resp->assertForbidden();
    }

    public function test_groups_invite_page_unknown_group_returns_404(): void
    {
        $this->groupsParityEnableFeature();
        $this->groupsParityUser();

        $resp = $this->get("/{$this->testTenantSlug}/accessible/groups/99999999/invite");

        $resp->assertNotFound();
    }

    public function test_groups_invite_link_persists(): void
    {
        $this->groupsParityEnableFeature();
        $owner = $this->groupsParityUser();
        $groupId = $this->groupsParityCreateGroup($owner->id);

        $resp = $this->post("/{$this->testTenantSlug}/accessible/groups/{$groupId}/invite/link");

        $resp->assertRedirect("/{$this->testTenantSlug}/accessible/groups/{$groupId}/invite?status=invite-link-created");
        $this->assertDatabaseHas('group_invites', [
            'group_id' => $groupId,
            'invited_by' => $owner->id,
            'invite_type' => 'link',
            'status' => 'pending',
        ]);
    }

    public function test_groups_invite_email_persists(): void
    {
        $this->groupsParityEnableFeature();
        $owner = $this->groupsParityUser();
        $groupId = $this->groupsParityCreateGroup($owner->id);

        $resp = $this->post("/{$this->testTenantSlug}/accessible/groups/{$groupId}/invite/email", [
            'emails' => 'newcomer@example.com',
            'message' => 'Please join us.',
        ]);

        $resp->assertRedirect("/{$this->testTenantSlug}/accessible/groups/{$groupId}/invite?status=invite-emails-sent");
        $this->assertDatabaseHas('group_invites', [
            'group_id' => $groupId,
            'invite_type' => 'email',
            'email' => 'newcomer@example.com',
            'status' => 'pending',
        ]);
    }

    public function test_groups_invite_email_requires_an_address(): void
    {
        $this->groupsParityEnableFeature();
        $owner = $this->groupsParityUser();
        $groupId = $this->groupsParityCreateGroup($owner->id);

        $resp = $this->post("/{$this->testTenantSlug}/accessible/groups/{$groupId}/invite/email", [
            'emails' => '   ',
        ]);

        $resp->assertRedirect("/{$this->testTenantSlug}/accessible/groups/{$groupId}/invite?status=invite-emails-required");
    }

    public function test_groups_invite_revoke_marks_invite_revoked(): void
    {
        $this->groupsParityEnableFeature();
        $owner = $this->groupsParityUser();
        $groupId = $this->groupsParityCreateGroup($owner->id);
        $inviteId = DB::table('group_invites')->insertGetId([
            'tenant_id' => $this->testTenantId,
            'group_id' => $groupId,
            'invited_by' => $owner->id,
            'invite_type' => 'email',
            'email' => 'pending@example.com',
            'token' => 'tok-' . uniqid(),
            'status' => 'pending',
            'expires_at' => now()->addDays(7),
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        $resp = $this->post("/{$this->testTenantSlug}/accessible/groups/{$groupId}/invite/{$inviteId}/revoke");

        $resp->assertRedirect("/{$this->testTenantSlug}/accessible/groups/{$groupId}/invite?status=invite-revoked");
        $this->assertDatabaseHas('group_invites', [
            'id' => $inviteId,
            'status' => 'revoked',
        ]);
    }

    // ================================================================
    // Notification preferences
    // ================================================================

    public function test_groups_notifications_page_renders_for_member(): void
    {
        $this->groupsParityEnableFeature();
        $owner = User::factory()->forTenant($this->testTenantId)->create(['status' => 'active', 'is_approved' => true]);
        $groupId = $this->groupsParityCreateGroup($owner->id);
        $member = $this->groupsParityUser();
        $this->groupsParityAddMember($groupId, $member->id);

        $resp = $this->get("/{$this->testTenantSlug}/accessible/groups/{$groupId}/notifications");

        $resp->assertOk();
        $resp->assertSee(__('govuk_alpha_groups.notifications.title'));
        $resp->assertSee(__('govuk_alpha_groups.notifications.frequency_legend'));
    }

    public function test_groups_notifications_page_forbids_non_member(): void
    {
        $this->groupsParityEnableFeature();
        $owner = User::factory()->forTenant($this->testTenantId)->create(['status' => 'active', 'is_approved' => true]);
        $groupId = $this->groupsParityCreateGroup($owner->id);
        // A logged-in user who is neither a member nor an admin.
        $this->groupsParityUser();

        $resp = $this->get("/{$this->testTenantSlug}/accessible/groups/{$groupId}/notifications");

        $resp->assertForbidden();
    }

    public function test_groups_notifications_update_persists(): void
    {
        $this->groupsParityEnableFeature();
        $owner = User::factory()->forTenant($this->testTenantId)->create(['status' => 'active', 'is_approved' => true]);
        $groupId = $this->groupsParityCreateGroup($owner->id);
        $member = $this->groupsParityUser();
        $this->groupsParityAddMember($groupId, $member->id);

        $resp = $this->post("/{$this->testTenantSlug}/accessible/groups/{$groupId}/notifications", [
            'frequency' => 'muted',
            'email_enabled' => '1',
            // push_enabled intentionally omitted → should persist as false.
        ]);

        $resp->assertRedirect("/{$this->testTenantSlug}/accessible/groups/{$groupId}/notifications?status=prefs-saved");
        $this->assertDatabaseHas('group_notification_preferences', [
            'group_id' => $groupId,
            'user_id' => $member->id,
            'frequency' => 'muted',
            'email_enabled' => 1,
            'push_enabled' => 0,
        ]);
    }

    // ================================================================
    // Avatar + cover images
    // ================================================================

    public function test_groups_image_page_renders_for_owner(): void
    {
        $this->groupsParityEnableFeature();
        $owner = $this->groupsParityUser();
        $groupId = $this->groupsParityCreateGroup($owner->id);

        $resp = $this->get("/{$this->testTenantSlug}/accessible/groups/{$groupId}/image");

        $resp->assertOk();
        $resp->assertSee(__('govuk_alpha_groups.image.avatar_heading'));
        $resp->assertSee(__('govuk_alpha_groups.image.cover_heading'));
    }

    public function test_groups_image_page_forbids_non_admin_member(): void
    {
        $this->groupsParityEnableFeature();
        $owner = User::factory()->forTenant($this->testTenantId)->create(['status' => 'active', 'is_approved' => true]);
        $groupId = $this->groupsParityCreateGroup($owner->id);
        $member = $this->groupsParityUser();
        $this->groupsParityAddMember($groupId, $member->id);

        $resp = $this->get("/{$this->testTenantSlug}/accessible/groups/{$groupId}/image");

        $resp->assertForbidden();
    }

    public function test_groups_image_update_rejects_missing_file(): void
    {
        $this->groupsParityEnableFeature();
        $owner = $this->groupsParityUser();
        $groupId = $this->groupsParityCreateGroup($owner->id);

        $resp = $this->post("/{$this->testTenantSlug}/accessible/groups/{$groupId}/image", [
            'type' => 'avatar',
        ]);

        $resp->assertRedirect("/{$this->testTenantSlug}/accessible/groups/{$groupId}/image?status=image-missing");
    }

    // ================================================================
    // Subgroups (read-only list on group detail; data from getById)
    // ================================================================

    public function test_groups_detail_lists_public_subgroups(): void
    {
        $this->groupsParityEnableFeature();
        $owner = $this->groupsParityUser();
        $parentId = $this->groupsParityCreateGroup($owner->id, ['name' => 'Allotment Network']);
        // A public child group nested under the parent.
        $childId = $this->groupsParityCreateGroup($owner->id, [
            'name' => 'Allotment Network — North Plot',
            'visibility' => 'public',
            'parent_id' => $parentId,
        ]);

        $resp = $this->get("/{$this->testTenantSlug}/accessible/groups/{$parentId}");

        $resp->assertOk();
        $resp->assertSee(__('govuk_alpha_groups.subgroups.heading'));
        $resp->assertSee('Allotment Network — North Plot', false);
        $resp->assertSee(route('govuk-alpha.groups.show', ['tenantSlug' => $this->testTenantSlug, 'id' => $childId]), false);
    }

    public function test_groups_detail_hides_subgroups_heading_when_none(): void
    {
        $this->groupsParityEnableFeature();
        $owner = $this->groupsParityUser();
        $parentId = $this->groupsParityCreateGroup($owner->id, ['name' => 'Standalone Group']);

        $resp = $this->get("/{$this->testTenantSlug}/accessible/groups/{$parentId}");

        $resp->assertOk();
        $resp->assertDontSee(__('govuk_alpha_groups.subgroups.heading'));
    }
}
