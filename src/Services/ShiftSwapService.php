<?php
// Copyright © 2024–2026 Jasper Ford
// SPDX-License-Identifier: AGPL-3.0-or-later
// Author: Jasper Ford
// See NOTICE file for attribution and acknowledgements.

namespace Nexus\Services;

use Nexus\Core\Database;
use Nexus\Core\TenantContext;
use Nexus\Models\VolShift;

/**
 * ShiftSwapService - Manages shift swapping between volunteers
 *
 * Allows volunteers to request trading shifts with qualified peers.
 * Supports optional admin approval for swaps.
 */
class ShiftSwapService
{
    private static array $errors = [];

    public static function getErrors(): array
    {
        return self::$errors;
    }

    /**
     * Request a shift swap
     *
     * @param int $fromUserId User requesting the swap
     * @param array $data [from_shift_id, to_shift_id, to_user_id, message]
     * @return int|null Swap request ID or null on failure
     */
    public static function requestSwap(int $fromUserId, array $data): ?int
    {
        self::$errors = [];
        $tenantId = TenantContext::getId();

        $fromShiftId = (int)($data['from_shift_id'] ?? 0);
        $toShiftId = (int)($data['to_shift_id'] ?? 0);
        $toUserId = (int)($data['to_user_id'] ?? 0);
        $message = trim($data['message'] ?? '');

        if (!$fromShiftId || !$toShiftId || !$toUserId) {
            self::$errors[] = ['code' => 'VALIDATION_ERROR', 'message' => 'from_shift_id, to_shift_id, and to_user_id are required'];
            return null;
        }

        if ($fromUserId === $toUserId) {
            self::$errors[] = ['code' => 'VALIDATION_ERROR', 'message' => 'Cannot swap with yourself'];
            return null;
        }

        $db = Database::getConnection();

        // Verify requester is signed up for from_shift
        $tenantId = TenantContext::getId();
        $stmt = $db->prepare("SELECT id FROM vol_applications WHERE shift_id = ? AND user_id = ? AND status = 'approved' AND tenant_id = ?");
        $stmt->execute([$fromShiftId, $fromUserId, $tenantId]);
        if (!$stmt->fetch()) {
            self::$errors[] = ['code' => 'FORBIDDEN', 'message' => 'You are not signed up for the source shift'];
            return null;
        }

        // Verify target user is signed up for to_shift
        $stmt = $db->prepare("SELECT id FROM vol_applications WHERE shift_id = ? AND user_id = ? AND status = 'approved' AND tenant_id = ?");
        $stmt->execute([$toShiftId, $toUserId, $tenantId]);
        if (!$stmt->fetch()) {
            self::$errors[] = ['code' => 'VALIDATION_ERROR', 'message' => 'Target user is not signed up for the requested shift'];
            return null;
        }

        // Check both shifts are in the future
        $fromShift = VolShift::find($fromShiftId);
        $toShift = VolShift::find($toShiftId);

        if (!$fromShift || !$toShift) {
            self::$errors[] = ['code' => 'NOT_FOUND', 'message' => 'One or both shifts not found'];
            return null;
        }

        if (strtotime($fromShift['start_time']) < time() || strtotime($toShift['start_time']) < time()) {
            self::$errors[] = ['code' => 'VALIDATION_ERROR', 'message' => 'Cannot swap shifts that have already started'];
            return null;
        }

        // Check for duplicate pending swap request
        $stmt = $db->prepare("
            SELECT id FROM vol_shift_swap_requests
            WHERE from_user_id = ? AND to_user_id = ? AND from_shift_id = ? AND to_shift_id = ?
            AND status IN ('pending', 'admin_pending')
            AND tenant_id = ?
        ");
        $stmt->execute([$fromUserId, $toUserId, $fromShiftId, $toShiftId, $tenantId]);
        if ($stmt->fetch()) {
            self::$errors[] = ['code' => 'ALREADY_EXISTS', 'message' => 'A swap request already exists for these shifts'];
            return null;
        }

        // Check if admin approval is required (tenant setting)
        $requiresAdmin = self::requiresAdminApproval();

        try {
            $stmt = $db->prepare("
                INSERT INTO vol_shift_swap_requests
                (tenant_id, from_user_id, to_user_id, from_shift_id, to_shift_id, status, requires_admin_approval, message, created_at)
                VALUES (?, ?, ?, ?, ?, 'pending', ?, ?, NOW())
            ");
            $stmt->execute([$tenantId, $fromUserId, $toUserId, $fromShiftId, $toShiftId, $requiresAdmin ? 1 : 0, $message]);

            $swapId = (int)$db->lastInsertId();

            // Notify target user
            try {
                $fromUser = \Nexus\Models\User::findById($fromUserId);
                $fromName = $fromUser['name'] ?? 'A volunteer';

                NotificationDispatcher::dispatch(
                    $toUserId,
                    'global',
                    0,
                    'volunteer_swap_request',
                    "{$fromName} wants to swap shifts with you. Review the request in your volunteering dashboard.",
                    '/volunteering',
                    null
                );
            } catch (\Throwable $e) {
                error_log("Swap notification failed: " . $e->getMessage());
            }

            return $swapId;
        } catch (\Exception $e) {
            error_log("ShiftSwapService::requestSwap error: " . $e->getMessage());
            self::$errors[] = ['code' => 'SERVER_ERROR', 'message' => 'Failed to create swap request'];
            return null;
        }
    }

    /**
     * Respond to a swap request (accept/reject by target user)
     *
     * @param int $swapId Swap request ID
     * @param int $userId Responding user (must be to_user_id)
     * @param string $action 'accept' or 'reject'
     * @return bool Success
     */
    public static function respond(int $swapId, int $userId, string $action): bool
    {
        self::$errors = [];
        $tenantId = TenantContext::getId();

        if (!in_array($action, ['accept', 'reject'])) {
            self::$errors[] = ['code' => 'VALIDATION_ERROR', 'message' => 'Action must be accept or reject'];
            return false;
        }

        $db = Database::getConnection();

        $stmt = $db->prepare("SELECT * FROM vol_shift_swap_requests WHERE id = ? AND status = 'pending' AND tenant_id = ?");
        $stmt->execute([$swapId, $tenantId]);
        $swap = $stmt->fetch(\PDO::FETCH_ASSOC);

        if (!$swap) {
            self::$errors[] = ['code' => 'NOT_FOUND', 'message' => 'Swap request not found or already processed'];
            return false;
        }

        if ((int)$swap['to_user_id'] !== $userId) {
            self::$errors[] = ['code' => 'FORBIDDEN', 'message' => 'This swap request is not addressed to you'];
            return false;
        }

        try {
            if ($action === 'reject') {
                $stmt = $db->prepare("UPDATE vol_shift_swap_requests SET status = 'rejected' WHERE id = ? AND tenant_id = ?");
                $stmt->execute([$swapId, $tenantId]);

                // Notify requester
                try {
                    NotificationDispatcher::dispatch(
                        (int)$swap['from_user_id'],
                        'global',
                        0,
                        'volunteer_swap_response',
                        "Your shift swap request has been declined.",
                        '/volunteering',
                        null
                    );
                } catch (\Throwable $e) {
                    // Silent fail
                }

                return true;
            }

            // Accept flow
            if ($swap['requires_admin_approval']) {
                // Move to admin pending
                $stmt = $db->prepare("UPDATE vol_shift_swap_requests SET status = 'admin_pending' WHERE id = ? AND tenant_id = ?");
                $stmt->execute([$swapId, $tenantId]);

                // Notify requester that admin approval is needed
                try {
                    NotificationDispatcher::dispatch(
                        (int)$swap['from_user_id'],
                        'global',
                        0,
                        'volunteer_swap_response',
                        "Your shift swap request has been accepted by the other volunteer and is pending admin approval.",
                        '/volunteering',
                        null
                    );
                } catch (\Throwable $e) {
                    // Silent fail
                }

                return true;
            }

            // Execute swap directly
            return self::executeSwap($swap);
        } catch (\Exception $e) {
            error_log("ShiftSwapService::respond error: " . $e->getMessage());
            self::$errors[] = ['code' => 'SERVER_ERROR', 'message' => 'Failed to process swap response'];
            return false;
        }
    }

    /**
     * Admin approve/reject a swap
     *
     * @param int $swapId Swap request ID
     * @param int $adminId Admin user ID
     * @param string $action 'approve' or 'reject'
     * @return bool Success
     */
    public static function adminDecision(int $swapId, int $adminId, string $action): bool
    {
        self::$errors = [];
        $tenantId = TenantContext::getId();

        if (!in_array($action, ['approve', 'reject'])) {
            self::$errors[] = ['code' => 'VALIDATION_ERROR', 'message' => 'Action must be approve or reject'];
            return false;
        }

        $db = Database::getConnection();

        $stmt = $db->prepare("SELECT * FROM vol_shift_swap_requests WHERE id = ? AND status = 'admin_pending' AND tenant_id = ?");
        $stmt->execute([$swapId, $tenantId]);
        $swap = $stmt->fetch(\PDO::FETCH_ASSOC);

        if (!$swap) {
            self::$errors[] = ['code' => 'NOT_FOUND', 'message' => 'Swap request not found or not pending admin approval'];
            return false;
        }

        try {
            if ($action === 'reject') {
                $stmt = $db->prepare("UPDATE vol_shift_swap_requests SET status = 'admin_rejected', admin_id = ? WHERE id = ? AND tenant_id = ?");
                $stmt->execute([$adminId, $swapId, $tenantId]);

                // Notify both users
                foreach ([(int)$swap['from_user_id'], (int)$swap['to_user_id']] as $uid) {
                    try {
                        NotificationDispatcher::dispatch(
                            $uid,
                            'global',
                            0,
                            'volunteer_swap_response',
                            "A shift swap request has been rejected by an admin.",
                            '/volunteering',
                            null
                        );
                    } catch (\Throwable $e) {
                        // Silent fail
                    }
                }

                return true;
            }

            // Execute the swap
            $result = self::executeSwap($swap);

            if ($result) {
                $stmt = $db->prepare("UPDATE vol_shift_swap_requests SET admin_id = ? WHERE id = ? AND tenant_id = ?");
                $stmt->execute([$adminId, $swapId, $tenantId]);
            }

            return $result;
        } catch (\Exception $e) {
            error_log("ShiftSwapService::adminDecision error: " . $e->getMessage());
            self::$errors[] = ['code' => 'SERVER_ERROR', 'message' => 'Failed to process admin decision'];
            return false;
        }
    }

    /**
     * Get swap requests for a user (incoming and outgoing)
     *
     * @param int $userId User ID
     * @param string $direction 'incoming', 'outgoing', or 'all'
     * @return array Swap requests
     */
    public static function getSwapRequests(int $userId, string $direction = 'all'): array
    {
        $db = Database::getConnection();
        $tenantId = TenantContext::getId();

        $sql = "
            SELECT sr.*,
                   fu.name as from_user_name, fu.avatar_url as from_user_avatar,
                   tu.name as to_user_name, tu.avatar_url as to_user_avatar,
                   fs.start_time as from_shift_start, fs.end_time as from_shift_end,
                   ts.start_time as to_shift_start, ts.end_time as to_shift_end
            FROM vol_shift_swap_requests sr
            JOIN users fu ON sr.from_user_id = fu.id
            JOIN users tu ON sr.to_user_id = tu.id
            JOIN vol_shifts fs ON sr.from_shift_id = fs.id
            JOIN vol_shifts ts ON sr.to_shift_id = ts.id
            WHERE sr.tenant_id = ?
        ";
        $params = [$tenantId];

        if ($direction === 'incoming') {
            $sql .= " AND sr.to_user_id = ?";
            $params[] = $userId;
        } elseif ($direction === 'outgoing') {
            $sql .= " AND sr.from_user_id = ?";
            $params[] = $userId;
        } else {
            $sql .= " AND (sr.from_user_id = ? OR sr.to_user_id = ?)";
            $params[] = $userId;
            $params[] = $userId;
        }

        $sql .= " ORDER BY sr.created_at DESC LIMIT 50";

        $stmt = $db->prepare($sql);
        $stmt->execute($params);
        $requests = $stmt->fetchAll(\PDO::FETCH_ASSOC);

        return array_map(function ($r) use ($userId) {
            return [
                'id' => (int)$r['id'],
                'direction' => (int)$r['from_user_id'] === $userId ? 'outgoing' : 'incoming',
                'status' => $r['status'],
                'message' => $r['message'],
                'requires_admin_approval' => (bool)$r['requires_admin_approval'],
                'from_user' => [
                    'id' => (int)$r['from_user_id'],
                    'name' => $r['from_user_name'],
                    'avatar_url' => $r['from_user_avatar'],
                ],
                'to_user' => [
                    'id' => (int)$r['to_user_id'],
                    'name' => $r['to_user_name'],
                    'avatar_url' => $r['to_user_avatar'],
                ],
                'from_shift' => [
                    'id' => (int)$r['from_shift_id'],
                    'start_time' => $r['from_shift_start'],
                    'end_time' => $r['from_shift_end'],
                ],
                'to_shift' => [
                    'id' => (int)$r['to_shift_id'],
                    'start_time' => $r['to_shift_start'],
                    'end_time' => $r['to_shift_end'],
                ],
                'created_at' => $r['created_at'],
            ];
        }, $requests);
    }

    /**
     * Cancel a pending swap request
     *
     * @param int $swapId Swap request ID
     * @param int $userId User cancelling (must be from_user_id)
     * @return bool Success
     */
    public static function cancel(int $swapId, int $userId): bool
    {
        self::$errors = [];
        $tenantId = TenantContext::getId();

        $db = Database::getConnection();

        $stmt = $db->prepare("SELECT * FROM vol_shift_swap_requests WHERE id = ? AND from_user_id = ? AND status IN ('pending', 'admin_pending') AND tenant_id = ?");
        $stmt->execute([$swapId, $userId, $tenantId]);
        $swap = $stmt->fetch(\PDO::FETCH_ASSOC);

        if (!$swap) {
            self::$errors[] = ['code' => 'NOT_FOUND', 'message' => 'Swap request not found or cannot be cancelled'];
            return false;
        }

        try {
            $stmt = $db->prepare("UPDATE vol_shift_swap_requests SET status = 'cancelled' WHERE id = ? AND tenant_id = ?");
            $stmt->execute([$swapId, $tenantId]);
            return true;
        } catch (\Exception $e) {
            error_log("ShiftSwapService::cancel error: " . $e->getMessage());
            self::$errors[] = ['code' => 'SERVER_ERROR', 'message' => 'Failed to cancel swap request'];
            return false;
        }
    }

    /**
     * Execute the actual swap (move shift assignments)
     */
    private static function executeSwap(array $swap): bool
    {
        $db = Database::getConnection();

        try {
            $db->beginTransaction();

            // Swap shift assignments in vol_applications
            $tenantId = TenantContext::getId();
            // User A: from_shift -> to_shift
            $stmt = $db->prepare("UPDATE vol_applications SET shift_id = ? WHERE user_id = ? AND shift_id = ? AND status = 'approved' AND tenant_id = ?");
            $stmt->execute([$swap['to_shift_id'], $swap['from_user_id'], $swap['from_shift_id'], $tenantId]);

            // User B: to_shift -> from_shift
            $stmt = $db->prepare("UPDATE vol_applications SET shift_id = ? WHERE user_id = ? AND shift_id = ? AND status = 'approved' AND tenant_id = ?");
            $stmt->execute([$swap['from_shift_id'], $swap['to_user_id'], $swap['to_shift_id'], $tenantId]);

            // Update swap status
            $stmt = $db->prepare("UPDATE vol_shift_swap_requests SET status = 'accepted' WHERE id = ? AND tenant_id = ?");
            $stmt->execute([$swap['id'], $tenantId]);

            $db->commit();

            // Notify both users
            foreach ([(int)$swap['from_user_id'], (int)$swap['to_user_id']] as $uid) {
                try {
                    NotificationDispatcher::dispatch(
                        $uid,
                        'global',
                        0,
                        'volunteer_swap_completed',
                        "Your shift swap has been completed successfully! Check your shifts for the updated schedule.",
                        '/volunteering',
                        null
                    );
                } catch (\Throwable $e) {
                    // Silent fail
                }
            }

            return true;
        } catch (\Exception $e) {
            $db->rollBack();
            error_log("ShiftSwapService::executeSwap error: " . $e->getMessage());
            self::$errors[] = ['code' => 'SERVER_ERROR', 'message' => 'Failed to execute shift swap'];
            return false;
        }
    }

    /**
     * Check if admin approval is required for swaps (tenant setting)
     */
    private static function requiresAdminApproval(): bool
    {
        try {
            $tenantId = TenantContext::getId();
            $db = Database::getConnection();
            $stmt = $db->prepare("SELECT setting_value FROM tenant_settings WHERE tenant_id = ? AND setting_key = 'volunteering.swap_requires_admin'");
            $stmt->execute([$tenantId]);
            $result = $stmt->fetch(\PDO::FETCH_ASSOC);
            return $result && $result['setting_value'] === '1';
        } catch (\Throwable $e) {
            return false; // Default: no admin approval needed
        }
    }
}
