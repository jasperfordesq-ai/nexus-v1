<?php

namespace Nexus\Controllers;

use Nexus\Core\TenantContext;
use Nexus\Models\Message;
use Nexus\Models\User;
use Nexus\Core\Mailer;
use Nexus\Core\EmailTemplate;
use Nexus\Services\RealtimeService;

class MessageController
{
    /**
     * Helper: Resolve View Path based on Tenant Layout
     * Uses bridge files that handle layout detection and header/footer wrapping
     */
    private function getViewPath($viewName)
    {
        $baseDir = __DIR__ . '/../../views/';

        // Use bridge files that handle layout routing with proper header/footer
        $bridges = [
            'thread' => 'messages/thread.php',
            'index'  => 'messages/index.php'
        ];

        $relativePath = $bridges[$viewName] ?? $bridges['index'];
        $fullPath = $baseDir . $relativePath;

        if (!file_exists($fullPath)) {
            throw new \Exception("View file not found: $relativePath");
        }

        return $fullPath;
    }

    // Inbox
    public function index()
    {
        $isApi = (strpos($_SERVER['REQUEST_URI'], '/api') === 0);

        if (!isset($_SESSION['user_id'])) {
            if ($isApi) {
                http_response_code(401);
                echo json_encode(['error' => 'Unauthorized']);
                exit;
            }
            header('Location: ' . TenantContext::getBasePath() . '/login');
            exit;
        }

        try {
            $userId = $_SESSION['user_id'];
            $tenantId = TenantContext::getId();

            $threads = Message::getInbox($userId, $tenantId);
            $pageTitle = 'Inbox';

            if ($isApi) {
                header('Content-Type: application/json');
                echo json_encode(['data' => $threads]);
                exit;
            }

            require $this->getViewPath('index');
        } catch (\Throwable $e) {
            die("Error loading messages: " . $e->getMessage());
        }
    }

    // View Thread (Chat)
    public function show($id)
    {
        $otherUserId = $id;

        if (!isset($_SESSION['user_id'])) {
            header('Location: ' . TenantContext::getBasePath() . '/login');
            exit;
        }

        try {
            if ($otherUserId == $_SESSION['user_id']) {
                header('Location: ' . TenantContext::getBasePath() . '/messages');
                exit;
            }

            $userId = $_SESSION['user_id'];
            $tenantId = TenantContext::getId();

            // Mark Read & Fetch
            Message::markThreadRead($tenantId, $userId, $otherUserId);
            $messages = Message::getThread($tenantId, $userId, $otherUserId);
            $otherUser = User::findById($otherUserId);

            if (!$otherUser) {
                die("User not found.");
            }

            // CHECK FOR AJAX REQUEST (From Messenger SPA)
            if (!empty($_GET['ajax'])) {
                $baseDir = __DIR__ . '/../../views/';
                // Use layout-specific message partial
                $layout = \Nexus\Services\LayoutHelper::get();
                $partialPath = $baseDir . $layout . '/messages/messages_thread_partial.php';
                if (file_exists($partialPath)) {
                    require $partialPath;
                } else {
                    require $baseDir . 'modern/messages/messages_thread_partial.php';
                }
                exit; // Stop further rendering
            }

            $pageTitle = 'Chat with ' . $otherUser['name'];
            require $this->getViewPath('thread');
        } catch (\Throwable $e) {
            if (!empty($_GET['ajax'])) {
                http_response_code(500);
                echo "Error loading chat: " . $e->getMessage();
                exit;
            }
            die("Error loading thread: " . $e->getMessage());
        }
    }

    // Create/Compose Redirect
    public function create()
    {
        if (!isset($_SESSION['user_id'])) {
            header('Location: ' . TenantContext::getBasePath() . '/login');
            exit;
        }

        // If "to" parameter exists, redirect to that conversation
        if (!empty($_GET['to'])) {
            $otherUserId = (int)$_GET['to']; // Sanitize

            // Check if this is a federated message request
            if (!empty($_GET['federated']) || !empty($_GET['tenant'])) {
                // Get the tenant ID - either from param or look up from user
                $otherTenantId = !empty($_GET['tenant']) ? (int)$_GET['tenant'] : null;

                if (!$otherTenantId) {
                    // Try to find the user's tenant from the database
                    $otherUser = User::findById($otherUserId, false); // Don't enforce tenant
                    if ($otherUser && isset($otherUser['tenant_id'])) {
                        $otherTenantId = $otherUser['tenant_id'];
                    }
                }

                if ($otherTenantId && $otherTenantId != TenantContext::getId()) {
                    // Redirect to federated messages with tenant parameter
                    header('Location: ' . TenantContext::getBasePath() . '/federation/messages/' . $otherUserId . '?tenant=' . $otherTenantId);
                    exit;
                }
            }

            // Same tenant - use regular messages
            header('Location: ' . TenantContext::getBasePath() . '/messages/' . $otherUserId);
            exit;
        }

        // Default to inbox if no target
        header('Location: ' . TenantContext::getBasePath() . '/messages');
        exit;
    }

    // New Message / User Search (Mobile)
    public function newMessage()
    {
        if (!isset($_SESSION['user_id'])) {
            header('Location: ' . TenantContext::getBasePath() . '/login');
            exit;
        }

        $userId = $_SESSION['user_id'];
        $tenantId = TenantContext::getTenantId();

        // 2026-01-17: Removed abandoned mobile app redirect
        // All devices now use the responsive messages page with modal
        header('Location: ' . TenantContext::getBasePath() . '/messages');
        exit;
    }

    // Send Message
    public function store()
    {
        \Nexus\Core\Csrf::verifyOrDie();
        if (!isset($_SESSION['user_id'])) {
            header('Location: ' . TenantContext::getBasePath() . '/login');
            exit;
        }

        try {
            $senderId = $_SESSION['user_id'];
            $receiverId = $_POST['receiver_id'] ?? null;
            $body = trim($_POST['body'] ?? '');
            $subject = $_POST['subject'] ?? '';

            if (!$receiverId || empty($body)) {
                header('Location: ' . $_SERVER['HTTP_REFERER']);
                exit;
            }

            $tenant = TenantContext::get();
            $tenantId = $tenant['id'];

            // 1. Save to DB
            Message::create($tenantId, $senderId, $receiverId, $subject, $body);

            // 2. Send Email Notification
            $db = \Nexus\Core\Database::getConnection();
            $stmt = $db->prepare("SELECT name FROM users WHERE id = ?");
            $stmt->execute([$senderId]);
            $sender = $stmt->fetch();

            $stmt = $db->prepare("SELECT name, email FROM users WHERE id = ?");
            $stmt->execute([$receiverId]);
            $receiver = $stmt->fetch();

            if ($receiver && $receiver['email']) {
                $protocol = (isset($_SERVER['HTTPS']) && $_SERVER['HTTPS'] === 'on') ? "https" : "http";
                $replyLink = $protocol . "://" . $_SERVER['HTTP_HOST'] . TenantContext::getBasePath() . "/messages/" . $senderId;

                if (class_exists('Nexus\Core\EmailTemplate')) {
                    $emailHtml = EmailTemplate::render(
                        "New Message",
                        "You have received a new message from " . htmlspecialchars($sender['name']),
                        nl2br(htmlspecialchars($body)),
                        "Reply to Message",
                        $replyLink,
                        $tenant['name']
                    );
                } else {
                    $emailHtml = "New message from " . $sender['name'] . ":<br><br>" . nl2br($body) . "<br><br><a href='$replyLink'>Reply Here</a>";
                }

                try {
                    $mailer = new Mailer();
                    $mailer->send($receiver['email'], "New Message from " . $sender['name'], $emailHtml);
                } catch (\Throwable $e) {
                    error_log("Message Notification Failed: " . $e->getMessage());
                }
            }

            // 3. Create In-App Notification (Instant)
            \Nexus\Models\Notification::create(
                $receiverId,
                "New message from " . $sender['name'],
                TenantContext::getBasePath() . "/messages/" . $senderId,
                'message'
            );

            // 4. Gamification: Check message badges
            try {
                \Nexus\Services\GamificationService::checkMessageBadges($senderId);
            } catch (\Throwable $e) {
                error_log("Gamification message error: " . $e->getMessage());
            }

            // Redirect back to thread
            header('Location: ' . TenantContext::getBasePath() . '/messages/' . $receiverId);
            exit;
        } catch (\Throwable $e) {
            die("Error sending message: " . $e->getMessage());
        }
    }


    /**
     * Delete a conversation with another user
     */
    public function destroy($id)
    {
        $isAjax = !empty($_SERVER['HTTP_X_REQUESTED_WITH']) &&
                  strtolower($_SERVER['HTTP_X_REQUESTED_WITH']) === 'xmlhttprequest';

        if (!$isAjax) {
            \Nexus\Core\Csrf::verifyOrDie();
        }

        if (!isset($_SESSION['user_id'])) {
            if ($isAjax) {
                http_response_code(401);
                header('Content-Type: application/json');
                echo json_encode(['error' => 'Unauthorized']);
                exit;
            }
            header('Location: ' . TenantContext::getBasePath() . '/login');
            exit;
        }

        try {
            $userId = $_SESSION['user_id'];
            $otherUserId = (int)$id;
            $tenantId = TenantContext::getId();

            $deleted = Message::deleteConversation($tenantId, $userId, $otherUserId);

            if ($isAjax) {
                header('Content-Type: application/json');
                echo json_encode(['success' => true, 'deleted' => $deleted]);
                exit;
            }

            header('Location: ' . TenantContext::getBasePath() . '/messages');
            exit;
        } catch (\Throwable $e) {
            if ($isAjax) {
                http_response_code(500);
                header('Content-Type: application/json');
                echo json_encode(['error' => $e->getMessage()]);
                exit;
            }
            die("Error deleting conversation: " . $e->getMessage());
        }
    }

    /**
     * Delete a single message (API endpoint)
     */
    public function deleteMessage()
    {
        if (ob_get_length()) ob_clean();
        header('Content-Type: application/json');

        if (!isset($_SESSION['user_id'])) {
            http_response_code(401);
            echo json_encode(['success' => false, 'error' => 'Unauthorized']);
            exit;
        }

        // CSRF verification
        $headers = getallheaders();
        $token = $headers['X-CSRF-Token'] ?? $headers['X-Csrf-Token'] ?? $headers['x-csrf-token'] ?? '';
        if (!\Nexus\Core\Csrf::verify($token)) {
            http_response_code(403);
            echo json_encode(['success' => false, 'error' => 'Invalid CSRF token']);
            exit;
        }

        // Get input
        $raw = file_get_contents('php://input');
        $input = json_decode($raw, true);
        if (!$input && !empty($_POST)) {
            $input = $_POST;
        }

        $messageId = (int)($input['message_id'] ?? 0);
        if (!$messageId) {
            http_response_code(400);
            echo json_encode(['success' => false, 'error' => 'Message ID required']);
            exit;
        }

        try {
            $userId = $_SESSION['user_id'];
            $tenantId = TenantContext::getId();

            $result = Message::deleteSingle($tenantId, $messageId, $userId);

            if ($result === false) {
                http_response_code(403);
                echo json_encode(['success' => false, 'error' => 'Message not found or access denied']);
                exit;
            }

            echo json_encode(['success' => true, 'deleted' => $result['deleted']]);
            exit;
        } catch (\Throwable $e) {
            http_response_code(500);
            echo json_encode(['success' => false, 'error' => $e->getMessage()]);
            exit;
        }
    }

    /**
     * Delete entire conversation (API endpoint)
     */
    public function deleteConversation()
    {
        if (ob_get_length()) ob_clean();
        header('Content-Type: application/json');

        if (!isset($_SESSION['user_id'])) {
            http_response_code(401);
            echo json_encode(['success' => false, 'error' => 'Unauthorized']);
            exit;
        }

        // CSRF verification
        $headers = getallheaders();
        $token = $headers['X-CSRF-Token'] ?? $headers['X-Csrf-Token'] ?? $headers['x-csrf-token'] ?? '';
        if (!\Nexus\Core\Csrf::verify($token)) {
            http_response_code(403);
            echo json_encode(['success' => false, 'error' => 'Invalid CSRF token']);
            exit;
        }

        // Get input
        $raw = file_get_contents('php://input');
        $input = json_decode($raw, true);
        if (!$input && !empty($_POST)) {
            $input = $_POST;
        }

        $otherUserId = (int)($input['other_user_id'] ?? 0);
        if (!$otherUserId) {
            http_response_code(400);
            echo json_encode(['success' => false, 'error' => 'Other user ID required']);
            exit;
        }

        try {
            $userId = $_SESSION['user_id'];
            $tenantId = TenantContext::getId();

            $deleted = Message::deleteConversation($tenantId, $userId, $otherUserId);

            echo json_encode(['success' => true, 'deleted' => $deleted]);
            exit;
        } catch (\Throwable $e) {
            http_response_code(500);
            echo json_encode(['success' => false, 'error' => $e->getMessage()]);
            exit;
        }
    }

    /**
     * Toggle reaction on a message (API endpoint)
     */
    public function toggleReaction()
    {
        if (ob_get_length()) ob_clean();
        header('Content-Type: application/json');

        if (!isset($_SESSION['user_id'])) {
            http_response_code(401);
            echo json_encode(['success' => false, 'error' => 'Unauthorized']);
            exit;
        }

        // CSRF verification
        $headers = getallheaders();
        $token = $headers['X-CSRF-Token'] ?? $headers['X-Csrf-Token'] ?? $headers['x-csrf-token'] ?? '';
        if (!\Nexus\Core\Csrf::verify($token)) {
            http_response_code(403);
            echo json_encode(['success' => false, 'error' => 'Invalid CSRF token']);
            exit;
        }

        // Get input
        $raw = file_get_contents('php://input');
        $input = json_decode($raw, true);
        if (!$input && !empty($_POST)) {
            $input = $_POST;
        }

        $messageId = (int)($input['message_id'] ?? 0);
        $emoji = trim($input['emoji'] ?? '');

        if (!$messageId) {
            http_response_code(400);
            echo json_encode(['success' => false, 'error' => 'Message ID required']);
            exit;
        }

        if (!$emoji) {
            http_response_code(400);
            echo json_encode(['success' => false, 'error' => 'Emoji required']);
            exit;
        }

        // Validate emoji (basic check - allow common emojis)
        $allowedEmojis = ['👍', '❤️', '😂', '😮', '😢', '🙏', '👎', '🔥', '💯', '🎉'];
        if (!in_array($emoji, $allowedEmojis)) {
            http_response_code(400);
            echo json_encode(['success' => false, 'error' => 'Invalid emoji']);
            exit;
        }

        try {
            $userId = $_SESSION['user_id'];
            $tenantId = TenantContext::getId();

            $result = Message::toggleReaction($tenantId, $messageId, $userId, $emoji);

            echo json_encode($result);
            exit;
        } catch (\Throwable $e) {
            http_response_code(500);
            echo json_encode(['success' => false, 'error' => $e->getMessage()]);
            exit;
        }
    }

    /**
     * Get reactions for multiple messages (batch API endpoint)
     */
    public function getReactionsBatch()
    {
        if (ob_get_length()) ob_clean();
        header('Content-Type: application/json');

        if (!isset($_SESSION['user_id'])) {
            http_response_code(401);
            echo json_encode(['success' => false, 'error' => 'Unauthorized']);
            exit;
        }

        $idsParam = $_GET['ids'] ?? '';
        if (empty($idsParam)) {
            echo json_encode(['success' => true, 'reactions' => []]);
            exit;
        }

        // Parse and validate IDs
        $ids = array_filter(array_map('intval', explode(',', $idsParam)));
        if (empty($ids)) {
            echo json_encode(['success' => true, 'reactions' => []]);
            exit;
        }

        // Limit to 100 messages at a time
        $ids = array_slice($ids, 0, 100);

        try {
            $reactions = Message::getReactionsBatch($ids);

            echo json_encode(['success' => true, 'reactions' => $reactions]);
            exit;
        } catch (\Throwable $e) {
            http_response_code(500);
            echo json_encode(['success' => false, 'error' => $e->getMessage()]);
            exit;
        }
    }
}
