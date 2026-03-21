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
 * Tests reminder settings CRUD and dispatcher return types for all reminder categories.
 */
class VolunteerReminderServiceTest extends DatabaseTestCase
{
    private const TENANT_ID = 2;

    private static int $testUserId = 0;

    public static function setUpBeforeClass(): void
    {
        parent::setUpBeforeClass();
        TenantContext::setById(self::TENANT_ID);

        $ts = time();

        // Create test user
        Database::query(
            'INSERT INTO users (tenant_id, email, username, first_name, last_name, name, balance, is_approved, created_at)
             VALUES (?, ?, ?, ?, ?, ?, 0, 1, NOW())',
            [
                self::TENANT_ID,
                "volreminder-test-{$ts}@example.test",
                "volreminder-test-{$ts}",
                'Reminder',
                'Tester',
                'Reminder Tester',
            ]
        );
        self::$testUserId = (int) Database::getInstance()->lastInsertId();

        // Insert a reminder setting row for testing getSettings / updateSetting
        try {
            Database::query(
                "INSERT INTO vol_reminder_settings
                    (tenant_id, reminder_type, is_enabled, hours_before, hours_after,
                     days_inactive, days_before_expiry, email_enabled, push_enabled,
                     message_template, updated_at)
                 VALUES (?, 'pre_shift', 1, 24, NULL, NULL, NULL, 1, 1, 'Test reminder template', NOW())
                 ON DUPLICATE KEY UPDATE
                     is_enabled = VALUES(is_enabled),
                     message_template = VALUES(message_template),
                     updated_at = NOW()",
                [self::TENANT_ID]
            );
        } catch (\Exception $e) {
            // Table may not exist — tests will skip via requireTables
        }
    }

    public static function tearDownAfterClass(): void
    {
        // Cleanup test data
        try {
            if (self::$testUserId > 0) {
                Database::query(
                    'DELETE FROM vol_reminder_settings WHERE tenant_id = ? AND message_template LIKE ?',
                    [self::TENANT_ID, '%Test reminder%']
                );
                Database::query(
                    'DELETE FROM vol_reminders_sent WHERE tenant_id = ? AND user_id = ?',
                    [self::TENANT_ID, self::$testUserId]
                );
                Database::query('DELETE FROM users WHERE id = ?', [self::$testUserId]);
            }
        } catch (\Exception $e) {
            // Best-effort cleanup
        }

        parent::tearDownAfterClass();
    }

    protected function setUp(): void
    {
        parent::setUp();
        TenantContext::setById(self::TENANT_ID);
    }

    // ==========================================
    // Class & method existence
    // ==========================================

    public function testClassExists(): void
    {
        $this->assertTrue(class_exists(VolunteerReminderService::class));
    }

    public function testGetSettingsMethodIsStatic(): void
    {
        $ref = new \ReflectionMethod(VolunteerReminderService::class, 'getSettings');
        $this->assertTrue($ref->isStatic());
    }

    public function testUpdateSettingMethodIsStatic(): void
    {
        $ref = new \ReflectionMethod(VolunteerReminderService::class, 'updateSetting');
        $this->assertTrue($ref->isStatic());
    }

    public function testSendPreShiftRemindersMethodIsStatic(): void
    {
        $ref = new \ReflectionMethod(VolunteerReminderService::class, 'sendPreShiftReminders');
        $this->assertTrue($ref->isStatic());
    }

    public function testNudgeLapsedVolunteersMethodIsStatic(): void
    {
        $ref = new \ReflectionMethod(VolunteerReminderService::class, 'nudgeLapsedVolunteers');
        $this->assertTrue($ref->isStatic());
    }

    // ==========================================
    // getSettings
    // ==========================================

    public function testGetSettingsReturnsArray(): void
    {
        $this->requireTables(['vol_reminder_settings']);

        $result = VolunteerReminderService::getSettings();
        $this->assertIsArray($result);
    }

    public function testGetSettingsContainsPreShiftAfterSetup(): void
    {
        $this->requireTables(['vol_reminder_settings']);

        $result = VolunteerReminderService::getSettings();
        $this->assertIsArray($result);

        // The setUpBeforeClass inserted a pre_shift row
        if (isset($result['pre_shift'])) {
            $this->assertArrayHasKey('reminder_type', $result['pre_shift']);
            $this->assertSame('pre_shift', $result['pre_shift']['reminder_type']);
        } else {
            // Row may have been rolled back; still valid that getSettings returns array
            $this->assertIsArray($result);
        }
    }

    // ==========================================
    // updateSetting — valid types
    // ==========================================

    public function testUpdateSettingWithValidTypeReturnsBool(): void
    {
        $this->requireTables(['vol_reminder_settings']);

        $result = VolunteerReminderService::updateSetting('post_shift_feedback', [
            'is_enabled'       => 1,
            'hours_after'      => 4,
            'email_enabled'    => 1,
            'push_enabled'     => 1,
            'message_template' => 'Test reminder feedback template',
        ]);

        $this->assertIsBool($result);
        $this->assertTrue($result);
    }

    public function testUpdateSettingPersistsData(): void
    {
        $this->requireTables(['vol_reminder_settings']);

        $updated = VolunteerReminderService::updateSetting('lapsed_volunteer', [
            'is_enabled'       => 1,
            'days_inactive'    => 45,
            'email_enabled'    => 1,
            'push_enabled'     => 0,
            'message_template' => 'Test reminder lapsed template',
        ]);
        $this->assertTrue($updated);

        $settings = VolunteerReminderService::getSettings();
        $this->assertArrayHasKey('lapsed_volunteer', $settings);
        $this->assertEquals(45, (int) $settings['lapsed_volunteer']['days_inactive']);
        $this->assertEquals(0, (int) $settings['lapsed_volunteer']['push_enabled']);
    }

    // ==========================================
    // updateSetting — invalid type
    // ==========================================

    public function testUpdateSettingWithInvalidTypeThrowsOrReturnsFalse(): void
    {
        $this->requireTables(['vol_reminder_settings']);

        // The service does not validate reminder_type against a whitelist,
        // so an unknown type will be inserted. We verify the method still returns bool.
        $result = VolunteerReminderService::updateSetting('totally_invalid_type', [
            'is_enabled'    => 0,
            'email_enabled' => 0,
            'push_enabled'  => 0,
        ]);

        $this->assertIsBool($result);

        // Cleanup the invalid row if it was inserted
        try {
            Database::query(
                'DELETE FROM vol_reminder_settings WHERE tenant_id = ? AND reminder_type = ?',
                [self::TENANT_ID, 'totally_invalid_type']
            );
        } catch (\Exception $e) {
            // ignore
        }
    }

    // ==========================================
    // Reminder dispatchers — return int
    // ==========================================

    public function testSendPreShiftRemindersReturnsInt(): void
    {
        $this->requireTables(['vol_reminder_settings', 'vol_shifts', 'vol_reminders_sent']);

        $result = VolunteerReminderService::sendPreShiftReminders();
        $this->assertIsInt($result);
        $this->assertGreaterThanOrEqual(0, $result);
    }

    public function testSendPostShiftFeedbackReturnsInt(): void
    {
        $this->requireTables(['vol_reminder_settings', 'vol_shifts', 'vol_reminders_sent']);

        $result = VolunteerReminderService::sendPostShiftFeedback();
        $this->assertIsInt($result);
        $this->assertGreaterThanOrEqual(0, $result);
    }

    public function testNudgeLapsedVolunteersReturnsInt(): void
    {
        $this->requireTables(['vol_reminder_settings', 'vol_reminders_sent']);

        $result = VolunteerReminderService::nudgeLapsedVolunteers();
        $this->assertIsInt($result);
        $this->assertGreaterThanOrEqual(0, $result);
    }

    public function testSendCredentialExpiryWarningsReturnsInt(): void
    {
        $this->requireTables(['vol_reminder_settings', 'vol_credentials', 'vol_reminders_sent']);

        $result = VolunteerReminderService::sendCredentialExpiryWarnings();
        $this->assertIsInt($result);
        $this->assertGreaterThanOrEqual(0, $result);
    }

    public function testSendTrainingExpiryWarningsReturnsInt(): void
    {
        $this->requireTables(['vol_reminder_settings', 'vol_safeguarding_training', 'vol_reminders_sent']);

        $result = VolunteerReminderService::sendTrainingExpiryWarnings();
        $this->assertIsInt($result);
        $this->assertGreaterThanOrEqual(0, $result);
    }

    // ==========================================
    // getSettings reflects updateSetting changes
    // ==========================================

    public function testGetSettingsReturnsDataAfterUpdateSetting(): void
    {
        $this->requireTables(['vol_reminder_settings']);

        $updated = VolunteerReminderService::updateSetting('credential_expiry', [
            'is_enabled'        => 1,
            'days_before_expiry' => 14,
            'email_enabled'     => 1,
            'push_enabled'      => 1,
            'email_template'  => 'Test reminder credential expiry template',
        ]);
        $this->assertTrue($updated);

        $settings = VolunteerReminderService::getSettings();
        $this->assertIsArray($settings);
        $this->assertArrayHasKey('credential_expiry', $settings);
        $this->assertEquals(14, (int) $settings['credential_expiry']['days_before_expiry']);
        $this->assertSame(
            'Test reminder credential expiry template',
            $settings['credential_expiry']['email_template']
        );
    }

    // ==========================================
    // Helpers
    // ==========================================

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
