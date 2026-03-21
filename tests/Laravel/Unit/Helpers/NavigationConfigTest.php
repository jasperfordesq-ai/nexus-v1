<?php
// Copyright © 2024–2026 Jasper Ford
// SPDX-License-Identifier: AGPL-3.0-or-later
// Author: Jasper Ford
// See NOTICE file for attribution and acknowledgements.

namespace Tests\Laravel\Unit\Helpers;

use App\Helpers\NavigationConfig;
use Tests\Laravel\TestCase;

class NavigationConfigTest extends TestCase
{
    // -------------------------------------------------------
    // getPrimaryNav()
    // -------------------------------------------------------

    public function test_getPrimaryNav_returns_array(): void
    {
        $items = NavigationConfig::getPrimaryNav();
        $this->assertIsArray($items);
        $this->assertNotEmpty($items);
    }

    public function test_getPrimaryNav_contains_feed_and_listings(): void
    {
        $items = NavigationConfig::getPrimaryNav();
        $keys = array_column($items, 'key');
        $this->assertContains('home', $keys);
        $this->assertContains('listings', $keys);
    }

    public function test_getPrimaryNav_items_have_required_keys(): void
    {
        $items = NavigationConfig::getPrimaryNav();
        foreach ($items as $item) {
            $this->assertArrayHasKey('label', $item);
            $this->assertArrayHasKey('url', $item);
            $this->assertArrayHasKey('icon', $item);
            $this->assertArrayHasKey('key', $item);
        }
    }

    // -------------------------------------------------------
    // getCommunityNav()
    // -------------------------------------------------------

    public function test_getCommunityNav_returns_array(): void
    {
        $items = NavigationConfig::getCommunityNav();
        $this->assertIsArray($items);
        $this->assertNotEmpty($items);
    }

    public function test_getCommunityNav_contains_members(): void
    {
        $items = NavigationConfig::getCommunityNav();
        $labels = array_column($items, 'label');
        $this->assertContains('Members', $labels);
    }

    // -------------------------------------------------------
    // getExploreNav()
    // -------------------------------------------------------

    public function test_getExploreNav_returns_array(): void
    {
        $items = NavigationConfig::getExploreNav();
        $this->assertIsArray($items);
    }

    public function test_getExploreNav_contains_leaderboard(): void
    {
        $items = NavigationConfig::getExploreNav();
        $labels = array_column($items, 'label');
        $this->assertContains('Leaderboard', $labels);
    }

    // -------------------------------------------------------
    // getHelpNav()
    // -------------------------------------------------------

    public function test_getHelpNav_returns_array(): void
    {
        $items = NavigationConfig::getHelpNav();
        $this->assertIsArray($items);
        $this->assertNotEmpty($items);
    }

    public function test_getHelpNav_contains_help_center(): void
    {
        $items = NavigationConfig::getHelpNav();
        $labels = array_column($items, 'label');
        $this->assertContains('Help Center', $labels);
    }

    // -------------------------------------------------------
    // getAboutNav()
    // -------------------------------------------------------

    public function test_getAboutNav_returns_array(): void
    {
        $items = NavigationConfig::getAboutNav();
        $this->assertIsArray($items);
    }

    // -------------------------------------------------------
    // getSecondaryNav()
    // -------------------------------------------------------

    public function test_getSecondaryNav_returns_grouped_array(): void
    {
        $nav = NavigationConfig::getSecondaryNav();
        $this->assertIsArray($nav);
        $this->assertArrayHasKey('community', $nav);
        $this->assertArrayHasKey('explore', $nav);
        $this->assertArrayHasKey('about', $nav);
        $this->assertArrayHasKey('help', $nav);
    }

    public function test_getSecondaryNav_groups_have_title_and_items(): void
    {
        $nav = NavigationConfig::getSecondaryNav();
        foreach ($nav as $group) {
            $this->assertArrayHasKey('title', $group);
            $this->assertArrayHasKey('items', $group);
            $this->assertIsArray($group['items']);
        }
    }

    // -------------------------------------------------------
    // getFlatSecondaryNav()
    // -------------------------------------------------------

    public function test_getFlatSecondaryNav_returns_flat_array(): void
    {
        $items = NavigationConfig::getFlatSecondaryNav();
        $this->assertIsArray($items);
        $this->assertNotEmpty($items);
        // Should be flat (each item is a nav item, not a group)
        foreach ($items as $item) {
            $this->assertArrayHasKey('label', $item);
            $this->assertArrayHasKey('url', $item);
        }
    }

    // -------------------------------------------------------
    // getGamificationNav()
    // -------------------------------------------------------

    public function test_getGamificationNav_returns_gamification_items_only(): void
    {
        $items = NavigationConfig::getGamificationNav();
        $this->assertIsArray($items);
        foreach ($items as $item) {
            $this->assertSame('gamification', $item['category']);
        }
    }

    public function test_getGamificationNav_contains_leaderboard_and_achievements(): void
    {
        $items = array_values(NavigationConfig::getGamificationNav());
        $labels = array_column($items, 'label');
        $this->assertContains('Leaderboard', $labels);
        $this->assertContains('Achievements', $labels);
    }
}
