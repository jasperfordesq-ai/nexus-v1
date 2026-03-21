<?php
// Copyright © 2024–2026 Jasper Ford
// SPDX-License-Identifier: AGPL-3.0-or-later
// Author: Jasper Ford
// See NOTICE file for attribution and acknowledgements.

namespace App\Services;

use App\Core\TenantContext;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;

/**
 * RecurringShiftService — Manages recurring shift patterns for volunteer opportunities.
 *
 * Handles CRUD for recurring_shift_patterns and automatic generation of
 * vol_shifts instances from those patterns.
 */
class RecurringShiftService
{
    /** @var array<string> */
    private array $errors = [];

    public function __construct()
    {
    }

    /**
     * Get accumulated errors from the last operation.
     */
    public function getErrors(): array
    {
        return $this->errors;
    }

    /**
     * Create a new recurring shift pattern.
     */
    public function createPattern(int $opportunityId, int $createdBy, array $data): ?int
    {
        $this->errors = [];
        $tenantId = TenantContext::getId();

        // Validate opportunity belongs to tenant and user has access
        $opp = DB::selectOne(
            "SELECT id, created_by FROM vol_opportunities WHERE id = ? AND tenant_id = ?",
            [$opportunityId, $tenantId]
        );

        if (!$opp) {
            $this->errors[] = 'Opportunity not found';
            return null;
        }

        // Validate required fields
        $frequency = $data['frequency'] ?? 'weekly';
        $validFrequencies = ['daily', 'weekly', 'biweekly', 'monthly'];
        if (!in_array($frequency, $validFrequencies, true)) {
            $this->errors[] = 'Invalid frequency. Must be one of: ' . implode(', ', $validFrequencies);
            return null;
        }

        $startTime = $data['start_time'] ?? null;
        $endTime = $data['end_time'] ?? null;
        if (!$startTime || !$endTime) {
            $this->errors[] = 'Start time and end time are required';
            return null;
        }

        $startDate = $data['start_date'] ?? date('Y-m-d');

        $daysOfWeek = $data['days_of_week'] ?? null;
        if (is_array($daysOfWeek)) {
            $daysOfWeek = json_encode($daysOfWeek);
        }

        try {
            DB::insert(
                "INSERT INTO recurring_shift_patterns
                 (tenant_id, opportunity_id, created_by, title, frequency, days_of_week,
                  start_time, end_time, spots_per_shift, capacity, start_date, end_date,
                  max_occurrences, is_active, created_at, updated_at)
                 VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, 1, NOW(), NOW())",
                [
                    $tenantId,
                    $opportunityId,
                    $createdBy,
                    $data['title'] ?? null,
                    $frequency,
                    $daysOfWeek,
                    $startTime,
                    $endTime,
                    (int) ($data['spots_per_shift'] ?? 1),
                    (int) ($data['capacity'] ?? 1),
                    $startDate,
                    $data['end_date'] ?? null,
                    isset($data['max_occurrences']) ? (int) $data['max_occurrences'] : null,
                ]
            );

            $patternId = (int) DB::getPdo()->lastInsertId();

            Log::info('[RecurringShift] Pattern created', [
                'id' => $patternId,
                'opportunity' => $opportunityId,
                'frequency' => $frequency,
            ]);

            return $patternId;
        } catch (\Exception $e) {
            Log::error('[RecurringShift] createPattern failed', ['error' => $e->getMessage()]);
            $this->errors[] = 'Failed to create recurring shift pattern';
            return null;
        }
    }

    /**
     * Generate shift occurrences from a pattern for the next N days.
     */
    public function generateOccurrences(int $patternId, int $daysAhead = 14): int
    {
        $this->errors = [];
        $tenantId = TenantContext::getId();

        try {
            $pattern = DB::selectOne(
                "SELECT * FROM recurring_shift_patterns WHERE id = ? AND tenant_id = ? AND is_active = 1",
                [$patternId, $tenantId]
            );

            if (!$pattern) {
                $this->errors[] = 'Pattern not found or inactive';
                return 0;
            }

            $daysOfWeek = is_string($pattern->days_of_week)
                ? (json_decode($pattern->days_of_week, true) ?: [])
                : [];

            $startDate = new \DateTime(max($pattern->start_date, date('Y-m-d')));
            $endDate = new \DateTime(date('Y-m-d', strtotime("+{$daysAhead} days")));

            // Respect pattern end_date
            if ($pattern->end_date) {
                $patternEnd = new \DateTime($pattern->end_date);
                if ($patternEnd < $endDate) {
                    $endDate = $patternEnd;
                }
            }

            // Respect max_occurrences
            $maxOccurrences = $pattern->max_occurrences ? (int) $pattern->max_occurrences : PHP_INT_MAX;
            $currentGenerated = (int) $pattern->occurrences_generated;

            $generated = 0;
            $current = clone $startDate;

            while ($current <= $endDate && ($currentGenerated + $generated) < $maxOccurrences) {
                $dayOfWeek = (int) $current->format('N'); // 1=Monday, 7=Sunday

                $shouldGenerate = false;

                switch ($pattern->frequency) {
                    case 'daily':
                        $shouldGenerate = true;
                        break;
                    case 'weekly':
                        $shouldGenerate = empty($daysOfWeek) || in_array($dayOfWeek, $daysOfWeek, true);
                        break;
                    case 'biweekly':
                        $weekDiff = (int) $startDate->diff($current)->days / 7;
                        $shouldGenerate = ($weekDiff % 2 === 0) && (empty($daysOfWeek) || in_array($dayOfWeek, $daysOfWeek, true));
                        break;
                    case 'monthly':
                        $shouldGenerate = ($current->format('d') === $startDate->format('d'));
                        break;
                }

                if ($shouldGenerate) {
                    $shiftDate = $current->format('Y-m-d');
                    $shiftStart = $shiftDate . ' ' . $pattern->start_time;
                    $shiftEnd = $shiftDate . ' ' . $pattern->end_time;

                    // Check if shift already exists for this date/pattern
                    $exists = DB::selectOne(
                        "SELECT id FROM vol_shifts
                         WHERE recurring_pattern_id = ? AND tenant_id = ? AND start_time = ?",
                        [$patternId, $tenantId, $shiftStart]
                    );

                    if (!$exists) {
                        DB::insert(
                            "INSERT INTO vol_shifts (tenant_id, opportunity_id, recurring_pattern_id, start_time, end_time, capacity, created_at)
                             VALUES (?, ?, ?, ?, ?, ?, NOW())",
                            [
                                $tenantId,
                                (int) $pattern->opportunity_id,
                                $patternId,
                                $shiftStart,
                                $shiftEnd,
                                (int) $pattern->capacity,
                            ]
                        );
                        $generated++;
                    }
                }

                $current->modify('+1 day');
            }

            // Update occurrences_generated count
            if ($generated > 0) {
                DB::update(
                    "UPDATE recurring_shift_patterns SET occurrences_generated = occurrences_generated + ?, updated_at = NOW() WHERE id = ?",
                    [$generated, $patternId]
                );
            }

            Log::info('[RecurringShift] Occurrences generated', [
                'pattern' => $patternId,
                'generated' => $generated,
            ]);

            return $generated;
        } catch (\Exception $e) {
            Log::error('[RecurringShift] generateOccurrences failed', ['error' => $e->getMessage()]);
            $this->errors[] = 'Failed to generate occurrences';
            return 0;
        }
    }

    /**
     * Process all active patterns for a tenant, generating upcoming occurrences.
     */
    public function processAllPatterns(int $daysAhead = 14): array
    {
        $this->errors = [];
        $tenantId = TenantContext::getId();
        $results = ['processed' => 0, 'generated' => 0, 'errors' => 0];

        try {
            $patterns = DB::select(
                "SELECT id FROM recurring_shift_patterns
                 WHERE tenant_id = ? AND is_active = 1
                   AND (end_date IS NULL OR end_date >= CURDATE())",
                [$tenantId]
            );

            foreach ($patterns as $pattern) {
                try {
                    $generated = $this->generateOccurrences((int) $pattern->id, $daysAhead);
                    $results['generated'] += $generated;
                    $results['processed']++;
                } catch (\Exception $e) {
                    $results['errors']++;
                    Log::error('[RecurringShift] processAllPatterns: pattern failed', [
                        'pattern' => $pattern->id,
                        'error' => $e->getMessage(),
                    ]);
                }
            }

            return $results;
        } catch (\Exception $e) {
            Log::error('[RecurringShift] processAllPatterns failed', ['error' => $e->getMessage()]);
            $this->errors[] = 'Failed to process patterns';
            return $results;
        }
    }

    /**
     * Get recurring patterns for an opportunity.
     */
    public function getPatternsForOpportunity(int $opportunityId, ?int $userId = null): array
    {
        $this->errors = [];
        $tenantId = TenantContext::getId();

        try {
            $rows = DB::select(
                "SELECT rsp.*,
                        CONCAT(COALESCE(u.first_name, ''), ' ', COALESCE(u.last_name, '')) as created_by_name
                 FROM recurring_shift_patterns rsp
                 LEFT JOIN users u ON rsp.created_by = u.id
                 WHERE rsp.opportunity_id = ? AND rsp.tenant_id = ?
                 ORDER BY rsp.created_at DESC",
                [$opportunityId, $tenantId]
            );

            return array_map(function ($row) {
                $daysOfWeek = is_string($row->days_of_week)
                    ? (json_decode($row->days_of_week, true) ?: [])
                    : [];

                return [
                    'id' => (int) $row->id,
                    'opportunity_id' => (int) $row->opportunity_id,
                    'title' => $row->title,
                    'frequency' => $row->frequency,
                    'days_of_week' => $daysOfWeek,
                    'start_time' => $row->start_time,
                    'end_time' => $row->end_time,
                    'spots_per_shift' => (int) $row->spots_per_shift,
                    'capacity' => (int) $row->capacity,
                    'start_date' => $row->start_date,
                    'end_date' => $row->end_date,
                    'max_occurrences' => $row->max_occurrences ? (int) $row->max_occurrences : null,
                    'occurrences_generated' => (int) $row->occurrences_generated,
                    'is_active' => (bool) $row->is_active,
                    'created_by' => (int) $row->created_by,
                    'created_by_name' => $row->created_by_name ? trim($row->created_by_name) : null,
                    'created_at' => $row->created_at,
                    'updated_at' => $row->updated_at,
                ];
            }, $rows);
        } catch (\Exception $e) {
            Log::error('[RecurringShift] getPatternsForOpportunity failed', ['error' => $e->getMessage()]);
            $this->errors[] = 'Failed to load patterns';
            return [];
        }
    }

    /**
     * Get a single pattern by ID.
     */
    public function getPattern(int $patternId): ?array
    {
        $tenantId = TenantContext::getId();

        try {
            $row = DB::selectOne(
                "SELECT rsp.*,
                        CONCAT(COALESCE(u.first_name, ''), ' ', COALESCE(u.last_name, '')) as created_by_name
                 FROM recurring_shift_patterns rsp
                 LEFT JOIN users u ON rsp.created_by = u.id
                 WHERE rsp.id = ? AND rsp.tenant_id = ?",
                [$patternId, $tenantId]
            );

            if (!$row) {
                return null;
            }

            $daysOfWeek = is_string($row->days_of_week)
                ? (json_decode($row->days_of_week, true) ?: [])
                : [];

            return [
                'id' => (int) $row->id,
                'opportunity_id' => (int) $row->opportunity_id,
                'title' => $row->title,
                'frequency' => $row->frequency,
                'days_of_week' => $daysOfWeek,
                'start_time' => $row->start_time,
                'end_time' => $row->end_time,
                'spots_per_shift' => (int) $row->spots_per_shift,
                'capacity' => (int) $row->capacity,
                'start_date' => $row->start_date,
                'end_date' => $row->end_date,
                'max_occurrences' => $row->max_occurrences ? (int) $row->max_occurrences : null,
                'occurrences_generated' => (int) $row->occurrences_generated,
                'is_active' => (bool) $row->is_active,
                'created_by' => (int) $row->created_by,
                'created_by_name' => $row->created_by_name ? trim($row->created_by_name) : null,
                'created_at' => $row->created_at,
                'updated_at' => $row->updated_at,
            ];
        } catch (\Exception $e) {
            Log::error('[RecurringShift] getPattern failed', ['error' => $e->getMessage()]);
            return null;
        }
    }

    /**
     * Update a recurring shift pattern.
     */
    public function updatePattern(int $patternId, array $data, int $userId): bool
    {
        $this->errors = [];
        $tenantId = TenantContext::getId();

        $pattern = DB::selectOne(
            "SELECT id, created_by FROM recurring_shift_patterns WHERE id = ? AND tenant_id = ?",
            [$patternId, $tenantId]
        );

        if (!$pattern) {
            $this->errors[] = 'Pattern not found';
            return false;
        }

        $updates = [];
        $params = [];

        $allowedFields = [
            'title' => 'string',
            'frequency' => 'string',
            'start_time' => 'string',
            'end_time' => 'string',
            'spots_per_shift' => 'int',
            'capacity' => 'int',
            'start_date' => 'string',
            'end_date' => 'string',
            'max_occurrences' => 'int',
        ];

        foreach ($allowedFields as $field => $type) {
            if (array_key_exists($field, $data)) {
                if ($field === 'frequency') {
                    $validFrequencies = ['daily', 'weekly', 'biweekly', 'monthly'];
                    if (!in_array($data[$field], $validFrequencies, true)) {
                        $this->errors[] = 'Invalid frequency';
                        return false;
                    }
                }

                $updates[] = "{$field} = ?";
                $params[] = $type === 'int' ? (int) $data[$field] : $data[$field];
            }
        }

        if (array_key_exists('days_of_week', $data)) {
            $updates[] = 'days_of_week = ?';
            $params[] = is_array($data['days_of_week']) ? json_encode($data['days_of_week']) : $data['days_of_week'];
        }

        if (empty($updates)) {
            return true;
        }

        try {
            $params[] = $patternId;
            $params[] = $tenantId;
            DB::update(
                "UPDATE recurring_shift_patterns SET " . implode(', ', $updates) . ", updated_at = NOW() WHERE id = ? AND tenant_id = ?",
                $params
            );

            Log::info('[RecurringShift] Pattern updated', ['id' => $patternId, 'by' => $userId]);
            return true;
        } catch (\Exception $e) {
            Log::error('[RecurringShift] updatePattern failed', ['error' => $e->getMessage()]);
            $this->errors[] = 'Failed to update pattern';
            return false;
        }
    }

    /**
     * Deactivate a recurring shift pattern.
     */
    public function deactivatePattern(int $patternId, int $userId): bool
    {
        $this->errors = [];
        $tenantId = TenantContext::getId();

        try {
            $updated = DB::update(
                "UPDATE recurring_shift_patterns SET is_active = 0, updated_at = NOW() WHERE id = ? AND tenant_id = ?",
                [$patternId, $tenantId]
            );

            if ($updated === 0) {
                $this->errors[] = 'Pattern not found';
                return false;
            }

            Log::info('[RecurringShift] Pattern deactivated', ['id' => $patternId, 'by' => $userId]);
            return true;
        } catch (\Exception $e) {
            Log::error('[RecurringShift] deactivatePattern failed', ['error' => $e->getMessage()]);
            $this->errors[] = 'Failed to deactivate pattern';
            return false;
        }
    }

    /**
     * Delete future (unstarted) shifts generated by a pattern.
     * Returns the number of shifts deleted.
     */
    public function deleteFutureShifts(int $patternId, int $userId): int
    {
        $this->errors = [];
        $tenantId = TenantContext::getId();

        try {
            $deleted = DB::delete(
                "DELETE FROM vol_shifts
                 WHERE recurring_pattern_id = ? AND tenant_id = ? AND start_time > NOW()",
                [$patternId, $tenantId]
            );

            Log::info('[RecurringShift] Future shifts deleted', [
                'pattern' => $patternId,
                'deleted' => $deleted,
                'by' => $userId,
            ]);

            return $deleted;
        } catch (\Exception $e) {
            Log::error('[RecurringShift] deleteFutureShifts failed', ['error' => $e->getMessage()]);
            $this->errors[] = 'Failed to delete future shifts';
            return 0;
        }
    }
}
