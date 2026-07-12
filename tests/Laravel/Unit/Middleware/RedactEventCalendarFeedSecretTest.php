<?php
// Copyright © 2024–2026 Jasper Ford
// SPDX-License-Identifier: AGPL-3.0-or-later
// Author: Jasper Ford
// See NOTICE file for attribution and acknowledgements.

declare(strict_types=1);

namespace Tests\Laravel\Unit\Middleware;

use App\Http\Middleware\RedactEventCalendarFeedSecret;
use Illuminate\Http\Request;
use Illuminate\Routing\Route;
use PHPUnit\Framework\TestCase;
use Symfony\Component\HttpFoundation\Response;

final class RedactEventCalendarFeedSecretTest extends TestCase
{
    public function test_capability_is_available_only_during_dispatch_and_request_context_is_redacted(): void
    {
        $secret = 'nxc_' . str_repeat('a', 64);
        $request = Request::create(
            '/api/v2/events/calendar/personal/hour-timebank/' . $secret . '.ics',
        );
        $route = new Route('GET', '/v2/events/calendar/personal/{tenantSlug}/{secret}.ics', []);
        $route->bind($request);
        $route->setParameter('tenantSlug', 'hour-timebank');
        $route->setParameter('secret', $secret);
        $request->setRouteResolver(static fn (): Route => $route);

        $response = (new RedactEventCalendarFeedSecret())->handle(
            $request,
            function (Request $during) use ($secret): Response {
                self::assertSame(
                    $secret,
                    $during->attributes->get(RedactEventCalendarFeedSecret::ATTRIBUTE),
                );
                self::assertSame('[redacted]', $during->route('secret'));
                self::assertStringNotContainsString(
                    $secret,
                    (string) $during->server->get('REQUEST_URI'),
                );

                return new Response('ok');
            },
        );

        self::assertSame('ok', $response->getContent());
        self::assertFalse($request->attributes->has(
            RedactEventCalendarFeedSecret::ATTRIBUTE,
        ));
        self::assertSame('[redacted]', $request->route('secret'));
        self::assertStringNotContainsString(
            $secret,
            (string) $request->server->get('REQUEST_URI'),
        );
    }
}
