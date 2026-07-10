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
 * On a custom accessible domain, strips the "/{slug}/accessible" prefix from
 * every generated accessible URL so the address bar shows clean, slug-less
 * paths — mirroring how the React custom domains work.
 *
 * The accessible views build links with route('govuk-alpha.*', ['tenantSlug' =>
 * $slug]), which always produce /{slug}/accessible/... URLs. Rather than
 * rewrite the ~1,194 link sites, we rewrite the generated output here,
 * centrally. This is a no-op on every other host (shared platform domain,
 * API), so those keep the slug exactly as before — the slug is only dropped
 * for tenants WITH a custom accessible domain.
 */
class StripTenantSlugOnAccessibleDomain
{
    public function handle(Request $request, Closure $next): Response
    {
        $response = $next($request);

        if (!TenantContext::isAccessibleDomain()) {
            return $response;
        }

        $slug = TenantContext::get()['slug'] ?? '';
        if (!is_string($slug) || $slug === '') {
            return $response;
        }

        $base = '/' . $slug . '/accessible';

        // Redirect Location header (after login/logout/form posts, etc.).
        $location = $response->headers->get('Location');
        if (is_string($location) && $location !== '') {
            $response->headers->set('Location', $this->strip($location, $base));
        }

        // HTML body links/forms only — never touch JSON, CSV or file downloads.
        $contentType = (string) $response->headers->get('Content-Type', '');
        if (
            str_contains($contentType, 'text/html')
            && method_exists($response, 'getContent')
            && method_exists($response, 'setContent')
        ) {
            $content = $response->getContent();
            if (is_string($content) && $content !== '') {
                $response->setContent($this->strip($content, $base));
            }
        }

        return $response;
    }

    /**
     * Replace every "/{slug}/accessible" prefix with "/". The longer
     * "/{slug}/accessible/" form is replaced first so sub-paths collapse
     * cleanly (".../accessible/login" → "/login") and the bare home
     * ("/{slug}/accessible") maps to "/".
     */
    private function strip(string $value, string $base): string
    {
        $value = str_replace($base . '/', '/', $value);
        $value = str_replace($base, '/', $value);

        return $value;
    }
}
