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
 * Eloquent/DI counterpart to the legacy static \Nexus\Services\BrokerControlConfigService
 * and \Nexus\Services\BrokerMessageVisibilityService. Manages broker configuration
 * and message visibility settings.
 */
class BrokerService
{
    /**
     * Get broker configuration for a tenant.
     */
    public function getConfig(int $tenantId): array
    {
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
    }

    /**
     * Get broker messages for a tenant.
     */
    public function getMessages(int $tenantId, int $limit = 20, int $offset = 0): array
    {
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
    }

    /**
     * Update message visibility setting.
     */
    public function updateVisibility(int $tenantId, string $visibility): bool
    {
        $allowed = ['admin_only', 'brokers', 'members', 'public'];
        if (! in_array($visibility, $allowed, true)) {
            return false;
        }

        return DB::table('broker_config')
            ->updateOrInsert(
                ['tenant_id' => $tenantId],
                ['visibility' => $visibility, 'updated_at' => now()]
            );
    }
}
