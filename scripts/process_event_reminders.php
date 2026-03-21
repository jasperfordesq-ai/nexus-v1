<?php
// Copyright © 2024–2026 Jasper Ford
// SPDX-License-Identifier: AGPL-3.0-or-later
// Author: Jasper Ford
// See NOTICE file for attribution and acknowledgements.

/**
 * Event Reminders Cron Job
 *
 * Processes pending event reminders and sends notifications.
 * Should be run every minute via cron:
 *
 *   * * * * * php /opt/nexus-php/scripts/process_event_reminders.php >> /var/log/nexus/reminders.log 2>&1
 *
 * Or via Docker:
 *   docker exec nexus-php-app php /var/www/html/scripts/process_event_reminders.php
 */

// Bootstrap the application
$basePath = dirname(__DIR__);
require $basePath . '/vendor/autoload.php';
$app = require_once $basePath . '/bootstrap/app.php';
$app->make(Illuminate\Contracts\Console\Kernel::class)->bootstrap();

// Initialize database connection
try {
    \App\Core\Database::getConnection();
} catch (\Exception $e) {
    echo "[" . date('Y-m-d H:i:s') . "] ERROR: Database connection failed: " . $e->getMessage() . "\n";
    exit(1);
}

echo "[" . date('Y-m-d H:i:s') . "] Processing event reminders...\n";

try {
    $sent = \App\Services\EventService::processPendingReminders();
    echo "[" . date('Y-m-d H:i:s') . "] Sent {$sent} reminder(s).\n";
} catch (\Exception $e) {
    echo "[" . date('Y-m-d H:i:s') . "] ERROR: " . $e->getMessage() . "\n";
    exit(1);
}

echo "[" . date('Y-m-d H:i:s') . "] Done.\n";
