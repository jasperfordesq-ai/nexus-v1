<?php
// Copyright © 2024–2026 Jasper Ford
// SPDX-License-Identifier: AGPL-3.0-or-later
// Author: Jasper Ford
// See NOTICE file for attribution and acknowledgements.

declare(strict_types=1);

namespace Tests\Laravel\Unit\Console;

use Illuminate\Foundation\Testing\DatabaseTransactions;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;
use Tests\Laravel\TestCase;

/**
 * Tests for stripe:check-stuck-webhooks console command.
 *
 * Uses unique tenant id 99706 for isolation.
 * stripe_webhook_events is not tenant-scoped, so we use unique event_ids.
 */
class StuckStripeWebhookCheckTest extends TestCase
{
    use DatabaseTransactions;

    private const TENANT_ID = 99706;

    /** Unique prefix for event_ids in this test run to avoid collisions */
    private string $prefix;

    protected function setUp(): void
    {
        parent::setUp();

        // Insert the isolation tenant (idempotent within transaction).
        DB::table('tenants')->insertOrIgnore([
            'id'         => self::TENANT_ID,
            'name'       => 'Test Stripe Webhook Tenant',
            'slug'       => 'test-stripe-webhook-' . self::TENANT_ID,
            'is_active'  => 1,
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        \App\Core\TenantContext::setById(self::TENANT_ID);

        // Fake all HTTP to prevent real Slack/Sentry calls.
        Http::fake(['*' => Http::response(['ok' => true], 200)]);

        // Use a unique prefix per test to avoid UNIQUE key conflicts across methods.
        $this->prefix = 'test-' . uniqid('', true) . '-';
    }

    // ------------------------------------------------------------------
    // Helper
    // ------------------------------------------------------------------

    private function insertEvent(
        string $eventId,
        string $eventType,
        string $status,
        Carbon $processedAt
    ): void {
        DB::table('stripe_webhook_events')->insert([
            'event_id'     => $eventId,
            'event_type'   => $eventType,
            'status'       => $status,
            'processed_at' => $processedAt->toDateTimeString(),
        ]);
    }

    // ------------------------------------------------------------------
    // Tests
    // ------------------------------------------------------------------

    public function test_exits_success_when_no_failed_events_exist(): void
    {
        // Insert only a "processed" event — should be ignored.
        $this->insertEvent(
            $this->prefix . 'processed-1',
            'payment_intent.succeeded',
            'processed',
            Carbon::now()->subHours(10)
        );

        $this->artisan('stripe:check-stuck-webhooks')
            ->assertExitCode(0);
    }

    public function test_exits_success_when_failed_event_is_recent_within_threshold(): void
    {
        // A failed event that is only 2 hours old — below the default 6-hour threshold.
        $this->insertEvent(
            $this->prefix . 'recent-failed',
            'charge.failed',
            'failed',
            Carbon::now()->subHours(2)
        );

        $this->artisan('stripe:check-stuck-webhooks', ['--hours' => '6'])
            ->assertExitCode(0);
    }

    public function test_exits_failure_when_one_stuck_failed_event_found(): void
    {
        $this->insertEvent(
            $this->prefix . 'stuck-1',
            'payment_intent.payment_failed',
            'failed',
            Carbon::now()->subHours(8)
        );

        $this->artisan('stripe:check-stuck-webhooks', ['--hours' => '6'])
            ->assertExitCode(1);
    }

    public function test_exits_failure_with_multiple_stuck_events(): void
    {
        foreach (['charge.refunded', 'payment_intent.succeeded', 'customer.subscription.updated'] as $i => $type) {
            $this->insertEvent(
                $this->prefix . 'multi-' . $i,
                $type,
                'failed',
                Carbon::now()->subHours(12 + $i)
            );
        }

        $this->artisan('stripe:check-stuck-webhooks')
            ->assertExitCode(1);
    }

    public function test_custom_hours_threshold_respected(): void
    {
        // Event is 3 hours old — stuck under --hours=2, healthy under --hours=6.
        $this->insertEvent(
            $this->prefix . 'threshold',
            'invoice.payment_failed',
            'failed',
            Carbon::now()->subHours(3)
        );

        $this->artisan('stripe:check-stuck-webhooks', ['--hours' => '2'])
            ->assertExitCode(1);

        $this->artisan('stripe:check-stuck-webhooks', ['--hours' => '6'])
            ->assertExitCode(0);
    }

    public function test_max_option_caps_number_of_rows_surfaced(): void
    {
        // Insert 5 stuck events.
        for ($i = 0; $i < 5; $i++) {
            $this->insertEvent(
                $this->prefix . 'max-' . $i,
                'payment_intent.payment_failed',
                'failed',
                Carbon::now()->subHours(10)
            );
        }

        // --max=2 still detects the problem (exits 1), just doesn't surface all 5.
        $this->artisan('stripe:check-stuck-webhooks', ['--max' => '2', '--hours' => '6'])
            ->assertExitCode(1);
    }

    public function test_only_failed_status_triggers_alert_not_processing(): void
    {
        // processing rows are legitimately in-flight and must NOT trigger the alert.
        $this->insertEvent(
            $this->prefix . 'processing',
            'payment_intent.succeeded',
            'processing',
            Carbon::now()->subHours(10)
        );

        $this->artisan('stripe:check-stuck-webhooks')
            ->assertExitCode(0);
    }

    public function test_slack_webhook_is_called_when_stuck_events_found(): void
    {
        // Re-fake after setUp so we can assert the URL.
        Http::fake([
            'https://hooks.slack.com/*' => Http::response('ok', 200),
            '*'                          => Http::response(['ok' => true], 200),
        ]);
        config(['services.slack.slo_alerts_webhook' => 'https://hooks.slack.com/services/T000/B000/test']);

        $this->insertEvent(
            $this->prefix . 'slack-test',
            'charge.failed',
            'failed',
            Carbon::now()->subHours(8)
        );

        $this->artisan('stripe:check-stuck-webhooks', ['--hours' => '6'])
            ->assertExitCode(1);

        Http::assertSent(function ($request) {
            return str_contains($request->url(), 'hooks.slack.com')
                && str_contains((string) $request->body(), 'STRIPE WEBHOOK ALERT');
        });
    }

    public function test_no_slack_call_when_no_stuck_events(): void
    {
        Http::fake(['*' => Http::response('ok', 200)]);
        config(['services.slack.slo_alerts_webhook' => 'https://hooks.slack.com/services/T000/B000/test']);

        // Only a healthy processed row.
        $this->insertEvent(
            $this->prefix . 'healthy',
            'payment_intent.succeeded',
            'processed',
            Carbon::now()->subHours(2)
        );

        $this->artisan('stripe:check-stuck-webhooks')
            ->assertExitCode(0);

        Http::assertNothingSent();
    }

    public function test_boundary_event_exactly_at_cutoff_is_not_stuck(): void
    {
        // An event that is exactly at the boundary (same second as cutoff) uses
        // strict "<" — at-cutoff should NOT appear in results.
        // We use subHours(6)->addSecond() which is 1 second before the cutoff → NOT stuck.
        $this->insertEvent(
            $this->prefix . 'boundary',
            'charge.failed',
            'failed',
            Carbon::now()->subHours(6)->addSecond()
        );

        $this->artisan('stripe:check-stuck-webhooks', ['--hours' => '6'])
            ->assertExitCode(0);
    }

    public function test_event_one_second_past_cutoff_is_detected(): void
    {
        // subHours(6)->subSecond() puts the event 1s beyond the 6h cutoff.
        $this->insertEvent(
            $this->prefix . 'just-past',
            'charge.failed',
            'failed',
            Carbon::now()->subHours(6)->subSecond()
        );

        $this->artisan('stripe:check-stuck-webhooks', ['--hours' => '6'])
            ->assertExitCode(1);
    }

    public function test_hours_option_minimum_enforced_as_one(): void
    {
        // --hours=0 should be clamped to 1.
        $this->insertEvent(
            $this->prefix . 'clamp-test',
            'charge.failed',
            'failed',
            Carbon::now()->subMinutes(90)  // 1.5 h old — past a clamped 1h threshold
        );

        $this->artisan('stripe:check-stuck-webhooks', ['--hours' => '0'])
            ->assertExitCode(1);
    }
}
