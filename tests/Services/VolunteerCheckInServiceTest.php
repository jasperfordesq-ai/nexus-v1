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
use Nexus\Services\VolunteerCheckInService;

class VolunteerCheckInServiceTest extends DatabaseTestCase
{
    private const TENANT_ID = 2;

    protected function setUp(): void
    {
        parent::setUp();
        TenantContext::setById(self::TENANT_ID);
    }

    public function testGenerateTokenFailsForUnapprovedVolunteer(): void
    {
        $this->requireTables(["vol_shift_checkins"]);
        $ownerId = $this->createUser("token-owner");
        $volunteerId = $this->createUser("token-volunteer");
        [, $shiftId] = $this->createOpportunityAndShift($ownerId);
        $token = VolunteerCheckInService::generateToken($shiftId, $volunteerId);
        $this->assertNull($token);
        $errors = VolunteerCheckInService::getErrors();
        $this->assertNotEmpty($errors);
        $this->assertSame("FORBIDDEN", $errors[0]["code"]);
    }

    public function testGenerateTokenSucceedsForApprovedVolunteer(): void
    {
        $this->requireTables(["vol_shift_checkins"]);
        $ownerId = $this->createUser("token-owner2");
        $volunteerId = $this->createUser("token-volunteer2");
        [$opportunityId, $shiftId] = $this->createOpportunityAndShift($ownerId);
        $this->approveVolunteerForShift($opportunityId, $shiftId, $volunteerId);
        $token = VolunteerCheckInService::generateToken($shiftId, $volunteerId);
        $this->assertNotNull($token);
        $this->assertIsString($token);
        $this->assertNotEmpty($token);
    }

    public function testGenerateTokenIsIdempotent(): void
    {
        $this->requireTables(["vol_shift_checkins"]);
        $ownerId = $this->createUser("token-idem-owner");
        $volunteerId = $this->createUser("token-idem-vol");
        [$opportunityId, $shiftId] = $this->createOpportunityAndShift($ownerId);
        $this->approveVolunteerForShift($opportunityId, $shiftId, $volunteerId);
        $token1 = VolunteerCheckInService::generateToken($shiftId, $volunteerId);
        $token2 = VolunteerCheckInService::generateToken($shiftId, $volunteerId);
        $this->assertSame($token1, $token2);
    }

    public function testGetShiftCheckInsDoesNotExposeQrToken(): void
    {
        $this->requireTables(["vol_shift_checkins"]);
        $ownerId = $this->createUser("checkins-owner");
        $volunteerId = $this->createUser("checkins-vol");
        [$opportunityId, $shiftId] = $this->createOpportunityAndShift($ownerId);
        $this->approveVolunteerForShift($opportunityId, $shiftId, $volunteerId);
        $token = VolunteerCheckInService::generateToken($shiftId, $volunteerId);
        $this->assertNotNull($token);
        $checkins = VolunteerCheckInService::getShiftCheckIns($shiftId);
        $this->assertNotEmpty($checkins);
        $this->assertArrayNotHasKey("qr_token", $checkins[0]);
        $this->assertArrayHasKey("user", $checkins[0]);
        $this->assertArrayHasKey("status", $checkins[0]);
    }

    public function testGetUserCheckInReturnsQrToken(): void
    {
        $this->requireTables(["vol_shift_checkins"]);
        $ownerId = $this->createUser("ucheckin-owner");
        $volunteerId = $this->createUser("ucheckin-vol");
        [$opportunityId, $shiftId] = $this->createOpportunityAndShift($ownerId);
        $this->approveVolunteerForShift($opportunityId, $shiftId, $volunteerId);
        $token = VolunteerCheckInService::generateToken($shiftId, $volunteerId);
        $this->assertNotNull($token);
        $checkin = VolunteerCheckInService::getUserCheckIn($shiftId, $volunteerId);
        $this->assertNotNull($checkin);
        $this->assertArrayHasKey("qr_token", $checkin);
        $this->assertArrayHasKey("qr_url", $checkin);
        $this->assertArrayHasKey("status", $checkin);
        $this->assertSame($token, $checkin["qr_token"]);
    }

    public function testGetUserCheckInReturnsNullForUnapprovedVolunteer(): void
    {
        $this->requireTables(["vol_shift_checkins"]);
        $ownerId = $this->createUser("ucheckin-noapp-owner");
        $volunteerId = $this->createUser("ucheckin-noapp-vol");
        [, $shiftId] = $this->createOpportunityAndShift($ownerId);
        $result = VolunteerCheckInService::getUserCheckIn($shiftId, $volunteerId);
        $this->assertNull($result);
    }

    public function testVerifyCheckInFailsTooEarly(): void
    {
        $this->requireTables(["vol_shift_checkins"]);
        $ownerId = $this->createUser("verify-early-owner");
        $volunteerId = $this->createUser("verify-early-vol");
        [$opportunityId, $shiftId] = $this->createFutureShift($ownerId, 3600 * 2);
        $this->approveVolunteerForShift($opportunityId, $shiftId, $volunteerId);
        $token = VolunteerCheckInService::generateToken($shiftId, $volunteerId);
        $this->assertNotNull($token);
        $result = VolunteerCheckInService::verifyCheckIn($token);
        $this->assertNull($result);
        $errors = VolunteerCheckInService::getErrors();
        $this->assertNotEmpty($errors);
        $this->assertSame("VALIDATION_ERROR", $errors[0]["code"]);
    }

    public function testCheckOutFailsWhenNotCheckedIn(): void
    {
        $this->requireTables(["vol_shift_checkins"]);
        $ownerId = $this->createUser("checkout-owner");
        $volunteerId = $this->createUser("checkout-vol");
        [$opportunityId, $shiftId] = $this->createOpportunityAndShift($ownerId);
        $this->approveVolunteerForShift($opportunityId, $shiftId, $volunteerId);
        $token = VolunteerCheckInService::generateToken($shiftId, $volunteerId);
        $this->assertNotNull($token);
        $result = VolunteerCheckInService::checkOut($token);
        $this->assertFalse($result);
        $errors = VolunteerCheckInService::getErrors();
        $this->assertNotEmpty($errors);
        $this->assertSame("VALIDATION_ERROR", $errors[0]["code"]);
    }

    public function testVerifyCheckInFailsForInvalidToken(): void
    {
        $this->requireTables(["vol_shift_checkins"]);
        $result = VolunteerCheckInService::verifyCheckIn("invalidtoken000000000000000000000000000");
        $this->assertNull($result);
        $errors = VolunteerCheckInService::getErrors();
        $this->assertNotEmpty($errors);
        $this->assertSame("NOT_FOUND", $errors[0]["code"]);
    }

    private function createUser(string $prefix): int
    {
        $uniq = $prefix . "-" . str_replace(".", "", (string)microtime(true)) . "-" . random_int(1000, 9999);
        Database::query(
            "INSERT INTO users (tenant_id, email, username, first_name, last_name, name, balance, is_approved, created_at) VALUES (?, ?, ?, ?, ?, ?, ?, 1, NOW())",
            [self::TENANT_ID, $uniq . "@example.test", $uniq, "Test", "User", "Test User", 0]
        );
        return (int)Database::getInstance()->lastInsertId();
    }

    /**
     * @return array{0:int,1:int}
     */
    private function createOpportunityAndShift(int $ownerId): array
    {
        $uniq = "org-" . str_replace(".", "", (string)microtime(true)) . "-" . random_int(1000, 9999);
        Database::query(
            "INSERT INTO vol_organizations (tenant_id, user_id, name, description, contact_email, status, created_at) VALUES (?, ?, ?, ?, ?, ?, NOW())",
            [self::TENANT_ID, $ownerId, $uniq, "Test organization", $uniq . "@example.test", "approved"]
        );
        $orgId = (int)Database::getInstance()->lastInsertId();

        $uniq2 = "opp-" . str_replace(".", "", (string)microtime(true)) . "-" . random_int(1000, 9999);
        Database::query(
            "INSERT INTO vol_opportunities (tenant_id, organization_id, created_by, title, description, location, status, is_active, created_at) VALUES (?, ?, ?, ?, ?, ?, ?, 1, NOW())",
            [self::TENANT_ID, $orgId, $ownerId, $uniq2, "Test opportunity", "Remote", "open"]
        );
        $opportunityId = (int)Database::getInstance()->lastInsertId();

        Database::query(
            "INSERT INTO vol_shifts (tenant_id, opportunity_id, start_time, end_time, capacity, created_at) VALUES (?, ?, DATE_ADD(NOW(), INTERVAL 3 DAY), DATE_ADD(DATE_ADD(NOW(), INTERVAL 3 DAY), INTERVAL 2 HOUR), 5, NOW())",
            [self::TENANT_ID, $opportunityId]
        );
        $shiftId = (int)Database::getInstance()->lastInsertId();

        return [$opportunityId, $shiftId];
    }

    /**
     * Create a shift starting $secondsFromNow in the future (for timing tests).
     *
     * @return array{0:int,1:int}
     */
    private function createFutureShift(int $ownerId, int $secondsFromNow): array
    {
        $uniq = "org-" . str_replace(".", "", (string)microtime(true)) . "-" . random_int(1000, 9999);
        Database::query(
            "INSERT INTO vol_organizations (tenant_id, user_id, name, description, contact_email, status, created_at) VALUES (?, ?, ?, ?, ?, ?, NOW())",
            [self::TENANT_ID, $ownerId, $uniq, "Test organization", $uniq . "@example.test", "approved"]
        );
        $orgId = (int)Database::getInstance()->lastInsertId();

        $uniq2 = "opp-" . str_replace(".", "", (string)microtime(true)) . "-" . random_int(1000, 9999);
        Database::query(
            "INSERT INTO vol_opportunities (tenant_id, organization_id, created_by, title, description, location, status, is_active, created_at) VALUES (?, ?, ?, ?, ?, ?, ?, 1, NOW())",
            [self::TENANT_ID, $orgId, $ownerId, $uniq2, "Test opportunity", "Remote", "open"]
        );
        $opportunityId = (int)Database::getInstance()->lastInsertId();

        Database::query(
            "INSERT INTO vol_shifts (tenant_id, opportunity_id, start_time, end_time, capacity, created_at) VALUES (?, ?, DATE_ADD(NOW(), INTERVAL ? SECOND), DATE_ADD(DATE_ADD(NOW(), INTERVAL ? SECOND), INTERVAL 2 HOUR), 5, NOW())",
            [self::TENANT_ID, $opportunityId, $secondsFromNow, $secondsFromNow]
        );
        $shiftId = (int)Database::getInstance()->lastInsertId();

        return [$opportunityId, $shiftId];
    }

    private function approveVolunteerForShift(int $opportunityId, int $shiftId, int $userId): void
    {
        Database::query(
            "INSERT INTO vol_applications (tenant_id, opportunity_id, user_id, shift_id, status, created_at) VALUES (?, ?, ?, ?, ?, NOW())",
            [self::TENANT_ID, $opportunityId, $userId, $shiftId, "approved"]
        );
    }

    private function requireTables(array $tables): void
    {
        foreach ($tables as $table) {
            $exists = (int)Database::query(
                "SELECT COUNT(*) FROM information_schema.TABLES WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = ?",
                [$table]
            )->fetchColumn();
            if ($exists === 0) {
                $this->markTestSkipped("Required table not present in test DB: {$table}");
            }
        }
    }
}
