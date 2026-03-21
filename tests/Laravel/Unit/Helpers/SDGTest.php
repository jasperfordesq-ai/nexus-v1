<?php
// Copyright © 2024–2026 Jasper Ford
// SPDX-License-Identifier: AGPL-3.0-or-later
// Author: Jasper Ford
// See NOTICE file for attribution and acknowledgements.

namespace Tests\Laravel\Unit\Helpers;

use App\Helpers\SDG;
use PHPUnit\Framework\TestCase;

class SDGTest extends TestCase
{
    // -------------------------------------------------------
    // all()
    // -------------------------------------------------------

    public function test_all_returns_17_goals(): void
    {
        $goals = SDG::all();
        $this->assertCount(17, $goals);
    }

    public function test_all_keys_are_1_through_17(): void
    {
        $goals = SDG::all();
        for ($i = 1; $i <= 17; $i++) {
            $this->assertArrayHasKey($i, $goals);
        }
    }

    public function test_all_each_goal_has_required_keys(): void
    {
        foreach (SDG::all() as $id => $goal) {
            $this->assertArrayHasKey('label', $goal, "SDG {$id} missing 'label'");
            $this->assertArrayHasKey('color', $goal, "SDG {$id} missing 'color'");
            $this->assertArrayHasKey('icon', $goal, "SDG {$id} missing 'icon'");
        }
    }

    public function test_all_colors_are_hex_format(): void
    {
        foreach (SDG::all() as $id => $goal) {
            $this->assertMatchesRegularExpression('/^#[0-9A-Fa-f]{6}$/', $goal['color'], "SDG {$id} has invalid color");
        }
    }

    // -------------------------------------------------------
    // get()
    // -------------------------------------------------------

    public function test_get_returns_goal_for_valid_id(): void
    {
        $goal = SDG::get(1);
        $this->assertNotNull($goal);
        $this->assertSame('No Poverty', $goal['label']);
    }

    public function test_get_returns_goal_13_climate_action(): void
    {
        $goal = SDG::get(13);
        $this->assertSame('Climate Action', $goal['label']);
        $this->assertSame('#3F7E44', $goal['color']);
    }

    public function test_get_returns_null_for_invalid_id(): void
    {
        $this->assertNull(SDG::get(0));
        $this->assertNull(SDG::get(18));
        $this->assertNull(SDG::get(999));
    }
}
