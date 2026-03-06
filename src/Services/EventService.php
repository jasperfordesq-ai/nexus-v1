<?php
// Copyright © 2024–2026 Jasper Ford
// SPDX-License-Identifier: AGPL-3.0-or-later
// Author: Jasper Ford
// See NOTICE file for attribution and acknowledgements.

namespace Nexus\Services;

use Nexus\Core\Database;
use Nexus\Core\TenantContext;
use Nexus\Models\Event;
use Nexus\Models\EventRsvp;
use Nexus\Models\User;
use Nexus\Models\Notification;
use Nexus\Models\ActivityLog;

/**
 * EventService - Business logic for events
 *
 * This service extracts business logic from the Event model and EventController
 * to be shared between HTML and API controllers.
 *
 * Key operations:
 * - Event CRUD with validation
 * - RSVP management with capacity enforcement (E2)
 * - Waitlist management (E3)
 * - Event cancellation with notifications (E5)
 * - Event attendance tracking (E6)
 * - Event series linking (E7)
 * - Event recurrence (E1)
 * - Event reminders (E4)
 * - Attendee listing
 * - Cursor-based pagination
 */
class EventService
{
    /**
     * Validation error messages
     */
    private static array $errors = [];

    /**
     * Get validation errors
     */
    public static function getErrors(): array
    {
        return self::$errors;
    }

    /**
     * Get events with cursor-based pagination
     *
     * @param array $filters [
     *   'when' => 'upcoming'|'past' (default: upcoming),
     *   'category_id' => int,
     *   'group_id' => int,
     *   'user_id' => int (organizer),
     *   'search' => string,
     *   'cursor' => string,
     *   'limit' => int (default: 20, max: 100)
     * ]
     * @return array ['items' => [], 'cursor' => string|null, 'has_more' => bool]
     */
    public static function getAll(array $filters = []): array
    {
        $db = Database::getConnection();
        $tenantId = TenantContext::getId();

        $limit = min($filters['limit'] ?? 20, 100);
        $when = $filters['when'] ?? 'upcoming';
        $cursor = $filters['cursor'] ?? null;

        // Decode cursor (event ID)
        $cursorId = null;
        if ($cursor) {
            $decoded = base64_decode($cursor, true);
            if ($decoded && is_numeric($decoded)) {
                $cursorId = (int)$decoded;
            }
        }

        // Build query
        $sql = "
            SELECT
                e.*,
                u.name as organizer_name,
                u.first_name as organizer_first_name,
                u.last_name as organizer_last_name,
                u.avatar_url as organizer_avatar,
                c.name as category_name,
                c.color as category_color,
                (SELECT COUNT(*) FROM event_rsvps WHERE event_id = e.id AND status = 'going') as going_count,
                (SELECT COUNT(*) FROM event_rsvps WHERE event_id = e.id AND status = 'interested') as interested_count
            FROM events e
            JOIN users u ON e.user_id = u.id
            LEFT JOIN categories c ON e.category_id = c.id
            LEFT JOIN `groups` g ON e.group_id = g.id
            WHERE e.tenant_id = ?
            AND (g.visibility IS NULL OR g.visibility = 'public')
        ";
        $params = [$tenantId];

        // Time filter
        if ($when === 'past') {
            $sql .= " AND e.start_time < NOW()";
        } else {
            $sql .= " AND e.start_time >= NOW()";
        }

        // Category filter
        if (!empty($filters['category_id'])) {
            $sql .= " AND e.category_id = ?";
            $params[] = (int)$filters['category_id'];
        }

        // Group filter
        if (!empty($filters['group_id'])) {
            $sql .= " AND e.group_id = ?";
            $params[] = (int)$filters['group_id'];
        }

        // Organizer filter
        if (!empty($filters['user_id'])) {
            $sql .= " AND e.user_id = ?";
            $params[] = (int)$filters['user_id'];
        }

        // Search filter
        if (!empty($filters['search'])) {
            $sql .= " AND (e.title LIKE ? OR e.description LIKE ? OR e.location LIKE ?)";
            $term = '%' . $filters['search'] . '%';
            $params[] = $term;
            $params[] = $term;
            $params[] = $term;
        }

        // Cursor pagination
        if ($cursorId) {
            if ($when === 'past') {
                // Past events: order by start_time DESC, so cursor means "older than this"
                $sql .= " AND e.id < ?";
            } else {
                // Upcoming events: order by start_time ASC, so cursor means "after this"
                $sql .= " AND e.id > ?";
            }
            $params[] = $cursorId;
        }

        // Ordering
        if ($when === 'past') {
            $sql .= " ORDER BY e.start_time DESC, e.id DESC";
        } else {
            $sql .= " ORDER BY e.start_time ASC, e.id ASC";
        }

        $sql .= " LIMIT " . ($limit + 1);

        $stmt = $db->prepare($sql);
        $stmt->execute($params);
        $events = $stmt->fetchAll(\PDO::FETCH_ASSOC);

        // Check if there are more results
        $hasMore = count($events) > $limit;
        if ($hasMore) {
            array_pop($events);
        }

        // Format events
        $items = [];
        $lastId = null;

        foreach ($events as $event) {
            $lastId = $event['id'];
            $items[] = self::formatEvent($event);
        }

        return [
            'items' => $items,
            'cursor' => $hasMore && $lastId ? base64_encode((string)$lastId) : null,
            'has_more' => $hasMore,
        ];
    }

    /**
     * Get a single event by ID
     *
     * @param int $id
     * @param int|null $userId Optional user ID to include their RSVP status
     * @return array|null
     */
    public static function getById(int $id, ?int $userId = null): ?array
    {
        $tenantId = TenantContext::getId();
        $db = Database::getConnection();

        $sql = "
            SELECT
                e.*,
                u.name as organizer_name,
                u.first_name as organizer_first_name,
                u.last_name as organizer_last_name,
                u.avatar_url as organizer_avatar,
                c.name as category_name,
                c.color as category_color,
                (SELECT COUNT(*) FROM event_rsvps WHERE event_id = e.id AND status = 'going') as going_count,
                (SELECT COUNT(*) FROM event_rsvps WHERE event_id = e.id AND status = 'interested') as interested_count
            FROM events e
            JOIN users u ON e.user_id = u.id
            LEFT JOIN categories c ON e.category_id = c.id
            WHERE e.id = ? AND e.tenant_id = ?
        ";

        $stmt = $db->prepare($sql);
        $stmt->execute([$id, $tenantId]);
        $event = $stmt->fetch(\PDO::FETCH_ASSOC);

        if (!$event) {
            return null;
        }

        $formatted = self::formatEvent($event, true);

        // Add user's RSVP status if logged in
        if ($userId) {
            $formatted['rsvp_status'] = EventRsvp::getUserStatus($id, $userId);
            $formatted['user_rsvp'] = $formatted['rsvp_status'];
        }

        return $formatted;
    }

    /**
     * Format event data for API response
     *
     * @param array $event Raw event data
     * @param bool $detailed Include full description
     * @return array
     */
    private static function formatEvent(array $event, bool $detailed = false): array
    {
        $goingCount = (int)($event['going_count'] ?? 0);
        $maxAttendees = isset($event['max_attendees']) && $event['max_attendees'] !== null ? (int)$event['max_attendees'] : null;
        $spotsLeft = $maxAttendees !== null ? max(0, $maxAttendees - $goingCount) : null;

        $formatted = [
            'id' => (int)$event['id'],
            'title' => $event['title'],
            'description' => $detailed ? $event['description'] : self::truncate($event['description'] ?? '', 200),
            'location' => $event['location'],
            'is_online' => (bool)($event['is_online'] ?? false),
            'online_url' => $event['online_url'] ?? null,
            'latitude' => $event['latitude'] ? (float)$event['latitude'] : null,
            'longitude' => $event['longitude'] ? (float)$event['longitude'] : null,
            'coordinates' => ($event['latitude'] && $event['longitude']) ? [
                'lat' => (float)$event['latitude'],
                'lng' => (float)$event['longitude'],
            ] : null,
            'start_time' => $event['start_time'],
            'end_time' => $event['end_time'],
            'start_date' => $event['start_time'],
            'end_date' => $event['end_time'],
            'max_attendees' => $maxAttendees,
            'spots_left' => $spotsLeft,
            'is_full' => ($maxAttendees !== null && $goingCount >= $maxAttendees),
            'cover_image' => $event['cover_image'] ?? null,
            'status' => $event['status'] ?? 'active',
            'cancellation_reason' => $event['cancellation_reason'] ?? null,
            'series_id' => isset($event['series_id']) ? (int)$event['series_id'] : null,
            'parent_event_id' => isset($event['parent_event_id']) ? (int)$event['parent_event_id'] : null,
            'is_recurring' => !empty($event['is_recurring_template']) || !empty($event['parent_event_id']),
            'organizer' => [
                'id' => (int)$event['user_id'],
                'name' => $event['organizer_name'] ?? trim(($event['organizer_first_name'] ?? '') . ' ' . ($event['organizer_last_name'] ?? '')),
                'first_name' => $event['organizer_first_name'] ?? null,
                'last_name' => $event['organizer_last_name'] ?? null,
                'avatar' => $event['organizer_avatar'] ?? null,
                'avatar_url' => $event['organizer_avatar'] ?? null,
            ],
            'category' => $event['category_id'] ? [
                'id' => (int)$event['category_id'],
                'name' => $event['category_name'] ?? null,
                'color' => $event['category_color'] ?? null,
            ] : null,
            'category_name' => $event['category_name'] ?? null,
            'group_id' => $event['group_id'] ? (int)$event['group_id'] : null,
            'rsvp_counts' => [
                'going' => $goingCount,
                'interested' => (int)($event['interested_count'] ?? 0),
            ],
            'attendees_count' => $goingCount,
            'interested_count' => (int)($event['interested_count'] ?? 0),
            'created_at' => $event['created_at'] ?? null,
        ];

        // Add detailed fields
        if ($detailed) {
            $formatted['federated_visibility'] = $event['federated_visibility'] ?? 'none';
            $formatted['sdg_goals'] = $event['sdg_goals'] ? json_decode($event['sdg_goals'], true) : [];

            // Add waitlist count if capacity limited
            if ($maxAttendees !== null) {
                $formatted['waitlist_count'] = self::getWaitlistCount($event['id']);
            }

            // Add series info if part of a series
            if (!empty($event['series_id'])) {
                $formatted['series'] = self::getSeriesInfo((int)$event['series_id']);
            }
        }

        return $formatted;
    }

    /**
     * Validate event data
     *
     * @param array $data
     * @param bool $isUpdate
     * @return bool
     */
    public static function validate(array $data, bool $isUpdate = false): bool
    {
        self::$errors = [];

        // Title validation
        if (!$isUpdate || array_key_exists('title', $data)) {
            $title = trim($data['title'] ?? '');
            if (empty($title)) {
                self::$errors[] = ['code' => 'VALIDATION_ERROR', 'message' => 'Title is required', 'field' => 'title'];
            } elseif (strlen($title) > 255) {
                self::$errors[] = ['code' => 'VALIDATION_ERROR', 'message' => 'Title must be 255 characters or less', 'field' => 'title'];
            }
        }

        // Start time validation
        if (!$isUpdate || array_key_exists('start_time', $data)) {
            $startTime = $data['start_time'] ?? null;
            if (empty($startTime)) {
                self::$errors[] = ['code' => 'VALIDATION_ERROR', 'message' => 'Start time is required', 'field' => 'start_time'];
            } elseif (!strtotime($startTime)) {
                self::$errors[] = ['code' => 'VALIDATION_ERROR', 'message' => 'Invalid start time format', 'field' => 'start_time'];
            }
        }

        // End time validation (optional but must be after start if provided)
        if (!empty($data['end_time']) && !empty($data['start_time'])) {
            $start = strtotime($data['start_time']);
            $end = strtotime($data['end_time']);
            if ($end && $start && $end < $start) {
                self::$errors[] = ['code' => 'VALIDATION_ERROR', 'message' => 'End time must be after start time', 'field' => 'end_time'];
            }
        }

        // Description length
        if (isset($data['description']) && strlen($data['description']) > 10000) {
            self::$errors[] = ['code' => 'VALIDATION_ERROR', 'message' => 'Description must be 10000 characters or less', 'field' => 'description'];
        }

        // Location length
        if (isset($data['location']) && strlen($data['location']) > 500) {
            self::$errors[] = ['code' => 'VALIDATION_ERROR', 'message' => 'Location must be 500 characters or less', 'field' => 'location'];
        }

        // Latitude/Longitude validation
        if (isset($data['latitude']) && $data['latitude'] !== null) {
            $lat = (float)$data['latitude'];
            if ($lat < -90 || $lat > 90) {
                self::$errors[] = ['code' => 'VALIDATION_ERROR', 'message' => 'Latitude must be between -90 and 90', 'field' => 'latitude'];
            }
        }
        if (isset($data['longitude']) && $data['longitude'] !== null) {
            $lon = (float)$data['longitude'];
            if ($lon < -180 || $lon > 180) {
                self::$errors[] = ['code' => 'VALIDATION_ERROR', 'message' => 'Longitude must be between -180 and 180', 'field' => 'longitude'];
            }
        }

        // Federated visibility validation
        if (isset($data['federated_visibility'])) {
            $validVisibilities = ['none', 'listed', 'joinable'];
            if (!in_array($data['federated_visibility'], $validVisibilities)) {
                self::$errors[] = ['code' => 'VALIDATION_ERROR', 'message' => 'Invalid federated visibility', 'field' => 'federated_visibility'];
            }
        }

        return empty(self::$errors);
    }

    /**
     * Create a new event
     *
     * @param int $userId Organizer user ID
     * @param array $data Event data
     * @return int|null Event ID or null on failure
     */
    public static function create(int $userId, array $data): ?int
    {
        if (!self::validate($data, false)) {
            return null;
        }

        $tenantId = TenantContext::getId();

        // Verify group membership if group specified
        if (!empty($data['group_id'])) {
            if (!self::canPostToGroup((int)$data['group_id'], $userId)) {
                self::$errors[] = ['code' => 'FORBIDDEN', 'message' => 'You are not a member of this group'];
                return null;
            }
        }

        try {
            $eventId = Event::create(
                $tenantId,
                $userId,
                trim($data['title']),
                $data['description'] ?? '',
                $data['location'] ?? '',
                $data['start_time'],
                $data['end_time'] ?? null,
                $data['group_id'] ?? null,
                $data['category_id'] ?? null,
                $data['latitude'] ?? null,
                $data['longitude'] ?? null,
                $data['federated_visibility'] ?? 'none'
            );

            // Handle max_attendees
            if (isset($data['max_attendees']) && $data['max_attendees'] !== null) {
                Database::query("UPDATE events SET max_attendees = ? WHERE id = ? AND tenant_id = ?", [(int)$data['max_attendees'], $eventId, TenantContext::getId()]);
            }

            // Handle SDG goals
            if (!empty($data['sdg_goals']) && is_array($data['sdg_goals'])) {
                $goals = array_map('intval', $data['sdg_goals']);
                $json = json_encode($goals);
                Database::query("UPDATE events SET sdg_goals = ? WHERE id = ? AND tenant_id = ?", [$json, $eventId, TenantContext::getId()]);
            }

            // Record in feed_activity table
            try {
                FeedActivityService::recordActivity($tenantId, $userId, 'event', (int)$eventId, [
                    'title' => trim($data['title']),
                    'content' => $data['description'] ?? '',
                    'metadata' => [
                        'start_date' => $data['start_time'] ?? null,
                        'location' => $data['location'] ?? null,
                    ],
                    'group_id' => $data['group_id'] ?? null,
                    'created_at' => date('Y-m-d H:i:s'),
                ]);
            } catch (\Exception $faEx) {
                error_log("EventService::create feed_activity record failed: " . $faEx->getMessage());
            }

            // Log activity
            ActivityLog::log($userId, 'hosted an Event', $data['title'], true, '/events/' . $eventId);

            // Gamification
            try {
                GamificationService::checkEventBadges($userId, 'host');
            } catch (\Throwable $e) {
                error_log("Gamification event host error: " . $e->getMessage());
            }

            return (int)$eventId;
        } catch (\Exception $e) {
            error_log("EventService::create error: " . $e->getMessage());
            self::$errors[] = ['code' => 'SERVER_ERROR', 'message' => 'Failed to create event'];
            return null;
        }
    }

    /**
     * Update an existing event
     *
     * @param int $id Event ID
     * @param int $userId User making the update
     * @param array $data Updated data
     * @return bool
     */
    public static function update(int $id, int $userId, array $data): bool
    {
        self::$errors = [];

        // Get existing event
        $event = Event::find($id);
        if (!$event) {
            self::$errors[] = ['code' => 'NOT_FOUND', 'message' => 'Event not found'];
            return false;
        }

        // Check authorization
        if (!self::canModify($event, $userId)) {
            self::$errors[] = ['code' => 'FORBIDDEN', 'message' => 'You do not have permission to edit this event'];
            return false;
        }

        if (!self::validate($data, true)) {
            return false;
        }

        try {
            // Merge with existing data for fields not provided
            $title = array_key_exists('title', $data) ? trim($data['title']) : $event['title'];
            $description = array_key_exists('description', $data) ? $data['description'] : $event['description'];
            $location = array_key_exists('location', $data) ? $data['location'] : $event['location'];
            $startTime = array_key_exists('start_time', $data) ? $data['start_time'] : $event['start_time'];
            $endTime = array_key_exists('end_time', $data) ? $data['end_time'] : $event['end_time'];
            $groupId = array_key_exists('group_id', $data) ? $data['group_id'] : $event['group_id'];
            $categoryId = array_key_exists('category_id', $data) ? $data['category_id'] : $event['category_id'];
            $lat = array_key_exists('latitude', $data) ? $data['latitude'] : $event['latitude'];
            $lon = array_key_exists('longitude', $data) ? $data['longitude'] : $event['longitude'];
            $fedVis = array_key_exists('federated_visibility', $data) ? $data['federated_visibility'] : null;

            Event::update($id, $title, $description, $location, $startTime, $endTime, $groupId, $categoryId, $lat, $lon, $fedVis);

            // Handle max_attendees update
            if (array_key_exists('max_attendees', $data)) {
                $maxAtt = $data['max_attendees'] !== null ? (int)$data['max_attendees'] : null;
                Database::query("UPDATE events SET max_attendees = ? WHERE id = ? AND tenant_id = ?", [$maxAtt, $id, TenantContext::getId()]);
            }

            // Handle SDG goals update
            if (array_key_exists('sdg_goals', $data)) {
                $goals = is_array($data['sdg_goals']) ? array_map('intval', $data['sdg_goals']) : [];
                $json = json_encode($goals);
                Database::query("UPDATE events SET sdg_goals = ? WHERE id = ? AND tenant_id = ?", [$json, $id, TenantContext::getId()]);
            }

            return true;
        } catch (\Exception $e) {
            error_log("EventService::update error: " . $e->getMessage());
            self::$errors[] = ['code' => 'SERVER_ERROR', 'message' => 'Failed to update event'];
            return false;
        }
    }

    /**
     * Delete an event
     *
     * @param int $id Event ID
     * @param int $userId User making the deletion
     * @return bool
     */
    public static function delete(int $id, int $userId): bool
    {
        self::$errors = [];

        $event = Event::find($id);
        if (!$event) {
            self::$errors[] = ['code' => 'NOT_FOUND', 'message' => 'Event not found'];
            return false;
        }

        if (!self::canModify($event, $userId)) {
            self::$errors[] = ['code' => 'FORBIDDEN', 'message' => 'You do not have permission to delete this event'];
            return false;
        }

        try {
            Event::delete($id);

            // Remove from feed_activity
            try {
                FeedActivityService::removeActivity('event', $id);
            } catch (\Exception $faEx) {
                error_log("EventService::delete feed_activity remove failed: " . $faEx->getMessage());
            }

            return true;
        } catch (\Exception $e) {
            error_log("EventService::delete error: " . $e->getMessage());
            self::$errors[] = ['code' => 'SERVER_ERROR', 'message' => 'Failed to delete event'];
            return false;
        }
    }

    /**
     * Set RSVP status for an event (E2: capacity enforcement)
     *
     * @param int $eventId
     * @param int $userId
     * @param string $status 'going', 'interested', 'not_going'
     * @return array ['success' => bool, 'waitlisted' => bool]
     */
    public static function rsvp(int $eventId, int $userId, string $status): bool
    {
        self::$errors = [];

        // Validate status
        $validStatuses = ['going', 'interested', 'not_going', 'declined'];
        if (!in_array($status, $validStatuses)) {
            self::$errors[] = ['code' => 'VALIDATION_ERROR', 'message' => 'Invalid RSVP status', 'field' => 'status'];
            return false;
        }

        // Check event exists and is active
        $event = Event::find($eventId);
        if (!$event) {
            self::$errors[] = ['code' => 'NOT_FOUND', 'message' => 'Event not found'];
            return false;
        }

        if (($event['status'] ?? 'active') === 'cancelled') {
            self::$errors[] = ['code' => 'EVENT_CANCELLED', 'message' => 'This event has been cancelled'];
            return false;
        }

        // E2: Capacity enforcement for 'going' status
        if ($status === 'going' && !empty($event['max_attendees'])) {
            $maxAttendees = (int)$event['max_attendees'];
            $currentGoing = (int)EventRsvp::getCount($eventId, 'going');

            // Check if user already has 'going' status (re-RSVP)
            $currentUserStatus = EventRsvp::getUserStatus($eventId, $userId);
            $isAlreadyGoing = ($currentUserStatus === 'going');

            if (!$isAlreadyGoing && $currentGoing >= $maxAttendees) {
                // Event is full — add to waitlist instead (E3)
                self::addToWaitlist($eventId, $userId);
                self::$errors[] = [
                    'code' => 'EVENT_FULL',
                    'message' => 'This event is full. You have been added to the waitlist.',
                    'waitlisted' => true,
                ];
                return false;
            }
        }

        try {
            EventRsvp::rsvp($eventId, $userId, $status);

            // If going, remove from waitlist if they were on it
            if ($status === 'going') {
                self::removeFromWaitlist($eventId, $userId);
            }

            // Gamification for "going"
            if ($status === 'going') {
                try {
                    GamificationService::checkEventBadges($userId, 'attend');
                } catch (\Throwable $e) {
                    error_log("Gamification event attend error: " . $e->getMessage());
                }
            }

            // E4: Auto-create default reminders when going
            if ($status === 'going') {
                self::createDefaultReminders($eventId, $userId);
            }

            return true;
        } catch (\Exception $e) {
            error_log("EventService::rsvp error: " . $e->getMessage());
            self::$errors[] = ['code' => 'SERVER_ERROR', 'message' => 'Failed to update RSVP'];
            return false;
        }
    }

    /**
     * Remove RSVP for an event (E2/E3: auto-promote from waitlist)
     *
     * @param int $eventId
     * @param int $userId
     * @return bool
     */
    public static function removeRsvp(int $eventId, int $userId): bool
    {
        self::$errors = [];

        // Validate event belongs to this tenant before any mutation
        $event = Event::find($eventId);
        if (!$event) {
            self::$errors[] = ['code' => 'NOT_FOUND', 'message' => 'Event not found'];
            return false;
        }

        $db = Database::getConnection();

        try {
            // Check if user was 'going' — we need to know for waitlist promotion
            $currentStatus = EventRsvp::getUserStatus($eventId, $userId);

            $stmt = $db->prepare("DELETE FROM event_rsvps WHERE event_id = ? AND user_id = ?");
            $stmt->execute([$eventId, $userId]);

            // Also remove any reminders for this user/event
            self::cancelReminders($eventId, $userId);

            // E3: If user was 'going' and event has capacity, promote from waitlist
            if ($currentStatus === 'going') {
                self::promoteFromWaitlist($eventId);
            }

            return true;
        } catch (\Exception $e) {
            error_log("EventService::removeRsvp error: " . $e->getMessage());
            self::$errors[] = ['code' => 'SERVER_ERROR', 'message' => 'Failed to remove RSVP'];
            return false;
        }
    }

    /**
     * Get event attendees with cursor-based pagination
     *
     * @param int $eventId
     * @param array $filters ['status' => 'going'|'interested'|'invited', 'cursor', 'limit']
     * @return array ['items' => [], 'cursor' => string|null, 'has_more' => bool]
     */
    public static function getAttendees(int $eventId, array $filters = []): array
    {
        $db = Database::getConnection();

        $limit = min($filters['limit'] ?? 20, 100);
        $status = $filters['status'] ?? 'going';
        $cursor = $filters['cursor'] ?? null;

        // Validate status
        $validStatuses = ['going', 'interested', 'invited', 'attended'];
        if (!in_array($status, $validStatuses)) {
            $status = 'going';
        }

        // Decode cursor
        $cursorId = null;
        if ($cursor) {
            $decoded = base64_decode($cursor, true);
            if ($decoded && is_numeric($decoded)) {
                $cursorId = (int)$decoded;
            }
        }

        $sql = "
            SELECT r.id as rsvp_id, r.user_id, r.status, r.created_at as rsvp_at,
                   u.name, u.first_name, u.last_name, u.avatar_url
            FROM event_rsvps r
            JOIN users u ON r.user_id = u.id
            WHERE r.event_id = ? AND r.status = ?
        ";
        $params = [$eventId, $status];

        if ($cursorId) {
            $sql .= " AND r.id > ?";
            $params[] = $cursorId;
        }

        $sql .= " ORDER BY r.id ASC LIMIT " . ($limit + 1);

        $stmt = $db->prepare($sql);
        $stmt->execute($params);
        $attendees = $stmt->fetchAll(\PDO::FETCH_ASSOC);

        $hasMore = count($attendees) > $limit;
        if ($hasMore) {
            array_pop($attendees);
        }

        $items = [];
        $lastId = null;

        foreach ($attendees as $att) {
            $lastId = $att['rsvp_id'];
            $items[] = [
                'id' => (int)$att['user_id'],
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
            'cursor' => $hasMore && $lastId ? base64_encode((string)$lastId) : null,
            'has_more' => $hasMore,
        ];
    }

    /**
     * Get user's RSVP status for an event
     *
     * @param int $eventId
     * @param int $userId
     * @return string|null
     */
    public static function getUserRsvp(int $eventId, int $userId): ?string
    {
        return EventRsvp::getUserStatus($eventId, $userId);
    }

    /**
     * Check if user can modify an event
     *
     * @param array $event
     * @param int $userId
     * @return bool
     */
    public static function canModify(array $event, int $userId): bool
    {
        // Owner can always modify
        if ((int)$event['user_id'] === $userId) {
            return true;
        }

        // Check for admin role
        $user = User::findById($userId);
        if ($user && in_array($user['role'] ?? '', ['admin', 'super_admin', 'god'])) {
            return true;
        }

        return false;
    }

    /**
     * Check if user can post to a group
     */
    private static function canPostToGroup(int $groupId, int $userId): bool
    {
        if (class_exists('\Nexus\Models\Group')) {
            return \Nexus\Models\Group::isMember($groupId, $userId);
        }
        return false;
    }

    /**
     * Truncate text to a maximum length
     */
    private static function truncate(string $text, int $length): string
    {
        if (mb_strlen($text) <= $length) {
            return $text;
        }
        return mb_substr($text, 0, $length - 3) . '...';
    }

    /**
     * Update event cover image
     *
     * @param int $eventId
     * @param int $userId
     * @param string $imageUrl
     * @return bool
     */
    public static function updateImage(int $eventId, int $userId, string $imageUrl): bool
    {
        self::$errors = [];

        $event = Event::find($eventId);
        if (!$event) {
            self::$errors[] = ['code' => 'NOT_FOUND', 'message' => 'Event not found'];
            return false;
        }

        if (!self::canModify($event, $userId)) {
            self::$errors[] = ['code' => 'FORBIDDEN', 'message' => 'You do not have permission to modify this event'];
            return false;
        }

        try {
            Database::query("UPDATE events SET cover_image = ? WHERE id = ? AND tenant_id = ?", [$imageUrl, $eventId, TenantContext::getId()]);
            return true;
        } catch (\Exception $e) {
            error_log("EventService::updateImage error: " . $e->getMessage());
            self::$errors[] = ['code' => 'SERVER_ERROR', 'message' => 'Failed to update image'];
            return false;
        }
    }

    /**
     * Get nearby upcoming events with distance calculation
     *
     * @param float $lat Latitude
     * @param float $lon Longitude
     * @param array $filters Additional filters (radius_km, category_id, limit)
     * @return array ['items' => [], 'has_more' => bool]
     */
    public static function getNearby(float $lat, float $lon, array $filters = []): array
    {
        $radiusKm = (float)($filters['radius_km'] ?? 25);
        $limit = min((int)($filters['limit'] ?? 20), 100);

        $items = Event::getNearby(
            $lat,
            $lon,
            $radiusKm,
            $limit,
            $filters['category_id'] ?? null
        );

        return [
            'items' => $items,
            'has_more' => count($items) >= $limit,
        ];
    }

    // =========================================================================
    // E3: WAITLIST MANAGEMENT
    // =========================================================================

    /**
     * Add user to event waitlist
     */
    public static function addToWaitlist(int $eventId, int $userId): bool
    {
        $db = Database::getConnection();
        $tenantId = TenantContext::getId();

        try {
            // Check if already on waitlist
            $exists = $db->prepare("SELECT id FROM event_waitlist WHERE event_id = ? AND user_id = ? AND status = 'waiting'");
            $exists->execute([$eventId, $userId]);
            if ($exists->fetch()) {
                return true; // Already on waitlist
            }

            // Get next position
            $posStmt = $db->prepare("SELECT COALESCE(MAX(position), 0) + 1 as next_pos FROM event_waitlist WHERE event_id = ? AND status = 'waiting'");
            $posStmt->execute([$eventId]);
            $nextPos = (int)$posStmt->fetch(\PDO::FETCH_ASSOC)['next_pos'];

            $db->prepare(
                "INSERT INTO event_waitlist (event_id, user_id, tenant_id, position, status) VALUES (?, ?, ?, ?, 'waiting')
                 ON DUPLICATE KEY UPDATE status = 'waiting', position = ?, updated_at = NOW()"
            )->execute([$eventId, $userId, $tenantId, $nextPos, $nextPos]);

            // Notify user they're on waitlist
            $event = Event::find($eventId);
            if ($event) {
                Notification::create(
                    $userId,
                    "You've been added to the waitlist for \"{$event['title']}\" (position #{$nextPos}). We'll notify you if a spot opens up.",
                    '/events/' . $eventId,
                    'event',
                    true,
                    $tenantId
                );
            }

            return true;
        } catch (\Exception $e) {
            error_log("EventService::addToWaitlist error: " . $e->getMessage());
            return false;
        }
    }

    /**
     * Remove user from waitlist
     */
    public static function removeFromWaitlist(int $eventId, int $userId): bool
    {
        $db = Database::getConnection();

        try {
            $db->prepare(
                "UPDATE event_waitlist SET status = 'cancelled', cancelled_at = NOW() WHERE event_id = ? AND user_id = ? AND status = 'waiting' AND tenant_id = ?"
            )->execute([$eventId, $userId, TenantContext::getId()]);
            return true;
        } catch (\Exception $e) {
            error_log("EventService::removeFromWaitlist error: " . $e->getMessage());
            return false;
        }
    }

    /**
     * Promote next user from waitlist when a spot opens (E3)
     */
    public static function promoteFromWaitlist(int $eventId): bool
    {
        $db = Database::getConnection();

        $event = Event::find($eventId);
        if (!$event || empty($event['max_attendees'])) {
            return false;
        }

        $maxAttendees = (int)$event['max_attendees'];
        $currentGoing = (int)EventRsvp::getCount($eventId, 'going');

        if ($currentGoing >= $maxAttendees) {
            return false; // Still full
        }

        try {
            // Get next person on waitlist
            $stmt = $db->prepare(
                "SELECT id, user_id FROM event_waitlist WHERE event_id = ? AND status = 'waiting' ORDER BY position ASC LIMIT 1"
            );
            $stmt->execute([$eventId]);
            $next = $stmt->fetch(\PDO::FETCH_ASSOC);

            if (!$next) {
                return false; // No one waiting
            }

            // Promote: update waitlist status
            $db->prepare(
                "UPDATE event_waitlist SET status = 'promoted', promoted_at = NOW() WHERE id = ? AND tenant_id = ?"
            )->execute([$next['id'], TenantContext::getId()]);

            // Set RSVP to going
            EventRsvp::rsvp($eventId, (int)$next['user_id'], 'going');

            // Create reminders for promoted user
            self::createDefaultReminders($eventId, (int)$next['user_id']);

            // Notify the promoted user
            Notification::create(
                (int)$next['user_id'],
                "A spot opened up! You've been moved from the waitlist to the attendee list for \"{$event['title']}\".",
                '/events/' . $eventId,
                'event',
                true,
                TenantContext::getId()
            );

            return true;
        } catch (\Exception $e) {
            error_log("EventService::promoteFromWaitlist error: " . $e->getMessage());
            return false;
        }
    }

    /**
     * Get waitlist for an event
     */
    public static function getWaitlist(int $eventId, array $filters = []): array
    {
        $db = Database::getConnection();
        $limit = min($filters['limit'] ?? 20, 100);

        $stmt = $db->prepare("
            SELECT w.id, w.user_id, w.position, w.status, w.created_at,
                   u.name, u.first_name, u.last_name, u.avatar_url
            FROM event_waitlist w
            JOIN users u ON w.user_id = u.id
            WHERE w.event_id = ? AND w.status = 'waiting'
            ORDER BY w.position ASC
            LIMIT " . ($limit + 1)
        );
        $stmt->execute([$eventId]);
        $items = $stmt->fetchAll(\PDO::FETCH_ASSOC);

        $hasMore = count($items) > $limit;
        if ($hasMore) {
            array_pop($items);
        }

        $formatted = [];
        foreach ($items as $item) {
            $formatted[] = [
                'id' => (int)$item['user_id'],
                'name' => $item['name'] ?? trim(($item['first_name'] ?? '') . ' ' . ($item['last_name'] ?? '')),
                'first_name' => $item['first_name'] ?? null,
                'last_name' => $item['last_name'] ?? null,
                'avatar_url' => $item['avatar_url'],
                'position' => (int)$item['position'],
                'joined_at' => $item['created_at'],
            ];
        }

        return ['items' => $formatted, 'has_more' => $hasMore];
    }

    /**
     * Get waitlist count for an event
     */
    public static function getWaitlistCount(int $eventId): int
    {
        $db = Database::getConnection();
        try {
            $stmt = $db->prepare("SELECT COUNT(*) FROM event_waitlist WHERE event_id = ? AND status = 'waiting'");
            $stmt->execute([$eventId]);
            return (int)$stmt->fetchColumn();
        } catch (\Exception $e) {
            return 0;
        }
    }

    /**
     * Get user's waitlist position (or null if not on waitlist)
     */
    public static function getUserWaitlistPosition(int $eventId, int $userId): ?int
    {
        $db = Database::getConnection();
        try {
            $stmt = $db->prepare("SELECT position FROM event_waitlist WHERE event_id = ? AND user_id = ? AND status = 'waiting'");
            $stmt->execute([$eventId, $userId]);
            $row = $stmt->fetch(\PDO::FETCH_ASSOC);
            return $row ? (int)$row['position'] : null;
        } catch (\Exception $e) {
            return null;
        }
    }

    // =========================================================================
    // E4: EVENT REMINDERS
    // =========================================================================

    /**
     * Create default reminders for a user attending an event
     * Default: 1 hour before and 1 day before
     */
    public static function createDefaultReminders(int $eventId, int $userId): void
    {
        $event = Event::find($eventId);
        if (!$event || !$event['start_time']) {
            return;
        }

        $tenantId = TenantContext::getId();
        $defaultIntervals = [60, 1440]; // 1 hour, 1 day (in minutes)

        foreach ($defaultIntervals as $minutes) {
            self::createReminder($eventId, $userId, $tenantId, $minutes, 'both');
        }
    }

    /**
     * Create a single event reminder
     */
    public static function createReminder(int $eventId, int $userId, int $tenantId, int $minutesBefore, string $type = 'both'): bool
    {
        $db = Database::getConnection();

        $event = Event::find($eventId);
        if (!$event || !$event['start_time']) {
            return false;
        }

        $startTimestamp = strtotime($event['start_time']);
        $scheduledFor = date('Y-m-d H:i:s', $startTimestamp - ($minutesBefore * 60));

        // Don't create reminders in the past
        if (strtotime($scheduledFor) < time()) {
            return false;
        }

        try {
            $db->prepare("
                INSERT INTO event_reminders (event_id, user_id, tenant_id, remind_before_minutes, reminder_type, scheduled_for, status)
                VALUES (?, ?, ?, ?, ?, ?, 'pending')
                ON DUPLICATE KEY UPDATE status = 'pending', scheduled_for = ?, updated_at = NOW()
            ")->execute([$eventId, $userId, $tenantId, $minutesBefore, $type, $scheduledFor, $scheduledFor]);
            return true;
        } catch (\Exception $e) {
            error_log("EventService::createReminder error: " . $e->getMessage());
            return false;
        }
    }

    /**
     * Update user's reminder preferences for an event
     *
     * @param int $eventId
     * @param int $userId
     * @param array $reminders Array of ['minutes' => int, 'type' => 'platform'|'email'|'both']
     * @return bool
     */
    public static function updateReminders(int $eventId, int $userId, array $reminders): bool
    {
        $tenantId = TenantContext::getId();
        $db = Database::getConnection();

        try {
            // Cancel existing reminders
            self::cancelReminders($eventId, $userId);

            // Create new reminders
            $validTypes = ['platform', 'email', 'both'];
            $validMinutes = [60, 1440, 10080]; // 1hr, 1day, 1week

            foreach ($reminders as $reminder) {
                $minutes = (int)($reminder['minutes'] ?? 0);
                $type = $reminder['type'] ?? 'both';

                if (!in_array($minutes, $validMinutes) || !in_array($type, $validTypes)) {
                    continue;
                }

                self::createReminder($eventId, $userId, $tenantId, $minutes, $type);
            }

            return true;
        } catch (\Exception $e) {
            error_log("EventService::updateReminders error: " . $e->getMessage());
            return false;
        }
    }

    /**
     * Cancel all reminders for a user's event
     */
    public static function cancelReminders(int $eventId, int $userId): void
    {
        $db = Database::getConnection();
        try {
            $db->prepare(
                "UPDATE event_reminders SET status = 'cancelled' WHERE event_id = ? AND user_id = ? AND status = 'pending' AND tenant_id = ?"
            )->execute([$eventId, $userId, TenantContext::getId()]);
        } catch (\Exception $e) {
            error_log("EventService::cancelReminders error: " . $e->getMessage());
        }
    }

    /**
     * Get user's reminders for an event
     */
    public static function getUserReminders(int $eventId, int $userId): array
    {
        $db = Database::getConnection();
        try {
            $stmt = $db->prepare("
                SELECT remind_before_minutes, reminder_type, status, scheduled_for
                FROM event_reminders
                WHERE event_id = ? AND user_id = ? AND status = 'pending'
                ORDER BY remind_before_minutes ASC
            ");
            $stmt->execute([$eventId, $userId]);
            return $stmt->fetchAll(\PDO::FETCH_ASSOC);
        } catch (\Exception $e) {
            return [];
        }
    }

    /**
     * Process pending reminders (cron job logic — E4)
     * Call this from a scheduled task: `php scripts/process_event_reminders.php`
     *
     * @return int Number of reminders sent
     */
    public static function processPendingReminders(): int
    {
        $db = Database::getConnection();
        $sentCount = 0;

        try {
            // Get all reminders that should fire now
            $stmt = $db->prepare("
                SELECT r.*, e.title as event_title, e.start_time, e.location
                FROM event_reminders r
                JOIN events e ON r.event_id = e.id
                WHERE r.status = 'pending'
                AND r.scheduled_for <= NOW()
                AND e.status = 'active'
                LIMIT 100
            ");
            $stmt->execute();
            $reminders = $stmt->fetchAll(\PDO::FETCH_ASSOC);

            foreach ($reminders as $reminder) {
                try {
                    $minutesBefore = (int)$reminder['remind_before_minutes'];
                    $timeLabel = match(true) {
                        $minutesBefore >= 10080 => '1 week',
                        $minutesBefore >= 1440 => '1 day',
                        $minutesBefore >= 60 => '1 hour',
                        default => $minutesBefore . ' minutes',
                    };

                    $message = "Reminder: \"{$reminder['event_title']}\" starts in {$timeLabel}";
                    if ($reminder['location']) {
                        $message .= " at {$reminder['location']}";
                    }

                    $shouldPlatform = in_array($reminder['reminder_type'], ['platform', 'both']);
                    $shouldEmail = in_array($reminder['reminder_type'], ['email', 'both']);

                    if ($shouldPlatform) {
                        Notification::create(
                            (int)$reminder['user_id'],
                            $message,
                            '/events/' . $reminder['event_id'],
                            'event_reminder',
                            true,
                            (int)$reminder['tenant_id']
                        );
                    }

                    // Mark as sent
                    $db->prepare(
                        "UPDATE event_reminders SET status = 'sent', sent_at = NOW() WHERE id = ? AND tenant_id = ?"
                    )->execute([$reminder['id'], $reminder['tenant_id']]);

                    $sentCount++;
                } catch (\Exception $e) {
                    error_log("Failed to send reminder {$reminder['id']}: " . $e->getMessage());
                    $db->prepare(
                        "UPDATE event_reminders SET status = 'failed' WHERE id = ? AND tenant_id = ?"
                    )->execute([$reminder['id'], $reminder['tenant_id']]);
                }
            }
        } catch (\Exception $e) {
            error_log("EventService::processPendingReminders error: " . $e->getMessage());
        }

        return $sentCount;
    }

    // =========================================================================
    // E5: EVENT CANCELLATION WITH NOTIFICATIONS
    // =========================================================================

    /**
     * Cancel an event and notify all RSVPs
     *
     * @param int $eventId
     * @param int $userId User performing the cancellation
     * @param string $reason Cancellation reason
     * @return bool
     */
    public static function cancelEvent(int $eventId, int $userId, string $reason = ''): bool
    {
        self::$errors = [];

        $event = Event::find($eventId);
        if (!$event) {
            self::$errors[] = ['code' => 'NOT_FOUND', 'message' => 'Event not found'];
            return false;
        }

        if (!self::canModify($event, $userId)) {
            self::$errors[] = ['code' => 'FORBIDDEN', 'message' => 'You do not have permission to cancel this event'];
            return false;
        }

        if (($event['status'] ?? 'active') === 'cancelled') {
            self::$errors[] = ['code' => 'ALREADY_CANCELLED', 'message' => 'This event is already cancelled'];
            return false;
        }

        $tenantId = TenantContext::getId();
        $db = Database::getConnection();

        try {
            // Update event status
            $db->prepare("
                UPDATE events SET status = 'cancelled', cancellation_reason = ?, cancelled_at = NOW(), cancelled_by = ?
                WHERE id = ? AND tenant_id = ?
            ")->execute([$reason, $userId, $eventId, $tenantId]);

            // Cancel all pending reminders
            $db->prepare("
                UPDATE event_reminders SET status = 'cancelled' WHERE event_id = ? AND status = 'pending' AND tenant_id = ?
            ")->execute([$eventId, $tenantId]);

            // Cancel all waitlist entries
            $db->prepare("
                UPDATE event_waitlist SET status = 'cancelled', cancelled_at = NOW() WHERE event_id = ? AND status = 'waiting' AND tenant_id = ?
            ")->execute([$eventId, $tenantId]);

            // Notify all RSVPs (going + interested)
            self::notifyCancellation($eventId, $event['title'], $reason, $tenantId);

            // Hide from feed_activity
            try {
                FeedActivityService::hideActivity('event', $eventId);
            } catch (\Exception $faEx) {
                error_log("EventService::cancelEvent feed_activity hide failed: " . $faEx->getMessage());
            }

            // Log activity
            ActivityLog::log($userId, 'cancelled an Event', $event['title'], true, '/events/' . $eventId);

            return true;
        } catch (\Exception $e) {
            error_log("EventService::cancelEvent error: " . $e->getMessage());
            self::$errors[] = ['code' => 'SERVER_ERROR', 'message' => 'Failed to cancel event'];
            return false;
        }
    }

    /**
     * Send cancellation notifications to all RSVPs (E5)
     */
    private static function notifyCancellation(int $eventId, string $eventTitle, string $reason, int $tenantId): void
    {
        $db = Database::getConnection();

        try {
            // Get all users with RSVP (going, interested, invited)
            $stmt = $db->prepare("
                SELECT DISTINCT user_id FROM event_rsvps
                WHERE event_id = ? AND status IN ('going', 'interested', 'invited')
            ");
            $stmt->execute([$eventId]);
            $rsvps = $stmt->fetchAll(\PDO::FETCH_COLUMN);

            // Also get waitlisted users
            $wStmt = $db->prepare("
                SELECT DISTINCT user_id FROM event_waitlist
                WHERE event_id = ? AND status = 'waiting'
            ");
            $wStmt->execute([$eventId]);
            $waitlisted = $wStmt->fetchAll(\PDO::FETCH_COLUMN);

            $allUsers = array_unique(array_merge($rsvps, $waitlisted));

            $message = "The event \"{$eventTitle}\" has been cancelled.";
            if (!empty($reason)) {
                $message .= " Reason: {$reason}";
            }

            foreach ($allUsers as $uid) {
                try {
                    Notification::create(
                        (int)$uid,
                        $message,
                        '/events/' . $eventId,
                        'event',
                        true,
                        $tenantId
                    );
                } catch (\Exception $e) {
                    error_log("Failed to notify user {$uid} of event cancellation: " . $e->getMessage());
                }
            }
        } catch (\Exception $e) {
            error_log("EventService::notifyCancellation error: " . $e->getMessage());
        }
    }

    // =========================================================================
    // E6: EVENT ATTENDANCE TRACKING
    // =========================================================================

    /**
     * Mark a user as attended (post-event attendance tracking)
     *
     * @param int $eventId
     * @param int $attendeeId User being marked as attended
     * @param int $markedById User doing the marking (organizer/admin)
     * @param float|null $hoursOverride Override calculated hours
     * @param string|null $notes Optional notes
     * @return bool
     */
    public static function markAttended(int $eventId, int $attendeeId, int $markedById, ?float $hoursOverride = null, ?string $notes = null): bool
    {
        self::$errors = [];

        $event = Event::find($eventId);
        if (!$event) {
            self::$errors[] = ['code' => 'NOT_FOUND', 'message' => 'Event not found'];
            return false;
        }

        if (!self::canModify($event, $markedById)) {
            self::$errors[] = ['code' => 'FORBIDDEN', 'message' => 'Only the organizer or admin can mark attendance'];
            return false;
        }

        $tenantId = TenantContext::getId();
        $db = Database::getConnection();

        // Calculate hours from event duration
        $hours = $hoursOverride;
        if ($hours === null && !empty($event['start_time']) && !empty($event['end_time'])) {
            $start = strtotime($event['start_time']);
            $end = strtotime($event['end_time']);
            $hours = round(($end - $start) / 3600, 2);
            if ($hours < 0.5) {
                $hours = 0.5;
            }
        }
        if ($hours === null) {
            $hours = 1.0;
        }

        try {
            // Upsert attendance record
            $db->prepare("
                INSERT INTO event_attendance (event_id, user_id, tenant_id, checked_in_at, checked_in_by, hours_credited, notes)
                VALUES (?, ?, ?, NOW(), ?, ?, ?)
                ON DUPLICATE KEY UPDATE checked_in_at = NOW(), checked_in_by = ?, hours_credited = ?, notes = ?, updated_at = NOW()
            ")->execute([$eventId, $attendeeId, $tenantId, $markedById, $hours, $notes, $markedById, $hours, $notes]);

            // Also update RSVP status to 'attended' for backward compatibility
            EventRsvp::rsvp($eventId, $attendeeId, 'attended');

            return true;
        } catch (\Exception $e) {
            error_log("EventService::markAttended error: " . $e->getMessage());
            self::$errors[] = ['code' => 'SERVER_ERROR', 'message' => 'Failed to mark attendance'];
            return false;
        }
    }

    /**
     * Unmark a user's attendance
     */
    public static function unmarkAttended(int $eventId, int $attendeeId, int $markedById): bool
    {
        self::$errors = [];

        $event = Event::find($eventId);
        if (!$event) {
            self::$errors[] = ['code' => 'NOT_FOUND', 'message' => 'Event not found'];
            return false;
        }

        if (!self::canModify($event, $markedById)) {
            self::$errors[] = ['code' => 'FORBIDDEN', 'message' => 'Only the organizer or admin can modify attendance'];
            return false;
        }

        $db = Database::getConnection();

        try {
            $db->prepare("DELETE FROM event_attendance WHERE event_id = ? AND user_id = ?")->execute([$eventId, $attendeeId]);
            // Revert RSVP status back to going
            EventRsvp::rsvp($eventId, $attendeeId, 'going');
            return true;
        } catch (\Exception $e) {
            error_log("EventService::unmarkAttended error: " . $e->getMessage());
            self::$errors[] = ['code' => 'SERVER_ERROR', 'message' => 'Failed to unmark attendance'];
            return false;
        }
    }

    /**
     * Bulk mark attendance (organizer can mark multiple users at once)
     *
     * @param int $eventId
     * @param array $attendeeIds Array of user IDs
     * @param int $markedById
     * @return array ['marked' => int, 'failed' => int]
     */
    public static function bulkMarkAttended(int $eventId, array $attendeeIds, int $markedById): array
    {
        $marked = 0;
        $failed = 0;

        foreach ($attendeeIds as $attendeeId) {
            if (self::markAttended($eventId, (int)$attendeeId, $markedById)) {
                $marked++;
            } else {
                $failed++;
            }
        }

        return ['marked' => $marked, 'failed' => $failed];
    }

    /**
     * Get attendance records for an event
     */
    public static function getAttendanceRecords(int $eventId): array
    {
        $db = Database::getConnection();

        try {
            $stmt = $db->prepare("
                SELECT a.*, u.name, u.first_name, u.last_name, u.avatar_url,
                       cb.name as checked_in_by_name
                FROM event_attendance a
                JOIN users u ON a.user_id = u.id
                LEFT JOIN users cb ON a.checked_in_by = cb.id
                WHERE a.event_id = ?
                ORDER BY a.checked_in_at ASC
            ");
            $stmt->execute([$eventId]);
            $records = $stmt->fetchAll(\PDO::FETCH_ASSOC);

            $items = [];
            foreach ($records as $r) {
                $items[] = [
                    'user_id' => (int)$r['user_id'],
                    'name' => $r['name'] ?? trim(($r['first_name'] ?? '') . ' ' . ($r['last_name'] ?? '')),
                    'first_name' => $r['first_name'] ?? null,
                    'last_name' => $r['last_name'] ?? null,
                    'avatar_url' => $r['avatar_url'],
                    'checked_in_at' => $r['checked_in_at'],
                    'checked_in_by' => $r['checked_in_by_name'] ?? null,
                    'hours_credited' => $r['hours_credited'] ? (float)$r['hours_credited'] : null,
                    'notes' => $r['notes'],
                ];
            }

            return $items;
        } catch (\Exception $e) {
            error_log("EventService::getAttendanceRecords error: " . $e->getMessage());
            return [];
        }
    }

    /**
     * Get attendance stats for a user across all events
     */
    public static function getUserAttendanceStats(int $userId): array
    {
        $db = Database::getConnection();
        $tenantId = TenantContext::getId();

        try {
            $stmt = $db->prepare("
                SELECT COUNT(*) as total_attended,
                       COALESCE(SUM(hours_credited), 0) as total_hours
                FROM event_attendance
                WHERE user_id = ? AND tenant_id = ?
            ");
            $stmt->execute([$userId, $tenantId]);
            $stats = $stmt->fetch(\PDO::FETCH_ASSOC);

            return [
                'total_attended' => (int)($stats['total_attended'] ?? 0),
                'total_hours' => (float)($stats['total_hours'] ?? 0),
            ];
        } catch (\Exception $e) {
            return ['total_attended' => 0, 'total_hours' => 0];
        }
    }

    // =========================================================================
    // E7: EVENT SERIES LINKING
    // =========================================================================

    /**
     * Create a new event series
     */
    public static function createSeries(int $userId, string $title, ?string $description = null): ?int
    {
        self::$errors = [];
        $tenantId = TenantContext::getId();
        $db = Database::getConnection();

        if (empty(trim($title))) {
            self::$errors[] = ['code' => 'VALIDATION_ERROR', 'message' => 'Series title is required', 'field' => 'title'];
            return null;
        }

        try {
            $db->prepare("
                INSERT INTO event_series (tenant_id, title, description, created_by) VALUES (?, ?, ?, ?)
            ")->execute([$tenantId, trim($title), $description, $userId]);
            return (int)$db->lastInsertId();
        } catch (\Exception $e) {
            error_log("EventService::createSeries error: " . $e->getMessage());
            self::$errors[] = ['code' => 'SERVER_ERROR', 'message' => 'Failed to create series'];
            return null;
        }
    }

    /**
     * Link an event to a series
     */
    public static function linkToSeries(int $eventId, int $seriesId, int $userId): bool
    {
        self::$errors = [];

        $event = Event::find($eventId);
        if (!$event) {
            self::$errors[] = ['code' => 'NOT_FOUND', 'message' => 'Event not found'];
            return false;
        }

        if (!self::canModify($event, $userId)) {
            self::$errors[] = ['code' => 'FORBIDDEN', 'message' => 'You do not have permission to modify this event'];
            return false;
        }

        $tenantId = TenantContext::getId();
        $db = Database::getConnection();

        try {
            $db->prepare("UPDATE events SET series_id = ? WHERE id = ? AND tenant_id = ?")->execute([$seriesId, $eventId, $tenantId]);
            return true;
        } catch (\Exception $e) {
            error_log("EventService::linkToSeries error: " . $e->getMessage());
            self::$errors[] = ['code' => 'SERVER_ERROR', 'message' => 'Failed to link event to series'];
            return false;
        }
    }

    /**
     * Get all events in a series
     */
    public static function getSeriesEvents(int $seriesId): array
    {
        $db = Database::getConnection();
        $tenantId = TenantContext::getId();

        try {
            $stmt = $db->prepare("
                SELECT e.id, e.title, e.start_time, e.end_time, e.status, e.location,
                       (SELECT COUNT(*) FROM event_rsvps WHERE event_id = e.id AND status = 'going') as going_count
                FROM events e
                WHERE e.series_id = ? AND e.tenant_id = ?
                ORDER BY e.start_time ASC
            ");
            $stmt->execute([$seriesId, $tenantId]);
            $events = $stmt->fetchAll(\PDO::FETCH_ASSOC);

            $items = [];
            foreach ($events as $e) {
                $items[] = [
                    'id' => (int)$e['id'],
                    'title' => $e['title'],
                    'start_time' => $e['start_time'],
                    'end_time' => $e['end_time'],
                    'status' => $e['status'] ?? 'active',
                    'location' => $e['location'],
                    'going_count' => (int)($e['going_count'] ?? 0),
                ];
            }

            return $items;
        } catch (\Exception $e) {
            error_log("EventService::getSeriesEvents error: " . $e->getMessage());
            return [];
        }
    }

    /**
     * Get series info (for embedding in event detail)
     */
    public static function getSeriesInfo(int $seriesId): ?array
    {
        $db = Database::getConnection();
        $tenantId = TenantContext::getId();

        try {
            $stmt = $db->prepare("
                SELECT s.*, u.name as creator_name,
                       (SELECT COUNT(*) FROM events WHERE series_id = s.id AND tenant_id = ?) as event_count
                FROM event_series s
                JOIN users u ON s.created_by = u.id
                WHERE s.id = ? AND s.tenant_id = ?
            ");
            $stmt->execute([$tenantId, $seriesId, $tenantId]);
            $series = $stmt->fetch(\PDO::FETCH_ASSOC);

            if (!$series) {
                return null;
            }

            return [
                'id' => (int)$series['id'],
                'title' => $series['title'],
                'description' => $series['description'],
                'event_count' => (int)$series['event_count'],
                'creator' => $series['creator_name'],
            ];
        } catch (\Exception $e) {
            return null;
        }
    }

    /**
     * Get all series for the current tenant
     */
    public static function getAllSeries(array $filters = []): array
    {
        $db = Database::getConnection();
        $tenantId = TenantContext::getId();
        $limit = min($filters['limit'] ?? 20, 100);

        try {
            $stmt = $db->prepare("
                SELECT s.*, u.name as creator_name,
                       (SELECT COUNT(*) FROM events WHERE series_id = s.id AND tenant_id = ?) as event_count,
                       (SELECT MIN(start_time) FROM events WHERE series_id = s.id AND tenant_id = ? AND start_time >= NOW()) as next_event
                FROM event_series s
                JOIN users u ON s.created_by = u.id
                WHERE s.tenant_id = ?
                ORDER BY s.created_at DESC
                LIMIT " . ($limit + 1)
            );
            $stmt->execute([$tenantId, $tenantId, $tenantId]);
            $series = $stmt->fetchAll(\PDO::FETCH_ASSOC);

            $hasMore = count($series) > $limit;
            if ($hasMore) {
                array_pop($series);
            }

            $items = [];
            foreach ($series as $s) {
                $items[] = [
                    'id' => (int)$s['id'],
                    'title' => $s['title'],
                    'description' => $s['description'],
                    'event_count' => (int)$s['event_count'],
                    'next_event' => $s['next_event'],
                    'creator' => $s['creator_name'],
                    'created_at' => $s['created_at'],
                ];
            }

            return ['items' => $items, 'has_more' => $hasMore];
        } catch (\Exception $e) {
            error_log("EventService::getAllSeries error: " . $e->getMessage());
            return ['items' => [], 'has_more' => false];
        }
    }

    // =========================================================================
    // E1: RECURRING EVENTS
    // =========================================================================

    /**
     * Create a recurring event with recurrence rule
     *
     * @param int $userId
     * @param array $data Event data (same as create) + recurrence fields
     * @return array|null ['template_id' => int, 'occurrences' => int] or null on failure
     */
    public static function createRecurring(int $userId, array $data): ?array
    {
        self::$errors = [];

        // Validate recurrence
        $frequency = $data['recurrence_frequency'] ?? null;
        $validFrequencies = ['daily', 'weekly', 'monthly', 'yearly', 'custom'];
        if (!$frequency || !in_array($frequency, $validFrequencies)) {
            self::$errors[] = ['code' => 'VALIDATION_ERROR', 'message' => 'Valid recurrence frequency is required', 'field' => 'recurrence_frequency'];
            return null;
        }

        // Create the template event
        $data['_is_template'] = true;
        $templateId = self::create($userId, $data);
        if (!$templateId) {
            return null;
        }

        $tenantId = TenantContext::getId();
        $db = Database::getConnection();

        // Mark as recurring template
        $db->prepare("UPDATE events SET is_recurring_template = 1 WHERE id = ? AND tenant_id = ?")->execute([$templateId, $tenantId]);

        // Create recurrence rule
        $interval = max(1, (int)($data['recurrence_interval'] ?? 1));
        $daysOfWeek = $data['recurrence_days'] ?? null; // e.g., "1,3,5" for Mon,Wed,Fri
        $dayOfMonth = $data['recurrence_day_of_month'] ?? null;
        $endsType = $data['recurrence_ends_type'] ?? 'after_count';
        $endsAfterCount = $data['recurrence_ends_after_count'] ?? 10;
        $endsOnDate = $data['recurrence_ends_on_date'] ?? null;
        $rrule = $data['recurrence_rrule'] ?? null;

        try {
            $db->prepare("
                INSERT INTO event_recurrence_rules
                (event_id, tenant_id, frequency, interval_value, days_of_week, day_of_month, rrule, ends_type, ends_after_count, ends_on_date)
                VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?)
            ")->execute([
                $templateId, $tenantId, $frequency, $interval,
                $daysOfWeek, $dayOfMonth, $rrule, $endsType,
                $endsAfterCount, $endsOnDate
            ]);

            // Generate occurrences
            $occurrenceCount = self::generateOccurrences($templateId, $data);

            return [
                'template_id' => $templateId,
                'occurrences' => $occurrenceCount,
            ];
        } catch (\Exception $e) {
            error_log("EventService::createRecurring error: " . $e->getMessage());
            self::$errors[] = ['code' => 'SERVER_ERROR', 'message' => 'Failed to create recurring event'];
            return null;
        }
    }

    /**
     * Generate occurrence events from a recurrence template
     */
    private static function generateOccurrences(int $templateId, array $data): int
    {
        $db = Database::getConnection();
        $tenantId = TenantContext::getId();

        // Get recurrence rule
        $stmt = $db->prepare("SELECT * FROM event_recurrence_rules WHERE event_id = ? AND tenant_id = ?");
        $stmt->execute([$templateId, $tenantId]);
        $rule = $stmt->fetch(\PDO::FETCH_ASSOC);

        if (!$rule) {
            return 0;
        }

        // Get template event
        $template = Event::find($templateId);
        if (!$template) {
            return 0;
        }

        $startTime = new \DateTime($template['start_time']);
        $endTime = $template['end_time'] ? new \DateTime($template['end_time']) : null;
        $duration = $endTime ? $startTime->diff($endTime) : null;

        $frequency = $rule['frequency'];
        $interval = max(1, (int)$rule['interval_value']);
        $endsType = $rule['ends_type'];
        $maxOccurrences = $endsType === 'after_count' ? min((int)($rule['ends_after_count'] ?? 10), 52) : 52; // Cap at 52
        $endsOnDate = $rule['ends_on_date'] ? new \DateTime($rule['ends_on_date']) : null;
        $daysOfWeek = $rule['days_of_week'] ? explode(',', $rule['days_of_week']) : null;

        $occurrences = [];
        $current = clone $startTime;

        for ($i = 0; $i < $maxOccurrences; $i++) {
            // Advance to next occurrence
            switch ($frequency) {
                case 'daily':
                    $current->modify("+{$interval} days");
                    break;
                case 'weekly':
                    $current->modify("+{$interval} weeks");
                    break;
                case 'monthly':
                    $current->modify("+{$interval} months");
                    break;
                case 'yearly':
                    $current->modify("+{$interval} years");
                    break;
                default:
                    $current->modify("+{$interval} weeks"); // Default to weekly
                    break;
            }

            // Check end conditions
            if ($endsOnDate && $current > $endsOnDate) {
                break;
            }

            // Don't generate more than 1 year out
            $oneYearOut = new \DateTime('+1 year');
            if ($current > $oneYearOut) {
                break;
            }

            $occurrenceStart = clone $current;
            $occurrenceEnd = null;
            if ($duration) {
                $occurrenceEnd = clone $occurrenceStart;
                $occurrenceEnd->add($duration);
            }

            $occurrences[] = [
                'start' => $occurrenceStart->format('Y-m-d H:i:s'),
                'end' => $occurrenceEnd ? $occurrenceEnd->format('Y-m-d H:i:s') : null,
                'date' => $occurrenceStart->format('Y-m-d'),
            ];
        }

        // Create occurrence events
        $count = 0;
        foreach ($occurrences as $occ) {
            try {
                $occId = Event::create(
                    $tenantId,
                    (int)$template['user_id'],
                    $template['title'],
                    $template['description'] ?? '',
                    $template['location'] ?? '',
                    $occ['start'],
                    $occ['end'],
                    $template['group_id'] ?? null,
                    $template['category_id'] ?? null,
                    $template['latitude'] ?? null,
                    $template['longitude'] ?? null,
                    $template['federated_visibility'] ?? 'none'
                );

                // Link to parent template
                $db->prepare("
                    UPDATE events SET parent_event_id = ?, occurrence_date = ?, max_attendees = ?, series_id = ?
                    WHERE id = ? AND tenant_id = ?
                ")->execute([
                    $templateId,
                    $occ['date'],
                    $template['max_attendees'] ?? null,
                    $template['series_id'] ?? null,
                    $occId,
                    $tenantId,
                ]);

                $count++;
            } catch (\Exception $e) {
                error_log("Failed to generate occurrence: " . $e->getMessage());
            }
        }

        return $count;
    }

    /**
     * Update a single occurrence or all occurrences of a recurring event
     *
     * @param int $eventId The specific occurrence ID
     * @param int $userId
     * @param array $data
     * @param string $scope 'single' or 'all'
     * @return bool
     */
    public static function updateRecurring(int $eventId, int $userId, array $data, string $scope = 'single'): bool
    {
        self::$errors = [];

        if ($scope === 'single') {
            // Detach from parent (make independent) and update
            $tenantId = TenantContext::getId();
            Database::query("UPDATE events SET parent_event_id = NULL WHERE id = ? AND tenant_id = ?", [$eventId, $tenantId]);
            return self::update($eventId, $userId, $data);
        }

        // scope === 'all': update all future occurrences
        $event = Event::find($eventId);
        if (!$event) {
            self::$errors[] = ['code' => 'NOT_FOUND', 'message' => 'Event not found'];
            return false;
        }

        $parentId = $event['parent_event_id'] ?? $eventId;
        $tenantId = TenantContext::getId();
        $db = Database::getConnection();

        // Get all future occurrences of this parent
        $stmt = $db->prepare("
            SELECT id FROM events
            WHERE (parent_event_id = ? OR id = ?) AND tenant_id = ? AND start_time >= NOW()
        ");
        $stmt->execute([$parentId, $parentId, $tenantId]);
        $ids = $stmt->fetchAll(\PDO::FETCH_COLUMN);

        $updated = 0;
        foreach ($ids as $id) {
            if (self::update((int)$id, $userId, $data)) {
                $updated++;
            }
        }

        return $updated > 0;
    }
}
