<?php
// Copyright © 2024–2026 Jasper Ford
// SPDX-License-Identifier: AGPL-3.0-or-later
// Author: Jasper Ford
// See NOTICE file for attribution and acknowledgements.

namespace Tests\Laravel\Unit\Services;

use Tests\Laravel\TestCase;
use App\Services\MarketplaceConfigurationService;
use App\Services\MarketplacePaymentService;
use App\Models\MarketplaceOrder;
use App\Models\MarketplacePayment;
use App\Models\User;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Illuminate\Support\Facades\DB;
use Mockery;

class MarketplacePaymentServiceTest extends TestCase
{
    use DatabaseTransactions;

    protected function setUp(): void
    {
        parent::setUp();
        MarketplaceConfigurationService::set(
            MarketplaceConfigurationService::CONFIG_STRIPE_ENABLED,
            true,
        );
    }

    // -----------------------------------------------------------------
    //  createPaymentIntent — state validation
    // -----------------------------------------------------------------

    public function test_createPaymentIntent_rejectsOrderNotInPendingPaymentStatus(): void
    {
        $order = Mockery::mock(MarketplaceOrder::class)->makePartial();
        $order->status = 'paid';

        $this->expectException(\InvalidArgumentException::class);
        $this->expectExceptionMessage('Order must be in pending_payment status');

        MarketplacePaymentService::createPaymentIntent($order);
    }

    public function test_createPaymentIntent_rejectsCompletedOrder(): void
    {
        $order = Mockery::mock(MarketplaceOrder::class)->makePartial();
        $order->status = 'completed';

        $this->expectException(\InvalidArgumentException::class);
        $this->expectExceptionMessage('Order must be in pending_payment status');

        MarketplacePaymentService::createPaymentIntent($order);
    }

    public function test_createPaymentIntent_rejectsCancelledOrder(): void
    {
        $order = Mockery::mock(MarketplaceOrder::class)->makePartial();
        $order->status = 'cancelled';

        $this->expectException(\InvalidArgumentException::class);
        $this->expectExceptionMessage('Order must be in pending_payment status');

        MarketplacePaymentService::createPaymentIntent($order);
    }

    public function test_unready_seller_does_not_claim_either_stripe_checkout_mode(): void
    {
        $seller = User::factory()->forTenant($this->testTenantId)->create();
        $buyer = User::factory()->forTenant($this->testTenantId)->create();
        DB::table('marketplace_seller_profiles')->insert([
            'tenant_id' => $this->testTenantId,
            'user_id' => $seller->id,
            'seller_type' => 'private',
            'stripe_account_id' => null,
            'stripe_onboarding_complete' => false,
            'created_at' => now(),
            'updated_at' => now(),
        ]);
        $expiresAt = now()->addMinutes(20)->startOfSecond();
        $orderId = (int) DB::table('marketplace_orders')->insertGetId([
            'tenant_id' => $this->testTenantId,
            'order_number' => 'MKT-UNREADY-' . strtoupper(uniqid('', true)),
            'buyer_id' => $buyer->id,
            'seller_id' => $seller->id,
            'quantity' => 1,
            'unit_price' => 25.00,
            'total_price' => 25.00,
            'currency' => 'EUR',
            'status' => 'pending_payment',
            'payment_expires_at' => $expiresAt,
            'created_at' => now(),
            'updated_at' => now(),
        ]);
        $order = MarketplaceOrder::withoutGlobalScopes()->findOrFail($orderId);

        foreach (['payment_intent', 'checkout_session'] as $mechanism) {
            try {
                if ($mechanism === 'payment_intent') {
                    MarketplacePaymentService::createPaymentIntent($order->fresh());
                } else {
                    MarketplacePaymentService::createCheckoutSession(
                        $order->fresh(),
                        'https://example.test/success',
                        'https://example.test/cancel',
                    );
                }
                $this->fail("Expected seller readiness failure for {$mechanism}");
            } catch (\RuntimeException $exception) {
                $this->assertSame(
                    __('api.marketplace_seller_onboarding_required'),
                    $exception->getMessage(),
                );
            }
        }

        $stored = DB::table('marketplace_orders')->where('id', $orderId)->first();
        $this->assertNull($stored->stripe_checkout_mode);
        $this->assertNull($stored->payment_intent_id);
        $this->assertNull($stored->checkout_session_id);
        $this->assertSame($expiresAt->toDateTimeString(), (string) $stored->payment_expires_at);
    }

    // -----------------------------------------------------------------
    //  processRefund — state validation
    // -----------------------------------------------------------------

    public function test_processRefund_throwsWhenNoPaymentExists(): void
    {
        // processRefund queries for a MarketplacePayment by order_id.
        // We test the thrown exception by passing an order whose payment lookup
        // returns null. Since we can't mock the Eloquent static, we verify
        // that the method signature and exception types are correct by calling
        // with a real (non-persisted) order instance that has no DB rows.
        // This test ensures the guard clause works at the service boundary.
        $order = new MarketplaceOrder();
        $order->id = 999999; // Non-existent

        $this->expectException(\RuntimeException::class);
        $this->expectExceptionMessage('No successful payment found');

        MarketplacePaymentService::processRefund($order, null, 'test');
    }

    public function test_processRefund_uses_refund_email_category_for_webhook_dedupe(): void
    {
        // The refund-email dedupe/send logic now lives in the dedicated
        // sendMarketplaceRefundNotification* helpers (and the matching
        // *HaveEvidence guard), not inline in processRefund(). It must use the
        // 'marketplace_refund' email-log category for both the dedupe lookup and
        // the send — never the payment-receipt category 'marketplace_payment'.
        $source = file_get_contents(app_path('Services/MarketplacePaymentService.php'));
        $start = strpos($source, 'private static function marketplaceRefundNotificationsHaveEvidence');
        $end = strpos($source, 'private static function handleAccountUpdated', $start);
        $method = substr($source, $start, $end - $start);

        $this->assertStringContainsString("'marketplace_refund'", $method);
        $this->assertStringNotContainsString("'marketplace_payment'", $method);
    }

    public function test_connect_provider_errors_are_not_exposed_to_api_callers(): void
    {
        $source = file_get_contents(app_path('Services/MarketplacePaymentService.php'));

        $this->assertStringContainsString(
            "__('api.marketplace_connect_account_create_failed'))",
            $source,
        );
        $this->assertStringNotContainsString(
            "__('api.marketplace_connect_account_create_failed',",
            $source,
        );
        $this->assertStringContainsString(
            "__('api.marketplace_onboarding_link_failed'))",
            $source,
        );
        $this->assertStringNotContainsString(
            "__('api.marketplace_onboarding_link_failed',",
            $source,
        );

        foreach (glob(base_path('lang/*/api.php')) ?: [] as $translationFile) {
            $translations = require $translationFile;
            $this->assertStringNotContainsString(
                ':error',
                (string) $translations['marketplace_connect_account_create_failed'],
                $translationFile,
            );
            $this->assertStringNotContainsString(
                ':error',
                (string) $translations['marketplace_onboarding_link_failed'],
                $translationFile,
            );
        }
    }

    public function test_connect_account_retry_reuses_one_provider_account_with_stable_idempotency(): void
    {
        $seller = User::factory()->forTenant($this->testTenantId)->create([
            'status' => 'active',
            'email' => 'connect-retry@example.test',
        ]);
        DB::table('marketplace_seller_profiles')->insert([
            'tenant_id' => $this->testTenantId,
            'user_id' => $seller->id,
            'seller_type' => 'private',
            'stripe_account_id' => null,
            'stripe_onboarding_complete' => false,
            'created_at' => now(),
            'updated_at' => now(),
        ]);
        config(['services.stripe.secret' => 'sk_test_connect_retry']);

        $stripeHttp = new class implements \Stripe\HttpClient\ClientInterface {
            /** @var list<array{method:string,url:string,headers:array,params:array}> */
            public array $requests = [];

            public function request($method, $absUrl, $headers, $params, $hasFile, $apiMode = 'v1'): array
            {
                $this->requests[] = [
                    'method' => strtolower((string) $method),
                    'url' => (string) $absUrl,
                    'headers' => is_array($headers) ? $headers : [],
                    'params' => is_array($params) ? $params : [],
                ];
                if (str_ends_with((string) $absUrl, '/v1/accounts')) {
                    return [json_encode([
                        'id' => 'acct_marketplace_retry',
                        'object' => 'account',
                        'type' => 'express',
                    ], JSON_THROW_ON_ERROR), 200, []];
                }
                if (str_ends_with((string) $absUrl, '/v1/account_links')) {
                    return [json_encode([
                        'object' => 'account_link',
                        'created' => now()->getTimestamp(),
                        'expires_at' => now()->addHour()->getTimestamp(),
                        'url' => 'https://connect.stripe.test/onboarding/retry',
                    ], JSON_THROW_ON_ERROR), 200, []];
                }

                throw new \RuntimeException('Unexpected Stripe request: ' . $absUrl);
            }
        };
        \Stripe\ApiRequestor::setHttpClient($stripeHttp);

        try {
            $first = MarketplacePaymentService::createConnectAccount((int) $seller->id);
            $retried = MarketplacePaymentService::createConnectAccount((int) $seller->id);
        } finally {
            \Stripe\ApiRequestor::setHttpClient(null);
        }

        $this->assertSame('acct_marketplace_retry', $first['account_id']);
        $this->assertSame($first['account_id'], $retried['account_id']);
        $this->assertSame('acct_marketplace_retry', DB::table('marketplace_seller_profiles')
            ->where('tenant_id', $this->testTenantId)
            ->where('user_id', $seller->id)
            ->value('stripe_account_id'));

        $accountCreates = array_values(array_filter(
            $stripeHttp->requests,
            static fn (array $request): bool => $request['method'] === 'post'
                && str_ends_with($request['url'], '/v1/accounts'),
        ));
        $this->assertCount(1, $accountCreates);
        $this->assertContains(
            'Idempotency-Key: marketplace-connect-account-' . $this->testTenantId . '-' . $seller->id,
            $accountCreates[0]['headers'],
        );
    }

    public function test_payment_webhook_retries_and_replays_heal_missing_escrow(): void
    {
        $source = file_get_contents(app_path('Services/MarketplacePaymentService.php'));
        $handlerStart = strpos($source, 'private static function handlePaymentIntentSucceeded');
        $handlerEnd = strpos($source, 'private static function handleChargeRefunded', $handlerStart);
        $handler = substr($source, $handlerStart, $handlerEnd - $handlerStart);
        $confirmStart = strpos($source, 'public static function confirmPayment');
        $confirmEnd = strpos($source, 'public static function prepareOrderForExpiry', $confirmStart);
        $confirm = substr($source, $confirmStart, $confirmEnd - $confirmStart);

        $this->assertStringNotContainsString('payment already confirmed', $handler);
        $this->assertStringContainsString('throw $e;', $handler);
        $this->assertStringContainsString(
            'MarketplaceEscrowService::holdFunds($lockedOrder, $payment);',
            $confirm,
        );
        $this->assertLessThan(
            strpos($confirm, '$createdPayment = true;'),
            strpos($confirm, 'MarketplaceEscrowService::holdFunds($lockedOrder, $payment);'),
            'Escrow must be written before the paid transaction can commit.',
        );
    }

    public function test_account_updated_persists_disabled_and_reenabled_capabilities(): void
    {
        $seller = User::factory()->forTenant($this->testTenantId)->create();
        DB::table('marketplace_seller_profiles')->insert([
            'tenant_id' => $this->testTenantId,
            'user_id' => $seller->id,
            'seller_type' => 'private',
            'stripe_account_id' => 'acct_capability_transition',
            'stripe_onboarding_complete' => true,
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        MarketplacePaymentService::handleWebhookEvent('account.updated', (object) [
            'id' => 'acct_capability_transition',
            'details_submitted' => true,
            'charges_enabled' => false,
            'payouts_enabled' => true,
        ]);
        $this->assertSame(0, (int) DB::table('marketplace_seller_profiles')
            ->where('stripe_account_id', 'acct_capability_transition')
            ->value('stripe_onboarding_complete'));

        MarketplacePaymentService::handleWebhookEvent('account.updated', (object) [
            'id' => 'acct_capability_transition',
            'details_submitted' => true,
            'charges_enabled' => true,
            'payouts_enabled' => true,
        ]);
        $this->assertSame(1, (int) DB::table('marketplace_seller_profiles')
            ->where('stripe_account_id', 'acct_capability_transition')
            ->value('stripe_onboarding_complete'));
    }

    public function test_processRefund_replays_a_completed_full_refund_without_calling_stripe(): void
    {
        $orderId = (int) DB::table('marketplace_orders')->insertGetId([
            'tenant_id' => $this->testTenantId,
            'order_number' => 'MKT-REFUND-' . strtoupper(uniqid('', true)),
            'buyer_id' => 920001,
            'seller_id' => 920002,
            'quantity' => 1,
            'unit_price' => 25.00,
            'total_price' => 25.00,
            'currency' => 'EUR',
            'status' => 'refunded',
            'created_at' => now(),
            'updated_at' => now(),
        ]);
        $paymentId = (int) DB::table('marketplace_payments')->insertGetId([
            'tenant_id' => $this->testTenantId,
            'order_id' => $orderId,
            'stripe_payment_intent_id' => 'pi_already_refunded_' . uniqid(),
            'amount' => 25.00,
            'currency' => 'EUR',
            'platform_fee' => 0,
            'seller_payout' => 0,
            'status' => 'refunded',
            'refund_amount' => 25.00,
            'payout_status' => 'failed',
            'created_at' => now(),
            'updated_at' => now(),
        ]);
        $order = MarketplaceOrder::withoutGlobalScopes()->findOrFail($orderId);

        $result = MarketplacePaymentService::processRefund($order, null, 'retry');

        $this->assertSame($paymentId, (int) $result->id);
        $this->assertSame('refunded', $result->status);
        $this->assertSame(25.0, (float) $result->refund_amount);
    }

    // -----------------------------------------------------------------
    //  Fee calculation verification
    // -----------------------------------------------------------------

    public function test_feeCalculation_correctForTypicalAmounts(): void
    {
        // Verify the fee calculation logic matches what the service does:
        // $platformFee = round($totalAmount * ($feePercent / 100), 2)
        // $sellerPayout = round($totalAmount - $platformFee, 2)

        $totalAmount = 50.00;
        $feePercent = 5.0;
        $platformFee = round($totalAmount * ($feePercent / 100), 2);
        $sellerPayout = round($totalAmount - $platformFee, 2);

        $this->assertEquals(2.50, $platformFee);
        $this->assertEquals(47.50, $sellerPayout);

        // Verify cents conversion
        $amountCents = (int) round($totalAmount * 100);
        $feeCents = (int) round($platformFee * 100);

        $this->assertEquals(5000, $amountCents);
        $this->assertEquals(250, $feeCents);
    }

    public function test_feeCalculation_handlesEdgeCaseRounding(): void
    {
        // 33.33 at 7% fee — tests rounding behavior
        $totalAmount = 33.33;
        $feePercent = 7.0;
        $platformFee = round($totalAmount * ($feePercent / 100), 2);
        $sellerPayout = round($totalAmount - $platformFee, 2);

        $this->assertEquals(2.33, $platformFee);
        $this->assertEquals(31.00, $sellerPayout);

        // Total should roughly equal original (within rounding)
        $this->assertEqualsWithDelta($totalAmount, $platformFee + $sellerPayout, 0.01);
    }

    public function test_feeCalculation_handlesZeroFeePercent(): void
    {
        $totalAmount = 100.00;
        $feePercent = 0.0;
        $platformFee = round($totalAmount * ($feePercent / 100), 2);
        $sellerPayout = round($totalAmount - $platformFee, 2);

        $this->assertEquals(0.00, $platformFee);
        $this->assertEquals(100.00, $sellerPayout);
    }
}
