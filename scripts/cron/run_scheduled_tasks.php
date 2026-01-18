<?php
/**
 * Cron Job: Run All Scheduled Tasks
 *
 * This is a master cron script that runs all scheduled tasks.
 * Run this every minute and it will handle task scheduling internally.
 *
 * Crontab entry:
 * * * * * * /usr/bin/php /path/to/scripts/cron/run_scheduled_tasks.php >> /var/log/nexus_cron.log 2>&1
 */

// Prevent web access
if (php_sapi_name() !== 'cli') {
    http_response_code(403);
    die('This script can only be run from the command line.');
}

// Set script timeout
set_time_limit(300);

$startTime = microtime(true);
echo "[" . date('Y-m-d H:i:s') . "] Starting scheduled tasks runner...\n";

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

use Nexus\Core\Database;

try {
    Database::init();
} catch (\Exception $e) {
    echo "[ERROR] Database connection failed: " . $e->getMessage() . "\n";
    exit(1);
}

/**
 * Task Definitions
 * Each task has:
 * - name: Display name
 * - schedule: Cron-like schedule (minute patterns)
 * - callback: Function to execute
 */
$tasks = [
    [
        'name' => 'Process Recurring Newsletters',
        'schedule' => '*/15', // Every 15 minutes
        'callback' => function() {
            $processed = \Nexus\Services\NewsletterService::processRecurring();
            return "Processed {$processed} recurring newsletter(s)";
        }
    ],
    [
        'name' => 'Process Newsletter Queue',
        'schedule' => '*/5', // Every 5 minutes
        'callback' => function() {
            // Process any pending newsletter queues
            $sql = "SELECT DISTINCT newsletter_id FROM newsletter_queue WHERE status = 'pending' LIMIT 5";
            $pending = Database::query($sql)->fetchAll();
            $count = 0;
            foreach ($pending as $row) {
                try {
                    $newsletter = \Nexus\Models\Newsletter::find($row['newsletter_id']);
                    if ($newsletter && $newsletter['status'] === 'sending') {
                        \Nexus\Core\TenantContext::setById($newsletter['tenant_id']);
                        \Nexus\Services\NewsletterService::processQueue($row['newsletter_id'], 50);
                        $count++;
                    }
                } catch (\Exception $e) {
                    error_log("Error processing queue for newsletter {$row['newsletter_id']}: " . $e->getMessage());
                }
            }
            return "Processed {$count} newsletter queue(s)";
        }
    ],
    [
        'name' => 'Clean Old Sessions',
        'schedule' => '0', // Every hour at :00
        'callback' => function() {
            // Clean sessions older than 24 hours
            $expiry = date('Y-m-d H:i:s', strtotime('-24 hours'));
            try {
                $sql = "DELETE FROM sessions WHERE last_activity < ?";
                Database::query($sql, [$expiry]);
                return "Cleaned old sessions";
            } catch (\Exception $e) {
                return "Sessions table may not exist, skipped";
            }
        }
    ],
    [
        'name' => 'Clean Expired Tokens',
        'schedule' => '0', // Every hour at :00
        'callback' => function() {
            // Clean expired password reset tokens
            try {
                $sql = "UPDATE users SET reset_token = NULL, reset_token_expires = NULL
                        WHERE reset_token_expires < NOW()";
                Database::query($sql);
                return "Cleaned expired reset tokens";
            } catch (\Exception $e) {
                return "Skipped: " . $e->getMessage();
            }
        }
    ],
    [
        'name' => 'Process Hot Match Notifications',
        'schedule' => '*/30', // Every 30 minutes
        'callback' => function() {
            // Find new listings and notify matching users
            $processed = 0;
            $notified = 0;

            try {
                // Get all active tenants
                $tenants = Database::query("SELECT id FROM tenants WHERE status = 'active'")->fetchAll();

                foreach ($tenants as $tenant) {
                    \Nexus\Core\TenantContext::setById($tenant['id']);

                    // Find new listings in the last 30 minutes
                    $sql = "SELECT l.*, u.name as user_name, c.name as category_name
                            FROM listings l
                            JOIN users u ON l.user_id = u.id
                            LEFT JOIN categories c ON l.category_id = c.id
                            WHERE l.tenant_id = ?
                            AND l.status = 'active'
                            AND l.created_at > DATE_SUB(NOW(), INTERVAL 30 MINUTE)
                            LIMIT 20";

                    $newListings = Database::query($sql, [$tenant['id']])->fetchAll();

                    foreach ($newListings as $listing) {
                        // Find users who might want this listing
                        $oppositeType = $listing['type'] === 'offer' ? 'request' : 'offer';

                        $matchSql = "SELECT DISTINCT l2.user_id
                                     FROM listings l2
                                     WHERE l2.category_id = ?
                                     AND l2.type = ?
                                     AND l2.status = 'active'
                                     AND l2.user_id != ?
                                     AND l2.tenant_id = ?
                                     LIMIT 50";

                        $potentialMatches = Database::query($matchSql, [
                            $listing['category_id'],
                            $oppositeType,
                            $listing['user_id'],
                            $tenant['id']
                        ])->fetchAll();

                        foreach ($potentialMatches as $match) {
                            $count = \Nexus\Services\MatchingService::notifyNewMatches($match['user_id']);
                            $notified += $count;
                        }
                        $processed++;
                    }
                }
                return "Processed {$processed} listings, sent {$notified} notifications";
            } catch (\Exception $e) {
                return "Error: " . $e->getMessage();
            }
        }
    ],
    [
        'name' => 'Daily Match Digest',
        'schedule' => '0', // Every hour at :00 (check if 8am)
        'callback' => function() {
            $hour = (int)date('G');
            if ($hour !== 8) {
                return "Skipped (only runs at 8am)";
            }

            $processed = 0;
            try {
                // Get all active tenants
                $tenants = Database::query("SELECT id FROM tenants WHERE status = 'active'")->fetchAll();

                foreach ($tenants as $tenant) {
                    \Nexus\Core\TenantContext::setById($tenant['id']);

                    // Find users with daily digest preference
                    $sql = "SELECT DISTINCT u.id, u.name
                            FROM users u
                            LEFT JOIN match_preferences mp ON u.id = mp.user_id
                            WHERE u.tenant_id = ?
                            AND u.status = 'active'
                            AND (mp.notification_frequency = 'daily' OR mp.notification_frequency IS NULL)
                            AND u.id IN (SELECT DISTINCT user_id FROM listings WHERE status = 'active')
                            LIMIT 100";

                    $users = Database::query($sql, [$tenant['id']])->fetchAll();

                    foreach ($users as $user) {
                        $matches = \Nexus\Services\SmartMatchingEngine::findMatchesForUser($user['id'], ['limit' => 10]);
                        if (!empty($matches)) {
                            \Nexus\Services\NotificationDispatcher::dispatchMatchDigest($user['id'], $matches, 'daily');
                            $processed++;
                        }
                    }
                }
                return "Sent daily digest to {$processed} users";
            } catch (\Exception $e) {
                return "Error: " . $e->getMessage();
            }
        }
    ],
    [
        'name' => 'Weekly Match Digest',
        'schedule' => '0', // Every hour at :00 (check if Sunday 9am)
        'callback' => function() {
            $hour = (int)date('G');
            $dayOfWeek = (int)date('w'); // 0 = Sunday
            if ($dayOfWeek !== 0 || $hour !== 9) {
                return "Skipped (only runs Sunday at 9am)";
            }

            $processed = 0;
            try {
                // Get all active tenants
                $tenants = Database::query("SELECT id FROM tenants WHERE status = 'active'")->fetchAll();

                foreach ($tenants as $tenant) {
                    \Nexus\Core\TenantContext::setById($tenant['id']);

                    // Find users with weekly digest preference
                    $sql = "SELECT DISTINCT u.id, u.name
                            FROM users u
                            INNER JOIN match_preferences mp ON u.id = mp.user_id
                            WHERE u.tenant_id = ?
                            AND u.status = 'active'
                            AND mp.notification_frequency = 'weekly'
                            AND u.id IN (SELECT DISTINCT user_id FROM listings WHERE status = 'active')
                            LIMIT 100";

                    $users = Database::query($sql, [$tenant['id']])->fetchAll();

                    foreach ($users as $user) {
                        $matches = \Nexus\Services\SmartMatchingEngine::findMatchesForUser($user['id'], ['limit' => 15]);
                        if (!empty($matches)) {
                            \Nexus\Services\NotificationDispatcher::dispatchMatchDigest($user['id'], $matches, 'weekly');
                            $processed++;
                        }
                    }
                }
                return "Sent weekly digest to {$processed} users";
            } catch (\Exception $e) {
                return "Error: " . $e->getMessage();
            }
        }
    ],
    [
        'name' => 'Warm Match Cache',
        'schedule' => '30', // Every hour at :30
        'callback' => function() {
            $cached = 0;
            try {
                // Get all active tenants
                $tenants = Database::query("SELECT id FROM tenants WHERE status = 'active'")->fetchAll();

                foreach ($tenants as $tenant) {
                    \Nexus\Core\TenantContext::setById($tenant['id']);
                    $result = \Nexus\Services\SmartMatchingEngine::warmUpCache(20);
                    $cached += $result['cached'];
                }
                return "Cached {$cached} matches across all tenants";
            } catch (\Exception $e) {
                return "Error: " . $e->getMessage();
            }
        }
    ]
];

/**
 * Check if a task should run based on its schedule
 */
function shouldRun($schedule) {
    $minute = (int) date('i');

    // Every N minutes pattern (*/N)
    if (preg_match('/^\*\/(\d+)$/', $schedule, $matches)) {
        return ($minute % (int)$matches[1]) === 0;
    }

    // Specific minute (0-59)
    if (is_numeric($schedule)) {
        return $minute === (int)$schedule;
    }

    // Every minute (*)
    if ($schedule === '*') {
        return true;
    }

    return false;
}

// Run tasks
$tasksRun = 0;
foreach ($tasks as $task) {
    if (shouldRun($task['schedule'])) {
        echo "  [RUNNING] {$task['name']}... ";
        try {
            $result = $task['callback']();
            echo "OK - {$result}\n";
            $tasksRun++;
        } catch (\Exception $e) {
            echo "ERROR - " . $e->getMessage() . "\n";
            error_log("Scheduled task '{$task['name']}' failed: " . $e->getMessage());
        }
    }
}

$duration = round(microtime(true) - $startTime, 2);
echo "[" . date('Y-m-d H:i:s') . "] Completed. Ran {$tasksRun} task(s) in {$duration}s\n";

exit(0);
