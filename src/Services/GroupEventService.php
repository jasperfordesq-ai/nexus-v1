<?php
// Copyright © 2024–2026 Jasper Ford
// SPDX-License-Identifier: AGPL-3.0-or-later
// Author: Jasper Ford
// See NOTICE file for attribution and acknowledgements.

namespace Nexus\Services;

use Nexus\Core\Database;
use Nexus\Core\TenantContext;
use Nexus\Models\Group;

/**
 * GroupEventService - Events scoped to groups
 *
 * Groups can create events linked via the group_id column on the events table.
 * Only group members can RSVP to group events.
 * Group admins manage (create/update/delete) group events.
 */
class GroupEventService
{
    private static array $errors = [];

    public static function getErrors(): array
    {
        return self::$errors;
    }

    /**
     * List events for a group
     *
     * @param int $groupId
     * @param int $userId Requesting user
     * @param array $filters ['cursor', 'limit', 'upcoming_only']
     * @return array|null Paginated events or null on error
     */
    public static function listEvents(int $groupId, int $userId, array $filters = []): ?array
    {
        self::$errors = [];

        // Public groups: anyone can see events. Private groups: members only.
        $group = Group::findById($groupId);
        if (!$group) {
            self::$errors[] = ['code' => 'NOT_FOUND', 'message' => 'Group not found'];
            return null;
        }

        if ($group['visibility'] === 'private' && !Group::isMember($groupId, $userId)) {
            self::$errors[] = ['code' => 'FORBIDDEN', 'message' => 'You must be a member to view events in this private group'];
            return null;
        }

        $tenantId = TenantContext::getId();
        $limit = min($filters['limit'] ?? 20, 100);
        $upcomingOnly = $filters['upcoming_only'] ?? true;
        $cursor = $filters['cursor'] ?? null;

        $cursorId = null;
        if ($cursor) {
            $decoded = base64_decode($cursor, true);
            if ($decoded && is_numeric($decoded)) {
                $cursorId = (int)$decoded;
            }
        }

        $sql = "
            SELECT e.*, u.name as organizer_name, u.avatar_url as organizer_avatar,
                   (SELECT COUNT(*) FROM event_rsvps WHERE event_id = e.id AND status = 'going') as attendee_count
            FROM events e
            JOIN users u ON e.user_id = u.id
            WHERE e.group_id = ? AND e.tenant_id = ?
        ";
        $params = [$groupId, $tenantId];

        if ($upcomingOnly) {
            $sql .= " AND e.start_time >= NOW()";
        }

        if ($cursorId) {
            $sql .= " AND e.id > ?";
            $params[] = $cursorId;
        }

        $sql .= " ORDER BY e.start_time ASC, e.id ASC LIMIT " . ($limit + 1);

        $db = Database::getConnection();
        $stmt = $db->prepare($sql);
        $stmt->execute($params);
        $events = $stmt->fetchAll(\PDO::FETCH_ASSOC);

        $hasMore = count($events) > $limit;
        if ($hasMore) {
            array_pop($events);
        }

        $items = [];
        $lastId = null;

        foreach ($events as $e) {
            $lastId = $e['id'];
            $items[] = self::formatEvent($e, $userId);
        }

        return [
            'items' => $items,
            'cursor' => $hasMore && $lastId ? base64_encode((string)$lastId) : null,
            'has_more' => $hasMore,
        ];
    }

    /**
     * Create a group event (group admin only)
     */
    public static function createEvent(int $groupId, int $userId, array $data): ?array
    {
        self::$errors = [];

        if (!Group::isAdmin($groupId, $userId)) {
            self::$errors[] = ['code' => 'FORBIDDEN', 'message' => 'Only group admins can create group events'];
            return null;
        }

        $title = trim($data['title'] ?? '');
        $description = trim($data['description'] ?? '');
        $startTime = $data['start_time'] ?? null;
        $endTime = $data['end_time'] ?? null;
        $location = trim($data['location'] ?? '');

        if (empty($title)) {
            self::$errors[] = ['code' => 'VALIDATION_ERROR', 'message' => 'Title is required', 'field' => 'title'];
            return null;
        }
        if (empty($startTime)) {
            self::$errors[] = ['code' => 'VALIDATION_ERROR', 'message' => 'Start time is required', 'field' => 'start_time'];
            return null;
        }

        $tenantId = TenantContext::getId();

        try {
            $db = Database::getConnection();
            $stmt = $db->prepare("
                INSERT INTO events (tenant_id, user_id, group_id, title, description, start_time, end_time, location, created_at)
                VALUES (?, ?, ?, ?, ?, ?, ?, ?, NOW())
            ");
            $stmt->execute([
                $tenantId,
                $userId,
                $groupId,
                $title,
                $description,
                date('Y-m-d H:i:s', strtotime($startTime)),
                $endTime ? date('Y-m-d H:i:s', strtotime($endTime)) : null,
                $location,
            ]);
            $eventId = (int)$db->lastInsertId();

            // Fetch and return
            $eventStmt = $db->prepare("
                SELECT e.*, u.name as organizer_name, u.avatar_url as organizer_avatar
                FROM events e
                JOIN users u ON e.user_id = u.id
                WHERE e.id = ? AND e.tenant_id = ?
            ");
            $eventStmt->execute([$eventId, $tenantId]);
            $event = $eventStmt->fetch(\PDO::FETCH_ASSOC);

            return $event ? self::formatEvent($event, $userId) : null;
        } catch (\Exception $e) {
            error_log("GroupEventService::createEvent error: " . $e->getMessage());
            self::$errors[] = ['code' => 'SERVER_ERROR', 'message' => 'Failed to create event'];
            return null;
        }
    }

    /**
     * RSVP to a group event (members only)
     */
    public static function rsvp(int $groupId, int $eventId, int $userId, string $status): ?array
    {
        self::$errors = [];

        if (!in_array($status, ['going', 'interested', 'not_going'])) {
            self::$errors[] = ['code' => 'VALIDATION_ERROR', 'message' => 'Status must be going, interested, or not_going', 'field' => 'status'];
            return null;
        }

        if (!Group::isMember($groupId, $userId)) {
            self::$errors[] = ['code' => 'FORBIDDEN', 'message' => 'Only group members can RSVP to group events'];
            return null;
        }

        $tenantId = TenantContext::getId();
        $db = Database::getConnection();

        // Verify event belongs to this group
        $eventStmt = $db->prepare("SELECT id FROM events WHERE id = ? AND group_id = ? AND tenant_id = ?");
        $eventStmt->execute([$eventId, $groupId, $tenantId]);
        if (!$eventStmt->fetch()) {
            self::$errors[] = ['code' => 'NOT_FOUND', 'message' => 'Event not found in this group'];
            return null;
        }

        try {
            // Upsert RSVP
            $db->prepare("
                INSERT INTO event_rsvps (event_id, user_id, status, created_at)
                VALUES (?, ?, ?, NOW())
                ON DUPLICATE KEY UPDATE status = VALUES(status), created_at = NOW()
            ")->execute([$eventId, $userId, $status]);

            return [
                'event_id' => $eventId,
                'user_id' => $userId,
                'status' => $status,
            ];
        } catch (\Exception $e) {
            error_log("GroupEventService::rsvp error: " . $e->getMessage());
            self::$errors[] = ['code' => 'SERVER_ERROR', 'message' => 'Failed to RSVP'];
            return null;
        }
    }

    /**
     * Delete a group event (group admin only)
     */
    public static function deleteEvent(int $groupId, int $eventId, int $userId): bool
    {
        self::$errors = [];

        if (!Group::isAdmin($groupId, $userId)) {
            self::$errors[] = ['code' => 'FORBIDDEN', 'message' => 'Only group admins can delete group events'];
            return false;
        }

        $tenantId = TenantContext::getId();

        try {
            $db = Database::getConnection();

            // Delete RSVPs first
            $db->prepare("DELETE FROM event_rsvps WHERE event_id = ?")->execute([$eventId]);

            // Delete event (scoped by group and tenant)
            $stmt = $db->prepare("DELETE FROM events WHERE id = ? AND group_id = ? AND tenant_id = ?");
            $stmt->execute([$eventId, $groupId, $tenantId]);

            if ($stmt->rowCount() === 0) {
                self::$errors[] = ['code' => 'NOT_FOUND', 'message' => 'Event not found in this group'];
                return false;
            }

            return true;
        } catch (\Exception $e) {
            error_log("GroupEventService::deleteEvent error: " . $e->getMessage());
            self::$errors[] = ['code' => 'SERVER_ERROR', 'message' => 'Failed to delete event'];
            return false;
        }
    }

    /**
     * Format event for API response
     */
    private static function formatEvent(array $e, int $viewerId): array
    {
        // Check viewer's RSVP status
        $rsvpStatus = null;
        try {
            $db = Database::getConnection();
            $stmt = $db->prepare("SELECT status FROM event_rsvps WHERE event_id = ? AND user_id = ?");
            $stmt->execute([$e['id'], $viewerId]);
            $rsvp = $stmt->fetch(\PDO::FETCH_ASSOC);
            $rsvpStatus = $rsvp ? $rsvp['status'] : null;
        } catch (\Exception $ex) {
            // Ignore
        }

        return [
            'id' => (int)$e['id'],
            'title' => $e['title'],
            'description' => $e['description'] ?? null,
            'start_time' => $e['start_time'],
            'end_time' => $e['end_time'] ?? null,
            'location' => $e['location'] ?? null,
            'cover_image' => $e['cover_image'] ?? null,
            'group_id' => (int)($e['group_id'] ?? 0),
            'attendee_count' => (int)($e['attendee_count'] ?? 0),
            'organizer' => [
                'id' => (int)$e['user_id'],
                'name' => $e['organizer_name'] ?? null,
                'avatar_url' => $e['organizer_avatar'] ?? null,
            ],
            'viewer_rsvp' => $rsvpStatus,
            'created_at' => $e['created_at'],
        ];
    }
}
