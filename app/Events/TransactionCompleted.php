<?php
// Copyright © 2024–2026 Jasper Ford
// SPDX-License-Identifier: AGPL-3.0-or-later
// Author: Jasper Ford
// See NOTICE file for attribution and acknowledgements.

namespace App\Events;

use App\Models\Transaction;
use App\Models\User;
use Illuminate\Broadcasting\InteractsWithSockets;
use Illuminate\Broadcasting\PrivateChannel;
use Illuminate\Contracts\Broadcasting\ShouldBroadcast;
use Illuminate\Foundation\Events\Dispatchable;
use Illuminate\Queue\SerializesModels;

/**
 * Fired when a time-credit transaction is completed between two users.
 *
 * Broadcasts privately to both the sender and receiver so their wallets
 * can update in real time.
 */
class TransactionCompleted implements ShouldBroadcast
{
    use Dispatchable, InteractsWithSockets, SerializesModels;

    public function __construct(
        public readonly Transaction $transaction,
        public readonly User $sender,
        public readonly User $receiver,
        public readonly int $tenantId,
    ) {}

    /**
     * Get the channels the event should broadcast on.
     *
     * @return array<int, \Illuminate\Broadcasting\PrivateChannel>
     */
    public function broadcastOn(): array
    {
        return [
            new PrivateChannel("tenant.{$this->tenantId}.user.{$this->sender->id}"),
            new PrivateChannel("tenant.{$this->tenantId}.user.{$this->receiver->id}"),
        ];
    }

    /**
     * The event's broadcast name.
     */
    public function broadcastAs(): string
    {
        return 'transaction.completed';
    }

    /**
     * Data to broadcast — only include fields the frontend needs.
     * Prevents leaking full User models (email, phone, balance, etc.)
     * and full Transaction model (internal notes, admin fields).
     *
     * @return array<string, mixed>
     */
    public function broadcastWith(): array
    {
        return [
            'id'          => $this->transaction->id,
            'amount'      => $this->transaction->amount,
            'description' => $this->transaction->description,
            'sender_id'   => $this->sender->id,
            'receiver_id' => $this->receiver->id,
            'created_at'  => $this->transaction->created_at?->toISOString(),
        ];
    }
}
