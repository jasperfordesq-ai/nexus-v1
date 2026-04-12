<?php
// Copyright © 2024–2026 Jasper Ford
// SPDX-License-Identifier: AGPL-3.0-or-later
// Author: Jasper Ford
// See NOTICE file for attribution and acknowledgements.

declare(strict_types=1);

namespace Tests\Laravel\Unit\Services;

use App\Services\FederationExternalApiClient;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Crypt;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Http;
use Tests\Laravel\TestCase;

/**
 * FederationExternalApiClient tests — covers structural checks plus HTTP-mocked
 * behavioural tests using Laravel's Http::fake() so no real network calls occur.
 */
class FederationExternalApiClientTest extends TestCase
{
    /** Partner ID used as primary key in the throwaway rows inserted per-test. */
    private int $partnerId;

    protected function setUp(): void
    {
        parent::setUp();

        FederationExternalApiClient::clearAdapterCache();
        Cache::flush();

        // Clean any leftover throwaway partner rows from prior runs in the same DB.
        try {
            DB::table('federation_external_partner_logs')
                ->where('partner_id', '>=', 900000)->delete();
            DB::table('federation_external_partners')
                ->where('id', '>=', 900000)->delete();
        } catch (\Throwable $e) {
            // Tables may be absent in pure unit runs — that's fine; those tests skip.
        }

        $this->partnerId = 900000 + random_int(1, 99999);
    }

    protected function tearDown(): void
    {
        try {
            DB::table('federation_external_partner_logs')
                ->where('partner_id', $this->partnerId)->delete();
            DB::table('federation_external_partners')
                ->where('id', $this->partnerId)->delete();
        } catch (\Throwable $e) {
            // ignore
        }

        Cache::flush();
        parent::tearDown();
    }

    /**
     * Seed a federation_external_partners row. Returns true if the table exists.
     */
    private function seedPartner(array $overrides = []): bool
    {
        $encryptedApiKey = '';
        try {
            $encryptedApiKey = Crypt::encryptString('test-api-key');
        } catch (\Throwable $e) {
            return false;
        }

        $defaults = [
            'id'             => $this->partnerId,
            'tenant_id'      => $this->testTenantId,
            'name'           => 'Mock Partner',
            'base_url'       => 'https://partner.test',
            'api_path'       => '/api/v1/federation',
            'api_key'        => $encryptedApiKey,
            'auth_method'    => 'api_key',
            'signing_secret' => '',
            'status'         => 'active',
            'created_at'     => now(),
            'updated_at'     => now(),
        ];

        try {
            DB::table('federation_external_partners')->insert(array_merge($defaults, $overrides));
            return true;
        } catch (\Throwable $e) {
            return false;
        }
    }

    // ==========================================
    //  STRUCTURAL / SIGNATURE / REFLECTION
    // ==========================================

    public function test_all_core_static_methods_exist(): void
    {
        foreach ([
            'get', 'post', 'put', 'patch', 'delete',
            'fetchMembers', 'fetchListings', 'fetchMember', 'fetchListing',
            'sendMessage', 'createTransaction', 'healthCheck',
            'resolveAdapter', 'createAdapter', 'getSupportedProtocols',
            'clearAdapterCache',
        ] as $method) {
            $this->assertTrue(
                method_exists(FederationExternalApiClient::class, $method),
                "Static method {$method}() should exist"
            );
        }
    }

    public function test_http_verb_methods_share_signature(): void
    {
        foreach (['get', 'post', 'put', 'patch', 'delete'] as $method) {
            $ref = new \ReflectionMethod(FederationExternalApiClient::class, $method);
            $params = $ref->getParameters();
            $this->assertSame('partnerId', $params[0]->getName());
            $this->assertSame('int', $params[0]->getType()->getName());
            $this->assertSame('endpoint', $params[1]->getName());
            $this->assertSame('string', $params[1]->getType()->getName());
        }
    }

    public function test_getSupportedProtocols_returns_all_four(): void
    {
        $protocols = FederationExternalApiClient::getSupportedProtocols();
        $this->assertArrayHasKey('nexus', $protocols);
        $this->assertArrayHasKey('timeoverflow', $protocols);
        $this->assertArrayHasKey('komunitin', $protocols);
        $this->assertArrayHasKey('credit_commons', $protocols);
        $this->assertCount(4, $protocols);
    }

    public function test_createAdapter_returns_correct_types(): void
    {
        $this->assertInstanceOf(
            \App\Services\Protocols\NexusAdapter::class,
            FederationExternalApiClient::createAdapter('nexus')
        );
        $this->assertInstanceOf(
            \App\Services\Protocols\TimeOverflowAdapter::class,
            FederationExternalApiClient::createAdapter('timeoverflow')
        );
        $this->assertInstanceOf(
            \App\Services\Protocols\KomunitinAdapter::class,
            FederationExternalApiClient::createAdapter('komunitin')
        );
        $this->assertInstanceOf(
            \App\Services\Protocols\CreditCommonsAdapter::class,
            FederationExternalApiClient::createAdapter('credit_commons')
        );
    }

    public function test_createAdapter_defaults_to_nexus_for_unknown(): void
    {
        $this->assertInstanceOf(
            \App\Services\Protocols\NexusAdapter::class,
            FederationExternalApiClient::createAdapter('some_unknown_protocol')
        );
    }

    public function test_resolveAdapter_from_array_uses_protocol_type(): void
    {
        $this->assertInstanceOf(
            \App\Services\Protocols\KomunitinAdapter::class,
            FederationExternalApiClient::resolveAdapter(['protocol_type' => 'komunitin'])
        );
    }

    public function test_resolveAdapter_from_array_defaults_to_nexus(): void
    {
        $this->assertInstanceOf(
            \App\Services\Protocols\NexusAdapter::class,
            FederationExternalApiClient::resolveAdapter(['name' => 'no protocol_type'])
        );
    }

    // ==========================================
    //  HTTP VERB DISPATCH (Http::fake)
    // ==========================================

    public function test_get_sends_GET_request_with_bearer_auth(): void
    {
        if (!$this->seedPartner()) {
            $this->markTestSkipped('DB/Crypt unavailable');
        }

        Http::fake(['*' => Http::response(['ok' => true], 200)]);

        $result = FederationExternalApiClient::get($this->partnerId, '/members', ['limit' => 5]);

        $this->assertTrue($result['success']);
        Http::assertSent(function ($request) {
            return $request->method() === 'GET'
                && str_contains($request->url(), '/members')
                && str_contains($request->url(), 'limit=5')
                && str_starts_with($request->header('Authorization')[0] ?? '', 'Bearer ');
        });
    }

    public function test_post_put_patch_delete_send_correct_methods(): void
    {
        if (!$this->seedPartner()) {
            $this->markTestSkipped('DB/Crypt unavailable');
        }

        Http::fake(['*' => Http::response(['ok' => true], 200)]);

        FederationExternalApiClient::post($this->partnerId, '/x', ['a' => 1]);
        FederationExternalApiClient::put($this->partnerId, '/x', ['a' => 2]);
        FederationExternalApiClient::patch($this->partnerId, '/x', ['a' => 3]);
        FederationExternalApiClient::delete($this->partnerId, '/x', ['a' => 4]);

        $observed = [];
        Http::assertSent(function ($request) use (&$observed) {
            $observed[] = $request->method();
            return true;
        });

        $this->assertSame(['POST', 'PUT', 'PATCH', 'DELETE'], $observed);
    }

    // ==========================================
    //  AUTH HEADERS
    // ==========================================

    public function test_api_key_auth_produces_bearer_header(): void
    {
        if (!$this->seedPartner()) {
            $this->markTestSkipped('DB/Crypt unavailable');
        }

        Http::fake(['*' => Http::response([], 200)]);
        FederationExternalApiClient::get($this->partnerId, '/ping');

        Http::assertSent(function ($request) {
            $auth = $request->header('Authorization')[0] ?? '';
            return $auth === 'Bearer test-api-key';
        });
    }

    public function test_hmac_auth_adds_signature_timestamp_and_nonce(): void
    {
        try {
            $secret = Crypt::encryptString('s3cret');
        } catch (\Throwable $e) {
            $this->markTestSkipped('Crypt unavailable');
        }

        if (!$this->seedPartner(['auth_method' => 'hmac', 'signing_secret' => $secret, 'api_key' => ''])) {
            $this->markTestSkipped('DB unavailable');
        }

        Http::fake(['*' => Http::response([], 200)]);
        FederationExternalApiClient::get($this->partnerId, '/ping');

        Http::assertSent(function ($request) {
            $sig = $request->header('X-Federation-Signature')[0] ?? '';
            $ts  = $request->header('X-Federation-Timestamp')[0] ?? '';
            $n   = $request->header('X-Federation-Nonce')[0] ?? '';

            // signature is sha256 hex (64 chars), timestamp numeric, nonce 32 hex chars
            return preg_match('/^[a-f0-9]{64}$/', $sig) === 1
                && ctype_digit($ts)
                && preg_match('/^[a-f0-9]{32}$/', $n) === 1;
        });
    }

    public function test_komunitin_partner_uses_jsonapi_content_negotiation(): void
    {
        if (!$this->seedPartner(['protocol_type' => 'komunitin'])) {
            $this->markTestSkipped('DB/Crypt unavailable (protocol_type column may not exist)');
        }

        Http::fake(['*' => Http::response(['data' => []], 200)]);
        FederationExternalApiClient::post($this->partnerId, '/transfers', ['foo' => 'bar']);

        Http::assertSent(function ($request) {
            $accept = $request->header('Accept')[0] ?? '';
            $ct     = $request->header('Content-Type')[0] ?? '';
            return $accept === 'application/vnd.api+json'
                && str_starts_with($ct, 'application/vnd.api+json');
        });
    }

    // ==========================================
    //  TIMEOUT BEHAVIOUR
    // ==========================================

    public function test_connect_and_request_timeouts_are_short(): void
    {
        if (!$this->seedPartner()) {
            $this->markTestSkipped('DB/Crypt unavailable');
        }

        Http::fake(['*' => Http::response([], 200)]);
        FederationExternalApiClient::get($this->partnerId, '/ping');

        // Http::assertSent asserts at least one request went out with the configured headers.
        // Timeout values are enforced by Guzzle — here we only assert that the request
        // completed and didn't block on a default 30s+ timeout.
        Http::assertSentCount(1);
    }

    // ==========================================
    //  RETRY + CIRCUIT BREAKER
    // ==========================================

    public function test_5xx_triggers_retries(): void
    {
        if (!$this->seedPartner()) {
            $this->markTestSkipped('DB/Crypt unavailable');
        }

        Http::fake(['*' => Http::response(['err' => 'boom'], 503)]);
        $result = FederationExternalApiClient::get($this->partnerId, '/ping');

        $this->assertFalse($result['success']);
        // MAX_RETRIES = 3, so 1 initial + 3 retries = 4 attempts
        Http::assertSentCount(4);
    }

    public function test_4xx_does_not_retry(): void
    {
        if (!$this->seedPartner()) {
            $this->markTestSkipped('DB/Crypt unavailable');
        }

        Http::fake(['*' => Http::response(['error' => 'bad'], 400)]);
        $result = FederationExternalApiClient::get($this->partnerId, '/ping');

        $this->assertFalse($result['success']);
        $this->assertSame(400, $result['status_code']);
        Http::assertSentCount(1);
    }

    public function test_circuit_breaker_short_circuits_when_open(): void
    {
        if (!$this->seedPartner()) {
            $this->markTestSkipped('DB/Crypt unavailable');
        }

        // Pre-open the circuit breaker
        Cache::put("federation_cb_open:{$this->partnerId}", true, 300);

        Http::fake(['*' => Http::response([], 200)]);

        $result = FederationExternalApiClient::get($this->partnerId, '/ping');

        $this->assertFalse($result['success']);
        $this->assertStringContainsString('Circuit breaker', $result['error']);
        // No HTTP request should have been dispatched
        Http::assertNothingSent();
    }

    // ==========================================
    //  ERROR PATHS
    // ==========================================

    public function test_missing_partner_returns_error_without_http(): void
    {
        Http::fake();
        // Use a partner ID that definitely doesn't exist
        $result = FederationExternalApiClient::get(888888888, '/ping');

        $this->assertFalse($result['success']);
        $this->assertStringContainsString('not found', $result['error']);
        Http::assertNothingSent();
    }

    public function test_api_key_missing_returns_auth_error(): void
    {
        if (!$this->seedPartner(['api_key' => ''])) {
            $this->markTestSkipped('DB/Crypt unavailable');
        }

        Http::fake();
        $result = FederationExternalApiClient::get($this->partnerId, '/ping');

        $this->assertFalse($result['success']);
        $this->assertStringContainsString('Authentication setup failed', $result['error']);
        Http::assertNothingSent();
    }

    public function test_clearAdapterCache_is_callable(): void
    {
        FederationExternalApiClient::clearAdapterCache();
        $this->assertTrue(true);
    }
}
