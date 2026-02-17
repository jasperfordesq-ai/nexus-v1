<?php

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
 * - RSVP management
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
            $formatted['user_rsvp'] = EventRsvp::getUserStatus($id, $userId);
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
        $formatted = [
            'id' => (int)$event['id'],
            'title' => $event['title'],
            'description' => $detailed ? $event['description'] : self::truncate($event['description'] ?? '', 200),
            'location' => $event['location'],
            'latitude' => $event['latitude'] ? (float)$event['latitude'] : null,
            'longitude' => $event['longitude'] ? (float)$event['longitude'] : null,
            'start_time' => $event['start_time'],
            'end_time' => $event['end_time'],
            'cover_image' => $event['cover_image'] ?? null,
            'organizer' => [
                'id' => (int)$event['user_id'],
                'name' => $event['organizer_name'] ?? trim(($event['organizer_first_name'] ?? '') . ' ' . ($event['organizer_last_name'] ?? '')),
                'avatar_url' => $event['organizer_avatar'] ?? null,
            ],
            'category' => $event['category_id'] ? [
                'id' => (int)$event['category_id'],
                'name' => $event['category_name'] ?? null,
                'color' => $event['category_color'] ?? null,
            ] : null,
            'group_id' => $event['group_id'] ? (int)$event['group_id'] : null,
            'rsvp_counts' => [
                'going' => (int)($event['going_count'] ?? 0),
                'interested' => (int)($event['interested_count'] ?? 0),
            ],
            'created_at' => $event['created_at'] ?? null,
        ];

        // Add detailed fields
        if ($detailed) {
            $formatted['federated_visibility'] = $event['federated_visibility'] ?? 'none';
            $formatted['sdg_goals'] = $event['sdg_goals'] ? json_decode($event['sdg_goals'], true) : [];
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

            // Handle SDG goals
            if (!empty($data['sdg_goals']) && is_array($data['sdg_goals'])) {
                $goals = array_map('intval', $data['sdg_goals']);
                $json = json_encode($goals);
                Database::query("UPDATE events SET sdg_goals = ? WHERE id = ?", [$json, $eventId]);
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

            // Handle SDG goals update
            if (array_key_exists('sdg_goals', $data)) {
                $goals = is_array($data['sdg_goals']) ? array_map('intval', $data['sdg_goals']) : [];
                $json = json_encode($goals);
                Database::query("UPDATE events SET sdg_goals = ? WHERE id = ?", [$json, $id]);
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
            return true;
        } catch (\Exception $e) {
            error_log("EventService::delete error: " . $e->getMessage());
            self::$errors[] = ['code' => 'SERVER_ERROR', 'message' => 'Failed to delete event'];
            return false;
        }
    }

    /**
     * Set RSVP status for an event
     *
     * @param int $eventId
     * @param int $userId
     * @param string $status 'going', 'interested', 'not_going'
     * @return bool
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

        // Check event exists
        $event = Event::find($eventId);
        if (!$event) {
            self::$errors[] = ['code' => 'NOT_FOUND', 'message' => 'Event not found'];
            return false;
        }

        try {
            EventRsvp::rsvp($eventId, $userId, $status);

            // Gamification for "going"
            if ($status === 'going') {
                try {
                    GamificationService::checkEventBadges($userId, 'attend');
                } catch (\Throwable $e) {
                    error_log("Gamification event attend error: " . $e->getMessage());
                }
            }

            return true;
        } catch (\Exception $e) {
            error_log("EventService::rsvp error: " . $e->getMessage());
            self::$errors[] = ['code' => 'SERVER_ERROR', 'message' => 'Failed to update RSVP'];
            return false;
        }
    }

    /**
     * Remove RSVP for an event
     *
     * @param int $eventId
     * @param int $userId
     * @return bool
     */
    public static function removeRsvp(int $eventId, int $userId): bool
    {
        self::$errors = [];

        $db = Database::getConnection();

        try {
            $stmt = $db->prepare("DELETE FROM event_rsvps WHERE event_id = ? AND user_id = ?");
            $stmt->execute([$eventId, $userId]);
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
                'avatar_url' => $att['avatar_url'],
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
            Database::query("UPDATE events SET cover_image = ? WHERE id = ?", [$imageUrl, $eventId]);
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
}
