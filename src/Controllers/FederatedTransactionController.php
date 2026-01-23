<?php

namespace Nexus\Controllers;

use Nexus\Core\View;
use Nexus\Core\TenantContext;
use Nexus\Services\FederatedTransactionService;
use Nexus\Services\FederationFeatureService;
use Nexus\Services\FederationUserService;
use Nexus\Services\FederatedMessageService;
use Nexus\Helpers\UrlHelper;

/**
 * Federated Transaction Controller
 *
 * Handles cross-tenant hour exchanges between federated timebank members.
 */
class FederatedTransactionController
{
    /**
     * Show new transaction form (typically from a federated profile)
     */
    public function create()
    {
        if (!isset($_SESSION['user_id'])) {
            header('Location: ' . TenantContext::getBasePath() . '/login');
            exit;
        }

        $tenantId = TenantContext::getId();
        $userId = $_SESSION['user_id'];

        // Check federation enabled
        if (!$this->isFederationEnabled($tenantId)) {
            View::render('federation/not-available', [
                'pageTitle' => 'Federation Not Available'
            ]);
            return;
        }

        // Check user has opted in with transactions enabled
        $userSettings = FederationUserService::getUserSettings($userId);
        if (!$userSettings['federation_optin'] || !$userSettings['transactions_enabled_federated']) {
            View::render('federation/transactions/enable-required', [
                'pageTitle' => 'Enable Federated Transactions'
            ]);
            return;
        }

        // Get recipient info from query params
        $recipientId = isset($_GET['with']) ? (int)$_GET['with'] : 0;
        $recipientTenantId = isset($_GET['tenant']) ? (int)$_GET['tenant'] : 0;

        $recipient = null;
        if ($recipientId && $recipientTenantId) {
            $recipient = FederatedMessageService::getFederatedUserInfo($recipientId, $recipientTenantId);
        }

        // Get user's current balance
        $user = \Nexus\Models\User::findById($userId);
        $balance = $user['balance'] ?? 0;

        View::render('federation/transactions/create', [
            'recipient' => $recipient,
            'recipientTenantId' => $recipientTenantId,
            'balance' => $balance,
            'pageTitle' => 'Send Hours'
        ]);
    }

    /**
     * Process the transaction
     */
    public function store()
    {
        \Nexus\Core\Csrf::verifyOrDie();

        if (!isset($_SESSION['user_id'])) {
            if ($this->isAjax()) {
                $this->jsonResponse(['success' => false, 'error' => 'Unauthorized'], 401);
            }
            header('Location: ' . TenantContext::getBasePath() . '/login');
            exit;
        }

        $userId = $_SESSION['user_id'];

        $receiverId = (int)($_POST['receiver_id'] ?? 0);
        $receiverTenantId = (int)($_POST['receiver_tenant_id'] ?? 0);
        $amount = (float)($_POST['amount'] ?? 0);
        $description = trim($_POST['description'] ?? '');

        if (!$receiverId || !$receiverTenantId || $amount <= 0) {
            if ($this->isAjax()) {
                $this->jsonResponse(['success' => false, 'error' => 'Missing required fields'], 400);
            }
            $_SESSION['flash_error'] = 'Please fill in all required fields';
            header('Location: ' . UrlHelper::safeReferer(TenantContext::getBasePath() . '/federation/transactions'));
            exit;
        }

        // Process the transaction
        $result = FederatedTransactionService::createTransaction(
            $userId,
            $receiverId,
            $receiverTenantId,
            $amount,
            $description
        );

        if ($this->isAjax()) {
            $this->jsonResponse($result);
            exit;
        }

        if ($result['success']) {
            $_SESSION['flash_success'] = "Successfully sent {$amount} hour(s)!";
            header('Location: ' . TenantContext::getBasePath() . '/federation/transactions');
        } else {
            $_SESSION['flash_error'] = $result['error'] ?? 'Transaction failed';
            header('Location: ' . UrlHelper::safeReferer(TenantContext::getBasePath() . '/federation/transactions'));
        }
        exit;
    }

    /**
     * View federated transaction history
     */
    public function index()
    {
        if (!isset($_SESSION['user_id'])) {
            header('Location: ' . TenantContext::getBasePath() . '/login');
            exit;
        }

        $tenantId = TenantContext::getId();
        $userId = $_SESSION['user_id'];

        // Check federation enabled
        if (!$this->isFederationEnabled($tenantId)) {
            View::render('federation/not-available', [
                'pageTitle' => 'Federation Not Available'
            ]);
            return;
        }

        // Get transaction history
        $transactions = FederatedTransactionService::getHistory($userId);
        $stats = FederatedTransactionService::getStats($userId);

        // Get current balance
        $user = \Nexus\Models\User::findById($userId);

        // Get partner communities for scope switcher (if any)
        $partnerCommunities = $this->getPartnerCommunities($tenantId);
        $currentScope = $_GET['scope'] ?? 'all';

        // Use CivicOne wrapper if CivicOne layout is active
        $viewPath = (layout() === 'civicone')
            ? 'civicone/federation/transactions'
            : 'federation/transactions/index';

        View::render($viewPath, [
            'transactions' => $transactions,
            'stats' => $stats,
            'balance' => $user['balance'] ?? 0,
            'partnerCommunities' => $partnerCommunities,
            'currentScope' => $currentScope,
            'pageTitle' => 'Federated Transactions'
        ]);
    }

    /**
     * API endpoint for transaction data
     */
    public function api()
    {
        header('Content-Type: application/json');

        if (!isset($_SESSION['user_id'])) {
            $this->jsonResponse(['success' => false, 'error' => 'Unauthorized'], 401);
            exit;
        }

        $userId = $_SESSION['user_id'];
        $action = $_GET['action'] ?? 'history';

        switch ($action) {
            case 'history':
                $limit = min((int)($_GET['limit'] ?? 50), 100);
                $offset = max(0, (int)($_GET['offset'] ?? 0));
                $transactions = FederatedTransactionService::getHistory($userId, $limit, $offset);
                $this->jsonResponse([
                    'success' => true,
                    'transactions' => $transactions,
                    'hasMore' => count($transactions) >= $limit
                ]);
                break;

            case 'stats':
                $stats = FederatedTransactionService::getStats($userId);
                $this->jsonResponse(['success' => true, 'stats' => $stats]);
                break;

            case 'can_receive':
                $targetUserId = (int)($_GET['user'] ?? 0);
                $targetTenantId = (int)($_GET['tenant'] ?? 0);
                $canReceive = FederatedTransactionService::canReceiveTransactions($targetUserId, $targetTenantId);
                $this->jsonResponse(['success' => true, 'can_receive' => $canReceive]);
                break;

            default:
                $this->jsonResponse(['success' => false, 'error' => 'Unknown action'], 400);
        }
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
