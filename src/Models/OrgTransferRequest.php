<?php
// Copyright © 2024–2026 Jasper Ford
// SPDX-License-Identifier: AGPL-3.0-or-later
// Author: Jasper Ford
// See NOTICE file for attribution and acknowledgements.

namespace Nexus\Models;

use Nexus\Core\Database;
use Nexus\Core\TenantContext;
use Nexus\Services\OrgNotificationService;

/**
 * OrgTransferRequest Model
 *
 * Handles transfer requests from organization wallets.
 * Members can request transfers, owners/admins approve or reject.
 */
class OrgTransferRequest
{
    /**
     * Create a new transfer request
     *
     * @param int $organizationId Organization wallet to transfer from
     * @param int $requesterId User making the request
     * @param int $recipientId User to receive the transfer
     * @param float $amount Amount requested
     * @param string $description Reason for transfer
     * @return int Request ID
     */
    public static function create($organizationId, $requesterId, $recipientId, $amount, $description = '')
    {
        if ($amount <= 0) {
            throw new \InvalidArgumentException('Amount must be positive');
        }

        $tenantId = TenantContext::getId();

        // Verify requester is a member
        if (!OrgMember::isMember($organizationId, $requesterId)) {
            throw new \Exception('Only organization members can request transfers');
        }

        Database::query(
            "INSERT INTO org_transfer_requests
             (tenant_id, organization_id, requester_id, recipient_id, amount, description, status)
             VALUES (?, ?, ?, ?, ?, ?, 'pending')",
            [$tenantId, $organizationId, $requesterId, $recipientId, $amount, $description]
        );

        $requestId = Database::getInstance()->lastInsertId();

        // Notify admins
        $admins = OrgMember::getAdmins($organizationId);
        $org = VolOrganization::find($organizationId);
        $orgName = $org ? $org['name'] : 'Organization';
        $requester = User::findById($requesterId);
        $requesterName = $requester ? "{$requester['first_name']} {$requester['last_name']}" : 'A member';

        foreach ($admins as $admin) {
            if ($admin['user_id'] != $requesterId) {
                Notification::create(
                    $admin['user_id'],
                    "$requesterName requested a transfer of $amount credits from $orgName",
                    TenantContext::getBasePath() . "/organizations/$organizationId/wallet",
                    'org_transfer_request'
                );
            }
        }

        return $requestId;
    }

    /**
     * Get a transfer request by ID
     */
    public static function find($id)
    {
        $tenantId = TenantContext::getId();

        return Database::query(
            "SELECT tr.*,
                    CONCAT(req.first_name, ' ', req.last_name) as requester_name,
                    req.email as requester_email,
                    CONCAT(rec.first_name, ' ', rec.last_name) as recipient_name,
                    rec.email as recipient_email,
                    vo.name as organization_name
             FROM org_transfer_requests tr
             JOIN users req ON tr.requester_id = req.id
             JOIN users rec ON tr.recipient_id = rec.id
             JOIN vol_organizations vo ON tr.organization_id = vo.id
             WHERE tr.id = ? AND tr.tenant_id = ?",
            [$id, $tenantId]
        )->fetch();
    }

    /**
     * Alias for find()
     */
    public static function getById($id)
    {
        return self::find($id);
    }

    /**
     * Approve a transfer request (owner/admin only)
     *
     * @param int $requestId Request to approve
     * @param int $approverId User approving (must be owner/admin)
     * @return int Transaction ID
     */
    public static function approve($requestId, $approverId)
    {
        $request = self::find($requestId);

        if (!$request) {
            throw new \Exception('Transfer request not found');
        }

        if ($request['status'] !== 'pending') {
            throw new \Exception('Transfer request is no longer pending');
        }

        // Verify approver is admin
        if (!OrgMember::isAdmin($request['organization_id'], $approverId)) {
            throw new \Exception('Only owners and admins can approve transfer requests');
        }

        // Approver cannot approve their own request
        if ($request['requester_id'] == $approverId) {
            throw new \Exception('You cannot approve your own transfer request');
        }

        $pdo = Database::getInstance();
        $pdo->beginTransaction();

        try {
            // Update request status
            Database::query(
                "UPDATE org_transfer_requests
                 SET status = 'approved', approved_by = ?, approved_at = NOW(), updated_at = NOW()
                 WHERE id = ?",
                [$approverId, $requestId]
            );

            // Execute the transfer
            $transactionId = OrgWallet::withdrawToUser(
                $request['organization_id'],
                $request['recipient_id'],
                $request['amount'],
                $request['description'] ?: 'Approved transfer request',
                $requestId
            );

            // Notify requester (platform + email)
            Notification::create(
                $request['requester_id'],
                "Your transfer request for {$request['amount']} credits has been approved",
                TenantContext::getBasePath() . '/wallet',
                'org_transfer_approved'
            );

            // Send email notification
            OrgNotificationService::notifyTransferRequestApproved(
                $request['requester_id'],
                $request['recipient_id'],
                $request['organization_id'],
                $request['amount'],
                $approverId
            );

            $pdo->commit();
            return $transactionId;

        } catch (\Exception $e) {
            $pdo->rollBack();
            throw $e;
        }
    }

    /**
     * Reject a transfer request (owner/admin only)
     *
     * @param int $requestId Request to reject
     * @param int $approverId User rejecting
     * @param string $reason Rejection reason
     */
    public static function reject($requestId, $approverId, $reason = '')
    {
        $request = self::find($requestId);

        if (!$request) {
            throw new \Exception('Transfer request not found');
        }

        if ($request['status'] !== 'pending') {
            throw new \Exception('Transfer request is no longer pending');
        }

        // Verify approver is admin
        if (!OrgMember::isAdmin($request['organization_id'], $approverId)) {
            throw new \Exception('Only owners and admins can reject transfer requests');
        }

        Database::query(
            "UPDATE org_transfer_requests
             SET status = 'rejected', approved_by = ?, approved_at = NOW(),
                 rejection_reason = ?, updated_at = NOW()
             WHERE id = ?",
            [$approverId, $reason, $requestId]
        );

        // Notify requester (platform + email)
        $reasonText = $reason ? " Reason: $reason" : '';
        Notification::create(
            $request['requester_id'],
            "Your transfer request for {$request['amount']} credits has been rejected.$reasonText",
            TenantContext::getBasePath() . "/organizations/{$request['organization_id']}/wallet",
            'org_transfer_rejected'
        );

        // Send email notification
        OrgNotificationService::notifyTransferRequestRejected(
            $request['requester_id'],
            $request['organization_id'],
            $request['amount'],
            $approverId,
            $reason
        );
    }

    /**
     * Cancel a transfer request (requester only)
     */
    public static function cancel($requestId, $userId)
    {
        $request = self::find($requestId);

        if (!$request) {
            throw new \Exception('Transfer request not found');
        }

        if ($request['status'] !== 'pending') {
            throw new \Exception('Transfer request is no longer pending');
        }

        if ($request['requester_id'] != $userId) {
            throw new \Exception('Only the requester can cancel their request');
        }

        Database::query(
            "UPDATE org_transfer_requests
             SET status = 'cancelled', updated_at = NOW()
             WHERE id = ?",
            [$requestId]
        );
    }

    /**
     * Get pending requests for an organization
     */
    public static function getPendingForOrganization($organizationId)
    {
        $tenantId = TenantContext::getId();

        return Database::query(
            "SELECT tr.*,
                    CONCAT(req.first_name, ' ', req.last_name) as requester_name,
                    CONCAT(rec.first_name, ' ', rec.last_name) as recipient_name
             FROM org_transfer_requests tr
             JOIN users req ON tr.requester_id = req.id
             JOIN users rec ON tr.recipient_id = rec.id
             WHERE tr.tenant_id = ? AND tr.organization_id = ? AND tr.status = 'pending'
             ORDER BY tr.created_at DESC",
            [$tenantId, $organizationId]
        )->fetchAll();
    }

    /**
     * Get all requests for an organization
     */
    public static function getAllForOrganization($organizationId, $limit = 50, $offset = 0)
    {
        $tenantId = TenantContext::getId();
        $limit = (int) $limit;
        $offset = (int) $offset;

        return Database::query(
            "SELECT tr.*,
                    CONCAT(req.first_name, ' ', req.last_name) as requester_name,
                    CONCAT(rec.first_name, ' ', rec.last_name) as recipient_name,
                    CONCAT(app.first_name, ' ', app.last_name) as approved_by_name
             FROM org_transfer_requests tr
             JOIN users req ON tr.requester_id = req.id
             JOIN users rec ON tr.recipient_id = rec.id
             LEFT JOIN users app ON tr.approved_by = app.id
             WHERE tr.tenant_id = ? AND tr.organization_id = ?
             ORDER BY tr.created_at DESC
             LIMIT $limit OFFSET $offset",
            [$tenantId, $organizationId]
        )->fetchAll();
    }

    /**
     * Get requests made by a user
     */
    public static function getByRequester($userId, $limit = 20)
    {
        $tenantId = TenantContext::getId();
        $limit = (int) $limit;

        return Database::query(
            "SELECT tr.*, vo.name as organization_name,
                    CONCAT(rec.first_name, ' ', rec.last_name) as recipient_name
             FROM org_transfer_requests tr
             JOIN vol_organizations vo ON tr.organization_id = vo.id
             JOIN users rec ON tr.recipient_id = rec.id
             WHERE tr.tenant_id = ? AND tr.requester_id = ?
             ORDER BY tr.created_at DESC
             LIMIT $limit",
            [$tenantId, $userId]
        )->fetchAll();
    }

    /**
     * Count pending requests for an organization
     */
    public static function countPending($organizationId)
    {
        $tenantId = TenantContext::getId();

        return (int) Database::query(
            "SELECT COUNT(*) FROM org_transfer_requests
             WHERE tenant_id = ? AND organization_id = ? AND status = 'pending'",
            [$tenantId, $organizationId]
        )->fetchColumn();
    }
}
