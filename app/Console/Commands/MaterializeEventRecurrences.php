<?php
// Copyright © 2024–2026 Jasper Ford
// SPDX-License-Identifier: AGPL-3.0-or-later
// Author: Jasper Ford
// See NOTICE file for attribution and acknowledgements.

declare(strict_types=1);

namespace App\Console\Commands;

use App\Services\EventHealthService;
use App\Services\EventRecurrenceMaterializationService;
use Illuminate\Console\Command;

/** Bounded rolling materialization and payload-free operational status. */
final class MaterializeEventRecurrences extends Command
{
    protected $signature = 'events:materialize-recurrences
        {--tenant= : Process or inspect one tenant only}
        {--limit= : Maximum due series processed in this run}
        {--status : Print the payload-free recurrence health snapshot without writing}
        {--json : Emit machine-readable JSON}';

    protected $description = 'Top up v2 never-ending Event recurrences within the configured rolling horizon';

    public function handle(
        EventRecurrenceMaterializationService $materializer,
        EventHealthService $health,
    ): int {
        $tenantId = $this->positiveOption('tenant');
        if ($tenantId === false) {
            return self::INVALID;
        }
        $limit = $this->positiveOption('limit');
        if ($limit === false) {
            return self::INVALID;
        }

        if ((bool) $this->option('status')) {
            $snapshot = $health->snapshot($tenantId)['recurrence'];
            $this->output($snapshot);

            return $snapshot['unhealthy'] ? self::FAILURE : self::SUCCESS;
        }

        $summary = $materializer->materialize($tenantId, $limit);
        $this->output($summary);

        return $summary['failed'] === 0 ? self::SUCCESS : self::FAILURE;
    }

    private function positiveOption(string $name): int|false|null
    {
        $value = $this->option($name);
        if ($value === null || $value === '') {
            return null;
        }
        $validated = filter_var($value, FILTER_VALIDATE_INT, ['options' => ['min_range' => 1]]);
        if ($validated === false) {
            $this->error("The --{$name} option must be a positive integer.");

            return false;
        }

        return (int) $validated;
    }

    /** @param array<string,mixed> $payload */
    private function output(array $payload): void
    {
        if ((bool) $this->option('json')) {
            $this->line(json_encode($payload, JSON_THROW_ON_ERROR | JSON_UNESCAPED_SLASHES));

            return;
        }
        foreach ($payload as $key => $value) {
            $this->line($key . '=' . (is_array($value)
                ? json_encode($value, JSON_THROW_ON_ERROR | JSON_UNESCAPED_SLASHES)
                : var_export($value, true)));
        }
    }
}
