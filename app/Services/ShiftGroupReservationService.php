<?php
// Copyright © 2024–2026 Jasper Ford
// SPDX-License-Identifier: AGPL-3.0-or-later
// Author: Jasper Ford
// See NOTICE file for attribution and acknowledgements.

namespace App\Services;

use App\Core\TenantContext;
use App\Models\Group;
use App\Models\VolShift;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;

/**
 * ShiftGroupReservationService — Laravel DI-based service for group shift reservations.
 *
 * Eloquent/DI counterpart to the legacy static \Nexus\Services\ShiftGroupReservationService.
 * Allows group leaders to reserve blocks of shift slots for their teams.
 */
class ShiftGroupReservationService
{
    private array $errors = [];

    public function getErrors(): array
    {
        return $this->errors;
    }

    /**
     * Create a group reservation for a shift.
     *
     * @return int|null Reservation ID or null on failure
     */
    public function reserve(int $shiftId, int $groupId, int $reservedBy, int $slots, ?string $notes = null): ?int
    {
        $this->errors = [];
        $tenantId = TenantContext::getId();

        if ($slots < 1) {
            $this->errors[] = ['code' => 'VALIDATION_ERROR', 'message' => 'Must reserve at least 1 slot', 'field' => 'reserved_slots'];
            return null;
        }

        $shift = VolShift::find($shiftId);
        if (! $shift) {
            $this->errors[] = ['code' => 'NOT_FOUND', 'message' => 'Shift not found'];
            return null;
        }

        if ($shift->start_time->isPast()) {
            $this->errors[] = ['code' => 'VALIDATION_ERROR', 'message' => 'Cannot reserve slots for a shift that has already started'];
            return null;
        }

        // Verify group exists
        $group = Group::find($groupId);
        if (! $group) {
            $this->errors[] = ['code' => 'NOT_FOUND', 'message' => 'Group not found'];
            return null;
        }

        if (! $this->canManageGroup($groupId, $reservedBy, $tenantId)) {
            $this->errors[] = ['code' => 'FORBIDDEN', 'message' => 'Only group leaders/admins can reserve slots for this group'];
            return null;
        }

        // Check available capacity
        $availableSlots = $this->getAvailableSlots($shiftId, $shift);
        if ($availableSlots !== null && $slots > $availableSlots) {
            $this->errors[] = ['code' => 'VALIDATION_ERROR', 'message' => "Only {$availableSlots} slots available", 'field' => 'reserved_slots'];
            return null;
        }

        // Check for existing reservation
        $existing = DB::table('vol_shift_group_reservations')
            ->where('shift_id', $shiftId)
            ->where('group_id', $groupId)
            ->where('status', 'active')
            ->where('tenant_id', $tenantId)
            ->exists();

        if ($existing) {
            $this->errors[] = ['code' => 'ALREADY_EXISTS', 'message' => 'This group already has a reservation for this shift'];
            return null;
        }

        try {
            return DB::table('vol_shift_group_reservations')->insertGetId([
                'tenant_id'      => $tenantId,
                'shift_id'       => $shiftId,
                'group_id'       => $groupId,
                'reserved_slots' => $slots,
                'filled_slots'   => 0,
                'reserved_by'    => $reservedBy,
                'status'         => 'active',
                'notes'          => $notes,
                'created_at'     => now(),
            ]);
        } catch (\Exception $e) {
            Log::error('ShiftGroupReservationService::reserve error: ' . $e->getMessage());
            $this->errors[] = ['code' => 'SERVER_ERROR', 'message' => 'Failed to create reservation'];
            return null;
        }
    }

    /**
     * Add a member to a group reservation.
     */
    public function addMember(int $reservationId, int $userId, int $leaderUserId): bool
    {
        $this->errors = [];
        $tenantId = TenantContext::getId();

        $reservation = DB::table('vol_shift_group_reservations')
            ->where('id', $reservationId)
            ->where('status', 'active')
            ->where('tenant_id', $tenantId)
            ->first();

        if (! $reservation) {
            $this->errors[] = ['code' => 'NOT_FOUND', 'message' => 'Reservation not found'];
            return false;
        }

        if (! $this->canManageReservation((int) $reservation->group_id, (int) $reservation->reserved_by, $leaderUserId, $tenantId)) {
            $this->errors[] = ['code' => 'FORBIDDEN', 'message' => 'Only group leaders/admins can manage this reservation'];
            return false;
        }

        if ((int) $reservation->filled_slots >= (int) $reservation->reserved_slots) {
            $this->errors[] = ['code' => 'VALIDATION_ERROR', 'message' => 'All reserved slots are filled'];
            return false;
        }

        // Check if user is already in this reservation
        $alreadyMember = DB::table('vol_shift_group_members')
            ->where('reservation_id', $reservationId)
            ->where('user_id', $userId)
            ->where('status', 'confirmed')
            ->exists();

        if ($alreadyMember) {
            $this->errors[] = ['code' => 'ALREADY_EXISTS', 'message' => 'User is already in this group reservation'];
            return false;
        }

        try {
            return DB::transaction(function () use ($reservationId, $userId, $tenantId) {
                DB::table('vol_shift_group_members')->insert([
                    'reservation_id' => $reservationId,
                    'user_id'        => $userId,
                    'status'         => 'confirmed',
                    'created_at'     => now(),
                ]);

                DB::table('vol_shift_group_reservations')
                    ->where('id', $reservationId)
                    ->where('tenant_id', $tenantId)
                    ->increment('filled_slots');

                return true;
            });
        } catch (\Exception $e) {
            Log::error('ShiftGroupReservationService::addMember error: ' . $e->getMessage());
            $this->errors[] = ['code' => 'SERVER_ERROR', 'message' => 'Failed to add member'];
            return false;
        }
    }

    /**
     * Remove a member from a group reservation.
     */
    public function removeMember(int $reservationId, int $userId, int $leaderUserId): bool
    {
        $this->errors = [];
        $tenantId = TenantContext::getId();

        $reservation = DB::table('vol_shift_group_reservations')
            ->where('id', $reservationId)
            ->where('tenant_id', $tenantId)
            ->first();

        if (! $reservation) {
            $this->errors[] = ['code' => 'NOT_FOUND', 'message' => 'Reservation not found'];
            return false;
        }

        if (! $this->canManageReservation((int) $reservation->group_id, (int) $reservation->reserved_by, $leaderUserId, $tenantId)) {
            $this->errors[] = ['code' => 'FORBIDDEN', 'message' => 'Only group leaders/admins can manage this reservation'];
            return false;
        }

        $member = DB::table('vol_shift_group_members')
            ->where('reservation_id', $reservationId)
            ->where('user_id', $userId)
            ->where('status', 'confirmed')
            ->first();

        if (! $member) {
            $this->errors[] = ['code' => 'NOT_FOUND', 'message' => 'Member not found in this reservation'];
            return false;
        }

        try {
            return DB::transaction(function () use ($member, $reservationId, $tenantId) {
                DB::table('vol_shift_group_members')
                    ->where('id', $member->id)
                    ->update(['status' => 'cancelled']);

                DB::table('vol_shift_group_reservations')
                    ->where('id', $reservationId)
                    ->where('tenant_id', $tenantId)
                    ->update(['filled_slots' => DB::raw('GREATEST(filled_slots - 1, 0)')]);

                return true;
            });
        } catch (\Exception $e) {
            Log::error('ShiftGroupReservationService::removeMember error: ' . $e->getMessage());
            $this->errors[] = ['code' => 'SERVER_ERROR', 'message' => 'Failed to remove member'];
            return false;
        }
    }

    /**
     * Cancel an entire group reservation.
     */
    public function cancelReservation(int $reservationId, int $leaderUserId): bool
    {
        $this->errors = [];
        $tenantId = TenantContext::getId();

        $reservation = DB::table('vol_shift_group_reservations')
            ->where('id', $reservationId)
            ->where('status', 'active')
            ->where('tenant_id', $tenantId)
            ->first();

        if (! $reservation) {
            $this->errors[] = ['code' => 'NOT_FOUND', 'message' => 'Reservation not found'];
            return false;
        }

        if (! $this->canManageReservation((int) $reservation->group_id, (int) $reservation->reserved_by, $leaderUserId, $tenantId)) {
            $this->errors[] = ['code' => 'FORBIDDEN', 'message' => 'Only group leaders/admins can cancel this reservation'];
            return false;
        }

        try {
            return DB::transaction(function () use ($reservationId, $tenantId) {
                DB::table('vol_shift_group_reservations')
                    ->where('id', $reservationId)
                    ->where('tenant_id', $tenantId)
                    ->update(['status' => 'cancelled']);

                DB::table('vol_shift_group_members')
                    ->where('reservation_id', $reservationId)
                    ->update(['status' => 'cancelled']);

                return true;
            });
        } catch (\Exception $e) {
            Log::error('ShiftGroupReservationService::cancelReservation error: ' . $e->getMessage());
            $this->errors[] = ['code' => 'SERVER_ERROR', 'message' => 'Failed to cancel reservation'];
            return false;
        }
    }

    /**
     * Get all group reservations a user is involved in (as leader or member).
     */
    public function getUserReservations(int $userId, int $tenantId): array
    {
        try {
            $rows = DB::table('vol_shift_group_reservations as r')
                ->join('vol_shifts as s', 'r.shift_id', '=', 's.id')
                ->join('vol_opportunities as opp', 's.opportunity_id', '=', 'opp.id')
                ->leftJoin('vol_organizations as org', 'opp.organization_id', '=', 'org.id')
                ->leftJoin('vol_shift_group_members as gm', function ($join) use ($userId) {
                    $join->on('gm.reservation_id', '=', 'r.id')
                         ->where('gm.user_id', '=', $userId)
                         ->where('gm.status', '=', 'confirmed');
                })
                ->where('r.tenant_id', $tenantId)
                ->where(function ($q) use ($userId) {
                    $q->where('r.reserved_by', $userId)
                      ->orWhereNotNull('gm.id');
                })
                ->distinct()
                ->orderBy('s.start_time')
                ->select(
                    'r.id', 'r.reserved_slots', 'r.filled_slots', 'r.reserved_by',
                    'r.status', 'r.notes', 'r.created_at', 'r.group_id',
                    's.id as shift_id', 's.start_time', 's.end_time',
                    'opp.id as opportunity_id', 'opp.title as opportunity_title', 'opp.location as opportunity_location',
                    'org.id as organization_id', 'org.name as organization_name', 'org.logo_url as organization_logo_url'
                )
                ->get();

            return $rows->map(function ($row) use ($tenantId, $userId) {
                $groupName = '';
                $group = Group::find((int) $row->group_id);
                if ($group) {
                    $groupName = $group->name;
                }

                // Get members
                $memberRows = DB::table('vol_shift_group_members as gm')
                    ->join('vol_shift_group_reservations as r', 'gm.reservation_id', '=', 'r.id')
                    ->join('users as u', 'gm.user_id', '=', 'u.id')
                    ->where('gm.reservation_id', $row->id)
                    ->where('gm.status', 'confirmed')
                    ->where('r.tenant_id', $tenantId)
                    ->orderBy('gm.created_at')
                    ->select('gm.id', 'gm.user_id', 'gm.created_at', 'u.name as user_name', 'u.avatar_url as user_avatar')
                    ->get();

                $members = $memberRows->map(function ($m) {
                    return [
                        'id'   => (int) $m->id,
                        'user' => [
                            'id'         => (int) $m->user_id,
                            'name'       => $m->user_name,
                            'avatar_url' => $m->user_avatar,
                        ],
                        'created_at' => $m->created_at,
                    ];
                })->all();

                return [
                    'id'           => (int) $row->id,
                    'group_name'   => $groupName,
                    'status'       => $row->status,
                    'is_leader'    => (int) $row->reserved_by === $userId,
                    'shift'        => [
                        'id'         => (int) $row->shift_id,
                        'start_time' => $row->start_time,
                        'end_time'   => $row->end_time,
                    ],
                    'opportunity'  => [
                        'id'       => (int) $row->opportunity_id,
                        'title'    => $row->opportunity_title,
                        'location' => $row->opportunity_location ?? '',
                    ],
                    'organization' => [
                        'id'       => $row->organization_id ? (int) $row->organization_id : 0,
                        'name'     => $row->organization_name ?? '',
                        'logo_url' => $row->organization_logo_url ?? null,
                    ],
                    'members'      => $members,
                    'max_members'  => $row->reserved_slots !== null ? (int) $row->reserved_slots : null,
                    'created_at'   => $row->created_at,
                ];
            })->all();
        } catch (\Exception $e) {
            Log::error('ShiftGroupReservationService::getUserReservations error: ' . $e->getMessage());
            return [];
        }
    }

    /**
     * Calculate available slots for a shift (accounting for regular signups + group reservations).
     */
    private function getAvailableSlots(int $shiftId, VolShift $shift): ?int
    {
        if (! $shift->capacity) {
            return null; // Unlimited
        }

        $tenantId = TenantContext::getId();

        $regularSignups = DB::table('vol_applications')
            ->where('shift_id', $shiftId)
            ->where('status', 'approved')
            ->where('tenant_id', $tenantId)
            ->count();

        $reservedSlots = (int) DB::table('vol_shift_group_reservations')
            ->where('shift_id', $shiftId)
            ->where('status', 'active')
            ->where('tenant_id', $tenantId)
            ->sum('reserved_slots');

        return max(0, (int) $shift->capacity - $regularSignups - $reservedSlots);
    }

    private function canManageReservation(int $groupId, int $reservedBy, int $userId, int $tenantId): bool
    {
        if ($reservedBy === $userId) {
            return true;
        }

        return $this->canManageGroup($groupId, $userId, $tenantId);
    }

    private function canManageGroup(int $groupId, int $userId, int $tenantId): bool
    {
        if ($this->isTenantAdmin($userId, $tenantId)) {
            return true;
        }

        // Check if group owner
        $isOwner = DB::table('groups')
            ->where('id', $groupId)
            ->where('tenant_id', $tenantId)
            ->where('owner_id', $userId)
            ->exists();

        if ($isOwner) {
            return true;
        }

        // Check if group admin/owner role
        return DB::table('group_members')
            ->where('group_id', $groupId)
            ->where('user_id', $userId)
            ->where('status', 'active')
            ->whereIn('role', ['owner', 'admin'])
            ->exists();
    }

    private function isTenantAdmin(int $userId, int $tenantId): bool
    {
        $role = DB::table('users')
            ->where('id', $userId)
            ->where('tenant_id', $tenantId)
            ->value('role');

        return in_array($role, ['admin', 'tenant_admin', 'tenant_super_admin', 'super_admin'], true);
    }
}
