<?php
// Copyright © 2024–2026 Jasper Ford
// SPDX-License-Identifier: AGPL-3.0-or-later
// Author: Jasper Ford
// See NOTICE file for attribution and acknowledgements.

declare(strict_types=1);

namespace Tests\Laravel\Unit\Console;

use App\Services\EmailMonitorService;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Queue;
use Tests\Laravel\TestCase;

/**
 * Tests for email:health-alert console command.
 *
 * Uses unique tenant id 99711 for isolation.
 */
class EmailHealthAlertTest extends TestCase
{
    use DatabaseTransactions;

    private const TENANT_ID = 99711;
    private const CACHE_KEY = 'email_health_alert:last_signature';
    private const WEBHOOK   = 'https://hooks.slack.com/services/T99711/B99711/test';

    protected function setUp(): void
    {
        parent::setUp();

        Queue::fake();

        DB::table('tenants')->insertOrIgnore([
            'id'         => self::TENANT_ID,
            'name'       => 'Email Health Alert Test Tenant',
            'slug'       => 'test-email-health-' . self::TENANT_ID,
            'is_active'  => 1,
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        \App\Core\TenantContext::setById(self::TENANT_ID);

        // Clear the dedupe cache key so each test starts fresh.
        Cache::forget(self::CACHE_KEY);

        // Default: fake all HTTP responses as Slack OK.
        Http::fake(['*' => Http::response('ok', 200)]);
    }

    // ------------------------------------------------------------------
    // Helpers
    // ------------------------------------------------------------------

    /** Insert a row into email_log scoped to our isolation tenant. */
    private function insertEmailLog(
        string $status,
        string $category = 'general',
        ?string $createdAt = null
    ): int {
        return (int) DB::table('email_log')->insertGetId([
            'tenant_id'       => self::TENANT_ID,
            'recipient_email' => 'test-' . uniqid() . '@example.com',
            'category'        => $category,
            'status'          => $status,
            'provider'        => 'sendgrid',
            'created_at'      => $createdAt ?? now()->toDateTimeString(),
            'updated_at'      => now()->toDateTimeString(),
        ]);
    }

    /** Bind a mock EmailMonitorService that returns the given warnings. */
    private function bindMonitor(array $globalWarnings, array $tenantWarnings = []): void
    {
        $mock = \Mockery::mock(EmailMonitorService::class);
        $mock->shouldReceive('getWarnings')
            ->with(null)
            ->andReturn($globalWarnings);
        $mock->shouldReceive('getWarnings')
            ->with(\Mockery::type('int'))
            ->andReturn($tenantWarnings);

        $this->app->instance(EmailMonitorService::class, $mock);
    }

    // ------------------------------------------------------------------
    // Tests — healthy / no-op paths
    // ------------------------------------------------------------------

    public function test_exits_success_and_no_slack_when_monitor_returns_no_issues(): void
    {
        $this->bindMonitor([]);

        Http::fake(['*' => Http::response('ok', 200)]);

        $this->artisan('email:health-alert')
            ->assertExitCode(0);

        Http::assertNothingSent();
    }

    public function test_exits_success_when_monitor_returns_only_info_severity(): void
    {
        $infoOnly = [[
            'code'     => 'no_recent_email_activity',
            'severity' => 'info',
            'params'   => ['days' => 7],
        ]];

        $this->bindMonitor($infoOnly);

        $this->artisan('email:health-alert')
            ->assertExitCode(0);

        Http::assertNothingSent();
    }

    // ------------------------------------------------------------------
    // Tests — warning / critical alert paths
    // ------------------------------------------------------------------

    public function test_exits_success_and_sends_slack_when_warning_issues_present(): void
    {
        config(['services.slack.email_alerts_webhook' => self::WEBHOOK]);
        Http::fake([self::WEBHOOK => Http::response('ok', 200)]);

        $warnings = [[
            'code'     => 'recent_email_failures',
            'severity' => 'warning',
            'params'   => ['count' => 3, 'rate' => 10.0, 'window_hours' => 24],
        ]];

        $this->bindMonitor($warnings);

        $this->artisan('email:health-alert')
            ->assertExitCode(0);

        Http::assertSent(function ($request) {
            return str_contains($request->url(), 'hooks.slack.com')
                && str_contains((string) $request->body(), 'recent_email_failures');
        });
    }

    public function test_exits_success_and_sends_slack_when_critical_issues_present(): void
    {
        config(['services.slack.email_alerts_webhook' => self::WEBHOOK]);
        Http::fake([self::WEBHOOK => Http::response('ok', 200)]);

        $critical = [[
            'code'     => 'critical_email_failures',
            'severity' => 'critical',
            'params'   => ['count' => 5, 'window_hours' => 24],
        ]];

        $this->bindMonitor($critical);

        $this->artisan('email:health-alert')
            ->assertExitCode(0);

        Http::assertSent(function ($request) {
            return str_contains($request->url(), 'hooks.slack.com')
                && str_contains((string) $request->body(), 'critical_email_failures');
        });
    }

    public function test_no_slack_sent_when_webhook_not_configured(): void
    {
        config(['services.slack.email_alerts_webhook' => null]);

        $this->bindMonitor([[
            'code'     => 'recent_email_failures',
            'severity' => 'warning',
            'params'   => ['count' => 2, 'rate' => 5.0, 'window_hours' => 24],
        ]]);

        $this->artisan('email:health-alert')
            ->assertExitCode(0);

        Http::assertNothingSent();
    }

    // ------------------------------------------------------------------
    // Tests — dedupe / cache behaviour
    // ------------------------------------------------------------------

    public function test_dedupe_suppresses_second_identical_alert_within_window(): void
    {
        config(['services.slack.email_alerts_webhook' => self::WEBHOOK]);
        Http::fake([self::WEBHOOK => Http::response('ok', 200)]);

        $warning = [[
            'code'     => 'recent_email_failures',
            'severity' => 'warning',
            'params'   => ['count' => 1, 'rate' => 5.0, 'window_hours' => 24],
        ]];

        $this->bindMonitor($warning);

        // First run — should send.
        $this->artisan('email:health-alert')->assertExitCode(0);

        $sentCount = Http::recorded();
        $this->assertCount(1, $sentCount);

        // Reset Http::fake to count fresh.
        Http::fake([self::WEBHOOK => Http::response('ok', 200)]);

        // Re-bind mock (Mockery mock consumed).
        $this->bindMonitor($warning);

        // Second run — identical issue set, should be deduped (no new Slack call).
        $this->artisan('email:health-alert')->assertExitCode(0);

        Http::assertNothingSent();
    }

    public function test_force_flag_bypasses_dedupe_and_always_sends(): void
    {
        config(['services.slack.email_alerts_webhook' => self::WEBHOOK]);

        $warning = [[
            'code'     => 'recent_email_failures',
            'severity' => 'warning',
            'params'   => ['count' => 1, 'rate' => 5.0, 'window_hours' => 24],
        ]];

        // Pre-populate the dedupe cache as if an alert was already sent.
        $sig = md5((string) json_encode(['global|warning|recent_email_failures']));
        Cache::put(self::CACHE_KEY, $sig, 21600);

        $this->bindMonitor($warning);

        Http::fake([self::WEBHOOK => Http::response('ok', 200)]);

        // With --force, the cached signature should be ignored.
        $this->artisan('email:health-alert', ['--force' => true])
            ->assertExitCode(0);

        Http::assertSent(function ($request) {
            return str_contains($request->url(), 'hooks.slack.com');
        });
    }

    public function test_new_issue_type_alerts_even_when_dedupe_cache_exists(): void
    {
        config(['services.slack.email_alerts_webhook' => self::WEBHOOK]);
        Http::fake([self::WEBHOOK => Http::response('ok', 200)]);

        // Cache a signature for an old issue set.
        $oldSig = md5((string) json_encode(['global|warning|recent_email_failures']));
        Cache::put(self::CACHE_KEY, $oldSig, 21600);

        // New (different) issue set — different signature → should page.
        $this->bindMonitor([[
            'code'     => 'critical_email_failures',
            'severity' => 'critical',
            'params'   => ['count' => 2, 'window_hours' => 24],
        ]]);

        $this->artisan('email:health-alert')->assertExitCode(0);

        Http::assertSent(function ($request) {
            return str_contains($request->url(), 'hooks.slack.com');
        });
    }

    public function test_custom_dedupe_ttl_option_accepted(): void
    {
        config(['services.slack.email_alerts_webhook' => self::WEBHOOK]);
        Http::fake([self::WEBHOOK => Http::response('ok', 200)]);

        $this->bindMonitor([[
            'code'     => 'recent_email_failures',
            'severity' => 'warning',
            'params'   => ['count' => 1, 'rate' => 5.0, 'window_hours' => 24],
        ]]);

        // Command should accept --dedupe-ttl without error.
        $this->artisan('email:health-alert', ['--dedupe-ttl' => '300'])
            ->assertExitCode(0);

        $this->assertTrue(true); // verified no exception
    }

    // ------------------------------------------------------------------
    // Tests — real DB path (email_log rows, tenant isolation)
    // ------------------------------------------------------------------

    public function test_real_db_warning_fires_when_failed_email_log_rows_exist(): void
    {
        config(['services.slack.email_alerts_webhook' => self::WEBHOOK]);
        Http::fake([self::WEBHOOK => Http::response('ok', 200)]);

        // Insert enough failed rows to trigger the EmailMonitorService real threshold.
        // Seed 6 failed rows for our tenant within the last 24h.
        for ($i = 0; $i < 6; $i++) {
            $this->insertEmailLog('failed', 'general');
        }

        // Let the REAL EmailMonitorService run (no mock bound).
        // Also seed a recent email_log row with our tenant_id so recentlyActiveTenantIds() picks it up.
        // (Our inserted rows already satisfy that — created_at = now().)

        $this->artisan('email:health-alert')
            ->assertExitCode(0);

        // 6 failed rows with no successes = 100% bad rate → critical threshold (bad>=5 or rate>=25).
        // The Slack push is attempted.
        Http::assertSent(function ($request) {
            return str_contains($request->url(), 'hooks.slack.com');
        });
    }

    public function test_no_alert_when_email_log_empty_for_isolation_tenant(): void
    {
        // No webhook set — if no issues, no send.
        config(['services.slack.email_alerts_webhook' => null]);

        // No email_log rows for our tenant, no global issues from real service.
        // The global run of EmailMonitorService on a clean test DB should return no issues
        // (or only info-level). We bind a stub to ensure complete isolation.
        $this->bindMonitor([]);

        $this->artisan('email:health-alert')
            ->assertExitCode(0);

        Http::assertNothingSent();
    }

    // ------------------------------------------------------------------
    // Tests — Slack error handling
    // ------------------------------------------------------------------

    public function test_exits_success_even_when_slack_webhook_returns_non_2xx(): void
    {
        config(['services.slack.email_alerts_webhook' => self::WEBHOOK]);
        Http::fake([self::WEBHOOK => Http::response('error', 500)]);

        $this->bindMonitor([[
            'code'     => 'recent_email_failures',
            'severity' => 'warning',
            'params'   => ['count' => 1, 'rate' => 5.0, 'window_hours' => 24],
        ]]);

        // A Slack failure must not fail the command.
        $this->artisan('email:health-alert')
            ->assertExitCode(0);

        $this->assertTrue(true);
    }
}
