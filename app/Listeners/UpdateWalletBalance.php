<?php
// Copyright © 2024–2026 Jasper Ford
// SPDX-License-Identifier: AGPL-3.0-or-later
// Author: Jasper Ford
// See NOTICE file for attribution and acknowledgements.

namespace App\Listeners;

use App\Core\TenantContext;
use App\Events\TransactionCompleted;
use App\Services\GamificationService;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Support\Facades\Log;

/**
 * Handles post-transaction side effects: XP awards and badge checks
 * for both sender and receiver after a time-credit transaction completes.
 *
 * Note: Wallet balance is already updated by the controller at transaction
 * creation time. This listener only handles gamification side effects.
 */
class UpdateWalletBalance implements ShouldQueue
{
    public function __construct()
    {
        //
    }

    /**
     * Handle the event.
     */
    public function handle(TransactionCompleted $event): void
    {
        try {
            // Ensure tenant context is set (required when running via async queue)
            TenantContext::setById($event->tenantId);
            /** @var GamificationService $gamification */
            $gamification = app(GamificationService::class);

            $senderId = $event->sender->id;
            $receiverId = $event->receiver->id;

            // Award XP to sender for sending credits
            $gamification->awardXP(
                $senderId,
                GamificationService::XP_VALUES['send_credits'],
                'send_credits',
                'Sent time credits in transaction #' . $event->transaction->id
            );

            // Award XP to receiver for receiving credits
            $gamification->awardXP(
                $receiverId,
                GamificationService::XP_VALUES['receive_credits'],
                'receive_credits',
                'Received time credits in transaction #' . $event->transaction->id
            );

            // Run badge checks for both users
            $gamification->runAllBadgeChecks($senderId);
            $gamification->runAllBadgeChecks($receiverId);
        } catch (\Throwable $e) {
            Log::error('UpdateWalletBalance listener failed', [
                'transaction_id' => $event->transaction->id ?? null,
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString(),
            ]);
        }
    }
}
