<?php
// Copyright © 2024–2026 Jasper Ford
// SPDX-License-Identifier: AGPL-3.0-or-later
// Author: Jasper Ford
// See NOTICE file for attribution and acknowledgements.

declare(strict_types=1);

namespace Tests\Laravel\Unit\Console;

use Illuminate\Foundation\Testing\DatabaseTransactions;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Queue;
use Tests\Laravel\TestCase;

/**
 * Tests for federation:test-timeoverflow Artisan command.
 *
 * Uses unique tenant id 99724 for isolation.
 *
 * Strategy:
 *  - Missing --partner option → exits FAILURE immediately (no HTTP).
 *  - Partner not found in DB → exits FAILURE (no HTTP).
 *  - Valid partner + Http::fake() for all outbound calls → exercises the
 *    multi-test loop; asserts exit code and summary output.
 *  - TimeOverflowAdapter unit-conversion test (Test 7) runs entirely in-process
 *    with no HTTP; assert it passes when outbound tests all succeed.
 *
 * Http::fake() is used for ALL tests that touch outbound HTTP so no real
 * network call is ever made. The SSRF guard in OutboundUrlGuard calls
 * dns_get_record on the partner base_url hostname; to avoid DNS in CI
 * the partner is seeded with base_url = 'https://www.timeoverflow.net'
 * which is a well-known public domain resolvable in any environment with
 * working DNS (or bypassed entirely when Http::fake() short-circuits the
 * client before the guard runs).
 *
 * NOTE: validateBaseUrl() runs BEFORE Http::fake() intercepts, so a fake
 * hostname would fail the SSRF DNS check. We therefore use
 * 'https://www.timeoverflow.net' as the partner base URL. If CI has no DNS
 * the SSRF guard returns an error and the command exits FAILURE — which we
 * handle by asserting exit code 0 OR by catching the skip signal below.
 *
 * SSRF guard note: OutboundUrlGuard::isSafeHttpUrl() does a dns_get_record()
 * for hostnames.  Docker containers in CI have no external DNS, so a hostname
 * like 'www.timeoverflow.net' returns an empty result and the guard blocks the
 * URL.  Seeding the partner with a public IP literal (93.184.216.34 = example.com)
 * skips DNS — the guard calls isPublicIp() on the IP directly and passes.
 * Http::fake() then intercepts all outbound calls before any real TCP happens.
 *
 * Real partner columns (from federation_external_partners schema):
 *   id, tenant_id, name, base_url, api_path, api_key, auth_method,
 *   protocol_type, status, created_at
 */
class TestTimeOverflowFederationTest extends TestCase
{
    use DatabaseTransactions;

    private const TENANT_ID  = 99724;
    private const PARTNER_ID = 99724;

    /**
     * A public IP-based base URL for the partner fixture.
     *
     * OutboundUrlGuard::isSafeHttpUrl() short-circuits DNS lookup when the
     * host is already an IP literal — it calls filter_var(FILTER_VALIDATE_IP)
     * and then isPublicIp() directly.  93.184.216.34 is example.com's
     * authoritative IP, which is in the public routable range and therefore
     * passes FILTER_FLAG_NO_PRIV_RANGE | FILTER_FLAG_NO_RES_RANGE.
     * Http::fake() intercepts the request before any real TCP connection is made.
     */
    private const BASE_URL = 'https://93.184.216.34';

    protected function setUp(): void
    {
        parent::setUp();
        Queue::fake();

        // Clear the static adapter cache to avoid cross-test pollution.
        \App\Services\FederationExternalApiClient::clearAdapterCache();

        DB::table('tenants')->updateOrInsert(
            ['id' => self::TENANT_ID],
            [
                'name'              => 'TestTimeOverflow Tenant',
                'slug'              => 'test-timeoverflow-99724',
                'domain'            => null,
                'is_active'         => true,
                'depth'             => 0,
                'allows_subtenants' => false,
                'created_at'        => now(),
                'updated_at'        => now(),
            ]
        );

        \App\Core\TenantContext::setById(self::TENANT_ID);
    }

    // ---------------------------------------------------------------
    // Helpers
    // ---------------------------------------------------------------

    /**
     * Insert a federation_external_partners row scoped to our isolated
     * tenant.  The api_key is stored plaintext — decryptCredential() falls
     * back to the raw value when Crypt::decryptString() fails.
     */
    private function seedPartner(string $status = 'active'): void
    {
        DB::table('federation_external_partners')->updateOrInsert(
            ['id' => self::PARTNER_ID],
            [
                'tenant_id'     => self::TENANT_ID,
                'name'          => 'Test TimeOverflow Partner',
                'base_url'      => self::BASE_URL,
                'api_path'      => '/api/v1/federation',
                'api_key'       => 'test-api-key-99724',
                'auth_method'   => 'api_key',
                'protocol_type' => 'timeoverflow',
                'status'        => $status,
                'created_at'    => now(),
                'updated_at'    => now(),
            ]
        );
    }

    /**
     * Build a realistic Http::fake() response map that satisfies every
     * HTTP call the command makes in Tests 1–6.
     *
     * TimeOverflowAdapter maps:
     *   health     → GET /health  (or /status)
     *   orgs       → GET /organizations
     *   members    → GET /members
     *   listings   → GET /offers or /needs
     *
     * We use a wildcard '*' to keep the fake simple — any URL → 200.
     */
    private function fakeSuccessResponses(): void
    {
        Http::fake([
            // Health check
            '*health*'        => Http::response(['status' => 'ok', 'platform' => 'timeoverflow', 'version' => '1.0'], 200),
            // Organizations
            '*organizations*' => Http::response(['data' => [
                ['id' => 1, 'name' => 'Test Org', 'members_count' => 42, 'description' => null],
            ]], 200),
            // Members / search
            '*members*'       => Http::response(['data' => [
                ['id' => 10, 'username' => 'alice', 'name' => 'Alice', 'balance' => 3600, 'offers' => [], 'person' => ['id' => 10]],
            ]], 200),
            // Offers / listings
            '*offers*'        => Http::response(['data' => [
                ['id' => 5, 'title' => 'Test offer', 'description' => '', 'category' => ['name' => 'Education']],
            ]], 200),
            '*needs*'         => Http::response(['data' => []], 200),
            // Catch-all for any other endpoints
            '*'               => Http::response(['data' => []], 200),
        ]);
    }

    // ---------------------------------------------------------------
    // Missing --partner option → FAILURE without touching the DB
    // ---------------------------------------------------------------

    public function test_exits_failure_when_partner_option_missing(): void
    {
        $this->artisan('federation:test-timeoverflow')
            ->assertExitCode(1);
    }

    // ---------------------------------------------------------------
    // Partner ID given but no matching DB row → tests fail → FAILURE
    // ---------------------------------------------------------------

    public function test_exits_failure_when_partner_not_found_in_db(): void
    {
        // Do NOT seed a partner row. The command will attempt health check
        // which calls FederationExternalApiClient::healthCheck(99724). Since
        // getPartner() returns null the result is ['success' => false, ...].
        // The assertResult() helper inside the command throws, failing Test 1.
        // With 1+ failures the command exits FAILURE (exit code 1).
        Http::fake(['*' => Http::response(['data' => []], 200)]);

        $this->artisan('federation:test-timeoverflow', ['--partner' => (string) self::PARTNER_ID])
            ->assertExitCode(1);
    }

    // ---------------------------------------------------------------
    // All tests pass with valid partner + faked HTTP responses
    // ---------------------------------------------------------------

    public function test_exits_success_when_all_tests_pass(): void
    {
        $this->seedPartner();
        $this->fakeSuccessResponses();

        $this->artisan('federation:test-timeoverflow', [
            '--partner' => (string) self::PARTNER_ID,
            '--tenant'  => (string) self::TENANT_ID,
        ])->assertExitCode(0);
    }

    // ---------------------------------------------------------------
    // Summary output contains results line
    // ---------------------------------------------------------------

    public function test_output_contains_results_summary(): void
    {
        $this->seedPartner();
        $this->fakeSuccessResponses();

        $this->artisan('federation:test-timeoverflow', [
            '--partner' => (string) self::PARTNER_ID,
        ])
            ->expectsOutputToContain('Results:')
            ->assertExitCode(0);
    }

    // ---------------------------------------------------------------
    // Output contains the suite banner
    // ---------------------------------------------------------------

    public function test_output_contains_suite_banner(): void
    {
        $this->seedPartner();
        $this->fakeSuccessResponses();

        $this->artisan('federation:test-timeoverflow', [
            '--partner' => (string) self::PARTNER_ID,
        ])
            ->expectsOutputToContain('TimeOverflow Federation E2E Test Suite')
            ->assertExitCode(0);
    }

    // ---------------------------------------------------------------
    // Unit-conversion test (Test 7) is purely in-process → always runs
    // ---------------------------------------------------------------

    public function test_unit_conversion_assertions_are_valid(): void
    {
        // Verify the TimeOverflowAdapter math that the command exercises in
        // its built-in Test 7, without invoking the full command.
        $this->assertEqualsWithDelta(1.0, \App\Services\TimeOverflowAdapter::secondsToHours(3600), 0.0001);
        $this->assertEqualsWithDelta(1.5, \App\Services\TimeOverflowAdapter::secondsToHours(5400), 0.0001);
        $this->assertSame(3600, \App\Services\TimeOverflowAdapter::hoursToSeconds(1.0));
        $this->assertSame(9000, \App\Services\TimeOverflowAdapter::hoursToSeconds(2.5));
    }

    // ---------------------------------------------------------------
    // Inactive partner → getPartner() returns null → tests fail
    // ---------------------------------------------------------------

    public function test_exits_failure_when_partner_is_pending_status(): void
    {
        // getPartner() filters status IN ('active', 'failed'). A 'pending'
        // partner returns null → health check result['success'] = false → Test 1 fails.
        $this->seedPartner('pending');
        Http::fake(['*' => Http::response(['data' => []], 200)]);

        $this->artisan('federation:test-timeoverflow', [
            '--partner' => (string) self::PARTNER_ID,
        ])->assertExitCode(1);
    }

    // ---------------------------------------------------------------
    // --org option is accepted and forwarded (no extra failure)
    // ---------------------------------------------------------------

    public function test_accepts_org_option_without_error(): void
    {
        $this->seedPartner();
        $this->fakeSuccessResponses();

        $this->artisan('federation:test-timeoverflow', [
            '--partner' => (string) self::PARTNER_ID,
            '--org'     => '42',
        ])->assertExitCode(0);
    }

    // ---------------------------------------------------------------
    // HTTP 500 from peer → health check fails → FAILURE exit code
    // ---------------------------------------------------------------

    public function test_exits_failure_when_peer_returns_server_error(): void
    {
        $this->seedPartner();

        // All calls return 500 — health check result['success'] = false.
        Http::fake(['*' => Http::response(['message' => 'Internal Server Error'], 500)]);

        $this->artisan('federation:test-timeoverflow', [
            '--partner' => (string) self::PARTNER_ID,
        ])->assertExitCode(1);
    }

    // ---------------------------------------------------------------
    // TimeOverflowAdapter::transformOrganizations produces valid shape
    // ---------------------------------------------------------------

    public function test_transform_organizations_returns_expected_shape(): void
    {
        // transformOrganization() reads 'member_count' (not 'members_count')
        $raw = [
            ['id' => 7, 'name' => 'Coop A', 'member_count' => 10, 'description' => 'Desc'],
        ];

        $result = \App\Services\TimeOverflowAdapter::transformOrganizations($raw);

        $this->assertIsArray($result);
        $this->assertCount(1, $result);
        $this->assertArrayHasKey('name', $result[0]);
        $this->assertArrayHasKey('external_id', $result[0]);
        $this->assertSame('Coop A', $result[0]['name']);
        $this->assertSame(7, $result[0]['external_id']);
    }

    // ---------------------------------------------------------------
    // Command requires --partner (integer ≥ 1); zero-like value → FAILURE
    // ---------------------------------------------------------------

    public function test_exits_failure_when_partner_id_is_zero(): void
    {
        // The command casts --partner to int; (int)"0" === 0 → falsy → error.
        $this->artisan('federation:test-timeoverflow', ['--partner' => '0'])
            ->assertExitCode(1);
    }
}
