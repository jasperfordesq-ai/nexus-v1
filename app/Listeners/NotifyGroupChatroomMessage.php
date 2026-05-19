<?php
// Copyright © 2024–2026 Jasper Ford
// SPDX-License-Identifier: AGPL-3.0-or-later
// Author: Jasper Ford
// See NOTICE file for attribution and acknowledgements.

declare(strict_types=1);

namespace App\Listeners;

use App\Core\TenantContext;
use App\Events\GroupChatroomMessagePosted;
use App\Models\Notification;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;

/**
 * Creates in-app bell notifications for offline group members when a new
 * chatroom message is posted.
 *
 * NOTE — in-app only, NOT email. Group chat volume is too high to safely
 * email every message; users see chat in real time via the Pusher broadcast
 * that GroupChatroomMessagePosted also drives, and the in-app bell catches
 * up the rest when they next log in.
 *
 * Members who muted the sender (`user_muted_users`) are skipped. The sender
 * never gets a notification for their own message. Dedup against an
 * existing recent (last 5 min) chatroom-message notification per recipient
 * prevents a flurry of consecutive chat messages from producing one bell
 * row each.
 */
class NotifyGroupChatroomMessage implements ShouldQueue
{
    public string $queue = 'default';

    public int $tries = 3;

    public array $backoff = [10, 30, 60];

    public function handle(GroupChatroomMessagePosted $event): void
    {
        $previousTenantId = TenantContext::currentId();

        try {
            TenantContext::setById($event->tenantId);

            $senderId    = (int) ($event->message['user_id'] ?? 0);
            $messageId   = (int) ($event->message['id'] ?? 0);
            $messageBody = (string) ($event->message['body'] ?? '');

            if ($senderId === 0 || $messageId === 0) {
                return;
            }

            // Resolve sender name once for the notification preview.
            $sender = DB::table('users')
                ->where('id', $senderId)
                ->where('tenant_id', $event->tenantId)
                ->select(['first_name', 'name'])
                ->first();
            $senderName = $sender->first_name ?? $sender->name ?? 'Someone';

            // Resolve group name for the link/preview.
            $group = DB::table('groups')
                ->where('id', $event->groupId)
                ->where('tenant_id', $event->tenantId)
                ->select(['name', 'slug'])
                ->first();
            $groupName = $group->name ?? 'a group';

            $previewBody = mb_substr(strip_tags($messageBody), 0, 120);
            $content = "{$senderName} in {$groupName}: {$previewBody}";
            $link    = '/groups/' . $event->groupId . '/chat';

            // All active group members EXCEPT the sender and anyone who muted
            // the sender are eligible. group_members has `joined_at` and a
            // unique (tenant_id, group_id, user_id) shape.
            $recipients = DB::table('group_members as gm')
                ->join('users as u', 'u.id', '=', 'gm.user_id')
                ->where('gm.tenant_id', $event->tenantId)
                ->where('gm.group_id', $event->groupId)
                ->where('u.status', 'active')
                ->where('u.id', '!=', $senderId)
                ->whereNotIn('u.id', function ($q) use ($senderId, $event) {
                    $q->select('user_id')->from('user_muted_users')
                      ->where('muted_user_id', $senderId)
                      ->where('tenant_id', $event->tenantId);
                })
                ->pluck('u.id')
                ->all();

            if (empty($recipients)) {
                return;
            }

            // Dedup window: if the recipient already has a chatroom-message
            // bell from the same group within the last 5 minutes, don't add
            // another. Prevents 10 chat messages → 10 bell rows.
            $recentlyNotified = DB::table('notifications')
                ->where('tenant_id', $event->tenantId)
                ->where('type', 'group_chatroom_message')
                ->where('link', $link)
                ->where('created_at', '>=', now()->subMinutes(5))
                ->whereIn('user_id', $recipients)
                ->pluck('user_id')
                ->all();

            $newRecipients = array_diff($recipients, $recentlyNotified);
            if (empty($newRecipients)) {
                return;
            }

            foreach ($newRecipients as $userId) {
                Notification::createNotification(
                    (int) $userId,
                    $content,
                    $link,
                    'group_chatroom_message',
                    false,
                    $event->tenantId
                );
            }

            Log::debug('NotifyGroupChatroomMessage: notified members', [
                'tenant_id'   => $event->tenantId,
                'group_id'    => $event->groupId,
                'chatroom_id' => $event->chatroomId,
                'sender_id'   => $senderId,
                'sent'        => count($newRecipients),
                'deduped'     => count($recentlyNotified),
            ]);
        } catch (\Throwable $e) {
            Log::warning('NotifyGroupChatroomMessage listener failed', [
                'tenant_id'   => $event->tenantId ?? null,
                'group_id'    => $event->groupId ?? null,
                'chatroom_id' => $event->chatroomId ?? null,
                'error'       => $e->getMessage(),
            ]);
        } finally {
            TenantContext::restoreAfterScopedListener($previousTenantId);
        }
    }
}
