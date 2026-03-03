<?php
// Copyright © 2024–2026 Jasper Ford
// SPDX-License-Identifier: AGPL-3.0-or-later
// Author: Jasper Ford
// See NOTICE file for attribution and acknowledgements.

namespace Nexus\Services;

use Nexus\Core\Database;
use Nexus\Core\TenantContext;

/**
 * PredictiveStaffingService - Predict upcoming volunteer shortages
 *
 * Analyzes historical volunteer patterns to predict upcoming shortages:
 * - Shift fill rates over time
 * - Seasonal patterns
 * - Volunteer availability trends
 *
 * Key method: predictShortages($tenantId, $nextDays)
 *  -> returns upcoming shifts/events at risk of being unfilled
 *
 * Stores predictions in `staffing_predictions` table.
 * Alerts coordinators via notifications.
 */
class PredictiveStaffingService
{
    /**
     * Predict upcoming staffing shortages
     *
     * @param int $tenantId
     * @param int $nextDays Number of days to look ahead
     * @return array Predictions with risk levels
     */
    public static function predictShortages(int $tenantId, int $nextDays = 14): array
    {
        $predictions = [];

        // Analyze upcoming volunteer opportunities
        $upcomingShifts = self::getUpcomingShifts($tenantId, $nextDays);
        $historicalFillRate = self::getHistoricalFillRate($tenantId);
        $seasonalFactors = self::getSeasonalFactors($tenantId);

        foreach ($upcomingShifts as $shift) {
            $prediction = self::assessShiftRisk($shift, $historicalFillRate, $seasonalFactors);

            if ($prediction['risk_level'] !== 'low') {
                $predictions[] = $prediction;

                // Store prediction in database
                self::storePrediction($tenantId, $prediction);
            }
        }

        // Sort by risk level (critical first)
        usort($predictions, function ($a, $b) {
            $riskOrder = ['critical' => 0, 'high' => 1, 'medium' => 2, 'low' => 3];
            return ($riskOrder[$a['risk_level']] ?? 3) <=> ($riskOrder[$b['risk_level']] ?? 3);
        });

        return [
            'predictions' => $predictions,
            'total_at_risk' => count($predictions),
            'critical_count' => count(array_filter($predictions, fn($p) => $p['risk_level'] === 'critical')),
            'high_count' => count(array_filter($predictions, fn($p) => $p['risk_level'] === 'high')),
            'medium_count' => count(array_filter($predictions, fn($p) => $p['risk_level'] === 'medium')),
            'analysis_period_days' => $nextDays,
            'historical_fill_rate' => $historicalFillRate,
        ];
    }

    /**
     * Get upcoming shifts/volunteer opportunities
     */
    private static function getUpcomingShifts(int $tenantId, int $nextDays): array
    {
        try {
            $shifts = [];

            // Check volunteer opportunities with slots
            $stmt = Database::query(
                "SELECT v.id, v.title, v.start_date, v.end_date, v.slots_needed,
                        v.organization_id, o.name as org_name,
                        (SELECT COUNT(*) FROM vol_applications vs WHERE vs.opportunity_id = v.id AND vs.status = 'approved') as filled_slots
                 FROM vol_opportunities v
                 LEFT JOIN vol_organizations o ON v.organization_id = o.id
                 WHERE v.tenant_id = ? AND v.status = 'active'
                   AND v.start_date BETWEEN CURDATE() AND DATE_ADD(CURDATE(), INTERVAL ? DAY)
                 ORDER BY v.start_date ASC",
                [$tenantId, $nextDays]
            );

            foreach ($stmt->fetchAll(\PDO::FETCH_ASSOC) as $row) {
                $shifts[] = [
                    'type' => 'volunteering',
                    'id' => (int)$row['id'],
                    'title' => $row['title'],
                    'date' => $row['start_date'],
                    'end_date' => $row['end_date'],
                    'slots_needed' => (int)($row['slots_needed'] ?? 1),
                    'filled_slots' => (int)$row['filled_slots'],
                    'organization' => $row['org_name'],
                ];
            }

            // Check events that need volunteers (group events with RSVP counts)
            $eventStmt = Database::query(
                "SELECT e.id, e.title, e.start_time as start_date, e.end_time as end_date,
                        (SELECT COUNT(*) FROM event_rsvps er WHERE er.event_id = e.id AND er.status = 'going') as confirmed_attendees
                 FROM events e
                 WHERE e.tenant_id = ?
                   AND e.start_time BETWEEN NOW() AND DATE_ADD(NOW(), INTERVAL ? DAY)
                 ORDER BY e.start_time ASC",
                [$tenantId, $nextDays]
            );

            foreach ($eventStmt->fetchAll(\PDO::FETCH_ASSOC) as $row) {
                $shifts[] = [
                    'type' => 'event',
                    'id' => (int)$row['id'],
                    'title' => $row['title'],
                    'date' => $row['start_date'],
                    'end_date' => $row['end_date'],
                    'slots_needed' => 5, // Default expected minimum
                    'filled_slots' => (int)$row['confirmed_attendees'],
                    'organization' => null,
                ];
            }

            return $shifts;
        } catch (\Exception $e) {
            error_log("PredictiveStaffingService::getUpcomingShifts error: " . $e->getMessage());
            return [];
        }
    }

    /**
     * Get historical fill rate for the tenant
     *
     * @return float Fill rate as percentage (0-100)
     */
    private static function getHistoricalFillRate(int $tenantId): float
    {
        try {
            // Look at past 90 days of volunteer opportunities
            $stmt = Database::query(
                "SELECT
                    COALESCE(AVG(
                        CASE WHEN v.slots_needed > 0
                            THEN LEAST(100, (SELECT COUNT(*) FROM vol_applications vs WHERE vs.opportunity_id = v.id AND vs.status = 'approved') * 100.0 / v.slots_needed)
                            ELSE 100
                        END
                    ), 75) as avg_fill_rate
                 FROM vol_opportunities v
                 WHERE v.tenant_id = ?
                   AND v.start_date BETWEEN DATE_SUB(CURDATE(), INTERVAL 90 DAY) AND CURDATE()",
                [$tenantId]
            );

            $result = $stmt->fetch(\PDO::FETCH_ASSOC);
            return round((float)($result['avg_fill_rate'] ?? 75), 1);
        } catch (\Exception $e) {
            return 75.0; // Default assumption
        }
    }

    /**
     * Get seasonal factors (day-of-week and month patterns)
     */
    private static function getSeasonalFactors(int $tenantId): array
    {
        $factors = [
            'day_of_week' => [],
            'month' => [],
        ];

        try {
            // Day-of-week signup rates
            $dayStmt = Database::query(
                "SELECT DAYOFWEEK(vs.created_at) as dow, COUNT(*) as signups
                 FROM vol_applications vs
                 JOIN vol_opportunities v ON vs.opportunity_id = v.id
                 WHERE v.tenant_id = ? AND vs.created_at >= DATE_SUB(NOW(), INTERVAL 6 MONTH)
                 GROUP BY DAYOFWEEK(vs.created_at)",
                [$tenantId]
            );

            foreach ($dayStmt->fetchAll(\PDO::FETCH_ASSOC) as $row) {
                $factors['day_of_week'][(int)$row['dow']] = (int)$row['signups'];
            }

            // Monthly patterns
            $monthStmt = Database::query(
                "SELECT MONTH(vs.created_at) as month, COUNT(*) as signups
                 FROM vol_applications vs
                 JOIN vol_opportunities v ON vs.opportunity_id = v.id
                 WHERE v.tenant_id = ? AND vs.created_at >= DATE_SUB(NOW(), INTERVAL 12 MONTH)
                 GROUP BY MONTH(vs.created_at)",
                [$tenantId]
            );

            foreach ($monthStmt->fetchAll(\PDO::FETCH_ASSOC) as $row) {
                $factors['month'][(int)$row['month']] = (int)$row['signups'];
            }
        } catch (\Exception $e) {
            // Use defaults
        }

        return $factors;
    }

    /**
     * Assess risk for a specific shift
     */
    private static function assessShiftRisk(array $shift, float $historicalFillRate, array $seasonalFactors): array
    {
        $slotsNeeded = max(1, $shift['slots_needed']);
        $filledSlots = $shift['filled_slots'];
        $fillPercentage = ($filledSlots / $slotsNeeded) * 100;
        $shortfall = max(0, $slotsNeeded - $filledSlots);

        // Days until shift
        $daysUntil = max(0, (int)((strtotime($shift['date']) - time()) / 86400));

        // Calculate risk score (0-100, higher = more risk)
        $riskScore = 0;

        // Factor 1: Current fill gap (40% weight)
        $gapFactor = (1 - ($filledSlots / $slotsNeeded)) * 100;
        $riskScore += $gapFactor * 0.4;

        // Factor 2: Time pressure (30% weight) - less time = more risk
        if ($daysUntil <= 1) {
            $riskScore += 30;
        } elseif ($daysUntil <= 3) {
            $riskScore += 25;
        } elseif ($daysUntil <= 7) {
            $riskScore += 15;
        } else {
            $riskScore += 5;
        }

        // Factor 3: Historical fill rate (20% weight)
        if ($historicalFillRate < 50) {
            $riskScore += 20;
        } elseif ($historicalFillRate < 75) {
            $riskScore += 10;
        }

        // Factor 4: Seasonal adjustment (10% weight)
        $shiftMonth = (int)date('n', strtotime($shift['date']));
        $monthSignups = $seasonalFactors['month'][$shiftMonth] ?? null;
        $avgMonthSignups = !empty($seasonalFactors['month'])
            ? array_sum($seasonalFactors['month']) / count($seasonalFactors['month'])
            : null;

        if ($monthSignups !== null && $avgMonthSignups !== null && $avgMonthSignups > 0) {
            if ($monthSignups < $avgMonthSignups * 0.7) {
                $riskScore += 10; // Below average month
            }
        }

        // Determine risk level
        $riskLevel = 'low';
        if ($riskScore >= 70) {
            $riskLevel = 'critical';
        } elseif ($riskScore >= 50) {
            $riskLevel = 'high';
        } elseif ($riskScore >= 30) {
            $riskLevel = 'medium';
        }

        // Calculate confidence
        $confidence = min(95, 50 + ($daysUntil <= 3 ? 30 : ($daysUntil <= 7 ? 15 : 5)));
        if ($historicalFillRate > 0) {
            $confidence = min(95, $confidence + 10);
        }

        return [
            'type' => $shift['type'],
            'id' => $shift['id'],
            'title' => $shift['title'],
            'date' => $shift['date'],
            'days_until' => $daysUntil,
            'slots_needed' => $slotsNeeded,
            'filled_slots' => $filledSlots,
            'shortfall' => $shortfall,
            'fill_percentage' => round($fillPercentage, 1),
            'risk_level' => $riskLevel,
            'risk_score' => (int)round($riskScore),
            'confidence' => round($confidence, 1),
            'organization' => $shift['organization'],
            'factors' => [
                'gap' => round($gapFactor, 1),
                'time_pressure' => $daysUntil <= 3 ? 'high' : ($daysUntil <= 7 ? 'medium' : 'low'),
                'historical_fill_rate' => $historicalFillRate,
            ],
        ];
    }

    /**
     * Store prediction in database
     */
    private static function storePrediction(int $tenantId, array $prediction): void
    {
        try {
            $shiftId = $prediction['type'] === 'volunteering' ? $prediction['id'] : null;
            $eventId = $prediction['type'] === 'event' ? $prediction['id'] : null;

            Database::query(
                "INSERT INTO staffing_predictions (tenant_id, shift_id, event_id, predicted_date, predicted_shortfall, confidence, risk_level, factors_json, created_at)
                 VALUES (?, ?, ?, ?, ?, ?, ?, ?, NOW())
                 ON DUPLICATE KEY UPDATE predicted_shortfall = VALUES(predicted_shortfall), confidence = VALUES(confidence), risk_level = VALUES(risk_level), factors_json = VALUES(factors_json)",
                [
                    $tenantId,
                    $shiftId,
                    $eventId,
                    $prediction['date'],
                    $prediction['shortfall'],
                    $prediction['confidence'],
                    $prediction['risk_level'],
                    json_encode($prediction['factors']),
                ]
            );
        } catch (\Exception $e) {
            error_log("PredictiveStaffingService::storePrediction error: " . $e->getMessage());
        }
    }

    /**
     * Alert coordinators about critical shortages
     */
    public static function alertCoordinators(int $tenantId, array $criticalPredictions): void
    {
        if (empty($criticalPredictions)) {
            return;
        }

        try {
            // Get coordinator/admin users
            $admins = Database::query(
                "SELECT id FROM users WHERE tenant_id = ? AND role IN ('admin', 'coordinator') AND status = 'active'",
                [$tenantId]
            )->fetchAll(\PDO::FETCH_COLUMN);

            foreach ($admins as $adminId) {
                foreach ($criticalPredictions as $prediction) {
                    $message = "Staffing alert: {$prediction['title']} on {$prediction['date']} — {$prediction['shortfall']} volunteer(s) still needed ({$prediction['risk_level']} risk)";
                    \Nexus\Models\Notification::create(
                        (int)$adminId,
                        $message,
                        '/admin/staffing',
                        'staffing_alert'
                    );
                }
            }
        } catch (\Exception $e) {
            error_log("PredictiveStaffingService::alertCoordinators error: " . $e->getMessage());
        }
    }

    /**
     * Get summary of recent predictions for admin dashboard
     */
    public static function getDashboardSummary(int $tenantId): array
    {
        try {
            $stmt = Database::query(
                "SELECT risk_level, COUNT(*) as count
                 FROM staffing_predictions
                 WHERE tenant_id = ? AND predicted_date >= CURDATE() AND resolved_at IS NULL
                 GROUP BY risk_level",
                [$tenantId]
            );

            $summary = ['critical' => 0, 'high' => 0, 'medium' => 0, 'low' => 0, 'total' => 0];
            foreach ($stmt->fetchAll(\PDO::FETCH_ASSOC) as $row) {
                $summary[$row['risk_level']] = (int)$row['count'];
                $summary['total'] += (int)$row['count'];
            }

            return $summary;
        } catch (\Exception $e) {
            return ['critical' => 0, 'high' => 0, 'medium' => 0, 'low' => 0, 'total' => 0];
        }
    }
}
