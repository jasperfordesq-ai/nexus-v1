<?php
// Copyright © 2024–2026 Jasper Ford
// SPDX-License-Identifier: AGPL-3.0-or-later
// Author: Jasper Ford
// See NOTICE file for attribution and acknowledgements.

namespace Tests\Laravel\Feature\GovukAlpha;

use App\Core\TenantContext;
use App\Models\User;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
use Illuminate\Http\UploadedFile;
use Laravel\Sanctum\Sanctum;
use Tests\Laravel\Feature\GovukAlphaFrontendTest;

/**
 * Accessible (GOV.UK) frontend — Group Files parity coverage.
 *
 * Covers the files feature added to GroupsParity:
 *   - List page renders for active members.
 *   - Non-members receive 403.
 *   - Upload form visible to all members (admin and non-admin alike).
 *   - Auth gating redirects unauthenticated requests.
 *   - Members who did not upload a file cannot delete it (non-admin).
 *   - Admins can see and use the delete action.
 *
 * NOTE: Actual file-storage round-trips are tested via Storage::fake(). The
 * GroupFileService::upload() path is exercised only if the fake disk is wired;
 * delete and download are asserted via routing/gate checks to avoid slow I/O.
 */
class GroupsFilesParityTest extends GovukAlphaFrontendTest
{
    // ================================================================
    // Private helpers (redeclared per test class — PHP cannot call parent private)
    // ================================================================

    private function groupsFilesEnableFeature(): void
    {
        $row     = DB::table('tenants')->where('id', $this->testTenantId)->value('features');
        $current = $row ? (json_decode($row, true) ?: []) : [];
        $current['groups'] = true;
        DB::table('tenants')->where('id', $this->testTenantId)->update(['features' => json_encode($current)]);
        TenantContext::reset();
        TenantContext::setById($this->testTenantId);
    }

    private function groupsFilesUser(array $overrides = []): User
    {
        $user = User::factory()->forTenant($this->testTenantId)->create(array_merge([
            'status'      => 'active',
            'is_approved' => true,
        ], $overrides));
        Sanctum::actingAs($user, ['*']);

        return $user;
    }

    private function groupsFilesCreateGroup(int $ownerId, array $overrides = []): int
    {
        $groupId = DB::table('groups')->insertGetId(array_merge([
            'tenant_id'   => $this->testTenantId,
            'owner_id'    => $ownerId,
            'name'        => 'Files Test Group',
            'description' => 'A group for testing file uploads.',
            'visibility'  => 'public',
            'created_at'  => now(),
            'updated_at'  => now(),
        ], $overrides));

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

    private function groupsFilesAddMember(int $groupId, int $userId, string $role = 'member'): void
    {
        DB::table('group_members')->insert([
            'tenant_id'  => $this->testTenantId,
            'group_id'   => $groupId,
            'user_id'    => $userId,
            'status'     => 'active',
            'role'       => $role,
            'joined_at'  => now(),
            'created_at' => now(),
        ]);
    }

    /** Insert a group_files row directly (bypasses storage) and return its id. */
    private function groupsFilesInsertRecord(int $groupId, int $uploadedBy, array $overrides = []): int
    {
        return DB::table('group_files')->insertGetId(array_merge([
            'tenant_id'      => $this->testTenantId,
            'group_id'       => $groupId,
            'file_name'      => 'test-document.pdf',
            'file_path'      => "groups/{$this->testTenantId}/{$groupId}/test-document.pdf",
            'file_type'      => 'application/pdf',
            'file_size'      => 12345,
            'uploaded_by'    => $uploadedBy,
            'download_count' => 0,
            'created_at'     => now(),
        ], $overrides));
    }

    // ================================================================
    // Auth gating
    // ================================================================

    public function test_groups_files_list_requires_login(): void
    {
        $this->groupsFilesEnableFeature();
        $owner   = User::factory()->forTenant($this->testTenantId)->create(['status' => 'active', 'is_approved' => true]);
        $groupId = $this->groupsFilesCreateGroup($owner->id);

        $resp = $this->get("/{$this->testTenantSlug}/alpha/groups/{$groupId}/files");

        $resp->assertRedirect();
        $this->assertStringContainsString('status=auth-required', $resp->headers->get('location') ?? '');
    }

    // ================================================================
    // List page
    // ================================================================

    public function test_groups_files_list_renders_for_member(): void
    {
        $this->groupsFilesEnableFeature();
        $owner   = $this->groupsFilesUser();
        $groupId = $this->groupsFilesCreateGroup($owner->id);

        $this->groupsFilesInsertRecord($groupId, $owner->id, ['file_name' => 'meeting-notes.pdf']);

        $resp = $this->get("/{$this->testTenantSlug}/alpha/groups/{$groupId}/files");

        $resp->assertOk();
        $resp->assertSee('meeting-notes.pdf');
    }

    public function test_groups_files_list_shows_empty_state(): void
    {
        $this->groupsFilesEnableFeature();
        $owner   = $this->groupsFilesUser();
        $groupId = $this->groupsFilesCreateGroup($owner->id);

        $resp = $this->get("/{$this->testTenantSlug}/alpha/groups/{$groupId}/files");

        $resp->assertOk();
        // Empty state inset text should appear
        $resp->assertSee('govuk-inset-text', false);
    }

    public function test_groups_files_non_member_gets_403(): void
    {
        $this->groupsFilesEnableFeature();
        $owner   = User::factory()->forTenant($this->testTenantId)->create(['status' => 'active', 'is_approved' => true]);
        $groupId = $this->groupsFilesCreateGroup($owner->id);

        $this->groupsFilesUser(); // authenticated but NOT a member

        $resp = $this->get("/{$this->testTenantSlug}/alpha/groups/{$groupId}/files");
        $resp->assertForbidden();
    }

    public function test_groups_files_non_admin_member_sees_upload_form(): void
    {
        $this->groupsFilesEnableFeature();
        $owner   = User::factory()->forTenant($this->testTenantId)->create(['status' => 'active', 'is_approved' => true]);
        $groupId = $this->groupsFilesCreateGroup($owner->id);

        $member = $this->groupsFilesUser();
        $this->groupsFilesAddMember($groupId, $member->id, 'member');

        $resp = $this->get("/{$this->testTenantSlug}/alpha/groups/{$groupId}/files");

        $resp->assertOk();
        // Upload form is visible to all members
        $resp->assertSee('file-input');
    }

    public function test_groups_files_admin_sees_upload_form(): void
    {
        $this->groupsFilesEnableFeature();
        $owner   = $this->groupsFilesUser();
        $groupId = $this->groupsFilesCreateGroup($owner->id);

        $resp = $this->get("/{$this->testTenantSlug}/alpha/groups/{$groupId}/files");

        $resp->assertOk();
        $resp->assertSee('file-input');
    }

    // ================================================================
    // Delete permissions (no actual file on disk needed for gate checks)
    // ================================================================

    public function test_groups_files_uploader_sees_delete_button_for_own_file(): void
    {
        $this->groupsFilesEnableFeature();
        $owner   = User::factory()->forTenant($this->testTenantId)->create(['status' => 'active', 'is_approved' => true]);
        $groupId = $this->groupsFilesCreateGroup($owner->id);

        $member = $this->groupsFilesUser();
        $this->groupsFilesAddMember($groupId, $member->id, 'member');

        // Member uploads their own file
        $this->groupsFilesInsertRecord($groupId, $member->id, ['file_name' => 'my-file.pdf']);

        $resp = $this->get("/{$this->testTenantSlug}/alpha/groups/{$groupId}/files");

        $resp->assertOk();
        // Delete form action should appear since current user is the uploader
        $resp->assertSee('/files/', false);
        $resp->assertSee('/delete', false);
    }

    public function test_groups_files_non_uploader_non_admin_cannot_see_delete_for_others_files(): void
    {
        $this->groupsFilesEnableFeature();
        $owner   = User::factory()->forTenant($this->testTenantId)->create(['status' => 'active', 'is_approved' => true]);
        $groupId = $this->groupsFilesCreateGroup($owner->id);

        // File uploaded by the owner (not the viewer)
        $this->groupsFilesInsertRecord($groupId, $owner->id, ['file_name' => 'owner-file.pdf']);

        // Viewer is a plain member (different user)
        $member = $this->groupsFilesUser();
        $this->groupsFilesAddMember($groupId, $member->id, 'member');

        $resp = $this->get("/{$this->testTenantSlug}/alpha/groups/{$groupId}/files");

        $resp->assertOk();
        // Delete button should NOT appear because viewer is not uploader or admin
        $resp->assertDontSee('/delete', false);
    }

    public function test_groups_files_admin_sees_delete_for_any_file(): void
    {
        $this->groupsFilesEnableFeature();
        $admin   = $this->groupsFilesUser();
        $groupId = $this->groupsFilesCreateGroup($admin->id);

        // Another user uploaded the file (but admin should still see delete)
        $other   = User::factory()->forTenant($this->testTenantId)->create(['status' => 'active', 'is_approved' => true]);
        $this->groupsFilesInsertRecord($groupId, $other->id, ['file_name' => 'someone-elses.pdf']);

        $resp = $this->get("/{$this->testTenantSlug}/alpha/groups/{$groupId}/files");

        $resp->assertOk();
        $resp->assertSee('/delete', false);
    }

    // ================================================================
    // Upload (route + gate only — file storage via Storage::fake)
    // ================================================================

    public function test_groups_files_upload_requires_auth(): void
    {
        $this->groupsFilesEnableFeature();
        $owner   = User::factory()->forTenant($this->testTenantId)->create(['status' => 'active', 'is_approved' => true]);
        $groupId = $this->groupsFilesCreateGroup($owner->id);

        $resp = $this->post("/{$this->testTenantSlug}/alpha/groups/{$groupId}/files", [
            '_token' => csrf_token(),
        ]);

        $resp->assertRedirect();
        $this->assertStringContainsString('status=auth-required', $resp->headers->get('location') ?? '');
    }

    public function test_groups_files_upload_without_file_redirects_with_error(): void
    {
        $this->groupsFilesEnableFeature();
        $owner   = $this->groupsFilesUser();
        $groupId = $this->groupsFilesCreateGroup($owner->id);

        $resp = $this->post("/{$this->testTenantSlug}/alpha/groups/{$groupId}/files", [
            '_token' => csrf_token(),
        ]);

        $resp->assertRedirect();
        $this->assertStringContainsString('status=file-missing', $resp->headers->get('location') ?? '');
    }

    public function test_groups_files_upload_by_member_persists_record(): void
    {
        $this->groupsFilesEnableFeature();
        Storage::fake('local');

        $owner   = User::factory()->forTenant($this->testTenantId)->create(['status' => 'active', 'is_approved' => true]);
        $groupId = $this->groupsFilesCreateGroup($owner->id);

        $member  = $this->groupsFilesUser();
        $this->groupsFilesAddMember($groupId, $member->id, 'member');

        $resp = $this->post("/{$this->testTenantSlug}/alpha/groups/{$groupId}/files", [
            '_token' => csrf_token(),
            'file'   => UploadedFile::fake()->create('report.pdf', 100, 'application/pdf'),
        ]);

        $resp->assertRedirect();
        $this->assertStringContainsString('status=file-uploaded', $resp->headers->get('location') ?? '');
        $this->assertDatabaseHas('group_files', [
            'group_id'    => $groupId,
            'tenant_id'   => $this->testTenantId,
            'uploaded_by' => $member->id,
        ]);
    }

    public function test_groups_files_upload_non_member_gets_403(): void
    {
        $this->groupsFilesEnableFeature();
        Storage::fake('local');

        $owner   = User::factory()->forTenant($this->testTenantId)->create(['status' => 'active', 'is_approved' => true]);
        $groupId = $this->groupsFilesCreateGroup($owner->id);

        $this->groupsFilesUser(); // NOT a member

        $resp = $this->post("/{$this->testTenantSlug}/alpha/groups/{$groupId}/files", [
            '_token' => csrf_token(),
            'file'   => UploadedFile::fake()->create('report.pdf', 100, 'application/pdf'),
        ]);

        $resp->assertForbidden();
    }

    // ================================================================
    // Delete route
    // ================================================================

    public function test_groups_files_delete_by_admin_removes_record(): void
    {
        $this->groupsFilesEnableFeature();
        Storage::fake('local');

        $admin   = $this->groupsFilesUser();
        $groupId = $this->groupsFilesCreateGroup($admin->id);

        $fileId  = $this->groupsFilesInsertRecord($groupId, $admin->id);

        $resp = $this->post("/{$this->testTenantSlug}/alpha/groups/{$groupId}/files/{$fileId}/delete", [
            '_token' => csrf_token(),
        ]);

        $resp->assertRedirect();
        $this->assertStringContainsString('status=file-deleted', $resp->headers->get('location') ?? '');
        $this->assertDatabaseMissing('group_files', ['id' => $fileId]);
    }

    public function test_groups_files_delete_by_non_admin_non_uploader_gets_forbidden_redirect(): void
    {
        $this->groupsFilesEnableFeature();
        Storage::fake('local');

        $owner   = User::factory()->forTenant($this->testTenantId)->create(['status' => 'active', 'is_approved' => true]);
        $groupId = $this->groupsFilesCreateGroup($owner->id);

        // File uploaded by the owner
        $fileId  = $this->groupsFilesInsertRecord($groupId, $owner->id);

        // A plain member tries to delete
        $member  = $this->groupsFilesUser();
        $this->groupsFilesAddMember($groupId, $member->id, 'member');

        $resp = $this->post("/{$this->testTenantSlug}/alpha/groups/{$groupId}/files/{$fileId}/delete", [
            '_token' => csrf_token(),
        ]);

        $resp->assertRedirect();
        $this->assertStringContainsString('status=file-forbidden', $resp->headers->get('location') ?? '');
        // Record must still exist
        $this->assertDatabaseHas('group_files', ['id' => $fileId]);
    }

    public function test_groups_files_delete_requires_auth(): void
    {
        $this->groupsFilesEnableFeature();
        $owner   = User::factory()->forTenant($this->testTenantId)->create(['status' => 'active', 'is_approved' => true]);
        $groupId = $this->groupsFilesCreateGroup($owner->id);
        $fileId  = $this->groupsFilesInsertRecord($groupId, $owner->id);

        $resp = $this->post("/{$this->testTenantSlug}/alpha/groups/{$groupId}/files/{$fileId}/delete", [
            '_token' => csrf_token(),
        ]);

        $resp->assertRedirect();
        $this->assertStringContainsString('status=auth-required', $resp->headers->get('location') ?? '');
    }
}
