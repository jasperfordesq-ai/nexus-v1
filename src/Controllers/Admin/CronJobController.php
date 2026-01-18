<?php

namespace Nexus\Controllers\Admin;

use Nexus\Core\Database;
use Nexus\Core\View;
use Nexus\Core\Csrf;
use Nexus\Core\TenantContext;
use Nexus\Core\Env;
use Nexus\Core\Mailer;

/**
 * Cron Job Administration Controller
 *
 * Provides a comprehensive admin interface for managing all cron jobs
 * on the platform with manual triggers, logs, statistics, and setup instructions.
 */
class CronJobController
{
    /**
     * Ensure user has admin access
     */
    private function requireAdmin()
    {
        if (session_status() === PHP_SESSION_NONE) {
            session_start();
        }

        if (!isset($_SESSION['user_id'])) {
            header('Location: ' . TenantContext::getBasePath() . '/login');
            exit;
        }

        $role = $_SESSION['user_role'] ?? '';
        $isAdmin = in_array($role, ['admin', 'tenant_admin']);
        $isSuper = !empty($_SESSION['is_super_admin']);
        $isAdminSession = !empty($_SESSION['is_admin']);

        if (!$isAdmin && !$isSuper && !$isAdminSession) {
            http_response_code(403);
            echo 'Access Denied: Administrator privileges required.';
            exit;
        }
    }

    /**
     * Ensure database tables exist
     */
    private function ensureTables(): void
    {
        try {
            // Cron logs table
            Database::query("
                CREATE TABLE IF NOT EXISTS cron_logs (
                    id INT AUTO_INCREMENT PRIMARY KEY,
                    job_id VARCHAR(100) NOT NULL,
                    status ENUM('success', 'error', 'running') DEFAULT 'running',
                    output TEXT,
                    duration_seconds DECIMAL(10,2),
                    executed_at DATETIME DEFAULT CURRENT_TIMESTAMP,
                    executed_by INT,
                    tenant_id INT,
                    INDEX idx_job_id (job_id),
                    INDEX idx_executed_at (executed_at),
                    INDEX idx_status (status)
                )
            ");

            // Cron job settings table
            Database::query("
                CREATE TABLE IF NOT EXISTS cron_job_settings (
                    id INT AUTO_INCREMENT PRIMARY KEY,
                    job_id VARCHAR(100) NOT NULL UNIQUE,
                    is_enabled TINYINT(1) DEFAULT 1,
                    custom_schedule VARCHAR(50),
                    notify_on_failure TINYINT(1) DEFAULT 0,
                    notify_emails TEXT,
                    max_retries INT DEFAULT 0,
                    timeout_seconds INT DEFAULT 300,
                    created_at DATETIME DEFAULT CURRENT_TIMESTAMP,
                    updated_at DATETIME DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
                    INDEX idx_job_id (job_id)
                )
            ");

            // Cron settings (global)
            Database::query("
                CREATE TABLE IF NOT EXISTS cron_settings (
                    id INT AUTO_INCREMENT PRIMARY KEY,
                    setting_key VARCHAR(100) NOT NULL UNIQUE,
                    setting_value TEXT,
                    updated_at DATETIME DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP
                )
            ");

            // Ensure notification_queue has sent_at column (may be missing in older installs)
            try {
                Database::query("ALTER TABLE notification_queue ADD COLUMN IF NOT EXISTS sent_at DATETIME NULL AFTER status");
            } catch (\Exception $e) {
                // Column likely already exists, ignore
            }
        } catch (\Exception $e) {
            error_log("Failed to create cron tables: " . $e->getMessage());
        }
    }

    /**
     * Get all defined cron jobs with their configurations
     */
    private function getCronJobs(): array
    {
        $baseUrl = Env::get('APP_URL', 'https://yourdomain.com');
        $cronKey = Env::get('CRON_KEY', 'your-secure-cron-key');

        return [
            // Core Notification Jobs
            [
                'id' => 'daily-digest',
                'name' => 'Daily Digest',
                'description' => 'Sends daily notification digest emails to users who opted for daily frequency.',
                'endpoint' => '/cron/daily-digest',
                'frequency' => 'Daily at 8:00 AM',
                'cron_expression' => '0 8 * * *',
                'category' => 'notifications',
                'priority' => 'high',
                'estimated_duration' => '1-5 minutes',
                'dependencies' => ['SMTP configured', 'Users with daily digest preference'],
            ],
            [
                'id' => 'weekly-digest',
                'name' => 'Weekly Digest',
                'description' => 'Sends weekly notification digest emails (typically on Fridays at 5 PM).',
                'endpoint' => '/cron/weekly-digest',
                'frequency' => 'Weekly (Friday 5:00 PM)',
                'cron_expression' => '0 17 * * 5',
                'category' => 'notifications',
                'priority' => 'high',
                'estimated_duration' => '2-10 minutes',
                'dependencies' => ['SMTP configured', 'Users with weekly digest preference'],
            ],
            [
                'id' => 'process-queue',
                'name' => 'Instant Notification Queue',
                'description' => 'Processes the instant notification queue, sending pending notifications immediately.',
                'endpoint' => '/cron/process-queue',
                'frequency' => 'Every 1-2 minutes',
                'cron_expression' => '*/2 * * * *',
                'category' => 'notifications',
                'priority' => 'critical',
                'estimated_duration' => '30 seconds - 2 minutes',
                'dependencies' => ['SMTP configured'],
            ],

            // Newsletter Jobs
            [
                'id' => 'process-newsletters',
                'name' => 'Process Scheduled Newsletters',
                'description' => 'Checks for newsletters scheduled to be sent and initiates their sending process.',
                'endpoint' => '/cron/process-newsletters',
                'frequency' => 'Every 5 minutes',
                'cron_expression' => '*/5 * * * *',
                'category' => 'newsletters',
                'priority' => 'high',
                'estimated_duration' => '1-3 minutes',
                'dependencies' => ['SMTP configured', 'Newsletter module enabled'],
            ],
            [
                'id' => 'process-recurring',
                'name' => 'Process Recurring Newsletters',
                'description' => 'Handles recurring/automated newsletters (e.g., weekly community updates).',
                'endpoint' => '/cron/process-recurring',
                'frequency' => 'Every 15 minutes',
                'cron_expression' => '*/15 * * * *',
                'category' => 'newsletters',
                'priority' => 'medium',
                'estimated_duration' => '2-5 minutes',
                'dependencies' => ['SMTP configured', 'Recurring newsletters configured'],
            ],
            [
                'id' => 'process-newsletter-queue',
                'name' => 'Newsletter Queue Processor',
                'description' => 'Processes the newsletter sending queue for large sends, sending in batches to avoid timeouts.',
                'endpoint' => '/cron/process-newsletter-queue',
                'frequency' => 'Every 2-5 minutes',
                'cron_expression' => '*/3 * * * *',
                'category' => 'newsletters',
                'priority' => 'high',
                'estimated_duration' => '1-5 minutes per batch',
                'dependencies' => ['SMTP configured', 'Active newsletter sends'],
            ],

            // Smart Matching Jobs
            [
                'id' => 'match-digest-daily',
                'name' => 'Daily Match Digest',
                'description' => 'Sends daily match recommendations to users who opted for daily match notifications.',
                'endpoint' => '/cron/match-digest-daily',
                'frequency' => 'Daily at 9:00 AM',
                'cron_expression' => '0 9 * * *',
                'category' => 'matching',
                'priority' => 'medium',
                'estimated_duration' => '2-10 minutes',
                'dependencies' => ['Smart matching enabled', 'SMTP configured'],
            ],
            [
                'id' => 'match-digest-weekly',
                'name' => 'Weekly Match Digest',
                'description' => 'Sends weekly match recommendations summary to users.',
                'endpoint' => '/cron/match-digest-weekly',
                'frequency' => 'Weekly (Monday 9:00 AM)',
                'cron_expression' => '0 9 * * 1',
                'category' => 'matching',
                'priority' => 'medium',
                'estimated_duration' => '5-15 minutes',
                'dependencies' => ['Smart matching enabled', 'SMTP configured'],
            ],
            [
                'id' => 'notify-hot-matches',
                'name' => 'Hot Match Notifications',
                'description' => 'Notifies users of new high-scoring matches based on recently created listings.',
                'endpoint' => '/cron/notify-hot-matches',
                'frequency' => 'Every hour',
                'cron_expression' => '0 * * * *',
                'category' => 'matching',
                'priority' => 'medium',
                'estimated_duration' => '1-5 minutes',
                'dependencies' => ['Smart matching enabled', 'Active listings'],
            ],

            // Geocoding
            [
                'id' => 'geocode-batch',
                'name' => 'Batch Geocoding',
                'description' => 'Geocodes users and listings that are missing latitude/longitude coordinates for distance-based features.',
                'endpoint' => '/cron/geocode-batch',
                'frequency' => 'Every 30 minutes',
                'cron_expression' => '*/30 * * * *',
                'category' => 'geocoding',
                'priority' => 'low',
                'estimated_duration' => '1-10 minutes',
                'dependencies' => ['Mapbox API key configured'],
            ],

            // Maintenance
            [
                'id' => 'cleanup',
                'name' => 'System Cleanup',
                'description' => 'Cleans expired tokens, old notification queue entries, suppression list entries, and tracking data.',
                'endpoint' => '/cron/cleanup',
                'frequency' => 'Daily at midnight',
                'cron_expression' => '0 0 * * *',
                'category' => 'maintenance',
                'priority' => 'medium',
                'estimated_duration' => '1-3 minutes',
                'dependencies' => [],
            ],

            // Master Cron
            [
                'id' => 'run-all',
                'name' => 'Master Cron Runner',
                'description' => 'A single endpoint that runs all appropriate cron tasks based on the current time. Useful for hosts with limited cron job slots.',
                'endpoint' => '/cron/run-all',
                'frequency' => 'Every minute',
                'cron_expression' => '* * * * *',
                'category' => 'master',
                'priority' => 'critical',
                'estimated_duration' => '1-15 minutes (varies)',
                'dependencies' => ['All dependencies for individual jobs'],
            ],

            // Gamification Jobs (from gamification_cron.php)
            [
                'id' => 'gamification-daily',
                'name' => 'Gamification Daily Tasks',
                'description' => 'Processes streak resets, daily bonuses, and badge checks for recently active users.',
                'endpoint' => '/admin/cron-jobs/run/gamification-daily',
                'frequency' => 'Daily at 3:00 AM',
                'cron_expression' => '0 3 * * *',
                'category' => 'gamification',
                'priority' => 'medium',
                'estimated_duration' => '1-5 minutes',
                'dependencies' => ['Gamification module enabled'],
                'script_path' => 'scripts/cron/gamification_cron.php daily',
            ],
            [
                'id' => 'gamification-weekly-digest',
                'name' => 'Gamification Weekly Digest',
                'description' => 'Sends weekly progress email digests to users who earned XP or badges.',
                'endpoint' => '/admin/cron-jobs/run/gamification-weekly-digest',
                'frequency' => 'Weekly (Monday 4:00 AM)',
                'cron_expression' => '0 4 * * 1',
                'category' => 'gamification',
                'priority' => 'medium',
                'estimated_duration' => '2-10 minutes',
                'dependencies' => ['Gamification module enabled', 'SMTP configured'],
                'script_path' => 'scripts/cron/gamification_cron.php weekly_digest',
            ],
            [
                'id' => 'gamification-campaigns',
                'name' => 'Process Achievement Campaigns',
                'description' => 'Processes recurring achievement campaigns and awards badges/XP to qualifying users.',
                'endpoint' => '/admin/cron-jobs/run/gamification-campaigns',
                'frequency' => 'Every hour',
                'cron_expression' => '0 * * * *',
                'category' => 'gamification',
                'priority' => 'medium',
                'estimated_duration' => '1-5 minutes',
                'dependencies' => ['Gamification module enabled', 'Active campaigns'],
                'script_path' => 'scripts/cron/gamification_cron.php campaigns',
            ],
            [
                'id' => 'gamification-leaderboard',
                'name' => 'Leaderboard Snapshot',
                'description' => 'Creates daily leaderboard snapshots and finalizes ended seasons with rewards.',
                'endpoint' => '/admin/cron-jobs/run/gamification-leaderboard',
                'frequency' => 'Daily at midnight',
                'cron_expression' => '0 0 * * *',
                'category' => 'gamification',
                'priority' => 'low',
                'estimated_duration' => '2-5 minutes',
                'dependencies' => ['Gamification module enabled'],
                'script_path' => 'scripts/cron/gamification_cron.php leaderboard_snapshot',
            ],
            [
                'id' => 'gamification-challenges',
                'name' => 'Check Challenge Expirations',
                'description' => 'Expires completed challenges and updates friend challenge statuses.',
                'endpoint' => '/admin/cron-jobs/run/gamification-challenges',
                'frequency' => 'Every hour (at :30)',
                'cron_expression' => '30 * * * *',
                'category' => 'gamification',
                'priority' => 'low',
                'estimated_duration' => '1-2 minutes',
                'dependencies' => ['Gamification module enabled'],
                'script_path' => 'scripts/cron/gamification_cron.php check_challenges',
            ],

            // Group Management
            [
                'id' => 'update-featured-groups',
                'name' => 'Update Featured Groups',
                'description' => 'Automatically updates featured groups based on smart ranking algorithms (member count, engagement score, geographic diversity).',
                'endpoint' => '/admin/cron/update-featured-groups',
                'frequency' => 'Daily at 8:00 AM',
                'cron_expression' => '0 8 * * *',
                'category' => 'groups',
                'priority' => 'medium',
                'estimated_duration' => '30 seconds - 2 minutes',
                'dependencies' => ['Groups module enabled', 'Active group memberships'],
            ],
            [
                'id' => 'group-weekly-digest',
                'name' => 'Group Weekly Digests',
                'description' => 'Sends weekly analytics digest emails to all group owners with member growth, engagement stats, and top contributors.',
                'endpoint' => '/admin/cron-jobs/run/group-weekly-digest',
                'frequency' => 'Weekly (Monday 9:00 AM)',
                'cron_expression' => '0 9 * * 1',
                'category' => 'groups',
                'priority' => 'medium',
                'estimated_duration' => '2-10 minutes',
                'dependencies' => ['Groups module enabled', 'SMTP configured', 'Group owners'],
                'script_path' => 'scripts/cron/send_group_digests.php',
            ],

            // Abuse Detection
            [
                'id' => 'abuse-detection',
                'name' => 'Timebanking Abuse Detection',
                'description' => 'Scans transactions for potential abuse patterns and generates alerts for admin review.',
                'endpoint' => '/admin/cron-jobs/run/abuse-detection',
                'frequency' => 'Daily at 2:00 AM',
                'cron_expression' => '0 2 * * *',
                'category' => 'security',
                'priority' => 'high',
                'estimated_duration' => '5-15 minutes',
                'dependencies' => ['Timebanking module enabled'],
                'script_path' => 'scripts/cron/abuse_detection_cron.php',
            ],
        ];
    }

    /**
     * Get category metadata
     */
    private function getCategories(): array
    {
        return [
            'notifications' => [
                'name' => 'Notifications',
                'icon' => 'fa-bell',
                'color' => '#3b82f6',
                'description' => 'Email digests and notification processing',
            ],
            'newsletters' => [
                'name' => 'Newsletters',
                'icon' => 'fa-envelope-open-text',
                'color' => '#8b5cf6',
                'description' => 'Newsletter scheduling and sending',
            ],
            'matching' => [
                'name' => 'Smart Matching',
                'icon' => 'fa-handshake',
                'color' => '#10b981',
                'description' => 'Match recommendations and notifications',
            ],
            'geocoding' => [
                'name' => 'Geocoding',
                'icon' => 'fa-map-marker-alt',
                'color' => '#f59e0b',
                'description' => 'Location coordinate processing',
            ],
            'maintenance' => [
                'name' => 'Maintenance',
                'icon' => 'fa-broom',
                'color' => '#6b7280',
                'description' => 'System cleanup and optimization',
            ],
            'master' => [
                'name' => 'Master Cron',
                'icon' => 'fa-clock',
                'color' => '#ef4444',
                'description' => 'Combined cron runner',
            ],
            'gamification' => [
                'name' => 'Gamification',
                'icon' => 'fa-trophy',
                'color' => '#a855f7',
                'description' => 'Achievements, badges, and rewards',
            ],
            'security' => [
                'name' => 'Security',
                'icon' => 'fa-shield-alt',
                'color' => '#dc2626',
                'description' => 'Abuse detection and security scans',
            ],
            'groups' => [
                'name' => 'Groups',
                'icon' => 'fa-users',
                'color' => '#0ea5e9',
                'description' => 'Group management and analytics',
            ],
        ];
    }

    /**
     * Get job statistics from logs
     */
    private function getJobStats(): array
    {
        $stats = [];

        try {
            // Get last run and success/failure counts for each job
            $results = Database::query("
                SELECT
                    job_id,
                    MAX(executed_at) as last_run,
                    SUM(CASE WHEN status = 'success' THEN 1 ELSE 0 END) as success_count,
                    SUM(CASE WHEN status = 'error' THEN 1 ELSE 0 END) as error_count,
                    COUNT(*) as total_runs,
                    AVG(duration_seconds) as avg_duration
                FROM cron_logs
                WHERE executed_at > DATE_SUB(NOW(), INTERVAL 30 DAY)
                GROUP BY job_id
            ")->fetchAll();

            foreach ($results as $row) {
                $stats[$row['job_id']] = [
                    'last_run' => $row['last_run'],
                    'success_count' => (int)$row['success_count'],
                    'error_count' => (int)$row['error_count'],
                    'total_runs' => (int)$row['total_runs'],
                    'avg_duration' => round((float)$row['avg_duration'], 2),
                    'success_rate' => $row['total_runs'] > 0
                        ? round(($row['success_count'] / $row['total_runs']) * 100, 1)
                        : 0,
                ];
            }

            // Get last status for each job
            $lastStatus = Database::query("
                SELECT cl1.job_id, cl1.status, cl1.executed_at
                FROM cron_logs cl1
                INNER JOIN (
                    SELECT job_id, MAX(executed_at) as max_date
                    FROM cron_logs
                    GROUP BY job_id
                ) cl2 ON cl1.job_id = cl2.job_id AND cl1.executed_at = cl2.max_date
            ")->fetchAll();

            foreach ($lastStatus as $row) {
                if (isset($stats[$row['job_id']])) {
                    $stats[$row['job_id']]['last_status'] = $row['status'];
                }
            }
        } catch (\Exception $e) {
            // Table may not exist yet
        }

        return $stats;
    }

    /**
     * Get job settings (enabled/disabled, notifications, etc.)
     */
    private function getJobSettings(): array
    {
        $settings = [];

        try {
            $results = Database::query("SELECT * FROM cron_job_settings")->fetchAll();
            foreach ($results as $row) {
                $settings[$row['job_id']] = $row;
            }
        } catch (\Exception $e) {
            // Table may not exist yet
        }

        return $settings;
    }

    /**
     * Get global cron settings
     */
    private function getGlobalSettings(): array
    {
        $defaults = [
            'failure_notification_enabled' => '0',
            'failure_notification_emails' => '',
            'failure_notification_threshold' => '3',
            'log_retention_days' => '30',
            'timezone' => 'UTC',
        ];

        try {
            $results = Database::query("SELECT setting_key, setting_value FROM cron_settings")->fetchAll();
            foreach ($results as $row) {
                $defaults[$row['setting_key']] = $row['setting_value'];
            }
        } catch (\Exception $e) {
            // Table may not exist yet
        }

        return $defaults;
    }

    /**
     * Calculate next run time from cron expression
     */
    private function getNextRunTime(string $cronExpression): ?string
    {
        try {
            $parts = explode(' ', $cronExpression);
            if (count($parts) !== 5) {
                return null;
            }

            [$minute, $hour, $dayOfMonth, $month, $dayOfWeek] = $parts;

            $now = new \DateTime();
            $next = clone $now;

            // Simple calculation for common patterns
            if ($minute === '*' && $hour === '*') {
                // Every minute
                $next->modify('+1 minute');
                return $next->format('Y-m-d H:i');
            }

            if (strpos($minute, '*/') === 0) {
                // Every N minutes
                $interval = (int)substr($minute, 2);
                $currentMinute = (int)$now->format('i');
                $nextMinute = ceil($currentMinute / $interval) * $interval;
                if ($nextMinute >= 60) {
                    $next->modify('+1 hour');
                    $next->setTime((int)$next->format('H'), 0);
                } else {
                    $next->setTime((int)$now->format('H'), (int)$nextMinute);
                }
                if ($next <= $now) {
                    $next->modify("+{$interval} minutes");
                }
                return $next->format('Y-m-d H:i');
            }

            if ($minute !== '*' && $hour !== '*' && $dayOfWeek !== '*') {
                // Weekly job
                $targetDay = (int)$dayOfWeek;
                $currentDay = (int)$now->format('w');
                $daysUntil = ($targetDay - $currentDay + 7) % 7;

                if ($daysUntil === 0) {
                    // Same day, check time
                    $targetTime = sprintf('%02d:%02d', $hour, $minute);
                    if ($now->format('H:i') >= $targetTime) {
                        $daysUntil = 7;
                    }
                }

                $next->modify("+{$daysUntil} days");
                $next->setTime((int)$hour, (int)$minute);
                return $next->format('Y-m-d H:i');
            }

            if ($minute !== '*' && $hour !== '*') {
                // Daily job
                $next->setTime((int)$hour, (int)$minute);
                if ($next <= $now) {
                    $next->modify('+1 day');
                }
                return $next->format('Y-m-d H:i');
            }

            if ($minute === '0' && $hour === '*') {
                // Hourly
                $next->modify('+1 hour');
                $next->setTime((int)$next->format('H'), 0);
                return $next->format('Y-m-d H:i');
            }

            if ($minute === '30' && $hour === '*') {
                // Hourly at :30
                $currentMinute = (int)$now->format('i');
                if ($currentMinute >= 30) {
                    $next->modify('+1 hour');
                }
                $next->setTime((int)$next->format('H'), 30);
                return $next->format('Y-m-d H:i');
            }

            return null;
        } catch (\Exception $e) {
            return null;
        }
    }

    /**
     * Get recent cron execution logs
     */
    private function getCronLogs(int $limit = 50): array
    {
        try {
            // Cast to int to ensure proper binding (avoid quoted numeric literals)
            $limit = (int)$limit;
            $logs = Database::query(
                "SELECT * FROM cron_logs ORDER BY executed_at DESC LIMIT " . $limit
            )->fetchAll();
            return $logs ?: [];
        } catch (\Exception $e) {
            return [];
        }
    }

    /**
     * Log a cron execution
     */
    private function logCronExecution(string $jobId, string $status, string $output, float $duration): void
    {
        try {
            $this->ensureTables();

            Database::query(
                "INSERT INTO cron_logs (job_id, status, output, duration_seconds, executed_by, tenant_id) VALUES (?, ?, ?, ?, ?, ?)",
                [
                    $jobId,
                    $status,
                    substr($output, 0, 65000),
                    $duration,
                    $_SESSION['user_id'] ?? null,
                    TenantContext::getId()
                ]
            );

            // Check if we should send failure notification
            if ($status === 'error') {
                $this->checkAndSendFailureNotification($jobId, $output);
            }
        } catch (\Exception $e) {
            error_log("Failed to log cron execution: " . $e->getMessage());
        }
    }

    /**
     * Check and send failure notification if configured
     */
    private function checkAndSendFailureNotification(string $jobId, string $output): void
    {
        try {
            $globalSettings = $this->getGlobalSettings();
            $jobSettings = $this->getJobSettings();

            // Check job-specific setting first
            $jobSetting = $jobSettings[$jobId] ?? null;
            $shouldNotify = false;
            $emails = [];

            if ($jobSetting && $jobSetting['notify_on_failure']) {
                $shouldNotify = true;
                $emails = array_filter(array_map('trim', explode(',', $jobSetting['notify_emails'] ?? '')));
            }

            // Fall back to global setting
            if (empty($emails) && $globalSettings['failure_notification_enabled'] === '1') {
                $shouldNotify = true;
                $emails = array_filter(array_map('trim', explode(',', $globalSettings['failure_notification_emails'])));
            }

            if (!$shouldNotify || empty($emails)) {
                return;
            }

            // Check failure threshold
            $threshold = (int)($globalSettings['failure_notification_threshold'] ?? 1);
            $recentFailures = Database::query(
                "SELECT COUNT(*) as c FROM cron_logs WHERE job_id = ? AND status = 'error' AND executed_at > DATE_SUB(NOW(), INTERVAL 1 HOUR)",
                [$jobId]
            )->fetch()['c'] ?? 0;

            if ($recentFailures < $threshold) {
                return;
            }

            // Send notification
            $cronJobs = $this->getCronJobs();
            $jobName = $jobId;
            foreach ($cronJobs as $j) {
                if ($j['id'] === $jobId) {
                    $jobName = $j['name'];
                    break;
                }
            }

            $siteName = Env::get('APP_NAME', 'Nexus');
            $subject = "[{$siteName}] Cron Job Failed: {$jobName}";
            $body = $this->generateFailureEmailHtml($jobId, $jobName, $output, $recentFailures);

            $mailer = new Mailer();
            foreach ($emails as $email) {
                $mailer->send($email, $subject, $body);
            }
        } catch (\Exception $e) {
            error_log("Failed to send cron failure notification: " . $e->getMessage());
        }
    }

    /**
     * Generate failure notification email HTML
     */
    private function generateFailureEmailHtml(string $jobId, string $jobName, string $output, int $failureCount): string
    {
        $appUrl = Env::get('APP_URL', '');
        $time = date('Y-m-d H:i:s');

        return "
        <html>
        <body style='font-family: -apple-system, BlinkMacSystemFont, \"Segoe UI\", Roboto, sans-serif; line-height: 1.6; color: #333; max-width: 600px; margin: 0 auto;'>
            <div style='background: linear-gradient(135deg, #ef4444, #dc2626); padding: 30px; text-align: center;'>
                <h1 style='color: white; margin: 0; font-size: 24px;'>⚠️ Cron Job Failed</h1>
            </div>
            <div style='padding: 30px; background: #fff; border: 1px solid #e5e7eb;'>
                <table style='width: 100%; border-collapse: collapse; margin-bottom: 20px;'>
                    <tr>
                        <td style='padding: 10px; border-bottom: 1px solid #e5e7eb; font-weight: 600; width: 140px;'>Job Name:</td>
                        <td style='padding: 10px; border-bottom: 1px solid #e5e7eb;'>{$jobName}</td>
                    </tr>
                    <tr>
                        <td style='padding: 10px; border-bottom: 1px solid #e5e7eb; font-weight: 600;'>Job ID:</td>
                        <td style='padding: 10px; border-bottom: 1px solid #e5e7eb;'><code>{$jobId}</code></td>
                    </tr>
                    <tr>
                        <td style='padding: 10px; border-bottom: 1px solid #e5e7eb; font-weight: 600;'>Failed At:</td>
                        <td style='padding: 10px; border-bottom: 1px solid #e5e7eb;'>{$time}</td>
                    </tr>
                    <tr>
                        <td style='padding: 10px; border-bottom: 1px solid #e5e7eb; font-weight: 600;'>Recent Failures:</td>
                        <td style='padding: 10px; border-bottom: 1px solid #e5e7eb;'><span style='background: #fef2f2; color: #dc2626; padding: 2px 8px; border-radius: 4px;'>{$failureCount} in last hour</span></td>
                    </tr>
                </table>

                <h3 style='margin-top: 25px; color: #1e293b;'>Error Output:</h3>
                <div style='background: #f8fafc; border: 1px solid #e2e8f0; border-radius: 8px; padding: 15px; font-family: monospace; font-size: 13px; white-space: pre-wrap; max-height: 300px; overflow-y: auto;'>" . htmlspecialchars(substr($output, 0, 2000)) . "</div>

                <div style='margin-top: 25px; text-align: center;'>
                    <a href='{$appUrl}/admin/cron-jobs/logs' style='display: inline-block; background: #6366f1; color: white; padding: 12px 24px; text-decoration: none; border-radius: 8px; font-weight: 600;'>View Cron Logs</a>
                </div>
            </div>
            <div style='padding: 20px; text-align: center; color: #64748b; font-size: 12px;'>
                This is an automated message from your cron job monitoring system.
            </div>
        </body>
        </html>
        ";
    }

    /**
     * Main cron jobs index page
     */
    public function index()
    {
        $this->requireAdmin();
        $this->ensureTables();

        $cronJobs = $this->getCronJobs();
        $categories = $this->getCategories();
        $logs = $this->getCronLogs(20);
        $stats = $this->getJobStats();
        $jobSettings = $this->getJobSettings();
        $globalSettings = $this->getGlobalSettings();
        $cronKey = Env::get('CRON_KEY', '');
        $appUrl = Env::get('APP_URL', '');

        // Enrich jobs with stats, settings, and next run time
        foreach ($cronJobs as &$job) {
            $jobId = $job['id'];
            $job['stats'] = $stats[$jobId] ?? null;
            $job['settings'] = $jobSettings[$jobId] ?? ['is_enabled' => 1];
            $job['next_run'] = $this->getNextRunTime($job['cron_expression']);
        }
        unset($job);

        // Group jobs by category
        $jobsByCategory = [];
        foreach ($cronJobs as $job) {
            $cat = $job['category'];
            if (!isset($jobsByCategory[$cat])) {
                $jobsByCategory[$cat] = [];
            }
            $jobsByCategory[$cat][] = $job;
        }

        // Calculate overall stats
        $overallStats = [
            'total_jobs' => count($cronJobs),
            'enabled_jobs' => 0,
            'total_runs_24h' => 0,
            'success_rate_24h' => 0,
            'failures_24h' => 0,
        ];

        foreach ($cronJobs as $job) {
            if (($job['settings']['is_enabled'] ?? 1) == 1) {
                $overallStats['enabled_jobs']++;
            }
        }

        try {
            $last24h = Database::query("
                SELECT
                    COUNT(*) as total,
                    SUM(CASE WHEN status = 'success' THEN 1 ELSE 0 END) as success,
                    SUM(CASE WHEN status = 'error' THEN 1 ELSE 0 END) as errors
                FROM cron_logs
                WHERE executed_at > DATE_SUB(NOW(), INTERVAL 24 HOUR)
            ")->fetch();

            $overallStats['total_runs_24h'] = (int)($last24h['total'] ?? 0);
            $overallStats['failures_24h'] = (int)($last24h['errors'] ?? 0);
            $overallStats['success_rate_24h'] = $overallStats['total_runs_24h'] > 0
                ? round((($last24h['success'] ?? 0) / $overallStats['total_runs_24h']) * 100, 1)
                : 100;
        } catch (\Exception $e) {
            // Tables may not exist
        }

        View::render('admin/cron-jobs/index', [
            'cronJobs' => $cronJobs,
            'jobsByCategory' => $jobsByCategory,
            'categories' => $categories,
            'logs' => $logs,
            'stats' => $stats,
            'overallStats' => $overallStats,
            'globalSettings' => $globalSettings,
            'cronKey' => $cronKey,
            'appUrl' => $appUrl,
        ]);
    }

    /**
     * Toggle job enabled/disabled status
     */
    public function toggle(string $jobId)
    {
        $this->requireAdmin();
        Csrf::verifyOrDie();
        $this->ensureTables();

        $enabled = ($_POST['enabled'] ?? '1') === '1' ? 1 : 0;

        try {
            // Check if setting exists
            $existing = Database::query(
                "SELECT id FROM cron_job_settings WHERE job_id = ?",
                [$jobId]
            )->fetch();

            if ($existing) {
                Database::query(
                    "UPDATE cron_job_settings SET is_enabled = ? WHERE job_id = ?",
                    [$enabled, $jobId]
                );
            } else {
                Database::query(
                    "INSERT INTO cron_job_settings (job_id, is_enabled) VALUES (?, ?)",
                    [$jobId, $enabled]
                );
            }

            $_SESSION['flash_success'] = $enabled
                ? "Job '{$jobId}' has been enabled."
                : "Job '{$jobId}' has been disabled.";
        } catch (\Exception $e) {
            $_SESSION['flash_error'] = "Failed to update job status: " . $e->getMessage();
        }

        header('Location: ' . TenantContext::getBasePath() . '/admin/cron-jobs');
        exit;
    }

    /**
     * Run a specific cron job manually
     */
    public function run(string $jobId)
    {
        $this->requireAdmin();
        Csrf::verifyOrDie();
        $this->ensureTables();

        $cronJobs = $this->getCronJobs();
        $job = null;

        foreach ($cronJobs as $j) {
            if ($j['id'] === $jobId) {
                $job = $j;
                break;
            }
        }

        if (!$job) {
            $_SESSION['flash_error'] = 'Unknown cron job: ' . htmlspecialchars($jobId);
            header('Location: ' . TenantContext::getBasePath() . '/admin/cron-jobs');
            exit;
        }

        // Check if job is enabled
        $jobSettings = $this->getJobSettings();
        if (isset($jobSettings[$jobId]) && !$jobSettings[$jobId]['is_enabled']) {
            $_SESSION['flash_error'] = "Cannot run disabled job: {$job['name']}. Enable it first.";
            header('Location: ' . TenantContext::getBasePath() . '/admin/cron-jobs');
            exit;
        }

        $startTime = microtime(true);
        $output = '';
        $status = 'success';
        $logId = null;

        // Insert a "running" log entry FIRST so we have a record even if it crashes
        try {
            Database::query(
                "INSERT INTO cron_logs (job_id, status, output, duration_seconds, executed_by, tenant_id) VALUES (?, 'running', 'Job started...', 0, ?, ?)",
                [$jobId, $_SESSION['user_id'] ?? null, TenantContext::getId()]
            );
            $logId = Database::getConnection()->lastInsertId();
        } catch (\Exception $e) {
            error_log("Failed to create initial cron log: " . $e->getMessage());
        }

        // Store job info for shutdown handler
        $GLOBALS['_cron_job_info'] = [
            'job_id' => $jobId,
            'log_id' => $logId,
            'start_time' => $startTime,
            'user_id' => $_SESSION['user_id'] ?? null,
            'tenant_id' => TenantContext::getId(),
        ];

        // Register shutdown handler to catch fatal errors
        register_shutdown_function(function() {
            $error = error_get_last();
            if ($error && in_array($error['type'], [E_ERROR, E_PARSE, E_CORE_ERROR, E_COMPILE_ERROR])) {
                $jobInfo = $GLOBALS['_cron_job_info'] ?? null;
                if ($jobInfo) {
                    $duration = microtime(true) - $jobInfo['start_time'];
                    $errorMsg = "Fatal Error: {$error['message']} in {$error['file']} on line {$error['line']}";
                    try {
                        if ($jobInfo['log_id']) {
                            // Update existing log entry
                            Database::query(
                                "UPDATE cron_logs SET status = 'error', output = ?, duration_seconds = ? WHERE id = ?",
                                [$errorMsg, $duration, $jobInfo['log_id']]
                            );
                        } else {
                            // Insert new log entry
                            Database::query(
                                "INSERT INTO cron_logs (job_id, status, output, duration_seconds, executed_by, tenant_id) VALUES (?, ?, ?, ?, ?, ?)",
                                [$jobInfo['job_id'], 'error', $errorMsg, $duration, $jobInfo['user_id'], $jobInfo['tenant_id']]
                            );
                        }
                    } catch (\Exception $e) {
                        error_log("Failed to log cron fatal error: " . $e->getMessage());
                    }
                }
            }
        });

        try {
            if (isset($job['script_path'])) {
                $parts = explode(' ', $job['script_path']);
                $script = dirname(__DIR__, 3) . '/' . $parts[0];
                $args = $parts[1] ?? '';

                if (file_exists($script)) {
                    // Save original argv/argc
                    $oldArgv = $GLOBALS['argv'] ?? [];
                    $oldArgc = $GLOBALS['argc'] ?? 0;

                    // Set new argv/argc for the included script
                    $newArgv = ['gamification_cron.php', $args];
                    $GLOBALS['argv'] = $newArgv;
                    $GLOBALS['argc'] = count($newArgv);

                    // Also set $argv and $argc as local variables that will be in scope
                    $argv = $newArgv;
                    $argc = count($newArgv);

                    // Define constant so bootstrap.php allows internal execution
                    if (!defined('CRON_INTERNAL_RUN')) {
                        define('CRON_INTERNAL_RUN', true);
                    }

                    // Set up error handler to catch non-fatal errors
                    $errorMessages = [];
                    set_error_handler(function($errno, $errstr, $errfile, $errline) use (&$errorMessages) {
                        $errorMessages[] = "Error [$errno]: $errstr in $errfile on line $errline";
                        return true;
                    });

                    ob_start();
                    try {
                        include $script;
                        $output = ob_get_clean();
                    } catch (\Throwable $e) {
                        $output = ob_get_clean();
                        $output .= "\n\nError: " . $e->getMessage() . "\n" . $e->getTraceAsString();
                        $status = 'error';
                    }

                    restore_error_handler();

                    if (!empty($errorMessages)) {
                        $output .= "\n\nErrors captured:\n" . implode("\n", $errorMessages);
                        $status = 'error';
                    }

                    // Restore original argv/argc
                    $GLOBALS['argv'] = $oldArgv;
                    $GLOBALS['argc'] = $oldArgc;
                } else {
                    $output = "Script not found: {$script}";
                    $status = 'error';
                }
            } else {
                // Define constant so CronController skips its own logging (we handle logging here)
                if (!defined('CRON_INTERNAL_RUN')) {
                    define('CRON_INTERNAL_RUN', true);
                }

                $cronKey = Env::get('CRON_KEY', 'default_insecure_key_change_me');
                $controller = new \Nexus\Controllers\CronController();

                $methodMap = [
                    'daily-digest' => 'dailyDigest',
                    'weekly-digest' => 'weeklyDigest',
                    'process-queue' => 'runInstantQueue',
                    'process-newsletters' => 'processNewsletters',
                    'process-recurring' => 'processRecurring',
                    'process-newsletter-queue' => 'processNewsletterQueue',
                    'match-digest-daily' => 'matchDigestDaily',
                    'match-digest-weekly' => 'matchDigestWeekly',
                    'notify-hot-matches' => 'notifyHotMatches',
                    'geocode-batch' => 'geocodeBatch',
                    'cleanup' => 'cleanup',
                    'run-all' => 'runAll',
                ];

                // Jobs that use different controllers
                $adminControllerJobs = [
                    'update-featured-groups' => 'cronUpdateFeaturedGroups',
                ];

                if (isset($methodMap[$jobId])) {
                    $method = $methodMap[$jobId];
                    ob_start();
                    $_GET['key'] = $cronKey;
                    $controller->$method();
                    unset($_GET['key']);
                    $output = ob_get_clean();
                } elseif (isset($adminControllerJobs[$jobId])) {
                    $method = $adminControllerJobs[$jobId];
                    $adminController = new \Nexus\Controllers\AdminController();
                    ob_start();
                    $_GET['key'] = $cronKey;
                    $adminController->$method();
                    unset($_GET['key']);
                    $output = ob_get_clean();
                } else {
                    $output = "No method mapping for job: {$jobId}";
                    $status = 'error';
                }
            }
        } catch (\Throwable $e) {
            $output = "Exception: " . $e->getMessage() . "\n" . $e->getTraceAsString();
            $status = 'error';
        }

        $duration = microtime(true) - $startTime;

        // Clear the shutdown handler flag since we're logging normally
        unset($GLOBALS['_cron_job_info']);

        // Update the existing log entry (or create new if somehow missing)
        if ($logId) {
            try {
                Database::query(
                    "UPDATE cron_logs SET status = ?, output = ?, duration_seconds = ? WHERE id = ?",
                    [$status, substr($output, 0, 65000), $duration, $logId]
                );
            } catch (\Exception $e) {
                error_log("Failed to update cron log: " . $e->getMessage());
            }
        } else {
            $this->logCronExecution($jobId, $status, $output, $duration);
        }

        $_SESSION['cron_result'] = [
            'job_id' => $jobId,
            'job_name' => $job['name'],
            'status' => $status,
            'output' => $output,
            'duration' => round($duration, 2),
        ];

        header('Location: ' . TenantContext::getBasePath() . '/admin/cron-jobs?ran=' . $jobId);
        exit;
    }

    /**
     * View execution logs
     */
    public function logs()
    {
        $this->requireAdmin();
        $this->ensureTables();

        $page = max(1, (int)($_GET['page'] ?? 1));
        $perPage = 50;
        $offset = ($page - 1) * $perPage;
        $jobFilter = $_GET['job'] ?? '';
        $statusFilter = $_GET['status'] ?? '';

        $logs = [];
        $total = 0;

        try {
            $whereClause = "1=1";
            $params = [];

            if ($jobFilter) {
                $whereClause .= " AND cl.job_id = ?";
                $params[] = $jobFilter;
            }

            if ($statusFilter) {
                $whereClause .= " AND cl.status = ?";
                $params[] = $statusFilter;
            }

            $logs = Database::query(
                "SELECT cl.*, u.name as executed_by_name
                 FROM cron_logs cl
                 LEFT JOIN users u ON cl.executed_by = u.id
                 WHERE {$whereClause}
                 ORDER BY cl.executed_at DESC
                 LIMIT ? OFFSET ?",
                array_merge($params, [$perPage, $offset])
            )->fetchAll();

            $total = Database::query(
                "SELECT COUNT(*) as c FROM cron_logs cl WHERE {$whereClause}",
                $params
            )->fetch()['c'] ?? 0;
        } catch (\Exception $e) {
            // Table may not exist
        }

        $cronJobs = $this->getCronJobs();

        View::render('admin/cron-jobs/logs', [
            'logs' => $logs,
            'page' => $page,
            'perPage' => $perPage,
            'total' => $total,
            'totalPages' => ceil($total / $perPage),
            'jobFilter' => $jobFilter,
            'statusFilter' => $statusFilter,
            'cronJobs' => $cronJobs,
        ]);
    }

    /**
     * Settings page
     */
    public function settings()
    {
        $this->requireAdmin();
        $this->ensureTables();

        $globalSettings = $this->getGlobalSettings();
        $jobSettings = $this->getJobSettings();
        $cronJobs = $this->getCronJobs();

        View::render('admin/cron-jobs/settings', [
            'globalSettings' => $globalSettings,
            'jobSettings' => $jobSettings,
            'cronJobs' => $cronJobs,
        ]);
    }

    /**
     * Save settings
     */
    public function saveSettings()
    {
        $this->requireAdmin();
        Csrf::verifyOrDie();
        $this->ensureTables();

        try {
            // Save global settings
            $globalKeys = [
                'failure_notification_enabled',
                'failure_notification_emails',
                'failure_notification_threshold',
                'log_retention_days',
                'timezone',
            ];

            foreach ($globalKeys as $key) {
                $value = $_POST[$key] ?? '';
                Database::query(
                    "INSERT INTO cron_settings (setting_key, setting_value) VALUES (?, ?)
                     ON DUPLICATE KEY UPDATE setting_value = VALUES(setting_value)",
                    [$key, $value]
                );
            }

            // Save per-job settings
            if (!empty($_POST['job_settings'])) {
                foreach ($_POST['job_settings'] as $jobId => $settings) {
                    $notifyOnFailure = isset($settings['notify_on_failure']) ? 1 : 0;
                    $notifyEmails = trim($settings['notify_emails'] ?? '');

                    $existing = Database::query(
                        "SELECT id FROM cron_job_settings WHERE job_id = ?",
                        [$jobId]
                    )->fetch();

                    if ($existing) {
                        Database::query(
                            "UPDATE cron_job_settings SET notify_on_failure = ?, notify_emails = ? WHERE job_id = ?",
                            [$notifyOnFailure, $notifyEmails, $jobId]
                        );
                    } else {
                        Database::query(
                            "INSERT INTO cron_job_settings (job_id, notify_on_failure, notify_emails) VALUES (?, ?, ?)",
                            [$jobId, $notifyOnFailure, $notifyEmails]
                        );
                    }
                }
            }

            $_SESSION['flash_success'] = 'Cron settings saved successfully.';
        } catch (\Exception $e) {
            $_SESSION['flash_error'] = 'Failed to save settings: ' . $e->getMessage();
        }

        header('Location: ' . TenantContext::getBasePath() . '/admin/cron-jobs/settings');
        exit;
    }

    /**
     * Setup instructions page
     */
    public function setup()
    {
        $this->requireAdmin();

        $cronJobs = $this->getCronJobs();
        $cronKey = Env::get('CRON_KEY', 'your-secure-cron-key');
        $appUrl = Env::get('APP_URL', 'https://yourdomain.com');

        View::render('admin/cron-jobs/setup', [
            'cronJobs' => $cronJobs,
            'cronKey' => $cronKey,
            'appUrl' => $appUrl,
        ]);
    }

    /**
     * Clear old logs
     */
    public function clearLogs()
    {
        $this->requireAdmin();
        Csrf::verifyOrDie();

        try {
            $days = (int)($_POST['days'] ?? 30);
            Database::query(
                "DELETE FROM cron_logs WHERE executed_at < DATE_SUB(NOW(), INTERVAL ? DAY)",
                [$days]
            );
            $_SESSION['flash_success'] = "Cleared cron logs older than {$days} days.";
        } catch (\Exception $e) {
            $_SESSION['flash_error'] = "Failed to clear logs: " . $e->getMessage();
        }

        header('Location: ' . TenantContext::getBasePath() . '/admin/cron-jobs/logs');
        exit;
    }

    /**
     * API endpoint for job stats (for AJAX refresh)
     */
    public function apiStats()
    {
        $this->requireAdmin();

        header('Content-Type: application/json');
        echo json_encode([
            'stats' => $this->getJobStats(),
            'timestamp' => date('Y-m-d H:i:s'),
        ]);
        exit;
    }
}
