<?php
// Copyright © 2024–2026 Jasper Ford
// SPDX-License-Identifier: AGPL-3.0-or-later
// Author: Jasper Ford
// See NOTICE file for attribution and acknowledgements.

declare(strict_types=1);

namespace App\Http\Controllers\Api;

use App\Core\TenantContext;
use App\Services\RegionalAnalytics\RegionalAnalyticsService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;

/**
 * AG59 — Partner-facing endpoints for paying regional-analytics subscribers.
 *
 * Auth: a `subscription_token` is provided per subscription and presented as
 * either an `Authorization: Bearer <token>` header or a `?token=` query
 * parameter. Every call is recorded in regional_analytics_access_log.
 */
class RegionalAnalyticsPartnerController extends BaseApiController
{
    protected bool $isV2Api = true;

    public function dashboard(Request $request, RegionalAnalyticsService $analytics): JsonResponse
    {
        $sub = $this->resolveSubscription($request);
        if (! $sub) {
            return $this->respondWithError('unauthorized', 'Invalid or missing subscription token.', null, 401);
        }
        if (! in_array($sub->status, ['active', 'trialing'], true)) {
            return $this->respondWithError('forbidden', 'Subscription is not active.', null, 403);
        }

        $period = (string) $request->query('period', 'last_30d');
        if (! in_array($period, ['last_30d', 'last_90d', 'last_year', 'last_12m'], true)) {
            $period = 'last_30d';
        }

        TenantContext::setById((int) $sub->tenant_id);

        $modules = is_string($sub->enabled_modules ?? null)
            ? (json_decode($sub->enabled_modules, true) ?: [])
            : [];

        $payload = $analytics->buildDashboardPayload((int) $sub->tenant_id, $period, $modules);

        $this->logAccess($sub, $request, '/partner-analytics/me/dashboard');

        return $this->respondWithData($payload);
    }

    public function reports(Request $request): JsonResponse
    {
        $sub = $this->resolveSubscription($request);
        if (! $sub) {
            return $this->respondWithError('unauthorized', 'Invalid or missing subscription token.', null, 401);
        }

        $rows = DB::table('regional_analytics_reports')
            ->where('subscription_id', $sub->id)
            ->where('tenant_id', $sub->tenant_id)
            ->orderBy('id', 'desc')
            ->limit(60)
            ->get(['id', 'report_type', 'period_start', 'period_end', 'generated_at', 'status', 'file_url'])
            ->all();

        $this->logAccess($sub, $request, '/partner-analytics/me/reports');

        return $this->respondWithData(['reports' => $rows]);
    }

    public function downloadReport(Request $request, int $id)
    {
        $sub = $this->resolveSubscription($request);
        if (! $sub) {
            return $this->respondWithError('unauthorized', 'Invalid or missing subscription token.', null, 401);
        }

        $report = DB::table('regional_analytics_reports')
            ->where('id', $id)
            ->where('subscription_id', $sub->id)
            ->where('tenant_id', $sub->tenant_id)
            ->first();
        if (! $report || empty($report->file_url)) {
            return $this->respondNotFound('Report not available.', 'REPORT_NOT_FOUND');
        }

        $this->logAccess($sub, $request, '/partner-analytics/me/reports/' . $id . '/download');

        $relative = ltrim((string) $report->file_url, '/');
        // Stored under /storage/regional-analytics/...; strip the leading prefix.
        if (str_starts_with($relative, 'storage/')) {
            $relative = substr($relative, strlen('storage/'));
        }

        if (! Storage::exists($relative)) {
            return $this->respondNotFound('Report file missing.', 'REPORT_FILE_MISSING');
        }

        return response(
            Storage::get($relative),
            200,
            [
                'Content-Type' => 'application/pdf',
                'Content-Disposition' => 'attachment; filename="regional-analytics-' . $report->period_start . '.pdf"',
            ]
        );
    }

    private function resolveSubscription(Request $request): ?object
    {
        $token = $request->bearerToken();
        if (! $token) {
            $token = (string) $request->query('token', '');
        }
        if ($token === '') {
            return null;
        }
        return DB::table('regional_analytics_subscriptions')
            ->where('subscription_token', $token)
            ->first();
    }

    private function logAccess(object $sub, Request $request, string $endpoint): void
    {
        try {
            DB::table('regional_analytics_access_log')->insert([
                'subscription_id' => $sub->id,
                'tenant_id' => $sub->tenant_id,
                'accessed_endpoint' => substr($endpoint, 0, 255),
                'accessed_at' => now(),
                'ip_hash' => hash('sha256', (string) $request->ip()),
                'user_agent' => substr((string) $request->userAgent(), 0, 255),
            ]);
        } catch (\Throwable $e) {
            // Swallow — logging must never break the response.
        }
    }
}
