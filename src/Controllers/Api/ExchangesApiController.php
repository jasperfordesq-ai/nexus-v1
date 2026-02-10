<?php

namespace Nexus\Controllers\Api;

use Nexus\Services\ExchangeWorkflowService;
use Nexus\Services\BrokerControlConfigService;

/**
 * ExchangesApiController
 *
 * API endpoints for the exchange workflow system.
 * Handles creating, accepting, declining, and confirming exchanges.
 *
 * Endpoints:
 * GET    /api/v2/exchanges          - List user's exchanges
 * POST   /api/v2/exchanges          - Create new exchange request
 * GET    /api/v2/exchanges/{id}     - Get exchange details
 * POST   /api/v2/exchanges/{id}/accept   - Accept exchange (provider)
 * POST   /api/v2/exchanges/{id}/decline  - Decline exchange (provider)
 * POST   /api/v2/exchanges/{id}/start    - Mark as in progress
 * POST   /api/v2/exchanges/{id}/complete - Mark ready for confirmation
 * POST   /api/v2/exchanges/{id}/confirm  - Confirm hours
 * DELETE /api/v2/exchanges/{id}     - Cancel exchange
 * GET    /api/v2/exchanges/config   - Get exchange config (public)
 */
class ExchangesApiController extends BaseApiController
{
    /**
     * List user's exchanges
     *
     * GET /api/v2/exchanges
     * Query params: status, role (requester|provider), page, per_page
     */
    public function index(): void
    {
        $userId = $this->requireAuth();

        // Check if exchange workflow is enabled
        if (!BrokerControlConfigService::isExchangeWorkflowEnabled()) {
            $this->error('Exchange workflow is not enabled for this community', 400);
            return;
        }

        $filters = [];
        if (!empty($_GET['status'])) {
            $filters['status'] = $_GET['status'];
        }
        if (!empty($_GET['role'])) {
            $filters['role'] = $_GET['role'];
        }

        $page = max(1, (int) ($_GET['page'] ?? 1));
        $perPage = min(50, max(1, (int) ($_GET['per_page'] ?? 20)));

        $result = ExchangeWorkflowService::getExchangesForUser($userId, $filters, $page, $perPage);

        $this->jsonResponse([
            'data' => array_map([$this, 'formatExchange'], $result['items']),
            'meta' => [
                'current_page' => $page,
                'per_page' => $perPage,
                'total' => $result['total'],
                'total_pages' => $result['pages'],
            ],
        ]);
    }

    /**
     * Create a new exchange request
     *
     * POST /api/v2/exchanges
     * Body: { listing_id, proposed_hours, message? }
     */
    public function store(): void
    {
        $userId = $this->requireAuth();

        if (!BrokerControlConfigService::isExchangeWorkflowEnabled()) {
            $this->error('Exchange workflow is not enabled for this community', 400);
            return;
        }

        $data = $this->getAllInput();

        if (empty($data['listing_id'])) {
            $this->error('listing_id is required', 400);
            return;
        }

        $exchangeId = ExchangeWorkflowService::createRequest(
            $userId,
            (int) $data['listing_id'],
            [
                'proposed_hours' => $data['proposed_hours'] ?? null,
                'message' => $data['message'] ?? null,
            ]
        );

        if (!$exchangeId) {
            $this->error('Failed to create exchange request', 400);
            return;
        }

        $exchange = ExchangeWorkflowService::getExchange($exchangeId);

        $this->jsonResponse([
            'data' => $this->formatExchange($exchange),
            'message' => 'Exchange request created successfully',
        ], 201);
    }

    /**
     * Get exchange details
     *
     * GET /api/v2/exchanges/{id}
     */
    public function show(int $id): void
    {
        $userId = $this->requireAuth();

        $exchange = ExchangeWorkflowService::getExchange($id);

        if (!$exchange) {
            $this->error('Exchange not found', 404);
            return;
        }

        // Check user is participant
        if ($exchange['requester_id'] !== $userId && $exchange['provider_id'] !== $userId) {
            $this->error('Exchange not found', 404);
            return;
        }

        // Get history
        $history = ExchangeWorkflowService::getExchangeHistory($id);

        $this->jsonResponse([
            'data' => $this->formatExchange($exchange),
            'history' => array_map(function ($h) {
                return [
                    'action' => $h['action'],
                    'actor_role' => $h['actor_role'],
                    'old_status' => $h['old_status'],
                    'new_status' => $h['new_status'],
                    'notes' => $h['notes'],
                    'created_at' => $h['created_at'],
                ];
            }, $history),
        ]);
    }

    /**
     * Accept exchange request (provider only)
     *
     * POST /api/v2/exchanges/{id}/accept
     */
    public function accept(int $id): void
    {
        $userId = $this->requireAuth();

        $exchange = ExchangeWorkflowService::getExchange($id);

        if (!$exchange) {
            $this->error('Exchange not found', 404);
            return;
        }

        if ($exchange['provider_id'] !== $userId) {
            $this->error('Only the provider can accept this request', 403);
            return;
        }

        $success = ExchangeWorkflowService::acceptRequest($id, $userId);

        if (!$success) {
            $this->error('Unable to accept this exchange request', 400);
            return;
        }

        $exchange = ExchangeWorkflowService::getExchange($id);

        $this->jsonResponse([
            'data' => $this->formatExchange($exchange),
            'message' => 'Exchange request accepted',
        ]);
    }

    /**
     * Decline exchange request (provider only)
     *
     * POST /api/v2/exchanges/{id}/decline
     * Body: { reason? }
     */
    public function decline(int $id): void
    {
        $userId = $this->requireAuth();

        $exchange = ExchangeWorkflowService::getExchange($id);

        if (!$exchange) {
            $this->error('Exchange not found', 404);
            return;
        }

        if ($exchange['provider_id'] !== $userId) {
            $this->error('Only the provider can decline this request', 403);
            return;
        }

        $data = $this->getAllInput();
        $reason = $data['reason'] ?? '';

        $success = ExchangeWorkflowService::declineRequest($id, $userId, $reason);

        if (!$success) {
            $this->error('Unable to decline this exchange request', 400);
            return;
        }

        $this->jsonResponse([
            'message' => 'Exchange request declined',
        ]);
    }

    /**
     * Start exchange (mark as in progress)
     *
     * POST /api/v2/exchanges/{id}/start
     */
    public function start(int $id): void
    {
        $userId = $this->requireAuth();

        $exchange = ExchangeWorkflowService::getExchange($id);

        if (!$exchange) {
            $this->error('Exchange not found', 404);
            return;
        }

        if ($exchange['requester_id'] !== $userId && $exchange['provider_id'] !== $userId) {
            $this->error('Exchange not found', 404);
            return;
        }

        $success = ExchangeWorkflowService::startProgress($id, $userId);

        if (!$success) {
            $this->error('Unable to start this exchange', 400);
            return;
        }

        $exchange = ExchangeWorkflowService::getExchange($id);

        $this->jsonResponse([
            'data' => $this->formatExchange($exchange),
            'message' => 'Exchange marked as in progress',
        ]);
    }

    /**
     * Complete exchange (mark ready for confirmation)
     *
     * POST /api/v2/exchanges/{id}/complete
     */
    public function complete(int $id): void
    {
        $userId = $this->requireAuth();

        $exchange = ExchangeWorkflowService::getExchange($id);

        if (!$exchange) {
            $this->error('Exchange not found', 404);
            return;
        }

        if ($exchange['requester_id'] !== $userId && $exchange['provider_id'] !== $userId) {
            $this->error('Exchange not found', 404);
            return;
        }

        $success = ExchangeWorkflowService::markReadyForConfirmation($id, $userId);

        if (!$success) {
            $this->error('Unable to complete this exchange', 400);
            return;
        }

        $exchange = ExchangeWorkflowService::getExchange($id);

        $this->jsonResponse([
            'data' => $this->formatExchange($exchange),
            'message' => 'Exchange marked as ready for confirmation',
        ]);
    }

    /**
     * Confirm exchange hours
     *
     * POST /api/v2/exchanges/{id}/confirm
     * Body: { hours }
     */
    public function confirm(int $id): void
    {
        $userId = $this->requireAuth();

        $exchange = ExchangeWorkflowService::getExchange($id);

        if (!$exchange) {
            $this->error('Exchange not found', 404);
            return;
        }

        if ($exchange['requester_id'] !== $userId && $exchange['provider_id'] !== $userId) {
            $this->error('Exchange not found', 404);
            return;
        }

        $data = $this->getAllInput();

        if (!isset($data['hours'])) {
            $this->error('hours is required', 400);
            return;
        }

        $hours = (float) $data['hours'];

        if ($hours <= 0) {
            $this->error('hours must be greater than 0', 400);
            return;
        }

        $success = ExchangeWorkflowService::confirmCompletion($id, $userId, $hours);

        if (!$success) {
            $this->error('Unable to confirm this exchange', 400);
            return;
        }

        $exchange = ExchangeWorkflowService::getExchange($id);

        $message = 'Hours confirmed';
        if ($exchange['status'] === 'completed') {
            $message = 'Exchange completed! Credits have been transferred.';
        } elseif ($exchange['status'] === 'disputed') {
            $message = 'Hours recorded. There is a discrepancy - a broker will review.';
        }

        $this->jsonResponse([
            'data' => $this->formatExchange($exchange),
            'message' => $message,
        ]);
    }

    /**
     * Cancel exchange
     *
     * DELETE /api/v2/exchanges/{id}
     * Body: { reason? }
     */
    public function cancel(int $id): void
    {
        $userId = $this->requireAuth();

        $exchange = ExchangeWorkflowService::getExchange($id);

        if (!$exchange) {
            $this->error('Exchange not found', 404);
            return;
        }

        if ($exchange['requester_id'] !== $userId && $exchange['provider_id'] !== $userId) {
            $this->error('Exchange not found', 404);
            return;
        }

        $data = $this->getAllInput();
        $reason = $data['reason'] ?? '';

        $success = ExchangeWorkflowService::cancelExchange($id, $userId, $reason);

        if (!$success) {
            $this->error('Unable to cancel this exchange', 400);
            return;
        }

        $this->jsonResponse([
            'message' => 'Exchange cancelled',
        ]);
    }

    /**
     * Get exchange workflow configuration for current tenant
     *
     * GET /api/v2/exchanges/config
     */
    public function config(): void
    {
        $userId = $this->requireAuth();

        $config = BrokerControlConfigService::getConfig('exchange_workflow');
        $directMessaging = BrokerControlConfigService::isDirectMessagingEnabled();

        $this->jsonResponse([
            'data' => [
                'exchange_workflow_enabled' => $config['enabled'] ?? false,
                'direct_messaging_enabled' => $directMessaging,
                'require_broker_approval' => $config['require_broker_approval'] ?? false,
                'confirmation_deadline_hours' => $config['confirmation_deadline_hours'] ?? 72,
                'allow_hour_adjustment' => $config['allow_hour_adjustment'] ?? true,
                'max_hour_variance_percent' => $config['max_hour_variance_percent'] ?? 25,
            ],
        ]);
    }

    /**
     * Format exchange for API response
     */
    private function formatExchange(array $exchange): array
    {
        return [
            'id' => (int) $exchange['id'],
            'listing' => [
                'id' => (int) $exchange['listing_id'],
                'title' => $exchange['listing_title'] ?? null,
                'type' => $exchange['listing_type'] ?? null,
            ],
            'requester' => [
                'id' => (int) $exchange['requester_id'],
                'name' => $exchange['requester_name'] ?? null,
                'avatar' => $exchange['requester_avatar'] ?? null,
            ],
            'provider' => [
                'id' => (int) $exchange['provider_id'],
                'name' => $exchange['provider_name'] ?? null,
                'avatar' => $exchange['provider_avatar'] ?? null,
            ],
            'proposed_hours' => (float) $exchange['proposed_hours'],
            'final_hours' => $exchange['final_hours'] ? (float) $exchange['final_hours'] : null,
            'status' => $exchange['status'],
            'risk_level' => $exchange['risk_level'] ?? null,
            'requester_confirmed' => [
                'at' => $exchange['requester_confirmed_at'] ?? null,
                'hours' => $exchange['requester_confirmed_hours'] ? (float) $exchange['requester_confirmed_hours'] : null,
            ],
            'provider_confirmed' => [
                'at' => $exchange['provider_confirmed_at'] ?? null,
                'hours' => $exchange['provider_confirmed_hours'] ? (float) $exchange['provider_confirmed_hours'] : null,
            ],
            'broker_notes' => $exchange['broker_notes'] ?? null,
            'created_at' => $exchange['created_at'],
        ];
    }
}
