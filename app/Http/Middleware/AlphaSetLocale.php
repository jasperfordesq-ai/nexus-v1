<?php
// Copyright © 2024–2026 Jasper Ford
// SPDX-License-Identifier: AGPL-3.0-or-later
// Author: Jasper Ford
// See NOTICE file for attribution and acknowledgements.

namespace App\Http\Middleware;

use App\I18n\Translator;
use App\Services\TokenService;
use Closure;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\App;
use Illuminate\Support\Facades\DB;
use Symfony\Component\HttpFoundation\Response;

/**
 * Resolve and persist the request locale for the accessible (GOV.UK alpha)
 * frontend.
 *
 * The shared {@see SetLocale} middleware cannot serve this track: it runs
 * BEFORE Laravel's StartSession (so it can't read a stored locale), and the
 * alpha frontend authenticates through a cookie/token session rather than the
 * web Auth guard (so Auth::user() — SetLocale's preference source — is always
 * null here). The net effect without this middleware is that a member's saved
 * language is never honoured and every page falls back to Accept-Language.
 *
 * This middleware is appended AFTER `web` in the alpha route group, so the
 * session is available. Resolution priority (highest first):
 *   1. ?locale=xx          — an explicit switch; persisted to the session.
 *   2. session('locale')   — set by (1), the layout language switcher, or
 *                            AlphaController::updateProfileLanguage.
 *   3. the signed-in member's stored preferred_language — seeded into the
 *      session so the lookup is paid at most once per session.
 *   4. (no override) — leave whatever SetLocale resolved (Accept-Language/'en').
 */
class AlphaSetLocale
{
    public const SUPPORTED_LOCALES = ['en', 'ga', 'de', 'fr', 'it', 'pt', 'es', 'nl', 'pl', 'ja', 'ar'];

    public function __construct(private TokenService $tokenService)
    {
    }

    public function handle(Request $request, Closure $next): Response
    {
        $locale = $this->resolveLocale($request);

        if ($locale !== null) {
            App::setLocale($locale);
            Translator::setLocale($locale);
        }

        return $next($request);
    }

    private function resolveLocale(Request $request): ?string
    {
        // 1. Explicit switch — honour for this request and persist it.
        $query = $request->query('locale');
        if (is_string($query) && in_array($query, self::SUPPORTED_LOCALES, true)) {
            if ($request->hasSession()) {
                $request->session()->put('locale', $query);
            }

            return $query;
        }

        // 2. A locale already chosen this session.
        if ($request->hasSession()) {
            $session = $request->session()->get('locale');
            if (is_string($session) && in_array($session, self::SUPPORTED_LOCALES, true)) {
                return $session;
            }
        }

        // 3. The signed-in member's stored preference — seed the session.
        $userId = $this->resolveUserId($request);
        if ($userId !== null) {
            $pref = DB::table('users')->where('id', $userId)->value('preferred_language');
            if (is_string($pref) && in_array($pref, self::SUPPORTED_LOCALES, true)) {
                if ($request->hasSession()) {
                    $request->session()->put('locale', $pref);
                }

                return $pref;
            }
        }

        // 4. No override — keep SetLocale's resolution.
        return null;
    }

    /**
     * Resolve the alpha member id the same way AlphaController::currentUserId
     * does, but without the web Auth guard (which is never populated here).
     */
    private function resolveUserId(Request $request): ?int
    {
        if (session_status() === PHP_SESSION_ACTIVE && ! empty($_SESSION['user_id'])) {
            return (int) $_SESSION['user_id'];
        }

        $token = $request->bearerToken() ?: $request->cookie('auth_token');
        if (! is_string($token) || $token === '') {
            return null;
        }

        try {
            $payload = $this->tokenService->validateToken($token);
            $userId = (int) (($payload['user_id'] ?? $payload['sub'] ?? 0));

            return $userId > 0 ? $userId : null;
        } catch (\Throwable) {
            return null;
        }
    }
}
