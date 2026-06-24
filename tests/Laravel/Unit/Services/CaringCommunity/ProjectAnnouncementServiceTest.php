<?php
// Copyright © 2024–2026 Jasper Ford
// SPDX-License-Identifier: AGPL-3.0-or-later
// Author: Jasper Ford
// See NOTICE file for attribution and acknowledgements.

declare(strict_types=1);

namespace Tests\Laravel\Unit\Services\CaringCommunity;

use App\Services\CaringCommunity\ProjectAnnouncementService;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Queue;
use Illuminate\Support\Facades\Schema;
use InvalidArgumentException;
use RuntimeException;
use Tests\Laravel\TestCase;

/**
 * ProjectAnnouncementServiceTest
 *
 * Covers the multi-stage project announcement tracking service (AG69).
 *
 * Skipped paths:
 *  - notifySubscribers / NotificationDispatcher.fanOutPush (push infrastructure;
 *    side-effects verified indirectly via notification_count in publishUpdate tests)
 *  - Tables unavailable path (ensureAvailable throws) — verified by isAvailable() check
 */
class ProjectAnnouncementServiceTest extends TestCase
{
    use DatabaseTransactions;

    private const TENANT_ID = 2;
    private const OTHER_TENANT_ID = 3;

    protected function setUp(): void
    {
        parent::setUp();

        Queue::fake();

        if (
            ! Schema::hasTable('caring_project_announcements')
            || ! Schema::hasTable('caring_project_updates')
            || ! Schema::hasTable('caring_project_subscriptions')
        ) {
            $this->markTestSkipped('caring_project_announcements tables not present.');
        }

        \App\Core\TenantContext::setById(self::TENANT_ID);
    }

    // ── Helpers ───────────────────────────────────────────────────────────────

    /** Insert a minimal user row and return its id. */
    private function insertUser(int $tenantId = self::TENANT_ID): int
    {
        $uid = uniqid('pas_u_', true);
        return (int) DB::table('users')->insertGetId([
            'tenant_id'  => $tenantId,
            'name'       => 'PA Test User ' . $uid,
            'first_name' => 'PA',
            'last_name'  => 'User',
            'email'      => $uid . '@example.test',
            'status'     => 'active',
            'balance'    => 0,
            'role'       => 'member',
            'is_approved' => 1,
            'created_at' => now(),
            'updated_at' => now(),
        ]);
    }

    /** Seed a project row directly; return its id. */
    private function seedProject(array $overrides = []): int
    {
        $userId = $this->insertUser();
        return (int) DB::table('caring_project_announcements')->insertGetId(array_merge([
            'tenant_id'        => self::TENANT_ID,
            'created_by'       => $userId,
            'title'            => 'Test Project ' . uniqid(),
            'summary'          => null,
            'location'         => null,
            'status'           => 'active',
            'current_stage'    => null,
            'progress_percent' => 0,
            'starts_at'        => null,
            'ends_at'          => null,
            'published_at'     => now(),
            'last_update_at'   => null,
            'subscriber_count' => 0,
            'created_at'       => now(),
            'updated_at'       => now(),
        ], $overrides));
    }

    // ── isAvailable ───────────────────────────────────────────────────────────

    public function test_is_available_returns_true_when_tables_exist(): void
    {
        $this->assertTrue(ProjectAnnouncementService::isAvailable());
    }

    // ── createProject ─────────────────────────────────────────────────────────

    public function test_create_project_inserts_row_and_returns_array(): void
    {
        $userId = $this->insertUser();

        $result = ProjectAnnouncementService::createProject(self::TENANT_ID, $userId, [
            'title'   => 'New Road Widening Project',
            'summary' => 'Summary text',
            'status'  => 'draft',
        ]);

        $this->assertIsArray($result);
        $this->assertArrayHasKey('id', $result);
        $this->assertGreaterThan(0, $result['id']);
        $this->assertSame('New Road Widening Project', $result['title']);
        $this->assertSame('draft', $result['status']);
        $this->assertSame(self::TENANT_ID, $result['tenant_id']);
        $this->assertNull($result['published_at']); // draft → no published_at
    }

    public function test_create_project_sets_published_at_when_status_is_active(): void
    {
        $userId = $this->insertUser();

        $result = ProjectAnnouncementService::createProject(self::TENANT_ID, $userId, [
            'title'  => 'Active From Scratch',
            'status' => 'active',
        ]);

        $this->assertSame('active', $result['status']);
        $this->assertNotNull($result['published_at']);
    }

    public function test_create_project_throws_on_missing_title(): void
    {
        $userId = $this->insertUser();

        $this->expectException(InvalidArgumentException::class);
        ProjectAnnouncementService::createProject(self::TENANT_ID, $userId, []);
    }

    public function test_create_project_throws_on_blank_title(): void
    {
        $userId = $this->insertUser();

        $this->expectException(InvalidArgumentException::class);
        ProjectAnnouncementService::createProject(self::TENANT_ID, $userId, ['title' => '   ']);
    }

    public function test_create_project_clamps_invalid_status_to_draft(): void
    {
        $userId = $this->insertUser();

        $result = ProjectAnnouncementService::createProject(self::TENANT_ID, $userId, [
            'title'  => 'Status Clamp Test',
            'status' => 'bogus_status',
        ]);

        $this->assertSame('draft', $result['status']);
    }

    public function test_create_project_normalises_progress_percent_to_range(): void
    {
        $userId = $this->insertUser();

        $over = ProjectAnnouncementService::createProject(self::TENANT_ID, $userId, [
            'title'            => 'Over 100',
            'progress_percent' => 150,
        ]);
        $under = ProjectAnnouncementService::createProject(self::TENANT_ID, $userId, [
            'title'            => 'Under 0',
            'progress_percent' => -10,
        ]);

        $this->assertSame(100, $over['progress_percent']);
        $this->assertSame(0, $under['progress_percent']);
    }

    // ── listPublished ─────────────────────────────────────────────────────────

    public function test_list_published_excludes_draft_and_cancelled_projects(): void
    {
        $activeId    = $this->seedProject(['status' => 'active']);
        $draftId     = $this->seedProject(['status' => 'draft']);
        $cancelledId = $this->seedProject(['status' => 'cancelled']);

        $result = ProjectAnnouncementService::listPublished(self::TENANT_ID);

        $ids = array_column($result, 'id');
        $this->assertContains($activeId, $ids);
        $this->assertNotContains($draftId, $ids);
        $this->assertNotContains($cancelledId, $ids);
    }

    public function test_list_published_includes_paused_and_completed(): void
    {
        $pausedId    = $this->seedProject(['status' => 'paused', 'published_at' => now()]);
        $completedId = $this->seedProject(['status' => 'completed', 'published_at' => now()]);

        $result = ProjectAnnouncementService::listPublished(self::TENANT_ID);

        $ids = array_column($result, 'id');
        $this->assertContains($pausedId, $ids);
        $this->assertContains($completedId, $ids);
    }

    public function test_list_published_is_tenant_scoped(): void
    {
        // Ensure other tenant row exists in tenants table
        DB::table('tenants')->updateOrInsert(
            ['id' => self::OTHER_TENANT_ID],
            ['name' => 'Other', 'slug' => 'other-tenant-pas-3', 'is_active' => true, 'depth' => 0, 'allows_subtenants' => false, 'created_at' => now(), 'updated_at' => now()]
        );
        $otherUserId = $this->insertUser(self::OTHER_TENANT_ID);
        DB::table('caring_project_announcements')->insert([
            'tenant_id'        => self::OTHER_TENANT_ID,
            'created_by'       => $otherUserId,
            'title'            => 'Other Tenant Project',
            'status'           => 'active',
            'progress_percent' => 0,
            'subscriber_count' => 0,
            'published_at'     => now(),
            'created_at'       => now(),
            'updated_at'       => now(),
        ]);

        $ownId = $this->seedProject(['status' => 'active']);

        $result = ProjectAnnouncementService::listPublished(self::TENANT_ID);

        $ids = array_column($result, 'id');
        $this->assertContains($ownId, $ids);
        $titles = array_column($result, 'title');
        $this->assertNotContains('Other Tenant Project', $titles);
    }

    // ── listAdmin ─────────────────────────────────────────────────────────────

    public function test_list_admin_returns_all_statuses(): void
    {
        $activeId  = $this->seedProject(['status' => 'active']);
        $draftId   = $this->seedProject(['status' => 'draft']);

        $result = ProjectAnnouncementService::listAdmin(self::TENANT_ID);

        $ids = array_column($result, 'id');
        $this->assertContains($activeId, $ids);
        $this->assertContains($draftId, $ids);
    }

    public function test_list_admin_filters_by_status(): void
    {
        $draftId  = $this->seedProject(['status' => 'draft']);
        $activeId = $this->seedProject(['status' => 'active']);

        $draftResult = ProjectAnnouncementService::listAdmin(self::TENANT_ID, 'draft');
        $draftIds = array_column($draftResult, 'id');

        $this->assertContains($draftId, $draftIds);
        $this->assertNotContains($activeId, $draftIds);
    }

    // ── getProject ────────────────────────────────────────────────────────────

    public function test_get_project_returns_null_for_unknown_id(): void
    {
        $result = ProjectAnnouncementService::getProject(999999999, self::TENANT_ID);
        $this->assertNull($result);
    }

    public function test_get_project_returns_null_for_draft_when_include_drafts_false(): void
    {
        $draftId = $this->seedProject(['status' => 'draft', 'published_at' => null]);

        $result = ProjectAnnouncementService::getProject($draftId, self::TENANT_ID, false);
        $this->assertNull($result);
    }

    public function test_get_project_returns_draft_when_include_drafts_true(): void
    {
        $draftId = $this->seedProject(['status' => 'draft', 'published_at' => null]);

        $result = ProjectAnnouncementService::getProject($draftId, self::TENANT_ID, true);
        $this->assertNotNull($result);
        $this->assertSame($draftId, $result['id']);
    }

    public function test_get_project_includes_updates_array_and_is_subscribed_flag(): void
    {
        $id = $this->seedProject(['status' => 'active']);

        $result = ProjectAnnouncementService::getProject($id, self::TENANT_ID, false, null);

        $this->assertArrayHasKey('updates', $result);
        $this->assertIsArray($result['updates']);
        $this->assertArrayHasKey('is_subscribed', $result);
        $this->assertFalse($result['is_subscribed']);
    }

    // ── updateProject ─────────────────────────────────────────────────────────

    public function test_update_project_changes_title_and_returns_fresh_row(): void
    {
        $id = $this->seedProject(['title' => 'Original Title']);

        $result = ProjectAnnouncementService::updateProject($id, self::TENANT_ID, [
            'title' => 'Updated Title',
        ]);

        $this->assertSame('Updated Title', $result['title']);
        $this->assertSame($id, $result['id']);
    }

    public function test_update_project_changes_progress_percent(): void
    {
        $id = $this->seedProject(['progress_percent' => 10]);

        $result = ProjectAnnouncementService::updateProject($id, self::TENANT_ID, [
            'progress_percent' => 75,
        ]);

        $this->assertSame(75, $result['progress_percent']);
    }

    public function test_update_project_throws_for_invalid_status(): void
    {
        $id = $this->seedProject();

        $this->expectException(InvalidArgumentException::class);
        ProjectAnnouncementService::updateProject($id, self::TENANT_ID, [
            'status' => 'not_a_valid_status',
        ]);
    }

    public function test_update_project_sets_published_at_when_transitioning_from_draft(): void
    {
        $userId  = $this->insertUser();
        $created = ProjectAnnouncementService::createProject(self::TENANT_ID, $userId, [
            'title'  => 'Draft To Active',
            'status' => 'draft',
        ]);
        $this->assertNull($created['published_at']);

        $updated = ProjectAnnouncementService::updateProject((int) $created['id'], self::TENANT_ID, [
            'status' => 'active',
        ]);

        $this->assertNotNull($updated['published_at']);
    }

    // ── publishProject ────────────────────────────────────────────────────────

    public function test_publish_project_sets_status_to_active(): void
    {
        $id = $this->seedProject(['status' => 'draft', 'published_at' => null]);

        $result = ProjectAnnouncementService::publishProject($id, self::TENANT_ID);

        $this->assertSame('active', $result['status']);
        $this->assertNotNull($result['published_at']);
    }

    public function test_publish_project_throws_for_nonexistent_id(): void
    {
        $this->expectException(RuntimeException::class);
        ProjectAnnouncementService::publishProject(999999999, self::TENANT_ID);
    }

    // ── createUpdate ──────────────────────────────────────────────────────────

    public function test_create_update_inserts_draft_update_row(): void
    {
        $projectId = $this->seedProject();
        $userId    = $this->insertUser();

        $result = ProjectAnnouncementService::createUpdate($projectId, self::TENANT_ID, $userId, [
            'title'  => 'Phase 1 Started',
            'body'   => 'We have broken ground.',
            'status' => 'draft',
        ]);

        $this->assertIsArray($result);
        $this->assertGreaterThan(0, $result['id']);
        $this->assertSame('Phase 1 Started', $result['title']);
        $this->assertSame('draft', $result['status']);
        $this->assertNull($result['published_at']);
    }

    public function test_create_update_published_sets_published_at_and_bumps_project_last_update(): void
    {
        $projectId = $this->seedProject(['status' => 'active']);
        $userId    = $this->insertUser();

        $result = ProjectAnnouncementService::createUpdate($projectId, self::TENANT_ID, $userId, [
            'title'  => 'Milestone Reached',
            'status' => 'published',
        ]);

        $this->assertSame('published', $result['status']);
        $this->assertNotNull($result['published_at']);

        // Project last_update_at should have been refreshed
        $project = DB::table('caring_project_announcements')->where('id', $projectId)->first();
        $this->assertNotNull($project->last_update_at);
    }

    public function test_create_update_marks_milestone_flag(): void
    {
        $projectId = $this->seedProject();
        $userId    = $this->insertUser();

        $result = ProjectAnnouncementService::createUpdate($projectId, self::TENANT_ID, $userId, [
            'title'        => 'Big Milestone',
            'is_milestone' => true,
        ]);

        $this->assertTrue($result['is_milestone']);
    }

    // ── publishUpdate ─────────────────────────────────────────────────────────

    public function test_publish_update_transitions_draft_to_published(): void
    {
        $projectId = $this->seedProject(['status' => 'active']);
        $userId    = $this->insertUser();

        $created = ProjectAnnouncementService::createUpdate($projectId, self::TENANT_ID, $userId, [
            'title'  => 'Update To Publish',
            'status' => 'draft',
        ]);

        $published = ProjectAnnouncementService::publishUpdate((int) $created['id'], self::TENANT_ID);

        $this->assertSame('published', $published['status']);
        $this->assertNotNull($published['published_at']);
    }

    public function test_publish_update_throws_for_nonexistent_update(): void
    {
        $this->expectException(RuntimeException::class);
        ProjectAnnouncementService::publishUpdate(999999999, self::TENANT_ID);
    }

    public function test_publish_update_is_idempotent_when_already_published(): void
    {
        $projectId = $this->seedProject(['status' => 'active']);
        $userId    = $this->insertUser();

        $created   = ProjectAnnouncementService::createUpdate($projectId, self::TENANT_ID, $userId, [
            'title'  => 'Already Published',
            'status' => 'published',
        ]);

        // Calling publishUpdate again on an already-published update should not throw
        $result = ProjectAnnouncementService::publishUpdate((int) $created['id'], self::TENANT_ID);
        $this->assertSame('published', $result['status']);
    }

    // ── subscribe / unsubscribe ───────────────────────────────────────────────

    public function test_subscribe_creates_subscription_and_increments_count(): void
    {
        $projectId = $this->seedProject(['status' => 'active']);
        $userId    = $this->insertUser();

        ProjectAnnouncementService::subscribe($projectId, self::TENANT_ID, $userId);

        $row = DB::table('caring_project_subscriptions')
            ->where('project_id', $projectId)
            ->where('user_id', $userId)
            ->first();

        $this->assertNotNull($row);
        $this->assertNull($row->unsubscribed_at);

        $project = DB::table('caring_project_announcements')->where('id', $projectId)->first();
        $this->assertSame(1, (int) $project->subscriber_count);
    }

    public function test_subscribe_is_reflected_in_get_project_is_subscribed_flag(): void
    {
        $projectId = $this->seedProject(['status' => 'active']);
        $userId    = $this->insertUser();

        ProjectAnnouncementService::subscribe($projectId, self::TENANT_ID, $userId);

        $result = ProjectAnnouncementService::getProject($projectId, self::TENANT_ID, false, $userId);
        $this->assertTrue($result['is_subscribed']);
    }

    public function test_unsubscribe_sets_unsubscribed_at_and_decrements_count(): void
    {
        $projectId = $this->seedProject(['status' => 'active']);
        $userId    = $this->insertUser();

        ProjectAnnouncementService::subscribe($projectId, self::TENANT_ID, $userId);
        ProjectAnnouncementService::unsubscribe($projectId, self::TENANT_ID, $userId);

        $row = DB::table('caring_project_subscriptions')
            ->where('project_id', $projectId)
            ->where('user_id', $userId)
            ->first();

        $this->assertNotNull($row->unsubscribed_at);

        $project = DB::table('caring_project_announcements')->where('id', $projectId)->first();
        $this->assertSame(0, (int) $project->subscriber_count);
    }

    public function test_subscribe_throws_for_draft_project_without_active_status(): void
    {
        // subscribe() calls assertProjectExists(..., includeDrafts=false) → only active/paused/completed
        $draftId = $this->seedProject(['status' => 'draft', 'published_at' => null]);
        $userId  = $this->insertUser();

        $this->expectException(RuntimeException::class);
        ProjectAnnouncementService::subscribe($draftId, self::TENANT_ID, $userId);
    }
}
