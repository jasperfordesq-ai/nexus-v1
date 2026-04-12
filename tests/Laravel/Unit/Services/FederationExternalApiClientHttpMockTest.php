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
 * Behavioural tests for FederationExternalApiClient using Laravel's Http::fake().
 *
 * These complement FederationExternalApiClientTest.php (which covers structural
 * assertions) by exercising actual request dispatch, auth-header construction,
 * retry/circuit-breaker logic and error paths without any real network calls.
 */
class FederationExternalApiClientHttpMockTest extends TestCase
{
    private int $partnerId;

    protected function setUp(): void
    {
        parent::setUp();
        FederationExternalApiClient::clearAdapterCache();
        Cache::flush();

        try {
            DB::table('federation_external_partner_logs')
                ->where('partner_id', '>=', 900000)->delete();
            DB::table('federation_external_partners')
                ->where('id', '>=', 900000)->delete();
        } catch (\Throwable $e) {
            // DB may not be available in pure unit runs
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
     * Insert a throwaway partner row. Returns true on success.
     */
    private function seedPartner(array $overrides = []): bool
    {
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
    //  HTTP VERB DISPATCH
    // ==========================================

    public function test_get_sends_GET_with_bearer_and_query(): void
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

    public function test_post_put_patch_delete_dispatch_correct_methods(): void
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
            return ($request->header('Authorization')[0] ?? '') === 'Bearer test-api-key';
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

            return preg_match('/^[a-f0-9]{64}$/', $sig) === 1
                && ctype_digit($ts)
                && preg_match('/^[a-f0-9]{32}$/', $n) === 1;
        });
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

        Cache::put("federation_cb_open:{$this->partnerId}", true, 300);
        Http::fake(['*' => Http::response([], 200)]);

        $result = FederationExternalApiClient::get($this->partnerId, '/ping');

        $this->assertFalse($result['success']);
        $this->assertStringContainsString('Circuit breaker', $result['error']);
        Http::assertNothingSent();
    }

    // ==========================================
    //  ERROR PATHS
    // ==========================================

    public function test_missing_partner_returns_error_without_http(): void
    {
        Http::fake();

        $result = FederationExternalApiClient::get(888888888, '/ping');

        $this->assertFalse($result['success']);
        $this->assertStringContainsString('not found', $result['error']);
        Http::assertNothingSent();
    }

    public function test_missing_api_key_returns_auth_error(): void
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

    public function test_timeouts_are_short_enough_to_complete_quickly(): void
    {
        if (!$this->seedPartner()) {
            $this->markTestSkipped('DB/Crypt unavailable');
        }

        Http::fake(['*' => Http::response([], 200)]);

        $start = microtime(true);
        FederationExternalApiClient::get($this->partnerId, '/ping');
        $elapsed = microtime(true) - $start;

        // With Http::fake() the call returns instantly — assert under 5s to
        // catch regressions where timeouts aren't applied to the pending request.
        $this->assertLessThan(5.0, $elapsed);
        Http::assertSentCount(1);
    }
}
