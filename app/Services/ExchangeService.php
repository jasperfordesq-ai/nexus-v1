<?php
// Copyright © 2024–2026 Jasper Ford
// SPDX-License-Identifier: AGPL-3.0-or-later
// Author: Jasper Ford
// See NOTICE file for attribution and acknowledgements.

namespace App\Services;

use Illuminate\Support\Facades\DB;

/**
 * ExchangeService — Laravel DI-based service for exchange workflow operations.
 *
 * Eloquent/DI counterpart to the legacy static \Nexus\Services\ExchangeWorkflowService.
 * Manages the structured exchange lifecycle between members.
 */
class ExchangeService
{
    public const STATUS_PENDING = 'pending_provider';
    public const STATUS_ACCEPTED = 'accepted';
    public const STATUS_COMPLETED = 'completed';
    public const STATUS_DECLINED = 'declined';
    public const STATUS_CANCELLED = 'cancelled';

    /**
     * Get all exchanges for a user with cursor-based pagination.
     *
     * @return array{items: array, cursor: string|null, has_more: bool}
     */
    public function getAll(int $userId, array $filters = []): array
    {
        $limit = min((int) ($filters['limit'] ?? 20), 100);
        $cursor = $filters['cursor'] ?? null;

        $query = DB::table('exchange_requests as er')
            ->leftJoin('listings as l', 'er.listing_id', '=', 'l.id')
            ->where(fn ($q) => $q->where('er.requester_id', $userId)->orWhere('er.provider_id', $userId))
            ->select('er.*', 'l.title as listing_title');

        if (! empty($filters['status'])) {
            $query->where('er.status', $filters['status']);
        }
        if ($cursor !== null) {
            $query->where('er.id', '<', (int) base64_decode($cursor));
        }

        $query->orderByDesc('er.id');
        $items = $query->limit($limit + 1)->get();
        $hasMore = $items->count() > $limit;
        if ($hasMore) {
            $items->pop();
        }

        return [
            'items'    => $items->map(fn ($i) => (array) $i)->values()->all(),
            'cursor'   => $hasMore && $items->isNotEmpty() ? base64_encode((string) $items->last()->id) : null,
            'has_more' => $hasMore,
        ];
    }

    /**
     * Get a single exchange by ID.
     */
    public function getById(int $id): ?array
    {
        $exchange = DB::table('exchange_requests')->find($id);
        return $exchange ? (array) $exchange : null;
    }

    /**
     * Create a new exchange request.
     */
    public function create(int $requesterId, int $listingId, array $data = []): ?int
    {
        $listing = DB::table('listings')->find($listingId);
        if (! $listing) {
            return null;
        }
        if ($requesterId === (int) $listing->user_id) {
            return null;
        }

        return DB::table('exchange_requests')->insertGetId([
            'listing_id'      => $listingId,
            'requester_id'    => $requesterId,
            'provider_id'     => $listing->user_id,
            'proposed_hours'  => max(0.25, min(24, (float) ($data['proposed_hours'] ?? 1))),
            'requester_notes' => $data['message'] ?? null,
            'status'          => self::STATUS_PENDING,
            'created_at'      => now(),
            'updated_at'      => now(),
        ]);
    }

    /**
     * Accept an exchange request.
     */
    public function accept(int $exchangeId, int $providerId): bool
    {
        return DB::table('exchange_requests')
            ->where('id', $exchangeId)
            ->where('provider_id', $providerId)
            ->where('status', self::STATUS_PENDING)
            ->update(['status' => self::STATUS_ACCEPTED, 'updated_at' => now()]) > 0;
    }

    /**
     * Decline an exchange request.
     */
    public function decline(int $exchangeId, int $providerId, ?string $reason = null): bool
    {
        return DB::table('exchange_requests')
            ->where('id', $exchangeId)
            ->where('provider_id', $providerId)
            ->where('status', self::STATUS_PENDING)
            ->update([
                'status'         => self::STATUS_DECLINED,
                'provider_notes' => $reason,
                'updated_at'     => now(),
            ]) > 0;
    }

    /**
     * Complete an exchange.
     */
    public function complete(int $exchangeId, int $userId): bool
    {
        $exchange = DB::table('exchange_requests')->find($exchangeId);
        if (! $exchange || $exchange->status !== self::STATUS_ACCEPTED) {
            return false;
        }

        if ((int) $exchange->requester_id !== $userId && (int) $exchange->provider_id !== $userId) {
            return false;
        }

        return DB::table('exchange_requests')
            ->where('id', $exchangeId)
            ->update([
                'status'       => self::STATUS_COMPLETED,
                'completed_at' => now(),
                'updated_at'   => now(),
            ]) > 0;
    }
}
