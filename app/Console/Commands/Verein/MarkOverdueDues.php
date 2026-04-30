<?php
// Copyright © 2024–2026 Jasper Ford
// SPDX-License-Identifier: AGPL-3.0-or-later
// Author: Jasper Ford
// See NOTICE file for attribution and acknowledgements.

declare(strict_types=1);

namespace App\Console\Commands\Verein;

use App\Services\Verein\VereinDuesService;
use Illuminate\Console\Command;

/**
 * AG54 — Daily — flips pending verein_member_dues to overdue past grace_period.
 */
class MarkOverdueDues extends Command
{
    protected $signature = 'verein:mark-overdue';
    protected $description = 'Flip pending Verein dues to overdue past their configured grace_period_days';

    public function handle(VereinDuesService $service): int
    {
        $count = $service->markOverdueDues();
        if ($count > 0) {
            $this->info("Marked {$count} Verein dues row(s) as overdue.");
        }
        return self::SUCCESS;
    }
}
