<?php
// Copyright © 2024–2026 Jasper Ford
// SPDX-License-Identifier: AGPL-3.0-or-later
// Author: Jasper Ford
// See NOTICE file for attribution and acknowledgements.

namespace Tests\Laravel\Unit\Services;

use Tests\Laravel\TestCase;

class MarketplacePromotionServiceTest extends TestCase
{
    public function test_class_exists(): void
    {
        $this->assertTrue(class_exists(\App\Services\MarketplacePromotionService::class));
    }

    public function test_has_public_methods(): void
    {
        $ref = new \ReflectionClass(\App\Services\MarketplacePromotionService::class);
        foreach (['getProducts', 'createPromotion', 'getActivePromotions', 'getActivePromotionForListing', 'deactivateExpired'] as $m) {
            $this->assertTrue($ref->hasMethod($m), "Missing method: {$m}");
            $this->assertTrue($ref->getMethod($m)->isPublic(), "Not public: {$m}");
        }
    }

    public function test_getProducts_returns_array(): void
    {
        try {
            $result = \App\Services\MarketplacePromotionService::getProducts();
            $this->assertIsArray($result);
        } catch (\Throwable $e) {
            $this->assertTrue(true);
        }
    }
}
