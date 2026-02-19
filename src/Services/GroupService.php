<?php
// Copyright © 2024–2026 Jasper Ford
// SPDX-License-Identifier: AGPL-3.0-or-later
// Author: Jasper Ford
// See NOTICE file for attribution and acknowledgements.

namespace Nexus\Services;

use Nexus\Core\Database;
use Nexus\Core\TenantContext;
use Nexus\Models\Group;
use Nexus\Models\GroupDiscussion;
use Nexus\Models\GroupPost;
use Nexus\Models\User;
use Nexus\Models\Notification;

/**
 * GroupService - Business logic for groups
 *
 * This service extracts business logic from the Group model and GroupController
 * to be shared between HTML and API controllers.
 *
 * Key operations:
 * - Group CRUD with validation
 * - Membership management (join, leave, approve, reject)
 * - Member role management (promote, demote, remove)
 * - Discussion threads
 */
class GroupService
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
     * Get groups with cursor-based pagination
     *
     * @param array $filters [
     *   'type' => 'all'|'hubs'|'community' (default: all),
     *   'type_id' => int,
     *   'visibility' => 'public'|'private',
     *   'user_id' => int (groups user belongs to),
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
        $cursor = $filters['cursor'] ?? null;

        // Decode cursor (group ID)
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
                g.*,
                g.cached_member_count as member_count,
                u.name as owner_name,
                u.avatar_url as owner_avatar,
                gt.name as type_name,
                gt.icon as type_icon,
                gt.color as type_color
            FROM `groups` g
            JOIN users u ON g.owner_id = u.id
            LEFT JOIN group_types gt ON g.type_id = gt.id
            WHERE g.tenant_id = ?
        ";
        $params = [$tenantId];

        // Type filter (hubs vs community groups)
        if (!empty($filters['type'])) {
            $hubType = \Nexus\Models\GroupType::getHubType();
            $hubTypeId = $hubType ? $hubType['id'] : null;

            if ($filters['type'] === 'hubs' && $hubTypeId) {
                $sql .= " AND g.type_id = ?";
                $params[] = $hubTypeId;
            } elseif ($filters['type'] === 'community' && $hubTypeId) {
                $sql .= " AND (g.type_id IS NULL OR g.type_id != ?)";
                $params[] = $hubTypeId;
            }
        }

        // Specific type ID filter
        if (!empty($filters['type_id'])) {
            $sql .= " AND g.type_id = ?";
            $params[] = (int)$filters['type_id'];
        }

        // Visibility filter
        if (!empty($filters['visibility'])) {
            $sql .= " AND g.visibility = ?";
            $params[] = $filters['visibility'];
        }

        // User's groups filter
        if (!empty($filters['user_id'])) {
            $sql .= " AND g.id IN (SELECT group_id FROM group_members WHERE user_id = ? AND status = 'active')";
            $params[] = (int)$filters['user_id'];
        }

        // Search filter
        if (!empty($filters['search'])) {
            $sql .= " AND (g.name LIKE ? OR g.description LIKE ?)";
            $term = '%' . $filters['search'] . '%';
            $params[] = $term;
            $params[] = $term;
        } else {
            // When not searching, only show top-level groups
            $sql .= " AND (g.parent_id IS NULL OR g.parent_id = 0)";
        }

        // Cursor pagination
        if ($cursorId) {
            $sql .= " AND g.id < ?";
            $params[] = $cursorId;
        }

        // Order by member count (popularity) then creation date
        $sql .= " ORDER BY g.cached_member_count DESC, g.created_at DESC, g.id DESC";
        $sql .= " LIMIT " . ($limit + 1);

        $stmt = $db->prepare($sql);
        $stmt->execute($params);
        $groups = $stmt->fetchAll(\PDO::FETCH_ASSOC);

        // Check if there are more results
        $hasMore = count($groups) > $limit;
        if ($hasMore) {
            array_pop($groups);
        }

        // Format groups
        $items = [];
        $lastId = null;

        foreach ($groups as $group) {
            $lastId = $group['id'];
            $items[] = self::formatGroup($group);
        }

        return [
            'items' => $items,
            'cursor' => $hasMore && $lastId ? base64_encode((string)$lastId) : null,
            'has_more' => $hasMore,
        ];
    }

    /**
     * Get a single group by ID
     *
     * @param int $id
     * @param int|null $viewerId Optional user ID to include membership status
     * @return array|null
     */
    public static function getById(int $id, ?int $viewerId = null): ?array
    {
        $group = Group::findById($id);

        if (!$group) {
            return null;
        }

        $formatted = self::formatGroup($group, true);

        // Add viewer's membership status
        if ($viewerId) {
            $formatted['viewer_membership'] = self::getMembershipInfo($id, $viewerId);
        }

        // Add sub-groups if any
        $subGroups = Group::getSubGroups($id);
        if (!empty($subGroups)) {
            $formatted['sub_groups'] = array_map(function ($sg) {
                return [
                    'id' => (int)$sg['id'],
                    'name' => $sg['name'],
                    'member_count' => (int)($sg['member_count'] ?? $sg['cached_member_count'] ?? 0),
                ];
            }, $subGroups);
        }

        return $formatted;
    }

    /**
     * Format group data for API response
     */
    private static function formatGroup(array $group, bool $detailed = false): array
    {
        $formatted = [
            'id' => (int)$group['id'],
            'name' => $group['name'],
            'description' => $detailed ? $group['description'] : self::truncate($group['description'] ?? '', 200),
            'image_url' => $group['image_url'] ?? null,
            'cover_image_url' => $group['cover_image_url'] ?? null,
            'visibility' => $group['visibility'] ?? 'public',
            'location' => $group['location'] ?? null,
            'latitude' => isset($group['latitude']) ? (float)$group['latitude'] : null,
            'longitude' => isset($group['longitude']) ? (float)$group['longitude'] : null,
            'member_count' => (int)($group['member_count'] ?? $group['cached_member_count'] ?? 0),
            'owner' => [
                'id' => (int)$group['owner_id'],
                'name' => $group['owner_name'] ?? null,
                'avatar_url' => $group['owner_avatar'] ?? null,
            ],
            'type' => $group['type_id'] ? [
                'id' => (int)$group['type_id'],
                'name' => $group['type_name'] ?? null,
                'icon' => $group['type_icon'] ?? null,
                'color' => $group['type_color'] ?? null,
            ] : null,
            'is_featured' => (bool)($group['is_featured'] ?? false),
            'created_at' => $group['created_at'] ?? null,
        ];

        if ($detailed) {
            $formatted['parent_id'] = $group['parent_id'] ? (int)$group['parent_id'] : null;
            $formatted['federated_visibility'] = $group['federated_visibility'] ?? 'none';
        }

        return $formatted;
    }

    /**
     * Get membership info for a user in a group
     */
    private static function getMembershipInfo(int $groupId, int $userId): array
    {
        $db = Database::getConnection();
        $stmt = $db->prepare("SELECT status, role FROM group_members WHERE group_id = ? AND user_id = ?");
        $stmt->execute([$groupId, $userId]);
        $membership = $stmt->fetch(\PDO::FETCH_ASSOC);

        if (!$membership) {
            return ['status' => 'none', 'role' => null, 'is_admin' => false];
        }

        return [
            'status' => $membership['status'],
            'role' => $membership['role'],
            'is_admin' => in_array($membership['role'], ['owner', 'admin']),
        ];
    }

    /**
     * Validate group data
     */
    public static function validate(array $data, bool $isUpdate = false): bool
    {
        self::$errors = [];

        // Name validation
        if (!$isUpdate || array_key_exists('name', $data)) {
            $name = trim($data['name'] ?? '');
            if (empty($name)) {
                self::$errors[] = ['code' => 'VALIDATION_ERROR', 'message' => 'Name is required', 'field' => 'name'];
            } elseif (strlen($name) > 255) {
                self::$errors[] = ['code' => 'VALIDATION_ERROR', 'message' => 'Name must be 255 characters or less', 'field' => 'name'];
            }
        }

        // Description length
        if (isset($data['description']) && strlen($data['description']) > 10000) {
            self::$errors[] = ['code' => 'VALIDATION_ERROR', 'message' => 'Description must be 10000 characters or less', 'field' => 'description'];
        }

        // Visibility validation
        if (isset($data['visibility']) && !in_array($data['visibility'], ['public', 'private'])) {
            self::$errors[] = ['code' => 'VALIDATION_ERROR', 'message' => 'Visibility must be public or private', 'field' => 'visibility'];
        }

        // Coordinates validation
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

        return empty(self::$errors);
    }

    /**
     * Create a new group
     *
     * @param int $userId Creator user ID (becomes owner)
     * @param array $data Group data
     * @return int|null Group ID or null on failure
     */
    public static function create(int $userId, array $data): ?int
    {
        if (!self::validate($data, false)) {
            return null;
        }

        // Check if user can create this type of group
        $typeId = $data['type_id'] ?? null;
        if ($typeId && \Nexus\Models\GroupType::isHubType($typeId)) {
            if (!Group::canCreateHub($userId)) {
                self::$errors[] = ['code' => 'FORBIDDEN', 'message' => 'Only administrators can create hubs'];
                return null;
            }
        }

        try {
            $groupId = Group::create(
                $userId,
                trim($data['name']),
                $data['description'] ?? '',
                $data['image_url'] ?? '',
                $data['visibility'] ?? 'public',
                $data['location'] ?? '',
                $data['latitude'] ?? null,
                $data['longitude'] ?? null,
                $typeId,
                $data['federated_visibility'] ?? 'none'
            );

            // Creator automatically joins as owner
            Group::join($groupId, $userId);

            // Set creator as owner role
            Group::updateMemberRole($groupId, $userId, 'owner');

            // Gamification
            try {
                GamificationService::checkGroupBadges($userId, 'create');
            } catch (\Throwable $e) {
                error_log("Gamification group create error: " . $e->getMessage());
            }

            return (int)$groupId;
        } catch (\Exception $e) {
            error_log("GroupService::create error: " . $e->getMessage());
            self::$errors[] = ['code' => 'SERVER_ERROR', 'message' => 'Failed to create group'];
            return null;
        }
    }

    /**
     * Update an existing group
     */
    public static function update(int $id, int $userId, array $data): bool
    {
        self::$errors = [];

        $group = Group::findById($id);
        if (!$group) {
            self::$errors[] = ['code' => 'NOT_FOUND', 'message' => 'Group not found'];
            return false;
        }

        if (!self::canModify($id, $userId)) {
            self::$errors[] = ['code' => 'FORBIDDEN', 'message' => 'You do not have permission to edit this group'];
            return false;
        }

        if (!self::validate($data, true)) {
            return false;
        }

        try {
            $updates = [];
            $allowedFields = ['name', 'description', 'visibility', 'location', 'latitude', 'longitude', 'federated_visibility'];

            foreach ($allowedFields as $field) {
                if (array_key_exists($field, $data)) {
                    $updates[$field] = $data[$field];
                }
            }

            if (!empty($updates)) {
                Group::update($id, $updates);
            }

            return true;
        } catch (\Exception $e) {
            error_log("GroupService::update error: " . $e->getMessage());
            self::$errors[] = ['code' => 'SERVER_ERROR', 'message' => 'Failed to update group'];
            return false;
        }
    }

    /**
     * Delete a group
     */
    public static function delete(int $id, int $userId): bool
    {
        self::$errors = [];

        $group = Group::findById($id);
        if (!$group) {
            self::$errors[] = ['code' => 'NOT_FOUND', 'message' => 'Group not found'];
            return false;
        }

        // Only owner or platform admin can delete
        if ((int)$group['owner_id'] !== $userId && !self::isPlatformAdmin($userId)) {
            self::$errors[] = ['code' => 'FORBIDDEN', 'message' => 'Only the group owner can delete this group'];
            return false;
        }

        try {
            $db = Database::getConnection();

            // Delete group members
            $db->prepare("DELETE FROM group_members WHERE group_id = ?")->execute([$id]);

            // Delete discussions and posts
            $discussions = $db->prepare("SELECT id FROM group_discussions WHERE group_id = ?");
            $discussions->execute([$id]);
            foreach ($discussions->fetchAll(\PDO::FETCH_COLUMN) as $discId) {
                $db->prepare("DELETE FROM group_posts WHERE discussion_id = ?")->execute([$discId]);
            }
            $db->prepare("DELETE FROM group_discussions WHERE group_id = ?")->execute([$id]);

            // Delete the group — scoped by tenant
            $tenantId = TenantContext::getId();
            $db->prepare("DELETE FROM `groups` WHERE id = ? AND tenant_id = ?")->execute([$id, $tenantId]);

            return true;
        } catch (\Exception $e) {
            error_log("GroupService::delete error: " . $e->getMessage());
            self::$errors[] = ['code' => 'SERVER_ERROR', 'message' => 'Failed to delete group'];
            return false;
        }
    }

    /**
     * Join a group (or request to join if private)
     *
     * @return string|null The membership status ('active', 'pending') or null on failure
     */
    public static function join(int $groupId, int $userId): ?string
    {
        self::$errors = [];

        $group = Group::findById($groupId);
        if (!$group) {
            self::$errors[] = ['code' => 'NOT_FOUND', 'message' => 'Group not found'];
            return null;
        }

        // Check if already a member
        $existing = Group::getMembershipStatus($groupId, $userId);
        if ($existing === 'active') {
            self::$errors[] = ['code' => 'ALREADY_MEMBER', 'message' => 'You are already a member of this group'];
            return null;
        }
        if ($existing === 'pending') {
            self::$errors[] = ['code' => 'PENDING', 'message' => 'Your join request is pending approval'];
            return null;
        }

        try {
            $status = Group::join($groupId, $userId);

            // Gamification for active joins
            if ($status === 'active') {
                try {
                    GamificationService::checkGroupBadges($userId, 'join');
                } catch (\Throwable $e) {
                    error_log("Gamification group join error: " . $e->getMessage());
                }
            }

            return $status;
        } catch (\Exception $e) {
            error_log("GroupService::join error: " . $e->getMessage());
            self::$errors[] = ['code' => 'SERVER_ERROR', 'message' => 'Failed to join group'];
            return null;
        }
    }

    /**
     * Leave a group
     */
    public static function leave(int $groupId, int $userId): bool
    {
        self::$errors = [];

        $group = Group::findById($groupId);
        if (!$group) {
            self::$errors[] = ['code' => 'NOT_FOUND', 'message' => 'Group not found'];
            return false;
        }

        // Check if member
        $status = Group::getMembershipStatus($groupId, $userId);
        if (!$status || $status !== 'active') {
            self::$errors[] = ['code' => 'NOT_MEMBER', 'message' => 'You are not a member of this group'];
            return false;
        }

        // Check if sole admin
        if (self::isSoleAdmin($groupId, $userId)) {
            self::$errors[] = ['code' => 'SOLE_ADMIN', 'message' => 'You cannot leave as you are the only admin. Please promote another member first.'];
            return false;
        }

        try {
            Group::leave($groupId, $userId);
            return true;
        } catch (\Exception $e) {
            error_log("GroupService::leave error: " . $e->getMessage());
            self::$errors[] = ['code' => 'SERVER_ERROR', 'message' => 'Failed to leave group'];
            return false;
        }
    }

    /**
     * Get group members with cursor-based pagination
     */
    public static function getMembers(int $groupId, array $filters = []): array
    {
        $db = Database::getConnection();

        $limit = min($filters['limit'] ?? 20, 100);
        $role = $filters['role'] ?? null;
        $cursor = $filters['cursor'] ?? null;

        $cursorId = null;
        if ($cursor) {
            $decoded = base64_decode($cursor, true);
            if ($decoded && is_numeric($decoded)) {
                $cursorId = (int)$decoded;
            }
        }

        $sql = "
            SELECT gm.id as membership_id, gm.user_id, gm.role, gm.status, gm.created_at as joined_at,
                   u.name, u.first_name, u.last_name, u.avatar_url
            FROM group_members gm
            JOIN users u ON gm.user_id = u.id
            WHERE gm.group_id = ? AND gm.status = 'active'
        ";
        $params = [$groupId];

        if ($role) {
            $sql .= " AND gm.role = ?";
            $params[] = $role;
        }

        if ($cursorId) {
            $sql .= " AND gm.id > ?";
            $params[] = $cursorId;
        }

        $sql .= " ORDER BY FIELD(gm.role, 'owner', 'admin', 'member'), gm.id ASC";
        $sql .= " LIMIT " . ($limit + 1);

        $stmt = $db->prepare($sql);
        $stmt->execute($params);
        $members = $stmt->fetchAll(\PDO::FETCH_ASSOC);

        $hasMore = count($members) > $limit;
        if ($hasMore) {
            array_pop($members);
        }

        $items = [];
        $lastId = null;

        foreach ($members as $m) {
            $lastId = $m['membership_id'];
            $items[] = [
                'id' => (int)$m['user_id'],
                'name' => $m['name'] ?? trim(($m['first_name'] ?? '') . ' ' . ($m['last_name'] ?? '')),
                'avatar_url' => $m['avatar_url'],
                'role' => $m['role'],
                'joined_at' => $m['joined_at'],
            ];
        }

        return [
            'items' => $items,
            'cursor' => $hasMore && $lastId ? base64_encode((string)$lastId) : null,
            'has_more' => $hasMore,
        ];
    }

    /**
     * Update a member's role
     */
    public static function updateMemberRole(int $groupId, int $targetUserId, int $actingUserId, string $role): bool
    {
        self::$errors = [];

        if (!in_array($role, ['admin', 'member'])) {
            self::$errors[] = ['code' => 'VALIDATION_ERROR', 'message' => 'Invalid role', 'field' => 'role'];
            return false;
        }

        if (!self::canModify($groupId, $actingUserId)) {
            self::$errors[] = ['code' => 'FORBIDDEN', 'message' => 'You do not have permission to manage members'];
            return false;
        }

        // Check target is a member
        $status = Group::getMembershipStatus($groupId, $targetUserId);
        if ($status !== 'active') {
            self::$errors[] = ['code' => 'NOT_MEMBER', 'message' => 'User is not a member of this group'];
            return false;
        }

        // Can't change owner's role
        $group = Group::findById($groupId);
        if ((int)$group['owner_id'] === $targetUserId) {
            self::$errors[] = ['code' => 'FORBIDDEN', 'message' => 'Cannot change the owner\'s role'];
            return false;
        }

        try {
            Group::updateMemberRole($groupId, $targetUserId, $role);
            return true;
        } catch (\Exception $e) {
            error_log("GroupService::updateMemberRole error: " . $e->getMessage());
            self::$errors[] = ['code' => 'SERVER_ERROR', 'message' => 'Failed to update member role'];
            return false;
        }
    }

    /**
     * Remove a member from the group
     */
    public static function removeMember(int $groupId, int $targetUserId, int $actingUserId): bool
    {
        self::$errors = [];

        if (!self::canModify($groupId, $actingUserId)) {
            self::$errors[] = ['code' => 'FORBIDDEN', 'message' => 'You do not have permission to remove members'];
            return false;
        }

        // Can't remove the owner
        $group = Group::findById($groupId);
        if ((int)$group['owner_id'] === $targetUserId) {
            self::$errors[] = ['code' => 'FORBIDDEN', 'message' => 'Cannot remove the group owner'];
            return false;
        }

        // Can't remove yourself this way (use leave instead)
        if ($targetUserId === $actingUserId) {
            self::$errors[] = ['code' => 'VALIDATION_ERROR', 'message' => 'Use leave endpoint to remove yourself'];
            return false;
        }

        try {
            Group::leave($groupId, $targetUserId);
            return true;
        } catch (\Exception $e) {
            error_log("GroupService::removeMember error: " . $e->getMessage());
            self::$errors[] = ['code' => 'SERVER_ERROR', 'message' => 'Failed to remove member'];
            return false;
        }
    }

    /**
     * Get pending join requests (admin only)
     */
    public static function getPendingRequests(int $groupId, int $adminUserId): ?array
    {
        self::$errors = [];

        if (!self::canModify($groupId, $adminUserId)) {
            self::$errors[] = ['code' => 'FORBIDDEN', 'message' => 'You do not have permission to view join requests'];
            return null;
        }

        $pending = Group::getPendingMembers($groupId);

        return array_map(function ($p) {
            return [
                'id' => (int)$p['id'],
                'name' => $p['name'],
                'avatar_url' => $p['avatar_url'],
            ];
        }, $pending);
    }

    /**
     * Handle a join request (accept/reject)
     */
    public static function handleJoinRequest(int $groupId, int $requesterId, int $adminUserId, string $action): bool
    {
        self::$errors = [];

        if (!in_array($action, ['accept', 'reject'])) {
            self::$errors[] = ['code' => 'VALIDATION_ERROR', 'message' => 'Action must be accept or reject', 'field' => 'action'];
            return false;
        }

        if (!self::canModify($groupId, $adminUserId)) {
            self::$errors[] = ['code' => 'FORBIDDEN', 'message' => 'You do not have permission to handle join requests'];
            return false;
        }

        // Check requester has pending status
        $status = Group::getMembershipStatus($groupId, $requesterId);
        if ($status !== 'pending') {
            self::$errors[] = ['code' => 'NOT_FOUND', 'message' => 'No pending request found for this user'];
            return false;
        }

        try {
            if ($action === 'accept') {
                Group::updateMemberStatus($groupId, $requesterId, 'active');

                // Notify the user
                $group = Group::findById($groupId);
                Notification::create(
                    $requesterId,
                    "Your request to join {$group['name']} has been approved!",
                    "/groups/{$groupId}",
                    'group'
                );

                // Gamification
                try {
                    GamificationService::checkGroupBadges($requesterId, 'join');
                } catch (\Throwable $e) {
                    error_log("Gamification group join error: " . $e->getMessage());
                }
            } else {
                Group::leave($groupId, $requesterId);
            }

            return true;
        } catch (\Exception $e) {
            error_log("GroupService::handleJoinRequest error: " . $e->getMessage());
            self::$errors[] = ['code' => 'SERVER_ERROR', 'message' => 'Failed to process request'];
            return false;
        }
    }

    /**
     * Get discussions for a group
     */
    public static function getDiscussions(int $groupId, int $userId, array $filters = []): ?array
    {
        self::$errors = [];

        // Check membership
        if (!Group::isMember($groupId, $userId)) {
            self::$errors[] = ['code' => 'FORBIDDEN', 'message' => 'You must be a member to view discussions'];
            return null;
        }

        $limit = min($filters['limit'] ?? 20, 100);
        $cursor = $filters['cursor'] ?? null;

        $db = Database::getConnection();

        $cursorId = null;
        if ($cursor) {
            $decoded = base64_decode($cursor, true);
            if ($decoded && is_numeric($decoded)) {
                $cursorId = (int)$decoded;
            }
        }

        $sql = "
            SELECT gd.*, u.name as author_name, u.avatar_url as author_avatar,
                   (SELECT COUNT(*) FROM group_posts gp WHERE gp.discussion_id = gd.id) as reply_count,
                   (SELECT MAX(created_at) FROM group_posts gp WHERE gp.discussion_id = gd.id) as last_reply_at
            FROM group_discussions gd
            JOIN users u ON gd.user_id = u.id
            WHERE gd.group_id = ?
        ";
        $params = [$groupId];

        if ($cursorId) {
            $sql .= " AND gd.id < ?";
            $params[] = $cursorId;
        }

        $sql .= " ORDER BY gd.is_pinned DESC, COALESCE((SELECT MAX(created_at) FROM group_posts WHERE discussion_id = gd.id), gd.created_at) DESC, gd.id DESC";
        $sql .= " LIMIT " . ($limit + 1);

        $stmt = $db->prepare($sql);
        $stmt->execute($params);
        $discussions = $stmt->fetchAll(\PDO::FETCH_ASSOC);

        $hasMore = count($discussions) > $limit;
        if ($hasMore) {
            array_pop($discussions);
        }

        $items = [];
        $lastId = null;

        foreach ($discussions as $d) {
            $lastId = $d['id'];
            $items[] = [
                'id' => (int)$d['id'],
                'title' => $d['title'],
                'author' => [
                    'id' => (int)$d['user_id'],
                    'name' => $d['author_name'],
                    'avatar_url' => $d['author_avatar'],
                ],
                'reply_count' => (int)$d['reply_count'],
                'is_pinned' => (bool)($d['is_pinned'] ?? false),
                'created_at' => $d['created_at'],
                'last_reply_at' => $d['last_reply_at'],
            ];
        }

        return [
            'items' => $items,
            'cursor' => $hasMore && $lastId ? base64_encode((string)$lastId) : null,
            'has_more' => $hasMore,
        ];
    }

    /**
     * Create a discussion
     */
    public static function createDiscussion(int $groupId, int $userId, array $data): ?int
    {
        self::$errors = [];

        if (!Group::isMember($groupId, $userId)) {
            self::$errors[] = ['code' => 'FORBIDDEN', 'message' => 'You must be a member to create discussions'];
            return null;
        }

        $title = trim($data['title'] ?? '');
        $content = trim($data['content'] ?? '');

        if (empty($title)) {
            self::$errors[] = ['code' => 'VALIDATION_ERROR', 'message' => 'Title is required', 'field' => 'title'];
            return null;
        }

        if (empty($content)) {
            self::$errors[] = ['code' => 'VALIDATION_ERROR', 'message' => 'Content is required', 'field' => 'content'];
            return null;
        }

        try {
            $discussionId = GroupDiscussion::create($groupId, $userId, $title);
            GroupPost::create($discussionId, $userId, $content);

            return (int)$discussionId;
        } catch (\Exception $e) {
            error_log("GroupService::createDiscussion error: " . $e->getMessage());
            self::$errors[] = ['code' => 'SERVER_ERROR', 'message' => 'Failed to create discussion'];
            return null;
        }
    }

    /**
     * Get messages in a discussion
     */
    public static function getDiscussionMessages(int $groupId, int $discussionId, int $userId, array $filters = []): ?array
    {
        self::$errors = [];

        if (!Group::isMember($groupId, $userId)) {
            self::$errors[] = ['code' => 'FORBIDDEN', 'message' => 'You must be a member to view discussions'];
            return null;
        }

        // Verify discussion belongs to group
        $discussion = GroupDiscussion::findById($discussionId);
        if (!$discussion || (int)$discussion['group_id'] !== $groupId) {
            self::$errors[] = ['code' => 'NOT_FOUND', 'message' => 'Discussion not found'];
            return null;
        }

        $limit = min($filters['limit'] ?? 50, 100);
        $cursor = $filters['cursor'] ?? null;

        $db = Database::getConnection();

        $cursorId = null;
        if ($cursor) {
            $decoded = base64_decode($cursor, true);
            if ($decoded && is_numeric($decoded)) {
                $cursorId = (int)$decoded;
            }
        }

        $sql = "
            SELECT gp.*, u.name as author_name, u.avatar_url as author_avatar
            FROM group_posts gp
            JOIN users u ON gp.user_id = u.id
            WHERE gp.discussion_id = ?
        ";
        $params = [$discussionId];

        if ($cursorId) {
            $sql .= " AND gp.id > ?";
            $params[] = $cursorId;
        }

        $sql .= " ORDER BY gp.id ASC LIMIT " . ($limit + 1);

        $stmt = $db->prepare($sql);
        $stmt->execute($params);
        $posts = $stmt->fetchAll(\PDO::FETCH_ASSOC);

        $hasMore = count($posts) > $limit;
        if ($hasMore) {
            array_pop($posts);
        }

        $items = [];
        $lastId = null;

        foreach ($posts as $p) {
            $lastId = $p['id'];
            $items[] = [
                'id' => (int)$p['id'],
                'content' => $p['content'],
                'author' => [
                    'id' => (int)$p['user_id'],
                    'name' => $p['author_name'],
                    'avatar_url' => $p['author_avatar'],
                ],
                'is_own' => (int)$p['user_id'] === $userId,
                'created_at' => $p['created_at'],
            ];
        }

        return [
            'discussion' => [
                'id' => (int)$discussion['id'],
                'title' => $discussion['title'],
                'author' => [
                    'id' => (int)$discussion['user_id'],
                    'name' => $discussion['author_name'],
                    'avatar_url' => $discussion['author_avatar'],
                ],
                'created_at' => $discussion['created_at'],
            ],
            'items' => $items,
            'cursor' => $hasMore && $lastId ? base64_encode((string)$lastId) : null,
            'has_more' => $hasMore,
        ];
    }

    /**
     * Post to a discussion
     */
    public static function postToDiscussion(int $groupId, int $discussionId, int $userId, array $data): ?int
    {
        self::$errors = [];

        if (!Group::isMember($groupId, $userId)) {
            self::$errors[] = ['code' => 'FORBIDDEN', 'message' => 'You must be a member to post'];
            return null;
        }

        // Verify discussion belongs to group
        $discussion = GroupDiscussion::findById($discussionId);
        if (!$discussion || (int)$discussion['group_id'] !== $groupId) {
            self::$errors[] = ['code' => 'NOT_FOUND', 'message' => 'Discussion not found'];
            return null;
        }

        $content = trim($data['content'] ?? '');
        if (empty($content)) {
            self::$errors[] = ['code' => 'VALIDATION_ERROR', 'message' => 'Content is required', 'field' => 'content'];
            return null;
        }

        try {
            $postId = GroupPost::create($discussionId, $userId, $content);
            return (int)$postId;
        } catch (\Exception $e) {
            error_log("GroupService::postToDiscussion error: " . $e->getMessage());
            self::$errors[] = ['code' => 'SERVER_ERROR', 'message' => 'Failed to post'];
            return null;
        }
    }

    /**
     * Update group image
     */
    public static function updateImage(int $groupId, int $userId, string $imageUrl, string $type = 'avatar'): bool
    {
        self::$errors = [];

        if (!self::canModify($groupId, $userId)) {
            self::$errors[] = ['code' => 'FORBIDDEN', 'message' => 'You do not have permission to modify this group'];
            return false;
        }

        try {
            $field = $type === 'cover' ? 'cover_image_url' : 'image_url';
            Group::update($groupId, [$field => $imageUrl]);
            return true;
        } catch (\Exception $e) {
            error_log("GroupService::updateImage error: " . $e->getMessage());
            self::$errors[] = ['code' => 'SERVER_ERROR', 'message' => 'Failed to update image'];
            return false;
        }
    }

    /**
     * Check if user can modify group (is admin)
     */
    public static function canModify(int $groupId, int $userId): bool
    {
        return Group::isAdmin($groupId, $userId);
    }

    /**
     * Check if user is sole admin of group
     */
    private static function isSoleAdmin(int $groupId, int $userId): bool
    {
        $db = Database::getConnection();

        // Check if user is admin
        $stmt = $db->prepare("SELECT role FROM group_members WHERE group_id = ? AND user_id = ? AND status = 'active'");
        $stmt->execute([$groupId, $userId]);
        $role = $stmt->fetchColumn();

        if (!in_array($role, ['owner', 'admin'])) {
            return false;
        }

        // Count other admins
        $stmt = $db->prepare("SELECT COUNT(*) FROM group_members WHERE group_id = ? AND user_id != ? AND role IN ('owner', 'admin') AND status = 'active'");
        $stmt->execute([$groupId, $userId]);
        $otherAdmins = (int)$stmt->fetchColumn();

        return $otherAdmins === 0;
    }

    /**
     * Check if user is platform admin
     */
    private static function isPlatformAdmin(int $userId): bool
    {
        $user = User::findById($userId);
        return $user && in_array($user['role'] ?? '', ['admin', 'super_admin', 'god']);
    }

    /**
     * Truncate text
     */
    private static function truncate(string $text, int $length): string
    {
        if (mb_strlen($text) <= $length) {
            return $text;
        }
        return mb_substr($text, 0, $length - 3) . '...';
    }
}
