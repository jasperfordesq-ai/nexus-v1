<?php
// Copyright © 2024–2026 Jasper Ford
// SPDX-License-Identifier: AGPL-3.0-or-later
// Author: Jasper Ford
// See NOTICE file for attribution and acknowledgements.

namespace App\Services;

use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;

/**
 * EngagementRecognitionService — Replaces daily login reward mechanics with
 * monthly engagement tracking and seasonal recognition.
 *
 * Instead of rewarding "showed up every day", this service recognises
 * meaningful community participation: transactions, event attendance,
 * volunteer logs, and listings created.
 */
class EngagementRecognitionService
{
    /**
     * Check whether the user had meaningful activity this month.
     *
     * Counts transactions, event attendances, volunteer logs, and
     * listings created within the current calendar month, then upserts
     * the monthly_engagement row.
     *
     * @return array{was_active: bool, activity_count: int, year_month: string}
     */
    public static function checkMonthlyEngagement(int $tenantId, int $userId): array
    {
        try {
            $yearMonth = now()->format('Y-m');
            $monthStart = now()->startOfMonth()->toDateTimeString();
            $monthEnd = now()->endOfMonth()->toDateTimeString();

            $activityCount = 0;

            // Transactions (sent or received)
            $activityCount += (int) DB::table('transactions')
                ->where('tenant_id', $tenantId)
                ->where(function ($q) use ($userId) {
                    $q->where('sender_id', $userId)
                      ->orWhere('receiver_id', $userId);
                })
                ->whereBetween('created_at', [$monthStart, $monthEnd])
                ->count();

            // Event attendance
            $activityCount += (int) DB::table('event_attendees')
                ->where('tenant_id', $tenantId)
                ->where('user_id', $userId)
                ->whereBetween('created_at', [$monthStart, $monthEnd])
                ->count();

            // Volunteer logs
            $activityCount += (int) DB::table('volunteer_logs')
                ->where('tenant_id', $tenantId)
                ->where('user_id', $userId)
                ->whereBetween('created_at', [$monthStart, $monthEnd])
                ->count();

            // Listings created
            $activityCount += (int) DB::table('listings')
                ->where('tenant_id', $tenantId)
                ->where('user_id', $userId)
                ->whereBetween('created_at', [$monthStart, $monthEnd])
                ->count();

            $wasActive = $activityCount > 0;

            // Upsert monthly_engagement row
            DB::table('monthly_engagement')->updateOrInsert(
                [
                    'tenant_id'  => $tenantId,
                    'user_id'    => $userId,
                    'year_month' => $yearMonth,
                ],
                [
                    'was_active'     => $wasActive,
                    'activity_count' => $activityCount,
                    'recognized_at'  => $wasActive ? now() : null,
                    'updated_at'     => now(),
                    'created_at'     => now(),
                ]
            );

            return [
                'was_active'     => $wasActive,
                'activity_count' => $activityCount,
                'year_month'     => $yearMonth,
            ];
        } catch (\Throwable $e) {
            Log::error('EngagementRecognitionService::checkMonthlyEngagement error: ' . $e->getMessage());
            return [
                'was_active'     => false,
                'activity_count' => 0,
                'year_month'     => now()->format('Y-m'),
            ];
        }
    }

    /**
     * Return the last N months of engagement data for a user.
     *
     * @return array<int, array{year_month: string, was_active: bool, activity_count: int, recognized_at: string|null}>
     */
    public static function getEngagementHistory(int $tenantId, int $userId, int $months = 12): array
    {
        try {
            return DB::table('monthly_engagement')
                ->where('tenant_id', $tenantId)
                ->where('user_id', $userId)
                ->orderByDesc('year_month')
                ->limit($months)
                ->get()
                ->map(fn ($row) => [
                    'year_month'     => $row->year_month,
                    'was_active'     => (bool) $row->was_active,
                    'activity_count' => (int) $row->activity_count,
                    'recognized_at'  => $row->recognized_at,
                ])
                ->toArray();
        } catch (\Throwable $e) {
            Log::error('EngagementRecognitionService::getEngagementHistory error: ' . $e->getMessage());
            return [];
        }
    }

    /**
     * Return seasonal recognition data for a user.
     *
     * A "season" is a calendar quarter (e.g. '2026-Q1'). The months_active
     * count reflects how many of the quarter's months the user was active.
     *
     * @return array<int, array{season: string, months_active: int, recognized_at: string|null}>
     */
    public static function getSeasonalRecognition(int $tenantId, int $userId): array
    {
        try {
            return DB::table('seasonal_recognition')
                ->where('tenant_id', $tenantId)
                ->where('user_id', $userId)
                ->orderByDesc('season')
                ->get()
                ->map(fn ($row) => [
                    'season'        => $row->season,
                    'months_active' => (int) $row->months_active,
                    'recognized_at' => $row->recognized_at,
                ])
                ->toArray();
        } catch (\Throwable $e) {
            Log::error('EngagementRecognitionService::getSeasonalRecognition error: ' . $e->getMessage());
            return [];
        }
    }

    /**
     * Recalculate seasonal recognition for the current quarter.
     *
     * Counts how many months in the current quarter the user was active
     * (from monthly_engagement) and upserts the seasonal_recognition row.
     */
    public static function updateSeasonalRecognition(int $tenantId, int $userId): array
    {
        try {
            $quarter = (int) ceil(now()->month / 3);
            $year = now()->year;
            $season = "{$year}-Q{$quarter}";

            // Determine months in this quarter
            $quarterStartMonth = ($quarter - 1) * 3 + 1;
            $quarterMonths = [];
            for ($m = $quarterStartMonth; $m < $quarterStartMonth + 3; $m++) {
                $quarterMonths[] = sprintf('%d-%02d', $year, $m);
            }

            $monthsActive = (int) DB::table('monthly_engagement')
                ->where('tenant_id', $tenantId)
                ->where('user_id', $userId)
                ->whereIn('year_month', $quarterMonths)
                ->where('was_active', true)
                ->count();

            DB::table('seasonal_recognition')->updateOrInsert(
                [
                    'tenant_id' => $tenantId,
                    'user_id'   => $userId,
                    'season'    => $season,
                ],
                [
                    'months_active' => $monthsActive,
                    'recognized_at' => $monthsActive > 0 ? now() : null,
                    'updated_at'    => now(),
                    'created_at'    => now(),
                ]
            );

            return [
                'season'        => $season,
                'months_active' => $monthsActive,
            ];
        } catch (\Throwable $e) {
            Log::error('EngagementRecognitionService::updateSeasonalRecognition error: ' . $e->getMessage());
            return [
                'season'        => '',
                'months_active' => 0,
            ];
        }
    }
}
