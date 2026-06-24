<?php
// Copyright © 2024–2026 Jasper Ford
// SPDX-License-Identifier: AGPL-3.0-or-later
// Author: Jasper Ford
// See NOTICE file for attribution and acknowledgements.

declare(strict_types=1);

namespace Tests\Laravel\Unit\Console;

use App\Core\TenantContext;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Queue;
use Tests\Laravel\TestCase;

/**
 * Tests for groups:publish-scheduled console command.
 *
 * Uses unique tenant id 99739 for isolation.
 *
 * The command publishes due scheduled group posts via
 * GroupScheduledPostService::publishDue() and expires overdue challenges
 * via GroupChallengeService::expireOverdue().
 *
 * For 'announcement' post type no FK constraints exist on group_announcements,
 * so we seed minimal group_scheduled_posts rows directly.
 * For 'discussion' type we also seed a user + group to satisfy FKs on
 * group_discussions.
 *
 * GroupScheduledPostService::publishDue() iterates ALL tenants (no filter),
 * so we use explicit tenant_id 99739 in every seed row and assert only
 * rows belonging to our tenant changed.
 */
class PublishScheduledGroupPostsCommandTest extends TestCase
{
    use DatabaseTransactions;

    private const TENANT_ID   = 99739;
    private const TENANT_SLUG = 'test-publish-scheduled-99739';

    /** Seeded user id (needed for discussion FK) */
    private int $userId;

    /** Seeded group id (needed for discussion FK) */
    private int $groupId;

    protected function setUp(): void
    {
        parent::setUp();

        Queue::fake();

        DB::table('tenants')->insertOrIgnore([
            'id'         => self::TENANT_ID,
            'name'       => 'Test Publish Scheduled Tenant',
            'slug'       => self::TENANT_SLUG,
            'is_active'  => 1,
            'features'   => json_encode([]),
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        TenantContext::setById(self::TENANT_ID);

        // Seed one user (needed for group FK owner_id and discussion FK user_id).
        $this->userId = (int) DB::table('users')->insertGetId([
            'tenant_id'    => self::TENANT_ID,
            'name'         => 'Scheduler Test User',
            'email'        => 'sched-test-99739@example.com',
            'role'         => 'member',
            'status'       => 'active',
            'is_approved'  => 1,
            'created_at'   => now(),
        ]);

        // Seed one group (needed for discussion FK group_id).
        $this->groupId = (int) DB::table('groups')->insertGetId([
            'tenant_id'  => self::TENANT_ID,
            'owner_id'   => $this->userId,
            'name'       => 'Test Group 99739',
            'status'     => 'active',
            'is_active'  => 1,
            'created_at' => now(),
        ]);
    }

    // ------------------------------------------------------------------ //
    // Helpers                                                              //
    // ------------------------------------------------------------------ //

    /**
     * Insert a group_scheduled_posts row for our tenant.
     *
     * @param array<string,mixed> $overrides
     */
    private function insertScheduledPost(array $overrides = []): int
    {
        $defaults = [
            'tenant_id'          => self::TENANT_ID,
            'group_id'           => $this->groupId,
            'user_id'            => $this->userId,
            'post_type'          => 'announcement',
            'title'              => 'Test Announcement',
            'content'            => 'Test announcement content',
            'is_recurring'       => 0,
            'recurrence_pattern' => null,
            'scheduled_at'       => now()->subMinutes(5),   // due by default
            'status'             => 'scheduled',
            'created_at'         => now(),
            'updated_at'         => now(),
        ];

        return (int) DB::table('group_scheduled_posts')
            ->insertGetId(array_merge($defaults, $overrides));
    }

    /**
     * Insert a group_challenges row for our tenant.
     */
    private function insertChallenge(array $overrides = []): int
    {
        $defaults = [
            'tenant_id'     => self::TENANT_ID,
            'group_id'      => $this->groupId,
            'created_by'    => $this->userId,
            'title'         => 'Test Challenge',
            'metric'        => 'posts',
            'target_value'  => 10,
            'current_value' => 0,
            'reward_xp'     => 0,   // 0 so GamificationService::awardXP is not triggered
            'status'        => 'active',
            'starts_at'     => now()->subDays(7),
            'ends_at'       => now()->subDay(),  // overdue by default
            'created_at'    => now(),
            'updated_at'    => now(),
        ];

        return (int) DB::table('group_challenges')
            ->insertGetId(array_merge($defaults, $overrides));
    }

    // ------------------------------------------------------------------ //
    // Tests — basic exit code                                              //
    // ------------------------------------------------------------------ //

    public function test_exits_success_with_no_due_posts(): void
    {
        $this->artisan('groups:publish-scheduled')
            ->assertExitCode(0);
    }

    public function test_exits_success_when_due_post_is_published(): void
    {
        $this->insertScheduledPost(['scheduled_at' => now()->subHour()]);

        $this->artisan('groups:publish-scheduled')
            ->assertExitCode(0);
    }

    // ------------------------------------------------------------------ //
    // Tests — scheduled post published (status flipped)                   //
    // ------------------------------------------------------------------ //

    public function test_past_scheduled_announcement_is_published(): void
    {
        $id = $this->insertScheduledPost([
            'post_type'    => 'announcement',
            'scheduled_at' => now()->subHour(),
        ]);

        $this->artisan('groups:publish-scheduled')->assertExitCode(0);

        $row = DB::table('group_scheduled_posts')->where('id', $id)->first();
        $this->assertSame('published', $row->status, 'Status must flip to published');
        $this->assertNotNull($row->published_at, 'published_at must be set');
    }

    public function test_past_scheduled_discussion_post_is_published(): void
    {
        $id = $this->insertScheduledPost([
            'post_type'    => 'discussion',
            'scheduled_at' => now()->subHour(),
        ]);

        $this->artisan('groups:publish-scheduled')->assertExitCode(0);

        $row = DB::table('group_scheduled_posts')->where('id', $id)->first();
        $this->assertSame('published', $row->status);
        $this->assertNotNull($row->published_at);
    }

    public function test_discussion_post_creates_discussion_and_post_rows(): void
    {
        $id = $this->insertScheduledPost([
            'post_type'    => 'discussion',
            'title'        => 'Scheduled Discussion Title',
            'content'      => 'Scheduled discussion body',
            'scheduled_at' => now()->subHour(),
        ]);

        $this->artisan('groups:publish-scheduled')->assertExitCode(0);

        // A group_discussions row must have been created for this group.
        $this->assertDatabaseHas('group_discussions', [
            'group_id' => $this->groupId,
            'user_id'  => $this->userId,
        ]);

        // A corresponding group_posts row must also exist.
        $discussion = DB::table('group_discussions')
            ->where('group_id', $this->groupId)
            ->where('user_id', $this->userId)
            ->first();
        $this->assertNotNull($discussion, 'group_discussions row must be created');

        $this->assertDatabaseHas('group_posts', [
            'discussion_id' => $discussion->id,
            'user_id'       => $this->userId,
        ]);
    }

    public function test_announcement_post_creates_announcement_row(): void
    {
        $id = $this->insertScheduledPost([
            'post_type'    => 'announcement',
            'title'        => 'Scheduled Announcement',
            'content'      => 'Announcement body',
            'scheduled_at' => now()->subHour(),
        ]);

        $this->artisan('groups:publish-scheduled')->assertExitCode(0);

        $this->assertDatabaseHas('group_announcements', [
            'group_id'   => $this->groupId,
            'tenant_id'  => self::TENANT_ID,
            'created_by' => $this->userId,
        ]);
    }

    // ------------------------------------------------------------------ //
    // Tests — future-scheduled post must be untouched                     //
    // ------------------------------------------------------------------ //

    public function test_future_scheduled_post_is_not_published(): void
    {
        $id = $this->insertScheduledPost([
            'scheduled_at' => now()->addHour(),   // NOT due yet
        ]);

        $this->artisan('groups:publish-scheduled')->assertExitCode(0);

        $row = DB::table('group_scheduled_posts')->where('id', $id)->first();
        $this->assertSame('scheduled', $row->status, 'Future post must remain scheduled');
        $this->assertNull($row->published_at, 'published_at must remain NULL for future post');
    }

    public function test_exactly_due_post_is_published(): void
    {
        // scheduled_at = 1 second in the past (boundary: <= now() is true).
        $id = $this->insertScheduledPost([
            'scheduled_at' => now()->subSecond(),
        ]);

        $this->artisan('groups:publish-scheduled')->assertExitCode(0);

        $row = DB::table('group_scheduled_posts')->where('id', $id)->first();
        $this->assertSame('published', $row->status, 'Post due at boundary must be published');
    }

    // ------------------------------------------------------------------ //
    // Tests — challenges expiry                                            //
    // ------------------------------------------------------------------ //

    public function test_overdue_challenge_is_expired(): void
    {
        $id = $this->insertChallenge([
            'ends_at' => now()->subHour(),  // overdue
        ]);

        $this->artisan('groups:publish-scheduled')->assertExitCode(0);

        $row = DB::table('group_challenges')->where('id', $id)->first();
        $this->assertSame('expired', $row->status, 'Overdue challenge must be marked expired');
    }

    public function test_future_challenge_is_not_expired(): void
    {
        $id = $this->insertChallenge([
            'ends_at' => now()->addHour(),  // still active
        ]);

        $this->artisan('groups:publish-scheduled')->assertExitCode(0);

        $row = DB::table('group_challenges')->where('id', $id)->first();
        $this->assertSame('active', $row->status, 'Non-overdue challenge must remain active');
    }

    // ------------------------------------------------------------------ //
    // Tests — output and combined run                                      //
    // ------------------------------------------------------------------ //

    public function test_output_contains_published_count(): void
    {
        $this->insertScheduledPost(['scheduled_at' => now()->subHour()]);

        $this->artisan('groups:publish-scheduled')
            ->expectsOutputToContain('Published')
            ->assertExitCode(0);
    }

    public function test_output_contains_expired_count(): void
    {
        $this->artisan('groups:publish-scheduled')
            ->expectsOutputToContain('Expired')
            ->assertExitCode(0);
    }
}
