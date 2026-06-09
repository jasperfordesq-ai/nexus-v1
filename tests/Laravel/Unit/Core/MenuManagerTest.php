<?php
// Copyright © 2024–2026 Jasper Ford
// SPDX-License-Identifier: AGPL-3.0-or-later
// Author: Jasper Ford
// See NOTICE file for attribution and acknowledgements.

namespace Tests\Laravel\Unit\Core;

use App\Core\MenuManager;
use PHPUnit\Framework\TestCase;

class MenuManagerTest extends TestCase
{
    // -------------------------------------------------------
    // Constants
    // -------------------------------------------------------

    public function test_location_constants_are_defined(): void
    {
        $this->assertSame('header-main', MenuManager::LOCATION_HEADER_MAIN);
        $this->assertSame('header-secondary', MenuManager::LOCATION_HEADER_SECONDARY);
        $this->assertSame('footer', MenuManager::LOCATION_FOOTER);
        $this->assertSame('sidebar', MenuManager::LOCATION_SIDEBAR);
        $this->assertSame('mobile', MenuManager::LOCATION_MOBILE);
    }

    public function test_legacy_menu_constants_are_defined(): void
    {
        $this->assertSame('about', MenuManager::MENU_ABOUT);
        $this->assertSame('main', MenuManager::MENU_MAIN);
        $this->assertSame('footer', MenuManager::MENU_FOOTER);
    }

    // -------------------------------------------------------
    // Visibility filtering (server-side gating)
    // -------------------------------------------------------

    /**
     * @param array<int,array<string,mixed>> $items
     * @param array<string,mixed>|null       $user
     * @param array<string,bool>             $features
     * @return list<string>
     */
    private function filterLabels(array $items, ?array $user, array $features): array
    {
        $method = new \ReflectionMethod(MenuManager::class, 'filterVisibleItems');
        $method->setAccessible(true);
        $visible = $method->invoke(null, $items, $user, $features);

        return array_values(array_map(static fn ($i) => $i['label'], $visible));
    }

    /** Items written with the canonical (React/seeder) visibility vocabulary. */
    private function sampleItems(): array
    {
        return [
            ['label' => 'Home', 'visibility_rules' => [], 'children' => []],
            ['label' => 'Members', 'visibility_rules' => ['requires_auth' => true], 'children' => []],
            ['label' => 'Groups', 'visibility_rules' => ['requires_feature' => 'groups'], 'children' => []],
            ['label' => 'LegacyAuth', 'visibility_rules' => ['require_auth' => true], 'children' => []],
        ];
    }

    public function test_filter_hides_auth_and_feature_items_from_guests(): void
    {
        // Guest: requires_auth / require_auth items hidden; an enabled feature item stays.
        $labels = $this->filterLabels($this->sampleItems(), null, ['groups' => true]);
        $this->assertSame(['Home', 'Groups'], $labels);
    }

    public function test_filter_honours_canonical_requires_feature(): void
    {
        // Feature disabled → the canonical `requires_feature` item is hidden.
        $labels = $this->filterLabels($this->sampleItems(), ['role' => 'member'], []);
        $this->assertSame(['Home', 'Members', 'LegacyAuth'], $labels);
    }

    public function test_filter_does_not_leak_or_over_hide_for_default_member_role(): void
    {
        // Regression guard: a 'member' (the default role, absent from any ordered
        // role list) must SEE every auth-only item — server-side gating must not
        // fail open OR over-hide for roles outside the legacy hierarchy.
        $labels = $this->filterLabels($this->sampleItems(), ['role' => 'member'], ['groups' => true]);
        $this->assertSame(['Home', 'Members', 'Groups', 'LegacyAuth'], $labels);
    }
}
