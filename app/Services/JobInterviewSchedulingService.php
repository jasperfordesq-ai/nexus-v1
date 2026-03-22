<?php
// Copyright © 2024–2026 Jasper Ford
// SPDX-License-Identifier: AGPL-3.0-or-later
// Author: Jasper Ford
// See NOTICE file for attribution and acknowledgements.

namespace App\Services;

use App\Core\TenantContext;
use App\Models\JobInterviewSlot;
use App\Models\JobVacancy;
use Carbon\Carbon;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;

/**
 * JobInterviewSchedulingService — Manages interview self-scheduling slots.
 *
 * Employers create available time slots; candidates book from those slots.
 * All queries are tenant-scoped.
 */
class JobInterviewSchedulingService
{
    /** @var array Collected errors from the last operation */
    private array $errors = [];

    public function getErrors(): array
    {
        return $this->errors;
    }

    /**
     * Create individual interview slots for a job.
     *
     * @param int $jobId
     * @param int $employerId
     * @param array $slots Each: { start, end, type?, meeting_link?, location?, notes? }
     * @param int $tenantId
     * @return array Created slot records
     */
    public function createSlots(int $jobId, int $employerId, array $slots, int $tenantId): array
    {
        $this->errors = [];

        $vacancy = JobVacancy::where('id', $jobId)
            ->where('tenant_id', $tenantId)
            ->first();

        if (!$vacancy) {
            $this->errors[] = ['code' => 'RESOURCE_NOT_FOUND', 'message' => 'Job vacancy not found'];
            return [];
        }

        if ((int) $vacancy->user_id !== $employerId) {
            $this->errors[] = ['code' => 'RESOURCE_FORBIDDEN', 'message' => 'Only the job owner can create interview slots'];
            return [];
        }

        $created = [];

        foreach ($slots as $slot) {
            if (empty($slot['start']) || empty($slot['end'])) {
                continue;
            }

            $record = JobInterviewSlot::create([
                'tenant_id' => $tenantId,
                'job_id' => $jobId,
                'employer_user_id' => $employerId,
                'slot_start' => $slot['start'],
                'slot_end' => $slot['end'],
                'interview_type' => $slot['type'] ?? 'video',
                'meeting_link' => $slot['meeting_link'] ?? null,
                'location' => $slot['location'] ?? null,
                'notes' => $slot['notes'] ?? null,
            ]);

            $created[] = $record->toArray();
        }

        return $created;
    }

    /**
     * Get available (unbooked) slots for a job.
     *
     * @param int $jobId
     * @param int $tenantId
     * @return array
     */
    public function getAvailableSlots(int $jobId, int $tenantId): array
    {
        return JobInterviewSlot::where('tenant_id', $tenantId)
            ->where('job_id', $jobId)
            ->where('slot_start', '>', now())
            ->orderBy('slot_start', 'asc')
            ->get()
            ->toArray();
    }

    /**
     * Book an available slot for a candidate.
     *
     * @param int $slotId
     * @param int $candidateUserId
     * @param int $tenantId
     * @return array|null Slot data on success, null on failure
     */
    public function bookSlot(int $slotId, int $candidateUserId, int $tenantId): ?array
    {
        $this->errors = [];

        $slot = JobInterviewSlot::where('id', $slotId)
            ->where('tenant_id', $tenantId)
            ->first();

        if (!$slot) {
            $this->errors[] = ['code' => 'RESOURCE_NOT_FOUND', 'message' => 'Interview slot not found'];
            return null;
        }

        if ($slot->is_booked) {
            $this->errors[] = ['code' => 'RESOURCE_CONFLICT', 'message' => 'This slot has already been booked'];
            return null;
        }

        if ($slot->slot_start <= now()) {
            $this->errors[] = ['code' => 'VALIDATION_ERROR', 'message' => 'This slot has already passed'];
            return null;
        }

        // Prevent employer from booking their own slot
        if ((int) $slot->employer_user_id === $candidateUserId) {
            $this->errors[] = ['code' => 'RESOURCE_FORBIDDEN', 'message' => 'You cannot book your own interview slot'];
            return null;
        }

        $slot->update([
            'is_booked' => true,
            'booked_by_user_id' => $candidateUserId,
            'booked_at' => now(),
        ]);

        return $slot->fresh()->toArray();
    }

    /**
     * Cancel a slot booking and reopen it.
     *
     * @param int $slotId
     * @param int $tenantId
     * @return bool
     */
    public function cancelSlotBooking(int $slotId, int $tenantId): bool
    {
        $this->errors = [];

        $slot = JobInterviewSlot::where('id', $slotId)
            ->where('tenant_id', $tenantId)
            ->first();

        if (!$slot) {
            $this->errors[] = ['code' => 'RESOURCE_NOT_FOUND', 'message' => 'Interview slot not found'];
            return false;
        }

        if (!$slot->is_booked) {
            $this->errors[] = ['code' => 'VALIDATION_ERROR', 'message' => 'This slot is not booked'];
            return false;
        }

        $slot->update([
            'is_booked' => false,
            'booked_by_user_id' => null,
            'booked_at' => null,
        ]);

        return true;
    }

    /**
     * Delete a slot entirely (employer action).
     *
     * @param int $slotId
     * @param int $tenantId
     * @return bool
     */
    public function deleteSlot(int $slotId, int $tenantId): bool
    {
        $this->errors = [];

        $slot = JobInterviewSlot::where('id', $slotId)
            ->where('tenant_id', $tenantId)
            ->first();

        if (!$slot) {
            $this->errors[] = ['code' => 'RESOURCE_NOT_FOUND', 'message' => 'Interview slot not found'];
            return false;
        }

        $slot->delete();

        return true;
    }

    /**
     * Bulk-create slots for a date range with per-day time windows.
     *
     * @param int $jobId
     * @param int $employerId
     * @param string $dateFrom Y-m-d
     * @param string $dateTo Y-m-d
     * @param int $durationMinutes Slot duration (e.g. 30, 45, 60)
     * @param array $dayConfig e.g. { "monday": { "start": "09:00", "end": "17:00" }, ... }
     * @param int $tenantId
     * @return array Created slot records
     */
    public function bulkCreateSlots(
        int $jobId,
        int $employerId,
        string $dateFrom,
        string $dateTo,
        int $durationMinutes,
        array $dayConfig,
        int $tenantId
    ): array {
        $this->errors = [];

        $vacancy = JobVacancy::where('id', $jobId)
            ->where('tenant_id', $tenantId)
            ->first();

        if (!$vacancy) {
            $this->errors[] = ['code' => 'RESOURCE_NOT_FOUND', 'message' => 'Job vacancy not found'];
            return [];
        }

        if ((int) $vacancy->user_id !== $employerId) {
            $this->errors[] = ['code' => 'RESOURCE_FORBIDDEN', 'message' => 'Only the job owner can create interview slots'];
            return [];
        }

        $durationMinutes = max(15, min(180, $durationMinutes));
        $slots = [];

        $start = Carbon::parse($dateFrom)->startOfDay();
        $end = Carbon::parse($dateTo)->endOfDay();

        $dayNames = ['sunday', 'monday', 'tuesday', 'wednesday', 'thursday', 'friday', 'saturday'];

        $current = $start->copy();
        while ($current->lte($end)) {
            $dayName = $dayNames[$current->dayOfWeek];

            if (isset($dayConfig[$dayName]) && !empty($dayConfig[$dayName]['start']) && !empty($dayConfig[$dayName]['end'])) {
                $dayStart = Carbon::parse($current->format('Y-m-d') . ' ' . $dayConfig[$dayName]['start']);
                $dayEnd = Carbon::parse($current->format('Y-m-d') . ' ' . $dayConfig[$dayName]['end']);

                $slotStart = $dayStart->copy();
                while ($slotStart->copy()->addMinutes($durationMinutes)->lte($dayEnd)) {
                    $slotEnd = $slotStart->copy()->addMinutes($durationMinutes);

                    // Only create future slots
                    if ($slotStart->gt(now())) {
                        $slots[] = [
                            'start' => $slotStart->format('Y-m-d H:i:s'),
                            'end' => $slotEnd->format('Y-m-d H:i:s'),
                        ];
                    }

                    $slotStart = $slotEnd;
                }
            }

            $current->addDay();
        }

        if (empty($slots)) {
            return [];
        }

        return $this->createSlots($jobId, $employerId, $slots, $tenantId);
    }
}
