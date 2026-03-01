<?php
// Copyright © 2024–2026 Jasper Ford
// SPDX-License-Identifier: AGPL-3.0-or-later
// Author: Jasper Ford
// See NOTICE file for attribution and acknowledgements.

namespace Nexus\Services;

use Nexus\Core\Database;
use Nexus\Core\TenantContext;

/**
 * MemberAvailabilityService - Manage member availability schedules
 *
 * Provides:
 * - Set recurring weekly availability (day + time slots)
 * - Set one-off availability for specific dates
 * - Query availability for matching engine
 * - Get compatible times between two members
 */
class MemberAvailabilityService
{
    private static array $errors = [];

    public static function getErrors(): array
    {
        return self::$errors;
    }

    /**
     * Get all availability slots for a user
     *
     * @param int $userId
     * @return array
     */
    public static function getUserAvailability(int $userId): array
    {
        $tenantId = TenantContext::getId();

        return Database::query(
            "SELECT id, day_of_week, start_time, end_time, is_recurring, specific_date, note
             FROM member_availability
             WHERE user_id = ? AND tenant_id = ?
             ORDER BY day_of_week ASC, start_time ASC",
            [$userId, $tenantId]
        )->fetchAll(\PDO::FETCH_ASSOC);
    }

    /**
     * Set availability for a user (replaces all recurring slots for a given day)
     *
     * @param int $userId
     * @param int $dayOfWeek 0=Sunday, 6=Saturday
     * @param array $slots Array of ['start_time' => 'HH:MM', 'end_time' => 'HH:MM', 'note' => '']
     * @return bool
     */
    public static function setDayAvailability(int $userId, int $dayOfWeek, array $slots): bool
    {
        self::$errors = [];
        $tenantId = TenantContext::getId();

        if ($dayOfWeek < 0 || $dayOfWeek > 6) {
            self::$errors[] = ['code' => 'VALIDATION_ERROR', 'message' => 'day_of_week must be 0-6', 'field' => 'day_of_week'];
            return false;
        }

        // Validate slots
        foreach ($slots as $i => $slot) {
            if (empty($slot['start_time']) || empty($slot['end_time'])) {
                self::$errors[] = ['code' => 'VALIDATION_ERROR', 'message' => "Slot {$i}: start_time and end_time required", 'field' => 'slots'];
                return false;
            }
            if ($slot['start_time'] >= $slot['end_time']) {
                self::$errors[] = ['code' => 'VALIDATION_ERROR', 'message' => "Slot {$i}: end_time must be after start_time", 'field' => 'slots'];
                return false;
            }
        }

        $db = Database::getInstance();
        $db->beginTransaction();

        try {
            // Remove existing recurring slots for this day
            Database::query(
                "DELETE FROM member_availability WHERE user_id = ? AND tenant_id = ? AND day_of_week = ? AND is_recurring = 1",
                [$userId, $tenantId, $dayOfWeek]
            );

            // Insert new slots
            foreach ($slots as $slot) {
                Database::query(
                    "INSERT INTO member_availability (user_id, tenant_id, day_of_week, start_time, end_time, is_recurring, note)
                     VALUES (?, ?, ?, ?, ?, 1, ?)",
                    [
                        $userId,
                        $tenantId,
                        $dayOfWeek,
                        $slot['start_time'],
                        $slot['end_time'],
                        $slot['note'] ?? null,
                    ]
                );
            }

            $db->commit();
            return true;
        } catch (\Exception $e) {
            $db->rollBack();
            self::$errors[] = ['code' => 'SERVER_ERROR', 'message' => 'Failed to save availability'];
            return false;
        }
    }

    /**
     * Set a bulk availability schedule (all 7 days at once)
     *
     * @param int $userId
     * @param array $schedule ['0' => [slots], '1' => [slots], ...]
     * @return bool
     */
    public static function setBulkAvailability(int $userId, array $schedule): bool
    {
        self::$errors = [];
        $tenantId = TenantContext::getId();

        $db = Database::getInstance();
        $db->beginTransaction();

        try {
            // Remove all recurring slots
            Database::query(
                "DELETE FROM member_availability WHERE user_id = ? AND tenant_id = ? AND is_recurring = 1",
                [$userId, $tenantId]
            );

            // Insert new slots for each day
            foreach ($schedule as $dayOfWeek => $slots) {
                $dayOfWeek = (int)$dayOfWeek;
                if ($dayOfWeek < 0 || $dayOfWeek > 6) {
                    continue;
                }

                foreach ($slots as $slot) {
                    if (empty($slot['start_time']) || empty($slot['end_time'])) {
                        continue;
                    }
                    if ($slot['start_time'] >= $slot['end_time']) {
                        continue;
                    }

                    Database::query(
                        "INSERT INTO member_availability (user_id, tenant_id, day_of_week, start_time, end_time, is_recurring, note)
                         VALUES (?, ?, ?, ?, ?, 1, ?)",
                        [
                            $userId,
                            $tenantId,
                            $dayOfWeek,
                            $slot['start_time'],
                            $slot['end_time'],
                            $slot['note'] ?? null,
                        ]
                    );
                }
            }

            $db->commit();
            return true;
        } catch (\Exception $e) {
            $db->rollBack();
            self::$errors[] = ['code' => 'SERVER_ERROR', 'message' => 'Failed to save availability'];
            return false;
        }
    }

    /**
     * Add a one-off availability slot for a specific date
     */
    public static function addSpecificDate(int $userId, array $data): ?int
    {
        self::$errors = [];
        $tenantId = TenantContext::getId();

        $date = $data['date'] ?? '';
        $startTime = $data['start_time'] ?? '';
        $endTime = $data['end_time'] ?? '';

        if (empty($date) || empty($startTime) || empty($endTime)) {
            self::$errors[] = ['code' => 'VALIDATION_ERROR', 'message' => 'date, start_time, and end_time are required'];
            return null;
        }

        if ($startTime >= $endTime) {
            self::$errors[] = ['code' => 'VALIDATION_ERROR', 'message' => 'end_time must be after start_time'];
            return null;
        }

        // Calculate day_of_week from date
        $dayOfWeek = (int)date('w', strtotime($date));

        Database::query(
            "INSERT INTO member_availability (user_id, tenant_id, day_of_week, start_time, end_time, is_recurring, specific_date, note)
             VALUES (?, ?, ?, ?, ?, 0, ?, ?)",
            [
                $userId,
                $tenantId,
                $dayOfWeek,
                $startTime,
                $endTime,
                $date,
                $data['note'] ?? null,
            ]
        );

        return (int)Database::lastInsertId();
    }

    /**
     * Delete an availability slot
     */
    public static function deleteSlot(int $userId, int $slotId): bool
    {
        $tenantId = TenantContext::getId();

        Database::query(
            "DELETE FROM member_availability WHERE id = ? AND user_id = ? AND tenant_id = ?",
            [$slotId, $userId, $tenantId]
        );

        return true;
    }

    /**
     * Find compatible times between two members
     *
     * @param int $userId1
     * @param int $userId2
     * @return array Overlapping time slots by day
     */
    public static function findCompatibleTimes(int $userId1, int $userId2): array
    {
        $tenantId = TenantContext::getId();

        // Get recurring availability for both users
        $sql = "SELECT a.day_of_week, a.start_time, a.end_time, a.user_id
                FROM member_availability a
                WHERE a.tenant_id = ? AND a.user_id IN (?, ?) AND a.is_recurring = 1
                ORDER BY a.day_of_week, a.start_time";

        $rows = Database::query($sql, [$tenantId, $userId1, $userId2])->fetchAll(\PDO::FETCH_ASSOC);

        // Group by day and user
        $byDay = [];
        foreach ($rows as $row) {
            $day = (int)$row['day_of_week'];
            $uid = (int)$row['user_id'];
            $byDay[$day][$uid][] = [
                'start' => $row['start_time'],
                'end' => $row['end_time'],
            ];
        }

        $compatible = [];
        $dayNames = ['Sunday', 'Monday', 'Tuesday', 'Wednesday', 'Thursday', 'Friday', 'Saturday'];

        foreach ($byDay as $day => $users) {
            if (!isset($users[$userId1]) || !isset($users[$userId2])) {
                continue;
            }

            // Find overlapping intervals
            foreach ($users[$userId1] as $slot1) {
                foreach ($users[$userId2] as $slot2) {
                    $overlapStart = max($slot1['start'], $slot2['start']);
                    $overlapEnd = min($slot1['end'], $slot2['end']);

                    if ($overlapStart < $overlapEnd) {
                        $compatible[] = [
                            'day_of_week' => $day,
                            'day_name' => $dayNames[$day],
                            'start_time' => $overlapStart,
                            'end_time' => $overlapEnd,
                        ];
                    }
                }
            }
        }

        return $compatible;
    }

    /**
     * Get members available on a specific day and time
     *
     * @param int $dayOfWeek
     * @param string|null $time Optional time to check (HH:MM)
     * @param int $limit
     * @return array User IDs with availability
     */
    public static function getAvailableMembers(int $dayOfWeek, ?string $time = null, int $limit = 50): array
    {
        $tenantId = TenantContext::getId();

        $sql = "SELECT DISTINCT a.user_id, a.start_time, a.end_time,
                       CONCAT(u.first_name, ' ', u.last_name) as member_name,
                       u.avatar_url
                FROM member_availability a
                JOIN users u ON a.user_id = u.id
                WHERE a.tenant_id = ? AND a.day_of_week = ? AND a.is_recurring = 1";
        $params = [$tenantId, $dayOfWeek];

        if ($time !== null) {
            $sql .= " AND a.start_time <= ? AND a.end_time > ?";
            $params[] = $time;
            $params[] = $time;
        }

        $sql .= " ORDER BY a.start_time ASC LIMIT ?";
        $params[] = $limit;

        return Database::query($sql, $params)->fetchAll(\PDO::FETCH_ASSOC);
    }
}
