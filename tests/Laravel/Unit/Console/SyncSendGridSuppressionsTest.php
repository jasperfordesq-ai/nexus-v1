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
 * Tests for sendgrid:sync-suppressions console command.
 *
 * Uses unique tenant id 99713 for isolation.
 * email_suppression is global (not tenant-scoped), so rows are
 * cleaned up via DatabaseTransactions.
 */
class SyncSendGridSuppressionsTest extends TestCase
{
    use DatabaseTransactions;

    private const TENANT_ID = 99713;

    /** Unique e-mail prefix to avoid collisions with pre-existing rows. */
    private string $prefix;

    protected function setUp(): void
    {
        parent::setUp();

        Queue::fake();

        DB::table('tenants')->insertOrIgnore([
            'id'         => self::TENANT_ID,
            'name'       => 'Test SendGrid Sync Tenant',
            'slug'       => 'test-sendgrid-sync-' . self::TENANT_ID,
            'is_active'  => 1,
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        \App\Core\TenantContext::setById(self::TENANT_ID);

        // Unique prefix so parallel test runs don't clash on the UNIQUE(email,reason) key.
        $this->prefix = 'sgtest-' . uniqid('', true) . '@example.com-';

        // Default: real API key so the command proceeds past the guard.
        config(['mail.sendgrid.api_key' => 'SG.fake-test-key-for-unit-testing']);
    }

    // ------------------------------------------------------------------
    // Helper
    // ------------------------------------------------------------------

    /**
     * Build a minimal SendGrid suppression response list.
     *
     * @param list<string> $emails
     * @param string       $reason  Detail field value in the API payload.
     */
    private function makeSuppressionList(array $emails, string $reason = 'bounced hard'): array
    {
        return array_map(fn (string $email) => [
            'email'   => $email,
            'created' => time() - 3600,
            'reason'  => $reason,
        ], $emails);
    }

    /**
     * Return the count of email_suppression rows matching this email + reason enum value.
     */
    private function suppressionCount(string $email, string $reasonEnum): int
    {
        return DB::table('email_suppression')
            ->where('email', $email)
            ->where('reason', $reasonEnum)
            ->count();
    }

    // ------------------------------------------------------------------
    // Tests
    // ------------------------------------------------------------------

    public function test_exits_success_with_no_api_key_configured(): void
    {
        config(['mail.sendgrid.api_key' => null]);

        Http::fake(); // nothing should be called

        $this->artisan('sendgrid:sync-suppressions')
            ->expectsOutputToContain('SENDGRID_API_KEY not configured')
            ->assertExitCode(0);

        Http::assertNothingSent();
    }

    public function test_exits_success_with_empty_api_key_string(): void
    {
        config(['mail.sendgrid.api_key' => '']);

        Http::fake();

        $this->artisan('sendgrid:sync-suppressions')
            ->expectsOutputToContain('SENDGRID_API_KEY not configured')
            ->assertExitCode(0);

        Http::assertNothingSent();
    }

    public function test_exits_success_with_empty_suppression_lists(): void
    {
        // All four endpoints return an empty array.
        Http::fake([
            'https://api.sendgrid.com/v3/suppression/bounces*'        => Http::response([], 200),
            'https://api.sendgrid.com/v3/suppression/blocks*'         => Http::response([], 200),
            'https://api.sendgrid.com/v3/suppression/invalid_emails*' => Http::response([], 200),
            'https://api.sendgrid.com/v3/suppression/spam_reports*'   => Http::response([], 200),
        ]);

        $this->artisan('sendgrid:sync-suppressions')
            ->expectsOutputToContain('Done. Total upserts: 0')
            ->assertExitCode(0);
    }

    public function test_bounce_rows_are_upserted_to_email_suppression(): void
    {
        $email = $this->prefix . 'bounce@example.com';

        Http::fake([
            'https://api.sendgrid.com/v3/suppression/bounces*'        => Http::response(
                $this->makeSuppressionList([$email], 'bounced hard'),
                200
            ),
            'https://api.sendgrid.com/v3/suppression/blocks*'         => Http::response([], 200),
            'https://api.sendgrid.com/v3/suppression/invalid_emails*' => Http::response([], 200),
            'https://api.sendgrid.com/v3/suppression/spam_reports*'   => Http::response([], 200),
        ]);

        $this->artisan('sendgrid:sync-suppressions')->assertExitCode(0);

        $this->assertSame(1, $this->suppressionCount($email, 'bounce'));
    }

    public function test_block_rows_are_upserted_with_correct_reason_enum(): void
    {
        $email = $this->prefix . 'block@example.com';

        Http::fake([
            'https://api.sendgrid.com/v3/suppression/bounces*'        => Http::response([], 200),
            'https://api.sendgrid.com/v3/suppression/blocks*'         => Http::response(
                $this->makeSuppressionList([$email], 'IP block'),
                200
            ),
            'https://api.sendgrid.com/v3/suppression/invalid_emails*' => Http::response([], 200),
            'https://api.sendgrid.com/v3/suppression/spam_reports*'   => Http::response([], 200),
        ]);

        $this->artisan('sendgrid:sync-suppressions')->assertExitCode(0);

        $this->assertSame(1, $this->suppressionCount($email, 'block'));
    }

    public function test_invalid_email_rows_stored_as_invalid_reason(): void
    {
        $email = $this->prefix . 'invalid@bad-domain.invalid';

        Http::fake([
            'https://api.sendgrid.com/v3/suppression/bounces*'        => Http::response([], 200),
            'https://api.sendgrid.com/v3/suppression/blocks*'         => Http::response([], 200),
            'https://api.sendgrid.com/v3/suppression/invalid_emails*' => Http::response(
                $this->makeSuppressionList([$email], 'Undeliverable due to invalid address'),
                200
            ),
            'https://api.sendgrid.com/v3/suppression/spam_reports*'   => Http::response([], 200),
        ]);

        $this->artisan('sendgrid:sync-suppressions')->assertExitCode(0);

        $this->assertSame(1, $this->suppressionCount($email, 'invalid'));
    }

    public function test_spam_report_rows_stored_as_spam_report_reason(): void
    {
        $email = $this->prefix . 'spam@example.com';

        Http::fake([
            'https://api.sendgrid.com/v3/suppression/bounces*'        => Http::response([], 200),
            'https://api.sendgrid.com/v3/suppression/blocks*'         => Http::response([], 200),
            'https://api.sendgrid.com/v3/suppression/invalid_emails*' => Http::response([], 200),
            'https://api.sendgrid.com/v3/suppression/spam_reports*'   => Http::response(
                $this->makeSuppressionList([$email], 'User marked as spam'),
                200
            ),
        ]);

        $this->artisan('sendgrid:sync-suppressions')->assertExitCode(0);

        $this->assertSame(1, $this->suppressionCount($email, 'spam_report'));
    }

    public function test_all_four_endpoints_called_with_bearer_token(): void
    {
        Http::fake([
            'https://api.sendgrid.com/v3/suppression/*' => Http::response([], 200),
        ]);

        $this->artisan('sendgrid:sync-suppressions')->assertExitCode(0);

        // Each of the four endpoints must be called exactly once.
        $sentUrls = [];
        Http::assertSentCount(4);
        Http::recorded(function ($request) use (&$sentUrls): bool {
            $sentUrls[] = $request->url();
            // Every request must carry the Bearer token.
            $this->assertStringStartsWith('Bearer SG.', $request->header('Authorization')[0]);
            return true;
        });
    }

    public function test_api_error_on_one_endpoint_does_not_abort_others(): void
    {
        $email = $this->prefix . 'after-error@example.com';

        Http::fake([
            'https://api.sendgrid.com/v3/suppression/bounces*'        => Http::response([], 500),
            'https://api.sendgrid.com/v3/suppression/blocks*'         => Http::response([], 500),
            'https://api.sendgrid.com/v3/suppression/invalid_emails*' => Http::response(
                $this->makeSuppressionList([$email]),
                200
            ),
            'https://api.sendgrid.com/v3/suppression/spam_reports*'   => Http::response([], 500),
        ]);

        // Must exit SUCCESS even if some endpoints fail (graceful degradation).
        $this->artisan('sendgrid:sync-suppressions')->assertExitCode(0);

        // The successful endpoint's row must still have been written.
        $this->assertSame(1, $this->suppressionCount($email, 'invalid'));
    }

    public function test_all_endpoints_500_exits_success_with_zero_upserts(): void
    {
        Http::fake([
            'https://api.sendgrid.com/v3/suppression/*' => Http::response([], 500),
        ]);

        $this->artisan('sendgrid:sync-suppressions')
            ->expectsOutputToContain('Done. Total upserts: 0')
            ->assertExitCode(0);
    }

    public function test_upsert_is_idempotent_re_running_does_not_duplicate(): void
    {
        $email = $this->prefix . 'idempotent@example.com';

        Http::fake([
            'https://api.sendgrid.com/v3/suppression/bounces*'        => Http::response(
                $this->makeSuppressionList([$email]),
                200
            ),
            'https://api.sendgrid.com/v3/suppression/blocks*'         => Http::response([], 200),
            'https://api.sendgrid.com/v3/suppression/invalid_emails*' => Http::response([], 200),
            'https://api.sendgrid.com/v3/suppression/spam_reports*'   => Http::response([], 200),
        ]);

        $this->artisan('sendgrid:sync-suppressions')->assertExitCode(0);
        $this->artisan('sendgrid:sync-suppressions')->assertExitCode(0);

        // Still exactly 1 row (UNIQUE key on email+reason, updateOrInsert is idempotent).
        $this->assertSame(1, $this->suppressionCount($email, 'bounce'));
    }

    public function test_max_option_is_forwarded_as_limit_query_param(): void
    {
        Http::fake([
            'https://api.sendgrid.com/v3/suppression/*' => Http::response([], 200),
        ]);

        $this->artisan('sendgrid:sync-suppressions', ['--max' => '50'])->assertExitCode(0);

        Http::assertSent(function ($request): bool {
            return str_contains($request->url(), 'sendgrid.com/v3/suppression/')
                && $request->data()['limit'] === 50;
        });
    }

    public function test_row_with_empty_email_field_is_skipped(): void
    {
        Http::fake([
            'https://api.sendgrid.com/v3/suppression/bounces*' => Http::response([
                ['email' => '', 'created' => time()],
                ['email' => $this->prefix . 'valid@example.com', 'created' => time()],
            ], 200),
            'https://api.sendgrid.com/v3/suppression/blocks*'         => Http::response([], 200),
            'https://api.sendgrid.com/v3/suppression/invalid_emails*' => Http::response([], 200),
            'https://api.sendgrid.com/v3/suppression/spam_reports*'   => Http::response([], 200),
        ]);

        $this->artisan('sendgrid:sync-suppressions')->assertExitCode(0);

        // Empty email must NOT produce a row; valid email must.
        $this->assertSame(0, DB::table('email_suppression')->where('email', '')->count());
        $this->assertSame(1, $this->suppressionCount($this->prefix . 'valid@example.com', 'bounce'));
    }

    public function test_detail_field_is_truncated_to_500_characters(): void
    {
        $email  = $this->prefix . 'detail@example.com';
        $detail = str_repeat('x', 600);   // over the 500-char mb_substr cap

        Http::fake([
            'https://api.sendgrid.com/v3/suppression/bounces*' => Http::response([
                ['email' => $email, 'created' => time(), 'reason' => $detail],
            ], 200),
            'https://api.sendgrid.com/v3/suppression/blocks*'         => Http::response([], 200),
            'https://api.sendgrid.com/v3/suppression/invalid_emails*' => Http::response([], 200),
            'https://api.sendgrid.com/v3/suppression/spam_reports*'   => Http::response([], 200),
        ]);

        $this->artisan('sendgrid:sync-suppressions')->assertExitCode(0);

        $row = DB::table('email_suppression')->where('email', $email)->where('reason', 'bounce')->first();
        $this->assertNotNull($row);
        $this->assertLessThanOrEqual(500, mb_strlen($row->detail));
    }
}
