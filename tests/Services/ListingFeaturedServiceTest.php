<?php
// Copyright © 2024–2026 Jasper Ford
// SPDX-License-Identifier: AGPL-3.0-or-later
// Author: Jasper Ford
// See NOTICE file for attribution and acknowledgements.

declare(strict_types=1);

namespace App\Tests\Services;

use App\Tests\TestCase;
use App\Services\ListingFeaturedService;
use App\Core\TenantContext;

/**
 * ListingFeaturedService Tests
 */
class ListingFeaturedServiceTest extends TestCase
{
    private ListingFeaturedService $service;
    private static int $testTenantId = 2;

    protected function setUp(): void
    {
        parent::setUp();
        TenantContext::setById(self::$testTenantId);
        $this->service = new ListingFeaturedService();
    }

    public function test_service_can_be_instantiated(): void
    {
        $this->assertInstanceOf(ListingFeaturedService::class, $this->service);
    }

    public function test_feature_listing_nonexistent_returns_failure(): void
    {
        $result = $this->service->featureListing(999999);
        $this->assertIsArray($result);
        $this->assertArrayHasKey('success', $result);
        $this->assertArrayHasKey('error', $result);
        $this->assertFalse($result['success']);
        $this->assertSame('Listing not found', $result['error']);
    }

    public function test_feature_listing_with_days(): void
    {
        $result = $this->service->featureListing(999999, 7);
        $this->assertIsArray($result);
        $this->assertFalse($result['success']);
    }

    public function test_unfeature_listing_nonexistent_returns_failure(): void
    {
        $result = $this->service->unfeatureListing(999999);
        $this->assertIsArray($result);
        $this->assertArrayHasKey('success', $result);
        $this->assertArrayHasKey('error', $result);
        $this->assertFalse($result['success']);
        $this->assertSame('Listing not found', $result['error']);
    }

    public function test_process_expired_featured_returns_int(): void
    {
        $result = $this->service->processExpiredFeatured();
        $this->assertIsInt($result);
        $this->assertGreaterThanOrEqual(0, $result);
    }

    public function test_get_featured_listings_returns_array(): void
    {
        $result = $this->service->getFeaturedListings();
        $this->assertIsArray($result);
    }

    public function test_get_featured_listings_respects_limit(): void
    {
        $result = $this->service->getFeaturedListings(3);
        $this->assertIsArray($result);
        $this->assertLessThanOrEqual(3, count($result));
    }

    public function test_is_featured_returns_false_for_nonexistent(): void
    {
        $result = $this->service->isFeatured(999999);
        $this->assertFalse($result);
    }

    public function test_default_feature_days_constant(): void
    {
        $reflection = new \ReflectionClass(ListingFeaturedService::class);
        $this->assertSame(7, $reflection->getConstant('DEFAULT_FEATURE_DAYS'));
    }
}
