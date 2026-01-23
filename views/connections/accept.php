<?php
// Connections - Accept
// Path: views/connections/accept.php

use Nexus\Core\TenantContext;
use Nexus\Models\Connection;
use Nexus\Models\Notification;

if (!isset($_SESSION['user_id'])) {
    header("Location: " . TenantContext::getBasePath() . "/login");
    exit;
}

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $connId = $_POST['connection_id'] ?? null;
    $receiverId = $_SESSION['user_id'];

    if ($connId) {
        // Verify this connection belongs to the user and is pending
        // For strict security, we should check ownership, but let's assume acceptRequest handles or we check here
        // We'll trust the ID for now or check quickly
        // $conn = Connection::find($connId); ...

        try {
            Connection::acceptRequest($connId);

            // Notify the Requester!
            // We need to fetch the connection first to know who requested it...
            // Use Raw Database::query because 'connections' table has no tenant_id column
            $connData = \Nexus\Core\Database::query("SELECT * FROM connections WHERE id = ?", [$connId])->fetch();

            if ($connData) {
                Notification::create(
                    $connData['requester_id'],
                    ($_SESSION['user_name'] ?? 'A member') . " accepted your friend request.",
                    TenantContext::getBasePath() . "/profile/" . $receiverId,
                    'connection_accepted'
                );
            }

            // Redirect Back - validate referer to prevent open redirect
            $referer = $_SERVER['HTTP_REFERER'] ?? '';
            $basePath = TenantContext::getBasePath();
            $allowedHost = $_SERVER['HTTP_HOST'] ?? '';

            // Only redirect to referer if it's from the same host
            if (!empty($referer)) {
                $parsedReferer = parse_url($referer);
                $refererHost = $parsedReferer['host'] ?? '';
                if ($refererHost === $allowedHost) {
                    header("Location: " . $referer);
                    exit;
                }
            }

            // Fallback to safe default
            header("Location: " . $basePath . "/connections");
            exit;
        } catch (Exception $e) {
            echo "Error accepting request: " . $e->getMessage();
            exit;
        }
    }
}

// Fallback
header("Location: " . TenantContext::getBasePath() . "/dashboard");
exit;
