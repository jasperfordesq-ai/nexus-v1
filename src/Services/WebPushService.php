<?php
// Copyright © 2024–2026 Jasper Ford
// SPDX-License-Identifier: AGPL-3.0-or-later
// Author: Jasper Ford
// See NOTICE file for attribution and acknowledgements.

namespace Nexus\Services;

use Nexus\Core\Database;
use Nexus\Core\TenantContext;

/**
 * WebPushService - Handles sending Web Push notifications to user devices
 *
 * This service is called automatically when notifications are created,
 * sending real-time push notifications to all subscribed devices.
 */
class WebPushService
{
    /**
     * Send a push notification to a specific user
     *
     * @param int $userId Target user ID
     * @param string $title Notification title
     * @param string $body Notification body text
     * @param string|null $link URL to open when clicked
     * @param string $type Notification type (message, transaction, event, reminder, general)
     * @param array $options Additional options (icon, badge, image, tag, etc.)
     * @return array Results with sent/failed counts
     */
    public static function sendToUser($userId, $title, $body, $link = null, $type = 'general', $options = [])
    {
        $db = Database::getConnection();
        $tenantId = TenantContext::getId();

        // Get all push subscriptions for this user
        $stmt = $db->prepare("
            SELECT * FROM push_subscriptions
            WHERE user_id = ? AND tenant_id = ?
        ");
        $stmt->execute([$userId, $tenantId]);
        $subscriptions = $stmt->fetchAll();

        if (empty($subscriptions)) {
            return ['sent' => 0, 'failed' => 0, 'reason' => 'No subscriptions found'];
        }

        $payload = self::buildPayload($title, $body, $link, $type, $options);

        return self::sendToSubscriptions($subscriptions, $payload);
    }

    /**
     * Send a push notification to multiple users
     *
     * @param array $userIds Array of user IDs
     * @param string $title Notification title
     * @param string $body Notification body text
     * @param string|null $link URL to open when clicked
     * @param string $type Notification type
     * @param array $options Additional options
     * @return array Results with sent/failed counts
     */
    public static function sendToUsers($userIds, $title, $body, $link = null, $type = 'general', $options = [])
    {
        if (empty($userIds)) {
            return ['sent' => 0, 'failed' => 0, 'reason' => 'No user IDs provided'];
        }

        $db = Database::getConnection();
        $tenantId = TenantContext::getId();

        $placeholders = str_repeat('?,', count($userIds) - 1) . '?';
        $stmt = $db->prepare("
            SELECT * FROM push_subscriptions
            WHERE user_id IN ($placeholders) AND tenant_id = ?
        ");
        $params = array_merge($userIds, [$tenantId]);
        $stmt->execute($params);
        $subscriptions = $stmt->fetchAll();

        if (empty($subscriptions)) {
            return ['sent' => 0, 'failed' => 0, 'reason' => 'No subscriptions found'];
        }

        $payload = self::buildPayload($title, $body, $link, $type, $options);

        return self::sendToSubscriptions($subscriptions, $payload);
    }

    /**
     * Build the push notification payload
     */
    private static function buildPayload($title, $body, $link, $type, $options = [])
    {
        // Default icons and badges based on notification type
        // Using existing icons - type-specific icons can be added later
        $typeConfig = [
            'message' => [
                'icon' => '/assets/images/pwa/icon-192x192.png',
                'badge' => '/assets/images/pwa/icon-72x72.png',
                'tag' => 'nexus-message'
            ],
            'transaction' => [
                'icon' => '/assets/images/pwa/icon-192x192.png',
                'badge' => '/assets/images/pwa/icon-72x72.png',
                'tag' => 'nexus-transaction'
            ],
            'event' => [
                'icon' => '/assets/images/pwa/icon-192x192.png',
                'badge' => '/assets/images/pwa/icon-72x72.png',
                'tag' => 'nexus-event'
            ],
            'reminder' => [
                'icon' => '/assets/images/pwa/icon-192x192.png',
                'badge' => '/assets/images/pwa/icon-72x72.png',
                'tag' => 'nexus-reminder'
            ],
            'general' => [
                'icon' => '/assets/images/pwa/icon-192x192.png',
                'badge' => '/assets/images/pwa/icon-72x72.png',
                'tag' => 'nexus-notification'
            ]
        ];

        $config = $typeConfig[$type] ?? $typeConfig['general'];

        return [
            'title' => $title,
            'body' => $body,
            'icon' => $options['icon'] ?? $config['icon'],
            'badge' => $options['badge'] ?? $config['badge'],
            'url' => $link ?? '/',
            'tag' => $options['tag'] ?? $config['tag'],
            'type' => $type,
            'timestamp' => time() * 1000,
            'requireInteraction' => $options['requireInteraction'] ?? false,
            'renotify' => $options['renotify'] ?? true,
            'data' => array_merge([
                'url' => $link ?? '/',
                'type' => $type
            ], $options['data'] ?? [])
        ];
    }

    /**
     * Send push notifications to a list of subscriptions
     */
    private static function sendToSubscriptions($subscriptions, $payload)
    {
        $publicKey = self::getVapidPublicKey();
        $privateKey = self::getVapidPrivateKey();

        if (empty($publicKey) || empty($privateKey)) {
            error_log('[WebPush] VAPID keys not configured');
            return ['sent' => 0, 'failed' => count($subscriptions), 'reason' => 'VAPID keys not configured'];
        }

        // Check if web-push library is available
        if (!class_exists('Minishlink\WebPush\WebPush')) {
            error_log('[WebPush] Minishlink\WebPush library not installed. Run: composer require minishlink/web-push');
            return ['sent' => 0, 'failed' => count($subscriptions), 'reason' => 'WebPush library not installed'];
        }

        $sent = 0;
        $failed = 0;
        $expiredEndpoints = [];

        try {
            $auth = [
                'VAPID' => [
                    'subject' => 'mailto:' . self::getMailFrom(),
                    'publicKey' => $publicKey,
                    'privateKey' => $privateKey,
                ],
            ];

            $webPush = new \Minishlink\WebPush\WebPush($auth);

            // Queue all notifications
            foreach ($subscriptions as $sub) {
                if (empty($sub['endpoint']) || empty($sub['p256dh_key']) || empty($sub['auth_key'])) {
                    $failed++;
                    continue;
                }

                $webPush->queueNotification(
                    \Minishlink\WebPush\Subscription::create([
                        'endpoint' => $sub['endpoint'],
                        'keys' => [
                            'p256dh' => $sub['p256dh_key'],
                            'auth' => $sub['auth_key'],
                        ],
                    ]),
                    json_encode($payload)
                );
            }

            // Flush and process reports
            $reports = $webPush->flush();

            foreach ($reports as $report) {
                if ($report->isSuccess()) {
                    $sent++;
                } else {
                    $failed++;
                    error_log('[WebPush] Failed: ' . $report->getReason());

                    // Track expired subscriptions for cleanup
                    if ($report->isSubscriptionExpired()) {
                        $expiredEndpoints[] = $report->getEndpoint();
                    }
                }
            }

            // Clean up expired subscriptions
            if (!empty($expiredEndpoints)) {
                self::cleanupExpiredSubscriptions($expiredEndpoints);
            }

        } catch (\Exception $e) {
            error_log('[WebPush] Exception: ' . $e->getMessage());
            return ['sent' => $sent, 'failed' => count($subscriptions) - $sent, 'reason' => $e->getMessage()];
        }

        return ['sent' => $sent, 'failed' => $failed];
    }

    /**
     * Remove expired push subscriptions from database
     */
    private static function cleanupExpiredSubscriptions($endpoints)
    {
        if (empty($endpoints)) return;

        $db = Database::getConnection();
        $placeholders = str_repeat('?,', count($endpoints) - 1) . '?';
        $stmt = $db->prepare("DELETE FROM push_subscriptions WHERE endpoint IN ($placeholders)");
        $stmt->execute($endpoints);

        error_log('[WebPush] Cleaned up ' . count($endpoints) . ' expired subscriptions');
    }

    /**
     * Get VAPID public key from environment or tenant config
     */
    private static function getVapidPublicKey()
    {
        // Check environment variable first
        if (!empty($_ENV['VAPID_PUBLIC_KEY'])) {
            return $_ENV['VAPID_PUBLIC_KEY'];
        }

        // Check .env file
        $envPaths = [
            dirname(__DIR__, 2) . '/.env',
            dirname(__DIR__, 2) . '/httpdocs/../.env'
        ];

        foreach ($envPaths as $envPath) {
            if (file_exists($envPath)) {
                $lines = file($envPath, FILE_IGNORE_NEW_LINES | FILE_SKIP_EMPTY_LINES);
                foreach ($lines as $line) {
                    $line = trim($line);
                    if (strpos($line, 'VAPID_PUBLIC_KEY=') === 0) {
                        return trim(substr($line, 17), '"\'');
                    }
                }
            }
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
            // Tenant context not available
        }

        return null;
    }

    /**
     * Get VAPID private key from environment or tenant config
     */
    private static function getVapidPrivateKey()
    {
        // Check environment variable first
        if (!empty($_ENV['VAPID_PRIVATE_KEY'])) {
            return $_ENV['VAPID_PRIVATE_KEY'];
        }

        // Check .env file
        $envPaths = [
            dirname(__DIR__, 2) . '/.env',
            dirname(__DIR__, 2) . '/httpdocs/../.env'
        ];

        foreach ($envPaths as $envPath) {
            if (file_exists($envPath)) {
                $lines = file($envPath, FILE_IGNORE_NEW_LINES | FILE_SKIP_EMPTY_LINES);
                foreach ($lines as $line) {
                    $line = trim($line);
                    if (strpos($line, 'VAPID_PRIVATE_KEY=') === 0) {
                        return trim(substr($line, 18), '"\'');
                    }
                }
            }
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
            // Tenant context not available
        }

        return null;
    }

    /**
     * Get mail from address for VAPID subject
     */
    private static function getMailFrom()
    {
        if (!empty($_ENV['MAIL_FROM'])) {
            return $_ENV['MAIL_FROM'];
        }

        // Check .env file
        $envPath = dirname(__DIR__, 2) . '/.env';
        if (file_exists($envPath)) {
            $lines = file($envPath, FILE_IGNORE_NEW_LINES | FILE_SKIP_EMPTY_LINES);
            foreach ($lines as $line) {
                $line = trim($line);
                if (strpos($line, 'MAIL_FROM=') === 0) {
                    return trim(substr($line, 10), '"\'');
                }
            }
        }

        return 'admin@nexus.local';
    }
}
