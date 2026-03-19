<?php
// Copyright © 2024–2026 Jasper Ford
// SPDX-License-Identifier: AGPL-3.0-or-later
// Author: Jasper Ford
// See NOTICE file for attribution and acknowledgements.

namespace App\Services;

use Illuminate\Support\Facades\DB;

/**
 * ShiftWaitlistService — Laravel DI wrapper for legacy \Nexus\Services\ShiftWaitlistService.
 *
 * Provides dependency-injectable access to the legacy static service methods.
 */
class ShiftWaitlistService
{
    public function __construct()
    {
    }

    /**
     * Delegates to legacy ShiftWaitlistService::getErrors().
     */
    public function getErrors(): array
    {
        if (!class_exists('\Nexus\Services\ShiftWaitlistService')) { return []; }
        return \Nexus\Services\ShiftWaitlistService::getErrors();
    }

    /**
     * Delegates to legacy ShiftWaitlistService::join().
     */
    public function join(int $shiftId, int $userId): ?int
    {
        if (!class_exists('\Nexus\Services\ShiftWaitlistService')) { return null; }
        return \Nexus\Services\ShiftWaitlistService::join($shiftId, $userId);
    }

    /**
     * Delegates to legacy ShiftWaitlistService::leave().
     */
    public function leave(int $shiftId, int $userId): bool
    {
        if (!class_exists('\Nexus\Services\ShiftWaitlistService')) { return false; }
        return \Nexus\Services\ShiftWaitlistService::leave($shiftId, $userId);
    }

    /**
     * Delegates to legacy ShiftWaitlistService::getWaitlist().
     */
    public function getWaitlist(int $shiftId): array
    {
        if (!class_exists('\Nexus\Services\ShiftWaitlistService')) { return []; }
        return \Nexus\Services\ShiftWaitlistService::getWaitlist($shiftId);
    }

    /**
     * Delegates to legacy ShiftWaitlistService::getUserPosition().
     */
    public function getUserPosition(int $shiftId, int $userId): ?array
    {
        if (!class_exists('\Nexus\Services\ShiftWaitlistService')) { return null; }
        return \Nexus\Services\ShiftWaitlistService::getUserPosition($shiftId, $userId);
    }

    /**
     * Get all waitlist entries for a user across all shifts.
     *
     * Returns waitlist entries with shift, opportunity, and organization details.
     */
    public function getUserWaitlists(int $userId, int $tenantId): array
    {
        try {
            $rows = DB::select("
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
            ", [$userId, $tenantId]);

            return array_map(function ($row): array {
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
            }, $rows);
        } catch (\Exception $e) {
            \Log::error("ShiftWaitlistService::getUserWaitlists error: " . $e->getMessage());
            return [];
        }
    }

    /**
     * Promote a user from the waitlist to the shift.
     *
     * Verifies the user has a 'notified' waitlist entry, marks it as promoted,
     * and signs them up for the shift via the legacy VolunteerService.
     */
    public function promoteUser(int $waitlistId, int $tenantId): bool
    {
        $entry = DB::table('vol_shift_waitlist')
            ->where('id', $waitlistId)
            ->where('tenant_id', $tenantId)
            ->where('status', 'notified')
            ->first();

        if (!$entry) {
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

            // Sign up for shift via legacy service
            if (!class_exists('\Nexus\Services\VolunteerService')) { return false; }
            $result = \Nexus\Services\VolunteerService::signUpForShift($entry->shift_id, $entry->user_id);

            if (!$result) {
                // Revert promotion if signup fails
                DB::table('vol_shift_waitlist')
                    ->where('id', $waitlistId)
                    ->where('tenant_id', $tenantId)
                    ->update(['status' => 'waiting']);

                return false;
            }

            return true;
        } catch (\Exception $e) {
            \Log::error("ShiftWaitlistService::promoteUser error: " . $e->getMessage());
            return false;
        }
    }
}
