<?php
// Copyright © 2024–2026 Jasper Ford
// SPDX-License-Identifier: AGPL-3.0-or-later
// Author: Jasper Ford
// See NOTICE file for attribution and acknowledgements.

namespace Tests\Laravel\Unit\Services;

use App\Core\TenantContext;
use App\Models\User;
use App\Services\GroupAchievementService;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Illuminate\Support\Facades\DB;
use Tests\Laravel\TestCase;

/**
 * Real-DB tests for GroupAchievementService (group milestone tracking + awards).
 *
 * Previously the integration surface was a single markTestIncomplete
 * ("Requires DB for calculateProgress and getEarnedAchievements"). The static
 * constant / structure tests already asserted real behaviour and are kept as-is.
 * The integration test is now a set of real assertions against nexus_test:
 *   - calculateProgress counts real group_members / group_discussions /
 *     group_posts / events rows
 *   - getGroupAchievements returns the full definition list with live progress
 *   - getEarnedAchievements / checkAndAwardAchievements / awardAchievement
 *     write and read group_achievements + group_achievement_progress rows
 *   - tenant isolation: a group in another tenant returns [] / false
 *
 * Gotchas honoured:
 *   - DatabaseTransactions rolls every row back.
 *   - TenantContext is RE-PINNED immediately before each tenant-scoped service
 *     call because factory creates drift TenantContext::getId().
 *   - All FK ids (owner_id, user_id, group_id, achievement_id) are REAL rows
 *     created via factories / inserts, never literals.
 *   - target_value 10 (first_steps member_count) used for the award path so a
 *     small, deterministic row count crosses the threshold.
 */
class GroupAchievementServiceTest extends TestCase
{
    use DatabaseTransactions;

    // ---------------------------------------------------------------------
    // Static definition / structure tests (already real — kept unchanged)
    // ---------------------------------------------------------------------

    public function test_group_achievements_constants_are_defined(): void
    {
        $achievements = GroupAchievementService::GROUP_ACHIEVEMENTS;
        $this->assertArrayHasKey('community_builders', $achievements);
        $this->assertArrayHasKey('active_hub', $achievements);
        $this->assertArrayHasKey('event_masters', $achievements);
        $this->assertArrayHasKey('first_steps', $achievements);
        $this->assertArrayHasKey('discussion_starters', $achievements);
    }

    public function test_community_builders_requires_50_members(): void
    {
        $achievement = GroupAchievementService::GROUP_ACHIEVEMENTS['community_builders'];
        $this->assertEquals('member_count', $achievement['target_type']);
        $this->assertEquals(50, $achievement['target_value']);
        $this->assertEquals(500, $achievement['xp_reward']);
    }

    public function test_first_steps_requires_10_members(): void
    {
        $achievement = GroupAchievementService::GROUP_ACHIEVEMENTS['first_steps'];
        $this->assertEquals('member_count', $achievement['target_type']);
        $this->assertEquals(10, $achievement['target_value']);
    }

    public function test_all_achievements_have_required_keys(): void
    {
        foreach (GroupAchievementService::GROUP_ACHIEVEMENTS as $key => $achievement) {
            $this->assertArrayHasKey('name', $achievement, "Achievement {$key} missing 'name'");
            $this->assertArrayHasKey('description', $achievement, "Achievement {$key} missing 'description'");
            $this->assertArrayHasKey('target_type', $achievement, "Achievement {$key} missing 'target_type'");
            $this->assertArrayHasKey('target_value', $achievement, "Achievement {$key} missing 'target_value'");
            $this->assertArrayHasKey('xp_reward', $achievement, "Achievement {$key} missing 'xp_reward'");
            $this->assertArrayHasKey('icon', $achievement, "Achievement {$key} missing 'icon'");
        }
    }

    // ---------------------------------------------------------------------
    // Helpers
    // ---------------------------------------------------------------------

    /**
     * Create a real group owned by a real user in the given tenant and return
     * its id. Re-pins TenantContext afterwards (factory creates drift it).
     */
    private function makeGroup(int $tenantId): int
    {
        $owner = User::factory()->forTenant($tenantId)->create();

        $groupId = (int) DB::table('groups')->insertGetId([
            'tenant_id'   => $tenantId,
            'owner_id'    => (int) $owner->id,
            'name'        => 'Test Group ' . uniqid(),
            'visibility'  => 'public',
            'status'      => 'active',
            'is_active'   => 1,
            'created_at'  => now(),
            'updated_at'  => now(),
        ]);

        TenantContext::setById($tenantId);

        return $groupId;
    }

    /**
     * Insert $count active members into a group, each a real user row.
     */
    private function addActiveMembers(int $tenantId, int $groupId, int $count): void
    {
        for ($i = 0; $i < $count; $i++) {
            $user = User::factory()->forTenant($tenantId)->create();
            DB::table('group_members')->insert([
                'tenant_id'  => $tenantId,
                'group_id'   => $groupId,
                'user_id'    => (int) $user->id,
                'status'     => 'active',
                'role'       => 'member',
                'joined_at'  => now(),
                'created_at' => now(),
                'updated_at' => now(),
            ]);
        }

        TenantContext::setById($tenantId);
    }

    // ---------------------------------------------------------------------
    // calculateProgress — real counts (converted from markTestIncomplete)
    // ---------------------------------------------------------------------

    public function test_calculateProgress_zero_target_returns_zero(): void
    {
        $result = GroupAchievementService::calculateProgress(1, 'member_count', 0);
        $this->assertSame(0, $result['current']);
        $this->assertSame(0, $result['percent']);
    }

    public function test_calculateProgress_counts_active_members(): void
    {
        $groupId = $this->makeGroup($this->testTenantId);
        $this->addActiveMembers($this->testTenantId, $groupId, 4);

        TenantContext::setById($this->testTenantId);
        $result = GroupAchievementService::calculateProgress($groupId, 'member_count', 10);

        $this->assertSame(4, $result['current']);
        // 4 / 10 = 40 %
        $this->assertEqualsWithDelta(40.0, (float) $result['percent'], 0.001);
    }

    public function test_calculateProgress_percent_caps_at_100(): void
    {
        $groupId = $this->makeGroup($this->testTenantId);
        $this->addActiveMembers($this->testTenantId, $groupId, 3);

        // 3 members against a target of 2 would be 150 %; must clamp to 100.
        TenantContext::setById($this->testTenantId);
        $result = GroupAchievementService::calculateProgress($groupId, 'member_count', 2);

        $this->assertSame(3, $result['current']);
        $this->assertEqualsWithDelta(100.0, (float) $result['percent'], 0.001);
    }

    public function test_calculateProgress_counts_discussions(): void
    {
        $groupId = $this->makeGroup($this->testTenantId);
        $author  = User::factory()->forTenant($this->testTenantId)->create();

        for ($i = 0; $i < 2; $i++) {
            DB::table('group_discussions')->insert([
                'tenant_id'  => $this->testTenantId,
                'group_id'   => $groupId,
                'user_id'    => (int) $author->id,
                'title'      => 'Topic ' . $i,
                'created_at' => now(),
                'updated_at' => now(),
            ]);
        }

        TenantContext::setById($this->testTenantId);
        $result = GroupAchievementService::calculateProgress($groupId, 'discussion_count', 10);
        $this->assertSame(2, $result['current']);
    }

    public function test_calculateProgress_counts_posts_via_discussions(): void
    {
        $groupId = $this->makeGroup($this->testTenantId);
        $author  = User::factory()->forTenant($this->testTenantId)->create();

        $discussionId = (int) DB::table('group_discussions')->insertGetId([
            'tenant_id'  => $this->testTenantId,
            'group_id'   => $groupId,
            'user_id'    => (int) $author->id,
            'title'      => 'Thread with posts',
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        for ($i = 0; $i < 3; $i++) {
            DB::table('group_posts')->insert([
                'tenant_id'     => $this->testTenantId,
                'discussion_id' => $discussionId,
                'user_id'       => (int) $author->id,
                'content'       => 'Reply ' . $i,
                'created_at'    => now(),
            ]);
        }

        TenantContext::setById($this->testTenantId);
        $result = GroupAchievementService::calculateProgress($groupId, 'post_count', 100);
        $this->assertSame(3, $result['current']);
    }

    public function test_calculateProgress_counts_events(): void
    {
        $groupId = $this->makeGroup($this->testTenantId);
        $author  = User::factory()->forTenant($this->testTenantId)->create();

        for ($i = 0; $i < 2; $i++) {
            DB::table('events')->insert([
                'tenant_id'   => $this->testTenantId,
                'user_id'     => (int) $author->id,
                'group_id'    => $groupId,
                'title'       => 'Event ' . $i,
                'description' => 'desc',
                'start_time'  => now()->addDays($i + 1),
                'created_at'  => now(),
                'updated_at'  => now(),
            ]);
        }

        TenantContext::setById($this->testTenantId);
        $result = GroupAchievementService::calculateProgress($groupId, 'event_count', 10);
        $this->assertSame(2, $result['current']);
    }

    // ---------------------------------------------------------------------
    // getGroupAchievements — full list with live progress + earned flag
    // ---------------------------------------------------------------------

    public function test_getGroupAchievements_returns_full_definition_list(): void
    {
        $groupId = $this->makeGroup($this->testTenantId);

        TenantContext::setById($this->testTenantId);
        $result = GroupAchievementService::getGroupAchievements($groupId);

        $this->assertIsArray($result);
        $this->assertCount(count(GroupAchievementService::GROUP_ACHIEVEMENTS), $result);

        $keys = array_column($result, 'key');
        $this->assertContains('community_builders', $keys);
        $this->assertContains('first_steps', $keys);

        // Each entry carries the live-progress + earned shape.
        foreach ($result as $row) {
            foreach (['key', 'name', 'description', 'target_type', 'target_value',
                'xp_reward', 'icon', 'progress_percent', 'current_value', 'earned'] as $field) {
                $this->assertArrayHasKey($field, $row);
            }
            $this->assertIsBool($row['earned']);
        }
    }

    public function test_getGroupAchievements_reflects_member_progress(): void
    {
        $groupId = $this->makeGroup($this->testTenantId);
        $this->addActiveMembers($this->testTenantId, $groupId, 5);

        TenantContext::setById($this->testTenantId);
        $result = GroupAchievementService::getGroupAchievements($groupId);

        $firstSteps = collect($result)->firstWhere('key', 'first_steps');
        $this->assertNotNull($firstSteps);
        // 5 active members against the 10-member first_steps target.
        $this->assertSame(5, (int) $firstSteps['current_value']);
        $this->assertEqualsWithDelta(50.0, (float) $firstSteps['progress_percent'], 0.001);
        $this->assertFalse($firstSteps['earned']);
    }

    public function test_getGroupAchievements_returns_empty_for_foreign_tenant_group(): void
    {
        // Group lives in tenant 999, but we query as tenant 2 → verifyGroupTenant fails.
        $groupId = $this->makeGroup(999);

        TenantContext::setById($this->testTenantId); // tenant 2
        $this->assertSame([], GroupAchievementService::getGroupAchievements($groupId));
    }

    // ---------------------------------------------------------------------
    // award + earned roundtrip (converted from markTestIncomplete)
    // ---------------------------------------------------------------------

    public function test_checkAndAwardAchievements_awards_first_steps_at_threshold(): void
    {
        $groupId = $this->makeGroup($this->testTenantId);
        // 10 active members crosses the first_steps target (10).
        $this->addActiveMembers($this->testTenantId, $groupId, 10);

        TenantContext::setById($this->testTenantId);
        $awarded = GroupAchievementService::checkAndAwardAchievements($groupId);

        $this->assertIsArray($awarded);
        $this->assertContains('first_steps', $awarded, 'first_steps should be awarded at 10 members');

        // A completed progress row now exists for the resolved definition.
        TenantContext::setById($this->testTenantId);
        $defId = (int) DB::table('group_achievements')
            ->where('tenant_id', $this->testTenantId)
            ->where('achievement_key', 'first_steps')
            ->value('id');
        $this->assertGreaterThan(0, $defId);

        $this->assertTrue(
            DB::table('group_achievement_progress')
                ->where('group_id', $groupId)
                ->where('achievement_id', $defId)
                ->whereNotNull('completed_at')
                ->exists()
        );
    }

    public function test_checkAndAwardAchievements_is_idempotent(): void
    {
        $groupId = $this->makeGroup($this->testTenantId);
        $this->addActiveMembers($this->testTenantId, $groupId, 10);

        TenantContext::setById($this->testTenantId);
        $first = GroupAchievementService::checkAndAwardAchievements($groupId);
        $this->assertContains('first_steps', $first);

        // Second pass must NOT re-award an already-completed achievement.
        TenantContext::setById($this->testTenantId);
        $second = GroupAchievementService::checkAndAwardAchievements($groupId);
        $this->assertNotContains('first_steps', $second);

        // Exactly one completed progress row for first_steps.
        TenantContext::setById($this->testTenantId);
        $defId = (int) DB::table('group_achievements')
            ->where('tenant_id', $this->testTenantId)
            ->where('achievement_key', 'first_steps')
            ->value('id');
        $this->assertSame(1, (int) DB::table('group_achievement_progress')
            ->where('group_id', $groupId)
            ->where('achievement_id', $defId)
            ->whereNotNull('completed_at')
            ->count());
    }

    public function test_getEarnedAchievements_returns_awarded_row(): void
    {
        $groupId = $this->makeGroup($this->testTenantId);
        $this->addActiveMembers($this->testTenantId, $groupId, 10);

        TenantContext::setById($this->testTenantId);
        GroupAchievementService::checkAndAwardAchievements($groupId);

        TenantContext::setById($this->testTenantId);
        $earned = GroupAchievementService::getEarnedAchievements($groupId);

        $this->assertIsArray($earned);
        $this->assertNotEmpty($earned);
        $keys = array_column($earned, 'achievement_key');
        $this->assertContains('first_steps', $keys);
    }

    public function test_awardAchievement_writes_completed_progress(): void
    {
        $groupId = $this->makeGroup($this->testTenantId);

        TenantContext::setById($this->testTenantId);
        $ok = GroupAchievementService::awardAchievement(
            $groupId,
            'community_builders',
            GroupAchievementService::GROUP_ACHIEVEMENTS['community_builders'],
            50
        );

        $this->assertTrue($ok);

        TenantContext::setById($this->testTenantId);
        $defId = (int) DB::table('group_achievements')
            ->where('tenant_id', $this->testTenantId)
            ->where('achievement_key', 'community_builders')
            ->value('id');
        $this->assertGreaterThan(0, $defId);

        $row = DB::table('group_achievement_progress')
            ->where('group_id', $groupId)
            ->where('achievement_id', $defId)
            ->first();
        $this->assertNotNull($row);
        $this->assertNotNull($row->completed_at);
        $this->assertSame(50, (int) $row->current_count);
    }

    public function test_awardAchievement_returns_false_for_foreign_tenant_group(): void
    {
        $groupId = $this->makeGroup(999); // group in another tenant

        TenantContext::setById($this->testTenantId); // querying as tenant 2
        $this->assertFalse(GroupAchievementService::awardAchievement(
            $groupId,
            'first_steps',
            GroupAchievementService::GROUP_ACHIEVEMENTS['first_steps'],
            10
        ));
    }

    public function test_getEarnedAchievements_returns_empty_for_foreign_tenant_group(): void
    {
        $groupId = $this->makeGroup(999);

        TenantContext::setById($this->testTenantId);
        $this->assertSame([], GroupAchievementService::getEarnedAchievements($groupId));
    }
}
