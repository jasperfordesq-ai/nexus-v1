<?php
// Copyright © 2024–2026 Jasper Ford
// SPDX-License-Identifier: AGPL-3.0-or-later
// Author: Jasper Ford
// See NOTICE file for attribution and acknowledgements.

namespace App\Http\Controllers\Api;

use App\Services\PushNotificationService;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Facades\DB;
use Nexus\Core\TenantContext;

/**
 * PushController — Push notification subscription management.
 *
 * Native DB facade implementation for most endpoints.
 * The send() method is kept as delegation because it uses the WebPush library.
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

    /**
     * POST /api/v2/push/send
     *
     * Sends a push notification to specified users (admin only).
     * Kept as delegation because it uses the Minishlink\WebPush library.
     */
    public function send(): JsonResponse
    {
        return $this->delegate(\Nexus\Controllers\Api\PushApiController::class, 'send');
    }

    /**
     * GET /api/v2/push/status
     *
     * Returns push subscription status for current user.
     *
     * Response: { data: { subscribed: bool, subscription_count: N } }
     */
    public function status(): JsonResponse
    {
        $userId = $this->requireAuth();
        $tenantId = $this->getTenantId();

        $count = (int) DB::table('push_subscriptions')
            ->where('user_id', $userId)
            ->where('tenant_id', $tenantId)
            ->count();

        return $this->respondWithData([
            'subscribed' => $count > 0,
            'subscription_count' => $count,
        ]);
    }

    /**
     * POST /api/v2/push/register-device
     *
     * Registers an FCM device token for native Android push notifications.
     * Body: token (required), platform (optional, default: android).
     */
    public function registerDevice(): JsonResponse
    {
        $this->rateLimit('push_register_device', 10, 60);

        $token = $this->input('token');
        $platform = $this->input('platform', 'android');

        if (empty($token)) {
            return $this->respondWithError('VALIDATION_ERROR', 'Token is required', 'token', 400);
        }

        $userId = $this->getOptionalUserId();

        if (!$userId) {
            return $this->respondWithData([
                'registered' => false,
                'pending' => true,
                'message' => 'Token received - will be associated on login',
            ]);
        }

        try {
            \Nexus\Services\FCMPushService::ensureTableExists();

            $result = \Nexus\Services\FCMPushService::registerDevice(
                $userId,
                $token,
                $platform
            );

            if ($result) {
                return $this->respondWithData([
                    'registered' => true,
                    'message' => 'Device registered for push notifications',
                ]);
            }

            return $this->respondWithError('REGISTRATION_FAILED', 'Failed to register device', null, 500);
        } catch (\Exception $e) {
            error_log('[PushApi] Register device error: ' . $e->getMessage());
            return $this->respondWithError('SERVER_ERROR', 'Server error', null, 500);
        }
    }

    /**
     * POST /api/v2/push/unregister-device
     *
     * Removes an FCM device token.
     * Body: token (required).
     */
    public function unregisterDevice(): JsonResponse
    {
        $this->rateLimit('push_unregister_device', 10, 60);

        $token = $this->input('token');

        if (empty($token)) {
            return $this->respondWithError('VALIDATION_ERROR', 'Token is required', 'token', 400);
        }

        try {
            $result = \Nexus\Services\FCMPushService::unregisterDevice($token);

            return $this->respondWithData([
                'unregistered' => $result,
                'message' => $result ? 'Device unregistered' : 'Device not found',
            ]);
        } catch (\Exception $e) {
            error_log('[PushApi] Unregister device error: ' . $e->getMessage());
            return $this->respondWithError('SERVER_ERROR', 'Server error', null, 500);
        }
    }

    /**
     * Delegate to legacy controller via output buffering.
     * Kept only for send() which uses WebPush library.
     */
    private function delegate(string $legacyClass, string $method, array $params = []): JsonResponse
    {
        $controller = new $legacyClass();
        ob_start();
        $controller->$method(...$params);
        $output = ob_get_clean();
        $status = http_response_code();
        return response()->json(json_decode($output, true) ?: $output, $status ?: 200);
    }
}
