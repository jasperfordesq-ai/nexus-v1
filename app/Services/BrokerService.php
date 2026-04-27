<?php
// Copyright © 2024–2026 Jasper Ford
// SPDX-License-Identifier: AGPL-3.0-or-later
// Author: Jasper Ford
// See NOTICE file for attribution and acknowledgements.

namespace App\Services;

use Illuminate\Support\Facades\DB;

/**
 * BrokerService — Laravel DI-based service for broker operations.
 *
 * @deprecated This class references non-existent tables (broker_config, broker_messages)
 *   that were never created in the actual schema. It is kept here only because it is
 *   still registered in AppServiceProvider (singleton binding) and has a unit test.
 *
 *   TODO: remove — use BrokerControlConfigService and BrokerMessageVisibilityService
 *   instead, which are the live implementations backed by tenant_settings and
 *   broker_message_copies respectively.
 *
 * All methods below return safe fallbacks so that any accidental call to this class
 * at runtime does NOT throw a QueryException due to missing tables.
 */
class BrokerService
{
    /**
     * Get broker configuration for a tenant.
     *
     * @deprecated Use BrokerControlConfigService::getConfig() instead.
     */
    public function getConfig(int $tenantId): array
    {
        // Guard: broker_config table does not exist — return safe defaults rather
        // than throwing a QueryException that would surface as a 500 error.
        try {
            $config = DB::table('broker_config')
                ->where('tenant_id', $tenantId)
                ->first();

            if (! $config) {
                return [
                    'enabled'             => false,
                    'auto_match'          => false,
                    'visibility'          => 'admin_only',
                    'notification_emails' => [],
                ];
            }

            return (array) $config;
        } catch (\Throwable $e) {
            \Illuminate\Support\Facades\Log::warning('[BrokerService] getConfig failed (table likely missing): ' . $e->getMessage());
            return [
                'enabled'             => false,
                'auto_match'          => false,
                'visibility'          => 'admin_only',
                'notification_emails' => [],
            ];
        }
    }

    /**
     * Get broker messages for a tenant.
     *
     * @deprecated Use BrokerMessageVisibilityService instead.
     */
    public function getMessages(int $tenantId, int $limit = 20, int $offset = 0): array
    {
        // Guard: broker_messages table does not exist — return safe empty result.
        try {
            $query = DB::table('broker_messages')
                ->where('tenant_id', $tenantId);

            $total = $query->count();
            $items = $query->orderByDesc('created_at')
                ->offset($offset)
                ->limit(min($limit, 100))
                ->get()
                ->map(fn ($r) => (array) $r)
                ->all();

            return ['items' => $items, 'total' => $total];
        } catch (\Throwable $e) {
            \Illuminate\Support\Facades\Log::warning('[BrokerService] getMessages failed (table likely missing): ' . $e->getMessage());
            return ['items' => [], 'total' => 0];
        }
    }

    /**
     * Update message visibility setting.
     *
     * @deprecated Use BrokerControlConfigService::updateConfig() instead.
     */
    public function updateVisibility(int $tenantId, string $visibility): bool
    {
        $allowed = ['admin_only', 'brokers', 'members', 'public'];
        if (! in_array($visibility, $allowed, true)) {
            return false;
        }

        // Guard: broker_config table does not exist — return false rather than throwing.
        try {
            return DB::table('broker_config')
                ->updateOrInsert(
                    ['tenant_id' => $tenantId],
                    ['visibility' => $visibility, 'updated_at' => now()]
                );
        } catch (\Throwable $e) {
            \Illuminate\Support\Facades\Log::warning('[BrokerService] updateVisibility failed (table likely missing): ' . $e->getMessage());
            return false;
        }
    }
}
