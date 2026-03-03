<?php
// Copyright © 2024–2026 Jasper Ford
// SPDX-License-Identifier: AGPL-3.0-or-later
// Author: Jasper Ford
// See NOTICE file for attribution and acknowledgements.

namespace Nexus\Services;

use Nexus\Core\Database;
use Nexus\Core\TenantContext;
use Nexus\Models\VolShift;

/**
 * VolunteerEmergencyAlertService - Urgent shift-fill requests
 *
 * Coordinators can send urgent alerts to qualified volunteers when
 * a shift needs to be filled quickly. Filters by skills, availability,
 * and proximity. Marks alerts with priority levels and sends
 * push/email notifications.
 */
class VolunteerEmergencyAlertService
{
    private static array $errors = [];

    public static function getErrors(): array
    {
        return self::$errors;
    }

    /**
     * Create an emergency alert for a shift
     *
     * @param int $createdBy Coordinator user ID
     * @param array $data [shift_id, message, priority, required_skills, expires_hours]
     * @return int|null Alert ID or null on failure
     */
    public static function createAlert(int $createdBy, array $data): ?int
    {
        self::$errors = [];
        $tenantId = TenantContext::getId();

        $shiftId = (int)($data['shift_id'] ?? 0);
        $message = trim($data['message'] ?? '');
        $priority = $data['priority'] ?? 'urgent';
        $requiredSkills = $data['required_skills'] ?? null;
        $expiresHours = (int)($data['expires_hours'] ?? 24);

        if (!$shiftId) {
            self::$errors[] = ['code' => 'VALIDATION_ERROR', 'message' => 'Shift ID is required', 'field' => 'shift_id'];
            return null;
        }

        if (!$message) {
            self::$errors[] = ['code' => 'VALIDATION_ERROR', 'message' => 'Message is required', 'field' => 'message'];
            return null;
        }

        if (!in_array($priority, ['normal', 'urgent', 'critical'])) {
            self::$errors[] = ['code' => 'VALIDATION_ERROR', 'message' => 'Priority must be normal, urgent, or critical', 'field' => 'priority'];
            return null;
        }

        // Verify shift exists
        $shift = VolShift::find($shiftId);
        if (!$shift) {
            self::$errors[] = ['code' => 'NOT_FOUND', 'message' => 'Shift not found'];
            return null;
        }

        // Verify coordinator owns the opportunity's org
        $db = Database::getConnection();
        $stmt = $db->prepare("
            SELECT org.user_id as org_owner_id
            FROM vol_opportunities opp
            JOIN vol_organizations org ON opp.organization_id = org.id
            WHERE opp.id = ? AND org.tenant_id = ?
        ");
        $stmt->execute([$shift['opportunity_id'], $tenantId]);
        $opp = $stmt->fetch(\PDO::FETCH_ASSOC);

        if (!$opp) {
            self::$errors[] = ['code' => 'NOT_FOUND', 'message' => 'Opportunity not found'];
            return null;
        }

        // Allow org owner or tenant admin
        $isAdmin = self::isAdminOrOrgOwner($createdBy, (int)$opp['org_owner_id']);
        if (!$isAdmin) {
            self::$errors[] = ['code' => 'FORBIDDEN', 'message' => 'Only coordinators or admins can create emergency alerts'];
            return null;
        }

        // Encode skills as JSON
        $skillsJson = null;
        if ($requiredSkills) {
            if (is_array($requiredSkills)) {
                $skillsJson = json_encode($requiredSkills);
            } elseif (is_string($requiredSkills)) {
                // Parse comma-separated skills
                $skills = array_filter(array_map('trim', explode(',', $requiredSkills)));
                $skillsJson = json_encode(array_values($skills));
            }
        }

        $expiresAt = date('Y-m-d H:i:s', time() + ($expiresHours * 3600));

        try {
            $stmt = $db->prepare("
                INSERT INTO vol_emergency_alerts
                (tenant_id, shift_id, created_by, priority, message, required_skills, status, expires_at, created_at)
                VALUES (?, ?, ?, ?, ?, ?, 'active', ?, NOW())
            ");
            $stmt->execute([$tenantId, $shiftId, $createdBy, $priority, $message, $skillsJson, $expiresAt]);

            $alertId = (int)$db->lastInsertId();

            // Find and notify qualified volunteers
            $recipientCount = self::notifyQualifiedVolunteers($alertId, $shiftId, $skillsJson, $tenantId, $priority, $message);

            return $alertId;
        } catch (\Exception $e) {
            error_log("VolunteerEmergencyAlertService::createAlert error: " . $e->getMessage());
            self::$errors[] = ['code' => 'SERVER_ERROR', 'message' => 'Failed to create emergency alert'];
            return null;
        }
    }

    /**
     * Respond to an emergency alert (accept/decline)
     *
     * @param int $alertId Alert ID
     * @param int $userId Responding user
     * @param string $response 'accepted' or 'declined'
     * @return bool Success
     */
    public static function respond(int $alertId, int $userId, string $response): bool
    {
        self::$errors = [];

        if (!in_array($response, ['accepted', 'declined'])) {
            self::$errors[] = ['code' => 'VALIDATION_ERROR', 'message' => 'Response must be accepted or declined'];
            return false;
        }

        $db = Database::getConnection();

        // Verify the user was a recipient
        $stmt = $db->prepare("SELECT id FROM vol_emergency_alert_recipients WHERE alert_id = ? AND user_id = ? AND response = 'pending'");
        $stmt->execute([$alertId, $userId]);
        $recipient = $stmt->fetch(\PDO::FETCH_ASSOC);

        if (!$recipient) {
            self::$errors[] = ['code' => 'NOT_FOUND', 'message' => 'You were not invited for this alert or have already responded'];
            return false;
        }

        // Check alert is still active
        $tenantId = TenantContext::getId();
        $stmt = $db->prepare("SELECT * FROM vol_emergency_alerts WHERE id = ? AND status = 'active' AND tenant_id = ?");
        $stmt->execute([$alertId, $tenantId]);
        $alert = $stmt->fetch(\PDO::FETCH_ASSOC);

        if (!$alert) {
            self::$errors[] = ['code' => 'VALIDATION_ERROR', 'message' => 'This alert is no longer active'];
            return false;
        }

        try {
            // Update recipient response
            $stmt = $db->prepare("UPDATE vol_emergency_alert_recipients SET response = ?, responded_at = NOW() WHERE id = ?");
            $stmt->execute([$response, $recipient['id']]);

            if ($response === 'accepted') {
                // Mark alert as filled
                $stmt = $db->prepare("UPDATE vol_emergency_alerts SET status = 'filled', filled_at = NOW() WHERE id = ?");
                $stmt->execute([$alertId]);

                // Sign up the volunteer for the shift
                $signupResult = VolunteerService::signUpForShift((int)$alert['shift_id'], $userId);

                // Notify the coordinator
                try {
                    $user = \Nexus\Models\User::findById($userId);
                    $userName = $user['name'] ?? 'A volunteer';

                    NotificationDispatcher::dispatch(
                        (int)$alert['created_by'],
                        'global',
                        0,
                        'volunteer_emergency_filled',
                        "{$userName} has accepted your emergency shift request!",
                        '/volunteering',
                        null
                    );
                } catch (\Throwable $e) {
                    // Silent fail
                }
            }

            return true;
        } catch (\Exception $e) {
            error_log("VolunteerEmergencyAlertService::respond error: " . $e->getMessage());
            self::$errors[] = ['code' => 'SERVER_ERROR', 'message' => 'Failed to process response'];
            return false;
        }
    }

    /**
     * Get active alerts for a user (ones they've been notified about)
     *
     * @param int $userId User ID
     * @return array Active alerts
     */
    public static function getUserAlerts(int $userId): array
    {
        $tenantId = TenantContext::getId();
        $db = Database::getConnection();

        $stmt = $db->prepare("
            SELECT a.*, r.response as my_response, r.notified_at,
                   s.start_time, s.end_time,
                   o.title as opp_title, o.location as opp_location,
                   org.name as org_name,
                   u.name as coordinator_name
            FROM vol_emergency_alerts a
            JOIN vol_emergency_alert_recipients r ON r.alert_id = a.id
            JOIN vol_shifts s ON a.shift_id = s.id
            JOIN vol_opportunities o ON s.opportunity_id = o.id
            JOIN vol_organizations org ON o.organization_id = org.id
            JOIN users u ON a.created_by = u.id
            WHERE r.user_id = ? AND a.tenant_id = ? AND a.status = 'active'
            ORDER BY
                CASE a.priority WHEN 'critical' THEN 0 WHEN 'urgent' THEN 1 ELSE 2 END,
                a.created_at DESC
        ");
        $stmt->execute([$userId, $tenantId]);
        $alerts = $stmt->fetchAll(\PDO::FETCH_ASSOC);

        return array_map(function ($a) {
            return [
                'id' => (int)$a['id'],
                'priority' => $a['priority'],
                'message' => $a['message'],
                'my_response' => $a['my_response'],
                'required_skills' => json_decode($a['required_skills'] ?? '[]', true) ?: [],
                'shift' => [
                    'id' => (int)$a['shift_id'],
                    'start_time' => $a['start_time'],
                    'end_time' => $a['end_time'],
                ],
                'opportunity' => [
                    'title' => $a['opp_title'],
                    'location' => $a['opp_location'],
                ],
                'organization' => [
                    'name' => $a['org_name'],
                ],
                'coordinator' => [
                    'name' => $a['coordinator_name'],
                ],
                'expires_at' => $a['expires_at'],
                'created_at' => $a['created_at'],
            ];
        }, $alerts);
    }

    /**
     * Get alerts created by a coordinator
     *
     * @param int $coordinatorId Coordinator user ID
     * @return array Alerts with response stats
     */
    public static function getCoordinatorAlerts(int $coordinatorId): array
    {
        $tenantId = TenantContext::getId();
        $db = Database::getConnection();

        $stmt = $db->prepare("
            SELECT a.*,
                   s.start_time, s.end_time,
                   o.title as opp_title,
                   (SELECT COUNT(*) FROM vol_emergency_alert_recipients WHERE alert_id = a.id) as total_notified,
                   (SELECT COUNT(*) FROM vol_emergency_alert_recipients WHERE alert_id = a.id AND response = 'accepted') as total_accepted,
                   (SELECT COUNT(*) FROM vol_emergency_alert_recipients WHERE alert_id = a.id AND response = 'declined') as total_declined
            FROM vol_emergency_alerts a
            JOIN vol_shifts s ON a.shift_id = s.id
            JOIN vol_opportunities o ON s.opportunity_id = o.id
            WHERE a.created_by = ? AND a.tenant_id = ?
            ORDER BY a.created_at DESC
            LIMIT 50
        ");
        $stmt->execute([$coordinatorId, $tenantId]);
        $alerts = $stmt->fetchAll(\PDO::FETCH_ASSOC);

        return array_map(function ($a) {
            return [
                'id' => (int)$a['id'],
                'priority' => $a['priority'],
                'message' => $a['message'],
                'status' => $a['status'],
                'shift' => [
                    'id' => (int)$a['shift_id'],
                    'start_time' => $a['start_time'],
                    'end_time' => $a['end_time'],
                ],
                'opportunity_title' => $a['opp_title'],
                'stats' => [
                    'total_notified' => (int)$a['total_notified'],
                    'total_accepted' => (int)$a['total_accepted'],
                    'total_declined' => (int)$a['total_declined'],
                ],
                'expires_at' => $a['expires_at'],
                'created_at' => $a['created_at'],
            ];
        }, $alerts);
    }

    /**
     * Cancel an active alert
     */
    public static function cancelAlert(int $alertId, int $userId): bool
    {
        self::$errors = [];

        $db = Database::getConnection();
        $tenantId = TenantContext::getId();

        $stmt = $db->prepare("SELECT * FROM vol_emergency_alerts WHERE id = ? AND created_by = ? AND status = 'active' AND tenant_id = ?");
        $stmt->execute([$alertId, $userId, $tenantId]);
        $alert = $stmt->fetch(\PDO::FETCH_ASSOC);

        if (!$alert) {
            self::$errors[] = ['code' => 'NOT_FOUND', 'message' => 'Alert not found or cannot be cancelled'];
            return false;
        }

        try {
            $stmt = $db->prepare("UPDATE vol_emergency_alerts SET status = 'cancelled' WHERE id = ? AND tenant_id = ?");
            $stmt->execute([$alertId, $tenantId]);
            return true;
        } catch (\Exception $e) {
            error_log("VolunteerEmergencyAlertService::cancelAlert error: " . $e->getMessage());
            self::$errors[] = ['code' => 'SERVER_ERROR', 'message' => 'Failed to cancel alert'];
            return false;
        }
    }

    /**
     * Find and notify qualified volunteers for an emergency alert
     */
    private static function notifyQualifiedVolunteers(int $alertId, int $shiftId, ?string $skillsJson, int $tenantId, string $priority, string $message): int
    {
        $db = Database::getConnection();

        // Find volunteers who:
        // 1. Have approved applications for the opportunity's org
        // 2. Are not already signed up for this specific shift
        // 3. Optionally match required skills
        $stmt = $db->prepare("
            SELECT DISTINCT u.id as user_id, u.email, u.name, u.skills
            FROM users u
            JOIN vol_applications va ON va.user_id = u.id AND va.status = 'approved'
            JOIN vol_opportunities opp ON va.opportunity_id = opp.id
            JOIN vol_shifts s ON s.opportunity_id = opp.id AND s.id = ?
            WHERE u.tenant_id = ?
            AND u.id NOT IN (
                SELECT user_id FROM vol_applications WHERE shift_id = ? AND status = 'approved'
            )
            LIMIT 50
        ");
        $stmt->execute([$shiftId, $tenantId, $shiftId]);
        $candidates = $stmt->fetchAll(\PDO::FETCH_ASSOC);

        // If we have required skills, filter candidates
        $requiredSkills = [];
        if ($skillsJson) {
            $requiredSkills = json_decode($skillsJson, true) ?: [];
        }

        $notifiedCount = 0;
        $shift = VolShift::find($shiftId);
        $shiftDate = $shift ? date('M j, Y g:ia', strtotime($shift['start_time'])) : 'upcoming';

        foreach ($candidates as $candidate) {
            // If skills are required, check for match
            if (!empty($requiredSkills)) {
                $userSkills = SmartMatchingEngine::extractKeywords($candidate['skills'] ?? '');
                $matches = array_intersect(
                    array_map('strtolower', $userSkills),
                    array_map('strtolower', $requiredSkills)
                );

                if (empty($matches)) {
                    continue; // Skip if no skill match
                }
            }

            try {
                // Add as recipient
                $stmt = $db->prepare("
                    INSERT INTO vol_emergency_alert_recipients (alert_id, user_id, notified_at, response)
                    VALUES (?, ?, NOW(), 'pending')
                ");
                $stmt->execute([$alertId, $candidate['user_id']]);

                // Send notification
                $priorityLabel = strtoupper($priority);
                NotificationDispatcher::dispatch(
                    (int)$candidate['user_id'],
                    'global',
                    0,
                    'volunteer_emergency',
                    "[{$priorityLabel}] {$message} - Shift on {$shiftDate}. Can you help?",
                    '/volunteering',
                    null
                );

                // Send email for urgent/critical
                if (in_array($priority, ['urgent', 'critical']) && !empty($candidate['email'])) {
                    try {
                        $mailer = new \Nexus\Core\Mailer();
                        $priorityBadge = $priority === 'critical' ? '🔴 CRITICAL' : '🟠 URGENT';
                        $subject = "[{$priorityBadge}] Volunteer Needed: Shift on {$shiftDate}";
                        $body = "<h2>{$priorityBadge}: Volunteer Needed!</h2>"
                            . "<p>{$message}</p>"
                            . "<p><strong>Shift:</strong> {$shiftDate}</p>"
                            . "<p>Log in to your dashboard to respond to this request.</p>";
                        $mailer->send($candidate['email'], $subject, $body);
                    } catch (\Throwable $e) {
                        error_log("Emergency alert email failed: " . $e->getMessage());
                    }
                }

                $notifiedCount++;
            } catch (\Throwable $e) {
                error_log("Failed to notify volunteer {$candidate['user_id']}: " . $e->getMessage());
            }
        }

        // Also notify all active volunteers in the tenant (broader search if few qualified found)
        if ($notifiedCount < 5 && $priority === 'critical') {
            self::broadcastToActiveVolunteers($alertId, $tenantId, $shiftId, $priority, $message, $shiftDate);
        }

        return $notifiedCount;
    }

    /**
     * Broadcast to all active volunteers (for critical alerts with few qualified matches)
     */
    private static function broadcastToActiveVolunteers(int $alertId, int $tenantId, int $shiftId, string $priority, string $message, string $shiftDate): void
    {
        $db = Database::getConnection();

        $stmt = $db->prepare("
            SELECT DISTINCT u.id as user_id
            FROM users u
            JOIN vol_applications va ON va.user_id = u.id AND va.status = 'approved'
            WHERE u.tenant_id = ?
            AND u.id NOT IN (SELECT user_id FROM vol_emergency_alert_recipients WHERE alert_id = ?)
            AND u.id NOT IN (SELECT user_id FROM vol_applications WHERE shift_id = ? AND status = 'approved')
            LIMIT 25
        ");
        $stmt->execute([$tenantId, $alertId, $shiftId]);
        $extraVolunteers = $stmt->fetchAll(\PDO::FETCH_ASSOC);

        foreach ($extraVolunteers as $v) {
            try {
                $stmt2 = $db->prepare("
                    INSERT IGNORE INTO vol_emergency_alert_recipients (alert_id, user_id, notified_at, response)
                    VALUES (?, ?, NOW(), 'pending')
                ");
                $stmt2->execute([$alertId, $v['user_id']]);

                NotificationDispatcher::dispatch(
                    (int)$v['user_id'],
                    'global',
                    0,
                    'volunteer_emergency',
                    "[CRITICAL] {$message} - Shift on {$shiftDate}. We need your help!",
                    '/volunteering',
                    null
                );
            } catch (\Throwable $e) {
                // Silent fail for individual notifications
            }
        }
    }

    /**
     * Check if user is admin or org owner
     */
    private static function isAdminOrOrgOwner(int $userId, int $orgOwnerId): bool
    {
        if ($userId === $orgOwnerId) {
            return true;
        }

        // Check if tenant admin
        try {
            $tenantId = TenantContext::getId();
            $db = Database::getConnection();
            $stmt = $db->prepare("SELECT role FROM users WHERE id = ? AND tenant_id = ?");
            $stmt->execute([$userId, $tenantId]);
            $user = $stmt->fetch(\PDO::FETCH_ASSOC);

            if ($user && in_array($user['role'], ['admin', 'tenant_admin', 'tenant_super_admin', 'super_admin'])) {
                return true;
            }
        } catch (\Throwable $e) {
            // Fall through
        }

        return false;
    }
}
