<?php
// Copyright © 2024–2026 Jasper Ford
// SPDX-License-Identifier: AGPL-3.0-or-later
// Author: Jasper Ford
// See NOTICE file for attribution and acknowledgements.

declare(strict_types=1);

namespace Tests\Laravel\Feature\Marketplace;

use App\Core\TenantContext;
use App\Models\User;
use App\Services\MarketplaceConfigurationService;
use App\Services\SafeguardingInteractionPolicy;
use App\Services\TenantFeatureConfig;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Illuminate\Support\Facades\DB;
use Laravel\Sanctum\Sanctum;
use Mockery;
use Stripe\HttpClient\ClientInterface;
use Tests\Laravel\TestCase;

/**
 * Exercises the maintained marketplace API lifecycle against the real database
 * and application services. Only Stripe's remote HTTP boundary is simulated.
 */
final class MarketplaceApiLifecycleTest extends TestCase
{
    use DatabaseTransactions;

    private object $stripeHttp;

    protected function setUp(): void
    {
        parent::setUp();

        $features = TenantFeatureConfig::FEATURE_DEFAULTS;
        $features['marketplace'] = true;
        DB::table('tenants')->where('id', $this->testTenantId)->update([
            'features' => json_encode($features, JSON_THROW_ON_ERROR),
        ]);
        TenantContext::setById($this->testTenantId);

        MarketplaceConfigurationService::set(
            MarketplaceConfigurationService::CONFIG_STRIPE_ENABLED,
            true,
        );
        MarketplaceConfigurationService::set(
            MarketplaceConfigurationService::CONFIG_ESCROW_ENABLED,
            true,
        );
        config(['services.stripe.secret' => 'sk_test_marketplace_api_lifecycle']);

        $this->stripeHttp = new class implements ClientInterface {
            /** @var array<string,mixed>|null */
            public ?array $paymentIntent = null;

            /** @var list<array{method:string,url:string,params:array}> */
            public array $requests = [];

            public function request($method, $absUrl, $headers, $params, $hasFile, $apiMode = 'v1'): array
            {
                $method = strtolower((string) $method);
                $url = (string) $absUrl;
                $params = is_array($params) ? $params : [];
                $this->requests[] = compact('method', 'url', 'params');

                if ($method === 'post' && str_ends_with($url, '/v1/payment_intents')) {
                    $this->paymentIntent = [
                        'id' => 'pi_marketplace_api_lifecycle',
                        'object' => 'payment_intent',
                        'amount' => (int) ($params['amount'] ?? 0),
                        'amount_received' => (int) ($params['amount'] ?? 0),
                        'currency' => (string) ($params['currency'] ?? 'eur'),
                        'client_secret' => 'pi_marketplace_api_lifecycle_secret_test',
                        'status' => 'succeeded',
                        'metadata' => $params['metadata'] ?? [],
                        'latest_charge' => 'ch_marketplace_api_lifecycle',
                        'payment_method_types' => ['card'],
                    ];

                    return [$this->json($this->paymentIntent), 200, []];
                }

                if ($method === 'get' && str_contains($url, '/v1/payment_intents/')) {
                    if ($this->paymentIntent === null) {
                        throw new \RuntimeException('PaymentIntent was retrieved before creation.');
                    }

                    return [$this->json($this->paymentIntent), 200, []];
                }

                if ($method === 'post' && str_ends_with($url, '/v1/refunds')) {
                    return [$this->json([
                        'id' => 're_marketplace_api_lifecycle',
                        'object' => 'refund',
                        'amount' => (int) ($params['amount'] ?? 0),
                        'payment_intent' => (string) ($params['payment_intent'] ?? ''),
                        'status' => 'succeeded',
                    ]), 200, []];
                }

                throw new \RuntimeException("Unexpected Stripe request: {$method} {$url}");
            }

            /** @param array<string,mixed> $payload */
            private function json(array $payload): string
            {
                return json_encode($payload, JSON_THROW_ON_ERROR);
            }
        };
        \Stripe\ApiRequestor::setHttpClient($this->stripeHttp);

        $policy = Mockery::mock(SafeguardingInteractionPolicy::class);
        $policy->shouldReceive('assertLocalContactAllowed')->zeroOrMoreTimes();
        $this->app->instance(SafeguardingInteractionPolicy::class, $policy);
    }

    protected function tearDown(): void
    {
        \Stripe\ApiRequestor::setHttpClient(null);
        parent::tearDown();
    }

    public function test_cash_order_payment_delivery_dispute_and_refund_complete_through_api(): void
    {
        $buyer = User::factory()->forTenant($this->testTenantId)->create([
            'status' => 'active',
            'is_approved' => true,
            'preferred_language' => 'en',
        ]);
        $seller = User::factory()->forTenant($this->testTenantId)->create([
            'status' => 'active',
            'is_approved' => true,
            'preferred_language' => 'en',
        ]);
        $admin = User::factory()->forTenant($this->testTenantId)->admin()->create([
            'status' => 'active',
            'preferred_language' => 'en',
        ]);

        DB::table('marketplace_seller_profiles')->insert([
            'tenant_id' => $this->testTenantId,
            'user_id' => $seller->id,
            'seller_type' => 'private',
            'stripe_account_id' => 'acct_marketplace_api_lifecycle',
            'stripe_onboarding_complete' => true,
            'is_suspended' => false,
            'created_at' => now(),
            'updated_at' => now(),
        ]);
        $listingId = (int) DB::table('marketplace_listings')->insertGetId([
            'tenant_id' => $this->testTenantId,
            'user_id' => $seller->id,
            'title' => 'API lifecycle marketplace item',
            'description' => 'A real application lifecycle regression fixture.',
            'price' => 25.00,
            'price_currency' => 'EUR',
            'price_type' => 'fixed',
            'quantity' => 1,
            'inventory_count' => 1,
            'shipping_available' => false,
            'local_pickup' => true,
            'delivery_method' => 'pickup',
            'seller_type' => 'private',
            'status' => 'active',
            'moderation_status' => 'approved',
            'expires_at' => now()->addWeek(),
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        Sanctum::actingAs($buyer, ['*']);
        $orderResponse = $this->apiPost('/v2/marketplace/orders', [
            'listing_id' => $listingId,
            'quantity' => 1,
            'shipping_method' => 'pickup',
            'payment_method' => 'cash',
            'idempotency_key' => 'marketplace-api-lifecycle-order',
        ]);
        $orderResponse->assertCreated()
            ->assertJsonPath('data.status', 'pending_payment')
            ->assertJsonPath('data.total_price', 25);
        $orderId = (int) $orderResponse->json('data.id');

        $intentResponse = $this->apiPost('/v2/marketplace/payments/create-intent', [
            'order_id' => $orderId,
        ]);
        $intentResponse->assertOk()
            ->assertJsonPath('data.payment_intent_id', 'pi_marketplace_api_lifecycle');
        $this->apiPost('/v2/marketplace/payments/create-intent', [
            'order_id' => $orderId,
        ])->assertOk()
            ->assertJsonPath('data.payment_intent_id', 'pi_marketplace_api_lifecycle');

        $paymentResponse = $this->apiPost('/v2/marketplace/payments/confirm', [
            'payment_intent_id' => 'pi_marketplace_api_lifecycle',
        ]);
        $paymentResponse->assertOk()
            ->assertJsonPath('data.status', 'succeeded')
            ->assertJsonPath('data.order_id', $orderId);
        $paymentId = (int) $paymentResponse->json('data.payment_id');

        $this->assertDatabaseHas('marketplace_escrow', [
            'tenant_id' => $this->testTenantId,
            'order_id' => $orderId,
            'payment_id' => $paymentId,
            'status' => 'held',
        ]);

        // Simulate a prior post-capture hold write being lost. A succeeded
        // confirmation replay must heal the missing durable escrow exactly once.
        DB::table('marketplace_escrow')->where('order_id', $orderId)->delete();
        $this->apiPost('/v2/marketplace/payments/confirm', [
            'payment_intent_id' => 'pi_marketplace_api_lifecycle',
        ])->assertOk();
        $this->assertDatabaseHas('marketplace_escrow', [
            'tenant_id' => $this->testTenantId,
            'order_id' => $orderId,
            'payment_id' => $paymentId,
            'status' => 'held',
        ]);
        $this->assertSame(1, DB::table('marketplace_escrow')
            ->where('order_id', $orderId)
            ->count());

        Sanctum::actingAs($seller, ['*']);
        $this->apiPut("/v2/marketplace/orders/{$orderId}/ship", [
            'tracking_number' => 'TRACK-LIFECYCLE-1',
        ])->assertOk()->assertJsonPath('data.status', 'shipped');

        Sanctum::actingAs($buyer, ['*']);
        $this->apiPut("/v2/marketplace/orders/{$orderId}/confirm-delivery")
            ->assertOk()
            ->assertJsonPath('data.status', 'delivered');
        $disputeResponse = $this->apiPost("/v2/marketplace/orders/{$orderId}/dispute", [
            'reason' => 'not_as_described',
            'description' => 'The delivered item materially differs from the listing.',
        ]);
        $disputeResponse->assertCreated()
            ->assertJsonPath('data.status', 'open');
        $disputeId = (int) $disputeResponse->json('data.id');

        Sanctum::actingAs($admin, ['*']);
        $this->apiPut("/v2/admin/marketplace/disputes/{$disputeId}/resolve", [
            'resolution' => 'buyer',
            'resolution_notes' => 'Evidence supports a full buyer refund.',
        ])->assertOk()->assertJsonPath('data.status', 'resolved_buyer');

        $this->assertDatabaseHas('marketplace_orders', [
            'id' => $orderId,
            'tenant_id' => $this->testTenantId,
            'status' => 'refunded',
        ]);
        $this->assertDatabaseHas('marketplace_payments', [
            'id' => $paymentId,
            'tenant_id' => $this->testTenantId,
            'status' => 'refunded',
            'refund_amount' => 25.00,
            'payout_status' => 'failed',
        ]);
        $this->assertDatabaseHas('marketplace_payment_refunds', [
            'tenant_id' => $this->testTenantId,
            'payment_id' => $paymentId,
            'stripe_refund_id' => 're_marketplace_api_lifecycle',
            'amount' => 25.00,
        ]);
        $this->assertDatabaseHas('marketplace_escrow', [
            'tenant_id' => $this->testTenantId,
            'order_id' => $orderId,
            'status' => 'refunded',
            'amount' => 0.00,
        ]);
        $this->assertDatabaseHas('marketplace_listings', [
            'id' => $listingId,
            'tenant_id' => $this->testTenantId,
            'status' => 'active',
            'inventory_count' => 1,
        ]);

        $stripeUrls = array_column($this->stripeHttp->requests, 'url');
        $this->assertCount(1, array_filter($stripeUrls, fn (string $url): bool => str_ends_with($url, '/v1/payment_intents')));
        $this->assertCount(1, array_filter($stripeUrls, fn (string $url): bool => str_ends_with($url, '/v1/refunds')));
    }
}
