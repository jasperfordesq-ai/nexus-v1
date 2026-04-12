<?php
// Copyright © 2024–2026 Jasper Ford
// SPDX-License-Identifier: AGPL-3.0-or-later
// Author: Jasper Ford
// See NOTICE file for attribution and acknowledgements.

declare(strict_types=1);

namespace Tests\Laravel\Concerns;

use App\Services\FederationExternalApiClient;
use Illuminate\Support\Facades\Crypt;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Http;
use Illuminate\Testing\TestResponse;

/**
 * FederationIntegrationHarness — shared helpers for end-to-end federation
 * two-way flow tests.
 *
 * Provides:
 *  - setupPartner($protocol)                 → creates an active test partner with all allow_* flags ON
 *  - enableFederationForTenant($tenantId)    → seeds system_control + whitelist + tenant_features
 *  - fakePartnerHttp()                        → Http::fake() returning 200 for everything
 *  - assertOutboundPush($entity, $contains)  → asserts an Http::sent matched entity + payload shape
 *  - simulateInboundWebhook($partner, $evt, $data) → posts an HMAC-signed webhook
 *
 * Not a test case by itself — tests `use` this trait from Laravel\TestCase.
 */
trait FederationIntegrationHarness
{
    /** Plaintext signing secret used for outbound / inbound HMAC in tests. */
    protected string $testSigningSecret = 'test-federation-secret-1234567890';

    /**
     * Create a test partner with the given protocol_type and all allow_* flags ON.
     * Returns the partner row as a stdClass so callers can ->id, ->signing_secret etc.
     */
    protected function setupPartner(string $protocol = 'nexus', ?int $tenantId = null): object
    {
        $tenantId = $tenantId ?? $this->testTenantId;

        $encryptedSecret = Crypt::encryptString($this->testSigningSecret);

        $row = [
            'tenant_id'            => $tenantId,
            'name'                 => 'Test ' . ucfirst($protocol) . ' Partner ' . uniqid(),
            'description'          => 'Harness-created partner for ' . $protocol,
            'base_url'             => 'https://partner-' . $protocol . '-' . uniqid() . '.test',
            'api_path'             => '/api/v1',
            'api_key'              => 'test-api-key',
            'auth_method'          => 'hmac',
            'signing_secret'       => $encryptedSecret,
            'status'               => 'active',
            'verified_at'          => now(),
            'allow_member_search'  => 1,
            'allow_listing_search' => 1,
            'allow_messaging'      => 1,
            'allow_transactions'   => 1,
            'allow_events'         => 1,
            'allow_groups'         => 1,
            'created_at'           => now(),
            'updated_at'           => now(),
        ];

        // New allow_* columns (migration 2026_04_12_110000).
        foreach (['allow_connections', 'allow_volunteering', 'allow_member_sync'] as $col) {
            if ($this->columnExists('federation_external_partners', $col)) {
                $row[$col] = 1;
            }
        }

        // allow_reviews — may or may not exist; include if column is present.
        if ($this->columnExists('federation_external_partners', 'allow_reviews')) {
            $row['allow_reviews'] = 1;
        }

        // protocol_type — added by migration 2026_04_11_200000
        if ($this->columnExists('federation_external_partners', 'protocol_type')) {
            $row['protocol_type'] = $protocol;
        }

        $id = DB::table('federation_external_partners')->insertGetId($row);

        // Bust adapter cache so this partner resolves with its fresh protocol_type.
        FederationExternalApiClient::clearAdapterCache();

        $partner = DB::table('federation_external_partners')->where('id', $id)->first();
        if (!$partner) {
            $this->fail("Harness: failed to persist test partner for protocol '{$protocol}'");
        }

        return $partner;
    }

    /**
     * Seed the 3 federation gating tables so the tenant is fully enabled.
     */
    protected function enableFederationForTenant(?int $tenantId = null): void
    {
        $tenantId = $tenantId ?? $this->testTenantId;

        // 1. System-wide control row
        DB::table('federation_system_control')->updateOrInsert(
            ['id' => 1],
            [
                'federation_enabled'                => 1,
                'whitelist_mode_enabled'            => 1,
                'cross_tenant_profiles_enabled'     => 1,
                'cross_tenant_messaging_enabled'    => 1,
                'cross_tenant_transactions_enabled' => 1,
                'cross_tenant_listings_enabled'     => 1,
                'cross_tenant_events_enabled'       => 1,
                'cross_tenant_groups_enabled'       => 1,
                'emergency_lockdown_active'         => 0,
                'updated_at'                        => now(),
            ]
        );

        // 2. Whitelist the tenant
        DB::table('federation_tenant_whitelist')->updateOrInsert(
            ['tenant_id' => $tenantId],
            [
                'approved_at' => now(),
                'approved_by' => 1,
            ]
        );

        // 3. Feature flags — enable both federation + everything downstream features use
        foreach ([
            'tenant_federation_enabled',
            'federation',
        ] as $feature) {
            DB::table('federation_tenant_features')->updateOrInsert(
                ['tenant_id' => $tenantId, 'feature_key' => $feature],
                [
                    'is_enabled' => 1,
                    'updated_at' => now(),
                ]
            );
        }

        // Ensure tenant-level feature gate `TenantContext::hasFeature('federation')`
        // resolves truthy. We stamp it into tenants.features JSON as belt-and-braces.
        try {
            $tenant = DB::table('tenants')->where('id', $tenantId)->first();
            if ($tenant && $this->columnExists('tenants', 'features')) {
                $features = json_decode($tenant->features ?? '{}', true) ?: [];
                $features['federation'] = true;
                DB::table('tenants')->where('id', $tenantId)->update([
                    'features'   => json_encode($features),
                    'updated_at' => now(),
                ]);
            }
        } catch (\Throwable $e) {
            // Non-fatal: some test schemas may not have this column.
        }
    }

    /**
     * Http::fake() returning 200 + a minimal success envelope for any outbound URL.
     */
    protected function fakePartnerHttp(): void
    {
        Http::fake(function ($request) {
            return Http::response([
                'success' => true,
                'data'    => ['id' => 99, 'status' => 'accepted'],
            ], 200);
        });
    }

    /**
     * Assert that at least one outbound HTTP request matched the given
     * entity endpoint fragment and contains the expected payload fields.
     *
     * @param string $entityEndpointFragment E.g. '/listings', '/reviews', '/members'
     * @param array<string,mixed> $expectedPayloadContains
     */
    protected function assertOutboundPush(string $entityEndpointFragment, array $expectedPayloadContains = []): void
    {
        Http::assertSent(function ($request) use ($entityEndpointFragment, $expectedPayloadContains) {
            if (!str_contains($request->url(), $entityEndpointFragment)) {
                return false;
            }
            $body = $request->data();
            foreach ($expectedPayloadContains as $key => $value) {
                // Support dot-path `attributes.title`
                $actual = data_get($body, $key);
                if ($value !== null && $actual !== $value) {
                    return false;
                }
                if ($value === null && $actual === null && !data_get($body, $key, '___missing___') !== '___missing___') {
                    return false;
                }
            }
            return true;
        });
    }

    /**
     * Simulate an inbound webhook from the given partner using HMAC auth.
     * Signs the body with the partner's plaintext secret (decrypted from DB).
     *
     * @param array<string,mixed> $data
     */
    protected function simulateInboundWebhook(object $partner, string $eventType, array $data = []): TestResponse
    {
        $payload = [
            'event'      => $eventType,
            'partner_id' => $partner->id,
            'timestamp'  => (string) time(),
            'data'       => $data,
        ];
        $body = json_encode($payload);
        $signature = hash_hmac('sha256', $body, $this->testSigningSecret);

        return $this->postJson(
            '/api/v2/federation/external/webhooks/receive',
            $payload,
            [
                'X-Webhook-Signature' => $signature,
                'X-Webhook-Timestamp' => (string) time(),
                'Content-Type'        => 'application/json',
                'Accept'              => 'application/json',
            ]
        );
    }

    /**
     * True if the column exists on the given table. Silent on failure.
     */
    protected function columnExists(string $table, string $column): bool
    {
        try {
            return \Illuminate\Support\Facades\Schema::hasColumn($table, $column);
        } catch (\Throwable $e) {
            return false;
        }
    }
}
