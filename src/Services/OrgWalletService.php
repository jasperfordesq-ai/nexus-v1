<?php

namespace Nexus\Services;

use Nexus\Core\Database;
use Nexus\Core\TenantContext;
use Nexus\Models\OrgMember;
use Nexus\Models\OrgWallet;
use Nexus\Models\OrgTransferRequest;
use Nexus\Models\OrgTransaction;
use Nexus\Models\VolOrganization;
use Nexus\Models\User;
use Nexus\Models\Notification;
use Nexus\Services\OrgNotificationService;
use Nexus\Services\TransactionLimitService;
use Nexus\Services\AuditLogService;

/**
 * OrgWalletService
 *
 * Business logic layer for organization wallet operations.
 * Handles transfer workflows, permission checks, and coordination between models.
 */
class OrgWalletService
{
    /**
     * Create a transfer request from organization wallet
     *
     * Any member can request, but requires admin/owner approval.
     *
     * @param int $organizationId Organization to transfer from
     * @param int $requesterId User making the request
     * @param int $recipientId User to receive credits
     * @param float $amount Amount to transfer
     * @param string $description Reason for transfer
     * @return array ['success' => bool, 'message' => string, 'request_id' => int|null]
     */
    public static function createTransferRequest($organizationId, $requesterId, $recipientId, $amount, $description = '')
    {
        try {
            // Validate amount
            if ($amount <= 0) {
                return ['success' => false, 'message' => 'Amount must be positive'];
            }

            // Check requester is a member
            if (!OrgMember::isMember($organizationId, $requesterId)) {
                return ['success' => false, 'message' => 'You must be a member of this organization'];
            }

            // Check recipient exists
            $recipient = User::findById($recipientId);
            if (!$recipient) {
                return ['success' => false, 'message' => 'Recipient not found'];
            }

            // Check organization has sufficient balance
            $balance = OrgWallet::getBalance($organizationId);
            if ($balance < $amount) {
                return ['success' => false, 'message' => 'Insufficient organization wallet balance'];
            }

            // Check transaction limits
            $limitCheck = TransactionLimitService::checkLimits($organizationId, $recipientId, $amount, 'outgoing');
            if (!$limitCheck['allowed']) {
                return ['success' => false, 'message' => $limitCheck['reason']];
            }

            // If requester is admin/owner and requesting for themselves, auto-approve
            if ($requesterId === $recipientId && OrgMember::isAdmin($organizationId, $requesterId)) {
                return self::directTransferFromOrg($organizationId, $recipientId, $amount, $description, $requesterId);
            }

            // Create the request
            $requestId = OrgTransferRequest::create(
                $organizationId,
                $requesterId,
                $recipientId,
                $amount,
                $description
            );

            // Notify admins about the new request
            OrgNotificationService::notifyTransferRequestCreated($organizationId, $requesterId, $recipientId, $amount, $description);

            // Audit log
            AuditLogService::logTransferRequest($organizationId, $requesterId, $recipientId, $amount, $description);

            return [
                'success' => true,
                'message' => 'Transfer request submitted for approval',
                'request_id' => $requestId
            ];

        } catch (\Exception $e) {
            return ['success' => false, 'message' => $e->getMessage()];
        }
    }

    /**
     * Approve a pending transfer request
     *
     * @param int $requestId Request to approve
     * @param int $approverId User approving (must be owner/admin)
     * @return array ['success' => bool, 'message' => string, 'transaction_id' => int|null]
     */
    public static function approveRequest($requestId, $approverId)
    {
        try {
            // Get request details before approval for audit log
            $request = OrgTransferRequest::getById($requestId);

            $transactionId = OrgTransferRequest::approve($requestId, $approverId);

            // Audit log
            if ($request) {
                AuditLogService::logTransferApproval(
                    $request['organization_id'],
                    $approverId,
                    $requestId,
                    $request['recipient_id'],
                    $request['amount']
                );
            }

            return [
                'success' => true,
                'message' => 'Transfer request approved and executed',
                'transaction_id' => $transactionId
            ];

        } catch (\Exception $e) {
            return ['success' => false, 'message' => $e->getMessage()];
        }
    }

    /**
     * Reject a pending transfer request
     *
     * @param int $requestId Request to reject
     * @param int $approverId User rejecting (must be owner/admin)
     * @param string $reason Rejection reason
     * @return array ['success' => bool, 'message' => string]
     */
    public static function rejectRequest($requestId, $approverId, $reason = '')
    {
        try {
            // Get request details before rejection for audit log
            $request = OrgTransferRequest::getById($requestId);

            OrgTransferRequest::reject($requestId, $approverId, $reason);

            // Audit log
            if ($request) {
                AuditLogService::logTransferRejection(
                    $request['organization_id'],
                    $approverId,
                    $requestId,
                    $request['recipient_id'],
                    $reason
                );
            }

            return [
                'success' => true,
                'message' => 'Transfer request rejected'
            ];

        } catch (\Exception $e) {
            return ['success' => false, 'message' => $e->getMessage()];
        }
    }

    /**
     * Cancel a pending transfer request (requester only)
     *
     * @param int $requestId Request to cancel
     * @param int $userId User cancelling
     * @return array ['success' => bool, 'message' => string]
     */
    public static function cancelRequest($requestId, $userId)
    {
        try {
            OrgTransferRequest::cancel($requestId, $userId);

            return [
                'success' => true,
                'message' => 'Transfer request cancelled'
            ];

        } catch (\Exception $e) {
            return ['success' => false, 'message' => $e->getMessage()];
        }
    }

    /**
     * Deposit credits from user to organization wallet
     *
     * Any member can deposit their own credits.
     *
     * @param int $userId User depositing
     * @param int $organizationId Target organization
     * @param float $amount Amount to deposit
     * @param string $description Transaction description
     * @return array ['success' => bool, 'message' => string, 'transaction_id' => int|null]
     */
    public static function depositToOrg($userId, $organizationId, $amount, $description = '')
    {
        try {
            // Validate amount
            if ($amount <= 0) {
                return ['success' => false, 'message' => 'Amount must be positive'];
            }

            // Check user is a member
            if (!OrgMember::isMember($organizationId, $userId)) {
                return ['success' => false, 'message' => 'You must be a member of this organization'];
            }

            // Execute transfer
            $transactionId = OrgWallet::depositFromUser(
                $userId,
                $organizationId,
                $amount,
                $description ?: 'Deposit to organization wallet'
            );

            // Audit log
            AuditLogService::logTransaction($organizationId, $userId, 'deposit', $amount, null, $description);

            return [
                'success' => true,
                'message' => "Deposited $amount credits to organization wallet",
                'transaction_id' => $transactionId
            ];

        } catch (\Exception $e) {
            return ['success' => false, 'message' => $e->getMessage()];
        }
    }

    /**
     * Direct transfer from organization to user (admin/owner only)
     *
     * Bypasses approval workflow - for owners/admins only.
     *
     * @param int $organizationId Source organization
     * @param int $recipientId Target user
     * @param float $amount Amount to transfer
     * @param string $description Transaction description
     * @param int $authorizedBy User authorizing (must be admin/owner)
     * @return array ['success' => bool, 'message' => string, 'transaction_id' => int|null]
     */
    public static function directTransferFromOrg($organizationId, $recipientId, $amount, $description = '', $authorizedBy = null)
    {
        try {
            // Validate amount
            if ($amount <= 0) {
                return ['success' => false, 'message' => 'Amount must be positive'];
            }

            // Check authorizer is admin
            if ($authorizedBy && !OrgMember::isAdmin($organizationId, $authorizedBy)) {
                return ['success' => false, 'message' => 'Only owners and admins can make direct transfers'];
            }

            // Check transaction limits
            $limitCheck = TransactionLimitService::checkLimits($organizationId, $recipientId, $amount, 'outgoing');
            if (!$limitCheck['allowed']) {
                return ['success' => false, 'message' => $limitCheck['reason']];
            }

            // Execute transfer
            $transactionId = OrgWallet::withdrawToUser(
                $organizationId,
                $recipientId,
                $amount,
                $description ?: 'Direct transfer from organization wallet'
            );

            // Audit log
            AuditLogService::logTransaction($organizationId, $authorizedBy, 'withdrawal', $amount, $recipientId, $description);

            return [
                'success' => true,
                'message' => "Transferred $amount credits from organization wallet",
                'transaction_id' => $transactionId
            ];

        } catch (\Exception $e) {
            return ['success' => false, 'message' => $e->getMessage()];
        }
    }

    /**
     * Get organization wallet summary
     *
     * @param int $organizationId
     * @return array Wallet summary with balance and stats
     */
    public static function getWalletSummary($organizationId)
    {
        $wallet = OrgWallet::getOrCreate($organizationId);
        $totalReceived = OrgWallet::getTotalReceived($organizationId);
        $totalPaidOut = OrgWallet::getTotalPaidOut($organizationId);
        $transactionCount = OrgWallet::getTransactionCount($organizationId);
        $pendingRequests = OrgTransferRequest::countPending($organizationId);
        $recentTransactions = OrgWallet::getTransactionHistory($organizationId, 10);

        return [
            'balance' => (float) $wallet['balance'],
            'total_received' => $totalReceived,
            'total_paid_out' => $totalPaidOut,
            'transaction_count' => $transactionCount,
            'pending_requests' => $pendingRequests,
            'recent_transactions' => $recentTransactions
        ];
    }

    /**
     * Get pending transfer requests for organization
     *
     * @param int $organizationId
     * @param int $userId User viewing (for permission check)
     * @return array ['success' => bool, 'requests' => array]
     */
    public static function getPendingRequests($organizationId, $userId)
    {
        // Only admins can view pending requests
        if (!OrgMember::isAdmin($organizationId, $userId)) {
            return ['success' => false, 'message' => 'Access denied'];
        }

        $requests = OrgTransferRequest::getPendingForOrganization($organizationId);

        return [
            'success' => true,
            'requests' => $requests
        ];
    }

    /**
     * Get all transfer requests for organization
     *
     * @param int $organizationId
     * @param int $userId User viewing (for permission check)
     * @param int $limit
     * @param int $offset
     * @return array ['success' => bool, 'requests' => array]
     */
    public static function getAllRequests($organizationId, $userId, $limit = 50, $offset = 0)
    {
        // Only admins can view all requests
        if (!OrgMember::isAdmin($organizationId, $userId)) {
            return ['success' => false, 'message' => 'Access denied'];
        }

        $requests = OrgTransferRequest::getAllForOrganization($organizationId, $limit, $offset);

        return [
            'success' => true,
            'requests' => $requests
        ];
    }

    /**
     * Get user's own transfer requests
     *
     * @param int $userId
     * @param int $limit
     * @return array
     */
    public static function getUserRequests($userId, $limit = 20)
    {
        return OrgTransferRequest::getByRequester($userId, $limit);
    }

    /**
     * Bulk approve multiple requests
     *
     * @param array $requestIds Array of request IDs
     * @param int $approverId User approving
     * @return array ['success' => bool, 'approved' => int, 'failed' => int, 'errors' => array]
     */
    public static function bulkApprove(array $requestIds, $approverId)
    {
        $approved = 0;
        $failed = 0;
        $errors = [];
        $organizationId = null;

        foreach ($requestIds as $requestId) {
            $result = self::approveRequest($requestId, $approverId);
            if ($result['success']) {
                $approved++;
                // Get org ID from first successful request for audit log
                if (!$organizationId) {
                    $request = OrgTransferRequest::getById($requestId);
                    $organizationId = $request['organization_id'] ?? null;
                }
            } else {
                $failed++;
                $errors[$requestId] = $result['message'];
            }
        }

        // Log bulk operation
        if ($organizationId) {
            AuditLogService::logBulkApproval($organizationId, $approverId, $requestIds, $approved, $failed);
        }

        return [
            'success' => $failed === 0,
            'approved' => $approved,
            'failed' => $failed,
            'errors' => $errors
        ];
    }

    /**
     * Bulk reject multiple requests
     *
     * @param array $requestIds Array of request IDs
     * @param int $approverId User rejecting
     * @param string $reason Rejection reason (same for all)
     * @return array ['success' => bool, 'rejected' => int, 'failed' => int, 'errors' => array]
     */
    public static function bulkReject(array $requestIds, $approverId, $reason = '')
    {
        $rejected = 0;
        $failed = 0;
        $errors = [];
        $organizationId = null;

        foreach ($requestIds as $requestId) {
            $result = self::rejectRequest($requestId, $approverId, $reason);
            if ($result['success']) {
                $rejected++;
                // Get org ID from first successful request for audit log
                if (!$organizationId) {
                    $request = OrgTransferRequest::getById($requestId);
                    $organizationId = $request['organization_id'] ?? null;
                }
            } else {
                $failed++;
                $errors[$requestId] = $result['message'];
            }
        }

        // Log bulk operation
        if ($organizationId) {
            AuditLogService::logBulkRejection($organizationId, $approverId, $requestIds, $rejected, $failed, $reason);
        }

        return [
            'success' => $failed === 0,
            'rejected' => $rejected,
            'failed' => $failed,
            'errors' => $errors
        ];
    }
}
