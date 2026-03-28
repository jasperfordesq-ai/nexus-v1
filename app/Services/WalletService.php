<?php
// Copyright © 2024–2026 Jasper Ford
// SPDX-License-Identifier: AGPL-3.0-or-later
// Author: Jasper Ford
// See NOTICE file for attribution and acknowledgements.

namespace App\Services;

use App\Models\Transaction;
use App\Models\User;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Facades\DB;

/**
 * WalletService — Laravel DI-based service for time credit wallet operations.
 *
 * All queries are tenant-scoped automatically via the HasTenantScope trait.
 */
class WalletService
{
    public function __construct(
        private readonly Transaction $transaction,
        private readonly User $user,
    ) {}

    /**
     * Get wallet balance and summary for a user.
     *
     * @return array{balance: float, total_earned: float, total_spent: float, transaction_count: int, currency: string}
     */
    public function getBalance(int $userId): array
    {
        /** @var User|null $user */
        $user = $this->user->newQuery()->find($userId);

        // Single query to compute all balance aggregates (replaces 5 separate queries)
        $stats = $this->transaction->newQuery()
            ->where(fn (Builder $q) => $q->where('sender_id', $userId)->orWhere('receiver_id', $userId))
            ->selectRaw(
                "SUM(CASE WHEN receiver_id = ? AND status = 'completed' THEN amount ELSE 0 END) as total_earned,
                 SUM(CASE WHEN sender_id = ? AND status = 'completed' THEN amount ELSE 0 END) as total_spent,
                 SUM(CASE WHEN receiver_id = ? AND status = 'pending' THEN amount ELSE 0 END) as pending_incoming,
                 SUM(CASE WHEN sender_id = ? AND status = 'pending' THEN amount ELSE 0 END) as pending_outgoing,
                 COUNT(CASE WHEN status = 'completed' THEN 1 END) as tx_count",
                [$userId, $userId, $userId, $userId]
            )
            ->first();

        $totalEarned = (float) ($stats->total_earned ?? 0);
        $totalSpent = (float) ($stats->total_spent ?? 0);
        $pendingIncoming = (float) ($stats->pending_incoming ?? 0);
        $pendingOutgoing = (float) ($stats->pending_outgoing ?? 0);
        $txCount = (int) ($stats->tx_count ?? 0);

        return [
            'balance'           => (float) ($user->balance ?? 0),
            'total_earned'      => $totalEarned,
            'total_spent'       => $totalSpent,
            'transaction_count' => $txCount,
            'currency'          => 'hours',
            'pending_incoming'  => $pendingIncoming,
            'pending_outgoing'  => $pendingOutgoing,
            // Aliases for frontend compatibility
            'pending_in'        => $pendingIncoming,
            'pending_out'       => $pendingOutgoing,
        ];
    }

    /**
     * Get transaction history with cursor-based pagination.
     *
     * @param array{type?: string, cursor?: string|null, limit?: int} $filters
     * @return array{items: array, cursor: string|null, has_more: bool}
     */
    public function getTransactions(int $userId, array $filters = []): array
    {
        $limit = min((int) ($filters['limit'] ?? 20), 100);
        $type = $filters['type'] ?? 'all';
        $cursor = $filters['cursor'] ?? null;

        $query = $this->transaction->newQuery()
            ->with([
                'sender:id,first_name,last_name,avatar_url,organization_name,profile_type',
                'receiver:id,first_name,last_name,avatar_url,organization_name,profile_type',
            ])
            ->completed();

        // Filter by type and respect soft-delete flags
        if ($type === 'sent') {
            $query->where('sender_id', $userId)
                  ->where('deleted_for_sender', false);
        } elseif ($type === 'received') {
            $query->where('receiver_id', $userId)
                  ->where('deleted_for_receiver', false);
        } else {
            $query->where(function (Builder $q) use ($userId) {
                $q->where(function (Builder $q2) use ($userId) {
                    $q2->where('sender_id', $userId)->where('deleted_for_sender', false);
                })->orWhere(function (Builder $q2) use ($userId) {
                    $q2->where('receiver_id', $userId)->where('deleted_for_receiver', false);
                });
            });
        }

        if ($cursor !== null) {
            $cursorId = base64_decode($cursor, true);
            if ($cursorId !== false) {
                $query->where('id', '<', (int) $cursorId);
            }
        }

        $query->orderByDesc('id');

        $items = $query->limit($limit + 1)->get();
        $hasMore = $items->count() > $limit;
        if ($hasMore) {
            $items->pop();
        }

        // Format each transaction into the standard API shape expected by the frontend
        $formatted = $items->map(fn (Transaction $txn) => $this->formatTransaction($txn, $userId))->all();

        return [
            'items'    => $formatted,
            'cursor'   => $hasMore && $items->isNotEmpty() ? base64_encode((string) $items->last()->id) : null,
            'has_more' => $hasMore,
        ];
    }

    /**
     * Transfer time credits from one user to another.
     *
     * Accepts a flexible input array with recipient specified as
     * user_id, username, email, or generic "recipient" field.
     *
     * @param int   $senderId Sender user ID
     * @param array $data     Transfer data: recipient, amount, description
     * @return array Created transaction data
     *
     * @throws \InvalidArgumentException On validation failure
     * @throws \RuntimeException On insufficient balance or self-transfer
     */
    public function transfer(int $senderId, array $data): array
    {
        $recipient = $data['recipient'] ?? $data['user_id'] ?? $data['username'] ?? $data['email'] ?? null;
        $amount = (float) ($data['amount'] ?? 0);
        $description = trim($data['description'] ?? '');

        if (empty($recipient)) {
            throw new \InvalidArgumentException('Recipient is required');
        }

        if ($amount <= 0) {
            throw new \InvalidArgumentException('Amount must be greater than 0');
        }

        // Cap maximum transfer amount to prevent accidental or malicious large transfers
        if ($amount > 1000) {
            throw new \InvalidArgumentException('Transfer amount cannot exceed 1000 hours');
        }

        // Enforce reasonable decimal precision (max 2 decimal places)
        if (round($amount, 2) != $amount) {
            throw new \InvalidArgumentException('Amount must have at most 2 decimal places');
        }

        // Resolve recipient: ID, email, or username
        $receiver = null;
        if (is_numeric($recipient)) {
            $receiver = $this->user->newQuery()->find((int) $recipient);
        } elseif (filter_var($recipient, FILTER_VALIDATE_EMAIL)) {
            $receiver = $this->user->newQuery()->where('email', $recipient)->first();
        } else {
            $receiver = $this->user->newQuery()->where('username', $recipient)->first();
        }

        if (! $receiver) {
            throw new \RuntimeException('Recipient not found');
        }

        if ($senderId === $receiver->id) {
            throw new \RuntimeException('Cannot transfer to yourself');
        }

        $txn = DB::transaction(function () use ($senderId, $receiver, $amount, $description) {
            /** @var User $sender */
            $sender = $this->user->newQuery()->lockForUpdate()->findOrFail($senderId);

            if ((float) $sender->balance < $amount) {
                throw new \RuntimeException('Insufficient balance');
            }

            // Lock receiver row too
            $this->user->newQuery()->lockForUpdate()->findOrFail($receiver->id);

            $txn = $this->transaction->newInstance([
                'sender_id'   => $senderId,
                'receiver_id' => $receiver->id,
                'amount'      => $amount,
                'description' => $description,
                'status'      => 'completed',
            ]);
            $txn->save();

            $sender->decrement('balance', $amount);
            $receiver->increment('balance', $amount);

            return $txn->fresh(['sender', 'receiver']);
        });

        return $this->formatTransaction($txn, $senderId);
    }

    /**
     * Get a single transaction by ID for a specific user.
     *
     * @return array|null Transaction data or null if not found / not authorized
     */
    public function getTransaction(int $transactionId, int $userId): ?array
    {
        /** @var Transaction|null $txn */
        $txn = $this->transaction->newQuery()
            ->with([
                'sender:id,first_name,last_name,avatar_url,organization_name,profile_type',
                'receiver:id,first_name,last_name,avatar_url,organization_name,profile_type',
            ])
            ->where('id', $transactionId)
            ->where(function (Builder $q) use ($userId) {
                $q->where(function (Builder $q2) use ($userId) {
                    $q2->where('sender_id', $userId)->where('deleted_for_sender', false);
                })->orWhere(function (Builder $q2) use ($userId) {
                    $q2->where('receiver_id', $userId)->where('deleted_for_receiver', false);
                });
            })
            ->first();

        if (! $txn) {
            return null;
        }

        return $this->formatTransaction($txn, $userId);
    }

    /**
     * Hide (soft-delete) a transaction from a user's history.
     *
     * @return bool True on success, false if not found/not authorized
     */
    public function deleteTransaction(int $transactionId, int $userId): bool
    {
        /** @var Transaction|null $txn */
        $txn = $this->transaction->newQuery()
            ->where('id', $transactionId)
            ->where(fn (Builder $q) => $q->where('sender_id', $userId)->orWhere('receiver_id', $userId))
            ->first();

        if (! $txn) {
            return false;
        }

        if ($txn->sender_id === $userId) {
            $txn->deleted_for_sender = true;
        }
        if ($txn->receiver_id === $userId) {
            $txn->deleted_for_receiver = true;
        }

        $txn->save();

        return true;
    }

    /**
     * Search users for wallet transfer autocomplete.
     *
     * @return array Array of user summaries
     */
    public function searchUsers(int $excludeUserId, string $query, int $limit = 10): array
    {
        if (strlen($query) < 1) {
            return [];
        }

        $like = '%' . $query . '%';

        return $this->user->newQuery()
            ->where('id', '!=', $excludeUserId)
            ->where('status', '!=', 'banned')
            ->where(function (Builder $q) use ($like) {
                $q->where('first_name', 'LIKE', $like)
                  ->orWhere('last_name', 'LIKE', $like)
                  ->orWhere('username', 'LIKE', $like)
                  ->orWhere('organization_name', 'LIKE', $like)
                  ->orWhereRaw("CONCAT(first_name, ' ', last_name) LIKE ?", [$like]);
            })
            ->select('id', 'first_name', 'last_name', 'username', 'avatar_url', 'organization_name', 'profile_type')
            ->limit($limit)
            ->get()
            ->map(function (User $u) {
                $name = ($u->profile_type === 'organisation' && $u->organization_name)
                    ? $u->organization_name
                    : trim($u->first_name . ' ' . $u->last_name);

                return [
                    'id'         => $u->id,
                    'username'   => $u->username,
                    'name'       => $name,
                    'first_name' => $u->first_name,
                    'last_name'  => $u->last_name,
                    'avatar'     => $u->avatar_url,
                ];
            })
            ->all();
    }

    /**
     * Format a Transaction model into the standard API response shape.
     */
    private function formatTransaction(Transaction $txn, int $userId): array
    {
        $isSender = $txn->sender_id === $userId;
        // If sender_id is null (community fund grant), the user is always the receiver
        if ($txn->sender_id === null) {
            $isSender = false;
        }
        $sender = $txn->sender;
        $receiver = $txn->receiver;

        $txnType = $txn->transaction_type ?? 'transfer';

        $formatUser = function (?User $u) use ($txnType): array {
            if (! $u) {
                // Use a meaningful label for system transactions
                $label = match ($txnType) {
                    'donation'         => 'Community Fund',
                    'community_fund'   => 'Community Fund',
                    'starting_balance' => 'System',
                    'admin_grant'      => 'Admin',
                    default            => 'Unknown',
                };
                return ['id' => 0, 'name' => $label, 'avatar' => null];
            }
            $name = ($u->profile_type === 'organisation' && $u->organization_name)
                ? $u->organization_name
                : trim($u->first_name . ' ' . $u->last_name);
            return [
                'id'         => $u->id,
                'name'       => $name,
                'avatar'     => $u->avatar_url,
            ];
        };

        return [
            'id'               => $txn->id,
            'type'             => $isSender ? 'debit' : 'credit',
            'status'           => $txn->status ?? 'completed',
            'amount'           => (float) $txn->amount,
            'description'      => $txn->description,
            'transaction_type' => $txn->transaction_type ?? 'transfer',
            'sender'           => $formatUser($sender),
            'receiver'         => $formatUser($receiver),
            'other_user'       => $formatUser($isSender ? $receiver : $sender),
            'balance_after'    => null,
            'created_at'       => $txn->created_at?->toIso8601String(),
        ];
    }
}
