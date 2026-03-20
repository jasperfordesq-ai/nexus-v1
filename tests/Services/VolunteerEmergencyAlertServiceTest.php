<?php
// Copyright (c) 2024-2026 Jasper Ford
// SPDX-License-Identifier: AGPL-3.0-or-later
// Author: Jasper Ford
// See NOTICE file for attribution and acknowledgements.

declare(strict_types=1);

namespace Nexus\Tests\Services;

use Nexus\Tests\DatabaseTestCase;
use Nexus\Core\Database;
use App\Core\TenantContext;
use App\Services\VolunteerEmergencyAlertService;

/**
 * VolunteerEmergencyAlertService Tests
 *
 * Tests creation, cancellation, and response flows for emergency shift alerts.
 */
class VolunteerEmergencyAlertServiceTest extends DatabaseTestCase
{
    private const TENANT_ID = 2;

    protected function setUp(): void
    {
        parent::setUp();
        TenantContext::setById(self::TENANT_ID);
    }

    // ==========================================
    // createAlert - validation
    // ==========================================

    public function testCreateAlertRequiresShiftId(): void
    {
        $this->requireTables(['vol_emergency_alerts']);

        $ownerId = $this->createUser('alert-owner');

        $result = VolunteerEmergencyAlertService::createAlert($ownerId, [
            'shift_id' => 0,
            'message'  => 'We need help urgently!',
            'priority' => 'urgent',
        ]);

        $this->assertNull($result);
        $errors = VolunteerEmergencyAlertService::getErrors();
        $this->assertNotEmpty($errors);
        $this->assertSame('VALIDATION_ERROR', $errors[0]['code']);
        $this->assertSame('shift_id', $errors[0]['field'] ?? '');
    }

    public function testCreateAlertRequiresMessage(): void
    {
        $this->requireTables(['vol_emergency_alerts']);

        $ownerId = $this->createUser('alert-owner');
        [, $shiftId] = $this->createOpportunityAndShift($ownerId);

        $result = VolunteerEmergencyAlertService::createAlert($ownerId, [
            'shift_id' => $shiftId,
            'message'  => '',
            'priority' => 'urgent',
        ]);

        $this->assertNull($result);
        $errors = VolunteerEmergencyAlertService::getErrors();
        $this->assertNotEmpty($errors);
        $this->assertSame('VALIDATION_ERROR', $errors[0]['code']);
        $this->assertSame('message', $errors[0]['field'] ?? '');
    }

    public function testCreateAlertRejectsInvalidPriority(): void
    {
        $this->requireTables(['vol_emergency_alerts']);

        $ownerId = $this->createUser('alert-owner');
        [, $shiftId] = $this->createOpportunityAndShift($ownerId);

        $result = VolunteerEmergencyAlertService::createAlert($ownerId, [
            'shift_id' => $shiftId,
            'message'  => 'We need help urgently!',
            'priority' => 'extreme',
        ]);

        $this->assertNull($result);
        $errors = VolunteerEmergencyAlertService::getErrors();
        $this->assertNotEmpty($errors);
        $this->assertSame('VALIDATION_ERROR', $errors[0]['code']);
        $this->assertSame('priority', $errors[0]['field'] ?? '');
    }

    public function testCreateAlertRejectsForbiddenUser(): void
    {
        $this->requireTables(['vol_emergency_alerts']);

        $ownerId     = $this->createUser('alert-org-owner');
        $forbiddenId = $this->createUser('alert-outsider');
        [, $shiftId] = $this->createOpportunityAndShift($ownerId);

        $result = VolunteerEmergencyAlertService::createAlert($forbiddenId, [
            'shift_id' => $shiftId,
            'message'  => 'We need help urgently!',
            'priority' => 'urgent',
        ]);

        $this->assertNull($result);
        $errors = VolunteerEmergencyAlertService::getErrors();
        $this->assertNotEmpty($errors);
        $this->assertSame('FORBIDDEN', $errors[0]['code']);
    }

    public function testCreateAlertSucceedsForOrgOwner(): void
    {
        $this->requireTables(['vol_emergency_alerts']);

        $ownerId = $this->createUser('alert-org-owner');
        [, $shiftId] = $this->createOpportunityAndShift($ownerId);

        $alertId = VolunteerEmergencyAlertService::createAlert($ownerId, [
            'shift_id'      => $shiftId,
            'message'       => 'We urgently need a volunteer for this shift!',
            'priority'      => 'urgent',
            'expires_hours' => 6,
        ]);

        $this->assertNotNull($alertId);
        $this->assertIsInt($alertId);
        $this->assertGreaterThan(0, $alertId);

        $count = (int)Database::query(
            'SELECT COUNT(*) FROM vol_emergency_alerts WHERE id = ? AND tenant_id = ?',
            [$alertId, self::TENANT_ID]
        )->fetchColumn();
        $this->assertSame(1, $count);
    }

    // ==========================================
    // respond - validation
    // ==========================================

    public function testRespondRejectsInvalidResponse(): void
    {
        $this->requireTables(['vol_emergency_alerts', 'vol_emergency_alert_recipients']);

        $userId = $this->createUser('alert-responder');

        $result = VolunteerEmergencyAlertService::respond(999999, $userId, 'maybe');

        $this->assertFalse($result);
        $errors = VolunteerEmergencyAlertService::getErrors();
        $this->assertNotEmpty($errors);
        $this->assertSame('VALIDATION_ERROR', $errors[0]['code']);
    }

    public function testRespondRejectsNonRecipient(): void
    {
        $this->requireTables(['vol_emergency_alerts', 'vol_emergency_alert_recipients']);

        $ownerId     = $this->createUser('alert-owner');
        $outsiderId  = $this->createUser('alert-non-recipient');
        [, $shiftId] = $this->createOpportunityAndShift($ownerId);

        $alertId = VolunteerEmergencyAlertService::createAlert($ownerId, [
            'shift_id' => $shiftId,
            'message'  => 'Help needed',
            'priority' => 'normal',
        ]);
        $this->assertNotNull($alertId);

        // outsiderId was never added as a recipient
        $result = VolunteerEmergencyAlertService::respond($alertId, $outsiderId, 'declined');

        $this->assertFalse($result);
        $errors = VolunteerEmergencyAlertService::getErrors();
        $this->assertNotEmpty($errors);
        $this->assertSame('NOT_FOUND', $errors[0]['code']);
    }

    // ==========================================
    // cancelAlert
    // ==========================================

    public function testCancelAlertFailsForNonCreator(): void
    {
        $this->requireTables(['vol_emergency_alerts']);

        $ownerId     = $this->createUser('alert-owner');
        $outsiderId  = $this->createUser('alert-outsider');
        [, $shiftId] = $this->createOpportunityAndShift($ownerId);

        $alertId = VolunteerEmergencyAlertService::createAlert($ownerId, [
            'shift_id' => $shiftId,
            'message'  => 'Help needed',
            'priority' => 'normal',
        ]);
        $this->assertNotNull($alertId);

        $result = VolunteerEmergencyAlertService::cancelAlert($alertId, $outsiderId);

        $this->assertFalse($result);
        $errors = VolunteerEmergencyAlertService::getErrors();
        $this->assertNotEmpty($errors);
        $this->assertSame('NOT_FOUND', $errors[0]['code']);
    }

    public function testCancelAlertSucceedsForCreator(): void
    {
        $this->requireTables(['vol_emergency_alerts']);

        $ownerId = $this->createUser('alert-owner');
        [, $shiftId] = $this->createOpportunityAndShift($ownerId);

        $alertId = VolunteerEmergencyAlertService::createAlert($ownerId, [
            'shift_id' => $shiftId,
            'message'  => 'Help needed',
            'priority' => 'normal',
        ]);
        $this->assertNotNull($alertId);

        $result = VolunteerEmergencyAlertService::cancelAlert($alertId, $ownerId);

        $this->assertTrue($result);

        $status = (string)Database::query(
            'SELECT status FROM vol_emergency_alerts WHERE id = ? AND tenant_id = ?',
            [$alertId, self::TENANT_ID]
        )->fetchColumn();
        $this->assertSame('cancelled', $status);
    }

    // ==========================================
    // Helpers
    // ==========================================

    private function createUser(string $prefix): int
    {
        $uniq = $prefix . '-' . str_replace('.', '', (string)microtime(true)) . '-' . random_int(1000, 9999);
        Database::query(
            'INSERT INTO users (tenant_id, email, username, first_name, last_name, name, balance, is_approved, created_at) VALUES (?, ?, ?, ?, ?, ?, ?, 1, NOW())',
            [self::TENANT_ID, $uniq . '@example.test', $uniq, 'Test', 'User', 'Test User', 0]
        );
        return (int)Database::getInstance()->lastInsertId();
    }

    private function createOrganization(int $ownerId, string $status = 'approved'): int
    {
        $uniq = 'org-' . str_replace('.', '', (string)microtime(true)) . '-' . random_int(1000, 9999);
        Database::query(
            'INSERT INTO vol_organizations (tenant_id, user_id, name, description, contact_email, status, created_at) VALUES (?, ?, ?, ?, ?, ?, NOW())',
            [self::TENANT_ID, $ownerId, $uniq, 'Test organization description', $uniq . '@example.test', $status]
        );
        return (int)Database::getInstance()->lastInsertId();
    }

    /** @return array{0:int,1:int} [opportunityId, shiftId] */
    private function createOpportunityAndShift(int $ownerId): array
    {
        $orgId = $this->createOrganization($ownerId, 'approved');
        $uniq  = 'opp-' . str_replace('.', '', (string)microtime(true)) . '-' . random_int(1000, 9999);
        Database::query(
            "INSERT INTO vol_opportunities (tenant_id, organization_id, created_by, title, description, location, status, is_active, created_at) VALUES (?, ?, ?, ?, ?, ?, 'open', 1, NOW())",
            [self::TENANT_ID, $orgId, $ownerId, $uniq, 'Test opportunity', 'Remote']
        );
        $opportunityId = (int)Database::getInstance()->lastInsertId();
        Database::query(
            'INSERT INTO vol_shifts (tenant_id, opportunity_id, start_time, end_time, capacity, created_at) VALUES (?, ?, DATE_ADD(NOW(), INTERVAL 3 DAY), DATE_ADD(DATE_ADD(NOW(), INTERVAL 3 DAY), INTERVAL 2 HOUR), 5, NOW())',
            [self::TENANT_ID, $opportunityId]
        );
        $shiftId = (int)Database::getInstance()->lastInsertId();
        return [$opportunityId, $shiftId];
    }

    /** @param string[] $tables */
    private function requireTables(array $tables): void
    {
        foreach ($tables as $table) {
            $exists = (int)Database::query(
                'SELECT COUNT(*) FROM information_schema.TABLES WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = ?',
                [$table]
            )->fetchColumn();
            if ($exists === 0) {
                $this->markTestSkipped('Required table not present in test DB: ' . $table);
            }
        }
    }
}
