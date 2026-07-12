<?php
// Copyright © 2024–2026 Jasper Ford
// SPDX-License-Identifier: AGPL-3.0-or-later
// Author: Jasper Ford
// See NOTICE file for attribution and acknowledgements.

declare(strict_types=1);

namespace Tests\Laravel\Unit\Observers;

use App\Models\Event;
use App\Models\User;
use App\Observers\EventObserver;
use App\Services\EventSearchIndexService;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Queue;
use Mockery;
use Tests\Laravel\TestCase;

final class EventObserverTest extends TestCase
{
    private Mockery\MockInterface $searchMock;

    /** @var list<int> */
    private array $eventIds = [];

    /** @var list<int> */
    private array $userIds = [];

    protected function setUp(): void
    {
        parent::setUp();
        Queue::fake();
        $this->searchMock = Mockery::mock(EventSearchIndexService::class);
    }

    protected function tearDown(): void
    {
        if (DB::transactionLevel() > 0) {
            DB::rollBack();
        }
        if ($this->eventIds !== []) {
            DB::table('feed_activity')
                ->where('tenant_id', $this->testTenantId)
                ->where('source_type', 'event')
                ->whereIn('source_id', $this->eventIds)
                ->delete();
            DB::table('events')->whereIn('id', $this->eventIds)->delete();
        }
        if ($this->userIds !== []) {
            DB::table('users')->whereIn('id', $this->userIds)->delete();
        }
        parent::tearDown();
    }

    public function test_created_indexes_discoverable_event_after_commit(): void
    {
        $event = $this->event();
        $this->searchMock->shouldReceive('synchronize')
            ->once()
            ->with(Mockery::on(fn (Event $current): bool => (int) $current->id === (int) $event->id));

        DB::beginTransaction();
        (new EventObserver($this->searchMock))->created($event);
        $this->searchMock->shouldNotHaveReceived('synchronize');
        DB::commit();

        $this->searchMock->shouldHaveReceived('synchronize')->once();
        $activity = DB::table('feed_activity')
            ->where('tenant_id', $this->testTenantId)
            ->where('source_type', 'event')
            ->where('source_id', (int) $event->id)
            ->first();
        self::assertNotNull($activity);
        self::assertSame(1, (int) $activity->is_visible);
        self::assertSame((string) $event->title, (string) $activity->title);
        self::assertSame('Observer venue', json_decode((string) $activity->metadata, true)['location'] ?? null);
    }

    public function test_rolled_back_create_never_touches_external_index(): void
    {
        $event = $this->event();

        DB::beginTransaction();
        (new EventObserver($this->searchMock))->created($event);
        DB::rollBack();

        $this->searchMock->shouldNotHaveReceived('synchronize');
        $this->searchMock->shouldNotHaveReceived('remove');
        self::assertFalse(DB::table('feed_activity')
            ->where('tenant_id', $this->testTenantId)
            ->where('source_type', 'event')
            ->where('source_id', (int) $event->id)
            ->exists());
    }

    public function test_created_logs_search_failure_without_rethrowing(): void
    {
        $event = $this->event();
        $this->searchMock->shouldReceive('synchronize')->once()->andThrow(new \RuntimeException('fail'));
        Log::shouldReceive('error')
            ->once()
            ->with('EventObserver: failed to synchronize event search indexes', Mockery::type('array'));

        (new EventObserver($this->searchMock))->created($event);

        $this->assertTrue(true);
    }

    public function test_updated_skips_when_no_search_affecting_field_is_dirty(): void
    {
        $event = $this->event();
        $event->setAttribute('updated_at', now()->addMinute());

        (new EventObserver($this->searchMock))->updated($event);

        $this->searchMock->shouldNotHaveReceived('synchronize');
    }

    public function test_updated_reindexes_when_title_or_lifecycle_changes(): void
    {
        $titleEvent = $this->event();
        $lifecycleEvent = $this->event();
        $this->searchMock->shouldReceive('synchronize')->twice();

        $titleEvent->setAttribute('title', 'Changed search title');
        (new EventObserver($this->searchMock))->updated($titleEvent);
        $lifecycleEvent->forceFill(['operational_status' => 'postponed', 'status' => 'cancelled']);
        (new EventObserver($this->searchMock))->updated($lifecycleEvent);

        $this->searchMock->shouldHaveReceived('synchronize')->twice();
    }

    public function test_updated_hides_a_retained_event_that_is_no_longer_discoverable(): void
    {
        $event = $this->event();
        DB::table('feed_activity')->insert([
            'tenant_id' => $this->testTenantId,
            'user_id' => (int) $event->user_id,
            'source_type' => 'event',
            'source_id' => (int) $event->id,
            'title' => (string) $event->title,
            'content' => (string) $event->description,
            'metadata' => '{}',
            'is_visible' => 1,
            'created_at' => now(),
        ]);
        $event->forceFill([
            'publication_status' => 'published',
            'operational_status' => 'cancelled',
            'status' => 'cancelled',
        ]);
        DB::table('events')->where('id', (int) $event->id)->update([
            'publication_status' => 'published',
            'operational_status' => 'cancelled',
            'status' => 'cancelled',
        ]);
        $this->searchMock->shouldReceive('synchronize')->once();

        (new EventObserver($this->searchMock))->updated($event);

        self::assertSame(0, (int) DB::table('feed_activity')
            ->where('tenant_id', $this->testTenantId)
            ->where('source_type', 'event')
            ->where('source_id', (int) $event->id)
            ->value('is_visible'));
    }

    public function test_deleted_removes_event_only_after_commit(): void
    {
        $event = $this->event();
        DB::table('feed_activity')->insert([
            'tenant_id' => $this->testTenantId,
            'user_id' => (int) $event->user_id,
            'source_type' => 'event',
            'source_id' => (int) $event->id,
            'title' => (string) $event->title,
            'content' => (string) $event->description,
            'metadata' => '{}',
            'is_visible' => 1,
            'created_at' => now(),
        ]);
        $this->searchMock->shouldReceive('remove')->once()->with((int) $event->id);

        DB::beginTransaction();
        (new EventObserver($this->searchMock))->deleted($event);
        $this->searchMock->shouldNotHaveReceived('remove');
        DB::commit();

        $this->searchMock->shouldHaveReceived('remove')->once();
        self::assertFalse(DB::table('feed_activity')
            ->where('tenant_id', $this->testTenantId)
            ->where('source_type', 'event')
            ->where('source_id', (int) $event->id)
            ->exists());
    }

    private function event(array $overrides = []): Event
    {
        $userId = (int) DB::table('users')
            ->where('tenant_id', $this->testTenantId)
            ->where('status', 'active')
            ->value('id');
        if ($userId <= 0) {
            $user = User::factory()->forTenant($this->testTenantId)->create([
                'status' => 'active',
                'is_approved' => true,
            ]);
            $userId = (int) $user->id;
            $this->userIds[] = $userId;
        }

        $id = (int) DB::table('events')->insertGetId(array_merge([
            'tenant_id' => $this->testTenantId,
            'user_id' => $userId,
            'title' => 'Observer search event ' . uniqid('', true),
            'description' => 'Observer event description',
            'location' => 'Observer venue',
            'status' => 'active',
            'publication_status' => 'published',
            'operational_status' => 'scheduled',
            'lifecycle_version' => 1,
            'is_recurring_template' => 0,
            'start_time' => now()->addWeek(),
            'end_time' => now()->addWeek()->addHour(),
            'created_at' => now(),
            'updated_at' => now(),
        ], $overrides));
        $this->eventIds[] = $id;

        return Event::withoutGlobalScopes()->findOrFail($id);
    }
}
