<?php
// Copyright © 2024–2026 Jasper Ford
// SPDX-License-Identifier: AGPL-3.0-or-later
// Author: Jasper Ford
// See NOTICE file for attribution and acknowledgements.

namespace App\Services;

use Illuminate\Support\Facades\DB;

/**
 * PushNotificationService — Laravel DI-based service for web push notifications.
 *
 * Manages push subscriptions, VAPID keys, and delegates sending to WebPushService.
 */
class PushNotificationService
{
    /**
     * Subscribe a device for push notifications.
     *
     * Tenant-scoped: stores the current tenant_id so push notifications
     * are only sent to subscriptions belonging to the correct tenant.
     *
     * @param array{endpoint: string, keys: array{p256dh: string, auth: string}} $subscription
     */
    public function subscribe(int $userId, array $subscription): bool
    {
        $endpoint = $subscription['endpoint'] ?? '';
        if (empty($endpoint)) {
            return false;
        }

        $tenantId = \App\Core\TenantContext::getId();

        $existing = DB::table('push_subscriptions')
            ->where('user_id', $userId)
            ->where('endpoint', $endpoint)
            ->exists();

        if ($existing) {
            DB::table('push_subscriptions')
                ->where('user_id', $userId)
                ->where('endpoint', $endpoint)
                ->update([
                    'tenant_id'  => $tenantId,
                    'p256dh_key' => $subscription['keys']['p256dh'] ?? null,
                    'auth_key'   => $subscription['keys']['auth'] ?? null,
                    'updated_at' => now(),
                ]);
            return true;
        }

        DB::table('push_subscriptions')->insert([
            'user_id'    => $userId,
            'tenant_id'  => $tenantId,
            'endpoint'   => $endpoint,
            'p256dh_key' => $subscription['keys']['p256dh'] ?? null,
            'auth_key'   => $subscription['keys']['auth'] ?? null,
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        return true;
    }

    /**
     * Unsubscribe a device from push notifications.
     */
    public function unsubscribe(int $userId, string $endpoint): bool
    {
        return DB::table('push_subscriptions')
            ->where('user_id', $userId)
            ->where('endpoint', $endpoint)
            ->delete() > 0;
    }

    /**
     * Get the VAPID public key for the client.
     */
    public function getVapidKey(): ?string
    {
        return config('services.vapid.public_key');
    }

    /**
     * Get subscription count for a user.
     */
    public function getSubscriptionCount(int $userId): int
    {
        return DB::table('push_subscriptions')
            ->where('user_id', $userId)
            ->count();
    }

    /**
     * Send a push notification to a user via WebPushService.
     *
     * This is a convenience method that delegates to WebPushService.
     * Push is best-effort — failures are logged but do not propagate.
     */
    public function send(int $userId, string $title, string $body, ?string $link = null): bool
    {
        try {
            $webPush = app(WebPushService::class);
            return $webPush->sendToUser($userId, $title, $body, $link);
        } catch (\Exception $e) {
            \Illuminate\Support\Facades\Log::error('PushNotificationService::send failed', [
                'user_id' => $userId,
                'error'   => $e->getMessage(),
            ]);
            return false;
        }
    }
}
