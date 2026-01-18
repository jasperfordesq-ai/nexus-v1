<?php
/**
 * Cron Job: Process Recurring Newsletters
 *
 * This script should be run every 15 minutes (or as needed) via cron:
 *
 * Example crontab entry (every 15 minutes):
 * */15 * * * * /usr/bin/php /path/to/scripts/cron/process_recurring_newsletters.php >> /var/log/newsletter_cron.log 2>&1
 *
 * Example crontab entry (every hour at :00):
 * 0 * * * * /usr/bin/php /path/to/scripts/cron/process_recurring_newsletters.php >> /var/log/newsletter_cron.log 2>&1
 */

// Prevent web access
if (php_sapi_name() !== 'cli') {
    http_response_code(403);
    die('This script can only be run from the command line.');
}

// Set script timeout (10 minutes max)
set_time_limit(600);

// Start timing
$startTime = microtime(true);
$startDate = date('Y-m-d H:i:s');

echo "========================================\n";
echo "Recurring Newsletter Processor\n";
echo "Started: {$startDate}\n";
echo "========================================\n\n";

// Load the application bootstrap
$basePath = dirname(dirname(__DIR__));
require_once $basePath . '/vendor/autoload.php';

// Load environment
$envFile = $basePath . '/.env';
if (file_exists($envFile)) {
    $lines = file($envFile, FILE_IGNORE_NEW_LINES | FILE_SKIP_EMPTY_LINES);
    foreach ($lines as $line) {
        if (strpos(trim($line), '#') === 0) continue;
        if (strpos($line, '=') !== false) {
            list($key, $value) = explode('=', $line, 2);
            $key = trim($key);
            $value = trim($value, " \t\n\r\0\x0B\"'");
            putenv("{$key}={$value}");
            $_ENV[$key] = $value;
        }
    }
}

// Load config
require_once $basePath . '/config/app.php';

// Initialize database connection
use Nexus\Core\Database;
use Nexus\Services\NewsletterService;

try {
    // Initialize database
    Database::init();
    echo "[OK] Database connection established\n";

    // Process recurring newsletters
    echo "\n[INFO] Checking for recurring newsletters due to send...\n";

    $processed = NewsletterService::processRecurring();

    if ($processed > 0) {
        echo "[SUCCESS] Processed {$processed} recurring newsletter(s)\n";
    } else {
        echo "[INFO] No recurring newsletters due at this time\n";
    }

} catch (\Exception $e) {
    echo "[ERROR] " . $e->getMessage() . "\n";
    echo "[TRACE] " . $e->getTraceAsString() . "\n";

    // Log to error log as well
    error_log("Recurring Newsletter Cron Error: " . $e->getMessage());

    // Exit with error code
    exit(1);
}

// Calculate execution time
$endTime = microtime(true);
$duration = round($endTime - $startTime, 2);

echo "\n========================================\n";
echo "Completed in {$duration} seconds\n";
echo "Ended: " . date('Y-m-d H:i:s') . "\n";
echo "========================================\n";

exit(0);
