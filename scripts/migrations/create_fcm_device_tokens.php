<?php
/**
 * Migration: Create FCM Device Tokens Table
 *
 * Run this script on your live server to create the fcm_device_tokens table
 * for Firebase Cloud Messaging push notifications.
 *
 * Usage: php scripts/migrations/create_fcm_device_tokens.php
 */

require_once __DIR__ . '/../../vendor/autoload.php';

$dotenv = Dotenv\Dotenv::createImmutable(dirname(__DIR__, 2));
$dotenv->safeLoad();

use Nexus\Services\FCMPushService;

echo "Creating fcm_device_tokens table...\n";

try {
    FCMPushService::ensureTableExists();
    echo "Done! Table created successfully.\n";
} catch (Exception $e) {
    echo "Error: " . $e->getMessage() . "\n";
    exit(1);
}
