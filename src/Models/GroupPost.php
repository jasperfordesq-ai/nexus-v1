<?php
// Copyright © 2024–2026 Jasper Ford
// SPDX-License-Identifier: AGPL-3.0-or-later
// Author: Jasper Ford
// See NOTICE file for attribution and acknowledgements.

namespace Nexus\Models;

use Nexus\Core\Database;
use Nexus\Core\TenantContext;

class GroupPost
{
    public static function create($discussionId, $userId, $content)
    {
        $tenantId = TenantContext::getId();
        $sql = "INSERT INTO group_posts (tenant_id, discussion_id, user_id, content) VALUES (?, ?, ?, ?)";
        Database::query($sql, [$tenantId, $discussionId, $userId, $content]);
        return Database::getInstance()->lastInsertId();
    }

    public static function findById($id)
    {
        $tenantId = TenantContext::getId();
        $sql = "SELECT gp.*, u.name as author_name, u.avatar_url as author_avatar
                FROM group_posts gp
                JOIN users u ON gp.user_id = u.id
                WHERE gp.id = ? AND gp.tenant_id = ?";
        return Database::query($sql, [$id, $tenantId])->fetch();
    }

    public static function getForDiscussion($discussionId)
    {
        $tenantId = TenantContext::getId();
        $sql = "SELECT gp.*, u.name as author_name, u.avatar_url as author_avatar
                FROM group_posts gp
                JOIN users u ON gp.user_id = u.id
                WHERE gp.discussion_id = ? AND gp.tenant_id = ?
                ORDER BY gp.created_at ASC";
        return Database::query($sql, [$discussionId, $tenantId])->fetchAll();
    }

    public static function delete($id)
    {
        $tenantId = TenantContext::getId();
        $sql = "DELETE FROM group_posts WHERE id = ? AND tenant_id = ?";
        return Database::query($sql, [$id, $tenantId]);
    }
}
