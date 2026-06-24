<?php
// Copyright © 2024–2026 Jasper Ford
// SPDX-License-Identifier: AGPL-3.0-or-later
// Author: Jasper Ford
// See NOTICE file for attribution and acknowledgements.

namespace Tests\Laravel\Unit\Middleware;

use App\Http\Middleware\SeoRedirectMiddleware;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Tests\Laravel\TestCase;
use Illuminate\Foundation\Testing\DatabaseTransactions;

class SeoRedirectMiddlewareTest extends TestCase
{
    use DatabaseTransactions;

    private SeoRedirectMiddleware $middleware;

    protected function setUp(): void
    {
        parent::setUp();
        $this->middleware = new SeoRedirectMiddleware();
    }

    private function makeNext(): \Closure
    {
        return function ($request) {
            return response('ok', 200);
        };
    }

    private function insertRedirect(string $source, string $destination): int
    {
        return (int) DB::table('seo_redirects')->insertGetId([
            'tenant_id'       => $this->testTenantId,
            'source_url'      => $source,
            'destination_url' => $destination,
            'hits'            => 0,
            'created_at'      => now(),
        ]);
    }

    // -----------------------------------------------------------------------
    // Matching redirect → 301
    // -----------------------------------------------------------------------

    public function test_returns_301_when_source_url_matches(): void
    {
        $this->insertRedirect('/old-page', '/new-page');

        $request = Request::create('/old-page', 'GET');
        $response = $this->middleware->handle($request, $this->makeNext());

        $this->assertEquals(301, $response->getStatusCode());
    }

    public function test_redirect_target_is_correct_destination(): void
    {
        $this->insertRedirect('/old-path', '/new-path');

        $request = Request::create('/old-path', 'GET');
        $response = $this->middleware->handle($request, $this->makeNext());

        $this->assertStringContainsString('/new-path', $response->headers->get('Location'));
    }

    // -----------------------------------------------------------------------
    // No matching redirect → pass through
    // -----------------------------------------------------------------------

    public function test_passes_through_when_no_matching_redirect(): void
    {
        $called = false;
        $next = function ($request) use (&$called) {
            $called = true;
            return response('ok', 200);
        };

        $request = Request::create('/path-with-no-redirect', 'GET');
        $response = $this->middleware->handle($request, $next);

        $this->assertTrue($called);
        $this->assertEquals(200, $response->getStatusCode());
    }

    // -----------------------------------------------------------------------
    // Non-GET/HEAD methods bypass redirect lookup
    // -----------------------------------------------------------------------

    public function test_post_request_is_not_redirected(): void
    {
        $this->insertRedirect('/old-page', '/new-page');

        $called = false;
        $next = function ($request) use (&$called) {
            $called = true;
            return response('ok', 200);
        };

        $request = Request::create('/old-page', 'POST');
        $response = $this->middleware->handle($request, $next);

        $this->assertTrue($called);
        $this->assertEquals(200, $response->getStatusCode());
    }

    public function test_put_request_is_not_redirected(): void
    {
        $this->insertRedirect('/old-page', '/new-page');

        $called = false;
        $next = function ($request) use (&$called) {
            $called = true;
            return response('ok', 200);
        };

        $request = Request::create('/old-page', 'PUT');
        $response = $this->middleware->handle($request, $next);

        $this->assertTrue($called);
        $this->assertEquals(200, $response->getStatusCode());
    }

    // -----------------------------------------------------------------------
    // HEAD requests should also be checked (allowed by middleware)
    // -----------------------------------------------------------------------

    public function test_head_request_triggers_redirect(): void
    {
        $this->insertRedirect('/old-head-page', '/new-head-page');

        $request = Request::create('/old-head-page', 'HEAD');
        $response = $this->middleware->handle($request, $this->makeNext());

        $this->assertEquals(301, $response->getStatusCode());
    }

    // -----------------------------------------------------------------------
    // Excluded path prefixes pass through without DB lookup
    // -----------------------------------------------------------------------

    public function test_admin_path_is_not_redirected(): void
    {
        // Even if we have a redirect row, admin paths must never be redirected
        $this->insertRedirect('/admin/settings', '/somewhere-else');

        $called = false;
        $next = function ($request) use (&$called) {
            $called = true;
            return response('ok', 200);
        };

        $request = Request::create('/admin/settings', 'GET');
        $response = $this->middleware->handle($request, $next);

        $this->assertTrue($called);
        $this->assertEquals(200, $response->getStatusCode());
    }

    public function test_api_path_is_not_redirected(): void
    {
        $called = false;
        $next = function ($request) use (&$called) {
            $called = true;
            return response('ok', 200);
        };

        $request = Request::create('/api/v2/feed', 'GET');
        $response = $this->middleware->handle($request, $next);

        $this->assertTrue($called);
        $this->assertEquals(200, $response->getStatusCode());
    }

    public function test_login_path_is_not_redirected(): void
    {
        $called = false;
        $next = function ($request) use (&$called) {
            $called = true;
            return response('ok', 200);
        };

        $request = Request::create('/login', 'GET');
        $response = $this->middleware->handle($request, $next);

        $this->assertTrue($called);
        $this->assertEquals(200, $response->getStatusCode());
    }

    // -----------------------------------------------------------------------
    // Static file extensions pass through
    // -----------------------------------------------------------------------

    public function test_css_file_request_is_not_redirected(): void
    {
        $called = false;
        $next = function ($request) use (&$called) {
            $called = true;
            return response('ok', 200);
        };

        $request = Request::create('/assets/style.css', 'GET');
        $response = $this->middleware->handle($request, $next);

        $this->assertTrue($called);
        $this->assertEquals(200, $response->getStatusCode());
    }

    public function test_js_file_request_is_not_redirected(): void
    {
        $called = false;
        $next = function ($request) use (&$called) {
            $called = true;
            return response('ok', 200);
        };

        $request = Request::create('/assets/app.js', 'GET');
        $response = $this->middleware->handle($request, $next);

        $this->assertTrue($called);
        $this->assertEquals(200, $response->getStatusCode());
    }

    public function test_image_file_request_is_not_redirected(): void
    {
        $called = false;
        $next = function ($request) use (&$called) {
            $called = true;
            return response('ok', 200);
        };

        $request = Request::create('/uploads/photo.jpg', 'GET');
        $response = $this->middleware->handle($request, $next);

        $this->assertTrue($called);
        $this->assertEquals(200, $response->getStatusCode());
    }

    // -----------------------------------------------------------------------
    // Source == destination: no infinite redirect (different tenant rows only)
    // -----------------------------------------------------------------------

    public function test_does_not_redirect_when_source_equals_destination(): void
    {
        // Insert a redirect where destination_url == source_url (should not loop)
        $this->insertRedirect('/same-path', '/same-path');

        $called = false;
        $next = function ($request) use (&$called) {
            $called = true;
            return response('ok', 200);
        };

        $request = Request::create('/same-path', 'GET');
        $response = $this->middleware->handle($request, $next);

        $this->assertTrue($called);
        $this->assertEquals(200, $response->getStatusCode());
    }

    // -----------------------------------------------------------------------
    // Tenant isolation: redirect from another tenant is not applied
    // -----------------------------------------------------------------------

    public function test_redirect_for_other_tenant_is_not_applied(): void
    {
        // Insert a redirect row for a DIFFERENT tenant
        DB::table('seo_redirects')->insert([
            'tenant_id'       => 999,
            'source_url'      => '/tenant-specific-old',
            'destination_url' => '/tenant-specific-new',
            'hits'            => 0,
            'created_at'      => now(),
        ]);

        $called = false;
        $next = function ($request) use (&$called) {
            $called = true;
            return response('ok', 200);
        };

        // Current tenant is $this->testTenantId (2), not 999
        $request = Request::create('/tenant-specific-old', 'GET');
        $response = $this->middleware->handle($request, $next);

        $this->assertTrue($called);
        $this->assertEquals(200, $response->getStatusCode());
    }

    // -----------------------------------------------------------------------
    // Hit counter is incremented on a match
    // -----------------------------------------------------------------------

    public function test_hit_counter_incremented_on_redirect(): void
    {
        $id = $this->insertRedirect('/hit-test-page', '/hit-test-dest');

        $request = Request::create('/hit-test-page', 'GET');
        $this->middleware->handle($request, $this->makeNext());

        $hits = DB::table('seo_redirects')->where('id', $id)->value('hits');
        $this->assertEquals(1, $hits);
    }
}
