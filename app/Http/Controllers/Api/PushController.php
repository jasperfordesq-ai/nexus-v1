<?php
// Copyright © 2024–2026 Jasper Ford
// SPDX-License-Identifier: AGPL-3.0-or-later
// Author: Jasper Ford
// See NOTICE file for attribution and acknowledgements.

namespace App\Http\Controllers\Api;

use App\Services\PushNotificationService;
use Illuminate\Http\JsonResponse;

/**
 * PushController — Push notification subscription management.
 */
class PushController extends BaseApiController
{
    protected bool $isV2Api = true;

    public function __construct(
        private readonly PushNotificationService $pushService,
    ) {}

    /**
     * POST /api/v2/push/subscribe
     *
     * Subscribe the current device to push notifications.
     * Body: endpoint, keys (p256dh, auth), device_name (optional).
     */
    public function subscribe(): JsonResponse
    {
        $userId = $this->requireAuth();
        $tenantId = $this->getTenantId();
        $data = $this->getAllInput();

        $subscription = $this->pushService->subscribe($userId, $tenantId, $data);

        return $this->respondWithData($subscription, null, 201);
    }

    /**
     * POST /api/v2/push/unsubscribe
     *
     * Unsubscribe a device from push notifications.
     * Body: endpoint (required).
     */
    public function unsubscribe(): JsonResponse
    {
        $userId = $this->requireAuth();

        $endpoint = $this->requireInput('endpoint');
        $this->pushService->unsubscribe($userId, $endpoint);

        return $this->respondWithData(['message' => 'Unsubscribed successfully']);
    }

    /**
     * GET /api/v2/push/vapid-key
     *
     * Get the VAPID public key for push subscriptions.
     */
    public function vapidKey(): JsonResponse
    {
        $key = $this->pushService->getVapidPublicKey();

        return $this->respondWithData(['vapid_public_key' => $key]);
    }
}
