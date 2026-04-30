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
 * AG54 — Daily — sends reminder emails for overdue Verein dues
 * (max 3 reminders per dues row, 7-day cadence).
 */
class SendDuesReminders extends Command
{
    protected $signature = 'verein:send-dues-reminders';
    protected $description = 'Send overdue Verein dues reminder emails on a 7-day cadence (max 3 per row)';

    public function handle(VereinDuesService $service): int
    {
        $sent = $service->sendDueReminders();
        if ($sent > 0) {
            $this->info("Sent {$sent} Verein dues reminder email(s).");
        }
        return self::SUCCESS;
    }
}
