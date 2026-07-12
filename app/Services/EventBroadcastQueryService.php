<?php
// Copyright © 2024–2026 Jasper Ford
// SPDX-License-Identifier: AGPL-3.0-or-later
// Author: Jasper Ford
// See NOTICE file for attribution and acknowledgements.

declare(strict_types=1);

namespace App\Services;

use App\Exceptions\EventBroadcastException;
use App\Http\Resources\EventBroadcastHistoryResource;
use App\Http\Resources\EventBroadcastResource;
use App\Models\EventBroadcast;
use App\Models\EventBroadcastHistory;
use App\Models\User;
use App\Support\Events\EventBroadcastFoundationSupport;

/** Authorized organizer read model; all participant detail remains internal. */
final class EventBroadcastQueryService
{
    public function __construct(
        private readonly EventBroadcastFoundationSupport $support = new EventBroadcastFoundationSupport(),
    ) {
    }

    /** @return array{items:list<array<string,mixed>>,total:int,page:int,per_page:int} */
    public function paginateForEvent(
        int $eventId,
        User|int $actor,
        int $page = 1,
        int $perPage = 20,
    ): array {
        $this->support->assertSchema();
        $tenantId = $this->support->tenantId();
        $event = $this->support->event($tenantId, $eventId);
        $persistedActor = $this->support->actor($tenantId, $actor);
        $this->support->authorize($persistedActor, $event);
        $page = max(1, $page);
        $perPage = max(1, min(100, $perPage));
        $query = EventBroadcast::withoutGlobalScopes()
            ->where('tenant_id', $tenantId)
            ->where('event_id', $eventId);
        $total = (clone $query)->count();
        $items = $query
            ->orderByDesc('created_at')
            ->orderByDesc('id')
            ->offset(($page - 1) * $perPage)
            ->limit($perPage)
            ->get()
            ->map(static fn (EventBroadcast $broadcast): array =>
                EventBroadcastResource::fromModel($broadcast, false))
            ->all();

        return [
            'items' => $items,
            'total' => $total,
            'page' => $page,
            'per_page' => $perPage,
        ];
    }

    /** @return array{broadcast:array<string,mixed>,history:list<array<string,mixed>>} */
    public function detail(int $broadcastId, User|int $actor): array
    {
        $this->support->assertSchema();
        $tenantId = $this->support->tenantId();
        $broadcast = EventBroadcast::withoutGlobalScopes()
            ->where('tenant_id', $tenantId)
            ->whereKey($broadcastId)
            ->first();
        if ($broadcast === null) {
            throw new EventBroadcastException('event_broadcast_not_found');
        }
        $event = $this->support->event($tenantId, (int) $broadcast->event_id);
        $persistedActor = $this->support->actor($tenantId, $actor);
        $this->support->authorize($persistedActor, $event);
        $history = EventBroadcastHistory::withoutGlobalScopes()
            ->where('tenant_id', $tenantId)
            ->where('broadcast_id', $broadcastId)
            ->orderBy('broadcast_version')
            ->get()
            ->map(static fn (EventBroadcastHistory $item): array =>
                EventBroadcastHistoryResource::fromModel($item))
            ->all();

        return [
            'broadcast' => EventBroadcastResource::fromModel($broadcast),
            'history' => $history,
        ];
    }
}
