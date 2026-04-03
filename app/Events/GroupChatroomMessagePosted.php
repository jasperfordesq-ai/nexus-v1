<?php
// Copyright © 2024–2026 Jasper Ford
// SPDX-License-Identifier: AGPL-3.0-or-later
// Author: Jasper Ford
// See NOTICE file for attribution and acknowledgements.

namespace App\Events;

use Illuminate\Broadcasting\InteractsWithSockets;
use Illuminate\Broadcasting\PrivateChannel;
use Illuminate\Contracts\Broadcasting\ShouldBroadcast;
use Illuminate\Foundation\Events\Dispatchable;
use Illuminate\Queue\SerializesModels;

/**
 * Fired when a user posts a message in a group chatroom.
 *
 * Broadcasts privately to the group channel so all group members
 * with an active Pusher subscription see the message in real time.
 */
class GroupChatroomMessagePosted implements ShouldBroadcast
{
    use Dispatchable, InteractsWithSockets, SerializesModels;

    public function __construct(
        public readonly int $tenantId,
        public readonly int $groupId,
        public readonly int $chatroomId,
        public readonly array $message,
    ) {}

    /**
     * Get the channels the event should broadcast on.
     *
     * @return array<int, \Illuminate\Broadcasting\PrivateChannel>
     */
    public function broadcastOn(): array
    {
        return [new PrivateChannel("tenant.{$this->tenantId}.group.{$this->groupId}")];
    }

    /**
     * The event's broadcast name.
     */
    public function broadcastAs(): string
    {
        return 'chatroom.message_posted';
    }

    /**
     * Data to broadcast — only include fields the frontend needs.
     *
     * @return array<string, mixed>
     */
    public function broadcastWith(): array
    {
        return [
            'chatroom_id' => $this->chatroomId,
            'message' => $this->message,
        ];
    }
}
