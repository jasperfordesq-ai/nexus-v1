<?php

namespace Nexus\Models;

use Nexus\Core\Database;

class Connection
{
    public static function sendRequest($requesterId, $receiverId)
    {
        // Check if exists
        $sql = "SELECT id FROM connections WHERE (requester_id = ? AND receiver_id = ?) OR (requester_id = ? AND receiver_id = ?)";
        $exists = Database::query($sql, [$requesterId, $receiverId, $receiverId, $requesterId])->fetch();

        if ($exists) return false;

        $sql = "INSERT INTO connections (requester_id, receiver_id, status) VALUES (?, ?, 'pending')";
        Database::query($sql, [$requesterId, $receiverId]);
        return true;
    }

    public static function acceptRequest($id)
    {
        $sql = "UPDATE connections SET status = 'accepted' WHERE id = ?";
        Database::query($sql, [$id]);
    }

    public static function removeConnection($id)
    {
        $sql = "DELETE FROM connections WHERE id = ?";
        Database::query($sql, [$id]);
    }

    public static function getStatus($user1, $user2)
    {
        $sql = "SELECT * FROM connections WHERE (requester_id = ? AND receiver_id = ?) OR (requester_id = ? AND receiver_id = ?)";
        return Database::query($sql, [$user1, $user2, $user2, $user1])->fetch();
    }

    public static function getPending($userId)
    {
        $sql = "SELECT c.*, u.name as requester_name, u.avatar_url 
                FROM connections c 
                JOIN users u ON c.requester_id = u.id 
                WHERE c.receiver_id = ? AND c.status = 'pending'";
        return Database::query($sql, [$userId])->fetchAll();
    }

    public static function getFriends($userId)
    {
        // Complex query to get the "other" user
        $sql = "SELECT u.id, u.name, u.avatar_url, u.location 
                FROM connections c
                JOIN users u ON (CASE WHEN c.requester_id = ? THEN c.receiver_id ELSE c.requester_id END) = u.id
                WHERE (c.requester_id = ? OR c.receiver_id = ?) AND c.status = 'accepted'";
        return Database::query($sql, [$userId, $userId, $userId])->fetchAll();
    }
}
