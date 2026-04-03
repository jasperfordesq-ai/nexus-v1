<?php
// Copyright © 2024–2026 Jasper Ford
// SPDX-License-Identifier: AGPL-3.0-or-later
// Author: Jasper Ford
// See NOTICE file for attribution and acknowledgements.

namespace App\Http\Controllers\Api;

use Illuminate\Http\JsonResponse;
use App\Services\GroupWebhookService;

/**
 * GroupWebhookController — Webhook registration, listing, deletion, and toggling for groups.
 */
class GroupWebhookController extends BaseApiController
{
    protected bool $isV2Api = true;

    /**
     * GET /api/v2/groups/{id}/webhooks
     *
     * List all webhooks for a group.
     */
    public function index(int $id): JsonResponse
    {
        $userId = $this->requireUserId();
        if ($userId instanceof JsonResponse) {
            return $userId;
        }

        $result = GroupWebhookService::list($id);

        return $this->successResponse($result);
    }

    /**
     * POST /api/v2/groups/{id}/webhooks
     *
     * Register a new webhook for a group.
     * Body: { url: string, events: string[], secret?: string }
     */
    public function store(int $id): JsonResponse
    {
        $userId = $this->requireUserId();
        if ($userId instanceof JsonResponse) {
            return $userId;
        }

        $url = request()->input('url');
        $events = request()->input('events');
        $secret = request()->input('secret');

        if (empty($url)) {
            return $this->errorResponse('URL is required', 422);
        }
        if (!filter_var($url, FILTER_VALIDATE_URL)) {
            return $this->errorResponse('A valid URL is required', 422);
        }
        if (!is_array($events) || empty($events)) {
            return $this->errorResponse('A valid events array is required', 422);
        }

        $result = GroupWebhookService::register($id, $url, $events, $secret);

        if ($result === null) {
            return $this->errorResponse('Failed to register webhook', 400);
        }

        return $this->successResponse($result, 201);
    }

    /**
     * DELETE /api/v2/groups/{id}/webhooks/{webhookId}
     *
     * Delete a webhook.
     */
    public function destroy(int $id, int $webhookId): JsonResponse
    {
        $userId = $this->requireUserId();
        if ($userId instanceof JsonResponse) {
            return $userId;
        }

        $success = GroupWebhookService::delete($webhookId);

        if (!$success) {
            return $this->errorResponse('Webhook not found', 404);
        }

        return $this->successResponse(['message' => 'Webhook deleted']);
    }

    /**
     * PUT /api/v2/groups/{id}/webhooks/{webhookId}/toggle
     *
     * Toggle a webhook's active state.
     * Body: { is_active: bool }
     */
    public function toggle(int $id, int $webhookId): JsonResponse
    {
        $userId = $this->requireUserId();
        if ($userId instanceof JsonResponse) {
            return $userId;
        }

        $isActive = (bool) request()->input('is_active');

        $success = GroupWebhookService::toggle($webhookId, $isActive);

        if (!$success) {
            return $this->errorResponse('Webhook not found', 404);
        }

        return $this->successResponse(['is_active' => $isActive]);
    }
}
