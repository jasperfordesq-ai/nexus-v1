<?php
// Copyright © 2024–2026 Jasper Ford
// SPDX-License-Identifier: AGPL-3.0-or-later
// Author: Jasper Ford
// See NOTICE file for attribution and acknowledgements.

namespace Tests\Laravel\Unit\Observers;

use App\Models\Event;
use App\Observers\EventObserver;
use App\Services\SearchService;
use Illuminate\Support\Facades\Log;
use Mockery;
use Tests\Laravel\TestCase;

/**
 * @runInSeparateProcess
 * @preserveGlobalState disabled
 */
class EventObserverTest extends TestCase
{
    public function test_created_indexes_event(): void
    {
        $event = new Event();
        $event->id = 7;

        $searchMock = Mockery::mock('alias:' . SearchService::class);
        $searchMock->shouldReceive('indexEvent')->once()->with($event);

        (new EventObserver())->created($event);

        $this->assertTrue(true);
    }

    public function test_created_logs_on_exception(): void
    {
        $event = new Event();
        $event->id = 7;

        $searchMock = Mockery::mock('alias:' . SearchService::class);
        $searchMock->shouldReceive('indexEvent')->andThrow(new \RuntimeException('fail'));

        Log::shouldReceive('error')
            ->once()
            ->with('EventObserver: failed to index new event', Mockery::type('array'));

        (new EventObserver())->created($event);

        $this->assertTrue(true);
    }

    public function test_updated_skips_when_no_searchable_field_dirty(): void
    {
        $event = Mockery::mock(Event::class)->makePartial();
        $event->id = 7;
        $event->shouldReceive('getDirty')->andReturn(['updated_at' => '2026-04-12']);

        $searchMock = Mockery::mock('alias:' . SearchService::class);
        $searchMock->shouldNotReceive('indexEvent');

        (new EventObserver())->updated($event);

        $this->assertTrue(true);
    }

    public function test_updated_reindexes_when_title_changed(): void
    {
        $event = Mockery::mock(Event::class)->makePartial();
        $event->id = 7;
        $event->shouldReceive('getDirty')->andReturn(['title' => 'New Title']);

        $searchMock = Mockery::mock('alias:' . SearchService::class);
        $searchMock->shouldReceive('indexEvent')->once()->with($event);

        (new EventObserver())->updated($event);

        $this->assertTrue(true);
    }

    public function test_deleted_removes_event_from_index(): void
    {
        $event = new Event();
        $event->id = 99;

        $searchMock = Mockery::mock('alias:' . SearchService::class);
        $searchMock->shouldReceive('removeEvent')->once()->with(99);

        (new EventObserver())->deleted($event);

        $this->assertTrue(true);
    }
}
