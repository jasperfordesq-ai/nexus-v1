<?php
// Copyright © 2024–2026 Jasper Ford
// SPDX-License-Identifier: AGPL-3.0-or-later
// Author: Jasper Ford
// See NOTICE file for attribution and acknowledgements.

declare(strict_types=1);

namespace Tests\Laravel\Unit\Console;

use App\Core\TenantContext;
use App\Services\CaringCommunity\CaringNudgeService;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Queue;
use Mockery;
use Tests\Laravel\TestCase;

/**
 * Tests for caring:nudges-dispatch console command.
 *
 * Uses unique tenant id 99738 for isolation.
 *
 * Strategy:
 *   - All tests mock CaringNudgeService so we never hit Notification::create,
 *     real SMTP, or the complex tandem-matching pipeline.
 *   - DB side-effects are verified via the caring_smart_nudges table only when
 *     the real service is invoked; those paths are covered by mocks here.
 */
class DispatchCaringNudgesTest extends TestCase
{
    use DatabaseTransactions;

    private const TENANT_ID   = 99738;
    private const TENANT_SLUG = 'test-nudges-dispatch-99738';

    protected function setUp(): void
    {
        parent::setUp();

        Queue::fake();

        DB::table('tenants')->insertOrIgnore([
            'id'         => self::TENANT_ID,
            'name'       => 'Test Nudges Dispatch Tenant',
            'slug'       => self::TENANT_SLUG,
            'is_active'  => 1,
            'features'   => json_encode(['caring_community' => true]),
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        TenantContext::setById(self::TENANT_ID);
    }

    // ------------------------------------------------------------------ //
    // Helper                                                               //
    // ------------------------------------------------------------------ //

    /**
     * Bind a mock CaringNudgeService with a controlled dispatchDue return value.
     *
     * @param array{enabled:bool,dry_run:bool,candidates:int,sent:int,skipped:int,items:list<array<string,mixed>>} $result
     */
    private function mockService(array $result): void
    {
        $mock = Mockery::mock(CaringNudgeService::class);
        $mock->shouldReceive('dispatchDue')
            ->andReturn($result);
        $this->app->instance(CaringNudgeService::class, $mock);
    }

    /** Standard empty-result shape returned when nudges are disabled. */
    private function disabledResult(): array
    {
        return ['enabled' => false, 'dry_run' => false, 'candidates' => 0, 'sent' => 0, 'skipped' => 0, 'items' => []];
    }

    /** Standard result shape for a successful dispatch of $n nudges. */
    private function sentResult(int $sent, int $candidates = -1): array
    {
        return [
            'enabled'    => true,
            'dry_run'    => false,
            'candidates' => $candidates >= 0 ? $candidates : $sent,
            'sent'       => $sent,
            'skipped'    => 0,
            'items'      => [],
        ];
    }

    // ------------------------------------------------------------------ //
    // Tests                                                                //
    // ------------------------------------------------------------------ //

    public function test_exits_success_when_no_nudges_sent(): void
    {
        $this->mockService($this->sentResult(0));

        $this->artisan('caring:nudges-dispatch', ['--tenant' => self::TENANT_ID])
            ->assertExitCode(0);
    }

    public function test_exits_success_when_nudges_are_sent(): void
    {
        $this->mockService($this->sentResult(3));

        $this->artisan('caring:nudges-dispatch', ['--tenant' => self::TENANT_ID])
            ->assertExitCode(0);
    }

    public function test_output_contains_summary_line(): void
    {
        $this->mockService($this->sentResult(2));

        $this->artisan('caring:nudges-dispatch', ['--tenant' => self::TENANT_ID])
            ->expectsOutputToContain('Smart nudges dispatched: 2')
            ->assertExitCode(0);
    }

    public function test_exits_failure_when_service_throws(): void
    {
        $mock = Mockery::mock(CaringNudgeService::class);
        $mock->shouldReceive('dispatchDue')
            ->andThrow(new \RuntimeException('Simulated failure'));
        $this->app->instance(CaringNudgeService::class, $mock);

        $this->artisan('caring:nudges-dispatch', ['--tenant' => self::TENANT_ID])
            ->assertExitCode(1);
    }

    public function test_caring_community_feature_disabled_skips_dispatch(): void
    {
        // Disable the feature for this tenant.
        DB::table('tenants')
            ->where('id', self::TENANT_ID)
            ->update(['features' => json_encode(['caring_community' => false])]);

        // Service should NOT be called (feature gate happens before service call).
        $mock = Mockery::mock(CaringNudgeService::class);
        $mock->shouldNotReceive('dispatchDue');
        $this->app->instance(CaringNudgeService::class, $mock);

        $this->artisan('caring:nudges-dispatch', ['--tenant' => self::TENANT_ID])
            ->assertExitCode(0);
    }

    public function test_dry_run_option_is_passed_to_service(): void
    {
        $capturedDryRun = null;
        $mock = Mockery::mock(CaringNudgeService::class);
        $mock->shouldReceive('dispatchDue')
            ->once()
            ->andReturnUsing(function (int $tid, $limit, bool $dryRun) use (&$capturedDryRun) {
                $capturedDryRun = $dryRun;
                return ['enabled' => true, 'dry_run' => true, 'candidates' => 0, 'sent' => 0, 'skipped' => 0, 'items' => []];
            });
        $this->app->instance(CaringNudgeService::class, $mock);

        $this->artisan('caring:nudges-dispatch', [
            '--tenant'  => self::TENANT_ID,
            '--dry-run' => true,
        ])->assertExitCode(0);

        $this->assertTrue($capturedDryRun, '--dry-run must be forwarded to service as true');
    }

    public function test_limit_option_is_passed_to_service(): void
    {
        $capturedLimit = null;
        $mock = Mockery::mock(CaringNudgeService::class);
        $mock->shouldReceive('dispatchDue')
            ->once()
            ->andReturnUsing(function (int $tid, ?int $limit, bool $dryRun) use (&$capturedLimit) {
                $capturedLimit = $limit;
                return $this->sentResult(0);
            });
        $this->app->instance(CaringNudgeService::class, $mock);

        $this->artisan('caring:nudges-dispatch', [
            '--tenant' => self::TENANT_ID,
            '--limit'  => '7',
        ])->assertExitCode(0);

        $this->assertSame(7, $capturedLimit, '--limit must be forwarded as integer 7');
    }

    public function test_nudges_disabled_result_exits_success(): void
    {
        $this->mockService($this->disabledResult());

        $this->artisan('caring:nudges-dispatch', ['--tenant' => self::TENANT_ID])
            ->assertExitCode(0);
    }

    public function test_summary_shows_zero_failures_on_clean_run(): void
    {
        $this->mockService($this->sentResult(1));

        $this->artisan('caring:nudges-dispatch', ['--tenant' => self::TENANT_ID])
            ->expectsOutputToContain('tenant failures: 0')
            ->assertExitCode(0);
    }

    public function test_tenant_id_option_targets_only_that_tenant(): void
    {
        // Insert a second active tenant that should NOT be dispatched to.
        DB::table('tenants')->insertOrIgnore([
            'id'         => 99739,
            'name'       => 'Other Tenant 99739',
            'slug'       => 'other-99739',
            'is_active'  => 1,
            'features'   => json_encode(['caring_community' => true]),
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        $calledTenantIds = [];
        $mock = Mockery::mock(CaringNudgeService::class);
        $mock->shouldReceive('dispatchDue')
            ->andReturnUsing(function (int $tid, $limit, bool $dry) use (&$calledTenantIds) {
                $calledTenantIds[] = $tid;
                return $this->sentResult(0);
            });
        $this->app->instance(CaringNudgeService::class, $mock);

        $this->artisan('caring:nudges-dispatch', ['--tenant' => self::TENANT_ID])
            ->assertExitCode(0);

        $this->assertSame([self::TENANT_ID], $calledTenantIds, 'Only the specified tenant should be dispatched to');
    }
}
