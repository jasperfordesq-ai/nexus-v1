<?php
// Copyright © 2024–2026 Jasper Ford
// SPDX-License-Identifier: AGPL-3.0-or-later
// Author: Jasper Ford
// See NOTICE file for attribution and acknowledgements.

namespace Nexus\Models;

use Nexus\Core\Database;

class GroupPost
{
    public static function create($discussionId, $userId, $content)
    {
        $sql = "INSERT INTO group_posts (discussion_id, user_id, content) VALUES (?, ?, ?)";
        Database::query($sql, [$discussionId, $userId, $content]);
        return Database::getInstance()->lastInsertId();
    }

    public static function getForDiscussion($discussionId)
    {
        $sql = "SELECT gp.*, u.name as author_name, u.avatar_url as author_avatar
                FROM group_posts gp
                JOIN users u ON gp.user_id = u.id
                WHERE gp.discussion_id = ?
                ORDER BY gp.created_at ASC";
        return Database::query($sql, [$discussionId])->fetchAll();
    }

    public static function delete($id)
    {
        $sql = "DELETE FROM group_posts WHERE id = ?";
        return Database::query($sql, [$id]);
    }
}
