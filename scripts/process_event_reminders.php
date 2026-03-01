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
require_once $basePath . '/vendor/autoload.php';

// Load environment
if (file_exists($basePath . '/.env')) {
    $envFile = file_get_contents($basePath . '/.env');
    foreach (explode("\n", $envFile) as $line) {
        $line = trim($line);
        if ($line && $line[0] !== '#' && strpos($line, '=') !== false) {
            [$key, $value] = explode('=', $line, 2);
            $key = trim($key);
            $value = trim($value, " \t\n\r\0\x0B\"'");
            if (!getenv($key)) {
                putenv("{$key}={$value}");
                $_ENV[$key] = $value;
            }
        }
    }
}

// Initialize database connection
try {
    \Nexus\Core\Database::getConnection();
} catch (\Exception $e) {
    echo "[" . date('Y-m-d H:i:s') . "] ERROR: Database connection failed: " . $e->getMessage() . "\n";
    exit(1);
}

echo "[" . date('Y-m-d H:i:s') . "] Processing event reminders...\n";

try {
    $sent = \Nexus\Services\EventService::processPendingReminders();
    echo "[" . date('Y-m-d H:i:s') . "] Sent {$sent} reminder(s).\n";
} catch (\Exception $e) {
    echo "[" . date('Y-m-d H:i:s') . "] ERROR: " . $e->getMessage() . "\n";
    exit(1);
}

echo "[" . date('Y-m-d H:i:s') . "] Done.\n";
