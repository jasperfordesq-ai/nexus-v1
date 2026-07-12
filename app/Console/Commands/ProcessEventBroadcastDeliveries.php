<?php
// Copyright © 2024–2026 Jasper Ford
// SPDX-License-Identifier: AGPL-3.0-or-later
// Author: Jasper Ford
// See NOTICE file for attribution and acknowledgements.

declare(strict_types=1);

namespace App\Console\Commands;

use App\Services\EventBroadcastDeliveryConsumer;
use Illuminate\Console\Command;

/** Identity-free bounded worker entrypoint; scheduler wiring is intentionally separate. */
final class ProcessEventBroadcastDeliveries extends Command
{
    protected $signature = 'events:process-broadcasts
        {--tenant= : Restrict processing to one tenant ID}
        {--limit=50 : Maximum delivery facts to claim (1-100)}
        {--json : Emit machine-readable summary output}';

    protected $description = 'Process durable per-recipient Event broadcast deliveries';

    public function __construct(
        private readonly EventBroadcastDeliveryConsumer $consumer,
    ) {
        parent::__construct();
    }

    public function handle(): int
    {
        $tenantOption = $this->option('tenant');
        $tenantId = $tenantOption === null || $tenantOption === ''
            ? null
            : filter_var($tenantOption, FILTER_VALIDATE_INT, [
                'options' => ['min_range' => 1],
            ]);
        if ($tenantOption !== null && $tenantOption !== '' && $tenantId === false) {
            $this->error('The tenant option must be a positive integer.');
            return self::INVALID;
        }
        $limit = filter_var($this->option('limit'), FILTER_VALIDATE_INT, [
            'options' => ['min_range' => 1, 'max_range' => 100],
        ]);
        if ($limit === false) {
            $this->error('The limit option must be between 1 and 100.');
            return self::INVALID;
        }

        $summary = $this->consumer->processBatch(
            (int) $limit,
            $tenantId === null ? null : (int) $tenantId,
        );
        if ((bool) $this->option('json')) {
            $this->line(json_encode($summary, JSON_THROW_ON_ERROR));
        } else {
            $this->table(
                ['Claimed', 'Delivered', 'Suppressed', 'Retrying', 'Dead-lettered', 'Stale released'],
                [[
                    $summary['claimed'],
                    $summary['delivered'],
                    $summary['suppressed'],
                    $summary['retrying'],
                    $summary['dead_lettered'],
                    $summary['stale_released'],
                ]],
            );
        }

        return $summary['dead_lettered'] > 0 ? self::FAILURE : self::SUCCESS;
    }
}
