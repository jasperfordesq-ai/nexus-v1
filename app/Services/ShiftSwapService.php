<?php
// Copyright © 2024–2026 Jasper Ford
// SPDX-License-Identifier: AGPL-3.0-or-later
// Author: Jasper Ford
// See NOTICE file for attribution and acknowledgements.

namespace App\Services;

/**
 * ShiftSwapService — Laravel DI wrapper for legacy \Nexus\Services\ShiftSwapService.
 *
 * Provides dependency-injectable access to the legacy static service methods.
 */
class ShiftSwapService
{
    public function __construct()
    {
    }

    /**
     * Delegates to legacy ShiftSwapService::getErrors().
     */
    public function getErrors(): array
    {
        return \Nexus\Services\ShiftSwapService::getErrors();
    }

    /**
     * Delegates to legacy ShiftSwapService::requestSwap().
     */
    public function requestSwap(int $fromUserId, array $data): ?int
    {
        return \Nexus\Services\ShiftSwapService::requestSwap($fromUserId, $data);
    }

    /**
     * Delegates to legacy ShiftSwapService::respond().
     */
    public function respond(int $swapId, int $userId, string $action): bool
    {
        return \Nexus\Services\ShiftSwapService::respond($swapId, $userId, $action);
    }

    /**
     * Delegates to legacy ShiftSwapService::adminDecision().
     */
    public function adminDecision(int $swapId, int $adminId, string $action): bool
    {
        return \Nexus\Services\ShiftSwapService::adminDecision($swapId, $adminId, $action);
    }

    /**
     * Delegates to legacy ShiftSwapService::getSwapRequests().
     */
    public function getSwapRequests(int $userId, string $direction = 'all'): array
    {
        return \Nexus\Services\ShiftSwapService::getSwapRequests($userId, $direction);
    }
}
