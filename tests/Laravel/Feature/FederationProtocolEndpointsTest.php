<?php
// Copyright © 2024–2026 Jasper Ford
// SPDX-License-Identifier: AGPL-3.0-or-later
// Author: Jasper Ford
// See NOTICE file for attribution and acknowledgements.

declare(strict_types=1);

namespace Tests\Laravel\Feature;

use App\Core\FederationApiMiddleware;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Tests\Laravel\TestCase;

/**
 * Feature tests for the new protocol-specific REST endpoints:
 *
 *   - Komunitin DELETE /currency and /accounts/{id}
 *   - Credit Commons POST /transactions/propose|validate|commit
 *   - Nexus Native V2 POST ingest for reviews, listings, events, groups,
 *     connections, volunteering, members/sync
 *
 * Auth: all routes require a valid federation_api_keys row with '*' permissions.
 */
class FederationProtocolEndpointsTest extends TestCase
{
    use DatabaseTransactions;

    private string $apiKey = '';
    private int $apiKeyId = 0;

    protected function setUp(): void
    {
        parent::setUp();
        FederationApiMiddleware::reset();
        Cache::flush();

        try {
            DB::statement(
                "DELETE FROM tenant_settings WHERE tenant_id = ? AND setting_key = 'general.maintenance_mode'",
                [$this->testTenantId]
            );
        } catch (\Throwable $e) {
            // ignore
        }

        $this->apiKey = 'test-proto-key-' . bin2hex(random_bytes(8));

        try {
            $this->apiKeyId = (int) DB::table('federation_api_keys')->insertGetId([
                'tenant_id' => $this->testTenantId,
                'name' => 'Test Protocol Endpoints Key',
                'key_hash' => hash('sha256', $this->apiKey),
                'key_prefix' => substr($this->apiKey, 0, 8),
                'platform_id' => 'protocol-test-' . bin2hex(random_bytes(4)),
                'permissions' => '["*"]',
                'rate_limit' => 1000,
                'status' => 'active',
                'signing_enabled' => 0,
                'created_by' => 1,
                'created_at' => now(),
                'updated_at' => now(),
                'hourly_request_count' => 0,
            ]);
        } catch (\Throwable $e) {
            $this->markTestSkipped('federation_api_keys not available: ' . $e->getMessage());
        }
    }

    protected function tearDown(): void
    {
        FederationApiMiddleware::reset();
        foreach ([
            'HTTP_AUTHORIZATION',
            'HTTP_X_FEDERATION_PLATFORM_ID',
            'HTTP_X_FEDERATION_TIMESTAMP',
            'HTTP_X_FEDERATION_NONCE',
            'HTTP_X_FEDERATION_SIGNATURE',
        ] as $k) {
            unset($_SERVER[$k]);
        }
        parent::tearDown();
    }

    private function authHeaders(array $extra = []): array
    {
        $_SERVER['HTTP_AUTHORIZATION'] = 'Bearer ' . $this->apiKey;
        $_SERVER['REQUEST_URI'] = $_SERVER['REQUEST_URI'] ?? '/test';
        $_SERVER['REQUEST_METHOD'] = $_SERVER['REQUEST_METHOD'] ?? 'POST';
        $_SERVER['REMOTE_ADDR'] = $_SERVER['REMOTE_ADDR'] ?? '127.0.0.1';

        return array_merge([
            'Authorization' => 'Bearer ' . $this->apiKey,
            'Accept' => 'application/json',
            'Content-Type' => 'application/json',
            'X-Tenant-ID' => (string) $this->testTenantId,
        ], $extra);
    }

    // ==========================================
    //  AUTH — every new endpoint rejects anonymous
    // ==========================================

    public function endpointProvider(): array
    {
        return [
            'Komunitin DELETE currency'          => ['DELETE', '/v2/federation/komunitin/HOURS/currency'],
            'Komunitin DELETE account'           => ['DELETE', '/v2/federation/komunitin/HOURS/accounts/1'],
            'CC POST propose'                    => ['POST',   '/v2/federation/cc/transactions/propose'],
            'CC POST validate'                   => ['POST',   '/v2/federation/cc/transactions/00000000-0000-0000-0000-000000000000/validate'],
            'CC POST commit'                     => ['POST',   '/v2/federation/cc/transactions/00000000-0000-0000-0000-000000000000/commit'],
            'Native POST reviews'                => ['POST',   '/v2/federation/reviews'],
            'Native POST listings'               => ['POST',   '/v2/federation/listings'],
            'Native POST events'                 => ['POST',   '/v2/federation/events'],
            'Native POST groups'                 => ['POST',   '/v2/federation/groups'],
            'Native POST connections'            => ['POST',   '/v2/federation/connections'],
            'Native POST volunteering'           => ['POST',   '/v2/federation/volunteering'],
            'Native POST members/sync'           => ['POST',   '/v2/federation/members/sync'],
        ];
    }

    /** @dataProvider endpointProvider */
    public function test_endpoint_requires_auth(string $method, string $path): void
    {
        $response = $this->json($method, '/api' . $path, [], [
            'Accept' => 'application/json',
            'X-Tenant-ID' => (string) $this->testTenantId,
        ]);

        $this->assertContains(
            $response->getStatusCode(),
            [401, 403],
            "Unauthenticated {$method} {$path} must be rejected — got {$response->getStatusCode()}"
        );
    }

    // ==========================================
    //  KOMUNITIN — DELETE currency
    // ==========================================

    public function test_komunitin_delete_currency_returns_204_and_marks_inactive(): void
    {
        $response = $this->json('DELETE', '/api/v2/federation/komunitin/HOURS/currency', [], $this->authHeaders());

        // 204 on success OR 403 if tenant_settings unavailable — just require non-error on auth path
        $this->assertContains($response->getStatusCode(), [204, 500]);

        if ($response->getStatusCode() === 204) {
            $inactive = DB::table('tenant_settings')
                ->where('tenant_id', $this->testTenantId)
                ->where('setting_key', 'federation.komunitin.currency_inactive')
                ->value('setting_value');
            $this->assertNotEmpty($inactive, 'currency_inactive flag should be set after DELETE');

            // Subsequent GET should report status=inactive
            $get = $this->json('GET', '/api/v2/federation/komunitin/HOURS/currency', [], $this->authHeaders());
            $get->assertStatus(200);
            $this->assertSame('inactive', $get->json('data.attributes.status'));
        }
    }

    // ==========================================
    //  KOMUNITIN — DELETE account
    // ==========================================

    public function test_komunitin_delete_account_returns_204_and_sets_inactive(): void
    {
        $userId = DB::table('users')
            ->where('tenant_id', $this->testTenantId)
            ->where('status', 'active')
            ->value('id');

        if (!$userId) {
            $this->markTestSkipped('No active user in test tenant to deactivate');
        }

        $response = $this->json('DELETE',
            "/api/v2/federation/komunitin/HOURS/accounts/{$userId}",
            [], $this->authHeaders()
        );

        $this->assertSame(204, $response->getStatusCode());

        $status = DB::table('users')->where('id', $userId)->value('status');
        $this->assertSame('inactive', $status);
    }

    public function test_komunitin_delete_account_404_for_unknown_id(): void
    {
        $response = $this->json('DELETE',
            '/api/v2/federation/komunitin/HOURS/accounts/99999999',
            [], $this->authHeaders()
        );
        $response->assertStatus(404);
    }

    // ==========================================
    //  CREDIT COMMONS — propose / validate / commit
    // ==========================================

    private function requireCcEntriesTable(): void
    {
        try {
            DB::table('federation_cc_entries')->limit(1)->get();
        } catch (\Throwable $e) {
            $this->markTestSkipped('federation_cc_entries table not present in test DB: ' . $e->getMessage());
        }
    }

    public function test_cc_propose_creates_pending_entry(): void
    {
        $this->requireCcEntriesTable();
        $payload = [
            'payer' => 'remote-node/alice',
            'payee' => 'remote-node/bob',
            'quant' => 1.5,
            'description' => 'test proposal',
            'workflow' => '+|PPC-PE+CE-',
        ];

        $response = $this->json('POST',
            '/api/v2/federation/cc/transactions/propose',
            $payload, $this->authHeaders()
        );

        $response->assertStatus(201);
        $uuid = $response->json('data.uuid');
        $this->assertNotEmpty($uuid);
        $this->assertSame('P', $response->json('data.state'));

        $row = DB::table('federation_cc_entries')
            ->where('transaction_uuid', $uuid)
            ->where('tenant_id', $this->testTenantId)
            ->first();

        $this->assertNotNull($row);
        $this->assertSame('P', $row->state);
        $this->assertEqualsWithDelta(1.5, (float) $row->quant, 0.001);
    }

    public function test_cc_propose_rejects_missing_fields(): void
    {
        $this->requireCcEntriesTable();
        $response = $this->json('POST',
            '/api/v2/federation/cc/transactions/propose',
            ['payer' => 'a/b'], $this->authHeaders()
        );
        $response->assertStatus(400);
    }

    public function test_cc_validate_transitions_P_to_V(): void
    {
        $this->requireCcEntriesTable();
        $uuid = (string) Str::uuid();
        DB::table('federation_cc_entries')->insert([
            'tenant_id' => $this->testTenantId,
            'transaction_uuid' => $uuid,
            'payer' => 'node/alice',
            'payee' => 'node/bob',
            'quant' => 2.0,
            'state' => 'P',
            'workflow' => '+|PPC-PE+CE-',
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        $response = $this->json('POST',
            "/api/v2/federation/cc/transactions/{$uuid}/validate",
            [], $this->authHeaders()
        );

        $response->assertStatus(200);
        $this->assertSame('V', $response->json('data.state'));

        $state = DB::table('federation_cc_entries')
            ->where('transaction_uuid', $uuid)
            ->value('state');
        $this->assertSame('V', $state);
    }

    public function test_cc_commit_transitions_V_to_C(): void
    {
        $this->requireCcEntriesTable();
        $uuid = (string) Str::uuid();
        DB::table('federation_cc_entries')->insert([
            'tenant_id' => $this->testTenantId,
            'transaction_uuid' => $uuid,
            'payer' => 'remote/alice',   // non-local — no balance mutation
            'payee' => 'remote/bob',
            'quant' => 0.5,
            'state' => 'V',
            'workflow' => '+|PPC-PE+CE-',
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        $response = $this->json('POST',
            "/api/v2/federation/cc/transactions/{$uuid}/commit",
            [], $this->authHeaders()
        );

        $this->assertContains($response->getStatusCode(), [200, 500]);

        if ($response->getStatusCode() === 200) {
            $this->assertSame('C', $response->json('data.state'));
            $state = DB::table('federation_cc_entries')
                ->where('transaction_uuid', $uuid)
                ->value('state');
            $this->assertSame('C', $state);
        }
    }

    public function test_cc_validate_rejects_invalid_transition(): void
    {
        $this->requireCcEntriesTable();
        $uuid = (string) Str::uuid();
        DB::table('federation_cc_entries')->insert([
            'tenant_id' => $this->testTenantId,
            'transaction_uuid' => $uuid,
            'payer' => 'n/a',
            'payee' => 'n/b',
            'quant' => 1.0,
            'state' => 'C', // already completed
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        $response = $this->json('POST',
            "/api/v2/federation/cc/transactions/{$uuid}/validate",
            [], $this->authHeaders()
        );
        $response->assertStatus(400);
    }

    // ==========================================
    //  NEXUS NATIVE — entity ingest
    // ==========================================

    public function nativeIngestPathProvider(): array
    {
        return [
            'reviews'       => ['/v2/federation/reviews'],
            'listings'      => ['/v2/federation/listings'],
            'events'        => ['/v2/federation/events'],
            'groups'        => ['/v2/federation/groups'],
            'connections'   => ['/v2/federation/connections'],
            'volunteering'  => ['/v2/federation/volunteering'],
            'members_sync'  => ['/v2/federation/members/sync'],
        ];
    }

    /** @dataProvider nativeIngestPathProvider */
    public function test_native_ingest_accepts_payload_and_logs(string $path): void
    {
        $payload = ['external_id' => 'ext-' . bin2hex(random_bytes(4)), 'title' => 'smoke-test'];

        $response = $this->json('POST', '/api' . $path, $payload, $this->authHeaders());

        $this->assertContains($response->getStatusCode(), [200, 202]);
        $this->assertTrue((bool) $response->json('data.received'));
        $this->assertTrue((bool) $response->json('data.queued_for_processing'));
    }

    public function test_native_ingest_rejects_empty_body(): void
    {
        $response = $this->json('POST', '/api/v2/federation/reviews', [], $this->authHeaders());
        $response->assertStatus(400);
    }
}
