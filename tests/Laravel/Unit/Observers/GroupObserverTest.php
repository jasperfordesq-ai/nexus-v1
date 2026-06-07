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
 * @runTestsInSeparateProcesses
 * @preserveGlobalState disabled
 */
class GroupObserverTest extends TestCase
{
    private $searchMock;

    protected function setUp(): void
    {
        // App\Services\SearchService may already be autoloaded by app boot or an
        // earlier test in the combined run, so the alias mock MUST be created
        // before parent::setUp() and tolerate the class already existing.
        // shouldIgnoreMissing() makes boot-time/static calls no-ops; per-test
        // expectations are layered on the shared instance below.
        $this->searchMock = Mockery::mock('alias:' . SearchService::class)->shouldIgnoreMissing();
        parent::setUp();
    }

    public function test_created_indexes_group(): void
    {
        $group = new Group();
        $group->id = 5;

        $this->searchMock->shouldReceive('indexGroup')->once()->with($group);

        (new GroupObserver())->created($group);

        $this->assertTrue(true);
    }

    public function test_updated_skips_when_no_searchable_field_dirty(): void
    {
        $group = Mockery::mock(Group::class)->makePartial();
        $group->id = 5;
        $group->shouldReceive('getDirty')->andReturn(['member_count' => 25]);

        $this->searchMock->shouldNotReceive('indexGroup');

        (new GroupObserver())->updated($group);

        $this->assertTrue(true);
    }

    public function test_updated_reindexes_when_name_changes(): void
    {
        $group = Mockery::mock(Group::class)->makePartial();
        $group->id = 5;
        $group->shouldReceive('getDirty')->andReturn(['name' => 'New Name']);

        $this->searchMock->shouldReceive('indexGroup')->once()->with($group);

        (new GroupObserver())->updated($group);

        $this->assertTrue(true);
    }

    public function test_updated_reindexes_when_visibility_changes(): void
    {
        $group = Mockery::mock(Group::class)->makePartial();
        $group->id = 5;
        $group->shouldReceive('getDirty')->andReturn(['visibility' => 'private']);

        $this->searchMock->shouldReceive('indexGroup')->once()->with($group);

        (new GroupObserver())->updated($group);

        $this->assertTrue(true);
    }

    public function test_deleted_removes_group_from_index(): void
    {
        $group = new Group();
        $group->id = 50;

        $this->searchMock->shouldReceive('removeGroup')->once()->with(50);

        (new GroupObserver())->deleted($group);

        $this->assertTrue(true);
    }

    public function test_deleted_logs_on_exception(): void
    {
        $group = new Group();
        $group->id = 50;

        $this->searchMock->shouldReceive('removeGroup')->andThrow(new \RuntimeException('x'));

        Log::shouldReceive('error')
            ->once()
            ->with('GroupObserver: failed to remove deleted group from index', Mockery::type('array'));

        (new GroupObserver())->deleted($group);

        $this->assertTrue(true);
    }
}
