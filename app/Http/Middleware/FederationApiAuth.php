<?php
// Copyright © 2024–2026 Jasper Ford
// SPDX-License-Identifier: AGPL-3.0-or-later
// Author: Jasper Ford
// See NOTICE file for attribution and acknowledgements.

declare(strict_types=1);

namespace App\Http\Middleware;

use App\Core\FederationApiMiddleware;
use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

/**
 * Laravel middleware wrapper for FederationApiMiddleware.
 *
 * The existing FederationApiMiddleware uses static methods and returns
 * bool|JsonResponse. This wrapper adapts it to Laravel's standard
 * middleware contract (handle + next) so it can be used as route middleware.
 *
 * Used by: /v2/federation/komunitin/* and /v2/federation/cc/* routes.
 */
class FederationApiAuth
{
    public function handle(Request $request, Closure $next): Response
    {
        $result = FederationApiMiddleware::authenticate();

        // authenticate() returns true on success, JsonResponse on failure
        if ($result !== true) {
            return $result;
        }

        return $next($request);
    }
}
