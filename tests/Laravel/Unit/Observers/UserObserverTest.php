<?php
// Copyright © 2024–2026 Jasper Ford
// SPDX-License-Identifier: AGPL-3.0-or-later
// Author: Jasper Ford
// See NOTICE file for attribution and acknowledgements.

namespace Tests\Laravel\Unit\Observers;

use App\Jobs\SyncUserSearchIndexJob;
use App\Models\User;
use App\Observers\UserObserver;
use Illuminate\Support\Facades\Queue;
use Mockery;
use Tests\Laravel\TestCase;

/**
 * UserObserver keeps the Meilisearch users index in sync by dispatching
 * SyncUserSearchIndexJob (queue-backed, auto-retrying) rather than calling
 * SearchService inline — so a transient Meilisearch outage during signup or a
 * profile edit doesn't leave the user missing from search forever.
 *
 * @runTestsInSeparateProcesses
 * @preserveGlobalState disabled
 */
class UserObserverTest extends TestCase
{
    public function test_created_dispatches_index_job(): void
    {
        Queue::fake();

        $user = new User();
        $user->id = 123;

        (new UserObserver())->created($user);

        Queue::assertPushed(SyncUserSearchIndexJob::class, function (SyncUserSearchIndexJob $job) {
            return $job->userId === 123 && $job->action === 'index';
        });
    }

    public function test_updated_skips_reindex_when_no_searchable_fields_dirty(): void
    {
        Queue::fake();

        $user = Mockery::mock(User::class)->makePartial();
        $user->id = 123;
        $user->shouldReceive('getDirty')->andReturn(['last_login_at' => '2026-04-12']);

        (new UserObserver())->updated($user);

        // Non-searchable field changed → no search index job dispatched.
        Queue::assertNotPushed(SyncUserSearchIndexJob::class);
    }

    public function test_updated_reindexes_when_searchable_field_changed(): void
    {
        Queue::fake();

        $user = Mockery::mock(User::class)->makePartial();
        $user->id = 123;
        $user->shouldReceive('getDirty')->andReturn(['first_name' => 'Alice', 'email' => 'a@b.c']);

        (new UserObserver())->updated($user);

        Queue::assertPushed(SyncUserSearchIndexJob::class, function (SyncUserSearchIndexJob $job) {
            return $job->userId === 123 && $job->action === 'index';
        });
    }

    public function test_deleted_dispatches_remove_job(): void
    {
        Queue::fake();

        $user = new User();
        $user->id = 456;

        (new UserObserver())->deleted($user);

        Queue::assertPushed(SyncUserSearchIndexJob::class, function (SyncUserSearchIndexJob $job) {
            return $job->userId === 456 && $job->action === 'remove';
        });
    }
}
