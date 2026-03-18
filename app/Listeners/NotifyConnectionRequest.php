<?php
// Copyright © 2024–2026 Jasper Ford
// SPDX-License-Identifier: AGPL-3.0-or-later
// Author: Jasper Ford
// See NOTICE file for attribution and acknowledgements.

namespace App\Listeners;

use App\Events\ConnectionRequested;
use Illuminate\Contracts\Queue\ShouldQueue;

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
     *
     * TODO: Migrate logic from legacy ConnectionService::sendConnectionNotification()
     *       and NotificationService::create(). The legacy code lives at:
     *       - src/Services/ConnectionService.php
     *       - src/Services/NotificationService.php
     *       - src/Services/PushNotificationService.php (FCM push)
     */
    public function handle(ConnectionRequested $event): void
    {
        // TODO: Create in-app notification via NotificationService::create()
        // TODO: Send push notification via PushNotificationService
        // TODO: Send email notification if user preferences allow
    }
}
