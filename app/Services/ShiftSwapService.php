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

    /**
     * Cancel a pending swap request.
     *
     * @param int $swapId   Swap request ID
     * @param int $userId   User cancelling (must be from_user_id)
     * @param int $tenantId Tenant ID
     * @return bool Success
     */
    public function cancel(int $swapId, int $userId, int $tenantId): bool
    {
        $this->errors = [];

        $swap = \Illuminate\Support\Facades\DB::selectOne(
            "SELECT * FROM vol_shift_swap_requests WHERE id = ? AND from_user_id = ? AND status IN ('pending', 'admin_pending') AND tenant_id = ?",
            [$swapId, $userId, $tenantId]
        );

        if (!$swap) {
            $this->errors[] = ['code' => 'NOT_FOUND', 'message' => 'Swap request not found or cannot be cancelled'];
            return false;
        }

        try {
            \Illuminate\Support\Facades\DB::update(
                "UPDATE vol_shift_swap_requests SET status = 'cancelled' WHERE id = ? AND tenant_id = ?",
                [$swapId, $tenantId]
            );
            return true;
        } catch (\Exception $e) {
            \Illuminate\Support\Facades\Log::error('ShiftSwapService::cancel error: ' . $e->getMessage());
            $this->errors[] = ['code' => 'SERVER_ERROR', 'message' => 'Failed to cancel swap request'];
            return false;
        }
    }

    /** @var array */
    private array $errors = [];

    /**
     * Get errors from the last cancel() call.
     *
     * @return array
     */
    public function getCancelErrors(): array
    {
        return $this->errors;
    }
}
