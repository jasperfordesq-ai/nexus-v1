<?php

namespace Nexus\Models;

use Nexus\Core\Database;

class EventRsvp
{
    public static function rsvp($eventId, $userId, $status)
    {
        // Insert or Update (Upsert logic)
        // Check existence first
        $exists = Database::query("SELECT id FROM event_rsvps WHERE event_id = ? AND user_id = ?", [$eventId, $userId])->fetch();

        if ($exists) {
            Database::query("UPDATE event_rsvps SET status = ? WHERE id = ?", [$status, $exists['id']]);
        } else {
            Database::query("INSERT INTO event_rsvps (event_id, user_id, status) VALUES (?, ?, ?)", [$eventId, $userId, $status]);
        }
    }

    public static function getUserStatus($eventId, $userId)
    {
        $res = Database::query("SELECT status FROM event_rsvps WHERE event_id = ? AND user_id = ?", [$eventId, $userId])->fetch();
        return $res ? $res['status'] : null;
    }

    public static function getAttendees($eventId)
    {
        $sql = "SELECT r.*, u.name, u.avatar_url 
                FROM event_rsvps r
                JOIN users u ON r.user_id = u.id
                WHERE r.event_id = ? AND r.status IN ('going', 'attended')
                ORDER BY r.created_at DESC";
        return Database::query($sql, [$eventId])->fetchAll();
    }

    public static function getInvited($eventId)
    {
        $sql = "SELECT r.*, u.name, u.avatar_url 
                FROM event_rsvps r
                JOIN users u ON r.user_id = u.id
                WHERE r.event_id = ? AND r.status = 'invited'
                ORDER BY r.created_at DESC";
        return Database::query($sql, [$eventId])->fetchAll();
    }

    public static function getCount($eventId, $status = 'going')
    {
        $sql = "SELECT COUNT(*) FROM event_rsvps WHERE event_id = ? AND status = ?";
        return Database::query($sql, [$eventId, $status])->fetchColumn();
    }
}
