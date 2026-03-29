<?php
// Copyright © 2024–2026 Jasper Ford
// SPDX-License-Identifier: AGPL-3.0-or-later
// Author: Jasper Ford
// See NOTICE file for attribution and acknowledgements.

namespace App\Listeners;

use App\Core\TenantContext;
use App\Events\MessageSent;
use App\Models\Notification;
use App\Models\User;
use App\Services\NotificationDispatcher;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Support\Facades\Log;

/**
 * Sends a notification to the message recipient(s) when a new message arrives.
 */
class NotifyMessageReceived implements ShouldQueue
{
    public function __construct()
    {
        //
    }

    /**
     * Handle the event.
     *
     * Messages are 1-to-1 direct messages with sender_id and receiver_id
     * on the Message model. The recipient is simply the receiver_id.
     */
    public function handle(MessageSent $event): void
    {
        try {
            // Ensure tenant context is set (required when running via async queue)
            TenantContext::setById($event->tenantId);
            $message = $event->message;
            $senderId = $event->sender->id;
            $recipientId = (int) $message->receiver_id;

            if ($recipientId === $senderId || $recipientId <= 0) {
                Log::warning('NotifyMessageReceived: invalid recipient', [
                    'sender_id' => $senderId,
                    'receiver_id' => $recipientId,
                    'message_id' => $message->id ?? null,
                    'tenant_id' => $event->tenantId,
                ]);
                return;
            }

            $senderName = $event->sender->first_name ?? $event->sender->name ?? 'Someone';
            $link = '/messages/' . $senderId;
            $content = "{$senderName} sent you a message";

            // Check email_messages preference — default to sending (opt-out model)
            $emailEnabled = true;
            try {
                $prefs = User::getNotificationPreferences($recipientId);
                $emailEnabled = (bool) ($prefs['email_messages'] ?? true);
            } catch (\Throwable $prefError) {
                Log::debug('NotifyMessageReceived: could not read email_messages pref', [
                    'user_id' => $recipientId,
                    'error' => $prefError->getMessage(),
                ]);
            }

            if ($emailEnabled) {
                // Bell + email (via dispatcher)
                NotificationDispatcher::dispatch(
                    $recipientId,
                    'global',
                    null,
                    'new_message',
                    $content,
                    $link,
                    null
                );
            } else {
                // Bell only — user opted out of message emails
                Notification::createNotification($recipientId, $content, $link, 'new_message');
            }
        } catch (\Throwable $e) {
            Log::error('NotifyMessageReceived listener failed', [
                'message_id' => $event->message->id ?? null,
                'sender_id' => $event->sender->id ?? null,
                'receiver_id' => $event->message->receiver_id ?? null,
                'tenant_id' => $event->tenantId ?? null,
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString(),
            ]);
        }
    }
}
