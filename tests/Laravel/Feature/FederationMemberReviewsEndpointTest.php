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
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Illuminate\Support\Facades\DB;
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

    private function setFederationFeature(int $tenantId, bool $enabled): void
    {
        $row = DB::table('tenants')->where('id', $tenantId)->first();
        if (!$row) {
            $this->markTestSkipped("Tenant {$tenantId} does not exist.");
        }
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
    }

    private function makeController(): FederationV2Controller
    {
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

        $actingUser = User::factory()->forTenant($this->testTenantId)->create();
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
}
