<?php
// Copyright © 2024–2026 Jasper Ford
// SPDX-License-Identifier: AGPL-3.0-or-later
// Author: Jasper Ford
// See NOTICE file for attribution and acknowledgements.

/**
 * Cron Job: Clean Up Old Broker Message Copies
 *
 * Deletes reviewed, unflagged broker message copies older than the
 * configured retention period (default: 90 days).
 *
 * Usage:
 *   docker exec nexus-php-app php /var/www/html/scripts/cron-cleanup-broker-copies.php
 *
 * Recommended cron schedule (daily at 3am):
 *   0 3 * * * docker exec nexus-php-app php /var/www/html/scripts/cron-cleanup-broker-copies.php >> /var/log/nexus-cleanup.log 2>&1
 */

require_once __DIR__ . '/../bootstrap.php';

use Nexus\Core\TenantContext;
use Nexus\Core\Database;
use Nexus\Services\BrokerMessageVisibilityService;

echo "[" . date('Y-m-d H:i:s') . "] Starting broker message copy cleanup\n";

// Process all tenants
$tenants = Database::query("SELECT id, name, slug FROM tenants WHERE id > 0")->fetchAll();

$totalDeleted = 0;

foreach ($tenants as $tenant) {
    TenantContext::setById($tenant['id']);

    $deleted = BrokerMessageVisibilityService::cleanupOldCopies();
    $totalDeleted += $deleted;

    if ($deleted > 0) {
        echo "  Tenant {$tenant['id']} ({$tenant['slug']}): deleted {$deleted} old copies\n";
    }
}

echo "[" . date('Y-m-d H:i:s') . "] Cleanup complete. Total deleted: {$totalDeleted}\n";
