<?php
// Copyright © 2024–2026 Jasper Ford
// SPDX-License-Identifier: AGPL-3.0-or-later
// Author: Jasper Ford
// See NOTICE file for attribution and acknowledgements.

namespace App\Services;

use Illuminate\Support\Facades\DB;

class VolunteerShiftCapacityService
{
    public static function approvedSignupCount(int $shiftId, int $tenantId): int
    {
        return (int) DB::table('vol_applications')
            ->where('shift_id', $shiftId)
            ->where('status', 'approved')
            ->where('tenant_id', $tenantId)
            ->count();
    }

    public static function reservedSlotCount(int $shiftId, int $tenantId, ?int $excludeReservationId = null): int
    {
        $query = DB::table('vol_shift_group_reservations')
            ->where('shift_id', $shiftId)
            ->where('status', 'active')
            ->where('tenant_id', $tenantId);

        if ($excludeReservationId !== null) {
            $query->where('id', '<>', $excludeReservationId);
        }

        return (int) $query->sum('reserved_slots');
    }

    public static function outstandingOfferCount(int $shiftId, int $tenantId): int
    {
        return (int) DB::table('vol_shift_waitlist')
            ->where('shift_id', $shiftId)
            ->where('status', 'notified')
            ->where('tenant_id', $tenantId)
            ->count();
    }

    public static function usedSlots(
        int $shiftId,
        int $tenantId,
        ?int $excludeReservationId = null,
        bool $includeOutstandingOffers = false,
    ): int {
        $used = self::approvedSignupCount($shiftId, $tenantId)
            + self::reservedSlotCount($shiftId, $tenantId, $excludeReservationId);

        if ($includeOutstandingOffers) {
            $used += self::outstandingOfferCount($shiftId, $tenantId);
        }

        return $used;
    }

    public static function availableSlots(
        int $shiftId,
        int $tenantId,
        int|null $capacity,
        ?int $excludeReservationId = null,
        bool $includeOutstandingOffers = false,
    ): ?int {
        if ($capacity === null || $capacity <= 0) {
            return null;
        }

        return max(0, $capacity - self::usedSlots($shiftId, $tenantId, $excludeReservationId, $includeOutstandingOffers));
    }

    public static function hasAvailableSlot(
        int $shiftId,
        int $tenantId,
        int|null $capacity,
        ?int $excludeReservationId = null,
        bool $includeOutstandingOffers = false,
    ): bool {
        $availableSlots = self::availableSlots($shiftId, $tenantId, $capacity, $excludeReservationId, $includeOutstandingOffers);

        return $availableSlots === null || $availableSlots > 0;
    }
}
