<?php
// Copyright © 2024–2026 Jasper Ford
// SPDX-License-Identifier: AGPL-3.0-or-later
// Author: Jasper Ford
// See NOTICE file for attribution and acknowledgements.

/**
 * Cron Job: Send Automated Event Reminders
 *
 * Sends reminder notifications to event attendees at two intervals:
 *   - 24 hours before the event (push + email)
 *   - 1 hour before the event (push only)
 *
 * Usage:
 *   docker exec nexus-php-app php /var/www/html/scripts/cron-event-reminders.php
 *
 * Recommended cron schedule (every 15 minutes):
 *   0,15,30,45 * * * * docker exec nexus-php-app php /var/www/html/scripts/cron-event-reminders.php
 */

require_once __DIR__ . '/../bootstrap.php';

use Nexus\Core\TenantContext;
use Nexus\Core\Database;
use Nexus\Services\EventReminderService;

echo "[" . date('Y-m-d H:i:s') . "] Starting event reminder processing\n";

// Process all active tenants
$tenants = Database::query(
    "SELECT id, name, slug FROM tenants WHERE id > 0 AND (is_active = 1 OR is_active IS NULL)"
)->fetchAll();

$totalSent = 0;
$totalErrors = 0;

foreach ($tenants as $tenant) {
    TenantContext::setById($tenant['id']);

    // Check if events feature is enabled for this tenant
    if (!TenantContext::hasFeature('events')) {
        continue;
    }

    $result = EventReminderService::sendDueReminders();
    $totalSent += $result['sent'];
    $totalErrors += $result['errors'];

    if ($result['sent'] > 0 || $result['errors'] > 0) {
        echo "  Tenant {$tenant['id']} ({$tenant['slug']}): sent={$result['sent']}, errors={$result['errors']}\n";
    }
}

// Cleanup old records (once per run)
$cleaned = EventReminderService::cleanupOldRecords();
if ($cleaned > 0) {
    echo "  Cleaned up {$cleaned} old reminder records\n";
}

echo "[" . date('Y-m-d H:i:s') . "] Event reminders complete. Total sent: {$totalSent}, errors: {$totalErrors}\n";
