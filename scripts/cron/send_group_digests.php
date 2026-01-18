<?php

/**
 * Group Weekly Digest Cron Job
 *
 * Sends weekly analytics digest emails to all group owners.
 *
 * Recommended crontab entry:
 * # Weekly digests on Mondays at 9:00 AM
 * 0 9 * * 1 php /path/to/scripts/cron/send_group_digests.php
 */

// Bootstrap the application
require_once dirname(__DIR__, 2) . '/bootstrap.php';

use Nexus\Services\GroupReportingService;
use Nexus\Core\TenantContext;

// Log function
function digestLog($message) {
    $timestamp = date('Y-m-d H:i:s');
    echo "[{$timestamp}] {$message}\n";
    error_log("[Group Digest Cron] {$message}");
}

digestLog("Starting group weekly digest cron job...");

try {
    // Get tenant ID (adjust if multi-tenant)
    $tenantId = TenantContext::getId();
    digestLog("Processing for tenant ID: {$tenantId}");

    // Send all weekly digests
    $stats = GroupReportingService::sendAllWeeklyDigests();

    digestLog("Digest sending complete!");
    digestLog("Total groups processed: {$stats['total_groups']}");
    digestLog("Successfully sent: {$stats['sent']}");
    digestLog("Failed: {$stats['failed']}");

    if ($stats['failed'] > 0) {
        digestLog("WARNING: {$stats['failed']} digest(s) failed to send. Check email configuration.");
    }

    // Success output
    echo "\n✅ Group Weekly Digest Cron Completed Successfully\n";
    echo "Total Groups: {$stats['total_groups']}\n";
    echo "Sent: {$stats['sent']}\n";
    echo "Failed: {$stats['failed']}\n";

    exit(0);

} catch (\Exception $e) {
    digestLog("ERROR: " . $e->getMessage());
    digestLog("Stack trace: " . $e->getTraceAsString());

    echo "\n❌ Group Weekly Digest Cron Failed\n";
    echo "Error: " . $e->getMessage() . "\n";

    exit(1);
}
