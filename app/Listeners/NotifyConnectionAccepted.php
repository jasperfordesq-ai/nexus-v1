<?php
// Copyright © 2024–2026 Jasper Ford
// SPDX-License-Identifier: AGPL-3.0-or-later
// Author: Jasper Ford
// See NOTICE file for attribution and acknowledgements.

namespace App\Listeners;

use App\Core\TenantContext;
use App\Events\ConnectionAccepted;
use App\Models\Notification;
use App\Models\User;
use App\Services\NotificationDispatcher;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;

/**
 * Notifies the original requester when their connection request is accepted.
 */
class NotifyConnectionAccepted implements ShouldQueue
{
    public function handle(ConnectionAccepted $event): void
    {
        try {
            TenantContext::setById($event->tenantId);

            $connection  = $event->connectionModel;
            $requesterId = $connection->requester_id;
            $receiverId  = $connection->receiver_id;

            // Load the receiver (the person who accepted the request)
            $receiver = DB::table('users')
                ->where('id', $receiverId)
                ->where('tenant_id', $event->tenantId)
                ->select(['first_name', 'last_name', 'name'])
                ->first();

            if (!$receiver) {
                return;
            }

            $receiverName = trim(($receiver->first_name ?? '') . ' ' . ($receiver->last_name ?? ''))
                ?: ($receiver->name ?? 'Someone');

            $content = __('emails_misc.social.connection_accepted', ['name' => $receiverName]);
            $link    = '/connections';

            // Respect email_connections opt-out preference
            $emailEnabled = true;
            try {
                $prefs        = User::getNotificationPreferences($requesterId);
                $emailEnabled = (bool) ($prefs['email_connections'] ?? true);
            } catch (\Throwable) {
                // Default to sending if preference lookup fails
            }

            if ($emailEnabled) {
                NotificationDispatcher::dispatch(
                    $requesterId,
                    'global',
                    null,
                    'connection_accepted',
                    $content,
                    $link,
                    null
                );
            } else {
                // Bell notification only — user opted out of connection emails
                Notification::createNotification($requesterId, $content, $link, 'connection_accepted');
            }
        } catch (\Throwable $e) {
            Log::error('NotifyConnectionAccepted listener failed', [
                'connection_id' => $event->connectionModel->id ?? null,
                'tenant_id'     => $event->tenantId,
                'error'         => $e->getMessage(),
                'trace'         => $e->getTraceAsString(),
            ]);
        }
    }
}
