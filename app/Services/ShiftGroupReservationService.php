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
        return \Nexus\Services\ShiftGroupReservationService::getErrors();
    }

    /**
     * Delegates to legacy ShiftGroupReservationService::reserve().
     */
    public function reserve(int $shiftId, int $groupId, int $reservedBy, int $slots, ?string $notes = null): ?int
    {
        return \Nexus\Services\ShiftGroupReservationService::reserve($shiftId, $groupId, $reservedBy, $slots, $notes);
    }

    /**
     * Delegates to legacy ShiftGroupReservationService::addMember().
     */
    public function addMember(int $reservationId, int $userId, int $leaderUserId): bool
    {
        return \Nexus\Services\ShiftGroupReservationService::addMember($reservationId, $userId, $leaderUserId);
    }

    /**
     * Delegates to legacy ShiftGroupReservationService::removeMember().
     */
    public function removeMember(int $reservationId, int $userId, int $leaderUserId): bool
    {
        return \Nexus\Services\ShiftGroupReservationService::removeMember($reservationId, $userId, $leaderUserId);
    }

    /**
     * Delegates to legacy ShiftGroupReservationService::cancelReservation().
     */
    public function cancelReservation(int $reservationId, int $leaderUserId): bool
    {
        return \Nexus\Services\ShiftGroupReservationService::cancelReservation($reservationId, $leaderUserId);
    }
}
