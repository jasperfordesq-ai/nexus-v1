<?php
// Copyright © 2024–2026 Jasper Ford
// SPDX-License-Identifier: AGPL-3.0-or-later
// Author: Jasper Ford
// See NOTICE file for attribution and acknowledgements.

namespace Tests\Laravel\Unit\Observers;

use App\Models\Group;
use App\Observers\GroupObserver;
use App\Services\SearchService;
use Illuminate\Support\Facades\Log;
use Mockery;
use Tests\Laravel\TestCase;

/**
 * @runInSeparateProcess
 * @preserveGlobalState disabled
 */
class GroupObserverTest extends TestCase
{
    public function test_created_indexes_group(): void
    {
        $group = new Group();
        $group->id = 5;

        $searchMock = Mockery::mock('alias:' . SearchService::class);
        $searchMock->shouldReceive('indexGroup')->once()->with($group);

        (new GroupObserver())->created($group);

        $this->assertTrue(true);
    }

    public function test_updated_skips_when_no_searchable_field_dirty(): void
    {
        $group = Mockery::mock(Group::class)->makePartial();
        $group->id = 5;
        $group->shouldReceive('getDirty')->andReturn(['member_count' => 25]);

        $searchMock = Mockery::mock('alias:' . SearchService::class);
        $searchMock->shouldNotReceive('indexGroup');

        (new GroupObserver())->updated($group);

        $this->assertTrue(true);
    }

    public function test_updated_reindexes_when_name_changes(): void
    {
        $group = Mockery::mock(Group::class)->makePartial();
        $group->id = 5;
        $group->shouldReceive('getDirty')->andReturn(['name' => 'New Name']);

        $searchMock = Mockery::mock('alias:' . SearchService::class);
        $searchMock->shouldReceive('indexGroup')->once()->with($group);

        (new GroupObserver())->updated($group);

        $this->assertTrue(true);
    }

    public function test_updated_reindexes_when_visibility_changes(): void
    {
        $group = Mockery::mock(Group::class)->makePartial();
        $group->id = 5;
        $group->shouldReceive('getDirty')->andReturn(['visibility' => 'private']);

        $searchMock = Mockery::mock('alias:' . SearchService::class);
        $searchMock->shouldReceive('indexGroup')->once()->with($group);

        (new GroupObserver())->updated($group);

        $this->assertTrue(true);
    }

    public function test_deleted_removes_group_from_index(): void
    {
        $group = new Group();
        $group->id = 50;

        $searchMock = Mockery::mock('alias:' . SearchService::class);
        $searchMock->shouldReceive('removeGroup')->once()->with(50);

        (new GroupObserver())->deleted($group);

        $this->assertTrue(true);
    }

    public function test_deleted_logs_on_exception(): void
    {
        $group = new Group();
        $group->id = 50;

        $searchMock = Mockery::mock('alias:' . SearchService::class);
        $searchMock->shouldReceive('removeGroup')->andThrow(new \RuntimeException('x'));

        Log::shouldReceive('error')
            ->once()
            ->with('GroupObserver: failed to remove deleted group from index', Mockery::type('array'));

        (new GroupObserver())->deleted($group);

        $this->assertTrue(true);
    }
}
