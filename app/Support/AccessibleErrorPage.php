<?php
// Copyright © 2024–2026 Jasper Ford
// SPDX-License-Identifier: AGPL-3.0-or-later
// Author: Jasper Ford
// See NOTICE file for attribution and acknowledgements.

namespace App\Support;

use App\Core\TenantContext;
use Illuminate\Database\Eloquent\ModelNotFoundException;
use Illuminate\Http\Request;
use Illuminate\Session\TokenMismatchException;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpKernel\Exception\HttpExceptionInterface;

/**
 * Renders GOV.UK-styled error pages for the accessible frontend.
 *
 * Without this, aborts inside the accessible route group fall through to the
 * default Laravel error pages: no GOV.UK layout, no tenant context, no way
 * back, and no AGPL Section 7(b) attribution (a hard project requirement on
 * every page). The error view is standalone — it does not extend the main
 * accessible layout, so it needs none of the controller shared data and can
 * never itself fail on missing tenant context.
 */
class AccessibleErrorPage
{
    /** Statuses with dedicated copy; anything else falls back to generic. */
    private const HANDLED = [403, 404, 419, 429, 503];

    /**
     * Map an exception to the HTTP status this page should render, or null
     * when the exception is not an HTTP-shaped error we want to skin.
     */
    public static function statusFor(\Throwable $e): ?int
    {
        if ($e instanceof HttpExceptionInterface) {
            return $e->getStatusCode();
        }
        if ($e instanceof ModelNotFoundException) {
            return 404;
        }
        if ($e instanceof TokenMismatchException) {
            return 419;
        }

        return null;
    }

    /**
     * Whether this request belongs to the accessible (GOV.UK) frontend.
     */
    public static function handles(Request $request): bool
    {
        $routeName = (string) ($request->route()?->getName() ?? '');
        if (str_starts_with($routeName, 'govuk-alpha.')) {
            return true;
        }

        // Unmatched paths under the accessible prefix never resolve a route,
        // so fall back to the path shape ("/alpha" kept for legacy URLs).
        if (preg_match('#^[a-zA-Z0-9_-]+/(accessible|alpha)(/|$)#', $request->path())) {
            return true;
        }

        try {
            return TenantContext::isAccessibleDomain();
        } catch (\Throwable) {
            return false;
        }
    }

    public static function render(Request $request, int $status): ?Response
    {
        if ($status < 400 || $status > 599) {
            return null;
        }

        $key = in_array($status, self::HANDLED, true) ? (string) $status : 'generic';
        $prefix = 'govuk_alpha.error_pages.' . $key;

        return response()->view('accessible-frontend::error', [
            'status' => $status,
            'title' => __($prefix . '_title'),
            'body' => __($prefix . '_body'),
            'homeUrl' => self::homeUrl($request),
            'assetCss' => self::stylesheets(),
        ], $status);
    }

    /**
     * Best-effort link back to the accessible home for this tenant.
     */
    private static function homeUrl(Request $request): ?string
    {
        $slug = $request->route()?->parameter('tenantSlug');
        if (is_string($slug) && $slug !== '') {
            return '/' . $slug . '/accessible';
        }

        if (preg_match('#^([a-zA-Z0-9_-]+)/(accessible|alpha)(/|$)#', $request->path(), $m)) {
            return '/' . $m[1] . '/accessible';
        }

        try {
            if (TenantContext::isAccessibleDomain()) {
                return '/';
            }
        } catch (\Throwable) {
            // Tenant not resolved — no home link is better than a wrong one.
        }

        return null;
    }

    /**
     * Built stylesheet URLs from the accessible frontend's Vite manifest.
     * Mirrors AlphaController::assetEntrypoint(), CSS only.
     *
     * @return list<string>
     */
    private static function stylesheets(): array
    {
        $manifestPath = base_path('httpdocs/build/accessible-frontend/.vite/manifest.json');
        if (!is_file($manifestPath)) {
            return [];
        }

        $manifest = json_decode((string) file_get_contents($manifestPath), true);
        $entry = is_array($manifest) ? ($manifest['accessible-frontend/src/app.ts'] ?? []) : [];

        return array_map(
            static fn (string $file): string => '/build/accessible-frontend/' . $file,
            is_array($entry['css'] ?? null) ? $entry['css'] : []
        );
    }
}
