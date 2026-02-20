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
use Nexus\Models\VolOrganization;

/**
 * VolOrganization Model Tests
 *
 * Tests volunteer organization CRUD, status management,
 * owner lookup, search, and tenant scoping.
 */
class VolOrganizationTest extends DatabaseTestCase
{
    protected static ?int $testTenantId = null;
    protected static ?int $testUserId = null;

    public static function setUpBeforeClass(): void
    {
        parent::setUpBeforeClass();

        self::$testTenantId = 2;
        TenantContext::setById(self::$testTenantId);

        $timestamp = time();
        Database::query(
            "INSERT INTO users (tenant_id, email, username, first_name, last_name, name, balance, is_approved, created_at)
             VALUES (?, ?, ?, ?, ?, ?, 100, 1, NOW())",
            [self::$testTenantId, "vol_org_test_{$timestamp}@test.com", "vol_org_test_{$timestamp}", 'VolOrg', 'Tester', 'VolOrg Tester']
        );
        self::$testUserId = (int)Database::getInstance()->lastInsertId();
    }

    public static function tearDownAfterClass(): void
    {
        try {
            if (self::$testUserId) {
                // Clean up opportunities tied to test orgs
                Database::query(
                    "DELETE FROM vol_opportunities WHERE organization_id IN (SELECT id FROM vol_organizations WHERE user_id = ?)",
                    [self::$testUserId]
                );
                Database::query("DELETE FROM vol_organizations WHERE user_id = ?", [self::$testUserId]);
                Database::query("DELETE FROM activity_log WHERE user_id = ?", [self::$testUserId]);
                Database::query("DELETE FROM users WHERE id = ?", [self::$testUserId]);
            }
        } catch (\Exception $e) {
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
        $id = VolOrganization::create(
            self::$testTenantId,
            self::$testUserId,
            'Test Volunteer Org',
            'A test organization for volunteering',
            'test@volorg.com',
            'https://volorg.example.com'
        );

        $this->assertNotEmpty($id);
        $this->assertGreaterThan(0, (int)$id);
    }

    public function testCreateSetsStatusToPending(): void
    {
        $id = VolOrganization::create(
            self::$testTenantId,
            self::$testUserId,
            'Pending Status Org',
            'Should default to pending',
            'pending@volorg.com'
        );

        $org = VolOrganization::find($id);
        $this->assertEquals('pending', $org['status']);
    }

    public function testCreateWithoutWebsite(): void
    {
        $id = VolOrganization::create(
            self::$testTenantId,
            self::$testUserId,
            'No Website Org',
            'Org without website',
            'nosite@volorg.com',
            null
        );

        $org = VolOrganization::find($id);
        $this->assertNotFalse($org);
        $this->assertEmpty($org['website']);
    }

    // ==========================================
    // Find Tests
    // ==========================================

    public function testFindReturnsOrganization(): void
    {
        $id = VolOrganization::create(
            self::$testTenantId,
            self::$testUserId,
            'Findable Org',
            'Description',
            'find@volorg.com'
        );

        $org = VolOrganization::find($id);
        $this->assertNotFalse($org);
        $this->assertEquals('Findable Org', $org['name']);
        $this->assertEquals(self::$testUserId, $org['user_id']);
    }

    public function testFindReturnsFalseForNonExistent(): void
    {
        $org = VolOrganization::find(999999999);
        $this->assertFalse($org);
    }

    // ==========================================
    // FindByOwner Tests
    // ==========================================

    public function testFindByOwnerReturnsArray(): void
    {
        $orgs = VolOrganization::findByOwner(self::$testUserId);
        $this->assertIsArray($orgs);
        $this->assertNotEmpty($orgs);
    }

    public function testFindByOwnerReturnsEmptyForNonExistent(): void
    {
        $orgs = VolOrganization::findByOwner(999999999);
        $this->assertIsArray($orgs);
        $this->assertEmpty($orgs);
    }

    // ==========================================
    // Update Tests
    // ==========================================

    public function testUpdateChangesFields(): void
    {
        $id = VolOrganization::create(
            self::$testTenantId,
            self::$testUserId,
            'Original Org',
            'Original desc',
            'original@volorg.com',
            'https://original.example.com'
        );

        VolOrganization::update(
            $id,
            'Updated Org',
            'Updated desc',
            'updated@volorg.com',
            'https://updated.example.com',
            true
        );

        $org = VolOrganization::find($id);
        $this->assertEquals('Updated Org', $org['name']);
        $this->assertEquals('Updated desc', $org['description']);
        $this->assertEquals('updated@volorg.com', $org['contact_email']);
        $this->assertEquals('https://updated.example.com', $org['website']);
        $this->assertEquals(1, (int)$org['auto_pay_enabled']);
    }

    public function testUpdateAutoPayDisabled(): void
    {
        $id = VolOrganization::create(
            self::$testTenantId,
            self::$testUserId,
            'AutoPay Org',
            'Test auto pay',
            'autopay@volorg.com'
        );

        VolOrganization::update($id, 'AutoPay Org', 'Test auto pay', 'autopay@volorg.com', null, false);

        $org = VolOrganization::find($id);
        $this->assertEquals(0, (int)$org['auto_pay_enabled']);
    }

    // ==========================================
    // UpdateStatus Tests
    // ==========================================

    public function testUpdateStatusToApproved(): void
    {
        $id = VolOrganization::create(
            self::$testTenantId,
            self::$testUserId,
            'Approve Me Org',
            'Description',
            'approve@volorg.com'
        );

        VolOrganization::updateStatus($id, 'approved');

        $org = VolOrganization::find($id);
        $this->assertEquals('approved', $org['status']);
    }

    public function testUpdateStatusToRejected(): void
    {
        $id = VolOrganization::create(
            self::$testTenantId,
            self::$testUserId,
            'Reject Me Org',
            'Description',
            'reject@volorg.com'
        );

        VolOrganization::updateStatus($id, 'rejected');

        $org = VolOrganization::find($id);
        $this->assertEquals('rejected', $org['status']);
    }

    // ==========================================
    // All Tests
    // ==========================================

    public function testAllReturnsArray(): void
    {
        $all = VolOrganization::all(self::$testTenantId);
        $this->assertIsArray($all);
        $this->assertNotEmpty($all);
    }

    public function testAllScopesByTenant(): void
    {
        $all = VolOrganization::all(999999);
        $this->assertIsArray($all);
        $this->assertEmpty($all);
    }

    // ==========================================
    // GetApproved Tests
    // ==========================================

    public function testGetApprovedReturnsArray(): void
    {
        // Ensure at least one approved org exists
        $id = VolOrganization::create(
            self::$testTenantId,
            self::$testUserId,
            'Approved Org For Listing',
            'Approved org',
            'approved@volorg.com'
        );
        VolOrganization::updateStatus($id, 'approved');

        $approved = VolOrganization::getApproved(self::$testTenantId);
        $this->assertIsArray($approved);
        $this->assertNotEmpty($approved);
    }

    public function testGetApprovedIncludesOwnerInfo(): void
    {
        $id = VolOrganization::create(
            self::$testTenantId,
            self::$testUserId,
            'Owner Info Org',
            'Check owner info',
            'owner@volorg.com'
        );
        VolOrganization::updateStatus($id, 'approved');

        $approved = VolOrganization::getApproved(self::$testTenantId);
        $this->assertNotEmpty($approved);
        $this->assertArrayHasKey('owner_name', $approved[0]);
        $this->assertArrayHasKey('opportunity_count', $approved[0]);
    }

    public function testGetApprovedExcludesPending(): void
    {
        // Create a pending org
        VolOrganization::create(
            self::$testTenantId,
            self::$testUserId,
            'Pending Should Not Appear',
            'This is pending',
            'pending_exclude@volorg.com'
        );

        $approved = VolOrganization::getApproved(self::$testTenantId);
        foreach ($approved as $org) {
            $this->assertNotEquals('Pending Should Not Appear', $org['name']);
        }
    }

    // ==========================================
    // Search Tests
    // ==========================================

    public function testSearchReturnsArray(): void
    {
        $results = VolOrganization::search(self::$testTenantId, 'test');
        $this->assertIsArray($results);
    }

    public function testSearchFindsOrgByName(): void
    {
        $id = VolOrganization::create(
            self::$testTenantId,
            self::$testUserId,
            'Searchable UniqueOrg',
            'Description',
            'search@volorg.com'
        );
        VolOrganization::updateStatus($id, 'approved');

        $results = VolOrganization::search(self::$testTenantId, 'UniqueOrg');
        $this->assertIsArray($results);

        $found = false;
        foreach ($results as $r) {
            if ($r['id'] == $id) {
                $found = true;
                break;
            }
        }
        $this->assertTrue($found, 'Search should find org by name');
    }

    public function testSearchScopesByTenant(): void
    {
        $results = VolOrganization::search(999999, 'test');
        $this->assertIsArray($results);
        $this->assertEmpty($results);
    }
}
