<?php
// Copyright © 2024–2026 Jasper Ford
// SPDX-License-Identifier: AGPL-3.0-or-later
// Author: Jasper Ford
// See NOTICE file for attribution and acknowledgements.

namespace Nexus\Services;

use Nexus\Core\Database;
use Nexus\Core\TenantContext;

/**
 * VolunteerWellbeingService - Algorithmic burnout risk detection
 *
 * Detects declining engagement patterns by tracking:
 * - Shift frequency trending down
 * - Cancellation rate increasing
 * - Response time to shift offers increasing
 * - Hours logged decreasing
 * - Login/activity frequency declining
 *
 * Risk levels: low (0-25), moderate (26-50), high (51-75), critical (76-100)
 *
 * Flags at-risk volunteers for coordinator follow-up.
 */
class VolunteerWellbeingService
{
    private static array $errors = [];

    public static function getErrors(): array
    {
        return self::$errors;
    }

    /**
     * Risk level thresholds
     */
    private const RISK_LOW = 25;
    private const RISK_MODERATE = 50;
    private const RISK_HIGH = 75;

    /**
     * Detect burnout risk for a specific volunteer
     *
     * @param int $userId User to assess
     * @return array Risk assessment result
     */
    public static function detectBurnoutRisk(int $userId): array
    {
        $tenantId = TenantContext::getId();
        $db = Database::getConnection();

        $indicators = [];
        $totalScore = 0;

        // 1. Shift frequency trend (compare last 30 days vs previous 30 days)
        $shiftTrend = self::calculateShiftFrequencyTrend($db, $userId);
        $indicators['shift_frequency'] = $shiftTrend;
        $totalScore += $shiftTrend['risk_contribution'];

        // 2. Cancellation rate
        $cancellationRate = self::calculateCancellationRate($db, $userId);
        $indicators['cancellation_rate'] = $cancellationRate;
        $totalScore += $cancellationRate['risk_contribution'];

        // 3. Hours logged trend
        $hoursTrend = self::calculateHoursLoggedTrend($db, $userId);
        $indicators['hours_trend'] = $hoursTrend;
        $totalScore += $hoursTrend['risk_contribution'];

        // 4. Response time to alerts/invitations
        $responseTime = self::calculateResponseTimeTrend($db, $userId);
        $indicators['response_time'] = $responseTime;
        $totalScore += $responseTime['risk_contribution'];

        // 5. Engagement gap (days since last activity)
        $engagementGap = self::calculateEngagementGap($db, $userId);
        $indicators['engagement_gap'] = $engagementGap;
        $totalScore += $engagementGap['risk_contribution'];

        // Cap at 100
        $totalScore = min(100, max(0, $totalScore));

        // Determine risk level
        $riskLevel = 'low';
        if ($totalScore > self::RISK_HIGH) {
            $riskLevel = 'critical';
        } elseif ($totalScore > self::RISK_MODERATE) {
            $riskLevel = 'high';
        } elseif ($totalScore > self::RISK_LOW) {
            $riskLevel = 'moderate';
        }

        return [
            'user_id' => $userId,
            'risk_score' => round($totalScore, 1),
            'risk_level' => $riskLevel,
            'indicators' => $indicators,
            'assessed_at' => date('Y-m-d H:i:s'),
        ];
    }

    /**
     * Run burnout detection for all active volunteers in a tenant
     *
     * @return array Summary of assessments
     */
    public static function runTenantAssessment(): array
    {
        $tenantId = TenantContext::getId();
        $db = Database::getConnection();

        // Find active volunteers (had at least one approved application or logged hours)
        $stmt = $db->prepare("
            SELECT DISTINCT u.id
            FROM users u
            WHERE u.tenant_id = ?
            AND (
                u.id IN (SELECT user_id FROM vol_applications WHERE status = 'approved' AND tenant_id = u.tenant_id)
                OR u.id IN (SELECT user_id FROM vol_logs WHERE tenant_id = u.tenant_id)
            )
            LIMIT 500
        ");
        $stmt->execute([$tenantId]);
        $volunteers = $stmt->fetchAll(\PDO::FETCH_COLUMN);

        $assessments = [];
        $alertsCreated = 0;

        foreach ($volunteers as $volunteerId) {
            $assessment = self::detectBurnoutRisk((int)$volunteerId);

            // Only store alerts for moderate+ risk
            if (in_array($assessment['risk_level'], ['moderate', 'high', 'critical'])) {
                $alertId = self::storeAlert($tenantId, (int)$volunteerId, $assessment);
                if ($alertId) {
                    $alertsCreated++;
                }
            }

            $assessments[] = [
                'user_id' => $assessment['user_id'],
                'risk_score' => $assessment['risk_score'],
                'risk_level' => $assessment['risk_level'],
            ];
        }

        return [
            'total_assessed' => count($assessments),
            'alerts_created' => $alertsCreated,
            'risk_distribution' => self::getRiskDistribution($assessments),
        ];
    }

    /**
     * Get active wellbeing alerts for coordinators
     *
     * @return array Active alerts with user details
     */
    public static function getActiveAlerts(): array
    {
        $tenantId = TenantContext::getId();
        $db = Database::getConnection();

        $stmt = $db->prepare("
            SELECT wa.*, u.name as user_name, u.email as user_email, u.avatar_url as user_avatar,
                   u.skills as user_skills
            FROM vol_wellbeing_alerts wa
            JOIN users u ON wa.user_id = u.id
            WHERE wa.tenant_id = ? AND wa.status = 'active'
            ORDER BY
                CASE wa.risk_level WHEN 'critical' THEN 0 WHEN 'high' THEN 1 WHEN 'moderate' THEN 2 ELSE 3 END,
                wa.risk_score DESC
            LIMIT 50
        ");
        $stmt->execute([$tenantId]);
        $alerts = $stmt->fetchAll(\PDO::FETCH_ASSOC);

        return array_map(function ($a) {
            return [
                'id' => (int)$a['id'],
                'user' => [
                    'id' => (int)$a['user_id'],
                    'name' => $a['user_name'],
                    'email' => $a['user_email'],
                    'avatar_url' => $a['user_avatar'],
                    'skills' => $a['user_skills'],
                ],
                'risk_level' => $a['risk_level'],
                'risk_score' => (float)$a['risk_score'],
                'indicators' => json_decode($a['indicators'] ?? '{}', true) ?: [],
                'coordinator_notified' => (bool)$a['coordinator_notified'],
                'coordinator_notes' => $a['coordinator_notes'],
                'status' => $a['status'],
                'created_at' => $a['created_at'],
            ];
        }, $alerts);
    }

    /**
     * Acknowledge or resolve a wellbeing alert
     *
     * @param int $alertId Alert ID
     * @param string $action 'acknowledge', 'resolve', or 'dismiss'
     * @param string|null $notes Coordinator notes
     * @return bool Success
     */
    public static function updateAlert(int $alertId, string $action, ?string $notes = null): bool
    {
        self::$errors = [];

        if (!in_array($action, ['acknowledge', 'resolve', 'dismiss'])) {
            self::$errors[] = ['code' => 'VALIDATION_ERROR', 'message' => 'Action must be acknowledge, resolve, or dismiss'];
            return false;
        }

        $tenantId = TenantContext::getId();
        $db = Database::getConnection();

        $stmt = $db->prepare("SELECT id FROM vol_wellbeing_alerts WHERE id = ? AND tenant_id = ?");
        $stmt->execute([$alertId, $tenantId]);
        if (!$stmt->fetch()) {
            self::$errors[] = ['code' => 'NOT_FOUND', 'message' => 'Alert not found'];
            return false;
        }

        try {
            $status = match ($action) {
                'acknowledge' => 'acknowledged',
                'resolve' => 'resolved',
                'dismiss' => 'dismissed',
            };

            $sql = "UPDATE vol_wellbeing_alerts SET status = ?, coordinator_notified = 1";
            $params = [$status];

            if ($notes !== null) {
                $sql .= ", coordinator_notes = ?";
                $params[] = $notes;
            }

            if ($action === 'resolve') {
                $sql .= ", resolved_at = NOW()";
            }

            $sql .= " WHERE id = ? AND tenant_id = ?";
            $params[] = $alertId;
            $params[] = $tenantId;

            $stmt = $db->prepare($sql);
            $stmt->execute($params);

            return true;
        } catch (\Exception $e) {
            error_log("VolunteerWellbeingService::updateAlert error: " . $e->getMessage());
            self::$errors[] = ['code' => 'SERVER_ERROR', 'message' => 'Failed to update alert'];
            return false;
        }
    }

    // ========================================
    // INDICATOR CALCULATIONS
    // ========================================

    /**
     * Calculate shift frequency trend
     * Compares last 30 days vs previous 30 days
     */
    private static function calculateShiftFrequencyTrend(\PDO $db, int $userId): array
    {
        $tenantId = TenantContext::getId();
        $stmt = $db->prepare("
            SELECT
                (SELECT COUNT(*) FROM vol_applications
                 WHERE user_id = ? AND tenant_id = ? AND shift_id IS NOT NULL AND status = 'approved'
                 AND created_at >= DATE_SUB(NOW(), INTERVAL 30 DAY)) as recent_shifts,
                (SELECT COUNT(*) FROM vol_applications
                 WHERE user_id = ? AND tenant_id = ? AND shift_id IS NOT NULL AND status = 'approved'
                 AND created_at >= DATE_SUB(NOW(), INTERVAL 60 DAY)
                 AND created_at < DATE_SUB(NOW(), INTERVAL 30 DAY)) as previous_shifts
        ");
        $stmt->execute([$userId, $tenantId, $userId, $tenantId]);
        $result = $stmt->fetch(\PDO::FETCH_ASSOC);

        $recent = (int)$result['recent_shifts'];
        $previous = (int)$result['previous_shifts'];

        $risk = 0;
        $trend = 'stable';

        if ($previous > 0 && $recent < $previous) {
            $dropPercent = (($previous - $recent) / $previous) * 100;
            $risk = min(25, $dropPercent * 0.5);
            $trend = 'declining';
        } elseif ($previous > 0 && $recent > $previous) {
            $trend = 'increasing';
        } elseif ($previous === 0 && $recent === 0) {
            $risk = 10; // Some risk if no activity at all
            $trend = 'inactive';
        }

        return [
            'recent_count' => $recent,
            'previous_count' => $previous,
            'trend' => $trend,
            'risk_contribution' => round($risk, 1),
        ];
    }

    /**
     * Calculate cancellation rate (last 90 days)
     */
    private static function calculateCancellationRate(\PDO $db, int $userId): array
    {
        $tenantId = TenantContext::getId();
        // Count cancelled vs total shift signups
        $stmt = $db->prepare("
            SELECT
                COUNT(*) as total,
                SUM(CASE WHEN status = 'declined' OR (shift_id IS NULL AND status = 'approved') THEN 1 ELSE 0 END) as cancelled
            FROM vol_applications
            WHERE user_id = ? AND tenant_id = ?
            AND created_at >= DATE_SUB(NOW(), INTERVAL 90 DAY)
        ");
        $stmt->execute([$userId, $tenantId]);
        $result = $stmt->fetch(\PDO::FETCH_ASSOC);

        $total = (int)$result['total'];
        $cancelled = (int)$result['cancelled'];

        $rate = $total > 0 ? ($cancelled / $total) * 100 : 0;

        // High cancellation rate = higher risk
        $risk = 0;
        if ($rate > 50) {
            $risk = 20;
        } elseif ($rate > 30) {
            $risk = 12;
        } elseif ($rate > 15) {
            $risk = 5;
        }

        return [
            'total_signups' => $total,
            'cancellations' => $cancelled,
            'rate_percent' => round($rate, 1),
            'risk_contribution' => round($risk, 1),
        ];
    }

    /**
     * Calculate hours logged trend
     */
    private static function calculateHoursLoggedTrend(\PDO $db, int $userId): array
    {
        $tenantId = TenantContext::getId();
        $stmt = $db->prepare("
            SELECT
                COALESCE((SELECT SUM(hours) FROM vol_logs
                 WHERE user_id = ? AND tenant_id = ? AND status = 'approved'
                 AND date_logged >= DATE_SUB(NOW(), INTERVAL 30 DAY)), 0) as recent_hours,
                COALESCE((SELECT SUM(hours) FROM vol_logs
                 WHERE user_id = ? AND tenant_id = ? AND status = 'approved'
                 AND date_logged >= DATE_SUB(NOW(), INTERVAL 60 DAY)
                 AND date_logged < DATE_SUB(NOW(), INTERVAL 30 DAY)), 0) as previous_hours
        ");
        $stmt->execute([$userId, $tenantId, $userId, $tenantId]);
        $result = $stmt->fetch(\PDO::FETCH_ASSOC);

        $recent = (float)$result['recent_hours'];
        $previous = (float)$result['previous_hours'];

        $risk = 0;
        $trend = 'stable';

        if ($previous > 0 && $recent < $previous * 0.5) {
            $risk = 20; // Significant drop
            $trend = 'declining_significantly';
        } elseif ($previous > 0 && $recent < $previous) {
            $risk = 10;
            $trend = 'declining';
        } elseif ($previous > 0 && $recent >= $previous) {
            $trend = 'stable_or_increasing';
        } elseif ($previous === 0.0 && $recent === 0.0) {
            $risk = 5;
            $trend = 'inactive';
        }

        return [
            'recent_hours' => round($recent, 1),
            'previous_hours' => round($previous, 1),
            'trend' => $trend,
            'risk_contribution' => round($risk, 1),
        ];
    }

    /**
     * Calculate response time trend for alerts/invitations
     */
    private static function calculateResponseTimeTrend(\PDO $db, int $userId): array
    {
        // Check if tables exist
        try {
            $tenantId = TenantContext::getId();
            $stmt = $db->prepare("
                SELECT AVG(TIMESTAMPDIFF(HOUR, r.notified_at, r.responded_at)) as avg_response_hours
                FROM vol_emergency_alert_recipients r
                JOIN vol_emergency_alerts a ON r.alert_id = a.id AND a.tenant_id = ?
                WHERE r.user_id = ? AND r.responded_at IS NOT NULL
                AND r.notified_at >= DATE_SUB(NOW(), INTERVAL 90 DAY)
            ");
            $stmt->execute([$tenantId, $userId]);
            $result = $stmt->fetch(\PDO::FETCH_ASSOC);
            $avgHours = (float)($result['avg_response_hours'] ?? 0);
        } catch (\Throwable $e) {
            $avgHours = 0;
        }

        $risk = 0;
        if ($avgHours > 48) {
            $risk = 15;
        } elseif ($avgHours > 24) {
            $risk = 8;
        } elseif ($avgHours > 12) {
            $risk = 3;
        }

        return [
            'avg_response_hours' => round($avgHours, 1),
            'risk_contribution' => round($risk, 1),
        ];
    }

    /**
     * Calculate engagement gap (days since last activity)
     */
    private static function calculateEngagementGap(\PDO $db, int $userId): array
    {
        $tenantId = TenantContext::getId();
        $stmt = $db->prepare("
            SELECT MAX(date_logged) as last_hours_log,
                   (SELECT MAX(created_at) FROM vol_applications WHERE user_id = ? AND tenant_id = ?) as last_application
            FROM vol_logs
            WHERE user_id = ? AND tenant_id = ?
        ");
        $stmt->execute([$userId, $tenantId, $userId, $tenantId]);
        $result = $stmt->fetch(\PDO::FETCH_ASSOC);

        $lastActivity = null;
        if ($result['last_hours_log']) {
            $lastActivity = strtotime($result['last_hours_log']);
        }
        if ($result['last_application']) {
            $appTime = strtotime($result['last_application']);
            if (!$lastActivity || $appTime > $lastActivity) {
                $lastActivity = $appTime;
            }
        }

        $daysSince = $lastActivity ? (int)((time() - $lastActivity) / 86400) : 999;

        $risk = 0;
        if ($daysSince > 90) {
            $risk = 20;
        } elseif ($daysSince > 60) {
            $risk = 15;
        } elseif ($daysSince > 30) {
            $risk = 8;
        } elseif ($daysSince > 14) {
            $risk = 3;
        }

        return [
            'days_since_last_activity' => $daysSince,
            'risk_contribution' => round($risk, 1),
        ];
    }

    // ========================================
    // STORAGE
    // ========================================

    /**
     * Store a burnout risk alert
     */
    private static function storeAlert(int $tenantId, int $userId, array $assessment): ?int
    {
        $db = Database::getConnection();

        // Check for existing active alert for this user
        $stmt = $db->prepare("SELECT id FROM vol_wellbeing_alerts WHERE tenant_id = ? AND user_id = ? AND status = 'active'");
        $stmt->execute([$tenantId, $userId]);
        $existing = $stmt->fetch(\PDO::FETCH_ASSOC);

        try {
            if ($existing) {
                // Update existing alert
                $stmt = $db->prepare("
                    UPDATE vol_wellbeing_alerts
                    SET risk_level = ?, risk_score = ?, indicators = ?, updated_at = NOW()
                    WHERE id = ?
                ");
                $stmt->execute([
                    $assessment['risk_level'],
                    $assessment['risk_score'],
                    json_encode($assessment['indicators']),
                    $existing['id'],
                ]);
                return (int)$existing['id'];
            }

            // Create new alert
            $stmt = $db->prepare("
                INSERT INTO vol_wellbeing_alerts
                (tenant_id, user_id, risk_level, risk_score, indicators, status, created_at)
                VALUES (?, ?, ?, ?, ?, 'active', NOW())
            ");
            $stmt->execute([
                $tenantId,
                $userId,
                $assessment['risk_level'],
                $assessment['risk_score'],
                json_encode($assessment['indicators']),
            ]);

            return (int)$db->lastInsertId();
        } catch (\Exception $e) {
            error_log("VolunteerWellbeingService::storeAlert error: " . $e->getMessage());
            return null;
        }
    }

    /**
     * Get risk distribution summary
     */
    private static function getRiskDistribution(array $assessments): array
    {
        $dist = ['low' => 0, 'moderate' => 0, 'high' => 0, 'critical' => 0];
        foreach ($assessments as $a) {
            $dist[$a['risk_level']] = ($dist[$a['risk_level']] ?? 0) + 1;
        }
        return $dist;
    }
}
