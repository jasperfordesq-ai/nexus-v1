<?php
// Copyright © 2024–2026 Jasper Ford
// SPDX-License-Identifier: AGPL-3.0-or-later
// Author: Jasper Ford
// See NOTICE file for attribution and acknowledgements.

namespace App\Http\Controllers\Api;

use Illuminate\Http\JsonResponse;
use App\Services\ExchangeService;
use App\Services\BrokerControlConfigService;
use App\Services\ExchangeWorkflowService;

/**
 * ExchangesController -- Time credit exchange lifecycle (create, accept, decline, confirm).
 *
 * Core CRUD uses Eloquent via ExchangeService; complex state transitions delegate to legacy.
 */
class ExchangesController extends BaseApiController
{
    protected bool $isV2Api = true;

    public function __construct(
        private readonly ExchangeService $exchangeService,
        private readonly BrokerControlConfigService $brokerControlConfigService,
        private readonly ExchangeWorkflowService $exchangeWorkflowService,
    ) {}

    /** GET /api/v2/exchanges/config — exchange workflow configuration */
    public function config(): JsonResponse
    {
        $this->requireAuth();

        $config = $this->brokerControlConfigService->getConfig('exchange_workflow');
        $directMessaging = $this->brokerControlConfigService->isDirectMessagingEnabled();

        return $this->respondWithData([
            'exchange_workflow_enabled' => $this->brokerControlConfigService->isExchangeWorkflowEnabled(),
            'direct_messaging_enabled' => $directMessaging,
            'require_broker_approval'  => $config['require_broker_approval'] ?? false,
            'confirmation_deadline_hours' => $config['confirmation_deadline_hours'] ?? 72,
            'allow_hour_adjustment'    => $config['allow_hour_adjustment'] ?? true,
            'max_hour_variance_percent' => $config['max_hour_variance_percent'] ?? 25,
        ]);
    }

    /** GET /api/v2/exchanges/check?listing_id={id} — check active exchange for listing */
    public function check(): JsonResponse
    {
        $userId = $this->requireAuth();

        $listingId = $this->queryInt('listing_id');
        if (!$listingId || $listingId <= 0) {
            return $this->respondWithError('VALIDATION_REQUIRED_FIELD', 'listing_id is required', 'listing_id', 400);
        }

        $exchange = $this->exchangeWorkflowService->getActiveExchangeForListing($userId, $listingId);

        return $this->respondWithData($exchange ? [
            'id'             => (int) $exchange['id'],
            'status'         => $exchange['status'],
            'proposed_hours' => (float) $exchange['proposed_hours'],
            'role'           => (int) $exchange['requester_id'] === $userId ? 'requester' : 'provider',
            'created_at'     => $exchange['created_at'],
        ] : null);
    }

    /** GET /api/v2/exchanges — list user's exchanges */
    public function index(): JsonResponse
    {
        $userId = $this->requireAuth();

        if (!$this->brokerControlConfigService->isExchangeWorkflowEnabled()) {
            return $this->respondWithError('FEATURE_DISABLED', 'Exchange workflow is not enabled for this community', null, 400);
        }

        $filters = [
            'limit' => $this->queryInt('per_page', 20, 1, 100),
        ];
        if ($this->query('status')) {
            $filters['status'] = $this->query('status');
        }
        if ($this->query('cursor')) {
            $filters['cursor'] = $this->query('cursor');
        }

        $result = $this->exchangeService->getAll($userId, $filters);

        $formatted = array_map(fn ($item) => $this->formatExchange($item), $result['items']);

        return $this->respondWithCollection(
            $formatted,
            $result['cursor'],
            $filters['limit'],
            $result['has_more']
        );
    }

    /** GET /api/v2/exchanges/{id} — single exchange with history */
    public function show(int $id): JsonResponse
    {
        $userId = $this->requireAuth();

        $exchange = $this->exchangeWorkflowService->getExchange($id);

        if (!$exchange) {
            return $this->respondWithError('NOT_FOUND', 'Exchange not found', null, 404);
        }

        if ((int) $exchange['requester_id'] !== $userId && (int) $exchange['provider_id'] !== $userId) {
            return $this->respondWithError('NOT_FOUND', 'Exchange not found', null, 404);
        }

        $history = $this->exchangeWorkflowService->getExchangeHistory($id);

        $formatted = $this->formatExchange($exchange);
        $formatted['status_history'] = array_map(fn ($h) => [
            'action'     => $h['action'],
            'actor_role' => $h['actor_role'],
            'actor_name' => $h['actor_name'] ?? null,
            'old_status' => $h['old_status'],
            'new_status' => $h['new_status'],
            'notes'      => $h['notes'],
            'created_at' => $h['created_at'],
        ], $history);

        return $this->respondWithData($formatted);
    }

    /** POST /api/v2/exchanges — create exchange request */
    public function store(): JsonResponse
    {
        $userId = $this->requireAuth();
        $this->rateLimit('exchange_create', 10, 60);

        if (!$this->brokerControlConfigService->isExchangeWorkflowEnabled()) {
            return $this->respondWithError('FEATURE_DISABLED', 'Exchange workflow is not enabled for this community', null, 400);
        }

        $data = $this->getAllInput();

        if (empty($data['listing_id'])) {
            return $this->respondWithError('VALIDATION_REQUIRED_FIELD', 'listing_id is required', 'listing_id', 400);
        }

        // Check compliance requirements
        $violations = $this->exchangeWorkflowService->checkComplianceRequirements((int) $data['listing_id'], $userId);
        if (!empty($violations)) {
            return $this->respondWithError('COMPLIANCE_VIOLATION', implode(' ', $violations), null, 403);
        }

        $exchangeId = $this->exchangeWorkflowService->createRequest(
            $userId,
            (int) $data['listing_id'],
            [
                'proposed_hours' => $data['proposed_hours'] ?? null,
                'prep_time'      => $data['prep_time'] ?? null,
                'message'        => $data['message'] ?? null,
            ]
        );

        if (!$exchangeId) {
            return $this->error('Failed to create exchange request', 400);
        }

        $exchange = $this->exchangeWorkflowService->getExchange($exchangeId);

        return $this->respondWithData($this->formatExchange($exchange), null, 201);
    }

    /** POST /api/v2/exchanges/{id}/accept — provider accepts */
    public function accept(int $id): JsonResponse
    {
        $userId = $this->requireAuth();

        $exchange = $this->exchangeWorkflowService->getExchange($id);
        if (!$exchange) {
            return $this->respondWithError('NOT_FOUND', 'Exchange not found', null, 404);
        }

        if ((int) $exchange['provider_id'] !== $userId) {
            return $this->respondWithError('FORBIDDEN', 'Only the provider can accept this request', null, 403);
        }

        $violations = $this->exchangeWorkflowService->checkComplianceRequirements((int) $exchange['listing_id'], $userId);
        if (!empty($violations)) {
            return $this->respondWithError('COMPLIANCE_VIOLATION', implode(' ', $violations), null, 403);
        }

        $success = $this->exchangeWorkflowService->acceptRequest($id, $userId);
        if (!$success) {
            return $this->error('Unable to accept this exchange request', 400);
        }

        $exchange = $this->exchangeWorkflowService->getExchange($id);

        return $this->respondWithData($this->formatExchange($exchange));
    }

    /** POST /api/v2/exchanges/{id}/decline — provider declines */
    public function decline(int $id): JsonResponse
    {
        $userId = $this->requireAuth();

        $exchange = $this->exchangeWorkflowService->getExchange($id);
        if (!$exchange) {
            return $this->respondWithError('NOT_FOUND', 'Exchange not found', null, 404);
        }

        if ((int) $exchange['provider_id'] !== $userId) {
            return $this->respondWithError('FORBIDDEN', 'Only the provider can decline this request', null, 403);
        }

        $reason = $this->input('reason', '');

        $success = $this->exchangeWorkflowService->declineRequest($id, $userId, $reason);
        if (!$success) {
            return $this->error('Unable to decline this exchange request', 400);
        }

        return $this->respondWithData(['message' => 'Exchange request declined']);
    }

    /** POST /api/v2/exchanges/{id}/start — mark as in progress */
    public function start($id): JsonResponse
    {
        $userId = $this->requireAuth();
        $id = (int) $id;

        $exchange = $this->exchangeWorkflowService->getExchange($id);
        if (!$exchange) {
            return $this->respondWithError('NOT_FOUND', 'Exchange not found', null, 404);
        }

        if ((int) $exchange['requester_id'] !== $userId && (int) $exchange['provider_id'] !== $userId) {
            return $this->respondWithError('NOT_FOUND', 'Exchange not found', null, 404);
        }

        $success = $this->exchangeWorkflowService->startProgress($id, $userId);
        if (!$success) {
            return $this->error('Unable to start this exchange', 400);
        }

        $exchange = $this->exchangeWorkflowService->getExchange($id);

        return $this->respondWithData($this->formatExchange($exchange));
    }

    /** POST /api/v2/exchanges/{id}/complete — mark ready for confirmation */
    public function complete($id): JsonResponse
    {
        $userId = $this->requireAuth();
        $id = (int) $id;

        $exchange = $this->exchangeWorkflowService->getExchange($id);
        if (!$exchange) {
            return $this->respondWithError('NOT_FOUND', 'Exchange not found', null, 404);
        }

        if ((int) $exchange['requester_id'] !== $userId && (int) $exchange['provider_id'] !== $userId) {
            return $this->respondWithError('NOT_FOUND', 'Exchange not found', null, 404);
        }

        $success = $this->exchangeWorkflowService->markReadyForConfirmation($id, $userId);
        if (!$success) {
            return $this->error('Unable to complete this exchange', 400);
        }

        $exchange = $this->exchangeWorkflowService->getExchange($id);

        return $this->respondWithData($this->formatExchange($exchange));
    }

    /** POST /api/v2/exchanges/{id}/confirm — confirm hours */
    public function confirm($id): JsonResponse
    {
        $userId = $this->requireAuth();
        $id = (int) $id;

        $exchange = $this->exchangeWorkflowService->getExchange($id);
        if (!$exchange) {
            return $this->respondWithError('NOT_FOUND', 'Exchange not found', null, 404);
        }

        if ((int) $exchange['requester_id'] !== $userId && (int) $exchange['provider_id'] !== $userId) {
            return $this->respondWithError('NOT_FOUND', 'Exchange not found', null, 404);
        }

        $hours = (float) $this->input('hours', 0);
        if ($hours <= 0) {
            return $this->respondWithError('VALIDATION_REQUIRED_FIELD', 'hours must be greater than 0', 'hours', 400);
        }

        $success = $this->exchangeWorkflowService->confirmCompletion($id, $userId, $hours);
        if (!$success) {
            return $this->error('Unable to confirm this exchange', 400);
        }

        $exchange = $this->exchangeWorkflowService->getExchange($id);

        $message = 'Hours confirmed';
        if ($exchange['status'] === 'completed') {
            $message = 'Exchange completed! Credits have been transferred.';
        } elseif ($exchange['status'] === 'disputed') {
            $message = 'Hours recorded. There is a discrepancy - a broker will review.';
        }

        return $this->respondWithData(array_merge($this->formatExchange($exchange), ['message' => $message]));
    }

    /** DELETE /api/v2/exchanges/{id} — cancel exchange */
    public function cancel($id): JsonResponse
    {
        $userId = $this->requireAuth();
        $id = (int) $id;

        $exchange = $this->exchangeWorkflowService->getExchange($id);
        if (!$exchange) {
            return $this->respondWithError('NOT_FOUND', 'Exchange not found', null, 404);
        }

        if ((int) $exchange['requester_id'] !== $userId && (int) $exchange['provider_id'] !== $userId) {
            return $this->respondWithError('NOT_FOUND', 'Exchange not found', null, 404);
        }

        $reason = $this->input('reason', '');

        $success = $this->exchangeWorkflowService->cancelExchange($id, $userId, $reason);
        if (!$success) {
            return $this->error('Unable to cancel this exchange', 400);
        }

        return $this->respondWithData(['message' => 'Exchange cancelled']);
    }

    /**
     * Format exchange for API response.
     */
    private function formatExchange(array $exchange): array
    {
        return [
            'id'            => (int) $exchange['id'],
            'listing_id'    => (int) $exchange['listing_id'],
            'requester_id'  => (int) $exchange['requester_id'],
            'provider_id'   => (int) $exchange['provider_id'],
            'listing'       => [
                'id'    => (int) $exchange['listing_id'],
                'title' => $exchange['listing_title'] ?? null,
                'type'  => $exchange['listing_type'] ?? null,
            ],
            'requester'     => [
                'id'     => (int) $exchange['requester_id'],
                'name'   => $exchange['requester_name'] ?? null,
                'avatar' => $exchange['requester_avatar'] ?? null,
            ],
            'provider'      => [
                'id'     => (int) $exchange['provider_id'],
                'name'   => $exchange['provider_name'] ?? null,
                'avatar' => $exchange['provider_avatar'] ?? null,
            ],
            'proposed_hours'           => (float) $exchange['proposed_hours'],
            'prep_time'                => isset($exchange['prep_time']) && $exchange['prep_time'] !== null ? (float) $exchange['prep_time'] : null,
            'final_hours'              => $exchange['final_hours'] ? (float) $exchange['final_hours'] : null,
            'status'                   => $exchange['status'],
            'risk_level'               => $exchange['risk_level'] ?? null,
            'message'                  => $exchange['requester_notes'] ?? null,
            'requester_confirmed_at'   => $exchange['requester_confirmed_at'] ?? null,
            'requester_confirmed_hours' => $exchange['requester_confirmed_hours'] ? (float) $exchange['requester_confirmed_hours'] : null,
            'provider_confirmed_at'    => $exchange['provider_confirmed_at'] ?? null,
            'provider_confirmed_hours' => $exchange['provider_confirmed_hours'] ? (float) $exchange['provider_confirmed_hours'] : null,
            'broker_notes'             => $exchange['broker_notes'] ?? null,
            'created_at'               => $exchange['created_at'],
        ];
    }
}
