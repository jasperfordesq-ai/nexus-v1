<?php
// Copyright © 2024–2026 Jasper Ford
// SPDX-License-Identifier: AGPL-3.0-or-later
// Author: Jasper Ford
// See NOTICE file for attribution and acknowledgements.

namespace Tests\Laravel\Unit\Services;

use App\Core\TenantContext;
use App\Models\User;
use App\Services\PostViewService;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;
use Tests\Laravel\TestCase;

/**
 * Unit tests for PostViewService.
 *
 * The recordView tests run against real seeded rows (DatabaseTransactions)
 * because recordView now calls FeedItemTables::canView('post', …) — a real DB
 * visibility check — before debouncing/inserting. That guard cannot be
 * satisfied under a fully-mocked DB facade, so these tests exercise the
 * service against a genuine (rolled-back) post and the array cache driver.
 *
 * getViewCount stays mock-isolated (it never touches canView).
 */
class PostViewServiceTest extends TestCase
{
    use DatabaseTransactions;

    private PostViewService $service;

    protected function setUp(): void
    {
        parent::setUp();
        $this->service = new PostViewService();
        Cache::flush();
    }

    /**
     * Create a real, active user under the test tenant and return its id.
     * Re-pins the tenant context in case a model observer reset it.
     */
    private function seedUser(): int
    {
        $user = User::factory()->forTenant($this->testTenantId)->create([
            'status'      => 'active',
            'is_approved' => true,
        ]);
        TenantContext::setById($this->testTenantId);

        return (int) $user->id;
    }

    /**
     * Seed a public, viewable feed_posts row (with views_count = 0) so
     * FeedItemTables::canView('post', …) returns true. Returns the post id.
     */
    private function seedViewablePost(int $authorId): int
    {
        return DB::table('feed_posts')->insertGetId([
            'tenant_id'   => $this->testTenantId,
            'user_id'     => $authorId,
            'content'     => 'Post view service unit-test post',
            'type'        => 'post',
            'visibility'  => 'public',
            'views_count' => 0,
            'created_at'  => now(),
            'updated_at'  => now(),
        ]);
    }

    private function viewsCount(int $postId): int
    {
        return (int) DB::table('feed_posts')
            ->where('id', $postId)
            ->where('tenant_id', $this->testTenantId)
            ->value('views_count');
    }

    // ── recordView: debounce via cache ───────────────────────────────

    public function test_recordView_skips_when_cache_hit(): void
    {
        $userId = $this->seedUser();
        $postId = $this->seedViewablePost($userId);

        // Pre-seed the debounce cache key so recordView short-circuits.
        $cacheKey = "post_view:{$this->testTenantId}:{$postId}:u:{$userId}";
        Cache::put($cacheKey, true, now()->addMinutes(30));

        $this->service->recordView($postId, $userId, 'ip-hash');

        // No view row written and the counter stays at zero.
        $this->assertFalse(
            DB::table('post_views')
                ->where('tenant_id', $this->testTenantId)
                ->where('post_id', $postId)
                ->exists()
        );
        $this->assertSame(0, $this->viewsCount($postId));
    }

    public function test_recordView_logged_in_inserts_and_increments_counter(): void
    {
        $userId = $this->seedUser();
        $postId = $this->seedViewablePost($userId);

        $this->service->recordView($postId, $userId, 'ip-hash');

        // A logged-in view row is stored keyed by user_id (ip_hash null).
        $this->assertTrue(
            DB::table('post_views')
                ->where('tenant_id', $this->testTenantId)
                ->where('post_id', $postId)
                ->where('user_id', $userId)
                ->whereNull('ip_hash')
                ->exists()
        );
        $this->assertSame(1, $this->viewsCount($postId));
    }

    public function test_recordView_anonymous_uses_ip_hash(): void
    {
        $authorId = $this->seedUser();
        $postId = $this->seedViewablePost($authorId);

        $this->service->recordView($postId, null, 'hash-xyz');

        // An anonymous view row is keyed by ip_hash (user_id null).
        $this->assertTrue(
            DB::table('post_views')
                ->where('tenant_id', $this->testTenantId)
                ->where('post_id', $postId)
                ->whereNull('user_id')
                ->where('ip_hash', 'hash-xyz')
                ->exists()
        );
        $this->assertSame(1, $this->viewsCount($postId));
    }

    public function test_recordView_skips_counter_when_insert_is_duplicate(): void
    {
        $userId = $this->seedUser();
        $postId = $this->seedViewablePost($userId);

        // Pre-insert the same (tenant, post, user) view row so the service's
        // insertOrIgnore is a no-op and the counter must NOT increment.
        DB::table('post_views')->insert([
            'tenant_id' => $this->testTenantId,
            'post_id'   => $postId,
            'user_id'   => $userId,
            'ip_hash'   => null,
            'viewed_at' => now(),
        ]);

        $this->service->recordView($postId, $userId, 'ip-hash');

        $this->assertSame(0, $this->viewsCount($postId));
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
