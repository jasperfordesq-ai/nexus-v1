<?php
// Copyright © 2024–2026 Jasper Ford
// SPDX-License-Identifier: AGPL-3.0-or-later
// Author: Jasper Ford
// See NOTICE file for attribution and acknowledgements.

namespace Tests\Laravel\Unit\Services;

use App\Services\PostViewService;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;
use Tests\Laravel\TestCase;

class PostViewServiceTest extends TestCase
{
    private PostViewService $service;

    protected function setUp(): void
    {
        parent::setUp();
        $this->service = new PostViewService();
    }

    // ── recordView: debounce via cache ───────────────────────────────

    public function test_recordView_skips_when_cache_hit(): void
    {
        Cache::shouldReceive('has')->once()->andReturn(true);
        // DB::table MUST NOT be called — if it were, mock would miss
        DB::shouldReceive('table')->never();
        Cache::shouldReceive('put')->never();

        $this->service->recordView(10, 5, 'ip-hash');
        $this->assertTrue(true);
    }

    public function test_recordView_logged_in_inserts_and_increments_counter(): void
    {
        Cache::shouldReceive('has')->once()->andReturn(false);
        Cache::shouldReceive('put')->once();

        // First call: post_views insert
        DB::shouldReceive('table')->with('post_views')->once()->andReturnSelf();
        DB::shouldReceive('insertOrIgnore')->once()->andReturn(1);

        // Second call: feed_posts increment
        DB::shouldReceive('table')->with('feed_posts')->once()->andReturnSelf();
        DB::shouldReceive('where')->twice()->andReturnSelf();
        DB::shouldReceive('increment')->with('views_count')->once()->andReturn(1);

        $this->service->recordView(10, 5, 'ip-hash');
        $this->assertTrue(true);
    }

    public function test_recordView_anonymous_uses_ip_hash(): void
    {
        Cache::shouldReceive('has')->once()->andReturn(false);
        Cache::shouldReceive('put')->once();

        DB::shouldReceive('table')->with('post_views')->once()->andReturnSelf();
        DB::shouldReceive('insertOrIgnore')->once()
            ->withArgs(function ($data) {
                return $data['user_id'] === null
                    && $data['ip_hash'] === 'hash-xyz';
            })
            ->andReturn(1);

        DB::shouldReceive('table')->with('feed_posts')->once()->andReturnSelf();
        DB::shouldReceive('where')->twice()->andReturnSelf();
        DB::shouldReceive('increment')->once()->andReturn(1);

        $this->service->recordView(10, null, 'hash-xyz');
        $this->assertTrue(true);
    }

    public function test_recordView_skips_counter_when_insert_is_duplicate(): void
    {
        Cache::shouldReceive('has')->once()->andReturn(false);
        Cache::shouldReceive('put')->once();

        DB::shouldReceive('table')->with('post_views')->once()->andReturnSelf();
        DB::shouldReceive('insertOrIgnore')->once()->andReturn(0); // already exists

        // increment MUST NOT be called
        DB::shouldReceive('table')->with('feed_posts')->never();

        $this->service->recordView(10, 5, 'ip-hash');
        $this->assertTrue(true);
    }

    // ── getViewCount ────────────────────────────────────────────────

    public function test_getViewCount_returns_value(): void
    {
        DB::shouldReceive('table')->with('feed_posts')->andReturnSelf();
        DB::shouldReceive('where')->andReturnSelf();
        DB::shouldReceive('value')->with('views_count')->andReturn(42);

        $this->assertSame(42, $this->service->getViewCount(10));
    }

    public function test_getViewCount_returns_zero_when_null(): void
    {
        DB::shouldReceive('table')->with('feed_posts')->andReturnSelf();
        DB::shouldReceive('where')->andReturnSelf();
        DB::shouldReceive('value')->with('views_count')->andReturn(null);

        $this->assertSame(0, $this->service->getViewCount(10));
    }
}
