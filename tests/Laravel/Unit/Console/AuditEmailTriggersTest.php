<?php
// Copyright © 2024–2026 Jasper Ford
// SPDX-License-Identifier: AGPL-3.0-or-later
// Author: Jasper Ford
// See NOTICE file for attribution and acknowledgements.

declare(strict_types=1);

namespace Tests\Laravel\Unit\Console;

use App\Services\EmailTriggerAuditService;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Queue;
use Tests\Laravel\TestCase;

/**
 * Tests for email:audit-triggers console command.
 *
 * Uses unique tenant id 99714 for isolation.
 *
 * The command is READ-ONLY (no writes of its own); it delegates all
 * analysis to EmailTriggerAuditService. Tests assert:
 *   - exit code behaviour (0 when no criticals, 1 when criticals exist)
 *   - human-readable output lines
 *   - JSON output flag
 *   - --tenant / --hours option forwarding
 *   - service injection contract
 */
class AuditEmailTriggersTest extends TestCase
{
    use DatabaseTransactions;

    private const TENANT_ID = 99714;

    protected function setUp(): void
    {
        parent::setUp();

        Queue::fake();

        DB::table('tenants')->insertOrIgnore([
            'id'         => self::TENANT_ID,
            'name'       => 'Test Email Audit Tenant',
            'slug'       => 'test-email-audit-' . self::TENANT_ID,
            'is_active'  => 1,
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        \App\Core\TenantContext::setById(self::TENANT_ID);
    }

    // ------------------------------------------------------------------
    // Helpers
    // ------------------------------------------------------------------

    /**
     * Build a minimal audit result matching the EmailTriggerAuditService return shape.
     *
     * @param array<string,int> $issuesBySeverity
     * @param list<array<string,mixed>> $issues
     */
    private function makeAuditResult(
        int $score = 1000,
        array $issuesBySeverity = ['critical' => 0, 'warning' => 0, 'info' => 0],
        array $issues = [],
        int $matrixCount = 10,
        int $windowHours = 24,
        ?int $tenantId = null
    ): array {
        return [
            'checked_at'          => now()->toIso8601String(),
            'window_hours'        => $windowHours,
            'tenant_id'           => $tenantId,
            'score'               => $score,
            'matrix_count'        => $matrixCount,
            'issue_count'         => count($issues),
            'issues_by_severity'  => $issuesBySeverity,
            'issues'              => $issues,
            'matrix'              => [],
            'source_tables'       => [],
        ];
    }

    /**
     * Build a single issue array matching the shape emitted by EmailTriggerAuditService::issue().
     */
    private function makeIssue(
        string $code,
        string $severity,
        string $module,
        string $event,
        ?int $tenantId = null,
        array $params = []
    ): array {
        return [
            'code'      => $code,
            'severity'  => $severity,
            'tenant_id' => $tenantId,
            'module'    => $module,
            'event'     => $event,
            'params'    => $params,
        ];
    }

    // ------------------------------------------------------------------
    // Tests
    // ------------------------------------------------------------------

    public function test_exits_zero_when_audit_reports_no_criticals(): void
    {
        $service = $this->createMock(EmailTriggerAuditService::class);
        $service->expects($this->once())
            ->method('run')
            ->willReturn($this->makeAuditResult(score: 1000));

        $this->app->instance(EmailTriggerAuditService::class, $service);

        $this->artisan('email:audit-triggers')
            ->assertExitCode(0);
    }

    public function test_exits_one_when_at_least_one_critical_issue_found(): void
    {
        $issues = [
            $this->makeIssue('new_users_without_account_email_attempt', 'critical', 'registration', 'welcome_or_activation', self::TENANT_ID, ['count' => 5]),
        ];
        $result = $this->makeAuditResult(
            score: 910,
            issuesBySeverity: ['critical' => 1, 'warning' => 0, 'info' => 0],
            issues: $issues
        );

        $service = $this->createMock(EmailTriggerAuditService::class);
        $service->method('run')->willReturn($result);
        $this->app->instance(EmailTriggerAuditService::class, $service);

        $this->artisan('email:audit-triggers')
            ->assertExitCode(1);
    }

    public function test_exits_zero_when_only_warnings_no_criticals(): void
    {
        $issues = [
            $this->makeIssue('notification_queue_failed_recently', 'warning', 'notifications', 'queue_dispatch'),
        ];
        $result = $this->makeAuditResult(
            score: 965,
            issuesBySeverity: ['critical' => 0, 'warning' => 1, 'info' => 0],
            issues: $issues
        );

        $service = $this->createMock(EmailTriggerAuditService::class);
        $service->method('run')->willReturn($result);
        $this->app->instance(EmailTriggerAuditService::class, $service);

        $this->artisan('email:audit-triggers')
            ->assertExitCode(0);
    }

    public function test_output_contains_score_line(): void
    {
        $service = $this->createMock(EmailTriggerAuditService::class);
        $service->method('run')->willReturn($this->makeAuditResult(score: 850));
        $this->app->instance(EmailTriggerAuditService::class, $service);

        $this->artisan('email:audit-triggers')
            ->expectsOutputToContain('Email trigger audit score: 850/1000')
            ->assertExitCode(0);
    }

    public function test_output_contains_window_hours_in_summary_line(): void
    {
        $result = $this->makeAuditResult(
            score: 1000,
            matrixCount: 95,
            windowHours: 48
        );

        $service = $this->createMock(EmailTriggerAuditService::class);
        $service->method('run')->willReturn($result);
        $this->app->instance(EmailTriggerAuditService::class, $service);

        // The summary line: "Window: 48h; matrix entries: 95; issues: 0"
        $this->artisan('email:audit-triggers', ['--hours' => '48'])
            ->expectsOutputToContain('Window: 48h')
            ->assertExitCode(0);
    }

    public function test_each_issue_is_printed_with_severity_in_bracket(): void
    {
        $issues = [
            $this->makeIssue('password_resets_without_email_attempt', 'critical', 'auth', 'password_reset_requested', self::TENANT_ID, ['count' => 3]),
        ];
        $result = $this->makeAuditResult(
            score: 910,
            issuesBySeverity: ['critical' => 1, 'warning' => 0, 'info' => 0],
            issues: $issues
        );

        $service = $this->createMock(EmailTriggerAuditService::class);
        $service->method('run')->willReturn($result);
        $this->app->instance(EmailTriggerAuditService::class, $service);

        $this->artisan('email:audit-triggers')
            ->expectsOutputToContain('[CRITICAL]')
            ->assertExitCode(1);
    }

    public function test_each_issue_is_printed_with_module_slash_event(): void
    {
        $issues = [
            $this->makeIssue('password_resets_without_email_attempt', 'critical', 'auth', 'password_reset_requested', self::TENANT_ID, ['count' => 3]),
        ];
        $result = $this->makeAuditResult(
            score: 910,
            issuesBySeverity: ['critical' => 1, 'warning' => 0, 'info' => 0],
            issues: $issues
        );

        $service = $this->createMock(EmailTriggerAuditService::class);
        $service->method('run')->willReturn($result);
        $this->app->instance(EmailTriggerAuditService::class, $service);

        $this->artisan('email:audit-triggers')
            ->expectsOutputToContain('auth/password_reset_requested')
            ->assertExitCode(1);
    }

    public function test_each_issue_is_printed_with_issue_code(): void
    {
        $issues = [
            $this->makeIssue('password_resets_without_email_attempt', 'critical', 'auth', 'password_reset_requested', self::TENANT_ID, ['count' => 3]),
        ];
        $result = $this->makeAuditResult(
            score: 910,
            issuesBySeverity: ['critical' => 1, 'warning' => 0, 'info' => 0],
            issues: $issues
        );

        $service = $this->createMock(EmailTriggerAuditService::class);
        $service->method('run')->willReturn($result);
        $this->app->instance(EmailTriggerAuditService::class, $service);

        $this->artisan('email:audit-triggers')
            ->expectsOutputToContain('password_resets_without_email_attempt')
            ->assertExitCode(1);
    }

    public function test_issue_line_includes_count_param_when_present(): void
    {
        $issues = [
            $this->makeIssue('instant_notifications_stuck_pending', 'critical', 'notifications', 'instant_queue_dispatch', self::TENANT_ID, ['count' => 12]),
        ];
        $result = $this->makeAuditResult(
            score: 910,
            issuesBySeverity: ['critical' => 1, 'warning' => 0, 'info' => 0],
            issues: $issues
        );

        $service = $this->createMock(EmailTriggerAuditService::class);
        $service->method('run')->willReturn($result);
        $this->app->instance(EmailTriggerAuditService::class, $service);

        $this->artisan('email:audit-triggers')
            ->expectsOutputToContain('count=12')
            ->assertExitCode(1);
    }

    public function test_platform_issue_without_tenant_prints_platform_label(): void
    {
        $issues = [
            $this->makeIssue('direct_email_send_paths_remaining', 'warning', 'architecture', 'direct_send_surface', null),
        ];
        $result = $this->makeAuditResult(
            score: 965,
            issuesBySeverity: ['critical' => 0, 'warning' => 1, 'info' => 0],
            issues: $issues
        );

        $service = $this->createMock(EmailTriggerAuditService::class);
        $service->method('run')->willReturn($result);
        $this->app->instance(EmailTriggerAuditService::class, $service);

        $this->artisan('email:audit-triggers')
            ->expectsOutputToContain('platform')
            ->assertExitCode(0);
    }

    public function test_tenant_option_is_forwarded_to_service(): void
    {
        $capturedTenantId = null;

        $service = $this->createMock(EmailTriggerAuditService::class);
        $service->expects($this->once())
            ->method('run')
            ->willReturnCallback(function (?int $tid, int $hours) use (&$capturedTenantId): array {
                $capturedTenantId = $tid;
                return $this->makeAuditResult(tenantId: $tid, windowHours: $hours);
            });
        $this->app->instance(EmailTriggerAuditService::class, $service);

        $this->artisan('email:audit-triggers', ['--tenant' => (string) self::TENANT_ID])
            ->assertExitCode(0);

        $this->assertSame(self::TENANT_ID, $capturedTenantId);
    }

    public function test_hours_option_is_forwarded_to_service(): void
    {
        $capturedHours = null;

        $service = $this->createMock(EmailTriggerAuditService::class);
        $service->expects($this->once())
            ->method('run')
            ->willReturnCallback(function (?int $tid, int $hours) use (&$capturedHours): array {
                $capturedHours = $hours;
                return $this->makeAuditResult(windowHours: $hours);
            });
        $this->app->instance(EmailTriggerAuditService::class, $service);

        $this->artisan('email:audit-triggers', ['--hours' => '72'])
            ->assertExitCode(0);

        $this->assertSame(72, $capturedHours);
    }

    public function test_json_flag_outputs_score_in_json_and_exits_zero_when_no_criticals(): void
    {
        $result = $this->makeAuditResult(score: 1000);

        $service = $this->createMock(EmailTriggerAuditService::class);
        $service->method('run')->willReturn($result);
        $this->app->instance(EmailTriggerAuditService::class, $service);

        // Only one expectsOutputToContain per artisan() call — Mockery matches
        // the first matching expectation per doWrite call and removes it; a second
        // check on the same chunk would never fire.
        $this->artisan('email:audit-triggers', ['--json' => true])
            ->expectsOutputToContain('"score": 1000')
            ->assertExitCode(0);
    }

    public function test_json_flag_outputs_issue_count_in_json(): void
    {
        $result = $this->makeAuditResult(score: 1000);

        $service = $this->createMock(EmailTriggerAuditService::class);
        $service->method('run')->willReturn($result);
        $this->app->instance(EmailTriggerAuditService::class, $service);

        $this->artisan('email:audit-triggers', ['--json' => true])
            ->expectsOutputToContain('"issue_count": 0')
            ->assertExitCode(0);
    }

    public function test_json_flag_exits_one_when_criticals_present(): void
    {
        $issues = [
            $this->makeIssue('newsletter_queue_stale_processing', 'critical', 'newsletter', 'newsletter_queue_dispatch'),
        ];
        $result = $this->makeAuditResult(
            score: 910,
            issuesBySeverity: ['critical' => 1, 'warning' => 0, 'info' => 0],
            issues: $issues
        );

        $service = $this->createMock(EmailTriggerAuditService::class);
        $service->method('run')->willReturn($result);
        $this->app->instance(EmailTriggerAuditService::class, $service);

        $this->artisan('email:audit-triggers', ['--json' => true])
            ->expectsOutputToContain('"critical": 1')
            ->assertExitCode(1);
    }

    public function test_missing_tenant_option_passes_null_to_service(): void
    {
        $capturedTenantId = 'UNSET';

        $service = $this->createMock(EmailTriggerAuditService::class);
        $service->expects($this->once())
            ->method('run')
            ->willReturnCallback(function (?int $tid, int $hours) use (&$capturedTenantId): array {
                $capturedTenantId = $tid;
                return $this->makeAuditResult();
            });
        $this->app->instance(EmailTriggerAuditService::class, $service);

        $this->artisan('email:audit-triggers')
            ->assertExitCode(0);

        $this->assertNull($capturedTenantId);
    }

    public function test_hours_clamped_to_minimum_of_one(): void
    {
        $capturedHours = null;

        $service = $this->createMock(EmailTriggerAuditService::class);
        $service->expects($this->once())
            ->method('run')
            ->willReturnCallback(function (?int $tid, int $hours) use (&$capturedHours): array {
                $capturedHours = $hours;
                return $this->makeAuditResult(windowHours: $hours);
            });
        $this->app->instance(EmailTriggerAuditService::class, $service);

        // --hours=0 must be clamped to 1 by the command.
        $this->artisan('email:audit-triggers', ['--hours' => '0'])
            ->assertExitCode(0);

        $this->assertSame(1, $capturedHours);
    }

    public function test_hours_clamped_to_maximum_of_168(): void
    {
        $capturedHours = null;

        $service = $this->createMock(EmailTriggerAuditService::class);
        $service->expects($this->once())
            ->method('run')
            ->willReturnCallback(function (?int $tid, int $hours) use (&$capturedHours): array {
                $capturedHours = $hours;
                return $this->makeAuditResult(windowHours: $hours);
            });
        $this->app->instance(EmailTriggerAuditService::class, $service);

        // --hours=999 must be clamped to 168 by the command.
        $this->artisan('email:audit-triggers', ['--hours' => '999'])
            ->assertExitCode(0);

        $this->assertSame(168, $capturedHours);
    }

    public function test_multiple_criticals_exits_one(): void
    {
        $issues = [
            $this->makeIssue('new_users_without_account_email_attempt', 'critical', 'registration', 'welcome_or_activation', self::TENANT_ID, ['count' => 2]),
            $this->makeIssue('password_resets_without_email_attempt', 'critical', 'auth', 'password_reset_requested', self::TENANT_ID, ['count' => 1]),
        ];
        $result = $this->makeAuditResult(
            score: 820,
            issuesBySeverity: ['critical' => 2, 'warning' => 0, 'info' => 0],
            issues: $issues
        );

        $service = $this->createMock(EmailTriggerAuditService::class);
        $service->method('run')->willReturn($result);
        $this->app->instance(EmailTriggerAuditService::class, $service);

        // Exit code 1 confirms criticals gate fires regardless of count.
        $this->artisan('email:audit-triggers')
            ->expectsOutputToContain('[CRITICAL]')
            ->assertExitCode(1);
    }
}
