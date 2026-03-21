<?php
// Copyright © 2024–2026 Jasper Ford
// SPDX-License-Identifier: AGPL-3.0-or-later
// Author: Jasper Ford
// See NOTICE file for attribution and acknowledgements.

namespace Tests\Laravel\Unit\Middleware;

use App\Http\Middleware\SecurityHeaders;
use Illuminate\Http\Request;
use Tests\Laravel\TestCase;

class SecurityHeadersTest extends TestCase
{
    private SecurityHeaders $middleware;

    protected function setUp(): void
    {
        parent::setUp();
        $this->middleware = new SecurityHeaders();
    }

    private function makeNext(): \Closure
    {
        return function ($request) {
            return response()->json(['ok' => true], 200);
        };
    }

    public function test_handle_sets_x_frame_options(): void
    {
        $request = Request::create('/api/v2/feed', 'GET');
        $response = $this->middleware->handle($request, $this->makeNext());

        $this->assertEquals('SAMEORIGIN', $response->headers->get('X-Frame-Options'));
    }

    public function test_handle_sets_x_content_type_options(): void
    {
        $request = Request::create('/api/v2/feed', 'GET');
        $response = $this->middleware->handle($request, $this->makeNext());

        $this->assertEquals('nosniff', $response->headers->get('X-Content-Type-Options'));
    }

    public function test_handle_sets_x_xss_protection(): void
    {
        $request = Request::create('/api/v2/feed', 'GET');
        $response = $this->middleware->handle($request, $this->makeNext());

        $this->assertEquals('1; mode=block', $response->headers->get('X-XSS-Protection'));
    }

    public function test_handle_sets_content_security_policy(): void
    {
        $request = Request::create('/api/v2/feed', 'GET');
        $response = $this->middleware->handle($request, $this->makeNext());

        $csp = $response->headers->get('Content-Security-Policy');
        $this->assertNotNull($csp);
        $this->assertStringContainsString("default-src 'self'", $csp);
        $this->assertStringContainsString("frame-ancestors 'self'", $csp);
        $this->assertStringContainsString('wss://*.pusher.com', $csp);
    }

    public function test_handle_sets_referrer_policy(): void
    {
        $request = Request::create('/api/v2/feed', 'GET');
        $response = $this->middleware->handle($request, $this->makeNext());

        $this->assertEquals('strict-origin-when-cross-origin', $response->headers->get('Referrer-Policy'));
    }

    public function test_handle_sets_hsts_on_secure_request(): void
    {
        $request = Request::create('https://app.project-nexus.ie/api/v2/feed', 'GET', [], [], [], [
            'HTTPS' => 'on',
        ]);
        $response = $this->middleware->handle($request, $this->makeNext());

        $hsts = $response->headers->get('Strict-Transport-Security');
        $this->assertNotNull($hsts);
        $this->assertStringContainsString('max-age=31536000', $hsts);
        $this->assertStringContainsString('includeSubDomains', $hsts);
        $this->assertStringContainsString('preload', $hsts);
    }

    public function test_handle_does_not_set_hsts_on_insecure_request(): void
    {
        $request = Request::create('http://localhost/api/v2/feed', 'GET');
        $response = $this->middleware->handle($request, $this->makeNext());

        $this->assertNull($response->headers->get('Strict-Transport-Security'));
    }

    public function test_handle_preserves_original_response_status(): void
    {
        $next = function ($request) {
            return response()->json(['created' => true], 201);
        };

        $request = Request::create('/api/v2/listings', 'POST');
        $response = $this->middleware->handle($request, $next);

        $this->assertEquals(201, $response->getStatusCode());
        // Security headers still added
        $this->assertEquals('nosniff', $response->headers->get('X-Content-Type-Options'));
    }

    public function test_handle_preserves_original_response_body(): void
    {
        $next = function ($request) {
            return response()->json(['data' => 'test_value'], 200);
        };

        $request = Request::create('/api/v2/feed', 'GET');
        $response = $this->middleware->handle($request, $next);

        $data = $response->getData(true);
        $this->assertEquals('test_value', $data['data']);
    }
}
