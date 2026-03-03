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
 * ShiftWaitlistService - Manages waitlist automation for volunteer shifts
 *
 * When a shift is full, volunteers can join a waitlist. When a spot opens
 * (cancellation), the next person on the waitlist is automatically notified
 * and promoted if they accept within the notification window.
 */
class ShiftWaitlistService
{
    private static array $errors = [];

    public static function getErrors(): array
    {
        return self::$errors;
    }

    /**
     * Join the waitlist for a shift
     *
     * @param int $shiftId Shift to waitlist for
     * @param int $userId User joining waitlist
     * @return int|null Waitlist entry ID or null on failure
     */
    public static function join(int $shiftId, int $userId): ?int
    {
        self::$errors = [];
        $tenantId = TenantContext::getId();

        $shift = VolShift::find($shiftId);
        if (!$shift) {
            self::$errors[] = ['code' => 'NOT_FOUND', 'message' => 'Shift not found'];
            return null;
        }

        // Check shift hasn't passed
        if (strtotime($shift['start_time']) < time()) {
            self::$errors[] = ['code' => 'VALIDATION_ERROR', 'message' => 'This shift has already started'];
            return null;
        }

        $db = Database::getConnection();

        // Check if already on waitlist
        $stmt = $db->prepare("SELECT id FROM vol_shift_waitlist WHERE shift_id = ? AND user_id = ? AND status = 'waiting' AND tenant_id = ?");
        $stmt->execute([$shiftId, $userId, $tenantId]);
        if ($stmt->fetch()) {
            self::$errors[] = ['code' => 'ALREADY_EXISTS', 'message' => 'You are already on the waitlist for this shift'];
            return null;
        }

        // Check if already signed up for the shift
        $stmt = $db->prepare("SELECT id FROM vol_applications WHERE shift_id = ? AND user_id = ? AND status = 'approved' AND tenant_id = ?");
        $stmt->execute([$shiftId, $userId, $tenantId]);
        if ($stmt->fetch()) {
            self::$errors[] = ['code' => 'ALREADY_EXISTS', 'message' => 'You are already signed up for this shift'];
            return null;
        }

        try {
            $db->beginTransaction();

            // Get next position (inside transaction to prevent race condition)
            $stmt = $db->prepare("SELECT COALESCE(MAX(position), 0) + 1 as next_pos FROM vol_shift_waitlist WHERE shift_id = ? AND status = 'waiting' AND tenant_id = ? FOR UPDATE");
            $stmt->execute([$shiftId, $tenantId]);
            $nextPos = (int)$stmt->fetch(\PDO::FETCH_ASSOC)['next_pos'];

            $stmt = $db->prepare("
                INSERT INTO vol_shift_waitlist (tenant_id, shift_id, user_id, position, status, created_at)
                VALUES (?, ?, ?, ?, 'waiting', NOW())
            ");
            $stmt->execute([$tenantId, $shiftId, $userId, $nextPos]);

            $id = (int)$db->lastInsertId();
            $db->commit();
            return $id;
        } catch (\Exception $e) {
            if ($db->inTransaction()) {
                $db->rollBack();
            }
            error_log("ShiftWaitlistService::join error: " . $e->getMessage());
            self::$errors[] = ['code' => 'SERVER_ERROR', 'message' => 'Failed to join waitlist'];
            return null;
        }
    }

    /**
     * Leave the waitlist
     *
     * @param int $shiftId Shift ID
     * @param int $userId User leaving
     * @return bool Success
     */
    public static function leave(int $shiftId, int $userId): bool
    {
        self::$errors = [];
        $tenantId = TenantContext::getId();

        $db = Database::getConnection();

        $stmt = $db->prepare("SELECT id, position FROM vol_shift_waitlist WHERE shift_id = ? AND user_id = ? AND status = 'waiting' AND tenant_id = ?");
        $stmt->execute([$shiftId, $userId, $tenantId]);
        $entry = $stmt->fetch(\PDO::FETCH_ASSOC);

        if (!$entry) {
            self::$errors[] = ['code' => 'NOT_FOUND', 'message' => 'You are not on the waitlist for this shift'];
            return false;
        }

        try {
            // Cancel the entry
            $stmt = $db->prepare("UPDATE vol_shift_waitlist SET status = 'cancelled' WHERE id = ?");
            $stmt->execute([$entry['id']]);

            // Reorder remaining positions
            $stmt = $db->prepare("
                UPDATE vol_shift_waitlist
                SET position = position - 1
                WHERE shift_id = ? AND status = 'waiting' AND position > ? AND tenant_id = ?
            ");
            $stmt->execute([$shiftId, $entry['position'], $tenantId]);

            return true;
        } catch (\Exception $e) {
            error_log("ShiftWaitlistService::leave error: " . $e->getMessage());
            self::$errors[] = ['code' => 'SERVER_ERROR', 'message' => 'Failed to leave waitlist'];
            return false;
        }
    }

    /**
     * Get waitlist for a shift
     *
     * @param int $shiftId Shift ID
     * @return array Waitlist entries
     */
    public static function getWaitlist(int $shiftId): array
    {
        $tenantId = TenantContext::getId();
        $db = Database::getConnection();

        $stmt = $db->prepare("
            SELECT w.*, u.name as user_name, u.avatar_url as user_avatar
            FROM vol_shift_waitlist w
            JOIN users u ON w.user_id = u.id
            WHERE w.shift_id = ? AND w.status = 'waiting' AND w.tenant_id = ?
            ORDER BY w.position ASC
        ");
        $stmt->execute([$shiftId, $tenantId]);
        $entries = $stmt->fetchAll(\PDO::FETCH_ASSOC);

        return array_map(function ($e) {
            return [
                'id' => (int)$e['id'],
                'position' => (int)$e['position'],
                'user' => [
                    'id' => (int)$e['user_id'],
                    'name' => $e['user_name'],
                    'avatar_url' => $e['user_avatar'],
                ],
                'created_at' => $e['created_at'],
            ];
        }, $entries);
    }

    /**
     * Get user's waitlist position for a shift
     *
     * @param int $shiftId Shift ID
     * @param int $userId User ID
     * @return array|null Position info or null if not on waitlist
     */
    public static function getUserPosition(int $shiftId, int $userId): ?array
    {
        $tenantId = TenantContext::getId();
        $db = Database::getConnection();

        $stmt = $db->prepare("
            SELECT w.*,
                   (SELECT COUNT(*) FROM vol_shift_waitlist WHERE shift_id = ? AND status = 'waiting' AND tenant_id = ?) as total_waiting
            FROM vol_shift_waitlist w
            WHERE w.shift_id = ? AND w.user_id = ? AND w.status = 'waiting' AND w.tenant_id = ?
        ");
        $stmt->execute([$shiftId, $tenantId, $shiftId, $userId, $tenantId]);
        $entry = $stmt->fetch(\PDO::FETCH_ASSOC);

        if (!$entry) {
            return null;
        }

        return [
            'id' => (int)$entry['id'],
            'position' => (int)$entry['position'],
            'total_waiting' => (int)$entry['total_waiting'],
        ];
    }

    /**
     * Process spot opening — called when someone cancels a shift signup.
     * Notifies the next person on the waitlist.
     *
     * @param int $shiftId Shift that has an opening
     * @return bool Whether someone was notified
     */
    public static function processSpotOpening(int $shiftId): bool
    {
        $tenantId = TenantContext::getId();
        $db = Database::getConnection();

        // Find next person on waitlist
        $stmt = $db->prepare("
            SELECT w.*, u.email as user_email, u.name as user_name
            FROM vol_shift_waitlist w
            JOIN users u ON w.user_id = u.id
            WHERE w.shift_id = ? AND w.status = 'waiting' AND w.tenant_id = ?
            ORDER BY w.position ASC
            LIMIT 1
        ");
        $stmt->execute([$shiftId, $tenantId]);
        $nextPerson = $stmt->fetch(\PDO::FETCH_ASSOC);

        if (!$nextPerson) {
            return false;
        }

        try {
            // Mark as notified
            $stmt = $db->prepare("UPDATE vol_shift_waitlist SET status = 'notified', notified_at = NOW() WHERE id = ?");
            $stmt->execute([$nextPerson['id']]);

            // Get shift details for notification
            $shift = VolShift::find($shiftId);
            $shiftDate = $shift ? date('M j, Y g:ia', strtotime($shift['start_time'])) : 'upcoming';

            // Send notification
            try {
                NotificationDispatcher::dispatch(
                    (int)$nextPerson['user_id'],
                    'global',
                    0,
                    'volunteer_waitlist',
                    "A spot has opened up for the volunteer shift on {$shiftDate}! Check your shifts to claim it.",
                    '/volunteering',
                    null
                );
            } catch (\Throwable $e) {
                error_log("Waitlist notification failed: " . $e->getMessage());
            }

            // Also send email
            try {
                if (!empty($nextPerson['user_email'])) {
                    $mailer = new \Nexus\Core\Mailer();
                    $baseUrl = \Nexus\Core\TenantContext::getFrontendUrl();
                    $basePath = \Nexus\Core\TenantContext::getSlugPrefix();
                    $volunteeringUrl = $baseUrl . $basePath . '/volunteering';
                    $subject = "A Volunteer Shift Spot Has Opened Up!";
                    $body = "<h2>Good news, {$nextPerson['user_name']}!</h2>"
                        . "<p>A spot has opened up for the volunteer shift on <strong>{$shiftDate}</strong>.</p>"
                        . "<p><a href=\"{$volunteeringUrl}\" style=\"display:inline-block;padding:10px 20px;background-color:#0070f3;color:#ffffff;text-decoration:none;border-radius:6px;\">Claim Your Spot</a></p>"
                        . "<p>Or visit: {$volunteeringUrl}</p>";
                    $mailer->send($nextPerson['user_email'], $subject, $body);
                }
            } catch (\Throwable $e) {
                error_log("Waitlist email failed: " . $e->getMessage());
            }

            return true;
        } catch (\Exception $e) {
            error_log("ShiftWaitlistService::processSpotOpening error: " . $e->getMessage());
            return false;
        }
    }

    /**
     * Promote a notified waitlist user (they accepted the spot)
     *
     * @param int $shiftId Shift ID
     * @param int $userId User being promoted
     * @return bool Success
     */
    public static function promoteUser(int $shiftId, int $userId): bool
    {
        self::$errors = [];
        $tenantId = TenantContext::getId();

        $db = Database::getConnection();

        $stmt = $db->prepare("SELECT id FROM vol_shift_waitlist WHERE shift_id = ? AND user_id = ? AND status = 'notified' AND tenant_id = ?");
        $stmt->execute([$shiftId, $userId, $tenantId]);
        $entry = $stmt->fetch(\PDO::FETCH_ASSOC);

        if (!$entry) {
            self::$errors[] = ['code' => 'NOT_FOUND', 'message' => 'No pending waitlist promotion found'];
            return false;
        }

        try {
            // Mark as promoted
            $stmt = $db->prepare("UPDATE vol_shift_waitlist SET status = 'promoted', promoted_at = NOW() WHERE id = ?");
            $stmt->execute([$entry['id']]);

            // Actually sign them up for the shift via VolunteerService
            $result = VolunteerService::signUpForShift($shiftId, $userId);

            if (!$result) {
                // Revert promotion if signup fails
                $stmt = $db->prepare("UPDATE vol_shift_waitlist SET status = 'waiting' WHERE id = ?");
                $stmt->execute([$entry['id']]);

                self::$errors = VolunteerService::getErrors();
                return false;
            }

            return true;
        } catch (\Exception $e) {
            error_log("ShiftWaitlistService::promoteUser error: " . $e->getMessage());
            self::$errors[] = ['code' => 'SERVER_ERROR', 'message' => 'Failed to promote from waitlist'];
            return false;
        }
    }

    /**
     * Get all waitlist entries for a user across all shifts
     *
     * @param int $userId User ID
     * @return array Waitlist entries with shift, opportunity, and organization details
     */
    public static function getUserWaitlists(int $userId): array
    {
        $tenantId = TenantContext::getId();

        try {
            $db = Database::getConnection();

            $stmt = $db->prepare("
                SELECT
                    w.id,
                    w.position,
                    w.created_at AS joined_at,
                    s.id AS shift_id,
                    s.start_time,
                    s.end_time,
                    s.capacity,
                    opp.id AS opportunity_id,
                    opp.title AS opportunity_title,
                    opp.location AS opportunity_location,
                    org.id AS organization_id,
                    org.name AS organization_name,
                    org.logo_url AS organization_logo_url
                FROM vol_shift_waitlist w
                JOIN vol_shifts s ON w.shift_id = s.id
                JOIN vol_opportunities opp ON s.opportunity_id = opp.id
                LEFT JOIN vol_organizations org ON opp.organization_id = org.id
                WHERE w.user_id = ?
                  AND w.tenant_id = ?
                  AND w.status = 'waiting'
                ORDER BY s.start_time ASC
            ");
            $stmt->execute([$userId, $tenantId]);
            $rows = $stmt->fetchAll(\PDO::FETCH_ASSOC);

            return array_map(function (array $row): array {
                return [
                    'id' => (int)$row['id'],
                    'position' => (int)$row['position'],
                    'shift' => [
                        'id' => (int)$row['shift_id'],
                        'start_time' => $row['start_time'],
                        'end_time' => $row['end_time'],
                        'capacity' => $row['capacity'] !== null ? (int)$row['capacity'] : null,
                    ],
                    'opportunity' => [
                        'id' => (int)$row['opportunity_id'],
                        'title' => $row['opportunity_title'],
                        'location' => $row['opportunity_location'] ?? '',
                    ],
                    'organization' => [
                        'id' => $row['organization_id'] ? (int)$row['organization_id'] : 0,
                        'name' => $row['organization_name'] ?? '',
                        'logo_url' => $row['organization_logo_url'] ?? null,
                    ],
                    'joined_at' => $row['joined_at'],
                ];
            }, $rows);
        } catch (\Exception $e) {
            error_log("ShiftWaitlistService::getUserWaitlists error: " . $e->getMessage());
            return [];
        }
    }
}
