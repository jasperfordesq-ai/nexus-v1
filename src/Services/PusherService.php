<?php

namespace Nexus\Services;

use Nexus\Core\Env;
use Nexus\Core\TenantContext;
use Pusher\Pusher;

/**
 * PusherService - Manages Pusher Channels connection for real-time features
 *
 * Provides real-time WebSocket communication for:
 * - Instant notifications
 * - Real-time messaging
 * - Online presence
 * - Typing indicators
 */
class PusherService
{
    private static ?Pusher $instance = null;
    private static array $config = [];

    /**
     * Get configured Pusher instance
     */
    public static function getInstance(): ?Pusher
    {
        if (self::$instance === null) {
            self::$instance = self::createInstance();
        }
        return self::$instance;
    }

    /**
     * Create a new Pusher instance with configuration
     */
    private static function createInstance(): ?Pusher
    {
        $config = self::getConfig();

        if (empty($config['app_id']) || empty($config['key']) || empty($config['secret'])) {
            error_log('[PusherService] Pusher credentials not configured');
            return null;
        }

        try {
            return new Pusher(
                $config['key'],
                $config['secret'],
                $config['app_id'],
                [
                    'cluster' => $config['cluster'],
                    'useTLS' => $config['useTLS'],
                    'debug' => $config['debug'] ?? false,
                ]
            );
        } catch (\Exception $e) {
            error_log('[PusherService] Failed to create Pusher instance: ' . $e->getMessage());
            return null;
        }
    }

    /**
     * Get Pusher configuration
     */
    public static function getConfig(): array
    {
        if (empty(self::$config)) {
            self::$config = [
                'app_id'  => Env::get('PUSHER_APP_ID', ''),
                'key'     => Env::get('PUSHER_KEY', ''),
                'secret'  => Env::get('PUSHER_SECRET', ''),
                'cluster' => Env::get('PUSHER_CLUSTER', 'us2'),
                'useTLS'  => true,
                'debug'   => Env::get('PUSHER_DEBUG', false),
            ];
        }
        return self::$config;
    }

    /**
     * Get the public Pusher key for frontend use
     */
    public static function getPublicKey(): string
    {
        return self::getConfig()['key'] ?? '';
    }

    /**
     * Get the Pusher cluster for frontend use
     */
    public static function getCluster(): string
    {
        return self::getConfig()['cluster'] ?? 'us2';
    }

    /**
     * Check if Pusher is configured and available
     */
    public static function isConfigured(): bool
    {
        $config = self::getConfig();
        return !empty($config['app_id']) && !empty($config['key']) && !empty($config['secret']);
    }

    /**
     * Trigger an event on a channel
     *
     * @param string|array $channels Channel name(s)
     * @param string $event Event name
     * @param array $data Event data
     * @return bool Success status
     */
    public static function trigger($channels, string $event, array $data): bool
    {
        $pusher = self::getInstance();
        if ($pusher === null) {
            return false;
        }

        try {
            $pusher->trigger($channels, $event, $data);
            return true;
        } catch (\Exception $e) {
            error_log('[PusherService] Trigger failed: ' . $e->getMessage());
            return false;
        }
    }

    /**
     * Get channel name for user notifications (tenant-scoped)
     */
    public static function getUserChannel(int $userId, ?int $tenantId = null): string
    {
        $tenantId = $tenantId ?? TenantContext::getId();
        return "private-tenant.{$tenantId}.user.{$userId}";
    }

    /**
     * Get channel name for chat/conversation (tenant-scoped)
     * @param int|string $chatId Chat ID (can be string like "14-15" for user pairs)
     */
    public static function getChatChannel($chatId, ?int $tenantId = null): string
    {
        $tenantId = $tenantId ?? TenantContext::getId();
        return "private-tenant.{$tenantId}.chat.{$chatId}";
    }

    /**
     * Get channel name for presence (tenant-scoped online users)
     */
    public static function getPresenceChannel(?int $tenantId = null): string
    {
        $tenantId = $tenantId ?? TenantContext::getId();
        return "presence-tenant.{$tenantId}.online";
    }

    /**
     * Authenticate a private channel subscription
     *
     * @param string $channelName The channel being subscribed to
     * @param string $socketId The Pusher socket ID
     * @param int $userId The authenticated user ID
     * @return string|null JSON auth response or null on failure
     */
    public static function authPrivateChannel(string $channelName, string $socketId, int $userId): ?string
    {
        try {
            $pusher = self::getInstance();
            if ($pusher === null) {
                error_log("[PusherService] authPrivateChannel: Pusher instance is null - check credentials");
                return null;
            }
        } catch (\Throwable $e) {
            error_log("[PusherService] authPrivateChannel getInstance error: " . $e->getMessage());
            return null;
        }

        // Validate channel belongs to user's tenant
        $tenantId = TenantContext::getId();
        $expectedPrefix = "private-tenant.{$tenantId}.";

        if (strpos($channelName, $expectedPrefix) !== 0) {
            error_log("[PusherService] Channel auth denied: {$channelName} doesn't match tenant {$tenantId}");
            return null;
        }

        // For user channels, verify the user ID matches
        if (preg_match('/^private-tenant\.\d+\.user\.(\d+)$/', $channelName, $matches)) {
            $channelUserId = (int)$matches[1];
            if ($channelUserId !== $userId) {
                error_log("[PusherService] Channel auth denied: User {$userId} cannot subscribe to user {$channelUserId}'s channel");
                return null;
            }
        }

        try {
            // Pusher SDK returns a JSON string directly
            return $pusher->authorizeChannel($channelName, $socketId);
        } catch (\Exception $e) {
            error_log('[PusherService] Channel auth failed: ' . $e->getMessage());
            return null;
        }
    }

    /**
     * Authenticate a presence channel subscription
     *
     * @param string $channelName The presence channel being subscribed to
     * @param string $socketId The Pusher socket ID
     * @param int $userId The authenticated user ID
     * @param array $userInfo Additional user info for presence
     * @return string|null JSON auth response or null on failure
     */
    public static function authPresenceChannel(string $channelName, string $socketId, int $userId, array $userInfo = []): ?string
    {
        try {
            $pusher = self::getInstance();
            if ($pusher === null) {
                error_log("[PusherService] authPresenceChannel: Pusher instance is null - check credentials");
                return null;
            }
        } catch (\Throwable $e) {
            error_log("[PusherService] authPresenceChannel getInstance error: " . $e->getMessage());
            return null;
        }

        // Validate channel belongs to user's tenant
        $tenantId = TenantContext::getId();
        $expectedPrefix = "presence-tenant.{$tenantId}.";

        if (strpos($channelName, $expectedPrefix) !== 0) {
            error_log("[PusherService] Presence auth denied: {$channelName} doesn't match tenant {$tenantId}");
            return null;
        }

        try {
            // Pusher SDK expects: authorizePresenceChannel($channel, $socket_id, $user_id, $user_info)
            // The SDK internally constructs: ['user_id' => $user_id, 'user_info' => $user_info]
            $presenceUserInfo = array_merge(['id' => $userId], $userInfo);

            // Pusher SDK returns a JSON string directly
            return $pusher->authorizePresenceChannel($channelName, $socketId, (string)$userId, $presenceUserInfo);
        } catch (\Exception $e) {
            error_log('[PusherService] Presence auth failed: ' . $e->getMessage());
            return null;
        }
    }
}
