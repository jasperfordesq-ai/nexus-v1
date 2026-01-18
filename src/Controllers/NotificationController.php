<?php

namespace Nexus\Controllers;

use Nexus\Core\View;
use Nexus\Core\App;
use Nexus\Models\Notification;
use Nexus\Core\Csrf;

class NotificationController
{
    // API: Get latest notifications (for dropdown)
    public function index()
    {
        header('Content-Type: application/json');
        header('Cache-Control: no-cache, no-store, must-revalidate');
        header('Pragma: no-cache');

        $userId = $_SESSION['user_id'] ?? 0;
        if (!$userId) {
            echo json_encode(['success' => false, 'error' => 'Unauthorized']);
            exit;
        }

        $notifications = Notification::getLatest($userId, 10);
        $unreadCount = Notification::countUnread($userId);

        echo json_encode([
            'success' => true,
            'notifications' => $notifications,
            'unread_count' => $unreadCount
        ]);
        exit;
    }

    // API: Mark as read
    public function markRead()
    {
        // Enforce Clean Output Buffering
        if (ob_get_length()) ob_clean();
        header('Content-Type: application/json');

        $userId = $_SESSION['user_id'] ?? 0;
        if (!$userId) {
            echo json_encode(['success' => false, 'error' => 'Unauthorized']);
            exit;
        }

        // CSRF Verification - check multiple header name variations
        $headers = getallheaders();
        $token = $headers['X-CSRF-Token']
              ?? $headers['X-Csrf-Token']
              ?? $headers['x-csrf-token']
              ?? $headers['X-CSRF-TOKEN']
              ?? $_SERVER['HTTP_X_CSRF_TOKEN']
              ?? '';

        if (!\Nexus\Core\Csrf::verify($token)) {
            http_response_code(403);
            error_log("CSRF Failed for markRead. Token: '$token'. Available headers: " . json_encode(array_keys($headers)));
            echo json_encode(['success' => false, 'error' => 'Invalid CSRF Token']);
            exit;
        }

        $raw = file_get_contents('php://input');
        $input = json_decode($raw, true);


        if (!$input && !empty($_POST)) {
            $input = $_POST;
        }

        if (isset($input['id'])) {
            Notification::markRead($input['id'], $userId);
            echo json_encode(['success' => true]);
            exit;
        } elseif (isset($input['all']) && ($input['all'] === true || $input['all'] === 'true')) {
            Notification::markAllRead($userId);
            echo json_encode(['success' => true]);
            exit;
        }

        echo json_encode(['success' => false, 'error' => 'Missing id or all parameter']);
        exit;
    }
    // API: Lightweight Polling (Unread Count)
    public function poll()
    {
        header('Content-Type: application/json');
        header('Cache-Control: no-cache, no-store, must-revalidate');
        header('Pragma: no-cache');

        $userId = $_SESSION['user_id'] ?? 0;
        if (!$userId) {
            echo json_encode(['success' => false, 'count' => 0]);
            exit;
        }

        // Optimize: Only count unread, no fetches
        $unreadCount = Notification::countUnread($userId);

        echo json_encode([
            'success' => true,
            'count' => $unreadCount
        ]);
        exit;
    }

    // API: Delete Notification
    public function delete()
    {
        // Enforce Clean Output Buffering
        if (ob_get_length()) ob_clean();
        header('Content-Type: application/json');

        $userId = $_SESSION['user_id'] ?? 0;
        if (!$userId) {
            echo json_encode(['success' => false, 'error' => 'Unauthorized']);
            exit;
        }

        // CSRF Verification
        $headers = getallheaders();
        $token = $headers['X-CSRF-Token'] ?? $headers['X-Csrf-Token'] ?? '';
        if (!\Nexus\Core\Csrf::verify($token)) {
            http_response_code(403);
            echo json_encode(['success' => false, 'error' => 'Invalid CSRF Token']);
            exit;
        }

        $raw = file_get_contents('php://input');
        $input = json_decode($raw, true);

        if (!$input && !empty($_POST)) {
            $input = $_POST;
        }

        if (isset($input['id'])) {
            Notification::delete($input['id'], $userId);
        } elseif (isset($input['all']) && ($input['all'] === true || $input['all'] === 'true')) {
            Notification::deleteAll($userId);
        } else {
            echo json_encode(['success' => false, 'error' => 'Missing ID or Action', 'debug_input' => $input]);
            exit;
        }

        echo json_encode(['success' => true]);
        exit;
    }

    // PAGE: Manage Notifications
    public function manage()
    {
        // Clean any output buffer to allow redirects
        if (ob_get_length()) ob_clean();

        $userId = $_SESSION['user_id'] ?? 0;
        if (!$userId) {
            header('Location: ' . \Nexus\Core\TenantContext::getBasePath() . '/login');
            exit;
        }

        // Handle Mark All Read POST action BEFORE any output
        if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['mark_all_read'])) {
            \Nexus\Core\Csrf::verifyOrDie();
            Notification::markAllRead($userId);
            header('Location: ' . \Nexus\Core\TenantContext::getBasePath() . '/notifications');
            exit;
        }

        $notifications = Notification::getAll($userId, 50); // Get last 50 (all, including read)
        $allNotifications = $notifications; // Alias for modern layout compatibility

        View::render('notifications/index', [
            'notifications' => $notifications,
            'allNotifications' => $allNotifications
        ]);
    }
}
