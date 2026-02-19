<?php
// Copyright © 2024–2026 Jasper Ford
// SPDX-License-Identifier: AGPL-3.0-or-later
// Author: Jasper Ford
// See NOTICE file for attribution and acknowledgements.

namespace Nexus\Models;

use Nexus\Core\Database;
use Nexus\Core\TenantContext;

class Group
{
    public static function create($ownerId, $name, $description, $imageUrl = '', $visibility = 'public', $location = '', $latitude = null, $longitude = null, $typeId = null, $federatedVisibility = 'none')
    {
        // Validate federated_visibility value
        $validVisibilities = ['none', 'listed', 'joinable'];
        if (!in_array($federatedVisibility, $validVisibilities)) {
            $federatedVisibility = 'none';
        }

        $tenantId = TenantContext::getId();
        $sql = "INSERT INTO `groups` (tenant_id, owner_id, name, description, image_url, visibility, location, latitude, longitude, type_id, federated_visibility) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)";
        Database::query($sql, [$tenantId, $ownerId, $name, $description, $imageUrl, $visibility, $location, $latitude, $longitude, $typeId, $federatedVisibility]);
        return Database::getInstance()->lastInsertId();
    }

    public static function update($id, $data)
    {
        $allowed = ['name', 'description', 'image_url', 'cover_image_url', 'visibility', 'location', 'latitude', 'longitude', 'type_id', 'is_featured', 'federated_visibility'];
        // Fields that should not be overwritten with empty values (unless explicitly cleared)
        $preserveIfEmpty = ['image_url', 'cover_image_url'];
        $set = [];
        $params = [];

        foreach ($data as $key => $value) {
            if (!in_array($key, $allowed)) {
                continue;
            }

            // Data Loss Prevention: Don't overwrite image URLs with empty values
            // Exception: '__CLEAR__' is a special marker to intentionally clear images
            if (in_array($key, $preserveIfEmpty)) {
                if ($value === '__CLEAR__') {
                    $value = null; // Intentional clearing
                } elseif ($value === '' || $value === null) {
                    continue; // Skip accidental empty values
                }
            }

            $set[] = "`$key` = ?";
            $params[] = $value;
        }

        if (empty($set)) return false;

        $params[] = $id;
        $sql = "UPDATE `groups` SET " . implode(', ', $set) . " WHERE id = ?";
        return Database::query($sql, $params);
    }

    public static function findById($id)
    {
        $sql = "SELECT g.*, u.name as owner_name, gt.name as type_name, gt.icon as type_icon, gt.color as type_color
                FROM `groups` g
                JOIN users u ON g.owner_id = u.id
                LEFT JOIN group_types gt ON g.type_id = gt.id
                WHERE g.id = ?";
        return Database::query($sql, [$id])->fetch();
    }

    public static function all($search = null, $typeId = null)
    {
        $tenantId = TenantContext::getId();
        // OPTIMIZED: Use cached_member_count instead of COUNT()
        $sql = "SELECT g.*, g.cached_member_count as member_count, gt.name as type_name, gt.icon as type_icon, gt.color as type_color
                FROM `groups` g
                LEFT JOIN group_types gt ON g.type_id = gt.id
                WHERE g.tenant_id = ?";

        $params = [$tenantId];

        if ($search) {
            // When searching, include sub-groups in results
            $sql .= " AND (g.name LIKE ? OR g.description LIKE ?)";
            $params[] = "%$search%";
            $params[] = "%$search%";
        } else {
            // When browsing, only show top-level groups
            $sql .= " AND (g.parent_id IS NULL OR g.parent_id = 0)";
        }

        if ($typeId) {
            $sql .= " AND g.type_id = ?";
            $params[] = $typeId;
        }

        $sql .= " ORDER BY g.cached_member_count DESC, g.created_at DESC";
        return Database::query($sql, $params)->fetchAll();
    }

    public static function getFeatured($limit = 3)
    {
        $tenantId = TenantContext::getId();
        $limit = (int) $limit; // Safety cast

        // OPTIMIZED QUERY: Uses cached_member_count and has_children flag
        // This eliminates expensive JOINs and COUNT() aggregation
        // Performance: 101ms -> <10ms (90-95% improvement)
        $sql = "SELECT g.*, g.cached_member_count as member_count
                FROM `groups` g
                WHERE g.tenant_id = ?
                AND g.has_children = FALSE
                ORDER BY g.cached_member_count DESC, g.name ASC
                LIMIT $limit";

        return Database::query($sql, [$tenantId])->fetchAll();
    }

    public static function join($groupId, $userId)
    {
        // 1. Check if already member
        $sql = "SELECT id, status FROM group_members WHERE group_id = ? AND user_id = ?";
        $existing = Database::query($sql, [$groupId, $userId])->fetch();
        $status = $existing ? $existing['status'] : null;

        if (!$existing) {
            // New Member: Determine status based on privacy
            $group = self::findById($groupId);

            // Check if User is Site Admin
            $userRole = Database::query("SELECT role FROM users WHERE id = ?", [$userId])->fetchColumn();

            // LOGGING FOR DEBUG
            error_log("Group::join - User ID: $userId, Role: $userRole");

            $isSiteAdmin = in_array($userRole, ['super_admin', 'admin', 'tenant_admin']);

            if ($isSiteAdmin) {
                // Auto-Promote Site Admin to Group Organizer
                $status = 'active';
                $role = 'admin';
            } else {
                $status = ($group['visibility'] === 'private') ? 'pending' : 'active';
                $role = 'member';
            }

            $sql = "INSERT INTO group_members (group_id, user_id, status, role) VALUES (?, ?, ?, ?)";
            Database::query($sql, [$groupId, $userId, $status, $role]);

            // Notify group owner/admins when someone requests to join a private group
            if ($status === 'pending') {
                self::notifyOrganizersOfJoinRequest($groupId, $userId, $group);
            }
        }
        // 2. Recursive Upward Inheritance
        $activeStatuses = ['active', 'owner', 'admin'];
        if (in_array($status, $activeStatuses)) {
            $parent = Database::query("SELECT parent_id FROM `groups` WHERE id = ?", [$groupId])->fetch();
            if ($parent && !empty($parent['parent_id'])) {
                self::join($parent['parent_id'], $userId);
            }
        }
        return $status;
    }

    public static function leave($groupId, $userId)
    {
        $sql = "DELETE FROM group_members WHERE group_id = ? AND user_id = ?";
        Database::query($sql, [$groupId, $userId]);
        return true;
    }

    public static function getMembers($groupId)
    {
        $sql = "SELECT u.id, u.name, u.avatar_url, gm.role 
                FROM group_members gm 
                JOIN users u ON gm.user_id = u.id 
                WHERE gm.group_id = ? AND gm.status = 'active'
                ORDER BY FIELD(gm.role, 'owner', 'admin', 'member'), u.name ASC";
        return Database::query($sql, [$groupId])->fetchAll();
    }

    public static function getSubGroups($parentId)
    {
        // Assumes parent_id column exists (added via migration)
        try {
            // OPTIMIZED: Use cached_member_count instead of COUNT()
            $sql = "SELECT g.*, g.cached_member_count as member_count
                    FROM `groups` g
                    WHERE g.parent_id = ?
                    ORDER BY g.name ASC";
            return Database::query($sql, [$parentId])->fetchAll();
        } catch (\Exception $e) {
            return []; // Return empty if column doesn't exist yet
        }
    }

    public static function isMember($groupId, $userId)
    {
        $sql = "SELECT id FROM group_members WHERE group_id = ? AND user_id = ? AND status = 'active'";
        return (bool) Database::query($sql, [$groupId, $userId])->fetch();
    }

    public static function getMembershipStatus($groupId, $userId)
    {
        $sql = "SELECT status FROM group_members WHERE group_id = ? AND user_id = ?";
        $res = Database::query($sql, [$groupId, $userId])->fetch();
        return $res ? $res['status'] : null;
    }

    public static function getPendingMembers($groupId)
    {
        $sql = "SELECT u.id, u.name, u.avatar_url
                FROM group_members gm
                JOIN users u ON gm.user_id = u.id
                WHERE gm.group_id = ? AND gm.status = 'pending'";
        return Database::query($sql, [$groupId])->fetchAll();
    }

    public static function getInvitedMembers($groupId)
    {
        $sql = "SELECT u.id, u.name, u.avatar_url
                FROM group_members gm
                JOIN users u ON gm.user_id = u.id
                WHERE gm.group_id = ? AND gm.status = 'invited'";
        return Database::query($sql, [$groupId])->fetchAll();
    }

    public static function updateSettings($groupId, $visibility)
    {
        $sql = "UPDATE `groups` SET visibility = ? WHERE id = ?";
        return Database::query($sql, [$visibility, $groupId]);
    }

    public static function updateMemberRole($groupId, $userId, $role)
    {
        $sql = "UPDATE group_members SET role = ? WHERE group_id = ? AND user_id = ?";
        return Database::query($sql, [$role, $groupId, $userId]);
    }

    public static function isAdmin($groupId, $userId)
    {
        // Check if user is a site admin/super admin - they are always organisers
        $userRole = Database::query("SELECT role FROM users WHERE id = ?", [$userId])->fetchColumn();
        if (in_array($userRole, ['super_admin', 'admin', 'tenant_admin'])) {
            return true;
        }

        // Otherwise check group membership role
        $sql = "SELECT role FROM group_members WHERE group_id = ? AND user_id = ?";
        $role = Database::query($sql, [$groupId, $userId])->fetchColumn();
        return in_array($role, ['owner', 'admin']);
    }

    public static function updateMemberStatus($groupId, $userId, $status)
    {
        $sql = "UPDATE group_members SET status = ? WHERE group_id = ? AND user_id = ?";
        return Database::query($sql, [$status, $groupId, $userId]);
    }

    public static function getUserGroups($userId)
    {
        $sql = "SELECT g.*,
                (SELECT COUNT(*) FROM group_members WHERE group_id = g.id AND status = 'active') as member_count
                FROM `groups` g
                JOIN group_members gm ON g.id = gm.group_id
                WHERE gm.user_id = ? AND gm.status = 'active'
                ORDER BY g.name ASC";
        return Database::query($sql, [$userId])->fetchAll();
    }

    /**
     * Get all organizers (owner + admins) for a group
     */
    public static function getOrganizers($groupId)
    {
        $sql = "SELECT u.id, u.name, u.email
                FROM group_members gm
                JOIN users u ON gm.user_id = u.id
                WHERE gm.group_id = ? AND gm.role IN ('owner', 'admin') AND gm.status = 'active'";
        return Database::query($sql, [$groupId])->fetchAll();
    }

    /**
     * Notify group organizers when someone requests to join a private group
     */
    private static function notifyOrganizersOfJoinRequest($groupId, $requestingUserId, $group)
    {
        // Get the requesting user's name
        $requestingUser = Database::query("SELECT name FROM users WHERE id = ?", [$requestingUserId])->fetch();
        $requesterName = $requestingUser ? $requestingUser['name'] : 'Someone';

        // Get group organizers (owner + admins)
        $organizers = self::getOrganizers($groupId);

        // Also include the owner directly in case they're not in group_members
        $ownerId = $group['owner_id'];
        $ownerInList = false;
        foreach ($organizers as $org) {
            if ($org['id'] == $ownerId) {
                $ownerInList = true;
                break;
            }
        }
        if (!$ownerInList) {
            $owner = Database::query("SELECT id, name, email FROM users WHERE id = ?", [$ownerId])->fetch();
            if ($owner) {
                $organizers[] = $owner;
            }
        }

        $basePath = TenantContext::getBasePath();
        $link = $basePath . '/groups/' . $groupId . '?tab=settings';
        $message = $requesterName . ' has requested to join ' . $group['name'];

        // Send notification to each organizer
        foreach ($organizers as $organizer) {
            if ($organizer['id'] == $requestingUserId) continue; // Don't notify yourself

            \Nexus\Services\NotificationDispatcher::dispatch(
                $organizer['id'],
                'group',
                $groupId,
                'join_request',
                $message,
                $link,
                '<p>' . htmlspecialchars($requesterName) . ' has requested to join <strong>' . htmlspecialchars($group['name']) . '</strong>. Please review their request.</p>',
                true // isOrganizer - so they get instant notification
            );
        }
    }

    /**
     * Get only hub-type groups (admin-curated geographic hubs)
     * By default, only returns parent hubs (those with sub-groups)
     * When searching, returns ALL matching hubs (including sub-groups)
     */
    public static function getHubs($search = null, $onlyParents = true)
    {
        $hubType = \Nexus\Models\GroupType::getHubType();
        if (!$hubType) {
            return [];
        }

        // If searching, show ALL matching hubs (including sub-groups)
        if ($search) {
            return self::all($search, $hubType['id']);
        }

        if (!$onlyParents) {
            return self::all($search, $hubType['id']);
        }

        // Only return top-level hub groups that have children (parent hubs)
        // OPTIMIZED: Use cached_member_count and has_children flag
        $tenantId = TenantContext::getId();
        $sql = "SELECT g.*, g.cached_member_count as member_count, gt.name as type_name, gt.icon as type_icon, gt.color as type_color
                FROM `groups` g
                LEFT JOIN group_types gt ON g.type_id = gt.id
                WHERE g.tenant_id = ?
                AND g.type_id = ?
                AND (g.parent_id IS NULL OR g.parent_id = 0)
                AND g.has_children = TRUE";

        $params = [$tenantId, $hubType['id']];

        $sql .= " ORDER BY g.cached_member_count DESC, g.created_at DESC";
        return Database::query($sql, $params)->fetchAll();
    }

    /**
     * Get featured hub groups (manually marked by admin)
     * Returns ALL groups marked as featured, regardless of parent/child status
     */
    public static function getFeaturedHubs()
    {
        $hubType = \Nexus\Models\GroupType::getHubType();
        if (!$hubType) {
            return [];
        }

        // OPTIMIZED: Use cached_member_count instead of COUNT()
        $tenantId = TenantContext::getId();
        $sql = "SELECT g.*, g.cached_member_count as member_count, gt.name as type_name, gt.icon as type_icon, gt.color as type_color
                FROM `groups` g
                LEFT JOIN group_types gt ON g.type_id = gt.id
                WHERE g.tenant_id = ?
                AND g.type_id = ?
                AND g.is_featured = 1
                ORDER BY g.cached_member_count DESC, g.created_at DESC";

        return Database::query($sql, [$tenantId, $hubType['id']])->fetchAll();
    }

    /**
     * Get only regular groups (non-hub, user-created interest groups)
     */
    public static function getRegularGroups($search = null, $typeId = null)
    {
        $hubType = \Nexus\Models\GroupType::getHubType();
        $hubTypeId = $hubType ? $hubType['id'] : null;

        // OPTIMIZED: Use cached_member_count instead of COUNT()
        $tenantId = TenantContext::getId();
        $sql = "SELECT g.*, g.cached_member_count as member_count, gt.name as type_name, gt.icon as type_icon, gt.color as type_color
                FROM `groups` g
                LEFT JOIN group_types gt ON g.type_id = gt.id
                WHERE g.tenant_id = ?";

        $params = [$tenantId];

        // Exclude hubs - only show groups with non-hub types
        if ($hubTypeId) {
            $sql .= " AND g.type_id IS NOT NULL AND g.type_id != ?";
            $params[] = $hubTypeId;
        } else {
            // If no hub type exists, exclude groups with NULL type_id
            $sql .= " AND g.type_id IS NOT NULL";
        }

        if ($search) {
            $sql .= " AND (g.name LIKE ? OR g.description LIKE ?)";
            $params[] = "%$search%";
            $params[] = "%$search%";
        } else {
            $sql .= " AND (g.parent_id IS NULL OR g.parent_id = 0)";
        }

        if ($typeId) {
            $sql .= " AND g.type_id = ?";
            $params[] = $typeId;
        }

        $sql .= " ORDER BY g.cached_member_count DESC, g.created_at DESC";
        return Database::query($sql, $params)->fetchAll();
    }

    /**
     * Check if user can create a hub (admin only)
     */
    public static function canCreateHub($userId)
    {
        if (!$userId) {
            return false;
        }

        $role = Database::query("SELECT role FROM users WHERE id = ?", [$userId])->fetchColumn();
        return in_array($role, ['admin', 'super_admin', 'tenant_admin']);
    }

    /**
     * Check if user can create a regular group (any logged-in user)
     */
    public static function canCreateRegularGroup($userId)
    {
        return $userId > 0;
    }

    /**
     * Check if a group is a hub
     */
    public static function isHub($groupId)
    {
        $sql = "SELECT g.type_id FROM `groups` g WHERE g.id = ?";
        $result = Database::query($sql, [$groupId])->fetch();

        if (!$result || !$result['type_id']) {
            return false;
        }

        return \Nexus\Models\GroupType::isHubType($result['type_id']);
    }
}
