<?php
// Copyright © 2024–2026 Jasper Ford
// SPDX-License-Identifier: AGPL-3.0-or-later
// Author: Jasper Ford
// See NOTICE file for attribution and acknowledgements.

declare(strict_types=1);

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

/**
 * Negotiate and expose the Events response contract at the outer HTTP edge.
 *
 * This middleware is deliberately outermost so CORS/cache middleware cannot
 * replace its Vary token after a controller or an auth/feature error returns.
 */
final class NegotiateEventsContract
{
    public function handle(Request $request, Closure $next): Response
    {
        /** @var Response $response */
        $response = $next($request);

        if (!$request->is('api/v2/events', 'api/v2/events/*')) {
            return $response;
        }

        $canonical = (string) config('events.contract.canonical_version', 2);
        $legacy = (string) config('events.contract.legacy_version', 1);
        $requested = trim((string) $request->headers->get('X-Events-Contract', ''));
        $version = hash_equals($canonical, $requested) ? $canonical : $legacy;
        $response->headers->set('X-Events-Contract', $version);

        $vary = [];
        foreach ($response->headers->all('Vary') as $value) {
            foreach (explode(',', $value) as $token) {
                $token = trim($token);
                if ($token !== '') {
                    $vary[strtolower($token)] = $token;
                }
            }
        }
        $vary['x-events-contract'] = 'X-Events-Contract';
        $response->headers->set('Vary', implode(', ', array_values($vary)));

        return $response;
    }
}
