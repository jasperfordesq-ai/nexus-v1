<?php
// Copyright © 2024–2026 Jasper Ford
// SPDX-License-Identifier: AGPL-3.0-or-later
// Author: Jasper Ford
// See NOTICE file for attribution and acknowledgements.

namespace Nexus\Controllers\Api;

use Nexus\Core\Database;
use Nexus\Core\TenantContext;

/**
 * Push Notification API Controller
 *
 * Handles VAPID key retrieval, subscription management, and push sending.
 * Supports both session-based and Bearer token authentication.
 *
 * Response Format:
 * Success: { "data": {...} }
 * Error:   { "errors": [{ "code": "...", "message": "..." }] }
 */
class PushApiController extends BaseApiController
{
    /**
     * GET /api/push/vapid-key
     *
     * Returns the VAPID public key for push subscription.
     * Public endpoint - no authentication required.
     *
     * Response: 200 OK with public key
     */
    public function vapidKey(): void
    {
        $publicKey = $this->getVapidPublicKey();

        if (empty($publicKey)) {
            $this->respondWithError('PUSH_NOT_CONFIGURED', 'Push notifications not configured', null, 500);
        }

        $this->respondWithData(['public_key' => $publicKey]);
    }

    /**
     * POST /api/push/subscribe
     *
     * Stores the push subscription for the current user.
     * Note: CSRF not required - authenticated via session/Bearer, affects only own subscription
     *
     * Request Body (JSON):
     * {
     *   "endpoint": string (required),
     *   "keys": { "p256dh": string, "auth": string }
     * }
     *
     * Response: 200 OK on success
     */
    public function subscribe(): void
    {
        $userId = $this->getUserId();
        $tenantId = $this->getAuthenticatedTenantId() ?? TenantContext::getId();
        $this->rateLimit('push_subscribe', 10, 60);

        $endpoint = $this->input('endpoint');
        $keys = $this->input('keys', []);

        if (empty($endpoint)) {
            $this->respondWithError('VALIDATION_ERROR', 'Endpoint is required', 'endpoint', 400);
        }

        try {
            $db = Database::getConnection();

            // Check if subscription already exists
            $stmt = $db->prepare("SELECT id FROM push_subscriptions WHERE endpoint = ? AND user_id = ?");
            $stmt->execute([$endpoint, $userId]);
            $existing = $stmt->fetch();

            if ($existing) {
                // Update existing subscription
                $stmt = $db->prepare("
                    UPDATE push_subscriptions
                    SET p256dh_key = ?, auth_key = ?, updated_at = NOW()
                    WHERE id = ?
                ");
                $stmt->execute([
                    $keys['p256dh'] ?? null,
                    $keys['auth'] ?? null,
                    $existing['id']
                ]);
                $action = 'updated';
            } else {
                // Create new subscription
                $stmt = $db->prepare("
                    INSERT INTO push_subscriptions (user_id, tenant_id, endpoint, p256dh_key, auth_key, created_at, updated_at)
                    VALUES (?, ?, ?, ?, ?, NOW(), NOW())
                ");
                $stmt->execute([
                    $userId,
                    $tenantId,
                    $endpoint,
                    $keys['p256dh'] ?? null,
                    $keys['auth'] ?? null
                ]);
                $action = 'created';
            }

            $this->respondWithData([
                'subscribed' => true,
                'action' => $action
            ]);
        } catch (\Exception $e) {
            error_log('[PushApi] Subscribe error: ' . $e->getMessage());
            $this->respondWithError('SUBSCRIBE_FAILED', 'Failed to save subscription', null, 500);
        }
    }

    /**
     * POST /api/push/unsubscribe
     *
     * Removes the push subscription for the current user.
     *
     * Request Body (JSON):
     * { "endpoint": string (required) }
     *
     * Response: 200 OK on success
     */
    public function unsubscribe(): void
    {
        $userId = $this->getUserId();
        $this->rateLimit('push_unsubscribe', 10, 60);

        $endpoint = $this->input('endpoint');

        if (empty($endpoint)) {
            $this->respondWithError('VALIDATION_ERROR', 'Endpoint is required', 'endpoint', 400);
        }

        $db = Database::getConnection();
        $stmt = $db->prepare("DELETE FROM push_subscriptions WHERE endpoint = ? AND user_id = ?");
        $stmt->execute([$endpoint, $userId]);

        $this->respondWithData(['unsubscribed' => true]);
    }

    /**
     * POST /api/push/send
     *
     * Sends a push notification to specified users (admin only).
     *
     * Request Body (JSON):
     * {
     *   "title": string (required),
     *   "body": string (required),
     *   "icon": string (optional),
     *   "badge": string (optional),
     *   "url": string (optional),
     *   "tag": string (optional),
     *   "user_ids": int[] (optional, if empty sends to all subscribers)
     * }
     *
     * Response: 200 OK with send results
     */
    public function send(): void
    {
        $this->requireAdmin();
        $this->verifyCsrf();
        $this->rateLimit('push_send', 10, 60);

        $tenantId = $this->getAuthenticatedTenantId() ?? TenantContext::getId();

        $title = $this->input('title');
        $body = $this->input('body');

        if (empty($title) || empty($body)) {
            $this->respondWithError('VALIDATION_ERROR', 'Title and body are required', null, 400);
        }

        $db = Database::getConnection();

        // Get target subscriptions
        $targetUserIds = $this->input('user_ids');

        if ($targetUserIds && is_array($targetUserIds)) {
            $placeholders = str_repeat('?,', count($targetUserIds) - 1) . '?';
            $stmt = $db->prepare("
                SELECT * FROM push_subscriptions
                WHERE tenant_id = ? AND user_id IN ($placeholders)
            ");
            $params = array_merge([$tenantId], $targetUserIds);
            $stmt->execute($params);
        } else {
            // Send to all subscribers in tenant
            $stmt = $db->prepare("SELECT * FROM push_subscriptions WHERE tenant_id = ?");
            $stmt->execute([$tenantId]);
        }

        $subscriptions = $stmt->fetchAll();

        if (empty($subscriptions)) {
            $this->respondWithData([
                'sent' => 0,
                'failed' => 0,
                'message' => 'No subscribers found'
            ]);
            return;
        }

        // Send push notifications
        $sent = 0;
        $failed = 0;

        foreach ($subscriptions as $sub) {
            $result = $this->sendPush($sub, [
                'title' => $title,
                'body' => $body,
                'icon' => $this->input('icon', '/assets/images/pwa/icon.svg'),
                'badge' => $this->input('badge', '/assets/images/pwa/badge.png'),
                'url' => $this->input('url', '/'),
                'tag' => $this->input('tag', 'nexus-notification')
            ]);

            if ($result) {
                $sent++;
            } else {
                $failed++;
            }
        }

        $this->respondWithData([
            'sent' => $sent,
            'failed' => $failed,
            'total' => count($subscriptions)
        ]);
    }

    /**
     * GET /api/push/status
     *
     * Returns push subscription status for current user.
     *
     * Response: 200 OK with subscription status
     */
    public function status(): void
    {
        $userId = $this->getUserId();

        $db = Database::getConnection();
        $stmt = $db->prepare("SELECT COUNT(*) as count FROM push_subscriptions WHERE user_id = ?");
        $stmt->execute([$userId]);
        $result = $stmt->fetch();

        $this->respondWithData([
            'subscribed' => $result['count'] > 0,
            'subscription_count' => (int)$result['count']
        ]);
    }

    /**
     * POST /api/push/register-device
     *
     * Registers an FCM device token for native Android push notifications.
     *
     * Request Body (JSON):
     * {
     *   "token": string (required),
     *   "platform": "android" | "ios" (optional, default: android)
     * }
     *
     * Response: 200 OK on success
     */
    public function registerDevice(): void
    {
        $this->rateLimit('push_register_device', 10, 60);

        $token = $this->input('token');
        $platform = $this->input('platform', 'android');

        if (empty($token)) {
            $this->respondWithError('VALIDATION_ERROR', 'Token is required', 'token', 400);
        }

        // User might not be logged in yet - device can be registered
        // and associated with user later when they log in
        $userId = $this->getOptionalUserId();

        if (!$userId) {
            // Store token without user association for now
            // It will be associated when user logs in
            $this->respondWithData([
                'registered' => false,
                'pending' => true,
                'message' => 'Token received - will be associated on login'
            ]);
            return;
        }

        try {
            \Nexus\Services\FCMPushService::ensureTableExists();

            $result = \Nexus\Services\FCMPushService::registerDevice(
                $userId,
                $token,
                $platform
            );

            if ($result) {
                $this->respondWithData([
                    'registered' => true,
                    'message' => 'Device registered for push notifications'
                ]);
            } else {
                $this->respondWithError('REGISTRATION_FAILED', 'Failed to register device', null, 500);
            }
        } catch (\Exception $e) {
            error_log('[PushApi] Register device error: ' . $e->getMessage());
            $this->respondWithError('SERVER_ERROR', 'Server error', null, 500);
        }
    }

    /**
     * POST /api/push/unregister-device
     *
     * Removes an FCM device token.
     *
     * Request Body (JSON):
     * { "token": string (required) }
     *
     * Response: 200 OK on success
     */
    public function unregisterDevice(): void
    {
        $this->rateLimit('push_unregister_device', 10, 60);

        $token = $this->input('token');

        if (empty($token)) {
            $this->respondWithError('VALIDATION_ERROR', 'Token is required', 'token', 400);
        }

        try {
            $result = \Nexus\Services\FCMPushService::unregisterDevice($token);

            $this->respondWithData([
                'unregistered' => $result,
                'message' => $result ? 'Device unregistered' : 'Device not found'
            ]);
        } catch (\Exception $e) {
            error_log('[PushApi] Unregister device error: ' . $e->getMessage());
            $this->respondWithError('SERVER_ERROR', 'Server error', null, 500);
        }
    }

    /**
     * Helper: Get VAPID public key from config
     */
    private function getVapidPublicKey(): ?string
    {
        // Check environment variable first
        $key = getenv('VAPID_PUBLIC_KEY') ?: ($_ENV['VAPID_PUBLIC_KEY'] ?? null);
        if ($key) {
            return $key;
        }

        // Fallback to tenant config
        try {
            $tenant = TenantContext::get();
            if (!empty($tenant['configuration'])) {
                $config = json_decode($tenant['configuration'], true);
                if (!empty($config['vapid_public_key'])) {
                    return $config['vapid_public_key'];
                }
            }
        } catch (\Exception $e) {
            // Tenant context not available, continue
        }

        return null;
    }

    /**
     * Helper: Get VAPID private key from config
     */
    private function getVapidPrivateKey(): ?string
    {
        // Check environment variable first
        $key = getenv('VAPID_PRIVATE_KEY') ?: ($_ENV['VAPID_PRIVATE_KEY'] ?? null);
        if ($key) {
            return $key;
        }

        // Fallback to tenant config
        try {
            $tenant = TenantContext::get();
            if (!empty($tenant['configuration'])) {
                $config = json_decode($tenant['configuration'], true);
                if (!empty($config['vapid_private_key'])) {
                    return $config['vapid_private_key'];
                }
            }
        } catch (\Exception $e) {
            // Tenant context not available, continue
        }

        return null;
    }

    /**
     * Helper: Send push notification using Web Push protocol
     */
    private function sendPush(array $subscription, array $payload): bool
    {
        $publicKey = $this->getVapidPublicKey();
        $privateKey = $this->getVapidPrivateKey();

        if (empty($publicKey) || empty($privateKey)) {
            error_log('[Push] VAPID keys not configured');
            return false;
        }

        // Check if web-push library is available
        if (!class_exists('Minishlink\WebPush\WebPush')) {
            error_log('[Push] web-push library not installed. Run: composer require minishlink/web-push');
            return false;
        }

        try {
            $auth = [
                'VAPID' => [
                    'subject' => 'mailto:' . (getenv('MAIL_FROM') ?: 'admin@nexus.local'),
                    'publicKey' => $publicKey,
                    'privateKey' => $privateKey,
                ],
            ];

            $webPush = new \Minishlink\WebPush\WebPush($auth);

            $webPush->queueNotification(
                \Minishlink\WebPush\Subscription::create([
                    'endpoint' => $subscription['endpoint'],
                    'keys' => [
                        'p256dh' => $subscription['p256dh_key'],
                        'auth' => $subscription['auth_key'],
                    ],
                ]),
                json_encode($payload)
            );

            $reports = $webPush->flush();

            foreach ($reports as $report) {
                if (!$report->isSuccess()) {
                    error_log('[Push] Failed: ' . $report->getReason());

                    // Remove invalid subscription
                    if ($report->isSubscriptionExpired()) {
                        $db = Database::getConnection();
                        $stmt = $db->prepare("DELETE FROM push_subscriptions WHERE endpoint = ?");
                        $stmt->execute([$subscription['endpoint']]);
                    }

                    return false;
                }
            }

            return true;

        } catch (\Exception $e) {
            error_log('[Push] Exception: ' . $e->getMessage());
            return false;
        }
    }
}
