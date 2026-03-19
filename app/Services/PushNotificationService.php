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
 * Eloquent/DI counterpart to the legacy static \Nexus\Services\WebPushService
 * and \Nexus\Services\FCMPushService. Manages push subscriptions and VAPID keys.
 */
class PushNotificationService
{
    /**
     * Subscribe a device for push notifications.
     *
     * @param array{endpoint: string, keys: array{p256dh: string, auth: string}} $subscription
     */
    public function subscribe(int $userId, array $subscription): bool
    {
        $endpoint = $subscription['endpoint'] ?? '';
        if (empty($endpoint)) {
            return false;
        }

        $existing = DB::table('push_subscriptions')
            ->where('user_id', $userId)
            ->where('endpoint', $endpoint)
            ->exists();

        if ($existing) {
            DB::table('push_subscriptions')
                ->where('user_id', $userId)
                ->where('endpoint', $endpoint)
                ->update([
                    'p256dh_key' => $subscription['keys']['p256dh'] ?? null,
                    'auth_token' => $subscription['keys']['auth'] ?? null,
                    'updated_at' => now(),
                ]);
            return true;
        }

        DB::table('push_subscriptions')->insert([
            'user_id'    => $userId,
            'endpoint'   => $endpoint,
            'p256dh_key' => $subscription['keys']['p256dh'] ?? null,
            'auth_token' => $subscription['keys']['auth'] ?? null,
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
        return config('services.webpush.vapid_public_key')
            ?: env('VAPID_PUBLIC_KEY');
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
}
