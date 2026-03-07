<?php
// Copyright © 2024–2026 Jasper Ford
// SPDX-License-Identifier: AGPL-3.0-or-later
// Author: Jasper Ford
// See NOTICE file for attribution and acknowledgements.

namespace Nexus\Controllers\Api;

use Nexus\Core\TenantContext;
use Nexus\Core\Database;
use Nexus\Services\Identity\IdentityProviderRegistry;

class IdentityProviderHealthApiController extends BaseApiController
{
    protected bool $isV2Api = true;

    /**
     * GET /api/v2/admin/identity/provider-health
     */
    public function getProviderHealth(): void
    {
        $this->requireAdmin();
        $tenantId = TenantContext::getId();

        $providers = IdentityProviderRegistry::all();
        $health = [];

        foreach ($providers as $slug => $provider) {
            // Get session stats from DB
            $stats = Database::query(
                "SELECT
                    COUNT(*) as total_sessions,
                    SUM(CASE WHEN status = 'passed' THEN 1 ELSE 0 END) as passed,
                    SUM(CASE WHEN status = 'failed' THEN 1 ELSE 0 END) as failed,
                    SUM(CASE WHEN status IN ('created', 'started', 'processing') THEN 1 ELSE 0 END) as pending,
                    SUM(CASE WHEN status = 'expired' THEN 1 ELSE 0 END) as expired,
                    MAX(created_at) as last_session_at,
                    MAX(CASE WHEN status = 'passed' THEN completed_at END) as last_success_at,
                    MAX(CASE WHEN status = 'failed' THEN completed_at END) as last_failure_at
                 FROM identity_verification_sessions
                 WHERE tenant_id = ? AND provider_slug = ?",
                [$tenantId, $slug]
            )->fetch();

            // Get last 24h stats
            $recent = Database::query(
                "SELECT
                    COUNT(*) as total_24h,
                    SUM(CASE WHEN status = 'passed' THEN 1 ELSE 0 END) as passed_24h,
                    SUM(CASE WHEN status = 'failed' THEN 1 ELSE 0 END) as failed_24h
                 FROM identity_verification_sessions
                 WHERE tenant_id = ? AND provider_slug = ? AND created_at > DATE_SUB(NOW(), INTERVAL 24 HOUR)",
                [$tenantId, $slug]
            )->fetch();

            // Get last webhook event
            $lastWebhook = Database::query(
                "SELECT created_at, event_type
                 FROM identity_verification_events
                 WHERE tenant_id = ? AND JSON_EXTRACT(event_data, '$.provider_slug') = ?
                 ORDER BY created_at DESC LIMIT 1",
                [$tenantId, $slug]
            )->fetch();

            $totalSessions = (int)($stats['total_sessions'] ?? 0);
            $passedCount = (int)($stats['passed'] ?? 0);
            $successRate = $totalSessions > 0 ? round(($passedCount / $totalSessions) * 100, 1) : null;

            $health[] = [
                'slug' => $slug,
                'name' => $provider->getName(),
                'available' => $provider->isAvailable($tenantId),
                'supported_levels' => $provider->getSupportedLevels(),
                'stats' => [
                    'total_sessions' => $totalSessions,
                    'passed' => $passedCount,
                    'failed' => (int)($stats['failed'] ?? 0),
                    'pending' => (int)($stats['pending'] ?? 0),
                    'expired' => (int)($stats['expired'] ?? 0),
                    'success_rate' => $successRate,
                    'last_session_at' => $stats['last_session_at'] ?? null,
                    'last_success_at' => $stats['last_success_at'] ?? null,
                    'last_failure_at' => $stats['last_failure_at'] ?? null,
                ],
                'recent_24h' => [
                    'total' => (int)($recent['total_24h'] ?? 0),
                    'passed' => (int)($recent['passed_24h'] ?? 0),
                    'failed' => (int)($recent['failed_24h'] ?? 0),
                ],
                'last_webhook' => $lastWebhook ? [
                    'at' => $lastWebhook['created_at'],
                    'type' => $lastWebhook['event_type'],
                ] : null,
            ];
        }

        $this->respondWithData($health);
    }
}
