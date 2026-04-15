<?php
// Copyright © 2024–2026 Jasper Ford
// SPDX-License-Identifier: AGPL-3.0-or-later
// Author: Jasper Ford
// See NOTICE file for attribution and acknowledgements.

namespace App\Services;

use App\Core\TenantContext;
use Illuminate\Support\Facades\DB;

/**
 * VolunteerWellbeingService — detects burnout risk and manages wellbeing alerts
 * for volunteers.
 *
 * Backed by `vol_wellbeing_alerts`, `vol_logs`, `vol_shift_signups`, and
 * `vol_applications` tables. All queries are tenant-scoped via TenantContext.
 */
class VolunteerWellbeingService
{
    /** @var array Validation/business errors from the last operation */
    private static array $errors = [];

    public function __construct()
    {
    }

    /**
     * Get errors from the last operation.
     */
    public static function getErrors(): array
    {
        return self::$errors;
    }

    /**
     * Detect burnout risk for a volunteer user.
     *
     * Analyses multiple indicators:
     *  - Shift frequency trend (current vs previous period)
     *  - Cancellation rate
     *  - Hours trend (increasing/declining)
     *  - Engagement gap (days since last activity)
     *
     * Returns a risk assessment with a 0-100 risk_score, risk_level, and
     * detailed indicators that the controller uses to build the wellbeing dashboard.
     *
     * @return array  Assessment with keys: risk_score, risk_level, indicators, recommendations
     */
    public static function detectBurnoutRisk(int $userId): array
    {
        $tenantId = TenantContext::getId();

        $indicators = [];
        $riskPoints = 0;

        // ── 1. Shift frequency trend ──
        // Compare shifts in the last 30 days vs the 30 days before that
        $recentShifts = (int) DB::table('vol_shift_signups as ss')
            ->join('vol_shifts as s', 'ss.shift_id', '=', 's.id')
            ->where('ss.user_id', $userId)
            ->where('ss.tenant_id', $tenantId)
            ->where('s.start_time', '>=', now()->subDays(30))
            ->count();

        $previousShifts = (int) DB::table('vol_shift_signups as ss')
            ->join('vol_shifts as s', 'ss.shift_id', '=', 's.id')
            ->where('ss.user_id', $userId)
            ->where('ss.tenant_id', $tenantId)
            ->where('s.start_time', '>=', now()->subDays(60))
            ->where('s.start_time', '<', now()->subDays(30))
            ->count();

        $shiftTrend = 'stable';
        if ($previousShifts > 0 && $recentShifts < $previousShifts * 0.5) {
            $shiftTrend = 'declining';
            $riskPoints += 20;
        } elseif ($previousShifts > 0 && $recentShifts < $previousShifts * 0.8) {
            $shiftTrend = 'slightly_declining';
            $riskPoints += 10;
        } elseif ($recentShifts > $previousShifts) {
            $shiftTrend = 'increasing';
        }

        $indicators['shift_frequency'] = [
            'recent_count' => $recentShifts,
            'previous_count' => $previousShifts,
            'trend' => $shiftTrend,
        ];

        // ── 2. Cancellation rate ──
        $totalSignups = (int) DB::table('vol_shift_signups')
            ->where('user_id', $userId)
            ->where('tenant_id', $tenantId)
            ->count();

        $cancelledSignups = (int) DB::table('vol_shift_signups')
            ->where('user_id', $userId)
            ->where('tenant_id', $tenantId)
            ->where('status', 'cancelled')
            ->count();

        $cancellationRate = $totalSignups > 0 ? round(($cancelledSignups / $totalSignups) * 100, 1) : 0;

        if ($cancellationRate > 50) {
            $riskPoints += 25;
        } elseif ($cancellationRate > 30) {
            $riskPoints += 15;
        } elseif ($cancellationRate > 15) {
            $riskPoints += 5;
        }

        $indicators['cancellation_rate'] = [
            'total_signups' => $totalSignups,
            'cancelled' => $cancelledSignups,
            'rate_percent' => $cancellationRate,
        ];

        // ── 3. Hours trend ──
        $recentHours = (float) DB::table('vol_logs')
            ->where('user_id', $userId)
            ->where('tenant_id', $tenantId)
            ->where('status', 'approved')
            ->where('date_logged', '>=', now()->subDays(30))
            ->sum('hours');

        $previousHours = (float) DB::table('vol_logs')
            ->where('user_id', $userId)
            ->where('tenant_id', $tenantId)
            ->where('status', 'approved')
            ->where('date_logged', '>=', now()->subDays(60))
            ->where('date_logged', '<', now()->subDays(30))
            ->sum('hours');

        $hoursTrend = 'stable';
        if ($previousHours > 0 && $recentHours < $previousHours * 0.3) {
            $hoursTrend = 'declining_significantly';
            $riskPoints += 25;
        } elseif ($previousHours > 0 && $recentHours < $previousHours * 0.6) {
            $hoursTrend = 'declining';
            $riskPoints += 15;
        } elseif ($recentHours > $previousHours && $previousHours > 0) {
            $hoursTrend = 'increasing';
        }

        $indicators['hours_trend'] = [
            'recent_hours' => round($recentHours, 2),
            'previous_hours' => round($previousHours, 2),
            'trend' => $hoursTrend,
        ];

        // ── 4. Engagement gap ──
        $lastActivity = DB::table('vol_logs')
            ->where('user_id', $userId)
            ->where('tenant_id', $tenantId)
            ->where('status', 'approved')
            ->max('date_logged');

        $daysSinceLastActivity = 0;
        if ($lastActivity) {
            $daysSinceLastActivity = (int) now()->diffInDays($lastActivity);
        } else {
            // No activity ever — check if they have any signups at all
            $hasAnySignup = DB::table('vol_shift_signups')
                ->where('user_id', $userId)
                ->where('tenant_id', $tenantId)
                ->exists();
            $daysSinceLastActivity = $hasAnySignup ? 90 : 0; // only flag if they were once active
        }

        if ($daysSinceLastActivity > 60) {
            $riskPoints += 20;
        } elseif ($daysSinceLastActivity > 30) {
            $riskPoints += 10;
        } elseif ($daysSinceLastActivity > 14) {
            $riskPoints += 5;
        }

        $indicators['engagement_gap'] = [
            'last_activity_date' => $lastActivity,
            'days_since_last_activity' => $daysSinceLastActivity,
        ];

        // ── 5. Overcommitment check ──
        // Count upcoming scheduled shifts in the next 7 days
        $upcomingShifts = (int) DB::table('vol_shift_signups as ss')
            ->join('vol_shifts as s', 'ss.shift_id', '=', 's.id')
            ->where('ss.user_id', $userId)
            ->where('ss.tenant_id', $tenantId)
            ->where('ss.status', 'confirmed')
            ->where('s.start_time', '>=', now())
            ->where('s.start_time', '<=', now()->addDays(7))
            ->count();

        if ($upcomingShifts > 7) {
            $riskPoints += 15; // More than one shift per day on average
        } elseif ($upcomingShifts > 5) {
            $riskPoints += 5;
        }

        $indicators['overcommitment'] = [
            'upcoming_shifts_7_days' => $upcomingShifts,
        ];

        // ── Calculate overall risk ──
        $riskScore = min(100, max(0, $riskPoints));

        $riskLevel = match (true) {
            $riskScore >= 70 => 'critical',
            $riskScore >= 50 => 'high',
            $riskScore >= 30 => 'moderate',
            default => 'low',
        };

        // Build recommendations based on risk indicators
        $recommendations = [];
        if ($shiftTrend === 'declining' || $hoursTrend === 'declining_significantly') {
            $recommendations[] = 'Consider taking a break or reducing your commitments for a while.';
        }
        if ($cancellationRate > 30) {
            $recommendations[] = 'Try committing to fewer shifts that you can reliably attend.';
        }
        if ($daysSinceLastActivity > 30) {
            $recommendations[] = 'Start with a small, low-commitment opportunity to ease back in.';
        }
        if ($upcomingShifts > 7) {
            $recommendations[] = 'You have many shifts coming up. Make sure to schedule rest days.';
        }
        if ($riskLevel === 'low' && empty($recommendations)) {
            $recommendations[] = 'You are maintaining a healthy volunteering balance. Keep it up!';
        }

        // Persist alert if risk is moderate or higher
        if ($riskScore >= 30) {
            self::upsertAlert($tenantId, $userId, $riskLevel, $riskScore, $indicators);
        }

        return [
            'risk_score' => $riskScore,
            'risk_level' => $riskLevel,
            'indicators' => $indicators,
            'recommendations' => $recommendations,
        ];
    }

    /**
     * Run a tenant-wide burnout assessment.
     *
     * Scans all active volunteers in the current tenant and returns
     * summary statistics plus a list of at-risk volunteers.
     *
     * @return array  Summary with keys: total_assessed, at_risk, risk_breakdown, at_risk_users
     */
    public static function runTenantAssessment(): array
    {
        $tenantId = TenantContext::getId();

        // Get users who have volunteered (have approved logs or shift signups)
        $volunteerIds = DB::table('vol_logs')
            ->where('tenant_id', $tenantId)
            ->where('status', 'approved')
            ->distinct()
            ->pluck('user_id')
            ->merge(
                DB::table('vol_shift_signups')
                    ->where('tenant_id', $tenantId)
                    ->distinct()
                    ->pluck('user_id')
            )
            ->unique()
            ->values()
            ->all();

        $riskBreakdown = ['low' => 0, 'moderate' => 0, 'high' => 0, 'critical' => 0];
        $atRiskUsers = [];

        foreach ($volunteerIds as $userId) {
            $assessment = self::detectBurnoutRisk((int) $userId);
            $level = $assessment['risk_level'] ?? 'low';
            $riskBreakdown[$level] = ($riskBreakdown[$level] ?? 0) + 1;

            if (in_array($level, ['moderate', 'high', 'critical'], true)) {
                // Fetch user name
                $user = DB::table('users')
                    ->where('id', $userId)
                    ->where('tenant_id', $tenantId)
                    ->select('id', 'first_name', 'last_name')
                    ->first();

                if ($user) {
                    $atRiskUsers[] = [
                        'user_id' => (int) $user->id,
                        'name' => trim($user->first_name . ' ' . $user->last_name),
                        'risk_level' => $level,
                        'risk_score' => $assessment['risk_score'],
                    ];
                }
            }
        }

        // Sort at-risk users by score descending
        usort($atRiskUsers, fn ($a, $b) => $b['risk_score'] <=> $a['risk_score']);

        return [
            'total_assessed' => count($volunteerIds),
            'at_risk' => count($atRiskUsers),
            'risk_breakdown' => $riskBreakdown,
            'at_risk_users' => $atRiskUsers,
        ];
    }

    /**
     * Get active wellbeing alerts for the current tenant.
     *
     * @return array  List of active alerts with user info
     */
    public static function getActiveAlerts(): array
    {
        $tenantId = TenantContext::getId();

        try {
            $alerts = DB::table('vol_wellbeing_alerts as wa')
                ->join('users as u', function ($join) {
                    $join->on('wa.user_id', '=', 'u.id')
                         ->on('wa.tenant_id', '=', 'u.tenant_id');
                })
                ->where('wa.tenant_id', $tenantId)
                ->where('wa.status', 'active')
                ->select(
                    'wa.id', 'wa.user_id', 'wa.risk_level', 'wa.risk_score',
                    'wa.indicators', 'wa.coordinator_notified', 'wa.coordinator_notes',
                    'wa.status', 'wa.created_at', 'wa.updated_at',
                    'u.first_name', 'u.last_name', 'u.avatar_url'
                )
                ->orderByDesc('wa.risk_score')
                ->get();

            return $alerts->map(fn ($row) => [
                'id' => (int) $row->id,
                'user_id' => (int) $row->user_id,
                'user_name' => trim($row->first_name . ' ' . $row->last_name),
                'avatar_url' => $row->avatar_url,
                'risk_level' => $row->risk_level,
                'risk_score' => round((float) $row->risk_score, 2),
                'indicators' => json_decode($row->indicators, true) ?? [],
                'coordinator_notified' => (bool) $row->coordinator_notified,
                'coordinator_notes' => $row->coordinator_notes,
                'status' => $row->status,
                'created_at' => $row->created_at,
                'updated_at' => $row->updated_at,
            ])->all();
        } catch (\Throwable $e) {
            \Illuminate\Support\Facades\Log::warning("VolunteerWellbeingService::getActiveAlerts error: " . $e->getMessage());
            return [];
        }
    }

    /**
     * Update a wellbeing alert (acknowledge, resolve, dismiss, or add notes).
     *
     * @param int         $alertId
     * @param string      $action   One of: acknowledged, resolved, dismissed
     * @param string|null $notes    Optional coordinator notes
     * @return bool  True on success
     */
    public static function updateAlert(int $alertId, string $action, ?string $notes = null): bool
    {
        self::$errors = [];
        $tenantId = TenantContext::getId();

        $allowedActions = ['acknowledged', 'resolved', 'dismissed'];
        if (!in_array($action, $allowedActions, true)) {
            self::$errors[] = ['code' => 'VALIDATION_ERROR', 'message' => 'Invalid action. Must be one of: ' . implode(', ', $allowedActions)];
            return false;
        }

        $alert = DB::table('vol_wellbeing_alerts')
            ->where('id', $alertId)
            ->where('tenant_id', $tenantId)
            ->first();

        if (!$alert) {
            self::$errors[] = ['code' => 'NOT_FOUND', 'message' => 'Alert not found'];
            return false;
        }

        try {
            $update = [
                'status' => $action,
                'coordinator_notified' => true,
                'updated_at' => now(),
            ];

            if ($notes !== null) {
                $update['coordinator_notes'] = trim(mb_substr($notes, 0, 2000));
            }

            if ($action === 'resolved') {
                $update['resolved_at'] = now();
            }

            DB::table('vol_wellbeing_alerts')
                ->where('id', $alertId)
                ->where('tenant_id', $tenantId)
                ->update($update);

            return true;
        } catch (\Throwable $e) {
            \Illuminate\Support\Facades\Log::warning("VolunteerWellbeingService::updateAlert error: " . $e->getMessage());
            self::$errors[] = ['code' => 'SERVER_ERROR', 'message' => 'Failed to update alert'];
            return false;
        }
    }

    /**
     * Upsert a wellbeing alert — creates or updates an active alert for a user.
     */
    private static function upsertAlert(int $tenantId, int $userId, string $riskLevel, int $riskScore, array $indicators): void
    {
        try {
            $existing = DB::table('vol_wellbeing_alerts')
                ->where('tenant_id', $tenantId)
                ->where('user_id', $userId)
                ->where('status', 'active')
                ->first();

            if ($existing) {
                DB::table('vol_wellbeing_alerts')
                    ->where('id', $existing->id)
                    ->update([
                        'risk_level' => $riskLevel,
                        'risk_score' => $riskScore,
                        'indicators' => json_encode($indicators),
                        'updated_at' => now(),
                    ]);
            } else {
                DB::table('vol_wellbeing_alerts')->insert([
                    'tenant_id' => $tenantId,
                    'user_id' => $userId,
                    'risk_level' => $riskLevel,
                    'risk_score' => $riskScore,
                    'indicators' => json_encode($indicators),
                    'coordinator_notified' => false,
                    'status' => 'active',
                    'created_at' => now(),
                    'updated_at' => now(),
                ]);
            }
        } catch (\Throwable $e) {
            // Non-critical — log but don't fail the assessment
            \Illuminate\Support\Facades\Log::warning("VolunteerWellbeingService::upsertAlert error: " . $e->getMessage());
        }
    }
}
