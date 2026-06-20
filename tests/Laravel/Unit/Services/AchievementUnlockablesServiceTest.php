<?php
// Copyright © 2024–2026 Jasper Ford
// SPDX-License-Identifier: AGPL-3.0-or-later
// Author: Jasper Ford
// See NOTICE file for attribution and acknowledgements.

namespace Tests\Laravel\Unit\Services;

use App\Core\TenantContext;
use App\Models\User;
use App\Services\AchievementUnlockablesService;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Illuminate\Support\Facades\DB;
use Tests\Laravel\TestCase;

/**
 * Real-DB tests for AchievementUnlockablesService (profile themes/frames/etc.
 * unlocked through level + badges).
 *
 * Previously the DB-touching tests used DB::shouldReceive() stubs, which assert
 * nothing about real behaviour. They now create real users / user_badges rows
 * and assert the real unlock logic (level requirements, badge requirements,
 * available vs locked partitioning).
 *
 * SCHEMA NOTE — three methods are deferred (markTestSkipped), not converted:
 *   getUserActiveUnlockables(), setActiveUnlockable() (happy path), and
 *   removeActiveUnlockable() all read/write `user_active_unlockables.tenant_id`,
 *   but the nexus_test copy of that table has NO `tenant_id` column (its unique
 *   key is (user_id, unlockable_type) only). Calling them throws
 *   "SQLSTATE[42S22] Unknown column 'tenant_id'" — a test-DB schema-drift issue,
 *   not a logic bug, so it cannot be exercised here without altering the schema.
 *   The not-unlocked branch of setActiveUnlockable() IS tested, because it
 *   returns false before reaching the broken upsert.
 */
class AchievementUnlockablesServiceTest extends TestCase
{
    use DatabaseTransactions;

    // --- Static data (no DB) — kept as real structural assertions ---

    public function test_constants_defined(): void
    {
        $this->assertNotEmpty(AchievementUnlockablesService::TYPES);
        $this->assertArrayHasKey('theme', AchievementUnlockablesService::TYPES);
        $this->assertArrayHasKey('avatar_frame', AchievementUnlockablesService::TYPES);
    }

    public function test_getAllUnlockables_returns_expected_categories(): void
    {
        $all = AchievementUnlockablesService::getAllUnlockables();

        $this->assertArrayHasKey('themes', $all);
        $this->assertArrayHasKey('frames', $all);
        $this->assertArrayHasKey('name_colors', $all);
        $this->assertArrayHasKey('banners', $all);
        $this->assertArrayHasKey('special_emojis', $all);
    }

    public function test_getAllUnlockables_themes_have_required_fields(): void
    {
        $all = AchievementUnlockablesService::getAllUnlockables();

        foreach ($all['themes'] as $key => $theme) {
            $this->assertArrayHasKey('name', $theme, "Theme $key missing name");
            $this->assertArrayHasKey('type', $theme, "Theme $key missing type");
            $this->assertArrayHasKey('requirement', $theme, "Theme $key missing requirement");
            $this->assertSame('theme', $theme['type']);
        }
    }

    // --- Real-DB unlock logic (converted from DB::shouldReceive stubs) ---

    public function test_getUserUnlockables_returns_available_and_locked(): void
    {
        // A level-1 user with no badges: nothing is unlocked (lowest level
        // requirement is frame_bronze at level 5).
        $user = User::factory()->forTenant($this->testTenantId)->create(['level' => 1]);
        TenantContext::setById($this->testTenantId);

        $result = AchievementUnlockablesService::getUserUnlockables((int) $user->id);

        $this->assertArrayHasKey('available', $result);
        $this->assertArrayHasKey('locked', $result);

        // Nothing unlocked at level 1 with no badges.
        $this->assertSame([], $result['available']);

        // theme_dark_gold (requires level 10) must be locked for a level-1 user.
        $this->assertArrayHasKey('themes', $result['locked']);
        $this->assertArrayHasKey('theme_dark_gold', $result['locked']['themes']);
        $this->assertFalse($result['locked']['themes']['theme_dark_gold']['unlocked']);
    }

    public function test_getUserUnlockables_unlocks_by_level_and_badge(): void
    {
        // High level + a badge: level-gated items and the matching badge-gated
        // item must both land in `available`.
        $user = User::factory()->forTenant($this->testTenantId)->create(['level' => 50]);
        DB::table('user_badges')->insert([
            'tenant_id'  => $this->testTenantId,
            'user_id'    => $user->id,
            'badge_key'  => 'volunteer_5',
            'awarded_at' => now(),
        ]);
        TenantContext::setById($this->testTenantId);

        $result = AchievementUnlockablesService::getUserUnlockables((int) $user->id);

        // Level-gated theme (level 10) unlocked for a level-50 user.
        $this->assertArrayHasKey('themes', $result['available']);
        $this->assertArrayHasKey('theme_dark_gold', $result['available']['themes']);
        $this->assertTrue($result['available']['themes']['theme_dark_gold']['unlocked']);
        $this->assertSame('themes', $result['available']['themes']['theme_dark_gold']['category']);
        $this->assertSame('theme_dark_gold', $result['available']['themes']['theme_dark_gold']['key']);

        // Badge-gated theme (requires badge volunteer_5) is unlocked via the badge.
        $this->assertArrayHasKey('theme_forest', $result['available']['themes']);

        // Highest level item (theme_legendary, level 50) is unlocked at exactly level 50.
        $this->assertArrayHasKey('theme_legendary', $result['available']['themes']);

        // A badge the user does NOT hold keeps its item locked (emoji_fire needs streak_7).
        $this->assertArrayHasKey('special_emojis', $result['locked']);
        $this->assertArrayHasKey('emoji_fire', $result['locked']['special_emojis']);
    }

    public function test_setActiveUnlockable_returns_false_when_not_unlocked(): void
    {
        // A level-1 user has unlocked nothing, so setActiveUnlockable must return
        // false BEFORE reaching the (tenant_id) upsert — this path is real.
        $user = User::factory()->forTenant($this->testTenantId)->create(['level' => 1]);
        TenantContext::setById($this->testTenantId);

        $result = AchievementUnlockablesService::setActiveUnlockable(
            (int) $user->id,
            'theme',
            'theme_legendary'
        );

        $this->assertFalse($result);
    }

    // --- Deferred: test-DB schema drift (user_active_unlockables.tenant_id missing) ---

    public function test_getUserActiveUnlockables_returns_array(): void
    {
        $this->markTestSkipped(
            'getUserActiveUnlockables() queries user_active_unlockables.tenant_id, '
            . 'but the nexus_test table has no tenant_id column (schema drift). '
            . 'The call throws SQLSTATE[42S22] Unknown column tenant_id. '
            . 'Cannot be exercised without altering the test schema.'
        );
    }

    public function test_removeActiveUnlockable_returns_true(): void
    {
        $this->markTestSkipped(
            'removeActiveUnlockable() filters user_active_unlockables on tenant_id, '
            . 'but the nexus_test table has no tenant_id column (schema drift). '
            . 'The DELETE throws SQLSTATE[42S22] Unknown column tenant_id. '
            . 'Cannot be exercised without altering the test schema.'
        );
    }
}
