<?php
// Copyright © 2024–2026 Jasper Ford
// SPDX-License-Identifier: AGPL-3.0-or-later
// Author: Jasper Ford
// See NOTICE file for attribution and acknowledgements.

namespace Tests\Laravel\Unit\Middleware;

use App\Http\Middleware\SetLocale;
use App\Models\User;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\App;
use Illuminate\Support\Facades\Auth;
use Tests\Laravel\TestCase;
use Illuminate\Foundation\Testing\DatabaseTransactions;

class SetLocaleTest extends TestCase
{
    use DatabaseTransactions;
    private SetLocale $middleware;

    protected function setUp(): void
    {
        parent::setUp();
        $this->middleware = new SetLocale();
        // Reset locale to 'en' before each test
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

    // -----------------------------------------------------------------------
    // Priority 1: ?locale= query parameter
    // -----------------------------------------------------------------------

    public function test_sets_locale_from_query_param(): void
    {
        $request = Request::create('/api/v2/feed?locale=fr', 'GET');

        $this->middleware->handle($request, $this->makeNext());

        $this->assertEquals('fr', App::getLocale());
    }

    public function test_ignores_unsupported_query_locale_and_falls_back_to_default(): void
    {
        $request = Request::create('/api/v2/feed?locale=zz', 'GET');

        $this->middleware->handle($request, $this->makeNext());

        // Should NOT be 'zz' — should fall back to 'en' (no Auth user, no Accept-Language)
        $this->assertNotEquals('zz', App::getLocale());
        $this->assertEquals('en', App::getLocale());
    }

    public function test_supported_locales_all_accepted_via_query_param(): void
    {
        $supported = ['en', 'ga', 'de', 'fr', 'it', 'pt', 'es', 'nl', 'pl', 'ja', 'ar'];

        foreach ($supported as $locale) {
            $request = Request::create("/api/v2/feed?locale={$locale}", 'GET');
            $this->middleware->handle($request, $this->makeNext());
            $this->assertEquals($locale, App::getLocale(), "Locale '{$locale}' via query param should be set");
        }
    }

    // -----------------------------------------------------------------------
    // Priority 2: authenticated user's preferred_language
    // -----------------------------------------------------------------------

    public function test_sets_locale_from_authenticated_user_preferred_language(): void
    {
        $user = User::factory()->create([
            'tenant_id'          => $this->testTenantId,
            'preferred_language' => 'de',
        ]);

        // actingAs() sets Auth::user() for the duration of the test
        $this->actingAs($user);

        $request = Request::create('/api/v2/feed', 'GET');
        $this->middleware->handle($request, $this->makeNext());

        $this->assertEquals('de', App::getLocale());
    }

    public function test_user_preferred_language_must_be_supported(): void
    {
        // preferred_language column has DEFAULT 'en'; override via DB after create
        $user = User::factory()->create([
            'tenant_id' => $this->testTenantId,
        ]);
        // Set an unsupported locale directly on the model attribute
        $user->preferred_language = 'zz';

        $this->actingAs($user);

        $request = Request::create('/api/v2/feed', 'GET');
        $this->middleware->handle($request, $this->makeNext());

        $this->assertNotEquals('zz', App::getLocale());
    }

    // -----------------------------------------------------------------------
    // Priority 3: Accept-Language header
    // -----------------------------------------------------------------------

    public function test_sets_locale_from_accept_language_header(): void
    {
        $request = Request::create('/api/v2/feed', 'GET', [], [], [], [
            'HTTP_ACCEPT_LANGUAGE' => 'nl, en;q=0.9',
        ]);

        $this->middleware->handle($request, $this->makeNext());

        $this->assertEquals('nl', App::getLocale());
    }

    public function test_falls_back_to_en_when_accept_language_unsupported(): void
    {
        $request = Request::create('/api/v2/feed', 'GET', [], [], [], [
            'HTTP_ACCEPT_LANGUAGE' => 'xx-XX',
        ]);

        $this->middleware->handle($request, $this->makeNext());

        $this->assertEquals('en', App::getLocale());
    }

    // -----------------------------------------------------------------------
    // Priority 4: fallback to app default
    // -----------------------------------------------------------------------

    public function test_falls_back_to_application_default_locale(): void
    {
        $request = Request::create('/api/v2/feed', 'GET');

        $this->middleware->handle($request, $this->makeNext());

        $this->assertEquals('en', App::getLocale());
    }

    // -----------------------------------------------------------------------
    // Query param takes priority over Accept-Language
    // -----------------------------------------------------------------------

    public function test_query_param_overrides_accept_language_header(): void
    {
        $request = Request::create('/api/v2/feed?locale=es', 'GET', [], [], [], [
            'HTTP_ACCEPT_LANGUAGE' => 'nl',
        ]);

        $this->middleware->handle($request, $this->makeNext());

        $this->assertEquals('es', App::getLocale());
    }

    // -----------------------------------------------------------------------
    // Response Content-Language header
    // -----------------------------------------------------------------------

    public function test_sets_content_language_response_header(): void
    {
        $request = Request::create('/api/v2/feed?locale=it', 'GET');

        $response = $this->middleware->handle($request, $this->makeNext());

        $this->assertEquals('it', $response->headers->get('Content-Language'));
    }

    public function test_content_language_header_matches_resolved_locale(): void
    {
        $request = Request::create('/api/v2/feed', 'GET');

        $response = $this->middleware->handle($request, $this->makeNext());

        $this->assertEquals(App::getLocale(), $response->headers->get('Content-Language'));
    }

    // -----------------------------------------------------------------------
    // Passes the request through to $next
    // -----------------------------------------------------------------------

    public function test_passes_request_to_next_middleware(): void
    {
        $called = false;
        $next = function ($request) use (&$called) {
            $called = true;
            return response('ok', 200);
        };

        $request = Request::create('/api/v2/feed', 'GET');
        $this->middleware->handle($request, $next);

        $this->assertTrue($called);
    }

    public function test_preserves_response_status_from_next(): void
    {
        $next = fn ($request) => response()->json(['created' => true], 201);

        $request = Request::create('/api/v2/listings', 'POST');
        $response = $this->middleware->handle($request, $next);

        $this->assertEquals(201, $response->getStatusCode());
    }
}
