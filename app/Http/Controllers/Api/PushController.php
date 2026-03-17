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
     */
    public function send(): JsonResponse
    {
        $this->requireAdmin();
        $this->rateLimit('push_send', 10, 60);

        $tenantId = $this->getTenantId();
        $title = $this->input('title');
        $body = $this->input('body');

        if (empty($title) || empty($body)) {
            return $this->respondWithError('VALIDATION_ERROR', 'Title and body are required', null, 400);
        }

        $db = Database::getConnection();
        $targetUserIds = $this->input('user_ids');

        if ($targetUserIds && is_array($targetUserIds)) {
            $placeholders = str_repeat('?,', count($targetUserIds) - 1) . '?';
            $stmt = $db->prepare("SELECT * FROM push_subscriptions WHERE tenant_id = ? AND user_id IN ($placeholders)");
            $stmt->execute(array_merge([$tenantId], $targetUserIds));
        } else {
            $stmt = $db->prepare("SELECT * FROM push_subscriptions WHERE tenant_id = ?");
            $stmt->execute([$tenantId]);
        }

        $subscriptions = $stmt->fetchAll();

        if (empty($subscriptions)) {
            return $this->respondWithData(['sent' => 0, 'failed' => 0, 'message' => 'No subscribers found']);
        }

        $sent = 0;
        $failed = 0;

        $pushController = new \Nexus\Controllers\Api\PushApiController();

        foreach ($subscriptions as $sub) {
            // Use reflection to call the private sendPush method, or call the service directly
            try {
                $publicKey = \Nexus\Core\Env::get('VAPID_PUBLIC_KEY');
                $privateKey = \Nexus\Core\Env::get('VAPID_PRIVATE_KEY');

                if (empty($publicKey) || empty($privateKey) || !class_exists('Minishlink\WebPush\WebPush')) {
                    $failed++;
                    continue;
                }

                $auth = ['VAPID' => [
                    'subject' => 'mailto:' . (getenv('MAIL_FROM') ?: 'admin@nexus.local'),
                    'publicKey' => $publicKey,
                    'privateKey' => $privateKey,
                ]];

                $webPush = new \Minishlink\WebPush\WebPush($auth);
                $webPush->queueNotification(
                    \Minishlink\WebPush\Subscription::create([
                        'endpoint' => $sub['endpoint'],
                        'keys' => ['p256dh' => $sub['p256dh_key'], 'auth' => $sub['auth_key']],
                    ]),
                    json_encode([
                        'title' => $title,
                        'body' => $body,
                        'icon' => $this->input('icon', '/assets/images/pwa/icon.svg'),
                        'badge' => $this->input('badge', '/assets/images/pwa/badge.png'),
                        'url' => $this->input('url', '/'),
                        'tag' => $this->input('tag', 'nexus-notification'),
                    ])
                );

                $reports = $webPush->flush();
                foreach ($reports as $report) {
                    if ($report->isSuccess()) {
                        $sent++;
                    } else {
                        $failed++;
                        if ($report->isSubscriptionExpired()) {
                            $db->prepare("DELETE FROM push_subscriptions WHERE endpoint = ?")->execute([$sub['endpoint']]);
                        }
                    }
                }
            } catch (\Throwable $e) {
                $failed++;
            }
        }

        return $this->respondWithData(['sent' => $sent, 'failed' => $failed, 'total' => count($subscriptions)]);
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

}
