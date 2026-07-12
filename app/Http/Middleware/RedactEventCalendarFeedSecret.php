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

/** Remove personal calendar capabilities from application request/error context. */
final class RedactEventCalendarFeedSecret
{
    public const ATTRIBUTE = 'event_calendar_feed_secret';

    public function handle(Request $request, Closure $next): Response
    {
        $secret = $request->route('secret');
        if (! is_string($secret) || $secret === '') {
            return $next($request);
        }

        $request->attributes->set(self::ATTRIBUTE, $secret);
        $request->route()?->setParameter('secret', '[redacted]');

        $requestUri = (string) $request->server->get('REQUEST_URI', '');
        $request->server->set('REQUEST_URI', str_replace(
            [$secret, rawurlencode($secret)],
            '[redacted]',
            $requestUri,
        ));

        try {
            return $next($request);
        } finally {
            // The global exception handler runs after middleware unwinds, so
            // neither the route parameter nor request attributes retain it.
            $request->attributes->remove(self::ATTRIBUTE);
        }
    }
}
