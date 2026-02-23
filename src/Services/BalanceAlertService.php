<?php
// Copyright © 2024–2026 Jasper Ford
// SPDX-License-Identifier: AGPL-3.0-or-later
// Author: Jasper Ford
// See NOTICE file for attribution and acknowledgements.

namespace Nexus\Services;

use Nexus\Core\Database;
use Nexus\Core\TenantContext;
use Nexus\Models\OrgMember;
use Nexus\Models\OrgWallet;
use Nexus\Models\User;
use Nexus\Models\Notification;

/**
 * BalanceAlertService
 *
 * Monitors organization wallet balances and sends alerts when thresholds are reached.
 */
class BalanceAlertService
{
    // Default thresholds
    const DEFAULT_LOW_BALANCE_THRESHOLD = 50;
    const DEFAULT_CRITICAL_BALANCE_THRESHOLD = 10;

    /**
     * Check all organization wallets for low balance and send alerts
     * Should be run via cron job
     *
     * Only alerts for wallets that have been previously funded (have transaction history).
     * Wallets with balance 0 that were never funded are intentionally excluded.
     */
    public static function checkAllBalances()
    {
        $tenantId = TenantContext::getId();

        // Get org wallets that have ACTUALLY been funded (have at least one credit transaction).
        // This prevents alerting on wallets that were auto-created with 0 balance
        // but never used, which would spam all org admins.
        $wallets = Database::query(
            "SELECT ow.*, vo.name as org_name
             FROM org_wallets ow
             JOIN vol_organizations vo ON ow.organization_id = vo.id AND ow.tenant_id = vo.tenant_id
             WHERE ow.tenant_id = ? AND vo.status = 'approved'
             AND EXISTS (
                 SELECT 1 FROM org_transactions ot
                 WHERE ot.organization_id = ow.organization_id
                 AND ot.tenant_id = ow.tenant_id
                 AND ot.receiver_type = 'organization'
                 AND ot.receiver_id = ow.organization_id
             )",
            [$tenantId]
        )->fetchAll();

        $alertsSent = 0;

        foreach ($wallets as $wallet) {
            // Skip if alerts are disabled for this org
            if (!self::areAlertsEnabled($wallet['organization_id'])) {
                continue;
            }

            $result = self::checkBalance($wallet['organization_id'], $wallet['balance'], $wallet['org_name']);
            if ($result['alert_sent']) {
                $alertsSent++;
            }
        }

        return $alertsSent;
    }

    /**
     * Check a single organization's balance and alert if needed
     */
    public static function checkBalance($organizationId, $balance = null, $orgName = null)
    {
        $tenantId = TenantContext::getId();

        // Get current balance if not provided
        if ($balance === null) {
            $balance = OrgWallet::getBalance($organizationId);
        }

        // Get org name if not provided
        if ($orgName === null) {
            $org = Database::query(
                "SELECT name FROM vol_organizations WHERE id = ? AND tenant_id = ?",
                [$organizationId, $tenantId]
            )->fetch();
            $orgName = $org['name'] ?? 'Organization';
        }

        // Get thresholds for this org
        $thresholds = self::getThresholds($organizationId);

        // Check if already alerted today (prevent spam)
        $alertedToday = self::hasAlertedToday($organizationId);

        $alertType = null;
        $alertSent = false;

        // Critical balance (higher priority)
        if ($balance <= $thresholds['critical'] && !$alertedToday['critical']) {
            $alertType = 'critical';
            self::sendBalanceAlert($organizationId, $orgName, $balance, 'critical');
            self::recordAlert($organizationId, 'critical');
            $alertSent = true;
        }
        // Low balance
        elseif ($balance <= $thresholds['low'] && $balance > $thresholds['critical'] && !$alertedToday['low']) {
            $alertType = 'low';
            self::sendBalanceAlert($organizationId, $orgName, $balance, 'low');
            self::recordAlert($organizationId, 'low');
            $alertSent = true;
        }

        return [
            'balance' => $balance,
            'thresholds' => $thresholds,
            'alert_type' => $alertType,
            'alert_sent' => $alertSent
        ];
    }

    /**
     * Get thresholds for an organization
     */
    public static function getThresholds($organizationId)
    {
        $tenantId = TenantContext::getId();

        // Check for org-specific thresholds
        try {
            $thresholds = Database::query(
                "SELECT low_balance_threshold, critical_balance_threshold
                 FROM org_alert_settings
                 WHERE tenant_id = ? AND organization_id = ?",
                [$tenantId, $organizationId]
            )->fetch();

            if ($thresholds) {
                return [
                    'low' => (float) $thresholds['low_balance_threshold'],
                    'critical' => (float) $thresholds['critical_balance_threshold'],
                ];
            }
        } catch (\Exception $e) {
            // Table may not exist
        }

        // Return defaults
        return [
            'low' => self::DEFAULT_LOW_BALANCE_THRESHOLD,
            'critical' => self::DEFAULT_CRITICAL_BALANCE_THRESHOLD,
        ];
    }

    /**
     * Check if balance alerts are enabled for an organization.
     * Returns true by default if no settings exist (opt-out model for funded wallets).
     */
    public static function areAlertsEnabled($organizationId)
    {
        $tenantId = TenantContext::getId();

        try {
            $settings = Database::query(
                "SELECT alerts_enabled FROM org_alert_settings
                 WHERE tenant_id = ? AND organization_id = ?",
                [$tenantId, $organizationId]
            )->fetch();

            if ($settings) {
                return (bool) $settings['alerts_enabled'];
            }
        } catch (\Exception $e) {
            // Table may not exist — default to enabled
        }

        return true;
    }

    /**
     * Set custom thresholds for an organization
     */
    public static function setThresholds($organizationId, $lowThreshold, $criticalThreshold)
    {
        $tenantId = TenantContext::getId();

        // Create table if needed
        try {
            Database::query("SELECT 1 FROM org_alert_settings LIMIT 1");
        } catch (\Exception $e) {
            Database::query("
                CREATE TABLE IF NOT EXISTS org_alert_settings (
                    id INT AUTO_INCREMENT PRIMARY KEY,
                    tenant_id INT NOT NULL,
                    organization_id INT NOT NULL,
                    low_balance_threshold DECIMAL(10,2) DEFAULT 50,
                    critical_balance_threshold DECIMAL(10,2) DEFAULT 10,
                    alerts_enabled TINYINT(1) DEFAULT 1,
                    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
                    updated_at TIMESTAMP NULL ON UPDATE CURRENT_TIMESTAMP,
                    UNIQUE KEY unique_org_alerts (tenant_id, organization_id)
                ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci
            ");
        }

        Database::query(
            "INSERT INTO org_alert_settings
             (tenant_id, organization_id, low_balance_threshold, critical_balance_threshold)
             VALUES (?, ?, ?, ?)
             ON DUPLICATE KEY UPDATE
             low_balance_threshold = VALUES(low_balance_threshold),
             critical_balance_threshold = VALUES(critical_balance_threshold),
             updated_at = NOW()",
            [$tenantId, $organizationId, $lowThreshold, $criticalThreshold]
        );

        return true;
    }

    /**
     * Check if we already sent an alert today
     */
    private static function hasAlertedToday($organizationId)
    {
        $tenantId = TenantContext::getId();

        try {
            $alerts = Database::query(
                "SELECT alert_type FROM org_balance_alerts
                 WHERE tenant_id = ? AND organization_id = ? AND DATE(created_at) = CURDATE()",
                [$tenantId, $organizationId]
            )->fetchAll(\PDO::FETCH_COLUMN);

            return [
                'low' => in_array('low', $alerts),
                'critical' => in_array('critical', $alerts),
            ];
        } catch (\Exception $e) {
            return ['low' => false, 'critical' => false];
        }
    }

    /**
     * Record that an alert was sent
     */
    private static function recordAlert($organizationId, $alertType)
    {
        $tenantId = TenantContext::getId();

        // Create table if needed
        try {
            Database::query("SELECT 1 FROM org_balance_alerts LIMIT 1");
        } catch (\Exception $e) {
            Database::query("
                CREATE TABLE IF NOT EXISTS org_balance_alerts (
                    id INT AUTO_INCREMENT PRIMARY KEY,
                    tenant_id INT NOT NULL,
                    organization_id INT NOT NULL,
                    alert_type ENUM('low', 'critical') NOT NULL,
                    balance_at_alert DECIMAL(10,2),
                    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
                    INDEX idx_org_alerts (tenant_id, organization_id, created_at)
                ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci
            ");
        }

        $balance = OrgWallet::getBalance($organizationId);

        Database::query(
            "INSERT INTO org_balance_alerts (tenant_id, organization_id, alert_type, balance_at_alert)
             VALUES (?, ?, ?, ?)",
            [$tenantId, $organizationId, $alertType, $balance]
        );
    }

    /**
     * Send balance alert to organization owner (and admins for critical only).
     *
     * Financial alerts are sent to the org owner. For critical alerts,
     * admins are also notified. Regular members never receive these.
     */
    private static function sendBalanceAlert($organizationId, $orgName, $balance, $severity)
    {
        if ($severity === 'critical') {
            // Critical: notify owner + admins
            $recipients = OrgMember::getAdminsAndOwners($organizationId);
        } else {
            // Low: notify owner only
            $owner = OrgMember::getOwner($organizationId);
            $recipients = $owner ? [$owner] : [];
        }

        if (empty($recipients)) {
            error_log("BalanceAlertService: No recipients found for org $organizationId ($orgName)");
            return;
        }

        $basePath = TenantContext::getBasePath();
        $link = $basePath . "/organizations/{$organizationId}/wallet";

        $formattedBalance = number_format($balance, 2);

        if ($severity === 'critical') {
            $message = "Critical: {$orgName} wallet balance is very low ({$formattedBalance} credits)!";
            $subject = "Critical Balance Alert - {$orgName}";
            $body = "Your organization wallet balance has dropped to a critical level.<br><br>" .
                    "<strong>Current Balance:</strong> {$formattedBalance} credits<br><br>" .
                    "Please add funds to continue operations.";
        } else {
            $message = "Low balance: {$orgName} wallet has {$formattedBalance} credits remaining";
            $subject = "Low Balance Alert - {$orgName}";
            $body = "Your organization wallet balance is running low.<br><br>" .
                    "<strong>Current Balance:</strong> {$formattedBalance} credits<br><br>" .
                    "Consider adding funds soon.";
        }

        foreach ($recipients as $admin) {
            // Platform notification
            try {
                Notification::create($admin['user_id'], $message, $link, 'balance_alert');
            } catch (\Exception $e) {
                error_log("BalanceAlertService: Failed to create notification - " . $e->getMessage());
            }

            // Email notification
            $user = User::findById($admin['user_id']);
            if ($user) {
                $prefs = User::getNotificationPreferences($admin['user_id']);
                // Use org_admin preference for balance alerts
                if (!isset($prefs['email_org_admin']) || $prefs['email_org_admin']) {
                    self::sendAlertEmail($user, $subject, $body, $link, $orgName);
                }
            }
        }
    }

    /**
     * Send alert email
     */
    private static function sendAlertEmail($user, $subject, $body, $link, $orgName)
    {
        try {
            $tenant = TenantContext::get();
            $tenantName = $tenant['name'] ?? 'Project NEXUS';
            $fullUrl = \Nexus\Core\TenantContext::getFrontendUrl() . $link;

            $html = \Nexus\Core\EmailTemplate::render(
                $subject,
                "Action Required",
                $body,
                "View Wallet",
                $fullUrl,
                $tenantName
            );

            $mailer = new \Nexus\Core\Mailer();
            $mailer->send($user['email'], "{$subject} - {$tenantName}", $html);
        } catch (\Exception $e) {
            error_log("BalanceAlertService: Failed to send email - " . $e->getMessage());
        }
    }

    /**
     * Get balance status for display
     */
    public static function getBalanceStatus($organizationId)
    {
        $balance = OrgWallet::getBalance($organizationId);
        $thresholds = self::getThresholds($organizationId);

        if ($balance <= $thresholds['critical']) {
            return [
                'status' => 'critical',
                'label' => 'Critical',
                'color' => '#ef4444',
                'message' => 'Balance critically low!'
            ];
        } elseif ($balance <= $thresholds['low']) {
            return [
                'status' => 'low',
                'label' => 'Low',
                'color' => '#f59e0b',
                'message' => 'Balance running low'
            ];
        } else {
            return [
                'status' => 'healthy',
                'label' => 'Healthy',
                'color' => '#10b981',
                'message' => 'Balance is healthy'
            ];
        }
    }
}
