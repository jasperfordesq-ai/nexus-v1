<?php
// Copyright © 2024–2026 Jasper Ford
// SPDX-License-Identifier: AGPL-3.0-or-later
// Author: Jasper Ford
// See NOTICE file for attribution and acknowledgements.

namespace Nexus\Services;

use Nexus\Core\Database;
use Nexus\Core\TenantContext;
use Nexus\Services\NotificationDispatcher;
use Nexus\Services\WebhookDispatchService;

/**
 * VolunteerReminderService - Automated volunteer reminders and nudges
 *
 * Handles scheduling and sending of automated reminders for the volunteering module:
 * - Pre-shift reminders (upcoming shifts)
 * - Post-shift feedback requests
 * - Lapsed volunteer nudges
 * - Credential expiry warnings
 * - Training expiry warnings
 *
 * Tables:
 *   vol_reminder_settings — per-tenant reminder configuration
 *   vol_reminders_sent — deduplication log of sent reminders
 */
class VolunteerReminderService
{
    // =========================================================================
    // SETTINGS
    // =========================================================================

    /**
     * Get all reminder settings for the current tenant
     *
     * @return array Associative array of reminder settings keyed by reminder_type
     */
    public static function getSettings(): array
    {
        $tenantId = TenantContext::getId();

        try {
            $rows = Database::query(
                "SELECT * FROM vol_reminder_settings WHERE tenant_id = ? ORDER BY reminder_type",
                [$tenantId]
            )->fetchAll(\PDO::FETCH_ASSOC);

            $settings = [];
            foreach ($rows as $row) {
                $settings[$row['reminder_type']] = $row;
            }
            return $settings;
        } catch (\Exception $e) {
            error_log("VolunteerReminderService::getSettings error: " . $e->getMessage());
            return [];
        }
    }

    /**
     * Upsert a reminder setting
     *
     * @param string $reminderType e.g. 'pre_shift', 'post_shift_feedback', 'lapsed_volunteer', 'credential_expiry', 'training_expiry'
     * @param array $data [is_enabled, hours_before, hours_after, days_inactive, days_before_expiry, email_enabled, push_enabled, message_template]
     * @return bool
     */
    public static function updateSetting(string $reminderType, array $data): bool
    {
        $tenantId = TenantContext::getId();

        try {
            $db = Database::getConnection();
            $stmt = $db->prepare(
                "INSERT INTO vol_reminder_settings
                    (tenant_id, reminder_type, enabled, hours_before, hours_after,
                     days_inactive, days_before_expiry, email_enabled, push_enabled,
                     email_template, updated_at)
                 VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, NOW())
                 ON DUPLICATE KEY UPDATE
                     enabled = VALUES(enabled),
                     hours_before = VALUES(hours_before),
                     hours_after = VALUES(hours_after),
                     days_inactive = VALUES(days_inactive),
                     days_before_expiry = VALUES(days_before_expiry),
                     email_enabled = VALUES(email_enabled),
                     push_enabled = VALUES(push_enabled),
                     email_template = VALUES(email_template),
                     updated_at = NOW()"
            );
            $stmt->execute([
                $tenantId,
                $reminderType,
                $data['enabled'] ?? ($data['is_enabled'] ?? 1),
                $data['hours_before'] ?? null,
                $data['hours_after'] ?? null,
                $data['days_inactive'] ?? null,
                $data['days_before_expiry'] ?? null,
                $data['email_enabled'] ?? 1,
                $data['push_enabled'] ?? 1,
                $data['email_template'] ?? ($data['message_template'] ?? null),
            ]);
            return true;
        } catch (\Exception $e) {
            error_log("VolunteerReminderService::updateSetting error: " . $e->getMessage());
            return false;
        }
    }

    // =========================================================================
    // REMINDER DISPATCHERS
    // =========================================================================

    /**
     * Send pre-shift reminders for upcoming shifts
     *
     * Finds shifts starting within configured hours_before window,
     * checks vol_reminders_sent to avoid duplicates, and sends notifications.
     *
     * @return int Number of reminders sent
     */
    public static function sendPreShiftReminders(): int
    {
        $tenantId = TenantContext::getId();
        $settings = self::getSettings();
        $config = $settings['pre_shift'] ?? null;

        if (!$config || !$config['enabled']) {
            return 0;
        }

        $hoursBefore = (int)($config['hours_before'] ?? 24);
        $count = 0;

        try {
            // Find shifts starting within the configured window
            $shifts = Database::query(
                "SELECT s.*, o.title as opportunity_title, org.name as org_name
                 FROM vol_shifts s
                 JOIN vol_opportunities o ON s.opportunity_id = o.id
                 JOIN vol_organizations org ON o.organization_id = org.id
                 WHERE org.tenant_id = ?
                   AND s.start_time BETWEEN NOW() AND DATE_ADD(NOW(), INTERVAL ? HOUR)",
                [$tenantId, $hoursBefore]
            )->fetchAll(\PDO::FETCH_ASSOC);

            foreach ($shifts as $shift) {
                // Get signed-up volunteers for this shift
                $signups = Database::query(
                    "SELECT su.user_id, u.name as user_name
                     FROM vol_shift_signups su
                     JOIN users u ON su.user_id = u.id AND u.tenant_id = ?
                     WHERE su.shift_id = ? AND su.status = 'confirmed'",
                    [$tenantId, $shift['id']]
                )->fetchAll(\PDO::FETCH_ASSOC);

                foreach ($signups as $signup) {
                    $userId = (int)$signup['user_id'];

                    if (self::alreadySent($userId, 'pre_shift', (int)$shift['id'], 'email', $hoursBefore)) {
                        continue;
                    }

                    $message = $config['email_template']
                        ?? "Reminder: You have an upcoming shift for \"{$shift['opportunity_title']}\" starting at {$shift['start_time']}.";

                    NotificationDispatcher::dispatch(
                        $userId,
                        'global',
                        null,
                        'volunteer_reminder',
                        $message,
                        '/volunteering/shifts/' . $shift['id'],
                        '<p>' . htmlspecialchars($message) . '</p>'
                    );

                    self::recordSent($userId, 'pre_shift', (int)$shift['id'], 'email');
                    $count++;
                }
            }

            return $count;
        } catch (\Exception $e) {
            error_log("VolunteerReminderService::sendPreShiftReminders error: " . $e->getMessage());
            return $count;
        }
    }

    /**
     * Send post-shift feedback requests
     *
     * Finds completed shifts within configured hours_after window
     * and sends feedback request notifications.
     *
     * @return int Number of feedback requests sent
     */
    public static function sendPostShiftFeedback(): int
    {
        $tenantId = TenantContext::getId();
        $settings = self::getSettings();
        $config = $settings['post_shift_feedback'] ?? null;

        if (!$config || !$config['enabled']) {
            return 0;
        }

        $hoursAfter = (int)($config['hours_after'] ?? 4);
        $count = 0;

        try {
            // Find shifts that ended within the configured window
            $shifts = Database::query(
                "SELECT s.*, o.title as opportunity_title, org.name as org_name
                 FROM vol_shifts s
                 JOIN vol_opportunities o ON s.opportunity_id = o.id
                 JOIN vol_organizations org ON o.organization_id = org.id
                 WHERE org.tenant_id = ?
                   AND s.end_time BETWEEN DATE_SUB(NOW(), INTERVAL ? HOUR) AND NOW()",
                [$tenantId, $hoursAfter]
            )->fetchAll(\PDO::FETCH_ASSOC);

            foreach ($shifts as $shift) {
                $signups = Database::query(
                    "SELECT su.user_id
                     FROM vol_shift_signups su
                     JOIN users u ON su.user_id = u.id AND u.tenant_id = ?
                     WHERE su.shift_id = ? AND su.status = 'confirmed'",
                    [$tenantId, $shift['id']]
                )->fetchAll(\PDO::FETCH_ASSOC);

                foreach ($signups as $signup) {
                    $userId = (int)$signup['user_id'];

                    if (self::alreadySent($userId, 'post_shift_feedback', (int)$shift['id'], 'email')) {
                        continue;
                    }

                    $message = $config['email_template']
                        ?? "How was your shift for \"{$shift['opportunity_title']}\"? Please share your feedback.";

                    NotificationDispatcher::dispatch(
                        $userId,
                        'global',
                        null,
                        'volunteer_feedback',
                        $message,
                        '/volunteering/shifts/' . $shift['id'] . '/feedback',
                        '<p>' . htmlspecialchars($message) . '</p>'
                    );

                    self::recordSent($userId, 'post_shift_feedback', (int)$shift['id'], 'email');
                    $count++;
                }
            }

            return $count;
        } catch (\Exception $e) {
            error_log("VolunteerReminderService::sendPostShiftFeedback error: " . $e->getMessage());
            return $count;
        }
    }

    /**
     * Nudge lapsed volunteers who have been inactive for N days
     *
     * @return int Number of nudges sent
     */
    public static function nudgeLapsedVolunteers(): int
    {
        $tenantId = TenantContext::getId();
        $settings = self::getSettings();
        $config = $settings['lapsed_volunteer'] ?? null;

        if (!$config || !$config['enabled']) {
            return 0;
        }

        $daysInactive = (int)($config['days_inactive'] ?? 30);
        $count = 0;

        try {
            // Find volunteers with no recent activity in vol_logs or vol_shift_signups
            $lapsedUsers = Database::query(
                "SELECT DISTINCT u.id as user_id, u.name
                 FROM users u
                 WHERE u.tenant_id = ?
                   AND u.status = 'active'
                   AND u.id IN (
                       SELECT DISTINCT su.user_id FROM vol_shift_signups su
                       JOIN users su_u ON su.user_id = su_u.id AND su_u.tenant_id = ?
                       UNION
                       SELECT DISTINCT vl.user_id FROM vol_logs vl
                       JOIN users vl_u ON vl.user_id = vl_u.id AND vl_u.tenant_id = ?
                   )
                   AND u.id NOT IN (
                       SELECT DISTINCT vl2.user_id FROM vol_logs vl2
                       JOIN users vl2_u ON vl2.user_id = vl2_u.id AND vl2_u.tenant_id = ?
                       WHERE vl2.created_at > DATE_SUB(NOW(), INTERVAL ? DAY)
                   )
                   AND u.id NOT IN (
                       SELECT DISTINCT su2.user_id FROM vol_shift_signups su2
                       JOIN users su2_u ON su2.user_id = su2_u.id AND su2_u.tenant_id = ?
                       WHERE su2.created_at > DATE_SUB(NOW(), INTERVAL ? DAY)
                   )",
                [$tenantId, $tenantId, $tenantId, $tenantId, $daysInactive, $tenantId, $daysInactive]
            )->fetchAll(\PDO::FETCH_ASSOC);

            foreach ($lapsedUsers as $user) {
                $userId = (int)$user['user_id'];

                if (self::alreadySent($userId, 'lapsed_nudge', null, 'email', $daysInactive * 24)) {
                    continue;
                }

                $message = $config['email_template']
                    ?? "We miss you! There are new volunteering opportunities waiting for you.";

                NotificationDispatcher::dispatch(
                    $userId,
                    'global',
                    null,
                    'volunteer_nudge',
                    $message,
                    '/volunteering',
                    '<p>' . htmlspecialchars($message) . '</p>'
                );

                self::recordSent($userId, 'lapsed_nudge', null, 'email');
                $count++;
            }

            return $count;
        } catch (\Exception $e) {
            error_log("VolunteerReminderService::nudgeLapsedVolunteers error: " . $e->getMessage());
            return $count;
        }
    }

    /**
     * Send credential expiry warnings for vol_credentials expiring within N days
     *
     * @return int Number of warnings sent
     */
    public static function sendCredentialExpiryWarnings(): int
    {
        $tenantId = TenantContext::getId();
        $settings = self::getSettings();
        $config = $settings['credential_expiry'] ?? null;

        if (!$config || !$config['enabled']) {
            return 0;
        }

        $daysBefore = (int)($config['days_before_expiry'] ?? 30);
        $count = 0;

        try {
            $expiring = Database::query(
                "SELECT vc.*, u.name as user_name
                 FROM vol_credentials vc
                 JOIN users u ON vc.user_id = u.id
                 WHERE u.tenant_id = ?
                   AND vc.tenant_id = ?
                   AND vc.expires_at IS NOT NULL
                   AND vc.expires_at BETWEEN NOW() AND DATE_ADD(NOW(), INTERVAL ? DAY)
                   AND vc.status = 'verified'",
                [$tenantId, $tenantId, $daysBefore]
            )->fetchAll(\PDO::FETCH_ASSOC);

            foreach ($expiring as $cred) {
                $userId = (int)$cred['user_id'];

                if (self::alreadySent($userId, 'credential_expiry', (int)$cred['id'], 'email', $daysBefore * 24)) {
                    continue;
                }

                $message = $config['email_template']
                    ?? "Your credential \"{$cred['credential_type']}\" is expiring on {$cred['expires_at']}. Please renew it.";

                NotificationDispatcher::dispatch(
                    $userId,
                    'global',
                    null,
                    'credential_expiry',
                    $message,
                    '/volunteering/credentials',
                    '<p>' . htmlspecialchars($message) . '</p>'
                );

                // Webhook: credential.expiring
                try {
                    WebhookDispatchService::dispatch('credential.expiring', [
                        'user_id' => $userId,
                        'user_name' => $cred['user_name'] ?? '',
                        'credential_id' => (int)$cred['id'],
                        'credential_type' => $cred['credential_type'] ?? '',
                        'expires_at' => $cred['expires_at'],
                    ]);
                } catch (\Throwable $e) {
                    error_log("Webhook dispatch failed for credential.expiring: " . $e->getMessage());
                }

                self::recordSent($userId, 'credential_expiry', (int)$cred['id'], 'email');
                $count++;
            }

            return $count;
        } catch (\Exception $e) {
            error_log("VolunteerReminderService::sendCredentialExpiryWarnings error: " . $e->getMessage());
            return $count;
        }
    }

    /**
     * Send training expiry warnings for vol_safeguarding_training expiring within N days
     *
     * @return int Number of warnings sent
     */
    public static function sendTrainingExpiryWarnings(): int
    {
        $tenantId = TenantContext::getId();
        $settings = self::getSettings();
        $config = $settings['training_expiry'] ?? null;

        if (!$config || !$config['enabled']) {
            return 0;
        }

        $daysBefore = (int)($config['days_before_expiry'] ?? 30);
        $count = 0;

        try {
            $expiring = Database::query(
                "SELECT st.*, u.name as user_name
                 FROM vol_safeguarding_training st
                 JOIN users u ON st.user_id = u.id
                 WHERE st.tenant_id = ?
                   AND u.tenant_id = ?
                   AND st.expires_at IS NOT NULL
                   AND st.expires_at BETWEEN NOW() AND DATE_ADD(NOW(), INTERVAL ? DAY)
                   AND st.status = 'verified'",
                [$tenantId, $tenantId, $daysBefore]
            )->fetchAll(\PDO::FETCH_ASSOC);

            foreach ($expiring as $training) {
                $userId = (int)$training['user_id'];

                if (self::alreadySent($userId, 'training_expiry', (int)$training['id'], 'email', $daysBefore * 24)) {
                    continue;
                }

                $message = $config['email_template']
                    ?? "Your training \"{$training['training_name']}\" is expiring on {$training['expires_at']}. Please renew it.";

                NotificationDispatcher::dispatch(
                    $userId,
                    'global',
                    null,
                    'training_expiry',
                    $message,
                    '/volunteering/training',
                    '<p>' . htmlspecialchars($message) . '</p>'
                );

                // Webhook: training.expired
                try {
                    WebhookDispatchService::dispatch('training.expired', [
                        'user_id' => $userId,
                        'user_name' => $training['user_name'] ?? '',
                        'training_id' => (int)$training['id'],
                        'training_name' => $training['training_name'] ?? '',
                        'training_type' => $training['training_type'] ?? '',
                        'expires_at' => $training['expires_at'],
                    ]);
                } catch (\Throwable $e) {
                    error_log("Webhook dispatch failed for training.expired: " . $e->getMessage());
                }

                self::recordSent($userId, 'training_expiry', (int)$training['id'], 'email');
                $count++;
            }

            return $count;
        } catch (\Exception $e) {
            error_log("VolunteerReminderService::sendTrainingExpiryWarnings error: " . $e->getMessage());
            return $count;
        }
    }

    // =========================================================================
    // DEDUPLICATION HELPERS
    // =========================================================================

    /**
     * Record that a reminder was sent (for deduplication)
     */
    private static function recordSent(int $userId, string $type, ?int $referenceId, string $channel): void
    {
        $tenantId = TenantContext::getId();

        try {
            Database::query(
                "INSERT INTO vol_reminders_sent
                    (tenant_id, user_id, reminder_type, reference_id, channel, sent_at)
                 VALUES (?, ?, ?, ?, ?, NOW())",
                [$tenantId, $userId, $type, $referenceId, $channel]
            );
        } catch (\Exception $e) {
            error_log("VolunteerReminderService::recordSent error: " . $e->getMessage());
        }
    }

    /**
     * Check if a reminder was already sent within a given time window
     *
     * @param int $userId
     * @param string $type Reminder type
     * @param int|null $referenceId Related entity ID (shift, credential, etc.)
     * @param string $channel Notification channel
     * @param int $withinHours Only consider reminders sent within this many hours (default 24)
     * @return bool True if already sent
     */
    private static function alreadySent(int $userId, string $type, ?int $referenceId, string $channel, int $withinHours = 24): bool
    {
        $tenantId = TenantContext::getId();

        try {
            $sql = "SELECT COUNT(*) FROM vol_reminders_sent
                    WHERE tenant_id = ? AND user_id = ? AND reminder_type = ? AND channel = ?
                      AND sent_at > DATE_SUB(NOW(), INTERVAL ? HOUR)";
            $params = [$tenantId, $userId, $type, $channel, $withinHours];

            if ($referenceId !== null) {
                $sql .= " AND reference_id = ?";
                $params[] = $referenceId;
            } else {
                $sql .= " AND reference_id IS NULL";
            }

            $count = (int)Database::query($sql, $params)->fetchColumn();
            return $count > 0;
        } catch (\Exception $e) {
            error_log("VolunteerReminderService::alreadySent error: " . $e->getMessage());
            return false;
        }
    }
}
