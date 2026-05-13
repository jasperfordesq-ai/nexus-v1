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
 * Both subcommands are idempotent and safe to call from a host cron.
 */
class PrerenderProcessQueue extends Command
{
    protected $signature = 'prerender:process-queue '
        . '{--claim-next : Claim the next queued job and exit} '
        . '{--shell-export : With --claim-next, emit KEY=VALUE lines for eval} '
        . '{--finalise-id= : Job id to finalise} '
        . '{--status= : succeeded|partial|failed} '
        . '{--planned= : Planned page count from worker log} '
        . '{--rendered= : Rendered page count from worker log} '
        . '{--invalid= : Discarded count from worker log} '
        . '{--exit-code= : Underlying script exit code} '
        . '{--duration= : Wall clock seconds} '
        . '{--log-file= : Path to log to ingest (tail used)} '
        . '{--error= : Optional error message}';

    protected $description = 'Claim or finalise a prerender job.';

    public function __construct(private readonly PrerenderService $service)
    {
        parent::__construct();
    }

    public function handle(): int
    {
        if ($this->option('claim-next')) return $this->claimNext();
        if ($this->option('finalise-id') !== null) return $this->finalise();

        $this->error('Specify --claim-next or --finalise-id={id}.');
        return self::INVALID;
    }

    private function claimNext(): int
    {
        $claimedBy = gethostname() . ':' . getmypid();
        $row = $this->service->claimNextJob($claimedBy);
        if (!$row) return self::SUCCESS;
        $this->service->markRunning((int) $row['id']);

        $tenantSlug = '';
        if (!empty($row['tenant_id'])) {
            $tenantSlug = (string) DB::table('tenants')
                ->where('id', (int) $row['tenant_id'])->value('slug');
        }

        $shell = (bool) $this->option('shell-export');
        if ($shell) {
            // Print KEY=VALUE lines suitable for `eval $(...)` in bash.
            // Quote values with single quotes (no embedded single-quotes
            // possible: routes are regex-validated and slugs are [A-Za-z0-9_-]).
            $this->line('JOB_ID=' . (int) $row['id']);
            $this->line("JOB_TENANT_SLUG='{$tenantSlug}'");
            $this->line("JOB_ROUTES='" . (string) ($row['routes'] ?? '') . "'");
            $this->line('JOB_FORCE=' . (!empty($row['force_render']) ? 1 : 0));
            $this->line('JOB_DRY_RUN=' . (!empty($row['dry_run']) ? 1 : 0));
        } else {
            $this->line(json_encode([
                'id'           => (int) $row['id'],
                'tenant_slug'  => $tenantSlug,
                'routes'       => $row['routes'] ?? null,
                'force'        => !empty($row['force_render']),
                'dry_run'      => !empty($row['dry_run']),
            ], JSON_UNESCAPED_SLASHES));
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
        $invalid  = $this->intOpt('invalid') ?? 0;
        $exit     = $this->intOpt('exit-code') ?? 0;
        $duration = $this->intOpt('duration') ?? 0;
        $error    = $this->option('error');

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
            $invalid  = $invalid !== null ? $invalid : ($li ?? 0);
        }

        $this->service->finaliseJob(
            $id, $status,
            $planned, $rendered, $invalid,
            $exit, $duration, $logExcerpt, $error
        );
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
            $matchInt('/(\d+) pre-rendered page\(s\) injected/'),
            $matchInt('/(\d+) rendered page\(s\) discarded/'),
        ];
    }
}
