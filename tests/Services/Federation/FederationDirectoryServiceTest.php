<?php
// Copyright © 2024–2026 Jasper Ford
// SPDX-License-Identifier: AGPL-3.0-or-later
// Author: Jasper Ford
// See NOTICE file for attribution and acknowledgements.

declare(strict_types=1);

namespace Nexus\Tests\Services\Federation;

use Nexus\Tests\DatabaseTestCase;
use Nexus\Core\Database;
use Nexus\Core\TenantContext;
use Nexus\Services\FederationDirectoryService;

/**
 * FederationDirectoryService Tests
 *
 * Tests the federation directory where admins can discover
 * and request partnerships with other timebanks.
 */
class FederationDirectoryServiceTest extends DatabaseTestCase
{
    protected static ?int $testTenantId = null;

    public static function setUpBeforeClass(): void
    {
        parent::setUpBeforeClass();
        self::$testTenantId = 2; // hour-timebank
        TenantContext::setById(self::$testTenantId);
    }

    // ==========================================
    // getDiscoverableTimebanks Tests
    // ==========================================

    public function testGetDiscoverableTimebanksReturnsArray(): void
    {
        $result = FederationDirectoryService::getDiscoverableTimebanks(self::$testTenantId);

        $this->assertIsArray($result);
    }

    public function testGetDiscoverableTimebanksExcludesCurrentTenant(): void
    {
        $result = FederationDirectoryService::getDiscoverableTimebanks(self::$testTenantId);
        $this->assertIsArray($result);

        foreach ($result as $timebank) {
            $this->assertNotEquals(self::$testTenantId, $timebank['id']);
        }
    }

    public function testGetDiscoverableTimebanksWithSearchFilter(): void
    {
        $result = FederationDirectoryService::getDiscoverableTimebanks(
            self::$testTenantId,
            ['search' => 'nonexistent_timebank_xyz']
        );

        $this->assertIsArray($result);
    }

    public function testGetDiscoverableTimebanksWithRegionFilter(): void
    {
        $result = FederationDirectoryService::getDiscoverableTimebanks(
            self::$testTenantId,
            ['region' => 'Test Region']
        );

        $this->assertIsArray($result);
    }

    public function testGetDiscoverableTimebanksWithCategoryFilter(): void
    {
        $result = FederationDirectoryService::getDiscoverableTimebanks(
            self::$testTenantId,
            ['category' => 'community']
        );

        $this->assertIsArray($result);
    }

    public function testGetDiscoverableTimebanksWithLimitFilter(): void
    {
        $result = FederationDirectoryService::getDiscoverableTimebanks(
            self::$testTenantId,
            ['limit' => 5]
        );

        $this->assertIsArray($result);
        $this->assertLessThanOrEqual(5, count($result));
    }

    public function testGetDiscoverableTimebanksWithExcludePartnerFilter(): void
    {
        $result = FederationDirectoryService::getDiscoverableTimebanks(
            self::$testTenantId,
            ['exclude_partnered' => true]
        );

        $this->assertIsArray($result);
    }

    public function testGetDiscoverableTimebanksResultStructure(): void
    {
        $result = FederationDirectoryService::getDiscoverableTimebanks(self::$testTenantId);
        $this->assertIsArray($result);

        foreach ($result as $timebank) {
            $this->assertArrayHasKey('id', $timebank);
            $this->assertArrayHasKey('name', $timebank);
            $this->assertArrayHasKey('slug', $timebank);
        }
    }

    // ==========================================
    // getAvailableRegions Tests
    // ==========================================

    public function testGetAvailableRegionsReturnsArray(): void
    {
        $result = FederationDirectoryService::getAvailableRegions();

        $this->assertIsArray($result);
    }

    // ==========================================
    // getAvailableCategories Tests
    // ==========================================

    public function testGetAvailableCategoriesReturnsArray(): void
    {
        $result = FederationDirectoryService::getAvailableCategories();

        $this->assertIsArray($result);
    }

    // ==========================================
    // getTimebankProfile Tests
    // ==========================================

    public function testGetTimebankProfileReturnsArrayForExistingTenant(): void
    {
        $result = FederationDirectoryService::getTimebankProfile(self::$testTenantId);

        $this->assertIsArray($result);
        $this->assertArrayHasKey('id', $result);
        $this->assertArrayHasKey('name', $result);
        $this->assertArrayHasKey('slug', $result);
        $this->assertEquals(self::$testTenantId, $result['id']);
    }

    public function testGetTimebankProfileReturnsNullForNonExistentTenant(): void
    {
        $result = FederationDirectoryService::getTimebankProfile(999999);

        $this->assertNull($result);
    }

    // ==========================================
    // updateDirectoryProfile Tests
    // ==========================================

    public function testUpdateDirectoryProfileReturnsBool(): void
    {
        $result = FederationDirectoryService::updateDirectoryProfile(
            self::$testTenantId,
            ['federation_public_description' => 'Test description for federation directory']
        );

        $this->assertIsBool($result);
    }

    public function testUpdateDirectoryProfileWithEmptyDataReturnsFalse(): void
    {
        $result = FederationDirectoryService::updateDirectoryProfile(
            self::$testTenantId,
            []
        );

        $this->assertFalse($result);
    }

    public function testUpdateDirectoryProfileIgnoresUnknownFields(): void
    {
        $result = FederationDirectoryService::updateDirectoryProfile(
            self::$testTenantId,
            ['unknown_field' => 'value']
        );

        // Should return false because no allowed fields were present
        $this->assertFalse($result);
    }

    public function testUpdateDirectoryProfileWithAllAllowedFields(): void
    {
        $result = FederationDirectoryService::updateDirectoryProfile(
            self::$testTenantId,
            [
                'federation_public_description' => 'Test description',
                'federation_region' => 'Test Region',
                'federation_contact_email' => 'test@test.com',
                'federation_contact_name' => 'Test Contact',
                'federation_member_count_public' => 1,
                'federation_discoverable' => 1,
            ]
        );

        $this->assertTrue($result);
    }

    // ==========================================
    // getDirectoryStats Tests
    // ==========================================

    public function testGetDirectoryStatsReturnsExpectedStructure(): void
    {
        $result = FederationDirectoryService::getDirectoryStats();

        $this->assertIsArray($result);
        $this->assertArrayHasKey('total_timebanks', $result);
        $this->assertArrayHasKey('discoverable_timebanks', $result);
        $this->assertArrayHasKey('total_partnerships', $result);
        $this->assertArrayHasKey('active_partnerships', $result);
        $this->assertArrayHasKey('pending_partnerships', $result);
    }

    public function testGetDirectoryStatsValuesAreNumeric(): void
    {
        $result = FederationDirectoryService::getDirectoryStats();

        $this->assertIsNumeric($result['total_timebanks']);
        $this->assertIsNumeric($result['discoverable_timebanks']);
        $this->assertIsNumeric($result['total_partnerships']);
        $this->assertIsNumeric($result['active_partnerships']);
        $this->assertIsNumeric($result['pending_partnerships']);
    }

    // ==========================================
    // Edge Cases
    // ==========================================

    public function testGetDiscoverableTimebanksWithMultipleFilters(): void
    {
        $result = FederationDirectoryService::getDiscoverableTimebanks(
            self::$testTenantId,
            [
                'search' => 'test',
                'region' => 'Test',
                'category' => 'community',
                'limit' => 3,
            ]
        );

        $this->assertIsArray($result);
        $this->assertLessThanOrEqual(3, count($result));
    }

    public function testGetDiscoverableTimebanksWithInvalidTenantId(): void
    {
        $result = FederationDirectoryService::getDiscoverableTimebanks(999999);

        $this->assertIsArray($result);
    }
}
