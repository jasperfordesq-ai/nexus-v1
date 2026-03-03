<?php
// Copyright © 2024–2026 Jasper Ford
// SPDX-License-Identifier: AGPL-3.0-or-later
// Author: Jasper Ford
// See NOTICE file for attribution and acknowledgements.

namespace Nexus\Services;

use Nexus\Core\Database;
use Nexus\Core\TenantContext;

/**
 * RecurringShiftService - Manages recurring shift patterns for volunteering
 *
 * Creates and manages recurring shift patterns that auto-generate vol_shifts.
 * Cron runs daily to generate upcoming shift occurrences from active patterns.
 */
class RecurringShiftService
{
    private static array $errors = [];

    public static function getErrors(): array
    {
        return self::$errors;
    }

    /**
     * Create a new recurring shift pattern
     *
     * @param int $opportunityId Opportunity ID
     * @param int $createdBy User ID of creator
     * @param array $data Pattern configuration
     * @return int|null Pattern ID or null on failure
     */
    public static function createPattern(int $opportunityId, int $createdBy, array $data): ?int
    {
        self::$errors = [];
        $tenantId = TenantContext::getId();

        // Validate required fields
        if (empty($data['frequency']) || !in_array($data['frequency'], ['daily', 'weekly', 'biweekly', 'monthly'], true)) {
            self::$errors[] = ['code' => 'VALIDATION_ERROR', 'message' => 'Invalid frequency', 'field' => 'frequency'];
            return null;
        }

        if (empty($data['start_time']) || empty($data['end_time'])) {
            self::$errors[] = ['code' => 'VALIDATION_ERROR', 'message' => 'Start time and end time are required', 'field' => 'start_time'];
            return null;
        }

        if (empty($data['start_date'])) {
            self::$errors[] = ['code' => 'VALIDATION_ERROR', 'message' => 'Start date is required', 'field' => 'start_date'];
            return null;
        }

        $daysOfWeek = null;
        if (in_array($data['frequency'], ['weekly', 'biweekly'], true)) {
            if (empty($data['days_of_week']) || !is_array($data['days_of_week'])) {
                self::$errors[] = ['code' => 'VALIDATION_ERROR', 'message' => 'Days of week required for weekly/biweekly patterns', 'field' => 'days_of_week'];
                return null;
            }
            $daysOfWeek = json_encode(array_map('intval', $data['days_of_week']));
        }

        $capacity = max(1, (int) ($data['capacity'] ?? 1));
        $endDate = $data['end_date'] ?? null;
        $maxOccurrences = isset($data['max_occurrences']) ? (int) $data['max_occurrences'] : null;
        $title = $data['title'] ?? null;

        try {
            $db = Database::getConnection();
            $stmt = $db->prepare("
                INSERT INTO recurring_shift_patterns
                    (tenant_id, opportunity_id, created_by, title, frequency, days_of_week,
                     start_time, end_time, capacity, start_date, end_date, max_occurrences, is_active)
                VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, 1)
            ");
            $stmt->execute([
                $tenantId, $opportunityId, $createdBy, $title,
                $data['frequency'], $daysOfWeek,
                $data['start_time'], $data['end_time'], $capacity,
                $data['start_date'], $endDate, $maxOccurrences,
            ]);

            $patternId = (int) $db->lastInsertId();

            // Generate initial batch of shifts (next 14 days)
            self::generateOccurrences($patternId, 14);

            return $patternId;
        } catch (\Exception $e) {
            error_log("RecurringShiftService::createPattern error: " . $e->getMessage());
            self::$errors[] = ['code' => 'SERVER_ERROR', 'message' => 'Failed to create recurring pattern'];
            return null;
        }
    }

    /**
     * Generate shift occurrences from a pattern for the next N days
     *
     * @param int $patternId Pattern ID
     * @param int $daysAhead Number of days ahead to generate
     * @return int Number of shifts generated
     */
    public static function generateOccurrences(int $patternId, int $daysAhead = 14): int
    {
        $tenantId = TenantContext::getId();
        $db = Database::getConnection();

        $stmt = $db->prepare("SELECT * FROM recurring_shift_patterns WHERE id = ? AND is_active = 1 AND tenant_id = ?");
        $stmt->execute([$patternId, $tenantId]);
        $pattern = $stmt->fetch(\PDO::FETCH_ASSOC);

        if (!$pattern) {
            return 0;
        }

        // Check max occurrences limit
        if ($pattern['max_occurrences'] && $pattern['occurrences_generated'] >= $pattern['max_occurrences']) {
            return 0;
        }

        // Check end date
        $endDate = $pattern['end_date'] ? new \DateTime($pattern['end_date']) : null;
        $startDate = new \DateTime($pattern['start_date']);
        $today = new \DateTime('today');
        $generateUntil = new \DateTime("+{$daysAhead} days");

        // Don't generate past the end date
        if ($endDate && $generateUntil > $endDate) {
            $generateUntil = $endDate;
        }

        // Start from the later of: pattern start date or today
        $cursor = $startDate > $today ? clone $startDate : clone $today;

        $daysOfWeek = $pattern['days_of_week'] ? json_decode($pattern['days_of_week'], true) : null;
        $generated = 0;
        $remainingOccurrences = $pattern['max_occurrences']
            ? $pattern['max_occurrences'] - $pattern['occurrences_generated']
            : PHP_INT_MAX;

        while ($cursor <= $generateUntil && $generated < $remainingOccurrences) {
            $dayOfWeek = (int) $cursor->format('w'); // 0=Sun, 6=Sat

            $shouldGenerate = match ($pattern['frequency']) {
                'daily' => true,
                'weekly' => $daysOfWeek && in_array($dayOfWeek, $daysOfWeek, true),
                'biweekly' => $daysOfWeek && in_array($dayOfWeek, $daysOfWeek, true)
                    && self::isCorrectBiweeklyWeek($startDate, $cursor),
                'monthly' => (int) $cursor->format('j') === (int) $startDate->format('j'),
                default => false,
            };

            if ($shouldGenerate) {
                $shiftDate = $cursor->format('Y-m-d');
                $shiftStart = $shiftDate . ' ' . $pattern['start_time'];
                $shiftEnd = $shiftDate . ' ' . $pattern['end_time'];

                // Check if this shift already exists (idempotent)
                $existsStmt = $db->prepare("
                    SELECT id FROM vol_shifts
                    WHERE opportunity_id = ? AND recurring_pattern_id = ?
                      AND start_time = ? AND end_time = ?
                ");
                $existsStmt->execute([
                    $pattern['opportunity_id'], $patternId,
                    $shiftStart, $shiftEnd,
                ]);

                if (!$existsStmt->fetch()) {
                    $insertStmt = $db->prepare("
                        INSERT INTO vol_shifts (tenant_id, opportunity_id, recurring_pattern_id, start_time, end_time, capacity)
                        VALUES (?, ?, ?, ?, ?, ?)
                    ");
                    $insertStmt->execute([
                        $tenantId, $pattern['opportunity_id'], $patternId,
                        $shiftStart, $shiftEnd, $pattern['capacity'],
                    ]);
                    $generated++;
                }
            }

            $cursor->modify('+1 day');
        }

        // Update occurrences count
        if ($generated > 0) {
            $db->prepare("
                UPDATE recurring_shift_patterns
                SET occurrences_generated = occurrences_generated + ?, updated_at = NOW()
                WHERE id = ? AND tenant_id = ?
            ")->execute([$generated, $patternId, $tenantId]);
        }

        return $generated;
    }

    /**
     * Process all active patterns for a tenant — called by cron
     *
     * @param int $daysAhead Days of shifts to pre-generate
     * @return array Summary of processing
     */
    public static function processAllPatterns(int $daysAhead = 14): array
    {
        $tenantId = TenantContext::getId();
        $db = Database::getConnection();

        $stmt = $db->prepare("SELECT id FROM recurring_shift_patterns WHERE tenant_id = ? AND is_active = 1");
        $stmt->execute([$tenantId]);
        $patterns = $stmt->fetchAll(\PDO::FETCH_COLUMN);

        $totalGenerated = 0;
        $patternsProcessed = 0;

        foreach ($patterns as $patternId) {
            $generated = self::generateOccurrences((int) $patternId, $daysAhead);
            $totalGenerated += $generated;
            $patternsProcessed++;
        }

        return [
            'patterns_processed' => $patternsProcessed,
            'shifts_generated' => $totalGenerated,
        ];
    }

    /**
     * Get patterns for an opportunity
     *
     * @param int $opportunityId Opportunity ID
     * @return array List of patterns
     */
    public static function getPatternsForOpportunity(int $opportunityId): array
    {
        $tenantId = TenantContext::getId();
        $db = Database::getConnection();

        $stmt = $db->prepare("
            SELECT rsp.*, u.name as creator_name
            FROM recurring_shift_patterns rsp
            LEFT JOIN users u ON rsp.created_by = u.id
            WHERE rsp.opportunity_id = ? AND rsp.tenant_id = ?
            ORDER BY rsp.created_at DESC
        ");
        $stmt->execute([$opportunityId, $tenantId]);

        return array_map(function ($row) {
            $row['days_of_week'] = $row['days_of_week'] ? json_decode($row['days_of_week'], true) : null;
            $row['id'] = (int) $row['id'];
            $row['opportunity_id'] = (int) $row['opportunity_id'];
            $row['capacity'] = (int) $row['capacity'];
            $row['occurrences_generated'] = (int) $row['occurrences_generated'];
            $row['is_active'] = (bool) $row['is_active'];
            return $row;
        }, $stmt->fetchAll(\PDO::FETCH_ASSOC));
    }

    /**
     * Get a single pattern by ID
     *
     * @param int $patternId Pattern ID
     * @return array|null Pattern data or null
     */
    public static function getPattern(int $patternId): ?array
    {
        $tenantId = TenantContext::getId();
        $db = Database::getConnection();

        $stmt = $db->prepare("
            SELECT * FROM recurring_shift_patterns WHERE id = ? AND tenant_id = ?
        ");
        $stmt->execute([$patternId, $tenantId]);
        $pattern = $stmt->fetch(\PDO::FETCH_ASSOC);

        if (!$pattern) {
            return null;
        }

        $pattern['days_of_week'] = $pattern['days_of_week'] ? json_decode($pattern['days_of_week'], true) : null;
        $pattern['id'] = (int) $pattern['id'];
        $pattern['is_active'] = (bool) $pattern['is_active'];
        return $pattern;
    }

    /**
     * Update a recurring pattern
     *
     * @param int $patternId Pattern ID
     * @param array $data Updated fields
     * @return bool Success
     */
    public static function updatePattern(int $patternId, array $data): bool
    {
        self::$errors = [];
        $tenantId = TenantContext::getId();
        $db = Database::getConnection();

        $pattern = self::getPattern($patternId);
        if (!$pattern) {
            self::$errors[] = ['code' => 'NOT_FOUND', 'message' => 'Pattern not found'];
            return false;
        }

        $updates = [];
        $params = [];

        $allowedFields = ['title', 'frequency', 'start_time', 'end_time', 'capacity', 'end_date', 'max_occurrences', 'is_active'];
        foreach ($allowedFields as $field) {
            if (array_key_exists($field, $data)) {
                $updates[] = "`{$field}` = ?";
                $params[] = $data[$field];
            }
        }

        if (array_key_exists('days_of_week', $data)) {
            $updates[] = "`days_of_week` = ?";
            $params[] = is_array($data['days_of_week']) ? json_encode($data['days_of_week']) : $data['days_of_week'];
        }

        if (empty($updates)) {
            return true; // Nothing to update
        }

        $updates[] = "`updated_at` = NOW()";
        $params[] = $patternId;
        $params[] = $tenantId;

        try {
            $sql = "UPDATE recurring_shift_patterns SET " . implode(', ', $updates) . " WHERE id = ? AND tenant_id = ?";
            $db->prepare($sql)->execute($params);
            return true;
        } catch (\Exception $e) {
            error_log("RecurringShiftService::updatePattern error: " . $e->getMessage());
            self::$errors[] = ['code' => 'SERVER_ERROR', 'message' => 'Failed to update pattern'];
            return false;
        }
    }

    /**
     * Deactivate a pattern (soft delete)
     *
     * @param int $patternId Pattern ID
     * @return bool Success
     */
    public static function deactivatePattern(int $patternId): bool
    {
        return self::updatePattern($patternId, ['is_active' => 0]);
    }

    /**
     * Delete future unbooked shifts for a pattern
     *
     * @param int $patternId Pattern ID
     * @return int Number of shifts deleted
     */
    public static function deleteFutureShifts(int $patternId): int
    {
        $tenantId = TenantContext::getId();
        $db = Database::getConnection();

        // Verify pattern belongs to this tenant
        $stmt = $db->prepare("SELECT id FROM recurring_shift_patterns WHERE id = ? AND tenant_id = ?");
        $stmt->execute([$patternId, $tenantId]);
        if (!$stmt->fetch()) {
            return 0;
        }

        // Only delete shifts that haven't started and have no signups
        $stmt = $db->prepare("
            DELETE vs FROM vol_shifts vs
            LEFT JOIN vol_applications va ON vs.id = va.shift_id
            WHERE vs.recurring_pattern_id = ?
              AND vs.start_time > NOW()
              AND va.id IS NULL
              AND vs.tenant_id = ?
        ");
        $stmt->execute([$patternId, $tenantId]);

        return $stmt->rowCount();
    }

    /**
     * Check if cursor date falls on a correct biweekly week
     */
    private static function isCorrectBiweeklyWeek(\DateTime $startDate, \DateTime $cursor): bool
    {
        $startWeek = (int) $startDate->format('W');
        $cursorWeek = (int) $cursor->format('W');
        $startYear = (int) $startDate->format('o');
        $cursorYear = (int) $cursor->format('o');

        // Calculate total weeks difference
        $weeksDiff = (($cursorYear - $startYear) * 52) + ($cursorWeek - $startWeek);

        return $weeksDiff % 2 === 0;
    }
}
