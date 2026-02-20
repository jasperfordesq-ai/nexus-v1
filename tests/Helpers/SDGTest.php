<?php
// Copyright © 2024–2026 Jasper Ford
// SPDX-License-Identifier: AGPL-3.0-or-later
// Author: Jasper Ford
// See NOTICE file for attribution and acknowledgements.

declare(strict_types=1);

namespace Nexus\Tests\Helpers;

use Nexus\Tests\TestCase;
use Nexus\Helpers\SDG;

/**
 * SDG (Sustainable Development Goals) Tests
 *
 * Tests SDG data helpers for the 17 UN Sustainable Development Goals.
 * Includes goals labels, colors, and icons.
 *
 * @covers \Nexus\Helpers\SDG
 */
class SDGTest extends TestCase
{
    // =========================================================================
    // CLASS STRUCTURE TESTS
    // =========================================================================

    public function testClassExists(): void
    {
        $this->assertTrue(class_exists(SDG::class));
    }

    public function testPublicMethodsExist(): void
    {
        $this->assertTrue(method_exists(SDG::class, 'all'));
        $this->assertTrue(method_exists(SDG::class, 'get'));
    }

    // =========================================================================
    // ALL GOALS TESTS
    // =========================================================================

    public function testAllReturnsArray(): void
    {
        $goals = SDG::all();

        $this->assertIsArray($goals);
        $this->assertCount(17, $goals);
    }

    public function testAllGoalsHaveRequiredFields(): void
    {
        $goals = SDG::all();

        foreach ($goals as $id => $goal) {
            $this->assertIsInt($id, "Goal ID should be integer");
            $this->assertArrayHasKey('label', $goal, "Goal {$id} should have label");
            $this->assertArrayHasKey('color', $goal, "Goal {$id} should have color");
            $this->assertArrayHasKey('icon', $goal, "Goal {$id} should have icon");

            $this->assertIsString($goal['label']);
            $this->assertIsString($goal['color']);
            $this->assertIsString($goal['icon']);

            $this->assertNotEmpty($goal['label']);
            $this->assertNotEmpty($goal['color']);
            $this->assertNotEmpty($goal['icon']);
        }
    }

    public function testAllGoalsAreNumbered1To17(): void
    {
        $goals = SDG::all();

        $this->assertArrayHasKey(1, $goals);
        $this->assertArrayHasKey(17, $goals);
        $this->assertArrayNotHasKey(0, $goals);
        $this->assertArrayNotHasKey(18, $goals);

        // Check all IDs from 1 to 17 exist
        for ($i = 1; $i <= 17; $i++) {
            $this->assertArrayHasKey($i, $goals, "Goal {$i} should exist");
        }
    }

    public function testAllGoalsHaveUniqueLabels(): void
    {
        $goals = SDG::all();
        $labels = array_column($goals, 'label');

        $this->assertCount(17, array_unique($labels), "All goal labels should be unique");
    }

    public function testAllGoalsHaveUniqueColors(): void
    {
        $goals = SDG::all();
        $colors = array_column($goals, 'color');

        $this->assertCount(17, array_unique($colors), "All goal colors should be unique");
    }

    public function testAllGoalsColorsAreHexFormat(): void
    {
        $goals = SDG::all();

        foreach ($goals as $id => $goal) {
            $this->assertMatchesRegularExpression(
                '/^#[0-9A-F]{6}$/i',
                $goal['color'],
                "Goal {$id} color should be valid hex color"
            );
        }
    }

    // =========================================================================
    // SPECIFIC GOAL TESTS
    // =========================================================================

    public function testGoal1NoPoverty(): void
    {
        $goal = SDG::get(1);

        $this->assertIsArray($goal);
        $this->assertEquals('No Poverty', $goal['label']);
        $this->assertEquals('#E5243B', $goal['color']);
        $this->assertEquals('🏘️', $goal['icon']);
    }

    public function testGoal2ZeroHunger(): void
    {
        $goal = SDG::get(2);

        $this->assertEquals('Zero Hunger', $goal['label']);
        $this->assertEquals('#DDA63A', $goal['color']);
        $this->assertEquals('🍲', $goal['icon']);
    }

    public function testGoal13ClimateAction(): void
    {
        $goal = SDG::get(13);

        $this->assertEquals('Climate Action', $goal['label']);
        $this->assertEquals('#3F7E44', $goal['color']);
        $this->assertEquals('🌍', $goal['icon']);
    }

    public function testGoal17Partnerships(): void
    {
        $goal = SDG::get(17);

        $this->assertEquals('Partnerships', $goal['label']);
        $this->assertEquals('#19486A', $goal['color']);
        $this->assertEquals('🔗', $goal['icon']);
    }

    // =========================================================================
    // GET METHOD TESTS
    // =========================================================================

    public function testGetReturnsGoalForValidId(): void
    {
        for ($i = 1; $i <= 17; $i++) {
            $goal = SDG::get($i);

            $this->assertIsArray($goal, "Goal {$i} should return array");
            $this->assertArrayHasKey('label', $goal);
            $this->assertArrayHasKey('color', $goal);
            $this->assertArrayHasKey('icon', $goal);
        }
    }

    public function testGetReturnsNullForInvalidId(): void
    {
        $this->assertNull(SDG::get(0));
        $this->assertNull(SDG::get(18));
        $this->assertNull(SDG::get(100));
        $this->assertNull(SDG::get(-1));
    }

    public function testGetReturnsNullForNonNumericId(): void
    {
        $this->assertNull(SDG::get('invalid'));
        $this->assertNull(SDG::get('abc'));
    }

    // =========================================================================
    // DATA INTEGRITY TESTS
    // =========================================================================

    public function testAllGoalsHaveEmojiIcons(): void
    {
        $goals = SDG::all();

        foreach ($goals as $id => $goal) {
            // Emoji characters exist (may be 1 or more characters in UTF-8)
            $icon = $goal['icon'];
            $this->assertGreaterThanOrEqual(
                1,
                mb_strlen($icon, 'UTF-8'),
                "Goal {$id} icon should have at least one character"
            );
            $this->assertNotEmpty($icon);
        }
    }

    public function testGoalLabelsAreNonEmpty(): void
    {
        $goals = SDG::all();

        foreach ($goals as $id => $goal) {
            $this->assertNotEmpty(trim($goal['label']), "Goal {$id} label should not be empty");
        }
    }

    public function testAllMethodReturnsSameDataOnMultipleCalls(): void
    {
        $goals1 = SDG::all();
        $goals2 = SDG::all();

        $this->assertEquals($goals1, $goals2, "all() should return consistent data");
    }

    public function testGetMethodReturnsSameDataOnMultipleCalls(): void
    {
        $goal1 = SDG::get(5);
        $goal2 = SDG::get(5);

        $this->assertEquals($goal1, $goal2, "get() should return consistent data");
    }

    // =========================================================================
    // COMPREHENSIVE GOAL LIST TEST
    // =========================================================================

    public function testAllGoalLabelsAreCorrect(): void
    {
        $expectedLabels = [
            1 => 'No Poverty',
            2 => 'Zero Hunger',
            3 => 'Good Health',
            4 => 'Quality Education',
            5 => 'Gender Equality',
            6 => 'Clean Water',
            7 => 'Clean Energy',
            8 => 'Decent Work',
            9 => 'Innovation',
            10 => 'Reduced Inequalities',
            11 => 'Sustainable Cities',
            12 => 'Responsible Consumption',
            13 => 'Climate Action',
            14 => 'Life Below Water',
            15 => 'Life on Land',
            16 => 'Peace & Justice',
            17 => 'Partnerships',
        ];

        $goals = SDG::all();

        foreach ($expectedLabels as $id => $expectedLabel) {
            $this->assertEquals(
                $expectedLabel,
                $goals[$id]['label'],
                "Goal {$id} label should match expected value"
            );
        }
    }

    public function testGoalColorsMatchUNBranding(): void
    {
        // UN official SDG colors (verified from UN SDG guidelines)
        $expectedColors = [
            1 => '#E5243B',  // Red
            2 => '#DDA63A',  // Gold
            3 => '#4C9F38',  // Green
            4 => '#C5192D',  // Dark Red
            5 => '#FF3A21',  // Orange-Red
            6 => '#26BDE2',  // Cyan
            7 => '#FCC30B',  // Yellow
            8 => '#A21942',  // Maroon
            9 => '#FD6925',  // Orange
            10 => '#DD1367', // Magenta
            11 => '#FD9D24', // Yellow-Orange
            12 => '#BF8B2E', // Gold-Brown
            13 => '#3F7E44', // Green
            14 => '#0A97D9', // Blue
            15 => '#56C02B', // Lime Green
            16 => '#00689D', // Dark Blue
            17 => '#19486A', // Navy
        ];

        $goals = SDG::all();

        foreach ($expectedColors as $id => $expectedColor) {
            $this->assertEquals(
                $expectedColor,
                $goals[$id]['color'],
                "Goal {$id} color should match UN official color"
            );
        }
    }
}
