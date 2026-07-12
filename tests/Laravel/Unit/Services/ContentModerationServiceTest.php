<?php
// Copyright © 2024–2026 Jasper Ford
// SPDX-License-Identifier: AGPL-3.0-or-later
// Author: Jasper Ford
// See NOTICE file for attribution and acknowledgements.

namespace Tests\Laravel\Unit\Services;

use App\Core\TenantContext;
use App\Models\ContentModerationQueue;
use App\Models\Tenant;
use App\Models\User;
use App\Services\ContentModerationService;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Config;
use Tests\Laravel\TestCase;

/**
 * Real-DB tests for ContentModerationService.
 *
 * Previously most methods were markTestIncomplete ("Eloquent models cannot use
 * shouldReceive()") or stubbed with DB::shouldReceive(). They now run against
 * the real nexus_test database inside a rolled-back transaction.
 *
 * Tenant gotcha: ContentModerationQueue is tenant-scoped via HasTenantScope, so
 * every query reads TenantContext::getId(). User::factory() / model creation can
 * drift TenantContext, so we re-pin `TenantContext::setById($this->testTenantId)`
 * immediately before each tenant-scoped service call.
 *
 * Real FK ids: author_id / reviewer_id are FKs to users — we create real users
 * via User::factory()->forTenant() and pass their ids, never literals.
 *
 * The factory's default content_type can be an out-of-enum value (it includes
 * 'feed_post'/'message'); the table enum is post|listing|event|comment|group, so
 * we always pass an explicit valid content_type and status on create.
 */
class ContentModerationServiceTest extends TestCase
{
    use DatabaseTransactions;

    /**
     * Create a pending moderation-queue row owned by the test tenant.
     */
    private function makePendingItem(int $authorId, string $contentType = 'post', int $contentId = 12345): ContentModerationQueue
    {
        TenantContext::setById($this->testTenantId);

        return ContentModerationQueue::factory()->forTenant($this->testTenantId)->create([
            'content_type'  => $contentType,
            'content_id'    => $contentId,
            'author_id'     => $authorId,
            'status'        => 'pending',
            'auto_flagged'  => 0,
            'reviewer_id'   => null,
            'reviewed_at'   => null,
        ]);
    }

    // --- Pure constant / static-data assertions (no DB) ---

    public function test_constants_defined(): void
    {
        $this->assertContains('post', ContentModerationService::CONTENT_TYPES);
        $this->assertContains('listing', ContentModerationService::CONTENT_TYPES);
        $this->assertSame('pending', ContentModerationService::STATUS_PENDING);
        $this->assertSame('approved', ContentModerationService::STATUS_APPROVED);
        $this->assertSame('rejected', ContentModerationService::STATUS_REJECTED);
        $this->assertSame('flagged', ContentModerationService::STATUS_FLAGGED);
    }

    // --- detectSpam: pure regex, no DB ---

    public function test_detectSpam_flags_known_spam_phrases(): void
    {
        $this->assertTrue(ContentModerationService::detectSpam('Hey, buy now and click here for a limited offer!'));
        $this->assertTrue(ContentModerationService::detectSpam('FREE MONEY ACT NOW'));
    }

    public function test_detectSpam_passes_clean_content(): void
    {
        $this->assertFalse(ContentModerationService::detectSpam('Just sharing an update about my day at the community garden.'));
    }

    // --- approve() ---

    public function test_approve_returns_true_on_success(): void
    {
        $author = User::factory()->forTenant($this->testTenantId)->create();
        $admin  = User::factory()->forTenant($this->testTenantId)->create();
        $item   = $this->makePendingItem((int) $author->id, 'comment', 10);

        TenantContext::setById($this->testTenantId);
        $this->assertTrue(
            ContentModerationService::approve((int) $item->id, $this->testTenantId, (int) $admin->id)
        );

        $fresh = ContentModerationQueue::withoutGlobalScopes()->find($item->id);
        $this->assertSame('approved', $fresh->status);
        $this->assertSame((int) $admin->id, (int) $fresh->reviewer_id);
        $this->assertNotNull($fresh->reviewed_at);
    }

    public function test_approve_returns_false_when_not_pending(): void
    {
        $author = User::factory()->forTenant($this->testTenantId)->create();
        $admin  = User::factory()->forTenant($this->testTenantId)->create();
        $item   = $this->makePendingItem((int) $author->id, 'comment', 11);

        // First approval transitions away from pending.
        TenantContext::setById($this->testTenantId);
        $this->assertTrue(
            ContentModerationService::approve((int) $item->id, $this->testTenantId, (int) $admin->id)
        );

        // A second call must be a no-op (already non-pending).
        TenantContext::setById($this->testTenantId);
        $this->assertFalse(
            ContentModerationService::approve((int) $item->id, $this->testTenantId, (int) $admin->id)
        );
    }

    // --- reject() ---

    public function test_reject_returns_true_on_success(): void
    {
        $author = User::factory()->forTenant($this->testTenantId)->create();
        $admin  = User::factory()->forTenant($this->testTenantId)->create();
        $item   = $this->makePendingItem((int) $author->id, 'listing', 20);

        TenantContext::setById($this->testTenantId);
        $this->assertTrue(
            ContentModerationService::reject((int) $item->id, $this->testTenantId, (int) $admin->id, 'spam')
        );

        $fresh = ContentModerationQueue::withoutGlobalScopes()->find($item->id);
        $this->assertSame('rejected', $fresh->status);
        $this->assertSame((int) $admin->id, (int) $fresh->reviewer_id);
        $this->assertSame('spam', $fresh->rejection_reason);
    }

    // --- review() — pure validation guard (no DB) ---

    public function test_review_returns_error_for_invalid_decision(): void
    {
        $result = ContentModerationService::review(1, 2, 10, 'invalid');
        $this->assertFalse($result['success']);
        $this->assertStringContainsString('Invalid decision', $result['message']);
    }

    // --- review() — real-DB guards ---

    public function test_review_returns_error_when_item_not_found(): void
    {
        $admin = User::factory()->forTenant($this->testTenantId)->create();

        TenantContext::setById($this->testTenantId);
        $result = ContentModerationService::review(99999999, $this->testTenantId, (int) $admin->id, 'approved');

        $this->assertFalse($result['success']);
        $this->assertSame('Moderation queue item not found.', $result['message']);
    }

    public function test_review_returns_error_when_already_reviewed(): void
    {
        $author = User::factory()->forTenant($this->testTenantId)->create();
        $admin  = User::factory()->forTenant($this->testTenantId)->create();
        $item   = $this->makePendingItem((int) $author->id, 'comment', 30);

        // First review approves it.
        TenantContext::setById($this->testTenantId);
        $first = ContentModerationService::review((int) $item->id, $this->testTenantId, (int) $admin->id, 'approved');
        $this->assertTrue($first['success']);

        // Second review must be rejected as already-reviewed.
        TenantContext::setById($this->testTenantId);
        $second = ContentModerationService::review((int) $item->id, $this->testTenantId, (int) $admin->id, 'approved');
        $this->assertFalse($second['success']);
        $this->assertSame('This item has already been reviewed.', $second['message']);
    }

    public function test_review_requires_rejection_reason(): void
    {
        $author = User::factory()->forTenant($this->testTenantId)->create();
        $admin  = User::factory()->forTenant($this->testTenantId)->create();
        $item   = $this->makePendingItem((int) $author->id, 'post', 40);

        TenantContext::setById($this->testTenantId);
        $result = ContentModerationService::review((int) $item->id, $this->testTenantId, (int) $admin->id, 'rejected');

        $this->assertFalse($result['success']);
        $this->assertSame('Rejection reason is required.', $result['message']);

        // Item must remain pending — a rejected-without-reason call changes nothing.
        $fresh = ContentModerationQueue::withoutGlobalScopes()->find($item->id);
        $this->assertSame('pending', $fresh->status);
    }

    public function test_review_approve_succeeds_and_persists_decision(): void
    {
        $author = User::factory()->forTenant($this->testTenantId)->create();
        $admin  = User::factory()->forTenant($this->testTenantId)->create();
        $item   = $this->makePendingItem((int) $author->id, 'post', 50);

        TenantContext::setById($this->testTenantId);
        $result = ContentModerationService::review((int) $item->id, $this->testTenantId, (int) $admin->id, 'approved');

        $this->assertTrue($result['success']);
        $this->assertSame('post', $result['content_type']);
        $this->assertSame(50, $result['content_id']);

        $fresh = ContentModerationQueue::withoutGlobalScopes()->find($item->id);
        $this->assertSame('approved', $fresh->status);
        $this->assertSame((int) $admin->id, (int) $fresh->reviewer_id);
        $this->assertNotNull($fresh->reviewed_at);
    }

    public function test_event_review_uses_canonical_lifecycle_history_and_outbox(): void
    {
        Config::set('events.notification_delivery.mode', 'direct');
        $author = User::factory()->forTenant($this->testTenantId)->create([
            'status' => 'active',
            'is_approved' => true,
        ]);
        $admin = User::factory()->forTenant($this->testTenantId)->admin()->create([
            'status' => 'active',
        ]);
        $eventId = (int) DB::table('events')->insertGetId([
            'tenant_id' => $this->testTenantId,
            'user_id' => $author->id,
            'title' => 'Moderated Event',
            'description' => 'Canonical moderation coverage.',
            'location' => 'Test venue',
            'start_time' => now()->addWeek(),
            'end_time' => now()->addWeek()->addHour(),
            'status' => 'draft',
            'publication_status' => 'pending_review',
            'operational_status' => 'scheduled',
            'lifecycle_version' => 1,
            'created_at' => now(),
            'updated_at' => now(),
        ]);
        $item = $this->makePendingItem((int) $author->id, 'event', $eventId);

        TenantContext::setById($this->testTenantId);
        $result = ContentModerationService::review(
            (int) $item->id,
            $this->testTenantId,
            (int) $admin->id,
            'approved',
        );

        $this->assertTrue($result['success']);
        $this->assertDatabaseHas('events', [
            'id' => $eventId,
            'publication_status' => 'published',
            'operational_status' => 'scheduled',
            'status' => 'active',
            'lifecycle_version' => 2,
        ]);
        $this->assertSame(1, DB::table('event_status_history')->where('event_id', $eventId)->count());
        $this->assertSame(1, DB::table('event_domain_outbox')
            ->where('event_id', $eventId)
            ->where('action', 'event.lifecycle.transitioned')
            ->where('production_mode', 'outbox_authoritative')
            ->count());
    }

    public function test_stale_opposite_event_decisions_cannot_reverse_a_terminal_queue_decision(): void
    {
        Config::set('events.notification_delivery.mode', 'outbox_authoritative');
        $author = User::factory()->forTenant($this->testTenantId)->create([
            'status' => 'active',
            'is_approved' => true,
        ]);
        $admin = User::factory()->forTenant($this->testTenantId)->admin()->create(['status' => 'active']);

        foreach ([
            ['first' => 'rejected', 'second' => 'approved', 'terminal' => 'draft'],
            ['first' => 'approved', 'second' => 'rejected', 'terminal' => 'published'],
        ] as $case) {
            $eventId = (int) DB::table('events')->insertGetId([
                'tenant_id' => $this->testTenantId,
                'user_id' => $author->id,
                'title' => 'Concurrent moderation decision fixture',
                'description' => 'Only the first locked decision may win.',
                'location' => 'Test venue',
                'start_time' => now()->addWeek(),
                'end_time' => now()->addWeek()->addHour(),
                'status' => 'draft',
                'publication_status' => 'pending_review',
                'operational_status' => 'scheduled',
                'lifecycle_version' => 1,
                'created_at' => now(),
                'updated_at' => now(),
            ]);
            $item = $this->makePendingItem((int) $author->id, 'event', $eventId);
            TenantContext::setById($this->testTenantId);

            $first = ContentModerationService::review(
                (int) $item->id,
                $this->testTenantId,
                (int) $admin->id,
                $case['first'],
                $case['first'] === 'rejected' ? 'Needs revision.' : null,
            );
            $second = ContentModerationService::review(
                (int) $item->id,
                $this->testTenantId,
                (int) $admin->id,
                $case['second'],
                $case['second'] === 'rejected' ? 'Stale rejection.' : null,
            );

            self::assertTrue($first['success']);
            self::assertFalse($second['success']);
            self::assertSame('This item has already been reviewed.', $second['message']);
            self::assertSame($case['terminal'], DB::table('events')->where('id', $eventId)->value('publication_status'));
            self::assertSame(1, DB::table('event_status_history')->where('event_id', $eventId)->count());
            self::assertSame(1, DB::table('event_domain_outbox')->where('event_id', $eventId)->count());
        }
    }

    public function test_event_review_cannot_cross_the_active_tenant_boundary(): void
    {
        $foreignTenant = Tenant::factory()->create();
        $foreignAuthor = User::factory()->forTenant((int) $foreignTenant->id)->create([
            'status' => 'active',
            'is_approved' => true,
        ]);
        $foreignEventId = (int) DB::table('events')->insertGetId([
            'tenant_id' => $foreignTenant->id,
            'user_id' => $foreignAuthor->id,
            'title' => 'Foreign moderated Event',
            'description' => 'Must remain tenant isolated.',
            'location' => 'Foreign venue',
            'start_time' => now()->addWeek(),
            'end_time' => now()->addWeek()->addHour(),
            'status' => 'draft',
            'publication_status' => 'pending_review',
            'operational_status' => 'scheduled',
            'lifecycle_version' => 1,
            'created_at' => now(),
            'updated_at' => now(),
        ]);
        $foreignItem = ContentModerationQueue::withoutGlobalScopes()->create([
            'tenant_id' => $foreignTenant->id,
            'content_type' => 'event',
            'content_id' => $foreignEventId,
            'author_id' => $foreignAuthor->id,
            'title' => 'Foreign moderated Event',
            'status' => 'pending',
            'auto_flagged' => false,
        ]);
        $localAdmin = User::factory()->forTenant($this->testTenantId)->admin()->create([
            'status' => 'active',
        ]);

        TenantContext::setById($this->testTenantId);
        $result = ContentModerationService::review(
            (int) $foreignItem->id,
            $this->testTenantId,
            (int) $localAdmin->id,
            'approved',
        );

        $this->assertFalse($result['success']);
        $this->assertSame('pending_review', DB::table('events')->where('id', $foreignEventId)->value('publication_status'));
        $this->assertSame(0, DB::table('event_status_history')->where('event_id', $foreignEventId)->count());
        $this->assertSame(0, DB::table('event_domain_outbox')->where('event_id', $foreignEventId)->count());
    }

    // --- getStats() ---

    public function test_getStats_returns_expected_keys(): void
    {
        TenantContext::setById($this->testTenantId);
        $stats = ContentModerationService::getStats($this->testTenantId);

        foreach (['pending', 'approved', 'rejected', 'flagged', 'total'] as $key) {
            $this->assertArrayHasKey($key, $stats);
            $this->assertIsInt($stats[$key]);
        }
    }

    public function test_getStats_counts_a_new_pending_item(): void
    {
        $author = User::factory()->forTenant($this->testTenantId)->create();

        TenantContext::setById($this->testTenantId);
        $before = ContentModerationService::getStats($this->testTenantId);

        $this->makePendingItem((int) $author->id, 'post', 60);

        TenantContext::setById($this->testTenantId);
        $after = ContentModerationService::getStats($this->testTenantId);

        $this->assertSame($before['pending'] + 1, $after['pending']);
        $this->assertSame($before['total'] + 1, $after['total']);
    }

    // --- getReports() ---

    public function test_getReports_returns_items_and_total(): void
    {
        $author = User::factory()->forTenant($this->testTenantId)->create();
        $item   = $this->makePendingItem((int) $author->id, 'post', 70);

        TenantContext::setById($this->testTenantId);
        $reports = ContentModerationService::getReports($this->testTenantId, ['status' => 'pending', 'limit' => 50]);

        $this->assertArrayHasKey('items', $reports);
        $this->assertArrayHasKey('total', $reports);
        $this->assertIsArray($reports['items']);
        $this->assertIsInt($reports['total']);
        $this->assertGreaterThanOrEqual(1, $reports['total']);

        $ids = array_column($reports['items'], 'id');
        $this->assertContains($item->id, $ids);
    }

    // --- getQueue() ---

    public function test_getQueue_returns_shaped_items(): void
    {
        $author = User::factory()->forTenant($this->testTenantId)->create();
        $item   = $this->makePendingItem((int) $author->id, 'post', 80);

        TenantContext::setById($this->testTenantId);
        $queue = ContentModerationService::getQueue($this->testTenantId, ['status' => 'pending'], 50, 0);

        $this->assertArrayHasKey('items', $queue);
        $this->assertArrayHasKey('total', $queue);
        $this->assertIsArray($queue['items']);
        $this->assertGreaterThanOrEqual(1, $queue['total']);

        $row = null;
        foreach ($queue['items'] as $candidate) {
            if ((int) $candidate['id'] === (int) $item->id) {
                $row = $candidate;
                break;
            }
        }
        $this->assertNotNull($row, 'Inserted pending item should appear in the queue');

        foreach (['id', 'content_type', 'content_id', 'title', 'status', 'author', 'auto_flagged', 'flag_reason', 'reviewer', 'reviewed_at', 'rejection_reason', 'created_at', 'updated_at'] as $key) {
            $this->assertArrayHasKey($key, $row);
        }
        $this->assertSame('post', $row['content_type']);
        $this->assertSame(80, $row['content_id']);
        $this->assertSame((int) $author->id, $row['author']['id']);
    }

    // --- getModerationSettings() / updateSettings() round-trip ---

    public function test_getModerationSettings_returns_all_boolean_keys(): void
    {
        TenantContext::setById($this->testTenantId);
        $settings = ContentModerationService::getModerationSettings($this->testTenantId);

        foreach (['enabled', 'require_post', 'require_listing', 'require_event', 'require_comment', 'auto_filter'] as $key) {
            $this->assertArrayHasKey($key, $settings);
            $this->assertIsBool($settings[$key]);
        }
    }

    public function test_updateSettings_persists_and_is_readable(): void
    {
        // Start from a known-clean state for this tenant's moderation settings.
        DB::table('tenant_settings')
            ->where('tenant_id', $this->testTenantId)
            ->where('setting_key', 'LIKE', 'moderation.%')
            ->delete();

        $result = ContentModerationService::updateSettings($this->testTenantId, [
            'enabled'     => true,
            'auto_filter' => true,
        ]);
        $this->assertTrue($result);

        // Real round-trip: the written values come back from the DB, not a mock.
        $settings = ContentModerationService::getModerationSettings($this->testTenantId);
        $this->assertTrue($settings['enabled']);
        $this->assertTrue($settings['auto_filter']);
        // Keys that were not set stay at their default (false).
        $this->assertFalse($settings['require_post']);

        // Direct row check confirms updateOrInsert wrote a real row.
        $this->assertSame('1', (string) DB::table('tenant_settings')
            ->where('tenant_id', $this->testTenantId)
            ->where('setting_key', 'moderation.enabled')
            ->value('setting_value'));
    }
}
