<?php
// Copyright © 2024–2026 Jasper Ford
// SPDX-License-Identifier: AGPL-3.0-or-later
// Author: Jasper Ford
// See NOTICE file for attribution and acknowledgements.

namespace Tests\Laravel\Unit\Observers;

use App\Models\User;
use App\Observers\UserObserver;
use App\Services\SearchService;
use Illuminate\Support\Facades\Log;
use Mockery;
use Tests\Laravel\TestCase;

/**
 * @runInSeparateProcess
 * @preserveGlobalState disabled
 */
class UserObserverTest extends TestCase
{
    public function test_created_indexes_new_user(): void
    {
        $user = new User();
        $user->id = 123;

        $searchMock = Mockery::mock('alias:' . SearchService::class);
        $searchMock->shouldReceive('indexUser')->once()->with($user);

        (new UserObserver())->created($user);

        $this->assertTrue(true);
    }

    public function test_created_catches_exception_and_logs(): void
    {
        $user = new User();
        $user->id = 123;

        $searchMock = Mockery::mock('alias:' . SearchService::class);
        $searchMock->shouldReceive('indexUser')->andThrow(new \RuntimeException('Meili down'));

        Log::shouldReceive('error')
            ->once()
            ->with('UserObserver: failed to index new user', Mockery::type('array'));

        (new UserObserver())->created($user);

        $this->assertTrue(true);
    }

    public function test_updated_skips_reindex_when_no_searchable_fields_dirty(): void
    {
        $user = Mockery::mock(User::class)->makePartial();
        $user->id = 123;
        $user->shouldReceive('getDirty')->andReturn(['last_login_at' => '2026-04-12']);

        // SearchService should NOT be called — assert by not declaring any expectations
        $searchMock = Mockery::mock('alias:' . SearchService::class);
        $searchMock->shouldNotReceive('indexUser');

        (new UserObserver())->updated($user);

        $this->assertTrue(true);
    }

    public function test_updated_reindexes_when_searchable_field_changed(): void
    {
        $user = Mockery::mock(User::class)->makePartial();
        $user->id = 123;
        $user->shouldReceive('getDirty')->andReturn(['first_name' => 'Alice', 'email' => 'a@b.c']);

        $searchMock = Mockery::mock('alias:' . SearchService::class);
        $searchMock->shouldReceive('indexUser')->once()->with($user);

        (new UserObserver())->updated($user);

        $this->assertTrue(true);
    }

    public function test_deleted_removes_user_from_index(): void
    {
        $user = new User();
        $user->id = 456;

        $searchMock = Mockery::mock('alias:' . SearchService::class);
        $searchMock->shouldReceive('removeUser')->once()->with(456);

        (new UserObserver())->deleted($user);

        $this->assertTrue(true);
    }

    public function test_deleted_catches_exception_and_logs(): void
    {
        $user = new User();
        $user->id = 456;

        $searchMock = Mockery::mock('alias:' . SearchService::class);
        $searchMock->shouldReceive('removeUser')->andThrow(new \RuntimeException('fail'));

        Log::shouldReceive('error')
            ->once()
            ->with('UserObserver: failed to remove deleted user from index', Mockery::type('array'));

        (new UserObserver())->deleted($user);

        $this->assertTrue(true);
    }
}
