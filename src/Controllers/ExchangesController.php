<?php

namespace Nexus\Controllers;

use Nexus\Core\TenantContext;
use Nexus\Core\View;
use Nexus\Core\Csrf;
use Nexus\Services\ExchangeWorkflowService;
use Nexus\Services\BrokerControlConfigService;
use Nexus\Services\ListingService;
use Nexus\Middleware\TenantModuleMiddleware;

/**
 * ExchangesController
 *
 * User-facing controller for the exchange workflow.
 * Allows users to request exchanges, view their exchanges, and manage the workflow.
 */
class ExchangesController
{
    /**
     * Check if exchange workflow is enabled
     */
    private function checkFeature(): void
    {
        // First check if listings module is enabled
        TenantModuleMiddleware::require('listings');

        // Then check if exchange workflow is enabled
        if (!BrokerControlConfigService::isExchangeWorkflowEnabled()) {
            $basePath = TenantContext::getBasePath();
            header('Location: ' . $basePath . '/listings');
            exit;
        }
    }

    /**
     * Require authentication
     */
    private function requireAuth(): int
    {
        if (!isset($_SESSION['user_id'])) {
            $basePath = TenantContext::getBasePath();
            $returnUrl = urlencode($_SERVER['REQUEST_URI'] ?? '/exchanges');
            header('Location: ' . $basePath . '/login?return=' . $returnUrl);
            exit;
        }
        return (int) $_SESSION['user_id'];
    }

    /**
     * Get layout-specific view path
     */
    private function getViewPath(string $viewName): string
    {
        $layout = \Nexus\Services\LayoutHelper::get();
        $baseDir = __DIR__ . '/../../views/';
        $viewPath = $baseDir . $layout . '/exchanges/' . $viewName . '.php';

        // Fallback to modern if layout-specific view doesn't exist
        if (!file_exists($viewPath)) {
            $viewPath = $baseDir . 'modern/exchanges/' . $viewName . '.php';
        }

        return $viewPath;
    }

    /**
     * List user's exchanges
     * GET /exchanges
     */
    public function index(): void
    {
        $this->checkFeature();
        $userId = $this->requireAuth();

        $status = $_GET['status'] ?? 'active';
        $page = max(1, (int) ($_GET['page'] ?? 1));

        // Map UI tabs to filters
        $filters = [];
        switch ($status) {
            case 'pending':
                $filters['status'] = 'pending_provider';
                break;
            case 'active':
                // Active includes accepted, in_progress, pending_confirmation
                break;
            case 'completed':
                $filters['status'] = 'completed';
                break;
            case 'all':
            default:
                break;
        }

        $result = ExchangeWorkflowService::getExchangesForUser($userId, $filters, $page);

        // Filter by UI status if needed
        if ($status === 'active') {
            $result['items'] = array_filter($result['items'], function ($item) {
                return in_array($item['status'], [
                    ExchangeWorkflowService::STATUS_ACCEPTED,
                    ExchangeWorkflowService::STATUS_IN_PROGRESS,
                    ExchangeWorkflowService::STATUS_PENDING_CONFIRMATION,
                    ExchangeWorkflowService::STATUS_PENDING_PROVIDER,
                    ExchangeWorkflowService::STATUS_PENDING_BROKER,
                ]);
            });
        }

        $pageTitle = 'My Exchanges';
        $exchanges = $result['items'];
        $totalPages = $result['pages'];
        $totalCount = $result['total'];
        $currentUserId = $userId;
        $basePath = TenantContext::getBasePath();

        require $this->getViewPath('index');
    }

    /**
     * Show single exchange details
     * GET /exchanges/{id}
     */
    public function show(int $id): void
    {
        $this->checkFeature();
        $userId = $this->requireAuth();

        $exchange = ExchangeWorkflowService::getExchange($id);

        if (!$exchange) {
            header('HTTP/1.1 404 Not Found');
            $pageTitle = 'Exchange Not Found';
            require $this->getViewPath('not-found');
            return;
        }

        // Verify user has access (must be requester or provider)
        if ($exchange['requester_id'] !== $userId && $exchange['provider_id'] !== $userId) {
            header('HTTP/1.1 403 Forbidden');
            $basePath = TenantContext::getBasePath();
            header('Location: ' . $basePath . '/exchanges');
            exit;
        }

        $history = ExchangeWorkflowService::getExchangeHistory($id);
        $pageTitle = 'Exchange #' . $id;
        $currentUserId = $userId;
        $isRequester = $exchange['requester_id'] === $userId;
        $isProvider = $exchange['provider_id'] === $userId;
        $basePath = TenantContext::getBasePath();

        // Determine available actions based on status and role
        $actions = $this->getAvailableActions($exchange, $userId);

        require $this->getViewPath('show');
    }

    /**
     * Show form to request an exchange for a listing
     * GET /exchanges/request/{listingId}
     */
    public function create(int $listingId): void
    {
        $this->checkFeature();
        $userId = $this->requireAuth();

        $listing = ListingService::getById($listingId);

        if (!$listing) {
            header('HTTP/1.1 404 Not Found');
            $basePath = TenantContext::getBasePath();
            header('Location: ' . $basePath . '/listings');
            exit;
        }

        // Can't request exchange on own listing
        if ($listing['user_id'] === $userId) {
            $basePath = TenantContext::getBasePath();
            $_SESSION['flash_error'] = 'You cannot request an exchange on your own listing.';
            header('Location: ' . $basePath . '/listings/' . $listingId);
            exit;
        }

        $pageTitle = 'Request Exchange';
        $basePath = TenantContext::getBasePath();
        $defaultHours = $listing['hours'] ?? 1;

        require $this->getViewPath('request');
    }

    /**
     * Create a new exchange request
     * POST /exchanges
     */
    public function store(): void
    {
        $this->checkFeature();
        $userId = $this->requireAuth();
        Csrf::verifyOrDie();

        $listingId = (int) ($_POST['listing_id'] ?? 0);
        $proposedHours = (float) ($_POST['proposed_hours'] ?? 1);
        $message = trim($_POST['message'] ?? '');

        if ($listingId <= 0) {
            $_SESSION['flash_error'] = 'Invalid listing.';
            header('Location: ' . TenantContext::getBasePath() . '/exchanges');
            exit;
        }

        // Validate hours
        $proposedHours = max(0.25, min(24, $proposedHours));

        $exchangeId = ExchangeWorkflowService::createRequest($userId, $listingId, [
            'proposed_hours' => $proposedHours,
            'message' => $message,
        ]);

        $basePath = TenantContext::getBasePath();

        if ($exchangeId) {
            $_SESSION['flash_success'] = 'Exchange request sent successfully.';
            header('Location: ' . $basePath . '/exchanges/' . $exchangeId);
        } else {
            $_SESSION['flash_error'] = 'Failed to create exchange request.';
            header('Location: ' . $basePath . '/listings/' . $listingId);
        }
        exit;
    }

    /**
     * Accept an exchange request (provider only)
     * POST /exchanges/{id}/accept
     */
    public function accept(int $id): void
    {
        $this->checkFeature();
        $userId = $this->requireAuth();
        Csrf::verifyOrDie();

        $success = ExchangeWorkflowService::acceptRequest($id, $userId);
        $basePath = TenantContext::getBasePath();

        if ($success) {
            $_SESSION['flash_success'] = 'Exchange request accepted.';
        } else {
            $_SESSION['flash_error'] = 'Failed to accept exchange request.';
        }

        header('Location: ' . $basePath . '/exchanges/' . $id);
        exit;
    }

    /**
     * Decline an exchange request (provider only)
     * POST /exchanges/{id}/decline
     */
    public function decline(int $id): void
    {
        $this->checkFeature();
        $userId = $this->requireAuth();
        Csrf::verifyOrDie();

        $reason = trim($_POST['reason'] ?? '');
        $success = ExchangeWorkflowService::declineRequest($id, $userId, $reason);
        $basePath = TenantContext::getBasePath();

        if ($success) {
            $_SESSION['flash_success'] = 'Exchange request declined.';
            header('Location: ' . $basePath . '/exchanges');
        } else {
            $_SESSION['flash_error'] = 'Failed to decline exchange request.';
            header('Location: ' . $basePath . '/exchanges/' . $id);
        }
        exit;
    }

    /**
     * Mark exchange as started
     * POST /exchanges/{id}/start
     */
    public function start(int $id): void
    {
        $this->checkFeature();
        $userId = $this->requireAuth();
        Csrf::verifyOrDie();

        $success = ExchangeWorkflowService::startProgress($id, $userId);
        $basePath = TenantContext::getBasePath();

        if ($success) {
            $_SESSION['flash_success'] = 'Exchange marked as in progress.';
        } else {
            $_SESSION['flash_error'] = 'Failed to update exchange status.';
        }

        header('Location: ' . $basePath . '/exchanges/' . $id);
        exit;
    }

    /**
     * Confirm completion with hours
     * POST /exchanges/{id}/confirm
     */
    public function confirm(int $id): void
    {
        $this->checkFeature();
        $userId = $this->requireAuth();
        Csrf::verifyOrDie();

        $hours = (float) ($_POST['hours'] ?? 0);
        $hours = max(0.25, min(24, $hours));

        $success = ExchangeWorkflowService::confirmCompletion($id, $userId, $hours);
        $basePath = TenantContext::getBasePath();

        if ($success) {
            $_SESSION['flash_success'] = 'Hours confirmed. Waiting for the other party to confirm.';
        } else {
            $_SESSION['flash_error'] = 'Failed to confirm hours.';
        }

        header('Location: ' . $basePath . '/exchanges/' . $id);
        exit;
    }

    /**
     * Cancel an exchange
     * POST /exchanges/{id}/cancel
     */
    public function cancel(int $id): void
    {
        $this->checkFeature();
        $userId = $this->requireAuth();
        Csrf::verifyOrDie();

        $reason = trim($_POST['reason'] ?? '');
        $success = ExchangeWorkflowService::cancelExchange($id, $userId, $reason);
        $basePath = TenantContext::getBasePath();

        if ($success) {
            $_SESSION['flash_success'] = 'Exchange cancelled.';
            header('Location: ' . $basePath . '/exchanges');
        } else {
            $_SESSION['flash_error'] = 'Failed to cancel exchange.';
            header('Location: ' . $basePath . '/exchanges/' . $id);
        }
        exit;
    }

    /**
     * Determine available actions for an exchange based on status and user role
     */
    private function getAvailableActions(array $exchange, int $userId): array
    {
        $actions = [];
        $isRequester = $exchange['requester_id'] === $userId;
        $isProvider = $exchange['provider_id'] === $userId;
        $status = $exchange['status'];

        switch ($status) {
            case ExchangeWorkflowService::STATUS_PENDING_PROVIDER:
                if ($isProvider) {
                    $actions[] = ['action' => 'accept', 'label' => 'Accept Request', 'style' => 'primary'];
                    $actions[] = ['action' => 'decline', 'label' => 'Decline', 'style' => 'danger', 'confirm' => true];
                }
                if ($isRequester) {
                    $actions[] = ['action' => 'cancel', 'label' => 'Cancel Request', 'style' => 'secondary', 'confirm' => true];
                }
                break;

            case ExchangeWorkflowService::STATUS_PENDING_BROKER:
                // No user actions - waiting for broker
                break;

            case ExchangeWorkflowService::STATUS_ACCEPTED:
                $actions[] = ['action' => 'start', 'label' => 'Start Work', 'style' => 'primary'];
                $actions[] = ['action' => 'cancel', 'label' => 'Cancel', 'style' => 'secondary', 'confirm' => true];
                break;

            case ExchangeWorkflowService::STATUS_IN_PROGRESS:
            case ExchangeWorkflowService::STATUS_PENDING_CONFIRMATION:
                // Check if this user has already confirmed
                $hasConfirmed = false;
                if ($isRequester && !empty($exchange['requester_confirmed_at'])) {
                    $hasConfirmed = true;
                }
                if ($isProvider && !empty($exchange['provider_confirmed_at'])) {
                    $hasConfirmed = true;
                }

                if (!$hasConfirmed) {
                    $actions[] = ['action' => 'confirm', 'label' => 'Confirm Hours', 'style' => 'primary', 'needsHours' => true];
                }
                $actions[] = ['action' => 'cancel', 'label' => 'Cancel', 'style' => 'secondary', 'confirm' => true];
                break;

            case ExchangeWorkflowService::STATUS_DISPUTED:
                // No user actions - waiting for broker resolution
                break;

            case ExchangeWorkflowService::STATUS_COMPLETED:
            case ExchangeWorkflowService::STATUS_CANCELLED:
            case ExchangeWorkflowService::STATUS_EXPIRED:
                // No actions available
                break;
        }

        return $actions;
    }
}
