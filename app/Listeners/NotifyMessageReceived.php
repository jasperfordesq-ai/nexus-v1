<?php
// Copyright © 2024–2026 Jasper Ford
// SPDX-License-Identifier: AGPL-3.0-or-later
// Author: Jasper Ford
// See NOTICE file for attribution and acknowledgements.

namespace App\Listeners;

use App\Events\MessageSent;
use App\Services\NotificationDispatcher;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Support\Facades\DB;
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
     */
    public function handle(MessageSent $event): void
    {
        try {
            $senderId = $event->sender->id;
            $senderName = $event->sender->first_name ?? $event->sender->name ?? 'Someone';

            // Find all other participants in the conversation (not the sender)
            $recipientIds = DB::table('conversation_participants')
                ->where('conversation_id', $event->conversationId)
                ->where('user_id', '!=', $senderId)
                ->pluck('user_id');

            $link = '/messages/' . $event->conversationId;
            $content = "{$senderName} sent you a message";

            foreach ($recipientIds as $recipientId) {
                NotificationDispatcher::dispatch(
                    (int) $recipientId,
                    'global',
                    null,
                    'new_message',
                    $content,
                    $link,
                    null
                );
            }
        } catch (\Throwable $e) {
            Log::error('NotifyMessageReceived listener failed', [
                'conversation_id' => $event->conversationId ?? null,
                'sender_id' => $event->sender->id ?? null,
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString(),
            ]);
        }
    }
}
