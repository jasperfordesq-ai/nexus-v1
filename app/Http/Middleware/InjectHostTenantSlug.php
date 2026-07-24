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
            // setParameter() APPENDS, but Laravel binds scalar route params to
            // controller arguments POSITIONALLY (array_values order), not by name.
            // On a slug-less host route (e.g. /listings/{id}) appending would
            // yield ['id' => '42', 'tenantSlug' => slug], so the controller's
            // (string $tenantSlug, int $id) receives them swapped — the int-typed
            // $id gets the slug string and PHP throws a TypeError -> HTTP 500.
            // Rebuild the parameter bag with tenantSlug FIRST so host-mode order
            // matches the slug-based routes.
            $original = $route->parameters();
            foreach (array_keys($original) as $name) {
                $route->forgetParameter($name);
            }
            $route->setParameter('tenantSlug', $slug);
            foreach ($original as $name => $value) {
                if ($name === 'tenantSlug') {
                    continue;
                }
                $route->setParameter($name, $value);
            }
        }

        return $next($request);
    }
}
