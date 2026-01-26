<?php

namespace Nexus\Controllers;

use Nexus\Core\View;
use Nexus\Core\TenantContext;
use Nexus\Services\FederatedTransactionService;
use Nexus\Services\FederationFeatureService;
use Nexus\Services\FederationUserService;
use Nexus\Services\FederatedMessageService;
use Nexus\Services\FederationExternalPartnerService;
use Nexus\Services\FederationExternalApiClient;
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
     * Handles both internal federated members and external partner members
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

        // Check if this is an external partner transaction
        $externalPartnerId = isset($_GET['external_partner']) ? (int)$_GET['external_partner'] : 0;
        $isExternalTransaction = false;
        $externalPartner = null;
        $recipient = null;
        $recipientTenantId = 0;

        if ($externalPartnerId) {
            // External partner transaction
            $externalPartner = FederationExternalPartnerService::getById($externalPartnerId, $tenantId);

            if (!$externalPartner || $externalPartner['status'] !== 'active') {
                View::render('federation/not-available', [
                    'pageTitle' => 'Partner Not Available',
                    'message' => 'This external partner is not available for transactions.'
                ]);
                return;
            }

            if (!$externalPartner['allow_transactions']) {
                View::render('federation/not-available', [
                    'pageTitle' => 'Transactions Not Available',
                    'message' => 'Transactions are not enabled with this external partner.'
                ]);
                return;
            }

            $isExternalTransaction = true;
            $memberId = isset($_GET['member_id']) ? (int)$_GET['member_id'] : 0;
            $memberName = $_GET['member_name'] ?? '';

            if ($memberId) {
                // Fetch member details from external partner
                try {
                    $client = new FederationExternalApiClient($externalPartner);
                    $result = $client->getMember($memberId);

                    if ($result['success']) {
                        $memberData = $result['data']['member'] ?? $result['data']['data'] ?? $result['data'];
                        $recipient = [
                            'id' => $memberId,
                            'name' => $memberData['name'] ?? $memberData['display_name'] ?? $memberName,
                            'avatar_url' => $memberData['avatar_url'] ?? $memberData['avatar'] ?? null,
                            'tenant_name' => $externalPartner['partner_name'] ?: $externalPartner['name'],
                            'is_external' => true,
                            'external_partner_id' => $externalPartnerId,
                            'external_tenant_id' => $memberData['timebank']['id'] ?? $memberData['tenant_id'] ?? 1
                        ];
                    } else {
                        // Use basic info from URL params
                        $recipient = [
                            'id' => $memberId,
                            'name' => $memberName ?: 'External Member',
                            'avatar_url' => null,
                            'tenant_name' => $externalPartner['partner_name'] ?: $externalPartner['name'],
                            'is_external' => true,
                            'external_partner_id' => $externalPartnerId,
                            'external_tenant_id' => 1
                        ];
                    }
                } catch (\Exception $e) {
                    error_log("FederatedTransactionController::create external member fetch error: " . $e->getMessage());
                    $recipient = [
                        'id' => $memberId,
                        'name' => $memberName ?: 'External Member',
                        'avatar_url' => null,
                        'tenant_name' => $externalPartner['partner_name'] ?: $externalPartner['name'],
                        'is_external' => true,
                        'external_partner_id' => $externalPartnerId,
                        'external_tenant_id' => 1
                    ];
                }
            }
        } else {
            // Internal federated transaction
            $recipientId = isset($_GET['with']) ? (int)$_GET['with'] : 0;
            $recipientTenantId = isset($_GET['tenant']) ? (int)$_GET['tenant'] : 0;

            if ($recipientId && $recipientTenantId) {
                $recipient = FederatedMessageService::getFederatedUserInfo($recipientId, $recipientTenantId);
            }
        }

        // Get user's current balance
        $user = \Nexus\Models\User::findById($userId);
        $balance = $user['balance'] ?? 0;

        View::render('federation/transactions/create', [
            'recipient' => $recipient,
            'recipientTenantId' => $recipientTenantId,
            'balance' => $balance,
            'isExternalTransaction' => $isExternalTransaction,
            'externalPartner' => $externalPartner,
            'pageTitle' => 'Send Hours'
        ]);
    }

    /**
     * Process the transaction
     * Handles both internal federated transactions and external partner transactions
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

        $tenantId = TenantContext::getId();
        $userId = $_SESSION['user_id'];

        $receiverId = (int)($_POST['receiver_id'] ?? 0);
        $receiverTenantId = (int)($_POST['receiver_tenant_id'] ?? 0);
        $amount = (float)($_POST['amount'] ?? 0);
        $description = trim($_POST['description'] ?? '');
        $externalPartnerId = (int)($_POST['external_partner_id'] ?? 0);
        $receiverName = trim($_POST['receiver_name'] ?? '');

        // Validate basic fields
        if (!$receiverId || $amount <= 0) {
            if ($this->isAjax()) {
                $this->jsonResponse(['success' => false, 'error' => 'Missing required fields'], 400);
            }
            $_SESSION['flash_error'] = 'Please fill in all required fields';
            header('Location: ' . UrlHelper::safeReferer(TenantContext::getBasePath() . '/federation/transactions'));
            exit;
        }

        // External partner transaction
        if ($externalPartnerId > 0) {
            $result = $this->processExternalTransaction(
                $tenantId,
                $userId,
                $externalPartnerId,
                $receiverId,
                $receiverName,
                $amount,
                $description
            );
        } else {
            // Internal federated transaction (requires receiver_tenant_id)
            if (!$receiverTenantId) {
                if ($this->isAjax()) {
                    $this->jsonResponse(['success' => false, 'error' => 'Missing receiver tenant'], 400);
                }
                $_SESSION['flash_error'] = 'Please fill in all required fields';
                header('Location: ' . UrlHelper::safeReferer(TenantContext::getBasePath() . '/federation/transactions'));
                exit;
            }

            $result = FederatedTransactionService::createTransaction(
                $userId,
                $receiverId,
                $receiverTenantId,
                $amount,
                $description
            );
        }

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
     * Process an external partner transaction via API
     */
    private function processExternalTransaction(
        int $tenantId,
        int $senderId,
        int $externalPartnerId,
        int $receiverId,
        string $receiverName,
        float $amount,
        string $description
    ): array {
        // Get the external partner
        $partner = FederationExternalPartnerService::getById($externalPartnerId, $tenantId);

        if (!$partner || $partner['status'] !== 'active') {
            return ['success' => false, 'error' => 'External partner not found or inactive'];
        }

        if (!$partner['allow_transactions']) {
            return ['success' => false, 'error' => 'Transactions not enabled with this partner'];
        }

        // Check user's balance
        $user = \Nexus\Models\User::findById($senderId);
        if (($user['balance'] ?? 0) < $amount) {
            return ['success' => false, 'error' => 'Insufficient balance'];
        }

        // Send transaction to external partner via API
        try {
            $client = new FederationExternalApiClient($partner);

            $transactionData = [
                'sender_id' => $senderId,
                'sender_name' => $user['name'] ?? 'Member',
                'sender_email' => $user['email'] ?? '',
                'recipient_id' => $receiverId,
                'recipient_name' => $receiverName,
                'amount' => $amount,
                'description' => $description,
                'type' => 'transfer'
            ];

            $result = $client->createTransaction($transactionData);

            if ($result['success']) {
                // Deduct from sender's local balance
                \Nexus\Core\Database::query(
                    "UPDATE users SET balance = balance - ? WHERE id = ? AND tenant_id = ?",
                    [$amount, $senderId, $tenantId]
                );

                // Store local record of the external transaction
                $this->storeExternalTransactionRecord(
                    $senderId,
                    $externalPartnerId,
                    $receiverId,
                    $receiverName,
                    $amount,
                    $description,
                    $result['data']['transaction_id'] ?? null
                );

                return ['success' => true, 'message' => 'Transaction sent successfully'];
            } else {
                return ['success' => false, 'error' => $result['error'] ?? 'External transaction failed'];
            }
        } catch (\Exception $e) {
            error_log("FederatedTransactionController::processExternalTransaction error: " . $e->getMessage());
            return ['success' => false, 'error' => 'Failed to process external transaction'];
        }
    }

    /**
     * Store a local record of an external transaction
     */
    private function storeExternalTransactionRecord(
        int $senderId,
        int $externalPartnerId,
        int $receiverId,
        string $receiverName,
        float $amount,
        string $description,
        ?string $externalTransactionId
    ): void {
        $tenantId = TenantContext::getId();

        try {
            \Nexus\Core\Database::query("
                INSERT INTO federation_transactions
                (sender_tenant_id, sender_user_id, receiver_tenant_id, receiver_user_id,
                 amount, description, status, external_partner_id, external_receiver_name,
                 external_transaction_id, created_at)
                VALUES (?, ?, 0, ?, ?, ?, 'completed', ?, ?, ?, NOW())
            ", [
                $tenantId,
                $senderId,
                $receiverId,
                $amount,
                $description,
                $externalPartnerId,
                $receiverName,
                $externalTransactionId
            ]);
        } catch (\Exception $e) {
            error_log("storeExternalTransactionRecord error: " . $e->getMessage());
        }
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
