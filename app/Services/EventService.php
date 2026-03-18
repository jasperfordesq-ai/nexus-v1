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
use Illuminate\Support\Facades\Log;
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

    /** @var array Validation error messages */
    private array $errors = [];

    // ================================================================
    // CONVERTED FROM LEGACY — Direct DB facade calls
    // ================================================================

    /**
     * Get validation errors from the last operation.
     */
    public function getErrors(): array
    {
        return $this->errors;
    }

    /**
     * Get user's RSVP status for an event.
     */
    public function getUserRsvp(int $eventId, int $userId): ?string
    {
        $tenantId = \App\Core\TenantContext::getId();
        $row = DB::selectOne(
            "SELECT status FROM event_rsvps WHERE event_id = ? AND user_id = ? AND tenant_id = ?",
            [$eventId, $userId, $tenantId]
        );
        return $row ? $row->status : null;
    }

    /**
     * RSVP to an event with capacity enforcement.
     */
    public function rsvp(int $eventId, int $userId, string $status): bool
    {
        $this->errors = [];

        $validStatuses = ['going', 'interested', 'not_going', 'declined'];
        if (!in_array($status, $validStatuses)) {
            $this->errors[] = ['code' => 'VALIDATION_ERROR', 'message' => 'Invalid RSVP status', 'field' => 'status'];
            return false;
        }

        $tenantId = \App\Core\TenantContext::getId();
        $event = DB::selectOne("SELECT * FROM events WHERE id = ? AND tenant_id = ?", [$eventId, $tenantId]);
        if (!$event) {
            $this->errors[] = ['code' => 'NOT_FOUND', 'message' => 'Event not found'];
            return false;
        }

        if (($event->status ?? 'active') === 'cancelled') {
            $this->errors[] = ['code' => 'EVENT_CANCELLED', 'message' => 'This event has been cancelled'];
            return false;
        }

        // Capacity enforcement for 'going' status
        if ($status === 'going' && !empty($event->max_attendees)) {
            $maxAttendees = (int) $event->max_attendees;
            $currentGoing = (int) DB::selectOne("SELECT COUNT(*) as cnt FROM event_rsvps WHERE event_id = ? AND tenant_id = ? AND status = 'going'", [$eventId, $tenantId])->cnt;
            $currentUserStatus = $this->getUserRsvp($eventId, $userId);
            $isAlreadyGoing = ($currentUserStatus === 'going');

            if (!$isAlreadyGoing && $currentGoing >= $maxAttendees) {
                $this->addToWaitlist($eventId, $userId);
                $this->errors[] = [
                    'code' => 'EVENT_FULL',
                    'message' => 'This event is full. You have been added to the waitlist.',
                    'waitlisted' => true,
                ];
                return false;
            }
        }

        try {
            // Upsert RSVP
            DB::statement(
                "INSERT INTO event_rsvps (event_id, user_id, tenant_id, status, created_at)
                 VALUES (?, ?, ?, ?, NOW())
                 ON DUPLICATE KEY UPDATE status = VALUES(status), updated_at = NOW()",
                [$eventId, $userId, $tenantId, $status]
            );

            // If going, remove from waitlist
            if ($status === 'going') {
                $this->removeFromWaitlist($eventId, $userId);
            }

            return true;
        } catch (\Exception $e) {
            \Illuminate\Support\Facades\Log::error("EventService::rsvp error: " . $e->getMessage());
            $this->errors[] = ['code' => 'SERVER_ERROR', 'message' => 'Failed to update RSVP'];
            return false;
        }
    }

    /**
     * Remove RSVP from an event (with waitlist promotion).
     */
    public function removeRsvp(int $eventId, int $userId): bool
    {
        $this->errors = [];
        $tenantId = \App\Core\TenantContext::getId();

        $event = DB::selectOne("SELECT id FROM events WHERE id = ? AND tenant_id = ?", [$eventId, $tenantId]);
        if (!$event) {
            $this->errors[] = ['code' => 'NOT_FOUND', 'message' => 'Event not found'];
            return false;
        }

        try {
            $currentStatus = $this->getUserRsvp($eventId, $userId);

            DB::delete("DELETE FROM event_rsvps WHERE event_id = ? AND user_id = ? AND tenant_id = ?", [$eventId, $userId, $tenantId]);

            // Cancel reminders
            DB::update(
                "UPDATE event_reminders SET status = 'cancelled' WHERE event_id = ? AND user_id = ? AND status = 'pending' AND tenant_id = ?",
                [$eventId, $userId, $tenantId]
            );

            return true;
        } catch (\Exception $e) {
            \Illuminate\Support\Facades\Log::error("EventService::removeRsvp error: " . $e->getMessage());
            $this->errors[] = ['code' => 'SERVER_ERROR', 'message' => 'Failed to remove RSVP'];
            return false;
        }
    }

    /**
     * Get attendees for an event with cursor-based pagination.
     *
     * @return array{items: array, cursor: string|null, has_more: bool}
     */
    public function getAttendees(int $eventId, array $filters = []): array
    {
        $limit = min($filters['limit'] ?? 20, 100);
        $status = $filters['status'] ?? 'going';
        $cursor = $filters['cursor'] ?? null;

        $validStatuses = ['going', 'interested', 'invited', 'attended'];
        if (!in_array($status, $validStatuses)) {
            $status = 'going';
        }

        $cursorId = null;
        if ($cursor) {
            $decoded = base64_decode($cursor, true);
            if ($decoded && is_numeric($decoded)) {
                $cursorId = (int) $decoded;
            }
        }

        $params = [$eventId, $status];
        $cursorSql = '';
        if ($cursorId) {
            $cursorSql = ' AND r.id > ?';
            $params[] = $cursorId;
        }
        $params[] = $limit + 1;

        $tenantId = \App\Core\TenantContext::getId();
        array_splice($params, 2, 0, [$tenantId]);

        $rows = DB::select(
            "SELECT r.id as rsvp_id, r.user_id, r.status, r.created_at as rsvp_at,
                   u.name, u.first_name, u.last_name, u.avatar_url
            FROM event_rsvps r
            JOIN users u ON r.user_id = u.id
            WHERE r.event_id = ? AND r.status = ? AND r.tenant_id = ?{$cursorSql}
            ORDER BY r.id ASC LIMIT ?",
            $params
        );

        $attendees = array_map(fn($r) => (array) $r, $rows);
        $hasMore = count($attendees) > $limit;
        if ($hasMore) {
            array_pop($attendees);
        }

        $items = [];
        $lastId = null;

        foreach ($attendees as $att) {
            $lastId = $att['rsvp_id'];
            $items[] = [
                'id' => (int) $att['user_id'],
                'name' => $att['name'] ?? trim(($att['first_name'] ?? '') . ' ' . ($att['last_name'] ?? '')),
                'first_name' => $att['first_name'] ?? null,
                'last_name' => $att['last_name'] ?? null,
                'avatar' => $att['avatar_url'],
                'avatar_url' => $att['avatar_url'],
                'rsvp_status' => $att['status'],
                'status' => $att['status'],
                'rsvp_at' => $att['rsvp_at'],
            ];
        }

        return [
            'items' => $items,
            'cursor' => $hasMore && $lastId ? base64_encode((string) $lastId) : null,
            'has_more' => $hasMore,
        ];
    }

    /**
     * Get nearby events.
     * // TODO: Convert to Eloquent (depends on Event::getNearby with Haversine)
     */
    public function getNearby(float $lat, float $lon, array $filters = []): array
    {
        return \Nexus\Services\EventService::getNearby($lat, $lon, $filters);
    }

    /**
     * Update event cover image.
     */
    public function updateImage(int $eventId, int $userId, string $imageUrl): bool
    {
        $this->errors = [];
        $tenantId = \App\Core\TenantContext::getId();

        $event = DB::selectOne("SELECT id, user_id FROM events WHERE id = ? AND tenant_id = ?", [$eventId, $tenantId]);
        if (!$event) {
            $this->errors[] = ['code' => 'NOT_FOUND', 'message' => 'Event not found'];
            return false;
        }

        if ((int) $event->user_id !== $userId) {
            // Check admin
            $user = DB::selectOne("SELECT role FROM users WHERE id = ? AND tenant_id = ?", [$userId, $tenantId]);
            if (!$user || !in_array($user->role ?? '', ['admin', 'super_admin', 'god'])) {
                $this->errors[] = ['code' => 'FORBIDDEN', 'message' => 'You do not have permission to modify this event'];
                return false;
            }
        }

        try {
            DB::update("UPDATE events SET cover_image = ? WHERE id = ? AND tenant_id = ?", [$imageUrl, $eventId, $tenantId]);
            return true;
        } catch (\Exception $e) {
            \Illuminate\Support\Facades\Log::error("EventService::updateImage error: " . $e->getMessage());
            $this->errors[] = ['code' => 'SERVER_ERROR', 'message' => 'Failed to update image'];
            return false;
        }
    }

    /**
     * Cancel an event and notify all RSVPs.
     * // TODO: Convert to Eloquent (depends on Notification model, FeedActivityService, ActivityLog)
     */
    public function cancelEvent(int $eventId, int $userId, string $reason = ''): bool
    {
        return \Nexus\Services\EventService::cancelEvent($eventId, $userId, $reason);
    }

    /**
     * Add user to event waitlist.
     */
    public function addToWaitlist(int $eventId, int $userId): bool
    {
        $tenantId = \App\Core\TenantContext::getId();

        try {
            $exists = DB::selectOne("SELECT id FROM event_waitlist WHERE event_id = ? AND user_id = ? AND tenant_id = ? AND status = 'waiting'", [$eventId, $userId, $tenantId]);
            if ($exists) {
                return true;
            }

            $posRow = DB::selectOne("SELECT COALESCE(MAX(position), 0) + 1 as next_pos FROM event_waitlist WHERE event_id = ? AND tenant_id = ? AND status = 'waiting'", [$eventId, $tenantId]);
            $nextPos = (int) $posRow->next_pos;

            DB::statement(
                "INSERT INTO event_waitlist (event_id, user_id, tenant_id, position, status) VALUES (?, ?, ?, ?, 'waiting')
                 ON DUPLICATE KEY UPDATE status = 'waiting', position = ?, updated_at = NOW()",
                [$eventId, $userId, $tenantId, $nextPos, $nextPos]
            );

            return true;
        } catch (\Exception $e) {
            \Illuminate\Support\Facades\Log::error("EventService::addToWaitlist error: " . $e->getMessage());
            return false;
        }
    }

    /**
     * Remove user from event waitlist.
     */
    public function removeFromWaitlist(int $eventId, int $userId): void
    {
        $tenantId = \App\Core\TenantContext::getId();
        try {
            DB::update(
                "UPDATE event_waitlist SET status = 'cancelled', cancelled_at = NOW() WHERE event_id = ? AND user_id = ? AND status = 'waiting' AND tenant_id = ?",
                [$eventId, $userId, $tenantId]
            );
        } catch (\Exception $e) {
            \Illuminate\Support\Facades\Log::error("EventService::removeFromWaitlist error: " . $e->getMessage());
        }
    }

    /**
     * Get event waitlist.
     *
     * @return array{items: array, has_more: bool}
     */
    public function getWaitlist(int $eventId, array $filters = []): array
    {
        $limit = min($filters['limit'] ?? 20, 100);

        $tenantId = \App\Core\TenantContext::getId();

        $rows = DB::select(
            "SELECT w.id, w.user_id, w.position, w.status, w.created_at,
                   u.name, u.first_name, u.last_name, u.avatar_url
            FROM event_waitlist w
            JOIN users u ON w.user_id = u.id
            WHERE w.event_id = ? AND w.tenant_id = ? AND w.status = 'waiting'
            ORDER BY w.position ASC
            LIMIT ?",
            [$eventId, $tenantId, $limit + 1]
        );

        $items = array_map(fn($r) => (array) $r, $rows);
        $hasMore = count($items) > $limit;
        if ($hasMore) {
            array_pop($items);
        }

        $formatted = [];
        foreach ($items as $item) {
            $formatted[] = [
                'id' => (int) $item['user_id'],
                'name' => $item['name'] ?? trim(($item['first_name'] ?? '') . ' ' . ($item['last_name'] ?? '')),
                'first_name' => $item['first_name'] ?? null,
                'last_name' => $item['last_name'] ?? null,
                'avatar_url' => $item['avatar_url'],
                'position' => (int) $item['position'],
                'joined_at' => $item['created_at'],
            ];
        }

        return ['items' => $formatted, 'has_more' => $hasMore];
    }

    /**
     * Get user's waitlist position.
     */
    public function getUserWaitlistPosition(int $eventId, int $userId): ?int
    {
        try {
            $tenantId = \App\Core\TenantContext::getId();
            $row = DB::selectOne(
                "SELECT position FROM event_waitlist WHERE event_id = ? AND user_id = ? AND tenant_id = ? AND status = 'waiting'",
                [$eventId, $userId, $tenantId]
            );
            return $row ? (int) $row->position : null;
        } catch (\Exception $e) {
            return null;
        }
    }

    /**
     * Get user's reminders for an event.
     */
    public function getUserReminders(int $eventId, int $userId): array
    {
        try {
            $tenantId = \App\Core\TenantContext::getId();
            $rows = DB::select(
                "SELECT remind_before_minutes, reminder_type, status, scheduled_for
                 FROM event_reminders
                 WHERE event_id = ? AND user_id = ? AND tenant_id = ? AND status = 'pending'
                 ORDER BY remind_before_minutes ASC",
                [$eventId, $userId, $tenantId]
            );
            return array_map(fn($r) => (array) $r, $rows);
        } catch (\Exception $e) {
            return [];
        }
    }

    /**
     * Update reminders for an event.
     */
    public function updateReminders(int $eventId, int $userId, array $reminders): bool
    {
        $tenantId = \App\Core\TenantContext::getId();

        try {
            // Cancel existing reminders
            DB::update(
                "UPDATE event_reminders SET status = 'cancelled' WHERE event_id = ? AND user_id = ? AND status = 'pending' AND tenant_id = ?",
                [$eventId, $userId, $tenantId]
            );

            $validTypes = ['platform', 'email', 'both'];
            $validMinutes = [60, 1440, 10080];

            $event = DB::selectOne("SELECT start_time FROM events WHERE id = ? AND tenant_id = ?", [$eventId, $tenantId]);
            if (!$event || !$event->start_time) {
                return false;
            }

            foreach ($reminders as $reminder) {
                $minutes = (int) ($reminder['minutes'] ?? 0);
                $type = $reminder['type'] ?? 'both';

                if (!in_array($minutes, $validMinutes) || !in_array($type, $validTypes)) {
                    continue;
                }

                $startTimestamp = strtotime($event->start_time);
                $scheduledFor = date('Y-m-d H:i:s', $startTimestamp - ($minutes * 60));

                if (strtotime($scheduledFor) < time()) {
                    continue;
                }

                DB::statement(
                    "INSERT INTO event_reminders (event_id, user_id, tenant_id, remind_before_minutes, reminder_type, scheduled_for, status)
                     VALUES (?, ?, ?, ?, ?, ?, 'pending')
                     ON DUPLICATE KEY UPDATE status = 'pending', scheduled_for = ?, updated_at = NOW()",
                    [$eventId, $userId, $tenantId, $minutes, $type, $scheduledFor, $scheduledFor]
                );
            }

            return true;
        } catch (\Exception $e) {
            \Illuminate\Support\Facades\Log::error("EventService::updateReminders error: " . $e->getMessage());
            return false;
        }
    }

    /**
     * Get attendance records for an event.
     * // TODO: Convert to Eloquent (depends on Event model attendance tables)
     */
    public function getAttendanceRecords(int $eventId): array
    {
        return \Nexus\Services\EventService::getAttendanceRecords($eventId);
    }

    /**
     * Mark a user as attended at an event.
     * // TODO: Convert to Eloquent (depends on Event model attendance + wallet transactions)
     */
    public function markAttended(int $eventId, int $attendeeId, int $markedById, ?float $hoursOverride = null, ?string $notes = null): bool
    {
        return \Nexus\Services\EventService::markAttended($eventId, $attendeeId, $markedById, $hoursOverride, $notes);
    }

    /**
     * Bulk mark users as attended.
     * // TODO: Convert to Eloquent (depends on Event model attendance + wallet transactions)
     */
    public function bulkMarkAttended(int $eventId, array $attendeeIds, int $markedById): array
    {
        return \Nexus\Services\EventService::bulkMarkAttended($eventId, $attendeeIds, $markedById);
    }

    /**
     * Get all event series.
     * // TODO: Convert to Eloquent (depends on Event series tables)
     */
    public function getAllSeries(array $filters = []): array
    {
        return \Nexus\Services\EventService::getAllSeries($filters);
    }

    /**
     * Create an event series.
     * // TODO: Convert to Eloquent (depends on Event series tables)
     */
    public function createSeries(int $userId, string $title, ?string $description = null): ?int
    {
        return \Nexus\Services\EventService::createSeries($userId, $title, $description);
    }

    /**
     * Get series info.
     * // TODO: Convert to Eloquent (depends on Event series tables)
     */
    public function getSeriesInfo(int $seriesId): ?array
    {
        return \Nexus\Services\EventService::getSeriesInfo($seriesId);
    }

    /**
     * Get events in a series.
     * // TODO: Convert to Eloquent (depends on Event series tables)
     */
    public function getSeriesEvents(int $seriesId): array
    {
        return \Nexus\Services\EventService::getSeriesEvents($seriesId);
    }

    /**
     * Link an event to a series.
     * // TODO: Convert to Eloquent (depends on Event series tables)
     */
    public function linkToSeries(int $eventId, int $seriesId, int $userId): bool
    {
        return \Nexus\Services\EventService::linkToSeries($eventId, $seriesId, $userId);
    }

    /**
     * Create recurring event instances.
     * // TODO: Convert to Eloquent (depends on Event recurrence logic)
     */
    public function createRecurring(int $userId, array $data): ?array
    {
        return \Nexus\Services\EventService::createRecurring($userId, $data);
    }

    /**
     * Update recurring event(s).
     * // TODO: Convert to Eloquent (depends on Event recurrence logic)
     */
    public function updateRecurring(int $eventId, int $userId, array $data, string $scope = 'single'): bool
    {
        return \Nexus\Services\EventService::updateRecurring($eventId, $userId, $data, $scope);
    }
}
