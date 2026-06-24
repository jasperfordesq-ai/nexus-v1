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
 * Tests for `federation:sync-partners` Artisan command.
 *
 * Uses unique tenant id 99721 for isolation.
 *
 * The command iterates all tenants that have active/pending external partners,
 * calls FederationExternalApiClient::healthCheck() then fetches /timebanks for
 * member-count / partner-name, and updates federation_external_partners rows.
 *
 * OutboundUrlGuard::isSafeHttpUrl() does real DNS resolution for hostnames but
 * short-circuits for IPv4 literals. We use IPs from 93.184.216.0/24 (example.com's
 * public /24) so the SSRF guard passes without live DNS. Http::fake() then
 * intercepts all actual outbound calls at the Laravel HTTP-client layer.
 *
 * The partner's api_key column is stored encrypted; we insert via Crypt::encryptString()
 * using a rebound encrypter keyed to the test APP_KEY so decryption does not throw.
 *
 * IMPORTANT: Http::fake() closures MUST NOT have a return-type annotation of
 * \Illuminate\Http\Client\Response because Http::response() actually returns a
 * GuzzleHttp\Promise\FulfilledPromise — a PHP TypeError would be thrown inside
 * the closure and silently swallowed by the command's catch(\Throwable) block,
 * making all health checks appear to fail.
 *
 * NOTE on pending partners: FederationExternalApiClient::getPartner() queries
 * WHERE status IN ('active','failed') — it deliberately excludes 'pending' so
 * that pending partners are not contacted until an admin approves them. The sync
 * command's outer SELECT includes 'pending' so that it can record an error and
 * set last_sync_at (informing admins the partner was attempted), but the inner
 * API call fails with "not found or inactive" and increments error_count.
 */
class SyncFederationPartnersTest extends TestCase
{
    use DatabaseTransactions;

    private const TENANT_ID = 99721;

    /** Shared valid encrypted API key inserted into federation_external_partners.api_key. */
    private string $encryptedKey;

    /**
     * Monotonic IP octet counter for unique base_url per partner row.
     * Uses IPs from 93.184.216.0/24 (example.com's public block) to
     * satisfy OutboundUrlGuard without live DNS resolution.
     */
    private static int $ipSeq = 1;

    protected function setUp(): void
    {
        parent::setUp();

        Queue::fake();

        // Rebind the encrypter to the known test APP_KEY so Crypt::encryptString()
        // and the decryptCredential() inside FederationExternalApiClient both use
        // the same key — the container OS APP_KEY may be wrong for AES-256-CBC.
        $this->app->instance(
            'encrypter',
            new \Illuminate\Encryption\Encrypter(
                base64_decode('HfQEDtbtr90JIXhsaAhSFWnzIo1f31VZ2e5qLqKKnls='),
                'AES-256-CBC'
            )
        );

        $this->encryptedKey = \Illuminate\Support\Facades\Crypt::encryptString('fake-api-key-for-test');

        // Clear adapter cache between tests to avoid cross-test contamination.
        \App\Services\FederationExternalApiClient::clearAdapterCache();

        DB::table('tenants')->insertOrIgnore([
            'id'         => self::TENANT_ID,
            'name'       => 'Sync Federation Test Tenant',
            'slug'       => 'sync-fed-test-' . self::TENANT_ID,
            'is_active'  => 1,
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        \App\Core\TenantContext::setById(self::TENANT_ID);
    }

    // ----------------------------------------------------------------
    // Helpers
    // ----------------------------------------------------------------

    /**
     * Return a distinct public-IP URL from the 93.184.216.x block.
     * OutboundUrlGuard resolves IPv4 literals directly without DNS.
     */
    private function freshIpUrl(): string
    {
        $octet = 1 + (self::$ipSeq++ % 253);
        return 'https://93.184.216.' . $octet;
    }

    /**
     * Insert a minimal federation_external_partners row for our test tenant.
     * Each call uses a fresh IP URL to avoid UNIQUE constraint violations.
     */
    private function insertPartner(array $overrides = []): int
    {
        $id = DB::table('federation_external_partners')->insertGetId(array_merge([
            'tenant_id'    => self::TENANT_ID,
            'name'         => 'Test Partner ' . uniqid(),
            'base_url'     => $this->freshIpUrl(),
            'api_path'     => '/api/v1/federation',
            'auth_method'  => 'api_key',
            'api_key'      => $this->encryptedKey,
            'protocol_type' => 'nexus',
            'status'       => 'active',
            'error_count'  => 0,
            'created_at'   => now(),
        ], $overrides));

        return (int) $id;
    }

    /**
     * Return the current DB row for a partner.
     */
    private function fetchPartner(int $id): object
    {
        return DB::table('federation_external_partners')
            ->where('id', $id)
            ->where('tenant_id', self::TENANT_ID)
            ->first();
    }

    /**
     * Register a catch-all Http::fake() that alternates between health-ok and
     * /timebanks responses.
     *
     * IMPORTANT: do NOT add a return-type annotation of \Illuminate\Http\Client\Response
     * on the closure. Http::response() returns a GuzzleHttp\Promise\FulfilledPromise
     * at the Laravel-framework level; annotating the closure with the wrong type causes
     * PHP to throw a TypeError which the command silently swallows via catch(\Throwable).
     */
    private function fakeHealthOk(int $memberCount = 42, string $partnerName = 'Remote Bank'): void
    {
        $responses = [
            // health check
            ['success' => true, 'data' => ['status' => 'ok']],
            // /timebanks
            ['success' => true, 'data' => [['name' => $partnerName, 'member_count' => $memberCount]]],
        ];

        $idx = 0;
        // No return type on the closure — Http::response() returns a FulfilledPromise,
        // not \Illuminate\Http\Client\Response. Annotating incorrectly causes TypeError.
        Http::fake(function () use ($responses, &$idx) {
            $body = $responses[$idx % count($responses)];
            $idx++;
            return Http::response($body, 200);
        });
    }

    // ----------------------------------------------------------------
    // Tests
    // ----------------------------------------------------------------

    public function test_exits_success_with_no_partners(): void
    {
        // No partners for this tenant — command should finish cleanly.
        Http::fake();

        $this->artisan('federation:sync-partners', ['--tenant' => self::TENANT_ID])
            ->assertExitCode(0);

        Http::assertNothingSent();
    }

    public function test_healthy_partner_is_updated_with_member_count_and_name(): void
    {
        $partnerId = $this->insertPartner(['status' => 'active']);

        $this->fakeHealthOk(77, 'Healthy Community');

        $this->artisan('federation:sync-partners', ['--tenant' => self::TENANT_ID])
            ->assertExitCode(0);

        $row = $this->fetchPartner($partnerId);
        $this->assertSame(77, (int) $row->partner_member_count);
        $this->assertSame('Healthy Community', $row->partner_name);
        $this->assertSame('active', $row->status);
        $this->assertSame(0, (int) $row->error_count);
        $this->assertNotNull($row->last_sync_at);
        $this->assertNull($row->last_error);
    }

    public function test_pending_partner_is_found_but_api_client_rejects_it(): void
    {
        // FederationExternalApiClient::getPartner() queries WHERE status IN ('active','failed')
        // so it intentionally rejects 'pending' partners. The command outer SELECT includes
        // 'pending', but the inner getPartner() call returns null → health check fails with
        // "not found or inactive" → error_count incremented, last_sync_at stamped.
        $partnerId = $this->insertPartner(['status' => 'pending']);

        // Provide a fake in case Http is called, but expect it won't be (getPartner returns null).
        Http::fake(function () {
            return Http::response(['success' => true, 'data' => ['status' => 'ok']], 200);
        });

        $this->artisan('federation:sync-partners', ['--tenant' => self::TENANT_ID])
            ->assertExitCode(0);

        $row = $this->fetchPartner($partnerId);
        // Status remains 'pending' — the sync command cannot activate a pending partner.
        $this->assertSame('pending', $row->status);
        // last_sync_at is stamped (command records the attempt).
        $this->assertNotNull($row->last_sync_at, 'last_sync_at must be set even when pending partner is rejected by API client');
    }

    public function test_failed_health_check_increments_error_count(): void
    {
        $partnerId = $this->insertPartner(['status' => 'active', 'error_count' => 0]);

        // Health endpoint returns 503 — non-2xx causes the client to report failure.
        // No return type annotation on closure to avoid TypeError (Http::response() returns a PromiseInterface).
        Http::fake(function () {
            return Http::response(['error' => 'Service unavailable'], 503);
        });

        $this->artisan('federation:sync-partners', ['--tenant' => self::TENANT_ID])
            ->assertExitCode(0);

        $row = $this->fetchPartner($partnerId);
        // error_count incremented and last_sync_at stamped.
        $this->assertGreaterThan(0, (int) $row->error_count);
        $this->assertNotNull($row->last_sync_at);
    }

    public function test_tenant_filter_option_ignores_other_tenants(): void
    {
        // Insert a partner for a different tenant — must NOT be touched.
        $otherId = 99721 + 1000;

        DB::table('tenants')->insertOrIgnore([
            'id'         => $otherId,
            'name'       => 'Other Tenant',
            'slug'       => 'other-tenant-' . $otherId,
            'is_active'  => 1,
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        DB::table('federation_external_partners')->insert([
            'tenant_id'    => $otherId,
            'name'         => 'Other Tenant Partner',
            'base_url'     => $this->freshIpUrl(),
            'api_path'     => '/api/v1/federation',
            'auth_method'  => 'api_key',
            'api_key'      => $this->encryptedKey,
            'protocol_type' => 'nexus',
            'status'       => 'active',
            'error_count'  => 0,
            'created_at'   => now(),
        ]);

        Http::fake();

        // Run with --tenant filter on OUR tenant (which has no partners).
        $this->artisan('federation:sync-partners', ['--tenant' => self::TENANT_ID])
            ->assertExitCode(0);

        // Our tenant has no partners → nothing should have been sent.
        Http::assertNothingSent();
    }

    public function test_zero_member_count_when_timebanks_returns_empty_data(): void
    {
        $partnerId = $this->insertPartner();

        // First call → health ok; second call → empty /timebanks data.
        // No return type annotation to avoid TypeError with FulfilledPromise.
        $responses = [
            ['success' => true, 'data' => ['status' => 'ok']],
            ['success' => true, 'data' => []],
        ];
        $idx = 0;
        Http::fake(function () use ($responses, &$idx) {
            $body = $responses[$idx % count($responses)];
            $idx++;
            return Http::response($body, 200);
        });

        $this->artisan('federation:sync-partners', ['--tenant' => self::TENANT_ID])
            ->assertExitCode(0);

        $row = $this->fetchPartner($partnerId);
        $this->assertSame(0, (int) $row->partner_member_count);
        $this->assertSame('active', $row->status);
    }

    public function test_command_runs_without_tenant_option(): void
    {
        $partnerId = $this->insertPartner(['status' => 'active']);

        $this->fakeHealthOk(5, 'Global Test');

        // Run WITHOUT --tenant — command must process all tenants including ours.
        $this->artisan('federation:sync-partners')
            ->assertExitCode(0);

        $row = $this->fetchPartner($partnerId);
        // last_sync_at must be stamped, confirming the row was processed.
        $this->assertNotNull($row->last_sync_at);
    }

    public function test_error_count_resets_on_successful_sync(): void
    {
        $partnerId = $this->insertPartner([
            'status'      => 'active',
            'error_count' => 3,
            'last_error'  => 'Previous error',
        ]);

        $this->fakeHealthOk(20, 'Recovered Bank');

        $this->artisan('federation:sync-partners', ['--tenant' => self::TENANT_ID])
            ->assertExitCode(0);

        $row = $this->fetchPartner($partnerId);
        $this->assertSame(0, (int) $row->error_count);
        $this->assertNull($row->last_error);
    }

    public function test_http_requests_are_sent_for_active_partner(): void
    {
        $this->insertPartner(['status' => 'active']);

        $this->fakeHealthOk(1, 'Any Bank');

        $this->artisan('federation:sync-partners', ['--tenant' => self::TENANT_ID])
            ->assertExitCode(0);

        // At least one outbound request must have been sent (the health check).
        Http::assertSent(function ($request): bool {
            return str_contains($request->url(), '93.184.216.');
        });
    }

    public function test_suspended_partner_is_not_synced(): void
    {
        // 'suspended' is not in IN('active','pending') — should be invisible to the command.
        $partnerId = $this->insertPartner(['status' => 'suspended']);

        Http::fake();

        $this->artisan('federation:sync-partners', ['--tenant' => self::TENANT_ID])
            ->assertExitCode(0);

        Http::assertNothingSent();

        // Row must remain untouched.
        $row = $this->fetchPartner($partnerId);
        $this->assertSame('suspended', $row->status);
        $this->assertNull($row->last_sync_at);
    }

    public function test_multiple_active_partners_both_touched(): void
    {
        $p1 = $this->insertPartner(['name' => 'Partner Alpha', 'status' => 'active']);
        $p2 = $this->insertPartner(['name' => 'Partner Beta',  'status' => 'active']);

        // Serve alternating health/timebanks responses for both partners.
        // No return type annotation on closure to avoid TypeError.
        $responses = [
            ['success' => true, 'data' => ['status' => 'ok']],
            ['success' => true, 'data' => [['name' => 'Alpha', 'member_count' => 11]]],
            ['success' => true, 'data' => ['status' => 'ok']],
            ['success' => true, 'data' => [['name' => 'Beta',  'member_count' => 22]]],
        ];
        $idx = 0;
        Http::fake(function () use ($responses, &$idx) {
            $body = $responses[$idx % count($responses)];
            $idx++;
            return Http::response($body, 200);
        });

        $this->artisan('federation:sync-partners', ['--tenant' => self::TENANT_ID])
            ->assertExitCode(0);

        $r1 = $this->fetchPartner($p1);
        $r2 = $this->fetchPartner($p2);

        // Both partners must have been synced (last_sync_at set).
        $this->assertNotNull($r1->last_sync_at, 'Partner Alpha must be synced');
        $this->assertNotNull($r2->last_sync_at, 'Partner Beta must be synced');
    }
}
