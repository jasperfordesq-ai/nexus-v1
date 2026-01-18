<?php

namespace Nexus\Controllers;

use Nexus\Core\Database;
use Nexus\Core\Mailer;
use Nexus\Core\Env;
use Nexus\Core\TenantContext;
use Nexus\Models\User;
use Nexus\Services\NewsletterService;
use Nexus\Services\MatchingService;
use Nexus\Services\NotificationDispatcher;
use Nexus\Services\GeocodingService;
use Nexus\Services\FederationEmailService;

class CronController
{
    private ?float $jobStartTime = null;
    private ?string $currentJobId = null;

    private function checkAccess()
    {
        // 1. CLI Access is always allowed
        if (php_sapi_name() === 'cli') {
            return;
        }

        // 2. HTTP Access requires a Key
        // Try to get key from GET param or Header
        $key = $_GET['key'] ?? null;
        if (!$key) {
            // Check Header 'X-Cron-Key'
            $headers = getallheaders();
            $key = $headers['X-Cron-Key'] ?? null;
        }

        $validKey = Env::get('CRON_KEY', 'default_insecure_key_change_me');

        // Note: 'default_insecure_key_change_me' is a fallback to prevent open access if ENV is missing, 
        // effectively forcing the user to supply the fallback key if they haven't configured .env yet.
        // ideally we should block if CRON_KEY is not set.

        if (!$key || !hash_equals($validKey, $key)) {
            http_response_code(403);
            die('Access Denied: Invalid Cron Key');
        }
    }

    /**
     * Ensure the cron_logs table exists
     */
    private function ensureLogsTable(): void
    {
        try {
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
        } catch (\Exception $e) {
            // Table likely exists or DB error - log and continue
            error_log("Cron logs table check: " . $e->getMessage());
        }
    }

    /**
     * Check if we're being called from the admin panel (which handles its own logging)
     */
    private function isInternalRun(): bool
    {
        return defined('CRON_INTERNAL_RUN') && CRON_INTERNAL_RUN === true;
    }

    /**
     * Start tracking a cron job execution
     */
    private function startJob(string $jobId): void
    {
        // Skip logging if called from admin panel (it handles its own logging)
        if ($this->isInternalRun()) {
            return;
        }

        $this->currentJobId = $jobId;
        $this->jobStartTime = microtime(true);
        $this->ensureLogsTable();
    }

    /**
     * Log the completion of a cron job
     */
    private function logJob(string $status, string $output): void
    {
        // Skip logging if called from admin panel (it handles its own logging)
        if ($this->isInternalRun()) {
            return;
        }

        if (!$this->currentJobId || !$this->jobStartTime) {
            return;
        }

        $duration = microtime(true) - $this->jobStartTime;

        try {
            Database::query(
                "INSERT INTO cron_logs (job_id, status, output, duration_seconds, executed_by, tenant_id) VALUES (?, ?, ?, ?, NULL, NULL)",
                [
                    $this->currentJobId,
                    $status,
                    substr($output, 0, 65000),
                    round($duration, 2)
                ]
            );
        } catch (\Exception $e) {
            error_log("Failed to log cron execution for {$this->currentJobId}: " . $e->getMessage());
        }

        $this->currentJobId = null;
        $this->jobStartTime = null;
    }

    /**
     * Run Daily Digest
     * Should be triggered once every 24 hours via cron job.
     * URI: /cron/daily-digest
     */
    public function dailyDigest()
    {
        $this->checkAccess();
        $this->startJob('daily-digest');
        ob_start();
        try {
            $this->processDigest('daily');
            $output = ob_get_clean();
            echo $output;
            $this->logJob('success', $output);
        } catch (\Throwable $e) {
            $output = ob_get_clean() . "\nError: " . $e->getMessage();
            echo $output;
            $this->logJob('error', $output);
        }
    }

    /**
     * Run Weekly Digest
     * Should be triggered once a week (e.g. Friday 5pm).
     * URI: /cron/weekly-digest
     */
    public function weeklyDigest()
    {
        $this->checkAccess();
        $this->startJob('weekly-digest');
        ob_start();
        try {
            $this->processDigest('weekly');
            $output = ob_get_clean();
            echo $output;
            $this->logJob('success', $output);
        } catch (\Throwable $e) {
            $output = ob_get_clean() . "\nError: " . $e->getMessage();
            echo $output;
            $this->logJob('error', $output);
        }
    }


    private function processDigest($frequency)
    {
        header('Content-Type: text/plain');
        echo "Starting $frequency digest processing...\n";

        // 1. Find users with pending items for this frequency
        $sql = "SELECT user_id, COUNT(*) as count
                FROM notification_queue
                WHERE frequency = ? AND status = 'pending'
                GROUP BY user_id";

        $users = Database::query($sql, [$frequency])->fetchAll();

        if (empty($users)) {
            echo "No pending notifications for $frequency digest.\n";
            exit;
        }

        echo "Found " . count($users) . " users to process.\n";

        $mailer = new Mailer();

        foreach ($users as $uRow) {
            $userId = $uRow['user_id'];
            $count = $uRow['count'];

            $user = User::findById($userId);
            if (!$user || empty($user['email'])) {
                echo "Skipping User ID $userId (No email/Invalid).\n";
                continue;
            }

            echo "Processing User: {$user['name']} ($count items)...\n";

            // Partition into batches? (Maybe later. For now, fetch all pending for this user/freq)
            $itemsSql = "SELECT * FROM notification_queue
                         WHERE user_id = ? AND frequency = ? AND status = 'pending'
                         ORDER BY created_at ASC";
            $items = Database::query($itemsSql, [$userId, $frequency])->fetchAll();

            // Generate Email Body
            $subject = "Your $frequency Digest from Project NEXUS";
            $body = $this->generateEmailHtml($user, $items, $frequency);

            // Send Email
            if ($mailer->send($user['email'], $subject, $body)) {
                echo " - Email Sent.\n";

                // Mark as Sent
                // Collect IDs
                $ids = array_column($items, 'id');
                if (!empty($ids)) {
                    $inQuery = implode(',', array_fill(0, count($ids), '?'));
                    $updateSql = "UPDATE notification_queue SET status = 'sent', sent_at = NOW() WHERE id IN ($inQuery)";
                    Database::query($updateSql, $ids);
                    echo " - Queue updated (Marked as sent).\n";
                }
            } else {
                echo " - FAILED to send email.\n";
            }
        }

        echo "Done.\n";
    }

    public function runInstantQueue()
    {
        $this->checkAccess();
        $this->startJob('process-queue');

        header('Content-Type: text/plain');
        ob_start();
        $status = 'success';

        try {
            echo "Processing Instant Queue...\n";

            $mailer = new Mailer();

            // Limit to 50 to prevent timeout
            $sql = "SELECT q.*, u.email, u.name
                    FROM notification_queue q
                    JOIN users u ON q.user_id = u.id
                    WHERE q.frequency = 'instant' AND q.status = 'pending'
                    ORDER BY q.created_at ASC
                    LIMIT 50";

            $items = Database::query($sql)->fetchAll();

            if (empty($items)) {
                echo "No pending instant notifications.\n";
            } else {
                foreach ($items as $item) {
                    echo "Sending Instant ID {$item['id']} to {$item['email']}... ";

                    // Reconstruct subject
                    $subject = "Notification from Nexus";
                    if ($item['activity_type'] === 'new_topic') {
                        $subject = "New Discussion: " . substr(strip_tags($item['content_snippet']), 0, 50) . "...";
                    } elseif ($item['activity_type'] === 'new_reply') {
                        $subject = "New Reply to Discussion";
                    } elseif ($item['activity_type'] === 'hot_match') {
                        $subject = "🔥 Hot Match Found!";
                    } elseif ($item['activity_type'] === 'mutual_match') {
                        $subject = "🤝 Mutual Match Opportunity";
                    } elseif ($item['activity_type'] === 'match_digest') {
                        $subject = "📊 Your Match Digest";
                    }

                    $body = $item['email_body'] ?? nl2br($item['content_snippet']);

                    // Replace placeholder URLs in email body
                    $baseUrl = TenantContext::getSetting('site_url', 'https://' . ($_SERVER['HTTP_HOST'] ?? ''));
                    $basePath = TenantContext::getBasePath();

                    // Replace {{MATCHES_URL}} placeholder
                    $body = str_replace('{{MATCHES_URL}}', $baseUrl . $basePath . '/matches', $body);

                    // Replace {{LISTING_URL}} placeholder (for hot/mutual match emails)
                    if (!empty($item['link']) && strpos($item['link'], '/listings/') !== false) {
                        $body = str_replace('{{LISTING_URL}}', $baseUrl . $basePath . $item['link'], $body);
                    }

                    if ($mailer->send($item['email'], $subject, $body)) {
                        Database::query("UPDATE notification_queue SET status = 'sent', sent_at = NOW() WHERE id = ?", [$item['id']]);
                        echo "OK.\n";
                    } else {
                        echo "FAILED.\n";
                    }
                }
            }
            echo "Done.\n";
        } catch (\Throwable $e) {
            echo "\nError: " . $e->getMessage() . "\n";
            $status = 'error';
        }

        $output = ob_get_clean();
        echo $output;
        $this->logJob($status, $output);
    }

    private function generateEmailHtml($user, $items, $frequency)
    {
        // Simple HTML Template
        $listHtml = '';
        foreach ($items as $item) {
            $date = date('M j, g:i a', strtotime($item['created_at']));
            $snippet = htmlspecialchars($item['content_snippet']);
            $link = $item['link'] ? "https://" . $_SERVER['HTTP_HOST'] . $item['link'] : '#';

            $listHtml .= "
            <div style='margin-bottom: 15px; padding-bottom: 15px; border-bottom: 1px solid #eee;'>
                <div style='font-size: 14px; color: #333;'>$snippet</div>
                <div style='font-size: 12px; color: #888; margin-top: 4px;'>
                    $date - <a href='$link' style='color: #4f46e5; text-decoration: none;'>View</a>
                </div>
            </div>";
        }

        $freqLabel = ucfirst($frequency);

        return "
        <html>
        <body style='font-family: sans-serif; line-height: 1.5; color: #333;'>
            <div style='max-width: 600px; margin: 0 auto; padding: 20px; border: 1px solid #ddd; border-radius: 8px;'>
                <h2 style='color: #4f46e5;'>Your $freqLabel Digest</h2>
                <p>Hello {$user['name']},</p>
                <p>Here is a summary of what you missed on Project NEXUS:</p>

                <div style='margin-top: 20px; border-top: 2px solid #eee; padding-top: 20px;'>
                    $listHtml
                </div>

                <div style='margin-top: 30px; font-size: 12px; color: #aaa; text-align: center;'>
                    <p>You received this email because you opted for a $frequency summary.</p>
                    <p><a href='https://" . $_SERVER['HTTP_HOST'] . "/dashboard?tab=notifications' style='color: #aaa;'>Manage Notifications</a></p>
                </div>
            </div>
        </body>
        </html>
        ";
    }

    /**
     * Process Scheduled Newsletters
     * Should be triggered every 5-15 minutes via cron job.
     * URI: /cron/process-newsletters
     */
    public function processNewsletters()
    {
        $this->checkAccess();
        $this->startJob('process-newsletters');

        header('Content-Type: text/plain');
        ob_start();
        $status = 'success';

        try {
            echo "Processing scheduled newsletters...\n";
            $processed = NewsletterService::processScheduled();
            echo "Processed $processed scheduled newsletters.\n";
            echo "Done.\n";
        } catch (\Exception $e) {
            echo "Error: " . $e->getMessage() . "\n";
            error_log("Newsletter cron error: " . $e->getMessage());
            $status = 'error';
        }

        $output = ob_get_clean();
        echo $output;
        $this->logJob($status, $output);
    }

    /**
     * Process Recurring Newsletters
     * Should be triggered every 15 minutes via cron job.
     * URI: /cron/process-recurring
     */
    public function processRecurring()
    {
        $this->checkAccess();
        $this->startJob('process-recurring');

        header('Content-Type: text/plain');
        ob_start();
        $status = 'success';

        try {
            echo "Processing recurring newsletters...\n";
            $processed = NewsletterService::processRecurring();
            echo "Processed $processed recurring newsletters.\n";
            echo "Done.\n";
        } catch (\Exception $e) {
            echo "Error: " . $e->getMessage() . "\n";
            error_log("Recurring newsletter cron error: " . $e->getMessage());
            $status = 'error';
        }

        $output = ob_get_clean();
        echo $output;
        $this->logJob($status, $output);
    }

    /**
     * Process Newsletter Queue (for large sends)
     * Should be triggered every 2-5 minutes via cron job.
     * URI: /cron/process-newsletter-queue
     */
    public function processNewsletterQueue()
    {
        $this->checkAccess();
        $this->startJob('process-newsletter-queue');

        header('Content-Type: text/plain');
        ob_start();
        $status = 'success';
        $processed = 0;

        try {
            echo "Processing newsletter queue...\n";

            // Find newsletters that are currently sending
            $sql = "SELECT DISTINCT newsletter_id FROM newsletter_queue WHERE status = 'pending' LIMIT 10";
            $pending = Database::query($sql)->fetchAll();

            foreach ($pending as $row) {
                $newsletter = \Nexus\Models\Newsletter::find($row['newsletter_id']);
                if ($newsletter && $newsletter['status'] === 'sending') {
                    \Nexus\Core\TenantContext::setById($newsletter['tenant_id']);
                    $sent = NewsletterService::processQueue($row['newsletter_id'], 100);
                    echo "Newsletter {$row['newsletter_id']}: Sent $sent emails.\n";
                    $processed++;
                }
            }

            echo "Processed $processed newsletter queues.\n";
            echo "Done.\n";
        } catch (\Exception $e) {
            echo "Error: " . $e->getMessage() . "\n";
            error_log("Newsletter queue cron error: " . $e->getMessage());
            $status = 'error';
        }

        $output = ob_get_clean();
        echo $output;
        $this->logJob($status, $output);
    }

    /**
     * Cleanup expired tokens and old data
     * Should be triggered once daily.
     * URI: /cron/cleanup
     */
    public function cleanup()
    {
        $this->checkAccess();
        $this->startJob('cleanup');

        header('Content-Type: text/plain');
        ob_start();
        $status = 'success';

        echo "Running cleanup tasks...\n";

        $tasks = [];

        // 1. Clean expired password reset tokens (check column exists first)
        try {
            $sql = "UPDATE users SET reset_token = NULL WHERE reset_token IS NOT NULL AND created_at < DATE_SUB(NOW(), INTERVAL 24 HOUR)";
            Database::query($sql);
            $tasks[] = "Cleaned expired password reset tokens";
        } catch (\Exception $e) {
            // Column may not exist in this schema version
            $tasks[] = "Password reset tokens: skipped (column not found)";
        }

        // 2. Clean old notification queue items (older than 30 days, already sent)
        try {
            $sql = "DELETE FROM notification_queue WHERE status = 'sent' AND sent_at < DATE_SUB(NOW(), INTERVAL 30 DAY)";
            Database::query($sql);
            $tasks[] = "Cleaned old notification queue entries";
        } catch (\Exception $e) {
            $tasks[] = "Notification queue: " . $e->getMessage();
        }

        // 3. Clean expired newsletter suppression entries
        try {
            $sql = "DELETE FROM newsletter_suppression_list WHERE expires_at IS NOT NULL AND expires_at < NOW()";
            Database::query($sql);
            $tasks[] = "Cleaned expired suppression list entries";
        } catch (\Exception $e) {
            // Table may not exist
            $tasks[] = "Suppression list: skipped or " . $e->getMessage();
        }

        // 4. Clean old newsletter tracking data (older than 90 days)
        // DISABLED: newsletter_link_clicks table missing
        /*
        try {
            $sql = "DELETE FROM newsletter_link_clicks WHERE clicked_at < DATE_SUB(NOW(), INTERVAL 90 DAY)";
            Database::query($sql);
            $tasks[] = "Cleaned old newsletter click tracking data";
        } catch (\Exception $e) {
            // Table may not exist
        }
        */

        // 5. Clean old API logs if they exist
        // DISABLED: api_logs table missing
        /*
        try {
            $sql = "DELETE FROM api_logs WHERE created_at < DATE_SUB(NOW(), INTERVAL 30 DAY)";
            Database::query($sql);
            $tasks[] = "Cleaned old API logs";
        } catch (\Exception $e) {
            // Table may not exist
        }
        */

        foreach ($tasks as $task) {
            echo " - $task\n";
        }

        echo "Done.\n";

        $output = ob_get_clean();
        echo $output;
        $this->logJob($status, $output);
    }

    /**
     * Run all cron tasks (master endpoint)
     * Useful for simple hosting where you can only have one cron job.
     * URI: /cron/run-all
     */
    public function runAll()
    {
        $this->checkAccess();
        $this->startJob('run-all');

        header('Content-Type: text/plain');
        ob_start();
        $status = 'success';

        $minute = (int) date('i');
        $hour = (int) date('H');
        $dayOfWeek = (int) date('w'); // 0 = Sunday

        try {
            echo "=== NEXUS Cron Runner ===\n";
            echo "Time: " . date('Y-m-d H:i:s') . "\n\n";

            // Every run: Process instant notification queue
            echo "[1] Processing instant queue...\n";
            $this->runInstantQueueInternal();

            // Every run: Process newsletter queue
            echo "\n[2] Processing newsletter queue...\n";
            $this->processNewsletterQueueInternal();

            // Every 5 minutes: Process scheduled newsletters
            if ($minute % 5 === 0) {
                echo "\n[3] Processing scheduled newsletters...\n";
                try {
                    $processed = NewsletterService::processScheduled();
                    echo "   Processed $processed scheduled newsletters.\n";
                } catch (\Exception $e) {
                    echo "   Error: " . $e->getMessage() . "\n";
                }
            }

            // Every 15 minutes: Process recurring newsletters
            if ($minute % 15 === 0) {
                echo "\n[4] Processing recurring newsletters...\n";
                try {
                    $processed = NewsletterService::processRecurring();
                    echo "   Processed $processed recurring newsletters.\n";
                } catch (\Exception $e) {
                    echo "   Error: " . $e->getMessage() . "\n";
                }
            }

            // Daily at midnight: Cleanup
            if ($hour === 0 && $minute === 0) {
                echo "\n[5] Running daily cleanup...\n";
                $this->cleanupInternal();
            }

            // Daily at 8am: Daily digest
            if ($hour === 8 && $minute === 0) {
                echo "\n[6] Processing daily digest...\n";
                $this->processDigest('daily');
            }

            // Friday at 5pm: Weekly digest
            if ($dayOfWeek === 5 && $hour === 17 && $minute === 0) {
                echo "\n[7] Processing weekly digest...\n";
                $this->processDigest('weekly');
            }

            // Monday at 9am: Federation weekly digest
            if ($dayOfWeek === 1 && $hour === 9 && $minute === 0) {
                echo "\n[8] Processing federation weekly digest...\n";
                $this->processFederationWeeklyDigestInternal();
            }

            echo "\n=== Cron Run Complete ===\n";
        } catch (\Throwable $e) {
            echo "\nFatal Error: " . $e->getMessage() . "\n";
            $status = 'error';
        }

        $output = ob_get_clean();
        echo $output;
        $this->logJob($status, $output);
    }

    /**
     * Internal method for queue processing (no access check)
     */
    private function runInstantQueueInternal()
    {
        $mailer = new Mailer();

        $sql = "SELECT q.*, u.email, u.name
                FROM notification_queue q
                JOIN users u ON q.user_id = u.id
                WHERE q.frequency = 'instant' AND q.status = 'pending'
                ORDER BY q.created_at ASC
                LIMIT 50";

        $items = Database::query($sql)->fetchAll();

        if (empty($items)) {
            echo "   No pending instant notifications.\n";
            return;
        }

        $sent = 0;
        foreach ($items as $item) {
            $subject = "Notification from Nexus";
            if ($item['activity_type'] === 'new_topic') {
                $subject = "New Discussion: " . substr(strip_tags($item['content_snippet']), 0, 50) . "...";
            } elseif ($item['activity_type'] === 'new_reply') {
                $subject = "New Reply to Discussion";
            }

            $body = $item['email_body'] ?? nl2br($item['content_snippet']);

            if ($mailer->send($item['email'], $subject, $body)) {
                Database::query("UPDATE notification_queue SET status = 'sent', sent_at = NOW() WHERE id = ?", [$item['id']]);
                $sent++;
            }
        }
        echo "   Sent $sent instant notifications.\n";
    }

    /**
     * Internal method for newsletter queue processing
     */
    private function processNewsletterQueueInternal()
    {
        $sql = "SELECT DISTINCT newsletter_id FROM newsletter_queue WHERE status = 'pending' LIMIT 5";
        $pending = Database::query($sql)->fetchAll();

        if (empty($pending)) {
            echo "   No pending newsletter queues.\n";
            return;
        }

        foreach ($pending as $row) {
            $newsletter = \Nexus\Models\Newsletter::find($row['newsletter_id']);
            if ($newsletter && $newsletter['status'] === 'sending') {
                \Nexus\Core\TenantContext::setById($newsletter['tenant_id']);
                $sent = NewsletterService::processQueue($row['newsletter_id'], 50);
                echo "   Newsletter {$row['newsletter_id']}: Sent $sent emails.\n";
            }
        }
    }

    /**
     * Internal cleanup method
     */
    private function cleanupInternal()
    {
        try {
            Database::query("UPDATE users SET reset_token = NULL WHERE reset_token IS NOT NULL AND created_at < DATE_SUB(NOW(), INTERVAL 24 HOUR)");
            echo "   Cleaned expired reset tokens.\n";
        } catch (\Exception $e) {
        }

        try {
            Database::query("DELETE FROM notification_queue WHERE status = 'sent' AND sent_at < DATE_SUB(NOW(), INTERVAL 30 DAY)");
            echo "   Cleaned old notification queue.\n";
        } catch (\Exception $e) {
        }

        try {
            Database::query("DELETE FROM newsletter_suppression_list WHERE expires_at IS NOT NULL AND expires_at < NOW()");
            echo "   Cleaned expired suppressions.\n";
        } catch (\Exception $e) {
        }

        // Clean old match cache entries (older than 24 hours)
        try {
            Database::query("DELETE FROM match_cache WHERE expires_at < NOW()");
            echo "   Cleaned expired match cache.\n";
        } catch (\Exception $e) {
        }
    }

    /**
     * Internal method to process federation weekly digest
     */
    private function processFederationWeeklyDigestInternal()
    {
        try {
            $sql = "SELECT u.id, u.tenant_id
                    FROM users u
                    INNER JOIN federation_user_settings fus ON u.id = fus.user_id
                    WHERE u.status = 'active'
                    AND fus.federation_optin = 1
                    AND fus.email_notifications = 1
                    AND u.email IS NOT NULL";

            $users = Database::query($sql)->fetchAll(\PDO::FETCH_ASSOC);

            if (empty($users)) {
                echo "   No users eligible for federation digest.\n";
                return;
            }

            $sent = 0;
            foreach ($users as $user) {
                try {
                    if (FederationEmailService::sendWeeklyDigest($user['id'], $user['tenant_id'])) {
                        $sent++;
                    }
                } catch (\Exception $e) {
                    // Continue on individual user errors
                }
            }

            echo "   Sent $sent federation digests.\n";
        } catch (\Exception $e) {
            echo "   Error: " . $e->getMessage() . "\n";
        }
    }

    /**
     * Process Daily Match Digests
     * Sends match digest notifications to users who opted for daily frequency.
     * URI: /cron/match-digest-daily
     */
    public function matchDigestDaily()
    {
        $this->checkAccess();
        $this->startJob('match-digest-daily');
        ob_start();
        try {
            $this->processMatchDigest('daily');
            $output = ob_get_clean();
            echo $output;
            $this->logJob('success', $output);
        } catch (\Throwable $e) {
            $output = ob_get_clean() . "\nError: " . $e->getMessage();
            echo $output;
            $this->logJob('error', $output);
        }
    }

    /**
     * Process Weekly Match Digests
     * Sends match digest notifications to users who opted for weekly frequency.
     * URI: /cron/match-digest-weekly
     */
    public function matchDigestWeekly()
    {
        $this->checkAccess();
        $this->startJob('match-digest-weekly');
        ob_start();
        try {
            $this->processMatchDigest('weekly');
            $output = ob_get_clean();
            echo $output;
            $this->logJob('success', $output);
        } catch (\Throwable $e) {
            $output = ob_get_clean() . "\nError: " . $e->getMessage();
            echo $output;
            $this->logJob('error', $output);
        }
    }

    /**
     * Internal method to process match digests
     */
    private function processMatchDigest($frequency)
    {
        header('Content-Type: text/plain');
        echo "Starting $frequency match digest processing...\n";

        // Find users who have match notification frequency set to this value
        // and have at least one listing (so we can generate matches for them)
        $sql = "SELECT DISTINCT u.id, u.name, u.email, mp.notification_frequency
                FROM users u
                LEFT JOIN match_preferences mp ON u.id = mp.user_id
                WHERE u.status = 'active'
                AND u.id IN (SELECT DISTINCT user_id FROM listings WHERE status = 'active')
                AND (
                    (mp.notification_frequency = ? AND mp.notification_frequency IS NOT NULL)
                    OR (mp.notification_frequency IS NULL AND ? = 'daily')
                )";

        try {
            $users = Database::query($sql, [$frequency, $frequency])->fetchAll();
        } catch (\Exception $e) {
            echo "Error fetching users: " . $e->getMessage() . "\n";
            echo "Match preferences table may not exist yet. Run the migration.\n";
            return;
        }

        if (empty($users)) {
            echo "No users to process for $frequency match digest.\n";
            return;
        }

        echo "Found " . count($users) . " users to process.\n";

        $processed = 0;
        $errors = 0;

        foreach ($users as $user) {
            try {
                // Get fresh matches for this user (created in last 24h for daily, 7d for weekly)
                $lookbackHours = $frequency === 'daily' ? 24 : 168;

                $matches = MatchingService::getSuggestionsForUser($user['id'], 20, [
                    'created_after' => date('Y-m-d H:i:s', strtotime("-{$lookbackHours} hours"))
                ]);

                if (empty($matches)) {
                    echo "  User {$user['id']}: No new matches.\n";
                    continue;
                }

                // Dispatch the digest notification
                NotificationDispatcher::dispatchMatchDigest($user['id'], $matches, $frequency);

                echo "  User {$user['id']}: Sent digest with " . count($matches) . " matches.\n";
                $processed++;
            } catch (\Exception $e) {
                echo "  User {$user['id']}: Error - " . $e->getMessage() . "\n";
                $errors++;
            }
        }

        echo "\nProcessed: $processed users, Errors: $errors\n";
        echo "Done.\n";
    }

    /**
     * Notify users of new hot matches (real-time)
     * This should be called when a new listing is created.
     * Can also be run periodically to catch any missed notifications.
     * URI: /cron/notify-hot-matches
     */
    public function notifyHotMatches()
    {
        $this->checkAccess();
        $this->startJob('notify-hot-matches');

        header('Content-Type: text/plain');
        ob_start();
        $status = 'success';

        try {
            echo "Processing hot match notifications...\n";

            // Find listings created in the last hour that haven't been processed
            $sql = "SELECT l.*, u.name as user_name, c.name as category_name
                    FROM listings l
                    JOIN users u ON l.user_id = u.id
                    LEFT JOIN categories c ON l.category_id = c.id
                    WHERE l.status = 'active'
                    AND l.created_at > DATE_SUB(NOW(), INTERVAL 1 HOUR)
                    AND l.id NOT IN (
                        SELECT DISTINCT listing_id FROM match_history
                        WHERE action = 'notified' AND created_at > DATE_SUB(NOW(), INTERVAL 1 HOUR)
                    )
                    LIMIT 50";

            $newListings = Database::query($sql)->fetchAll();

            if (empty($newListings)) {
                echo "No new listings to process.\n";
                echo "Done.\n";
                $output = ob_get_clean();
                echo $output;
                $this->logJob($status, $output);
                return;
            }

            echo "Found " . count($newListings) . " new listings to check for matches.\n";

            $notificationsSent = 0;

            foreach ($newListings as $listing) {
                // Find users who might be interested in this listing
                // We look for users whose listings complement this one
                $listingType = $listing['type']; // 'offer' or 'request'
                $oppositeType = $listingType === 'offer' ? 'request' : 'offer';

                // Find users with opposite type listings in same category
                $matchSql = "SELECT DISTINCT l.user_id, u.name, u.email
                             FROM listings l
                             JOIN users u ON l.user_id = u.id
                             WHERE l.category_id = ?
                             AND l.type = ?
                             AND l.status = 'active'
                             AND l.user_id != ?
                             LIMIT 20";

                $potentialUsers = Database::query($matchSql, [
                    $listing['category_id'],
                    $oppositeType,
                    $listing['user_id']
                ])->fetchAll();

                foreach ($potentialUsers as $user) {
                    // Calculate actual match score
                    $prefs = MatchingService::getPreferences($user['user_id']);

                    // Check if user wants hot match notifications
                    if (empty($prefs['notify_hot_matches']) || $prefs['notification_frequency'] === 'never') {
                        continue;
                    }

                    // Get user's listings to calculate proper match score
                    $userListings = Database::query(
                        "SELECT * FROM listings WHERE user_id = ? AND status = 'active'",
                        [$user['user_id']]
                    )->fetchAll();

                    if (empty($userListings)) continue;

                    // Calculate match score using the engine
                    $userData = User::findById($user['user_id']);
                    $matchResult = \Nexus\Services\SmartMatchingEngine::calculateMatchScore(
                        $userData,
                        $userListings,
                        $userListings[0], // Use first listing as reference
                        $listing
                    );

                    if ($matchResult['score'] >= 85) {
                        // This is a hot match! Notify the user
                        $matchData = array_merge($listing, [
                            'match_score' => $matchResult['score'],
                            'match_reasons' => $matchResult['reasons'],
                            'distance_km' => $matchResult['distance'] ?? null
                        ]);

                        NotificationDispatcher::dispatchHotMatch($user['user_id'], $matchData);
                        $notificationsSent++;

                        // Record that we notified about this listing
                        try {
                            Database::query(
                                "INSERT INTO match_history (user_id, listing_id, action, match_score, created_at) VALUES (?, ?, 'notified', ?, NOW())",
                                [$user['user_id'], $listing['id'], $matchResult['score']]
                            );
                        } catch (\Exception $e) {
                            // Table may not exist
                        }
                    }
                }
            }

            echo "Sent $notificationsSent hot match notifications.\n";
            echo "Done.\n";
        } catch (\Throwable $e) {
            echo "\nError: " . $e->getMessage() . "\n";
            $status = 'error';
        }

        $output = ob_get_clean();
        echo $output;
        $this->logJob($status, $output);
    }

    /**
     * Batch geocode users and listings without coordinates
     * URI: /cron/geocode-batch
     */
    public function geocodeBatch()
    {
        $this->checkAccess();
        $this->startJob('geocode-batch');

        header('Content-Type: text/plain');
        ob_start();
        $status = 'success';

        try {
            echo "Starting batch geocoding...\n\n";

            // Get stats first
            $stats = GeocodingService::getStats();
            echo "Current Status:\n";
            echo "  Users with coordinates: {$stats['users_with_coords']}\n";
            echo "  Users needing geocoding: {$stats['users_without_coords']}\n";
            echo "  Listings with coordinates: {$stats['listings_with_coords']}\n";
            echo "  Listings needing geocoding: {$stats['listings_without_coords']}\n";
            echo "  Cache entries: {$stats['cache_entries']}\n\n";

            // Geocode users (limit to 50 per run to avoid timeouts)
            echo "Geocoding users...\n";
            $userResults = GeocodingService::batchGeocodeUsers(50);
            echo "  Processed: {$userResults['processed']}\n";
            echo "  Success: {$userResults['success']}\n";
            echo "  Failed: {$userResults['failed']}\n\n";

            // Geocode listings (limit to 50 per run)
            echo "Geocoding listings...\n";
            $listingResults = GeocodingService::batchGeocodeListings(50);
            echo "  Processed: {$listingResults['processed']}\n";
            echo "  Success: {$listingResults['success']}\n";
            echo "  Failed: {$listingResults['failed']}\n\n";

            echo "Done.\n";
        } catch (\Throwable $e) {
            echo "\nError: " . $e->getMessage() . "\n";
            $status = 'error';
        }

        $output = ob_get_clean();
        echo $output;
        $this->logJob($status, $output);
    }

    /**
     * Process Federation Weekly Digest
     * Sends weekly federation activity summaries to users who have federation enabled.
     * Should be triggered once a week (e.g. Monday morning).
     * URI: /cron/federation-weekly-digest
     */
    public function federationWeeklyDigest()
    {
        $this->checkAccess();
        $this->startJob('federation-weekly-digest');

        header('Content-Type: text/plain');
        ob_start();
        $status = 'success';

        try {
            echo "Starting federation weekly digest processing...\n";

            // Find all users who have federation opted in and email notifications enabled
            $sql = "SELECT u.id, u.tenant_id, u.email, u.first_name
                    FROM users u
                    INNER JOIN federation_user_settings fus ON u.id = fus.user_id
                    WHERE u.status = 'active'
                    AND fus.federation_optin = 1
                    AND fus.email_notifications = 1
                    AND u.email IS NOT NULL";

            $users = Database::query($sql)->fetchAll(\PDO::FETCH_ASSOC);

            if (empty($users)) {
                echo "No users eligible for federation weekly digest.\n";
                echo "Done.\n";
                $output = ob_get_clean();
                echo $output;
                $this->logJob($status, $output);
                return;
            }

            echo "Found " . count($users) . " users to check for federation activity.\n";

            $sent = 0;
            $skipped = 0;
            $errors = 0;

            foreach ($users as $user) {
                try {
                    // sendWeeklyDigest returns false if user has no activity
                    if (FederationEmailService::sendWeeklyDigest($user['id'], $user['tenant_id'])) {
                        echo "  User {$user['id']}: Sent digest.\n";
                        $sent++;
                    } else {
                        echo "  User {$user['id']}: No federation activity, skipped.\n";
                        $skipped++;
                    }
                } catch (\Exception $e) {
                    echo "  User {$user['id']}: Error - " . $e->getMessage() . "\n";
                    $errors++;
                }
            }

            echo "\nSent: $sent, Skipped (no activity): $skipped, Errors: $errors\n";
            echo "Done.\n";
        } catch (\Throwable $e) {
            echo "\nError: " . $e->getMessage() . "\n";
            $status = 'error';
        }

        $output = ob_get_clean();
        echo $output;
        $this->logJob($status, $output);
    }
}
