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
 * Eloquent/DI counterpart to the legacy static \Nexus\Services\WalletService.
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

        $totalEarned = (float) $this->transaction->newQuery()
            ->where('receiver_id', $userId)
            ->completed()
            ->sum('amount');

        $totalSpent = (float) $this->transaction->newQuery()
            ->where('sender_id', $userId)
            ->completed()
            ->sum('amount');

        return [
            'balance'           => (float) ($user->balance ?? 0),
            'total_earned'      => $totalEarned,
            'total_spent'       => $totalSpent,
            'transaction_count' => $this->transaction->newQuery()
                ->where(fn (Builder $q) => $q->where('sender_id', $userId)->orWhere('receiver_id', $userId))
                ->completed()
                ->count(),
            'currency'          => 'hours',
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

        if ($type === 'sent') {
            $query->where('sender_id', $userId);
        } elseif ($type === 'received') {
            $query->where('receiver_id', $userId);
        } else {
            $query->where(fn (Builder $q) => $q->where('sender_id', $userId)->orWhere('receiver_id', $userId));
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

        return [
            'items'    => $items->toArray(),
            'cursor'   => $hasMore && $items->isNotEmpty() ? base64_encode((string) $items->last()->id) : null,
            'has_more' => $hasMore,
        ];
    }

    /**
     * Transfer time credits between users.
     *
     * @throws \RuntimeException If insufficient balance.
     * @throws \InvalidArgumentException If amount is not positive.
     */
    public function transfer(int $senderId, int $receiverId, float $amount, string $description = ''): Transaction
    {
        if ($amount <= 0) {
            throw new \InvalidArgumentException('Amount must be positive');
        }

        return DB::transaction(function () use ($senderId, $receiverId, $amount, $description) {
            /** @var User $sender */
            $sender = $this->user->newQuery()->lockForUpdate()->findOrFail($senderId);

            if ((float) $sender->balance < $amount) {
                throw new \RuntimeException('Insufficient balance');
            }

            /** @var User $receiver */
            $receiver = $this->user->newQuery()->lockForUpdate()->findOrFail($receiverId);

            $txn = $this->transaction->newInstance([
                'sender_id'   => $senderId,
                'receiver_id' => $receiverId,
                'amount'      => $amount,
                'description' => $description,
                'status'      => 'completed',
            ]);
            $txn->save();

            $sender->decrement('balance', $amount);
            $receiver->increment('balance', $amount);

            return $txn->fresh(['sender', 'receiver']);
        });
    }
}
