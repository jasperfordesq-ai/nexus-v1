<?php
// Copyright © 2024–2026 Jasper Ford
// SPDX-License-Identifier: AGPL-3.0-or-later
// Author: Jasper Ford
// See NOTICE file for attribution and acknowledgements.

declare(strict_types=1);

namespace Tests\Laravel\Feature;

use App\Core\TenantContext;
use App\Http\Controllers\Api\FederationV2Controller;
use App\Models\User;
use App\Services\FederationFeatureService;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Http;
use Tests\Laravel\Concerns\FederationIntegrationHarness;
use Tests\Laravel\TestCase;

/**
 * Exercises GET /api/v2/federation/members/{id}/reviews — the endpoint that
 * feeds the FederationReviewsPanel frontend component.
 *
 * We call the controller method directly (mirrors FederationFeatureGateTest)
 * so the assertion isolates controller logic from unrelated middleware.
 */
class FederationMemberReviewsEndpointTest extends TestCase
{
    use DatabaseTransactions;
    use FederationIntegrationHarness;

    /**
     * Toggle the tenant's federation feature through the real 3-table gate that
     * FederationV2Controller::requireFederationOperation() consults
     * (FederationFeatureService::isOperationAllowed). The legacy tenants.features
     * JSON column is NOT what the controller gate reads.
     */
    private function setFederationFeature(int $tenantId, bool $enabled): void
    {
        $row = DB::table('tenants')->where('id', $tenantId)->first();
        if (!$row) {
            $this->markTestSkipped("Tenant {$tenantId} does not exist.");
        }

        $this->enableFederationForTenant($tenantId);
        if (!$enabled) {
            // Reach the tenant-feature branch, then disable it so the gate fires.
            DB::table('federation_tenant_features')->updateOrInsert(
                ['tenant_id' => $tenantId, 'feature_key' => FederationFeatureService::TENANT_FEDERATION_ENABLED],
                ['is_enabled' => 0, 'updated_at' => now()]
            );
        }

        // Keep tenants.features JSON in sync for any TenantContext::hasFeature() use.
        $features = [];
        if (!empty($row->features)) {
            $decoded = is_string($row->features) ? json_decode($row->features, true) : $row->features;
            if (is_array($decoded)) {
                $features = $decoded;
            }
        }
        $features['federation'] = $enabled;
        DB::table('tenants')->where('id', $tenantId)->update(['features' => json_encode($features)]);
        TenantContext::setById($tenantId);

        // FederationFeatureService is a container singleton that caches the gating
        // tables in-memory. DatabaseTransactions rolls rows back between tests, so
        // the singleton can carry a previous test's cached verdict into this one.
        // Bust its cache after re-seeding so the gate re-reads the fresh rows.
        $this->app->make(FederationFeatureService::class)->clearCache();
    }

    /**
     * The local-member reviews path gates on the receiver being a federation-
     * visible member: it requires a federation_user_settings opt-in row (with
     * show_reviews_federated = 1) plus an active federation_partnerships row
     * (profiles_enabled = 1). For a LOCAL member the controller query resolves
     * u.tenant_id == memberTenantId == acting tenant, so the partnership row is a
     * tenant↔itself pairing. The clean DB has none of this, so seed it.
     */
    private function makeReceiverReviewsVisible(int $receiverId, int $tenantId): void
    {
        DB::table('federation_user_settings')->updateOrInsert(
            ['user_id' => $receiverId],
            [
                'federation_optin'          => 1,
                'profile_visible_federated' => 1,
                'show_reviews_federated'    => 1,
                'opted_in_at'               => now(),
                'updated_at'                => now(),
            ]
        );

        DB::table('federation_partnerships')->updateOrInsert(
            ['tenant_id' => $tenantId, 'partner_tenant_id' => $tenantId],
            [
                'canonical_pair'   => min($tenantId, $tenantId) . '-' . max($tenantId, $tenantId),
                'status'           => 'active',
                'profiles_enabled' => 1,
                'created_at'       => now(),
                'updated_at'       => now(),
            ]
        );
    }

    private function makeController(): FederationV2Controller
    {
        // User factory observers reset TenantContext to tenant 1; re-pin tenant 2
        // so the controller's getTenantId() (and the federation gate) see tenant 2.
        TenantContext::setById($this->testTenantId);

        return $this->app->make(FederationV2Controller::class);
    }

    public function test_local_member_returns_local_and_federated_reviews_merged(): void
    {
        $this->setFederationFeature($this->testTenantId, true);

        $receiver = User::factory()->forTenant($this->testTenantId)->create();
        $localReviewer = User::factory()->forTenant($this->testTenantId)->create([
            'first_name' => 'Alice', 'last_name' => 'Local',
        ]);
        $foreignReviewer = User::factory()->forTenant(999)->create([
            'first_name' => 'Bob', 'last_name' => 'Remote',
        ]);

        // Local review
        DB::table('reviews')->insert([
            'tenant_id'         => $this->testTenantId,
            'reviewer_id'       => $localReviewer->id,
            'receiver_id'       => $receiver->id,
            'rating'            => 5,
            'comment'           => 'Great locally',
            'status'            => 'approved',
            'review_type'       => 'local',
            'show_cross_tenant' => 1,
            'created_at'        => now(),
        ]);

        // Federated review from tenant 999
        DB::table('reviews')->insert([
            'tenant_id'          => 999,
            'reviewer_id'        => $foreignReviewer->id,
            'reviewer_tenant_id' => 999,
            'receiver_id'        => $receiver->id,
            'receiver_tenant_id' => $this->testTenantId,
            'rating'             => 4,
            'comment'            => 'Great across the network',
            'status'             => 'approved',
            'review_type'        => 'federated',
            'show_cross_tenant'  => 1,
            'created_at'         => now(),
        ]);

        $this->makeReceiverReviewsVisible((int) $receiver->id, $this->testTenantId);

        $actingUser = User::factory()->forTenant($this->testTenantId)->create();
        $this->optInUserToFederation((int) $actingUser->id);
        $this->actingAs($actingUser);

        $response = $this->makeController()->memberReviews((string) $receiver->id);

        $this->assertSame(200, $response->getStatusCode());
        $body = json_decode((string) $response->getContent(), true);
        $this->assertIsArray($body['data'] ?? null);
        $this->assertCount(2, $body['data']);

        $ratings = array_column($body['data'], 'rating');
        sort($ratings);
        $this->assertSame([4, 5], $ratings);

        // Federated row is marked verified + carries partner metadata
        $federated = collect($body['data'])->firstWhere('verified', true);
        $this->assertNotNull($federated);
        $this->assertNotNull($federated['partner']);
        $this->assertSame(999, $federated['partner']['id']);
    }

    public function test_unknown_member_returns_empty_array_with_200(): void
    {
        $this->setFederationFeature($this->testTenantId, true);
        $actingUser = User::factory()->forTenant($this->testTenantId)->create();
        $this->optInUserToFederation((int) $actingUser->id);
        $this->actingAs($actingUser);

        $response = $this->makeController()->memberReviews('99999999');

        $this->assertSame(200, $response->getStatusCode());
        $body = json_decode((string) $response->getContent(), true);
        $this->assertSame([], $body['data'] ?? null);
    }

    public function test_tenant_isolation_cannot_see_other_tenants_local_reviews(): void
    {
        $this->setFederationFeature($this->testTenantId, true);

        // Receiver belongs to tenant 999 — NOT the acting tenant.
        $foreignReceiver = User::factory()->forTenant(999)->create();
        $foreignReviewer = User::factory()->forTenant(999)->create();

        DB::table('reviews')->insert([
            'tenant_id'          => 999,
            'reviewer_id'        => $foreignReviewer->id,
            'receiver_id'        => $foreignReceiver->id,
            'rating'             => 5,
            'status'             => 'approved',
            'review_type'        => 'local',
            // NOT cross-tenant — must stay inside tenant 999
            'show_cross_tenant'  => 0,
            'created_at'         => now(),
        ]);

        $actingUser = User::factory()->forTenant($this->testTenantId)->create();
        $this->optInUserToFederation((int) $actingUser->id);
        $this->actingAs($actingUser);

        $response = $this->makeController()->memberReviews((string) $foreignReceiver->id);

        $this->assertSame(200, $response->getStatusCode());
        $body = json_decode((string) $response->getContent(), true);
        $this->assertSame([], $body['data'] ?? null, 'tenant-scoped query must not leak reviews from other tenants');
    }

    public function test_feature_flag_disabled_returns_403(): void
    {
        $this->setFederationFeature($this->testTenantId, false);

        $actingUser = User::factory()->forTenant($this->testTenantId)->create();
        $this->actingAs($actingUser);

        $response = $this->makeController()->memberReviews('1');

        $this->assertSame(403, $response->getStatusCode());
        $this->assertStringContainsString(
            'Federation feature disabled',
            (string) $response->getContent()
        );
    }

    /**
     * @dataProvider inactiveExternalPartnerStatusProvider
     */
    public function test_external_member_reviews_rejects_inactive_partner_before_fetch(string $status): void
    {
        $this->setFederationFeature($this->testTenantId, true);
        Http::fake();

        $partner = $this->setupPartner('nexus', $this->testTenantId);
        DB::table('federation_external_partners')
            ->where('id', $partner->id)
            ->update(['status' => $status]);

        $actingUser = User::factory()->forTenant($this->testTenantId)->create();
        $this->optInUserToFederation((int) $actingUser->id);
        $this->actingAs($actingUser);

        $response = $this->makeController()->memberReviews('ext-' . $partner->id . '-remote-member-1');

        $this->assertSame(404, $response->getStatusCode());
        Http::assertNothingSent();
    }

    public static function inactiveExternalPartnerStatusProvider(): array
    {
        return [
            'inactive' => ['inactive'],
            'suspended' => ['suspended'],
            'disabled' => ['disabled'],
        ];
    }

    public function test_external_member_reviews_rejects_disabled_member_permission_before_fetch(): void
    {
        $this->setFederationFeature($this->testTenantId, true);
        Http::fake();

        $partner = $this->setupPartner('nexus', $this->testTenantId);
        DB::table('federation_external_partners')
            ->where('id', $partner->id)
            ->update(['allow_member_search' => 0]);

        $actingUser = User::factory()->forTenant($this->testTenantId)->create();
        $this->optInUserToFederation((int) $actingUser->id);
        $this->actingAs($actingUser);

        $response = $this->makeController()->memberReviews('ext-' . $partner->id . '-remote-member-1');

        $this->assertSame(403, $response->getStatusCode());
        Http::assertNothingSent();
    }
}
