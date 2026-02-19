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
use Nexus\Models\VolApplication;
use Nexus\Models\VolOpportunity;

/**
 * VolApplication Model Tests
 *
 * Tests volunteer application submission, duplicate prevention,
 * retrieval by opportunity/user, and approval flow basics.
 */
class VolApplicationTest extends DatabaseTestCase
{
    protected static ?int $testTenantId = null;
    protected static ?int $testUserId = null;
    protected static ?int $testUserId2 = null;
    protected static ?int $testOrgId = null;
    protected static ?int $testOppId = null;
    protected static ?int $testOppId2 = null;

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

        // Create test users
        Database::query(
            "INSERT INTO users (tenant_id, email, username, first_name, last_name, name, balance, is_approved, created_at)
             VALUES (?, ?, ?, ?, ?, ?, 100, 1, NOW())",
            [
                self::$testTenantId,
                "vol_app_user1_{$timestamp}@test.com",
                "vol_app_user1_{$timestamp}",
                'Vol',
                'Applicant1',
                'Vol Applicant1'
            ]
        );
        self::$testUserId = (int)Database::getInstance()->lastInsertId();

        Database::query(
            "INSERT INTO users (tenant_id, email, username, first_name, last_name, name, balance, is_approved, created_at)
             VALUES (?, ?, ?, ?, ?, ?, 100, 1, NOW())",
            [
                self::$testTenantId,
                "vol_app_user2_{$timestamp}@test.com",
                "vol_app_user2_{$timestamp}",
                'Vol',
                'Applicant2',
                'Vol Applicant2'
            ]
        );
        self::$testUserId2 = (int)Database::getInstance()->lastInsertId();

        // Create test organization
        Database::query(
            "INSERT INTO vol_organizations (tenant_id, user_id, name, description, website, contact_email, status, created_at)
             VALUES (?, ?, ?, ?, ?, ?, 'approved', NOW())",
            [
                self::$testTenantId,
                self::$testUserId,
                "App Test Org {$timestamp}",
                'Org for application testing',
                'https://app-test-org.example.com',
                "apporg_{$timestamp}@test.com"
            ]
        );
        self::$testOrgId = (int)Database::getInstance()->lastInsertId();

        // Create test opportunities
        self::$testOppId = (int)VolOpportunity::create(
            self::$testTenantId,
            self::$testUserId,
            self::$testOrgId,
            "Application Test Opp 1 {$timestamp}",
            'Opportunity for application testing',
            'Dublin, Ireland',
            'teamwork',
            date('Y-m-d', strtotime('+7 days')),
            date('Y-m-d', strtotime('+14 days'))
        );

        self::$testOppId2 = (int)VolOpportunity::create(
            self::$testTenantId,
            self::$testUserId,
            self::$testOrgId,
            "Application Test Opp 2 {$timestamp}",
            'Second opportunity for testing',
            'Cork, Ireland',
            'communication',
            date('Y-m-d', strtotime('+30 days')),
            date('Y-m-d', strtotime('+37 days'))
        );
    }

    public static function tearDownAfterClass(): void
    {
        $oppIds = array_filter([self::$testOppId, self::$testOppId2]);
        foreach ($oppIds as $oppId) {
            try {
                Database::query("DELETE FROM vol_applications WHERE opportunity_id = ?", [$oppId]);
            } catch (\Exception $e) {
            }
        }
        if (self::$testOrgId) {
            try {
                Database::query("DELETE FROM vol_opportunities WHERE organization_id = ?", [self::$testOrgId]);
                Database::query("DELETE FROM vol_organizations WHERE id = ?", [self::$testOrgId]);
            } catch (\Exception $e) {
            }
        }
        $userIds = array_filter([self::$testUserId, self::$testUserId2]);
        foreach ($userIds as $uid) {
            try {
                Database::query("DELETE FROM activity_log WHERE user_id = ?", [$uid]);
                Database::query("DELETE FROM users WHERE id = ?", [$uid]);
            } catch (\Exception $e) {
            }
        }

        parent::tearDownAfterClass();
    }

    protected function setUp(): void
    {
        parent::setUp();
        TenantContext::setById(self::$testTenantId);

        // Clean applications before each test
        try {
            Database::query(
                "DELETE FROM vol_applications WHERE opportunity_id IN (?, ?)",
                [self::$testOppId, self::$testOppId2]
            );
        } catch (\Exception $e) {
        }
    }

    // ==========================================
    // Application Submission Tests
    // ==========================================

    public function testCreateApplicationSucceeds(): void
    {
        VolApplication::create(self::$testOppId, self::$testUserId, 'I would like to volunteer');

        $this->assertTrue(VolApplication::hasApplied(self::$testOppId, self::$testUserId));
    }

    public function testCreateApplicationWithMessage(): void
    {
        $message = 'I have relevant experience in community gardening.';
        VolApplication::create(self::$testOppId, self::$testUserId, $message);

        $apps = VolApplication::getForOpportunity(self::$testOppId);
        $this->assertNotEmpty($apps);

        $found = false;
        foreach ($apps as $app) {
            if ($app['user_id'] == self::$testUserId && $app['message'] === $message) {
                $found = true;
                break;
            }
        }
        $this->assertTrue($found, 'Application message should be stored');
    }

    public function testCreateApplicationWithShiftId(): void
    {
        // Create a test shift first
        try {
            Database::query(
                "INSERT INTO vol_shifts (opportunity_id, start_time, end_time, max_volunteers, created_at) VALUES (?, ?, ?, 5, NOW())",
                [self::$testOppId, date('Y-m-d H:i:s', strtotime('+7 days')), date('Y-m-d H:i:s', strtotime('+7 days +4 hours'))]
            );
            $shiftId = (int)Database::getInstance()->lastInsertId();
        } catch (\Exception $e) {
            $this->markTestSkipped('Could not create test shift: ' . $e->getMessage());
            return;
        }

        VolApplication::create(self::$testOppId, self::$testUserId, 'Shift application', $shiftId);

        $apps = VolApplication::getForOpportunity(self::$testOppId);
        $found = false;
        foreach ($apps as $app) {
            if ($app['user_id'] == self::$testUserId && $app['shift_id'] == $shiftId) {
                $found = true;
                break;
            }
        }
        $this->assertTrue($found, 'Application should store shift_id');

        // Clean up shift
        try {
            Database::query("DELETE FROM vol_shifts WHERE id = ?", [$shiftId]);
        } catch (\Exception $e) {
        }
    }

    public function testCreateApplicationWithNullShiftId(): void
    {
        VolApplication::create(self::$testOppId, self::$testUserId, 'No shift', null);

        $apps = VolApplication::getForOpportunity(self::$testOppId);
        $found = false;
        foreach ($apps as $app) {
            if ($app['user_id'] == self::$testUserId) {
                $found = true;
                $this->assertNull($app['shift_id']);
                break;
            }
        }
        $this->assertTrue($found);
    }

    // ==========================================
    // Has Applied (Duplicate Check) Tests
    // ==========================================

    public function testHasAppliedReturnsTrueWhenApplied(): void
    {
        VolApplication::create(self::$testOppId, self::$testUserId, 'Applied');

        $this->assertTrue(VolApplication::hasApplied(self::$testOppId, self::$testUserId));
    }

    public function testHasAppliedReturnsFalseWhenNotApplied(): void
    {
        $this->assertFalse(VolApplication::hasApplied(self::$testOppId, self::$testUserId));
    }

    public function testHasAppliedReturnsFalseForDifferentOpportunity(): void
    {
        VolApplication::create(self::$testOppId, self::$testUserId, 'Applied to opp 1');

        $this->assertTrue(VolApplication::hasApplied(self::$testOppId, self::$testUserId));
        $this->assertFalse(VolApplication::hasApplied(self::$testOppId2, self::$testUserId));
    }

    public function testHasAppliedReturnsFalseForDifferentUser(): void
    {
        VolApplication::create(self::$testOppId, self::$testUserId, 'User 1 applied');

        $this->assertTrue(VolApplication::hasApplied(self::$testOppId, self::$testUserId));
        $this->assertFalse(VolApplication::hasApplied(self::$testOppId, self::$testUserId2));
    }

    // ==========================================
    // Get For Opportunity Tests
    // ==========================================

    public function testGetForOpportunityReturnsArray(): void
    {
        $apps = VolApplication::getForOpportunity(self::$testOppId);
        $this->assertIsArray($apps);
    }

    public function testGetForOpportunityReturnsEmptyWhenNoApplications(): void
    {
        $apps = VolApplication::getForOpportunity(self::$testOppId);
        $this->assertEmpty($apps);
    }

    public function testGetForOpportunityReturnsAllApplications(): void
    {
        VolApplication::create(self::$testOppId, self::$testUserId, 'Application 1');
        VolApplication::create(self::$testOppId, self::$testUserId2, 'Application 2');

        $apps = VolApplication::getForOpportunity(self::$testOppId);
        $this->assertCount(2, $apps);
    }

    public function testGetForOpportunityIncludesUserInfo(): void
    {
        VolApplication::create(self::$testOppId, self::$testUserId, 'User info test');

        $apps = VolApplication::getForOpportunity(self::$testOppId);
        $this->assertNotEmpty($apps);
        $this->assertArrayHasKey('user_name', $apps[0]);
        $this->assertArrayHasKey('user_email', $apps[0]);
    }

    public function testGetForOpportunityIncludesShiftInfo(): void
    {
        VolApplication::create(self::$testOppId, self::$testUserId, 'Shift info test');

        $apps = VolApplication::getForOpportunity(self::$testOppId);
        $this->assertNotEmpty($apps);
        // LEFT JOIN on shifts means these keys exist but may be null
        $this->assertArrayHasKey('shift_start', $apps[0]);
        $this->assertArrayHasKey('shift_end', $apps[0]);
    }

    public function testGetForOpportunityOrdersByCreatedAtDesc(): void
    {
        VolApplication::create(self::$testOppId, self::$testUserId, 'First application');
        usleep(10000);
        VolApplication::create(self::$testOppId, self::$testUserId2, 'Second application');

        $apps = VolApplication::getForOpportunity(self::$testOppId);
        $this->assertGreaterThanOrEqual(2, count($apps));

        // Most recent should come first
        if (count($apps) >= 2) {
            $first = strtotime($apps[0]['created_at']);
            $second = strtotime($apps[1]['created_at']);
            $this->assertGreaterThanOrEqual($second, $first);
        }
    }

    // ==========================================
    // Get By User Tests
    // ==========================================

    public function testGetByUserReturnsArray(): void
    {
        $apps = VolApplication::getByUser(self::$testUserId);
        $this->assertIsArray($apps);
    }

    public function testGetByUserReturnsEmptyWhenNoApplications(): void
    {
        $apps = VolApplication::getByUser(self::$testUserId);
        $this->assertEmpty($apps);
    }

    public function testGetByUserReturnsUserApplications(): void
    {
        VolApplication::create(self::$testOppId, self::$testUserId, 'My application 1');
        VolApplication::create(self::$testOppId2, self::$testUserId, 'My application 2');

        $apps = VolApplication::getByUser(self::$testUserId);
        $this->assertCount(2, $apps);

        foreach ($apps as $app) {
            $this->assertEquals(self::$testUserId, $app['user_id']);
        }
    }

    public function testGetByUserIncludesOpportunityInfo(): void
    {
        VolApplication::create(self::$testOppId, self::$testUserId, 'Opp info test');

        $apps = VolApplication::getByUser(self::$testUserId);
        $this->assertNotEmpty($apps);
        $this->assertArrayHasKey('opp_title', $apps[0]);
        $this->assertArrayHasKey('org_name', $apps[0]);
    }

    public function testGetByUserDoesNotReturnOtherUsersApplications(): void
    {
        VolApplication::create(self::$testOppId, self::$testUserId, 'User 1 app');
        VolApplication::create(self::$testOppId, self::$testUserId2, 'User 2 app');

        $user1Apps = VolApplication::getByUser(self::$testUserId);
        foreach ($user1Apps as $app) {
            $this->assertEquals(self::$testUserId, $app['user_id']);
        }

        $user2Apps = VolApplication::getByUser(self::$testUserId2);
        foreach ($user2Apps as $app) {
            $this->assertEquals(self::$testUserId2, $app['user_id']);
        }
    }

    // ==========================================
    // Edge Cases
    // ==========================================

    public function testCreateApplicationWithSpecialCharactersInMessage(): void
    {
        $message = 'I\'d love to help! Experience: <b>5 years</b> & "references available"';

        VolApplication::create(self::$testOppId, self::$testUserId, $message);

        $apps = VolApplication::getForOpportunity(self::$testOppId);
        $found = false;
        foreach ($apps as $app) {
            if ($app['user_id'] == self::$testUserId) {
                $found = true;
                $this->assertStringContainsString('references available', $app['message']);
                break;
            }
        }
        $this->assertTrue($found);
    }

    public function testGetByUserForNonExistentUser(): void
    {
        $apps = VolApplication::getByUser(999999999);
        $this->assertIsArray($apps);
        $this->assertEmpty($apps);
    }

    public function testGetForOpportunityForNonExistentOpportunity(): void
    {
        $apps = VolApplication::getForOpportunity(999999999);
        $this->assertIsArray($apps);
        $this->assertEmpty($apps);
    }
}
