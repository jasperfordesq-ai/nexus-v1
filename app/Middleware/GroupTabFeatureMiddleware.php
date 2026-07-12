<?php
// Copyright © 2024–2026 Jasper Ford
// SPDX-License-Identifier: AGPL-3.0-or-later
// Author: Jasper Ford
// See NOTICE file for attribution and acknowledgements.

declare(strict_types=1);

namespace App\Middleware;

use App\Services\GroupConfigurationService;
use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;
use Throwable;

/** Fail-closed server boundary for tenant-configurable Groups sections. */
final class GroupTabFeatureMiddleware
{
    public function handle(Request $request, Closure $next, string $tab): Response
    {
        try {
            $enabled = GroupConfigurationService::isTabEnabled($tab);
        } catch (Throwable $exception) {
            report($exception);
            $enabled = false;
        }

        // Never catch downstream controller exceptions here: doing so disguises
        // real authorization and server failures as a disabled tab.
        if ($enabled) {
            return $next($request);
        }

        return response()->json([
            'errors' => [[
                'code' => 'GROUP_TAB_DISABLED',
                'message' => __('api.service_unavailable'),
            ]],
            'success' => false,
        ], 403, ['API-Version' => '2.0']);
    }
}
