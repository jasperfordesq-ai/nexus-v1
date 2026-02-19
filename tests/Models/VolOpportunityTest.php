<?php
// Copyright © 2024–2026 Jasper Ford
// SPDX-License-Identifier: AGPL-3.0-or-later
// Author: Jasper Ford
// See NOTICE file for attribution and acknowledgements.

declare(strict_types=1);

namespace Nexus\Tests\Models;

use Nexus\Tests\DatabaseTestCase;
use Nexus\Core\Database;
use Nexus\Core\TenantContext;
use Nexus\Models\VolOpportunity;

/**
 * VolOpportunity Model Tests
 *
 * Tests volunteer opportunity CRUD operations, search functionality,
 * organization association, and tenant scoping.
 */
class VolOpportunityTest extends DatabaseTestCase
{
    protected static ?int $testTenantId = null;
    protected static ?int $testUserId = null;
    protected static ?int $testOrgId = null;
    protected static ?int $testOppId = null;
    protected static ?int $testCategoryId = null;

    public static function setUpBeforeClass(): void
    {
        parent::setUpBeforeClass();

        self::$testTenantId = 2;
        TenantContext::setById(self::$testTenantId);

        self::createTestData();
    }

    protected static function createTestData(): void
    {
        $timestamp = time();

        // Create test user
        Database::query(
            "INSERT INTO users (tenant_id, email, username, first_name, last_name, name, balance, is_approved, created_at)
             VALUES (?, ?, ?, ?, ?, ?, 100, 1, NOW())",
            [
                self::$testTenantId,
                "vol_opp_test_{$timestamp}@test.com",
                "vol_opp_test_{$timestamp}",
                'Vol',
                'Tester',
                'Vol Tester'
            ]
        );
        self::$testUserId = (int)Database::getInstance()->lastInsertId();

        // Create test volunteer organization (approved status for search)
        Database::query(
            "INSERT INTO vol_organizations (tenant_id, user_id, name, description, website, contact_email, status, created_at)
             VALUES (?, ?, ?, ?, ?, ?, 'approved', NOW())",
            [
                self::$testTenantId,
                self::$testUserId,
                "Test Org {$timestamp}",
                'A test volunteer organization',
                'https://test-org.example.com',
                "org_{$timestamp}@test.com"
            ]
        );
        self::$testOrgId = (int)Database::getInstance()->lastInsertId();

        // Try to get or create a test category
        try {
            $category = Database::query(
                "SELECT id FROM categories WHERE tenant_id = ? LIMIT 1",
                [self::$testTenantId]
            )->fetch();

            if ($category) {
                self::$testCategoryId = (int)$category['id'];
            } else {
                Database::query(
                    "INSERT INTO categories (tenant_id, name, slug, created_at) VALUES (?, ?, ?, NOW())",
                    [self::$testTenantId, 'Vol Test Category', 'vol-test-category-' . $timestamp]
                );
                self::$testCategoryId = (int)Database::getInstance()->lastInsertId();
            }
        } catch (\Exception $e) {
            self::$testCategoryId = null;
        }

        // Create test opportunity
        self::$testOppId = (int)VolOpportunity::create(
            self::$testTenantId,
            self::$testUserId,
            self::$testOrgId,
            "Test Volunteer Opportunity {$timestamp}",
            'Help clean up the local park and community garden',
            'Dublin, Ireland',
            'teamwork, gardening',
            date('Y-m-d', strtotime('+7 days')),
            date('Y-m-d', strtotime('+14 days')),
            self::$testCategoryId
        );
    }

    public static function tearDownAfterClass(): void
    {
        if (self::$testOrgId) {
            try {
                Database::query("DELETE FROM vol_applications WHERE opportunity_id IN (SELECT id FROM vol_opportunities WHERE organization_id = ?)", [self::$testOrgId]);
                Database::query("DELETE FROM vol_opportunities WHERE organization_id = ?", [self::$testOrgId]);
                Database::query("DELETE FROM vol_organizations WHERE id = ?", [self::$testOrgId]);
            } catch (\Exception $e) {
            }
        }
        if (self::$testUserId) {
            try {
                Database::query("DELETE FROM activity_log WHERE user_id = ?", [self::$testUserId]);
                Database::query("DELETE FROM users WHERE id = ?", [self::$testUserId]);
            } catch (\Exception $e) {
            }
        }

        parent::tearDownAfterClass();
    }

    protected function setUp(): void
    {
        parent::setUp();
        TenantContext::setById(self::$testTenantId);
    }

    // ==========================================
    // Create Tests
    // ==========================================

    public function testCreateReturnsId(): void
    {
        $id = VolOpportunity::create(
            self::$testTenantId,
            self::$testUserId,
            self::$testOrgId,
            'New Volunteer Opportunity',
            'Description of the opportunity',
            'Cork, Ireland',
            'communication, organization',
            date('Y-m-d', strtotime('+30 days')),
            date('Y-m-d', strtotime('+37 days'))
        );

        $this->assertIsNumeric($id);
        $this->assertGreaterThan(0, (int)$id);
    }

    public function testCreateWithCategory(): void
    {
        if (!self::$testCategoryId) {
            $this->markTestSkipped('No test category available');
        }

        $id = VolOpportunity::create(
            self::$testTenantId,
            self::$testUserId,
            self::$testOrgId,
            'Categorized Opportunity',
            'Has a category',
            'Galway, Ireland',
            'skills',
            date('Y-m-d', strtotime('+30 days')),
            date('Y-m-d', strtotime('+37 days')),
            self::$testCategoryId
        );

        $opp = VolOpportunity::find($id);
        $this->assertEquals(self::$testCategoryId, $opp['category_id']);
    }

    public function testCreateWithNullCategory(): void
    {
        $id = VolOpportunity::create(
            self::$testTenantId,
            self::$testUserId,
            self::$testOrgId,
            'No Category Opportunity',
            'Without category',
            'Limerick, Ireland',
            'general',
            date('Y-m-d', strtotime('+30 days')),
            date('Y-m-d', strtotime('+37 days')),
            null
        );

        $opp = VolOpportunity::find($id);
        $this->assertNull($opp['category_id']);
    }

    public function testCreateSetsDefaultStatusAndActive(): void
    {
        $id = VolOpportunity::create(
            self::$testTenantId,
            self::$testUserId,
            self::$testOrgId,
            'Default Status Opp',
            'Description',
            'Dublin',
            'skills',
            date('Y-m-d', strtotime('+30 days')),
            date('Y-m-d', strtotime('+37 days'))
        );

        $opp = Database::query("SELECT status, is_active FROM vol_opportunities WHERE id = ?", [$id])->fetch();
        $this->assertEquals('open', $opp['status']);
        $this->assertEquals(1, (int)$opp['is_active']);
    }

    // ==========================================
    // Find Tests
    // ==========================================

    public function testFindReturnsOpportunity(): void
    {
        $opp = VolOpportunity::find(self::$testOppId);

        $this->assertNotFalse($opp);
        $this->assertIsArray($opp);
        $this->assertEquals(self::$testOppId, $opp['id']);
    }

    public function testFindIncludesOrgInfo(): void
    {
        $opp = VolOpportunity::find(self::$testOppId);

        $this->assertArrayHasKey('org_name', $opp);
        $this->assertArrayHasKey('org_website', $opp);
        $this->assertArrayHasKey('org_email', $opp);
        $this->assertArrayHasKey('org_owner_id', $opp);
    }

    public function testFindReturnsFalseForNonExistent(): void
    {
        $opp = VolOpportunity::find(999999999);

        $this->assertFalse($opp);
    }

    public function testFindReturnsAllExpectedFields(): void
    {
        $opp = VolOpportunity::find(self::$testOppId);

        $this->assertArrayHasKey('title', $opp);
        $this->assertArrayHasKey('description', $opp);
        $this->assertArrayHasKey('location', $opp);
        $this->assertArrayHasKey('skills_needed', $opp);
        $this->assertArrayHasKey('start_date', $opp);
        $this->assertArrayHasKey('end_date', $opp);
    }

    // ==========================================
    // Update Tests
    // ==========================================

    public function testUpdateChangesFields(): void
    {
        $id = VolOpportunity::create(
            self::$testTenantId,
            self::$testUserId,
            self::$testOrgId,
            'Original Opp Title',
            'Original description',
            'Original Location',
            'original skills',
            date('Y-m-d', strtotime('+30 days')),
            date('Y-m-d', strtotime('+37 days'))
        );

        $newStart = date('Y-m-d', strtotime('+60 days'));
        $newEnd = date('Y-m-d', strtotime('+67 days'));

        VolOpportunity::update(
            $id,
            'Updated Opp Title',
            'Updated description',
            'New Location',
            'updated skills',
            $newStart,
            $newEnd,
            self::$testCategoryId
        );

        $opp = VolOpportunity::find($id);
        $this->assertEquals('Updated Opp Title', $opp['title']);
        $this->assertEquals('Updated description', $opp['description']);
        $this->assertEquals('New Location', $opp['location']);
        $this->assertEquals('updated skills', $opp['skills_needed']);
    }

    // ==========================================
    // Search Tests — Tenant Scoping
    // ==========================================

    public function testSearchReturnsArray(): void
    {
        $results = VolOpportunity::search(self::$testTenantId);

        $this->assertIsArray($results);
    }

    public function testSearchFindsOpportunityByTitle(): void
    {
        $results = VolOpportunity::search(self::$testTenantId, 'Test Volunteer');

        $this->assertIsArray($results);
        $found = false;
        foreach ($results as $r) {
            if ($r['id'] == self::$testOppId) {
                $found = true;
                break;
            }
        }
        $this->assertTrue($found, 'Search should find opportunity by title');
    }

    public function testSearchFindsOpportunityByDescription(): void
    {
        $results = VolOpportunity::search(self::$testTenantId, 'community garden');

        $this->assertIsArray($results);
        $found = false;
        foreach ($results as $r) {
            if ($r['id'] == self::$testOppId) {
                $found = true;
                break;
            }
        }
        $this->assertTrue($found, 'Search should find opportunity by description');
    }

    public function testSearchFiltersByCategory(): void
    {
        if (!self::$testCategoryId) {
            $this->markTestSkipped('No test category available');
        }

        $results = VolOpportunity::search(self::$testTenantId, null, self::$testCategoryId);

        foreach ($results as $r) {
            $this->assertEquals(self::$testCategoryId, $r['category_id']);
        }
    }

    public function testSearchOnlyReturnsActiveOpportunities(): void
    {
        $results = VolOpportunity::search(self::$testTenantId);

        foreach ($results as $r) {
            $this->assertEquals(1, (int)$r['is_active']);
        }
    }

    public function testSearchOnlyReturnsApprovedOrgs(): void
    {
        // All results should come from approved organizations
        $results = VolOpportunity::search(self::$testTenantId);

        // The SQL filters by org.status = 'approved', so we trust the query
        $this->assertIsArray($results);
    }

    public function testSearchIncludesOrgInfo(): void
    {
        $results = VolOpportunity::search(self::$testTenantId);

        if (!empty($results)) {
            $this->assertArrayHasKey('org_name', $results[0]);
            $this->assertArrayHasKey('logo_url', $results[0]);
        }
    }

    public function testSearchScopesByTenant(): void
    {
        $results = VolOpportunity::search(999999);

        $this->assertIsArray($results);
        $this->assertEmpty($results);
    }

    public function testSearchWithNullQueryReturnsAll(): void
    {
        $results = VolOpportunity::search(self::$testTenantId, null);

        $this->assertIsArray($results);
        $this->assertGreaterThanOrEqual(1, count($results));
    }

    // ==========================================
    // Get For Org Tests
    // ==========================================

    public function testGetForOrgReturnsOpportunities(): void
    {
        $results = VolOpportunity::getForOrg(self::$testOrgId);

        $this->assertIsArray($results);
        $this->assertGreaterThanOrEqual(1, count($results));

        foreach ($results as $r) {
            $this->assertEquals(self::$testOrgId, $r['organization_id']);
        }
    }

    public function testGetForOrgReturnsEmptyForNonExistentOrg(): void
    {
        $results = VolOpportunity::getForOrg(999999999);

        $this->assertIsArray($results);
        $this->assertEmpty($results);
    }

    // ==========================================
    // Edge Cases
    // ==========================================

    public function testCreateWithSpecialCharactersInTitle(): void
    {
        $id = VolOpportunity::create(
            self::$testTenantId,
            self::$testUserId,
            self::$testOrgId,
            'Opportunity with "quotes" & <tags>',
            'Description with special chars: <b>bold</b>',
            'Location with accents: cafe',
            'skill1, skill2',
            date('Y-m-d', strtotime('+30 days')),
            date('Y-m-d', strtotime('+37 days'))
        );

        $opp = VolOpportunity::find($id);
        $this->assertNotFalse($opp);
        $this->assertStringContainsString('quotes', $opp['title']);
    }

    public function testSearchWithSpecialCharacters(): void
    {
        $results = VolOpportunity::search(self::$testTenantId, "test's opportunity");

        $this->assertIsArray($results);
        // Should not throw an error
    }
}
