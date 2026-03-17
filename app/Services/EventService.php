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

        if (! empty($filters['user_id'])) {
            $query->where('user_id', (int) $filters['user_id']);
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

    // ================================================================
    // LEGACY DELEGATION METHODS
    // These delegate to Nexus\Services\EventService (static) for
    // features not yet ported to Eloquent.
    // ================================================================

    /**
     * Get validation errors — delegates to legacy EventService.
     */
    public function getErrors(): array
    {
        return \Nexus\Services\EventService::getErrors();
    }

    /**
     * Get user's RSVP status for an event — delegates to legacy EventService.
     */
    public function getUserRsvp(int $eventId, int $userId): ?string
    {
        return \Nexus\Services\EventService::getUserRsvp($eventId, $userId);
    }

    /**
     * RSVP to an event — delegates to legacy EventService.
     */
    public function rsvp(int $eventId, int $userId, string $status): bool
    {
        return \Nexus\Services\EventService::rsvp($eventId, $userId, $status);
    }

    /**
     * Remove RSVP from an event — delegates to legacy EventService.
     */
    public function removeRsvp(int $eventId, int $userId): bool
    {
        return \Nexus\Services\EventService::removeRsvp($eventId, $userId);
    }

    /**
     * Get attendees for an event — delegates to legacy EventService.
     *
     * @return array{items: array, cursor: string|null, has_more: bool}
     */
    public function getAttendees(int $eventId, array $filters = []): array
    {
        return \Nexus\Services\EventService::getAttendees($eventId, $filters);
    }

    /**
     * Get nearby events — delegates to legacy EventService.
     *
     * @return array{items: array, has_more: bool}
     */
    public function getNearby(float $lat, float $lon, array $filters = []): array
    {
        return \Nexus\Services\EventService::getNearby($lat, $lon, $filters);
    }

    /**
     * Update event image — delegates to legacy EventService.
     */
    public function updateImage(int $eventId, int $userId, string $imageUrl): bool
    {
        return \Nexus\Services\EventService::updateImage($eventId, $userId, $imageUrl);
    }

    /**
     * Cancel an event — delegates to legacy EventService.
     */
    public function cancelEvent(int $eventId, int $userId, string $reason = ''): bool
    {
        return \Nexus\Services\EventService::cancelEvent($eventId, $userId, $reason);
    }

    /**
     * Add user to event waitlist — delegates to legacy EventService.
     */
    public function addToWaitlist(int $eventId, int $userId): bool
    {
        return \Nexus\Services\EventService::addToWaitlist($eventId, $userId);
    }

    /**
     * Remove user from event waitlist — delegates to legacy EventService.
     */
    public function removeFromWaitlist(int $eventId, int $userId): void
    {
        \Nexus\Services\EventService::removeFromWaitlist($eventId, $userId);
    }

    /**
     * Get event waitlist — delegates to legacy EventService.
     *
     * @return array{items: array, has_more: bool}
     */
    public function getWaitlist(int $eventId, array $filters = []): array
    {
        return \Nexus\Services\EventService::getWaitlist($eventId, $filters);
    }

    /**
     * Get user's waitlist position — delegates to legacy EventService.
     */
    public function getUserWaitlistPosition(int $eventId, int $userId): ?int
    {
        return \Nexus\Services\EventService::getUserWaitlistPosition($eventId, $userId);
    }

    /**
     * Get user's reminders for an event — delegates to legacy EventService.
     */
    public function getUserReminders(int $eventId, int $userId): array
    {
        return \Nexus\Services\EventService::getUserReminders($eventId, $userId);
    }

    /**
     * Update reminders for an event — delegates to legacy EventService.
     */
    public function updateReminders(int $eventId, int $userId, array $reminders): bool
    {
        return \Nexus\Services\EventService::updateReminders($eventId, $userId, $reminders);
    }

    /**
     * Get attendance records for an event — delegates to legacy EventService.
     */
    public function getAttendanceRecords(int $eventId): array
    {
        return \Nexus\Services\EventService::getAttendanceRecords($eventId);
    }

    /**
     * Mark a user as attended at an event — delegates to legacy EventService.
     */
    public function markAttended(int $eventId, int $attendeeId, int $markedById, ?float $hoursOverride = null, ?string $notes = null): bool
    {
        return \Nexus\Services\EventService::markAttended($eventId, $attendeeId, $markedById, $hoursOverride, $notes);
    }

    /**
     * Bulk mark users as attended — delegates to legacy EventService.
     */
    public function bulkMarkAttended(int $eventId, array $attendeeIds, int $markedById): array
    {
        return \Nexus\Services\EventService::bulkMarkAttended($eventId, $attendeeIds, $markedById);
    }

    /**
     * Get all event series — delegates to legacy EventService.
     *
     * @return array{items: array, has_more: bool}
     */
    public function getAllSeries(array $filters = []): array
    {
        return \Nexus\Services\EventService::getAllSeries($filters);
    }

    /**
     * Create an event series — delegates to legacy EventService.
     */
    public function createSeries(int $userId, string $title, ?string $description = null): ?int
    {
        return \Nexus\Services\EventService::createSeries($userId, $title, $description);
    }

    /**
     * Get series info — delegates to legacy EventService.
     */
    public function getSeriesInfo(int $seriesId): ?array
    {
        return \Nexus\Services\EventService::getSeriesInfo($seriesId);
    }

    /**
     * Get events in a series — delegates to legacy EventService.
     */
    public function getSeriesEvents(int $seriesId): array
    {
        return \Nexus\Services\EventService::getSeriesEvents($seriesId);
    }

    /**
     * Link an event to a series — delegates to legacy EventService.
     */
    public function linkToSeries(int $eventId, int $seriesId, int $userId): bool
    {
        return \Nexus\Services\EventService::linkToSeries($eventId, $seriesId, $userId);
    }

    /**
     * Create recurring event instances — delegates to legacy EventService.
     *
     * @return array|null {template_id: int, occurrences: int} or null on failure
     */
    public function createRecurring(int $userId, array $data): ?array
    {
        return \Nexus\Services\EventService::createRecurring($userId, $data);
    }

    /**
     * Update recurring event(s) — delegates to legacy EventService.
     */
    public function updateRecurring(int $eventId, int $userId, array $data, string $scope = 'single'): bool
    {
        return \Nexus\Services\EventService::updateRecurring($eventId, $userId, $data, $scope);
    }
}
