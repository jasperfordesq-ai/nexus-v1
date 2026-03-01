<?php
// Copyright © 2024–2026 Jasper Ford
// SPDX-License-Identifier: AGPL-3.0-or-later
// Author: Jasper Ford
// See NOTICE file for attribution and acknowledgements.

namespace Nexus\Services;

use Nexus\Core\Database;
use Nexus\Core\TenantContext;
use Nexus\Models\User;

/**
 * CreditDonationService (W6)
 *
 * Manages credit donations — distinct from exchanges.
 * Members can donate credits to:
 * - Another member (peer-to-peer donation)
 * - The community fund
 */
class CreditDonationService
{
    /**
     * Donate credits to another member
     *
     * @param int $donorId Donor user ID
     * @param int $recipientId Recipient user ID
     * @param float $amount Amount to donate
     * @param string $message Optional donation message
     * @return array ['success' => bool, 'error' => string|null, 'donation_id' => int|null]
     */
    public static function donateToMember(int $donorId, int $recipientId, float $amount, string $message = ''): array
    {
        if ($amount <= 0) {
            return ['success' => false, 'error' => 'Amount must be greater than 0'];
        }

        if ($donorId === $recipientId) {
            return ['success' => false, 'error' => 'Cannot donate to yourself'];
        }

        $tenantId = TenantContext::getId();

        // Check donor balance
        $donor = User::findById($donorId);
        if (!$donor || (float) ($donor['balance'] ?? 0) < $amount) {
            return ['success' => false, 'error' => 'Insufficient balance'];
        }

        // Check recipient exists
        $recipient = User::findById($recipientId);
        if (!$recipient) {
            return ['success' => false, 'error' => 'Recipient not found'];
        }

        Database::beginTransaction();
        try {
            // Deduct from donor (atomic check)
            $deductStmt = Database::query(
                "UPDATE users SET balance = balance - ? WHERE id = ? AND tenant_id = ? AND balance >= ?",
                [$amount, $donorId, $tenantId, $amount]
            );
            if ($deductStmt->rowCount() === 0) {
                Database::rollback();
                return ['success' => false, 'error' => 'Insufficient balance'];
            }

            // Credit recipient
            Database::query(
                "UPDATE users SET balance = balance + ? WHERE id = ? AND tenant_id = ?",
                [$amount, $recipientId, $tenantId]
            );

            // Create transaction record
            Database::query(
                "INSERT INTO transactions (tenant_id, sender_id, receiver_id, amount, description, transaction_type)
                 VALUES (?, ?, ?, ?, ?, 'donation')",
                [$tenantId, $donorId, $recipientId, $amount, 'Donation' . ($message ? ": $message" : '')]
            );
            $transactionId = Database::lastInsertId();

            // Create donation record
            Database::query(
                "INSERT INTO credit_donations (tenant_id, donor_id, recipient_type, recipient_id, amount, message, transaction_id)
                 VALUES (?, ?, 'user', ?, ?, ?, ?)",
                [$tenantId, $donorId, $recipientId, $amount, $message, $transactionId]
            );
            $donationId = Database::lastInsertId();

            Database::commit();

            // Notify recipient
            $donorName = $donor['name'] ?? trim(($donor['first_name'] ?? '') . ' ' . ($donor['last_name'] ?? ''));
            NotificationDispatcher::send($recipientId, 'credit_donation_received', [
                'donor_id' => $donorId,
                'donor_name' => $donorName,
                'amount' => $amount,
                'message' => $message,
            ]);

            return ['success' => true, 'donation_id' => (int) $donationId];
        } catch (\Exception $e) {
            Database::rollback();
            error_log("CreditDonationService::donateToMember error: " . $e->getMessage());
            return ['success' => false, 'error' => 'Donation failed'];
        }
    }

    /**
     * Donate credits to the community fund
     * Delegates to CommunityFundService
     *
     * @param int $donorId Donor user ID
     * @param float $amount Amount to donate
     * @param string $message Optional message
     * @return array ['success' => bool, 'error' => string|null]
     */
    public static function donateToCommunityFund(int $donorId, float $amount, string $message = ''): array
    {
        return CommunityFundService::receiveDonation($donorId, $amount, $message);
    }

    /**
     * Get donation history for a user
     *
     * @param int $userId User ID
     * @param int $limit Max records
     * @param int $offset Offset
     * @return array ['items' => [...], 'total' => int]
     */
    public static function getDonationHistory(int $userId, int $limit = 20, int $offset = 0): array
    {
        $tenantId = TenantContext::getId();

        $countStmt = Database::query(
            "SELECT COUNT(*) as total FROM credit_donations
             WHERE tenant_id = ? AND (donor_id = ? OR (recipient_type = 'user' AND recipient_id = ?))",
            [$tenantId, $userId, $userId]
        );
        $total = (int) ($countStmt->fetch(\PDO::FETCH_ASSOC)['total'] ?? 0);

        $stmt = Database::query(
            "SELECT cd.*,
                    donor.name as donor_name, donor.avatar_url as donor_avatar,
                    recip.name as recipient_name, recip.avatar_url as recipient_avatar
             FROM credit_donations cd
             JOIN users donor ON cd.donor_id = donor.id
             LEFT JOIN users recip ON cd.recipient_type = 'user' AND cd.recipient_id = recip.id
             WHERE cd.tenant_id = ? AND (cd.donor_id = ? OR (cd.recipient_type = 'user' AND cd.recipient_id = ?))
             ORDER BY cd.created_at DESC
             LIMIT ? OFFSET ?",
            [$tenantId, $userId, $userId, $limit, $offset]
        );

        return [
            'items' => $stmt->fetchAll(\PDO::FETCH_ASSOC) ?: [],
            'total' => $total,
        ];
    }
}
