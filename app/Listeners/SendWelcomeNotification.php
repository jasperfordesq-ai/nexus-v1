<?php
// Copyright © 2024–2026 Jasper Ford
// SPDX-License-Identifier: AGPL-3.0-or-later
// Author: Jasper Ford
// See NOTICE file for attribution and acknowledgements.

namespace App\Listeners;

use App\Core\TenantContext;
use App\Events\UserRegistered;
use App\Models\Notification;
use App\Services\EmailService;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Support\Facades\Log;

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
     */
    public function handle(UserRegistered $event): void
    {
        try {
            // Create in-app welcome notification
            Notification::create([
                'tenant_id'  => $event->tenantId,
                'user_id'    => $event->user->id,
                'type'       => 'welcome',
                'message'    => 'Welcome to the community! Start by exploring your feed and connecting with other members.',
                'link'       => '/feed',
                'is_read'    => false,
                'created_at' => now(),
            ]);

            // Send welcome email
            $tenantName = TenantContext::get()['name'] ?? 'Project NEXUS';
            $userName = $event->user->first_name ?? $event->user->name ?? 'there';
            $userEmail = $event->user->email ?? null;

            if ($userEmail) {
                /** @var EmailService $emailService */
                $emailService = app(EmailService::class);

                $emailService->send(
                    $userEmail,
                    "Welcome to {$tenantName}!",
                    "Hi {$userName},\n\n"
                    . "Welcome to {$tenantName}! We're excited to have you join our community.\n\n"
                    . "Here are some things you can do to get started:\n"
                    . "- Complete your profile\n"
                    . "- Browse listings from other members\n"
                    . "- Connect with people in your community\n\n"
                    . "If you have any questions, don't hesitate to reach out.\n\n"
                    . "Best regards,\n"
                    . "The {$tenantName} Team"
                );
            }
        } catch (\Throwable $e) {
            Log::error('SendWelcomeNotification listener failed', [
                'user_id' => $event->user->id ?? null,
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString(),
            ]);
        }
    }
}
