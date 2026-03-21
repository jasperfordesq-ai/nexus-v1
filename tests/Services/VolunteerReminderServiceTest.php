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
use App\Services\VolunteerReminderService;

/**
 * VolunteerReminderService Tests
 *
 * Tests reminder sending, settings retrieval, and settings update.
 */
class VolunteerReminderServiceTest extends DatabaseTestCase
{
    private const TENANT_ID = 2;

    protected function setUp(): void
    {
        parent::setUp();
        TenantContext::setById(self::TENANT_ID);
    }

    // ==========================================
    // sendReminders
    // ==========================================

    public function testSendRemindersReturnsZeroWhenNoSettingExists(): void
    {
        $this->requireTables(['vol_reminder_settings', 'vol_shifts', 'vol_shift_signups', 'vol_reminders_sent']);

        // Ensure no pre_shift setting is enabled
        Database::query(
            "DELETE FROM vol_reminder_settings WHERE tenant_id = ? AND reminder_type = 'pre_shift'",
            [self::TENANT_ID]
        );

        $result = VolunteerReminderService::sendReminders(self::TENANT_ID, 999999);

        $this->assertSame(0, $result);
    }

    public function testSendRemindersReturnsZeroWhenNoShiftsInWindow(): void
    {
        $this->requireTables(['vol_reminder_settings', 'vol_shifts', 'vol_shift_signups', 'vol_reminders_sent', 'vol_opportunities', 'vol_organizations']);

        // Create a pre_shift setting
        Database::query(
            "DELETE FROM vol_reminder_settings WHERE tenant_id = ? AND reminder_type = 'pre_shift'",
            [self::TENANT_ID]
        );
        Database::query(
            "INSERT INTO vol_reminder_settings (tenant_id, reminder_type, enabled, hours_before, push_enabled, email_enabled, sms_enabled, created_at, updated_at)
             VALUES (?, 'pre_shift', 1, 24, 1, 1, 0, NOW(), NOW())",
            [self::TENANT_ID]
        );

        $ownerId = $this->createUser('rem-owner');
        $oppId = $this->createOpportunity($ownerId);

        $result = VolunteerReminderService::sendReminders(self::TENANT_ID, $oppId);

        $this->assertSame(0, $result);
    }

    public function testSendRemindersSendsToConfirmedVolunteers(): void
    {
        $this->requireTables(['vol_reminder_settings', 'vol_shifts', 'vol_shift_signups', 'vol_reminders_sent', 'vol_opportunities', 'vol_organizations']);

        // Create a pre_shift setting with 48-hour window
        Database::query(
            "DELETE FROM vol_reminder_settings WHERE tenant_id = ? AND reminder_type = 'pre_shift'",
            [self::TENANT_ID]
        );
        Database::query(
            "INSERT INTO vol_reminder_settings (tenant_id, reminder_type, enabled, hours_before, push_enabled, email_enabled, sms_enabled, created_at, updated_at)
             VALUES (?, 'pre_shift', 1, 48, 1, 1, 0, NOW(), NOW())",
            [self::TENANT_ID]
        );

        $ownerId = $this->createUser('rem-send-owner');
        $volunteerId = $this->createUser('rem-volunteer');
        $oppId = $this->createOpportunity($ownerId);

        // Create a shift starting in 12 hours (within 48-hour window)
        Database::query(
            'INSERT INTO vol_shifts (tenant_id, opportunity_id, start_time, end_time, capacity, created_at) VALUES (?, ?, DATE_ADD(NOW(), INTERVAL 12 HOUR), DATE_ADD(NOW(), INTERVAL 14 HOUR), 5, NOW())',
            [self::TENANT_ID, $oppId]
        );
        $shiftId = (int) Database::getInstance()->lastInsertId();

        // Confirm the volunteer for this shift
        Database::query(
            "INSERT INTO vol_shift_signups (tenant_id, shift_id, user_id, status, created_at) VALUES (?, ?, ?, 'confirmed', NOW())",
            [self::TENANT_ID, $shiftId, $volunteerId]
        );

        $result = VolunteerReminderService::sendReminders(self::TENANT_ID, $oppId);

        $this->assertGreaterThanOrEqual(1, $result);
    }

    public function testSendRemindersDoesNotDuplicate(): void
    {
        $this->requireTables(['vol_reminder_settings', 'vol_shifts', 'vol_shift_signups', 'vol_reminders_sent', 'vol_opportunities', 'vol_organizations']);

        Database::query(
            "DELETE FROM vol_reminder_settings WHERE tenant_id = ? AND reminder_type = 'pre_shift'",
            [self::TENANT_ID]
        );
        Database::query(
            "INSERT INTO vol_reminder_settings (tenant_id, reminder_type, enabled, hours_before, push_enabled, email_enabled, sms_enabled, created_at, updated_at)
             VALUES (?, 'pre_shift', 1, 48, 1, 0, 0, NOW(), NOW())",
            [self::TENANT_ID]
        );

        $ownerId = $this->createUser('rem-nodup-owner');
        $volunteerId = $this->createUser('rem-nodup-vol');
        $oppId = $this->createOpportunity($ownerId);

        Database::query(
            'INSERT INTO vol_shifts (tenant_id, opportunity_id, start_time, end_time, capacity, created_at) VALUES (?, ?, DATE_ADD(NOW(), INTERVAL 6 HOUR), DATE_ADD(NOW(), INTERVAL 8 HOUR), 5, NOW())',
            [self::TENANT_ID, $oppId]
        );
        $shiftId = (int) Database::getInstance()->lastInsertId();

        Database::query(
            "INSERT INTO vol_shift_signups (tenant_id, shift_id, user_id, status, created_at) VALUES (?, ?, ?, 'confirmed', NOW())",
            [self::TENANT_ID, $shiftId, $volunteerId]
        );

        $first = VolunteerReminderService::sendReminders(self::TENANT_ID, $oppId);
        $second = VolunteerReminderService::sendReminders(self::TENANT_ID, $oppId);

        $this->assertGreaterThanOrEqual(1, $first);
        $this->assertSame(0, $second);
    }

    // ==========================================
    // getSettings
    // ==========================================

    public function testGetSettingsReturnsAllFiveReminderTypes(): void
    {
        $this->requireTables(['vol_reminder_settings']);

        $settings = VolunteerReminderService::getSettings();

        $this->assertIsArray($settings);
        $this->assertCount(5, $settings);

        $types = array_column($settings, 'reminder_type');
        $this->assertContains('pre_shift', $types);
        $this->assertContains('post_shift_feedback', $types);
        $this->assertContains('lapsed_volunteer', $types);
        $this->assertContains('credential_expiry', $types);
        $this->assertContains('training_expiry', $types);
    }

    public function testGetSettingsReturnsDefaultsWhenNoRowsExist(): void
    {
        $this->requireTables(['vol_reminder_settings']);

        Database::query(
            'DELETE FROM vol_reminder_settings WHERE tenant_id = ?',
            [self::TENANT_ID]
        );

        $settings = VolunteerReminderService::getSettings();

        $this->assertCount(5, $settings);
        foreach ($settings as $setting) {
            $this->assertNull($setting['id']);
            $this->assertTrue($setting['enabled']);
        }
    }

    // ==========================================
    // updateSetting
    // ==========================================

    public function testUpdateSettingReturnsFalseForInvalidType(): void
    {
        $result = VolunteerReminderService::updateSetting('invalid_type', []);

        $this->assertFalse($result);
    }

    public function testUpdateSettingCreatesNewRow(): void
    {
        $this->requireTables(['vol_reminder_settings']);

        Database::query(
            "DELETE FROM vol_reminder_settings WHERE tenant_id = ? AND reminder_type = 'lapsed_volunteer'",
            [self::TENANT_ID]
        );

        $result = VolunteerReminderService::updateSetting('lapsed_volunteer', [
            'enabled' => true,
            'days_inactive' => 45,
            'push_enabled' => true,
            'email_enabled' => false,
        ]);

        $this->assertTrue($result);

        $row = Database::query(
            "SELECT * FROM vol_reminder_settings WHERE tenant_id = ? AND reminder_type = 'lapsed_volunteer'",
            [self::TENANT_ID]
        )->fetch(\PDO::FETCH_ASSOC);

        $this->assertNotFalse($row);
        $this->assertEquals(45, (int) $row['days_inactive']);
    }

    public function testUpdateSettingUpdatesExistingRow(): void
    {
        $this->requireTables(['vol_reminder_settings']);

        VolunteerReminderService::updateSetting('pre_shift', ['hours_before' => 24]);

        $result = VolunteerReminderService::updateSetting('pre_shift', ['hours_before' => 12]);

        $this->assertTrue($result);

        $row = Database::query(
            "SELECT hours_before FROM vol_reminder_settings WHERE tenant_id = ? AND reminder_type = 'pre_shift'",
            [self::TENANT_ID]
        )->fetch(\PDO::FETCH_ASSOC);

        $this->assertNotFalse($row);
        $this->assertEquals(12, (int) $row['hours_before']);
    }

    public function testUpdateSettingAcceptsAllValidTypes(): void
    {
        $this->requireTables(['vol_reminder_settings']);

        $validTypes = ['pre_shift', 'post_shift_feedback', 'lapsed_volunteer', 'credential_expiry', 'training_expiry'];
        foreach ($validTypes as $type) {
            $result = VolunteerReminderService::updateSetting($type, ['enabled' => true]);
            $this->assertTrue($result, "updateSetting should accept type: {$type}");
        }
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
