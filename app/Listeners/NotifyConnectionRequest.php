<?php
// Copyright © 2024–2026 Jasper Ford
// SPDX-License-Identifier: AGPL-3.0-or-later
// Author: Jasper Ford
// See NOTICE file for attribution and acknowledgements.

namespace App\Listeners;

use App\Core\TenantContext;
use App\Events\ConnectionRequested;
use App\Models\Notification;
use App\Models\User;
use App\Services\NotificationDispatcher;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Support\Facades\Log;

/**
 * Sends a notification to the target user when they receive a connection request.
 */
class NotifyConnectionRequest implements ShouldQueue
{
    public function __construct()
    {
        //
    }

    /**
     * Handle the event.
     */
    public function handle(ConnectionRequested $event): void
    {
        try {
            // Ensure tenant context is set (required when running via async queue)
            TenantContext::setById($event->tenantId);

            $requesterName = $event->requester->first_name ?? $event->requester->name ?? __('emails.common.fallback_someone');
            $targetUserId = $event->target->id;
            $content = __('emails_misc.social.connection_request', ['name' => $requesterName]);
            $link = '/connections';

            // Check email_connections preference — default to sending (opt-out model)
            $emailEnabled = true;
            try {
                $prefs = User::getNotificationPreferences($targetUserId);
                $emailEnabled = (bool) ($prefs['email_connections'] ?? true);
            } catch (\Throwable $prefError) {
                Log::debug('NotifyConnectionRequest: could not read email_connections pref', [
                    'user_id' => $targetUserId,
                    'error' => $prefError->getMessage(),
                ]);
            }

            if ($emailEnabled) {
                // Bell + email (via dispatcher)
                NotificationDispatcher::dispatch(
                    $targetUserId,
                    'global',
                    null,
                    'connection_request',
                    $content,
                    $link,
                    null
                );
            } else {
                // Bell only — user opted out of connection emails
                Notification::createNotification($targetUserId, $content, $link, 'connection_request');
            }
        } catch (\Throwable $e) {
            Log::error('NotifyConnectionRequest listener failed', [
                'requester_id' => $event->requester->id ?? null,
                'target_id' => $event->target->id ?? null,
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString(),
            ]);
        } finally {
            TenantContext::reset(); // Prevent context leaking to next queued job
        }
    }
}
