<?php
// Copyright © 2024–2026 Jasper Ford
// SPDX-License-Identifier: AGPL-3.0-or-later
// Author: Jasper Ford
// See NOTICE file for attribution and acknowledgements.

namespace Tests\Services;

use PHPUnit\Framework\TestCase;
use Nexus\Services\AchievementUnlockablesService;

class AchievementUnlockablesServiceTest extends TestCase
{
    public function testClassExists(): void
    {
        $this->assertTrue(class_exists(AchievementUnlockablesService::class));
    }

    public function testTypesConstant(): void
    {
        $types = AchievementUnlockablesService::TYPES;

        $this->assertIsArray($types);
        $this->assertArrayHasKey('theme', $types);
        $this->assertArrayHasKey('avatar_frame', $types);
        $this->assertArrayHasKey('badge_style', $types);
        $this->assertArrayHasKey('profile_banner', $types);
        $this->assertArrayHasKey('name_color', $types);
        $this->assertArrayHasKey('special_emoji', $types);
    }

    public function testGetAllUnlockablesMethodExists(): void
    {
        $this->assertTrue(method_exists(AchievementUnlockablesService::class, 'getAllUnlockables'));
    }

    public function testGetAllUnlockablesReturnsArray(): void
    {
        $unlockables = AchievementUnlockablesService::getAllUnlockables();
        $this->assertIsArray($unlockables);
        $this->assertArrayHasKey('themes', $unlockables);
    }

    public function testUnlockablesHaveRequiredStructure(): void
    {
        $unlockables = AchievementUnlockablesService::getAllUnlockables();

        foreach ($unlockables['themes'] as $key => $theme) {
            $this->assertArrayHasKey('name', $theme, "Theme '{$key}' missing 'name'");
            $this->assertArrayHasKey('type', $theme, "Theme '{$key}' missing 'type'");
            $this->assertArrayHasKey('preview', $theme, "Theme '{$key}' missing 'preview'");
            $this->assertArrayHasKey('requirement', $theme, "Theme '{$key}' missing 'requirement'");
            $this->assertEquals('theme', $theme['type']);
        }
    }
}
