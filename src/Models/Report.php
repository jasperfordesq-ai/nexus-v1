<?php

namespace Nexus\Models;

use Nexus\Core\Database;

class Report
{
    public static function create($tenantId, $reporterId, $targetType, $targetId, $reason)
    {
        $sql = "INSERT INTO reports (tenant_id, reporter_id, target_type, target_id, reason, status, created_at) VALUES (?, ?, ?, ?, ?, 'open', NOW())";
        Database::query($sql, [$tenantId, $reporterId, $targetType, $targetId, $reason]);
    }

    public static function getOpen($tenantId)
    {
        $sql = "SELECT r.*, u.name as reporter_name 
                FROM reports r 
                JOIN users u ON r.reporter_id = u.id 
                WHERE r.tenant_id = ? AND r.status = 'open' 
                ORDER BY r.created_at DESC";
        return Database::query($sql, [$tenantId])->fetchAll();
    }

    public static function resolve($id, $status)
    {
        // $status should be 'resolved' or 'dismissed'
        $sql = "UPDATE reports SET status = ? WHERE id = ?";
        Database::query($sql, [$status, $id]);
    }
}
