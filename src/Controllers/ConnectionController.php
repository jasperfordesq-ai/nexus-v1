<?php

namespace Nexus\Controllers;

use Nexus\Models\Connection;
use Nexus\Models\Notification;
use Nexus\Models\User;
use Nexus\Core\Mailer;
use Nexus\Core\Database;
use Nexus\Helpers\UrlHelper;

class ConnectionController
{
    public function index()
    {
        if (!isset($_SESSION['user_id'])) {
            header('Location: ' . \Nexus\Core\TenantContext::getBasePath() . '/login');
            exit;
        }

        $userId = $_SESSION['user_id'];
        $pending = Connection::getPending($userId);
        $friends = Connection::getFriends($userId);

        \Nexus\Core\View::render('connections/index', [
            'pending' => $pending,
            'friends' => $friends
        ]);
    }

    public function add()
    {
        $this->authCheck();
        $receiverId = $_POST['receiver_id'] ?? null;
        $requesterId = $_SESSION['user_id'];

        if ($receiverId && $receiverId != $requesterId) {
            if (Connection::sendRequest($requesterId, $receiverId)) {
                Notification::create($receiverId, "You have a new friend request from " . $_SESSION['user_name']);

                // Send Email
                try {
                    $receiver = User::findById($receiverId);
                    if ($receiver && $receiver['email']) {
                        $mailer = new Mailer();
                        $subject = "New Friend Request";
                        $profileLink = (isset($_SERVER['HTTPS']) ? "https" : "http") . "://" . $_SERVER['HTTP_HOST'] . "/profile/{$_SESSION['user_id']}";

                        $html = \Nexus\Core\EmailTemplate::render(
                            "New Friend Request",
                            "{$_SESSION['user_name']} sent you a friend request.",
                            "Expand your network on Project NEXUS. View their profile to accept or decline the request.",
                            "View Profile",
                            $profileLink,
                            "Project NEXUS"
                        );

                        $mailer->send($receiver['email'], $subject, $html);
                    }
                } catch (\Throwable $e) {
                    error_log("Friend Request Email Failed: " . $e->getMessage());
                }
            }
        }

        header('Location: ' . UrlHelper::safeReferer('/connections'));
    }

    public function accept()
    {
        $this->authCheck();
        $id = (int) ($_POST['connection_id'] ?? 0);
        $currentUserId = $_SESSION['user_id'];

        // Fetch connection to get requester for email
        $db = Database::getConnection();
        $conn = $db->query("SELECT * FROM connections WHERE id = ?", [$id])->fetch();

        // SECURITY: Verify current user is the receiver of this connection request
        if (!$conn || (int)$conn['receiver_id'] !== (int)$currentUserId) {
            $_SESSION['error'] = 'You are not authorized to accept this connection request.';
            header('Location: ' . \Nexus\Core\TenantContext::getBasePath() . '/connections');
            exit;
        }

        Connection::acceptRequest($id, $currentUserId);

        // Gamification: Check connection badges for both users
        try {
            \Nexus\Services\GamificationService::checkConnectionBadges($_SESSION['user_id']);
            if ($conn) {
                \Nexus\Services\GamificationService::checkConnectionBadges($conn['requester_id']);
            }
        } catch (\Throwable $e) {
            error_log("Gamification connection error: " . $e->getMessage());
        }

        if ($conn) {
            try {
                $requester = User::findById($conn['requester_id']);
                if ($requester && $requester['email']) {
                    $mailer = new Mailer();
                    $subject = "Friend Request Accepted";
                    $profileLink = (isset($_SERVER['HTTPS']) ? "https" : "http") . "://" . $_SERVER['HTTP_HOST'] . "/profile/{$_SESSION['user_id']}";

                    $html = \Nexus\Core\EmailTemplate::render(
                        "Request Accepted",
                        "{$_SESSION['user_name']} accepted your friend request!",
                        "You are now connected. You can send messages, exchange credits, and see their updates.",
                        "View Profile",
                        $profileLink,
                        "Project NEXUS"
                    );

                    $mailer->send($requester['email'], $subject, $html);
                }

                // In-App Notification for Requester
                Notification::create($conn['requester_id'], $_SESSION['user_name'] . " accepted your friend request.");
            } catch (\Throwable $e) {
                error_log("Friend Accept Email Failed: " . $e->getMessage());
            }
        }
        header('Location: ' . \Nexus\Core\TenantContext::getBasePath() . '/connections');
    }

    public function remove()
    {
        $this->authCheck();
        $id = (int) ($_POST['connection_id'] ?? 0);
        $currentUserId = $_SESSION['user_id'];

        // SECURITY: Verify current user is part of this connection
        $removed = Connection::removeConnection($id, $currentUserId);
        if (!$removed) {
            $_SESSION['error'] = 'You are not authorized to remove this connection.';
        }
        header('Location: ' . \Nexus\Core\TenantContext::getBasePath() . '/connections');
        exit;
    }

    private function authCheck()
    {
        if (!isset($_SESSION['user_id'])) {
            header('Location: ' . \Nexus\Core\TenantContext::getBasePath() . '/login');
            exit;
        }
    }
}
