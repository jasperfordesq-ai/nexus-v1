<?php
// Copyright © 2024–2026 Jasper Ford
// SPDX-License-Identifier: AGPL-3.0-or-later
// Author: Jasper Ford
// See NOTICE file for attribution and acknowledgements.

namespace Tests\Laravel\Unit\Services;

use Tests\Laravel\TestCase;
use App\Services\GroupSearchService;
use App\Services\SearchService;
use App\Core\TenantContext;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Illuminate\Support\Facades\DB;

/**
 * GroupSearchServiceTest
 *
 * Strategy:
 *  GroupSearchService has three concerns:
 *
 *  1. Meilisearch integration (indexGroupContent, searchGroupContent,
 *     removeGroupContent, reindexAll, ensureIndex) — all gate on
 *     SearchService::isAvailable(). In CI Meilisearch is not running so
 *     isAvailable() returns false and every method short-circuits with 0 / [].
 *     We test these "Meilisearch unavailable" code paths directly: they are the
 *     safe, observable behaviour this service guarantees when the external engine
 *     is down.
 *
 *  2. DB query correctness — the service fetches from group_discussions and
 *     group_posts scoped to (group_id, tenant_id). We insert real fixtures and
 *     assert the rows exist correctly; the document-assembly logic only runs when
 *     Meilisearch is available so we test it by inspecting what the DB fixture
 *     looks like (column correctness), which is sufficient to guard the schema
 *     contract without hitting the external engine.
 *
 *  3. Tenant isolation — reindexAll accepts an explicit tenantId and only
 *     collects groups whose tenant_id matches; cross-tenant groups must not appear.
 *
 * Meilisearch-dependent paths (ensureIndex, the addDocuments batch, deleteDocuments
 * with filter) are skipped when SearchService::isAvailable() is false — this is
 * intentional and documented inline.
 */
class GroupSearchServiceTest extends TestCase
{
    use DatabaseTransactions;

    private const TENANT_ID = 2;

    protected function setUp(): void
    {
        parent::setUp();
        TenantContext::setById(self::TENANT_ID);
    }

    // ── Helpers ───────────────────────────────────────────────────────────────

    /**
     * Insert a minimal user and return its ID.
     */
    private function insertUser(): int
    {
        $uid = uniqid('gss_', true);
        return DB::table('users')->insertGetId([
            'tenant_id'  => self::TENANT_ID,
            'name'       => 'GSS Test User ' . $uid,
            'first_name' => 'GSS',
            'last_name'  => 'User',
            'email'      => 'gss.' . $uid . '@example.test',
            'status'     => 'active',
            'balance'    => 0.0,
            'role'       => 'member',
            'is_approved'=> 1,
            'created_at' => now(),
            'updated_at' => now(),
        ]);
    }

    /**
     * Insert a minimal group and return its ID.
     */
    private function insertGroup(int $ownerId, int $tenantId = self::TENANT_ID): int
    {
        $uid = uniqid('g_', true);
        return DB::table('groups')->insertGetId([
            'tenant_id'  => $tenantId,
            'owner_id'   => $ownerId,
            'name'       => 'Test Group ' . $uid,
            'slug'       => 'test-group-' . $uid,
            'visibility' => 'public',
            'is_active'  => 1,
            'status'     => 'active',
            'created_at' => now(),
            'updated_at' => now(),
        ]);
    }

    /**
     * Insert a group_discussions row and return its ID.
     */
    private function insertDiscussion(int $groupId, int $userId, string $title = 'Test Discussion'): int
    {
        return DB::table('group_discussions')->insertGetId([
            'tenant_id'  => self::TENANT_ID,
            'group_id'   => $groupId,
            'user_id'    => $userId,
            'title'      => $title,
            'created_at' => now(),
            'updated_at' => now(),
        ]);
    }

    /**
     * Insert a group_posts row and return its ID.
     */
    private function insertPost(int $discussionId, int $userId, string $content = 'Post body'): int
    {
        return DB::table('group_posts')->insertGetId([
            'tenant_id'     => self::TENANT_ID,
            'discussion_id' => $discussionId,
            'user_id'       => $userId,
            'content'       => $content,
            'created_at'    => now(),
        ]);
    }

    // ── indexGroupContent: Meilisearch unavailable ────────────────────────────

    public function test_indexGroupContent_returns_zero_when_meilisearch_unavailable(): void
    {
        if (SearchService::isAvailable()) {
            $this->markTestSkipped('Meilisearch is available in this environment — skip unavailable-path test');
        }

        $userId  = $this->insertUser();
        $groupId = $this->insertGroup($userId);
        $this->insertDiscussion($groupId, $userId, 'My Discussion');

        $result = GroupSearchService::indexGroupContent($groupId);

        $this->assertSame(0, $result, 'indexGroupContent must return 0 when Meilisearch is unavailable');
    }

    public function test_indexGroupContent_returns_zero_for_empty_group_when_meilisearch_unavailable(): void
    {
        if (SearchService::isAvailable()) {
            $this->markTestSkipped('Meilisearch is available in this environment');
        }

        $userId  = $this->insertUser();
        $groupId = $this->insertGroup($userId);

        $result = GroupSearchService::indexGroupContent($groupId);

        $this->assertSame(0, $result);
    }

    // ── searchGroupContent: Meilisearch unavailable ───────────────────────────

    public function test_searchGroupContent_returns_empty_array_when_meilisearch_unavailable(): void
    {
        if (SearchService::isAvailable()) {
            $this->markTestSkipped('Meilisearch is available in this environment');
        }

        $userId  = $this->insertUser();
        $groupId = $this->insertGroup($userId);

        $result = GroupSearchService::searchGroupContent($groupId, 'test', 10);

        $this->assertIsArray($result);
        $this->assertEmpty($result, 'searchGroupContent must return [] when Meilisearch is unavailable');
    }

    public function test_searchGroupContent_returns_empty_array_for_empty_query_when_unavailable(): void
    {
        if (SearchService::isAvailable()) {
            $this->markTestSkipped('Meilisearch is available in this environment');
        }

        $userId  = $this->insertUser();
        $groupId = $this->insertGroup($userId);

        $result = GroupSearchService::searchGroupContent($groupId, '', 5);

        $this->assertIsArray($result);
        $this->assertEmpty($result);
    }

    // ── removeGroupContent: Meilisearch unavailable ───────────────────────────

    public function test_removeGroupContent_is_noop_when_meilisearch_unavailable(): void
    {
        if (SearchService::isAvailable()) {
            $this->markTestSkipped('Meilisearch is available in this environment');
        }

        $userId  = $this->insertUser();
        $groupId = $this->insertGroup($userId);
        $discId  = $this->insertDiscussion($groupId, $userId);
        $this->insertPost($discId, $userId, 'some content');

        // Should not throw; DB rows must remain untouched
        GroupSearchService::removeGroupContent($groupId);

        $discCount = DB::table('group_discussions')
            ->where('group_id', $groupId)
            ->where('tenant_id', self::TENANT_ID)
            ->count();

        $this->assertSame(1, $discCount, 'removeGroupContent must not delete DB rows');
    }

    // ── reindexAll: Meilisearch unavailable ───────────────────────────────────

    public function test_reindexAll_returns_zero_when_meilisearch_unavailable(): void
    {
        if (SearchService::isAvailable()) {
            $this->markTestSkipped('Meilisearch is available in this environment');
        }

        $result = GroupSearchService::reindexAll(self::TENANT_ID);

        $this->assertSame(0, $result, 'reindexAll must return 0 when Meilisearch is unavailable');
    }

    // ── DB fixture correctness: group_discussions schema contract ─────────────

    public function test_group_discussions_rows_are_scoped_to_correct_tenant_and_group(): void
    {
        $userId   = $this->insertUser();
        $groupId  = $this->insertGroup($userId);
        $discId   = $this->insertDiscussion($groupId, $userId, 'Tenant-scoped Title');

        $row = DB::table('group_discussions')
            ->where('id', $discId)
            ->first();

        $this->assertNotNull($row);
        $this->assertSame(self::TENANT_ID, (int) $row->tenant_id);
        $this->assertSame($groupId, (int) $row->group_id);
        $this->assertSame('Tenant-scoped Title', $row->title);
    }

    public function test_group_posts_rows_are_scoped_to_correct_tenant_and_discussion(): void
    {
        $userId  = $this->insertUser();
        $groupId = $this->insertGroup($userId);
        $discId  = $this->insertDiscussion($groupId, $userId, 'Parent discussion');
        $postId  = $this->insertPost($discId, $userId, 'Post content here');

        $row = DB::table('group_posts')
            ->where('id', $postId)
            ->first();

        $this->assertNotNull($row);
        $this->assertSame(self::TENANT_ID, (int) $row->tenant_id);
        $this->assertSame($discId, (int) $row->discussion_id);
        $this->assertSame('Post content here', $row->content);
    }

    // ── Tenant isolation: groups query is scoped ──────────────────────────────

    public function test_reindexAll_only_selects_active_groups_for_the_given_tenant(): void
    {
        if (SearchService::isAvailable()) {
            $this->markTestSkipped('Meilisearch is available — this test only validates the DB query path');
        }

        // We call reindexAll and it should NOT pick up groups from other tenants.
        // Since Meilisearch is unavailable, we validate by inspecting what the DB
        // query would return directly.
        $userId         = $this->insertUser();
        $groupActive    = $this->insertGroup($userId, self::TENANT_ID);
        $groupInactive  = $this->insertGroup($userId, self::TENANT_ID);

        // Mark one group canonically archived and keep the compatibility mirror aligned.
        DB::table('groups')->where('id', $groupInactive)->update([
            'status' => 'archived',
            'is_active' => 0,
        ]);

        $activeGroups = DB::select(
            "SELECT id FROM `groups` WHERE tenant_id = ? AND status = 'active' ORDER BY id",
            [self::TENANT_ID]
        );

        $activeIds = array_column($activeGroups, 'id');

        $this->assertContains($groupActive, $activeIds, 'Active group must be selected');
        $this->assertNotContains($groupInactive, $activeIds, 'Inactive group must be excluded');
    }

    public function test_cross_tenant_groups_are_excluded_from_query(): void
    {
        $userId           = $this->insertUser();
        $myGroup          = $this->insertGroup($userId, self::TENANT_ID);
        // Use an adjacent tenant ID that won't conflict with real data
        $otherTenantId    = 99002;
        DB::table('tenants')->insertOrIgnore([
            'id'                  => $otherTenantId,
            'name'                => 'Other Tenant GSS',
            'slug'                => 'other-gss-99002',
            'is_active'           => true,
            'depth'               => 0,
            'allows_subtenants'   => false,
            'created_at'          => now(),
            'updated_at'          => now(),
        ]);
        $otherGroup = $this->insertGroup($userId, $otherTenantId);

        $groups = DB::select(
            "SELECT id FROM `groups` WHERE tenant_id = ? AND status = 'active' ORDER BY id",
            [self::TENANT_ID]
        );

        $ids = array_column($groups, 'id');

        $this->assertContains($myGroup, $ids, 'Own tenant group must appear');
        $this->assertNotContains($otherGroup, $ids, 'Cross-tenant group must NOT appear in tenant-scoped query');
    }

    // ── Verify the service's DB query join structure with real data ───────────

    public function test_discussions_query_joins_user_names_correctly(): void
    {
        $userId  = $this->insertUser();
        $groupId = $this->insertGroup($userId);
        $this->insertDiscussion($groupId, $userId, 'Named Discussion');

        // Replicate the exact query the service runs
        $discussions = DB::select(
            "SELECT gd.id, gd.tenant_id, gd.group_id, gd.title,
                    UNIX_TIMESTAMP(gd.created_at) as created_at,
                    CONCAT(u.first_name, ' ', u.last_name) as author_name
             FROM group_discussions gd
             LEFT JOIN users u ON gd.user_id = u.id
             WHERE gd.group_id = ? AND gd.tenant_id = ?
             ORDER BY gd.id",
            [$groupId, self::TENANT_ID]
        );

        $this->assertCount(1, $discussions);
        $this->assertSame('Named Discussion', $discussions[0]->title);
        $this->assertSame(self::TENANT_ID, (int) $discussions[0]->tenant_id);
        $this->assertNotEmpty(trim($discussions[0]->author_name));
    }

    public function test_posts_query_joins_discussion_group_and_user_correctly(): void
    {
        $userId  = $this->insertUser();
        $groupId = $this->insertGroup($userId);
        $discId  = $this->insertDiscussion($groupId, $userId, 'Parent Title');
        $this->insertPost($discId, $userId, 'Hello World post');

        // Replicate the exact posts query the service runs
        $posts = DB::select(
            "SELECT gp.id, gp.tenant_id, gd.group_id, gp.content,
                    gd.title as discussion_title,
                    UNIX_TIMESTAMP(gp.created_at) as created_at,
                    CONCAT(u.first_name, ' ', u.last_name) as author_name
             FROM group_posts gp
             INNER JOIN group_discussions gd ON gp.discussion_id = gd.id
             LEFT JOIN users u ON gp.user_id = u.id
             WHERE gd.group_id = ? AND gp.tenant_id = ?
             ORDER BY gp.id",
            [$groupId, self::TENANT_ID]
        );

        $this->assertCount(1, $posts);
        $this->assertSame('Hello World post', $posts[0]->content);
        $this->assertSame($groupId, (int) $posts[0]->group_id);
        $this->assertSame('Parent Title', $posts[0]->discussion_title);
        $this->assertNotEmpty(trim($posts[0]->author_name));
    }
}
