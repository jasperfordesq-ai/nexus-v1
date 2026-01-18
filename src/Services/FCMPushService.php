<?php

namespace Nexus\Services;

use Nexus\Core\Database;
use Nexus\Core\TenantContext;

/**
 * FCMPushService - Handles sending Firebase Cloud Messaging notifications
 *
 * Uses FCM HTTP v1 API with service account authentication.
 * The legacy API was deprecated June 2024.
 */
class FCMPushService
{
    private static ?string $accessToken = null;
    private static ?int $tokenExpiry = null;
    private static ?array $serviceAccount = null;

    /**
     * Send a push notification to a specific user's native devices
     */
    public static function sendToUser(int $userId, string $title, string $body, array $data = []): array
    {
        $db = Database::getConnection();
        $tenantId = TenantContext::getId();

        $stmt = $db->prepare("
            SELECT * FROM fcm_device_tokens
            WHERE user_id = ? AND tenant_id = ?
        ");
        $stmt->execute([$userId, $tenantId]);
        $devices = $stmt->fetchAll();

        if (empty($devices)) {
            return ['sent' => 0, 'failed' => 0, 'reason' => 'No FCM devices registered'];
        }

        return self::sendToDevices($devices, $title, $body, $data);
    }

    /**
     * Send a push notification to multiple users
     */
    public static function sendToUsers(array $userIds, string $title, string $body, array $data = []): array
    {
        if (empty($userIds)) {
            return ['sent' => 0, 'failed' => 0, 'reason' => 'No user IDs provided'];
        }

        $db = Database::getConnection();
        $tenantId = TenantContext::getId();

        $placeholders = str_repeat('?,', count($userIds) - 1) . '?';
        $stmt = $db->prepare("
            SELECT * FROM fcm_device_tokens
            WHERE user_id IN ($placeholders) AND tenant_id = ?
        ");
        $params = array_merge($userIds, [$tenantId]);
        $stmt->execute($params);
        $devices = $stmt->fetchAll();

        if (empty($devices)) {
            return ['sent' => 0, 'failed' => 0, 'reason' => 'No FCM devices registered'];
        }

        return self::sendToDevices($devices, $title, $body, $data);
    }

    /**
     * Send notification to a list of devices
     */
    private static function sendToDevices(array $devices, string $title, string $body, array $data = []): array
    {
        $serviceAccount = self::getServiceAccount();

        if (empty($serviceAccount)) {
            error_log('[FCM] Service account not configured');
            return ['sent' => 0, 'failed' => count($devices), 'reason' => 'FCM service account not configured'];
        }

        $sent = 0;
        $failed = 0;
        $invalidTokens = [];

        foreach ($devices as $device) {
            $result = self::sendSingleV1($device['token'], $title, $body, $data);

            if ($result['success']) {
                $sent++;
            } else {
                $failed++;
                if ($result['invalid_token']) {
                    $invalidTokens[] = $device['token'];
                }
            }
        }

        // Clean up invalid tokens
        if (!empty($invalidTokens)) {
            self::cleanupInvalidTokens($invalidTokens);
        }

        return ['sent' => $sent, 'failed' => $failed];
    }

    /**
     * Send a single FCM message using HTTP v1 API
     */
    private static function sendSingleV1(string $token, string $title, string $body, array $data = []): array
    {
        $accessToken = self::getAccessToken();
        $projectId = self::getProjectId();

        if (empty($accessToken) || empty($projectId)) {
            return ['success' => false, 'invalid_token' => false];
        }

        $message = [
            'message' => [
                'token' => $token,
                'notification' => [
                    'title' => $title,
                    'body' => $body,
                ],
                'android' => [
                    'priority' => 'high',
                    'notification' => [
                        'channel_id' => 'nexus_notifications',
                        'icon' => 'ic_notification',
                        'color' => '#6366f1',
                    ],
                ],
                'data' => array_map('strval', array_merge($data, [
                    'title' => $title,
                    'body' => $body,
                ])),
            ],
        ];

        $url = "https://fcm.googleapis.com/v1/projects/{$projectId}/messages:send";

        $ch = curl_init($url);
        curl_setopt_array($ch, [
            CURLOPT_POST => true,
            CURLOPT_HTTPHEADER => [
                'Authorization: Bearer ' . $accessToken,
                'Content-Type: application/json',
            ],
            CURLOPT_POSTFIELDS => json_encode($message),
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_TIMEOUT => 30,
        ]);

        $response = curl_exec($ch);
        $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
        $error = curl_error($ch);
        curl_close($ch);

        if ($error) {
            error_log('[FCM] cURL error: ' . $error);
            return ['success' => false, 'invalid_token' => false];
        }

        $result = json_decode($response, true);

        if ($httpCode === 200) {
            return ['success' => true, 'invalid_token' => false];
        }

        // Check for invalid token errors
        $invalidToken = false;
        if (isset($result['error']['details'])) {
            foreach ($result['error']['details'] as $detail) {
                if (isset($detail['errorCode'])) {
                    $errorCode = $detail['errorCode'];
                    if (in_array($errorCode, ['UNREGISTERED', 'INVALID_ARGUMENT'])) {
                        $invalidToken = true;
                    }
                    error_log('[FCM] Send error: ' . $errorCode);
                }
            }
        } elseif (isset($result['error']['message'])) {
            error_log('[FCM] Send error: ' . $result['error']['message']);
            // Check if it's a token error
            if (strpos($result['error']['message'], 'not a valid FCM registration token') !== false) {
                $invalidToken = true;
            }
        }

        return ['success' => false, 'invalid_token' => $invalidToken];
    }

    /**
     * Get OAuth2 access token for FCM v1 API
     */
    private static function getAccessToken(): ?string
    {
        // Return cached token if still valid
        if (self::$accessToken && self::$tokenExpiry && time() < self::$tokenExpiry - 60) {
            return self::$accessToken;
        }

        $serviceAccount = self::getServiceAccount();
        if (empty($serviceAccount)) {
            return null;
        }

        try {
            // Create JWT for service account
            $now = time();
            $expiry = $now + 3600;

            $header = [
                'alg' => 'RS256',
                'typ' => 'JWT',
            ];

            $payload = [
                'iss' => $serviceAccount['client_email'],
                'scope' => 'https://www.googleapis.com/auth/firebase.messaging',
                'aud' => 'https://oauth2.googleapis.com/token',
                'iat' => $now,
                'exp' => $expiry,
            ];

            $jwt = self::createJWT($header, $payload, $serviceAccount['private_key']);

            // Exchange JWT for access token
            $ch = curl_init('https://oauth2.googleapis.com/token');
            curl_setopt_array($ch, [
                CURLOPT_POST => true,
                CURLOPT_POSTFIELDS => http_build_query([
                    'grant_type' => 'urn:ietf:params:oauth:grant-type:jwt-bearer',
                    'assertion' => $jwt,
                ]),
                CURLOPT_RETURNTRANSFER => true,
                CURLOPT_TIMEOUT => 30,
            ]);

            $response = curl_exec($ch);
            $error = curl_error($ch);
            curl_close($ch);

            if ($error) {
                error_log('[FCM] OAuth error: ' . $error);
                return null;
            }

            $result = json_decode($response, true);

            if (isset($result['access_token'])) {
                self::$accessToken = $result['access_token'];
                self::$tokenExpiry = $now + ($result['expires_in'] ?? 3600);
                return self::$accessToken;
            }

            error_log('[FCM] OAuth failed: ' . ($result['error_description'] ?? 'Unknown error'));
            return null;

        } catch (\Exception $e) {
            error_log('[FCM] Access token error: ' . $e->getMessage());
            return null;
        }
    }

    /**
     * Create a signed JWT
     */
    private static function createJWT(array $header, array $payload, string $privateKey): string
    {
        $headerEncoded = self::base64UrlEncode(json_encode($header));
        $payloadEncoded = self::base64UrlEncode(json_encode($payload));

        $signatureInput = $headerEncoded . '.' . $payloadEncoded;

        openssl_sign($signatureInput, $signature, $privateKey, OPENSSL_ALGO_SHA256);

        $signatureEncoded = self::base64UrlEncode($signature);

        return $signatureInput . '.' . $signatureEncoded;
    }

    /**
     * Base64 URL encode
     */
    private static function base64UrlEncode(string $data): string
    {
        return rtrim(strtr(base64_encode($data), '+/', '-_'), '=');
    }

    /**
     * Get Firebase project ID from service account
     */
    private static function getProjectId(): ?string
    {
        $serviceAccount = self::getServiceAccount();
        return $serviceAccount['project_id'] ?? null;
    }

    /**
     * Get service account configuration
     */
    private static function getServiceAccount(): ?array
    {
        if (self::$serviceAccount !== null) {
            return self::$serviceAccount;
        }

        // Check for service account JSON file
        $paths = [
            dirname(__DIR__, 2) . '/firebase-service-account.json',
            dirname(__DIR__, 2) . '/config/firebase-service-account.json',
            dirname(__DIR__, 2) . '/capacitor/firebase-service-account.json',
        ];

        foreach ($paths as $path) {
            if (file_exists($path)) {
                $content = file_get_contents($path);
                $data = json_decode($content, true);
                if ($data && isset($data['project_id']) && isset($data['private_key'])) {
                    self::$serviceAccount = $data;
                    return self::$serviceAccount;
                }
            }
        }

        // Check environment variable for JSON content
        if (!empty($_ENV['FCM_SERVICE_ACCOUNT_JSON'])) {
            $data = json_decode($_ENV['FCM_SERVICE_ACCOUNT_JSON'], true);
            if ($data && isset($data['project_id']) && isset($data['private_key'])) {
                self::$serviceAccount = $data;
                return self::$serviceAccount;
            }
        }

        // Check .env for path to service account file
        $envPaths = [
            dirname(__DIR__, 2) . '/.env',
        ];

        foreach ($envPaths as $envPath) {
            if (file_exists($envPath)) {
                $lines = file($envPath, FILE_IGNORE_NEW_LINES | FILE_SKIP_EMPTY_LINES);
                foreach ($lines as $line) {
                    $line = trim($line);
                    if (strpos($line, 'FCM_SERVICE_ACCOUNT_PATH=') === 0) {
                        $path = trim(substr($line, 25), '"\'');
                        if (!str_starts_with($path, '/')) {
                            $path = dirname(__DIR__, 2) . '/' . $path;
                        }
                        if (file_exists($path)) {
                            $content = file_get_contents($path);
                            $data = json_decode($content, true);
                            if ($data && isset($data['project_id'])) {
                                self::$serviceAccount = $data;
                                return self::$serviceAccount;
                            }
                        }
                    }
                }
            }
        }

        return null;
    }

    /**
     * Check if FCM is configured
     */
    public static function isConfigured(): bool
    {
        return self::getServiceAccount() !== null;
    }

    /**
     * Register a device token for a user
     */
    public static function registerDevice(int $userId, string $token, string $platform = 'android'): bool
    {
        $db = Database::getConnection();
        $tenantId = TenantContext::getId();

        try {
            $stmt = $db->prepare("
                SELECT id, user_id FROM fcm_device_tokens
                WHERE token = ? AND tenant_id = ?
            ");
            $stmt->execute([$token, $tenantId]);
            $existing = $stmt->fetch();

            if ($existing) {
                if ($existing['user_id'] != $userId) {
                    $stmt = $db->prepare("
                        UPDATE fcm_device_tokens
                        SET user_id = ?, updated_at = NOW()
                        WHERE id = ?
                    ");
                    $stmt->execute([$userId, $existing['id']]);
                } else {
                    $stmt = $db->prepare("
                        UPDATE fcm_device_tokens
                        SET updated_at = NOW()
                        WHERE id = ?
                    ");
                    $stmt->execute([$existing['id']]);
                }
            } else {
                $stmt = $db->prepare("
                    INSERT INTO fcm_device_tokens (user_id, tenant_id, token, platform, created_at, updated_at)
                    VALUES (?, ?, ?, ?, NOW(), NOW())
                ");
                $stmt->execute([$userId, $tenantId, $token, $platform]);
            }

            return true;
        } catch (\Exception $e) {
            error_log('[FCM] Register device error: ' . $e->getMessage());
            return false;
        }
    }

    /**
     * Unregister a device token
     */
    public static function unregisterDevice(string $token): bool
    {
        $db = Database::getConnection();
        $tenantId = TenantContext::getId();

        try {
            $stmt = $db->prepare("
                DELETE FROM fcm_device_tokens
                WHERE token = ? AND tenant_id = ?
            ");
            $stmt->execute([$token, $tenantId]);
            return true;
        } catch (\Exception $e) {
            error_log('[FCM] Unregister device error: ' . $e->getMessage());
            return false;
        }
    }

    /**
     * Clean up invalid tokens from database
     */
    private static function cleanupInvalidTokens(array $tokens): void
    {
        if (empty($tokens)) return;

        $db = Database::getConnection();
        $placeholders = str_repeat('?,', count($tokens) - 1) . '?';

        try {
            $stmt = $db->prepare("DELETE FROM fcm_device_tokens WHERE token IN ($placeholders)");
            $stmt->execute($tokens);
            error_log('[FCM] Cleaned up ' . count($tokens) . ' invalid tokens');
        } catch (\Exception $e) {
            error_log('[FCM] Cleanup error: ' . $e->getMessage());
        }
    }

    /**
     * Create the database table for FCM tokens if it doesn't exist
     */
    public static function ensureTableExists(): void
    {
        $db = Database::getConnection();

        $db->exec("
            CREATE TABLE IF NOT EXISTS fcm_device_tokens (
                id INT AUTO_INCREMENT PRIMARY KEY,
                user_id INT NOT NULL,
                tenant_id INT NOT NULL,
                token VARCHAR(255) NOT NULL,
                platform VARCHAR(20) DEFAULT 'android',
                created_at DATETIME DEFAULT CURRENT_TIMESTAMP,
                updated_at DATETIME DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
                UNIQUE KEY unique_token (token),
                INDEX idx_user_tenant (user_id, tenant_id),
                INDEX idx_tenant (tenant_id)
            ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci
        ");
    }
}
