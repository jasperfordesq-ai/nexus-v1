<?php

namespace Nexus\Models;

use Nexus\Core\Database;

class VolShift
{
    public static function create($oppId, $startTime, $endTime, $capacity)
    {
        $sql = "INSERT INTO vol_shifts (opportunity_id, start_time, end_time, capacity) VALUES (?, ?, ?, ?)";
        Database::query($sql, [$oppId, $startTime, $endTime, $capacity]);
    }

    public static function getForOpportunity($oppId)
    {
        $sql = "SELECT * FROM vol_shifts WHERE opportunity_id = ? ORDER BY start_time ASC";
        return Database::query($sql, [$oppId])->fetchAll();
    }

    public static function delete($id)
    {
        $sql = "DELETE FROM vol_shifts WHERE id = ?";
        Database::query($sql, [$id]);
    }

    public static function find($id)
    {
        $sql = "SELECT * FROM vol_shifts WHERE id = ?";
        return Database::query($sql, [$id])->fetch();
    }
}
