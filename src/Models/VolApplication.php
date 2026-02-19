<?php
// Copyright © 2024–2026 Jasper Ford
// SPDX-License-Identifier: AGPL-3.0-or-later
// Author: Jasper Ford
// See NOTICE file for attribution and acknowledgements.

namespace Nexus\Models;

use Nexus\Core\Database;

class VolApplication
{
    public static function create($oppId, $userId, $message, $shiftId = null)
    {
        $sql = "INSERT INTO vol_applications (opportunity_id, user_id, message, shift_id) VALUES (?, ?, ?, ?)";
        Database::query($sql, [$oppId, $userId, $message, $shiftId]);
    }

    public static function hasApplied($oppId, $userId)
    {
        $res = Database::query("SELECT id FROM vol_applications WHERE opportunity_id = ? AND user_id = ?", [$oppId, $userId])->fetch();
        return (bool) $res;
    }

    public static function getForOpportunity($oppId)
    {
        $sql = "SELECT app.*, u.name as user_name, u.email as user_email,
                       s.start_time as shift_start, s.end_time as shift_end
                FROM vol_applications app
                JOIN users u ON app.user_id = u.id
                LEFT JOIN vol_shifts s ON app.shift_id = s.id
                WHERE app.opportunity_id = ?
                ORDER BY app.created_at DESC";
        return Database::query($sql, [$oppId])->fetchAll();
    }
    public static function getByUser($userId)
    {
        $sql = "SELECT app.*, opp.title as opp_title, org.name as org_name
                FROM vol_applications app
                JOIN vol_opportunities opp ON app.opportunity_id = opp.id
                JOIN vol_organizations org ON opp.organization_id = org.id
                WHERE app.user_id = ?
                ORDER BY app.created_at DESC";
        return Database::query($sql, [$userId])->fetchAll();
    }
}
