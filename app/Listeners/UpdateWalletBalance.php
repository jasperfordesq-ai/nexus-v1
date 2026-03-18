<?php
// Copyright © 2024–2026 Jasper Ford
// SPDX-License-Identifier: AGPL-3.0-or-later
// Author: Jasper Ford
// See NOTICE file for attribution and acknowledgements.

namespace App\Listeners;

use App\Events\TransactionCompleted;
use Illuminate\Contracts\Queue\ShouldQueue;

/**
 * Updates wallet balances for both sender and receiver after a
 * time-credit transaction is completed.
 */
class UpdateWalletBalance implements ShouldQueue
{
    public function __construct()
    {
        //
    }

    /**
     * Handle the event.
     *
     * TODO: Migrate logic from legacy WalletService::processTransaction().
     *       The legacy code lives at:
     *       - src/Services/WalletService.php (processTransaction, updateBalance)
     *       - src/Services/GamificationService.php (award XP for transaction)
     */
    public function handle(TransactionCompleted $event): void
    {
        // TODO: Update sender wallet balance via WalletService
        // TODO: Update receiver wallet balance via WalletService
        // TODO: Award gamification XP via GamificationService::awardTransactionXp()
        // TODO: Update leaderboard via LeaderboardService
    }
}
