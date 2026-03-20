<?php
// Copyright © 2024–2026 Jasper Ford
// SPDX-License-Identifier: AGPL-3.0-or-later
// Author: Jasper Ford
// See NOTICE file for attribution and acknowledgements.

namespace Nexus\Tests\Services;

use PHPUnit\Framework\TestCase;
use App\Services\MemberRankingService;
use App\Core\Database;
use App\Core\TenantContext;

class MemberRankingServiceTest extends TestCase
{
    private static int $testTenantId = 2;
    private static int $otherTenantId = 1;
    private static ?int $viewerUserId = null;
    private static ?int $activeUserId = null;
    private static ?int $inactiveUserId = null;
    private static ?int $giverUserId = null;
    private static ?int $newUserId = null;
    private static ?int $otherTenantUserId = null;
    private static array $createdUserIds = [];

    public static function setUpBeforeClass(): void
    {
        TenantContext::setById(self::$testTenantId);
        $ts = time() . '_' . mt_rand(1000, 9999);

        self::$viewerUserId = self::createUser($ts, 'viewer', self::$testTenantId, [
            'status' => 'active', 'is_verified' => 1, 'bio' => 'Test viewer bio',
            'location' => 'Dublin', 'avatar_url' => '/avatars/viewer.jpg',
            'skills' => 'gardening,cooking',
            'created_at' => date('Y-m-d H:i:s', strtotime('-120 days')),
        ]);

        self::$activeUserId = self::createUser($ts, 'active', self::$testTenantId, [
            'status' => 'active', 'is_verified' => 1, 'bio' => 'Active member bio',
            'location' => 'Cork', 'avatar_url' => '/avatars/active.jpg',
            'skills' => 'cooking,tutoring',
            'created_at' => date('Y-m-d H:i:s', strtotime('-200 days')),
        ]);

        self::$inactiveUserId = self::createUser($ts, 'inactive', self::$testTenantId, [
            'status' => 'active',
            'created_at' => date('Y-m-d H:i:s', strtotime('-400 days')),
        ]);

        self::$giverUserId = self::createUser($ts, 'giver', self::$testTenantId, [
            'status' => 'active', 'is_verified' => 1, 'bio' => 'Generous giver',
            'location' => 'Galway', 'avatar_url' => '/avatars/giver.jpg',
            'skills' => 'gardening,plumbing',
            'created_at' => date('Y-m-d H:i:s', strtotime('-180 days')),
        ]);

        self::$newUserId = self::createUser($ts, 'newuser', self::$testTenantId, [
            'status' => 'active', 'created_at' => date('Y-m-d H:i:s'),
        ]);

        self::$otherTenantUserId = self::createUser($ts, 'othertenant', self::$otherTenantId, [
            'status' => 'active', 'is_verified' => 1, 'bio' => 'Other tenant user',
            'skills' => 'gardening',
            'created_at' => date('Y-m-d H:i:s', strtotime('-100 days')),
        ]);

        try {
            Database::query(
                "INSERT INTO feed_activity (user_id, tenant_id, activity_type, created_at) VALUES (?, ?, 'post', NOW())",
                [self::$activeUserId, self::$testTenantId]
            );
        } catch (\Exception $e) {}

        try {
            Database::query(
                "INSERT INTO transactions (sender_id, receiver_id, amount, tenant_id, status, created_at) VALUES (?, ?, 10, ?, 'completed', NOW())",
                [self::$giverUserId, self::$activeUserId, self::$testTenantId]
            );
            Database::query(
                "INSERT INTO transactions (sender_id, receiver_id, amount, tenant_id, status, created_at) VALUES (?, ?, 2, ?, 'completed', NOW())",
                [self::$activeUserId, self::$giverUserId, self::$testTenantId]
            );
        } catch (\Exception $e) {}

        try {
            Database::query(
                "INSERT INTO listings (user_id, tenant_id, title, type, status, created_at) VALUES (?, ?, 'Test Offer', 'offer', 'active', NOW())",
                [self::$activeUserId, self::$testTenantId]
            );
            Database::query(
                "INSERT INTO listings (user_id, tenant_id, title, type, status, created_at) VALUES (?, ?, 'Test Request', 'request', 'active', NOW())",
                [self::$activeUserId, self::$testTenantId]
            );
        } catch (\Exception $e) {}
    }

    protected function setUp(): void
    {
        TenantContext::setById(self::$testTenantId);
        MemberRankingService::clearCache();
    }

    public static function tearDownAfterClass(): void
    {
        foreach (self::$createdUserIds as $uid) {
            try { Database::query("DELETE FROM feed_activity WHERE user_id = ?", [$uid]); } catch (\Exception $e) {}
            try { Database::query("DELETE FROM transactions WHERE sender_id = ? OR receiver_id = ?", [$uid, $uid]); } catch (\Exception $e) {}
            try { Database::query("DELETE FROM listings WHERE user_id = ?", [$uid]); } catch (\Exception $e) {}
            try { Database::query("DELETE FROM users WHERE id = ?", [$uid]); } catch (\Exception $e) {}
        }
    }

    public function testScoreWeightsSumToOne(): void
    {
        $sum = array_sum(MemberRankingService::SCORE_WEIGHTS);
        $this->assertEqualsWithDelta(1.0, $sum, 0.001, 'SCORE_WEIGHTS must sum to 1.0');
    }

    public function testScoreWeightsHasAllComponents(): void
    {
        foreach (['activity', 'contribution', 'reputation', 'connectivity', 'proximity', 'complementary'] as $key) {
            $this->assertArrayHasKey($key, MemberRankingService::SCORE_WEIGHTS);
        }
    }

    public function testScoreWeightsArePositive(): void
    {
        foreach (MemberRankingService::SCORE_WEIGHTS as $c => $w) {
            $this->assertGreaterThan(0, $w);
        }
    }

    public function testIsEnabledReturnsBoolean(): void
    {
        $this->assertIsBool(MemberRankingService::isEnabled());
    }

    public function testGetConfigReturnsAllKeys(): void
    {
        $config = MemberRankingService::getConfig();
        foreach (['enabled', 'activity_login_weight', 'activity_minimum',
            'reputation_minimum', 'connectivity_mutual_connection',
            'complementary_enabled', 'geo_enabled'] as $key) {
            $this->assertArrayHasKey($key, $config, "Config missing: {$key}");
        }
    }

    public function testClearCacheResetsConfig(): void
    {
        MemberRankingService::getConfig();
        MemberRankingService::clearCache();
        $this->assertNotEmpty(MemberRankingService::getConfig());
    }

    public function testRankMembersExcludesViewer(): void
    {
        $ranked = MemberRankingService::rankMembers($this->getTestMembers(), self::$viewerUserId);
        $this->assertIsArray($ranked);
        foreach ($ranked as $m) {
            $this->assertNotEquals(self::$viewerUserId, $m['id']);
        }
    }

    public function testRankMembersAddsScoreFields(): void
    {
        $ranked = MemberRankingService::rankMembers($this->getTestMembers(), self::$viewerUserId);
        if (empty($ranked)) { $this->markTestSkipped('No ranked members'); }
        $this->assertArrayHasKey('_community_rank', $ranked[0]);
        $this->assertArrayHasKey('_score_breakdown', $ranked[0]);
        $this->assertIsFloat($ranked[0]['_community_rank']);
    }

    public function testRankMembersScoresAreBounded(): void
    {
        foreach (MemberRankingService::rankMembers($this->getTestMembers(), self::$viewerUserId) as $m) {
            $this->assertGreaterThanOrEqual(0.0, $m['_community_rank']);
            $this->assertLessThanOrEqual(1.0, $m['_community_rank']);
        }
    }

    public function testRankMembersOrderedDescending(): void
    {
        $ranked = MemberRankingService::rankMembers($this->getTestMembers(), self::$viewerUserId);
        for ($i = 1; $i < count($ranked); $i++) {
            $this->assertGreaterThanOrEqual($ranked[$i]['_community_rank'], $ranked[$i - 1]['_community_rank']);
        }
    }

    public function testBreakdownHasAllComponents(): void
    {
        $ranked = MemberRankingService::rankMembers($this->getTestMembers(), self::$viewerUserId);
        if (empty($ranked)) { $this->markTestSkipped('No ranked members'); }
        foreach (array_keys(MemberRankingService::SCORE_WEIGHTS) as $key) {
            $this->assertArrayHasKey($key, $ranked[0]['_score_breakdown']);
        }
    }

    public function testWeightedAdditiveFormula(): void
    {
        foreach (MemberRankingService::rankMembers($this->getTestMembers(), self::$viewerUserId) as $m) {
            $base = 0.0;
            foreach (MemberRankingService::SCORE_WEIGHTS as $c => $w) {
                $base += ($m['_score_breakdown'][$c] ?? 0.5) * $w;
            }
            $this->assertGreaterThanOrEqual($base - 0.001, $m['_community_rank']);
        }
    }

    public function testRankMembersEmptyArray(): void
    {
        $this->assertEmpty(MemberRankingService::rankMembers([], self::$viewerUserId));
    }

    public function testRankMembersNullViewer(): void
    {
        $ranked = MemberRankingService::rankMembers($this->getTestMembers(), null);
        $this->assertIsArray($ranked);
        $this->assertNotEmpty($ranked);
    }

    public function testNewUserNeutralScore(): void
    {
        $ranked = MemberRankingService::rankMembers(
            [$this->buildMemberRow(self::$newUserId, ['created_at' => date('Y-m-d H:i:s')])],
            self::$viewerUserId
        );
        if (!empty($ranked)) {
            $this->assertGreaterThan(0.0, $ranked[0]['_community_rank']);
            $this->assertLessThan(0.9, $ranked[0]['_community_rank']);
        }
    }

    public function testBuildRankedQueryScopesByTenant(): void
    {
        $q = MemberRankingService::buildRankedQuery(self::$viewerUserId, ['limit' => 100]);
        $this->assertStringContainsString('u.tenant_id = ?', $q['sql']);
        $this->assertContains(self::$testTenantId, $q['params']);
    }

    public function testBuildRankedQueryExcludesOtherTenants(): void
    {
        $q = MemberRankingService::buildRankedQuery(self::$viewerUserId, ['limit' => 100]);
        foreach (Database::query($q['sql'], $q['params'])->fetchAll(\PDO::FETCH_ASSOC) as $row) {
            $this->assertEquals(self::$testTenantId, (int) $row['tenant_id'],
                "Cross-tenant leak: user {$row['id']}");
        }
    }

    public function testBuildRankedQueryExecutes(): void
    {
        $q = MemberRankingService::buildRankedQuery(self::$viewerUserId, ['limit' => 5]);
        $this->assertIsArray(Database::query($q['sql'], $q['params'])->fetchAll(\PDO::FETCH_ASSOC));
    }

    public function testBuildRankedQueryExcludesViewer(): void
    {
        $q = MemberRankingService::buildRankedQuery(self::$viewerUserId, ['limit' => 100]);
        $ids = array_column(Database::query($q['sql'], $q['params'])->fetchAll(\PDO::FETCH_ASSOC), 'id');
        $this->assertNotContains((string) self::$viewerUserId, $ids);
    }

    public function testBuildRankedQueryHasScoreColumns(): void
    {
        $q = MemberRankingService::buildRankedQuery(self::$viewerUserId, ['limit' => 1]);
        $rows = Database::query($q['sql'], $q['params'])->fetchAll(\PDO::FETCH_ASSOC);
        if (!empty($rows)) {
            foreach (['community_rank', 'activity_score', 'contribution_score', 'reputation_score'] as $col) {
                $this->assertArrayHasKey($col, $rows[0]);
            }
        }
    }

    public function testBuildRankedQuerySearchFilter(): void
    {
        $q = MemberRankingService::buildRankedQuery(self::$viewerUserId, ['search' => 'nonexistent_xyz_9999', 'limit' => 10]);
        $this->assertEmpty(Database::query($q['sql'], $q['params'])->fetchAll(\PDO::FETCH_ASSOC));
    }

    public function testDebugMemberScoreBreakdown(): void
    {
        $r = MemberRankingService::debugMemberScore(self::$activeUserId, self::$viewerUserId);
        if (isset($r['error'])) { $this->markTestSkipped($r['error']); }
        $this->assertArrayHasKey('scores', $r);
        $this->assertArrayHasKey('final_score', $r);
        $this->assertArrayHasKey('weights', $r);
        $this->assertEquals(MemberRankingService::SCORE_WEIGHTS, $r['weights']);
    }

    public function testDebugNonexistentMember(): void
    {
        $this->assertArrayHasKey('error', MemberRankingService::debugMemberScore(999999999));
    }

    public function testDebugConsistentWithRankMembers(): void
    {
        $debug = MemberRankingService::debugMemberScore(self::$activeUserId, self::$viewerUserId);
        if (isset($debug['error'])) { $this->markTestSkipped($debug['error']); }
        $ranked = MemberRankingService::rankMembers($this->getTestMembers([self::$activeUserId]), self::$viewerUserId);
        $found = null;
        foreach ($ranked as $m) { if ((int)$m['id'] === self::$activeUserId) { $found = $m; break; } }
        if ($found) {
            foreach (['activity', 'contribution', 'reputation'] as $c) {
                $this->assertEqualsWithDelta($debug['scores'][$c], $found['_score_breakdown'][$c], 0.01);
            }
        }
    }

    public function testGetActiveMembersExcludesViewer(): void
    {
        $ids = array_map('intval', array_column(MemberRankingService::getActiveMembers(self::$viewerUserId, 50), 'id'));
        $this->assertNotContains(self::$viewerUserId, $ids);
    }

    public function testGetActiveMembersFields(): void
    {
        $members = MemberRankingService::getActiveMembers(null, 5);
        if (empty($members)) { $this->markTestSkipped('No active members'); }
        foreach (['id', 'first_name', 'last_name', 'avatar_url', 'last_active_at', 'display_name'] as $f) {
            $this->assertArrayHasKey($f, $members[0]);
        }
    }

    public function testGetSuggestedMembersReturnsArray(): void
    {
        $s = MemberRankingService::getSuggestedMembers(self::$viewerUserId, 5);
        $this->assertIsArray($s);
        $this->assertLessThanOrEqual(5, count($s));
    }

    public function testGetSuggestedMembersExcludesViewer(): void
    {
        $ids = array_map('intval', array_column(MemberRankingService::getSuggestedMembers(self::$viewerUserId, 20), 'id'));
        $this->assertNotContains(self::$viewerUserId, $ids);
    }

    public function testSqlScoresAreBounded(): void
    {
        $q = MemberRankingService::buildRankedQuery(null, ['limit' => 20]);
        foreach (Database::query($q['sql'], $q['params'])->fetchAll(\PDO::FETCH_ASSOC) as $row) {
            foreach (['activity_score', 'contribution_score', 'reputation_score', 'community_rank'] as $col) {
                $this->assertGreaterThanOrEqual(0.0, (float) $row[$col]);
                $this->assertLessThanOrEqual(1.0, (float) $row[$col]);
            }
        }
    }

    public function testWilsonScoreNoData(): void
    {
        $this->assertEqualsWithDelta(0.5, self::callStatic('wilsonScore', [0.0, 0.0]), 0.001);
    }

    public function testWilsonScorePerfectPositive(): void
    {
        $s = self::callStatic('wilsonScore', [100.0, 100.0]);
        $this->assertGreaterThan(0.9, $s);
        $this->assertLessThanOrEqual(1.0, $s);
    }

    public function testWilsonScorePerfectNegative(): void
    {
        $s = self::callStatic('wilsonScore', [0.0, 100.0]);
        $this->assertLessThan(0.05, $s);
        $this->assertGreaterThanOrEqual(0.0, $s);
    }

    public function testWilsonScoreSmallSampleConservative(): void
    {
        $this->assertLessThan(
            self::callStatic('wilsonScore', [100.0, 100.0]),
            self::callStatic('wilsonScore', [2.0, 2.0])
        );
    }

    // =========================================================================
    // HELPERS
    // =========================================================================

    private static function createUser(string $ts, string $suffix, int $tenantId, array $extra = []): int
    {
        $data = array_merge([
            'tenant_id' => $tenantId,
            'email' => "cr_test_{$suffix}_{$ts}@test.com",
            'username' => "cr_test_{$suffix}_{$ts}",
            'first_name' => 'CR', 'last_name' => ucfirst($suffix),
            'name' => 'CR ' . ucfirst($suffix),
            'balance' => 0, 'is_approved' => 1,
            'status' => 'active', 'created_at' => date('Y-m-d H:i:s'),
        ], $extra);
        $cols = implode(', ', array_keys($data));
        $phs = implode(', ', array_fill(0, count($data), '?'));
        Database::query("INSERT INTO users ({$cols}) VALUES ({$phs})", array_values($data));
        $id = (int) Database::getInstance()->lastInsertId();
        self::$createdUserIds[] = $id;
        return $id;
    }

    private function getTestMembers(array $onlyIds = []): array
    {
        $t = self::$testTenantId;
        $ids = !empty($onlyIds) ? $onlyIds : array_filter([
            self::$viewerUserId, self::$activeUserId, self::$inactiveUserId,
            self::$giverUserId, self::$newUserId,
        ]);
        if (empty($ids)) { return []; }
        $ph = implode(',', array_fill(0, count($ids), '?'));
        return Database::query(
            "SELECT u.*,
                COALESCE(
                    (SELECT MAX(created_at) FROM feed_activity WHERE user_id = u.id AND tenant_id = ?),
                    (SELECT MAX(created_at) FROM transactions WHERE (sender_id = u.id OR receiver_id = u.id) AND tenant_id = ?),
                    u.created_at
                ) AS last_active_at,
                (SELECT COUNT(*) FROM listings WHERE user_id = u.id AND tenant_id = u.tenant_id AND status = 'active' AND type = 'offer') as offer_count,
                (SELECT COUNT(*) FROM listings WHERE user_id = u.id AND tenant_id = u.tenant_id AND status = 'active' AND type = 'request') as request_count,
                (SELECT COALESCE(SUM(amount), 0) FROM transactions WHERE sender_id = u.id AND tenant_id = u.tenant_id AND status = 'completed') as hours_given,
                (SELECT COALESCE(SUM(amount), 0) FROM transactions WHERE receiver_id = u.id AND tenant_id = u.tenant_id AND status = 'completed') as hours_received
             FROM users u WHERE u.tenant_id = ? AND u.id IN ({$ph})",
            array_merge([$t, $t, $t], $ids)
        )->fetchAll(\PDO::FETCH_ASSOC);
    }

    private function buildMemberRow(int $id, array $overrides = []): array
    {
        return array_merge([
            'id' => $id, 'first_name' => 'Test', 'last_name' => 'User',
            'bio' => null, 'location' => null, 'avatar_url' => null,
            'skills' => null, 'is_verified' => 0, 'status' => 'active',
            'offer_count' => 0, 'request_count' => 0,
            'hours_given' => 0, 'hours_received' => 0,
            'last_active_at' => null, 'created_at' => date('Y-m-d H:i:s'),
            'latitude' => null, 'longitude' => null,
        ], $overrides);
    }

    private static function callStatic(string $method, array $args = []): mixed
    {
        $ref = new \ReflectionMethod(MemberRankingService::class, $method);
        $ref->setAccessible(true);
        return $ref->invokeArgs(null, $args);
    }
}
