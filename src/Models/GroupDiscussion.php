<?php

namespace Nexus\Models;

use Nexus\Core\Database;
use Nexus\Core\TenantContext;

class GroupDiscussion
{
    public static function create($groupId, $userId, $title)
    {
        $sql = "INSERT INTO group_discussions (group_id, user_id, title) VALUES (?, ?, ?)";
        Database::query($sql, [$groupId, $userId, $title]);
        return Database::getInstance()->lastInsertId();
    }

    public static function getForGroup($groupId)
    {
        $sql = "SELECT gd.*, u.name as author_name, u.avatar_url as author_avatar,
                       (SELECT COUNT(*) FROM group_posts gp WHERE gp.discussion_id = gd.id) as reply_count,
                       (SELECT MAX(created_at) FROM group_posts gp WHERE gp.discussion_id = gd.id) as last_reply_at
                FROM group_discussions gd
                JOIN users u ON gd.user_id = u.id
                WHERE gd.group_id = ?
                ORDER BY is_pinned DESC, last_reply_at DESC, created_at DESC";
        return Database::query($sql, [$groupId])->fetchAll();
    }

    public static function findById($id)
    {
        $sql = "SELECT gd.*, u.name as author_name, u.avatar_url as author_avatar
                FROM group_discussions gd
                JOIN users u ON gd.user_id = u.id
                WHERE gd.id = ?";
        return Database::query($sql, [$id])->fetch();
    }

    public static function delete($id)
    {
        $sql = "DELETE FROM group_discussions WHERE id = ?";
        return Database::query($sql, [$id]);
    }
}
