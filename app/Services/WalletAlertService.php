<?php
// Copyright © 2024–2026 Jasper Ford
// SPDX-License-Identifier: AGPL-3.0-or-later
// Author: Jasper Ford
// See NOTICE file for attribution and acknowledgements.

namespace App\Services;

use App\Core\TenantContext;
use App\I18n\LocaleContext;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;

/**
 * WalletAlertService — sends low balance and empty balance email notifications.
 *
 * Deduplication via Laravel Cache prevents repeat alerts within a 24-hour window.
 */
class WalletAlertService
{
    private const LOW_BALANCE_THRESHOLD = 5.0;
    private const CACHE_TTL = 86400; // 24 hours

    /**
     * Check a user's new balance and send the appropriate alert email if needed.
     *
     * - 0 < newBalance <= 5.0 → "low balance" email
     * - newBalance <= 0       → "balance empty" email
     * - Deduplication: one alert per user per 24h (cache key per tenant+user)
     */
    public static function checkAndSendLowBalanceAlert(int $tenantId, int $userId, float $newBalance): void
    {
        // No alert needed when balance is comfortably above threshold
        if ($newBalance > self::LOW_BALANCE_THRESHOLD) {
            return;
        }

        $cacheKey = "wallet_low_balance:{$tenantId}:{$userId}";

        // Skip if an alert was already sent within the last 24 hours
        if (Cache::has($cacheKey)) {
            return;
        }

        $user = DB::table('users')
            ->where('id', $userId)
            ->where('tenant_id', $tenantId)
            ->select(['email', 'first_name', 'name', 'preferred_language'])
            ->first();

        if (!$user || empty($user->email)) {
            return;
        }

        $previousTenantId = TenantContext::currentId();
        TenantContext::setById($tenantId);
        try {
            $sent = (bool) LocaleContext::withLocale($user, function () use ($user, $newBalance, $tenantId) {
                $firstName = $user->first_name ?? $user->name ?? __('emails.common.fallback_name');
                $balanceFormatted = number_format($newBalance, 1);
                $baseUrl = TenantContext::getFrontendUrl() . TenantContext::getSlugPrefix();

                if ($newBalance <= 0) {
                    return self::sendEmptyBalanceEmail($user->email, $firstName, $balanceFormatted, $baseUrl, $tenantId);
                }

                return self::sendLowBalanceEmail($user->email, $firstName, $balanceFormatted, $baseUrl, $tenantId);
            });

            if ($sent) {
                Cache::put($cacheKey, true, self::CACHE_TTL);
            }
        } finally {
            if ($previousTenantId !== null) {
                TenantContext::setById((int) $previousTenantId);
            } else {
                TenantContext::reset();
            }
        }
    }

    private static function sendLowBalanceEmail(string $email, string $firstName, string $balance, string $baseUrl, int $tenantId): bool
    {
        $html = \App\Core\EmailTemplateBuilder::make()
            ->theme('brand')
            ->title(__('emails.wallet_alert.low_title'))
            ->previewText(__('emails.wallet_alert.low_preview', ['balance' => $balance]))
            ->greeting($firstName)
            ->paragraph(__('emails.wallet_alert.low_body', ['balance' => $balance]))
            ->button(__('emails.wallet_alert.low_cta'), $baseUrl . '/listings')
            ->render();

        return \App\Services\EmailDispatchService::sendRaw(
            $email,
            __('emails.wallet_alert.low_subject'),
            $html,
            null,
            null,
            null,
            'wallet_alert',
            ['tenant_id' => $tenantId]
        );
    }

    private static function sendEmptyBalanceEmail(string $email, string $firstName, string $balance, string $baseUrl, int $tenantId): bool
    {
        $html = \App\Core\EmailTemplateBuilder::make()
            ->theme('brand')
            ->title(__('emails.wallet_alert.empty_title'))
            ->previewText(__('emails.wallet_alert.empty_preview'))
            ->greeting($firstName)
            ->paragraph(__('emails.wallet_alert.empty_body', ['balance' => $balance]))
            ->button(__('emails.wallet_alert.empty_cta'), $baseUrl . '/listings/create')
            ->render();

        return \App\Services\EmailDispatchService::sendRaw(
            $email,
            __('emails.wallet_alert.empty_subject'),
            $html,
            null,
            null,
            null,
            'wallet_alert',
            ['tenant_id' => $tenantId]
        );
    }
}
