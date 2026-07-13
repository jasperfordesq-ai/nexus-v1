<?php
// Copyright © 2024–2026 Jasper Ford
// SPDX-License-Identifier: AGPL-3.0-or-later
// Author: Jasper Ford
// See NOTICE file for attribution and acknowledgements.

declare(strict_types=1);

namespace App\Console\Commands;

use App\Services\PodcastMediaCleanupService;
use Illuminate\Console\Command;

/** Dispatch durable podcast storage cleanup entries that are due for retry. */
class DispatchPodcastMediaCleanup extends Command
{
    protected $signature = 'podcasts:dispatch-media-cleanup {--limit=100}';

    protected $description = 'Dispatch due podcast media cleanup ledger entries';

    public function handle(PodcastMediaCleanupService $cleanup): int
    {
        $count = $cleanup->dispatchDue((int) $this->option('limit'));
        $this->info("Dispatched {$count} podcast media cleanup job(s).");

        return self::SUCCESS;
    }
}
