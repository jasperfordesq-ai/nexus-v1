<?php
// Copyright © 2024–2026 Jasper Ford
// SPDX-License-Identifier: AGPL-3.0-or-later
// Author: Jasper Ford
// See NOTICE file for attribution and acknowledgements.

declare(strict_types=1);

namespace Tests\Laravel\Unit\Console;

use App\Core\TenantContext;
use App\Services\EmailDispatchService;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Queue;
use Tests\Laravel\TestCase;

/**
 * Tests for marketplace:retry-report-notifications console command.
 *
 * The command calls MarketplaceReportService::retryPendingReportNotifications($limit)
 * and prints "Processed N marketplace report notification(s)."
 *
 * Key contracts tested:
 *  - pending rows → retried (status flipped to sent/failed, attempts incremented)
 *  - failed rows with past next_retry_at → retried
 *  - failed rows with future next_retry_at → skipped
 *  - sent rows → skipped
 *  - --limit option is respected
 *  - output message contains the count
 *  - exit code is always 0 (SUCCESS)
 *
 * Uses unique tenant id 99758 for isolation.
 *
 * EmailDispatchService is stubbed to prevent real SMTP calls.
 */
class RetryMarketplaceReportNotificationsTest extends TestCase
{
    use DatabaseTransactions;

    private const TENANT_ID = 99758;
    private const TENANT_SLUG = 'test-retry-mkt-99758';

    protected function setUp(): void
    {
        parent::setUp();

        Queue::fake();

        // Stub EmailDispatchService so no real SMTP is hit.
        $stub = \Mockery::mock(EmailDispatchService::class);
        $stub->shouldReceive('sendRaw')->andReturn(true)->byDefault();
        $this->app->instance(EmailDispatchService::class, $stub);

        DB::table('tenants')->insertOrIgnore([
            'id'         => self::TENANT_ID,
            'name'       => 'Test Retry MKT Tenant',
            'slug'       => self::TENANT_SLUG,
            'is_active'  => 1,
            'features'   => json_encode([]),
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        TenantContext::setById(self::TENANT_ID);
    }

    // ------------------------------------------------------------------ //
    // Helpers                                                              //
    // ------------------------------------------------------------------ //

    /**
     * Insert a marketplace_report_notifications row and return its id.
     *
     * The row uses a unique dedupe_key per test to avoid unique-key collisions
     * across concurrent or sequential test runs within the same transaction.
     *
     * @param array<string,mixed> $overrides
     */
    private function insertNotification(array $overrides = []): int
    {
        static $seq = 0;
        $seq++;

        $defaults = [
            'tenant_id'               => self::TENANT_ID,
            'marketplace_report_id'   => 9999000 + $seq,
            'recipient_user_id'       => 9999100 + $seq,
            'event_type'              => 'received',
            'channel'                 => 'email',
            'dedupe_key'              => 'test-retry-99758-' . $seq . '-' . uniqid(),
            'status'                  => 'pending',
            'attempts'                => 0,
            'last_error'              => null,
            'last_attempted_at'       => null,
            'sent_at'                 => null,
            'next_retry_at'           => null,
            'payload'                 => json_encode([
                'subject_key'    => '',
                'subject_params' => [],
                'title_key'      => '',
                'body_key'       => '',
                'body_params'    => [],
                'note_key'       => null,
                'note_params'    => [],
                'link'           => '/marketplace/reports/1',
                'cta_key'        => '',
            ]),
            'created_at'              => now(),
            'updated_at'              => now(),
        ];

        return (int) DB::table('marketplace_report_notifications')
            ->insertGetId(array_merge($defaults, $overrides));
    }

    // ------------------------------------------------------------------ //
    // Exit code tests                                                      //
    // ------------------------------------------------------------------ //

    public function test_exits_success_with_no_pending_rows(): void
    {
        // No rows for our tenant → nothing to process.
        $this->artisan('marketplace:retry-report-notifications')
            ->assertExitCode(0);
    }

    public function test_exits_success_with_pending_rows(): void
    {
        $this->insertNotification(['status' => 'pending']);

        $this->artisan('marketplace:retry-report-notifications')
            ->assertExitCode(0);
    }

    // ------------------------------------------------------------------ //
    // Output tests                                                         //
    // ------------------------------------------------------------------ //

    public function test_output_contains_processed_count_when_no_rows(): void
    {
        $this->artisan('marketplace:retry-report-notifications')
            ->expectsOutputToContain('Processed 0 marketplace report notification(s).')
            ->assertExitCode(0);
    }

    public function test_output_contains_processed_count_for_one_pending_row(): void
    {
        $this->insertNotification(['status' => 'pending', 'next_retry_at' => null]);

        $this->artisan('marketplace:retry-report-notifications')
            ->expectsOutputToContain('marketplace report notification(s).')
            ->assertExitCode(0);
    }

    // ------------------------------------------------------------------ //
    // Pending rows are retried                                             //
    // ------------------------------------------------------------------ //

    public function test_pending_row_is_processed_and_attempts_incremented(): void
    {
        $id = $this->insertNotification([
            'status'        => 'pending',
            'attempts'      => 0,
            'next_retry_at' => null,
        ]);

        $this->artisan('marketplace:retry-report-notifications')->assertExitCode(0);

        $row = DB::table('marketplace_report_notifications')->where('id', $id)->first();

        // attempts must be incremented by the attempt path
        $this->assertGreaterThan(0, (int) $row->attempts, 'attempts must be incremented after processing');
        // status must have moved away from "pending" (→ processing, sent, or failed)
        $this->assertNotSame('pending', $row->status, 'pending row must not remain pending after retry');
    }

    public function test_failed_row_with_past_next_retry_at_is_retried(): void
    {
        $id = $this->insertNotification([
            'status'        => 'failed',
            'attempts'      => 1,
            'next_retry_at' => now()->subMinutes(10),  // past → eligible
        ]);

        $this->artisan('marketplace:retry-report-notifications')->assertExitCode(0);

        $row = DB::table('marketplace_report_notifications')->where('id', $id)->first();
        // The row was picked up and processed — attempts must be incremented.
        // (Status remains 'failed' because the test payload has empty keys so
        //  sendReportEmail returns false — that is expected and correct behaviour.)
        $this->assertGreaterThan(1, (int) $row->attempts, 'attempts must be incremented for past-next_retry_at failed row');
        // last_attempted_at must be set (proves the row was actually processed this run)
        $this->assertNotNull($row->last_attempted_at, 'last_attempted_at must be stamped when a failed row is retried');
    }

    // ------------------------------------------------------------------ //
    // Rows that must be skipped                                            //
    // ------------------------------------------------------------------ //

    public function test_sent_row_is_not_reprocessed(): void
    {
        $id = $this->insertNotification([
            'status'   => 'sent',
            'attempts' => 1,
            'sent_at'  => now()->subMinute(),
        ]);

        $this->artisan('marketplace:retry-report-notifications')->assertExitCode(0);

        $row = DB::table('marketplace_report_notifications')->where('id', $id)->first();
        $this->assertSame('sent', $row->status, 'sent row must not be touched by retry');
        $this->assertSame(1, (int) $row->attempts, 'attempts must remain 1 for sent row');
    }

    public function test_failed_row_with_future_next_retry_at_is_skipped(): void
    {
        $id = $this->insertNotification([
            'status'        => 'failed',
            'attempts'      => 2,
            'next_retry_at' => now()->addHour(),  // future → not yet eligible
        ]);

        $this->artisan('marketplace:retry-report-notifications')->assertExitCode(0);

        $row = DB::table('marketplace_report_notifications')->where('id', $id)->first();
        $this->assertSame('failed', $row->status, 'failed row with future next_retry_at must be skipped');
        $this->assertSame(2, (int) $row->attempts, 'attempts must be unchanged for skipped row');
    }

    // ------------------------------------------------------------------ //
    // --limit option                                                       //
    // ------------------------------------------------------------------ //

    public function test_limit_option_caps_rows_processed(): void
    {
        // Seed 5 pending rows; run with --limit=2 → at most 2 processed.
        $ids = [];
        for ($i = 0; $i < 5; $i++) {
            $ids[] = $this->insertNotification(['status' => 'pending', 'next_retry_at' => null]);
        }

        $this->artisan('marketplace:retry-report-notifications', ['--limit' => '2'])
            ->assertExitCode(0);

        // Exactly 2 rows must have had their attempts incremented (processed).
        $processedCount = DB::table('marketplace_report_notifications')
            ->whereIn('id', $ids)
            ->where('attempts', '>', 0)
            ->count();

        $this->assertSame(2, $processedCount, '--limit=2 must process at most 2 rows');
    }

    public function test_output_contains_limit_bounded_count(): void
    {
        for ($i = 0; $i < 3; $i++) {
            $this->insertNotification(['status' => 'pending', 'next_retry_at' => null]);
        }

        $this->artisan('marketplace:retry-report-notifications', ['--limit' => '1'])
            ->expectsOutputToContain('Processed 1 marketplace report notification(s).')
            ->assertExitCode(0);
    }

    // ------------------------------------------------------------------ //
    // Multiple rows processed in one run                                   //
    // ------------------------------------------------------------------ //

    public function test_multiple_pending_rows_are_all_retried(): void
    {
        $ids = [
            $this->insertNotification(['status' => 'pending']),
            $this->insertNotification(['status' => 'pending']),
            $this->insertNotification(['status' => 'failed', 'next_retry_at' => now()->subMinute()]),
        ];

        $this->artisan('marketplace:retry-report-notifications')->assertExitCode(0);

        $processedCount = DB::table('marketplace_report_notifications')
            ->whereIn('id', $ids)
            ->where('attempts', '>', 0)
            ->count();

        $this->assertSame(3, $processedCount, 'All 3 eligible rows must be processed in a single run');
    }
}
