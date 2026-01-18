<?php
/**
 * Cron Job: Check Organization Wallet Balance Alerts
 *
 * Run daily via cron: 0 8 * * * php /path/to/scripts/cron/check_balance_alerts.php
 *
 * Checks all organization wallets for low/critical balance and sends alerts.
 */

// Bootstrap the application
require_once __DIR__ . '/../../httpdocs/bootstrap.php';

use Nexus\Core\TenantContext;
use Nexus\Core\Database;
use Nexus\Services\BalanceAlertService;

// Get all active tenants
$tenants = Database::query("SELECT id, name FROM tenants")->fetchAll();

$totalAlerts = 0;

foreach ($tenants as $tenant) {
    try {
        // Set tenant context
        TenantContext::set($tenant['id']);

        // Check all balances for this tenant
        $alertsSent = BalanceAlertService::checkAllBalances();
        $totalAlerts += $alertsSent;

        if ($alertsSent > 0) {
            echo "[{$tenant['name']}] Sent {$alertsSent} balance alerts\n";
        }

    } catch (\Exception $e) {
        error_log("Balance alert cron error for tenant {$tenant['id']}: " . $e->getMessage());
        echo "[{$tenant['name']}] Error: " . $e->getMessage() . "\n";
    }
}

echo "Completed. Total alerts sent: {$totalAlerts}\n";
