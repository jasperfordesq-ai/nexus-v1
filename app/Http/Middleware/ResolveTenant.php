<?php
// Copyright © 2024–2026 Jasper Ford
// SPDX-License-Identifier: AGPL-3.0-or-later
// Author: Jasper Ford
// See NOTICE file for attribution and acknowledgements.

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;
use App\Core\TenantContext;
use Symfony\Component\HttpFoundation\Response;

class ResolveTenant
{
    /**
     * Paths that are exempt from tenant resolution (health checks, etc.).
     */
    private const EXEMPT_PATHS = [
        '/up',
        '/api/laravel/health',
        '/api/v2/federation/external/webhooks/receive',
    ];

    public function handle(Request $request, Closure $next): Response
    {
        // Skip tenant resolution for exempt paths
        $path = $request->getPathInfo();
        foreach (self::EXEMPT_PATHS as $exempt) {
            if ($path === $exempt) {
                return $next($request);
            }
        }

        // KB attachment downloads are direct browser links — no tenant header available
        if (preg_match('#^/api/v2/kb/\d+/attachments/\d+/download$#', $path)) {
            return $next($request);
        }

        if (!TenantContext::getId()) {
            try {
                TenantContext::resolve();
            } catch (\Throwable $e) {
                return response()->json([
                    'errors' => [
                        ['code' => 'tenant_resolution_failed', 'message' => 'Unable to resolve tenant'],
                    ],
                    'success' => false,
                ], 400, ['API-Version' => '2.0']);
            }
        }

        // After resolution, verify we have a valid tenant ID
        $tenantId = TenantContext::getId();
        if (!$tenantId) {
            return response()->json([
                'errors' => [
                    ['code' => 'tenant_required', 'message' => 'A valid tenant context is required for this request'],
                ],
                'success' => false,
            ], 400, ['API-Version' => '2.0']);
        }

        // Bind tenant.id into Laravel's container for services that use app('tenant.id')
        app()->instance('tenant.id', $tenantId);
        Log::shareContext(['tenant_id' => $tenantId]);

        return $next($request);
    }
}
