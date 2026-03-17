<?php
// Copyright © 2024–2026 Jasper Ford
// SPDX-License-Identifier: AGPL-3.0-or-later
// Author: Jasper Ford
// See NOTICE file for attribution and acknowledgements.

namespace App\Services;

use App\Models\Connection;
use App\Models\User;
use Illuminate\Database\Eloquent\Builder;

/**
 * ConnectionService — Laravel DI-based service for user connections.
 *
 * Eloquent/DI counterpart to the legacy static \Nexus\Services\ConnectionService.
 * All queries are tenant-scoped automatically via the HasTenantScope trait.
 */
class ConnectionService
{
    public function __construct(
        private readonly Connection $connection,
    ) {}

    /**
     * Get connections for a user with cursor-based pagination.
     *
     * @param array{status?: string, cursor?: string|null, limit?: int} $filters
     * @return array{items: array, cursor: string|null, has_more: bool}
     */
    public function getAll(int $userId, array $filters = []): array
    {
        $limit = min((int) ($filters['limit'] ?? 20), 100);
        $status = $filters['status'] ?? 'accepted';
        $cursor = $filters['cursor'] ?? null;

        $query = $this->connection->newQuery()
            ->with([
                'requester:id,first_name,last_name,avatar_url,organization_name,profile_type,last_active_at',
                'receiver:id,first_name,last_name,avatar_url,organization_name,profile_type,last_active_at',
            ])
            ->where('status', $status);

        if ($status === 'pending') {
            // Show requests sent TO this user
            $query->where('receiver_id', $userId);
        } else {
            $query->where(fn (Builder $q) => $q->where('requester_id', $userId)->orWhere('receiver_id', $userId));
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

        // Map to include the partner user info
        $result = $items->map(function (Connection $conn) use ($userId) {
            $data = $conn->toArray();
            $data['partner'] = $conn->requester_id === $userId
                ? $conn->receiver?->toArray()
                : $conn->requester?->toArray();
            return $data;
        })->all();

        return [
            'items'    => array_values($result),
            'cursor'   => $hasMore && $items->isNotEmpty() ? base64_encode((string) $items->last()->id) : null,
            'has_more' => $hasMore,
        ];
    }

    /**
     * Send a connection request.
     *
     * @throws \RuntimeException
     */
    public function request(int $requesterId, int $receiverId): Connection
    {
        if ($requesterId === $receiverId) {
            throw new \RuntimeException('Cannot connect with yourself');
        }

        // Check for existing connection in either direction
        $existing = $this->connection->newQuery()
            ->where(function (Builder $q) use ($requesterId, $receiverId) {
                $q->where(function (Builder $q2) use ($requesterId, $receiverId) {
                    $q2->where('requester_id', $requesterId)->where('receiver_id', $receiverId);
                })->orWhere(function (Builder $q2) use ($requesterId, $receiverId) {
                    $q2->where('requester_id', $receiverId)->where('receiver_id', $requesterId);
                });
            })
            ->first();

        if ($existing) {
            throw new \RuntimeException('Connection already exists (status: ' . $existing->status . ')');
        }

        $connection = $this->connection->newInstance([
            'requester_id' => $requesterId,
            'receiver_id'  => $receiverId,
            'status'       => 'pending',
        ]);

        $connection->save();

        return $connection->fresh(['requester', 'receiver']);
    }

    /**
     * Accept a pending connection request.
     *
     * @throws \RuntimeException
     */
    public function accept(int $connectionId, int $userId): Connection
    {
        /** @var Connection $connection */
        $connection = $this->connection->newQuery()->findOrFail($connectionId);

        if ($connection->receiver_id !== $userId) {
            throw new \RuntimeException('Only the receiver can accept a connection request');
        }

        if ($connection->status !== 'pending') {
            throw new \RuntimeException('Connection is not pending');
        }

        $connection->status = 'accepted';
        $connection->save();

        return $connection->fresh(['requester', 'receiver']);
    }

    /**
     * Remove/cancel a connection.
     */
    public function destroy(int $connectionId, int $userId): bool
    {
        /** @var Connection|null $connection */
        $connection = $this->connection->newQuery()
            ->where('id', $connectionId)
            ->where(fn (Builder $q) => $q->where('requester_id', $userId)->orWhere('receiver_id', $userId))
            ->first();

        if (! $connection) {
            return false;
        }

        $connection->delete();

        return true;
    }

    /**
     * Get a single connection by ID, verifying the user is a participant.
     */
    public function getById(int $connectionId, int $userId): ?array
    {
        /** @var Connection|null $connection */
        $connection = $this->connection->newQuery()
            ->with(['requester:id,first_name,last_name,avatar_url', 'receiver:id,first_name,last_name,avatar_url'])
            ->where('id', $connectionId)
            ->where(fn (Builder $q) => $q->where('requester_id', $userId)->orWhere('receiver_id', $userId))
            ->first();

        if (! $connection) {
            return null;
        }

        return $connection->toArray();
    }

    /**
     * Get pending connection request counts for a user.
     *
     * @return array{received: int, sent: int, total_friends: int}
     */
    public function getPendingCounts(int $userId): array
    {
        $received = $this->connection->newQuery()
            ->where('receiver_id', $userId)
            ->where('status', 'pending')
            ->count();

        $sent = $this->connection->newQuery()
            ->where('requester_id', $userId)
            ->where('status', 'pending')
            ->count();

        $totalFriends = $this->connection->newQuery()
            ->where('status', 'accepted')
            ->where(fn (Builder $q) => $q->where('requester_id', $userId)->orWhere('receiver_id', $userId))
            ->count();

        return [
            'received'      => $received,
            'sent'          => $sent,
            'total_friends' => $totalFriends,
        ];
    }

    /**
     * Get connection status between two users.
     *
     * @return array{status: string, connection_id: int|null, direction: string|null}
     */
    public function getStatus(int $userId, int $otherUserId): array
    {
        /** @var Connection|null $connection */
        $connection = $this->connection->newQuery()
            ->where(function (Builder $q) use ($userId, $otherUserId) {
                $q->where(function (Builder $q2) use ($userId, $otherUserId) {
                    $q2->where('requester_id', $userId)->where('receiver_id', $otherUserId);
                })->orWhere(function (Builder $q2) use ($userId, $otherUserId) {
                    $q2->where('requester_id', $otherUserId)->where('receiver_id', $userId);
                });
            })
            ->first();

        if (! $connection) {
            return ['status' => 'none', 'connection_id' => null, 'direction' => null];
        }

        $direction = null;
        $status = $connection->status;

        if ($status === 'pending') {
            $direction = ($connection->requester_id === $userId) ? 'sent' : 'received';
            $status = "pending_{$direction}";
        } elseif ($status === 'accepted') {
            $status = 'connected';
        }

        return [
            'status'        => $status,
            'connection_id' => $connection->id,
            'direction'     => $direction,
        ];
    }

    /**
     * Send a connection request from input array.
     *
     * @param int   $requesterId Requesting user
     * @param array $data        Must contain 'user_id'
     * @return array Created connection data
     *
     * @throws \RuntimeException
     */
    public function sendRequest(int $requesterId, array $data): array
    {
        $receiverId = (int) ($data['user_id'] ?? 0);

        if (! $receiverId) {
            throw new \RuntimeException('User ID is required');
        }

        $connection = $this->request($requesterId, $receiverId);

        $status = $this->getStatus($requesterId, $receiverId);

        return [
            'status'        => $status['status'],
            'connection_id' => $status['connection_id'],
            'message'       => $status['status'] === 'connected'
                ? 'Connection accepted (they had already sent you a request)'
                : 'Connection request sent',
        ];
    }

    /**
     * Delete a connection (alias for destroy, used by controller).
     */
    public function delete(int $connectionId, int $userId): bool
    {
        return $this->destroy($connectionId, $userId);
    }
}
