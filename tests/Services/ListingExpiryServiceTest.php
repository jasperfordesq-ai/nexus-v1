<?php
// Copyright © 2024–2026 Jasper Ford
// SPDX-License-Identifier: AGPL-3.0-or-later
// Author: Jasper Ford
// See NOTICE file for attribution and acknowledgements.

declare(strict_types=1);

namespace App\Tests\Services;

use App\Tests\TestCase;
use App\Services\ListingExpiryService;
use App\Core\TenantContext;

/**
 * ListingExpiryService Tests
 */
class ListingExpiryServiceTest extends TestCase
{
    private ListingExpiryService $service;
    private static int $testTenantId = 2;

    protected function setUp(): void
    {
        parent::setUp();
        TenantContext::setById(self::$testTenantId);
        $this->service = new ListingExpiryService();
    }

    public function test_service_can_be_instantiated(): void
    {
        $this->assertInstanceOf(ListingExpiryService::class, $this->service);
    }

    public function test_process_expired_listings_returns_expected_structure(): void
    {
        $result = $this->service->processExpiredListings();
        $this->assertIsArray($result);
        $this->assertArrayHasKey('expired', $result);
        $this->assertArrayHasKey('errors', $result);
        $this->assertIsInt($result['expired']);
        $this->assertIsInt($result['errors']);
    }

    public function test_process_expired_listings_non_negative_counts(): void
    {
        $result = $this->service->processExpiredListings();
        $this->assertGreaterThanOrEqual(0, $result['expired']);
        $this->assertGreaterThanOrEqual(0, $result['errors']);
    }

    public function test_process_all_tenants_returns_expected_structure(): void
    {
        $result = $this->service->processAllTenants();
        $this->assertIsArray($result);
        $this->assertArrayHasKey('total_expired', $result);
        $this->assertArrayHasKey('total_errors', $result);
        $this->assertArrayHasKey('tenants_processed', $result);
        $this->assertIsInt($result['total_expired']);
        $this->assertIsInt($result['total_errors']);
        $this->assertIsInt($result['tenants_processed']);
    }

    public function test_renew_listing_nonexistent_returns_failure(): void
    {
        $result = $this->service->renewListing(999999, 1);
        $this->assertIsArray($result);
        $this->assertArrayHasKey('success', $result);
        $this->assertArrayHasKey('error', $result);
        $this->assertArrayHasKey('new_expires_at', $result);
        $this->assertFalse($result['success']);
        $this->assertSame('Listing not found', $result['error']);
        $this->assertNull($result['new_expires_at']);
    }

    public function test_set_expiry_nonexistent_returns_false(): void
    {
        $result = $this->service->setExpiry(999999, '2030-01-01 00:00:00');
        $this->assertFalse($result);
    }

    public function test_set_expiry_null_date(): void
    {
        $result = $this->service->setExpiry(999999, null);
        $this->assertFalse($result);
    }

    public function test_renewal_days_constant(): void
    {
        $reflection = new \ReflectionClass(ListingExpiryService::class);
        $this->assertSame(30, $reflection->getConstant('RENEWAL_DAYS'));
    }

    public function test_max_renewals_constant(): void
    {
        $reflection = new \ReflectionClass(ListingExpiryService::class);
        $this->assertSame(12, $reflection->getConstant('MAX_RENEWALS'));
    }
}
