<?php

namespace Nexus\Controllers;

use Nexus\Core\View;
use Nexus\Core\TenantContext;
use Nexus\Core\Database;
use Nexus\Services\FederatedMessageService;
use Nexus\Services\FederationFeatureService;
use Nexus\Services\FederationUserService;
use Nexus\Services\FederationGateway;
use Nexus\Services\FederationExternalPartnerService;
use Nexus\Services\FederationExternalApiClient;
use Nexus\Helpers\UrlHelper;

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

        // Get partner communities for scope switcher (if any)
        $partnerCommunities = $this->getPartnerCommunities($tenantId);
        $currentScope = $_GET['scope'] ?? 'all';

        $viewPath = 'federation/messages/index';

        View::render($viewPath, [
            'conversations' => $conversations,
            'unreadCount' => $unreadCount,
            'partnerCommunities' => $partnerCommunities,
            'currentScope' => $currentScope,
            'pageTitle' => 'Federated Messages'
        ]);
    }

    /**
     * Compose a new federated message
     * Supports both internal federated members and external partner members
     */
    public function compose()
    {
        if (!isset($_SESSION['user_id'])) {
            header('Location: ' . TenantContext::getBasePath() . '/login');
            exit;
        }

        $tenantId = TenantContext::getId();
        $userId = $_SESSION['user_id'];
        $basePath = TenantContext::getBasePath();

        // Check if federation is enabled
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

        // Determine if this is an external partner member or internal federated member
        $externalPartnerId = isset($_GET['external_partner']) ? (int)$_GET['external_partner'] : null;
        $memberId = isset($_GET['member_id']) ? (int)$_GET['member_id'] : (isset($_GET['to']) ? (int)$_GET['to'] : null);
        $memberTenantId = isset($_GET['tenant']) ? (int)$_GET['tenant'] : null;
        $externalTenantId = isset($_GET['external_tenant']) ? (int)$_GET['external_tenant'] : 1;
        $memberName = $_GET['member_name'] ?? '';

        $recipient = null;
        $isExternalMember = false;
        $externalPartner = null;

        if ($externalPartnerId && $memberId) {
            // External partner member
            $isExternalMember = true;
            $externalPartner = FederationExternalPartnerService::getById($externalPartnerId, $tenantId);

            if (!$externalPartner || $externalPartner['status'] !== 'active') {
                $_SESSION['flash_error'] = 'External partner not found or inactive.';
                header('Location: ' . $basePath . '/federation/members');
                exit;
            }

            if (!$externalPartner['allow_messaging']) {
                $_SESSION['flash_error'] = 'Messaging is not enabled with this external partner.';
                header('Location: ' . $basePath . '/federation/members');
                exit;
            }

            // Fetch member info from external partner if we don't have the name
            if (empty($memberName)) {
                try {
                    $client = new FederationExternalApiClient($externalPartner);
                    $result = $client->getMember($memberId);
                    if ($result['success']) {
                        $memberData = $result['data']['member'] ?? $result['data'] ?? [];
                        $memberName = $memberData['name'] ?? trim(($memberData['first_name'] ?? '') . ' ' . ($memberData['last_name'] ?? '')) ?: 'Member';
                        // Get external tenant ID from member data if available
                        $externalTenantId = $memberData['timebank']['id'] ?? $memberData['tenant_id'] ?? $externalTenantId;
                    }
                } catch (\Exception $e) {
                    error_log("FederatedMessageController::compose - Failed to fetch member: " . $e->getMessage());
                }
            }

            $recipient = [
                'id' => $memberId,
                'name' => urldecode($memberName) ?: 'External Member',
                'tenant_id' => null,
                'tenant_name' => $externalPartner['partner_name'] ?: $externalPartner['name'],
                'is_external' => true,
                'external_partner_id' => $externalPartnerId,
                'external_tenant_id' => $externalTenantId
            ];

        } elseif ($memberId && $memberTenantId) {
            // Internal federated member
            $recipient = FederatedMessageService::getFederatedUserInfo($memberId, $memberTenantId);

            if (!$recipient) {
                $_SESSION['flash_error'] = 'Member not found.';
                header('Location: ' . $basePath . '/federation/members');
                exit;
            }

            // Check if can message
            $canMessageResult = FederationGateway::canSendMessage($userId, $tenantId, $memberId, $memberTenantId);
            if (!$canMessageResult['allowed']) {
                $_SESSION['flash_error'] = $canMessageResult['reason'] ?? 'Cannot send messages to this member.';
                header('Location: ' . $basePath . '/federation/members');
                exit;
            }

            $recipient['is_external'] = false;
        }

        View::render('federation/messages/compose', [
            'recipient' => $recipient,
            'isExternalMember' => $isExternalMember,
            'externalPartner' => $externalPartner,
            'pageTitle' => $recipient ? 'Message ' . ($recipient['name'] ?? 'Member') : 'New Federated Message'
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
     * Supports both internal federated members and external partner members
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
        $basePath = TenantContext::getBasePath();

        $receiverId = (int)($_POST['receiver_id'] ?? 0);
        $receiverTenantId = (int)($_POST['receiver_tenant_id'] ?? 0);
        $externalPartnerId = (int)($_POST['external_partner_id'] ?? 0);
        $subject = trim($_POST['subject'] ?? '');
        $body = trim($_POST['body'] ?? '');

        if (!$receiverId || empty($body)) {
            if ($this->isAjax()) {
                $this->jsonResponse(['success' => false, 'error' => 'Missing required fields'], 400);
            }
            $_SESSION['flash_error'] = 'Please enter a message';
            header('Location: ' . UrlHelper::safeReferer($basePath . '/federation/messages'));
            exit;
        }

        // Handle external partner message
        if ($externalPartnerId) {
            $receiverName = trim($_POST['receiver_name'] ?? 'Member');
            $externalTenantId = (int)($_POST['external_tenant_id'] ?? 1);
            $result = $this->sendExternalMessage($tenantId, $userId, $externalPartnerId, $receiverId, $receiverName, $externalTenantId, $subject, $body);

            if ($this->isAjax()) {
                $this->jsonResponse($result);
                exit;
            }

            if ($result['success']) {
                $_SESSION['flash_success'] = 'Message sent to external partner successfully';
                header('Location: ' . $basePath . '/federation/messages');
            } else {
                $_SESSION['flash_error'] = $result['error'] ?? 'Failed to send message';
                header('Location: ' . UrlHelper::safeReferer($basePath . '/federation/messages'));
            }
            exit;
        }

        // Handle internal federated message
        if (!$receiverTenantId) {
            if ($this->isAjax()) {
                $this->jsonResponse(['success' => false, 'error' => 'Missing tenant ID'], 400);
            }
            $_SESSION['flash_error'] = 'Missing recipient information';
            header('Location: ' . UrlHelper::safeReferer($basePath . '/federation/messages'));
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

        header('Location: ' . $basePath . '/federation/messages/' . $receiverId . '?tenant=' . $receiverTenantId);
        exit;
    }

    /**
     * Send a message to an external partner member via API
     */
    private function sendExternalMessage(int $tenantId, int $userId, int $externalPartnerId, int $receiverId, string $receiverName, int $externalTenantId, string $subject, string $body): array
    {
        // Get the external partner
        $partner = FederationExternalPartnerService::getById($externalPartnerId, $tenantId);

        if (!$partner || $partner['status'] !== 'active') {
            return ['success' => false, 'error' => 'External partner not found or inactive'];
        }

        if (!$partner['allow_messaging']) {
            return ['success' => false, 'error' => 'Messaging is not enabled with this external partner'];
        }

        // Get sender info
        $sender = Database::query(
            "SELECT id, first_name, last_name, name, email FROM users WHERE id = ? AND tenant_id = ?",
            [$userId, $tenantId]
        )->fetch(\PDO::FETCH_ASSOC);

        if (!$sender) {
            return ['success' => false, 'error' => 'Sender not found'];
        }

        $senderName = $sender['name'] ?: trim($sender['first_name'] . ' ' . $sender['last_name']);
        $partnerName = $partner['partner_name'] ?: $partner['name'];

        try {
            $client = new FederationExternalApiClient($partner);

            $result = $client->sendMessage([
                'sender_id' => $userId,
                'sender_name' => $senderName,
                'sender_email' => $sender['email'],
                'receiver_id' => $receiverId,
                'recipient_tenant_id' => $externalTenantId,
                'subject' => $subject,
                'body' => $body
            ]);

            if ($result['success']) {
                $externalMessageId = $result['data']['message_id'] ?? null;

                // Store message locally for inbox display
                $storeResult = FederatedMessageService::storeExternalMessage(
                    $userId,
                    $externalPartnerId,
                    $receiverId,
                    $receiverName,
                    $partnerName,
                    $subject,
                    $body,
                    $externalMessageId
                );

                if (!$storeResult['success']) {
                    error_log("Failed to store external message locally: " . ($storeResult['error'] ?? 'Unknown error'));
                }

                return ['success' => true, 'message_id' => $storeResult['message_id'] ?? $externalMessageId];
            } else {
                return ['success' => false, 'error' => $result['error'] ?? 'Failed to send message via external partner'];
            }

        } catch (\Exception $e) {
            error_log("FederatedMessageController::sendExternalMessage error: " . $e->getMessage());
            return ['success' => false, 'error' => 'Failed to connect to external partner'];
        }
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

    /**
     * Get partner communities for scope switcher
     */
    private function getPartnerCommunities(int $tenantId): array
    {
        $partnerships = \Nexus\Services\FederationPartnershipService::getTenantPartnerships($tenantId);
        $activePartnerships = array_filter($partnerships, fn($p) => $p['status'] === 'active');

        $partnerCommunities = [];
        foreach ($activePartnerships as $p) {
            $partnerId = ($p['tenant_id'] == $tenantId) ? $p['partner_tenant_id'] : $p['tenant_id'];
            $tenant = \Nexus\Core\Database::query(
                "SELECT id, name FROM tenants WHERE id = ?",
                [$partnerId]
            )->fetch(\PDO::FETCH_ASSOC);

            if ($tenant) {
                $partnerCommunities[] = $tenant;
            }
        }

        return $partnerCommunities;
    }
}
