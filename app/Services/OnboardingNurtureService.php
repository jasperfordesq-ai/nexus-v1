<?php
// Copyright © 2024–2026 Jasper Ford
// SPDX-License-Identifier: AGPL-3.0-or-later
// Author: Jasper Ford
// See NOTICE file for attribution and acknowledgements.

namespace App\Services;

use App\Core\Mailer;
use App\Core\TenantContext;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;

/**
 * OnboardingNurtureService — sends a 3-email nurture sequence to new users.
 *
 * - Day 2: "Complete Your Profile" — helps new users understand the value of a full profile
 * - Day 5: "Make Your First Connection" — encourages member-to-member engagement
 * - Day 7: "Your First Week" — summary of platform capabilities, sent regardless of onboarding status
 *
 * Dedup is handled via a 30-day Redis/cache key so emails are sent at most once per user per day.
 * Runs daily at 08:00 via the Laravel scheduler.
 */
class OnboardingNurtureService
{
    /**
     * Nurture day windows — sent on approximately day 2, 5, and 7 after registration.
     * The cron uses a ±12h window to account for timing drift between runs.
     */
    private const NURTURE_DAYS = [2, 5, 7];

    /**
     * Cache TTL for dedup keys (30 days).
     */
    private const DEDUP_TTL_DAYS = 30;

    /**
     * Send all due onboarding nurture emails across all active tenants.
     *
     * @return array{sent: int, errors: int}
     */
    public static function sendDueNurtureEmails(): array
    {
        $totalSent = 0;
        $totalErrors = 0;

        try {
            $tenants = DB::select("SELECT id, slug FROM tenants WHERE is_active = 1");
        } catch (\Throwable $e) {
            Log::error("[OnboardingNurtureService] Failed to fetch tenants: " . $e->getMessage());
            return ['sent' => 0, 'errors' => 1];
        }

        foreach ($tenants as $tenant) {
            $tenantId = (int) $tenant->id;

            try {
                TenantContext::setById($tenantId);
                $result = self::sendForTenant($tenantId);
                $totalSent += $result['sent'];
                $totalErrors += $result['errors'];
            } catch (\Throwable $e) {
                Log::error("[OnboardingNurtureService] Tenant {$tenant->slug} error: " . $e->getMessage());
                $totalErrors++;
            }
        }

        return ['sent' => $totalSent, 'errors' => $totalErrors];
    }

    /**
     * Send nurture emails for a single tenant (TenantContext must already be set).
     *
     * @return array{sent: int, errors: int}
     */
    private static function sendForTenant(int $tenantId): array
    {
        $sent = 0;
        $errors = 0;

        foreach (self::NURTURE_DAYS as $day) {
            $windowStart = now()->subDays($day)->subHours(12);
            $windowEnd   = now()->subDays($day)->addHours(12);

            try {
                $users = DB::table('users')
                    ->where('tenant_id', $tenantId)
                    ->where('status', 'active')
                    ->whereNotNull('email')
                    ->whereBetween('created_at', [$windowStart, $windowEnd])
                    ->select(['id', 'email', 'first_name', 'name', 'onboarding_completed'])
                    ->get();
            } catch (\Throwable $e) {
                Log::error("[OnboardingNurtureService] Query failed for tenant={$tenantId}, day={$day}: " . $e->getMessage());
                $errors++;
                continue;
            }

            foreach ($users as $user) {
                $userId = (int) $user->id;

                // Day 7 is always sent; Day 2 and Day 5 are skipped if onboarding is already complete
                if ($day !== 7 && $user->onboarding_completed) {
                    continue;
                }

                $cacheKey = "onboarding_nurture:{$tenantId}:{$userId}:{$day}";
                if (Cache::has($cacheKey)) {
                    continue;
                }

                try {
                    self::sendNurtureEmail($tenantId, $userId, $user, $day);
                    Cache::put($cacheKey, true, now()->addDays(self::DEDUP_TTL_DAYS));
                    $sent++;
                } catch (\Throwable $e) {
                    Log::error("[OnboardingNurtureService] Send failed tenant={$tenantId}, user={$userId}, day={$day}: " . $e->getMessage());
                    $errors++;
                }
            }
        }

        return ['sent' => $sent, 'errors' => $errors];
    }

    /**
     * Build and send the appropriate nurture email for the given day.
     */
    private static function sendNurtureEmail(int $tenantId, int $userId, object $user, int $day): void
    {
        $email = $user->email ?? null;
        if (empty($email) || !filter_var($email, FILTER_VALIDATE_EMAIL)) {
            return;
        }

        $tenantData = TenantContext::get();
        $tenantName = $tenantData['name'] ?? 'Project NEXUS';
        $frontendUrl = TenantContext::getFrontendUrl();
        $basePath = TenantContext::getSlugPrefix();

        $userName = htmlspecialchars($user->first_name ?? $user->name ?? 'there', ENT_QUOTES, 'UTF-8');

        $subject  = __("emails.onboarding_nurture.day{$day}_subject", [':community' => $tenantName]);
        $title    = __("emails.onboarding_nurture.day{$day}_title");
        $preview  = __("emails.onboarding_nurture.day{$day}_preview", [':community' => $tenantName]);
        $greeting = __("emails.onboarding_nurture.day{$day}_greeting", [':name' => $userName]);
        $body     = __("emails.onboarding_nurture.day{$day}_body", [':name' => $userName, ':community' => $tenantName]);
        $cta      = __("emails.onboarding_nurture.day{$day}_cta");

        // CTA destination per day
        $ctaPath = match ($day) {
            2 => '/profile/edit',
            5 => '/members',
            7 => '/feed',
            default => '/',
        };
        $ctaUrl = $frontendUrl . $basePath . $ctaPath;

        $html = \App\Core\EmailTemplateBuilder::make()
            ->theme('brand')
            ->title($title)
            ->paragraph($preview)
            ->paragraph("<p>{$greeting}</p><p>{$body}</p>")
            ->button($cta, $ctaUrl)
            ->render();

        $mailer = Mailer::forCurrentTenant();
        $mailer->send($email, $subject, $html);

        Log::info("[OnboardingNurtureService] Sent day-{$day} nurture to user={$userId} tenant={$tenantId}");
    }
}
