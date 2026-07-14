<?php
// Copyright © 2024–2026 Jasper Ford
// SPDX-License-Identifier: AGPL-3.0-or-later
// Author: Jasper Ford
// See NOTICE file for attribution and acknowledgements.

namespace Tests\Laravel\Unit\Services;

use App\Models\Badge;
use App\Models\TenantBadgeOverride;
use App\Services\BadgeDefinitionService;
use Illuminate\Support\Facades\Cache;
use Tests\Laravel\TestCase;

class BadgeDefinitionServiceTest extends TestCase
{
    // ── getEnabledBadges: cache-backed ───────────────────────────────

    public function test_getEnabledBadges_uses_cache_remember_with_tenant_key(): void
    {
        $payload = [['key' => 'early_adopter', 'name' => 'Early Adopter']];

        Cache::shouldReceive('remember')
            ->once()
            ->with('badge_definitions:' . $this->testTenantId, 300, \Mockery::type('Closure'))
            ->andReturn($payload);

        $result = BadgeDefinitionService::getEnabledBadges();
        $this->assertSame($payload, $result);
    }

    public function test_getEnabledBadges_honors_explicit_tenantId(): void
    {
        Cache::shouldReceive('remember')
            ->once()
            ->with('badge_definitions:42', 300, \Mockery::type('Closure'))
            ->andReturn([]);

        $this->assertSame([], BadgeDefinitionService::getEnabledBadges(42));
    }

    public function test_builtin_copy_exposes_codes_but_tenant_authored_copy_does_not(): void
    {
        $badge = (new Badge())->forceFill([
            'badge_key' => 'community_hero',
            'name' => 'Community Hero',
            'description' => 'Help the community.',
            'icon' => 'award',
            'category' => 'community',
            'threshold' => 10,
            'badge_tier' => 'template',
            'badge_class' => 'quantity',
            'threshold_type' => 'count',
            'evaluation_method' => 'automatic',
            'config_json' => null,
            'rarity' => 'common',
            'xp_value' => 25,
            'is_enabled' => true,
        ]);

        $merge = new \ReflectionMethod(BadgeDefinitionService::class, 'mergeBadgeWithOverride');
        $builtIn = $merge->invoke(null, $badge, null);

        $this->assertSame('badges.community_hero.name', $builtIn['name_code']);
        $this->assertSame('badges.community_hero.description', $builtIn['description_code']);

        $override = (new TenantBadgeOverride())->forceFill([
            'custom_name' => 'Neighbourhood Star',
            'custom_description' => 'Tenant-authored badge copy.',
            'custom_icon' => null,
            'custom_threshold' => null,
            'is_enabled' => true,
        ]);
        $custom = $merge->invoke(null, $badge, $override);

        $this->assertSame('Neighbourhood Star', $custom['name']);
        $this->assertSame('Tenant-authored badge copy.', $custom['description']);
        $this->assertNull($custom['name_code']);
        $this->assertNull($custom['description_code']);
    }

    // ── getBadgeByKey ────────────────────────────────────────────────

    public function test_getBadgeByKey_returns_matching_badge(): void
    {
        Cache::shouldReceive('remember')->andReturn([
            ['key' => 'foo', 'name' => 'Foo'],
            ['key' => 'bar', 'name' => 'Bar'],
        ]);

        $result = BadgeDefinitionService::getBadgeByKey('bar');
        $this->assertSame('Bar', $result['name']);
    }

    public function test_getBadgeByKey_returns_null_when_not_found(): void
    {
        Cache::shouldReceive('remember')->andReturn([
            ['key' => 'foo', 'name' => 'Foo'],
        ]);

        $this->assertNull(BadgeDefinitionService::getBadgeByKey('missing'));
    }

    // ── getBadgesByCategory ──────────────────────────────────────────

    public function test_getBadgesByCategory_groups_by_type(): void
    {
        Cache::shouldReceive('remember')->andReturn([
            ['key' => 'a', 'type' => 'volunteer'],
            ['key' => 'b', 'type' => 'volunteer'],
            ['key' => 'c', 'type' => 'social'],
        ]);

        $grouped = BadgeDefinitionService::getBadgesByCategory();
        $this->assertCount(2, $grouped['volunteer']);
        $this->assertCount(1, $grouped['social']);
    }

    // ── getBadgesByClass ─────────────────────────────────────────────

    public function test_getBadgesByClass_filters_by_badge_class(): void
    {
        Cache::shouldReceive('remember')->andReturn([
            ['key' => 'a', 'badge_class' => 'quantity'],
            ['key' => 'b', 'badge_class' => 'quality'],
            ['key' => 'c', 'badge_class' => 'quantity'],
        ]);

        $result = BadgeDefinitionService::getBadgesByClass('quantity');
        $this->assertCount(2, $result);
        $this->assertSame('a', $result[0]['key']);
        $this->assertSame('c', $result[1]['key']);
    }

    // ── clearCache ───────────────────────────────────────────────────

    public function test_clearCache_forgets_cache_key_for_tenant(): void
    {
        Cache::shouldReceive('forget')
            ->once()
            ->with('badge_definitions:' . $this->testTenantId);

        BadgeDefinitionService::clearCache();
        $this->assertTrue(true);
    }
}
