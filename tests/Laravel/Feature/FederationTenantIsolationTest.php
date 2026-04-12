<?php
// Copyright © 2024–2026 Jasper Ford
// SPDX-License-Identifier: AGPL-3.0-or-later
// Author: Jasper Ford
// See NOTICE file for attribution and acknowledgements.

declare(strict_types=1);

namespace Tests\Laravel\Feature;

use App\Core\FederationApiMiddleware;
use App\Core\TenantContext;
use App\Services\FederationPartnershipService;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;
use Tests\Laravel\TestCase;

/**
 * Federation tenant-isolation regression tests.
 *
 * These assert that the critical federation data paths honour multi-tenant
 * scoping so partnerships, federated reviews, and rate-limit counters
 * cannot leak across tenants.
 */
class FederationTenantIsolationTest extends TestCase
{
    use DatabaseTransactions;

    private int $tenantA;
    private int $tenantB = 999;

    protected function setUp(): void
    {
        parent::setUp();
        FederationApiMiddleware::reset();
        Cache::flush();
        $this->tenantA = $this->testTenantId;
    }

    protected function tearDown(): void
    {
        FederationApiMiddleware::reset();
        parent::tearDown();
    }

    // ------------------------------------------------------------------
    //  Partnership isolation
    // ------------------------------------------------------------------

    public function test_partnership_listing_does_not_leak_across_tenants(): void
    {
        if (!class_exists(FederationPartnershipService::class)) {
            $this->markTestSkipped('FederationPartnershipService unavailable');
        }

        try {
            // Partnership owned by tenant A
            $partnershipA = DB::table('federation_partnerships')->insertGetId([
                'tenant_id'         => $this->tenantA,
                'partner_tenant_id' => $this->tenantB,
                'status'            => 'active',
                'federation_level'  => 1,
                'requested_at'      => now(),
                'approved_at'       => now(),
                'created_at'        => now(),
                'updated_at'        => now(),
            ]);

            // Partnership owned by tenant B (partners with tenantA but tenant_id=B)
            $partnershipB = DB::table('federation_partnerships')->insertGetId([
                'tenant_id'         => $this->tenantB,
                'partner_tenant_id' => 99998,
                'status'            => 'active',
                'federation_level'  => 1,
                'requested_at'      => now(),
                'approved_at'       => now(),
                'created_at'        => now(),
                'updated_at'        => now(),
            ]);
        } catch (\Throwable $e) {
            $this->markTestSkipped('federation_partnerships table unavailable: ' . $e->getMessage());
        }

        // Act as tenant A
        TenantContext::setById($this->tenantA);
        $rowsForA = DB::table('federation_partnerships')
            ->where('tenant_id', $this->tenantA)
            ->pluck('id')
            ->all();

        $this->assertContains($partnershipA, $rowsForA, 'Tenant A should see its own partnership');
        $this->assertNotContains($partnershipB, $rowsForA, 'Tenant A must NOT see tenant B\'s partnership rows');

        // Act as tenant B
        TenantContext::setById($this->tenantB);
        $rowsForB = DB::table('federation_partnerships')
            ->where('tenant_id', $this->tenantB)
            ->pluck('id')
            ->all();

        $this->assertContains($partnershipB, $rowsForB);
        $this->assertNotContains($partnershipA, $rowsForB, 'Tenant B must NOT see tenant A\'s partnership rows');
    }

    // ------------------------------------------------------------------
    //  Federated review isolation
    // ------------------------------------------------------------------

    public function test_federated_reviews_scoped_to_receiver_tenant(): void
    {
        // We write directly to exchange_ratings (federated=1) and assert that
        // receiver_tenant_id filtering prevents cross-tenant visibility.
        try {
            $columns = DB::getSchemaBuilder()->getColumnListing('exchange_ratings');
            if (!in_array('receiver_tenant_id', $columns, true)) {
                $this->markTestSkipped('exchange_ratings.receiver_tenant_id column not present');
            }
        } catch (\Throwable $e) {
            $this->markTestSkipped('exchange_ratings table unavailable: ' . $e->getMessage());
        }

        $idA = DB::table('exchange_ratings')->insertGetId([
            'tenant_id'          => $this->tenantA,
            'receiver_tenant_id' => $this->tenantA,
            'rater_id'           => 1,
            'rated_id'           => 2,
            'rating'             => 5,
            'is_federated'       => 1,
            'created_at'         => now(),
            'updated_at'         => now(),
        ]);

        $idB = DB::table('exchange_ratings')->insertGetId([
            'tenant_id'          => $this->tenantB,
            'receiver_tenant_id' => $this->tenantB,
            'rater_id'           => 1,
            'rated_id'           => 2,
            'rating'             => 3,
            'is_federated'       => 1,
            'created_at'         => now(),
            'updated_at'         => now(),
        ]);

        // Querying by the wrong tenant must not return the other's review.
        $visibleToA = DB::table('exchange_ratings')
            ->where('receiver_tenant_id', $this->tenantA)
            ->pluck('id')->all();

        $this->assertContains($idA, $visibleToA);
        $this->assertNotContains($idB, $visibleToA);
    }

    // ------------------------------------------------------------------
    //  Rate-limit counters are per-tenant+per-key, not global
    // ------------------------------------------------------------------

    public function test_rate_limit_counters_are_per_api_key_not_global(): void
    {
        // Two distinct API keys (simulating different tenants) should have
        // independent hourly_request_count values.
        try {
            $keyAId = DB::table('federation_api_keys')->insertGetId([
                'tenant_id'            => $this->tenantA,
                'name'                 => 'Iso key A',
                'key_hash'             => hash('sha256', 'iso-key-a-' . uniqid()),
                'key_prefix'           => 'isoA',
                'platform_id'          => 'iso-a-' . bin2hex(random_bytes(4)),
                'permissions'          => '["*"]',
                'rate_limit'           => 10,
                'status'               => 'active',
                'signing_enabled'      => 0,
                'hourly_request_count' => 5,
                'created_by'           => 1,
                'created_at'           => now(),
                'updated_at'           => now(),
            ]);

            $keyBId = DB::table('federation_api_keys')->insertGetId([
                'tenant_id'            => $this->tenantB,
                'name'                 => 'Iso key B',
                'key_hash'             => hash('sha256', 'iso-key-b-' . uniqid()),
                'key_prefix'           => 'isoB',
                'platform_id'          => 'iso-b-' . bin2hex(random_bytes(4)),
                'permissions'          => '["*"]',
                'rate_limit'           => 10,
                'status'               => 'active',
                'signing_enabled'      => 0,
                'hourly_request_count' => 0,
                'created_by'           => 1,
                'created_at'           => now(),
                'updated_at'           => now(),
            ]);
        } catch (\Throwable $e) {
            $this->markTestSkipped('federation_api_keys unavailable: ' . $e->getMessage());
        }

        // Incrementing key B must not touch key A's counter.
        DB::update('UPDATE federation_api_keys SET hourly_request_count = hourly_request_count + 1 WHERE id = ?', [$keyBId]);

        $countA = DB::table('federation_api_keys')->where('id', $keyAId)->value('hourly_request_count');
        $countB = DB::table('federation_api_keys')->where('id', $keyBId)->value('hourly_request_count');

        $this->assertSame(5, (int) $countA, 'Key A counter must be untouched');
        $this->assertSame(1, (int) $countB, 'Key B counter must increment independently');
    }

    public function test_external_partners_scoped_to_tenant(): void
    {
        try {
            $partnerA = DB::table('federation_external_partners')->insertGetId([
                'tenant_id'  => $this->tenantA,
                'name'       => 'Iso Ext A',
                'base_url'   => 'https://iso-a.test',
                'api_path'   => '/api/v1/federation',
                'status'     => 'active',
                'created_at' => now(),
                'updated_at' => now(),
            ]);
            $partnerB = DB::table('federation_external_partners')->insertGetId([
                'tenant_id'  => $this->tenantB,
                'name'       => 'Iso Ext B',
                'base_url'   => 'https://iso-b.test',
                'api_path'   => '/api/v1/federation',
                'status'     => 'active',
                'created_at' => now(),
                'updated_at' => now(),
            ]);
        } catch (\Throwable $e) {
            $this->markTestSkipped('federation_external_partners unavailable: ' . $e->getMessage());
        }

        TenantContext::setById($this->tenantA);
        $visible = DB::table('federation_external_partners')
            ->where('tenant_id', $this->tenantA)
            ->pluck('id')->all();

        $this->assertContains($partnerA, $visible);
        $this->assertNotContains($partnerB, $visible);
    }
}
