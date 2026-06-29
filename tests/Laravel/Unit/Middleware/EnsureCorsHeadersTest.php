<?php
// Copyright © 2024–2026 Jasper Ford
// SPDX-License-Identifier: AGPL-3.0-or-later
// Author: Jasper Ford
// See NOTICE file for attribution and acknowledgements.

namespace Tests\Laravel\Unit\Middleware;

use App\Http\Middleware\EnsureCorsHeaders;
use Illuminate\Http\Request;
use Tests\Laravel\TestCase;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Illuminate\Support\Facades\DB;

class EnsureCorsHeadersTest extends TestCase
{
    use DatabaseTransactions;

    private EnsureCorsHeaders $middleware;

    protected function setUp(): void
    {
        parent::setUp();
        $this->middleware = new EnsureCorsHeaders();

        // Reset CorsHelper static cache so tenant domain queries run fresh
        $ref = new \ReflectionClass(\App\Helpers\CorsHelper::class);
        $prop = $ref->getProperty('tenantDomainOrigins');
        $prop->setAccessible(true);
        $prop->setValue(null, null);

        $prop2 = $ref->getProperty('allowedOrigins');
        $prop2->setAccessible(true);
        $prop2->setValue(null, null);
    }

    private function makeNext(): \Closure
    {
        return function ($request) {
            return response()->json(['ok' => true], 200);
        };
    }

    /** Non-API paths (no api/ prefix) should pass through without CORS headers */
    public function test_non_api_path_passes_through_without_cors_headers(): void
    {
        $request = Request::create('/health', 'GET');
        $request->headers->set('Origin', 'http://localhost:5173');

        $response = $this->middleware->handle($request, $this->makeNext());

        $this->assertEquals(200, $response->getStatusCode());
        $this->assertFalse($response->headers->has('Access-Control-Allow-Origin'));
    }

    /** No Origin header on API path → no CORS headers (not a CORS request) */
    public function test_api_path_without_origin_header_gets_no_cors_headers(): void
    {
        $request = Request::create('/v2/feed', 'GET');
        // No Origin header

        $response = $this->middleware->handle($request, $this->makeNext());

        $this->assertEquals(200, $response->getStatusCode());
        $this->assertFalse($response->headers->has('Access-Control-Allow-Origin'));
    }

    /** Allowed origin on API path → ACAO reflects the specific origin + credentials */
    public function test_allowed_origin_on_api_path_gets_cors_headers(): void
    {
        $request = Request::create('/v2/feed', 'GET');
        $request->headers->set('Origin', 'http://localhost:5173');

        $response = $this->middleware->handle($request, $this->makeNext());

        $this->assertEquals(200, $response->getStatusCode());
        $this->assertEquals('http://localhost:5173', $response->headers->get('Access-Control-Allow-Origin'));
        $this->assertEquals('true', $response->headers->get('Access-Control-Allow-Credentials'));
        $this->assertNotEmpty($response->headers->get('Access-Control-Allow-Methods'));
    }

    public function test_laravel_cors_config_allows_wallet_transfer_idempotency_header(): void
    {
        $allowedHeaders = array_map('strtolower', config('cors.allowed_headers', []));

        $this->assertContains('idempotency-key', $allowedHeaders);
    }

    public function test_wallet_transfer_fallback_cors_headers_allow_idempotency_key(): void
    {
        $request = Request::create('/api/v2/wallet/transfer', 'OPTIONS');
        $request->headers->set('Origin', 'https://app.project-nexus.ie');

        $response = $this->middleware->handle($request, $this->makeNext());
        $allowedHeaders = strtolower((string) $response->headers->get('Access-Control-Allow-Headers'));

        $this->assertStringContainsString('idempotency-key', $allowedHeaders);
    }

    /** Disallowed (foreign) origin on normal API path → NO CORS headers set (blocked silently) */
    public function test_disallowed_origin_on_api_path_gets_no_cors_headers(): void
    {
        $request = Request::create('/v2/feed', 'GET');
        $request->headers->set('Origin', 'https://evil.example.com');

        $response = $this->middleware->handle($request, $this->makeNext());

        $this->assertEquals(200, $response->getStatusCode());
        $this->assertFalse($response->headers->has('Access-Control-Allow-Origin'));
    }

    /** Federation GET path allows wildcard ACAO regardless of origin */
    public function test_federation_komunitin_get_path_allows_wildcard_cors(): void
    {
        $request = Request::create('/v2/federation/komunitin/accounts', 'GET');
        $request->headers->set('Origin', 'https://external-partner.example.org');

        $response = $this->middleware->handle($request, $this->makeNext());

        $this->assertEquals(200, $response->getStatusCode());
        $this->assertEquals('*', $response->headers->get('Access-Control-Allow-Origin'));
    }

    /** Federation cc GET path also allows wildcard ACAO */
    public function test_federation_cc_get_path_allows_wildcard_cors(): void
    {
        $request = Request::create('/v2/federation/cc/members', 'GET');
        $request->headers->set('Origin', 'https://another-partner.cc');

        $response = $this->middleware->handle($request, $this->makeNext());

        $this->assertEquals(200, $response->getStatusCode());
        $this->assertEquals('*', $response->headers->get('Access-Control-Allow-Origin'));
    }

    /** Federation write from non-whitelisted origin → 403 */
    public function test_federation_write_from_non_whitelisted_origin_returns_403(): void
    {
        $request = Request::create('/v2/federation/komunitin/transfers', 'POST');
        $request->headers->set('Origin', 'https://not-in-whitelist.example.com');

        $response = $this->middleware->handle($request, $this->makeNext());

        $this->assertEquals(403, $response->getStatusCode());
        $data = json_decode($response->getContent(), true);
        $this->assertStringContainsString('federation whitelist', $data['message']);
    }

    /**
     * Federation write from a registered, ACTIVE remote partner origin → allowed,
     * with ACAO reflecting the specific origin.
     *
     * The whitelist source is federation_external_partners (base_url + status),
     * NOT federation_tenant_whitelist (which is the local-tenant approval list,
     * keyed by tenant_id, and holds no remote URLs). A partner row whose base_url
     * matches the request Origin and whose status is 'active' authorizes the write.
     */
    public function test_federation_write_from_whitelisted_active_partner_origin_is_allowed(): void
    {
        $origin = 'https://trusted-partner.example.org';
        $tenantId = (int) DB::table('tenants')->min('id');

        DB::table('federation_external_partners')->insert([
            'tenant_id' => $tenantId,
            'name'      => 'Trusted Partner',
            'base_url'  => $origin,
            'status'    => 'active',
        ]);

        $request = Request::create('/v2/federation/komunitin/transfers', 'POST');
        $request->headers->set('Origin', $origin);

        $response = $this->middleware->handle($request, $this->makeNext());

        $this->assertEquals(200, $response->getStatusCode());
        $this->assertEquals($origin, $response->headers->get('Access-Control-Allow-Origin'));
    }

    /**
     * Federation write from a registered partner that is NOT active (e.g. pending
     * or suspended) → 403. Only status='active' partners may mutate.
     */
    public function test_federation_write_from_non_active_partner_origin_returns_403(): void
    {
        $origin = 'https://pending-partner.example.org';
        $tenantId = (int) DB::table('tenants')->min('id');

        DB::table('federation_external_partners')->insert([
            'tenant_id' => $tenantId,
            'name'      => 'Pending Partner',
            'base_url'  => $origin,
            'status'    => 'pending',
        ]);

        $request = Request::create('/v2/federation/komunitin/transfers', 'POST');
        $request->headers->set('Origin', $origin);

        $response = $this->middleware->handle($request, $this->makeNext());

        $this->assertEquals(403, $response->getStatusCode());
        $data = json_decode($response->getContent(), true);
        $this->assertStringContainsString('federation whitelist', $data['message']);
    }

    /**
     * Security regression: the Origin header is attacker-controlled on these
     * server-to-server calls, so SQL LIKE wildcards in it must NOT bypass the
     * whitelist. With an active partner present, crafted wildcard origins must
     * still be rejected — they must not match every (or any non-equal) active
     * partner. (Previously the origin was used as a LIKE prefix pattern, so
     * "https://%" matched all active partners.)
     */
    public function test_federation_write_with_like_wildcard_origin_does_not_bypass_whitelist(): void
    {
        $tenantId = (int) DB::table('tenants')->min('id');
        DB::table('federation_external_partners')->insert([
            'tenant_id' => $tenantId,
            'name'      => 'Active Partner',
            'base_url'  => 'https://real-partner.example.org',
            'status'    => 'active',
        ]);

        foreach (['https://%', 'https://_____', 'https://real-partner.example.%'] as $craftedOrigin) {
            $request = Request::create('/v2/federation/cc/transfers', 'POST');
            $request->headers->set('Origin', $craftedOrigin);

            $response = $this->middleware->handle($request, $this->makeNext());

            $this->assertEquals(
                403,
                $response->getStatusCode(),
                "Crafted origin {$craftedOrigin} must not bypass the federation whitelist"
            );
        }
    }

    /**
     * Security regression: a short prefix of a real partner's origin must NOT
     * authorize a write. With an active partner "https://trusted-partner.example.org",
     * an Origin of "https://trusted-partner" must be rejected (the old LIKE prefix
     * match would have allowed it).
     */
    public function test_federation_write_with_prefix_origin_does_not_match_partner(): void
    {
        $tenantId = (int) DB::table('tenants')->min('id');
        DB::table('federation_external_partners')->insert([
            'tenant_id' => $tenantId,
            'name'      => 'Active Partner',
            'base_url'  => 'https://trusted-partner.example.org',
            'status'    => 'active',
        ]);

        $request = Request::create('/v2/federation/komunitin/transfers', 'POST');
        $request->headers->set('Origin', 'https://trusted-partner');

        $response = $this->middleware->handle($request, $this->makeNext());

        $this->assertEquals(403, $response->getStatusCode());
    }

    /**
     * A partner base_url stored with a path (or trailing slash) still authorizes a
     * write from the matching scheme://host origin: both sides are normalised to
     * their origin before the exact comparison.
     */
    public function test_federation_write_matches_partner_base_url_with_path(): void
    {
        $tenantId = (int) DB::table('tenants')->min('id');
        DB::table('federation_external_partners')->insert([
            'tenant_id' => $tenantId,
            'name'      => 'Pathful Partner',
            'base_url'  => 'https://pathful-partner.example.org/api/v1/federation',
            'status'    => 'active',
        ]);

        $request = Request::create('/v2/federation/komunitin/transfers', 'POST');
        $request->headers->set('Origin', 'https://pathful-partner.example.org');

        $response = $this->middleware->handle($request, $this->makeNext());

        $this->assertEquals(200, $response->getStatusCode());
        $this->assertEquals(
            'https://pathful-partner.example.org',
            $response->headers->get('Access-Control-Allow-Origin')
        );
    }

    /** If inner layer already set ACAO header, this middleware must not override it */
    public function test_does_not_override_already_set_cors_header(): void
    {
        $next = function ($request) {
            $resp = response()->json(['ok' => true], 200);
            $resp->headers->set('Access-Control-Allow-Origin', 'https://pre-set.example.com');
            return $resp;
        };

        $request = Request::create('/v2/feed', 'GET');
        $request->headers->set('Origin', 'http://localhost:5173');

        $response = $this->middleware->handle($request, $next);

        $this->assertEquals('https://pre-set.example.com', $response->headers->get('Access-Control-Allow-Origin'));
    }

    /** Exceptions thrown by inner middleware still get CORS headers on the rendered 500 */
    public function test_exception_from_inner_middleware_still_gets_cors_headers(): void
    {
        $next = function ($request) {
            throw new \RuntimeException('boom');
        };

        $request = Request::create('/v2/feed', 'GET');
        $request->headers->set('Origin', 'http://localhost:5173');

        $response = $this->middleware->handle($request, $next);

        // Should be a 500-range response
        $this->assertGreaterThanOrEqual(500, $response->getStatusCode());
        // CORS header must be present so browsers can read the error
        $this->assertTrue($response->headers->has('Access-Control-Allow-Origin'));
    }

    /** api/ prefix also treated as API path */
    public function test_api_prefix_path_gets_cors_headers(): void
    {
        $request = Request::create('/api/health', 'GET');
        $request->headers->set('Origin', 'http://localhost:5173');

        $response = $this->middleware->handle($request, $this->makeNext());

        $this->assertEquals(200, $response->getStatusCode());
        $this->assertTrue($response->headers->has('Access-Control-Allow-Origin'));
    }

    /** broadcasting/ prefix is treated as API path */
    public function test_broadcasting_prefix_path_gets_cors_headers(): void
    {
        $request = Request::create('/broadcasting/auth', 'POST');
        $request->headers->set('Origin', 'http://127.0.0.1:5173');

        $response = $this->middleware->handle($request, $this->makeNext());

        $this->assertEquals(200, $response->getStatusCode());
        $this->assertTrue($response->headers->has('Access-Control-Allow-Origin'));
    }
}
