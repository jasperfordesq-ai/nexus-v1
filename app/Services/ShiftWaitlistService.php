<?php
// Copyright © 2024–2026 Jasper Ford
// SPDX-License-Identifier: AGPL-3.0-or-later
// Author: Jasper Ford
// See NOTICE file for attribution and acknowledgements.

namespace App\Services;

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
        return \Nexus\Services\ShiftWaitlistService::getErrors();
    }

    /**
     * Delegates to legacy ShiftWaitlistService::join().
     */
    public function join(int $shiftId, int $userId): ?int
    {
        return \Nexus\Services\ShiftWaitlistService::join($shiftId, $userId);
    }

    /**
     * Delegates to legacy ShiftWaitlistService::leave().
     */
    public function leave(int $shiftId, int $userId): bool
    {
        return \Nexus\Services\ShiftWaitlistService::leave($shiftId, $userId);
    }

    /**
     * Delegates to legacy ShiftWaitlistService::getWaitlist().
     */
    public function getWaitlist(int $shiftId): array
    {
        return \Nexus\Services\ShiftWaitlistService::getWaitlist($shiftId);
    }

    /**
     * Delegates to legacy ShiftWaitlistService::getUserPosition().
     */
    public function getUserPosition(int $shiftId, int $userId): ?array
    {
        return \Nexus\Services\ShiftWaitlistService::getUserPosition($shiftId, $userId);
    }
}
