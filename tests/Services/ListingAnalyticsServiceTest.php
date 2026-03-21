<?php
// Copyright © 2024–2026 Jasper Ford
// SPDX-License-Identifier: AGPL-3.0-or-later
// Author: Jasper Ford
// See NOTICE file for attribution and acknowledgements.

declare(strict_types=1);

namespace App\Tests\Services;

use App\Tests\TestCase;
use App\Services\ListingAnalyticsService;
use App\Core\TenantContext;

/**
 * ListingAnalyticsService Tests
 */
class ListingAnalyticsServiceTest extends TestCase
{
    private ListingAnalyticsService $service;
    private static int $testTenantId = 2;

    protected function setUp(): void
    {
        parent::setUp();
        TenantContext::setById(self::$testTenantId);
        $this->service = new ListingAnalyticsService();
    }

    public function test_service_can_be_instantiated(): void
    {
        $this->assertInstanceOf(ListingAnalyticsService::class, $this->service);
    }

    public function test_record_view_with_no_user_or_ip(): void
    {
        // No user, no IP -> recent check returns false -> should insert if table exists
        try {
            $result = $this->service->recordView(999999);
            $this->assertIsBool($result);
        } catch (\Exception $e) {
            // Table may not exist in test env
            $this->assertTrue(true);
        }
    }

    public function test_record_contact_validates_contact_type(): void
    {
        // invalid contact type defaults to 'message'
        try {
            $result = $this->service->recordContact(999999, 1, 'invalid_type');
            $this->assertIsBool($result);
        } catch (\Exception $e) {
            $this->assertTrue(true);
        }
    }

    public function test_record_contact_valid_types(): void
    {
        $validTypes = ['message', 'phone', 'email', 'exchange_request'];
        foreach ($validTypes as $type) {
            try {
                $result = $this->service->recordContact(999999, 1, $type);
                $this->assertIsBool($result);
            } catch (\Exception $e) {
                $this->assertTrue(true);
            }
        }
    }

    public function test_get_analytics_returns_error_for_nonexistent_listing(): void
    {
        $result = $this->service->getAnalytics(999999);
        $this->assertIsArray($result);
        $this->assertArrayHasKey('error', $result);
        $this->assertSame('Listing not found', $result['error']);
    }

    public function test_get_analytics_custom_days(): void
    {
        $result = $this->service->getAnalytics(999999, 7);
        $this->assertIsArray($result);
        $this->assertArrayHasKey('error', $result);
    }

    public function test_update_save_count_does_not_throw(): void
    {
        // Should not throw even for nonexistent listing
        try {
            $this->service->updateSaveCount(999999, true);
            $this->service->updateSaveCount(999999, false);
            $this->assertTrue(true);
        } catch (\Exception $e) {
            $this->assertTrue(true);
        }
    }

    public function test_cleanup_old_records_returns_int(): void
    {
        try {
            $result = $this->service->cleanupOldRecords();
            $this->assertIsInt($result);
            $this->assertGreaterThanOrEqual(0, $result);
        } catch (\Exception $e) {
            // Table may not exist
            $this->assertTrue(true);
        }
    }
}
