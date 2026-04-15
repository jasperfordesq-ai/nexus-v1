<?php
// Copyright © 2024–2026 Jasper Ford
// SPDX-License-Identifier: AGPL-3.0-or-later
// Author: Jasper Ford
// See NOTICE file for attribution and acknowledgements.

namespace App\Listeners;

use App\Core\TenantContext;
use App\Events\TransactionCompleted;
use App\Models\Notification;
use App\Models\User;
use App\Services\NotificationDispatcher;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Support\Facades\Log;

/**
 * Sends in-app + email notifications to the receiver when a time-credit
 * transaction completes. Respects the user's email_transactions preference.
 */
class NotifyTransactionCompleted implements ShouldQueue
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

            $sender = $event->sender;
            $receiver = $event->receiver;
            $transaction = $event->transaction;

            $senderName = $sender->first_name ?? $sender->name ?? 'Someone';
            $amount = (float) $transaction->amount;
            $description = $transaction->description ?? '';

            // 1. Create in-app notification (bell icon) for receiver
            $content = __('notifications.credit_received', ['name' => $senderName, 'amount' => $amount]);
            if ($description !== '') {
                $content .= ' ' . __('notifications.credit_received_for', ['description' => $description]);
            }

            Notification::createNotification(
                $receiver->id,
                $content,
                '/wallet',
                'transaction'
            );

            // 2. Send email if user has email_transactions enabled
            $emailEnabled = true;
            try {
                $prefs = User::getNotificationPreferences($receiver->id);
                $emailEnabled = (bool) ($prefs['email_transactions'] ?? true);
            } catch (\Throwable $prefError) {
                Log::debug('NotifyTransactionCompleted: could not read email_transactions pref', [
                    'user_id' => $receiver->id,
                    'error' => $prefError->getMessage(),
                ]);
            }

            if ($emailEnabled) {
                NotificationDispatcher::sendCreditEmail(
                    $receiver->id,
                    $senderName,
                    $amount,
                    $description
                );
            }
        } catch (\Throwable $e) {
            Log::error('NotifyTransactionCompleted listener failed', [
                'transaction_id' => $event->transaction->id ?? null,
                'sender_id' => $event->sender->id ?? null,
                'receiver_id' => $event->receiver->id ?? null,
                'tenant_id' => $event->tenantId ?? null,
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString(),
            ]);
        }
    }
}
