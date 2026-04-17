<?php
// Copyright © 2024–2026 Jasper Ford
// SPDX-License-Identifier: AGPL-3.0-or-later
// Author: Jasper Ford
// See NOTICE file for attribution and acknowledgements.

declare(strict_types=1);

namespace App\Events;

use App\Models\Connection;
use App\Models\User;
use Illuminate\Broadcasting\InteractsWithSockets;
use Illuminate\Broadcasting\PrivateChannel;
use Illuminate\Contracts\Broadcasting\ShouldBroadcast;
use Illuminate\Contracts\Broadcasting\ShouldRescue;
use Illuminate\Foundation\Events\Dispatchable;
use Illuminate\Queue\SerializesModels;

/**
 * Fired when the receiver of a connection request accepts it.
 *
 * ConnectionRequested fires at request time; this is the counterpart for the
 * acceptance transition.  Broadcasts to the original requester so they get
 * a real-time notification.  Federation listeners use this to propagate the
 * accepted relationship to external partners.
 */
class ConnectionAccepted implements ShouldBroadcast, ShouldRescue
{
    use Dispatchable, InteractsWithSockets, SerializesModels;

    public function __construct(
        public readonly Connection $connectionModel,
        public readonly User $requester,
        public readonly User $acceptor,
        public readonly int $tenantId,
    ) {}

    /**
     * Broadcast to the original requester so they see the acceptance in real-time.
     *
     * @return array<int, \Illuminate\Broadcasting\PrivateChannel>
     */
    public function broadcastOn(): array
    {
        return [
            new PrivateChannel("tenant.{$this->tenantId}.user.{$this->requester->id}"),
        ];
    }

    public function broadcastAs(): string
    {
        return 'connection.accepted';
    }

    /**
     * @return array<string, mixed>
     */
    public function broadcastWith(): array
    {
        return [
            'id'           => $this->connectionModel->id,
            'acceptor_id'  => $this->acceptor->id,
            'acceptor_name' => trim(
                ($this->acceptor->first_name ?? '') . ' ' . ($this->acceptor->last_name ?? '')
            ) ?: ($this->acceptor->name ?? 'Someone'),
            'created_at'   => $this->connectionModel->created_at?->toISOString(),
        ];
    }
}
