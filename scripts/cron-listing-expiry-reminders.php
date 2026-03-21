<?php
// Copyright © 2024–2026 Jasper Ford
// SPDX-License-Identifier: AGPL-3.0-or-later
// Author: Jasper Ford
// See NOTICE file for attribution and acknowledgements.

/**
 * Cron Job: Send Listing Expiry Reminders
 *
 * Notifies listing owners when their listing is about to expire (3 days before).
 * Only affects listings with an `expires_at` date set.
 *
 * Usage:
 *   docker exec nexus-php-app php /var/www/html/scripts/cron-listing-expiry-reminders.php
 *
 * Recommended cron schedule (daily at 9am):
 *   0 9 * * * docker exec nexus-php-app php /var/www/html/scripts/cron-listing-expiry-reminders.php >> /var/log/nexus-listing-expiry.log 2>&1
 */

require __DIR__ . '/../vendor/autoload.php';
$app = require_once __DIR__ . '/../bootstrap/app.php';
$app->make(Illuminate\Contracts\Console\Kernel::class)->bootstrap();

use App\Core\TenantContext;
use Illuminate\Support\Facades\DB;
use App\Services\ListingExpiryReminderService;

echo "[" . date('Y-m-d H:i:s') . "] Starting listing expiry reminder processing\n";

// Process all active tenants
$tenants = array_map(fn($r) => (array) $r, DB::select(
    "SELECT id, name, slug FROM tenants WHERE id > 0 AND (is_active = 1 OR is_active IS NULL)"
));

$totalSent = 0;
$totalErrors = 0;

foreach ($tenants as $tenant) {
    TenantContext::setById($tenant['id']);

    // Check if listings feature is enabled (it's a core module, usually always on)
    // But check just in case
    if (!TenantContext::hasFeature('listings')) {
        // Listings are a core module — check via module config instead
        // Most tenants will have listings enabled, so this is a soft check
    }

    $result = ListingExpiryReminderService::sendDueReminders();
    $totalSent += $result['sent'];
    $totalErrors += $result['errors'];

    if ($result['sent'] > 0 || $result['errors'] > 0) {
        echo "  Tenant {$tenant['id']} ({$tenant['slug']}): sent={$result['sent']}, errors={$result['errors']}\n";
    }
}

// Cleanup old records (once per run)
$cleaned = ListingExpiryReminderService::cleanupOldRecords();
if ($cleaned > 0) {
    echo "  Cleaned up {$cleaned} old expiry reminder records\n";
}

// Also clean up old match notification records
try {
    $matchCleaned = \App\Services\MatchNotificationService::cleanupOldRecords();
    if ($matchCleaned > 0) {
        echo "  Cleaned up {$matchCleaned} old match notification records\n";
    }
} catch (\Exception $e) {
    // Non-critical
}

echo "[" . date('Y-m-d H:i:s') . "] Listing expiry reminders complete. Total sent: {$totalSent}, errors: {$totalErrors}\n";
