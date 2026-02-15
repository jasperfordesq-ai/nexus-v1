<?php

namespace Nexus\Services;

use Nexus\Core\Database;
use Nexus\Core\TenantContext;

/**
 * GroupExchangeService
 *
 * Manages multi-participant group exchanges with split types,
 * per-person confirmation, and broker approval.
 *
 * Workflow States:
 * 1. draft - Organizer is building the exchange
 * 2. pending_participants - Waiting for all participants to join
 * 3. pending_broker - Waiting for broker approval
 * 4. active - Exchange is in progress
 * 5. pending_confirmation - All participants must confirm hours
 * 6. completed - All confirmed, transactions created
 * 7. cancelled - Exchange cancelled
 * 8. disputed - Disagreement on hours
 *
 * Split Types:
 * - equal: Total hours divided equally among providers and receivers
 * - custom: Each participant has explicit hours
 * - weighted: Proportional distribution based on participant weights
 */
class GroupExchangeService
{
    /**
     * Create a new group exchange
     *
     * @param int $organizerId User creating the exchange
     * @param array $data Exchange data (title, description, split_type, total_hours, listing_id)
     * @return int|null Exchange ID or null on failure
     */
    public static function create(int $organizerId, array $data): ?int
    {
        $tenantId = TenantContext::getId();

        Database::query(
            "INSERT INTO group_exchanges (tenant_id, title, description, organizer_id, listing_id, split_type, total_hours, status)
             VALUES (?, ?, ?, ?, ?, ?, ?, 'draft')",
            [
                $tenantId,
                $data['title'],
                $data['description'] ?? null,
                $organizerId,
                $data['listing_id'] ?? null,
                $data['split_type'] ?? 'equal',
                (float) ($data['total_hours'] ?? 0),
            ]
        );

        return Database::lastInsertId();
    }

    /**
     * Get a group exchange by ID with participants
     *
     * @param int $id Exchange ID
     * @return array|null Exchange data with participants, or null if not found
     */
    public static function get(int $id): ?array
    {
        $tenantId = TenantContext::getId();

        $stmt = Database::query(
            "SELECT ge.*,
                    CONCAT(COALESCE(u.first_name, ''), ' ', COALESCE(u.last_name, '')) AS organizer_name,
                    u.avatar_url AS organizer_avatar
             FROM group_exchanges ge
             LEFT JOIN users u ON u.id = ge.organizer_id
             WHERE ge.id = ? AND ge.tenant_id = ?",
            [$id, $tenantId]
        );
        $exchange = $stmt->fetch();

        if (!$exchange) {
            return null;
        }

        // Get participants with user details
        $pStmt = Database::query(
            "SELECT gep.*,
                    CONCAT(COALESCE(u.first_name, ''), ' ', COALESCE(u.last_name, '')) AS user_name,
                    u.avatar_url AS user_avatar,
                    u.email AS user_email
             FROM group_exchange_participants gep
             LEFT JOIN users u ON u.id = gep.user_id
             WHERE gep.group_exchange_id = ?
             ORDER BY gep.role, gep.created_at",
            [$id]
        );
        $exchange['participants'] = $pStmt->fetchAll() ?: [];

        return $exchange;
    }

    /**
     * List group exchanges for a user (as organizer or participant)
     *
     * @param int $userId User ID
     * @param array $filters Optional filters (status, limit, offset)
     * @return array ['items' => [...], 'has_more' => bool]
     */
    public static function listForUser(int $userId, array $filters = []): array
    {
        $tenantId = TenantContext::getId();
        $limit = min((int) ($filters['limit'] ?? 20), 100);
        $offset = (int) ($filters['offset'] ?? 0);
        $status = $filters['status'] ?? null;

        $conditions = ['ge.tenant_id = ?'];
        $params = [$tenantId];

        // User must be organizer or participant
        $conditions[] = '(ge.organizer_id = ? OR EXISTS (SELECT 1 FROM group_exchange_participants gep WHERE gep.group_exchange_id = ge.id AND gep.user_id = ?))';
        $params[] = $userId;
        $params[] = $userId;

        if ($status) {
            $conditions[] = 'ge.status = ?';
            $params[] = $status;
        }

        $where = implode(' AND ', $conditions);

        $stmt = Database::query(
            "SELECT ge.*,
                    CONCAT(COALESCE(u.first_name, ''), ' ', COALESCE(u.last_name, '')) AS organizer_name,
                    u.avatar_url AS organizer_avatar,
                    (SELECT COUNT(*) FROM group_exchange_participants WHERE group_exchange_id = ge.id) AS participant_count
             FROM group_exchanges ge
             LEFT JOIN users u ON u.id = ge.organizer_id
             WHERE {$where}
             ORDER BY ge.created_at DESC
             LIMIT ? OFFSET ?",
            [...$params, $limit + 1, $offset]
        );

        $items = $stmt->fetchAll() ?: [];
        $hasMore = count($items) > $limit;

        if ($hasMore) {
            array_pop($items);
        }

        return ['items' => $items, 'has_more' => $hasMore];
    }

    /**
     * Add a participant to the exchange
     *
     * @param int $exchangeId Group exchange ID
     * @param int $userId User to add
     * @param string $role 'provider' or 'receiver'
     * @param float $hours Custom hours (for custom split)
     * @param float $weight Weight (for weighted split)
     * @return bool Success
     */
    public static function addParticipant(int $exchangeId, int $userId, string $role, float $hours = 0, float $weight = 1.0): bool
    {
        try {
            Database::query(
                "INSERT INTO group_exchange_participants (group_exchange_id, user_id, role, hours, weight)
                 VALUES (?, ?, ?, ?, ?)",
                [$exchangeId, $userId, $role, $hours, $weight]
            );
            return true;
        } catch (\Exception $e) {
            // Duplicate or constraint violation
            return false;
        }
    }

    /**
     * Remove a participant from the exchange
     *
     * @param int $exchangeId Group exchange ID
     * @param int $userId User to remove
     * @return bool Success
     */
    public static function removeParticipant(int $exchangeId, int $userId): bool
    {
        Database::query(
            "DELETE FROM group_exchange_participants WHERE group_exchange_id = ? AND user_id = ?",
            [$exchangeId, $userId]
        );
        return true;
    }

    /**
     * Confirm participation (current user confirms their hours)
     *
     * @param int $exchangeId Group exchange ID
     * @param int $userId User confirming
     * @return bool Success
     */
    public static function confirmParticipation(int $exchangeId, int $userId): bool
    {
        Database::query(
            "UPDATE group_exchange_participants SET confirmed = 1, confirmed_at = NOW()
             WHERE group_exchange_id = ? AND user_id = ?",
            [$exchangeId, $userId]
        );
        return true;
    }

    /**
     * Calculate the hour split based on split_type
     *
     * Returns array of [provider_id => [receiver_id => amount]]
     *
     * @param int $exchangeId Group exchange ID
     * @return array Split calculation
     */
    public static function calculateSplit(int $exchangeId): array
    {
        $exchange = self::get($exchangeId);
        if (!$exchange) {
            return [];
        }

        $providers = array_filter($exchange['participants'], fn($p) => $p['role'] === 'provider');
        $receivers = array_filter($exchange['participants'], fn($p) => $p['role'] === 'receiver');

        if (empty($providers) || empty($receivers)) {
            return [];
        }

        $splits = [];
        $totalHours = (float) $exchange['total_hours'];

        switch ($exchange['split_type']) {
            case 'equal':
                $perTransaction = $totalHours / count($providers) / count($receivers);
                foreach ($providers as $provider) {
                    foreach ($receivers as $receiver) {
                        $splits[(int) $provider['user_id']][(int) $receiver['user_id']] = round($perTransaction, 2);
                    }
                }
                break;

            case 'custom':
                // Each participant has explicit hours
                // Provider hours = how much they give, Receiver hours = how much they get
                // Distribute each provider's hours proportionally to receivers
                $totalReceiverHours = array_sum(array_column($receivers, 'hours'));
                if ($totalReceiverHours <= 0) {
                    break;
                }
                foreach ($providers as $provider) {
                    $providerHours = (float) $provider['hours'];
                    foreach ($receivers as $receiver) {
                        $receiverShare = (float) $receiver['hours'] / $totalReceiverHours;
                        $splits[(int) $provider['user_id']][(int) $receiver['user_id']] = round($providerHours * $receiverShare, 2);
                    }
                }
                break;

            case 'weighted':
                // Use weights to calculate proportional distribution
                $totalProviderWeight = array_sum(array_column($providers, 'weight'));
                $totalReceiverWeight = array_sum(array_column($receivers, 'weight'));
                if ($totalProviderWeight <= 0 || $totalReceiverWeight <= 0) {
                    break;
                }
                foreach ($providers as $provider) {
                    $providerShare = ((float) $provider['weight'] / $totalProviderWeight) * $totalHours;
                    foreach ($receivers as $receiver) {
                        $receiverShare = (float) $receiver['weight'] / $totalReceiverWeight;
                        $splits[(int) $provider['user_id']][(int) $receiver['user_id']] = round($providerShare * $receiverShare, 2);
                    }
                }
                break;
        }

        return $splits;
    }

    /**
     * Update exchange status
     *
     * @param int $exchangeId Group exchange ID
     * @param string $status New status
     * @return bool Success
     */
    public static function updateStatus(int $exchangeId, string $status): bool
    {
        $tenantId = TenantContext::getId();

        Database::query(
            "UPDATE group_exchanges SET status = ?, updated_at = NOW() WHERE id = ? AND tenant_id = ?",
            [$status, $exchangeId, $tenantId]
        );

        return true;
    }

    /**
     * Complete the exchange -- create all transactions atomically
     *
     * Validates:
     * - Exchange is in pending_confirmation status
     * - All participants have confirmed
     * - All providers have sufficient balance
     *
     * Creates transactions for each provider->receiver pair and updates balances.
     *
     * @param int $exchangeId Group exchange ID
     * @return array ['success' => bool, 'error' => string|null, 'transaction_ids' => int[]]
     */
    public static function complete(int $exchangeId): array
    {
        $exchange = self::get($exchangeId);

        if (!$exchange) {
            return ['success' => false, 'error' => 'Exchange not found'];
        }

        if ($exchange['status'] !== 'pending_confirmation') {
            return ['success' => false, 'error' => 'Exchange must be in pending_confirmation status'];
        }

        // Check all participants confirmed
        foreach ($exchange['participants'] as $p) {
            if (!$p['confirmed']) {
                return ['success' => false, 'error' => 'All participants must confirm before completing'];
            }
        }

        $splits = self::calculateSplit($exchangeId);

        if (empty($splits)) {
            return ['success' => false, 'error' => 'Could not calculate hour splits'];
        }

        // Validate all provider balances upfront
        foreach ($splits as $providerId => $receivers) {
            $totalSending = array_sum($receivers);
            $stmt = Database::query("SELECT balance FROM users WHERE id = ?", [$providerId]);
            $user = $stmt->fetch();

            if (!$user || (float) $user['balance'] < $totalSending) {
                return ['success' => false, 'error' => "Insufficient balance for user {$providerId}"];
            }
        }

        $tenantId = TenantContext::getId();

        Database::beginTransaction();
        try {
            $transactionIds = [];

            foreach ($splits as $providerId => $receivers) {
                foreach ($receivers as $receiverId => $amount) {
                    if ($amount <= 0) {
                        continue;
                    }

                    // Debit provider
                    Database::query(
                        "UPDATE users SET balance = balance - ? WHERE id = ? AND tenant_id = ?",
                        [$amount, $providerId, $tenantId]
                    );

                    // Credit receiver
                    Database::query(
                        "UPDATE users SET balance = balance + ? WHERE id = ? AND tenant_id = ?",
                        [$amount, $receiverId, $tenantId]
                    );

                    // Create transaction record
                    Database::query(
                        "INSERT INTO transactions (sender_id, receiver_id, amount, description, tenant_id, created_at)
                         VALUES (?, ?, ?, ?, ?, NOW())",
                        [$providerId, $receiverId, $amount, "Group exchange: {$exchange['title']}", $tenantId]
                    );
                    $transactionIds[] = Database::lastInsertId();
                }
            }

            // Mark exchange completed
            Database::query(
                "UPDATE group_exchanges SET status = 'completed', completed_at = NOW(), updated_at = NOW() WHERE id = ?",
                [$exchangeId]
            );

            Database::commit();

            return ['success' => true, 'transaction_ids' => $transactionIds];
        } catch (\Exception $e) {
            Database::rollback();
            return ['success' => false, 'error' => 'Transaction failed: ' . $e->getMessage()];
        }
    }

    /**
     * Update exchange details (organizer only)
     *
     * @param int $exchangeId Group exchange ID
     * @param array $data Fields to update (title, description, split_type, total_hours)
     * @return bool Success
     */
    public static function update(int $exchangeId, array $data): bool
    {
        $sets = [];
        $params = [];

        $allowed = ['title', 'description', 'split_type', 'total_hours'];
        foreach ($allowed as $field) {
            if (array_key_exists($field, $data)) {
                $sets[] = "{$field} = ?";
                $params[] = $data[$field];
            }
        }

        if (empty($sets)) {
            return false;
        }

        $sets[] = 'updated_at = NOW()';
        $params[] = $exchangeId;
        $params[] = TenantContext::getId();

        $setStr = implode(', ', $sets);
        Database::query("UPDATE group_exchanges SET {$setStr} WHERE id = ? AND tenant_id = ?", $params);

        return true;
    }
}
