<?php
// Copyright © 2024–2026 Jasper Ford
// SPDX-License-Identifier: AGPL-3.0-or-later
// Author: Jasper Ford
// See NOTICE file for attribution and acknowledgements.

namespace Tests\Laravel\Feature\Console;

use Illuminate\Foundation\Testing\DatabaseTransactions;
use Illuminate\Support\Facades\DB;
use Tests\Laravel\TestCase;

/**
 * B3 regression lock — stuck-Stripe-webhook alerter.
 *
 * A webhook event left in status='failed' means a payment/refund Stripe gave up
 * retrying (~3 days) — silent money-state drift. `stripe:check-stuck-webhooks`
 * must alert (non-zero exit + "STRIPE WEBHOOK ALERT") on a failed row older than
 * the threshold, and stay green for a recent failure or a resolved/processed row.
 */
class StuckStripeWebhookAlertTest extends TestCase
{
    use DatabaseTransactions;

    protected function setUp(): void
    {
        parent::setUp();

        // Clear the slate (rolled back by DatabaseTransactions) so pre-existing
        // failed rows in the shared nexus_test DB don't skew the assertions.
        DB::table('stripe_webhook_events')->delete();
    }

    public function test_alerts_on_failed_event_older_than_threshold(): void
    {
        DB::table('stripe_webhook_events')->insert([
            'event_id'     => 'evt_stuck_' . uniqid(),
            'event_type'   => 'payment_intent.succeeded',
            'status'       => 'failed',
            'processed_at' => now()->subHours(8),
        ]);

        $this->artisan('stripe:check-stuck-webhooks', ['--hours' => 6])
            ->expectsOutputToContain('STRIPE WEBHOOK ALERT')
            ->assertExitCode(1);
    }

    public function test_does_not_alert_on_recent_failure_or_resolved_event(): void
    {
        // A recent failure (within threshold) — Stripe may still be retrying.
        DB::table('stripe_webhook_events')->insert([
            'event_id'     => 'evt_recent_' . uniqid(),
            'event_type'   => 'payment_intent.succeeded',
            'status'       => 'failed',
            'processed_at' => now()->subHour(),
        ]);
        // An old but resolved event must be ignored.
        DB::table('stripe_webhook_events')->insert([
            'event_id'     => 'evt_done_' . uniqid(),
            'event_type'   => 'invoice.paid',
            'status'       => 'processed',
            'processed_at' => now()->subHours(8),
        ]);

        $this->artisan('stripe:check-stuck-webhooks', ['--hours' => 6])
            ->expectsOutputToContain('healthy')
            ->assertExitCode(0);
    }
}
