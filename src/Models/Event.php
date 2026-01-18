<?php

namespace Nexus\Models;

use Nexus\Core\Database;

class Event
{
    public static function create($tenantId, $userId, $title, $description, $location, $start, $end, $groupId = null, $categoryId = null, $lat = null, $lon = null, $federatedVisibility = 'none')
    {
        // Validate federated_visibility value
        $validVisibilities = ['none', 'listed', 'joinable'];
        if (!in_array($federatedVisibility, $validVisibilities)) {
            $federatedVisibility = 'none';
        }

        $sql = "INSERT INTO events (tenant_id, user_id, title, description, location, start_time, end_time, group_id, category_id, latitude, longitude, federated_visibility) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)";
        Database::query($sql, [$tenantId, $userId, $title, $description, $location, $start, $end, $groupId, $categoryId, $lat, $lon, $federatedVisibility]);
        return Database::getConnection()->lastInsertId();
    }

    public static function upcoming($tenantId, $limit = 50, $categoryId = null, $dateFilter = null, $search = null)
    {
        // Get upcoming events, sorted by nearest date first
        $sql = "SELECT e.*, u.name as organizer_name, c.name as category_name, c.color as category_color
                FROM events e
                JOIN users u ON e.user_id = u.id
                LEFT JOIN `groups` g ON e.group_id = g.id
                LEFT JOIN categories c ON e.category_id = c.id
                WHERE e.tenant_id = ? 
                AND e.start_time >= NOW()
                AND (g.visibility IS NULL OR g.visibility = 'public')";

        $params = [$tenantId];

        if ($categoryId) {
            $sql .= " AND e.category_id = ?";
            $params[] = $categoryId;
        }

        if ($dateFilter === 'weekend') {
            // Simple logic: Next Friday to Sunday? Or just Saturday/Sunday?
            // tailored for "This Weekend" usually means upcoming Fri/Sat/Sun
            $sql .= " AND WEEKOFYEAR(e.start_time) = WEEKOFYEAR(NOW()) AND DAYOFWEEK(e.start_time) IN (1, 6, 7)";
        } elseif ($dateFilter === 'month') {
            $sql .= " AND MONTH(e.start_time) = MONTH(NOW()) AND YEAR(e.start_time) = YEAR(NOW())";
        }

        if ($search) {
            $sql .= " AND (e.title LIKE ? OR e.description LIKE ? OR e.location LIKE ? OR u.name LIKE ?)";
            $term = '%' . $search . '%';
            $params[] = $term;
            $params[] = $term;
            $params[] = $term;
            $params[] = $term;
        }

        $sql .= " ORDER BY e.start_time ASC LIMIT $limit";

        return Database::query($sql, $params)->fetchAll();
    }

    public static function getRange($tenantId, $startDate, $endDate)
    {
        $sql = "SELECT e.*, c.color as category_color 
                FROM events e 
                LEFT JOIN categories c ON e.category_id = c.id
                WHERE e.tenant_id = ? 
                AND e.start_time BETWEEN ? AND ?
                ORDER BY e.start_time ASC";
        return Database::query($sql, [$tenantId, $startDate . ' 00:00:00', $endDate . ' 23:59:59'])->fetchAll();
    }

    public static function getForGroup($groupId)
    {
        $sql = "SELECT e.*, u.name as organizer_name, u.avatar_url as organizer_avatar, c.name as category_name, c.color as category_color
                FROM events e
                JOIN users u ON e.user_id = u.id
                LEFT JOIN categories c ON e.category_id = c.id
                WHERE e.group_id = ?
                ORDER BY e.start_time ASC";
        return Database::query($sql, [$groupId])->fetchAll();
    }

    public static function find($id)
    {
        $sql = "SELECT e.*, u.name as organizer_name, u.avatar_url as organizer_avatar
                FROM events e
                JOIN users u ON e.user_id = u.id
                WHERE e.id = ?";
        return Database::query($sql, [$id])->fetch();
    }

    public static function update($id, $title, $description, $location, $start, $end, $groupId = null, $categoryId = null, $lat = null, $lon = null, $federatedVisibility = null)
    {
        // Build dynamic update query
        $fields = ['title = ?', 'description = ?', 'location = ?', 'start_time = ?', 'end_time = ?', 'group_id = ?', 'category_id = ?', 'latitude = ?', 'longitude = ?'];
        $params = [$title, $description, $location, $start, $end, $groupId, $categoryId, $lat, $lon];

        if ($federatedVisibility !== null) {
            $validVisibilities = ['none', 'listed', 'joinable'];
            if (in_array($federatedVisibility, $validVisibilities)) {
                $fields[] = 'federated_visibility = ?';
                $params[] = $federatedVisibility;
            }
        }

        $params[] = $id;
        $sql = "UPDATE events SET " . implode(', ', $fields) . " WHERE id = ?";
        return Database::query($sql, $params);
    }

    public static function delete($id)
    {
        return Database::query("DELETE FROM events WHERE id = ?", [$id]);
    }
    public static function getAttending($userId)
    {
        $sql = "SELECT e.*, er.status as rsvp_status, u.name as organizer_name
                FROM events e
                JOIN event_rsvps er ON e.id = er.event_id
                JOIN users u ON e.user_id = u.id
                WHERE er.user_id = ? AND er.status = 'going' AND e.start_time >= NOW()
                ORDER BY e.start_time ASC
                LIMIT 10";
        return Database::query($sql, [$userId])->fetchAll();
    }

    public static function getHosted($userId)
    {
        $sql = "SELECT e.*, 
                (SELECT count(*) FROM event_rsvps WHERE event_id = e.id AND status = 'going') as attending_count,
                (SELECT count(*) FROM event_rsvps WHERE event_id = e.id AND status = 'invited') as invited_count
                FROM events e
                WHERE e.user_id = ? AND e.start_time >= NOW()
                ORDER BY e.start_time ASC";
        return Database::query($sql, [$userId])->fetchAll();
    }
}
