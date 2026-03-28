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
/**
 * EventService — Laravel DI-based service for event operations.
 *
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
    public static function getAll(array $filters = []): array
    {
        $limit = min((int) ($filters['limit'] ?? 20), 100);
        $when = $filters['when'] ?? 'upcoming';
        $cursor = $filters['cursor'] ?? null;

        $query = Event::query()
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
        $rsvpCounts = EventRsvp::query()
            ->selectRaw('event_id, COUNT(*) as count')
            ->whereIn('event_id', $eventIds)
            ->where('status', 'going')
            ->groupBy('event_id')
            ->pluck('count', 'event_id');

        // Also get interested counts for the same events
        $interestedCounts = EventRsvp::query()
            ->selectRaw('event_id, COUNT(*) as count')
            ->whereIn('event_id', $eventIds)
            ->where('status', 'interested')
            ->groupBy('event_id')
            ->pluck('count', 'event_id');

        $result = $items->map(function (Event $event) use ($rsvpCounts, $interestedCounts) {
            $data = $event->toArray();
            $goingCount = (int) ($rsvpCounts[$event->id] ?? 0);
            $interestedCount = (int) ($interestedCounts[$event->id] ?? 0);
            $maxAttendees = $event->max_attendees;

            // Frontend field names (with legacy aliases)
            $data['attendee_count'] = $goingCount;
            $data['attendees_count'] = $goingCount;
            $data['interested_count'] = $interestedCount;
            $data['rsvp_counts'] = ['going' => $goingCount, 'interested' => $interestedCount];
            $data['spots_left'] = $maxAttendees ? max(0, $maxAttendees - $goingCount) : null;
            $data['is_full'] = $maxAttendees ? ($goingCount >= $maxAttendees) : false;

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
    public static function getById(int $id, ?int $currentUserId = null): ?array
    {
        /** @var Event|null $event */
        $event = Event::query()
            ->with([
                'user:id,first_name,last_name,organization_name,profile_type,avatar_url',
                'category',
                'group',
                'rsvps.user:id,first_name,last_name,avatar_url',
            ])
            ->find($id);

        if (! $event) {
            return null;
        }

        $data = $event->toArray();

        // Replace eager-loaded user relation with safe public fields only
        $eventUser = $event->user;
        if ($eventUser) {
            $data['user'] = [
                'id'         => $eventUser->id,
                'name'       => ($eventUser->profile_type === 'organisation' && $eventUser->organization_name)
                                    ? $eventUser->organization_name
                                    : trim($eventUser->first_name . ' ' . $eventUser->last_name),
                'avatar'     => $eventUser->avatar_url,
                'avatar_url' => $eventUser->avatar_url,
            ];
        }
        $goingCount = $event->rsvps->where('status', 'going')->count();
        $interestedCount = $event->rsvps->where('status', 'interested')->count();
        $maxAttendees = $event->max_attendees;

        // Frontend field names (with legacy aliases)
        $data['attendee_count'] = $goingCount;
        $data['attendees_count'] = $goingCount;
        $data['interested_count'] = $interestedCount;
        $data['rsvp_counts'] = ['going' => $goingCount, 'interested' => $interestedCount];
        $data['spots_left'] = $maxAttendees ? max(0, $maxAttendees - $goingCount) : null;
        $data['is_full'] = $maxAttendees ? ($goingCount >= $maxAttendees) : false;

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
    public static function create(int $userId, array $data): Event
    {
        validator($data, [
            'title'       => 'required|string|max:255',
            'description' => 'required|string',
            'start_time'  => 'required|date|after:now',
            'end_time'    => 'nullable|date|after:start_time',
            'location'    => 'nullable|string|max:255',
        ])->validate();

        return DB::transaction(function () use ($userId, $data) {
            $event = new Event([
                'user_id'              => $userId,
                'title'                => trim($data['title']),
                'description'          => trim($data['description']),
                'start_time'           => $data['start_time'],
                'end_time'             => $data['end_time'] ?? null,
                'location'             => $data['location'] ?? null,
                'latitude'             => $data['latitude'] ?? null,
                'longitude'            => $data['longitude'] ?? null,
                'category_id'          => self::resolveCategoryId($data),
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
     *
     * @param int   $id     Event ID
     * @param int   $userId Authenticated user requesting the update
     * @param array $data   Fields to update
     * @return bool True on success, false on error (check getErrors())
     */
    public static function update(int $id, int $userId, array $data): bool
    {
        self::$errors = [];
        $tenantId = \App\Core\TenantContext::getId();

        /** @var Event|null $event */
        $event = Event::query()->find($id);

        if (! $event) {
            self::$errors[] = ['code' => 'NOT_FOUND', 'message' => 'Event not found'];
            return false;
        }

        // Authorization: only organizer or platform admin
        if ((int) $event->user_id !== $userId) {
            $user = DB::selectOne("SELECT role, is_super_admin, is_tenant_super_admin FROM users WHERE id = ? AND tenant_id = ?", [$userId, $tenantId]);
            if (!$user || !(
                in_array($user->role ?? '', ['admin', 'super_admin', 'god']) ||
                !empty($user->is_super_admin) ||
                !empty($user->is_tenant_super_admin)
            )) {
                self::$errors[] = ['code' => 'FORBIDDEN', 'message' => 'You do not have permission to edit this event'];
                return false;
            }
        }

        // Resolve category_name slug to category_id if provided
        if (!empty($data['category_name']) && empty($data['category_id'])) {
            $data['category_id'] = self::resolveCategoryId($data);
        }

        $allowed = [
            'title', 'description', 'start_time', 'end_time', 'location',
            'latitude', 'longitude', 'category_id', 'group_id', 'max_attendees',
            'is_online', 'online_link', 'image_url', 'federated_visibility',
        ];

        $event->fill(collect($data)->only($allowed)->all());
        $event->save();

        return true;
    }

    /**
     * Resolve category_id from data — supports both numeric ID and string slug.
     */
    private static function resolveCategoryId(array $data): ?int
    {
        if (!empty($data['category_id'])) {
            return (int) $data['category_id'];
        }

        if (!empty($data['category_name'])) {
            $category = DB::table('categories')
                ->where('name', $data['category_name'])
                ->where('type', 'events')
                ->value('id');
            return $category ? (int) $category : null;
        }

        return null;
    }

    /**
     * Delete an event.
     *
     * @param int $id     Event ID
     * @param int $userId Authenticated user requesting the deletion
     * @return bool True on success, false on error (check getErrors())
     */
    public static function delete(int $id, int $userId): bool
    {
        self::$errors = [];
        $tenantId = \App\Core\TenantContext::getId();

        /** @var Event|null $event */
        $event = Event::query()->find($id);

        if (! $event) {
            self::$errors[] = ['code' => 'NOT_FOUND', 'message' => 'Event not found'];
            return false;
        }

        // Authorization: only organizer or platform admin
        if ((int) $event->user_id !== $userId) {
            $user = DB::selectOne("SELECT role, is_super_admin, is_tenant_super_admin FROM users WHERE id = ? AND tenant_id = ?", [$userId, $tenantId]);
            if (!$user || !(
                in_array($user->role ?? '', ['admin', 'super_admin', 'god']) ||
                !empty($user->is_super_admin) ||
                !empty($user->is_tenant_super_admin)
            )) {
                self::$errors[] = ['code' => 'FORBIDDEN', 'message' => 'Only the event organizer can delete this event'];
                return false;
            }
        }

        $event->delete();

        return true;
    }

    /** @var array Validation error messages */
    private static array $errors = [];

    /**
     * Validate event data and return boolean.
     *
     * @return bool True if valid, false if errors (check getErrors()).
     */
    public static function validate(array $data): bool
    {
        self::$errors = [];

        $title = $data['title'] ?? null;
        $startTime = $data['start_time'] ?? null;
        $endTime = $data['end_time'] ?? null;

        // title is required and max 255
        if ($title === null || $title === '') {
            self::$errors[] = ['code' => 'VALIDATION_ERROR', 'message' => 'Title is required', 'field' => 'title'];
        } elseif (strlen($title) > 255) {
            self::$errors[] = ['code' => 'VALIDATION_ERROR', 'message' => 'Title must not exceed 255 characters', 'field' => 'title'];
        }

        // start_time: validate format if provided
        if ($startTime !== null) {
            $parsed = strtotime($startTime);
            if ($parsed === false) {
                self::$errors[] = ['code' => 'VALIDATION_ERROR', 'message' => 'Invalid start_time format', 'field' => 'start_time'];
            }
        }

        // end_time must be after start_time if both provided
        if ($startTime !== null && $endTime !== null) {
            $startParsed = strtotime($startTime);
            $endParsed = strtotime($endTime);
            if ($startParsed !== false && $endParsed !== false && $endParsed <= $startParsed) {
                self::$errors[] = ['code' => 'VALIDATION_ERROR', 'message' => 'End time must be after start time', 'field' => 'end_time'];
            }
        }

        return empty(self::$errors);
    }

    // ================================================================
    // CONVERTED FROM LEGACY — Direct DB facade calls
    // ================================================================

    /**
     * Get validation errors from the last operation.
     */
    public static function getErrors(): array
    {
        return self::$errors;
    }

    /**
     * Get user's RSVP status for an event.
     */
    public static function getUserRsvp(int $eventId, int $userId): ?string
    {
        $tenantId = \App\Core\TenantContext::getId();
        $row = DB::selectOne(
            "SELECT status FROM event_rsvps WHERE event_id = ? AND user_id = ? AND tenant_id = ?",
            [$eventId, $userId, $tenantId]
        );
        return $row ? $row->status : null;
    }

    /**
     * Batch-load user RSVP statuses for multiple events (avoids N+1).
     *
     * @param  int[] $eventIds
     * @return array<int, string> Map of event_id => status
     */
    public static function getUserRsvpsBatch(array $eventIds, int $userId): array
    {
        if (empty($eventIds)) {
            return [];
        }
        $tenantId = \App\Core\TenantContext::getId();
        $placeholders = implode(',', array_fill(0, count($eventIds), '?'));
        $params = array_merge($eventIds, [$userId, $tenantId]);
        $rows = DB::select(
            "SELECT event_id, status FROM event_rsvps WHERE event_id IN ({$placeholders}) AND user_id = ? AND tenant_id = ?",
            $params
        );
        $map = [];
        foreach ($rows as $row) {
            $map[(int) $row->event_id] = $row->status;
        }
        return $map;
    }

    /**
     * RSVP to an event with capacity enforcement.
     */
    public static function rsvp(int $eventId, int $userId, string $status): bool
    {
        self::$errors = [];

        $validStatuses = ['going', 'interested', 'not_going', 'declined'];
        if (!in_array($status, $validStatuses)) {
            self::$errors[] = ['code' => 'VALIDATION_ERROR', 'message' => 'Invalid RSVP status', 'field' => 'status'];
            return false;
        }

        $tenantId = \App\Core\TenantContext::getId();
        $event = DB::selectOne("SELECT * FROM events WHERE id = ? AND tenant_id = ?", [$eventId, $tenantId]);
        if (!$event) {
            self::$errors[] = ['code' => 'NOT_FOUND', 'message' => 'Event not found'];
            return false;
        }

        if (($event->status ?? 'active') === 'cancelled') {
            self::$errors[] = ['code' => 'EVENT_CANCELLED', 'message' => 'This event has been cancelled'];
            return false;
        }

        // Block RSVP to past events (event has already ended, or started with no end time)
        if ($status === 'going' || $status === 'interested') {
            $eventEnd = $event->end_time ?? $event->start_time ?? null;
            if ($eventEnd && strtotime($eventEnd) < time()) {
                self::$errors[] = ['code' => 'EVENT_ENDED', 'message' => 'This event has already ended'];
                return false;
            }
        }

        // Capacity enforcement for 'going' status — use SELECT ... FOR UPDATE to prevent race conditions
        if ($status === 'going' && !empty($event->max_attendees)) {
            $maxAttendees = (int) $event->max_attendees;

            return DB::transaction(function () use ($eventId, $userId, $tenantId, $status, $maxAttendees) {
                // Lock the RSVP rows for this event to prevent concurrent over-booking
                $currentGoing = (int) DB::selectOne(
                    "SELECT COUNT(*) as cnt FROM event_rsvps WHERE event_id = ? AND tenant_id = ? AND status = 'going' FOR UPDATE",
                    [$eventId, $tenantId]
                )->cnt;

                $currentUserStatus = self::getUserRsvp($eventId, $userId);
                $isAlreadyGoing = ($currentUserStatus === 'going');

                if (!$isAlreadyGoing && $currentGoing >= $maxAttendees) {
                    self::addToWaitlist($eventId, $userId);
                    self::$errors[] = [
                        'code' => 'EVENT_FULL',
                        'message' => 'This event is full. You have been added to the waitlist.',
                        'waitlisted' => true,
                    ];
                    return false;
                }

                // Upsert RSVP inside the transaction
                DB::statement(
                    "INSERT INTO event_rsvps (event_id, user_id, tenant_id, status, created_at)
                     VALUES (?, ?, ?, ?, NOW())
                     ON DUPLICATE KEY UPDATE status = VALUES(status), updated_at = NOW()",
                    [$eventId, $userId, $tenantId, $status]
                );

                self::removeFromWaitlist($eventId, $userId);

                return true;
            });
        }

        try {
            // Upsert RSVP (no capacity limit)
            DB::statement(
                "INSERT INTO event_rsvps (event_id, user_id, tenant_id, status, created_at)
                 VALUES (?, ?, ?, ?, NOW())
                 ON DUPLICATE KEY UPDATE status = VALUES(status), updated_at = NOW()",
                [$eventId, $userId, $tenantId, $status]
            );

            return true;
        } catch (\Exception $e) {
            \Illuminate\Support\Facades\Log::error("EventService::rsvp error: " . $e->getMessage());
            self::$errors[] = ['code' => 'SERVER_ERROR', 'message' => 'Failed to update RSVP'];
            return false;
        }
    }

    /**
     * Remove RSVP from an event (with waitlist promotion).
     */
    public static function removeRsvp(int $eventId, int $userId): bool
    {
        self::$errors = [];
        $tenantId = \App\Core\TenantContext::getId();

        $event = DB::selectOne("SELECT id FROM events WHERE id = ? AND tenant_id = ?", [$eventId, $tenantId]);
        if (!$event) {
            self::$errors[] = ['code' => 'NOT_FOUND', 'message' => 'Event not found'];
            return false;
        }

        try {
            $currentStatus = self::getUserRsvp($eventId, $userId);

            DB::delete("DELETE FROM event_rsvps WHERE event_id = ? AND user_id = ? AND tenant_id = ?", [$eventId, $userId, $tenantId]);

            // Cancel reminders
            DB::update(
                "UPDATE event_reminders SET status = 'cancelled' WHERE event_id = ? AND user_id = ? AND status = 'pending' AND tenant_id = ?",
                [$eventId, $userId, $tenantId]
            );

            return true;
        } catch (\Exception $e) {
            \Illuminate\Support\Facades\Log::error("EventService::removeRsvp error: " . $e->getMessage());
            self::$errors[] = ['code' => 'SERVER_ERROR', 'message' => 'Failed to remove RSVP'];
            return false;
        }
    }

    /**
     * Get attendees for an event with cursor-based pagination.
     *
     * @return array{items: array, cursor: string|null, has_more: bool}
     */
    public static function getAttendees(int $eventId, array $filters = []): array
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
     * Get nearby upcoming events using Haversine distance.
     *
     * @return array{items: array, has_more: bool}
     */
    public static function getNearby(float $lat, float $lon, array $filters = []): array
    {
        $radiusKm = (float) ($filters['radius_km'] ?? 25);
        $limit = min((int) ($filters['limit'] ?? 20), 100);
        $tenantId = \App\Core\TenantContext::getId();

        $query = "
            SELECT e.id, e.title, e.description, e.location, e.latitude, e.longitude,
                   e.start_time, e.end_time, e.max_attendees, e.cover_image, e.status,
                   e.user_id, e.category_id, e.group_id,
                   u.first_name as organizer_first_name, u.last_name as organizer_last_name,
                   u.avatar_url as organizer_avatar,
                   c.name as category_name, c.color as category_color,
                   (SELECT COUNT(*) FROM event_rsvps WHERE event_id = e.id AND status = 'going') as going_count,
                   (SELECT COUNT(*) FROM event_rsvps WHERE event_id = e.id AND status = 'interested') as interested_count,
                   (6371 * acos(
                       cos(radians(?)) * cos(radians(e.latitude)) *
                       cos(radians(e.longitude) - radians(?)) +
                       sin(radians(?)) * sin(radians(e.latitude))
                   )) AS distance_km
            FROM events e
            JOIN users u ON e.user_id = u.id
            LEFT JOIN categories c ON e.category_id = c.id
            WHERE e.tenant_id = ?
              AND e.latitude IS NOT NULL AND e.longitude IS NOT NULL
              AND e.start_time >= NOW()
              AND (e.status IS NULL OR e.status = 'active')
            HAVING distance_km <= ?
            ORDER BY distance_km ASC
            LIMIT ?
        ";

        $params = [$lat, $lon, $lat, $tenantId, $radiusKm, $limit + 1];

        if (!empty($filters['category_id'])) {
            // Rebuild with category filter injected before HAVING
            $query = "
                SELECT e.id, e.title, e.description, e.location, e.latitude, e.longitude,
                       e.start_time, e.end_time, e.max_attendees, e.cover_image, e.status,
                       e.user_id, e.category_id, e.group_id,
                       u.first_name as organizer_first_name, u.last_name as organizer_last_name,
                       u.avatar_url as organizer_avatar,
                       c.name as category_name, c.color as category_color,
                       (SELECT COUNT(*) FROM event_rsvps WHERE event_id = e.id AND status = 'going') as going_count,
                       (SELECT COUNT(*) FROM event_rsvps WHERE event_id = e.id AND status = 'interested') as interested_count,
                       (6371 * acos(
                           cos(radians(?)) * cos(radians(e.latitude)) *
                           cos(radians(e.longitude) - radians(?)) +
                           sin(radians(?)) * sin(radians(e.latitude))
                       )) AS distance_km
                FROM events e
                JOIN users u ON e.user_id = u.id
                LEFT JOIN categories c ON e.category_id = c.id
                WHERE e.tenant_id = ?
                  AND e.latitude IS NOT NULL AND e.longitude IS NOT NULL
                  AND e.start_time >= NOW()
                  AND (e.status IS NULL OR e.status = 'active')
                  AND e.category_id = ?
                HAVING distance_km <= ?
                ORDER BY distance_km ASC
                LIMIT ?
            ";
            $params = [$lat, $lon, $lat, $tenantId, (int) $filters['category_id'], $radiusKm, $limit + 1];
        }

        $rows = DB::select($query, $params);

        $items = array_map(function ($r) {
            $row = (array) $r;
            $goingCount = (int) ($row['going_count'] ?? 0);
            $maxAtt = isset($row['max_attendees']) ? (int) $row['max_attendees'] : null;
            return [
                'id'               => (int) $row['id'],
                'title'            => $row['title'],
                'description'      => $row['description'],
                'location'         => $row['location'],
                'latitude'         => $row['latitude'] ? (float) $row['latitude'] : null,
                'longitude'        => $row['longitude'] ? (float) $row['longitude'] : null,
                'start_time'       => $row['start_time'],
                'end_time'         => $row['end_time'],
                'distance_km'      => round((float) $row['distance_km'], 2),
                'cover_image'      => $row['cover_image'] ?? null,
                'status'           => $row['status'] ?? 'active',
                'organizer'        => [
                    'id'         => (int) $row['user_id'],
                    'first_name' => $row['organizer_first_name'] ?? null,
                    'last_name'  => $row['organizer_last_name'] ?? null,
                    'avatar_url' => $row['organizer_avatar'] ?? null,
                ],
                'category'         => $row['category_id'] ? [
                    'id'    => (int) $row['category_id'],
                    'name'  => $row['category_name'] ?? null,
                    'color' => $row['category_color'] ?? null,
                ] : null,
                'rsvp_counts'      => [
                    'going'      => $goingCount,
                    'interested' => (int) ($row['interested_count'] ?? 0),
                ],
                'attendees_count'  => $goingCount,
                'spots_left'       => $maxAtt ? max(0, $maxAtt - $goingCount) : null,
                'is_full'          => $maxAtt ? ($goingCount >= $maxAtt) : false,
            ];
        }, $rows);

        $hasMore = count($items) > $limit;
        if ($hasMore) {
            array_pop($items);
        }

        return [
            'items'    => $items,
            'has_more' => $hasMore,
        ];
    }

    /**
     * Update event cover image.
     */
    public static function updateImage(int $eventId, int $userId, string $imageUrl): bool
    {
        self::$errors = [];
        $tenantId = \App\Core\TenantContext::getId();

        $event = DB::selectOne("SELECT id, user_id FROM events WHERE id = ? AND tenant_id = ?", [$eventId, $tenantId]);
        if (!$event) {
            self::$errors[] = ['code' => 'NOT_FOUND', 'message' => 'Event not found'];
            return false;
        }

        if ((int) $event->user_id !== $userId) {
            // Check admin
            $user = DB::selectOne("SELECT role FROM users WHERE id = ? AND tenant_id = ?", [$userId, $tenantId]);
            if (!$user || !in_array($user->role ?? '', ['admin', 'super_admin', 'god'])) {
                self::$errors[] = ['code' => 'FORBIDDEN', 'message' => 'You do not have permission to modify this event'];
                return false;
            }
        }

        try {
            DB::update("UPDATE events SET cover_image = ? WHERE id = ? AND tenant_id = ?", [$imageUrl, $eventId, $tenantId]);
            return true;
        } catch (\Exception $e) {
            \Illuminate\Support\Facades\Log::error("EventService::updateImage error: " . $e->getMessage());
            self::$errors[] = ['code' => 'SERVER_ERROR', 'message' => 'Failed to update image'];
            return false;
        }
    }

    /**
     * Cancel an event and notify all RSVPs.
     */
    public static function cancelEvent(int $eventId, int $userId, string $reason = ''): bool
    {
        self::$errors = [];
        $tenantId = \App\Core\TenantContext::getId();

        $event = DB::selectOne("SELECT * FROM events WHERE id = ? AND tenant_id = ?", [$eventId, $tenantId]);
        if (!$event) {
            self::$errors[] = ['code' => 'NOT_FOUND', 'message' => 'Event not found'];
            return false;
        }

        // Ownership / admin check
        if ((int) $event->user_id !== $userId) {
            $user = DB::selectOne("SELECT role FROM users WHERE id = ? AND tenant_id = ?", [$userId, $tenantId]);
            if (!$user || !in_array($user->role ?? '', ['admin', 'super_admin', 'god'])) {
                self::$errors[] = ['code' => 'FORBIDDEN', 'message' => 'You do not have permission to cancel this event'];
                return false;
            }
        }

        if (($event->status ?? 'active') === 'cancelled') {
            self::$errors[] = ['code' => 'ALREADY_CANCELLED', 'message' => 'This event is already cancelled'];
            return false;
        }

        try {
            // Update event status
            DB::update(
                "UPDATE events SET status = 'cancelled', cancellation_reason = ?, cancelled_at = NOW(), cancelled_by = ? WHERE id = ? AND tenant_id = ?",
                [$reason, $userId, $eventId, $tenantId]
            );

            // Cancel all pending reminders
            DB::update(
                "UPDATE event_reminders SET status = 'cancelled' WHERE event_id = ? AND status = 'pending' AND tenant_id = ?",
                [$eventId, $tenantId]
            );

            // Cancel all waitlist entries
            DB::update(
                "UPDATE event_waitlist SET status = 'cancelled', cancelled_at = NOW() WHERE event_id = ? AND status = 'waiting' AND tenant_id = ?",
                [$eventId, $tenantId]
            );

            // Collect RSVP'd users BEFORE updating statuses (for notifications)
            $rsvpUserIds = DB::select(
                "SELECT DISTINCT user_id FROM event_rsvps WHERE event_id = ? AND tenant_id = ? AND status IN ('going', 'interested', 'invited')",
                [$eventId, $tenantId]
            );

            // Mark all active RSVPs as cancelled
            DB::update(
                "UPDATE event_rsvps SET status = 'cancelled' WHERE event_id = ? AND tenant_id = ? AND status IN ('going', 'interested', 'invited')",
                [$eventId, $tenantId]
            );

            // Notify all RSVPs (going + interested) and waitlisted users
            $waitlistUserIds = DB::select(
                "SELECT DISTINCT user_id FROM event_waitlist WHERE event_id = ? AND tenant_id = ? AND status = 'waiting'",
                [$eventId, $tenantId]
            );

            $allUserIds = collect($rsvpUserIds)->pluck('user_id')
                ->merge(collect($waitlistUserIds)->pluck('user_id'))
                ->unique();

            $message = "The event \"{$event->title}\" has been cancelled.";
            if (!empty($reason)) {
                $message .= " Reason: {$reason}";
            }

            foreach ($allUserIds as $uid) {
                try {
                    DB::statement(
                        "INSERT INTO notifications (user_id, tenant_id, message, link, type, is_actionable, created_at)
                         VALUES (?, ?, ?, ?, 'event', 0, NOW())",
                        [(int) $uid, $tenantId, $message, '/events/' . $eventId]
                    );
                } catch (\Exception $e) {
                    Log::error("Failed to notify user {$uid} of event cancellation: " . $e->getMessage());
                }
            }

            return true;
        } catch (\Exception $e) {
            Log::error("EventService::cancelEvent error: " . $e->getMessage());
            self::$errors[] = ['code' => 'SERVER_ERROR', 'message' => 'Failed to cancel event'];
            return false;
        }
    }

    /**
     * Add user to event waitlist.
     */
    public static function addToWaitlist(int $eventId, int $userId): bool
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
    public static function removeFromWaitlist(int $eventId, int $userId): void
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
    public static function getWaitlist(int $eventId, array $filters = []): array
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
    public static function getUserWaitlistPosition(int $eventId, int $userId): ?int
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
    public static function getUserReminders(int $eventId, int $userId): array
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
    public static function updateReminders(int $eventId, int $userId, array $reminders): bool
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
     */
    public static function getAttendanceRecords(int $eventId): array
    {
        $tenantId = \App\Core\TenantContext::getId();

        try {
            $rows = DB::select(
                "SELECT a.*, u.name, u.first_name, u.last_name, u.avatar_url,
                        cb.name as checked_in_by_name
                 FROM event_attendance a
                 JOIN users u ON a.user_id = u.id
                 LEFT JOIN users cb ON a.checked_in_by = cb.id
                 WHERE a.event_id = ? AND a.tenant_id = ?
                 ORDER BY a.checked_in_at ASC",
                [$eventId, $tenantId]
            );

            $items = [];
            foreach ($rows as $r) {
                $items[] = [
                    'user_id'        => (int) $r->user_id,
                    'name'           => $r->name ?? trim(($r->first_name ?? '') . ' ' . ($r->last_name ?? '')),
                    'first_name'     => $r->first_name ?? null,
                    'last_name'      => $r->last_name ?? null,
                    'avatar_url'     => $r->avatar_url,
                    'checked_in_at'  => $r->checked_in_at,
                    'checked_in_by'  => $r->checked_in_by_name ?? null,
                    'hours_credited' => $r->hours_credited ? (float) $r->hours_credited : null,
                    'notes'          => $r->notes,
                ];
            }

            return $items;
        } catch (\Exception $e) {
            Log::error("EventService::getAttendanceRecords error: " . $e->getMessage());
            return [];
        }
    }

    /**
     * Mark a user as attended at an event.
     */
    public static function markAttended(int $eventId, int $attendeeId, int $markedById, ?float $hoursOverride = null, ?string $notes = null): bool
    {
        self::$errors = [];
        $tenantId = \App\Core\TenantContext::getId();

        $event = DB::selectOne("SELECT * FROM events WHERE id = ? AND tenant_id = ?", [$eventId, $tenantId]);
        if (!$event) {
            self::$errors[] = ['code' => 'NOT_FOUND', 'message' => 'Event not found'];
            return false;
        }

        // Ownership / admin check
        if ((int) $event->user_id !== $markedById) {
            $user = DB::selectOne("SELECT role FROM users WHERE id = ? AND tenant_id = ?", [$markedById, $tenantId]);
            if (!$user || !in_array($user->role ?? '', ['admin', 'super_admin', 'god'])) {
                self::$errors[] = ['code' => 'FORBIDDEN', 'message' => 'Only the organizer or admin can mark attendance'];
                return false;
            }
        }

        // Calculate hours from event duration
        $hours = $hoursOverride;
        if ($hours === null && !empty($event->start_time) && !empty($event->end_time)) {
            $start = strtotime($event->start_time);
            $end = strtotime($event->end_time);
            $hours = round(($end - $start) / 3600, 2);
            if ($hours < 0.5) {
                $hours = 0.5;
            }
        }
        if ($hours === null) {
            $hours = 1.0;
        }

        try {
            DB::statement(
                "INSERT INTO event_attendance (event_id, user_id, tenant_id, checked_in_at, checked_in_by, hours_credited, notes)
                 VALUES (?, ?, ?, NOW(), ?, ?, ?)
                 ON DUPLICATE KEY UPDATE checked_in_at = NOW(), checked_in_by = ?, hours_credited = ?, notes = ?, updated_at = NOW()",
                [$eventId, $attendeeId, $tenantId, $markedById, $hours, $notes, $markedById, $hours, $notes]
            );

            // Update RSVP status to 'attended'
            DB::statement(
                "INSERT INTO event_rsvps (event_id, user_id, tenant_id, status, created_at)
                 VALUES (?, ?, ?, 'attended', NOW())
                 ON DUPLICATE KEY UPDATE status = 'attended', updated_at = NOW()",
                [$eventId, $attendeeId, $tenantId]
            );

            return true;
        } catch (\Exception $e) {
            Log::error("EventService::markAttended error: " . $e->getMessage());
            self::$errors[] = ['code' => 'SERVER_ERROR', 'message' => 'Failed to mark attendance'];
            return false;
        }
    }

    /**
     * Bulk mark users as attended.
     *
     * @return array{marked: int, failed: int}
     */
    public static function bulkMarkAttended(int $eventId, array $attendeeIds, int $markedById): array
    {
        $marked = 0;
        $failed = 0;

        foreach ($attendeeIds as $attendeeId) {
            if (self::markAttended($eventId, (int) $attendeeId, $markedById)) {
                $marked++;
            } else {
                $failed++;
            }
        }

        return ['marked' => $marked, 'failed' => $failed];
    }

    /**
     * Get all event series for the current tenant.
     *
     * @return array{items: array, has_more: bool}
     */
    public static function getAllSeries(array $filters = []): array
    {
        $tenantId = \App\Core\TenantContext::getId();
        $limit = min($filters['limit'] ?? 20, 100);

        try {
            $rows = DB::select(
                "SELECT s.*, u.name as creator_name,
                        (SELECT COUNT(*) FROM events WHERE series_id = s.id AND tenant_id = ?) as event_count,
                        (SELECT MIN(start_time) FROM events WHERE series_id = s.id AND tenant_id = ? AND start_time >= NOW()) as next_event
                 FROM event_series s
                 JOIN users u ON s.created_by = u.id
                 WHERE s.tenant_id = ?
                 ORDER BY s.created_at DESC
                 LIMIT ?",
                [$tenantId, $tenantId, $tenantId, $limit + 1]
            );

            $items = array_map(fn($r) => (array) $r, $rows);
            $hasMore = count($items) > $limit;
            if ($hasMore) {
                array_pop($items);
            }

            $formatted = [];
            foreach ($items as $s) {
                $formatted[] = [
                    'id'          => (int) $s['id'],
                    'title'       => $s['title'],
                    'description' => $s['description'],
                    'event_count' => (int) $s['event_count'],
                    'next_event'  => $s['next_event'],
                    'creator'     => $s['creator_name'],
                    'created_at'  => $s['created_at'],
                ];
            }

            return ['items' => $formatted, 'has_more' => $hasMore];
        } catch (\Exception $e) {
            Log::error("EventService::getAllSeries error: " . $e->getMessage());
            return ['items' => [], 'has_more' => false];
        }
    }

    /**
     * Create an event series.
     */
    public static function createSeries(int $userId, string $title, ?string $description = null): ?int
    {
        self::$errors = [];
        $tenantId = \App\Core\TenantContext::getId();

        if (empty(trim($title))) {
            self::$errors[] = ['code' => 'VALIDATION_ERROR', 'message' => 'Series title is required', 'field' => 'title'];
            return null;
        }

        try {
            DB::statement(
                "INSERT INTO event_series (tenant_id, title, description, created_by) VALUES (?, ?, ?, ?)",
                [$tenantId, trim($title), $description, $userId]
            );
            return (int) DB::getPdo()->lastInsertId();
        } catch (\Exception $e) {
            Log::error("EventService::createSeries error: " . $e->getMessage());
            self::$errors[] = ['code' => 'SERVER_ERROR', 'message' => 'Failed to create series'];
            return null;
        }
    }

    /**
     * Get series info.
     */
    public static function getSeriesInfo(int $seriesId): ?array
    {
        $tenantId = \App\Core\TenantContext::getId();

        try {
            $series = DB::selectOne(
                "SELECT s.*, u.name as creator_name,
                        (SELECT COUNT(*) FROM events WHERE series_id = s.id AND tenant_id = ?) as event_count
                 FROM event_series s
                 JOIN users u ON s.created_by = u.id
                 WHERE s.id = ? AND s.tenant_id = ?",
                [$tenantId, $seriesId, $tenantId]
            );

            if (!$series) {
                return null;
            }

            return [
                'id'          => (int) $series->id,
                'title'       => $series->title,
                'description' => $series->description,
                'event_count' => (int) $series->event_count,
                'creator'     => $series->creator_name,
            ];
        } catch (\Exception $e) {
            return null;
        }
    }

    /**
     * Get events in a series.
     */
    public static function getSeriesEvents(int $seriesId): array
    {
        $tenantId = \App\Core\TenantContext::getId();

        try {
            $rows = DB::select(
                "SELECT e.id, e.title, e.start_time, e.end_time, e.status, e.location,
                        (SELECT COUNT(*) FROM event_rsvps WHERE event_id = e.id AND status = 'going') as going_count
                 FROM events e
                 WHERE e.series_id = ? AND e.tenant_id = ?
                 ORDER BY e.start_time ASC",
                [$seriesId, $tenantId]
            );

            $items = [];
            foreach ($rows as $e) {
                $items[] = [
                    'id'          => (int) $e->id,
                    'title'       => $e->title,
                    'start_time'  => $e->start_time,
                    'end_time'    => $e->end_time,
                    'status'      => $e->status ?? 'active',
                    'location'    => $e->location,
                    'going_count' => (int) ($e->going_count ?? 0),
                ];
            }

            return $items;
        } catch (\Exception $e) {
            Log::error("EventService::getSeriesEvents error: " . $e->getMessage());
            return [];
        }
    }

    /**
     * Link an event to a series.
     */
    public static function linkToSeries(int $eventId, int $seriesId, int $userId): bool
    {
        self::$errors = [];
        $tenantId = \App\Core\TenantContext::getId();

        $event = DB::selectOne("SELECT id, user_id FROM events WHERE id = ? AND tenant_id = ?", [$eventId, $tenantId]);
        if (!$event) {
            self::$errors[] = ['code' => 'NOT_FOUND', 'message' => 'Event not found'];
            return false;
        }

        // Ownership / admin check
        if ((int) $event->user_id !== $userId) {
            $user = DB::selectOne("SELECT role FROM users WHERE id = ? AND tenant_id = ?", [$userId, $tenantId]);
            if (!$user || !in_array($user->role ?? '', ['admin', 'super_admin', 'god'])) {
                self::$errors[] = ['code' => 'FORBIDDEN', 'message' => 'You do not have permission to modify this event'];
                return false;
            }
        }

        try {
            DB::update("UPDATE events SET series_id = ? WHERE id = ? AND tenant_id = ?", [$seriesId, $eventId, $tenantId]);
            return true;
        } catch (\Exception $e) {
            Log::error("EventService::linkToSeries error: " . $e->getMessage());
            self::$errors[] = ['code' => 'SERVER_ERROR', 'message' => 'Failed to link event to series'];
            return false;
        }
    }

    /**
     * Create a recurring event with recurrence rule.
     *
     * @return array{template_id: int, occurrences: int}|null
     */
    public static function createRecurring(int $userId, array $data): ?array
    {
        self::$errors = [];

        // Validate recurrence frequency
        $frequency = $data['recurrence_frequency'] ?? null;
        $validFrequencies = ['daily', 'weekly', 'monthly', 'yearly', 'custom'];
        if (!$frequency || !in_array($frequency, $validFrequencies)) {
            self::$errors[] = ['code' => 'VALIDATION_ERROR', 'message' => 'Valid recurrence frequency is required', 'field' => 'recurrence_frequency'];
            return null;
        }

        // Create the template event
        $template = self::create($userId, $data);
        if (!$template) {
            return null;
        }
        $templateId = $template->id;

        $tenantId = \App\Core\TenantContext::getId();

        try {
            // Mark as recurring template
            DB::update("UPDATE events SET is_recurring_template = 1 WHERE id = ? AND tenant_id = ?", [$templateId, $tenantId]);

            // Store recurrence rule
            $interval = max(1, (int) ($data['recurrence_interval'] ?? 1));
            $daysOfWeek = $data['recurrence_days'] ?? null;
            $dayOfMonth = $data['recurrence_day_of_month'] ?? null;
            $endsType = $data['recurrence_ends_type'] ?? 'after_count';
            $endsAfterCount = $data['recurrence_ends_after_count'] ?? 10;
            $endsOnDate = $data['recurrence_ends_on_date'] ?? null;
            $rrule = $data['recurrence_rrule'] ?? null;

            DB::statement(
                "INSERT INTO event_recurrence_rules
                 (event_id, tenant_id, frequency, interval_value, days_of_week, day_of_month, rrule, ends_type, ends_after_count, ends_on_date)
                 VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?)",
                [$templateId, $tenantId, $frequency, $interval, $daysOfWeek, $dayOfMonth, $rrule, $endsType, $endsAfterCount, $endsOnDate]
            );

            // Generate occurrences
            $occurrenceCount = self::generateOccurrences($templateId, $data);

            return [
                'template_id' => $templateId,
                'occurrences' => $occurrenceCount,
            ];
        } catch (\Exception $e) {
            Log::error("EventService::createRecurring error: " . $e->getMessage());
            self::$errors[] = ['code' => 'SERVER_ERROR', 'message' => 'Failed to create recurring event'];
            return null;
        }
    }

    /**
     * Generate occurrence events from a recurrence template.
     */
    private static function generateOccurrences(int $templateId, array $data): int
    {
        $tenantId = \App\Core\TenantContext::getId();

        $rule = DB::selectOne("SELECT * FROM event_recurrence_rules WHERE event_id = ? AND tenant_id = ?", [$templateId, $tenantId]);
        if (!$rule) {
            return 0;
        }

        $template = DB::selectOne("SELECT * FROM events WHERE id = ? AND tenant_id = ?", [$templateId, $tenantId]);
        if (!$template) {
            return 0;
        }

        $startTime = new \DateTime($template->start_time);
        $endTime = $template->end_time ? new \DateTime($template->end_time) : null;
        $duration = $endTime ? $startTime->diff($endTime) : null;

        $frequency = $rule->frequency;
        $interval = max(1, (int) $rule->interval_value);
        $endsType = $rule->ends_type;
        $maxOccurrences = $endsType === 'after_count' ? min((int) ($rule->ends_after_count ?? 10), 52) : 52;
        $endsOnDate = $rule->ends_on_date ? new \DateTime($rule->ends_on_date) : null;

        $occurrences = [];
        $current = clone $startTime;

        for ($i = 0; $i < $maxOccurrences; $i++) {
            switch ($frequency) {
                case 'daily':   $current->modify("+{$interval} days"); break;
                case 'weekly':  $current->modify("+{$interval} weeks"); break;
                case 'monthly': $current->modify("+{$interval} months"); break;
                case 'yearly':  $current->modify("+{$interval} years"); break;
                default:        $current->modify("+{$interval} weeks"); break;
            }

            if ($endsOnDate && $current > $endsOnDate) {
                break;
            }
            $oneYearOut = new \DateTime('+1 year');
            if ($current > $oneYearOut) {
                break;
            }

            $occStart = clone $current;
            $occEnd = null;
            if ($duration) {
                $occEnd = clone $occStart;
                $occEnd->add($duration);
            }

            $occurrences[] = [
                'start' => $occStart->format('Y-m-d H:i:s'),
                'end'   => $occEnd ? $occEnd->format('Y-m-d H:i:s') : null,
                'date'  => $occStart->format('Y-m-d'),
            ];
        }

        $count = 0;
        foreach ($occurrences as $occ) {
            try {
                DB::statement(
                    "INSERT INTO events (tenant_id, user_id, title, description, location, start_time, end_time,
                     group_id, category_id, latitude, longitude, federated_visibility, parent_event_id, occurrence_date,
                     max_attendees, series_id, created_at, updated_at)
                     VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, NOW(), NOW())",
                    [
                        $tenantId, (int) $template->user_id, $template->title,
                        $template->description ?? '', $template->location ?? '',
                        $occ['start'], $occ['end'],
                        $template->group_id, $template->category_id,
                        $template->latitude, $template->longitude,
                        $template->federated_visibility ?? 'none',
                        $templateId, $occ['date'],
                        $template->max_attendees, $template->series_id,
                    ]
                );
                $count++;
            } catch (\Exception $e) {
                Log::error("Failed to generate occurrence: " . $e->getMessage());
            }
        }

        return $count;
    }

    /**
     * Update recurring event(s).
     *
     * @param string $scope 'single' to update only this occurrence, 'all' for all future occurrences
     */
    public static function updateRecurring(int $eventId, int $userId, array $data, string $scope = 'single'): bool
    {
        self::$errors = [];
        $tenantId = \App\Core\TenantContext::getId();

        if ($scope === 'single') {
            // Detach from parent (make independent) and update
            DB::update("UPDATE events SET parent_event_id = NULL WHERE id = ? AND tenant_id = ?", [$eventId, $tenantId]);
            return self::update($eventId, $userId, $data);
        }

        // scope === 'all': update all future occurrences
        $event = DB::selectOne("SELECT * FROM events WHERE id = ? AND tenant_id = ?", [$eventId, $tenantId]);
        if (!$event) {
            self::$errors[] = ['code' => 'NOT_FOUND', 'message' => 'Event not found'];
            return false;
        }

        $parentId = $event->parent_event_id ?? $eventId;

        $ids = DB::select(
            "SELECT id FROM events WHERE (parent_event_id = ? OR id = ?) AND tenant_id = ? AND start_time >= NOW()",
            [$parentId, $parentId, $tenantId]
        );

        $updated = 0;
        foreach ($ids as $row) {
            try {
                self::update((int) $row->id, $userId, $data);
                $updated++;
            } catch (\Exception $e) {
                Log::error("EventService::updateRecurring failed for event {$row->id}: " . $e->getMessage());
            }
        }

        return $updated > 0;
    }
}
