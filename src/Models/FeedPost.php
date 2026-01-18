<?php

namespace Nexus\Models;

use Nexus\Core\Database;
use Nexus\Core\TenantContext;

class FeedPost
{
    public static function create($userId, $content, $emoji = null, $imageUrl = null, $parentId = null, $parentType = 'post')
    {
        $tenantId = TenantContext::getId();
        // Updated to include parent_id and parent_type
        $sql = "INSERT INTO feed_posts (tenant_id, user_id, content, emoji, image_url, parent_id, parent_type) VALUES (?, ?, ?, ?, ?, ?, ?)";
        Database::query($sql, [$tenantId, $userId, $content, $emoji, $imageUrl, $parentId, $parentType]);
        return Database::lastInsertId();
    }

    public static function getRecent($limit = 50)
    {
        $tenantId = TenantContext::getId();
        $sql = "SELECT p.*, CONCAT(u.first_name, ' ', u.last_name) as user_name, u.avatar_url, 'post' as type
                FROM feed_posts p
                JOIN users u ON p.user_id = u.id
                WHERE p.tenant_id = ? AND p.visibility = 'public'
                ORDER BY p.created_at DESC
                LIMIT " . intval($limit);

        return Database::query($sql, [$tenantId])->fetchAll();
    }

    public static function findById($id)
    {
        $tenantId = TenantContext::getId();
        // Secure fetch with tenant isolation
        $sql = "SELECT p.*, CONCAT(u.first_name, ' ', u.last_name) as author_name, u.avatar_url as author_avatar
                FROM feed_posts p
                LEFT JOIN users u ON p.user_id = u.id
                WHERE p.id = ? AND p.tenant_id = ?";
        return Database::query($sql, [$id, $tenantId])->fetch(\PDO::FETCH_ASSOC);
    }
}
