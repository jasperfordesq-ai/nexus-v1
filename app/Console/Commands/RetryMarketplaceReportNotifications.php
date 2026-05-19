<?php
// Copyright © 2024–2026 Jasper Ford
// SPDX-License-Identifier: AGPL-3.0-or-later
// Author: Jasper Ford
// See NOTICE file for attribution and acknowledgements.

namespace App\Console\Commands;

use App\Services\MarketplaceReportService;
use Illuminate\Console\Command;

class RetryMarketplaceReportNotifications extends Command
{
    protected $signature = 'marketplace:retry-report-notifications {--limit=100 : Maximum rows to retry}';

    protected $description = 'Retry pending or failed marketplace report lifecycle notifications.';

    public function handle(): int
    {
        $limit = (int) $this->option('limit');
        $processed = MarketplaceReportService::retryPendingReportNotifications($limit);

        $this->info("Processed {$processed} marketplace report notification(s).");

        return self::SUCCESS;
    }
}
