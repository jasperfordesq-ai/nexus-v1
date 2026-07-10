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
 * Accessible (GOV.UK) frontend — Group Announcements parity coverage.
 *
 * Covers the announcements management feature added to GroupsParity:
 *   - List page visible to members.
 *   - Admins see create form and action buttons.
 *   - Create, edit, delete, pin/unpin (admin only).
 *   - 403 for non-admins attempting mutations.
 *   - Auth gating redirects unauthenticated requests.
 */
class GroupsAnnouncementsParityTest extends GovukAlphaFrontendTest
{
    // ================================================================
    // Private helpers (redeclared per trait — PHP cannot call parent private)
    // ================================================================

    private function groupsAnnEnableFeature(): void
    {
        $row     = DB::table('tenants')->where('id', $this->testTenantId)->value('features');
        $current = $row ? (json_decode($row, true) ?: []) : [];
        $current['groups'] = true;
        DB::table('tenants')->where('id', $this->testTenantId)->update(['features' => json_encode($current)]);
        TenantContext::reset();
        TenantContext::setById($this->testTenantId);
    }

    private function groupsAnnUser(array $overrides = []): User
    {
        $user = User::factory()->forTenant($this->testTenantId)->create(array_merge([
            'status'      => 'active',
            'is_approved' => true,
        ], $overrides));
        Sanctum::actingAs($user, ['*']);

        return $user;
    }

    private function groupsAnnCreateGroup(int $ownerId, array $overrides = []): int
    {
        $groupId = DB::table('groups')->insertGetId(array_merge([
            'tenant_id'   => $this->testTenantId,
            'owner_id'    => $ownerId,
            'name'        => 'Announcements Test Group',
            'description' => 'A group for testing announcements.',
            'visibility'  => 'public',
            'created_at'  => now(),
            'updated_at'  => now(),
        ], $overrides));

        // Owner is also an active admin member.
        DB::table('group_members')->insert([
            'tenant_id'  => $this->testTenantId,
            'group_id'   => $groupId,
            'user_id'    => $ownerId,
            'status'     => 'active',
            'role'       => 'owner',
            'joined_at'  => now(),
            'created_at' => now(),
        ]);

        return $groupId;
    }

    private function groupsAnnAddMember(int $groupId, int $userId, string $role = 'member', string $status = 'active'): void
    {
        DB::table('group_members')->insert([
            'tenant_id'  => $this->testTenantId,
            'group_id'   => $groupId,
            'user_id'    => $userId,
            'status'     => $status,
            'role'       => $role,
            'joined_at'  => now(),
            'created_at' => now(),
        ]);
    }

    private function groupsAnnInsert(int $groupId, int $createdBy, array $overrides = []): int
    {
        return DB::table('group_announcements')->insertGetId(array_merge([
            'tenant_id'  => $this->testTenantId,
            'group_id'   => $groupId,
            'created_by' => $createdBy,
            'title'      => 'Test Announcement',
            'content'    => 'This is a test announcement.',
            'is_pinned'  => 0,
            'priority'   => 0,
            'created_at' => now(),
            'updated_at' => now(),
        ], $overrides));
    }

    // ================================================================
    // Auth gating
    // ================================================================

    public function test_groups_announcements_list_requires_login(): void
    {
        $this->groupsAnnEnableFeature();
        $owner   = User::factory()->forTenant($this->testTenantId)->create(['status' => 'active', 'is_approved' => true]);
        $groupId = $this->groupsAnnCreateGroup($owner->id);

        $resp = $this->get("/{$this->testTenantSlug}/accessible/groups/{$groupId}/announcements");

        $resp->assertRedirect();
        $this->assertStringContainsString('status=auth-required', $resp->headers->get('location') ?? '');
    }

    // ================================================================
    // List page
    // ================================================================

    public function test_groups_announcements_list_renders_for_member(): void
    {
        $this->groupsAnnEnableFeature();
        $owner   = $this->groupsAnnUser();
        $groupId = $this->groupsAnnCreateGroup($owner->id);

        $this->groupsAnnInsert($groupId, $owner->id, ['title' => 'Welcome post', 'content' => 'Hello everyone.']);

        $resp = $this->get("/{$this->testTenantSlug}/accessible/groups/{$groupId}/announcements");

        $resp->assertOk();
        $resp->assertSee('Welcome post');
        $resp->assertSee('Hello everyone.');
    }

    public function test_groups_announcements_list_shows_pinned_tag(): void
    {
        $this->groupsAnnEnableFeature();
        $owner   = $this->groupsAnnUser();
        $groupId = $this->groupsAnnCreateGroup($owner->id);

        $this->groupsAnnInsert($groupId, $owner->id, ['title' => 'Pinned post', 'is_pinned' => 1]);

        $resp = $this->get("/{$this->testTenantSlug}/accessible/groups/{$groupId}/announcements");

        $resp->assertOk();
        $resp->assertSee('Pinned post');
    }

    public function test_groups_announcements_admin_sees_create_form(): void
    {
        $this->groupsAnnEnableFeature();
        $owner   = $this->groupsAnnUser();
        $groupId = $this->groupsAnnCreateGroup($owner->id);

        $resp = $this->get("/{$this->testTenantSlug}/accessible/groups/{$groupId}/announcements");

        $resp->assertOk();
        // Create form action present
        $resp->assertSee("/{$this->testTenantSlug}/accessible/groups/{$groupId}/announcements", false);
        $resp->assertSee('ann-title');
    }

    public function test_groups_announcements_non_admin_member_cannot_see_create_form(): void
    {
        $this->groupsAnnEnableFeature();
        $owner  = User::factory()->forTenant($this->testTenantId)->create(['status' => 'active', 'is_approved' => true]);
        $groupId = $this->groupsAnnCreateGroup($owner->id);

        $member = $this->groupsAnnUser();
        $this->groupsAnnAddMember($groupId, $member->id, 'member');

        $resp = $this->get("/{$this->testTenantSlug}/accessible/groups/{$groupId}/announcements");

        $resp->assertOk();
        // Create form should NOT appear for plain members
        $resp->assertDontSee('ann-title');
    }

    public function test_groups_announcements_non_member_gets_403(): void
    {
        $this->groupsAnnEnableFeature();
        $owner   = User::factory()->forTenant($this->testTenantId)->create(['status' => 'active', 'is_approved' => true]);
        $groupId = $this->groupsAnnCreateGroup($owner->id);

        $this->groupsAnnUser(); // authenticated but not a member

        $resp = $this->get("/{$this->testTenantSlug}/accessible/groups/{$groupId}/announcements");
        $resp->assertForbidden();
    }

    // ================================================================
    // Create
    // ================================================================

    public function test_groups_announcements_create_persists_and_redirects(): void
    {
        $this->groupsAnnEnableFeature();
        $owner   = $this->groupsAnnUser();
        $groupId = $this->groupsAnnCreateGroup($owner->id);

        $resp = $this->post("/{$this->testTenantSlug}/accessible/groups/{$groupId}/announcements", [
            '_token'  => csrf_token(),
            'title'   => 'New Announcement',
            'content' => 'Details here.',
        ]);

        $resp->assertRedirect();
        $this->assertStringContainsString('status=ann-created', $resp->headers->get('location') ?? '');
        $this->assertDatabaseHas('group_announcements', [
            'group_id'  => $groupId,
            'tenant_id' => $this->testTenantId,
            'title'     => 'New Announcement',
            'content'   => 'Details here.',
        ]);
    }

    public function test_groups_announcements_create_rejects_non_admin(): void
    {
        $this->groupsAnnEnableFeature();
        $owner   = User::factory()->forTenant($this->testTenantId)->create(['status' => 'active', 'is_approved' => true]);
        $groupId = $this->groupsAnnCreateGroup($owner->id);

        $member = $this->groupsAnnUser();
        $this->groupsAnnAddMember($groupId, $member->id, 'member');

        $resp = $this->post("/{$this->testTenantSlug}/accessible/groups/{$groupId}/announcements", [
            '_token'  => csrf_token(),
            'title'   => 'Sneaky',
            'content' => 'I should not be allowed.',
        ]);

        $resp->assertForbidden();
        $this->assertDatabaseMissing('group_announcements', [
            'group_id' => $groupId,
            'title'    => 'Sneaky',
        ]);
    }

    public function test_groups_announcements_create_validates_missing_title(): void
    {
        $this->groupsAnnEnableFeature();
        $owner   = $this->groupsAnnUser();
        $groupId = $this->groupsAnnCreateGroup($owner->id);

        $resp = $this->post("/{$this->testTenantSlug}/accessible/groups/{$groupId}/announcements", [
            '_token'  => csrf_token(),
            'title'   => '',
            'content' => 'Content without title.',
        ]);

        $resp->assertRedirect();
        $this->assertStringContainsString('status=ann-title-required', $resp->headers->get('location') ?? '');
    }

    // ================================================================
    // Edit page
    // ================================================================

    public function test_groups_announcements_edit_page_renders_for_admin(): void
    {
        $this->groupsAnnEnableFeature();
        $owner   = $this->groupsAnnUser();
        $groupId = $this->groupsAnnCreateGroup($owner->id);
        $annId   = $this->groupsAnnInsert($groupId, $owner->id, ['title' => 'Editable Post']);

        $resp = $this->get("/{$this->testTenantSlug}/accessible/groups/{$groupId}/announcements/{$annId}/edit");

        $resp->assertOk();
        $resp->assertSee('Editable Post');
    }

    public function test_groups_announcements_edit_page_403_for_non_admin(): void
    {
        $this->groupsAnnEnableFeature();
        $owner   = User::factory()->forTenant($this->testTenantId)->create(['status' => 'active', 'is_approved' => true]);
        $groupId = $this->groupsAnnCreateGroup($owner->id);
        $annId   = $this->groupsAnnInsert($groupId, $owner->id);

        $member = $this->groupsAnnUser();
        $this->groupsAnnAddMember($groupId, $member->id, 'member');

        $resp = $this->get("/{$this->testTenantSlug}/accessible/groups/{$groupId}/announcements/{$annId}/edit");
        $resp->assertForbidden();
    }

    // ================================================================
    // Update
    // ================================================================

    public function test_groups_announcements_update_persists_changes(): void
    {
        $this->groupsAnnEnableFeature();
        $owner   = $this->groupsAnnUser();
        $groupId = $this->groupsAnnCreateGroup($owner->id);
        $annId   = $this->groupsAnnInsert($groupId, $owner->id, ['title' => 'Old Title', 'content' => 'Old content.']);

        $resp = $this->post("/{$this->testTenantSlug}/accessible/groups/{$groupId}/announcements/{$annId}/edit", [
            '_token'  => csrf_token(),
            'title'   => 'Updated Title',
            'content' => 'Updated content.',
        ]);

        $resp->assertRedirect();
        $this->assertStringContainsString('status=ann-updated', $resp->headers->get('location') ?? '');
        $this->assertDatabaseHas('group_announcements', [
            'id'      => $annId,
            'title'   => 'Updated Title',
            'content' => 'Updated content.',
        ]);
    }

    // ================================================================
    // Delete
    // ================================================================

    public function test_groups_announcements_delete_removes_record(): void
    {
        $this->groupsAnnEnableFeature();
        $owner   = $this->groupsAnnUser();
        $groupId = $this->groupsAnnCreateGroup($owner->id);
        $annId   = $this->groupsAnnInsert($groupId, $owner->id, ['title' => 'To Be Deleted']);

        $resp = $this->post("/{$this->testTenantSlug}/accessible/groups/{$groupId}/announcements/{$annId}/delete", [
            '_token' => csrf_token(),
        ]);

        $resp->assertRedirect();
        $this->assertStringContainsString('status=ann-deleted', $resp->headers->get('location') ?? '');
        $this->assertDatabaseMissing('group_announcements', [
            'id'       => $annId,
            'group_id' => $groupId,
        ]);
    }

    public function test_groups_announcements_delete_rejects_non_admin(): void
    {
        $this->groupsAnnEnableFeature();
        $owner   = User::factory()->forTenant($this->testTenantId)->create(['status' => 'active', 'is_approved' => true]);
        $groupId = $this->groupsAnnCreateGroup($owner->id);
        $annId   = $this->groupsAnnInsert($groupId, $owner->id, ['title' => 'Protected']);

        $member = $this->groupsAnnUser();
        $this->groupsAnnAddMember($groupId, $member->id, 'member');

        $resp = $this->post("/{$this->testTenantSlug}/accessible/groups/{$groupId}/announcements/{$annId}/delete", [
            '_token' => csrf_token(),
        ]);

        $resp->assertForbidden();
        $this->assertDatabaseHas('group_announcements', ['id' => $annId]);
    }

    // ================================================================
    // Pin / Unpin
    // ================================================================

    public function test_groups_announcements_pin_sets_is_pinned(): void
    {
        $this->groupsAnnEnableFeature();
        $owner   = $this->groupsAnnUser();
        $groupId = $this->groupsAnnCreateGroup($owner->id);
        $annId   = $this->groupsAnnInsert($groupId, $owner->id, ['is_pinned' => 0]);

        $resp = $this->post("/{$this->testTenantSlug}/accessible/groups/{$groupId}/announcements/{$annId}/pin", [
            '_token'    => csrf_token(),
            'is_pinned' => '1',
        ]);

        $resp->assertRedirect();
        $this->assertStringContainsString('status=ann-pinned', $resp->headers->get('location') ?? '');
        $this->assertDatabaseHas('group_announcements', [
            'id'       => $annId,
            'is_pinned'=> 1,
        ]);
    }

    public function test_groups_announcements_unpin_clears_is_pinned(): void
    {
        $this->groupsAnnEnableFeature();
        $owner   = $this->groupsAnnUser();
        $groupId = $this->groupsAnnCreateGroup($owner->id);
        $annId   = $this->groupsAnnInsert($groupId, $owner->id, ['is_pinned' => 1]);

        $resp = $this->post("/{$this->testTenantSlug}/accessible/groups/{$groupId}/announcements/{$annId}/pin", [
            '_token'    => csrf_token(),
            'is_pinned' => '0',
        ]);

        $resp->assertRedirect();
        $this->assertStringContainsString('status=ann-unpinned', $resp->headers->get('location') ?? '');
        $this->assertDatabaseHas('group_announcements', [
            'id'       => $annId,
            'is_pinned'=> 0,
        ]);
    }

    public function test_groups_announcements_pin_rejects_non_admin(): void
    {
        $this->groupsAnnEnableFeature();
        $owner   = User::factory()->forTenant($this->testTenantId)->create(['status' => 'active', 'is_approved' => true]);
        $groupId = $this->groupsAnnCreateGroup($owner->id);
        $annId   = $this->groupsAnnInsert($groupId, $owner->id, ['is_pinned' => 0]);

        $member = $this->groupsAnnUser();
        $this->groupsAnnAddMember($groupId, $member->id, 'member');

        $resp = $this->post("/{$this->testTenantSlug}/accessible/groups/{$groupId}/announcements/{$annId}/pin", [
            '_token'    => csrf_token(),
            'is_pinned' => '1',
        ]);

        $resp->assertForbidden();
        $this->assertDatabaseHas('group_announcements', [
            'id'       => $annId,
            'is_pinned'=> 0,
        ]);
    }
}
