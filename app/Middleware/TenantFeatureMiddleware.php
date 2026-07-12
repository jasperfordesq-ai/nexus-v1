<?php
// Copyright © 2024–2026 Jasper Ford
// SPDX-License-Identifier: AGPL-3.0-or-later
// Author: Jasper Ford
// See NOTICE file for attribution and acknowledgements.

declare(strict_types=1);

namespace App\Middleware;

use App\Core\TenantContext;
use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;
use Throwable;

/**
 * Fail-closed route boundary for tenant feature flags.
 *
 * Usage: Route::middleware('feature:events')->group(...)
 */
final class TenantFeatureMiddleware
{
    public function handle(Request $request, Closure $next, string $feature): Response
    {
        $enabled = false;

        try {
            $enabled = $feature !== '' && TenantContext::hasFeature($feature);
        } catch (Throwable $exception) {
            report($exception);
        }

        if ($enabled) {
            return $next($request);
        }

        return response()->json([
            'errors' => [[
                'code' => 'FEATURE_DISABLED',
                'message' => __('api.service_unavailable'),
            ]],
            'success' => false,
        ], 403, ['API-Version' => '2.0']);
    }
}
