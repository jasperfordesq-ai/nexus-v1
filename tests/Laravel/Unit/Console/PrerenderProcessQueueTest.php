<?php
// Copyright © 2024–2026 Jasper Ford
// SPDX-License-Identifier: AGPL-3.0-or-later
// Author: Jasper Ford
// See NOTICE file for attribution and acknowledgements.

declare(strict_types=1);

namespace Tests\Laravel\Unit\Console;

use App\Console\Commands\PrerenderProcessQueue;
use App\Services\PrerenderService;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Illuminate\Support\Facades\DB; // used in setUp for tenant insert
use Mockery;
use Tests\Laravel\TestCase;

/**
 * Tests for prerender:process-queue Artisan command.
 *
 * Uses unique tenant id 99717 for isolation.
 * Covers --claim-next, --heartbeat-id, --finalise-id, empty queue, and
 * parseCounters static.
 */
class PrerenderProcessQueueTest extends TestCase
{
    use DatabaseTransactions;

    private const TENANT_ID = 99717;

    /** @var \Mockery\MockInterface&PrerenderService */
    private \Mockery\MockInterface $service;

    protected function setUp(): void
    {
        parent::setUp();

        DB::table('tenants')->insertOrIgnore([
            'id'         => self::TENANT_ID,
            'name'       => 'Test Prerender Process Tenant',
            'slug'       => 'test-prerender-process-' . self::TENANT_ID,
            'is_active'  => 1,
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        \App\Core\TenantContext::setById(self::TENANT_ID);

        $this->service = Mockery::mock(PrerenderService::class);
        $this->app->instance(PrerenderService::class, $this->service);
    }

    // -------------------------------------------------------------------------
    // --claim-next: empty queue
    // -------------------------------------------------------------------------

    public function test_claim_next_exits_success_when_queue_is_empty(): void
    {
        $this->service
            ->shouldReceive('claimNextJob')
            ->once()
            ->with(Mockery::type('string'))
            ->andReturn(null);

        $this->artisan('prerender:process-queue', ['--claim-next' => true])
            ->assertExitCode(0);
    }

    public function test_claim_tokens_include_a_non_repeating_random_nonce(): void
    {
        $tokens = [];
        $this->service
            ->shouldReceive('claimNextJob')
            ->twice()
            ->with(Mockery::on(function (mixed $token) use (&$tokens): bool {
                if (!is_string($token)
                    || preg_match('/:[a-f0-9]{32}$/', $token) !== 1
                    || strlen($token) > 128) {
                    return false;
                }
                $tokens[] = $token;
                return true;
            }))
            ->andReturn(null);

        $this->artisan('prerender:process-queue', ['--claim-next' => true])->assertExitCode(0);
        $this->artisan('prerender:process-queue', ['--claim-next' => true])->assertExitCode(0);

        $this->assertCount(2, $tokens);
        $this->assertNotSame($tokens[0], $tokens[1]);
    }

    // -------------------------------------------------------------------------
    // --claim-next: row available — emits JSON
    // -------------------------------------------------------------------------

    public function test_claim_next_claims_and_marks_running_when_row_exists(): void
    {
        $claimToken = null;
        $row = [
            'id'          => 42,
            'tenant_id'   => self::TENANT_ID,
            'routes'      => '/foo,/bar',
            'force_render' => 0,
            'dry_run'     => 0,
        ];

        $this->service
            ->shouldReceive('claimNextJob')
            ->once()
            ->with(Mockery::on(function (mixed $token) use (&$claimToken): bool {
                if (!is_string($token)
                    || strlen($token) > 128
                    || preg_match('/^[A-Za-z0-9_.-]{1,80}:[0-9]+:[a-f0-9]{32}$/', $token) !== 1) {
                    return false;
                }
                $claimToken = $token;
                return true;
            }))
            ->andReturn($row);

        $this->service
            ->shouldReceive('markRunning')
            ->once()
            ->with(42, Mockery::on(
                function (mixed $token) use (&$claimToken): bool {
                    return $token === $claimToken;
                }
            ))
            ->andReturn(true);

        $this->artisan('prerender:process-queue', ['--claim-next' => true])
            ->assertExitCode(0);
    }

    // -------------------------------------------------------------------------
    // --claim-next: shell-export format
    // -------------------------------------------------------------------------

    public function test_claim_next_with_shell_export_outputs_key_value_lines(): void
    {
        $row = [
            'id'           => 7,
            'tenant_id'    => self::TENANT_ID,
            'routes'       => '/home',
            'force_render' => 0,
            'dry_run'      => 1,
        ];

        $this->service
            ->shouldReceive('claimNextJob')
            ->once()
            ->andReturn($row);

        $this->service
            ->shouldReceive('markRunning')
            ->once()
            ->with(7, Mockery::type('string'))
            ->andReturn(true);

        $this->artisan('prerender:process-queue', ['--claim-next' => true, '--shell-export' => true])
            ->expectsOutputToContain('JOB_ID=7')
            ->expectsOutputToContain('JOB_CLAIMED_BY=')
            ->assertExitCode(0);
    }

    public function test_claim_next_fails_if_the_claim_is_cancelled_before_running(): void
    {
        $this->service
            ->shouldReceive('claimNextJob')
            ->once()
            ->andReturn([
                'id' => 43,
                'tenant_id' => self::TENANT_ID,
                'routes' => '/about',
                'force_render' => 0,
                'dry_run' => 0,
            ]);
        $this->service
            ->shouldReceive('markRunning')
            ->once()
            ->with(43, Mockery::type('string'))
            ->andReturn(false);

        $this->artisan('prerender:process-queue', ['--claim-next' => true])
            ->expectsOutputToContain('Claim ownership was lost')
            ->assertExitCode(\Illuminate\Console\Command::FAILURE);
    }

    // -------------------------------------------------------------------------
    // --finalise-id: missing id → INVALID
    // -------------------------------------------------------------------------

    public function test_finalise_exits_invalid_when_no_subcommand_given(): void
    {
        // No --claim-next or --finalise-id → INVALID (exit 2).
        $this->artisan('prerender:process-queue')
            ->assertExitCode(\Illuminate\Console\Command::INVALID);
    }

    public function test_enqueue_authoritative_writes_a_fenced_global_job(): void
    {
        $this->service
            ->shouldReceive('enqueueAuthoritativeRebuildIntent')
            ->once()
            ->with(null)
            ->andReturn([
                'job_id' => 812,
                'cancelled_jobs' => 2,
                'cancelled_active_jobs' => 1,
            ]);

        $this->artisan('prerender:process-queue', ['--enqueue-authoritative' => true])
            ->expectsOutput('812')
            ->assertExitCode(0);
    }

    public function test_heartbeat_renews_the_matching_claim_lease(): void
    {
        $this->service
            ->shouldReceive('heartbeatJob')
            ->once()
            ->with(77, 'host-a:123')
            ->andReturn(true);

        $this->artisan('prerender:process-queue', [
            '--heartbeat-id' => '77',
            '--claimed-by' => 'host-a:123',
        ])->assertExitCode(0);
    }

    public function test_heartbeat_requires_a_claim_owner_token(): void
    {
        $this->service->shouldNotReceive('heartbeatJob');

        $this->artisan('prerender:process-queue', [
            '--heartbeat-id' => '77',
        ])->assertExitCode(\Illuminate\Console\Command::INVALID);
    }

    public function test_heartbeat_fails_when_claim_ownership_was_lost(): void
    {
        $this->service
            ->shouldReceive('heartbeatJob')
            ->once()
            ->with(77, 'stale-host:123')
            ->andReturn(false);

        $this->artisan('prerender:process-queue', [
            '--heartbeat-id' => '77',
            '--claimed-by' => 'stale-host:123',
        ])->assertExitCode(\Illuminate\Console\Command::FAILURE);
    }

    // -------------------------------------------------------------------------
    // --finalise-id: bad status → INVALID
    // -------------------------------------------------------------------------

    public function test_finalise_exits_invalid_when_status_is_not_recognised(): void
    {
        // The command validates status before any DB access — use any numeric id.
        $this->artisan('prerender:process-queue', [
            '--finalise-id' => '9999',
            '--status'      => 'bogus',
        ])->assertExitCode(\Illuminate\Console\Command::INVALID);
    }

    public function test_finalise_requires_a_claim_owner_token(): void
    {
        $this->service->shouldNotReceive('finaliseJob');

        $this->artisan('prerender:process-queue', [
            '--finalise-id' => '99',
            '--status' => 'succeeded',
        ])->assertExitCode(\Illuminate\Console\Command::INVALID);
    }

    // -------------------------------------------------------------------------
    // --finalise-id: happy path → delegates to service
    // -------------------------------------------------------------------------

    public function test_finalise_delegates_to_service_on_valid_args(): void
    {
        $this->service
            ->shouldReceive('finaliseJob')
            ->once()
            ->with(99, 'succeeded', Mockery::any(), Mockery::any(), Mockery::any(), Mockery::any(), Mockery::any(), Mockery::any(), Mockery::any(), 'worker-99')
            ->andReturn(true);

        $this->artisan('prerender:process-queue', [
            '--finalise-id' => '99',
            '--status'      => 'succeeded',
            '--planned'     => '10',
            '--rendered'    => '8',
            '--exit-code'   => '0',
            '--duration'    => '12',
            '--claimed-by'  => 'worker-99',
        ])->assertExitCode(0);
    }

    public function test_finalise_fails_when_claim_ownership_was_lost(): void
    {
        $this->service
            ->shouldReceive('finaliseJob')
            ->once()
            ->andReturn(false);

        $this->artisan('prerender:process-queue', [
            '--finalise-id' => '99',
            '--status' => 'failed',
            '--claimed-by' => 'stale-worker',
        ])->assertExitCode(\Illuminate\Console\Command::FAILURE);
    }

    // -------------------------------------------------------------------------
    // --finalise-id: partial + counters
    // -------------------------------------------------------------------------

    public function test_finalise_partial_status_is_accepted(): void
    {
        $this->service
            ->shouldReceive('finaliseJob')
            ->once()
            ->with(55, 'partial', Mockery::any(), Mockery::any(), Mockery::any(), Mockery::any(), Mockery::any(), Mockery::any(), Mockery::any(), 'worker-55')
            ->andReturn(true);

        $this->artisan('prerender:process-queue', [
            '--finalise-id' => '55',
            '--status'      => 'partial',
            '--claimed-by'  => 'worker-55',
        ])->assertExitCode(0);
    }

    // -------------------------------------------------------------------------
    // parseCounters static — unit-level, no DI needed
    // -------------------------------------------------------------------------

    public function test_parse_counters_extracts_all_three_values_from_log(): void
    {
        $log = implode("\n", [
            '[INFO] Planned 30 page(s) to refresh',
            '[INFO] 28 pre-rendered page(s) injected into cache',
            '[WARN] 2 rendered page(s) discarded (invalid)',
        ]);

        [$planned, $rendered, $invalid] = PrerenderProcessQueue::parseCounters($log);

        $this->assertSame(30, $planned);
        $this->assertSame(28, $rendered);
        $this->assertSame(2, $invalid);
    }

    public function test_parse_counters_returns_null_for_missing_values(): void
    {
        [$planned, $rendered, $invalid] = PrerenderProcessQueue::parseCounters('No counters here.');

        $this->assertNull($planned);
        $this->assertNull($rendered);
        $this->assertNull($invalid);
    }

    public function test_parse_counters_handles_zero_values(): void
    {
        $log = implode("\n", [
            'Planned 0 page(s) to refresh',
            '0 pre-rendered page(s) injected',
            '0 rendered page(s) discarded',
        ]);

        [$planned, $rendered, $invalid] = PrerenderProcessQueue::parseCounters($log);

        $this->assertSame(0, $planned);
        $this->assertSame(0, $rendered);
        $this->assertSame(0, $invalid);
    }
}
