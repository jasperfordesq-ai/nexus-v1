<?php
// Copyright © 2024–2026 Jasper Ford
// SPDX-License-Identifier: AGPL-3.0-or-later
// Author: Jasper Ford
// See NOTICE file for attribution and acknowledgements.

namespace App\Http\Controllers\GovukAlpha\Middleware;

use App\Core\TenantContext;
use App\Http\Controllers\GovukAlpha\Support\AccessibleIdentityResolver;
use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

/**
 * Fail-closed authentication boundary for member-only accessible routes.
 *
 * Redirects through the existing tenant-aware accessible login route. The
 * outer StripTenantSlugOnAccessibleDomain middleware converts that redirect to
 * /login on a tenant's custom accessible domain.
 */
final class RequireAccessibleAuthentication
{
    public function __construct(
        private readonly AccessibleIdentityResolver $identity,
    ) {}

    public function handle(Request $request, Closure $next): Response
    {
        $userId = $this->identity->userId($request);
        if ($userId === null) {
            $tenantSlug = $request->route('tenantSlug');
            if (!is_string($tenantSlug) || $tenantSlug === '') {
                $tenantSlug = TenantContext::get()['slug'] ?? null;
            }

            abort_unless(is_string($tenantSlug) && $tenantSlug !== '', 404);

            $loginUrl = route('govuk-alpha.login', [
                'tenantSlug' => $tenantSlug,
                'status' => 'auth-required',
            ]);
            $response = $request->hasSession()
                ? redirect()->guest($loginUrl)
                : redirect()->to($loginUrl);
            $this->preventSharedCaching($response);

            return $response;
        }

        // Controllers and parity concerns reuse the already tenant-validated id.
        $request->attributes->set('accessible_user_id', $userId);

        $response = $next($request);
        $this->preventSharedCaching($response);

        return $response;
    }

    private function preventSharedCaching(Response $response): void
    {
        $response->headers->set('Cache-Control', 'private, no-store');
        $response->headers->set('Pragma', 'no-cache');
        $response->setVary(['Authorization', 'Cookie'], false);
    }
}
