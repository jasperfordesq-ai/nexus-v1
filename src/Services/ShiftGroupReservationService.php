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
 * ShiftGroupReservationService - Reserve shift slots for teams/groups
 *
 * Allows group leaders to reserve a block of shift slots for their
 * company team or community group. Links to the Groups module.
 */
class ShiftGroupReservationService
{
    private static array $errors = [];

    public static function getErrors(): array
    {
        return self::$errors;
    }

    /**
     * Create a group reservation for a shift
     *
     * @param int $shiftId Shift to reserve slots for
     * @param int $groupId Group ID (from groups table)
     * @param int $reservedBy User making the reservation (group leader)
     * @param int $slots Number of slots to reserve
     * @param string|null $notes Optional notes
     * @return int|null Reservation ID or null on failure
     */
    public static function reserve(int $shiftId, int $groupId, int $reservedBy, int $slots, ?string $notes = null): ?int
    {
        self::$errors = [];
        $tenantId = TenantContext::getId();

        if ($slots < 1) {
            self::$errors[] = ['code' => 'VALIDATION_ERROR', 'message' => 'Must reserve at least 1 slot', 'field' => 'reserved_slots'];
            return null;
        }

        $shift = VolShift::find($shiftId);
        if (!$shift) {
            self::$errors[] = ['code' => 'NOT_FOUND', 'message' => 'Shift not found'];
            return null;
        }

        if (strtotime($shift['start_time']) < time()) {
            self::$errors[] = ['code' => 'VALIDATION_ERROR', 'message' => 'Cannot reserve slots for a shift that has already started'];
            return null;
        }

        $db = Database::getConnection();

        // Verify group exists and user is a leader/admin of the group
        $stmt = $db->prepare("SELECT id, name FROM groups_table WHERE id = ? AND tenant_id = ?");
        $stmt->execute([$groupId, $tenantId]);
        $group = $stmt->fetch(\PDO::FETCH_ASSOC);

        if (!$group) {
            // Try alternate table name
            try {
                $stmt = $db->prepare("SELECT id, name FROM `groups` WHERE id = ? AND tenant_id = ?");
                $stmt->execute([$groupId, $tenantId]);
                $group = $stmt->fetch(\PDO::FETCH_ASSOC);
            } catch (\Throwable $e) {
                // Table might not exist
            }
        }

        if (!$group) {
            self::$errors[] = ['code' => 'NOT_FOUND', 'message' => 'Group not found'];
            return null;
        }

        // Check available capacity
        $availableSlots = self::getAvailableSlots($shiftId, $shift);
        if ($availableSlots !== null && $slots > $availableSlots) {
            self::$errors[] = ['code' => 'VALIDATION_ERROR', 'message' => "Only {$availableSlots} slots available", 'field' => 'reserved_slots'];
            return null;
        }

        // Check for existing reservation
        $stmt = $db->prepare("SELECT id FROM vol_shift_group_reservations WHERE shift_id = ? AND group_id = ? AND status = 'active'");
        $stmt->execute([$shiftId, $groupId]);
        if ($stmt->fetch()) {
            self::$errors[] = ['code' => 'ALREADY_EXISTS', 'message' => 'This group already has a reservation for this shift'];
            return null;
        }

        try {
            $stmt = $db->prepare("
                INSERT INTO vol_shift_group_reservations
                (tenant_id, shift_id, group_id, reserved_slots, filled_slots, reserved_by, status, notes, created_at)
                VALUES (?, ?, ?, ?, 0, ?, 'active', ?, NOW())
            ");
            $stmt->execute([$tenantId, $shiftId, $groupId, $slots, $reservedBy, $notes]);

            return (int)$db->lastInsertId();
        } catch (\Exception $e) {
            error_log("ShiftGroupReservationService::reserve error: " . $e->getMessage());
            self::$errors[] = ['code' => 'SERVER_ERROR', 'message' => 'Failed to create reservation'];
            return null;
        }
    }

    /**
     * Add a member to a group reservation
     *
     * @param int $reservationId Reservation ID
     * @param int $userId User to add
     * @param int $leaderUserId Group leader performing the action
     * @return bool Success
     */
    public static function addMember(int $reservationId, int $userId, int $leaderUserId): bool
    {
        self::$errors = [];

        $db = Database::getConnection();

        $stmt = $db->prepare("SELECT * FROM vol_shift_group_reservations WHERE id = ? AND status = 'active'");
        $stmt->execute([$reservationId]);
        $reservation = $stmt->fetch(\PDO::FETCH_ASSOC);

        if (!$reservation) {
            self::$errors[] = ['code' => 'NOT_FOUND', 'message' => 'Reservation not found'];
            return false;
        }

        if ((int)$reservation['filled_slots'] >= (int)$reservation['reserved_slots']) {
            self::$errors[] = ['code' => 'VALIDATION_ERROR', 'message' => 'All reserved slots are filled'];
            return false;
        }

        // Check if user is already in this reservation
        $stmt = $db->prepare("SELECT id FROM vol_shift_group_members WHERE reservation_id = ? AND user_id = ? AND status = 'confirmed'");
        $stmt->execute([$reservationId, $userId]);
        if ($stmt->fetch()) {
            self::$errors[] = ['code' => 'ALREADY_EXISTS', 'message' => 'User is already in this group reservation'];
            return false;
        }

        try {
            $db->beginTransaction();

            $stmt = $db->prepare("INSERT INTO vol_shift_group_members (reservation_id, user_id, status, created_at) VALUES (?, ?, 'confirmed', NOW())");
            $stmt->execute([$reservationId, $userId]);

            $stmt = $db->prepare("UPDATE vol_shift_group_reservations SET filled_slots = filled_slots + 1 WHERE id = ?");
            $stmt->execute([$reservationId]);

            $db->commit();

            // Notify the added member
            try {
                NotificationDispatcher::dispatch(
                    $userId,
                    'global',
                    0,
                    'volunteer_group_signup',
                    "You've been added to a group volunteer shift. Check your volunteering dashboard for details.",
                    '/volunteering',
                    null
                );
            } catch (\Throwable $e) {
                // Silent fail
            }

            return true;
        } catch (\Exception $e) {
            $db->rollBack();
            error_log("ShiftGroupReservationService::addMember error: " . $e->getMessage());
            self::$errors[] = ['code' => 'SERVER_ERROR', 'message' => 'Failed to add member'];
            return false;
        }
    }

    /**
     * Remove a member from a group reservation
     */
    public static function removeMember(int $reservationId, int $userId, int $leaderUserId): bool
    {
        self::$errors = [];

        $db = Database::getConnection();

        $stmt = $db->prepare("SELECT id FROM vol_shift_group_members WHERE reservation_id = ? AND user_id = ? AND status = 'confirmed'");
        $stmt->execute([$reservationId, $userId]);
        $member = $stmt->fetch(\PDO::FETCH_ASSOC);

        if (!$member) {
            self::$errors[] = ['code' => 'NOT_FOUND', 'message' => 'Member not found in this reservation'];
            return false;
        }

        try {
            $db->beginTransaction();

            $stmt = $db->prepare("UPDATE vol_shift_group_members SET status = 'cancelled' WHERE id = ?");
            $stmt->execute([$member['id']]);

            $stmt = $db->prepare("UPDATE vol_shift_group_reservations SET filled_slots = GREATEST(filled_slots - 1, 0) WHERE id = ?");
            $stmt->execute([$reservationId]);

            $db->commit();
            return true;
        } catch (\Exception $e) {
            $db->rollBack();
            error_log("ShiftGroupReservationService::removeMember error: " . $e->getMessage());
            self::$errors[] = ['code' => 'SERVER_ERROR', 'message' => 'Failed to remove member'];
            return false;
        }
    }

    /**
     * Cancel an entire group reservation
     */
    public static function cancelReservation(int $reservationId, int $leaderUserId): bool
    {
        self::$errors = [];

        $db = Database::getConnection();

        $stmt = $db->prepare("SELECT * FROM vol_shift_group_reservations WHERE id = ? AND reserved_by = ? AND status = 'active'");
        $stmt->execute([$reservationId, $leaderUserId]);
        $reservation = $stmt->fetch(\PDO::FETCH_ASSOC);

        if (!$reservation) {
            self::$errors[] = ['code' => 'NOT_FOUND', 'message' => 'Reservation not found or you are not the group leader'];
            return false;
        }

        try {
            $db->beginTransaction();

            $stmt = $db->prepare("UPDATE vol_shift_group_reservations SET status = 'cancelled' WHERE id = ?");
            $stmt->execute([$reservationId]);

            $stmt = $db->prepare("UPDATE vol_shift_group_members SET status = 'cancelled' WHERE reservation_id = ? AND status = 'confirmed'");
            $stmt->execute([$reservationId]);

            $db->commit();
            return true;
        } catch (\Exception $e) {
            $db->rollBack();
            error_log("ShiftGroupReservationService::cancelReservation error: " . $e->getMessage());
            self::$errors[] = ['code' => 'SERVER_ERROR', 'message' => 'Failed to cancel reservation'];
            return false;
        }
    }

    /**
     * Get reservations for a shift
     */
    public static function getReservationsForShift(int $shiftId): array
    {
        $db = Database::getConnection();

        $stmt = $db->prepare("
            SELECT r.*, u.name as reserved_by_name
            FROM vol_shift_group_reservations r
            JOIN users u ON r.reserved_by = u.id
            WHERE r.shift_id = ? AND r.status = 'active'
            ORDER BY r.created_at DESC
        ");
        $stmt->execute([$shiftId]);
        $reservations = $stmt->fetchAll(\PDO::FETCH_ASSOC);

        return array_map(function ($r) {
            return [
                'id' => (int)$r['id'],
                'group_id' => (int)$r['group_id'],
                'reserved_slots' => (int)$r['reserved_slots'],
                'filled_slots' => (int)$r['filled_slots'],
                'reserved_by' => [
                    'id' => (int)$r['reserved_by'],
                    'name' => $r['reserved_by_name'],
                ],
                'notes' => $r['notes'],
                'status' => $r['status'],
                'created_at' => $r['created_at'],
            ];
        }, $reservations);
    }

    /**
     * Get members of a group reservation
     */
    public static function getReservationMembers(int $reservationId): array
    {
        $db = Database::getConnection();

        $stmt = $db->prepare("
            SELECT gm.*, u.name as user_name, u.avatar_url as user_avatar, u.email as user_email
            FROM vol_shift_group_members gm
            JOIN users u ON gm.user_id = u.id
            WHERE gm.reservation_id = ? AND gm.status = 'confirmed'
            ORDER BY gm.created_at ASC
        ");
        $stmt->execute([$reservationId]);
        $members = $stmt->fetchAll(\PDO::FETCH_ASSOC);

        return array_map(function ($m) {
            return [
                'id' => (int)$m['id'],
                'user' => [
                    'id' => (int)$m['user_id'],
                    'name' => $m['user_name'],
                    'avatar_url' => $m['user_avatar'],
                ],
                'created_at' => $m['created_at'],
            ];
        }, $members);
    }

    /**
     * Get all group reservations a user is involved in (as leader or member)
     *
     * @param int $userId User ID
     * @return array Group reservations with shift, opportunity, organization, and member details
     */
    public static function getUserReservations(int $userId): array
    {
        $tenantId = TenantContext::getId();

        try {
            $db = Database::getConnection();

            // Find reservations where user is the leader or a confirmed member
            $stmt = $db->prepare("
                SELECT DISTINCT
                    r.id,
                    r.reserved_slots,
                    r.filled_slots,
                    r.reserved_by,
                    r.status,
                    r.notes,
                    r.created_at,
                    r.group_id,
                    s.id AS shift_id,
                    s.start_time,
                    s.end_time,
                    opp.id AS opportunity_id,
                    opp.title AS opportunity_title,
                    opp.location AS opportunity_location,
                    org.id AS organization_id,
                    org.name AS organization_name,
                    org.logo_url AS organization_logo_url
                FROM vol_shift_group_reservations r
                JOIN vol_shifts s ON r.shift_id = s.id
                JOIN vol_opportunities opp ON s.opportunity_id = opp.id
                LEFT JOIN vol_organizations org ON opp.organization_id = org.id
                LEFT JOIN vol_shift_group_members gm ON gm.reservation_id = r.id AND gm.user_id = ? AND gm.status = 'confirmed'
                WHERE r.tenant_id = ?
                  AND (r.reserved_by = ? OR gm.id IS NOT NULL)
                ORDER BY s.start_time ASC
            ");
            $stmt->execute([$userId, $tenantId, $userId]);
            $rows = $stmt->fetchAll(\PDO::FETCH_ASSOC);

            // Look up group names and members for each reservation
            return array_map(function (array $row) use ($db, $tenantId, $userId): array {
                $groupName = '';

                // Try groups_table first (same pattern as reserve())
                try {
                    $gStmt = $db->prepare("SELECT name FROM groups_table WHERE id = ? AND tenant_id = ?");
                    $gStmt->execute([$row['group_id'], $tenantId]);
                    $group = $gStmt->fetch(\PDO::FETCH_ASSOC);
                    if ($group) {
                        $groupName = $group['name'];
                    }
                } catch (\Throwable $e) {
                    // Table might not exist
                }

                // Fallback to `groups` table
                if (!$groupName) {
                    try {
                        $gStmt = $db->prepare("SELECT name FROM `groups` WHERE id = ? AND tenant_id = ?");
                        $gStmt->execute([$row['group_id'], $tenantId]);
                        $group = $gStmt->fetch(\PDO::FETCH_ASSOC);
                        if ($group) {
                            $groupName = $group['name'];
                        }
                    } catch (\Throwable $e) {
                        // Silent
                    }
                }

                // Get members via the existing helper
                $members = self::getReservationMembers((int)$row['id']);

                return [
                    'id' => (int)$row['id'],
                    'group_name' => $groupName,
                    'status' => $row['status'],
                    'is_leader' => (int)$row['reserved_by'] === $userId,
                    'shift' => [
                        'id' => (int)$row['shift_id'],
                        'start_time' => $row['start_time'],
                        'end_time' => $row['end_time'],
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
                    'members' => $members,
                    'max_members' => $row['reserved_slots'] !== null ? (int)$row['reserved_slots'] : null,
                    'created_at' => $row['created_at'],
                ];
            }, $rows);
        } catch (\Exception $e) {
            error_log("ShiftGroupReservationService::getUserReservations error: " . $e->getMessage());
            return [];
        }
    }

    /**
     * Calculate available slots for a shift (accounting for regular signups + group reservations)
     */
    private static function getAvailableSlots(int $shiftId, array $shift): ?int
    {
        if (!$shift['capacity']) {
            return null; // Unlimited
        }

        $db = Database::getConnection();

        // Count regular signups
        $stmt = $db->prepare("SELECT COUNT(*) as cnt FROM vol_applications WHERE shift_id = ? AND status = 'approved'");
        $stmt->execute([$shiftId]);
        $regularSignups = (int)$stmt->fetch(\PDO::FETCH_ASSOC)['cnt'];

        // Count reserved slots from active group reservations
        $stmt = $db->prepare("SELECT COALESCE(SUM(reserved_slots), 0) as total FROM vol_shift_group_reservations WHERE shift_id = ? AND status = 'active'");
        $stmt->execute([$shiftId]);
        $reservedSlots = (int)$stmt->fetch(\PDO::FETCH_ASSOC)['total'];

        return max(0, (int)$shift['capacity'] - $regularSignups - $reservedSlots);
    }
}
