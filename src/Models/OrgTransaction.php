<?php
// Copyright © 2024–2026 Jasper Ford
// SPDX-License-Identifier: AGPL-3.0-or-later
// Author: Jasper Ford
// See NOTICE file for attribution and acknowledgements.

namespace Nexus\Models;

use Nexus\Core\Database;
use Nexus\Core\TenantContext;

/**
 * OrgTransaction Model
 *
 * Handles organization transaction logging (audit trail).
 * Records all money movement involving organization wallets.
 */
class OrgTransaction
{
    /**
     * Log a transaction
     *
     * @param int $organizationId Organization involved
     * @param string $senderType 'organization' or 'user'
     * @param int $senderId ID of sender (org or user)
     * @param string $receiverType 'organization' or 'user'
     * @param int $receiverId ID of receiver (org or user)
     * @param float $amount Transaction amount
     * @param string $description Transaction description
     * @param int|null $transferRequestId Optional linked transfer request
     * @return int Transaction ID
     */
    public static function log(
        $organizationId,
        $senderType,
        $senderId,
        $receiverType,
        $receiverId,
        $amount,
        $description = '',
        $transferRequestId = null
    ) {
        $tenantId = TenantContext::getId();

        Database::query(
            "INSERT INTO org_transactions
             (tenant_id, organization_id, transfer_request_id, sender_type, sender_id,
              receiver_type, receiver_id, amount, description)
             VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?)",
            [
                $tenantId,
                $organizationId,
                $transferRequestId,
                $senderType,
                $senderId,
                $receiverType,
                $receiverId,
                $amount,
                $description
            ]
        );

        return Database::getInstance()->lastInsertId();
    }

    /**
     * Get transaction by ID
     */
    public static function find($id)
    {
        $tenantId = TenantContext::getId();

        return Database::query(
            "SELECT * FROM org_transactions WHERE id = ? AND tenant_id = ?",
            [$id, $tenantId]
        )->fetch();
    }

    /**
     * Get transactions for an organization
     */
    public static function getForOrganization($organizationId, $limit = 50, $offset = 0)
    {
        $tenantId = TenantContext::getId();
        $limit = (int) $limit;
        $offset = (int) $offset;

        return Database::query(
            "SELECT ot.*,
                    CASE WHEN ot.sender_type = 'user'
                         THEN CONCAT(su.first_name, ' ', su.last_name)
                         ELSE 'Organization Wallet' END as sender_name,
                    CASE WHEN ot.receiver_type = 'user'
                         THEN CONCAT(ru.first_name, ' ', ru.last_name)
                         ELSE 'Organization Wallet' END as receiver_name
             FROM org_transactions ot
             LEFT JOIN users su ON ot.sender_type = 'user' AND ot.sender_id = su.id
             LEFT JOIN users ru ON ot.receiver_type = 'user' AND ot.receiver_id = ru.id
             WHERE ot.tenant_id = ? AND ot.organization_id = ?
             ORDER BY ot.created_at DESC
             LIMIT $limit OFFSET $offset",
            [$tenantId, $organizationId]
        )->fetchAll();
    }

    /**
     * Count transactions for an organization
     */
    public static function countForOrganization($organizationId)
    {
        $tenantId = TenantContext::getId();

        return (int) Database::query(
            "SELECT COUNT(*) FROM org_transactions
             WHERE tenant_id = ? AND organization_id = ?",
            [$tenantId, $organizationId]
        )->fetchColumn();
    }

    /**
     * Get monthly transaction stats for an organization
     */
    public static function getMonthlyStats($organizationId, $months = 6)
    {
        $tenantId = TenantContext::getId();
        $months = (int) $months;

        return Database::query(
            "SELECT
                DATE_FORMAT(created_at, '%Y-%m') as month,
                SUM(CASE WHEN receiver_type = 'organization' AND receiver_id = ? THEN amount ELSE 0 END) as received,
                SUM(CASE WHEN sender_type = 'organization' AND sender_id = ? THEN amount ELSE 0 END) as paid_out,
                COUNT(*) as transaction_count
             FROM org_transactions
             WHERE tenant_id = ? AND organization_id = ?
             AND created_at >= DATE_SUB(NOW(), INTERVAL $months MONTH)
             GROUP BY month
             ORDER BY month ASC",
            [$organizationId, $organizationId, $tenantId, $organizationId]
        )->fetchAll();
    }
}
