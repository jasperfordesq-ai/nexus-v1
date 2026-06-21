<?php
// Copyright © 2024–2026 Jasper Ford
// SPDX-License-Identifier: AGPL-3.0-or-later
// Author: Jasper Ford
// See NOTICE file for attribution and acknowledgements.

namespace Tests\Laravel\Feature\Commands;

use Illuminate\Support\Facades\DB;
use Mockery;
use Tests\Laravel\TestCase;

/**
 * Regression guard for Sentry NEXUS-PHP-2B
 * (Exception: Scheduled command [... emails:reconcile-transient-failures]
 *  failed with exit code [1]).
 *
 * The reconcile command is best-effort 15-minute housekeeping. A transient DB
 * hiccup, a SendGrid outage, or a broken stdout/log pipe in the scheduler
 * container must NOT escalate into a paging "scheduled command failed" error —
 * it should log and exit cleanly so the next run reconciles the same window.
 */
class ReconcileTransientEmailFailuresResilienceTest extends TestCase
{
    protected function tearDown(): void
    {
        Mockery::close();
        parent::tearDown();
    }

    public function test_command_exits_zero_when_the_underlying_query_throws(): void
    {
        config(['mail.sendgrid.api_key' => 'test-key']);

        // Force the email_log lookup to blow up the way a transient DB outage
        // would. A partial mock lets every other DB call pass through untouched.
        $dbMock = Mockery::mock($this->app['db'])->makePartial();
        $dbMock->shouldReceive('table')
            ->with('email_log')
            ->andThrow(new \RuntimeException('transient db outage'));
        $this->app->instance('db', $dbMock);
        DB::clearResolvedInstances();

        $this->artisan('emails:reconcile-transient-failures')->assertExitCode(0);
    }
}
