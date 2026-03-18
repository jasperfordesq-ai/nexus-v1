<?php
// Copyright © 2024–2026 Jasper Ford
// SPDX-License-Identifier: AGPL-3.0-or-later
// Author: Jasper Ford
// See NOTICE file for attribution and acknowledgements.

namespace App\Services;

/**
 * ShiftGroupReservationService — Laravel DI wrapper for legacy \Nexus\Services\ShiftGroupReservationService.
 *
 * Provides dependency-injectable access to the legacy static service methods.
 */
class ShiftGroupReservationService
{
    public function __construct()
    {
    }

    /**
     * Delegates to legacy ShiftGroupReservationService::getErrors().
     */
    public function getErrors(): array
    {
        if (!class_exists('\Nexus\Services\ShiftGroupReservationService')) { return []; }
        return \Nexus\Services\ShiftGroupReservationService::getErrors();
    }

    /**
     * Delegates to legacy ShiftGroupReservationService::reserve().
     */
    public function reserve(int $shiftId, int $groupId, int $reservedBy, int $slots, ?string $notes = null): ?int
    {
        if (!class_exists('\Nexus\Services\ShiftGroupReservationService')) { return null; }
        return \Nexus\Services\ShiftGroupReservationService::reserve($shiftId, $groupId, $reservedBy, $slots, $notes);
    }

    /**
     * Delegates to legacy ShiftGroupReservationService::addMember().
     */
    public function addMember(int $reservationId, int $userId, int $leaderUserId): bool
    {
        if (!class_exists('\Nexus\Services\ShiftGroupReservationService')) { return false; }
        return \Nexus\Services\ShiftGroupReservationService::addMember($reservationId, $userId, $leaderUserId);
    }

    /**
     * Delegates to legacy ShiftGroupReservationService::removeMember().
     */
    public function removeMember(int $reservationId, int $userId, int $leaderUserId): bool
    {
        if (!class_exists('\Nexus\Services\ShiftGroupReservationService')) { return false; }
        return \Nexus\Services\ShiftGroupReservationService::removeMember($reservationId, $userId, $leaderUserId);
    }

    /**
     * Delegates to legacy ShiftGroupReservationService::cancelReservation().
     */
    public function cancelReservation(int $reservationId, int $leaderUserId): bool
    {
        if (!class_exists('\Nexus\Services\ShiftGroupReservationService')) { return false; }
        return \Nexus\Services\ShiftGroupReservationService::cancelReservation($reservationId, $leaderUserId);
    }

    /**
     * Get all group reservations a user is involved in (as leader or member).
     *
     * @param int $userId   User ID
     * @param int $tenantId Tenant ID
     * @return array Group reservations with shift, opportunity, organization, and member details
     */
    public function getUserReservations(int $userId, int $tenantId): array
    {
        try {
            // Find reservations where user is the leader or a confirmed member
            $rows = \Illuminate\Support\Facades\DB::select("
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
            ", [$userId, $tenantId, $userId]);

            return array_map(function ($row) use ($tenantId, $userId): array {
                $groupName = '';
                try {
                    $group = \Illuminate\Support\Facades\DB::selectOne(
                        "SELECT name FROM `groups` WHERE id = ? AND tenant_id = ?",
                        [$row->group_id, $tenantId]
                    );
                    if ($group) {
                        $groupName = $group->name;
                    }
                } catch (\Throwable $e) {
                    // Silent
                }

                // Get members
                $memberRows = \Illuminate\Support\Facades\DB::select("
                    SELECT gm.id, gm.user_id, gm.created_at, u.name as user_name, u.avatar_url as user_avatar
                    FROM vol_shift_group_members gm
                    JOIN vol_shift_group_reservations r ON gm.reservation_id = r.id
                    JOIN users u ON gm.user_id = u.id
                    WHERE gm.reservation_id = ? AND gm.status = 'confirmed' AND r.tenant_id = ?
                    ORDER BY gm.created_at ASC
                ", [$row->id, $tenantId]);

                $members = array_map(function ($m) {
                    return [
                        'id' => (int) $m->id,
                        'user' => [
                            'id' => (int) $m->user_id,
                            'name' => $m->user_name,
                            'avatar_url' => $m->user_avatar,
                        ],
                        'created_at' => $m->created_at,
                    ];
                }, $memberRows);

                return [
                    'id' => (int) $row->id,
                    'group_name' => $groupName,
                    'status' => $row->status,
                    'is_leader' => (int) $row->reserved_by === $userId,
                    'shift' => [
                        'id' => (int) $row->shift_id,
                        'start_time' => $row->start_time,
                        'end_time' => $row->end_time,
                    ],
                    'opportunity' => [
                        'id' => (int) $row->opportunity_id,
                        'title' => $row->opportunity_title,
                        'location' => $row->opportunity_location ?? '',
                    ],
                    'organization' => [
                        'id' => $row->organization_id ? (int) $row->organization_id : 0,
                        'name' => $row->organization_name ?? '',
                        'logo_url' => $row->organization_logo_url ?? null,
                    ],
                    'members' => $members,
                    'max_members' => $row->reserved_slots !== null ? (int) $row->reserved_slots : null,
                    'created_at' => $row->created_at,
                ];
            }, $rows);
        } catch (\Exception $e) {
            \Illuminate\Support\Facades\Log::error('ShiftGroupReservationService::getUserReservations error: ' . $e->getMessage());
            return [];
        }
    }
}
