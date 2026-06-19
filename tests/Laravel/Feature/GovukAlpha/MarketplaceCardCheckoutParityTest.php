<?php
// Copyright © 2024–2026 Jasper Ford
// SPDX-License-Identifier: AGPL-3.0-or-later
// Author: Jasper Ford
// See NOTICE file for attribution and acknowledgements.

namespace Tests\Laravel\Feature\GovukAlpha;

use App\Core\TenantContext;
use App\Models\User;
use App\Services\MarketplacePaymentService;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Illuminate\Support\Facades\DB;
use Laravel\Sanctum\Sanctum;
use Tests\Laravel\TestCase;

/**
 * Feature tests for the accessible (GOV.UK) no-JS Stripe card-checkout flow.
 *
 * These cover the deterministic, security-critical parts that do NOT touch the
 * Stripe API: the pre-checkout guards (auth, buyer-ownership, pending-only,
 * money-only, seller-onboarded) and the webhook reconciliation guards. The
 * happy path (real Checkout Session creation + checkout.session.completed →
 * paid) is exercised against Stripe TEST MODE (documented in the commit), since
 * it requires live Stripe keys that are not present in CI.
 */
class MarketplaceCardCheckoutParityTest extends TestCase
{
    use DatabaseTransactions;

    protected function setUp(): void
    {
        parent::setUp();
        $this->app['auth']->forgetGuards();
        foreach (['HTTP_X_TENANT_ID', 'HTTP_X_TENANT_SLUG', 'HTTP_AUTHORIZATION', 'REDIRECT_HTTP_AUTHORIZATION'] as $k) {
            unset($_SERVER[$k]);
        }
        TenantContext::reset();
        TenantContext::setById($this->testTenantId);

        $row = DB::table('tenants')->where('id', $this->testTenantId)->value('features');
        $current = $row ? (json_decode($row, true) ?: []) : [];
        $current['marketplace'] = true;
        DB::table('tenants')->where('id', $this->testTenantId)->update(['features' => json_encode($current)]);
        TenantContext::reset();
        TenantContext::setById($this->testTenantId);
    }

    private function user(array $o = []): User
    {
        $u = User::factory()->forTenant($this->testTenantId)->create(array_merge(['status' => 'active', 'is_approved' => true], $o));
        Sanctum::actingAs($u, ['*']);
        return $u;
    }

    private function seedOrder(int $buyerId, int $sellerId, array $o = []): int
    {
        return (int) DB::table('marketplace_orders')->insertGetId(array_merge([
            'tenant_id'    => $this->testTenantId,
            'order_number' => 'CARD-' . uniqid(),
            'buyer_id'     => $buyerId,
            'seller_id'    => $sellerId,
            'quantity'     => 1,
            'unit_price'   => 25.00,
            'total_price'  => 25.00,
            'currency'     => 'EUR',
            'status'       => 'pending_payment',
            'created_at'   => now(),
            'updated_at'   => now(),
        ], $o));
    }

    public function test_card_pay_requires_auth(): void
    {
        $this->post("/{$this->testTenantSlug}/alpha/marketplace/orders/1/pay")
            ->assertRedirectContains('/alpha/login');
    }

    public function test_card_pay_forbidden_for_non_buyer(): void
    {
        $buyer = $this->user();
        $seller = $this->user();
        $orderId = $this->seedOrder($buyer->id, $seller->id);

        $stranger = $this->user();
        Sanctum::actingAs($stranger, ['*']);
        $this->post("/{$this->testTenantSlug}/alpha/marketplace/orders/{$orderId}/pay")->assertStatus(403);
    }

    public function test_card_pay_rejects_non_pending_order(): void
    {
        $buyer = $this->user();
        $seller = $this->user();
        $orderId = $this->seedOrder($buyer->id, $seller->id, ['status' => 'paid']);
        Sanctum::actingAs($buyer, ['*']);

        $this->post("/{$this->testTenantSlug}/alpha/marketplace/orders/{$orderId}/pay")
            ->assertRedirectContains('status=pay-not-pending');
    }

    public function test_card_pay_rejects_zero_price_order(): void
    {
        $buyer = $this->user();
        $seller = $this->user();
        $orderId = $this->seedOrder($buyer->id, $seller->id, ['total_price' => 0, 'unit_price' => 0]);
        Sanctum::actingAs($buyer, ['*']);

        $this->post("/{$this->testTenantSlug}/alpha/marketplace/orders/{$orderId}/pay")
            ->assertRedirectContains('status=pay-not-required');
    }

    public function test_card_pay_unavailable_when_seller_not_onboarded(): void
    {
        $buyer = $this->user();
        $seller = $this->user();
        // No MarketplaceSellerProfile → createCheckoutSession throws BEFORE any
        // Stripe API call, so this is hermetic.
        $orderId = $this->seedOrder($buyer->id, $seller->id);
        Sanctum::actingAs($buyer, ['*']);

        $this->post("/{$this->testTenantSlug}/alpha/marketplace/orders/{$orderId}/pay")
            ->assertRedirectContains('status=pay-unavailable');
    }

    public function test_webhook_ignores_non_marketplace_session(): void
    {
        $session = (object) [
            'id' => 'cs_test_x',
            'payment_status' => 'paid',
            'payment_intent' => 'pi_test_x',
            'metadata' => (object) ['nexus_type' => 'subscription'],
        ];
        // Must be a no-op (no exception, no Stripe call).
        MarketplacePaymentService::handleWebhookEvent('checkout.session.completed', $session);
        $this->assertTrue(true);
    }

    public function test_webhook_noop_when_order_missing(): void
    {
        $session = (object) [
            'id' => 'cs_test_y',
            'payment_status' => 'paid',
            'payment_intent' => 'pi_test_y',
            'client_reference_id' => '99999999',
            'metadata' => (object) ['nexus_type' => 'marketplace', 'nexus_order_id' => '99999999'],
        ];
        // No local order → logged + returns, never reaching confirmPayment/Stripe.
        MarketplacePaymentService::handleWebhookEvent('checkout.session.completed', $session);
        $this->assertTrue(true);
    }

    public function test_webhook_noop_when_unpaid(): void
    {
        $session = (object) [
            'id' => 'cs_test_z',
            'payment_status' => 'unpaid',
            'payment_intent' => 'pi_test_z',
            'metadata' => (object) ['nexus_type' => 'marketplace', 'nexus_order_id' => '1'],
        ];
        MarketplacePaymentService::handleWebhookEvent('checkout.session.completed', $session);
        $this->assertTrue(true);
    }

    public function test_webhook_throws_to_retry_when_paid_but_pi_not_linked(): void
    {
        // A genuine paid marketplace order whose PaymentIntent is not yet linked
        // to the session (Stripe async lag) must THROW so the controller marks the
        // event failed and Stripe retries — never silently drop a paid order.
        $session = (object) [
            'id' => 'cs_test_w',
            'payment_status' => 'paid',
            'payment_intent' => null,
            'metadata' => (object) ['nexus_type' => 'marketplace', 'nexus_order_id' => '5'],
        ];
        $this->expectException(\RuntimeException::class);
        MarketplacePaymentService::handleWebhookEvent('checkout.session.completed', $session);
    }

    public function test_webhook_routes_marketplace_by_order_id_without_nexus_type(): void
    {
        // nexus_type missing but nexus_order_id present → still treated as
        // marketplace (defence-in-depth), and a missing order is a safe no-op.
        $session = (object) [
            'id' => 'cs_test_v',
            'payment_status' => 'paid',
            'payment_intent' => 'pi_test_v',
            'metadata' => (object) ['nexus_order_id' => '88888888'],
        ];
        MarketplacePaymentService::handleWebhookEvent('checkout.session.completed', $session);
        $this->assertTrue(true);
    }
}
