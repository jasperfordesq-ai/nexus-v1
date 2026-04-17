<?php
// Copyright © 2024–2026 Jasper Ford
// SPDX-License-Identifier: AGPL-3.0-or-later
// Author: Jasper Ford
// See NOTICE file for attribution and acknowledgements.

namespace App\Listeners;

use App\Core\EmailTemplateBuilder;
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
            // Ensure tenant context is set (required when running via async queue).
            // setById returns false if the tenant no longer exists (e.g. deleted
            // between event dispatch and listener execution). Bail out so we
            // don't process the message against the wrong / missing tenant.
            if (!TenantContext::setById($event->tenantId)) {
                Log::warning('NotifyMessageReceived: tenant not found, skipping', [
                    'tenant_id' => $event->tenantId,
                    'message_id' => $event->message->id ?? null,
                ]);
                return;
            }
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

            $senderName = $event->sender->first_name ?? $event->sender->name ?? __('emails.message.fallback_sender_name');
            $link = '/messages/' . $senderId;
            $content = __('emails.message.in_app_content', ['sender' => $senderName]);

            // Build a rich HTML email body using EmailTemplateBuilder
            $htmlContent = $this->buildMessageEmailHtml($event, $senderName, $link);

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
                // Bell + email (via dispatcher) — pass the rich HTML body
                NotificationDispatcher::dispatch(
                    $recipientId,
                    'global',
                    null,
                    'new_message',
                    $content,
                    $link,
                    $htmlContent
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
        } finally {
            TenantContext::reset(); // Prevent context leaking to next queued job
        }
    }

    /**
     * Build a beautiful HTML email for new message notifications.
     */
    private function buildMessageEmailHtml(MessageSent $event, string $senderName, string $link): string
    {
        $messageUrl = EmailTemplateBuilder::tenantUrl($link);

        // Get a safe preview of the message body (strip HTML, truncate)
        $rawBody = $event->message->body ?? '';
        $preview = strip_tags($rawBody);
        $preview = mb_strlen($preview) > 200 ? mb_substr($preview, 0, 200) . '…' : $preview;

        // Get recipient name for greeting
        $recipientName = __('emails.message.fallback_recipient_name');
        try {
            $recipient = User::withoutGlobalScopes()->find((int) $event->message->receiver_id);
            if ($recipient) {
                $recipientName = $recipient->first_name ?? $recipient->name ?? __('emails.message.fallback_recipient_name');
            }
        } catch (\Throwable $e) {
            // Fallback to generic greeting
        }

        $safeSenderName = htmlspecialchars($senderName, ENT_QUOTES, 'UTF-8');

        $builder = EmailTemplateBuilder::make()
            ->theme('brand')
            ->title(__('emails.message.title'))
            ->previewText(__('emails.message.preview', ['sender' => $senderName]))
            ->greeting($recipientName)
            ->paragraph(__('emails.message.body_intro', ['sender' => $safeSenderName]));

        // Include message preview if available (not voice messages)
        if (!empty($preview) && !$event->message->is_voice) {
            $builder->blockquote($preview, $senderName);
        } elseif ($event->message->is_voice) {
            $builder->highlight(__('emails.message.voice_notice'), '🎙️');
        }

        $builder->button(__('emails.message.view_button'), $messageUrl)
            ->divider()
            ->paragraph('<span style="font-size: 13px; color: #6b7280;">' . __('emails.message.footer_note', ['url' => EmailTemplateBuilder::tenantUrl('/notifications')]) . '</span>');

        return $builder->render();
    }
}
