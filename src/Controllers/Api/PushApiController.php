<?php

namespace Nexus\Controllers\Api;

use Nexus\Core\Database;
use Nexus\Core\TenantContext;
use Nexus\Core\ApiAuth;

/**
 * Push Notification API Controller
 * Handles VAPID key retrieval, subscription management, and push sending
 */
class PushApiController
{
    use ApiAuth;

    private function jsonResponse($data, $status = 200)
    {
        header('Content-Type: application/json');
        http_response_code($status);
        echo json_encode($data);
        exit;
    }

    private function getUserId()
    {
        return $this->requireAuth();
    }

    /**
     * GET /api/push/vapid-key
     * Returns the VAPID public key for push subscription
     */
    public function vapidKey()
    {
        // Get VAPID public key from environment or tenant config
        $publicKey = $this->getVapidPublicKey();

        if (empty($publicKey)) {
            $this->jsonResponse(['error' => 'Push notifications not configured'], 500);
        }

        $this->jsonResponse(['publicKey' => $publicKey]);
    }

    /**
     * POST /api/push/subscribe
     * Stores the push subscription for the current user
     * Note: CSRF not required - authenticated via session, affects only own subscription,
     * and PWA contexts can have CSRF token sync issues
     */
    public function subscribe()
    {
        try {
            $userId = $this->getUserId();
            $tenantId = TenantContext::getId();

            $input = json_decode(file_get_contents('php://input'), true);

            if (empty($input['endpoint'])) {
                $this->jsonResponse(['error' => 'Invalid subscription data'], 400);
            }

            $db = Database::getConnection();

            // Check if subscription already exists
            $stmt = $db->prepare("SELECT id FROM push_subscriptions WHERE endpoint = ? AND user_id = ?");
            $stmt->execute([$input['endpoint'], $userId]);
            $existing = $stmt->fetch();

            if ($existing) {
                // Update existing subscription
                $stmt = $db->prepare("
                    UPDATE push_subscriptions
                    SET p256dh_key = ?, auth_key = ?, updated_at = NOW()
                    WHERE id = ?
                ");
                $stmt->execute([
                    $input['keys']['p256dh'] ?? null,
                    $input['keys']['auth'] ?? null,
                    $existing['id']
                ]);
            } else {
                // Create new subscription
                $stmt = $db->prepare("
                    INSERT INTO push_subscriptions (user_id, tenant_id, endpoint, p256dh_key, auth_key, created_at, updated_at)
                    VALUES (?, ?, ?, ?, ?, NOW(), NOW())
                ");
                $stmt->execute([
                    $userId,
                    $tenantId,
                    $input['endpoint'],
                    $input['keys']['p256dh'] ?? null,
                    $input['keys']['auth'] ?? null
                ]);
            }

            $this->jsonResponse(['success' => true, 'message' => 'Subscription saved']);
        } catch (\Exception $e) {
            error_log('[PushApi] Subscribe error: ' . $e->getMessage());
            $this->jsonResponse(['error' => 'Database error: ' . $e->getMessage()], 500);
        }
    }

    /**
     * POST /api/push/unsubscribe
     * Removes the push subscription for the current user
     * Note: CSRF not required - authenticated via session, affects only own subscription
     */
    public function unsubscribe()
    {
        $userId = $this->getUserId();

        $input = json_decode(file_get_contents('php://input'), true);

        if (empty($input['endpoint'])) {
            $this->jsonResponse(['error' => 'Invalid request'], 400);
        }

        $db = Database::getConnection();
        $stmt = $db->prepare("DELETE FROM push_subscriptions WHERE endpoint = ? AND user_id = ?");
        $stmt->execute([$input['endpoint'], $userId]);

        $this->jsonResponse(['success' => true, 'message' => 'Unsubscribed']);
    }

    /**
     * POST /api/push/send (Admin only)
     * Sends a push notification to specified users
     */
    public function send()
    {
        // Security: Verify CSRF token for state-changing operations
        \Nexus\Core\Csrf::verifyOrDieJson();

        $userId = $this->getUserId();
        $tenantId = TenantContext::getId();

        // Check if user is admin
        $db = Database::getConnection();
        $stmt = $db->prepare("SELECT role FROM users WHERE id = ?");
        $stmt->execute([$userId]);
        $user = $stmt->fetch();

        if (!$user || !in_array($user['role'], ['admin', 'super_admin'])) {
            $this->jsonResponse(['error' => 'Forbidden'], 403);
        }

        $input = json_decode(file_get_contents('php://input'), true);

        if (empty($input['title']) || empty($input['body'])) {
            $this->jsonResponse(['error' => 'Title and body are required'], 400);
        }

        // Get target subscriptions
        $targetUserIds = $input['user_ids'] ?? null;

        if ($targetUserIds) {
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
            $this->jsonResponse(['success' => false, 'message' => 'No subscribers found']);
        }

        // Send push notifications
        $sent = 0;
        $failed = 0;

        foreach ($subscriptions as $sub) {
            $result = $this->sendPush($sub, [
                'title' => $input['title'],
                'body' => $input['body'],
                'icon' => $input['icon'] ?? '/assets/images/pwa/icon.svg',
                'badge' => $input['badge'] ?? '/assets/images/pwa/badge.png',
                'url' => $input['url'] ?? '/',
                'tag' => $input['tag'] ?? 'nexus-notification'
            ]);

            if ($result) {
                $sent++;
            } else {
                $failed++;
            }
        }

        $this->jsonResponse([
            'success' => true,
            'sent' => $sent,
            'failed' => $failed
        ]);
    }

    /**
     * GET /api/push/status
     * Returns push subscription status for current user
     */
    public function status()
    {
        $userId = $this->getUserId();

        $db = Database::getConnection();
        $stmt = $db->prepare("SELECT COUNT(*) as count FROM push_subscriptions WHERE user_id = ?");
        $stmt->execute([$userId]);
        $result = $stmt->fetch();

        $this->jsonResponse([
            'subscribed' => $result['count'] > 0,
            'subscriptionCount' => (int)$result['count']
        ]);
    }

    /**
     * POST /api/push/register-device
     * Registers an FCM device token for native Android push notifications
     * Note: CSRF not required - authenticated via session, affects only own device
     */
    public function registerDevice()
    {
        $input = json_decode(file_get_contents('php://input'), true);

        if (empty($input['token'])) {
            $this->jsonResponse(['error' => 'Token is required'], 400);
        }

        // User might not be logged in yet - device can be registered
        // and associated with user later when they log in
        $userId = $_SESSION['user_id'] ?? null;

        if (!$userId) {
            // Store token without user association for now
            // It will be associated when user logs in
            $this->jsonResponse([
                'success' => true,
                'message' => 'Token received - will be associated on login',
                'pending' => true
            ]);
            return;
        }

        try {
            \Nexus\Services\FCMPushService::ensureTableExists();

            $result = \Nexus\Services\FCMPushService::registerDevice(
                $userId,
                $input['token'],
                $input['platform'] ?? 'android'
            );

            if ($result) {
                $this->jsonResponse([
                    'success' => true,
                    'message' => 'Device registered for push notifications'
                ]);
            } else {
                $this->jsonResponse(['error' => 'Failed to register device'], 500);
            }
        } catch (\Exception $e) {
            error_log('[PushApi] Register device error: ' . $e->getMessage());
            $this->jsonResponse(['error' => 'Server error'], 500);
        }
    }

    /**
     * POST /api/push/unregister-device
     * Removes an FCM device token
     */
    public function unregisterDevice()
    {
        $input = json_decode(file_get_contents('php://input'), true);

        if (empty($input['token'])) {
            $this->jsonResponse(['error' => 'Token is required'], 400);
        }

        try {
            $result = \Nexus\Services\FCMPushService::unregisterDevice($input['token']);

            $this->jsonResponse([
                'success' => $result,
                'message' => $result ? 'Device unregistered' : 'Failed to unregister'
            ]);
        } catch (\Exception $e) {
            error_log('[PushApi] Unregister device error: ' . $e->getMessage());
            $this->jsonResponse(['error' => 'Server error'], 500);
        }
    }

    /**
     * Helper: Get VAPID public key from config
     */
    private function getVapidPublicKey()
    {
        // First check .env at project root
        $envPath = dirname(__DIR__, 3) . '/.env';
        if (file_exists($envPath)) {
            $lines = file($envPath, FILE_IGNORE_NEW_LINES | FILE_SKIP_EMPTY_LINES);
            foreach ($lines as $line) {
                $line = trim($line);
                if (strpos($line, 'VAPID_PUBLIC_KEY=') === 0) {
                    return trim(substr($line, 17), '"\'');
                }
            }
        }

        // Also check httpdocs/.env (Plesk structure)
        $envPath2 = dirname(__DIR__, 3) . '/httpdocs/../.env';
        if (file_exists($envPath2)) {
            $lines = file($envPath2, FILE_IGNORE_NEW_LINES | FILE_SKIP_EMPTY_LINES);
            foreach ($lines as $line) {
                $line = trim($line);
                if (strpos($line, 'VAPID_PUBLIC_KEY=') === 0) {
                    return trim(substr($line, 17), '"\'');
                }
            }
        }

        // Fallback to tenant config
        try {
            if (class_exists('Nexus\Core\TenantContext')) {
                $tenant = TenantContext::get();
                if (!empty($tenant['configuration'])) {
                    $config = json_decode($tenant['configuration'], true);
                    if (!empty($config['vapid_public_key'])) {
                        return $config['vapid_public_key'];
                    }
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
    private function getVapidPrivateKey()
    {
        // First check .env at project root
        $envPath = dirname(__DIR__, 3) . '/.env';
        if (file_exists($envPath)) {
            $lines = file($envPath, FILE_IGNORE_NEW_LINES | FILE_SKIP_EMPTY_LINES);
            foreach ($lines as $line) {
                $line = trim($line);
                if (strpos($line, 'VAPID_PRIVATE_KEY=') === 0) {
                    return trim(substr($line, 18), '"\'');
                }
            }
        }

        // Also check httpdocs/.env (Plesk structure)
        $envPath2 = dirname(__DIR__, 3) . '/httpdocs/../.env';
        if (file_exists($envPath2)) {
            $lines = file($envPath2, FILE_IGNORE_NEW_LINES | FILE_SKIP_EMPTY_LINES);
            foreach ($lines as $line) {
                $line = trim($line);
                if (strpos($line, 'VAPID_PRIVATE_KEY=') === 0) {
                    return trim(substr($line, 18), '"\'');
                }
            }
        }

        // Fallback to tenant config
        try {
            if (class_exists('Nexus\Core\TenantContext')) {
                $tenant = TenantContext::get();
                if (!empty($tenant['configuration'])) {
                    $config = json_decode($tenant['configuration'], true);
                    if (!empty($config['vapid_private_key'])) {
                        return $config['vapid_private_key'];
                    }
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
    private function sendPush($subscription, $payload)
    {
        $publicKey = $this->getVapidPublicKey();
        $privateKey = $this->getVapidPrivateKey();

        if (empty($publicKey) || empty($privateKey)) {
            error_log('[Push] VAPID keys not configured');
            return false;
        }

        // Check if web-push library is available
        if (!class_exists('Minishlink\WebPush\WebPush')) {
            // Fallback: Log and return false
            error_log('[Push] web-push library not installed. Run: composer require minishlink/web-push');
            return false;
        }

        try {
            $auth = [
                'VAPID' => [
                    'subject' => 'mailto:' . ($_ENV['MAIL_FROM'] ?? 'admin@nexus.local'),
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
