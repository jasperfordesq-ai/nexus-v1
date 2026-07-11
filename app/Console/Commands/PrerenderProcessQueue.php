<?php
// Copyright © 2024–2026 Jasper Ford
// SPDX-License-Identifier: AGPL-3.0-or-later
// Author: Jasper Ford
// See NOTICE file for attribution and acknowledgements.

declare(strict_types=1);

namespace App\Console\Commands;

use App\Services\PrerenderService;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;

/**
 * Prerender queue orchestrator — atomic claim + result recording.
 *
 * The PHP container can't talk to the host docker daemon, so the actual
 * prerender-tenants.sh run lives on the host. This command provides the
 * two pieces of work the host wrapper can't safely do in bash:
 *
 *   prerender:process-queue --claim-next [--shell-export]
 *       Atomically pops the oldest queued row, transitions it to
 *       claimed → running, and prints the job descriptor. With
 *       --shell-export the output is `eval`-safe (KEY=VALUE lines).
 *       Empty output means the queue was empty.
 *
 *   prerender:process-queue --finalise-id={id} --status=... [counters]
 *       Writes the outcome back to the row, including planned/rendered/
 *       invalid counters parsed from the script log. Broadcasts the
 *       final state on the admin realtime channel.
 *
 *   prerender:process-queue --heartbeat-id={id} --claimed-by={token}
 *       Renews a running job's lease only while its immutable claim token
 *       still owns the row. A stale/superseded host receives a failure exit.
 *
 * All subcommands are idempotent and safe to call from a host cron.
 */
class PrerenderProcessQueue extends Command
{
    protected $signature = 'prerender:process-queue '
        . '{--claim-next : Claim the next queued job and exit} '
        . '{--enqueue-authoritative : Fence older work and enqueue a global authoritative rebuild} '
        . '{--shell-export : With --claim-next, emit KEY=VALUE lines for eval} '
        . '{--heartbeat-id= : Running job id whose lease should be renewed} '
        . '{--finalise-id= : Job id to finalise} '
        . '{--status= : succeeded|partial|failed} '
        . '{--planned= : Planned page count from worker log} '
        . '{--rendered= : Rendered page count from worker log} '
        . '{--invalid= : Discarded count from worker log} '
        . '{--exit-code= : Underlying script exit code} '
        . '{--duration= : Wall clock seconds} '
        . '{--log-file= : Path to log to ingest (tail used)} '
        . '{--claimed-by= : Immutable claim owner token used to fence stale workers} '
        . '{--error= : Optional error message}';

    protected $description = 'Claim, heartbeat, or finalise a prerender job.';

    public function __construct(private readonly PrerenderService $service)
    {
        parent::__construct();
    }

    public function handle(): int
    {
        if ($this->option('enqueue-authoritative')) return $this->enqueueAuthoritative();
        if ($this->option('claim-next')) return $this->claimNext();
        if ($this->option('heartbeat-id') !== null) return $this->heartbeat();
        if ($this->option('finalise-id') !== null) return $this->finalise();

        $this->error('Specify --enqueue-authoritative, --claim-next, --heartbeat-id={id}, or --finalise-id={id}.');
        return self::INVALID;
    }

    private function enqueueAuthoritative(): int
    {
        $result = $this->service->enqueueAuthoritativeRebuildIntent(null);
        $this->line((string) $result['job_id']);
        return self::SUCCESS;
    }

    private function claimNext(): int
    {
        // PID reuse must never let a later processor impersonate an older
        // claim. Include 128 bits of CSPRNG entropy while keeping the token
        // shell-export safe and within the VARCHAR(128) storage boundary.
        $hostname = preg_replace(
            '/[^A-Za-z0-9_.-]/',
            '_',
            (string) (gethostname() ?: 'unknown')
        ) ?: 'unknown';
        $claimedBy = substr($hostname, 0, 80)
            . ':' . getmypid()
            . ':' . bin2hex(random_bytes(16));
        $row = $this->service->claimNextJob($claimedBy);
        if (!$row) return self::SUCCESS;
        $claimedBy = (string) ($row['claimed_by'] ?? $claimedBy);
        if (!$this->service->markRunning((int) $row['id'], $claimedBy)) {
            $this->error('Claim ownership was lost before the job could start.');
            return self::FAILURE;
        }

        $tenantSlug = '';
        if (!empty($row['tenant_id'])) {
            $tenantSlug = (string) DB::table('tenants')
                ->where('id', (int) $row['tenant_id'])
                ->where('is_active', 1)
                ->value('slug');
            if ($tenantSlug === '') {
                $this->service->finaliseJob(
                    (int) $row['id'], 'failed', null, null, 0, 66, 0,
                    null, 'tenant no longer exists or is inactive', $claimedBy
                );
                $this->error('Claimed tenant no longer exists or is inactive.');
                return self::FAILURE;
            }
        }

        $shell = (bool) $this->option('shell-export');
        if ($shell) {
            // Print KEY=VALUE lines suitable for `eval $(...)` in bash.
            // Quote values with single quotes (no embedded single-quotes
            // possible: routes are regex-validated and slugs are [A-Za-z0-9_-]).
            $this->line('JOB_ID=' . (int) $row['id']);
            $this->line("JOB_CLAIMED_BY='{$claimedBy}'");
            $this->line("JOB_TENANT_SLUG='{$tenantSlug}'");
            $this->line("JOB_ROUTES='" . (string) ($row['routes'] ?? '') . "'");
            $this->line('JOB_FORCE=' . (!empty($row['force_render']) ? 1 : 0));
            $this->line('JOB_DRY_RUN=' . (!empty($row['dry_run']) ? 1 : 0));
        } else {
            $this->line(json_encode([
                'id'           => (int) $row['id'],
                'claimed_by'   => $claimedBy,
                'tenant_slug'  => $tenantSlug,
                'routes'       => $row['routes'] ?? null,
                'force'        => !empty($row['force_render']),
                'dry_run'      => !empty($row['dry_run']),
            ], JSON_UNESCAPED_SLASHES));
        }
        return self::SUCCESS;
    }

    private function heartbeat(): int
    {
        $id = (int) $this->option('heartbeat-id');
        $claimedBy = $this->option('claimed-by');
        if ($id <= 0) {
            $this->error('Invalid --heartbeat-id');
            return self::INVALID;
        }
        if (!is_string($claimedBy) || $claimedBy === '') {
            $this->error('--claimed-by is required for a heartbeat');
            return self::INVALID;
        }

        if (!$this->service->heartbeatJob($id, $claimedBy)) {
            $this->error("Heartbeat rejected for job #{$id}; claim ownership was lost.");
            return self::FAILURE;
        }

        return self::SUCCESS;
    }

    private function finalise(): int
    {
        $id = (int) $this->option('finalise-id');
        if ($id <= 0) {
            $this->error('Invalid --finalise-id');
            return self::INVALID;
        }
        $status = (string) ($this->option('status') ?? '');
        if (!in_array($status, ['succeeded', 'partial', 'failed'], true)) {
            $this->error('Invalid --status (succeeded|partial|failed)');
            return self::INVALID;
        }

        $planned  = $this->intOpt('planned');
        $rendered = $this->intOpt('rendered');
        $invalid  = $this->intOpt('invalid');
        $exit     = $this->intOpt('exit-code') ?? 0;
        $duration = $this->intOpt('duration') ?? 0;
        $error    = $this->option('error');
        $claimedBy = $this->option('claimed-by');
        if (!is_string($claimedBy) || $claimedBy === '') {
            $this->error('--claimed-by is required for finalisation');
            return self::INVALID;
        }

        $logExcerpt = null;
        $logFile = $this->option('log-file');
        if (is_string($logFile) && $logFile !== '' && is_readable($logFile)) {
            $logExcerpt = (string) file_get_contents($logFile);
        }

        // Auto-parse counters from log if not supplied on argv.
        if ($logExcerpt !== null) {
            [$lp, $lr, $li] = self::parseCounters($logExcerpt);
            $planned  = $planned  ?? $lp;
            $rendered = $rendered ?? $lr;
            $invalid  = $invalid ?? $li;
        }

        $finalised = $this->service->finaliseJob(
            $id, $status,
            $planned, $rendered, $invalid ?? 0,
            $exit, $duration, $logExcerpt, $error,
            $claimedBy
        );
        if (!$finalised) {
            $this->error("Finalisation rejected for job #{$id}; claim ownership was lost.");
            return self::FAILURE;
        }
        $this->info("Finalised job #{$id} status={$status} exit={$exit} duration={$duration}s");
        return self::SUCCESS;
    }

    private function intOpt(string $name): ?int
    {
        $v = $this->option($name);
        if ($v === null || $v === '') return null;
        return (int) $v;
    }

    /**
     * Extract counters from a prerender-tenants.sh log. Public/static so
     * tests can hit it without spinning up the command.
     *
     * @return array{0:int|null,1:int|null,2:int|null} [planned, rendered, invalid]
     */
    public static function parseCounters(string $log): array
    {
        $matchInt = function (string $pattern) use ($log): ?int {
            if (preg_match($pattern, $log, $m) === 1) return (int) $m[1];
            return null;
        };
        return [
            $matchInt('/Planned (\d+) page\(s\) to refresh/'),
            $matchInt('/(\d+) pre-rendered page\(s\) (?:injected|published)/'),
            // Asset-reference notices are warnings only: bot clients do not
            // execute those assets and the pages remain published. Do not
            // misreport them as discarded/invalid job output.
            $matchInt('/(\d+) rendered page\(s\) discarded/'),
        ];
    }
}
