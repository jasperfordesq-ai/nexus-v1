<?php
// Copyright © 2024–2026 Jasper Ford
// SPDX-License-Identifier: AGPL-3.0-or-later
// Author: Jasper Ford
// See NOTICE file for attribution and acknowledgements.

namespace Nexus\Services;

use Nexus\Core\Database;
use Nexus\Core\TenantContext;

/**
 * SocialValueService
 *
 * Comprehensive Social Return on Investment (SROI) calculator.
 * Maps volunteer/exchange hours to monetary equivalents using configurable
 * per-tenant rates. Tracks hours exchanged, active members, skills shared,
 * events held, and generates impact summary reports.
 *
 * All methods are tenant-scoped via TenantContext.
 */
class SocialValueService
{
    /**
     * Default SROI configuration values
     */
    private const DEFAULT_CURRENCY = 'GBP';
    private const DEFAULT_HOUR_VALUE = 15.00;
    private const DEFAULT_MULTIPLIER = 3.5;

    /**
     * Calculate comprehensive SROI for a tenant within a date range
     *
     * @param int $tenantId
     * @param array $dateRange ['from' => 'Y-m-d', 'to' => 'Y-m-d'] (optional, defaults to last 12 months)
     * @return array Complete SROI report data
     */
    public static function calculateSROI(int $tenantId, array $dateRange = []): array
    {
        $config = self::getConfig($tenantId);
        $from = $dateRange['from'] ?? date('Y-m-d', strtotime('-12 months'));
        $to = $dateRange['to'] ?? date('Y-m-d');

        $hoursData = self::getHoursExchanged($tenantId, $from, $to);
        $membersData = self::getActiveMembers($tenantId, $from, $to);
        $skillsData = self::getSkillsShared($tenantId, $from, $to);
        $eventsData = self::getEventsHeld($tenantId, $from, $to);

        $totalHours = (float) $hoursData['total_hours'];
        $hourValue = (float) $config['hour_value_amount'];
        $multiplier = (float) $config['social_multiplier'];

        $monetaryValue = $totalHours * $hourValue;
        $socialValue = $monetaryValue * $multiplier;

        return [
            'period' => [
                'from' => $from,
                'to' => $to,
            ],
            'config' => [
                'currency' => $config['hour_value_currency'],
                'hour_value' => $hourValue,
                'social_multiplier' => $multiplier,
                'reporting_period' => $config['reporting_period'],
            ],
            'hours' => $hoursData,
            'valuation' => [
                'monetary_value' => round($monetaryValue, 2),
                'social_value' => round($socialValue, 2),
                'sroi_ratio' => $monetaryValue > 0 ? round($socialValue / $monetaryValue, 2) : 0,
                'currency' => $config['hour_value_currency'],
            ],
            'members' => $membersData,
            'skills' => $skillsData,
            'events' => $eventsData,
            'summary' => self::generateSummary($totalHours, $monetaryValue, $socialValue, $membersData, $config),
        ];
    }

    /**
     * Get or create tenant SROI config from social_value_config table
     *
     * @param int $tenantId
     * @return array Config values
     */
    public static function getConfig(int $tenantId): array
    {
        try {
            $stmt = Database::query(
                "SELECT * FROM social_value_config WHERE tenant_id = ?",
                [$tenantId]
            );
            $config = $stmt->fetch();

            if ($config) {
                return [
                    'hour_value_currency' => $config['hour_value_currency'],
                    'hour_value_amount' => (float) $config['hour_value_amount'],
                    'social_multiplier' => (float) $config['social_multiplier'],
                    'reporting_period' => $config['reporting_period'],
                ];
            }
        } catch (\Exception $e) {
            // Table may not exist yet
        }

        return [
            'hour_value_currency' => self::DEFAULT_CURRENCY,
            'hour_value_amount' => self::DEFAULT_HOUR_VALUE,
            'social_multiplier' => self::DEFAULT_MULTIPLIER,
            'reporting_period' => 'annually',
        ];
    }

    /**
     * Save tenant SROI config (upsert)
     *
     * @param int $tenantId
     * @param array $config ['hour_value_currency', 'hour_value_amount', 'social_multiplier', 'reporting_period']
     * @return bool
     */
    public static function saveConfig(int $tenantId, array $config): bool
    {
        try {
            Database::query(
                "INSERT INTO social_value_config (tenant_id, hour_value_currency, hour_value_amount, social_multiplier, reporting_period)
                 VALUES (?, ?, ?, ?, ?)
                 ON DUPLICATE KEY UPDATE
                    hour_value_currency = VALUES(hour_value_currency),
                    hour_value_amount = VALUES(hour_value_amount),
                    social_multiplier = VALUES(social_multiplier),
                    reporting_period = VALUES(reporting_period)",
                [
                    $tenantId,
                    $config['hour_value_currency'] ?? self::DEFAULT_CURRENCY,
                    $config['hour_value_amount'] ?? self::DEFAULT_HOUR_VALUE,
                    $config['social_multiplier'] ?? self::DEFAULT_MULTIPLIER,
                    $config['reporting_period'] ?? 'annually',
                ]
            );
            return true;
        } catch (\Exception $e) {
            error_log("SocialValueService::saveConfig failed: " . $e->getMessage());
            return false;
        }
    }

    /**
     * Get total hours exchanged within a date range
     */
    private static function getHoursExchanged(int $tenantId, string $from, string $to): array
    {
        $stmt = Database::query(
            "SELECT
                COALESCE(SUM(amount), 0) as total_hours,
                COUNT(*) as total_transactions,
                COUNT(DISTINCT sender_id) as unique_givers,
                COUNT(DISTINCT receiver_id) as unique_receivers,
                COALESCE(AVG(amount), 0) as avg_hours_per_transaction
             FROM transactions
             WHERE tenant_id = ?
               AND created_at >= ?
               AND created_at <= ?
               AND status = 'completed'",
            [$tenantId, $from . ' 00:00:00', $to . ' 23:59:59']
        );
        $data = $stmt->fetch();

        // Monthly breakdown for charting
        $monthly = Database::query(
            "SELECT
                DATE_FORMAT(created_at, '%Y-%m') as month,
                COALESCE(SUM(amount), 0) as hours,
                COUNT(*) as transactions
             FROM transactions
             WHERE tenant_id = ?
               AND created_at >= ?
               AND created_at <= ?
               AND status = 'completed'
             GROUP BY DATE_FORMAT(created_at, '%Y-%m')
             ORDER BY month ASC",
            [$tenantId, $from . ' 00:00:00', $to . ' 23:59:59']
        )->fetchAll();

        return [
            'total_hours' => round((float) $data['total_hours'], 1),
            'total_transactions' => (int) $data['total_transactions'],
            'unique_givers' => (int) $data['unique_givers'],
            'unique_receivers' => (int) $data['unique_receivers'],
            'avg_hours_per_transaction' => round((float) $data['avg_hours_per_transaction'], 2),
            'monthly' => array_map(function ($row) {
                return [
                    'month' => $row['month'],
                    'hours' => round((float) $row['hours'], 1),
                    'transactions' => (int) $row['transactions'],
                ];
            }, $monthly),
        ];
    }

    /**
     * Get active member metrics within a date range
     */
    private static function getActiveMembers(int $tenantId, string $from, string $to): array
    {
        // Total registered members
        $totalStmt = Database::query(
            "SELECT COUNT(*) as total FROM users WHERE tenant_id = ? AND status = 'active'",
            [$tenantId]
        );
        $total = (int) $totalStmt->fetch()['total'];

        // Members who traded (participated in transactions) in the period
        $activeStmt = Database::query(
            "SELECT COUNT(DISTINCT user_id) as active_count FROM (
                SELECT sender_id as user_id FROM transactions WHERE tenant_id = ? AND created_at >= ? AND created_at <= ? AND status = 'completed'
                UNION
                SELECT receiver_id as user_id FROM transactions WHERE tenant_id = ? AND created_at >= ? AND created_at <= ? AND status = 'completed'
            ) active_users",
            [$tenantId, $from . ' 00:00:00', $to . ' 23:59:59', $tenantId, $from . ' 00:00:00', $to . ' 23:59:59']
        );
        $activeTraders = (int) $activeStmt->fetch()['active_count'];

        // New members in period
        $newStmt = Database::query(
            "SELECT COUNT(*) as new_count FROM users WHERE tenant_id = ? AND created_at >= ? AND created_at <= ?",
            [$tenantId, $from . ' 00:00:00', $to . ' 23:59:59']
        );
        $newMembers = (int) $newStmt->fetch()['new_count'];

        // Members who logged in during period
        $loggedInStmt = Database::query(
            "SELECT COUNT(*) as login_count FROM users WHERE tenant_id = ? AND status = 'active' AND last_login_at >= ? AND last_login_at <= ?",
            [$tenantId, $from . ' 00:00:00', $to . ' 23:59:59']
        );
        $loggedIn = (int) $loggedInStmt->fetch()['login_count'];

        return [
            'total_registered' => $total,
            'active_traders' => $activeTraders,
            'participation_rate' => $total > 0 ? round($activeTraders / $total, 3) : 0,
            'new_members' => $newMembers,
            'logged_in' => $loggedIn,
        ];
    }

    /**
     * Get skills shared metrics
     */
    private static function getSkillsShared(int $tenantId, string $from, string $to): array
    {
        // Count unique skills offered via listings
        try {
            $listingsStmt = Database::query(
                "SELECT COUNT(DISTINCT category_id) as unique_categories,
                        COUNT(*) as total_listings
                 FROM listings
                 WHERE tenant_id = ? AND status = 'active' AND created_at >= ? AND created_at <= ?",
                [$tenantId, $from . ' 00:00:00', $to . ' 23:59:59']
            );
            $listings = $listingsStmt->fetch();
        } catch (\Exception $e) {
            $listings = ['unique_categories' => 0, 'total_listings' => 0];
        }

        // Count from user_skills table
        try {
            $skillsStmt = Database::query(
                "SELECT COUNT(DISTINCT skill_name) as unique_skills,
                        SUM(CASE WHEN is_offering = 1 THEN 1 ELSE 0 END) as skills_offered,
                        SUM(CASE WHEN is_requesting = 1 THEN 1 ELSE 0 END) as skills_requested
                 FROM user_skills
                 WHERE tenant_id = ?",
                [$tenantId]
            );
            $skills = $skillsStmt->fetch();
        } catch (\Exception $e) {
            $skills = ['unique_skills' => 0, 'skills_offered' => 0, 'skills_requested' => 0];
        }

        return [
            'unique_categories' => (int) ($listings['unique_categories'] ?? 0),
            'total_listings' => (int) ($listings['total_listings'] ?? 0),
            'unique_skills' => (int) ($skills['unique_skills'] ?? 0),
            'skills_offered' => (int) ($skills['skills_offered'] ?? 0),
            'skills_requested' => (int) ($skills['skills_requested'] ?? 0),
        ];
    }

    /**
     * Get events held metrics
     */
    private static function getEventsHeld(int $tenantId, string $from, string $to): array
    {
        try {
            $eventsStmt = Database::query(
                "SELECT COUNT(*) as total_events,
                        COUNT(DISTINCT user_id) as unique_organizers
                 FROM events
                 WHERE tenant_id = ? AND start_time >= ? AND start_time <= ?",
                [$tenantId, $from . ' 00:00:00', $to . ' 23:59:59']
            );
            $events = $eventsStmt->fetch();

            // Total RSVPs/attendees
            $rsvpStmt = Database::query(
                "SELECT COUNT(DISTINCT er.user_id) as total_attendees
                 FROM event_rsvps er
                 JOIN events e ON er.event_id = e.id
                 WHERE er.tenant_id = ? AND e.start_time >= ? AND e.start_time <= ? AND er.status = 'going'",
                [$tenantId, $from . ' 00:00:00', $to . ' 23:59:59']
            );
            $rsvp = $rsvpStmt->fetch();
        } catch (\Exception $e) {
            return [
                'total_events' => 0,
                'unique_organizers' => 0,
                'total_attendees' => 0,
            ];
        }

        return [
            'total_events' => (int) ($events['total_events'] ?? 0),
            'unique_organizers' => (int) ($events['unique_organizers'] ?? 0),
            'total_attendees' => (int) ($rsvp['total_attendees'] ?? 0),
        ];
    }

    /**
     * Generate human-readable impact summary
     */
    private static function generateSummary(
        float $totalHours,
        float $monetaryValue,
        float $socialValue,
        array $membersData,
        array $config
    ): string {
        $currency = $config['hour_value_currency'];
        $formattedMonetary = number_format($monetaryValue, 2);
        $formattedSocial = number_format($socialValue, 2);

        return sprintf(
            'During this period, %d active community members exchanged %.1f hours of service, '
            . 'valued at %s %s in direct economic impact. '
            . 'When accounting for secondary social benefits (multiplier: %.1fx), '
            . 'the estimated social value reaches %s %s. '
            . 'The community welcomed %d new members and maintained a %.1f%% participation rate.',
            $membersData['active_traders'],
            $totalHours,
            $currency,
            $formattedMonetary,
            $config['social_multiplier'],
            $currency,
            $formattedSocial,
            $membersData['new_members'],
            $membersData['participation_rate'] * 100
        );
    }
}
