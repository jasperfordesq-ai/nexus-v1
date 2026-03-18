<?php
// Copyright © 2024–2026 Jasper Ford
// SPDX-License-Identifier: AGPL-3.0-or-later
// Author: Jasper Ford
// See NOTICE file for attribution and acknowledgements.

namespace App\Listeners;

use App\Events\UserRegistered;
use Illuminate\Contracts\Queue\ShouldQueue;

/**
 * Sends a welcome notification (email + in-app) when a new user registers.
 */
class SendWelcomeNotification implements ShouldQueue
{
    public function __construct()
    {
        //
    }

    /**
     * Handle the event.
     *
     * TODO: Migrate logic from legacy NotificationService::sendWelcomeNotification()
     *       and EmailService::sendWelcomeEmail(). The legacy code lives at:
     *       - src/Services/NotificationService.php
     *       - src/Services/EmailService.php
     */
    public function handle(UserRegistered $event): void
    {
        // TODO: Send welcome email via EmailService
        // TODO: Create in-app welcome notification via NotificationService
        // TODO: Initialize gamification profile via GamificationService::initializeUser()
    }
}
