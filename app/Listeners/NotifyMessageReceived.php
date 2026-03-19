<?php
// Copyright © 2024–2026 Jasper Ford
// SPDX-License-Identifier: AGPL-3.0-or-later
// Author: Jasper Ford
// See NOTICE file for attribution and acknowledgements.

namespace App\Listeners;

use App\Events\MessageSent;
use Illuminate\Contracts\Queue\ShouldQueue;

/**
 * Sends a notification to the message recipient when a new message arrives.
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
     * TODO: Migrate logic from legacy MessageService::notifyRecipient()
     *       and NotificationService::create(). The legacy code lives at:
     *       - src/Services/MessageService.php (notifyRecipient method)
     *       - src/Services/NotificationService.php
     *       - src/Services/PushNotificationService.php (FCM push)
     *       - src/Services/RealtimeService.php (Pusher broadcast — now handled by ShouldBroadcast)
     */
    public function handle(MessageSent $event): void
    {
        // TODO: Create in-app notification via NotificationService::create()
        // TODO: Send push notification via PushNotificationService
        // TODO: Send email notification if user preferences allow and user is offline
    }
}
