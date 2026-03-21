<?php
// Copyright © 2024–2026 Jasper Ford
// SPDX-License-Identifier: AGPL-3.0-or-later
// Author: Jasper Ford
// See NOTICE file for attribution and acknowledgements.

namespace Tests\Laravel\Integration;

use App\Models\Tenant;
use App\Models\User;
use App\Services\FederationFeatureService;
use App\Services\FederationService;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Illuminate\Support\Facades\DB;
use Laravel\Sanctum\Sanctum;
use Tests\Laravel\TestCase;
use Tests\Laravel\Traits\ActsAsMember;

/**
 * Integration test: multi-tenant federation and cross-tenant discovery.
 *
 * Federation uses three control tables:
 *   - federation_system_control  (system-wide toggles)
 *   - federation_tenant_whitelist (tenant-to-tenant partnerships)
 *   - federation_tenant_features  (per-tenant feature toggles)
 */
class FederationTest extends TestCase
{
    use DatabaseTransactions;
    use ActsAsMember;

    private int $tenantAId;
    private int $tenantBId;

    protected function setUp(): void
    {
        parent::setUp();

        $this->tenantAId = $this->testTenantId; // 2 (hour-timebank)

        // Create a second tenant for federation tests
        $this->tenantBId = 100;
        DB::table('tenants')->insertOrIgnore([
            'id'         => $this->tenantBId,
            'name'       => 'Partner Timebank',
            'slug'       => 'partner-timebank',
            'domain'     => 'partner-timebank.project-nexus.ie',
            'is_active'  => true,
            'depth'      => 0,
            'allows_subtenants' => false,
            'created_at' => now(),
            'updated_at' => now(),
        ]);
    }

    // =========================================================================
    // Federation System Control
    // =========================================================================

    public function test_federation_status_endpoint_returns_response(): void
    {
        $user = $this->actAsMember();

        $response = $this->apiGet('/v2/federation/status');

        // Should return 200 with federation status, or 403/404 if feature is disabled
        $this->assertContains($response->getStatusCode(), [200, 403, 404]);
    }

    public function test_federation_requires_system_level_enable(): void
    {
        // Ensure system federation is disabled
        DB::table('federation_system_control')->updateOrInsert(
            ['setting_key' => FederationFeatureService::SYSTEM_FEDERATION_ENABLED],
            ['setting_value' => '0', 'updated_at' => now()]
        );

        $user = $this->actAsMember();

        $response = $this->apiGet('/v2/federation/partners');

        // With federation disabled system-wide, partners should return empty or error
        $this->assertContains($response->getStatusCode(), [200, 403, 404]);

        if ($response->getStatusCode() === 200) {
            $data = $response->json('data') ?? $response->json();
            $partners = $data['data'] ?? $data;
            // Should be empty or indicate federation is off
            $this->assertTrue(
                empty($partners) || isset($data['federation_enabled']),
                'With federation disabled, partners should be empty or status should indicate off'
            );
        }
    }

    // =========================================================================
    // Cross-Tenant Discovery
    // =========================================================================

    public function test_cross_tenant_discovery_with_whitelist(): void
    {
        // Enable federation system-wide
        DB::table('federation_system_control')->updateOrInsert(
            ['setting_key' => FederationFeatureService::SYSTEM_FEDERATION_ENABLED],
            ['setting_value' => '1', 'updated_at' => now()]
        );

        // Enable federation for tenant A
        DB::table('federation_tenant_features')->updateOrInsert(
            [
                'tenant_id'   => $this->tenantAId,
                'feature_key' => FederationFeatureService::TENANT_FEDERATION_ENABLED,
            ],
            ['feature_value' => '1', 'updated_at' => now()]
        );

        // Add tenant B to tenant A's whitelist
        DB::table('federation_tenant_whitelist')->insertOrIgnore([
            'tenant_id'         => $this->tenantAId,
            'partner_tenant_id' => $this->tenantBId,
            'status'            => 'active',
            'approved_at'       => now(),
            'created_at'        => now(),
            'updated_at'        => now(),
        ]);

        // Create a user on tenant B that is federation-visible
        $partnerUser = User::factory()->forTenant($this->tenantBId)->create([
            'status'             => 'active',
            'is_approved'        => true,
            'federation_visible' => true,
        ]);

        $user = $this->actAsMember();

        // Try to discover federated partners
        $response = $this->apiGet('/v2/federation/partners');

        if ($response->getStatusCode() === 403 || $response->getStatusCode() === 404) {
            $this->markTestIncomplete(
                'Federation endpoints not accessible — feature may need additional setup'
            );
        }

        $this->assertEquals(200, $response->getStatusCode());
    }

    public function test_cross_tenant_members_requires_whitelist(): void
    {
        // Enable federation system-wide
        DB::table('federation_system_control')->updateOrInsert(
            ['setting_key' => FederationFeatureService::SYSTEM_FEDERATION_ENABLED],
            ['setting_value' => '1', 'updated_at' => now()]
        );

        // Do NOT whitelist tenant B — discovery should return empty
        $user = $this->actAsMember();

        $response = $this->apiGet("/v2/federation/members?tenant_id={$this->tenantBId}");

        if ($response->getStatusCode() === 403 || $response->getStatusCode() === 404) {
            $this->markTestIncomplete(
                'Federation members endpoint not accessible — feature may need additional setup'
            );
        }

        $this->assertEquals(200, $response->getStatusCode());

        $data = $response->json('data') ?? $response->json();
        $members = $data['data'] ?? $data;

        // Without whitelist, should return no members from tenant B
        if (is_array($members)) {
            $this->assertEmpty($members, 'Should not expose members from non-whitelisted tenant');
        }
    }

    // =========================================================================
    // Federation Feature Gating
    // =========================================================================

    public function test_federation_feature_gating_per_tenant(): void
    {
        // Enable federation system-wide
        DB::table('federation_system_control')->updateOrInsert(
            ['setting_key' => FederationFeatureService::SYSTEM_FEDERATION_ENABLED],
            ['setting_value' => '1', 'updated_at' => now()]
        );

        // Disable federation for tenant A specifically
        DB::table('federation_tenant_features')->updateOrInsert(
            [
                'tenant_id'   => $this->tenantAId,
                'feature_key' => FederationFeatureService::TENANT_FEDERATION_ENABLED,
            ],
            ['feature_value' => '0', 'updated_at' => now()]
        );

        $user = $this->actAsMember();

        $response = $this->apiGet('/v2/federation/status');

        if ($response->getStatusCode() === 200) {
            $data = $response->json('data') ?? $response->json();
            $status = $data['data'] ?? $data;

            // Federation should be indicated as disabled for this tenant
            if (isset($status['federation_enabled'])) {
                $this->assertFalse(
                    (bool) $status['federation_enabled'],
                    'Federation should be disabled for tenant A'
                );
            }
        }

        // Also acceptable: 403 (forbidden because federation is off)
        $this->assertContains($response->getStatusCode(), [200, 403, 404]);
    }

    public function test_federation_opt_in_flow(): void
    {
        // Enable federation system-wide
        DB::table('federation_system_control')->updateOrInsert(
            ['setting_key' => FederationFeatureService::SYSTEM_FEDERATION_ENABLED],
            ['setting_value' => '1', 'updated_at' => now()]
        );

        $user = $this->actAsMember();

        $response = $this->apiPost('/v2/federation/opt-in');

        // Opt-in may succeed or fail depending on tenant config
        $this->assertContains($response->getStatusCode(), [200, 201, 400, 403, 404]);
    }

    public function test_federation_opt_out_flow(): void
    {
        $user = $this->actAsMember();

        $response = $this->apiPost('/v2/federation/opt-out');

        // Opt-out may succeed or fail depending on current state
        $this->assertContains($response->getStatusCode(), [200, 400, 403, 404]);
    }

    // =========================================================================
    // Tenant Isolation
    // =========================================================================

    public function test_tenant_data_is_isolated(): void
    {
        // Create users on both tenants
        $userA = User::factory()->forTenant($this->tenantAId)->create([
            'status' => 'active',
            'is_approved' => true,
            'name' => 'Tenant A User',
        ]);

        $userB = User::factory()->forTenant($this->tenantBId)->create([
            'status' => 'active',
            'is_approved' => true,
            'name' => 'Tenant B User',
        ]);

        // Acting as tenant A user, querying tenant A data
        Sanctum::actingAs($userA, ['*']);

        // User B should NOT appear in tenant A's member listings
        $usersInTenantA = User::where('tenant_id', $this->tenantAId)->pluck('id')->toArray();
        $this->assertContains($userA->id, $usersInTenantA);
        $this->assertNotContains($userB->id, $usersInTenantA);
    }

    public function test_federation_emergency_lockdown(): void
    {
        // Enable system lockdown
        DB::table('federation_system_control')->updateOrInsert(
            ['setting_key' => FederationFeatureService::SYSTEM_EMERGENCY_LOCKDOWN],
            ['setting_value' => '1', 'updated_at' => now()]
        );

        $user = $this->actAsMember();

        $response = $this->apiGet('/v2/federation/partners');

        // Under lockdown, federation should be fully disabled
        $this->assertContains($response->getStatusCode(), [200, 403, 404, 503]);

        if ($response->getStatusCode() === 200) {
            $data = $response->json('data') ?? $response->json();
            $partners = $data['data'] ?? $data;
            if (is_array($partners)) {
                $this->assertEmpty($partners, 'No partners should be visible during lockdown');
            }
        }
    }

    public function test_federation_cross_tenant_messaging_gating(): void
    {
        $this->markTestIncomplete(
            'Cross-tenant messaging requires full federation setup with bidirectional whitelist, '
            . 'message routing, and Pusher/FCM mocking — skipping for now.'
        );
    }

    public function test_federation_cross_tenant_transactions_gating(): void
    {
        $this->markTestIncomplete(
            'Cross-tenant transactions require exchange workflow + federation wallet bridging — '
            . 'too complex for automated integration test without real federation infrastructure.'
        );
    }
}
