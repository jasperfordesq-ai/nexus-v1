<?php
// Copyright (c) 2024-2026 Jasper Ford
// SPDX-License-Identifier: AGPL-3.0-or-later
// Author: Jasper Ford
// See NOTICE file for attribution and acknowledgements.

namespace Nexus\Services;

use Nexus\Core\Database;
use Nexus\Core\TenantContext;
use Nexus\Models\OrgMember;

/**
 * RecurringShiftService - Manages recurring shift patterns for volunteering.
 *
 * Creates and manages recurring shift patterns that auto-generate vol_shifts.
 * Cron runs daily to generate upcoming shift occurrences from active patterns.
 */
class RecurringShiftService
{
    private static array $errors = [];
    private static array $columnCache = [];

    public static function getErrors(): array
    {
        return self::$errors;
    }

    /**
     * Create a new recurring shift pattern.
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

        if (!self::opportunityExists($opportunityId)) {
            self::$errors[] = ['code' => 'NOT_FOUND', 'message' => 'Opportunity not found'];
            return null;
        }

        if (!self::canManageOpportunity($opportunityId, $createdBy)) {
            self::$errors[] = ['code' => 'FORBIDDEN', 'message' => 'You do not have permission to manage recurring shifts for this opportunity'];
            return null;
        }

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

        $capacity = max(1, (int)($data['capacity'] ?? 1));
        $startDate = $data['start_date'] ?? date('Y-m-d');
        $endDate = $data['end_date'] ?? null;
        $maxOccurrences = isset($data['max_occurrences']) ? (int)$data['max_occurrences'] : null;
        $title = $data['title'] ?? null;

        try {
            $db = Database::getConnection();

            $columns = ['tenant_id', 'opportunity_id', 'created_by', 'frequency', 'days_of_week', 'start_time', 'end_time', 'is_active'];
            $values = [$tenantId, $opportunityId, $createdBy, $data['frequency'], $daysOfWeek, $data['start_time'], $data['end_time'], 1];

            if (self::hasColumn('recurring_shift_patterns', 'title')) {
                $columns[] = 'title';
                $values[] = $title;
            }

            if (self::hasColumn('recurring_shift_patterns', 'capacity')) {
                $columns[] = 'capacity';
                $values[] = $capacity;
            } elseif (self::hasColumn('recurring_shift_patterns', 'spots_per_shift')) {
                $columns[] = 'spots_per_shift';
                $values[] = $capacity;
            }

            if (self::hasColumn('recurring_shift_patterns', 'start_date')) {
                $columns[] = 'start_date';
                $values[] = $startDate;
            }

            if (self::hasColumn('recurring_shift_patterns', 'end_date')) {
                $columns[] = 'end_date';
                $values[] = $endDate;
            } elseif (self::hasColumn('recurring_shift_patterns', 'generate_until')) {
                $columns[] = 'generate_until';
                $values[] = $endDate;
            }

            if (self::hasColumn('recurring_shift_patterns', 'max_occurrences')) {
                $columns[] = 'max_occurrences';
                $values[] = $maxOccurrences;
            }

            if (self::hasColumn('recurring_shift_patterns', 'occurrences_generated')) {
                $columns[] = 'occurrences_generated';
                $values[] = 0;
            }

            $columnSql = implode(', ', $columns);
            $placeholders = implode(', ', array_fill(0, count($columns), '?'));
            $stmt = $db->prepare("INSERT INTO recurring_shift_patterns ({$columnSql}) VALUES ({$placeholders})");
            $stmt->execute($values);

            $patternId = (int)$db->lastInsertId();

            // Generate initial batch of shifts (next 14 days).
            self::generateOccurrences($patternId, 14);

            return $patternId;
        } catch (\Throwable $e) {
            error_log('RecurringShiftService::createPattern error: ' . $e->getMessage());
            self::$errors[] = ['code' => 'SERVER_ERROR', 'message' => 'Failed to create recurring pattern'];
            return null;
        }
    }

    /**
     * Generate shift occurrences from a pattern for the next N days.
     *
     * @param int $patternId Pattern ID
     * @param int $daysAhead Number of days ahead to generate
     * @return int Number of shifts generated
     */
    public static function generateOccurrences(int $patternId, int $daysAhead = 14): int
    {
        self::$errors = [];
        $tenantId = TenantContext::getId();
        $db = Database::getConnection();

        $stmt = $db->prepare('SELECT * FROM recurring_shift_patterns WHERE id = ? AND is_active = 1 AND tenant_id = ?');
        $stmt->execute([$patternId, $tenantId]);
        $pattern = $stmt->fetch(\PDO::FETCH_ASSOC);

        if (!$pattern) {
            return 0;
        }

        $maxOccurrences = isset($pattern['max_occurrences']) ? (int)$pattern['max_occurrences'] : null;
        $occurrencesGenerated = isset($pattern['occurrences_generated']) ? (int)$pattern['occurrences_generated'] : 0;

        if ($maxOccurrences && $occurrencesGenerated >= $maxOccurrences) {
            return 0;
        }

        $rawEndDate = $pattern['end_date'] ?? ($pattern['generate_until'] ?? null);
        $rawStartDate = $pattern['start_date'] ?? date('Y-m-d');
        $endDate = $rawEndDate ? new \DateTime($rawEndDate) : null;
        $startDate = new \DateTime($rawStartDate);
        $today = new \DateTime('today');
        $generateUntil = new \DateTime("+{$daysAhead} days");

        if ($endDate && $generateUntil > $endDate) {
            $generateUntil = $endDate;
        }

        $cursor = $startDate > $today ? clone $startDate : clone $today;

        $daysOfWeek = $pattern['days_of_week'] ? json_decode($pattern['days_of_week'], true) : null;
        $generated = 0;
        $remainingOccurrences = $maxOccurrences
            ? $maxOccurrences - $occurrencesGenerated
            : PHP_INT_MAX;

        $hasRecurringPatternIdOnShift = self::hasColumn('vol_shifts', 'recurring_pattern_id');
        $patternCapacity = isset($pattern['capacity'])
            ? (int)$pattern['capacity']
            : (isset($pattern['spots_per_shift']) ? (int)$pattern['spots_per_shift'] : 1);
        if ($patternCapacity < 1) {
            $patternCapacity = 1;
        }

        while ($cursor <= $generateUntil && $generated < $remainingOccurrences) {
            $dayOfWeek = (int)$cursor->format('w'); // 0=Sun, 6=Sat

            $shouldGenerate = match ($pattern['frequency']) {
                'daily' => true,
                'weekly' => $daysOfWeek && in_array($dayOfWeek, $daysOfWeek, true),
                'biweekly' => $daysOfWeek && in_array($dayOfWeek, $daysOfWeek, true)
                    && self::isCorrectBiweeklyWeek($startDate, $cursor),
                'monthly' => (int)$cursor->format('j') === (int)$startDate->format('j'),
                default => false,
            };

            if ($shouldGenerate) {
                $shiftDate = $cursor->format('Y-m-d');
                $shiftStart = $shiftDate . ' ' . $pattern['start_time'];
                $shiftEnd = $shiftDate . ' ' . $pattern['end_time'];

                if ($hasRecurringPatternIdOnShift) {
                    $existsStmt = $db->prepare('SELECT id FROM vol_shifts WHERE opportunity_id = ? AND recurring_pattern_id = ? AND start_time = ? AND end_time = ? AND tenant_id = ?');
                    $existsStmt->execute([$pattern['opportunity_id'], $patternId, $shiftStart, $shiftEnd, $tenantId]);
                } else {
                    $existsStmt = $db->prepare('SELECT id FROM vol_shifts WHERE opportunity_id = ? AND start_time = ? AND end_time = ? AND tenant_id = ?');
                    $existsStmt->execute([$pattern['opportunity_id'], $shiftStart, $shiftEnd, $tenantId]);
                }

                if (!$existsStmt->fetch()) {
                    if ($hasRecurringPatternIdOnShift) {
                        $insertStmt = $db->prepare('INSERT INTO vol_shifts (tenant_id, opportunity_id, recurring_pattern_id, start_time, end_time, capacity) VALUES (?, ?, ?, ?, ?, ?)');
                        $insertStmt->execute([$tenantId, $pattern['opportunity_id'], $patternId, $shiftStart, $shiftEnd, $patternCapacity]);
                    } else {
                        $insertStmt = $db->prepare('INSERT INTO vol_shifts (tenant_id, opportunity_id, start_time, end_time, capacity) VALUES (?, ?, ?, ?, ?)');
                        $insertStmt->execute([$tenantId, $pattern['opportunity_id'], $shiftStart, $shiftEnd, $patternCapacity]);
                    }
                    $generated++;
                }
            }

            $cursor->modify('+1 day');
        }

        if ($generated > 0 && self::hasColumn('recurring_shift_patterns', 'occurrences_generated')) {
            $updates = ['occurrences_generated = occurrences_generated + ?'];
            if (self::hasColumn('recurring_shift_patterns', 'updated_at')) {
                $updates[] = 'updated_at = NOW()';
            }

            $sql = 'UPDATE recurring_shift_patterns SET ' . implode(', ', $updates) . ' WHERE id = ? AND tenant_id = ?';
            $db->prepare($sql)->execute([$generated, $patternId, $tenantId]);
        }

        return $generated;
    }

    /**
     * Process all active patterns for a tenant - called by cron.
     *
     * @param int $daysAhead Days of shifts to pre-generate
     * @return array Summary of processing
     */
    public static function processAllPatterns(int $daysAhead = 14): array
    {
        $tenantId = TenantContext::getId();
        $db = Database::getConnection();

        $stmt = $db->prepare('SELECT id FROM recurring_shift_patterns WHERE tenant_id = ? AND is_active = 1');
        $stmt->execute([$tenantId]);
        $patterns = $stmt->fetchAll(\PDO::FETCH_COLUMN);

        $totalGenerated = 0;
        $patternsProcessed = 0;

        foreach ($patterns as $patternId) {
            $generated = self::generateOccurrences((int)$patternId, $daysAhead);
            $totalGenerated += $generated;
            $patternsProcessed++;
        }

        return [
            'patterns_processed' => $patternsProcessed,
            'shifts_generated' => $totalGenerated,
        ];
    }

    /**
     * Get patterns for an opportunity.
     *
     * @param int $opportunityId Opportunity ID
     * @param int|null $userId Optional actor for authorization
     * @return array List of patterns
     */
    public static function getPatternsForOpportunity(int $opportunityId, ?int $userId = null): array
    {
        self::$errors = [];

        if (!self::opportunityExists($opportunityId)) {
            self::$errors[] = ['code' => 'NOT_FOUND', 'message' => 'Opportunity not found'];
            return [];
        }

        if ($userId !== null && !self::canManageOpportunity($opportunityId, $userId)) {
            self::$errors[] = ['code' => 'FORBIDDEN', 'message' => 'You do not have permission to view recurring patterns for this opportunity'];
            return [];
        }

        $tenantId = TenantContext::getId();
        $db = Database::getConnection();

        $stmt = $db->prepare('
            SELECT rsp.*, u.name as creator_name
            FROM recurring_shift_patterns rsp
            LEFT JOIN users u ON rsp.created_by = u.id
            WHERE rsp.opportunity_id = ? AND rsp.tenant_id = ?
            ORDER BY rsp.created_at DESC
        ');
        $stmt->execute([$opportunityId, $tenantId]);

        return array_map(static function (array $row): array {
            $row['days_of_week'] = $row['days_of_week'] ? json_decode($row['days_of_week'], true) : null;
            $row['id'] = (int)$row['id'];
            $row['opportunity_id'] = (int)$row['opportunity_id'];

            if (array_key_exists('capacity', $row)) {
                $row['capacity'] = (int)$row['capacity'];
            } elseif (array_key_exists('spots_per_shift', $row)) {
                $row['capacity'] = (int)$row['spots_per_shift'];
            }

            if (array_key_exists('occurrences_generated', $row)) {
                $row['occurrences_generated'] = (int)$row['occurrences_generated'];
            } else {
                $row['occurrences_generated'] = 0;
            }

            $row['is_active'] = (bool)$row['is_active'];
            return $row;
        }, $stmt->fetchAll(\PDO::FETCH_ASSOC));
    }

    /**
     * Get a single pattern by ID.
     *
     * @param int $patternId Pattern ID
     * @return array|null Pattern data or null
     */
    public static function getPattern(int $patternId): ?array
    {
        self::$errors = [];
        $tenantId = TenantContext::getId();
        $db = Database::getConnection();

        $stmt = $db->prepare('SELECT * FROM recurring_shift_patterns WHERE id = ? AND tenant_id = ?');
        $stmt->execute([$patternId, $tenantId]);
        $pattern = $stmt->fetch(\PDO::FETCH_ASSOC);

        if (!$pattern) {
            return null;
        }

        $pattern['days_of_week'] = $pattern['days_of_week'] ? json_decode($pattern['days_of_week'], true) : null;
        $pattern['id'] = (int)$pattern['id'];
        if (array_key_exists('capacity', $pattern)) {
            $pattern['capacity'] = (int)$pattern['capacity'];
        } elseif (array_key_exists('spots_per_shift', $pattern)) {
            $pattern['capacity'] = (int)$pattern['spots_per_shift'];
        }

        if (array_key_exists('occurrences_generated', $pattern)) {
            $pattern['occurrences_generated'] = (int)$pattern['occurrences_generated'];
        } else {
            $pattern['occurrences_generated'] = 0;
        }

        $pattern['is_active'] = (bool)$pattern['is_active'];
        return $pattern;
    }

    /**
     * Update a recurring pattern.
     *
     * @param int $patternId Pattern ID
     * @param array $data Updated fields
     * @param int|null $userId Optional actor for authorization
     * @return bool Success
     */
    public static function updatePattern(int $patternId, array $data, ?int $userId = null): bool
    {
        self::$errors = [];
        $tenantId = TenantContext::getId();
        $db = Database::getConnection();

        $pattern = self::getPattern($patternId);
        if (!$pattern) {
            self::$errors[] = ['code' => 'NOT_FOUND', 'message' => 'Pattern not found'];
            return false;
        }

        if ($userId !== null && !self::canManageOpportunity((int)$pattern['opportunity_id'], $userId)) {
            self::$errors[] = ['code' => 'FORBIDDEN', 'message' => 'You do not have permission to update this recurring pattern'];
            return false;
        }

        $updates = [];
        $params = [];

        if (array_key_exists('title', $data) && self::hasColumn('recurring_shift_patterns', 'title')) {
            $updates[] = '`title` = ?';
            $params[] = $data['title'];
        }

        foreach (['frequency', 'start_time', 'end_time', 'is_active'] as $field) {
            if (array_key_exists($field, $data)) {
                $updates[] = "`{$field}` = ?";
                $params[] = $data[$field];
            }
        }

        if (array_key_exists('capacity', $data)) {
            $capacity = max(1, (int)$data['capacity']);
            if (self::hasColumn('recurring_shift_patterns', 'capacity')) {
                $updates[] = '`capacity` = ?';
                $params[] = $capacity;
            } elseif (self::hasColumn('recurring_shift_patterns', 'spots_per_shift')) {
                $updates[] = '`spots_per_shift` = ?';
                $params[] = $capacity;
            }
        }

        if (array_key_exists('start_date', $data) && self::hasColumn('recurring_shift_patterns', 'start_date')) {
            $updates[] = '`start_date` = ?';
            $params[] = $data['start_date'];
        }

        if (array_key_exists('end_date', $data)) {
            if (self::hasColumn('recurring_shift_patterns', 'end_date')) {
                $updates[] = '`end_date` = ?';
                $params[] = $data['end_date'];
            } elseif (self::hasColumn('recurring_shift_patterns', 'generate_until')) {
                $updates[] = '`generate_until` = ?';
                $params[] = $data['end_date'];
            }
        }

        if (array_key_exists('max_occurrences', $data) && self::hasColumn('recurring_shift_patterns', 'max_occurrences')) {
            $updates[] = '`max_occurrences` = ?';
            $params[] = $data['max_occurrences'];
        }

        if (array_key_exists('days_of_week', $data)) {
            $updates[] = '`days_of_week` = ?';
            $params[] = is_array($data['days_of_week']) ? json_encode($data['days_of_week']) : $data['days_of_week'];
        }

        if (empty($updates)) {
            return true;
        }

        if (self::hasColumn('recurring_shift_patterns', 'updated_at')) {
            $updates[] = '`updated_at` = NOW()';
        }

        $params[] = $patternId;
        $params[] = $tenantId;

        try {
            $sql = 'UPDATE recurring_shift_patterns SET ' . implode(', ', $updates) . ' WHERE id = ? AND tenant_id = ?';
            $db->prepare($sql)->execute($params);
            return true;
        } catch (\Throwable $e) {
            error_log('RecurringShiftService::updatePattern error: ' . $e->getMessage());
            self::$errors[] = ['code' => 'SERVER_ERROR', 'message' => 'Failed to update pattern'];
            return false;
        }
    }

    /**
     * Deactivate a pattern (soft delete).
     *
     * @param int $patternId Pattern ID
     * @param int|null $userId Optional actor for authorization
     * @return bool Success
     */
    public static function deactivatePattern(int $patternId, ?int $userId = null): bool
    {
        return self::updatePattern($patternId, ['is_active' => 0], $userId);
    }

    /**
     * Delete future unbooked shifts for a pattern.
     *
     * @param int $patternId Pattern ID
     * @param int|null $userId Optional actor for authorization
     * @return int Number of shifts deleted
     */
    public static function deleteFutureShifts(int $patternId, ?int $userId = null): int
    {
        self::$errors = [];
        $tenantId = TenantContext::getId();
        $db = Database::getConnection();

        $stmt = $db->prepare('SELECT id, opportunity_id FROM recurring_shift_patterns WHERE id = ? AND tenant_id = ?');
        $stmt->execute([$patternId, $tenantId]);
        $pattern = $stmt->fetch(\PDO::FETCH_ASSOC);

        if (!$pattern) {
            return 0;
        }

        if ($userId !== null && !self::canManageOpportunity((int)$pattern['opportunity_id'], $userId)) {
            self::$errors[] = ['code' => 'FORBIDDEN', 'message' => 'You do not have permission to remove shifts for this recurring pattern'];
            return 0;
        }

        if (!self::hasColumn('vol_shifts', 'recurring_pattern_id')) {
            return 0;
        }

        $stmt = $db->prepare('
            DELETE vs FROM vol_shifts vs
            LEFT JOIN vol_applications va ON vs.id = va.shift_id AND va.tenant_id = ?
            WHERE vs.recurring_pattern_id = ?
              AND vs.start_time > NOW()
              AND va.id IS NULL
              AND vs.tenant_id = ?
        ');
        $stmt->execute([$tenantId, $patternId, $tenantId]);

        return $stmt->rowCount();
    }

    /**
     * Check if cursor date falls on a correct biweekly week.
     */
    private static function isCorrectBiweeklyWeek(\DateTime $startDate, \DateTime $cursor): bool
    {
        $startWeek = (int)$startDate->format('W');
        $cursorWeek = (int)$cursor->format('W');
        $startYear = (int)$startDate->format('o');
        $cursorYear = (int)$cursor->format('o');

        $weeksDiff = (($cursorYear - $startYear) * 52) + ($cursorWeek - $startWeek);

        return $weeksDiff % 2 === 0;
    }

    private static function canManageOpportunity(int $opportunityId, int $userId): bool
    {
        $tenantId = TenantContext::getId();
        $db = Database::getConnection();

        try {
            $stmt = $db->prepare('
                SELECT opp.organization_id, org.user_id AS org_owner_id
                FROM vol_opportunities opp
                JOIN vol_organizations org ON opp.organization_id = org.id
                WHERE opp.id = ? AND opp.tenant_id = ? AND org.tenant_id = ?
                LIMIT 1
            ');
            $stmt->execute([$opportunityId, $tenantId, $tenantId]);
            $row = $stmt->fetch(\PDO::FETCH_ASSOC);

            if (!$row) {
                return false;
            }

            if ((int)$row['org_owner_id'] === $userId) {
                return true;
            }

            if (OrgMember::isAdmin((int)$row['organization_id'], $userId)) {
                return true;
            }

            return self::isTenantAdmin($userId);
        } catch (\Throwable $e) {
            return false;
        }
    }

    private static function opportunityExists(int $opportunityId): bool
    {
        try {
            $db = Database::getConnection();
            $stmt = $db->prepare('SELECT id FROM vol_opportunities WHERE id = ? AND tenant_id = ? LIMIT 1');
            $stmt->execute([$opportunityId, TenantContext::getId()]);
            return (bool)$stmt->fetch(\PDO::FETCH_ASSOC);
        } catch (\Throwable $e) {
            return false;
        }
    }

    private static function isTenantAdmin(int $userId): bool
    {
        try {
            $db = Database::getConnection();
            $stmt = $db->prepare('SELECT role FROM users WHERE id = ? AND tenant_id = ?');
            $stmt->execute([$userId, TenantContext::getId()]);
            $role = $stmt->fetchColumn();
            return in_array($role, ['admin', 'tenant_admin', 'tenant_super_admin', 'super_admin'], true);
        } catch (\Throwable $e) {
            return false;
        }
    }

    private static function hasColumn(string $table, string $column): bool
    {
        $cacheKey = "{$table}.{$column}";
        if (array_key_exists($cacheKey, self::$columnCache)) {
            return self::$columnCache[$cacheKey];
        }

        try {
            $db = Database::getConnection();
            $stmt = $db->prepare('
                SELECT COUNT(*)
                FROM information_schema.COLUMNS
                WHERE TABLE_SCHEMA = DATABASE()
                  AND TABLE_NAME = ?
                  AND COLUMN_NAME = ?
            ');
            $stmt->execute([$table, $column]);
            self::$columnCache[$cacheKey] = ((int)$stmt->fetchColumn()) > 0;
        } catch (\Throwable $e) {
            self::$columnCache[$cacheKey] = false;
        }

        return self::$columnCache[$cacheKey];
    }
}
