<?php

namespace Nexus\Models;

use Nexus\Core\Database;
use PDO;

class Goal
{
    public static function create($tenantId, $userId, $title, $description, $deadline, $isPublic)
    {
        $db = Database::getConnection();
        $stmt = $db->prepare("
            INSERT INTO goals (tenant_id, user_id, title, description, deadline, is_public, status)
            VALUES (?, ?, ?, ?, ?, ?, 'active')
        ");
        $stmt->execute([$tenantId, $userId, $title, $description, $deadline ?: null, $isPublic]);
        return $db->lastInsertId();
    }

    public static function find($id, $tenantId = null)
    {
        $db = Database::getConnection();
        $sql = "
            SELECT g.*,
                   u.name as author_name,
                   u.avatar_url as author_avatar,
                   m.name as mentor_name,
                   m.avatar_url as mentor_avatar
            FROM goals g
            LEFT JOIN users u ON g.user_id = u.id
            LEFT JOIN users m ON g.mentor_id = m.id
            WHERE g.id = ?
        ";
        $params = [$id];

        if ($tenantId) {
            $sql .= " AND g.tenant_id = ?";
            $params[] = $tenantId;
        }

        $stmt = $db->prepare($sql);
        $stmt->execute($params);
        return $stmt->fetch(PDO::FETCH_ASSOC);
    }

    public static function allPublic($tenantId)
    {
        $db = Database::getConnection();
        $stmt = $db->prepare("
            SELECT g.*, u.name as author_name, u.email as author_email 
            FROM goals g 
            JOIN users u ON g.user_id = u.id 
            WHERE g.tenant_id = ? 
            AND u.tenant_id = ? 
            AND g.is_public = 1 
            AND g.mentor_id IS NULL
            AND g.status = 'active'
            ORDER BY g.created_at DESC
        ");
        $stmt->execute([$tenantId, $tenantId]);
        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    }

    public static function myGoals($userId, $tenantId = null)
    {
        $db = Database::getConnection();
        $sql = "
            SELECT g.*, m.name as mentor_name 
            FROM goals g 
            LEFT JOIN users m ON g.mentor_id = m.id
            WHERE g.user_id = ? 
        ";
        $params = [$userId];

        if ($tenantId) {
            $sql .= " AND g.tenant_id = ?";
            $params[] = $tenantId;
        }

        $sql .= " ORDER BY g.created_at DESC";

        $stmt = $db->prepare($sql);
        $stmt->execute($params);
        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    }

    public static function setMentor($goalId, $mentorId)
    {
        $db = Database::getConnection();
        $stmt = $db->prepare("UPDATE goals SET mentor_id = ? WHERE id = ?");
        $stmt->execute([$mentorId, $goalId]);
    }

    public static function update($id, $title, $description, $deadline, $isPublic)
    {
        $sql = "UPDATE goals SET title = ?, description = ?, deadline = ?, is_public = ? WHERE id = ?";
        Database::query($sql, [$title, $description, $deadline ?: null, $isPublic, $id]);
    }

    public static function setStatus($id, $status)
    {
        Database::query("UPDATE goals SET status = ? WHERE id = ?", [$status, $id]);
    }

    public static function delete($id)
    {
        Database::query("DELETE FROM goals WHERE id = ?", [$id]);
    }
}
