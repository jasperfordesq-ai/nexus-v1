<?php
// Copyright © 2024–2026 Jasper Ford
// SPDX-License-Identifier: AGPL-3.0-or-later
// Author: Jasper Ford
// See NOTICE file for attribution and acknowledgements.

declare(strict_types=1);

namespace Tests\Laravel\Unit\Console;

use Illuminate\Foundation\Testing\DatabaseTransactions;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Queue;
use Tests\Laravel\TestCase;

/**
 * Tests for emails:reconcile-transient-failures console command.
 *
 * Uses unique tenant id 99712 for isolation.
 *
 * This command queries SendGrid's /v3/messages activity API for recently-failed
 * email_log rows (provider=sendgrid, status=failed, has a provider_message_id).
 * When SendGrid reports 'delivered' or 'processed', it updates the row's status
 * to 'delivered'/'sent' respectively. Permanent failures (bounce/dropped) are
 * left alone. Rows without a SENDGRID_API_KEY configured are skipped entirely.
 */
class RetryTransientEmailFailuresTest extends TestCase
{
    use DatabaseTransactions;

    private const TENANT_ID  = 99712;
    // Fake SendGrid API endpoint pattern.
    private const SG_PATTERN = 'https://api.sendgrid.com/*';

    protected function setUp(): void
    {
        parent::setUp();

        Queue::fake();

        DB::table('tenants')->insertOrIgnore([
            'id'         => self::TENANT_ID,
            'name'       => 'Retry Transient Email Failures Test Tenant',
            'slug'       => 'test-retry-transient-' . self::TENANT_ID,
            'is_active'  => 1,
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        \App\Core\TenantContext::setById(self::TENANT_ID);

        // Prevent stray HTTP requests — each test that hits SendGrid fakes its own response.
        // Do NOT call Http::fake() here with a global catch-all: in Laravel's Http client,
        // the first registered fake URL pattern wins, so a setUp() default would silently
        // swallow per-test fakes added afterwards.  Instead, tests that need HTTP either
        // assert nothing is sent or call Http::fake() themselves as the sole fake setup.
    }

    // ------------------------------------------------------------------
    // Helpers
    // ------------------------------------------------------------------

    /**
     * Insert a row into email_log and return its id.
     *
     * @param array<string,mixed> $overrides
     */
    private function insertEmailLog(array $overrides = []): int
    {
        $defaults = [
            'tenant_id'           => self::TENANT_ID,
            'recipient_email'     => 'test-' . uniqid() . '@example.com',
            'category'            => 'welcome',
            'status'              => 'failed',
            'provider'            => 'sendgrid',
            'provider_message_id' => 'msg-' . uniqid(),
            'subject'             => 'Test Subject',
            'created_at'          => now()->toDateTimeString(),
            'sent_at'             => now()->toDateTimeString(),
            'updated_at'          => now()->toDateTimeString(),
        ];

        return (int) DB::table('email_log')->insertGetId(array_merge($defaults, $overrides));
    }

    /** Build a fake SendGrid /v3/messages response that matches a given row. */
    private function sgDeliveredResponse(string $msgId, string $email, string $status = 'delivered'): array
    {
        return [
            'messages' => [[
                'msg_id'          => $msgId,
                'to_email'        => $email,
                'status'          => $status,
                'last_event_time' => now()->toIso8601String(),
            ]],
        ];
    }

    // ------------------------------------------------------------------
    // Tests — no API key / early-exit paths
    // ------------------------------------------------------------------

    public function test_exits_success_and_does_nothing_when_no_sendgrid_api_key(): void
    {
        config(['mail.sendgrid.api_key' => null]);

        $id = $this->insertEmailLog();

        $this->artisan('emails:reconcile-transient-failures')
            ->assertExitCode(0);

        // Row untouched.
        $this->assertSame('failed', DB::table('email_log')->where('id', $id)->value('status'));
    }

    public function test_exits_success_when_no_failed_rows_in_window(): void
    {
        config(['mail.sendgrid.api_key' => 'sg-test-key']);

        // Insert only a delivered row — should be ignored.
        $this->insertEmailLog(['status' => 'delivered']);

        Http::fake([self::SG_PATTERN => Http::response(['messages' => []], 200)]);

        $this->artisan('emails:reconcile-transient-failures')
            ->assertExitCode(0);

        Http::assertNothingSent();
    }

    // ------------------------------------------------------------------
    // Tests — reconciliation / status update paths
    // ------------------------------------------------------------------

    public function test_updates_status_to_delivered_when_sendgrid_reports_delivered(): void
    {
        config(['mail.sendgrid.api_key' => 'sg-test-key']);

        $msgId = 'msg-reconcile-' . uniqid();
        $email = 'delivered-test-' . uniqid() . '@example.com';
        $id    = $this->insertEmailLog([
            'provider_message_id' => $msgId,
            'recipient_email'     => $email,
        ]);

        Http::fake([
            self::SG_PATTERN => Http::response($this->sgDeliveredResponse($msgId, $email, 'delivered'), 200),
        ]);

        $this->artisan('emails:reconcile-transient-failures')->assertExitCode(0);

        $row = DB::table('email_log')->where('id', $id)->first();
        $this->assertSame('delivered', $row->status);
        $this->assertNotNull($row->delivered_at);
    }

    public function test_updates_status_to_sent_when_sendgrid_reports_processed(): void
    {
        config(['mail.sendgrid.api_key' => 'sg-test-key']);

        $msgId = 'msg-processed-' . uniqid();
        $email = 'processed-test-' . uniqid() . '@example.com';
        $id    = $this->insertEmailLog([
            'provider_message_id' => $msgId,
            'recipient_email'     => $email,
        ]);

        Http::fake([
            self::SG_PATTERN => Http::response($this->sgDeliveredResponse($msgId, $email, 'processed'), 200),
        ]);

        $this->artisan('emails:reconcile-transient-failures')->assertExitCode(0);

        $row = DB::table('email_log')->where('id', $id)->first();
        $this->assertSame('sent', $row->status);
    }

    public function test_leaves_status_failed_when_sendgrid_has_no_matching_record(): void
    {
        config(['mail.sendgrid.api_key' => 'sg-test-key']);

        $id = $this->insertEmailLog(['provider_message_id' => 'msg-no-match-' . uniqid()]);

        // SendGrid returns empty messages array → genuine failure.
        Http::fake([self::SG_PATTERN => Http::response(['messages' => []], 200)]);

        $this->artisan('emails:reconcile-transient-failures')->assertExitCode(0);

        $this->assertSame('failed', DB::table('email_log')->where('id', $id)->value('status'));
    }

    public function test_leaves_status_failed_when_sendgrid_reports_bounce(): void
    {
        config(['mail.sendgrid.api_key' => 'sg-test-key']);

        $msgId = 'msg-bounce-' . uniqid();
        $email = 'bounce-test-' . uniqid() . '@example.com';
        $id    = $this->insertEmailLog([
            'provider_message_id' => $msgId,
            'recipient_email'     => $email,
        ]);

        // bounce status → confirmed failure; should not reconcile.
        Http::fake([
            self::SG_PATTERN => Http::response($this->sgDeliveredResponse($msgId, $email, 'bounce'), 200),
        ]);

        $this->artisan('emails:reconcile-transient-failures')->assertExitCode(0);

        $this->assertSame('failed', DB::table('email_log')->where('id', $id)->value('status'));
    }

    // ------------------------------------------------------------------
    // Tests — rows that must be SKIPPED
    // ------------------------------------------------------------------

    public function test_skips_rows_with_null_provider_message_id(): void
    {
        config(['mail.sendgrid.api_key' => 'sg-test-key']);

        // NULL provider_message_id — should never be queried.
        $id = $this->insertEmailLog(['provider_message_id' => null]);

        Http::fake([self::SG_PATTERN => Http::response(['messages' => []], 200)]);

        $this->artisan('emails:reconcile-transient-failures')->assertExitCode(0);

        Http::assertNothingSent();
        $this->assertSame('failed', DB::table('email_log')->where('id', $id)->value('status'));
    }

    public function test_skips_rows_with_empty_string_provider_message_id(): void
    {
        config(['mail.sendgrid.api_key' => 'sg-test-key']);

        $id = $this->insertEmailLog(['provider_message_id' => '']);

        Http::fake([self::SG_PATTERN => Http::response(['messages' => []], 200)]);

        $this->artisan('emails:reconcile-transient-failures')->assertExitCode(0);

        Http::assertNothingSent();
        $this->assertSame('failed', DB::table('email_log')->where('id', $id)->value('status'));
    }

    public function test_skips_rows_with_non_sendgrid_provider(): void
    {
        config(['mail.sendgrid.api_key' => 'sg-test-key']);

        $id = $this->insertEmailLog([
            'provider'            => 'gmail_api',
            'provider_message_id' => 'gmail-' . uniqid(),
        ]);

        Http::fake([self::SG_PATTERN => Http::response(['messages' => []], 200)]);

        $this->artisan('emails:reconcile-transient-failures')->assertExitCode(0);

        Http::assertNothingSent();
        $this->assertSame('failed', DB::table('email_log')->where('id', $id)->value('status'));
    }

    public function test_skips_rows_outside_the_lookback_window(): void
    {
        config(['mail.sendgrid.api_key' => 'sg-test-key']);

        // Row is 2 hours old but we look back only 1 minute.
        $id = $this->insertEmailLog([
            'provider_message_id' => 'msg-old-' . uniqid(),
            'created_at'          => now()->subHours(2)->toDateTimeString(),
        ]);

        Http::fake([self::SG_PATTERN => Http::response(['messages' => []], 200)]);

        $this->artisan('emails:reconcile-transient-failures', ['--minutes' => '1'])
            ->assertExitCode(0);

        Http::assertNothingSent();
        $this->assertSame('failed', DB::table('email_log')->where('id', $id)->value('status'));
    }

    public function test_skips_suppressed_and_bounced_rows(): void
    {
        config(['mail.sendgrid.api_key' => 'sg-test-key']);

        $suppId  = $this->insertEmailLog(['status' => 'suppressed', 'provider_message_id' => 'msg-supp-' . uniqid()]);
        $bounced = $this->insertEmailLog(['status' => 'bounced',    'provider_message_id' => 'msg-bnc-' . uniqid()]);

        Http::fake([self::SG_PATTERN => Http::response(['messages' => []], 200)]);

        $this->artisan('emails:reconcile-transient-failures')->assertExitCode(0);

        Http::assertNothingSent();
        $this->assertSame('suppressed', DB::table('email_log')->where('id', $suppId)->value('status'));
        $this->assertSame('bounced',    DB::table('email_log')->where('id', $bounced)->value('status'));
    }

    // ------------------------------------------------------------------
    // Tests — dry-run
    // ------------------------------------------------------------------

    public function test_dry_run_does_not_write_status_update(): void
    {
        config(['mail.sendgrid.api_key' => 'sg-test-key']);

        $msgId = 'msg-dryrun-' . uniqid();
        $email = 'dryrun-' . uniqid() . '@example.com';
        $id    = $this->insertEmailLog([
            'provider_message_id' => $msgId,
            'recipient_email'     => $email,
        ]);

        Http::fake([
            self::SG_PATTERN => Http::response($this->sgDeliveredResponse($msgId, $email, 'delivered'), 200),
        ]);

        $this->artisan('emails:reconcile-transient-failures', ['--dry-run' => true])
            ->assertExitCode(0);

        // Status must still be 'failed' — dry-run writes nothing.
        $this->assertSame('failed', DB::table('email_log')->where('id', $id)->value('status'));
    }

    // ------------------------------------------------------------------
    // Tests — limit option
    // ------------------------------------------------------------------

    public function test_limit_option_caps_number_of_rows_queried(): void
    {
        config(['mail.sendgrid.api_key' => 'sg-test-key']);

        // Insert 5 eligible rows.
        for ($i = 0; $i < 5; $i++) {
            $this->insertEmailLog(['provider_message_id' => 'msg-limit-' . $i . '-' . uniqid()]);
        }

        // With --limit=2, SendGrid is hit at most twice (regardless of match outcome).
        $callCount = 0;
        Http::fake([
            self::SG_PATTERN => function () use (&$callCount) {
                $callCount++;
                return Http::response(['messages' => []], 200);
            },
        ]);

        $this->artisan('emails:reconcile-transient-failures', ['--limit' => '2'])
            ->assertExitCode(0);

        $this->assertLessThanOrEqual(2, $callCount, 'SendGrid called more times than --limit allows');
    }

    // ------------------------------------------------------------------
    // Tests — resilience / error handling
    // ------------------------------------------------------------------

    public function test_exits_success_even_when_sendgrid_api_returns_500(): void
    {
        config(['mail.sendgrid.api_key' => 'sg-test-key']);

        $id = $this->insertEmailLog(['provider_message_id' => 'msg-500-' . uniqid()]);

        Http::fake([self::SG_PATTERN => Http::response('error', 500)]);

        // The command must swallow this and exit 0 (best-effort housekeeping).
        $this->artisan('emails:reconcile-transient-failures')->assertExitCode(0);

        // Row stays failed (not reconciled).
        $this->assertSame('failed', DB::table('email_log')->where('id', $id)->value('status'));
    }

    public function test_exits_success_even_when_sendgrid_api_returns_malformed_json(): void
    {
        config(['mail.sendgrid.api_key' => 'sg-test-key']);

        $this->insertEmailLog(['provider_message_id' => 'msg-bad-json-' . uniqid()]);

        Http::fake([
            self::SG_PATTERN => Http::response(null, 200),
        ]);

        $this->artisan('emails:reconcile-transient-failures')->assertExitCode(0);

        $this->assertTrue(true);
    }

    // ------------------------------------------------------------------
    // Tests — custom minutes option
    // ------------------------------------------------------------------

    public function test_custom_minutes_option_is_respected(): void
    {
        config(['mail.sendgrid.api_key' => 'sg-test-key']);

        // Row created 90 minutes ago — inside a 120-minute window but outside a 60-minute window.
        $msgId = 'msg-mins-' . uniqid();
        $email = 'mins-test-' . uniqid() . '@example.com';
        $id    = $this->insertEmailLog([
            'provider_message_id' => $msgId,
            'recipient_email'     => $email,
            'created_at'          => now()->subMinutes(90)->toDateTimeString(),
        ]);

        Http::fake([
            self::SG_PATTERN => Http::response($this->sgDeliveredResponse($msgId, $email, 'delivered'), 200),
        ]);

        // With --minutes=120 the row is in-window and gets reconciled.
        $this->artisan('emails:reconcile-transient-failures', ['--minutes' => '120'])
            ->assertExitCode(0);

        $this->assertSame('delivered', DB::table('email_log')->where('id', $id)->value('status'));
    }
}
