<?php
// Copyright © 2024–2026 Jasper Ford
// SPDX-License-Identifier: AGPL-3.0-or-later
// Author: Jasper Ford
// See NOTICE file for attribution and acknowledgements.

declare(strict_types=1);

namespace App\Console\Commands;

use App\Services\GroupWebhookService;
use Illuminate\Console\Command;

final class DispatchGroupWebhooksCommand extends Command
{
    protected $signature = 'groups:dispatch-webhooks {--limit=100 : Maximum deliveries to enqueue}';
    protected $description = 'Dispatch due group webhook outbox deliveries to queue workers';

    public function handle(): int
    {
        $limit = max(1, min((int) $this->option('limit'), 500));
        $dispatched = GroupWebhookService::dispatchDueDeliveries($limit);
        $this->components->info("Dispatched {$dispatched} group webhook deliveries.");

        return self::SUCCESS;
    }
}
