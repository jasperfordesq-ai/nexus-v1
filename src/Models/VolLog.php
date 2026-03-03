<?php
// Copyright © 2024–2026 Jasper Ford
// SPDX-License-Identifier: AGPL-3.0-or-later
// Author: Jasper Ford
// See NOTICE file for attribution and acknowledgements.

namespace Nexus\Models;

use Nexus\Core\Database;
use Nexus\Core\TenantContext;

class VolLog
{
    public static function create($userId, $orgId, $oppId, $date, $hours, $desc)
    {
        $sql = "INSERT INTO vol_logs (user_id, organization_id, opportunity_id, date_logged, hours, description) VALUES (?, ?, ?, ?, ?, ?)";
        Database::query($sql, [$userId, $orgId, $oppId, $date, $hours, $desc]);
    }

    public static function getForUser($userId)
    {
        $sql = "SELECT l.*, o.name as org_name, opp.title as opp_title
                FROM vol_logs l
                LEFT JOIN vol_organizations o ON l.organization_id = o.id
                LEFT JOIN vol_opportunities opp ON l.opportunity_id = opp.id
                WHERE l.user_id = ? AND l.tenant_id = ?
                ORDER BY l.date_logged DESC";
        return Database::query($sql, [$userId, TenantContext::getId()])->fetchAll();
    }

    public static function getForOrg($orgId, $status = null)
    {
        $sql = "SELECT l.*, u.first_name, u.last_name, u.email, opp.title as opp_title
                FROM vol_logs l
                JOIN users u ON l.user_id = u.id
                LEFT JOIN vol_opportunities opp ON l.opportunity_id = opp.id
                WHERE l.organization_id = ?";

        $params = [$orgId];

        if ($status) {
            $sql .= " AND l.status = ?";
            $params[] = $status;
        }

        $sql .= " ORDER BY l.created_at DESC";

        return Database::query($sql, $params)->fetchAll();
    }

    public static function updateStatus($id, $status)
    {
        $sql = "UPDATE vol_logs SET status = ? WHERE id = ? AND tenant_id = ?";
        Database::query($sql, [$status, $id, TenantContext::getId()]);
    }

    public static function find($id)
    {
        return Database::query("SELECT * FROM vol_logs WHERE id = ? AND tenant_id = ?", [$id, TenantContext::getId()])->fetch();
    }

    public static function getTotalVerifiedHours($userId)
    {
        $sql = "SELECT SUM(hours) as total FROM vol_logs WHERE user_id = ? AND status = 'approved' AND tenant_id = ?";
        $res = Database::query($sql, [$userId, TenantContext::getId()])->fetch();
        return (float) ($res['total'] ?? 0);
    }
}
