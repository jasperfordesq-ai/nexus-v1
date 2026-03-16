<?php
// Copyright © 2024–2026 Jasper Ford
// SPDX-License-Identifier: AGPL-3.0-or-later
// Author: Jasper Ford
// See NOTICE file for attribution and acknowledgements.

namespace App\Services;

use App\Models\Event;
use App\Models\EventRsvp;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

/**
 * EventService — Laravel DI-based service for event operations.
 *
 * Eloquent/DI counterpart to the legacy static \Nexus\Services\EventService.
 * All queries are tenant-scoped automatically via the HasTenantScope trait.
 */
class EventService
{
    public function __construct(
        private readonly Event $event,
        private readonly EventRsvp $rsvp,
    ) {}

    /**
     * Get events with cursor-based pagination.
     *
     * @return array{items: array, cursor: string|null, has_more: bool}
     */
    public function getAll(array $filters = []): array
    {
        $limit = min((int) ($filters['limit'] ?? 20), 100);
        $when = $filters['when'] ?? 'upcoming';
        $cursor = $filters['cursor'] ?? null;

        $query = $this->event->newQuery()
            ->with([
                'user:id,first_name,last_name,avatar_url,organization_name,profile_type',
                'category:id,name,color',
                'group:id,name',
            ]);

        if ($when === 'upcoming') {
            $query->where('start_time', '>=', now());
        } elseif ($when === 'past') {
            $query->where('start_time', '<', now());
        }

        if (! empty($filters['category_id'])) {
            $query->where('category_id', (int) $filters['category_id']);
        }

        if (! empty($filters['group_id'])) {
            $query->where('group_id', (int) $filters['group_id']);
        }

        if (! empty($filters['search'])) {
            $term = '%' . $filters['search'] . '%';
            $query->where(function (Builder $q) use ($term) {
                $q->where('title', 'LIKE', $term)
                  ->orWhere('description', 'LIKE', $term)
                  ->orWhere('location', 'LIKE', $term);
            });
        }

        if ($cursor !== null) {
            $cursorId = base64_decode($cursor, true);
            if ($cursorId !== false) {
                $query->where('id', '<', (int) $cursorId);
            }
        }

        $query->orderByDesc('start_time')->orderByDesc('id');

        $items = $query->limit($limit + 1)->get();
        $hasMore = $items->count() > $limit;
        if ($hasMore) {
            $items->pop();
        }

        $eventIds = $items->pluck('id');
        $rsvpCounts = $this->rsvp->newQuery()
            ->selectRaw('event_id, COUNT(*) as count')
            ->whereIn('event_id', $eventIds)
            ->where('status', 'going')
            ->groupBy('event_id')
            ->pluck('count', 'event_id');

        $result = $items->map(function (Event $event) use ($rsvpCounts) {
            $data = $event->toArray();
            $data['attendee_count'] = (int) ($rsvpCounts[$event->id] ?? 0);
            return $data;
        })->all();

        return [
            'items'    => array_values($result),
            'cursor'   => $hasMore && $items->isNotEmpty() ? base64_encode((string) $items->last()->id) : null,
            'has_more' => $hasMore,
        ];
    }

    /**
     * Get a single event by ID with attendees.
     */
    public function getById(int $id, ?int $currentUserId = null): ?array
    {
        /** @var Event|null $event */
        $event = $this->event->newQuery()
            ->with(['user', 'category', 'group', 'rsvps.user:id,first_name,last_name,avatar_url'])
            ->find($id);

        if (! $event) {
            return null;
        }

        $data = $event->toArray();
        $data['attendee_count'] = $event->rsvps->where('status', 'going')->count();

        if ($currentUserId) {
            $data['my_rsvp'] = $event->rsvps
                ->where('user_id', $currentUserId)
                ->first()?->status;
        }

        return $data;
    }

    /**
     * Create a new event.
     *
     * @throws ValidationException
     */
    public function create(int $userId, array $data): Event
    {
        validator($data, [
            'title'       => 'required|string|max:255',
            'description' => 'required|string',
            'start_time'  => 'required|date|after:now',
            'end_time'    => 'nullable|date|after:start_time',
            'location'    => 'nullable|string|max:255',
        ])->validate();

        return DB::transaction(function () use ($userId, $data) {
            $event = $this->event->newInstance([
                'user_id'              => $userId,
                'title'                => trim($data['title']),
                'description'          => trim($data['description']),
                'start_time'           => $data['start_time'],
                'end_time'             => $data['end_time'] ?? null,
                'location'             => $data['location'] ?? null,
                'latitude'             => $data['latitude'] ?? null,
                'longitude'            => $data['longitude'] ?? null,
                'category_id'          => $data['category_id'] ?? null,
                'group_id'             => $data['group_id'] ?? null,
                'max_attendees'        => $data['max_attendees'] ?? null,
                'is_online'            => $data['is_online'] ?? false,
                'online_link'          => $data['online_link'] ?? null,
                'image_url'            => $data['image_url'] ?? null,
                'federated_visibility' => $data['federated_visibility'] ?? 'none',
            ]);

            $event->save();

            return $event->fresh(['user', 'category']);
        });
    }

    /**
     * Update an existing event.
     */
    public function update(int $id, array $data): Event
    {
        /** @var Event $event */
        $event = $this->event->newQuery()->findOrFail($id);

        $allowed = [
            'title', 'description', 'start_time', 'end_time', 'location',
            'latitude', 'longitude', 'category_id', 'group_id', 'max_attendees',
            'is_online', 'online_link', 'image_url', 'federated_visibility',
        ];

        $event->fill(collect($data)->only($allowed)->all());
        $event->save();

        return $event->fresh(['user', 'category']);
    }

    /**
     * Delete an event.
     */
    public function delete(int $id): bool
    {
        /** @var Event|null $event */
        $event = $this->event->newQuery()->find($id);

        if (! $event) {
            return false;
        }

        $event->delete();

        return true;
    }
}
