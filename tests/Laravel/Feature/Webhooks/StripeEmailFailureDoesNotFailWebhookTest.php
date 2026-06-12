<?php
// Copyright © 2024–2026 Jasper Ford
// SPDX-License-Identifier: AGPL-3.0-or-later
// Author: Jasper Ford
// See NOTICE file for attribution and acknowledgements.

namespace Tests\Laravel\Feature\Webhooks;

use App\Core\TenantContext;
use App\Services\StripeDonationService;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Illuminate\Support\Facades\DB;
use Tests\Laravel\TestCase;

/**
 * Regression: Stripe webhook handlers used to throw RuntimeException when a
 * post-payment notification EMAIL failed (e.g. recipient on the suppression
 * list) even though the financial write had already committed. The exception
 * bubbled to StripeWebhookController which returned HTTP 500, making Stripe
 * retry the event for up to ~3 days per event and risking endpoint
 * disablement — all for an email that can never be delivered.
 *
 * A suppressed (hard-bounced) donor address is the realistic permanent
 * trigger: Mailer::send() returns false for suppressed recipients by design.
 * Email failures must be logged, never escalated to a webhook failure.
 */
class StripeEmailFailureDoesNotFailWebhookTest extends TestCase
{
    use DatabaseTransactions;

    private int $tenantId;
    private int $userId;

    protected function setUp(): void
    {
        parent::setUp();
        $this->tenantId = (int) DB::table('tenants')->where('is_active', 1)->orderBy('id')->value('id');
        $this->userId = (int) DB::table('users')->where('tenant_id', $this->tenantId)->orderBy('id')->value('id');
        if (!$this->tenantId || !$this->userId) {
            $this->markTestSkipped('Test DB lacks an active tenant/user');
        }
        TenantContext::setById($this->tenantId);
    }

    protected function tearDown(): void
    {
        TenantContext::reset();
        parent::tearDown();
    }

    public function test_donation_payment_succeeded_with_suppressed_donor_email_does_not_throw(): void
    {
        $suppressed = 'suppressed-donor-' . uniqid() . '@example.test';

        DB::table('email_suppression')->insert([
            'email' => $suppressed,
            'reason' => 'bounce',
            'suppressed_at' => now(),
            'created_at' => now(),
        ]);

        $donationId = (int) DB::table('vol_donations')->insertGetId([
            'tenant_id' => $this->tenantId,
            'user_id' => $this->userId,
            'donor_email' => $suppressed,
            'donor_name' => 'Suppressed Donor',
            'amount' => 10.00,
            'status' => 'pending',
            'stripe_payment_intent_id' => 'pi_test_' . uniqid(),
            'created_at' => now(),
        ]);

        $donation = DB::table('vol_donations')->where('id', $donationId)->first();

        $paymentIntent = (object) [
            'id' => $donation->stripe_payment_intent_id,
            'metadata' => (object) ['tenant_id' => (string) $this->tenantId],
        ];

        // Must complete the donation and return normally — never throw on a
        // failed receipt email (the webhook would 500 and Stripe would retry
        // this permanently-failing event for days).
        StripeDonationService::handlePaymentSucceeded($paymentIntent);

        $row = DB::table('vol_donations')->where('id', $donationId)->first();
        $this->assertSame('completed', $row->status, 'Donation must be completed despite email failure');

        // Replay (Stripe redelivery): already-completed branch must also not throw.
        StripeDonationService::handlePaymentSucceeded($paymentIntent);
        $this->assertTrue(true, 'Replay with suppressed recipient did not throw');
    }
}
