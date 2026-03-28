<?php
// Copyright © 2024–2026 Jasper Ford
// SPDX-License-Identifier: AGPL-3.0-or-later
// Author: Jasper Ford
// See NOTICE file for attribution and acknowledgements.

namespace App\Events;

use App\Models\Connection;
use App\Models\User;
use Illuminate\Broadcasting\InteractsWithSockets;
use Illuminate\Broadcasting\PrivateChannel;
use Illuminate\Contracts\Broadcasting\ShouldBroadcast;
use Illuminate\Foundation\Events\Dispatchable;
use Illuminate\Queue\SerializesModels;

/**
 * Fired when one user sends a connection request to another.
 *
 * Broadcasts privately to the target user for real-time notification.
 */
class ConnectionRequested implements ShouldBroadcast
{
    use Dispatchable, InteractsWithSockets, SerializesModels;

    public function __construct(
        public readonly Connection $connection,
        public readonly User $requester,
        public readonly User $target,
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
            new PrivateChannel("tenant.{$this->tenantId}.user.{$this->target->id}"),
        ];
    }

    /**
     * The event's broadcast name.
     */
    public function broadcastAs(): string
    {
        return 'connection.requested';
    }

    /**
     * Data to broadcast — only include fields the frontend needs.
     * Prevents leaking full User models (email, phone, etc.)
     * and full Connection model internals.
     *
     * @return array<string, mixed>
     */
    public function broadcastWith(): array
    {
        return [
            'id'           => $this->connection->id,
            'requester_id' => $this->requester->id,
            'requester_name' => trim(
                ($this->requester->first_name ?? '') . ' ' . ($this->requester->last_name ?? '')
            ) ?: ($this->requester->name ?? 'Someone'),
            'created_at'   => $this->connection->created_at?->toISOString(),
        ];
    }
}
