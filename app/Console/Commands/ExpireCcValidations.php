<?php
// Copyright © 2024–2026 Jasper Ford
// SPDX-License-Identifier: AGPL-3.0-or-later
// Author: Jasper Ford
// See NOTICE file for attribution and acknowledgements.

declare(strict_types=1);

namespace App\Console\Commands;

use App\Services\CreditCommonsNodeService;
use Illuminate\Console\Command;

/**
 * Expire CC validated (V) transactions that have exceeded their timeout window.
 *
 * CC protocol: validated transactions have a configurable timeout (default 300s).
 * After the window expires, the transaction automatically transitions to Erased (E).
 *
 * Scheduled to run every minute via bootstrap/app.php.
 */
class ExpireCcValidations extends Command
{
    protected $signature = 'federation:expire-cc-validations';
    protected $description = 'Expire Credit Commons validated transactions past their timeout window';

    public function handle(): int
    {
        $expired = CreditCommonsNodeService::expireValidatedTransactions();

        if ($expired > 0) {
            $this->info("Expired {$expired} validated CC transaction(s).");
        }

        return self::SUCCESS;
    }
}
