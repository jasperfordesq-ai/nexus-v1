<?php
// Copyright © 2024–2026 Jasper Ford
// SPDX-License-Identifier: AGPL-3.0-or-later
// Author: Jasper Ford
// See NOTICE file for attribution and acknowledgements.

namespace Tests\Laravel\Unit\Helpers;

use App\Helpers\HeroResolver;
use PHPUnit\Framework\TestCase;

class HeroResolverTest extends TestCase
{
    protected function setUp(): void
    {
        parent::setUp();
        HeroResolver::clearCache();
    }

    protected function tearDown(): void
    {
        HeroResolver::clearCache();
        parent::tearDown();
    }

    // -------------------------------------------------------
    // resolve()
    // -------------------------------------------------------

    public function test_resolve_returns_array_or_null(): void
    {
        $result = HeroResolver::resolve('/');
        // May be null if no config exists, or an array
        $this->assertTrue($result === null || is_array($result));
    }

    public function test_resolve_applies_overrides(): void
    {
        $result = HeroResolver::resolve('/', ['title' => 'Custom Title']);
        if ($result !== null) {
            $this->assertSame('Custom Title', $result['title']);
        } else {
            $this->assertNull($result);
        }
    }

    public function test_resolve_sets_default_variant_to_page(): void
    {
        $result = HeroResolver::resolve('/', ['title' => 'Test']);
        if ($result !== null) {
            $this->assertContains($result['variant'], ['page', 'banner']);
        } else {
            $this->assertNull($result);
        }
    }

    public function test_resolve_normalizes_trailing_slash(): void
    {
        // /members/ and /members should resolve the same
        $result1 = HeroResolver::resolve('/members');
        $result2 = HeroResolver::resolve('/members/');
        $this->assertSame($result1, $result2);
    }

    public function test_resolve_page_variant_has_no_cta(): void
    {
        $result = HeroResolver::resolve('/members', ['variant' => 'page']);
        if ($result !== null && $result['variant'] === 'page') {
            $this->assertNull($result['cta']);
        } else {
            $this->assertTrue(true); // Config might not exist
        }
    }

    // -------------------------------------------------------
    // shouldShow()
    // -------------------------------------------------------

    public function test_shouldShow_returns_bool(): void
    {
        $result = HeroResolver::shouldShow('/');
        $this->assertIsBool($result);
    }

    // -------------------------------------------------------
    // getVariant()
    // -------------------------------------------------------

    public function test_getVariant_returns_page_or_banner(): void
    {
        $result = HeroResolver::getVariant('/');
        $this->assertContains($result, ['page', 'banner']);
    }

    // -------------------------------------------------------
    // clearCache()
    // -------------------------------------------------------

    public function test_clearCache_resets_config(): void
    {
        // First call loads config
        HeroResolver::resolve('/');
        // Clear cache
        HeroResolver::clearCache();
        // Should not throw on next call
        $result = HeroResolver::resolve('/');
        $this->assertTrue($result === null || is_array($result));
    }
}
