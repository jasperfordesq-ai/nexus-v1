<?php
// Copyright © 2024–2026 Jasper Ford
// SPDX-License-Identifier: AGPL-3.0-or-later
// Author: Jasper Ford
// See NOTICE file for attribution and acknowledgements.

namespace Tests\Laravel\Feature\Console;

use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\Http;
use Tests\Laravel\TestCase;

/**
 * Regression guard for `monitoring:alarm-selftest` — the alarm-delivery
 * heartbeat (the watcher-of-the-watcher).
 *
 * Asserts the heartbeat always succeeds (it is a heartbeat, never an alarm),
 * actually pushes to Slack through the real HTTP leg when the webhook is
 * configured, and degrades cleanly to log-only when it is not. This is the
 * safety net that makes a silently-broken Sentry/Slack delivery path itself
 * detectable.
 */
class AlarmSelftestTest extends TestCase
{
    public function test_heartbeat_pushes_to_slack_when_webhook_configured(): void
    {
        config(['services.slack.slo_alerts_webhook' => 'https://hooks.slack.test/heartbeat']);
        Http::preventStrayRequests();
        Http::fake(['*' => Http::response('ok', 200)]);

        $exit = Artisan::call('monitoring:alarm-selftest');
        $output = Artisan::output();

        $this->assertSame(0, $exit, $output);
        $this->assertStringContainsString('heartbeat emitted', $output);
        $this->assertStringContainsString('slack=sent', $output);

        Http::assertSent(function ($request) {
            return str_contains((string) $request->url(), 'hooks.slack.test')
                && str_contains((string) ($request['text'] ?? ''), 'alarm-selftest heartbeat');
        });
    }

    public function test_heartbeat_degrades_to_log_only_without_slack_webhook(): void
    {
        config(['services.slack.slo_alerts_webhook' => '']);
        Http::fake();

        $exit = Artisan::call('monitoring:alarm-selftest');
        $output = Artisan::output();

        $this->assertSame(0, $exit, $output);
        $this->assertStringContainsString('SLACK_SLO_ALERTS_WEBHOOK not configured', $output);
        $this->assertStringContainsString('slack=skipped', $output);

        // No outbound HTTP when no webhook is configured.
        Http::assertNothingSent();
    }

    public function test_quiet_slack_option_skips_the_slack_leg_even_when_configured(): void
    {
        config(['services.slack.slo_alerts_webhook' => 'https://hooks.slack.test/heartbeat']);
        Http::fake();

        $exit = Artisan::call('monitoring:alarm-selftest', ['--quiet-slack' => true]);
        $output = Artisan::output();

        $this->assertSame(0, $exit, $output);
        $this->assertStringContainsString('slack=skipped', $output);

        Http::assertNothingSent();
    }
}
