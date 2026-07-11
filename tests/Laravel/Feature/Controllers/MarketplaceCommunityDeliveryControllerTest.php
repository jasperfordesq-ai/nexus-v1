<?php
// Copyright © 2024–2026 Jasper Ford
// SPDX-License-Identifier: AGPL-3.0-or-later
// Author: Jasper Ford
// See NOTICE file for attribution and acknowledgements.

namespace Tests\Laravel\Feature\Controllers;

use App\Exceptions\SafeguardingPolicyException;
use Tests\Laravel\TestCase;
use App\Core\TenantContext;
use App\Services\SafeguardingInteractionPolicy;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Laravel\Sanctum\Sanctum;
use App\Models\User;
use Mockery;

/**
 * Smoke tests for MarketplaceCommunityDeliveryController.
 */
class MarketplaceCommunityDeliveryControllerTest extends TestCase
{
    use DatabaseTransactions;

    private function setMarketplaceFeature(bool $enabled): void
    {
        $tenant = DB::table('tenants')->where('id', $this->testTenantId)->first();
        $features = [];
        if ($tenant && ! empty($tenant->features)) {
            $decoded = is_string($tenant->features) ? json_decode($tenant->features, true) : $tenant->features;
            $features = is_array($decoded) ? $decoded : [];
        }

        $features['marketplace'] = $enabled;
        DB::table('tenants')
            ->where('id', $this->testTenantId)
            ->update(['features' => json_encode($features)]);
        TenantContext::setById($this->testTenantId);
    }

    private function requireCommunityDeliveryTables(): void
    {
        foreach (['marketplace_listings', 'marketplace_orders', 'marketplace_delivery_offers'] as $table) {
            if (! Schema::hasTable($table)) {
                $this->markTestSkipped("{$table} is not present in the test database.");
            }
        }
    }

    private function authenticatedUser(): User
    {
        $user = User::factory()->forTenant($this->testTenantId)->create([
            'status' => 'active',
            'is_approved' => true,
        ]);
        Sanctum::actingAs($user, ['*']);
        return $user;
    }

    private function createCommunityDeliveryOrder(User $buyer, User $seller): int
    {
        $listingId = DB::table('marketplace_listings')->insertGetId([
            'tenant_id' => $this->testTenantId,
            'user_id' => $seller->id,
            'title' => 'Community delivery listing',
            'description' => 'A listing used for community delivery authorization tests.',
            'price' => 10,
            'price_currency' => 'EUR',
            'price_type' => 'fixed',
            'quantity' => 1,
            'delivery_method' => 'community_delivery',
            'seller_type' => 'private',
            'status' => 'active',
            'moderation_status' => 'approved',
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        return (int) DB::table('marketplace_orders')->insertGetId([
            'tenant_id' => $this->testTenantId,
            'order_number' => 'TEST-' . uniqid(),
            'buyer_id' => $buyer->id,
            'seller_id' => $seller->id,
            'marketplace_listing_id' => $listingId,
            'quantity' => 1,
            'unit_price' => 10,
            'total_price' => 10,
            'currency' => 'EUR',
            'status' => 'paid',
            'created_at' => now(),
            'updated_at' => now(),
        ]);
    }

    public function test_store_requires_auth(): void
    {
        $response = $this->apiPost('/v2/marketplace/orders/1/delivery-offers', []);
        $this->assertContains($response->status(), [401, 403]);
    }

    public function test_index_requires_auth(): void
    {
        $response = $this->apiGet('/v2/marketplace/orders/1/delivery-offers');
        $this->assertContains($response->status(), [401, 403]);
    }

    public function test_accept_requires_auth(): void
    {
        $response = $this->apiPut('/v2/marketplace/orders/1/delivery-offers/1/accept', []);
        $this->assertContains($response->status(), [401, 403]);
    }

    public function test_confirm_requires_auth(): void
    {
        $response = $this->apiPut('/v2/marketplace/orders/1/delivery-offers/1/confirm', []);
        $this->assertContains($response->status(), [401, 403]);
    }

    public function test_index_authenticated_smoke(): void
    {
        $this->authenticatedUser();
        $response = $this->apiGet('/v2/marketplace/orders/1/delivery-offers');
        $this->assertLessThan(500, $response->status());
    }

    public function test_accept_requires_order_participant(): void
    {
        $this->requireCommunityDeliveryTables();
        $this->setMarketplaceFeature(true);

        $buyer = User::factory()->forTenant($this->testTenantId)->create();
        $seller = User::factory()->forTenant($this->testTenantId)->create();
        $deliverer = User::factory()->forTenant($this->testTenantId)->create();
        $outsider = User::factory()->forTenant($this->testTenantId)->create();
        $orderId = $this->createCommunityDeliveryOrder($buyer, $seller);

        DB::table('marketplace_delivery_offers')->insert([
            'tenant_id' => $this->testTenantId,
            'order_id' => $orderId,
            'deliverer_id' => $deliverer->id,
            'time_credits' => 2,
            'status' => 'pending',
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        Sanctum::actingAs($outsider, ['*']);

        $response = $this->apiPut("/v2/marketplace/orders/{$orderId}/delivery-offers/{$deliverer->id}/accept", []);

        $response->assertStatus(403);
        $response->assertJsonPath('errors.0.code', 'FORBIDDEN');
        $this->assertDatabaseHas('marketplace_delivery_offers', [
            'tenant_id' => $this->testTenantId,
            'order_id' => $orderId,
            'deliverer_id' => $deliverer->id,
            'status' => 'pending',
        ]);
    }

    public function test_confirm_requires_order_participant(): void
    {
        $this->requireCommunityDeliveryTables();
        $this->setMarketplaceFeature(true);

        $buyer = User::factory()->forTenant($this->testTenantId)->create(['balance' => 10]);
        $seller = User::factory()->forTenant($this->testTenantId)->create();
        $deliverer = User::factory()->forTenant($this->testTenantId)->create(['balance' => 0]);
        $outsider = User::factory()->forTenant($this->testTenantId)->create();
        $orderId = $this->createCommunityDeliveryOrder($buyer, $seller);

        DB::table('marketplace_delivery_offers')->insert([
            'tenant_id' => $this->testTenantId,
            'order_id' => $orderId,
            'deliverer_id' => $deliverer->id,
            'time_credits' => 2,
            'status' => 'accepted',
            'accepted_at' => now(),
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        Sanctum::actingAs($outsider, ['*']);

        $response = $this->apiPut("/v2/marketplace/orders/{$orderId}/delivery-offers/{$deliverer->id}/confirm", []);

        $response->assertStatus(403);
        $response->assertJsonPath('errors.0.code', 'FORBIDDEN');
        $this->assertDatabaseHas('marketplace_delivery_offers', [
            'tenant_id' => $this->testTenantId,
            'order_id' => $orderId,
            'deliverer_id' => $deliverer->id,
            'status' => 'accepted',
        ]);
    }

    public function test_delivery_offer_denial_writes_no_offer_or_notes(): void
    {
        $this->requireCommunityDeliveryTables();
        $this->setMarketplaceFeature(true);

        $buyer = User::factory()->forTenant($this->testTenantId)->create();
        $seller = User::factory()->forTenant($this->testTenantId)->create();
        $deliverer = $this->authenticatedUser();
        $orderId = $this->createCommunityDeliveryOrder($buyer, $seller);

        $policy = Mockery::mock(SafeguardingInteractionPolicy::class);
        $policy->shouldReceive('assertManyLocalContactsAllowed')
            ->once()
            ->with(
                $deliverer->id,
                Mockery::on(fn (array $ids): bool => $ids === [$buyer->id, $seller->id]),
                $this->testTenantId,
                'marketplace_community_delivery_offer',
            )
            ->andThrow(new SafeguardingPolicyException('VETTING_REQUIRED', 'Vetting required'));
        $this->app->instance(SafeguardingInteractionPolicy::class, $policy);

        $response = $this->apiPost("/v2/marketplace/orders/{$orderId}/delivery-offers", [
            'time_credits' => 2,
            'estimated_minutes' => 45,
            'notes' => 'I can bring this to your home.',
        ]);

        $response->assertStatus(403)->assertJsonPath('errors.0.code', 'VETTING_REQUIRED');
        $this->assertDatabaseMissing('marketplace_delivery_offers', [
            'tenant_id' => $this->testTenantId,
            'order_id' => $orderId,
            'deliverer_id' => $deliverer->id,
        ]);
    }

    public function test_delivery_offer_allowed_checks_both_directions_before_persisting(): void
    {
        $this->requireCommunityDeliveryTables();
        $this->setMarketplaceFeature(true);

        $buyer = User::factory()->forTenant($this->testTenantId)->create();
        $seller = User::factory()->forTenant($this->testTenantId)->create();
        $deliverer = $this->authenticatedUser();
        $orderId = $this->createCommunityDeliveryOrder($buyer, $seller);

        $policy = Mockery::mock(SafeguardingInteractionPolicy::class);
        $policy->shouldReceive('assertManyLocalContactsAllowed')
            ->once()
            ->with(
                $deliverer->id,
                Mockery::on(fn (array $ids): bool => $ids === [$buyer->id, $seller->id]),
                $this->testTenantId,
                'marketplace_community_delivery_offer',
            );
        $policy->shouldReceive('assertLocalContactAllowed')
            ->once()
            ->with($buyer->id, $deliverer->id, $this->testTenantId, 'marketplace_community_delivery_offer');
        $policy->shouldReceive('assertLocalContactAllowed')
            ->once()
            ->with($seller->id, $deliverer->id, $this->testTenantId, 'marketplace_community_delivery_offer');
        $this->app->instance(SafeguardingInteractionPolicy::class, $policy);

        $response = $this->apiPost("/v2/marketplace/orders/{$orderId}/delivery-offers", [
            'time_credits' => 2,
            'estimated_minutes' => 45,
            'notes' => 'Available after 4pm.',
        ]);

        $response->assertCreated();
        $this->assertDatabaseHas('marketplace_delivery_offers', [
            'tenant_id' => $this->testTenantId,
            'order_id' => $orderId,
            'deliverer_id' => $deliverer->id,
            'notes' => 'Available after 4pm.',
            'status' => 'pending',
        ]);
    }

    public function test_acceptance_rechecks_reverse_direction_before_changing_any_offer(): void
    {
        $this->requireCommunityDeliveryTables();
        $this->setMarketplaceFeature(true);

        $buyer = $this->authenticatedUser();
        $seller = User::factory()->forTenant($this->testTenantId)->create();
        $deliverer = User::factory()->forTenant($this->testTenantId)->create();
        $otherDeliverer = User::factory()->forTenant($this->testTenantId)->create();
        $orderId = $this->createCommunityDeliveryOrder($buyer, $seller);

        foreach ([$deliverer, $otherDeliverer] as $candidate) {
            DB::table('marketplace_delivery_offers')->insert([
                'tenant_id' => $this->testTenantId,
                'order_id' => $orderId,
                'deliverer_id' => $candidate->id,
                'time_credits' => 2,
                'status' => 'pending',
                'created_at' => now(),
                'updated_at' => now(),
            ]);
        }

        $policy = Mockery::mock(SafeguardingInteractionPolicy::class);
        $policy->shouldReceive('assertManyLocalContactsAllowed')->once();
        $policy->shouldReceive('assertLocalContactAllowed')
            ->once()
            ->with($buyer->id, $deliverer->id, $this->testTenantId, 'marketplace_community_delivery_acceptance')
            ->andThrow(new SafeguardingPolicyException('SAFEGUARDING_CONTACT_RESTRICTED', 'Contact restricted'));
        $this->app->instance(SafeguardingInteractionPolicy::class, $policy);

        $response = $this->apiPut("/v2/marketplace/orders/{$orderId}/delivery-offers/{$deliverer->id}/accept", []);

        $response->assertStatus(403)->assertJsonPath('errors.0.code', 'SAFEGUARDING_CONTACT_RESTRICTED');
        $this->assertSame(2, DB::table('marketplace_delivery_offers')
            ->where('tenant_id', $this->testTenantId)
            ->where('order_id', $orderId)
            ->where('status', 'pending')
            ->count());
    }

    public function test_confirmation_policy_unavailable_moves_no_credits_and_writes_no_transaction(): void
    {
        $this->requireCommunityDeliveryTables();
        $this->setMarketplaceFeature(true);

        $buyer = $this->authenticatedUser();
        $buyer->forceFill(['balance' => 10])->save();
        $seller = User::factory()->forTenant($this->testTenantId)->create();
        $deliverer = User::factory()->forTenant($this->testTenantId)->create(['balance' => 0]);
        $orderId = $this->createCommunityDeliveryOrder($buyer, $seller);

        DB::table('marketplace_delivery_offers')->insert([
            'tenant_id' => $this->testTenantId,
            'order_id' => $orderId,
            'deliverer_id' => $deliverer->id,
            'time_credits' => 2,
            'status' => 'accepted',
            'accepted_at' => now(),
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        $transactionCount = DB::table('transactions')
            ->where('tenant_id', $this->testTenantId)
            ->where('transaction_type', 'community_delivery')
            ->count();

        $policy = Mockery::mock(SafeguardingInteractionPolicy::class);
        $policy->shouldReceive('assertManyLocalContactsAllowed')->once();
        $policy->shouldReceive('assertLocalContactAllowed')
            ->once()
            ->with($buyer->id, $deliverer->id, $this->testTenantId, 'marketplace_community_delivery_confirmation')
            ->andThrow(new SafeguardingPolicyException('SAFEGUARDING_POLICY_UNAVAILABLE', 'Policy unavailable'));
        $this->app->instance(SafeguardingInteractionPolicy::class, $policy);

        $response = $this->apiPut("/v2/marketplace/orders/{$orderId}/delivery-offers/{$deliverer->id}/confirm", []);

        $response->assertStatus(503)->assertJsonPath('errors.0.code', 'SAFEGUARDING_POLICY_UNAVAILABLE');
        $this->assertDatabaseHas('marketplace_delivery_offers', [
            'tenant_id' => $this->testTenantId,
            'order_id' => $orderId,
            'deliverer_id' => $deliverer->id,
            'status' => 'accepted',
        ]);
        $this->assertSame(10.0, (float) $buyer->fresh()->balance);
        $this->assertSame(0.0, (float) $deliverer->fresh()->balance);
        $this->assertSame($transactionCount, DB::table('transactions')
            ->where('tenant_id', $this->testTenantId)
            ->where('transaction_type', 'community_delivery')
            ->count());
    }
}
