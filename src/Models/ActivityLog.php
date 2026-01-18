<?php

namespace Nexus\Models;

use Nexus\Core\Database;

class ActivityLog
{
    public static function getGlobal($limit = 10)
    {
        // Wrapper for getPublicFeed which is tenant aware
        return self::getPublicFeed($limit);
    }

    public static function log($userId, $action, $details = '', $isPublic = false, $linkUrl = null, $actionType = 'system', $entityType = null, $entityId = null)
    {
        $ip = $_SERVER['REMOTE_ADDR'] ?? null;
        $sql = "INSERT INTO activity_log (user_id, action, details, is_public, link_url, ip_address, action_type, entity_type, entity_id) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?)";
        Database::query($sql, [$userId, $action, $details, $isPublic ? 1 : 0, $linkUrl, $ip, $actionType, $entityType, $entityId]);
    }

    public static function getRecent($limit = 20)
    {
        $limit = (int) $limit;
        $tenantId = \Nexus\Core\TenantContext::getId();

        // Exclude admin login events from the activity feed
        $sql = "SELECT a.*, CONCAT(u.first_name, ' ', u.last_name) as user_name, u.avatar_url
                FROM activity_log a
                JOIN users u ON a.user_id = u.id
                WHERE u.tenant_id = ?
                AND NOT (a.action = 'login' AND u.role = 'admin')
                ORDER BY a.created_at DESC
                LIMIT $limit";

        return Database::query($sql, [$tenantId])->fetchAll();
    }

    public static function getPublicFeed($limit = 50)
    {
        $limit = (int) $limit;
        $tenantId = \Nexus\Core\TenantContext::getId();

        $sql = "SELECT a.*, CONCAT(u.first_name, ' ', u.last_name) as user_name, u.avatar_url 
                FROM activity_log a 
                JOIN users u ON a.user_id = u.id 
                WHERE u.tenant_id = ? AND a.is_public = 1
                ORDER BY a.created_at DESC 
                LIMIT $limit";

        return Database::query($sql, [$tenantId])->fetchAll();
    }

    public static function getAll($limit = 20, $offset = 0)
    {
        $limit = (int) $limit;
        $offset = (int) $offset;
        $tenantId = \Nexus\Core\TenantContext::getId();

        $sql = "SELECT a.*, CONCAT(u.first_name, ' ', u.last_name) as user_name, u.email as user_email, u.avatar_url 
                FROM activity_log a 
                JOIN users u ON a.user_id = u.id 
                WHERE u.tenant_id = ?
                ORDER BY a.created_at DESC 
                LIMIT $limit OFFSET $offset";

        return Database::query($sql, [$tenantId])->fetchAll();
    }

    public static function count()
    {
        $tenantId = \Nexus\Core\TenantContext::getId();
        $sql = "SELECT COUNT(*) as c 
                FROM activity_log a 
                JOIN users u ON a.user_id = u.id 
                WHERE u.tenant_id = ?";

        return Database::query($sql, [$tenantId])->fetch()['c'];
    }
}
