<?php
// Copyright © 2024–2026 Jasper Ford
// SPDX-License-Identifier: AGPL-3.0-or-later
// Author: Jasper Ford
// See NOTICE file for attribution and acknowledgements.

namespace Nexus\Models;

use Nexus\Core\Database;

class Poll
{
    public static function create($tenantId, $userId, $question, $description, $endDate)
    {
        $sql = "INSERT INTO polls (tenant_id, user_id, question, description, end_date) VALUES (?, ?, ?, ?, ?)";
        Database::query($sql, [$tenantId, $userId, $question, $description, $endDate]);
        return Database::getConnection()->lastInsertId();
    }

    public static function addOption($pollId, $label)
    {
        Database::query("INSERT INTO poll_options (poll_id, label) VALUES (?, ?)", [$pollId, $label]);
    }

    public static function all($tenantId)
    {
        $sql = "SELECT p.*, u.name as creator_name, 
                (SELECT COUNT(*) FROM poll_votes v WHERE v.poll_id = p.id) as total_votes,
                CASE 
                    WHEN p.end_date IS NULL OR p.end_date > NOW() THEN 'open' 
                    ELSE 'closed' 
                END as status
                FROM polls p
                JOIN users u ON p.user_id = u.id
                WHERE p.tenant_id = ? AND p.is_active = 1
                ORDER BY p.created_at DESC";
        return Database::query($sql, [$tenantId])->fetchAll();
    }

    public static function find($id)
    {
        return Database::query("SELECT * FROM polls WHERE id = ?", [$id])->fetch();
    }

    public static function getOptions($id)
    {
        $sql = "SELECT o.*, 
                (SELECT COUNT(*) FROM poll_votes v WHERE v.option_id = o.id) as vote_count
                FROM poll_options o
                WHERE o.poll_id = ?";
        return Database::query($sql, [$id])->fetchAll();
    }

    public static function hasVoted($pollId, $userId)
    {
        $sql = "SELECT id FROM poll_votes WHERE poll_id = ? AND user_id = ?";
        return (bool) Database::query($sql, [$pollId, $userId])->fetch();
    }

    public static function castVote($pollId, $optionId, $userId)
    {
        // Double check unique constraint
        if (self::hasVoted($pollId, $userId)) {
            return false;
        }
        $sql = "INSERT INTO poll_votes (poll_id, option_id, user_id) VALUES (?, ?, ?)";
        Database::query($sql, [$pollId, $optionId, $userId]);
        return true;
    }

    public static function update($id, $question, $description, $endDate)
    {
        $sql = "UPDATE polls SET question = ?, description = ?, end_date = ? WHERE id = ?";
        Database::query($sql, [$question, $description, $endDate, $id]);
    }

    public static function delete($id)
    {
        $tenantId = \Nexus\Core\TenantContext::getId();
        Database::query("DELETE FROM polls WHERE id = ? AND tenant_id = ?", [$id, $tenantId]);
    }
}
