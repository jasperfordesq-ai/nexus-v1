<?php
// Copyright © 2024–2026 Jasper Ford
// SPDX-License-Identifier: AGPL-3.0-or-later
// Author: Jasper Ford
// See NOTICE file for attribution and acknowledgements.

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\App;
use Illuminate\Support\Facades\Auth;
use App\I18n\Translator;
use Symfony\Component\HttpFoundation\Response;

/**
 * Resolve the request locale for API responses and admin views.
 *
 * Priority (highest first):
 *   1. ?locale=xx  query parameter
 *   2. Authenticated user's saved language preference
 *   3. Accept-Language header (best match)
 *   4. Application default ('en')
 */
class SetLocale
{
    private const SUPPORTED_LOCALES = ['en', 'ga', 'de', 'fr', 'it', 'pt', 'es', 'nl', 'pl', 'ja', 'ar'];

    public function handle(Request $request, Closure $next): Response
    {
        $locale = $this->resolveLocale($request);

        App::setLocale($locale);
        Translator::setLocale($locale);

        /** @var Response $response */
        $response = $next($request);
        $response->headers->set('Content-Language', $locale);

        return $response;
    }

    private function resolveLocale(Request $request): string
    {
        // 1. Explicit query parameter
        $queryLocale = $request->query('locale');
        if ($queryLocale && in_array($queryLocale, self::SUPPORTED_LOCALES, true)) {
            return $queryLocale;
        }

        // 2. Authenticated user's saved preference
        $user = Auth::user();
        if ($user && !empty($user->preferred_language) && in_array($user->preferred_language, self::SUPPORTED_LOCALES, true)) {
            return $user->preferred_language;
        }

        // 3. Accept-Language header
        $preferred = $request->getPreferredLanguage(self::SUPPORTED_LOCALES);
        if ($preferred) {
            return $preferred;
        }

        // 4. Fallback
        return config('app.locale', 'en');
    }
}
