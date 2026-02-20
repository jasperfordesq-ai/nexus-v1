<?php
// Copyright © 2024–2026 Jasper Ford
// SPDX-License-Identifier: AGPL-3.0-or-later
// Author: Jasper Ford
// See NOTICE file for attribution and acknowledgements.

declare(strict_types=1);

namespace Nexus\Tests\Services;

use Nexus\Tests\DatabaseTestCase;
use Nexus\Core\Database;
use Nexus\Core\TenantContext;
use Nexus\Services\VolunteerService;

/**
 * VolunteerService Tests
 *
 * Tests volunteering operations including opportunities, applications,
 * shifts, hour logging, organizations, and reviews.
 */
class VolunteerServiceTest extends DatabaseTestCase
{
    protected static ?int $testTenantId = null;
    protected static ?int $testUserId = null;
    protected static ?int $testUser2Id = null;
    protected static ?int $testOrgId = null;
    protected static ?int $testOppId = null;
    protected static ?int $testShiftId = null;

    public static function setUpBeforeClass(): void
    {
        parent::setUpBeforeClass();

        self::$testTenantId = 2;
        TenantContext::setById(self::$testTenantId);

        self::createTestData();
    }

    protected static function createTestData(): void
    {
        $ts = time();

        // Create test users
        Database::query(
            "INSERT INTO users (tenant_id, email, username, first_name, last_name, name, balance, is_approved, created_at)
             VALUES (?, ?, ?, ?, ?, ?, ?, 1, NOW())",
            [self::$testTenantId, "volsvc_user1_{$ts}@test.com", "volsvc_user1_{$ts}", 'Vol', 'One', 'Vol One', 100]
        );
        self::$testUserId = (int)Database::getInstance()->lastInsertId();

        Database::query(
            "INSERT INTO users (tenant_id, email, username, first_name, last_name, name, balance, is_approved, created_at)
             VALUES (?, ?, ?, ?, ?, ?, ?, 1, NOW())",
            [self::$testTenantId, "volsvc_user2_{$ts}@test.com", "volsvc_user2_{$ts}", 'Vol', 'Two', 'Vol Two', 50]
        );
        self::$testUser2Id = (int)Database::getInstance()->lastInsertId();

        // Create test organization
        Database::query(
            "INSERT INTO vol_organizations (tenant_id, user_id, name, description, status, created_at)
             VALUES (?, ?, ?, ?, 'approved', NOW())",
            [self::$testTenantId, self::$testUserId, "Test Volunteer Org {$ts}", 'Test organization for volunteer opportunities']
        );
        self::$testOrgId = (int)Database::getInstance()->lastInsertId();

        // Create test opportunity
        Database::query(
            "INSERT INTO vol_opportunities (organization_id, title, description, location, is_active, created_at)
             VALUES (?, ?, ?, ?, 1, NOW())",
            [self::$testOrgId, "Test Opportunity {$ts}", 'Help with community cleanup', 'Community Center']
        );
        self::$testOppId = (int)Database::getInstance()->lastInsertId();

        // Create test shift
        Database::query(
            "INSERT INTO vol_shifts (opportunity_id, start_time, end_time, capacity)
             VALUES (?, DATE_ADD(NOW(), INTERVAL 7 DAY), DATE_ADD(NOW(), INTERVAL 7 DAY) + INTERVAL 2 HOUR, 10)",
            [self::$testOppId]
        );
        self::$testShiftId = (int)Database::getInstance()->lastInsertId();
    }

    public static function tearDownAfterClass(): void
    {
        // Cleanup test data
        if (self::$testShiftId) {
            try {
                Database::query("DELETE FROM vol_shifts WHERE id = ?", [self::$testShiftId]);
            } catch (\Exception $e) {}
        }
        if (self::$testOppId) {
            try {
                Database::query("DELETE FROM vol_applications WHERE opportunity_id = ?", [self::$testOppId]);
                Database::query("DELETE FROM vol_opportunities WHERE id = ?", [self::$testOppId]);
            } catch (\Exception $e) {}
        }
        if (self::$testOrgId) {
            try {
                Database::query("DELETE FROM vol_logs WHERE organization_id = ?", [self::$testOrgId]);
                Database::query("DELETE FROM vol_organizations WHERE id = ?", [self::$testOrgId]);
            } catch (\Exception $e) {}
        }
        if (self::$testUserId) {
            try {
                Database::query("DELETE FROM users WHERE id = ?", [self::$testUserId]);
            } catch (\Exception $e) {}
        }
        if (self::$testUser2Id) {
            try {
                Database::query("DELETE FROM users WHERE id = ?", [self::$testUser2Id]);
            } catch (\Exception $e) {}
        }

        parent::tearDownAfterClass();
    }

    // ==========================================
    // Opportunities Tests
    // ==========================================

    public function testGetOpportunitiesReturnsValidStructure(): void
    {
        $result = VolunteerService::getOpportunities();

        $this->assertIsArray($result);
        $this->assertArrayHasKey('items', $result);
        $this->assertArrayHasKey('cursor', $result);
        $this->assertArrayHasKey('has_more', $result);
    }

    public function testGetOpportunityByIdReturnsValidData(): void
    {
        $opp = VolunteerService::getOpportunityById(self::$testOppId);

        $this->assertNotNull($opp);
        $this->assertArrayHasKey('id', $opp);
        $this->assertArrayHasKey('title', $opp);
        $this->assertArrayHasKey('organization', $opp);
        $this->assertArrayHasKey('shifts', $opp);
        $this->assertEquals(self::$testOppId, $opp['id']);
    }

    public function testGetOpportunityByIdReturnsNullForInvalidId(): void
    {
        $opp = VolunteerService::getOpportunityById(999999);
        $this->assertNull($opp);
    }

    public function testCreateOpportunityRequiresOrganization(): void
    {
        $result = VolunteerService::createOpportunity(self::$testUserId, [
            'title' => 'Test Opportunity'
        ]);

        $this->assertNull($result);
        $errors = VolunteerService::getErrors();
        $this->assertNotEmpty($errors);
    }

    public function testCreateOpportunityRequiresTitle(): void
    {
        $result = VolunteerService::createOpportunity(self::$testUserId, [
            'organization_id' => self::$testOrgId
        ]);

        $this->assertNull($result);
        $errors = VolunteerService::getErrors();
        $this->assertNotEmpty($errors);
    }

    public function testCreateOpportunityVerifiesOwnership(): void
    {
        $result = VolunteerService::createOpportunity(self::$testUser2Id, [
            'organization_id' => self::$testOrgId,
            'title' => 'Unauthorized Opportunity'
        ]);

        $this->assertNull($result);
        $errors = VolunteerService::getErrors();
        $this->assertNotEmpty($errors);
        $this->assertEquals('FORBIDDEN', $errors[0]['code']);
    }

    // ==========================================
    // Applications Tests
    // ==========================================

    public function testApplyCreatesApplication(): void
    {
        $appId = VolunteerService::apply(self::$testUser2Id, self::$testOppId, [
            'message' => 'I would like to volunteer'
        ]);

        $this->assertNotNull($appId);
        $this->assertIsInt($appId);

        // Cleanup
        Database::query("DELETE FROM vol_applications WHERE id = ?", [$appId]);
    }

    public function testApplyPreventsDoubleApplication(): void
    {
        // First application
        $appId = VolunteerService::apply(self::$testUser2Id, self::$testOppId);
        $this->assertNotNull($appId);

        // Second application (should fail)
        $appId2 = VolunteerService::apply(self::$testUser2Id, self::$testOppId);
        $this->assertNull($appId2);
        $errors = VolunteerService::getErrors();
        $this->assertNotEmpty($errors);
        $this->assertEquals('ALREADY_EXISTS', $errors[0]['code']);

        // Cleanup
        Database::query("DELETE FROM vol_applications WHERE id = ?", [$appId]);
    }

    public function testGetMyApplicationsReturnsValidStructure(): void
    {
        $result = VolunteerService::getMyApplications(self::$testUserId);

        $this->assertIsArray($result);
        $this->assertArrayHasKey('items', $result);
        $this->assertArrayHasKey('cursor', $result);
        $this->assertArrayHasKey('has_more', $result);
    }

    public function testHandleApplicationRequiresValidAction(): void
    {
        // Create test application
        $appId = VolunteerService::apply(self::$testUser2Id, self::$testOppId);
        $this->assertNotNull($appId);

        $result = VolunteerService::handleApplication($appId, self::$testUserId, 'invalid_action');
        $this->assertFalse($result);

        // Cleanup
        Database::query("DELETE FROM vol_applications WHERE id = ?", [$appId]);
    }

    // ==========================================
    // Hour Logging Tests
    // ==========================================

    public function testLogHoursRequiresOrganization(): void
    {
        $result = VolunteerService::logHours(self::$testUserId, [
            'date' => date('Y-m-d'),
            'hours' => 2
        ]);

        $this->assertNull($result);
        $errors = VolunteerService::getErrors();
        $this->assertNotEmpty($errors);
    }

    public function testLogHoursRequiresDate(): void
    {
        $result = VolunteerService::logHours(self::$testUserId, [
            'organization_id' => self::$testOrgId,
            'hours' => 2
        ]);

        $this->assertNull($result);
        $errors = VolunteerService::getErrors();
        $this->assertNotEmpty($errors);
    }

    public function testLogHoursRequiresPositiveHours(): void
    {
        $result = VolunteerService::logHours(self::$testUserId, [
            'organization_id' => self::$testOrgId,
            'date' => date('Y-m-d'),
            'hours' => 0
        ]);

        $this->assertNull($result);
        $errors = VolunteerService::getErrors();
        $this->assertNotEmpty($errors);
    }

    public function testLogHoursRejectsFutureDate(): void
    {
        $result = VolunteerService::logHours(self::$testUserId, [
            'organization_id' => self::$testOrgId,
            'date' => date('Y-m-d', strtotime('+1 day')),
            'hours' => 2
        ]);

        $this->assertNull($result);
        $errors = VolunteerService::getErrors();
        $this->assertNotEmpty($errors);
    }

    public function testGetMyHoursReturnsValidStructure(): void
    {
        $result = VolunteerService::getMyHours(self::$testUserId);

        $this->assertIsArray($result);
        $this->assertArrayHasKey('items', $result);
        $this->assertArrayHasKey('cursor', $result);
        $this->assertArrayHasKey('has_more', $result);
    }

    public function testGetHoursSummaryReturnsValidStructure(): void
    {
        $summary = VolunteerService::getHoursSummary(self::$testUserId);

        $this->assertIsArray($summary);
        $this->assertArrayHasKey('total_verified', $summary);
        $this->assertArrayHasKey('total_pending', $summary);
        $this->assertArrayHasKey('by_organization', $summary);
        $this->assertArrayHasKey('by_month', $summary);
    }

    // ==========================================
    // Organizations Tests
    // ==========================================

    public function testGetOrganizationsReturnsValidStructure(): void
    {
        $result = VolunteerService::getOrganizations();

        $this->assertIsArray($result);
        $this->assertArrayHasKey('items', $result);
        $this->assertArrayHasKey('cursor', $result);
        $this->assertArrayHasKey('has_more', $result);
    }

    public function testGetOrganizationByIdReturnsValidData(): void
    {
        $org = VolunteerService::getOrganizationById(self::$testOrgId);

        $this->assertNotNull($org);
        $this->assertArrayHasKey('id', $org);
        $this->assertArrayHasKey('name', $org);
        $this->assertArrayHasKey('stats', $org);
        $this->assertEquals(self::$testOrgId, $org['id']);
    }

    // ==========================================
    // Reviews Tests
    // ==========================================

    public function testCreateReviewRequiresValidTargetType(): void
    {
        $result = VolunteerService::createReview(self::$testUserId, 'invalid_type', 1, 5);

        $this->assertNull($result);
        $errors = VolunteerService::getErrors();
        $this->assertNotEmpty($errors);
    }

    public function testCreateReviewRequiresValidRating(): void
    {
        $result = VolunteerService::createReview(self::$testUserId, 'organization', self::$testOrgId, 0);

        $this->assertNull($result);
        $errors = VolunteerService::getErrors();
        $this->assertNotEmpty($errors);
    }

    public function testGetReviewsReturnsArray(): void
    {
        $reviews = VolunteerService::getReviews('organization', self::$testOrgId);
        $this->assertIsArray($reviews);
    }
}
