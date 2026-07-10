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
 * Gates the slug-less accessible host routes to dedicated accessible custom
 * domains.
 *
 * Those routes are registered at the bare root (mirroring how the React custom
 * domains drop the tenant slug), so without this guard they would also match on
 * the shared platform domain and the API host. Any request that did NOT resolve
 * via a tenant's accessible_domain 404s here — those hosts use the canonical
 * /{tenantSlug}/accessible routes instead.
 */
class EnsureAccessibleCustomDomain
{
    public function handle(Request $request, Closure $next): Response
    {
        abort_unless(TenantContext::isAccessibleDomain(), 404);

        return $next($request);
    }
}
