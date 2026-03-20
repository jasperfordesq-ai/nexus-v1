<?php
// Copyright © 2024–2026 Jasper Ford
// SPDX-License-Identifier: AGPL-3.0-or-later
// Author: Jasper Ford
// See NOTICE file for attribution and acknowledgements.

namespace Nexus\Models;

use Nexus\Core\Database;
use Nexus\Core\TenantContext;

class EventRsvp
{
    public static function rsvp($eventId, $userId, $status)
    {
        $tenantId = TenantContext::getId();
        // Insert or Update (Upsert logic) — scope by tenant via events JOIN
        $exists = Database::query(
            "SELECT r.id FROM event_rsvps r
             JOIN events e ON r.event_id = e.id
             WHERE r.event_id = ? AND r.user_id = ? AND e.tenant_id = ?",
            [$eventId, $userId, $tenantId]
        )->fetch();

        if ($exists) {
            Database::query("UPDATE event_rsvps SET status = ? WHERE id = ? AND event_id = ?", [$status, $exists['id'], $eventId]);
        } else {
            Database::query("INSERT INTO event_rsvps (event_id, user_id, status, tenant_id) VALUES (?, ?, ?, ?)", [$eventId, $userId, $status, $tenantId]);
        }
    }

    public static function getUserStatus($eventId, $userId)
    {
        $tenantId = TenantContext::getId();
        $res = Database::query(
            "SELECT r.status FROM event_rsvps r
             JOIN events e ON r.event_id = e.id
             WHERE r.event_id = ? AND r.user_id = ? AND e.tenant_id = ?",
            [$eventId, $userId, $tenantId]
        )->fetch();
        return $res ? $res['status'] : null;
    }

    public static function getAttendees($eventId)
    {
        $tenantId = TenantContext::getId();
        $sql = "SELECT r.*, u.name, u.avatar_url
                FROM event_rsvps r
                JOIN users u ON r.user_id = u.id
                JOIN events e ON r.event_id = e.id
                WHERE r.event_id = ? AND e.tenant_id = ? AND r.status IN ('going', 'attended')
                ORDER BY r.created_at DESC";
        return Database::query($sql, [$eventId, $tenantId])->fetchAll();
    }

    public static function getInvited($eventId)
    {
        $tenantId = TenantContext::getId();
        $sql = "SELECT r.*, u.name, u.avatar_url
                FROM event_rsvps r
                JOIN users u ON r.user_id = u.id
                JOIN events e ON r.event_id = e.id
                WHERE r.event_id = ? AND e.tenant_id = ? AND r.status = 'invited'
                ORDER BY r.created_at DESC";
        return Database::query($sql, [$eventId, $tenantId])->fetchAll();
    }

    public static function getCount($eventId, $status = 'going')
    {
        $tenantId = TenantContext::getId();
        $sql = "SELECT COUNT(*) FROM event_rsvps r
                JOIN events e ON r.event_id = e.id
                WHERE r.event_id = ? AND e.tenant_id = ? AND r.status = ?";
        return Database::query($sql, [$eventId, $tenantId, $status])->fetchColumn();
    }
}
