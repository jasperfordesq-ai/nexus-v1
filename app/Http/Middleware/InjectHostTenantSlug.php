<?php
// Copyright © 2024–2026 Jasper Ford
// SPDX-License-Identifier: AGPL-3.0-or-later
// Author: Jasper Ford
// See NOTICE file for attribution and acknowledgements.

namespace App\Http\Middleware;

use App\Core\TenantContext;
use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

/**
 * Injects the host-resolved tenant slug into the route parameters for the
 * slug-less accessible (GOV.UK) host routes.
 *
 * On a custom accessible domain the tenant is identified by the host, so the
 * URL carries no {tenantSlug} segment. The AlphaController actions still take a
 * $tenantSlug argument (shared with the slug-based routes), so we set it from
 * TenantContext here — the same value the slug-based URL would have carried.
 */
class InjectHostTenantSlug
{
    public function handle(Request $request, Closure $next): Response
    {
        $slug = TenantContext::get()['slug'] ?? null;
        $route = $request->route();

        if (is_string($slug) && $slug !== '' && $route !== null) {
            $route->setParameter('tenantSlug', $slug);
        }

        return $next($request);
    }
}
