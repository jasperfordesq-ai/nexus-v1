<?php
// Copyright © 2024–2026 Jasper Ford
// SPDX-License-Identifier: AGPL-3.0-or-later
// Author: Jasper Ford
// See NOTICE file for attribution and acknowledgements.

namespace App\Services;

use App\Core\EmailTemplateBuilder;
use App\Core\Mailer;
use App\Core\TenantContext;
use App\I18n\LocaleContext;
use App\Models\User;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;

/**
 * BalanceAlertService
 *
 * Monitors organization wallet balances and sends alerts when thresholds are reached.
 * All queries are tenant-scoped via TenantContext::getId().
 */
class BalanceAlertService
{
    public const DEFAULT_LOW_BALANCE_THRESHOLD = 50;
    public const DEFAULT_CRITICAL_BALANCE_THRESHOLD = 10;

    /**
     * Check all organization wallets for low balance and send alerts.
     * Should be run via cron job.
     *
     * @return int Number of alerts sent
     */
    public function checkAllBalances(): int
    {
        $tenantId = TenantContext::getId();

        $wallets = DB::table('org_wallets as ow')
            ->join('vol_organizations as vo', function ($join) {
                $join->on('ow.organization_id', '=', 'vo.id')
                    ->whereColumn('ow.tenant_id', '=', 'vo.tenant_id');
            })
            ->where('ow.tenant_id', $tenantId)
            ->where('vo.status', 'approved')
            ->whereExists(function ($q) {
                $q->select(DB::raw(1))
                    ->from('org_transactions as ot')
                    ->whereColumn('ot.organization_id', '=', 'ow.organization_id')
                    ->whereColumn('ot.tenant_id', '=', 'ow.tenant_id')
                    ->where('ot.receiver_type', 'organization')
                    ->whereColumn('ot.receiver_id', '=', 'ow.organization_id');
            })
            ->select('ow.*', 'vo.name as org_name')
            ->get();

        $alertsSent = 0;

        foreach ($wallets as $wallet) {
            if (!$this->areAlertsEnabled($wallet->organization_id)) {
                continue;
            }

            $result = $this->checkBalance($wallet->organization_id, $wallet->balance, $wallet->org_name);
            if ($result['alert_sent']) {
                $alertsSent++;
            }
        }

        return $alertsSent;
    }

    /**
     * Check a single organization's balance and alert if needed.
     *
     * @param int $organizationId Organization ID
     * @param float|null $balance Current balance (fetched if null)
     * @param string|null $orgName Organization name (fetched if null)
     * @return array{balance: float, thresholds: array, alert_type: string|null, alert_sent: bool}
     */
    public function checkBalance($organizationId, $balance = null, $orgName = null): array
    {
        $tenantId = TenantContext::getId();

        if ($balance === null) {
            $wallet = DB::table('org_wallets')
                ->where('organization_id', $organizationId)
                ->where('tenant_id', $tenantId)
                ->first();
            $balance = $wallet ? (float) $wallet->balance : 0.0;
        }

        if ($orgName === null) {
            $org = DB::table('vol_organizations')
                ->where('id', $organizationId)
                ->where('tenant_id', $tenantId)
                ->first();
            $orgName = $org->name ?? __('emails.common.fallback_organization');
        }

        $thresholds = $this->getThresholds($organizationId);
        $alertedToday = $this->hasAlertedToday($organizationId);

        $alertType = null;
        $alertSent = false;

        if ($balance <= $thresholds['critical'] && !$alertedToday['critical']) {
            $alertType = 'critical';
            $this->recordAlert($organizationId, 'critical');
            $alertSent = true;
        } elseif ($balance <= $thresholds['low'] && $balance > $thresholds['critical'] && !$alertedToday['low']) {
            $alertType = 'low';
            $this->recordAlert($organizationId, 'low');
            $alertSent = true;
        }

        return [
            'balance' => (float) $balance,
            'thresholds' => $thresholds,
            'alert_type' => $alertType,
            'alert_sent' => $alertSent,
        ];
    }

    /**
     * Get thresholds for an organization.
     *
     * @param int $organizationId Organization ID
     * @return array{low: float, critical: float}
     */
    public function getThresholds($organizationId): array
    {
        $tenantId = TenantContext::getId();

        try {
            $row = DB::table('org_alert_settings')
                ->where('tenant_id', $tenantId)
                ->where('organization_id', $organizationId)
                ->first();

            if ($row) {
                return [
                    'low' => (float) $row->low_balance_threshold,
                    'critical' => (float) $row->critical_balance_threshold,
                ];
            }
        } catch (\Exception $e) {
            // Table may not exist
        }

        return [
            'low' => (float) self::DEFAULT_LOW_BALANCE_THRESHOLD,
            'critical' => (float) self::DEFAULT_CRITICAL_BALANCE_THRESHOLD,
        ];
    }

    /**
     * Check if balance alerts are enabled for an organization.
     * Returns true by default if no settings exist (opt-out model).
     *
     * @param int $organizationId Organization ID
     * @return bool
     */
    public function areAlertsEnabled($organizationId): bool
    {
        $tenantId = TenantContext::getId();

        try {
            $row = DB::table('org_alert_settings')
                ->where('tenant_id', $tenantId)
                ->where('organization_id', $organizationId)
                ->first();

            if ($row) {
                return (bool) $row->alerts_enabled;
            }
        } catch (\Exception $e) {
            // Table may not exist
        }

        return true;
    }

    /**
     * Set custom thresholds for an organization.
     *
     * @param int $organizationId Organization ID
     * @param float $lowThreshold Low balance threshold
     * @param float $criticalThreshold Critical balance threshold
     * @return bool
     */
    public function setThresholds($organizationId, $lowThreshold, $criticalThreshold): bool
    {
        $tenantId = TenantContext::getId();

        DB::table('org_alert_settings')->updateOrInsert(
            ['tenant_id' => $tenantId, 'organization_id' => $organizationId],
            [
                'low_balance_threshold' => $lowThreshold,
                'critical_balance_threshold' => $criticalThreshold,
                'updated_at' => now(),
            ]
        );

        return true;
    }

    /**
     * Check if we already sent an alert today.
     */
    private function hasAlertedToday(int $organizationId): array
    {
        $tenantId = TenantContext::getId();

        try {
            $alerts = DB::table('org_balance_alerts')
                ->where('tenant_id', $tenantId)
                ->where('organization_id', $organizationId)
                ->whereDate('created_at', DB::raw('CURDATE()'))
                ->pluck('alert_type')
                ->toArray();

            return [
                'low' => in_array('low', $alerts),
                'critical' => in_array('critical', $alerts),
            ];
        } catch (\Exception $e) {
            return ['low' => false, 'critical' => false];
        }
    }

    /**
     * Record that an alert was sent and email the org owner.
     */
    private function recordAlert(int $organizationId, string $alertType): void
    {
        $tenantId = TenantContext::getId();

        $wallet = DB::table('org_wallets')
            ->where('organization_id', $organizationId)
            ->where('tenant_id', $tenantId)
            ->first();
        $balance = $wallet ? (float) $wallet->balance : 0.0;

        try {
            DB::table('org_balance_alerts')->insert([
                'tenant_id'       => $tenantId,
                'organization_id' => $organizationId,
                'alert_type'      => $alertType,
                'balance_at_alert' => $balance,
                'created_at'      => now(),
            ]);
        } catch (\Exception $e) {
            Log::warning('BalanceAlertService::recordAlert DB insert failed: ' . $e->getMessage());
        }

        // Email the org owner
        try {
            $org = DB::table('vol_organizations')
                ->where('id', $organizationId)
                ->where('tenant_id', $tenantId)
                ->select(['user_id', 'name'])
                ->first();

            if ($org) {
                $owner = DB::table('users')
                    ->where('id', $org->user_id)
                    ->where('tenant_id', $tenantId)
                    ->select(['id', 'email', 'first_name', 'name', 'preferred_language'])
                    ->first();

                if ($owner && !empty($owner->email)) {
                    LocaleContext::withLocale($owner, fn() => $this->sendBalanceAlertEmail($owner, $org->name, $balance, $alertType));
                }
            }
        } catch (\Exception $e) {
            Log::warning('BalanceAlertService::recordAlert email failed: ' . $e->getMessage());
        }
    }

    private function sendBalanceAlertEmail(object $owner, string $orgName, float $balance, string $alertType): void
    {
        $ownerName       = $owner->first_name ?? $owner->name ?? __('emails.common.fallback_manager');
        $balanceFormatted = number_format($balance, 2);
        $orgLink         = TenantContext::getFrontendUrl() . TenantContext::getSlugPrefix() . '/volunteer-org/wallet';
        $safeOrgName     = htmlspecialchars($orgName, ENT_QUOTES, 'UTF-8');

        $isCritical = $alertType === 'critical';
        $bodyKey    = $isCritical
            ? 'emails_misc.balance_alert.critical_body'
            : 'emails_misc.balance_alert.low_body';
        $subjectKey = $isCritical
            ? 'emails_misc.balance_alert.critical_subject'
            : 'emails_misc.balance_alert.low_subject';

        $html = EmailTemplateBuilder::make()
            ->theme($isCritical ? 'danger' : 'warning')
            ->title(__('emails_misc.balance_alert.title'))
            ->previewText(__('emails_misc.balance_alert.preview'))
            ->greeting($ownerName)
            ->paragraph(__($bodyKey, ['org' => $safeOrgName, 'balance' => $balanceFormatted]))
            ->paragraph(__('emails_misc.balance_alert.action'))
            ->button(__('emails_misc.balance_alert.cta'), $orgLink)
            ->render();

        $subject = __($subjectKey, ['org' => $orgName]);
        if (!Mailer::forCurrentTenant()->send($owner->email, $subject, $html)) {
            Log::warning('BalanceAlertService: email failed to send', [
                'owner_id'  => $owner->id,
                'org_name'  => $orgName,
                'alert_type' => $alertType,
            ]);
        }
    }
}
