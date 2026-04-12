<?php
// Copyright © 2024–2026 Jasper Ford
// SPDX-License-Identifier: AGPL-3.0-or-later
// Author: Jasper Ford
// See NOTICE file for attribution and acknowledgements.

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class SecurityHeaders
{
    public function handle(Request $request, Closure $next): Response
    {
        // Generate a per-request nonce for CSP script-src. Blade templates can
        // read this via $request->attributes->get('csp_nonce') or the shared
        // 'cspNonce' view variable and emit <script nonce="{{ $cspNonce }}">.
        $nonce = bin2hex(random_bytes(16));
        $request->attributes->set('csp_nonce', $nonce);
        if (function_exists('view')) {
            try {
                view()->share('cspNonce', $nonce);
            } catch (\Throwable $e) {
                // View factory not bootable in this context — ignore.
            }
        }

        $response = $next($request);

        $response->headers->set('X-Frame-Options', 'SAMEORIGIN');
        $response->headers->set('X-Content-Type-Options', 'nosniff');
        $response->headers->set('X-XSS-Protection', '1; mode=block');

        // CSP notes:
        // - script-src: 'unsafe-inline' removed; per-request nonce required for
        //   any inline <script> blocks. Bundled React assets are served with
        //   src="..." from the React origin and are covered by 'self' https:.
        // - style-src: 'unsafe-inline' retained because HeroUI / Tailwind /
        //   Framer Motion inject runtime inline style attributes (e.g. style="...")
        //   and removing it would break the UI. Narrowed from "https:" wildcard
        //   to 'self' + explicit https: origins only.
        //   TODO: migrate to nonce/hash-based style-src once HeroUI exposes a
        //   nonce prop and inline style attributes are eliminated.
        $csp = "default-src 'self' https: data: blob:; "
            . "script-src 'self' 'nonce-{$nonce}' https:; "
            . "style-src 'self' 'unsafe-inline' https:; "
            . "connect-src 'self' https: wss://*.pusher.com wss://ws-eu.pusher.com; "
            . "img-src 'self' https: data: blob:; "
            . "font-src 'self' https: data:; "
            . "worker-src 'self' blob:; "
            . "frame-ancestors 'self';";
        $response->headers->set('Content-Security-Policy', $csp);
        $response->headers->set('Referrer-Policy', 'strict-origin-when-cross-origin');

        if ($request->secure()) {
            $response->headers->set('Strict-Transport-Security', 'max-age=31536000; includeSubDomains; preload');
        }

        return $response;
    }
}
