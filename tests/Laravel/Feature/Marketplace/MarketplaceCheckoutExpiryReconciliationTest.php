<?php
// Copyright © 2024–2026 Jasper Ford
// SPDX-License-Identifier: AGPL-3.0-or-later
// Author: Jasper Ford
// See NOTICE file for attribution and acknowledgements.

declare(strict_types=1);

namespace Tests\Laravel\Feature\Marketplace;

use App\Core\TenantContext;
use App\Models\MarketplaceOrder;
use App\Models\User;
use App\Services\MarketplaceConfigurationService;
use App\Services\MarketplacePaymentService;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Illuminate\Support\Facades\DB;
use Stripe\HttpClient\ClientInterface;
use Tests\Laravel\TestCase;

/** Regression coverage for the local-order/hosted-Checkout expiry boundary. */
final class MarketplaceCheckoutExpiryReconciliationTest extends TestCase
{
    use DatabaseTransactions;

    private object $stripeHttp;

    protected function setUp(): void
    {
        parent::setUp();
        TenantContext::setById($this->testTenantId);
        config([
            'services.stripe.secret' => 'sk_test_marketplace_checkout_expiry',
            'mail.default' => 'array',
        ]);
        MarketplaceConfigurationService::set(
            MarketplaceConfigurationService::CONFIG_STRIPE_ENABLED,
            true,
        );
        MarketplaceConfigurationService::set(
            MarketplaceConfigurationService::CONFIG_ESCROW_ENABLED,
            false,
        );
        MarketplaceConfigurationService::set(
            MarketplaceConfigurationService::CONFIG_PLATFORM_FEE_PERCENT,
            5,
        );

        $this->stripeHttp = new class implements ClientInterface {
            /** @var list<array{method:string,url:string,params:array}> */
            public array $requests = [];

            public string $sessionStatus = 'open';

            public string $paymentStatus = 'unpaid';

            public bool $failExpire = false;

            public ?string $paymentIntentId = null;

            public int $orderId = 0;

            public int $tenantId = 0;

            public int $expiresAt = 0;

            public ?\Closure $onPaymentIntentCreated = null;

            /** @var array<string,string> */
            public array $paymentIntentMetadata = [];

            public int $paymentIntentAmount = 2500;

            public int $applicationFeeAmount = 125;

            public function request($method, $absUrl, $headers, $params, $hasFile, $apiMode = 'v1'): array
            {
                $method = strtolower((string) $method);
                $url = (string) $absUrl;
                $params = is_array($params) ? $params : [];
                $this->requests[] = compact('method', 'url', 'params');

                if ($method === 'post' && str_ends_with($url, '/v1/checkout/sessions')) {
                    $this->orderId = (int) ($params['client_reference_id'] ?? 0);
                    $this->tenantId = (int) ($params['metadata']['nexus_tenant_id'] ?? 0);
                    $this->expiresAt = (int) ($params['expires_at'] ?? 0);
                    $this->paymentIntentMetadata = $params['payment_intent_data']['metadata'] ?? [];
                    $this->paymentIntentAmount = (int) ($params['line_items'][0]['price_data']['unit_amount'] ?? 0);
                    $this->applicationFeeAmount = (int) ($params['payment_intent_data']['application_fee_amount'] ?? 0);
                    return $this->sessionResponse();
                }
                if ($method === 'post' && str_ends_with($url, '/v1/payment_intents')) {
                    $this->orderId = (int) ($params['metadata']['nexus_order_id'] ?? 0);
                    $this->tenantId = (int) ($params['metadata']['nexus_tenant_id'] ?? 0);
                    $this->paymentIntentMetadata = $params['metadata'] ?? [];
                    $this->paymentIntentAmount = (int) ($params['amount'] ?? 0);
                    $this->applicationFeeAmount = (int) ($params['application_fee_amount'] ?? 0);
                    if ($this->onPaymentIntentCreated !== null) {
                        ($this->onPaymentIntentCreated)();
                    }
                    return [$this->json([
                        'id' => 'pi_test_marketplace_bind_race',
                        'object' => 'payment_intent',
                        'amount' => (int) ($params['amount'] ?? 0),
                        'currency' => (string) ($params['currency'] ?? 'eur'),
                        'status' => 'requires_payment_method',
                        'client_secret' => 'pi_test_marketplace_bind_race_secret',
                        'metadata' => $params['metadata'] ?? [],
                    ]), 200, []];
                }
                if ($method === 'post'
                    && str_contains($url, '/v1/payment_intents/')
                    && str_ends_with($url, '/cancel')) {
                    return [$this->json([
                        'id' => 'pi_test_marketplace_bind_race',
                        'object' => 'payment_intent',
                        'status' => 'canceled',
                    ]), 200, []];
                }
                if ($method === 'get' && str_contains($url, '/v1/checkout/sessions/')) {
                    return $this->sessionResponse();
                }
                if ($method === 'post' && str_ends_with($url, '/expire')) {
                    if ($this->failExpire) {
                        throw new \RuntimeException('The session completed while expiry was attempted.');
                    }
                    $this->sessionStatus = 'expired';
                    return $this->sessionResponse();
                }
                if ($method === 'get' && str_contains($url, '/v1/payment_intents/')) {
                    $metadata = $this->paymentIntentMetadata ?: $this->defaultPaymentMetadata();
                    return [$this->json([
                        'id' => $this->paymentIntentId,
                        'object' => 'payment_intent',
                        'amount' => $this->paymentIntentAmount,
                        'amount_received' => $this->paymentIntentAmount,
                        'currency' => 'eur',
                        'status' => 'succeeded',
                        'client_secret' => 'pi_test_marketplace_bind_race_secret',
                        'metadata' => $metadata,
                        'application_fee_amount' => $this->applicationFeeAmount,
                        'latest_charge' => 'ch_checkout_expiry_paid',
                        'payment_method_types' => ['card'],
                    ]), 200, []];
                }

                throw new \RuntimeException("Unexpected Stripe request: {$method} {$url}");
            }

            /** @return array{0:string,1:int,2:array} */
            private function sessionResponse(): array
            {
                return [$this->json([
                    'id' => 'cs_test_marketplace_expiry',
                    'object' => 'checkout.session',
                    'status' => $this->sessionStatus,
                    'payment_status' => $this->paymentStatus,
                    'payment_intent' => $this->paymentIntentId,
                    'client_reference_id' => (string) $this->orderId,
                    'metadata' => $this->paymentIntentMetadata ?: $this->defaultPaymentMetadata(),
                    'expires_at' => $this->expiresAt ?: now()->addMinutes(31)->getTimestamp(),
                    'url' => 'https://checkout.stripe.test/c/pay/cs_test_marketplace_expiry',
                ]), 200, []];
            }

            /** @param array<string,mixed> $payload */
            private function json(array $payload): string
            {
                return json_encode($payload, JSON_THROW_ON_ERROR);
            }

            /** @return array<string,string> */
            private function defaultPaymentMetadata(): array
            {
                $order = \Illuminate\Support\Facades\DB::table('marketplace_orders')
                    ->where('id', $this->orderId)
                    ->first();

                return [
                    'nexus_type' => 'marketplace',
                    'nexus_order_id' => (string) $this->orderId,
                    'nexus_tenant_id' => (string) $this->tenantId,
                    'nexus_buyer_id' => (string) ($order->buyer_id ?? 0),
                    'nexus_seller_id' => (string) ($order->seller_id ?? 0),
                    'nexus_funds_flow' => 'destination_charge',
                    'nexus_currency' => 'EUR',
                    'nexus_amount_minor' => (string) $this->paymentIntentAmount,
                    'nexus_platform_fee_minor' => (string) $this->applicationFeeAmount,
                    'nexus_seller_payout_minor' => (string) ($this->paymentIntentAmount - $this->applicationFeeAmount),
                ];
            }
        };
        \Stripe\ApiRequestor::setHttpClient($this->stripeHttp);
    }

    protected function tearDown(): void
    {
        \Stripe\ApiRequestor::setHttpClient(null);
        parent::tearDown();
    }

    public function test_checkout_session_is_persisted_and_network_retries_resume_it(): void
    {
        [$order] = $this->makeOrder();

        $url = MarketplacePaymentService::createCheckoutSession(
            $order,
            'https://accessible.example.test/success',
            'https://accessible.example.test/cancel',
        );
        $resumedUrl = MarketplacePaymentService::createCheckoutSession(
            $order->fresh(),
            'https://accessible.example.test/success',
            'https://accessible.example.test/cancel',
        );

        $this->assertSame($url, $resumedUrl);
        $this->assertSame(
            'cs_test_marketplace_expiry',
            DB::table('marketplace_orders')->where('id', $order->id)->value('checkout_session_id'),
        );
        $this->assertGreaterThan(
            now()->addMinutes(30)->getTimestamp(),
            strtotime((string) DB::table('marketplace_orders')->where('id', $order->id)->value('payment_expires_at')),
        );
        $createRequests = array_filter(
            $this->stripeHttp->requests,
            static fn (array $request): bool => $request['method'] === 'post'
                && str_ends_with($request['url'], '/v1/checkout/sessions'),
        );
        $this->assertCount(1, $createRequests);
    }

    public function test_payment_confirmation_uses_provider_bound_fee_after_tenant_config_changes(): void
    {
        [$order] = $this->makeOrder();

        $created = MarketplacePaymentService::createPaymentIntent($order);
        $this->assertSame('pi_test_marketplace_bind_race', $created['payment_intent_id']);
        $this->assertSame('125', $this->stripeHttp->paymentIntentMetadata['nexus_platform_fee_minor']);
        $this->assertSame('2375', $this->stripeHttp->paymentIntentMetadata['nexus_seller_payout_minor']);

        MarketplaceConfigurationService::set(
            MarketplaceConfigurationService::CONFIG_PLATFORM_FEE_PERCENT,
            20,
        );
        $this->stripeHttp->paymentIntentId = 'pi_test_marketplace_bind_race';

        $resumed = MarketplacePaymentService::createPaymentIntent($order->fresh());
        $this->assertSame('pi_test_marketplace_bind_race', $resumed['payment_intent_id']);

        $payment = MarketplacePaymentService::confirmPayment('pi_test_marketplace_bind_race');

        $this->assertSame(1.25, (float) $payment->platform_fee);
        $this->assertSame(23.75, (float) $payment->seller_payout);
        $this->assertSame('destination_charge', $payment->funds_flow);
    }

    public function test_payment_confirmation_rejects_tampered_provider_economics_metadata(): void
    {
        [$order] = $this->makeOrder();
        MarketplacePaymentService::createPaymentIntent($order);
        $this->stripeHttp->paymentIntentId = 'pi_test_marketplace_bind_race';
        $this->stripeHttp->paymentIntentMetadata['nexus_platform_fee_minor'] = '500';
        $this->stripeHttp->paymentIntentMetadata['nexus_seller_payout_minor'] = '2375';

        $this->expectException(\RuntimeException::class);
        $this->expectExceptionMessage(__('api.marketplace_payment_amount_mismatch'));
        MarketplacePaymentService::confirmPayment('pi_test_marketplace_bind_race');
    }

    public function test_expiry_worker_expires_open_checkout_before_releasing_inventory(): void
    {
        [$order] = $this->makeOrder([
            'checkout_session_id' => 'cs_test_marketplace_expiry',
            'payment_expires_at' => now()->subMinute(),
        ]);
        $this->stripeHttp->orderId = (int) $order->id;
        $this->stripeHttp->tenantId = $this->testTenantId;

        $safeToCancel = MarketplacePaymentService::prepareOrderForExpiry($order);

        $this->assertTrue($safeToCancel);
        $this->assertSame('expired', $this->stripeHttp->sessionStatus);
    }

    public function test_expiry_worker_defers_when_checkout_completion_wins_expire_race(): void
    {
        [$order] = $this->makeOrder([
            'checkout_session_id' => 'cs_test_marketplace_expiry',
            'payment_expires_at' => now()->subMinute(),
        ]);
        $this->stripeHttp->orderId = (int) $order->id;
        $this->stripeHttp->tenantId = $this->testTenantId;
        $this->stripeHttp->failExpire = true;

        $this->assertFalse(MarketplacePaymentService::prepareOrderForExpiry($order));
        $this->assertSame('pending_payment', $order->fresh()->status);
    }

    public function test_expiry_worker_reconciles_paid_checkout_instead_of_cancelling(): void
    {
        [$order] = $this->makeOrder([
            'checkout_session_id' => 'cs_test_marketplace_expiry',
            'payment_expires_at' => now()->subMinute(),
        ]);
        $this->stripeHttp->orderId = (int) $order->id;
        $this->stripeHttp->tenantId = $this->testTenantId;
        $this->stripeHttp->sessionStatus = 'complete';
        $this->stripeHttp->paymentStatus = 'paid';
        $this->stripeHttp->paymentIntentId = 'pi_checkout_expiry_paid';

        $this->assertFalse(MarketplacePaymentService::prepareOrderForExpiry($order));
        $this->assertSame('paid', $order->fresh()->status);
        $this->assertDatabaseHas('marketplace_payments', [
            'order_id' => $order->id,
            'stripe_payment_intent_id' => 'pi_checkout_expiry_paid',
            'status' => 'succeeded',
        ]);
    }

    public function test_hosted_checkout_binding_rejects_payment_intent_mode(): void
    {
        [$order] = $this->makeOrder(['stripe_checkout_mode' => 'payment_intent']);

        $this->expectException(\InvalidArgumentException::class);
        $this->expectExceptionMessage(__('api.marketplace_checkout_mode_conflict'));
        MarketplacePaymentService::createCheckoutSession(
            $order,
            'https://accessible.example.test/success',
            'https://accessible.example.test/cancel',
        );
    }

    public function test_payment_intent_binding_rejects_hosted_checkout_mode(): void
    {
        [$order] = $this->makeOrder();
        MarketplacePaymentService::createCheckoutSession(
            $order,
            'https://accessible.example.test/success',
            'https://accessible.example.test/cancel',
        );

        $this->expectException(\InvalidArgumentException::class);
        $this->expectExceptionMessage(__('api.marketplace_checkout_mode_conflict'));
        MarketplacePaymentService::createPaymentIntent($order->fresh());
    }

    public function test_payment_intent_created_after_cancellation_is_cancelled_and_not_bound(): void
    {
        [$order] = $this->makeOrder();
        $this->stripeHttp->onPaymentIntentCreated = static function () use ($order): void {
            DB::table('marketplace_orders')->where('id', $order->id)->update([
                'status' => 'cancelled',
                'cancelled_at' => now(),
                'updated_at' => now(),
            ]);
        };

        try {
            MarketplacePaymentService::createPaymentIntent($order);
            $this->fail('Expected the post-network payable-state check to fail');
        } catch (\InvalidArgumentException $exception) {
            $this->assertSame(
                __('api.marketplace_payment_intent_order_state_required'),
                $exception->getMessage(),
            );
        }

        $this->assertNull(DB::table('marketplace_orders')
            ->where('id', $order->id)
            ->value('payment_intent_id'));
        $cancelRequests = array_filter(
            $this->stripeHttp->requests,
            static fn (array $request): bool => $request['method'] === 'post'
                && str_ends_with($request['url'], '/v1/payment_intents/pi_test_marketplace_bind_race/cancel'),
        );
        $this->assertCount(1, $cancelRequests);
    }

    /** @return array{0:MarketplaceOrder,1:User,2:User} */
    private function makeOrder(array $overrides = []): array
    {
        $buyer = User::factory()->forTenant($this->testTenantId)->create([
            'status' => 'active',
            'preferred_language' => 'en',
        ]);
        $seller = User::factory()->forTenant($this->testTenantId)->create([
            'status' => 'active',
            'preferred_language' => 'en',
        ]);
        DB::table('marketplace_seller_profiles')->insert([
            'tenant_id' => $this->testTenantId,
            'user_id' => $seller->id,
            'seller_type' => 'private',
            'stripe_account_id' => 'acct_checkout_expiry',
            'stripe_onboarding_complete' => true,
            'created_at' => now(),
            'updated_at' => now(),
        ]);
        $id = (int) DB::table('marketplace_orders')->insertGetId(array_merge([
            'tenant_id' => $this->testTenantId,
            'order_number' => 'MKT-CHECKOUT-EXPIRY-' . strtoupper(uniqid('', true)),
            'buyer_id' => $buyer->id,
            'seller_id' => $seller->id,
            'quantity' => 1,
            'unit_price' => 25.00,
            'total_price' => 25.00,
            'currency' => 'EUR',
            'status' => 'pending_payment',
            'payment_expires_at' => now()->addMinutes(20),
            'created_at' => now(),
            'updated_at' => now(),
        ], $overrides));

        return [MarketplaceOrder::withoutGlobalScopes()->findOrFail($id), $buyer, $seller];
    }
}
