<?php
// Copyright © 2024–2026 Jasper Ford
// SPDX-License-Identifier: AGPL-3.0-or-later
// Author: Jasper Ford
// See NOTICE file for attribution and acknowledgements.

declare(strict_types=1);

namespace App\Tests\Services;

use App\Tests\DatabaseTestCase;
use App\Core\Database;
use App\Core\TenantContext;
use App\Services\VolunteerWellbeingService;

/**
 * VolunteerWellbeingService Tests
 *
 * Tests burnout risk detection, alert retrieval, and alert updates.
 */
class VolunteerWellbeingServiceTest extends DatabaseTestCase
{
    private const TENANT_ID = 2;

    protected function setUp(): void
    {
        parent::setUp();
        TenantContext::setById(self::TENANT_ID);
    }

    // ==========================================
    // detectBurnoutRisk
    // ==========================================

    public function testDetectBurnoutRiskReturnsRequiredKeys(): void
    {
        $this->requireTables(['vol_shift_signups', 'vol_shifts', 'vol_logs', 'vol_wellbeing_alerts', 'users']);

        $userId = $this->createUser('wellbeing-keys');

        $result = VolunteerWellbeingService::detectBurnoutRisk($userId);

        $this->assertIsArray($result);
        $this->assertArrayHasKey('risk_score', $result);
        $this->assertArrayHasKey('risk_level', $result);
        $this->assertArrayHasKey('indicators', $result);
        $this->assertArrayHasKey('recommendations', $result);
    }

    public function testDetectBurnoutRiskReturnsLowForInactiveUser(): void
    {
        $this->requireTables(['vol_shift_signups', 'vol_shifts', 'vol_logs', 'vol_wellbeing_alerts', 'users']);

        $userId = $this->createUser('wellbeing-inactive');

        $result = VolunteerWellbeingService::detectBurnoutRisk($userId);

        $this->assertSame('low', $result['risk_level']);
    }

    public function testDetectBurnoutRiskIncludesAllIndicators(): void
    {
        $this->requireTables(['vol_shift_signups', 'vol_shifts', 'vol_logs', 'vol_wellbeing_alerts', 'users']);

        $userId = $this->createUser('wellbeing-indicators');

        $result = VolunteerWellbeingService::detectBurnoutRisk($userId);

        $indicators = $result['indicators'];
        $this->assertArrayHasKey('shift_frequency', $indicators);
        $this->assertArrayHasKey('cancellation_rate', $indicators);
        $this->assertArrayHasKey('hours_trend', $indicators);
        $this->assertArrayHasKey('engagement_gap', $indicators);
        $this->assertArrayHasKey('overcommitment', $indicators);
    }

    public function testDetectBurnoutRiskScoreIsBounded(): void
    {
        $this->requireTables(['vol_shift_signups', 'vol_shifts', 'vol_logs', 'vol_wellbeing_alerts', 'users']);

        $userId = $this->createUser('wellbeing-bounded');

        $result = VolunteerWellbeingService::detectBurnoutRisk($userId);

        $this->assertGreaterThanOrEqual(0, $result['risk_score']);
        $this->assertLessThanOrEqual(100, $result['risk_score']);
    }

    public function testDetectBurnoutRiskScoresHighCancellationRate(): void
    {
        $this->requireTables(['vol_shift_signups', 'vol_shifts', 'vol_logs', 'vol_wellbeing_alerts', 'vol_opportunities', 'vol_organizations', 'users']);

        $userId = $this->createUser('wellbeing-cancel');
        $ownerId = $this->createUser('wellbeing-cancel-owner');
        $oppId = $this->createOpportunity($ownerId);

        // Create signups with 70% cancellation rate
        for ($i = 0; $i < 10; $i++) {
            Database::query(
                'INSERT INTO vol_shifts (tenant_id, opportunity_id, start_time, end_time, capacity, created_at) VALUES (?, ?, DATE_ADD(NOW(), INTERVAL ? DAY), DATE_ADD(DATE_ADD(NOW(), INTERVAL ? DAY), INTERVAL 2 HOUR), 5, NOW())',
                [self::TENANT_ID, $oppId, $i + 1, $i + 1]
            );
            $shiftId = (int) Database::getInstance()->lastInsertId();

            $status = $i < 7 ? 'cancelled' : 'confirmed';
            Database::query(
                "INSERT INTO vol_shift_signups (tenant_id, shift_id, user_id, status, created_at) VALUES (?, ?, ?, ?, NOW())",
                [self::TENANT_ID, $shiftId, $userId, $status]
            );
        }

        $result = VolunteerWellbeingService::detectBurnoutRisk($userId);

        $this->assertGreaterThan(0, $result['risk_score']);
        $this->assertGreaterThan(50, $result['indicators']['cancellation_rate']['rate_percent']);
    }

    public function testDetectBurnoutRiskProvidesRecommendations(): void
    {
        $this->requireTables(['vol_shift_signups', 'vol_shifts', 'vol_logs', 'vol_wellbeing_alerts', 'users']);

        $userId = $this->createUser('wellbeing-recs');

        $result = VolunteerWellbeingService::detectBurnoutRisk($userId);

        $this->assertIsArray($result['recommendations']);
        $this->assertNotEmpty($result['recommendations']);
    }

    // ==========================================
    // getActiveAlerts
    // ==========================================

    public function testGetActiveAlertsReturnsArray(): void
    {
        $this->requireTables(['vol_wellbeing_alerts', 'users']);

        $result = VolunteerWellbeingService::getActiveAlerts();

        $this->assertIsArray($result);
    }

    public function testGetActiveAlertsReturnsOnlyActiveStatus(): void
    {
        $this->requireTables(['vol_wellbeing_alerts', 'users']);

        $userId = $this->createUser('wellbeing-alert');

        // Insert an active alert
        Database::query(
            "INSERT INTO vol_wellbeing_alerts (tenant_id, user_id, risk_level, risk_score, indicators, coordinator_notified, status, created_at, updated_at)
             VALUES (?, ?, 'high', 65, '{}', 0, 'active', NOW(), NOW())",
            [self::TENANT_ID, $userId]
        );

        // Insert a resolved alert
        Database::query(
            "INSERT INTO vol_wellbeing_alerts (tenant_id, user_id, risk_level, risk_score, indicators, coordinator_notified, status, created_at, updated_at)
             VALUES (?, ?, 'moderate', 40, '{}', 1, 'resolved', NOW(), NOW())",
            [self::TENANT_ID, $userId]
        );

        $alerts = VolunteerWellbeingService::getActiveAlerts();

        foreach ($alerts as $alert) {
            $this->assertSame('active', $alert['status']);
        }
    }

    // ==========================================
    // updateAlert
    // ==========================================

    public function testUpdateAlertReturnsFalseForInvalidAction(): void
    {
        $result = VolunteerWellbeingService::updateAlert(1, 'invalid_action');

        $this->assertFalse($result);
        $errors = VolunteerWellbeingService::getErrors();
        $this->assertNotEmpty($errors);
        $this->assertSame('VALIDATION_ERROR', $errors[0]['code']);
    }

    public function testUpdateAlertReturnsFalseForNonexistentAlert(): void
    {
        $this->requireTables(['vol_wellbeing_alerts']);

        $result = VolunteerWellbeingService::updateAlert(999999, 'resolved');

        $this->assertFalse($result);
        $errors = VolunteerWellbeingService::getErrors();
        $this->assertNotEmpty($errors);
        $this->assertSame('NOT_FOUND', $errors[0]['code']);
    }

    public function testUpdateAlertResolvesSuccessfully(): void
    {
        $this->requireTables(['vol_wellbeing_alerts', 'users']);

        $userId = $this->createUser('wellbeing-resolve');

        Database::query(
            "INSERT INTO vol_wellbeing_alerts (tenant_id, user_id, risk_level, risk_score, indicators, coordinator_notified, status, created_at, updated_at)
             VALUES (?, ?, 'high', 70, '{}', 0, 'active', NOW(), NOW())",
            [self::TENANT_ID, $userId]
        );
        $alertId = (int) Database::getInstance()->lastInsertId();

        $result = VolunteerWellbeingService::updateAlert($alertId, 'resolved', 'Checked in with volunteer.');

        $this->assertTrue($result);

        $row = Database::query(
            'SELECT status, coordinator_notified, coordinator_notes FROM vol_wellbeing_alerts WHERE id = ?',
            [$alertId]
        )->fetch(\PDO::FETCH_ASSOC);

        $this->assertSame('resolved', $row['status']);
        $this->assertEquals(1, (int) $row['coordinator_notified']);
        $this->assertSame('Checked in with volunteer.', $row['coordinator_notes']);
    }

    public function testUpdateAlertAcceptsAcknowledgedAndDismissed(): void
    {
        $this->requireTables(['vol_wellbeing_alerts', 'users']);

        $userId = $this->createUser('wellbeing-actions');

        Database::query(
            "INSERT INTO vol_wellbeing_alerts (tenant_id, user_id, risk_level, risk_score, indicators, coordinator_notified, status, created_at, updated_at)
             VALUES (?, ?, 'moderate', 40, '{}', 0, 'active', NOW(), NOW())",
            [self::TENANT_ID, $userId]
        );
        $alertId1 = (int) Database::getInstance()->lastInsertId();

        Database::query(
            "INSERT INTO vol_wellbeing_alerts (tenant_id, user_id, risk_level, risk_score, indicators, coordinator_notified, status, created_at, updated_at)
             VALUES (?, ?, 'moderate', 35, '{}', 0, 'active', NOW(), NOW())",
            [self::TENANT_ID, $userId]
        );
        $alertId2 = (int) Database::getInstance()->lastInsertId();

        $this->assertTrue(VolunteerWellbeingService::updateAlert($alertId1, 'acknowledged'));
        $this->assertTrue(VolunteerWellbeingService::updateAlert($alertId2, 'dismissed'));
    }

    // ==========================================
    // Helpers
    // ==========================================

    private function createUser(string $prefix): int
    {
        $uniq = $prefix . '-' . str_replace('.', '', (string) microtime(true)) . '-' . random_int(1000, 9999);
        Database::query(
            'INSERT INTO users (tenant_id, email, username, first_name, last_name, name, balance, is_approved, created_at) VALUES (?, ?, ?, ?, ?, ?, ?, 1, NOW())',
            [self::TENANT_ID, $uniq . '@example.test', $uniq, 'Test', 'User', 'Test User', 0]
        );
        return (int) Database::getInstance()->lastInsertId();
    }

    private function createOpportunity(int $ownerId): int
    {
        $orgId = $this->createOrganization($ownerId);
        $uniq = 'opp-' . str_replace('.', '', (string) microtime(true)) . '-' . random_int(1000, 9999);
        Database::query(
            "INSERT INTO vol_opportunities (tenant_id, organization_id, created_by, title, description, location, status, is_active, created_at) VALUES (?, ?, ?, ?, ?, ?, 'open', 1, NOW())",
            [self::TENANT_ID, $orgId, $ownerId, $uniq, 'Test opportunity', 'Remote']
        );
        return (int) Database::getInstance()->lastInsertId();
    }

    private function createOrganization(int $ownerId): int
    {
        $uniq = 'org-' . str_replace('.', '', (string) microtime(true)) . '-' . random_int(1000, 9999);
        Database::query(
            'INSERT INTO vol_organizations (tenant_id, user_id, name, description, contact_email, status, created_at) VALUES (?, ?, ?, ?, ?, ?, NOW())',
            [self::TENANT_ID, $ownerId, $uniq, 'Test org', $uniq . '@example.test', 'approved']
        );
        return (int) Database::getInstance()->lastInsertId();
    }

    /** @param string[] $tables */
    private function requireTables(array $tables): void
    {
        foreach ($tables as $table) {
            $exists = (int) Database::query(
                'SELECT COUNT(*) FROM information_schema.TABLES WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = ?',
                [$table]
            )->fetchColumn();
            if ($exists === 0) {
                $this->markTestSkipped('Required table not present in test DB: ' . $table);
            }
        }
    }
}
