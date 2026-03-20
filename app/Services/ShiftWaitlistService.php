<?php
// Copyright © 2024–2026 Jasper Ford
// SPDX-License-Identifier: AGPL-3.0-or-later
// Author: Jasper Ford
// See NOTICE file for attribution and acknowledgements.

namespace App\Services;

use App\Core\TenantContext;
use App\Models\VolShift;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;

/**
 * ShiftWaitlistService — Laravel DI-based service for shift waitlist operations.
 *
 * Manages waitlist automation for volunteer shifts with tenant scoping.
 */
class ShiftWaitlistService
{
    private array $errors = [];

    public function getErrors(): array
    {
        return $this->errors;
    }

    /**
     * Join the waitlist for a shift.
     *
     * @return int|null Waitlist entry ID or null on failure
     */
    public function join(int $shiftId, int $userId): ?int
    {
        $this->errors = [];
        $tenantId = TenantContext::getId();

        $shift = VolShift::find($shiftId);
        if (! $shift) {
            $this->errors[] = ['code' => 'NOT_FOUND', 'message' => 'Shift not found'];
            return null;
        }

        if ($shift->start_time->isPast()) {
            $this->errors[] = ['code' => 'VALIDATION_ERROR', 'message' => 'This shift has already started'];
            return null;
        }

        // Check if already on waitlist
        $onWaitlist = DB::table('vol_shift_waitlist')
            ->where('shift_id', $shiftId)
            ->where('user_id', $userId)
            ->where('status', 'waiting')
            ->where('tenant_id', $tenantId)
            ->exists();

        if ($onWaitlist) {
            $this->errors[] = ['code' => 'ALREADY_EXISTS', 'message' => 'You are already on the waitlist for this shift'];
            return null;
        }

        // Check if already signed up
        $signedUp = DB::table('vol_applications')
            ->where('shift_id', $shiftId)
            ->where('user_id', $userId)
            ->where('status', 'approved')
            ->where('tenant_id', $tenantId)
            ->exists();

        if ($signedUp) {
            $this->errors[] = ['code' => 'ALREADY_EXISTS', 'message' => 'You are already signed up for this shift'];
            return null;
        }

        try {
            return DB::transaction(function () use ($shiftId, $userId, $tenantId) {
                // Get next position (locked to prevent race condition)
                $nextPos = (int) DB::table('vol_shift_waitlist')
                    ->where('shift_id', $shiftId)
                    ->where('status', 'waiting')
                    ->where('tenant_id', $tenantId)
                    ->lockForUpdate()
                    ->max('position') + 1;

                return DB::table('vol_shift_waitlist')->insertGetId([
                    'tenant_id'  => $tenantId,
                    'shift_id'   => $shiftId,
                    'user_id'    => $userId,
                    'position'   => $nextPos,
                    'status'     => 'waiting',
                    'created_at' => now(),
                ]);
            });
        } catch (\Exception $e) {
            Log::error('ShiftWaitlistService::join error: ' . $e->getMessage());
            $this->errors[] = ['code' => 'SERVER_ERROR', 'message' => 'Failed to join waitlist'];
            return null;
        }
    }

    /**
     * Leave the waitlist for a shift.
     */
    public function leave(int $shiftId, int $userId): bool
    {
        $this->errors = [];
        $tenantId = TenantContext::getId();

        $entry = DB::table('vol_shift_waitlist')
            ->where('shift_id', $shiftId)
            ->where('user_id', $userId)
            ->where('status', 'waiting')
            ->where('tenant_id', $tenantId)
            ->first();

        if (! $entry) {
            $this->errors[] = ['code' => 'NOT_FOUND', 'message' => 'You are not on the waitlist for this shift'];
            return false;
        }

        try {
            // Cancel the entry
            DB::table('vol_shift_waitlist')
                ->where('id', $entry->id)
                ->where('tenant_id', $tenantId)
                ->update(['status' => 'cancelled']);

            // Reorder remaining positions
            DB::table('vol_shift_waitlist')
                ->where('shift_id', $shiftId)
                ->where('status', 'waiting')
                ->where('position', '>', $entry->position)
                ->where('tenant_id', $tenantId)
                ->decrement('position');

            return true;
        } catch (\Exception $e) {
            Log::error('ShiftWaitlistService::leave error: ' . $e->getMessage());
            $this->errors[] = ['code' => 'SERVER_ERROR', 'message' => 'Failed to leave waitlist'];
            return false;
        }
    }

    /**
     * Get waitlist entries for a shift.
     */
    public function getWaitlist(int $shiftId): array
    {
        $tenantId = TenantContext::getId();

        $entries = DB::table('vol_shift_waitlist as w')
            ->join('users as u', 'w.user_id', '=', 'u.id')
            ->where('w.shift_id', $shiftId)
            ->where('w.status', 'waiting')
            ->where('w.tenant_id', $tenantId)
            ->orderBy('w.position')
            ->select('w.*', 'u.name as user_name', 'u.avatar_url as user_avatar')
            ->get();

        return $entries->map(function ($e) {
            return [
                'id'       => (int) $e->id,
                'position' => (int) $e->position,
                'user'     => [
                    'id'         => (int) $e->user_id,
                    'name'       => $e->user_name,
                    'avatar_url' => $e->user_avatar,
                ],
                'created_at' => $e->created_at,
            ];
        })->all();
    }

    /**
     * Get user's waitlist position for a shift.
     */
    public function getUserPosition(int $shiftId, int $userId): ?array
    {
        $tenantId = TenantContext::getId();

        $entry = DB::table('vol_shift_waitlist')
            ->where('shift_id', $shiftId)
            ->where('user_id', $userId)
            ->where('status', 'waiting')
            ->where('tenant_id', $tenantId)
            ->first();

        if (! $entry) {
            return null;
        }

        $totalWaiting = DB::table('vol_shift_waitlist')
            ->where('shift_id', $shiftId)
            ->where('status', 'waiting')
            ->where('tenant_id', $tenantId)
            ->count();

        return [
            'id'            => (int) $entry->id,
            'position'      => (int) $entry->position,
            'total_waiting' => $totalWaiting,
        ];
    }

    /**
     * Get all waitlist entries for a user across all shifts.
     */
    public function getUserWaitlists(int $userId, int $tenantId): array
    {
        try {
            $rows = DB::table('vol_shift_waitlist as w')
                ->join('vol_shifts as s', 'w.shift_id', '=', 's.id')
                ->join('vol_opportunities as opp', 's.opportunity_id', '=', 'opp.id')
                ->leftJoin('vol_organizations as org', 'opp.organization_id', '=', 'org.id')
                ->where('w.user_id', $userId)
                ->where('w.tenant_id', $tenantId)
                ->where('w.status', 'waiting')
                ->orderBy('s.start_time')
                ->select(
                    'w.id', 'w.position', 'w.created_at as joined_at',
                    's.id as shift_id', 's.start_time', 's.end_time', 's.capacity',
                    'opp.id as opportunity_id', 'opp.title as opportunity_title', 'opp.location as opportunity_location',
                    'org.id as organization_id', 'org.name as organization_name', 'org.logo_url as organization_logo_url'
                )
                ->get();

            return $rows->map(function ($row) {
                return [
                    'id'       => (int) $row->id,
                    'position' => (int) $row->position,
                    'shift'    => [
                        'id'         => (int) $row->shift_id,
                        'start_time' => $row->start_time,
                        'end_time'   => $row->end_time,
                        'capacity'   => $row->capacity !== null ? (int) $row->capacity : null,
                    ],
                    'opportunity' => [
                        'id'       => (int) $row->opportunity_id,
                        'title'    => $row->opportunity_title,
                        'location' => $row->opportunity_location ?? '',
                    ],
                    'organization' => [
                        'id'       => $row->organization_id ? (int) $row->organization_id : 0,
                        'name'     => $row->organization_name ?? '',
                        'logo_url' => $row->organization_logo_url ?? null,
                    ],
                    'joined_at' => $row->joined_at,
                ];
            })->all();
        } catch (\Exception $e) {
            Log::error('ShiftWaitlistService::getUserWaitlists error: ' . $e->getMessage());
            return [];
        }
    }

    /**
     * Promote a user from the waitlist to the shift.
     */
    public function promoteUser(int $waitlistId, int $tenantId): bool
    {
        $entry = DB::table('vol_shift_waitlist')
            ->where('id', $waitlistId)
            ->where('tenant_id', $tenantId)
            ->where('status', 'notified')
            ->first();

        if (! $entry) {
            return false;
        }

        try {
            // Mark as promoted
            DB::table('vol_shift_waitlist')
                ->where('id', $waitlistId)
                ->where('tenant_id', $tenantId)
                ->update([
                    'status'      => 'promoted',
                    'promoted_at' => now(),
                ]);

            // Sign up for shift — create an approved application
            $existingApp = DB::table('vol_applications')
                ->where('shift_id', $entry->shift_id)
                ->where('user_id', $entry->user_id)
                ->where('tenant_id', $tenantId)
                ->first();

            if ($existingApp) {
                // Reactivate existing application
                DB::table('vol_applications')
                    ->where('id', $existingApp->id)
                    ->where('tenant_id', $tenantId)
                    ->update(['status' => 'approved']);
            } else {
                // Get the opportunity_id from the shift
                $shift = DB::table('vol_shifts')->where('id', $entry->shift_id)->first();

                DB::table('vol_applications')->insert([
                    'tenant_id'      => $tenantId,
                    'opportunity_id' => $shift->opportunity_id ?? 0,
                    'shift_id'       => $entry->shift_id,
                    'user_id'        => $entry->user_id,
                    'status'         => 'approved',
                    'created_at'     => now(),
                    'updated_at'     => now(),
                ]);
            }

            return true;
        } catch (\Exception $e) {
            Log::error('ShiftWaitlistService::promoteUser error: ' . $e->getMessage());

            // Revert promotion on failure
            DB::table('vol_shift_waitlist')
                ->where('id', $waitlistId)
                ->where('tenant_id', $tenantId)
                ->update(['status' => 'waiting']);

            return false;
        }
    }
}
