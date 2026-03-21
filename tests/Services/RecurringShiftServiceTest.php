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
use App\Services\RecurringShiftService;

/**
 * RecurringShiftService Tests
 *
 * Tests pattern CRUD and occurrence generation.
 */
class RecurringShiftServiceTest extends DatabaseTestCase
{
    private const TENANT_ID = 2;
    private RecurringShiftService $service;

    protected function setUp(): void
    {
        parent::setUp();
        TenantContext::setById(self::TENANT_ID);
        $this->service = new RecurringShiftService();
    }

    // ==========================================
    // createPattern
    // ==========================================

    public function testCreatePatternReturnsNullForNonexistentOpportunity(): void
    {
        $this->requireTables(['recurring_shift_patterns', 'vol_opportunities']);

        $userId = $this->createUser('rshift-owner');

        $result = $this->service->createPattern(999999, $userId, [
            'frequency' => 'weekly',
            'start_time' => '09:00:00',
            'end_time' => '12:00:00',
        ]);

        $this->assertNull($result);
        $errors = $this->service->getErrors();
        $this->assertNotEmpty($errors);
        $this->assertStringContainsString('not found', $errors[0]);
    }

    public function testCreatePatternRejectsInvalidFrequency(): void
    {
        $this->requireTables(['recurring_shift_patterns', 'vol_opportunities', 'vol_organizations']);

        $userId = $this->createUser('rshift-badfreq');
        $oppId = $this->createOpportunity($userId);

        $result = $this->service->createPattern($oppId, $userId, [
            'frequency' => 'every_other_tuesday',
            'start_time' => '09:00:00',
            'end_time' => '12:00:00',
        ]);

        $this->assertNull($result);
        $errors = $this->service->getErrors();
        $this->assertNotEmpty($errors);
        $this->assertStringContainsString('frequency', $errors[0]);
    }

    public function testCreatePatternRequiresStartAndEndTime(): void
    {
        $this->requireTables(['recurring_shift_patterns', 'vol_opportunities', 'vol_organizations']);

        $userId = $this->createUser('rshift-notime');
        $oppId = $this->createOpportunity($userId);

        $result = $this->service->createPattern($oppId, $userId, [
            'frequency' => 'weekly',
        ]);

        $this->assertNull($result);
        $errors = $this->service->getErrors();
        $this->assertNotEmpty($errors);
        $this->assertStringContainsString('time', $errors[0]);
    }

    public function testCreatePatternSucceeds(): void
    {
        $this->requireTables(['recurring_shift_patterns', 'vol_opportunities', 'vol_organizations']);

        $userId = $this->createUser('rshift-create');
        $oppId = $this->createOpportunity($userId);

        $patternId = $this->service->createPattern($oppId, $userId, [
            'title' => 'Morning Shift',
            'frequency' => 'weekly',
            'days_of_week' => [1, 3, 5],
            'start_time' => '09:00:00',
            'end_time' => '12:00:00',
            'capacity' => 3,
            'start_date' => date('Y-m-d'),
        ]);

        $this->assertNotNull($patternId);
        $this->assertIsInt($patternId);
        $this->assertGreaterThan(0, $patternId);
    }

    // ==========================================
    // getPattern / getPatternsForOpportunity
    // ==========================================

    public function testGetPatternReturnsNullForNonexistent(): void
    {
        $this->requireTables(['recurring_shift_patterns']);

        $result = $this->service->getPattern(999999);
        $this->assertNull($result);
    }

    public function testGetPatternReturnsCreatedPattern(): void
    {
        $this->requireTables(['recurring_shift_patterns', 'vol_opportunities', 'vol_organizations', 'users']);

        $userId = $this->createUser('rshift-get');
        $oppId = $this->createOpportunity($userId);

        $patternId = $this->service->createPattern($oppId, $userId, [
            'title' => 'Afternoon Shift',
            'frequency' => 'daily',
            'start_time' => '14:00:00',
            'end_time' => '17:00:00',
            'capacity' => 2,
        ]);
        $this->assertNotNull($patternId);

        $pattern = $this->service->getPattern($patternId);

        $this->assertNotNull($pattern);
        $this->assertSame('Afternoon Shift', $pattern['title']);
        $this->assertSame('daily', $pattern['frequency']);
        $this->assertSame(2, $pattern['capacity']);
        $this->assertTrue($pattern['is_active']);
    }

    public function testGetPatternsForOpportunityReturnsList(): void
    {
        $this->requireTables(['recurring_shift_patterns', 'vol_opportunities', 'vol_organizations', 'users']);

        $userId = $this->createUser('rshift-list');
        $oppId = $this->createOpportunity($userId);

        $this->service->createPattern($oppId, $userId, [
            'title' => 'Pattern A',
            'frequency' => 'weekly',
            'start_time' => '09:00:00',
            'end_time' => '12:00:00',
        ]);

        $patterns = $this->service->getPatternsForOpportunity($oppId);

        $this->assertIsArray($patterns);
        $this->assertGreaterThanOrEqual(1, count($patterns));
    }

    // ==========================================
    // updatePattern
    // ==========================================

    public function testUpdatePatternReturnsFalseForNonexistent(): void
    {
        $this->requireTables(['recurring_shift_patterns']);

        $userId = $this->createUser('rshift-upd-bad');
        $result = $this->service->updatePattern(999999, ['title' => 'New'], $userId);

        $this->assertFalse($result);
    }

    public function testUpdatePatternChangesFields(): void
    {
        $this->requireTables(['recurring_shift_patterns', 'vol_opportunities', 'vol_organizations', 'users']);

        $userId = $this->createUser('rshift-upd');
        $oppId = $this->createOpportunity($userId);

        $patternId = $this->service->createPattern($oppId, $userId, [
            'title' => 'Old Title',
            'frequency' => 'weekly',
            'start_time' => '09:00:00',
            'end_time' => '12:00:00',
        ]);
        $this->assertNotNull($patternId);

        $result = $this->service->updatePattern($patternId, ['title' => 'New Title', 'capacity' => 5], $userId);

        $this->assertTrue($result);

        $updated = $this->service->getPattern($patternId);
        $this->assertSame('New Title', $updated['title']);
        $this->assertSame(5, $updated['capacity']);
    }

    public function testUpdatePatternRejectsInvalidFrequency(): void
    {
        $this->requireTables(['recurring_shift_patterns', 'vol_opportunities', 'vol_organizations', 'users']);

        $userId = $this->createUser('rshift-upd-freq');
        $oppId = $this->createOpportunity($userId);

        $patternId = $this->service->createPattern($oppId, $userId, [
            'frequency' => 'weekly',
            'start_time' => '09:00:00',
            'end_time' => '12:00:00',
        ]);
        $this->assertNotNull($patternId);

        $result = $this->service->updatePattern($patternId, ['frequency' => 'never'], $userId);

        $this->assertFalse($result);
    }

    // ==========================================
    // deactivatePattern
    // ==========================================

    public function testDeactivatePatternSucceeds(): void
    {
        $this->requireTables(['recurring_shift_patterns', 'vol_opportunities', 'vol_organizations', 'users']);

        $userId = $this->createUser('rshift-deact');
        $oppId = $this->createOpportunity($userId);

        $patternId = $this->service->createPattern($oppId, $userId, [
            'frequency' => 'weekly',
            'start_time' => '09:00:00',
            'end_time' => '12:00:00',
        ]);
        $this->assertNotNull($patternId);

        $result = $this->service->deactivatePattern($patternId, $userId);

        $this->assertTrue($result);

        $pattern = $this->service->getPattern($patternId);
        $this->assertFalse($pattern['is_active']);
    }

    public function testDeactivatePatternReturnsFalseForNonexistent(): void
    {
        $this->requireTables(['recurring_shift_patterns']);

        $userId = $this->createUser('rshift-deact-bad');
        $result = $this->service->deactivatePattern(999999, $userId);

        $this->assertFalse($result);
    }

    // ==========================================
    // generateOccurrences
    // ==========================================

    public function testGenerateOccurrencesReturnsZeroForInactivePattern(): void
    {
        $this->requireTables(['recurring_shift_patterns', 'vol_shifts']);

        $result = $this->service->generateOccurrences(999999, 7);

        $this->assertSame(0, $result);
    }

    public function testGenerateOccurrencesCreatesDailyShifts(): void
    {
        $this->requireTables(['recurring_shift_patterns', 'vol_shifts', 'vol_opportunities', 'vol_organizations', 'users']);

        $userId = $this->createUser('rshift-gen');
        $oppId = $this->createOpportunity($userId);

        $patternId = $this->service->createPattern($oppId, $userId, [
            'frequency' => 'daily',
            'start_time' => '10:00:00',
            'end_time' => '13:00:00',
            'capacity' => 2,
            'start_date' => date('Y-m-d'),
        ]);
        $this->assertNotNull($patternId);

        $generated = $this->service->generateOccurrences($patternId, 7);

        $this->assertGreaterThan(0, $generated);
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
