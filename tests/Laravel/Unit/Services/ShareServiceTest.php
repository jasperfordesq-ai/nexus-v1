<?php
// Copyright © 2024–2026 Jasper Ford
// SPDX-License-Identifier: AGPL-3.0-or-later
// Author: Jasper Ford
// See NOTICE file for attribution and acknowledgements.

namespace Tests\Laravel\Unit\Services;

use App\Core\TenantContext;
use App\Services\ShareService;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Illuminate\Support\Facades\DB;
use Tests\Laravel\TestCase;

/**
 * ShareServiceTest
 *
 * Strategy:
 *  - validateType / VALID_TYPES constant checks need no DB at all.
 *  - isShared / getShareCount / batchShareCount / batchIsShared operate purely
 *    on the post_shares table — we insert raw rows and assert counts/booleans.
 *  - toggle ON/OFF requires a real feed_posts row (so FeedItemTables::canViewPost
 *    can find it) plus two distinct users, one as owner, one as sharer.
 *    Self-share is tested by making sharer == owner.
 *  - resolveOwnerId is exercised through the toggle but also directly.
 *
 * Skipped: notifyOwner (calls SocialNotificationService — fire-and-forget,
 *   wrapped in try/catch; notification side-effects are tested in
 *   SocialNotificationServiceTest).
 */
class ShareServiceTest extends TestCase
{
    use DatabaseTransactions;

    private const TENANT_ID = 2;

    private ShareService $svc;

    protected function setUp(): void
    {
        parent::setUp();
        TenantContext::setById(self::TENANT_ID);
        $this->svc = new ShareService();
    }

    // ── helpers ───────────────────────────────────────────────────────────────

    private function insertUser(): int
    {
        $uid = uniqid('share_', true);
        return DB::table('users')->insertGetId([
            'tenant_id'  => self::TENANT_ID,
            'name'       => 'Share User ' . $uid,
            'first_name' => 'Share',
            'last_name'  => 'User',
            'email'      => $uid . '@share.test',
            'status'     => 'active',
            'balance'    => 0,
            'role'       => 'member',
            'is_approved'=> 1,
            'created_at' => now(),
            'updated_at' => now(),
        ]);
    }

    /**
     * Insert a feed_post row and return its id.
     * The post is public and published so canView passes.
     */
    private function insertFeedPost(int $userId): int
    {
        return DB::table('feed_posts')->insertGetId([
            'tenant_id'      => self::TENANT_ID,
            'user_id'        => $userId,
            'content'        => 'Test share post',
            'visibility'     => 'public',
            'publish_status' => 'published',
            'is_hidden'      => 0,
            'share_count'    => 0,
            'created_at'     => now(),
        ]);
    }

    /**
     * Directly insert a post_shares row (bypassing toggle) for low-level tests.
     */
    private function insertShare(int $userId, string $type, int $originalId): int
    {
        return DB::table('post_shares')->insertGetId([
            'tenant_id'        => self::TENANT_ID,
            'user_id'          => $userId,
            'original_type'    => $type,
            'original_post_id' => $originalId,
            'post_id'          => 0,
            'created_at'       => now(),
        ]);
    }

    // ── validateType ──────────────────────────────────────────────────────────

    public function test_validateType_passes_for_every_valid_type(): void
    {
        foreach (ShareService::VALID_TYPES as $type) {
            // Should not throw.
            $this->svc->validateType($type);
        }
        $this->assertCount(10, ShareService::VALID_TYPES);
    }

    public function test_validateType_throws_for_unknown_type(): void
    {
        $this->expectException(\InvalidArgumentException::class);
        $this->expectExceptionMessage('invalid_shareable_type');
        $this->svc->validateType('unknown_type');
    }

    public function test_validateType_throws_for_empty_string(): void
    {
        $this->expectException(\InvalidArgumentException::class);
        $this->svc->validateType('');
    }

    // ── isShared ──────────────────────────────────────────────────────────────

    public function test_isShared_returns_false_when_no_share_exists(): void
    {
        $userId = $this->insertUser();
        $result = $this->svc->isShared($userId, 'listing', 9999999);
        $this->assertFalse($result);
    }

    public function test_isShared_returns_true_when_share_row_exists(): void
    {
        $userId = $this->insertUser();
        $this->insertShare($userId, 'listing', 8888888);

        $result = $this->svc->isShared($userId, 'listing', 8888888);
        $this->assertTrue($result);
    }

    public function test_isShared_is_tenant_scoped_does_not_see_other_tenant(): void
    {
        $userId = $this->insertUser();
        // Insert share for tenant 1 (different)
        DB::table('post_shares')->insert([
            'tenant_id'        => 1,
            'user_id'          => $userId,
            'original_type'    => 'listing',
            'original_post_id' => 7777777,
            'post_id'          => 0,
            'created_at'       => now(),
        ]);
        // isShared uses tenant 2 (current context)
        $result = $this->svc->isShared($userId, 'listing', 7777777);
        $this->assertFalse($result);
    }

    // ── getShareCount ─────────────────────────────────────────────────────────

    public function test_getShareCount_returns_zero_when_no_shares(): void
    {
        $count = $this->svc->getShareCount('event', 9999999);
        $this->assertSame(0, $count);
    }

    public function test_getShareCount_returns_correct_count(): void
    {
        $u1 = $this->insertUser();
        $u2 = $this->insertUser();
        $this->insertShare($u1, 'event', 1234567);
        $this->insertShare($u2, 'event', 1234567);

        $count = $this->svc->getShareCount('event', 1234567);
        $this->assertSame(2, $count);
    }

    public function test_getShareCount_throws_for_invalid_type(): void
    {
        $this->expectException(\InvalidArgumentException::class);
        $this->svc->getShareCount('nope', 1);
    }

    // ── batchShareCount ───────────────────────────────────────────────────────

    public function test_batchShareCount_returns_empty_array_for_empty_input(): void
    {
        $result = $this->svc->batchShareCount([], self::TENANT_ID);
        $this->assertSame([], $result);
    }

    public function test_batchShareCount_skips_invalid_types_silently(): void
    {
        $result = $this->svc->batchShareCount(['bogus' => [1, 2, 3]], self::TENANT_ID);
        $this->assertSame([], $result);
    }

    public function test_batchShareCount_returns_correct_nested_counts(): void
    {
        $u1 = $this->insertUser();
        $u2 = $this->insertUser();
        $this->insertShare($u1, 'listing', 111111);
        $this->insertShare($u2, 'listing', 111111);
        $this->insertShare($u1, 'job',     222222);

        $result = $this->svc->batchShareCount(
            ['listing' => [111111, 333333], 'job' => [222222]],
            self::TENANT_ID
        );

        $this->assertSame(2, $result['listing'][111111]);
        $this->assertArrayNotHasKey(333333, $result['listing'] ?? []);
        $this->assertSame(1, $result['job'][222222]);
    }

    // ── toggle: share ON then OFF ─────────────────────────────────────────────

    public function test_toggle_creates_share_and_increments_share_count_on_post(): void
    {
        $owner  = $this->insertUser();
        $sharer = $this->insertUser();
        $postId = $this->insertFeedPost($owner);

        $result = $this->svc->toggle($sharer, 'post', $postId);

        $this->assertTrue($result['shared']);
        $this->assertIsInt($result['share_id']);
        $this->assertSame(1, $result['count']);
        $this->assertFalse($result['self_share']);

        $shareCount = DB::table('feed_posts')->where('id', $postId)->value('share_count');
        $this->assertEquals(1, (int) $shareCount);
    }

    public function test_toggle_removes_share_and_decrements_share_count_on_second_call(): void
    {
        $owner  = $this->insertUser();
        $sharer = $this->insertUser();
        $postId = $this->insertFeedPost($owner);

        // Share ON
        $this->svc->toggle($sharer, 'post', $postId);
        // Share OFF
        $result = $this->svc->toggle($sharer, 'post', $postId);

        $this->assertFalse($result['shared']);
        $this->assertNull($result['share_id']);
        $this->assertSame(0, $result['count']);

        $shareCount = DB::table('feed_posts')->where('id', $postId)->value('share_count');
        $this->assertEquals(0, (int) $shareCount);
    }

    public function test_toggle_throws_domain_exception_on_self_share(): void
    {
        $owner  = $this->insertUser();
        $postId = $this->insertFeedPost($owner);

        $this->expectException(\DomainException::class);
        $this->expectExceptionMessage('self_share');
        $this->svc->toggle($owner, 'post', $postId);
    }

    public function test_toggle_throws_invalid_argument_for_unknown_type(): void
    {
        $this->expectException(\InvalidArgumentException::class);
        $this->svc->toggle(1, 'badtype', 1);
    }

    // ── resolveOwnerId ────────────────────────────────────────────────────────

    public function test_resolveOwnerId_returns_null_for_nonexistent_item(): void
    {
        $result = $this->svc->resolveOwnerId('listing', 9999999, self::TENANT_ID);
        $this->assertNull($result);
    }
}
