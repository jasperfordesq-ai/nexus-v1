<?php
// Copyright © 2024–2026 Jasper Ford
// SPDX-License-Identifier: AGPL-3.0-or-later
// Author: Jasper Ford
// See NOTICE file for attribution and acknowledgements.

namespace App\Listeners;

use App\Core\EmailTemplateBuilder;
use App\Core\TenantContext;
use App\Events\UserRegistered;
use App\Models\Notification;
use App\Services\SearchService;
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
            // Index new user in Meilisearch so they appear in search immediately
            TenantContext::setById($event->tenantId);
            SearchService::indexUser($event->user);

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
                $safeTenantName = htmlspecialchars($tenantName, ENT_QUOTES, 'UTF-8');
                $safeUserName = htmlspecialchars($userName, ENT_QUOTES, 'UTF-8');

                $html = EmailTemplateBuilder::make()
                    ->theme('success')
                    ->title("Welcome to {$safeTenantName}! 👋")
                    ->previewText("Welcome aboard, {$safeUserName}! Here's how to get started.")
                    ->greeting($safeUserName)
                    ->paragraph("Welcome to <strong>{$safeTenantName}</strong>! We're excited to have you join our community.")
                    ->highlight("Here are some things to get started:", '✨')
                    ->bulletList([
                        '<strong>Complete your profile</strong> — let others know who you are and what you can offer',
                        '<strong>Browse listings</strong> — discover skills and services offered by other members',
                        '<strong>Make connections</strong> — reach out to people in your community',
                        '<strong>Explore events</strong> — find upcoming activities and gatherings',
                    ])
                    ->paragraph("If you have any questions, don't hesitate to reach out. We're here to help!")
                    ->button('Explore Your Community', EmailTemplateBuilder::tenantUrl('/feed'))
                    ->render();

                $mailer = \App\Core\Mailer::forCurrentTenant();
                $mailer->send($userEmail, "Welcome to {$tenantName}!", $html);
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
