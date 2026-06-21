<?php
// Copyright © 2024–2026 Jasper Ford
// SPDX-License-Identifier: AGPL-3.0-or-later
// Author: Jasper Ford
// See NOTICE file for attribution and acknowledgements.

namespace Tests\Laravel\Feature\Console;

use Illuminate\Foundation\Testing\DatabaseTransactions;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Tests\Laravel\TestCase;

/**
 * Compliance-observability lock — overdue-GDPR-request alerter.
 *
 * GDPR data-subject requests are stored as `pending` rows actioned manually by
 * an admin (no automated processor). A request nobody opens silently breaches
 * the GDPR Art.12(3) one-month deadline (prod read 2026-06-21 found requests
 * ~3.5 months old). `gdpr:check-overdue-requests` must alert (non-zero exit +
 * "GDPR ALERT") on an open request older than the threshold, and stay green for
 * a recent request or a completed/rejected one. It must NOT modify any request.
 */
class OverdueGdprRequestAlertTest extends TestCase
{
    use DatabaseTransactions;

    protected function setUp(): void
    {
        parent::setUp();

        if (! Schema::hasTable('gdpr_requests')) {
            $this->markTestSkipped('gdpr_requests table not present in this environment.');
        }

        // Clear the slate (rolled back by DatabaseTransactions) so pre-existing
        // rows in the shared nexus_test DB don't skew the assertions.
        DB::table('gdpr_requests')->delete();
    }

    /**
     * @param array<string,mixed> $overrides
     */
    private function insertRequest(array $overrides = []): int
    {
        return (int) DB::table('gdpr_requests')->insertGetId(array_merge([
            'user_id'      => 999001,
            'tenant_id'    => $this->testTenantId,
            'request_type' => 'erasure',
            'status'       => 'pending',
            'requested_at' => now()->subDays(40),
            'created_at'   => now()->subDays(40),
            'updated_at'   => now()->subDays(40),
        ], $overrides));
    }

    public function test_alerts_on_request_pending_past_threshold(): void
    {
        $this->insertRequest(['requested_at' => now()->subDays(40)]);

        $this->artisan('gdpr:check-overdue-requests', ['--days' => 25])
            ->expectsOutputToContain('GDPR ALERT')
            ->assertExitCode(1);
    }

    public function test_reports_count_past_statutory_deadline(): void
    {
        // One past the 30-day statutory deadline, one only past the warn window.
        $this->insertRequest(['requested_at' => now()->subDays(45)]);
        $this->insertRequest(['requested_at' => now()->subDays(27), 'request_type' => 'access']);

        $this->artisan('gdpr:check-overdue-requests', ['--days' => 25])
            ->expectsOutputToContain('1 already exceed the 30-day')
            ->assertExitCode(1);
    }

    public function test_does_not_alert_on_recent_or_completed_request(): void
    {
        // A recent request (within threshold) — admin still has time to action.
        $this->insertRequest(['requested_at' => now()->subDays(3)]);
        // An old but completed request must be ignored.
        $this->insertRequest(['requested_at' => now()->subDays(60), 'status' => 'completed']);
        // An old but rejected request must be ignored.
        $this->insertRequest(['requested_at' => now()->subDays(60), 'status' => 'rejected']);

        $this->artisan('gdpr:check-overdue-requests', ['--days' => 25])
            ->expectsOutputToContain('healthy')
            ->assertExitCode(0);
    }
}
