<?php
// Copyright © 2024–2026 Jasper Ford
// SPDX-License-Identifier: AGPL-3.0-or-later
// Author: Jasper Ford
// See NOTICE file for attribution and acknowledgements.

namespace App\Services;

use App\Core\TenantContext;
use App\Models\User;
use App\Models\VolApplication;
use App\Models\VolShift;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;

/**
 * ShiftSwapService — Laravel DI-based service for shift swap operations.
 *
 * Eloquent/DI counterpart to the legacy static \Nexus\Services\ShiftSwapService.
 * All queries are tenant-scoped automatically via the HasTenantScope trait.
 */
class ShiftSwapService
{
    private array $errors = [];

    public function getErrors(): array
    {
        return $this->errors;
    }

    /**
     * Alias for getErrors() used by the cancel flow.
     */
    public function getCancelErrors(): array
    {
        return $this->errors;
    }

    /**
     * Request a shift swap between two volunteers.
     *
     * @param int   $fromUserId User requesting the swap
     * @param array $data       [from_shift_id, to_shift_id, to_user_id, message]
     * @return int|null Swap request ID or null on failure
     */
    public function requestSwap(int $fromUserId, array $data): ?int
    {
        $this->errors = [];
        $tenantId = TenantContext::getId();

        $fromShiftId = (int) ($data['from_shift_id'] ?? 0);
        $toShiftId   = (int) ($data['to_shift_id'] ?? 0);
        $toUserId    = (int) ($data['to_user_id'] ?? 0);
        $message     = trim($data['message'] ?? '');

        if (! $fromShiftId || ! $toShiftId || ! $toUserId) {
            $this->errors[] = ['code' => 'VALIDATION_ERROR', 'message' => 'from_shift_id, to_shift_id, and to_user_id are required'];
            return null;
        }

        if ($fromUserId === $toUserId) {
            $this->errors[] = ['code' => 'VALIDATION_ERROR', 'message' => 'Cannot swap with yourself'];
            return null;
        }

        // Verify requester is signed up for from_shift
        $fromApp = VolApplication::where('shift_id', $fromShiftId)
            ->where('user_id', $fromUserId)
            ->where('status', 'approved')
            ->first();

        if (! $fromApp) {
            $this->errors[] = ['code' => 'FORBIDDEN', 'message' => 'You are not signed up for the source shift'];
            return null;
        }

        // Verify target user is signed up for to_shift
        $toApp = VolApplication::where('shift_id', $toShiftId)
            ->where('user_id', $toUserId)
            ->where('status', 'approved')
            ->first();

        if (! $toApp) {
            $this->errors[] = ['code' => 'VALIDATION_ERROR', 'message' => 'Target user is not signed up for the requested shift'];
            return null;
        }

        // Check both shifts are in the future
        $fromShift = VolShift::find($fromShiftId);
        $toShift   = VolShift::find($toShiftId);

        if (! $fromShift || ! $toShift) {
            $this->errors[] = ['code' => 'NOT_FOUND', 'message' => 'One or both shifts not found'];
            return null;
        }

        if ($fromShift->start_time->isPast() || $toShift->start_time->isPast()) {
            $this->errors[] = ['code' => 'VALIDATION_ERROR', 'message' => 'Cannot swap shifts that have already started'];
            return null;
        }

        // Check for duplicate pending swap request
        $duplicate = DB::table('vol_shift_swap_requests')
            ->where('from_user_id', $fromUserId)
            ->where('to_user_id', $toUserId)
            ->where('from_shift_id', $fromShiftId)
            ->where('to_shift_id', $toShiftId)
            ->whereIn('status', ['pending', 'admin_pending'])
            ->where('tenant_id', $tenantId)
            ->exists();

        if ($duplicate) {
            $this->errors[] = ['code' => 'ALREADY_EXISTS', 'message' => 'A swap request already exists for these shifts'];
            return null;
        }

        // Check if admin approval is required (tenant setting)
        $requiresAdmin = $this->requiresAdminApproval($tenantId);

        try {
            $swapId = DB::table('vol_shift_swap_requests')->insertGetId([
                'tenant_id'               => $tenantId,
                'from_user_id'            => $fromUserId,
                'to_user_id'              => $toUserId,
                'from_shift_id'           => $fromShiftId,
                'to_shift_id'             => $toShiftId,
                'status'                  => 'pending',
                'requires_admin_approval' => $requiresAdmin ? 1 : 0,
                'message'                 => $message,
                'created_at'              => now(),
            ]);

            return (int) $swapId;
        } catch (\Exception $e) {
            Log::error('ShiftSwapService::requestSwap error: ' . $e->getMessage());
            $this->errors[] = ['code' => 'SERVER_ERROR', 'message' => 'Failed to create swap request'];
            return null;
        }
    }

    /**
     * Respond to a swap request (accept/reject by target user).
     */
    public function respond(int $swapId, int $userId, string $action): bool
    {
        $this->errors = [];
        $tenantId = TenantContext::getId();

        if (! in_array($action, ['accept', 'reject'])) {
            $this->errors[] = ['code' => 'VALIDATION_ERROR', 'message' => 'Action must be accept or reject'];
            return false;
        }

        $swap = DB::table('vol_shift_swap_requests')
            ->where('id', $swapId)
            ->where('status', 'pending')
            ->where('tenant_id', $tenantId)
            ->first();

        if (! $swap) {
            $this->errors[] = ['code' => 'NOT_FOUND', 'message' => 'Swap request not found or already processed'];
            return false;
        }

        if ((int) $swap->to_user_id !== $userId) {
            $this->errors[] = ['code' => 'FORBIDDEN', 'message' => 'This swap request is not addressed to you'];
            return false;
        }

        try {
            if ($action === 'reject') {
                DB::table('vol_shift_swap_requests')
                    ->where('id', $swapId)
                    ->where('tenant_id', $tenantId)
                    ->update(['status' => 'rejected']);
                return true;
            }

            // Accept flow
            if ($swap->requires_admin_approval) {
                DB::table('vol_shift_swap_requests')
                    ->where('id', $swapId)
                    ->where('tenant_id', $tenantId)
                    ->update(['status' => 'admin_pending']);
                return true;
            }

            // Execute swap directly
            return $this->executeSwap($swap, $tenantId);
        } catch (\Exception $e) {
            Log::error('ShiftSwapService::respond error: ' . $e->getMessage());
            $this->errors[] = ['code' => 'SERVER_ERROR', 'message' => 'Failed to process swap response'];
            return false;
        }
    }

    /**
     * Admin approve/reject a swap.
     */
    public function adminDecision(int $swapId, int $adminId, string $action): bool
    {
        $this->errors = [];
        $tenantId = TenantContext::getId();

        if (! in_array($action, ['approve', 'reject'])) {
            $this->errors[] = ['code' => 'VALIDATION_ERROR', 'message' => 'Action must be approve or reject'];
            return false;
        }

        $swap = DB::table('vol_shift_swap_requests')
            ->where('id', $swapId)
            ->where('status', 'admin_pending')
            ->where('tenant_id', $tenantId)
            ->first();

        if (! $swap) {
            $this->errors[] = ['code' => 'NOT_FOUND', 'message' => 'Swap request not found or not pending admin approval'];
            return false;
        }

        try {
            if ($action === 'reject') {
                DB::table('vol_shift_swap_requests')
                    ->where('id', $swapId)
                    ->where('tenant_id', $tenantId)
                    ->update(['status' => 'admin_rejected', 'admin_id' => $adminId]);
                return true;
            }

            // Execute the swap
            $result = $this->executeSwap($swap, $tenantId);

            if ($result) {
                DB::table('vol_shift_swap_requests')
                    ->where('id', $swapId)
                    ->where('tenant_id', $tenantId)
                    ->update(['admin_id' => $adminId]);
            }

            return $result;
        } catch (\Exception $e) {
            Log::error('ShiftSwapService::adminDecision error: ' . $e->getMessage());
            $this->errors[] = ['code' => 'SERVER_ERROR', 'message' => 'Failed to process admin decision'];
            return false;
        }
    }

    /**
     * Get swap requests for a user (incoming and outgoing).
     */
    public function getSwapRequests(int $userId, string $direction = 'all'): array
    {
        $tenantId = TenantContext::getId();

        $query = DB::table('vol_shift_swap_requests as sr')
            ->join('users as fu', 'sr.from_user_id', '=', 'fu.id')
            ->join('users as tu', 'sr.to_user_id', '=', 'tu.id')
            ->join('vol_shifts as fs', 'sr.from_shift_id', '=', 'fs.id')
            ->join('vol_shifts as ts', 'sr.to_shift_id', '=', 'ts.id')
            ->where('sr.tenant_id', $tenantId)
            ->select(
                'sr.*',
                'fu.name as from_user_name', 'fu.avatar_url as from_user_avatar',
                'tu.name as to_user_name', 'tu.avatar_url as to_user_avatar',
                'fs.start_time as from_shift_start', 'fs.end_time as from_shift_end',
                'ts.start_time as to_shift_start', 'ts.end_time as to_shift_end'
            );

        if ($direction === 'incoming') {
            $query->where('sr.to_user_id', $userId);
        } elseif ($direction === 'outgoing') {
            $query->where('sr.from_user_id', $userId);
        } else {
            $query->where(function ($q) use ($userId) {
                $q->where('sr.from_user_id', $userId)
                  ->orWhere('sr.to_user_id', $userId);
            });
        }

        $requests = $query->orderByDesc('sr.created_at')->limit(50)->get();

        return $requests->map(function ($r) use ($userId) {
            return [
                'id'                       => (int) $r->id,
                'direction'                => (int) $r->from_user_id === $userId ? 'outgoing' : 'incoming',
                'status'                   => $r->status,
                'message'                  => $r->message,
                'requires_admin_approval'  => (bool) $r->requires_admin_approval,
                'from_user' => [
                    'id'         => (int) $r->from_user_id,
                    'name'       => $r->from_user_name,
                    'avatar_url' => $r->from_user_avatar,
                ],
                'to_user' => [
                    'id'         => (int) $r->to_user_id,
                    'name'       => $r->to_user_name,
                    'avatar_url' => $r->to_user_avatar,
                ],
                'from_shift' => [
                    'id'         => (int) $r->from_shift_id,
                    'start_time' => $r->from_shift_start,
                    'end_time'   => $r->from_shift_end,
                ],
                'to_shift' => [
                    'id'         => (int) $r->to_shift_id,
                    'start_time' => $r->to_shift_start,
                    'end_time'   => $r->to_shift_end,
                ],
                'created_at' => $r->created_at,
            ];
        })->all();
    }

    /**
     * Cancel a pending swap request.
     */
    public function cancel(int $swapId, int $userId, int $tenantId): bool
    {
        $this->errors = [];

        $swap = DB::table('vol_shift_swap_requests')
            ->where('id', $swapId)
            ->where('from_user_id', $userId)
            ->whereIn('status', ['pending', 'admin_pending'])
            ->where('tenant_id', $tenantId)
            ->first();

        if (! $swap) {
            $this->errors[] = ['code' => 'NOT_FOUND', 'message' => 'Swap request not found or cannot be cancelled'];
            return false;
        }

        try {
            DB::table('vol_shift_swap_requests')
                ->where('id', $swapId)
                ->where('tenant_id', $tenantId)
                ->update(['status' => 'cancelled']);
            return true;
        } catch (\Exception $e) {
            Log::error('ShiftSwapService::cancel error: ' . $e->getMessage());
            $this->errors[] = ['code' => 'SERVER_ERROR', 'message' => 'Failed to cancel swap request'];
            return false;
        }
    }

    /**
     * Execute the actual swap (move shift assignments) within a transaction.
     */
    private function executeSwap(object $swap, int $tenantId): bool
    {
        try {
            return DB::transaction(function () use ($swap, $tenantId) {
                // Lock both assignments
                $fromApp = DB::table('vol_applications')
                    ->where('user_id', $swap->from_user_id)
                    ->where('shift_id', $swap->from_shift_id)
                    ->where('status', 'approved')
                    ->where('tenant_id', $tenantId)
                    ->lockForUpdate()
                    ->first();

                $toApp = DB::table('vol_applications')
                    ->where('user_id', $swap->to_user_id)
                    ->where('shift_id', $swap->to_shift_id)
                    ->where('status', 'approved')
                    ->where('tenant_id', $tenantId)
                    ->lockForUpdate()
                    ->first();

                if (! $fromApp || ! $toApp) {
                    $this->errors[] = ['code' => 'VALIDATION_ERROR', 'message' => 'One or both volunteers are no longer assigned to the requested shifts'];
                    return false;
                }

                // User A: from_shift -> to_shift
                $updatedFrom = DB::table('vol_applications')
                    ->where('id', $fromApp->id)
                    ->where('tenant_id', $tenantId)
                    ->update(['shift_id' => $swap->to_shift_id]);

                // User B: to_shift -> from_shift
                $updatedTo = DB::table('vol_applications')
                    ->where('id', $toApp->id)
                    ->where('tenant_id', $tenantId)
                    ->update(['shift_id' => $swap->from_shift_id]);

                if ($updatedFrom !== 1 || $updatedTo !== 1) {
                    $this->errors[] = ['code' => 'SERVER_ERROR', 'message' => 'Failed to apply swap assignments atomically'];
                    throw new \RuntimeException('Swap update mismatch');
                }

                // Update swap status
                DB::table('vol_shift_swap_requests')
                    ->where('id', $swap->id)
                    ->where('tenant_id', $tenantId)
                    ->update(['status' => 'accepted']);

                return true;
            });
        } catch (\RuntimeException $e) {
            return false;
        } catch (\Exception $e) {
            Log::error('ShiftSwapService::executeSwap error: ' . $e->getMessage());
            $this->errors[] = ['code' => 'SERVER_ERROR', 'message' => 'Failed to execute shift swap'];
            return false;
        }
    }

    /**
     * Check if admin approval is required for swaps (tenant setting).
     */
    private function requiresAdminApproval(int $tenantId): bool
    {
        try {
            $result = DB::table('tenant_settings')
                ->where('tenant_id', $tenantId)
                ->where('setting_key', 'volunteering.swap_requires_admin')
                ->value('setting_value');

            return $result === '1';
        } catch (\Throwable $e) {
            return false;
        }
    }
}
