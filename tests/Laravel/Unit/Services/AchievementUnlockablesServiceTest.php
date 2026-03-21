<?php
// Copyright © 2024–2026 Jasper Ford
// SPDX-License-Identifier: AGPL-3.0-or-later
// Author: Jasper Ford
// See NOTICE file for attribution and acknowledgements.

namespace Tests\Laravel\Unit\Services;

use Tests\Laravel\TestCase;
use App\Services\AchievementUnlockablesService;
use Illuminate\Support\Facades\DB;

class AchievementUnlockablesServiceTest extends TestCase
{
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

    public function test_getUserUnlockables_returns_available_and_locked(): void
    {
        // Mock DB calls for getUserLevel and getUserBadgeKeys
        DB::shouldReceive('table')->andReturnSelf();
        DB::shouldReceive('where')->andReturnSelf();
        DB::shouldReceive('first')->andReturn((object) ['level' => 1]);
        DB::shouldReceive('pluck')->andReturn(collect([]));

        $result = AchievementUnlockablesService::getUserUnlockables(1);

        $this->assertArrayHasKey('available', $result);
        $this->assertArrayHasKey('locked', $result);
    }

    public function test_getUserActiveUnlockables_returns_array(): void
    {
        DB::shouldReceive('table')->with('user_active_unlockables')->andReturnSelf();
        DB::shouldReceive('where')->andReturnSelf();
        DB::shouldReceive('select')->andReturnSelf();
        DB::shouldReceive('get')->andReturn(collect([]));

        $result = AchievementUnlockablesService::getUserActiveUnlockables(1);

        $this->assertIsArray($result);
        $this->assertEmpty($result);
    }

    public function test_removeActiveUnlockable_returns_true(): void
    {
        DB::shouldReceive('table')->with('user_active_unlockables')->andReturnSelf();
        DB::shouldReceive('where')->andReturnSelf();
        DB::shouldReceive('delete')->andReturn(1);

        $result = AchievementUnlockablesService::removeActiveUnlockable(1, 'theme');
        $this->assertTrue($result);
    }

    public function test_setActiveUnlockable_returns_false_when_not_unlocked(): void
    {
        // Mock getUserUnlockables to return empty available
        DB::shouldReceive('table')->andReturnSelf();
        DB::shouldReceive('where')->andReturnSelf();
        DB::shouldReceive('first')->andReturn((object) ['level' => 1]);
        DB::shouldReceive('pluck')->andReturn(collect([]));

        $result = AchievementUnlockablesService::setActiveUnlockable(1, 'theme', 'theme_legendary');
        $this->assertFalse($result);
    }
}
