<?php
// Copyright © 2024–2026 Jasper Ford
// SPDX-License-Identifier: AGPL-3.0-or-later
// Author: Jasper Ford
// See NOTICE file for attribution and acknowledgements.

namespace App\Http\Middleware;

use App\Helpers\CorsHelper;
use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

/**
 * Ensures CORS headers are present on ALL responses, including errors.
 *
 * Laravel's HandleCors middleware normally handles CORS, but when inner
 * middleware (e.g., Authenticate) returns early with 401/403, the CORS
 * headers can be missing — causing browsers to report CORS errors instead
 * of the real HTTP status. This middleware runs as the outermost layer and
 * guarantees CORS headers on every response.
 */
class EnsureCorsHeaders
{
    public function handle(Request $request, Closure $next): Response
    {
        try {
            $response = $next($request);
        } catch (\Throwable $e) {
            // When inner middleware throws, Laravel's exception handler renders
            // a 500 response that bypasses this middleware's response phase.
            // Catch the exception, render it, and add CORS headers so the browser
            // can read the error instead of showing a misleading CORS block.
            $response = $this->renderException($request, $e);
        }

        return $this->addCorsHeaders($request, $response);
    }

    private function addCorsHeaders(Request $request, Response $response): Response
    {
        // Only add CORS headers to API paths (same paths as config/cors.php)
        $path = $request->path();
        if (!$this->isApiPath($path)) {
            return $response;
        }

        // If HandleCors already set the header, don't override it
        if ($response->headers->has('Access-Control-Allow-Origin')) {
            return $response;
        }

        // Check if the request origin is allowed
        $origin = $request->header('Origin');
        if (!$origin) {
            return $response;
        }

        if (CorsHelper::isOriginAllowed($origin)) {
            $response->headers->set('Access-Control-Allow-Origin', $origin);
            $response->headers->set('Access-Control-Allow-Credentials', 'true');
            $response->headers->set('Access-Control-Allow-Methods', 'GET, POST, PUT, PATCH, DELETE, OPTIONS');
            $response->headers->set('Access-Control-Allow-Headers', 'Content-Type, Authorization, X-Requested-With, X-CSRF-TOKEN, Accept, X-Tenant-Id, X-Trusted-Device, X-Timezone, X-Locale');
        }

        return $response;
    }

    private function renderException(Request $request, \Throwable $e): Response
    {
        try {
            return app(\Illuminate\Contracts\Debug\ExceptionHandler::class)
                ->render($request, $e);
        } catch (\Throwable) {
            // If even the exception handler fails, return a minimal JSON 500
            return response()->json(['message' => 'Server Error'], 500);
        }
    }

    private function isApiPath(string $path): bool
    {
        return str_starts_with($path, 'api/')
            || str_starts_with($path, 'v2/')
            || str_starts_with($path, 'sanctum/')
            || str_starts_with($path, 'broadcasting/');
    }
}
