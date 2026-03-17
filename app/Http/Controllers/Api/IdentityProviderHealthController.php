<?php
// Copyright © 2024-2026 Jasper Ford
// SPDX-License-Identifier: AGPL-3.0-or-later
// Author: Jasper Ford
// See NOTICE file for attribution and acknowledgements.

namespace App\Http\Controllers\Api;

use Illuminate\Http\JsonResponse;
use Illuminate\Support\Facades\DB;
use Nexus\Core\TenantContext;
use Nexus\Services\Identity\IdentityProviderRegistry;

/**
 * IdentityProviderHealthController -- Identity provider health status.
 *
 * Now calls legacy services directly (no ob_start delegation).
 */
class IdentityProviderHealthController extends BaseApiController
{
    protected bool $isV2Api = true;

    /** GET /api/v2/identity/provider-health */
    public function getProviderHealth(): JsonResponse
    {
        $this->requireAdmin();
        $tenantId = TenantContext::getId();

        $providers = IdentityProviderRegistry::all();
        $health = [];

        foreach ($providers as $slug => $provider) {
            // Get session stats from DB
            $stats = (array) DB::selectOne(
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
            );

            // Get last 24h stats
            $recent = (array) DB::selectOne(
                "SELECT
                    COUNT(*) as total_24h,
                    SUM(CASE WHEN status = 'passed' THEN 1 ELSE 0 END) as passed_24h,
                    SUM(CASE WHEN status = 'failed' THEN 1 ELSE 0 END) as failed_24h
                 FROM identity_verification_sessions
                 WHERE tenant_id = ? AND provider_slug = ? AND created_at > DATE_SUB(NOW(), INTERVAL 24 HOUR)",
                [$tenantId, $slug]
            );

            // Get last webhook event
            $lastWebhook = DB::selectOne(
                "SELECT created_at, event_type
                 FROM identity_verification_events
                 WHERE tenant_id = ? AND JSON_EXTRACT(event_data, '$.provider_slug') = ?
                 ORDER BY created_at DESC LIMIT 1",
                [$tenantId, $slug]
            );

            $totalSessions = (int) ($stats['total_sessions'] ?? 0);
            $passedCount = (int) ($stats['passed'] ?? 0);
            $successRate = $totalSessions > 0 ? round(($passedCount / $totalSessions) * 100, 1) : null;

            // Measure provider API latency
            $latencyStart = microtime(true);
            $isAvailable = $provider->isAvailable($tenantId);
            $latencyMs = round((microtime(true) - $latencyStart) * 1000, 1);

            // Average time-to-complete for passed sessions (last 30 days)
            $avgCompletion = DB::selectOne(
                "SELECT AVG(TIMESTAMPDIFF(SECOND, created_at, completed_at)) as avg_seconds
                 FROM identity_verification_sessions
                 WHERE tenant_id = ? AND provider_slug = ? AND status = 'passed'
                   AND completed_at > DATE_SUB(NOW(), INTERVAL 30 DAY)",
                [$tenantId, $slug]
            );
            $avgCompletionSeconds = ($avgCompletion->avg_seconds ?? null) !== null
                ? round((float) $avgCompletion->avg_seconds)
                : null;

            $health[] = [
                'slug'                    => $slug,
                'name'                    => $provider->getName(),
                'available'               => $isAvailable,
                'supported_levels'        => $provider->getSupportedLevels(),
                'latency_ms'              => $latencyMs,
                'avg_completion_seconds'  => $avgCompletionSeconds,
                'stats' => [
                    'total_sessions'  => $totalSessions,
                    'passed'          => $passedCount,
                    'failed'          => (int) ($stats['failed'] ?? 0),
                    'pending'         => (int) ($stats['pending'] ?? 0),
                    'expired'         => (int) ($stats['expired'] ?? 0),
                    'success_rate'    => $successRate,
                    'last_session_at' => $stats['last_session_at'] ?? null,
                    'last_success_at' => $stats['last_success_at'] ?? null,
                    'last_failure_at' => $stats['last_failure_at'] ?? null,
                ],
                'recent_24h' => [
                    'total'  => (int) ($recent['total_24h'] ?? 0),
                    'passed' => (int) ($recent['passed_24h'] ?? 0),
                    'failed' => (int) ($recent['failed_24h'] ?? 0),
                ],
                'last_webhook' => $lastWebhook ? [
                    'at'   => $lastWebhook->created_at,
                    'type' => $lastWebhook->event_type,
                ] : null,
            ];
        }

        return $this->respondWithData($health);
    }
}
