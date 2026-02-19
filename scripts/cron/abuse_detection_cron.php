<?php

/**
 * Abuse Detection Cron Jobs
 *
 * Run this script via cron to automatically detect potential timebanking abuse.
 *
 * Recommended crontab entries:
 *
 * # Run detection checks every hour
 * 0 * * * * php /path/to/scripts/cron/abuse_detection_cron.php detect
 *
 * # Daily summary report at 7:00 AM
 * 0 7 * * * php /path/to/scripts/cron/abuse_detection_cron.php daily_report
 *
 * # Weekly cleanup on Sundays at 2:00 AM
 * 0 2 * * 0 php /path/to/scripts/cron/abuse_detection_cron.php cleanup
 */

// Bootstrap the application (skip if already loaded via admin panel include)
if (!class_exists('Nexus\Core\Database', false)) {
    require_once dirname(__DIR__, 2) . '/bootstrap.php';
}

use Nexus\Core\Database;
use Nexus\Core\TenantContext;
use Nexus\Services\AbuseDetectionService;

// Get command argument - supports both CLI and internal (include) execution
$command = $GLOBALS['argv'][1] ?? $argv[1] ?? 'help';

// Log function (unique name + function_exists guard for safe re-inclusion)
if (!function_exists('abuseDetectionLog')) {
    function abuseDetectionLog($message) {
        $timestamp = date('Y-m-d H:i:s');
        echo "[{$timestamp}] {$message}\n";
        error_log("[Abuse Detection Cron] {$message}");
    }
}

// Process all tenants (unique name + function_exists guard for safe re-inclusion)
if (!function_exists('abuseDetectionForEachTenant')) {
    function abuseDetectionForEachTenant(callable $callback) {
        $tenants = Database::query("SELECT id, slug FROM tenants")->fetchAll();

        foreach ($tenants as $tenant) {
            try {
                TenantContext::setById($tenant['id']);
                abuseDetectionLog("Processing tenant: {$tenant['slug']} (ID: {$tenant['id']})");
                $callback($tenant);
            } catch (\Throwable $e) {
                abuseDetectionLog("Error processing tenant {$tenant['id']}: " . $e->getMessage());
            }
        }
    }
}

switch ($command) {
    case 'detect':
        abuseDetectionLog("Starting abuse detection checks...");

        abuseDetectionForEachTenant(function($tenant) {
            try {
                $results = AbuseDetectionService::runAllChecks();

                $totalAlerts = array_sum($results);

                if ($totalAlerts > 0) {
                    abuseDetectionLog("Tenant {$tenant['id']}: Created {$totalAlerts} new alerts");
                    abuseDetectionLog("  - Large transfers: {$results['large_transfer']}");
                    abuseDetectionLog("  - High velocity: {$results['high_velocity']}");
                    abuseDetectionLog("  - Circular transfers: {$results['circular_transfer']}");
                    abuseDetectionLog("  - Inactive high balance: {$results['inactive_high_balance']}");
                } else {
                    abuseDetectionLog("Tenant {$tenant['id']}: No suspicious activity detected");
                }
            } catch (\Throwable $e) {
                abuseDetectionLog("Error running detection for tenant {$tenant['id']}: " . $e->getMessage());
            }
        });

        abuseDetectionLog("Abuse detection completed.");
        break;

    case 'daily_report':
        abuseDetectionLog("Generating daily abuse report...");

        abuseDetectionForEachTenant(function($tenant) {
            try {
                $counts = AbuseDetectionService::getAlertCounts();
                $newAlerts = $counts['new'] ?? 0;
                $reviewingAlerts = $counts['reviewing'] ?? 0;

                if ($newAlerts > 0 || $reviewingAlerts > 0) {
                    abuseDetectionLog("Tenant {$tenant['id']}: {$newAlerts} new alerts, {$reviewingAlerts} under review");

                    // Get breakdown by type
                    $byType = AbuseDetectionService::getAlertCountsByType();
                    foreach ($byType as $type => $count) {
                        if ($count > 0) {
                            abuseDetectionLog("  - {$type}: {$count}");
                        }
                    }

                    // Get critical/high severity alerts
                    $criticalAlerts = Database::query(
                        "SELECT COUNT(*) FROM abuse_alerts
                         WHERE tenant_id = ? AND status = 'new' AND severity IN ('critical', 'high')",
                        [$tenant['id']]
                    )->fetchColumn();

                    if ($criticalAlerts > 0) {
                        abuseDetectionLog("  *** {$criticalAlerts} CRITICAL/HIGH severity alerts require attention! ***");
                    }
                } else {
                    abuseDetectionLog("Tenant {$tenant['id']}: No pending alerts");
                }
            } catch (\Throwable $e) {
                abuseDetectionLog("Error generating report for tenant {$tenant['id']}: " . $e->getMessage());
            }
        });

        abuseDetectionLog("Daily report completed.");
        break;

    case 'cleanup':
        abuseDetectionLog("Running abuse alert cleanup...");

        abuseDetectionForEachTenant(function($tenant) {
            try {
                // Archive old resolved/dismissed alerts (older than 90 days)
                $archived = Database::query(
                    "DELETE FROM abuse_alerts
                     WHERE tenant_id = ?
                     AND status IN ('resolved', 'dismissed')
                     AND resolved_at < DATE_SUB(NOW(), INTERVAL 90 DAY)",
                    [$tenant['id']]
                );

                if ($archived->rowCount() > 0) {
                    abuseDetectionLog("Tenant {$tenant['id']}: Archived {$archived->rowCount()} old alerts");
                }

                // Auto-dismiss low severity alerts older than 30 days with no activity
                $autoDismissed = Database::query(
                    "UPDATE abuse_alerts
                     SET status = 'dismissed',
                         resolved_at = NOW(),
                         resolution_notes = 'Auto-dismissed by cron (aged out)'
                     WHERE tenant_id = ?
                     AND status = 'new'
                     AND severity = 'low'
                     AND created_at < DATE_SUB(NOW(), INTERVAL 30 DAY)",
                    [$tenant['id']]
                );

                if ($autoDismissed->rowCount() > 0) {
                    abuseDetectionLog("Tenant {$tenant['id']}: Auto-dismissed {$autoDismissed->rowCount()} low-severity alerts");
                }
            } catch (\Throwable $e) {
                abuseDetectionLog("Error during cleanup for tenant {$tenant['id']}: " . $e->getMessage());
            }
        });

        abuseDetectionLog("Cleanup completed.");
        break;

    case 'stats':
        abuseDetectionLog("Abuse detection statistics...");

        abuseDetectionForEachTenant(function($tenant) {
            $counts = AbuseDetectionService::getAlertCounts();
            $byType = AbuseDetectionService::getAlertCountsByType();

            abuseDetectionLog("Tenant {$tenant['id']} Alert Summary:");
            abuseDetectionLog("  Status breakdown:");
            foreach ($counts as $status => $count) {
                abuseDetectionLog("    - {$status}: {$count}");
            }
            abuseDetectionLog("  Type breakdown:");
            foreach ($byType as $type => $count) {
                if ($count > 0) {
                    abuseDetectionLog("    - {$type}: {$count}");
                }
            }
        });

        abuseDetectionLog("Statistics completed.");
        break;

    case 'help':
    default:
        echo <<<HELP
Abuse Detection Cron Jobs

Usage: php abuse_detection_cron.php <command>

Available commands:
  detect       - Run all abuse detection checks and create alerts
  daily_report - Generate daily summary of pending alerts
  cleanup      - Archive old resolved alerts, auto-dismiss aged low-severity
  stats        - Display current alert statistics

Recommended cron schedule:
  0 * * * *   detect       (hourly)
  0 7 * * *   daily_report (daily at 7am)
  0 2 * * 0   cleanup      (weekly on Sunday at 2am)

Detection checks performed:
  - Large transfers (>50 credits in single transaction)
  - High velocity (>10 transactions per hour)
  - Circular transfers (A->B->A within 24h)
  - Inactive high balance (>10 credits, no activity 90+ days)

HELP;
        break;
}

// Use return instead of exit when included internally to allow the calling script to continue
if (defined('CRON_INTERNAL_RUN')) {
    return;
}
exit(0);
