<?php
// Copyright © 2024–2026 Jasper Ford
// SPDX-License-Identifier: AGPL-3.0-or-later
// Author: Jasper Ford
// See NOTICE file for attribution and acknowledgements.

namespace Tests\Laravel\Unit\Services\RegionalAnalytics;

use Tests\Laravel\TestCase;
use App\Services\RegionalAnalytics\RegionalAnalyticsBilling;
use App\Core\TenantContext;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Queue;
use Illuminate\Support\Facades\Log;

/**
 * RegionalAnalyticsBillingTest
 *
 * Tests the webhook handlers and no-Stripe-SDK guard branches of
 * RegionalAnalyticsBilling. The createSubscription / cancelSubscription
 * paths that call the Stripe SDK are not exercised here because the
 * Stripe PHP SDK is not installed in the test environment; the service
 * itself documents this as a safe no-op (returns null / false respectively).
 */
class RegionalAnalyticsBillingTest extends TestCase
{
    use DatabaseTransactions;

    private const TENANT_ID = 2;

    private RegionalAnalyticsBilling $billing;

    /** Unique token suffix per test to avoid UNIQUE constraint clashes inside the same transaction. */
    private string $token;

    protected function setUp(): void
    {
        parent::setUp();
        Queue::fake();
        TenantContext::setById(self::TENANT_ID);
        $this->billing = new RegionalAnalyticsBilling();
        $this->token   = substr(bin2hex(random_bytes(6)), 0, 12);
    }

    // ── helpers ───────────────────────────────────────────────────────────────

    /**
     * Insert a minimal regional_analytics_subscriptions row and return its id.
     */
    private function seedSubscription(string $stripeId, string $status = 'trialing'): int
    {
        return (int) DB::table('regional_analytics_subscriptions')->insertGetId([
            'tenant_id'             => self::TENANT_ID,
            'partner_name'          => 'Test Partner',
            'partner_type'          => 'municipality',
            'contact_email'         => 'partner@example.com',
            'plan_tier'             => 'basic',
            'status'                => $status,
            'stripe_subscription_id' => $stripeId,
            'subscription_token'    => $this->token,
            'monthly_price_cents'   => 0,
            'currency'              => 'EUR',
            'created_at'            => now(),
            'updated_at'            => now(),
        ]);
    }

    // ── createSubscription ────────────────────────────────────────────────────

    public function test_createSubscription_returns_null_when_stripe_secret_empty(): void
    {
        // Force the secret key to empty so createSubscription short-circuits
        // (even if the SDK is installed, a blank secret is a safe no-op).
        config(['services.stripe.secret' => '']);
        putenv('STRIPE_SECRET_KEY=');
        $_ENV['STRIPE_SECRET_KEY'] = '';

        $result = $this->billing->createSubscription(1, 'pro', 'test@example.com');

        $this->assertNull($result);
    }

    // ── cancelSubscription ────────────────────────────────────────────────────

    public function test_cancelSubscription_returns_false_when_stripe_secret_empty(): void
    {
        // No secret → cancelSubscription must return false without network call.
        config(['services.stripe.secret' => '']);
        putenv('STRIPE_SECRET_KEY=');
        $_ENV['STRIPE_SECRET_KEY'] = '';

        $result = $this->billing->cancelSubscription('sub_testABC');

        $this->assertFalse($result);
    }

    public function test_cancelSubscription_returns_false_for_empty_id(): void
    {
        // Empty stripe id is rejected before secret / SDK check.
        $result = $this->billing->cancelSubscription('');

        $this->assertFalse($result);
    }

    // ── handleInvoicePaid ─────────────────────────────────────────────────────

    public function test_handleInvoicePaid_sets_status_to_active(): void
    {
        $stripeId = 'sub_inv_' . $this->token;
        $id = $this->seedSubscription($stripeId, 'trialing');

        $event = [
            'data' => [
                'object' => [
                    'subscription' => $stripeId,
                    'period_start' => mktime(0, 0, 0, 1, 1, 2026),
                    'period_end'   => mktime(0, 0, 0, 2, 1, 2026),
                ],
            ],
        ];

        $this->billing->handleInvoicePaid($event);

        $row = DB::table('regional_analytics_subscriptions')->where('id', $id)->first();
        $this->assertNotNull($row);
        $this->assertSame('active', $row->status);
    }

    public function test_handleInvoicePaid_records_period_dates(): void
    {
        $stripeId = 'sub_period_' . $this->token;
        $id = $this->seedSubscription($stripeId, 'trialing');

        $start = mktime(0, 0, 0, 1, 1, 2026);
        $end   = mktime(0, 0, 0, 2, 1, 2026);

        $event = [
            'data' => [
                'object' => [
                    'subscription' => $stripeId,
                    'period_start' => $start,
                    'period_end'   => $end,
                ],
            ],
        ];

        $this->billing->handleInvoicePaid($event);

        $row = DB::table('regional_analytics_subscriptions')->where('id', $id)->first();
        $this->assertNotNull($row->current_period_start);
        $this->assertNotNull($row->current_period_end);
        $this->assertStringContainsString('2026-01-01', $row->current_period_start);
        $this->assertStringContainsString('2026-02-01', $row->current_period_end);
    }

    public function test_handleInvoicePaid_is_noop_when_no_subscription_key(): void
    {
        // If the event payload lacks 'subscription', the method must not throw.
        $before = DB::table('regional_analytics_subscriptions')
            ->where('tenant_id', self::TENANT_ID)
            ->count();

        $this->billing->handleInvoicePaid(['data' => ['object' => []]]);

        $after = DB::table('regional_analytics_subscriptions')
            ->where('tenant_id', self::TENANT_ID)
            ->count();

        $this->assertSame($before, $after);
    }

    // ── handleSubscriptionUpdated ─────────────────────────────────────────────

    public function test_handleSubscriptionUpdated_maps_active(): void
    {
        $stripeId = 'sub_upd_active_' . $this->token;
        $id = $this->seedSubscription($stripeId, 'trialing');

        $this->billing->handleSubscriptionUpdated([
            'data' => ['object' => ['id' => $stripeId, 'status' => 'active']],
        ]);

        $row = DB::table('regional_analytics_subscriptions')->where('id', $id)->first();
        $this->assertSame('active', $row->status);
    }

    public function test_handleSubscriptionUpdated_maps_canceled_to_cancelled(): void
    {
        $stripeId = 'sub_upd_canceled_' . $this->token;
        $id = $this->seedSubscription($stripeId, 'active');

        $this->billing->handleSubscriptionUpdated([
            'data' => ['object' => ['id' => $stripeId, 'status' => 'canceled']],
        ]);

        $row = DB::table('regional_analytics_subscriptions')->where('id', $id)->first();
        // Stripe uses 'canceled' (1 l); our enum uses 'cancelled' (2 l).
        $this->assertSame('cancelled', $row->status);
    }

    public function test_handleSubscriptionUpdated_maps_incomplete_expired_to_cancelled(): void
    {
        $stripeId = 'sub_upd_ie_' . $this->token;
        $id = $this->seedSubscription($stripeId, 'trialing');

        $this->billing->handleSubscriptionUpdated([
            'data' => ['object' => ['id' => $stripeId, 'status' => 'incomplete_expired']],
        ]);

        $row = DB::table('regional_analytics_subscriptions')->where('id', $id)->first();
        $this->assertSame('cancelled', $row->status);
    }

    public function test_handleSubscriptionUpdated_maps_past_due(): void
    {
        $stripeId = 'sub_upd_pd_' . $this->token;
        $id = $this->seedSubscription($stripeId, 'active');

        $this->billing->handleSubscriptionUpdated([
            'data' => ['object' => ['id' => $stripeId, 'status' => 'past_due']],
        ]);

        $row = DB::table('regional_analytics_subscriptions')->where('id', $id)->first();
        $this->assertSame('past_due', $row->status);
    }

    public function test_handleSubscriptionUpdated_unknown_stripe_status_maps_to_past_due(): void
    {
        $stripeId = 'sub_upd_unk_' . $this->token;
        $id = $this->seedSubscription($stripeId, 'active');

        $this->billing->handleSubscriptionUpdated([
            'data' => ['object' => ['id' => $stripeId, 'status' => 'unknown_future_status']],
        ]);

        $row = DB::table('regional_analytics_subscriptions')->where('id', $id)->first();
        $this->assertSame('past_due', $row->status);
    }

    public function test_handleSubscriptionUpdated_is_noop_when_no_stripe_id(): void
    {
        // Payload missing 'id' should not throw.
        $before = DB::table('regional_analytics_subscriptions')
            ->where('tenant_id', self::TENANT_ID)
            ->count();

        $this->billing->handleSubscriptionUpdated(['data' => ['object' => []]]);

        $after = DB::table('regional_analytics_subscriptions')
            ->where('tenant_id', self::TENANT_ID)
            ->count();

        $this->assertSame($before, $after);
    }
}
