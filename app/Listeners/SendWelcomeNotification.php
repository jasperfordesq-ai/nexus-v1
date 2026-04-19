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
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;

/**
 * Sends a welcome notification (email + in-app) when a new user registers.
 *
 * For pending users (awaiting email verification), this sends a combined
 * welcome + verification email with a verify link. For already-active users
 * (e.g. admin-created), it sends a generic welcome.
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
            // Tenant context for any notifications/emails below.
            // Search-index upserts are handled by UserObserver (queued + retried)
            // on the `created` event — no direct indexUser() call here.
            TenantContext::setById($event->tenantId);

            // Create in-app welcome notification
            Notification::create([
                'tenant_id'  => $event->tenantId,
                'user_id'    => $event->user->id,
                'type'       => 'welcome',
                'message'    => __('emails.welcome.in_app_message'),
                'link'       => '/feed',
                'is_read'    => false,
                'created_at' => now(),
            ]);

            // Send email
            $tenantName = TenantContext::get()['name'] ?? 'Project NEXUS';
            $userName = $event->user->first_name ?? $event->user->name ?? 'there';
            $userEmail = $event->user->email ?? null;

            if (!$userEmail) {
                return;
            }

            $safeTenantName = htmlspecialchars($tenantName, ENT_QUOTES, 'UTF-8');
            $safeUserName = htmlspecialchars($userName, ENT_QUOTES, 'UTF-8');

            // For pending users: send combined welcome + verification email
            $isPending = ($event->user->status ?? '') === 'pending'
                      || empty($event->user->email_verified_at);

            if ($isPending) {
                $verifyUrl = $this->generateVerificationToken($event->user->id, $event->tenantId);

                $html = EmailTemplateBuilder::make()
                    ->theme('success')
                    ->title(__('emails.welcome.title', ['community' => $safeTenantName]))
                    ->previewText(__('emails.welcome.pending_preview', ['community' => $safeTenantName]))
                    ->greeting($safeUserName)
                    ->paragraph(__('emails.welcome.pending_intro', ['community' => $safeTenantName]))
                    ->paragraph(__('emails.welcome.pending_verify_instruction'))
                    ->button(__('emails.welcome.pending_button'), $verifyUrl)
                    ->paragraph(__('emails.welcome.pending_once_verified'))
                    ->bulletList([
                        __('emails.welcome.pending_bullet_profile'),
                        __('emails.welcome.pending_bullet_listings'),
                        __('emails.welcome.pending_bullet_connections'),
                    ])
                    ->paragraph(__('emails.welcome.pending_ignore'))
                    ->render();

                $mailer = \App\Core\Mailer::forCurrentTenant();
                if (!$mailer->send($userEmail, __('emails.welcome.pending_subject', ['community' => $tenantName]), $html)) {
                    Log::warning('SendWelcomeNotification: pending welcome email failed to send', ['user_email' => $userEmail]);
                }
            } else {
                // Already active user (admin-created) — generic welcome only
                $html = EmailTemplateBuilder::make()
                    ->theme('success')
                    ->title(__('emails.welcome.title', ['community' => $safeTenantName]))
                    ->previewText(__('emails.welcome.active_preview', ['name' => $safeUserName]))
                    ->greeting($safeUserName)
                    ->paragraph(__('emails.welcome.active_intro', ['community' => $safeTenantName]))
                    ->highlight(__('emails.welcome.active_get_started'), '✨')
                    ->bulletList([
                        __('emails.welcome.active_bullet_profile'),
                        __('emails.welcome.active_bullet_listings'),
                        __('emails.welcome.active_bullet_connections'),
                        __('emails.welcome.active_bullet_events'),
                    ])
                    ->paragraph(__('emails.welcome.active_help'))
                    ->button(__('emails.welcome.active_button'), EmailTemplateBuilder::tenantUrl('/feed'))
                    ->render();

                $mailer = \App\Core\Mailer::forCurrentTenant();
                if (!$mailer->send($userEmail, __('emails.welcome.subject', ['community' => $tenantName]), $html)) {
                    Log::warning('SendWelcomeNotification: welcome email failed to send', ['user_email' => $userEmail]);
                }
            }
        } catch (\Throwable $e) {
            Log::error('SendWelcomeNotification listener failed', [
                'user_id' => $event->user->id ?? null,
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString(),
            ]);
        }
    }

    /**
     * Generate a verification token using the email_verification_tokens table
     * (the same system that EmailVerificationController::verifyEmail() checks).
     */
    private function generateVerificationToken(int $userId, int $tenantId): string
    {
        $token = bin2hex(random_bytes(32));
        $hashedToken = password_hash($token, PASSWORD_DEFAULT);
        $expiresAt = date('Y-m-d H:i:s', time() + 86400); // 24 hours

        // Ensure table exists
        DB::statement("
            CREATE TABLE IF NOT EXISTS `email_verification_tokens` (
                `id` INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
                `user_id` INT UNSIGNED NOT NULL,
                `tenant_id` INT(11) NOT NULL,
                `token` VARCHAR(255) NOT NULL,
                `created_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
                `expires_at` TIMESTAMP NOT NULL,
                INDEX `idx_user_id` (`user_id`),
                INDEX `idx_tenant_id` (`tenant_id`),
                INDEX `idx_tenant_user` (`tenant_id`, `user_id`),
                INDEX `idx_expires_at` (`expires_at`)
            ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci
        ");

        // Clean up old tokens for this user
        DB::delete(
            "DELETE FROM email_verification_tokens WHERE user_id = ? AND tenant_id = ?",
            [$userId, $tenantId]
        );

        // Store hashed token
        DB::insert(
            "INSERT INTO email_verification_tokens (user_id, tenant_id, token, expires_at) VALUES (?, ?, ?, ?)",
            [$userId, $tenantId, $hashedToken, $expiresAt]
        );

        // Build the verification URL
        $appUrl = TenantContext::getFrontendUrl();
        $basePath = TenantContext::getSlugPrefix();

        return $appUrl . $basePath . '/verify-email?token=' . $token;
    }
}
