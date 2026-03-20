<?php
// Copyright � 2024�2026 Jasper Ford
// SPDX-License-Identifier: AGPL-3.0-or-later
// Author: Jasper Ford
// See NOTICE file for attribution and acknowledgements.

namespace App\Services;

/**
 *
 * Provides dependency-injectable access to the legacy static service methods.
 */
class TransactionLimitService
{
    public const DEFAULT_SINGLE_TRANSACTION_MAX = 500;
    public const DEFAULT_DAILY_LIMIT = 1000;
    public const DEFAULT_WEEKLY_LIMIT = 3000;
    public const DEFAULT_MONTHLY_LIMIT = 10000;
    public const DEFAULT_ORG_DAILY_LIMIT = 5000;

    public function __construct()
    {
    }

    /**
     * Delegates to legacy TransactionLimitService::getLimit().
     */
    public function getLimit(int $tenantId, int $userId): ?float
    {
        \Illuminate\Support\Facades\Log::warning('Legacy delegation removed: ' . __METHOD__);
        return null;
    }

    /**
     * Delegates to legacy TransactionLimitService::setLimit().
     */
    public function setLimit(int $tenantId, int $userId, float $limit): bool
    {
        \Illuminate\Support\Facades\Log::warning('Legacy delegation removed: ' . __METHOD__);
        return false;
    }

    /**
     * Delegates to legacy TransactionLimitService::checkLimit().
     */
    public function checkLimit(int $tenantId, int $userId, float $amount): bool
    {
        \Illuminate\Support\Facades\Log::warning('Legacy delegation removed: ' . __METHOD__);
        return false;
    }

    /**
     * Check transaction limits for an organization wallet transaction.
     *
     * @param int    $orgId     Organization ID
     * @param int    $userId    User performing the transaction
     * @param float  $amount    Transaction amount
     * @param string $direction 'outgoing' or 'incoming'
     * @return array{allowed: bool, reason: string|null, limits: array}
     */
    public static function checkLimits(int $orgId, int $userId, float $amount, string $direction = 'outgoing'): array
    {
        $limits = self::getLimits($orgId);
        $usage = self::getUsage($orgId, $userId, $direction);

        // Zero or negative amounts are always allowed
        if ($amount <= 0) {
            return [
                'allowed' => true,
                'reason'  => null,
                'limits'  => $limits,
            ];
        }

        // Check single transaction max
        if ($amount > $limits['single_max']) {
            return [
                'allowed' => false,
                'reason'  => "Exceeds single transaction limit of {$limits['single_max']}",
                'limits'  => $limits,
            ];
        }

        // Check daily limit
        if (($usage['daily'] + $amount) > $limits['daily']) {
            return [
                'allowed' => false,
                'reason'  => "Exceeds daily limit of {$limits['daily']}",
                'limits'  => $limits,
            ];
        }

        // Check weekly limit
        if (($usage['weekly'] + $amount) > $limits['weekly']) {
            return [
                'allowed' => false,
                'reason'  => "Exceeds weekly limit of {$limits['weekly']}",
                'limits'  => $limits,
            ];
        }

        // Check monthly limit
        if (($usage['monthly'] + $amount) > $limits['monthly']) {
            return [
                'allowed' => false,
                'reason'  => "Exceeds monthly limit of {$limits['monthly']}",
                'limits'  => $limits,
            ];
        }

        return [
            'allowed' => true,
            'reason'  => null,
            'limits'  => $limits,
        ];
    }

    /**
     * Get configured limits for an organization (or defaults).
     *
     * @return array{single_max: float, daily: float, weekly: float, monthly: float}
     */
    public static function getLimits(int $orgId): array
    {
        try {
            $row = \Illuminate\Support\Facades\DB::table('org_transaction_limits')
                ->where('org_id', $orgId)
                ->first();

            if ($row) {
                return [
                    'single_max' => (float) ($row->single_max ?? self::DEFAULT_SINGLE_TRANSACTION_MAX),
                    'daily'      => (float) ($row->daily_limit ?? self::DEFAULT_DAILY_LIMIT),
                    'weekly'     => (float) ($row->weekly_limit ?? self::DEFAULT_WEEKLY_LIMIT),
                    'monthly'    => (float) ($row->monthly_limit ?? self::DEFAULT_MONTHLY_LIMIT),
                ];
            }
        } catch (\Throwable $e) {
            // Table may not exist
        }

        return [
            'single_max' => (float) self::DEFAULT_SINGLE_TRANSACTION_MAX,
            'daily'      => (float) self::DEFAULT_ORG_DAILY_LIMIT,
            'weekly'     => (float) self::DEFAULT_WEEKLY_LIMIT,
            'monthly'    => (float) self::DEFAULT_MONTHLY_LIMIT,
        ];
    }

    /**
     * Get current usage totals for an organization user.
     *
     * @return array{daily: float, weekly: float, monthly: float}
     */
    public static function getUsage(int $orgId, int $userId, string $direction = 'outgoing'): array
    {
        $column = $direction === 'incoming' ? 'receiver_id' : 'sender_id';

        try {
            $daily = (float) \Illuminate\Support\Facades\DB::table('transactions')
                ->where($column, $userId)
                ->where('status', 'completed')
                ->where('created_at', '>=', now()->startOfDay())
                ->sum('amount');

            $weekly = (float) \Illuminate\Support\Facades\DB::table('transactions')
                ->where($column, $userId)
                ->where('status', 'completed')
                ->where('created_at', '>=', now()->startOfWeek())
                ->sum('amount');

            $monthly = (float) \Illuminate\Support\Facades\DB::table('transactions')
                ->where($column, $userId)
                ->where('status', 'completed')
                ->where('created_at', '>=', now()->startOfMonth())
                ->sum('amount');

            return compact('daily', 'weekly', 'monthly');
        } catch (\Throwable $e) {
            return ['daily' => 0.0, 'weekly' => 0.0, 'monthly' => 0.0];
        }
    }

    /**
     * Get remaining limits for an organization user.
     *
     * @return array{daily: float, weekly: float, monthly: float, single_max: float}
     */
    public static function getRemainingLimits(int $orgId, int $userId): array
    {
        $limits = self::getLimits($orgId);
        $usage = self::getUsage($orgId, $userId, 'outgoing');

        return [
            'single_max' => $limits['single_max'],
            'daily'      => max(0, $limits['daily'] - $usage['daily']),
            'weekly'     => max(0, $limits['weekly'] - $usage['weekly']),
            'monthly'    => max(0, $limits['monthly'] - $usage['monthly']),
        ];
    }

    /**
     * Set custom limits for an organization.
     */
    public static function setCustomLimits(int $orgId, array $limits): bool
    {
        try {
            \Illuminate\Support\Facades\DB::table('org_transaction_limits')->updateOrInsert(
                ['org_id' => $orgId],
                [
                    'single_max'    => $limits['single_max'] ?? self::DEFAULT_SINGLE_TRANSACTION_MAX,
                    'daily_limit'   => $limits['daily'] ?? self::DEFAULT_ORG_DAILY_LIMIT,
                    'weekly_limit'  => $limits['weekly'] ?? self::DEFAULT_WEEKLY_LIMIT,
                    'monthly_limit' => $limits['monthly'] ?? self::DEFAULT_MONTHLY_LIMIT,
                    'updated_at'    => now(),
                ]
            );
            return true;
        } catch (\Throwable $e) {
            return false;
        }
    }
}
