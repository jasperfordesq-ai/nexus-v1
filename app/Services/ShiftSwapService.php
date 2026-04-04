<?php
// Copyright © 2024–2026 Jasper Ford
// SPDX-License-Identifier: AGPL-3.0-or-later
// Author: Jasper Ford
// See NOTICE file for attribution and acknowledgements.

namespace App\Services;

use App\Core\TenantContext;
use App\Models\VolApplication;
use App\Models\VolShift;
use App\Services\NotificationDispatcher;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;

/**
 * ShiftSwapService — Laravel DI-based service for shift swap operations.
 *
 * All queries are tenant-scoped automatically via the HasTenantScope trait.
 */
class ShiftSwapService
{
    private static array $errors = [];

    public static function getErrors(): array
    {
        return self::$errors;
    }

    /**
     * Alias for getErrors() used by the cancel flow.
     */
    public static function getCancelErrors(): array
    {
        return self::$errors;
    }

    /**
     * Request a shift swap between two volunteers.
     *
     * @param int   $fromUserId User requesting the swap
     * @param array $data       [from_shift_id, to_shift_id, to_user_id, message]
     * @return int|null Swap request ID or null on failure
     */
    public static function requestSwap(int $fromUserId, array $data): ?int
    {
        self::$errors = [];
        $tenantId = TenantContext::getId();

        $fromShiftId = (int) ($data['from_shift_id'] ?? 0);
        $toShiftId   = (int) ($data['to_shift_id'] ?? 0);
        $toUserId    = (int) ($data['to_user_id'] ?? 0);
        $message     = trim($data['message'] ?? '');

        if (! $fromShiftId || ! $toShiftId || ! $toUserId) {
            self::$errors[] = ['code' => 'VALIDATION_ERROR', 'message' => 'from_shift_id, to_shift_id, and to_user_id are required'];
            return null;
        }

        if ($fromUserId === $toUserId) {
            self::$errors[] = ['code' => 'VALIDATION_ERROR', 'message' => 'Cannot swap with yourself'];
            return null;
        }

        // Verify requester is signed up for from_shift
        $fromApp = VolApplication::where('shift_id', $fromShiftId)
            ->where('user_id', $fromUserId)
            ->where('status', 'approved')
            ->first();

        if (! $fromApp) {
            self::$errors[] = ['code' => 'FORBIDDEN', 'message' => 'You are not signed up for the source shift'];
            return null;
        }

        // Verify target user is signed up for to_shift
        $toApp = VolApplication::where('shift_id', $toShiftId)
            ->where('user_id', $toUserId)
            ->where('status', 'approved')
            ->first();

        if (! $toApp) {
            self::$errors[] = ['code' => 'VALIDATION_ERROR', 'message' => 'Target user is not signed up for the requested shift'];
            return null;
        }

        // Check both shifts are in the future
        $fromShift = VolShift::find($fromShiftId);
        $toShift   = VolShift::find($toShiftId);

        if (! $fromShift || ! $toShift) {
            self::$errors[] = ['code' => 'NOT_FOUND', 'message' => 'One or both shifts not found'];
            return null;
        }

        if ($fromShift->start_time->isPast() || $toShift->start_time->isPast()) {
            self::$errors[] = ['code' => 'VALIDATION_ERROR', 'message' => 'Cannot swap shifts that have already started'];
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
            self::$errors[] = ['code' => 'ALREADY_EXISTS', 'message' => 'A swap request already exists for these shifts'];
            return null;
        }

        // Check if admin approval is required (tenant setting)
        $requiresAdmin = self::requiresAdminApproval($tenantId);

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

            // Notify the target user about the incoming swap request
            self::notifySwap($toUserId, 'vol_swap_requested', 'You have a new shift swap request', '/volunteering?tab=swaps');

            return (int) $swapId;
        } catch (\Exception $e) {
            Log::error('ShiftSwapService::requestSwap error: ' . $e->getMessage());
            self::$errors[] = ['code' => 'SERVER_ERROR', 'message' => 'Failed to create swap request'];
            return null;
        }
    }

    /**
     * Respond to a swap request (accept/reject by target user).
     */
    public static function respond(int $swapId, int $userId, string $action): bool
    {
        self::$errors = [];
        $tenantId = TenantContext::getId();

        if (! in_array($action, ['accept', 'reject'])) {
            self::$errors[] = ['code' => 'VALIDATION_ERROR', 'message' => 'Action must be accept or reject'];
            return false;
        }

        $swap = DB::table('vol_shift_swap_requests')
            ->where('id', $swapId)
            ->where('status', 'pending')
            ->where('tenant_id', $tenantId)
            ->first();

        if (! $swap) {
            self::$errors[] = ['code' => 'NOT_FOUND', 'message' => 'Swap request not found or already processed'];
            return false;
        }

        if ((int) $swap->to_user_id !== $userId) {
            self::$errors[] = ['code' => 'FORBIDDEN', 'message' => 'This swap request is not addressed to you'];
            return false;
        }

        try {
            if ($action === 'reject') {
                DB::table('vol_shift_swap_requests')
                    ->where('id', $swapId)
                    ->where('tenant_id', $tenantId)
                    ->update(['status' => 'rejected']);

                // Notify requester their swap was declined
                self::notifySwap((int) $swap->from_user_id, 'vol_swap_declined', 'Your shift swap request was declined', '/volunteering?tab=swaps');

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
            $result = self::executeSwap($swap, $tenantId);

            if ($result) {
                // Notify requester their swap was approved
                self::notifySwap((int) $swap->from_user_id, 'vol_swap_approved', 'Your shift swap request was accepted', '/volunteering?tab=swaps');
            }

            return $result;
        } catch (\Exception $e) {
            Log::error('ShiftSwapService::respond error: ' . $e->getMessage());
            self::$errors[] = ['code' => 'SERVER_ERROR', 'message' => 'Failed to process swap response'];
            return false;
        }
    }

    /**
     * Admin approve/reject a swap.
     */
    public static function adminDecision(int $swapId, int $adminId, string $action): bool
    {
        self::$errors = [];
        $tenantId = TenantContext::getId();

        if (! in_array($action, ['approve', 'reject'])) {
            self::$errors[] = ['code' => 'VALIDATION_ERROR', 'message' => 'Action must be approve or reject'];
            return false;
        }

        $swap = DB::table('vol_shift_swap_requests')
            ->where('id', $swapId)
            ->where('status', 'admin_pending')
            ->where('tenant_id', $tenantId)
            ->first();

        if (! $swap) {
            self::$errors[] = ['code' => 'NOT_FOUND', 'message' => 'Swap request not found or not pending admin approval'];
            return false;
        }

        try {
            if ($action === 'reject') {
                DB::table('vol_shift_swap_requests')
                    ->where('id', $swapId)
                    ->where('tenant_id', $tenantId)
                    ->update(['status' => 'admin_rejected', 'admin_id' => $adminId]);

                // Notify both parties
                self::notifySwap((int) $swap->from_user_id, 'vol_swap_declined', 'Your shift swap request was declined by an admin', '/volunteering?tab=swaps');
                self::notifySwap((int) $swap->to_user_id, 'vol_swap_declined', 'A shift swap you accepted was declined by an admin', '/volunteering?tab=swaps');

                return true;
            }

            // Execute the swap — use admin_approved status
            $result = self::executeSwap($swap, $tenantId, 'admin_approved');

            if ($result) {
                DB::table('vol_shift_swap_requests')
                    ->where('id', $swapId)
                    ->where('tenant_id', $tenantId)
                    ->update(['admin_id' => $adminId]);

                // Notify both parties
                self::notifySwap((int) $swap->from_user_id, 'vol_swap_approved', 'Your shift swap was approved by an admin', '/volunteering?tab=swaps');
                self::notifySwap((int) $swap->to_user_id, 'vol_swap_approved', 'A shift swap you accepted was approved by an admin', '/volunteering?tab=swaps');
            }

            return $result;
        } catch (\Exception $e) {
            Log::error('ShiftSwapService::adminDecision error: ' . $e->getMessage());
            self::$errors[] = ['code' => 'SERVER_ERROR', 'message' => 'Failed to process admin decision'];
            return false;
        }
    }

    /**
     * Get swap requests for a user (incoming and outgoing).
     */
    public static function getSwapRequests(int $userId, string $direction = 'all'): array
    {
        $tenantId = TenantContext::getId();

        $query = DB::table('vol_shift_swap_requests as sr')
            ->join('users as fu', 'sr.from_user_id', '=', 'fu.id')
            ->join('users as tu', 'sr.to_user_id', '=', 'tu.id')
            ->join('vol_shifts as fs', 'sr.from_shift_id', '=', 'fs.id')
            ->join('vol_shifts as ts', 'sr.to_shift_id', '=', 'ts.id')
            ->leftJoin('vol_opportunities as fo', 'fs.opportunity_id', '=', 'fo.id')
            ->leftJoin('vol_organizations as forg', 'fo.organization_id', '=', 'forg.id')
            ->leftJoin('vol_opportunities as to_opp', 'ts.opportunity_id', '=', 'to_opp.id')
            ->leftJoin('vol_organizations as torg', 'to_opp.organization_id', '=', 'torg.id')
            ->where('sr.tenant_id', $tenantId)
            ->select(
                'sr.*',
                'fu.name as from_user_name', 'fu.avatar_url as from_user_avatar',
                'tu.name as to_user_name', 'tu.avatar_url as to_user_avatar',
                'fs.start_time as from_shift_start', 'fs.end_time as from_shift_end',
                'ts.start_time as to_shift_start', 'ts.end_time as to_shift_end',
                'fo.title as from_opp_title', 'forg.name as from_org_name',
                'to_opp.title as to_opp_title', 'torg.name as to_org_name'
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
                'direction'                => (int) $r->from_user_id === $userId ? 'sent' : 'received',
                'status'                   => $r->status,
                'message'                  => $r->message,
                'requires_admin_approval'  => (bool) $r->requires_admin_approval,
                'requester' => [
                    'id'         => (int) $r->from_user_id,
                    'name'       => $r->from_user_name,
                    'avatar_url' => $r->from_user_avatar,
                ],
                'recipient' => [
                    'id'         => (int) $r->to_user_id,
                    'name'       => $r->to_user_name,
                    'avatar_url' => $r->to_user_avatar,
                ],
                'original_shift' => [
                    'id'                => (int) $r->from_shift_id,
                    'start_time'        => $r->from_shift_start,
                    'end_time'          => $r->from_shift_end,
                    'opportunity_title' => $r->from_opp_title ?? null,
                    'organization_name' => $r->from_org_name ?? null,
                ],
                'proposed_shift' => [
                    'id'                => (int) $r->to_shift_id,
                    'start_time'        => $r->to_shift_start,
                    'end_time'          => $r->to_shift_end,
                    'opportunity_title' => $r->to_opp_title ?? null,
                    'organization_name' => $r->to_org_name ?? null,
                ],
                'created_at' => $r->created_at,
            ];
        })->all();
    }

    /**
     * Cancel a pending swap request.
     */
    public static function cancel(int $swapId, int $userId, int $tenantId): bool
    {
        self::$errors = [];

        $swap = DB::table('vol_shift_swap_requests')
            ->where('id', $swapId)
            ->where('from_user_id', $userId)
            ->whereIn('status', ['pending', 'admin_pending'])
            ->where('tenant_id', $tenantId)
            ->first();

        if (! $swap) {
            self::$errors[] = ['code' => 'NOT_FOUND', 'message' => 'Swap request not found or cannot be cancelled'];
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
            self::$errors[] = ['code' => 'SERVER_ERROR', 'message' => 'Failed to cancel swap request'];
            return false;
        }
    }

    /**
     * Execute the actual swap (move shift assignments) within a transaction.
     *
     * @param object $swap     The swap request row
     * @param int    $tenantId Current tenant
     * @param string $finalStatus Status to set on completion ('accepted' or 'admin_approved')
     */
    private static function executeSwap(object $swap, int $tenantId, string $finalStatus = 'accepted'): bool
    {
        try {
            return DB::transaction(function () use ($swap, $tenantId, $finalStatus) {
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
                    self::$errors[] = ['code' => 'VALIDATION_ERROR', 'message' => 'One or both volunteers are no longer assigned to the requested shifts'];
                    return false;
                }

                // Double-booking check: ensure neither user already has an overlapping
                // approved shift assignment for the shift they are moving INTO.
                if (self::hasOverlappingShift((int) $swap->from_user_id, (int) $swap->to_shift_id, (int) $swap->from_shift_id, $tenantId)) {
                    self::$errors[] = ['code' => 'VALIDATION_ERROR', 'message' => 'Swap would double-book the requester with an overlapping shift'];
                    return false;
                }
                if (self::hasOverlappingShift((int) $swap->to_user_id, (int) $swap->from_shift_id, (int) $swap->to_shift_id, $tenantId)) {
                    self::$errors[] = ['code' => 'VALIDATION_ERROR', 'message' => 'Swap would double-book the recipient with an overlapping shift'];
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
                    self::$errors[] = ['code' => 'SERVER_ERROR', 'message' => 'Failed to apply swap assignments atomically'];
                    throw new \RuntimeException('Swap update mismatch');
                }

                // Update swap status
                DB::table('vol_shift_swap_requests')
                    ->where('id', $swap->id)
                    ->where('tenant_id', $tenantId)
                    ->update(['status' => $finalStatus]);

                return true;
            });
        } catch (\RuntimeException $e) {
            return false;
        } catch (\Exception $e) {
            Log::error('ShiftSwapService::executeSwap error: ' . $e->getMessage());
            self::$errors[] = ['code' => 'SERVER_ERROR', 'message' => 'Failed to execute shift swap'];
            return false;
        }
    }

    /**
     * Check if a user has any approved shift assignment that overlaps with
     * the target shift's time window, excluding a specific shift being vacated.
     */
    private static function hasOverlappingShift(int $userId, int $targetShiftId, int $excludeShiftId, int $tenantId): bool
    {
        $targetShift = DB::table('vol_shifts')
            ->where('id', $targetShiftId)
            ->where('tenant_id', $tenantId)
            ->first();

        if (! $targetShift) {
            return false;
        }

        return DB::table('vol_applications as va')
            ->join('vol_shifts as vs', 'va.shift_id', '=', 'vs.id')
            ->where('va.user_id', $userId)
            ->where('va.status', 'approved')
            ->where('va.tenant_id', $tenantId)
            ->where('va.shift_id', '!=', $excludeShiftId) // exclude shift being vacated
            ->where('va.shift_id', '!=', $targetShiftId)  // exclude target itself
            ->where('vs.start_time', '<', $targetShift->end_time)
            ->where('vs.end_time', '>', $targetShift->start_time)
            ->exists();
    }

    /**
     * Get swap requests pending admin approval (for admin panel).
     */
    public static function getAdminPendingSwaps(): array
    {
        $tenantId = TenantContext::getId();

        $requests = DB::table('vol_shift_swap_requests as sr')
            ->join('users as fu', 'sr.from_user_id', '=', 'fu.id')
            ->join('users as tu', 'sr.to_user_id', '=', 'tu.id')
            ->join('vol_shifts as fs', 'sr.from_shift_id', '=', 'fs.id')
            ->join('vol_shifts as ts', 'sr.to_shift_id', '=', 'ts.id')
            ->leftJoin('vol_opportunities as fo', 'fs.opportunity_id', '=', 'fo.id')
            ->leftJoin('vol_opportunities as to_opp', 'ts.opportunity_id', '=', 'to_opp.id')
            ->where('sr.tenant_id', $tenantId)
            ->where('sr.status', 'admin_pending')
            ->select(
                'sr.*',
                'fu.name as from_user_name',
                'tu.name as to_user_name',
                'fs.start_time as from_shift_start', 'fs.end_time as from_shift_end',
                'ts.start_time as to_shift_start', 'ts.end_time as to_shift_end',
                'fo.title as from_opp_title',
                'to_opp.title as to_opp_title'
            )
            ->orderByDesc('sr.created_at')
            ->limit(100)
            ->get();

        return $requests->map(fn ($r) => [
            'id' => (int) $r->id,
            'status' => $r->status,
            'message' => $r->message,
            'requester' => ['id' => (int) $r->from_user_id, 'name' => $r->from_user_name],
            'recipient' => ['id' => (int) $r->to_user_id, 'name' => $r->to_user_name],
            'original_shift' => [
                'id' => (int) $r->from_shift_id,
                'start_time' => $r->from_shift_start,
                'end_time' => $r->from_shift_end,
                'opportunity_title' => $r->from_opp_title ?? null,
            ],
            'proposed_shift' => [
                'id' => (int) $r->to_shift_id,
                'start_time' => $r->to_shift_start,
                'end_time' => $r->to_shift_end,
                'opportunity_title' => $r->to_opp_title ?? null,
            ],
            'created_at' => $r->created_at,
        ])->all();
    }

    /**
     * Check if admin approval is required for swaps (tenant setting).
     */
    private static function requiresAdminApproval(int $tenantId): bool
    {
        try {
            $result = DB::table('tenant_settings')
                ->where('tenant_id', $tenantId)
                ->where('setting_key', 'volunteering.swap_requires_admin')
                ->value('setting_value');

            return filter_var($result, FILTER_VALIDATE_BOOLEAN);
        } catch (\Throwable $e) {
            return false;
        }
    }

    /**
     * Dispatch an in-app notification for a shift swap event.
     */
    private static function notifySwap(int $userId, string $activityType, string $content, string $link): void
    {
        try {
            NotificationDispatcher::dispatch(
                $userId,
                'global',
                null,
                $activityType,
                $content,
                $link,
                null,
                false
            );
        } catch (\Throwable $e) {
            // Notification failure should never block the swap operation
            Log::warning('ShiftSwapService: notification dispatch failed: ' . $e->getMessage());
        }
    }
}
