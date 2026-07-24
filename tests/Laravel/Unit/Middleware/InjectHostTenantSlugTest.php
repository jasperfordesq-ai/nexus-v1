<?php
// Copyright © 2024–2026 Jasper Ford
// SPDX-License-Identifier: AGPL-3.0-or-later
// Author: Jasper Ford
// See NOTICE file for attribution and acknowledgements.

namespace Tests\Laravel\Unit\Middleware;

use App\Core\TenantContext;
use App\Http\Middleware\InjectHostTenantSlug;
use Illuminate\Http\Request;
use Illuminate\Routing\Route;
use Symfony\Component\HttpFoundation\Response;
use Tests\Laravel\TestCase;

/**
 * Regression: on slug-less accessible custom domains the middleware injects the
 * host-resolved tenant slug into the route. Laravel binds scalar route params to
 * controller arguments POSITIONALLY (array_values order), so the injected
 * tenantSlug MUST come FIRST — matching the slug-based routes' ($tenantSlug, $id)
 * order. Appending it (the original bug) left ['id','tenantSlug'], so
 * AlphaController::listing(string $tenantSlug, int $id) received them swapped and
 * the int-typed $id got the slug string -> TypeError -> HTTP 500
 * (Sentry PHP 131002861).
 */
class InjectHostTenantSlugTest extends TestCase
{
    public function test_tenant_slug_is_prepended_so_positional_args_match(): void
    {
        TenantContext::setById($this->testTenantId);
        $slug = TenantContext::get()['slug'] ?? null;
        $this->assertIsString($slug);
        $this->assertNotSame('', $slug);

        // A slug-less host route with only {id} bound, as on a custom accessible domain.
        $route = new Route(['GET'], 'listings/{id}', ['uses' => fn () => 'ok']);
        $request = Request::create('/listings/42', 'GET');
        $route->bind($request);
        $request->setRouteResolver(fn () => $route);

        $this->assertSame(['id' => '42'], $route->parameters(), 'precondition: only {id} bound');

        (new InjectHostTenantSlug())->handle($request, fn ($r) => new Response('ok'));

        $params = $route->parameters();
        $this->assertSame('tenantSlug', array_key_first($params), 'tenantSlug must be the first (positional-0) param');
        $this->assertSame([$slug, '42'], array_values($params), 'positional order must be [tenantSlug, id]');
    }
}
