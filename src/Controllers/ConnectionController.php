<?php

namespace Nexus\Controllers;

use Nexus\Models\Connection;
use Nexus\Models\Notification;
use Nexus\Models\User;
use Nexus\Core\Mailer;
use Nexus\Core\Database;

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

        header('Location: ' . $_SERVER['HTTP_REFERER']);
    }

    public function accept()
    {
        $this->authCheck();
        $id = $_POST['connection_id'];

        // Fetch connection to get requester for email
        $db = Database::getConnection();
        $conn = $db->query("SELECT * FROM connections WHERE id = ?", [$id])->fetch();

        Connection::acceptRequest($id);

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
        $id = $_POST['connection_id'];
        Connection::removeConnection($id);
        header('Location: /connections'); // Refresh
    }

    private function authCheck()
    {
        if (!isset($_SESSION['user_id'])) {
            header('Location: ' . \Nexus\Core\TenantContext::getBasePath() . '/login');
            exit;
        }
    }
}
