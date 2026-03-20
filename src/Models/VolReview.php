<?php
// Copyright © 2024–2026 Jasper Ford
// SPDX-License-Identifier: AGPL-3.0-or-later
// Author: Jasper Ford
// See NOTICE file for attribution and acknowledgements.

namespace Nexus\Models;

use Nexus\Core\Database;
use Nexus\Core\TenantContext;

class VolReview
{
    public static function create($reviewerId, $targetType, $targetId, $rating, $comment)
    {
        $sql = "INSERT INTO vol_reviews (tenant_id, reviewer_id, target_type, target_id, rating, comment) VALUES (?, ?, ?, ?, ?, ?)";
        Database::query($sql, [TenantContext::getId(), $reviewerId, $targetType, $targetId, $rating, $comment]);
    }

    public static function getForTarget($type, $id)
    {
        $sql = "SELECT r.*, u.first_name, u.last_name, u.avatar_url
                FROM vol_reviews r
                JOIN users u ON r.reviewer_id = u.id
                WHERE r.target_type = ? AND r.target_id = ? AND r.tenant_id = ?
                ORDER BY r.created_at DESC";
        return Database::query($sql, [$type, $id, TenantContext::getId()])->fetchAll();
    }
}
