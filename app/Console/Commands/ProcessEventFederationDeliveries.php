<?php
// Copyright © 2024–2026 Jasper Ford
// SPDX-License-Identifier: AGPL-3.0-or-later
// Author: Jasper Ford
// See NOTICE file for attribution and acknowledgements.

declare(strict_types=1);

namespace App\Console\Commands;

use App\Services\EventFederationDeliveryConsumer;
use App\Services\EventFederationDiagnostics;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Schema;

/** Independent worker and payload-free health surface for Event federation. */
final class ProcessEventFederationDeliveries extends Command
{
    protected $signature = 'events:process-federation
        {--tenant= : Process or inspect one tenant only}
        {--partner= : Process one external partner only}
        {--limit=50 : Maximum delivery facts claimed in this run}
        {--status : Print a payload-free health snapshot}
        {--json : Emit machine-readable output}';

    protected $description = 'Process or inspect the independent Event federation delivery ledger.';

    public function handle(
        EventFederationDeliveryConsumer $consumer,
        EventFederationDiagnostics $diagnostics,
    ): int {
        if (! Schema::hasTable('event_federation_deliveries')) {
            $this->warn('Event federation delivery schema is unavailable.');

            return self::SUCCESS;
        }
        $tenantId = $this->positiveOption('tenant');
        $partnerId = $this->positiveOption('partner');
        if ($tenantId === false || $partnerId === false) {
            return self::INVALID;
        }
        if ((bool) $this->option('status')) {
            return $this->output($diagnostics->snapshot($tenantId));
        }
        $limit = filter_var($this->option('limit'), FILTER_VALIDATE_INT, [
            'options' => ['min_range' => 1, 'max_range' => 100],
        ]);
        if ($limit === false) {
            $this->error('The --limit option must be an integer between 1 and 100.');

            return self::INVALID;
        }

        return $this->output($consumer->processBatch((int) $limit, $tenantId, $partnerId));
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
    private function output(array $payload): int
    {
        if ((bool) $this->option('json')) {
            $this->line(json_encode($payload, JSON_THROW_ON_ERROR | JSON_UNESCAPED_SLASHES));
        } else {
            foreach ($payload as $key => $value) {
                $this->line($key . '=' . (is_array($value)
                    ? json_encode($value, JSON_THROW_ON_ERROR | JSON_UNESCAPED_SLASHES)
                    : var_export($value, true)));
            }
        }

        return self::SUCCESS;
    }
}
