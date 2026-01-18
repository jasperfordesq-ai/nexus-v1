<?php

namespace Nexus\Controllers;

use Nexus\Core\View;
use Nexus\Core\TenantContext;
use Nexus\Services\FederatedMessageService;
use Nexus\Services\FederationFeatureService;
use Nexus\Services\FederationUserService;
use Nexus\Services\FederationGateway;

/**
 * Federated Message Controller
 *
 * Handles cross-tenant messaging between federated timebank members.
 */
class FederatedMessageController
{
    /**
     * Federated inbox - list conversations with federated users
     */
    public function index()
    {
        if (!isset($_SESSION['user_id'])) {
            header('Location: ' . TenantContext::getBasePath() . '/login');
            exit;
        }

        $tenantId = TenantContext::getId();
        $userId = $_SESSION['user_id'];

        // Check if federation is enabled
        if (!$this->isFederationEnabled($tenantId)) {
            View::render('federation/not-available', [
                'pageTitle' => 'Federation Not Available'
            ]);
            return;
        }

        // Check if user has opted into federation
        $userSettings = FederationUserService::getUserSettings($userId);
        if (!$userSettings['federation_optin']) {
            View::render('federation/messages/opt-in-required', [
                'pageTitle' => 'Federation Opt-In Required'
            ]);
            return;
        }

        // Get federated inbox
        $conversations = FederatedMessageService::getInbox($userId);
        $unreadCount = FederatedMessageService::getUnreadCount($userId);

        View::render('federation/messages/index', [
            'conversations' => $conversations,
            'unreadCount' => $unreadCount,
            'pageTitle' => 'Federated Messages'
        ]);
    }

    /**
     * View conversation thread with a federated user
     */
    public function thread($otherUserId)
    {
        if (!isset($_SESSION['user_id'])) {
            header('Location: ' . TenantContext::getBasePath() . '/login');
            exit;
        }

        $tenantId = TenantContext::getId();
        $userId = $_SESSION['user_id'];
        $otherUserId = (int)$otherUserId;

        // Get other tenant ID from query param
        $otherTenantId = isset($_GET['tenant']) ? (int)$_GET['tenant'] : 0;

        if (!$otherTenantId) {
            http_response_code(400);
            View::render('errors/400', ['message' => 'Missing tenant parameter']);
            return;
        }

        // Check federation enabled
        if (!$this->isFederationEnabled($tenantId)) {
            View::render('federation/not-available', [
                'pageTitle' => 'Federation Not Available'
            ]);
            return;
        }

        // Check user opted in
        $userSettings = FederationUserService::getUserSettings($userId);
        if (!$userSettings['federation_optin']) {
            View::render('federation/messages/opt-in-required', [
                'pageTitle' => 'Federation Opt-In Required'
            ]);
            return;
        }

        // Get the other user's info
        $otherUser = FederatedMessageService::getFederatedUserInfo($otherUserId, $otherTenantId);
        if (!$otherUser) {
            http_response_code(404);
            View::render('errors/404', ['message' => 'User not found']);
            return;
        }

        // Mark thread as read
        FederatedMessageService::markThreadAsRead($userId, $otherUserId, $otherTenantId);

        // Get messages
        $messages = FederatedMessageService::getThread($userId, $otherUserId, $otherTenantId);

        // Check if can message this user and why not
        $canMessageResult = FederationGateway::canSendMessage($userId, $tenantId, $otherUserId, $otherTenantId);
        $canMessage = $canMessageResult['allowed'] && $otherUser['messaging_enabled_federated'];

        // Determine the reason messaging is disabled
        $cannotMessageReason = '';
        if (!$canMessage) {
            if (!$otherUser['federation_optin']) {
                $cannotMessageReason = 'This member has disabled federation.';
            } elseif (!$otherUser['messaging_enabled_federated']) {
                $cannotMessageReason = 'This member has disabled federated messaging.';
            } elseif (!$canMessageResult['allowed']) {
                $cannotMessageReason = $canMessageResult['reason'] ?? 'Messaging is not enabled for this partnership.';
            }
        }

        View::render('federation/messages/thread', [
            'messages' => $messages,
            'otherUser' => $otherUser,
            'otherTenantId' => $otherTenantId,
            'canMessage' => $canMessage,
            'cannotMessageReason' => $cannotMessageReason,
            'pageTitle' => 'Chat with ' . ($otherUser['name'] ?? 'Member')
        ]);
    }

    /**
     * Send a federated message (POST)
     */
    public function send()
    {
        \Nexus\Core\Csrf::verifyOrDie();

        if (!isset($_SESSION['user_id'])) {
            if ($this->isAjax()) {
                $this->jsonResponse(['success' => false, 'error' => 'Unauthorized'], 401);
            }
            header('Location: ' . TenantContext::getBasePath() . '/login');
            exit;
        }

        $tenantId = TenantContext::getId();
        $userId = $_SESSION['user_id'];

        $receiverId = (int)($_POST['receiver_id'] ?? 0);
        $receiverTenantId = (int)($_POST['receiver_tenant_id'] ?? 0);
        $subject = trim($_POST['subject'] ?? '');
        $body = trim($_POST['body'] ?? '');

        if (!$receiverId || !$receiverTenantId || empty($body)) {
            if ($this->isAjax()) {
                $this->jsonResponse(['success' => false, 'error' => 'Missing required fields'], 400);
            }
            $_SESSION['flash_error'] = 'Please enter a message';
            header('Location: ' . $_SERVER['HTTP_REFERER']);
            exit;
        }

        // Send the message
        $result = FederatedMessageService::sendMessage($userId, $receiverId, $receiverTenantId, $subject, $body);

        if ($this->isAjax()) {
            $this->jsonResponse($result);
            exit;
        }

        if ($result['success']) {
            $_SESSION['flash_success'] = 'Message sent successfully';
        } else {
            $_SESSION['flash_error'] = $result['error'] ?? 'Failed to send message';
        }

        header('Location: ' . TenantContext::getBasePath() . '/federation/messages/' . $receiverId . '?tenant=' . $receiverTenantId);
        exit;
    }

    /**
     * API endpoint for getting messages (for AJAX refresh)
     */
    public function api()
    {
        header('Content-Type: application/json');

        if (!isset($_SESSION['user_id'])) {
            $this->jsonResponse(['success' => false, 'error' => 'Unauthorized'], 401);
            exit;
        }

        $userId = $_SESSION['user_id'];
        $action = $_GET['action'] ?? 'inbox';

        switch ($action) {
            case 'inbox':
                $conversations = FederatedMessageService::getInbox($userId);
                $this->jsonResponse([
                    'success' => true,
                    'conversations' => $conversations,
                    'unreadCount' => FederatedMessageService::getUnreadCount($userId)
                ]);
                break;

            case 'thread':
                $otherUserId = (int)($_GET['user'] ?? 0);
                $otherTenantId = (int)($_GET['tenant'] ?? 0);

                if (!$otherUserId || !$otherTenantId) {
                    $this->jsonResponse(['success' => false, 'error' => 'Missing parameters'], 400);
                    break;
                }

                $messages = FederatedMessageService::getThread($userId, $otherUserId, $otherTenantId);
                $this->jsonResponse([
                    'success' => true,
                    'messages' => $messages
                ]);
                break;

            case 'unread':
                $this->jsonResponse([
                    'success' => true,
                    'count' => FederatedMessageService::getUnreadCount($userId)
                ]);
                break;

            default:
                $this->jsonResponse(['success' => false, 'error' => 'Unknown action'], 400);
        }
        exit;
    }

    /**
     * Mark message as read (AJAX)
     */
    public function markRead()
    {
        header('Content-Type: application/json');

        if (!isset($_SESSION['user_id'])) {
            $this->jsonResponse(['success' => false, 'error' => 'Unauthorized'], 401);
            exit;
        }

        $input = json_decode(file_get_contents('php://input'), true) ?? $_POST;
        $messageId = (int)($input['message_id'] ?? 0);

        if (!$messageId) {
            $this->jsonResponse(['success' => false, 'error' => 'Message ID required'], 400);
            exit;
        }

        $result = FederatedMessageService::markAsRead($messageId, $_SESSION['user_id']);
        $this->jsonResponse(['success' => $result]);
        exit;
    }

    /**
     * Check if federation is enabled for tenant
     */
    private function isFederationEnabled(int $tenantId): bool
    {
        return FederationFeatureService::isGloballyEnabled()
            && FederationFeatureService::isTenantWhitelisted($tenantId)
            && FederationFeatureService::isTenantFederationEnabled($tenantId);
    }

    /**
     * Check if request is AJAX
     */
    private function isAjax(): bool
    {
        return !empty($_SERVER['HTTP_X_REQUESTED_WITH'])
            && strtolower($_SERVER['HTTP_X_REQUESTED_WITH']) === 'xmlhttprequest';
    }

    /**
     * Send JSON response
     */
    private function jsonResponse(array $data, int $statusCode = 200): void
    {
        http_response_code($statusCode);
        header('Content-Type: application/json');
        echo json_encode($data);
    }
}
