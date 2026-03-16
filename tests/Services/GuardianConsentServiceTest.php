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
use Nexus\Services\GuardianConsentService;

/**
 * GuardianConsentService Tests
 *
 * Tests guardian/parental consent operations including consent requests,
 * granting, withdrawal, checking, admin views, expiry, and minor detection.
 */
class GuardianConsentServiceTest extends DatabaseTestCase
{
    protected static ?int $testTenantId = null;
    protected static ?int $minorUserId = null;
    protected static ?int $adultUserId = null;
    protected static ?int $testOrgId = null;
    protected static ?int $testOppId = null;

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

        // Create a minor user (born 10 years ago)
        $minorDob = date('Y-m-d', strtotime('-10 years'));
        Database::query(
            "INSERT INTO users (tenant_id, email, username, first_name, last_name, name, balance, is_approved, date_of_birth, created_at)
             VALUES (?, ?, ?, ?, ?, ?, ?, 1, ?, NOW())",
            [self::$testTenantId, "gc_minor_{$ts}@test.com", "gc_minor_{$ts}", 'Minor', 'Child', 'Minor Child', 0, $minorDob]
        );
        self::$minorUserId = (int)Database::getInstance()->lastInsertId();

        // Create an adult user (born 30 years ago)
        $adultDob = date('Y-m-d', strtotime('-30 years'));
        Database::query(
            "INSERT INTO users (tenant_id, email, username, first_name, last_name, name, balance, is_approved, date_of_birth, created_at)
             VALUES (?, ?, ?, ?, ?, ?, ?, 1, ?, NOW())",
            [self::$testTenantId, "gc_adult_{$ts}@test.com", "gc_adult_{$ts}", 'Adult', 'User', 'Adult User', 0, $adultDob]
        );
        self::$adultUserId = (int)Database::getInstance()->lastInsertId();

        // Create test organization
        Database::query(
            "INSERT INTO vol_organizations (tenant_id, user_id, name, description, status, created_at)
             VALUES (?, ?, ?, ?, 'approved', NOW())",
            [self::$testTenantId, self::$adultUserId, "GC Test Org {$ts}", 'Test org for guardian consent tests']
        );
        self::$testOrgId = (int)Database::getInstance()->lastInsertId();

        // Create test opportunity
        Database::query(
            "INSERT INTO vol_opportunities (tenant_id, organization_id, created_by, title, description, location, is_active, status, created_at)
             VALUES (?, ?, ?, ?, ?, ?, 1, 'open', NOW())",
            [self::$testTenantId, self::$testOrgId, self::$adultUserId, "GC Test Opportunity {$ts}", 'Test opportunity for consent', 'Test Location']
        );
        self::$testOppId = (int)Database::getInstance()->lastInsertId();
    }

    public static function tearDownAfterClass(): void
    {
        // Cleanup consent records first (FK constraints)
        if (self::$minorUserId) {
            try {
                Database::query("DELETE FROM vol_guardian_consents WHERE minor_user_id = ?", [self::$minorUserId]);
            } catch (\Exception $e) {}
        }
        if (self::$testOppId) {
            try {
                Database::query("DELETE FROM vol_opportunities WHERE id = ?", [self::$testOppId]);
            } catch (\Exception $e) {}
        }
        if (self::$testOrgId) {
            try {
                Database::query("DELETE FROM vol_organizations WHERE id = ?", [self::$testOrgId]);
            } catch (\Exception $e) {}
        }
        if (self::$minorUserId) {
            try {
                Database::query("DELETE FROM users WHERE id = ?", [self::$minorUserId]);
            } catch (\Exception $e) {}
        }
        if (self::$adultUserId) {
            try {
                Database::query("DELETE FROM users WHERE id = ?", [self::$adultUserId]);
            } catch (\Exception $e) {}
        }

        parent::tearDownAfterClass();
    }

    // ==========================================
    // Class & Method Existence Tests
    // ==========================================

    public function testClassExists(): void
    {
        $this->assertTrue(class_exists(GuardianConsentService::class));
    }

    public function testMethodsAreStatic(): void
    {
        $reflection = new \ReflectionClass(GuardianConsentService::class);

        $methods = [
            'requestConsent',
            'grantConsent',
            'withdrawConsent',
            'checkConsent',
            'getConsentsForMinor',
            'getConsentsForAdmin',
            'expireOldConsents',
            'isMinor',
        ];

        foreach ($methods as $method) {
            $this->assertTrue(
                $reflection->getMethod($method)->isStatic(),
                "Method {$method} should be static"
            );
        }
    }

    // ==========================================
    // isMinor Tests
    // ==========================================

    public function testIsMinorReturnsTrueForYoungUser(): void
    {
        $result = GuardianConsentService::isMinor(self::$minorUserId);
        $this->assertTrue($result);
    }

    public function testIsMinorReturnsFalseForAdultUser(): void
    {
        $result = GuardianConsentService::isMinor(self::$adultUserId);
        $this->assertFalse($result);
    }

    public function testIsMinorReturnsFalseForNonexistentUser(): void
    {
        $result = GuardianConsentService::isMinor(999999999);
        $this->assertFalse($result);
    }

    // ==========================================
    // requestConsent Tests
    // ==========================================

    public function testRequestConsentReturnsArrayWithToken(): void
    {
        $guardianData = [
            'guardian_name' => 'Jane Parent',
            'guardian_email' => 'jane.parent@test.com',
            'relationship' => 'parent',
        ];

        $result = GuardianConsentService::requestConsent(self::$minorUserId, $guardianData);

        $this->assertIsArray($result);
        $this->assertArrayHasKey('consent_token', $result);
        $this->assertNotEmpty($result['consent_token']);
        $this->assertEquals('pending', $result['status']);
        $this->assertEquals(self::$minorUserId, (int)$result['minor_user_id']);
        $this->assertEquals('Jane Parent', $result['guardian_name']);
        $this->assertEquals('jane.parent@test.com', $result['guardian_email']);
        $this->assertEquals('parent', $result['relationship']);
    }

    public function testRequestConsentWithOpportunityId(): void
    {
        $guardianData = [
            'guardian_name' => 'John Guardian',
            'guardian_email' => 'john.guardian@test.com',
            'relationship' => 'guardian',
        ];

        $result = GuardianConsentService::requestConsent(self::$minorUserId, $guardianData, self::$testOppId);

        $this->assertIsArray($result);
        $this->assertEquals(self::$testOppId, (int)$result['opportunity_id']);
    }

    public function testRequestConsentRequiresGuardianName(): void
    {
        $this->expectException(\InvalidArgumentException::class);

        GuardianConsentService::requestConsent(self::$minorUserId, [
            'guardian_email' => 'test@test.com',
            'relationship' => 'parent',
        ]);
    }

    public function testRequestConsentRequiresGuardianEmail(): void
    {
        $this->expectException(\InvalidArgumentException::class);

        GuardianConsentService::requestConsent(self::$minorUserId, [
            'guardian_name' => 'Test Parent',
            'relationship' => 'parent',
        ]);
    }

    public function testRequestConsentRequiresRelationship(): void
    {
        $this->expectException(\InvalidArgumentException::class);

        GuardianConsentService::requestConsent(self::$minorUserId, [
            'guardian_name' => 'Test Parent',
            'guardian_email' => 'test@test.com',
        ]);
    }

    public function testRequestConsentRejectsInvalidEmail(): void
    {
        $this->expectException(\InvalidArgumentException::class);

        GuardianConsentService::requestConsent(self::$minorUserId, [
            'guardian_name' => 'Test Parent',
            'guardian_email' => 'not-an-email',
            'relationship' => 'parent',
        ]);
    }

    public function testRequestConsentRejectsInvalidRelationship(): void
    {
        $this->expectException(\InvalidArgumentException::class);

        GuardianConsentService::requestConsent(self::$minorUserId, [
            'guardian_name' => 'Test Parent',
            'guardian_email' => 'test@test.com',
            'relationship' => 'sibling',
        ]);
    }

    public function testRequestConsentRejectsNonMinorUser(): void
    {
        $this->expectException(\InvalidArgumentException::class);

        GuardianConsentService::requestConsent(self::$adultUserId, [
            'guardian_name' => 'Test Parent',
            'guardian_email' => 'test@test.com',
            'relationship' => 'parent',
        ]);
    }

    // ==========================================
    // grantConsent Tests
    // ==========================================

    public function testGrantConsentWithValidToken(): void
    {
        $guardianData = [
            'guardian_name' => 'Grant Test Parent',
            'guardian_email' => 'grant.test@test.com',
            'relationship' => 'parent',
        ];

        $consent = GuardianConsentService::requestConsent(self::$minorUserId, $guardianData);
        $token = $consent['consent_token'];

        $result = GuardianConsentService::grantConsent($token, '127.0.0.1');
        $this->assertTrue($result);
    }

    public function testGrantConsentWithInvalidTokenReturnsFalse(): void
    {
        $result = GuardianConsentService::grantConsent('nonexistent_invalid_token_abc123', '127.0.0.1');
        $this->assertFalse($result);
    }

    public function testGrantConsentCannotBeGrantedTwice(): void
    {
        $guardianData = [
            'guardian_name' => 'Double Grant Parent',
            'guardian_email' => 'double.grant@test.com',
            'relationship' => 'parent',
        ];

        $consent = GuardianConsentService::requestConsent(self::$minorUserId, $guardianData);
        $token = $consent['consent_token'];

        // First grant succeeds
        $this->assertTrue(GuardianConsentService::grantConsent($token, '127.0.0.1'));

        // Second grant fails (status is no longer pending)
        $this->assertFalse(GuardianConsentService::grantConsent($token, '127.0.0.1'));
    }

    // ==========================================
    // withdrawConsent Tests
    // ==========================================

    public function testWithdrawActiveConsent(): void
    {
        $guardianData = [
            'guardian_name' => 'Withdraw Test Parent',
            'guardian_email' => 'withdraw.test@test.com',
            'relationship' => 'guardian',
        ];

        $consent = GuardianConsentService::requestConsent(self::$minorUserId, $guardianData);
        GuardianConsentService::grantConsent($consent['consent_token'], '127.0.0.1');

        $result = GuardianConsentService::withdrawConsent((int)$consent['id'], self::$minorUserId);
        $this->assertTrue($result);
    }

    public function testWithdrawPendingConsentReturnsFalse(): void
    {
        $guardianData = [
            'guardian_name' => 'Withdraw Pending Parent',
            'guardian_email' => 'withdraw.pending@test.com',
            'relationship' => 'parent',
        ];

        $consent = GuardianConsentService::requestConsent(self::$minorUserId, $guardianData);

        // Consent is pending, not active - withdraw should fail
        $result = GuardianConsentService::withdrawConsent((int)$consent['id'], self::$minorUserId);
        $this->assertFalse($result);
    }

    // ==========================================
    // checkConsent Tests
    // ==========================================

    public function testCheckConsentReturnsTrueWhenActiveConsentExists(): void
    {
        $guardianData = [
            'guardian_name' => 'Check Test Parent',
            'guardian_email' => 'check.test@test.com',
            'relationship' => 'parent',
        ];

        $consent = GuardianConsentService::requestConsent(self::$minorUserId, $guardianData);
        GuardianConsentService::grantConsent($consent['consent_token'], '127.0.0.1');

        $result = GuardianConsentService::checkConsent(self::$minorUserId);
        $this->assertTrue($result);
    }

    public function testCheckConsentReturnsFalseWhenNoConsent(): void
    {
        // Use the adult user who will have no consent records
        $result = GuardianConsentService::checkConsent(self::$adultUserId);
        $this->assertFalse($result);
    }

    public function testCheckConsentWithOpportunityId(): void
    {
        $guardianData = [
            'guardian_name' => 'Opp Check Parent',
            'guardian_email' => 'opp.check@test.com',
            'relationship' => 'parent',
        ];

        $consent = GuardianConsentService::requestConsent(self::$minorUserId, $guardianData, self::$testOppId);
        GuardianConsentService::grantConsent($consent['consent_token'], '127.0.0.1');

        $result = GuardianConsentService::checkConsent(self::$minorUserId, self::$testOppId);
        $this->assertTrue($result);
    }

    // ==========================================
    // getConsentsForMinor Tests
    // ==========================================

    public function testGetConsentsForMinorReturnsArray(): void
    {
        $result = GuardianConsentService::getConsentsForMinor(self::$minorUserId);

        $this->assertIsArray($result);
        $this->assertNotEmpty($result);

        // Verify structure of first record
        $first = $result[0];
        $this->assertArrayHasKey('id', $first);
        $this->assertArrayHasKey('minor_user_id', $first);
        $this->assertArrayHasKey('guardian_name', $first);
        $this->assertArrayHasKey('guardian_email', $first);
        $this->assertArrayHasKey('status', $first);
        $this->assertArrayHasKey('consent_token', $first);
    }

    public function testGetConsentsForMinorReturnsEmptyForUserWithNoConsents(): void
    {
        $result = GuardianConsentService::getConsentsForMinor(self::$adultUserId);
        $this->assertIsArray($result);
        $this->assertEmpty($result);
    }

    // ==========================================
    // getConsentsForAdmin Tests
    // ==========================================

    public function testGetConsentsForAdminReturnsPaginatedStructure(): void
    {
        $result = GuardianConsentService::getConsentsForAdmin();

        $this->assertIsArray($result);
        $this->assertArrayHasKey('items', $result);
        $this->assertArrayHasKey('cursor', $result);
        $this->assertArrayHasKey('has_more', $result);
        $this->assertIsArray($result['items']);
        $this->assertIsBool($result['has_more']);
    }

    public function testGetConsentsForAdminFiltersByStatus(): void
    {
        $result = GuardianConsentService::getConsentsForAdmin(['status' => 'active']);

        $this->assertIsArray($result);
        foreach ($result['items'] as $item) {
            $this->assertEquals('active', $item['status']);
        }
    }

    public function testGetConsentsForAdminRespectsLimit(): void
    {
        $result = GuardianConsentService::getConsentsForAdmin(['limit' => 2]);

        $this->assertIsArray($result);
        $this->assertLessThanOrEqual(2, count($result['items']));
    }

    // ==========================================
    // expireOldConsents Tests
    // ==========================================

    public function testExpireOldConsentsReturnsInt(): void
    {
        // Insert a consent that is already expired
        $ts = time();
        $expiredAt = date('Y-m-d H:i:s', strtotime('-1 day'));
        Database::query(
            "INSERT INTO vol_guardian_consents
             (tenant_id, minor_user_id, guardian_name, guardian_email, relationship, consent_token, status, expires_at, created_at)
             VALUES (?, ?, ?, ?, ?, ?, 'pending', ?, NOW())",
            [
                self::$testTenantId,
                self::$minorUserId,
                'Expiry Test Parent',
                "expiry.test.{$ts}@test.com",
                'parent',
                bin2hex(random_bytes(16)),
                $expiredAt,
            ]
        );

        $result = GuardianConsentService::expireOldConsents();

        $this->assertIsInt($result);
        $this->assertGreaterThanOrEqual(1, $result);
    }
}
