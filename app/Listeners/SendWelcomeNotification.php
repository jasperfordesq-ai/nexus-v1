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
                    ->title("Welcome to {$safeTenantName}!")
                    ->previewText("Verify your email to get started on {$safeTenantName}.")
                    ->greeting($safeUserName)
                    ->paragraph("Welcome to <strong>{$safeTenantName}</strong>! We're excited to have you join our community.")
                    ->paragraph("Please verify your email address by clicking the button below to activate your account. This link expires in 24 hours.")
                    ->button('Verify Email & Get Started', $verifyUrl)
                    ->paragraph("Once verified, you can:")
                    ->bulletList([
                        '<strong>Complete your profile</strong> — let others know who you are',
                        '<strong>Browse listings</strong> — discover skills and services offered by members',
                        '<strong>Make connections</strong> — reach out to people in your community',
                    ])
                    ->paragraph("If you did not create this account, you can safely ignore this email.")
                    ->render();

                $mailer = \App\Core\Mailer::forCurrentTenant();
                $mailer->send($userEmail, "Verify Your Email - {$tenantName}", $html);
            } else {
                // Already active user (admin-created) — generic welcome only
                $html = EmailTemplateBuilder::make()
                    ->theme('success')
                    ->title("Welcome to {$safeTenantName}!")
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
