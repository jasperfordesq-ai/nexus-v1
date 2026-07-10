<?php
// Copyright © 2024–2026 Jasper Ford
// SPDX-License-Identifier: AGPL-3.0-or-later
// Author: Jasper Ford
// See NOTICE file for attribution and acknowledgements.

namespace Tests\Laravel\Unit\Middleware;

use App\Http\Middleware\AlphaSetLocale;
use App\Services\TokenService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\App;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Session;
use Tests\Laravel\TestCase;
use Illuminate\Foundation\Testing\DatabaseTransactions;

class AlphaSetLocaleTest extends TestCase
{
    use DatabaseTransactions;

    private TokenService $tokenService;
    private AlphaSetLocale $middleware;

    protected function setUp(): void
    {
        parent::setUp();
        $this->tokenService = $this->createMock(TokenService::class);
        $this->middleware = new AlphaSetLocale($this->tokenService);
        App::setLocale('en');
    }

    protected function tearDown(): void
    {
        App::setLocale('en');
        parent::tearDown();
    }

    private function makeNext(): \Closure
    {
        return function ($request) {
            return response('ok', 200);
        };
    }

    /**
     * Build a request with a real Laravel session store attached so that
     * $request->hasSession() is true and session operations work.
     */
    private function makeSessionRequest(string $uri = '/accessible/feed', string $method = 'GET', array $query = []): Request
    {
        $request = Request::create($uri, $method, $query);
        // Attach a real session store backed by the array driver
        $session = app('session.store');
        $session->flush();
        $request->setLaravelSession($session);
        return $request;
    }

    // -----------------------------------------------------------------------
    // Priority 1: ?locale= query parameter
    // -----------------------------------------------------------------------

    public function test_sets_locale_from_query_param(): void
    {
        $request = $this->makeSessionRequest('/accessible/feed', 'GET', ['locale' => 'de']);

        $this->middleware->handle($request, $this->makeNext());

        $this->assertEquals('de', App::getLocale());
    }

    public function test_query_param_locale_is_persisted_to_session(): void
    {
        $request = $this->makeSessionRequest('/accessible/feed', 'GET', ['locale' => 'fr']);

        $this->middleware->handle($request, $this->makeNext());

        $this->assertEquals('fr', $request->session()->get('locale'));
    }

    public function test_ignores_unsupported_query_locale(): void
    {
        $request = $this->makeSessionRequest('/accessible/feed', 'GET', ['locale' => 'zz']);

        $this->middleware->handle($request, $this->makeNext());

        // Should not change to 'zz' (default 'en' stays)
        $this->assertNotEquals('zz', App::getLocale());
    }

    public function test_all_supported_locales_accepted_via_query_param(): void
    {
        foreach (AlphaSetLocale::SUPPORTED_LOCALES as $locale) {
            App::setLocale('en');
            $request = $this->makeSessionRequest('/accessible/feed', 'GET', ['locale' => $locale]);

            $this->middleware->handle($request, $this->makeNext());

            $this->assertEquals($locale, App::getLocale(), "Supported locale '{$locale}' should be applied");
        }
    }

    // -----------------------------------------------------------------------
    // Priority 2: locale already in session
    // -----------------------------------------------------------------------

    public function test_uses_session_locale_when_no_query_param(): void
    {
        $request = $this->makeSessionRequest('/accessible/feed');
        $request->session()->put('locale', 'es');

        $this->middleware->handle($request, $this->makeNext());

        $this->assertEquals('es', App::getLocale());
    }

    public function test_session_locale_ignored_if_unsupported(): void
    {
        $request = $this->makeSessionRequest('/accessible/feed');
        $request->session()->put('locale', 'xyz'); // not a supported locale

        // No user, no token — should leave App locale at whatever it was (fallback)
        $localeBeforeHandle = App::getLocale();
        $this->middleware->handle($request, $this->makeNext());

        // The middleware returns null → does not call App::setLocale() → stays at 'en'
        $this->assertEquals($localeBeforeHandle, App::getLocale());
    }

    public function test_query_param_overrides_session_locale(): void
    {
        $request = $this->makeSessionRequest('/accessible/feed', 'GET', ['locale' => 'nl']);
        $request->session()->put('locale', 'it');

        $this->middleware->handle($request, $this->makeNext());

        // query param wins over session
        $this->assertEquals('nl', App::getLocale());
    }

    // -----------------------------------------------------------------------
    // Priority 3: signed-in member's stored preferred_language (DB lookup)
    // -----------------------------------------------------------------------

    public function test_uses_user_preferred_language_from_db_via_bearer_token(): void
    {
        // Insert a real user row so DB::table('users') can find the preferred_language
        $userId = (int) DB::table('users')->insertGetId([
            'tenant_id'          => $this->testTenantId,
            'name'               => 'Alpha Locale User',
            'email'              => 'alpha-locale-' . uniqid() . '@test.com',
            'preferred_language' => 'pl',
            'status'             => 'active',
            'role'               => 'member',
            'created_at'         => now(),
            'updated_at'         => now(),
        ]);

        // Mock TokenService to return this user's id
        $this->tokenService
            ->expects($this->once())
            ->method('validateToken')
            ->willReturn(['user_id' => $userId]);

        $request = $this->makeSessionRequest('/accessible/feed');
        $request->headers->set('Authorization', 'Bearer valid-token-123');

        $this->middleware->handle($request, $this->makeNext());

        $this->assertEquals('pl', App::getLocale());
    }

    public function test_db_preference_seeded_into_session(): void
    {
        $userId = (int) DB::table('users')->insertGetId([
            'tenant_id'          => $this->testTenantId,
            'name'               => 'Alpha Session Seed User',
            'email'              => 'alpha-session-' . uniqid() . '@test.com',
            'preferred_language' => 'ja',
            'status'             => 'active',
            'role'               => 'member',
            'created_at'         => now(),
            'updated_at'         => now(),
        ]);

        $this->tokenService
            ->expects($this->once())
            ->method('validateToken')
            ->willReturn(['user_id' => $userId]);

        $request = $this->makeSessionRequest('/accessible/feed');
        $request->headers->set('Authorization', 'Bearer valid-token-abc');

        $this->middleware->handle($request, $this->makeNext());

        $this->assertEquals('ja', $request->session()->get('locale'));
    }

    public function test_invalid_token_does_not_set_locale_from_db(): void
    {
        $this->tokenService
            ->expects($this->once())
            ->method('validateToken')
            ->willThrowException(new \RuntimeException('invalid token'));

        $request = $this->makeSessionRequest('/accessible/feed');
        $request->headers->set('Authorization', 'Bearer bad-token');

        // No session locale, no query param — should leave locale at 'en'
        $this->middleware->handle($request, $this->makeNext());

        $this->assertEquals('en', App::getLocale());
    }

    // -----------------------------------------------------------------------
    // Priority 4: no override — do not call App::setLocale (leave as-is)
    // -----------------------------------------------------------------------

    public function test_does_not_change_locale_when_no_signals_present(): void
    {
        App::setLocale('en');

        $request = $this->makeSessionRequest('/accessible/feed');
        // No query param, no session, no token

        $this->middleware->handle($request, $this->makeNext());

        // The middleware returns null → App::setLocale is NOT called → stays at 'en'
        $this->assertEquals('en', App::getLocale());
    }

    // -----------------------------------------------------------------------
    // Passes through to $next regardless
    // -----------------------------------------------------------------------

    public function test_passes_request_to_next_middleware(): void
    {
        $called = false;
        $next = function ($req) use (&$called) {
            $called = true;
            return response('ok', 200);
        };

        $request = $this->makeSessionRequest('/accessible/feed');
        $this->middleware->handle($request, $next);

        $this->assertTrue($called);
    }

    public function test_preserves_response_from_next(): void
    {
        $next = fn ($req) => response()->json(['alpha' => true], 202);

        $request = $this->makeSessionRequest('/accessible/profile');
        $response = $this->middleware->handle($request, $next);

        $this->assertEquals(202, $response->getStatusCode());
    }
}
