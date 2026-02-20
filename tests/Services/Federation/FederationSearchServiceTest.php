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
use Nexus\Services\FederationSearchService;

/**
 * FederationSearchService Tests
 *
 * Tests advanced search capabilities for federated members across partner timebanks.
 */
class FederationSearchServiceTest extends DatabaseTestCase
{
    protected static ?int $testTenantId = null;

    public static function setUpBeforeClass(): void
    {
        parent::setUpBeforeClass();
        self::$testTenantId = 2;
        TenantContext::setById(self::$testTenantId);
    }

    // ==========================================
    // searchMembers Tests
    // ==========================================

    public function testSearchMembersWithEmptyPartnerIdsReturnsEmptyResult(): void
    {
        $result = FederationSearchService::searchMembers([], []);

        $this->assertIsArray($result);
        $this->assertArrayHasKey('members', $result);
        $this->assertArrayHasKey('total', $result);
        $this->assertArrayHasKey('filters_applied', $result);
        $this->assertEmpty($result['members']);
        $this->assertEquals(0, $result['total']);
    }

    public function testSearchMembersReturnsExpectedStructure(): void
    {
        $result = FederationSearchService::searchMembers([1, 2], []);

        $this->assertIsArray($result);
        $this->assertArrayHasKey('members', $result);
        $this->assertArrayHasKey('total', $result);
        $this->assertArrayHasKey('filters_applied', $result);
        $this->assertArrayHasKey('has_more', $result);
        $this->assertIsArray($result['members']);
        $this->assertIsInt($result['total']);
    }

    public function testSearchMembersWithSearchFilter(): void
    {
        $result = FederationSearchService::searchMembers([1, 2], ['search' => 'gardening']);

        $this->assertIsArray($result);
        if (!empty($result['filters_applied'])) {
            $this->assertContains('search', $result['filters_applied']);
        }
    }

    public function testSearchMembersWithTenantFilter(): void
    {
        $result = FederationSearchService::searchMembers([1, 2], ['tenant_id' => 1]);

        $this->assertIsArray($result);
    }

    public function testSearchMembersWithServiceReachFilter(): void
    {
        $reachFilters = ['remote_ok', 'travel_ok', 'local_only'];

        foreach ($reachFilters as $reach) {
            $result = FederationSearchService::searchMembers([1, 2], ['service_reach' => $reach]);
            $this->assertIsArray($result);
        }
    }

    public function testSearchMembersWithSkillsFilter(): void
    {
        $result = FederationSearchService::searchMembers([1, 2], ['skills' => 'cooking,gardening']);

        $this->assertIsArray($result);
    }

    public function testSearchMembersWithLocationFilter(): void
    {
        $result = FederationSearchService::searchMembers([1, 2], ['location' => 'Dublin']);

        $this->assertIsArray($result);
    }

    public function testSearchMembersWithRadiusFilter(): void
    {
        $result = FederationSearchService::searchMembers([1, 2], [
            'latitude' => 53.3498,
            'longitude' => -6.2603,
            'radius_km' => 50,
        ]);

        $this->assertIsArray($result);
    }

    public function testSearchMembersWithMessagingFilter(): void
    {
        $result = FederationSearchService::searchMembers([1, 2], ['messaging_enabled' => true]);

        $this->assertIsArray($result);
    }

    public function testSearchMembersWithTransactionsFilter(): void
    {
        $result = FederationSearchService::searchMembers([1, 2], ['transactions_enabled' => true]);

        $this->assertIsArray($result);
    }

    public function testSearchMembersWithPagination(): void
    {
        $result = FederationSearchService::searchMembers([1, 2], [
            'limit' => 5,
            'offset' => 0,
        ]);

        $this->assertIsArray($result);
        $this->assertLessThanOrEqual(5, count($result['members']));
    }

    public function testSearchMembersWithSorting(): void
    {
        $sortOptions = ['name', 'recent', 'active'];

        foreach ($sortOptions as $sort) {
            $result = FederationSearchService::searchMembers([1, 2], ['sort' => $sort]);
            $this->assertIsArray($result);
        }
    }

    public function testSearchMembersLimitCappedAt100(): void
    {
        $result = FederationSearchService::searchMembers([1, 2], ['limit' => 500]);

        $this->assertIsArray($result);
        // The service caps limit at 100
        $this->assertLessThanOrEqual(100, count($result['members']));
    }

    // ==========================================
    // getAvailableSkills Tests
    // ==========================================

    public function testGetAvailableSkillsWithEmptyPartnerIdsReturnsEmpty(): void
    {
        $result = FederationSearchService::getAvailableSkills([]);

        $this->assertIsArray($result);
        $this->assertEmpty($result);
    }

    public function testGetAvailableSkillsReturnsArray(): void
    {
        $result = FederationSearchService::getAvailableSkills([1, 2]);

        $this->assertIsArray($result);
    }

    public function testGetAvailableSkillsWithQuery(): void
    {
        $result = FederationSearchService::getAvailableSkills([1, 2], 'cook');

        $this->assertIsArray($result);
    }

    public function testGetAvailableSkillsWithLimit(): void
    {
        $result = FederationSearchService::getAvailableSkills([1, 2], '', 5);

        $this->assertIsArray($result);
        $this->assertLessThanOrEqual(5, count($result));
    }

    // ==========================================
    // getAvailableLocations Tests
    // ==========================================

    public function testGetAvailableLocationsWithEmptyPartnerIdsReturnsEmpty(): void
    {
        $result = FederationSearchService::getAvailableLocations([]);

        $this->assertIsArray($result);
        $this->assertEmpty($result);
    }

    public function testGetAvailableLocationsReturnsArray(): void
    {
        $result = FederationSearchService::getAvailableLocations([1, 2]);

        $this->assertIsArray($result);
    }

    public function testGetAvailableLocationsWithQuery(): void
    {
        $result = FederationSearchService::getAvailableLocations([1, 2], 'Dublin');

        $this->assertIsArray($result);
    }

    // ==========================================
    // getSearchStats Tests
    // ==========================================

    public function testGetSearchStatsWithEmptyPartnerIdsReturnsZeros(): void
    {
        $result = FederationSearchService::getSearchStats([]);

        $this->assertIsArray($result);
        $this->assertEquals(0, $result['total_members']);
        $this->assertEquals(0, $result['remote_available']);
        $this->assertEquals(0, $result['travel_available']);
        $this->assertEquals(0, $result['messaging_enabled']);
        $this->assertEquals(0, $result['transactions_enabled']);
    }

    public function testGetSearchStatsReturnsExpectedStructure(): void
    {
        $result = FederationSearchService::getSearchStats([1, 2]);

        $this->assertIsArray($result);
        $this->assertArrayHasKey('total_members', $result);
        $this->assertArrayHasKey('remote_available', $result);
        $this->assertArrayHasKey('travel_available', $result);
        $this->assertArrayHasKey('messaging_enabled', $result);
        $this->assertArrayHasKey('transactions_enabled', $result);
    }

    // ==========================================
    // searchExternalMembers Tests
    // ==========================================

    public function testSearchExternalMembersReturnsExpectedStructure(): void
    {
        $result = FederationSearchService::searchExternalMembers(self::$testTenantId, []);

        $this->assertIsArray($result);
        $this->assertArrayHasKey('members', $result);
        $this->assertArrayHasKey('total', $result);
        $this->assertArrayHasKey('partners_queried', $result);
        $this->assertArrayHasKey('errors', $result);
    }

    // ==========================================
    // searchAllFederatedMembers Tests
    // ==========================================

    public function testSearchAllFederatedMembersReturnsExpectedStructure(): void
    {
        $result = FederationSearchService::searchAllFederatedMembers(
            [1, 2],
            self::$testTenantId,
            []
        );

        $this->assertIsArray($result);
        $this->assertArrayHasKey('members', $result);
        $this->assertArrayHasKey('total', $result);
        $this->assertArrayHasKey('internal_count', $result);
        $this->assertArrayHasKey('external_count', $result);
        $this->assertArrayHasKey('has_more', $result);
    }

    // ==========================================
    // searchExternalListings Tests
    // ==========================================

    public function testSearchExternalListingsReturnsExpectedStructure(): void
    {
        $result = FederationSearchService::searchExternalListings(self::$testTenantId, []);

        $this->assertIsArray($result);
        $this->assertArrayHasKey('listings', $result);
        $this->assertArrayHasKey('total', $result);
        $this->assertArrayHasKey('partners_queried', $result);
        $this->assertArrayHasKey('errors', $result);
    }

    // ==========================================
    // findMembersBySkills Tests
    // ==========================================

    public function testFindMembersBySkillsWithEmptyPartnerIdsReturnsEmpty(): void
    {
        $result = FederationSearchService::findMembersBySkills([], ['cooking']);

        $this->assertIsArray($result);
        $this->assertEmpty($result);
    }

    public function testFindMembersBySkillsWithEmptySkillsReturnsEmpty(): void
    {
        $result = FederationSearchService::findMembersBySkills([1, 2], []);

        $this->assertIsArray($result);
        $this->assertEmpty($result);
    }

    public function testFindMembersBySkillsReturnsArray(): void
    {
        $result = FederationSearchService::findMembersBySkills([1, 2], ['cooking', 'gardening']);

        $this->assertIsArray($result);
    }

    public function testFindMembersBySkillsWithExcludeUser(): void
    {
        $result = FederationSearchService::findMembersBySkills([1, 2], ['cooking'], 1, 10);

        $this->assertIsArray($result);
    }
}
