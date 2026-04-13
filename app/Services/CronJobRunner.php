<?php
// Copyright © 2024–2026 Jasper Ford
// SPDX-License-Identifier: AGPL-3.0-or-later
// Author: Jasper Ford
// See NOTICE file for attribution and acknowledgements.

namespace App\Services;

use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;
use App\Core\Mailer;
use App\Core\Env;
use App\Core\TenantContext;
use App\Models\User;
use App\Services\NewsletterService;
use App\Services\MatchingService;
use App\Services\NotificationDispatcher;
use App\Services\GeocodingService;
use App\Services\FederationEmailService;
use App\Services\GamificationService;
use App\Services\GamificationEmailService;
use App\Services\AchievementCampaignService;
use App\Services\DailyRewardService;
use App\Services\ChallengeService;
use App\Services\AbuseDetectionService;
use App\Services\GroupReportingService;
use App\Services\BalanceAlertService;
use App\Services\SmartMatchingEngine;
use App\Services\BrokerMessageVisibilityService;
use App\Services\GoalReminderService;
use App\Services\InactiveMemberService;
use App\Services\EventReminderService;
use App\Services\ListingExpiryService;
use App\Services\ListingExpiryReminderService;
use App\Services\JobVacancyService;
use App\Services\RecurringShiftService;
use App\Services\StoryService;
use Illuminate\Support\Facades\RateLimiter;

/**
 * Executes scheduled cron jobs (digests, newsletters, cleanup, matching, etc.).
 */
class CronJobRunner
{
    private ?float $jobStartTime = null;
    private ?string $currentJobId = null;

    private function checkAccess()
    {
        // 1. CLI Access is always allowed
        if (php_sapi_name() === 'cli') {
            return;
        }

        // 2. IP-based rate limiting — 120 requests per hour (cron runs every minute = 60/hr, 2x headroom)
        $ip = $_SERVER['REMOTE_ADDR'] ?? 'unknown';
        $rateLimitKey = 'cron-access:' . $ip;

        if (RateLimiter::tooManyAttempts($rateLimitKey, 120)) {
            $retryAfter = RateLimiter::availableIn($rateLimitKey);
            if (($_ENV['APP_ENV'] ?? getenv('APP_ENV')) === 'testing' || (function_exists('app') && app()->environment('testing'))) {
                throw new \Symfony\Component\HttpKernel\Exception\HttpException(429, 'Too Many Requests: Rate limit exceeded. Retry after ' . $retryAfter . ' seconds.');
            }
            http_response_code(429);
            header('Retry-After: ' . $retryAfter);
            die('Too Many Requests: Rate limit exceeded. Retry after ' . $retryAfter . ' seconds.');
        }

        RateLimiter::hit($rateLimitKey, 3600);

        // 3. HTTP Access requires a Key
        // Try to get key from request query param or Header
        $key = request()->query('key');
        if (!$key) {
            $key = request()->header('X-Cron-Key');
        }

        // SECURITY: Require CRON_KEY to be explicitly set - no insecure defaults
        $validKey = Env::get('CRON_KEY');

        if (empty($validKey)) {
            error_log("SECURITY WARNING: CRON_KEY environment variable is not set. Cron access blocked.");
            if (($_ENV['APP_ENV'] ?? getenv('APP_ENV')) === 'testing' || (function_exists('app') && app()->environment('testing'))) {
                throw new \Symfony\Component\HttpKernel\Exception\HttpException(503, 'Service Unavailable: Cron key not configured');
            }
            http_response_code(503);
            die('Service Unavailable: Cron key not configured');
        }

        if (!$key || !hash_equals($validKey, $key)) {
            if (($_ENV['APP_ENV'] ?? getenv('APP_ENV')) === 'testing' || (function_exists('app') && app()->environment('testing'))) {
                throw new \Symfony\Component\HttpKernel\Exception\HttpException(403, 'Access Denied: Invalid Cron Key');
            }
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
            DB::statement("
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
            DB::insert(
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
     * Log a sub-task executed within runAll() to cron_logs.
     * Called after each sub-task so the admin page shows per-job last run times.
     */
    private function logSubTask(string $jobId, string $status, string $output, float $startTime): void
    {
        $duration = microtime(true) - $startTime;
        try {
            DB::insert(
                "INSERT INTO cron_logs (job_id, status, output, duration_seconds, executed_by, tenant_id) VALUES (?, ?, ?, ?, NULL, NULL)",
                [$jobId, $status, substr($output, 0, 65000), round($duration, 2)]
            );
        } catch (\Exception $e) {
            // Silently fail — don't break the cron run
        }
    }

    /**
     * Execute a sub-task within runAll(), capturing output and logging to cron_logs.
     */
    private function runSubTask(string $jobId, callable $task): string
    {
        // Circuit breaker: if this task has failed 3+ times consecutively,
        // skip it for this run to avoid wasting resources on a broken task.
        // Resets automatically once a run succeeds.
        try {
            $recentRuns = DB::select(
                "SELECT status FROM cron_logs WHERE job_id = ? ORDER BY executed_at DESC LIMIT 3",
                [$jobId]
            );
            if (count($recentRuns) >= 3) {
                $allFailed = true;
                foreach ($recentRuns as $run) {
                    if ($run->status !== 'error') {
                        $allFailed = false;
                        break;
                    }
                }
                if ($allFailed) {
                    $msg = "CIRCUIT BREAKER: Skipped '{$jobId}' — failed 3+ times consecutively. Will auto-reset on next success.\n";
                    echo $msg;
                    return $msg;
                }
            }
        } catch (\Throwable $e) {
            // cron_logs table may not exist yet — skip circuit breaker check
        }

        $start = microtime(true);
        $status = 'success';
        ob_start();
        try {
            $task();
        } catch (\Throwable $e) {
            echo "Error: " . $e->getMessage() . "\n";
            $status = 'error';
        }
        $output = ob_get_clean() ?: '';
        $this->logSubTask($jobId, $status, $output, $start);
        return $output;
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

        $users = array_map(fn($r) => (array) $r, DB::select($sql, [$frequency]));

        if (empty($users)) {
            echo "No pending notifications for $frequency digest.\n";
            return;
        }

        echo "Found " . count($users) . " users to process.\n";

        foreach ($users as $uRow) {
            $userId = $uRow['user_id'];
            $count = $uRow['count'];

            $user = User::findById($userId);
            if (!$user || empty($user['email'])) {
                echo "Skipping User ID $userId (No email/Invalid).\n";
                continue;
            }

            // Set tenant context so email links and SMTP credentials are correct
            if (!empty($user['tenant_id'])) {
                TenantContext::setById($user['tenant_id']);
            }

            echo "Processing User: {$user['name']} ($count items)...\n";

            // Race condition fix: atomically claim items by setting status='processing'
            $claimSql = "UPDATE notification_queue
                         SET status = 'processing'
                         WHERE user_id = ? AND frequency = ? AND status = 'pending'";
            $claimed = DB::update($claimSql, [$userId, $frequency]);

            if ($claimed === 0) {
                echo " - Items already claimed by another runner, skipping.\n";
                continue;
            }

            // Fetch the items we just claimed
            $itemsSql = "SELECT * FROM notification_queue
                         WHERE user_id = ? AND frequency = ? AND status = 'processing'
                         ORDER BY created_at ASC";
            $items = array_map(fn($r) => (array) $r, DB::select($itemsSql, [$userId, $frequency]));

            // Generate Email Body
            $subject = __('emails.digest.subject', ['frequency' => $frequency]);
            $body = $this->generateEmailHtml($user, $items, $frequency);

            // Create mailer with tenant context for correct SMTP credentials
            $mailer = Mailer::forCurrentTenant();

            // Send Email
            if ($mailer->send($user['email'], $subject, $body)) {
                echo " - Email Sent.\n";

                // Mark as Sent
                // SECURITY: also scope by tenant_id to prevent a bad row (somehow
                // matching an ID range) from crossing tenants. The user's tenant
                // is the authoritative source here.
                $ids = array_column($items, 'id');
                $userTenantId = $user['tenant_id'] ?? null;
                if (!empty($ids) && $userTenantId) {
                    $inQuery = implode(',', array_fill(0, count($ids), '?'));
                    $updateSql = "UPDATE notification_queue SET status = 'sent', sent_at = NOW()
                                  WHERE id IN ($inQuery) AND tenant_id = ?";
                    DB::update($updateSql, array_merge($ids, [$userTenantId]));
                    echo " - Queue updated (Marked as sent).\n";
                }
            } else {
                echo " - FAILED to send email.\n";

                // Revert processing items back to pending so they can be retried
                $ids = array_column($items, 'id');
                $userTenantId = $user['tenant_id'] ?? null;
                if (!empty($ids) && $userTenantId) {
                    $inQuery = implode(',', array_fill(0, count($ids), '?'));
                    $revertSql = "UPDATE notification_queue SET status = 'failed'
                                  WHERE id IN ($inQuery) AND tenant_id = ?";
                    DB::update($revertSql, array_merge($ids, [$userTenantId]));
                }
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

            // Race condition fix: atomically claim up to 50 pending items
            $claimSql = "UPDATE notification_queue
                         SET status = 'processing'
                         WHERE frequency = 'instant' AND status = 'pending'
                         ORDER BY created_at ASC
                         LIMIT 50";
            $claimed = DB::update($claimSql);

            if ($claimed === 0) {
                echo "No pending instant notifications.\n";
            } else {
                // Fetch ONLY items we just claimed (status=processing) — use a batch marker
                // to avoid picking up stale items from crashed previous runs
                $sql = "SELECT q.*, u.email, u.name, u.tenant_id as user_tenant_id
                        FROM notification_queue q
                        JOIN users u ON q.user_id = u.id
                        WHERE q.frequency = 'instant' AND q.status = 'processing'
                        ORDER BY q.created_at ASC
                        LIMIT 50";

                $items = array_map(fn($r) => (array) $r, DB::select($sql));

                foreach ($items as $item) {
                    // Per-item exception handling: one bad item must never affect others
                    try {
                        // Set tenant context so URLs and SMTP credentials are correct
                        if (!empty($item['user_tenant_id'])) {
                            TenantContext::setById($item['user_tenant_id']);
                        }

                        echo "Sending Instant ID {$item['id']} to {$item['email']}... ";

                        // Reconstruct subject
                        $subject = self::resolveEmailSubject($item['activity_type'], $item['content_snippet'] ?? '');

                        $body = $item['email_body'] ?? nl2br($item['content_snippet']);

                        // Replace placeholder URLs in email body
                        $baseUrl = TenantContext::getFrontendUrl();
                        $basePath = TenantContext::getSlugPrefix();

                        // Replace {{MATCHES_URL}} placeholder
                        $body = str_replace('{{MATCHES_URL}}', $baseUrl . $basePath . '/matches', $body);

                        // Replace {{LISTING_URL}} placeholder (for hot/mutual match emails)
                        if (!empty($item['link']) && strpos($item['link'], '/listings/') !== false) {
                            $body = str_replace('{{LISTING_URL}}', $baseUrl . $basePath . $item['link'], $body);
                        }

                        // Replace {{EXCHANGE_URL}} placeholder (for exchange notifications)
                        if (!empty($item['link']) && strpos($item['link'], '/exchanges/') !== false) {
                            $body = str_replace('{{EXCHANGE_URL}}', $baseUrl . $basePath . $item['link'], $body);
                        }

                        // Create mailer with tenant context for correct SMTP credentials
                        $mailer = Mailer::forCurrentTenant();

                        if ($mailer->send($item['email'], $subject, $body)) {
                            DB::update("UPDATE notification_queue SET status = 'sent', sent_at = NOW() WHERE id = ?", [$item['id']]);
                            echo "OK.\n";
                        } else {
                            DB::update("UPDATE notification_queue SET status = 'failed' WHERE id = ?", [$item['id']]);
                            echo "FAILED.\n";
                        }
                    } catch (\Throwable $itemError) {
                        // Mark this individual item as failed — do NOT revert to pending
                        echo "ERROR: {$itemError->getMessage()}\n";
                        try {
                            DB::update("UPDATE notification_queue SET status = 'failed' WHERE id = ?", [$item['id']]);
                        } catch (\Throwable $dbError) {
                            echo "Could not mark item {$item['id']} as failed: {$dbError->getMessage()}\n";
                        }
                    }
                }
            }

            // Clean up any stale 'processing' items older than 10 minutes (from crashed runs)
            DB::update("UPDATE notification_queue SET status = 'failed' WHERE status = 'processing' AND created_at < DATE_SUB(NOW(), INTERVAL 10 MINUTE)");

            echo "Done.\n";
        } catch (\Throwable $e) {
            echo "\nError: " . $e->getMessage() . "\n";
            $status = 'error';

            // Mark any remaining processing items as failed — NEVER revert to pending
            // (reverting to pending causes duplicate sends when items were already emailed)
            DB::update("UPDATE notification_queue SET status = 'failed' WHERE frequency = 'instant' AND status = 'processing'");
        }

        $output = ob_get_clean();
        echo $output;
        $this->logJob($status, $output);
    }

    /**
     * Map activity_type to a human-readable email subject line.
     */
    private static function resolveEmailSubject(string $activityType, string $contentSnippet = ''): string
    {
        if ($activityType === 'new_topic') {
            $snippet = substr(strip_tags($contentSnippet), 0, 50) . '...';
            return __('emails.notification_subject.new_topic', ['snippet' => $snippet]);
        }

        $key = "emails.notification_subject.{$activityType}";
        $translated = __($key);

        // If the key was not found, fall back to the default subject
        return $translated !== $key ? $translated : __('emails.notification_subject.default');
    }

    private function generateEmailHtml($user, $items, $frequency)
    {
        // Simple HTML Template
        $listHtml = '';
        foreach ($items as $item) {
            $date = date('M j, g:i a', strtotime($item['created_at']));
            $snippet = htmlspecialchars($item['content_snippet']);
            $link = $item['link'] ? TenantContext::getFrontendUrl() . TenantContext::getSlugPrefix() . $item['link'] : '#';

            $listHtml .= "
            <div style='margin-bottom: 15px; padding-bottom: 15px; border-bottom: 1px solid #eee;'>
                <div style='font-size: 14px; color: #333;'>$snippet</div>
                <div style='font-size: 12px; color: #888; margin-top: 4px;'>
                    $date - <a href='$link' style='color: #4f46e5; text-decoration: none;'>View</a>
                </div>
            </div>";
        }

        $freqLabel = ucfirst($frequency);
        $digestTitle = __('emails.digest.title', ['frequency' => $freqLabel]);
        $userName = htmlspecialchars($user['name'] ?? 'there', ENT_QUOTES, 'UTF-8');
        $digestGreeting = __('emails.digest.greeting', ['name' => $userName]);
        $digestIntro = __('emails.digest.intro');
        $digestOptedIn = __('emails.digest.opted_in_notice', ['frequency' => $frequency]);
        $manageNotifications = __('emails.digest.manage_notifications');
        $settingsUrl = TenantContext::getFrontendUrl() . TenantContext::getSlugPrefix() . '/dashboard?tab=notifications';

        return "
        <html>
        <body style='font-family: sans-serif; line-height: 1.5; color: #333;'>
            <div style='max-width: 600px; margin: 0 auto; padding: 20px; border: 1px solid #ddd; border-radius: 8px;'>
                <h2 style='color: #4f46e5;'>{$digestTitle}</h2>
                <p>{$digestGreeting}</p>
                <p>{$digestIntro}</p>

                <div style='margin-top: 20px; border-top: 2px solid #eee; padding-top: 20px;'>
                    $listHtml
                </div>

                <div style='margin-top: 30px; font-size: 12px; color: #aaa; text-align: center;'>
                    <p>{$digestOptedIn}</p>
                    <p><a href='{$settingsUrl}' style='color: #aaa;'>{$manageNotifications}</a></p>
                </div>
            </div>
        </body>
        </html>
        ";
    }

    /**
     * Process Scheduled Newsletters
     * Called internally by runAll() every 5 minutes and by admin panel manual trigger.
     * NOT an HTTP endpoint — the /cron/* routes were removed (2026-04-02, email bombing fix).
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
     * Called internally by runAll() every 15 minutes and by admin panel manual trigger.
     * NOT an HTTP endpoint — the /cron/* routes were removed (2026-04-02, email bombing fix).
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
     * Called internally by runAll() every minute and by admin panel manual trigger.
     * NOT an HTTP endpoint — the /cron/* routes were removed (2026-04-02, email bombing fix).
     */
    public function processNewsletterQueue()
    {
        $this->checkAccess();
        $this->startJob('process-newsletter-queue');

        header('Content-Type: text/plain');
        ob_start();
        $status = 'success';
        $totalSent = 0;
        $totalFailed = 0;

        try {
            echo "Processing newsletter queue...\n";

            // Find newsletters that are currently sending
            $sql = "SELECT DISTINCT newsletter_id FROM newsletter_queue WHERE status = 'pending' LIMIT 10";
            $pending = array_map(fn($r) => (array) $r, DB::select($sql));

            foreach ($pending as $row) {
                $newsletter = \App\Models\Newsletter::findById($row['newsletter_id']);
                if ($newsletter && $newsletter['status'] === 'sending') {
                    \App\Core\TenantContext::setById($newsletter['tenant_id']);

                    // Process ALL pending items for this newsletter (loop until done)
                    // Use smaller batches and pauses to avoid overwhelming the server
                    $batchSize = 25; // Smaller batches for stability
                    $batchCount = 0;
                    $newsletterSent = 0;
                    $newsletterFailed = 0;

                    do {
                        $result = NewsletterService::processQueue($row['newsletter_id'], $batchSize);
                        $batchSent = $result['sent'] ?? 0;
                        $batchFailed = $result['failed'] ?? 0;
                        $newsletterSent += $batchSent;
                        $newsletterFailed += $batchFailed;
                        $batchCount++;

                        // Check if there are more pending
                        $stats = \App\Models\Newsletter::getQueueStats($row['newsletter_id']);
                        $morePending = ($stats['pending'] ?? 0) > 0;

                        if ($batchSent > 0) {
                            echo "   Batch $batchCount: Sent $batchSent, Failed $batchFailed\n";
                            ob_flush();
                            flush();
                            // Pause between batches to prevent server overload
                            if ($morePending) {
                                sleep(2);
                            }
                        }
                    } while ($morePending && $batchSent > 0);

                    echo "Newsletter {$row['newsletter_id']}: Total sent $newsletterSent, failed $newsletterFailed\n";
                    $totalSent += $newsletterSent;
                    $totalFailed += $newsletterFailed;
                }
            }

            echo "Complete: Sent $totalSent emails, $totalFailed failed.\n";
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
     * Called internally by runAll() daily at midnight and by admin panel manual trigger.
     * NOT an HTTP endpoint — the /cron/* routes were removed (2026-04-02, email bombing fix).
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

        // 1. Clean expired password reset tokens (using reset_token_expiry column)
        try {
            $sql = "UPDATE users SET reset_token = NULL WHERE reset_token IS NOT NULL AND (reset_token_expiry IS NOT NULL AND reset_token_expiry < NOW())";
            DB::update($sql);
            $tasks[] = "Cleaned expired password reset tokens";
        } catch (\Exception $e) {
            // Column may not exist in this schema version
            $tasks[] = "Password reset tokens: skipped (column not found)";
        }

        // 1b. Clean expired password_resets table entries (Laravel password resets older than 1 hour)
        try {
            $sql = "DELETE FROM password_resets WHERE created_at < DATE_SUB(NOW(), INTERVAL 1 HOUR)";
            DB::delete($sql);
            $tasks[] = "Cleaned expired password_resets entries";
        } catch (\Exception $e) {
            $tasks[] = "password_resets table: skipped (" . $e->getMessage() . ")";
        }

        // 2. Clean old notification queue items (older than 30 days, already sent)
        try {
            $sql = "DELETE FROM notification_queue WHERE status = 'sent' AND sent_at < DATE_SUB(NOW(), INTERVAL 30 DAY)";
            DB::delete($sql);
            $tasks[] = "Cleaned old notification queue entries";
        } catch (\Exception $e) {
            $tasks[] = "Notification queue: " . $e->getMessage();
        }

        // 3. Clean expired newsletter suppression entries
        try {
            $sql = "DELETE FROM newsletter_suppression_list WHERE expires_at IS NOT NULL AND expires_at < NOW()";
            DB::delete($sql);
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
            DB::delete($sql);
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
            DB::delete($sql);
            $tasks[] = "Cleaned old API logs";
        } catch (\Exception $e) {
            // Table may not exist
        }
        */

        // 6. Clean expired API tokens
        try {
            DB::delete("DELETE FROM api_tokens WHERE expires_at IS NOT NULL AND expires_at < NOW()");
            $tasks[] = "Cleaned expired API tokens";
        } catch (\Exception $e) {
            $tasks[] = "API tokens: skipped (" . $e->getMessage() . ")";
        }

        // 7. Clean expired password_resets entries (older than 1 hour)
        try {
            DB::delete("DELETE FROM password_resets WHERE created_at < DATE_SUB(NOW(), INTERVAL 1 HOUR)");
            $tasks[] = "Cleaned expired password_resets entries";
        } catch (\Exception $e) {
            $tasks[] = "password_resets: skipped (" . $e->getMessage() . ")";
        }

        // 8. Expire stale exchange requests (pending_provider > 14 days)
        try {
            $expired = DB::update(
                "UPDATE exchange_requests SET status = 'expired', updated_at = NOW()
                 WHERE status = 'pending_provider' AND created_at < DATE_SUB(NOW(), INTERVAL 14 DAY)"
            );
            $tasks[] = "Expired $expired stale exchange requests";
        } catch (\Exception $e) {
            $tasks[] = "Exchange expiry: skipped (" . $e->getMessage() . ")";
        }

        foreach ($tasks as $task) {
            echo " - $task\n";
        }

        echo "Done.\n";

        $output = ob_get_clean();
        echo $output;
        $this->logJob($status, $output);
    }

    /**
     * Run all cron tasks (master scheduler)
     * Called every minute via `artisan schedule:run` (Laravel scheduler in bootstrap/app.php).
     * The HTTP /cron/run-all route was removed (2026-04-02) to prevent duplicate execution.
     * The ONLY trigger is: docker exec nexus-php-app php artisan schedule:run (host crontab).
     *
     * Schedule overview:
     *   Every run:    Instant queue, newsletter queue
     *   Every 5 min:  Scheduled newsletters
     *   Every 15 min: Recurring newsletters
     *   Every 30 min: Geocode batch, warm match cache
     *   Hourly :00:   Hot matches, gamification campaigns, abuse detection, session/token cleanup
     *   Hourly :30:   Challenge expiry check
     *   00:00:        Daily cleanup, story cleanup, leaderboard snapshot
     *   01:00:        Streak milestones
     *   03:00:        Gamification daily tasks
     *   06:00:        Recurring shift generation
     *   07:00:        Abuse daily report
     *   08:00:        Daily digest, balance alerts, job expiry
     *   09:00:        Match digest daily
     *   Every 6 hrs:  Verification reminders
     *   04:30:        Expire abandoned verifications
     *   Fri 17:00:    Weekly digest
     *   Sun 02:00:    Abuse cleanup
     *   Sun 03:00:    Gamification cleanup
     *   Sun 03:30:    Purge old verification sessions
     *   Mon 04:00:    Gamification weekly digest
     *   Mon 09:00:    Federation digest, group digests, match digest weekly
     */
    public function runAll()
    {
        $this->checkAccess();

        // Acquire exclusive lock — prevents concurrent execution from HTTP + scheduler.
        // If another runner holds the lock, bail immediately to avoid double-sends.
        $lock = Cache::lock('cron:run-all', 600); // 10-minute TTL
        if (!$lock->get()) {
            if (php_sapi_name() !== 'cli') {
                header('Content-Type: text/plain');
            }
            echo "Cron already running — skipped to prevent duplicate execution.\n";
            return;
        }

        try {
            $this->runAllInternal();
        } finally {
            $lock->release();
        }
    }

    /**
     * Internal implementation of runAll(), called under exclusive lock.
     */
    private function runAllInternal(): void
    {
        $this->startJob('run-all');

        header('Content-Type: text/plain');
        ob_start();
        $status = 'success';

        $minute = (int) date('i');
        $hour = (int) date('H');
        $dayOfWeek = (int) date('w'); // 0 = Sunday
        $taskNum = 0;

        try {
            echo "=== NEXUS Cron Runner ===\n";
            echo "Time: " . date('Y-m-d H:i:s') . " (min=$minute, hour=$hour, dow=$dayOfWeek)\n\n";

            // ── EVERY RUN (every minute) ──
            $taskNum++;
            echo "[{$taskNum}] Processing instant queue...\n";
            echo $this->runSubTask('process-queue', fn() => $this->runInstantQueueInternal());

            $taskNum++;
            echo "\n[{$taskNum}] Processing newsletter queue...\n";
            echo $this->runSubTask('process-newsletter-queue', fn() => $this->processNewsletterQueueInternal());

            // ── EVERY 5 MINUTES ──
            if ($minute % 5 === 0) {
                $taskNum++;
                echo "\n[{$taskNum}] Processing scheduled newsletters...\n";
                echo $this->runSubTask('process-newsletters', function () {
                    $processed = NewsletterService::processScheduled();
                    echo "   Processed $processed scheduled newsletters.\n";
                });

                $taskNum++;
                echo "\n[{$taskNum}] Retrying failed webhooks...\n";
                echo $this->runSubTask('retry-failed-webhooks', fn() => $this->retryFailedWebhooksInternal());
            }

            // ── EVERY 15 MINUTES ──
            if ($minute % 15 === 0) {
                $taskNum++;
                echo "\n[{$taskNum}] Processing recurring newsletters...\n";
                echo $this->runSubTask('process-recurring', function () {
                    $processed = NewsletterService::processRecurring();
                    echo "   Processed $processed recurring newsletters.\n";
                });

                $taskNum++;
                echo "\n[{$taskNum}] Event reminders...\n";
                echo $this->runSubTask('event-reminders', fn() => $this->eventRemindersInternal());
            }

            // ── EVERY 30 MINUTES ──
            if ($minute % 30 === 0) {
                $taskNum++;
                echo "\n[{$taskNum}] Batch geocoding...\n";
                echo $this->runSubTask('geocode-batch', fn() => $this->geocodeBatchInternal());

                $taskNum++;
                echo "\n[{$taskNum}] Volunteer pre-shift reminders...\n";
                echo $this->runSubTask('volunteer-pre-shift', fn() => $this->volunteerPreShiftRemindersInternal());

                $taskNum++;
                echo "\n[{$taskNum}] Volunteer post-shift feedback...\n";
                echo $this->runSubTask('volunteer-post-shift', fn() => $this->volunteerPostShiftFeedbackInternal());
            }

            if ($minute === 30) {
                $taskNum++;
                echo "\n[{$taskNum}] Warming match cache...\n";
                echo $this->runSubTask('warm-match-cache', fn() => $this->warmMatchCacheInternal());
            }

            // ── HOURLY (at :00) ──
            if ($minute === 0) {
                $taskNum++;
                echo "\n[{$taskNum}] Hot match notifications...\n";
                echo $this->runSubTask('notify-hot-matches', fn() => $this->notifyHotMatchesInternal());

                $taskNum++;
                echo "\n[{$taskNum}] Gamification campaigns...\n";
                echo $this->runSubTask('gamification-campaigns', fn() => $this->gamificationCampaignsInternal());

                $taskNum++;
                echo "\n[{$taskNum}] Abuse detection...\n";
                echo $this->runSubTask('abuse-detection', fn() => $this->abuseDetectionInternal());

                $taskNum++;
                echo "\n[{$taskNum}] Cleaning sessions & tokens...\n";
                echo $this->runSubTask('cleanup-sessions', fn() => $this->cleanSessionsAndTokensInternal());

                $taskNum++;
                echo "\n[{$taskNum}] Expiring monitoring restrictions...\n";
                echo $this->runSubTask('expire-monitoring', fn() => $this->expireMonitoringRestrictionsInternal());
            }

            // ── HOURLY (at :30) ──
            if ($minute === 30) {
                $taskNum++;
                echo "\n[{$taskNum}] Challenge expiry check...\n";
                echo $this->runSubTask('gamification-challenges', fn() => $this->gamificationChallengeCheckInternal());
            }

            // ── DAILY ──
            if ($hour === 0 && $minute === 0) {
                $taskNum++;
                echo "\n[{$taskNum}] Running daily cleanup...\n";
                echo $this->runSubTask('cleanup', fn() => $this->cleanupInternal());

                $taskNum++;
                echo "\n[{$taskNum}] Story cleanup (expire + media purge)...\n";
                echo $this->runSubTask('story-cleanup', function () {
                    (new StoryService())->cleanupExpired();
                    echo "   Story cleanup complete.\n";
                });

                $taskNum++;
                echo "\n[{$taskNum}] GDPR export cleanup (expired files)...\n";
                echo $this->runSubTask('gdpr-export-cleanup', function () {
                    $cleaned = (new \App\Services\Enterprise\GdprService())->cleanupExpiredExports();
                    echo "   GDPR export cleanup complete: {$cleaned} expired files removed.\n";
                });

                $taskNum++;
                echo "\n[{$taskNum}] Leaderboard snapshot...\n";
                echo $this->runSubTask('gamification-leaderboard', fn() => $this->gamificationLeaderboardSnapshotInternal());
            }

            if ($hour === 1 && $minute === 0) {
                $taskNum++;
                echo "\n[{$taskNum}] Streak milestones...\n";
                echo $this->runSubTask('gamification-streaks', fn() => $this->gamificationStreakMilestonesInternal());
            }

            if ($hour === 3 && $minute === 0) {
                $taskNum++;
                echo "\n[{$taskNum}] Gamification daily tasks...\n";
                echo $this->runSubTask('gamification-daily', fn() => $this->gamificationDailyInternal());
            }

            if ($hour === 4 && $minute === 0) {
                $taskNum++;
                echo "\n[{$taskNum}] Regenerating XML sitemaps...\n";
                echo $this->runSubTask('sitemap-generate', fn() => $this->sitemapGenerateInternal());
            }

            if ($hour === 5 && $minute === 0) {
                $taskNum++;
                echo "\n[{$taskNum}] Volunteer lapsed nudge...\n";
                echo $this->runSubTask('volunteer-lapsed-nudge', fn() => $this->volunteerLapsedNudgeInternal());

                $taskNum++;
                echo "\n[{$taskNum}] Volunteer expiry warnings...\n";
                echo $this->runSubTask('volunteer-expiry-warnings', fn() => $this->volunteerExpiryWarningsInternal());

                $taskNum++;
                echo "\n[{$taskNum}] Guardian consent expiry...\n";
                echo $this->runSubTask('volunteer-expire-consents', fn() => $this->volunteerExpireConsentsInternal());
            }

            if ($hour === 6 && $minute === 0) {
                $taskNum++;
                echo "\n[{$taskNum}] Recurring shift generation...\n";
                echo $this->runSubTask('recurring-shifts', fn() => $this->recurringShiftGenerationInternal());
            }

            if ($hour === 7 && $minute === 0) {
                $taskNum++;
                echo "\n[{$taskNum}] Abuse daily report...\n";
                echo $this->runSubTask('abuse-daily-report', fn() => $this->abuseDetectionDailyReportInternal());
            }

            if ($hour === 8 && $minute === 0) {
                $taskNum++;
                echo "\n[{$taskNum}] Processing daily digest...\n";
                echo $this->runSubTask('daily-digest', fn() => $this->processDigest('daily'));

                $taskNum++;
                echo "\n[{$taskNum}] Balance alerts...\n";
                echo $this->runSubTask('balance-alerts', fn() => $this->balanceAlertsInternal());

                $taskNum++;
                echo "\n[{$taskNum}] Goal reminders...\n";
                echo $this->runSubTask('goal-reminders', fn() => $this->goalRemindersInternal());

                $taskNum++;
                echo "\n[{$taskNum}] Listing expiry processing...\n";
                echo $this->runSubTask('listing-expiry', fn() => $this->listingExpiryInternal());

                $taskNum++;
                echo "\n[{$taskNum}] Listing expiry reminders...\n";
                echo $this->runSubTask('listing-expiry-reminders', fn() => $this->listingExpiryRemindersInternal());

                $taskNum++;
                echo "\n[{$taskNum}] Job expiry processing...\n";
                echo $this->runSubTask('job-expiry', fn() => $this->jobExpiryInternal());

                $taskNum++;
                echo "\n[{$taskNum}] Featured job expiry...\n";
                echo $this->runSubTask('featured-job-expiry', fn() => $this->featuredJobExpiryInternal());
            }

            if ($hour === 2 && $minute === 0) {
                $taskNum++;
                echo "\n[{$taskNum}] Inactive member detection...\n";
                echo $this->runSubTask('inactive-members', fn() => $this->inactiveMemberDetectionInternal());
            }

            if ($hour === 9 && $minute === 0) {
                $taskNum++;
                echo "\n[{$taskNum}] Match digest daily...\n";
                echo $this->runSubTask('match-digest-daily', fn() => $this->matchDigestInternal('daily'));

                // Fortnightly digest: runs every other Monday (weeks where ISO week number is even)
                if ($dayOfWeek === 1 && (int)date('W') % 2 === 0) {
                    $taskNum++;
                    echo "\n[{$taskNum}] Match digest fortnightly...\n";
                    echo $this->runSubTask('match-digest-fortnightly', fn() => $this->matchDigestInternal('fortnightly'));
                }
            }

            // ── WEEKLY ──
            if ($dayOfWeek === 5 && $hour === 17 && $minute === 0) {
                $taskNum++;
                echo "\n[{$taskNum}] Processing weekly digest...\n";
                echo $this->runSubTask('weekly-digest', fn() => $this->processDigest('weekly'));
            }

            if ($dayOfWeek === 0 && $hour === 2 && $minute === 0) {
                $taskNum++;
                echo "\n[{$taskNum}] Abuse cleanup...\n";
                echo $this->runSubTask('abuse-cleanup', fn() => $this->abuseDetectionCleanupInternal());
            }

            if ($dayOfWeek === 0 && $hour === 3 && $minute === 0) {
                $taskNum++;
                echo "\n[{$taskNum}] Gamification cleanup...\n";
                echo $this->runSubTask('gamification-cleanup', fn() => $this->gamificationCleanupInternal());
            }

            if ($dayOfWeek === 1 && $hour === 4 && $minute === 0) {
                $taskNum++;
                echo "\n[{$taskNum}] Gamification weekly digest...\n";
                echo $this->runSubTask('gamification-weekly-digest', fn() => $this->gamificationWeeklyDigestInternal());
            }

            if ($dayOfWeek === 1 && $hour === 9 && $minute === 0) {
                $taskNum++;
                echo "\n[{$taskNum}] Federation weekly digest...\n";
                echo $this->runSubTask('federation-weekly-digest', fn() => $this->processFederationWeeklyDigestInternal());

                $taskNum++;
                echo "\n[{$taskNum}] Group weekly digests...\n";
                echo $this->runSubTask('group-weekly-digest', fn() => $this->groupWeeklyDigestsInternal());

                $taskNum++;
                echo "\n[{$taskNum}] Match digest weekly...\n";
                echo $this->runSubTask('match-digest-weekly', fn() => $this->matchDigestInternal('weekly'));
            }

            // ── IDENTITY VERIFICATION TASKS ──
            // These were previously triggered via curl cron entries hitting HTTP endpoints
            // that no longer exist. Migrated into runAll() on 2026-04-02.

            // Every 6 hours: send verification reminders to users with incomplete verifications
            if ($minute === 0 && $hour % 6 === 0) {
                $taskNum++;
                echo "\n[{$taskNum}] Verification reminders...\n";
                echo $this->runSubTask('verification-reminders', function () {
                    $sent = \App\Services\Identity\RegistrationOrchestrationService::sendVerificationReminders();
                    echo "   Sent {$sent} verification reminders.\n";
                });
            }

            // Daily at 4:30 AM: expire abandoned verification sessions (72h+)
            if ($hour === 4 && $minute === 30) {
                $taskNum++;
                echo "\n[{$taskNum}] Expire abandoned verifications...\n";
                echo $this->runSubTask('expire-verifications', function () {
                    $expired = \App\Services\Identity\RegistrationOrchestrationService::expireAbandonedSessions();
                    echo "   Expired {$expired} abandoned verification sessions.\n";
                });
            }

            // Weekly (Sunday 3:30 AM): purge completed/expired sessions older than 180 days
            if ($dayOfWeek === 0 && $hour === 3 && $minute === 30) {
                $taskNum++;
                echo "\n[{$taskNum}] Purge old verification sessions...\n";
                echo $this->runSubTask('purge-verification-sessions', function () {
                    $purged = \App\Services\Identity\RegistrationOrchestrationService::purgeOldSessions(180);
                    echo "   Purged {$purged} old verification sessions.\n";
                });
            }

            echo "\n=== Cron Run Complete ({$taskNum} tasks checked) ===\n";
        } catch (\Throwable $e) {
            echo "\nFatal Error: " . $e->getMessage() . "\n";
            echo "Stack: " . $e->getTraceAsString() . "\n";
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
        // Race condition fix: atomically claim up to 50 pending items
        $claimSql = "UPDATE notification_queue
                     SET status = 'processing'
                     WHERE frequency = 'instant' AND status = 'pending'
                     ORDER BY created_at ASC
                     LIMIT 50";
        $claimed = DB::update($claimSql);

        if ($claimed === 0) {
            echo "   No pending instant notifications.\n";
            return;
        }

        // Fetch ONLY items we just claimed (limit matches claim batch)
        $sql = "SELECT q.*, u.email, u.name, u.tenant_id as user_tenant_id
                FROM notification_queue q
                JOIN users u ON q.user_id = u.id
                WHERE q.frequency = 'instant' AND q.status = 'processing'
                ORDER BY q.created_at ASC
                LIMIT 50";

        $items = array_map(fn($r) => (array) $r, DB::select($sql));

        $sent = 0;
        foreach ($items as $item) {
            // Per-item exception handling: one bad item must never affect others
            try {
                // Set tenant context so SMTP credentials are correct
                if (!empty($item['user_tenant_id'])) {
                    TenantContext::setById($item['user_tenant_id']);
                }

                $subject = self::resolveEmailSubject($item['activity_type'], $item['content_snippet'] ?? '');

                $body = $item['email_body'] ?? nl2br($item['content_snippet']);

                // Replace placeholder URLs in email body
                $baseUrl = TenantContext::getFrontendUrl();
                $basePath = TenantContext::getSlugPrefix();
                $body = str_replace('{{MATCHES_URL}}', $baseUrl . $basePath . '/matches', $body);
                if (!empty($item['link']) && strpos($item['link'], '/listings/') !== false) {
                    $body = str_replace('{{LISTING_URL}}', $baseUrl . $basePath . $item['link'], $body);
                }
                if (!empty($item['link']) && strpos($item['link'], '/exchanges/') !== false) {
                    $body = str_replace('{{EXCHANGE_URL}}', $baseUrl . $basePath . $item['link'], $body);
                }

                // Create mailer with tenant context for correct SMTP credentials
                $mailer = Mailer::forCurrentTenant();

                if ($mailer->send($item['email'], $subject, $body)) {
                    DB::update("UPDATE notification_queue SET status = 'sent', sent_at = NOW() WHERE id = ?", [$item['id']]);
                    $sent++;
                } else {
                    DB::update("UPDATE notification_queue SET status = 'failed' WHERE id = ?", [$item['id']]);
                }
            } catch (\Throwable $itemError) {
                // Mark this individual item as failed — do NOT leave in 'processing' state
                echo "   ERROR on item {$item['id']}: {$itemError->getMessage()}\n";
                try {
                    DB::update("UPDATE notification_queue SET status = 'failed' WHERE id = ?", [$item['id']]);
                } catch (\Throwable $dbError) {
                    // Last resort — log but don't crash the batch
                }
            }
        }

        // Clean up any stale 'processing' items older than 10 minutes (from crashed runs)
        DB::update("UPDATE notification_queue SET status = 'failed' WHERE status = 'processing' AND created_at < DATE_SUB(NOW(), INTERVAL 10 MINUTE)");

        echo "   Sent $sent instant notifications.\n";
    }

    /**
     * Internal method for newsletter queue processing
     */
    private function processNewsletterQueueInternal()
    {
        // Auto-retry: reset failed items that are older than 30 minutes back to pending
        // for one more attempt (max 1 retry). This acts as a dead-letter recovery.
        try {
            $retried = DB::update(
                "UPDATE newsletter_queue SET status = 'pending', error_message = CONCAT(IFNULL(error_message,''), ' [auto-retry]')
                 WHERE status = 'failed'
                 AND updated_at < DATE_SUB(NOW(), INTERVAL 30 MINUTE)
                 AND (error_message IS NULL OR error_message NOT LIKE '%[auto-retry]%')
                 LIMIT 50"
            );
            if ($retried > 0) {
                echo "   Auto-retried {$retried} previously failed queue items.\n";
            }
        } catch (\Throwable $e) {
            // Non-critical — continue with normal processing
        }

        $sql = "SELECT DISTINCT newsletter_id FROM newsletter_queue WHERE status = 'pending' LIMIT 5";
        $pending = array_map(fn($r) => (array) $r, DB::select($sql));

        if (empty($pending)) {
            echo "   No pending newsletter queues.\n";
            return;
        }

        foreach ($pending as $row) {
            $newsletter = \App\Models\Newsletter::findById($row['newsletter_id']);
            if ($newsletter && $newsletter['status'] === 'sending') {
                \App\Core\TenantContext::setById($newsletter['tenant_id']);

                // Process ALL pending items for this newsletter
                // Smaller batches with pauses for stability
                $batchSize = 25;
                $newsletterSent = 0;
                $newsletterFailed = 0;

                do {
                    $result = NewsletterService::processQueue($row['newsletter_id'], $batchSize);
                    $batchSent = $result['sent'] ?? 0;
                    $batchFailed = $result['failed'] ?? 0;
                    $newsletterSent += $batchSent;
                    $newsletterFailed += $batchFailed;

                    $stats = \App\Models\Newsletter::getQueueStats($row['newsletter_id']);
                    $morePending = ($stats['pending'] ?? 0) > 0;

                    // Pause between batches to prevent server overload
                    if ($morePending && $batchSent > 0) {
                        sleep(2);
                    }
                } while ($morePending && $batchSent > 0);

                echo "   Newsletter {$row['newsletter_id']}: Sent $newsletterSent, failed $newsletterFailed\n";
            }
        }
    }

    /**
     * Internal cleanup method
     */
    private function cleanupInternal()
    {
        try {
            DB::update("UPDATE users SET reset_token = NULL WHERE reset_token IS NOT NULL AND (reset_token_expiry IS NOT NULL AND reset_token_expiry < NOW())");
            echo "   Cleaned expired reset tokens.\n";
        } catch (\Exception $e) {
        }

        try {
            DB::delete("DELETE FROM notification_queue WHERE status = 'sent' AND sent_at < DATE_SUB(NOW(), INTERVAL 30 DAY)");
            echo "   Cleaned old notification queue.\n";
        } catch (\Exception $e) {
        }

        try {
            DB::delete("DELETE FROM newsletter_suppression_list WHERE expires_at IS NOT NULL AND expires_at < NOW()");
            echo "   Cleaned expired suppressions.\n";
        } catch (\Exception $e) {
        }

        // Clean expired password_resets table entries (older than 1 hour)
        try {
            DB::delete("DELETE FROM password_resets WHERE created_at < DATE_SUB(NOW(), INTERVAL 1 HOUR)");
            echo "   Cleaned expired password_resets.\n";
        } catch (\Exception $e) {
        }

        // Clean expired API tokens
        try {
            DB::delete("DELETE FROM api_tokens WHERE expires_at IS NOT NULL AND expires_at < NOW()");
            echo "   Cleaned expired API tokens.\n";
        } catch (\Exception $e) {
        }

        // Clean old match cache entries (older than 24 hours)
        try {
            DB::delete("DELETE FROM match_cache WHERE expires_at < NOW()");
            echo "   Cleaned expired match cache.\n";
        } catch (\Exception $e) {
        }

        // Clean old cron_logs entries (older than 30 days)
        try {
            DB::delete("DELETE FROM cron_logs WHERE executed_at < DATE_SUB(NOW(), INTERVAL 30 DAY)");
            echo "   Cleaned old cron logs.\n";
        } catch (\Exception $e) {
        }

        // Expire stale exchange requests stuck in pending_provider for 14+ days
        try {
            $expired = DB::update(
                "UPDATE exchange_requests SET status = 'expired', updated_at = NOW()
                 WHERE status = 'pending_provider'
                 AND created_at < DATE_SUB(NOW(), INTERVAL 14 DAY)"
            );
            if ($expired > 0) {
                echo "   Expired $expired stale exchange requests (pending_provider > 14 days).\n";
            }
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

            $users = array_map(fn($r) => (array) $r, DB::select($sql));

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
        // Scoped to active tenants only — deactivated communities should not receive digests
        $sql = "SELECT DISTINCT u.id, u.name, u.email, u.tenant_id, mp.notification_frequency
                FROM users u
                INNER JOIN tenants t ON u.tenant_id = t.id AND t.is_active = 1
                LEFT JOIN match_preferences mp ON u.id = mp.user_id
                WHERE u.status = 'active'
                AND u.id IN (SELECT DISTINCT user_id FROM listings WHERE status = 'active')
                AND (
                    (mp.notification_frequency = ? AND mp.notification_frequency IS NOT NULL)
                    OR (mp.notification_frequency IS NULL AND ? = 'fortnightly')
                )";

        try {
            $users = array_map(fn($r) => (array) $r, DB::select($sql, [$frequency, $frequency]));
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
                // Set tenant context for this user so notifications use correct branding
                TenantContext::setById($user['tenant_id']);

                // Get fresh matches for this user (24h daily, 7d weekly, 14d fortnightly)
                $lookbackHours = match ($frequency) {
                    'daily' => 24,
                    'weekly' => 168,
                    'fortnightly' => 336,
                    default => 24,
                };

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

            $newListings = array_map(fn($r) => (array) $r, DB::select($sql));

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

                $potentialUsers = array_map(fn($r) => (array) $r, DB::select($matchSql, [
                    $listing['category_id'],
                    $oppositeType,
                    $listing['user_id']
                ]));

                foreach ($potentialUsers as $user) {
                    // Calculate actual match score
                    $prefs = MatchingService::getPreferences($user['user_id']);

                    // Check if user wants hot match notifications
                    if (empty($prefs['notify_hot_matches']) || $prefs['notification_frequency'] === 'never') {
                        continue;
                    }

                    // Get user's listings to calculate proper match score
                    $userListings = array_map(fn($r) => (array) $r, DB::select(
                        "SELECT * FROM listings WHERE user_id = ? AND status = 'active'",
                        [$user['user_id']]
                    ));

                    if (empty($userListings)) continue;

                    // Calculate match score using the engine
                    $userData = User::findById($user['user_id']);
                    $matchResult = \App\Services\SmartMatchingEngine::calculateMatchScore(
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
                            DB::insert(
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

            $users = array_map(fn($r) => (array) $r, DB::select($sql));

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

    // ═══════════════════════════════════════════════════════════════
    // Internal helper methods for runAll() — all tasks below
    // ═══════════════════════════════════════════════════════════════

    /**
     * Iterate over all tenants, calling $callback($tenantId, $tenantSlug) for each.
     */
    private function forEachTenant(callable $callback): void
    {
        $tenants = array_map(fn($r) => (array) $r, DB::select("SELECT id, slug FROM tenants WHERE is_active = 1"));
        foreach ($tenants as $tenant) {
            try {
                TenantContext::setById($tenant['id']);
                $callback($tenant['id'], $tenant['slug']);
            } catch (\Throwable $e) {
                echo "   [Tenant {$tenant['slug']}] Error: " . $e->getMessage() . "\n";
            }
        }
    }

    /**
     * Clean old sessions and expired tokens (hourly)
     */
    private function cleanSessionsAndTokensInternal(): void
    {
        try {
            DB::delete("DELETE FROM sessions WHERE last_activity < DATE_SUB(NOW(), INTERVAL 24 HOUR)");
            echo "   Cleaned old sessions.\n";
        } catch (\Exception $e) {
            echo "   Sessions: skipped (" . $e->getMessage() . ")\n";
        }

        try {
            DB::update("UPDATE users SET reset_token = NULL WHERE reset_token IS NOT NULL AND (reset_token_expiry IS NOT NULL AND reset_token_expiry < NOW())");
            echo "   Cleaned expired reset tokens.\n";
        } catch (\Exception $e) {
            echo "   Reset tokens: skipped.\n";
        }
    }

    /**
     * Expire monitoring restrictions that have passed their expiry date (hourly)
     */
    private function expireMonitoringRestrictionsInternal(): void
    {
        try {
            $totalExpired = 0;
            $this->forEachTenant(function ($tenantId, $slug) use (&$totalExpired) {
                $expired = BrokerMessageVisibilityService::expireMonitoringBatch();
                if ($expired > 0) {
                    echo "   [{$slug}] Expired {$expired} monitoring restriction(s).\n";
                    $totalExpired += $expired;
                }
            });
            echo "   Total expired: {$totalExpired}\n";
        } catch (\Throwable $e) {
            echo "   Error: " . $e->getMessage() . "\n";
        }
    }

    /**
     * Batch geocode users and listings (every 30 min)
     */
    private function geocodeBatchInternal(): void
    {
        $this->forEachTenant(function ($tenantId, $slug) {
            try {
                $userResults = GeocodingService::batchGeocodeUsers(50);
                if ($userResults['processed'] > 0) {
                    echo "   [{$slug}] Users: {$userResults['processed']} processed, {$userResults['success']} success.\n";
                }

                $listingResults = GeocodingService::batchGeocodeListings(50);
                if ($listingResults['processed'] > 0) {
                    echo "   [{$slug}] Listings: {$listingResults['processed']} processed, {$listingResults['success']} success.\n";
                }
            } catch (\Throwable $e) {
                echo "   [{$slug}] Geocoding error: " . $e->getMessage() . "\n";
            }
        });
    }

    /**
     * Warm match cache for random users (every 30 min at :30)
     */
    private function warmMatchCacheInternal(): void
    {
        try {
            $totalCached = 0;
            $this->forEachTenant(function ($tenantId, $slug) use (&$totalCached) {
                $result = SmartMatchingEngine::warmUpCache(20);
                $totalCached += $result['cached'] ?? 0;
            });
            echo "   Cached $totalCached matches across all tenants.\n";
        } catch (\Throwable $e) {
            echo "   Error: " . $e->getMessage() . "\n";
        }
    }

    /**
     * Hot match notifications for new listings (hourly)
     */
    private function notifyHotMatchesInternal(): void
    {
        try {
            $totalNotified = 0;
            $this->forEachTenant(function ($tenantId, $slug) use (&$totalNotified) {
                $sql = "SELECT l.*, c.name as category_name
                        FROM listings l
                        LEFT JOIN categories c ON l.category_id = c.id
                        WHERE l.tenant_id = ? AND l.status = 'active'
                        AND l.created_at > DATE_SUB(NOW(), INTERVAL 1 HOUR)
                        LIMIT 50";
                $newListings = array_map(fn($r) => (array) $r, DB::select($sql, [$tenantId]));

                foreach ($newListings as $listing) {
                    $oppositeType = $listing['type'] === 'offer' ? 'request' : 'offer';
                    $matchSql = "SELECT DISTINCT l2.user_id
                                 FROM listings l2
                                 WHERE l2.category_id = ? AND l2.type = ? AND l2.status = 'active'
                                 AND l2.user_id != ? AND l2.tenant_id = ?
                                 LIMIT 20";
                    $potentialMatches = array_map(fn($r) => (array) $r, DB::select($matchSql, [
                        $listing['category_id'], $oppositeType, $listing['user_id'], $tenantId
                    ]));

                    foreach ($potentialMatches as $match) {
                        try {
                            $count = MatchingService::notifyNewMatches($match['user_id']);
                            $totalNotified += $count;
                        } catch (\Throwable $e) {
                            // Continue
                        }
                    }
                }
            });
            echo "   Sent $totalNotified hot match notifications.\n";
        } catch (\Throwable $e) {
            echo "   Error: " . $e->getMessage() . "\n";
        }
    }

    /**
     * Process gamification campaigns (hourly)
     */
    private function gamificationCampaignsInternal(): void
    {
        try {
            $this->forEachTenant(function ($tenantId, $slug) {
                if (!TenantContext::hasFeature('gamification')) return;
                $results = AchievementCampaignService::processRecurringCampaigns();
                $awarded = 0;
                foreach ($results as $result) {
                    $awarded += $result['awarded'] ?? 0;
                }
                if ($awarded > 0) {
                    echo "   [$slug] Awarded campaigns to $awarded users.\n";
                }
            });
            echo "   Campaigns processed.\n";
        } catch (\Throwable $e) {
            echo "   Error: " . $e->getMessage() . "\n";
        }
    }

    /**
     * Abuse detection checks (hourly)
     */
    private function abuseDetectionInternal(): void
    {
        try {
            $totalAlerts = 0;
            $this->forEachTenant(function ($tenantId, $slug) use (&$totalAlerts) {
                $results = AbuseDetectionService::runAllChecks();
                $count = array_sum($results);
                $totalAlerts += $count;
                if ($count > 0) {
                    echo "   [$slug] $count new abuse alerts.\n";
                }
            });
            echo "   Abuse detection complete ($totalAlerts total alerts).\n";
        } catch (\Throwable $e) {
            echo "   Error: " . $e->getMessage() . "\n";
        }
    }

    /**
     * Check and expire gamification challenges (hourly at :30)
     */
    private function gamificationChallengeCheckInternal(): void
    {
        try {
            $this->forEachTenant(function ($tenantId, $slug) {
                if (!TenantContext::hasFeature('gamification')) return;

                $expiredCount = DB::update(
                    "UPDATE challenges SET is_active = 0 WHERE tenant_id = ? AND end_date < CURDATE() AND is_active = 1",
                    [$tenantId]
                );

                DB::update(
                    "UPDATE friend_challenges SET status = 'expired' WHERE tenant_id = ? AND end_date < CURDATE() AND status IN ('pending', 'active')",
                    [$tenantId]
                );

                if ($expiredCount > 0) {
                    echo "   [$slug] Expired {$expiredCount} challenges.\n";
                }
            });
            echo "   Challenge check complete.\n";
        } catch (\Throwable $e) {
            echo "   Error: " . $e->getMessage() . "\n";
        }
    }

    /**
     * Leaderboard snapshot (daily midnight)
     */
    private function gamificationLeaderboardSnapshotInternal(): void
    {
        try {
            $this->forEachTenant(function ($tenantId, $slug) {
                if (!TenantContext::hasFeature('gamification')) return;

                DB::insert(
                    "INSERT IGNORE INTO weekly_rank_snapshots (tenant_id, user_id, rank_position, xp, snapshot_date)
                     SELECT ?, id, @rank := @rank + 1, xp, CURDATE()
                     FROM users, (SELECT @rank := 0) r
                     WHERE tenant_id = ? AND is_approved = 1
                     ORDER BY xp DESC",
                    [$tenantId, $tenantId]
                );

                // Finalize ended seasons
                $endedSeasons = array_map(fn($r) => (array) $r, DB::select(
                    "SELECT id FROM leaderboard_seasons WHERE tenant_id = ? AND end_date < CURDATE() AND is_finalized = 0",
                    [$tenantId]
                ));

                foreach ($endedSeasons as $season) {
                    $topUsers = array_map(fn($r) => (array) $r, DB::select(
                        "SELECT user_id, rank_position FROM weekly_rank_snapshots
                         WHERE tenant_id = ? AND snapshot_date = (SELECT end_date FROM leaderboard_seasons WHERE id = ?)
                         ORDER BY rank_position ASC LIMIT 10",
                        [$tenantId, $season['id']]
                    ));

                    $rewards = [1 => 500, 2 => 300, 3 => 200, 4 => 100, 5 => 100];
                    foreach ($topUsers as $user) {
                        $xp = $rewards[$user['rank_position']] ?? 50;
                        GamificationService::awardXP($user['user_id'], $xp, 'season_reward', "Season #{$season['id']} rank #{$user['rank_position']}");
                    }

                    DB::update("UPDATE leaderboard_seasons SET is_finalized = 1 WHERE id = ?", [$season['id']]);
                    echo "   [$slug] Finalized season {$season['id']}.\n";
                }
            });
            echo "   Leaderboard snapshots complete.\n";
        } catch (\Throwable $e) {
            echo "   Error: " . $e->getMessage() . "\n";
        }
    }

    /**
     * Streak milestone badges (daily 1am)
     */
    private function gamificationStreakMilestonesInternal(): void
    {
        try {
            $milestones = [7, 14, 30, 60, 90, 180, 365];
            $this->forEachTenant(function ($tenantId, $slug) use ($milestones) {
                if (!TenantContext::hasFeature('gamification')) return;

                foreach ($milestones as $days) {
                    $users = array_map(fn($r) => (array) $r, DB::select(
                        "SELECT id FROM users WHERE tenant_id = ? AND login_streak = ?",
                        [$tenantId, $days]
                    ));

                    foreach ($users as $user) {
                        GamificationService::awardBadge($user['id'], "streak_{$days}");
                    }

                    if (count($users) > 0) {
                        echo "   [$slug] Awarded {$days}-day streak to " . count($users) . " users.\n";
                    }
                }
            });
            echo "   Streak milestones complete.\n";
        } catch (\Throwable $e) {
            echo "   Error: " . $e->getMessage() . "\n";
        }
    }

    /**
     * Gamification daily tasks: streak resets, daily bonuses, badge checks (daily 3am)
     */
    private function gamificationDailyInternal(): void
    {
        try {
            $this->forEachTenant(function ($tenantId, $slug) {
                if (!TenantContext::hasFeature('gamification')) return;

                // Reset streaks for inactive users
                $resetCount = DB::update(
                    "UPDATE users SET login_streak = 0
                     WHERE tenant_id = ? AND COALESCE(last_login_at, created_at) < DATE_SUB(CURDATE(), INTERVAL 1 DAY) AND login_streak > 0",
                    [$tenantId]
                );
                echo "   [$slug] Reset {$resetCount} streaks.\n";

                // Award daily bonuses
                $activeUsers = array_map(fn($r) => (array) $r, DB::select(
                    "SELECT id FROM users
                     WHERE tenant_id = ? AND DATE(COALESCE(last_login_at, created_at)) = CURDATE()
                     AND id NOT IN (SELECT user_id FROM daily_rewards WHERE tenant_id = ? AND reward_date = CURDATE())",
                    [$tenantId, $tenantId]
                ));

                $bonuses = 0;
                foreach ($activeUsers as $user) {
                    try {
                        DailyRewardService::checkAndAwardDailyReward($user['id']);
                        $bonuses++;
                    } catch (\Throwable $e) {
                        // Continue
                    }
                }
                echo "   [$slug] Awarded $bonuses daily bonuses.\n";

                // Badge checks for recently active users
                $users = array_map(fn($r) => (array) $r, DB::select(
                    "SELECT id FROM users
                     WHERE tenant_id = ? AND is_approved = 1
                     AND COALESCE(last_login_at, created_at) > DATE_SUB(NOW(), INTERVAL 24 HOUR)
                     LIMIT 200",
                    [$tenantId]
                ));

                $badges = 0;
                foreach ($users as $user) {
                    try {
                        $before = DB::selectOne("SELECT COUNT(*) as c FROM user_badges WHERE user_id = ?", [$user['id']])->c;
                        GamificationService::runAllBadgeChecks($user['id']);
                        $after = DB::selectOne("SELECT COUNT(*) as c FROM user_badges WHERE user_id = ?", [$user['id']])->c;
                        $badges += ($after - $before);
                    } catch (\Throwable $e) {
                        // Continue
                    }
                }
                echo "   [$slug] Processed " . count($users) . " users, awarded $badges badges.\n";
            });
            echo "   Gamification daily complete.\n";
        } catch (\Throwable $e) {
            echo "   Error: " . $e->getMessage() . "\n";
        }
    }

    /**
     * Abuse detection daily report (daily 7am)
     */
    private function abuseDetectionDailyReportInternal(): void
    {
        try {
            $this->forEachTenant(function ($tenantId, $slug) {
                $counts = AbuseDetectionService::getAlertCounts();
                $newAlerts = $counts['new'] ?? 0;
                $reviewing = $counts['reviewing'] ?? 0;

                if ($newAlerts > 0 || $reviewing > 0) {
                    echo "   [$slug] $newAlerts new alerts, $reviewing under review.\n";

                    $criticalRow = DB::selectOne(
                        "SELECT COUNT(*) as cnt FROM abuse_alerts WHERE tenant_id = ? AND status = 'new' AND severity IN ('critical', 'high')",
                        [$tenantId]
                    );
                    $critical = $criticalRow->cnt ?? 0;

                    if ($critical > 0) {
                        echo "   [$slug] *** $critical CRITICAL/HIGH severity alerts! ***\n";
                    }
                }
            });
            echo "   Abuse daily report complete.\n";
        } catch (\Throwable $e) {
            echo "   Error: " . $e->getMessage() . "\n";
        }
    }

    /**
     * Balance alerts for organisation wallets (daily 8am)
     */
    private function balanceAlertsInternal(): void
    {
        try {
            $totalAlerts = 0;
            $this->forEachTenant(function ($tenantId, $slug) use (&$totalAlerts) {
                $alertsSent = BalanceAlertService::checkAllBalances();
                $totalAlerts += $alertsSent;
                if ($alertsSent > 0) {
                    echo "   [$slug] Sent $alertsSent balance alerts.\n";
                }
            });
            echo "   Balance alerts complete ($totalAlerts total).\n";
        } catch (\Throwable $e) {
            echo "   Error: " . $e->getMessage() . "\n";
        }
    }

    /**
     * Match digest (daily or weekly)
     */
    private function matchDigestInternal(string $frequency): void
    {
        try {
            $totalSent = 0;
            $lookbackHours = match ($frequency) {
                'daily' => 24,
                'weekly' => 168,
                'fortnightly' => 336,
                default => 24,
            };

            $this->forEachTenant(function ($tenantId, $slug) use ($frequency, $lookbackHours, &$totalSent) {
                $sql = "SELECT DISTINCT u.id, u.name
                        FROM users u
                        LEFT JOIN match_preferences mp ON u.id = mp.user_id
                        WHERE u.tenant_id = ? AND u.status = 'active'
                        AND u.id IN (SELECT DISTINCT user_id FROM listings WHERE status = 'active')
                        AND (
                            (mp.notification_frequency = ? AND mp.notification_frequency IS NOT NULL)
                            OR (mp.notification_frequency IS NULL AND ? = 'fortnightly')
                        )
                        LIMIT 100";

                $users = array_map(fn($r) => (array) $r, DB::select($sql, [$tenantId, $frequency, $frequency]));

                foreach ($users as $user) {
                    try {
                        $matches = MatchingService::getSuggestionsForUser($user['id'], 20, [
                            'created_after' => date('Y-m-d H:i:s', strtotime("-{$lookbackHours} hours"))
                        ]);
                        if (!empty($matches)) {
                            NotificationDispatcher::dispatchMatchDigest($user['id'], $matches, $frequency);
                            $totalSent++;
                        }
                    } catch (\Throwable $e) {
                        // Continue
                    }
                }
            });
            echo "   Sent $frequency match digest to $totalSent users.\n";
        } catch (\Throwable $e) {
            echo "   Error: " . $e->getMessage() . "\n";
        }
    }

    /**
     * Abuse detection cleanup (Sunday 2am)
     */
    private function abuseDetectionCleanupInternal(): void
    {
        try {
            $this->forEachTenant(function ($tenantId, $slug) {
                $archivedCount = DB::delete(
                    "DELETE FROM abuse_alerts WHERE tenant_id = ? AND status IN ('resolved', 'dismissed') AND resolved_at < DATE_SUB(NOW(), INTERVAL 90 DAY)",
                    [$tenantId]
                );

                $autoDismissedCount = DB::update(
                    "UPDATE abuse_alerts SET status = 'dismissed', resolved_at = NOW(), resolution_notes = 'Auto-dismissed (aged out)'
                     WHERE tenant_id = ? AND status = 'new' AND severity = 'low' AND created_at < DATE_SUB(NOW(), INTERVAL 30 DAY)",
                    [$tenantId]
                );

                if ($archivedCount > 0 || $autoDismissedCount > 0) {
                    echo "   [$slug] Archived {$archivedCount}, auto-dismissed {$autoDismissedCount}.\n";
                }
            });
            echo "   Abuse cleanup complete.\n";
        } catch (\Throwable $e) {
            echo "   Error: " . $e->getMessage() . "\n";
        }
    }

    /**
     * Gamification cleanup: old XP notifications, campaign awards, analytics (Sunday 3am)
     */
    private function gamificationCleanupInternal(): void
    {
        try {
            $this->forEachTenant(function ($tenantId, $slug) {
                if (!TenantContext::hasFeature('gamification')) return;

                DB::delete("DELETE FROM xp_notifications WHERE tenant_id = ? AND created_at < DATE_SUB(NOW(), INTERVAL 7 DAY)", [$tenantId]);
                DB::delete("DELETE FROM campaign_awards WHERE tenant_id = ? AND awarded_at < DATE_SUB(NOW(), INTERVAL 1 YEAR)", [$tenantId]);
                DB::delete("DELETE FROM achievement_analytics WHERE tenant_id = ? AND date < DATE_SUB(CURDATE(), INTERVAL 2 YEAR)", [$tenantId]);
            });
            echo "   Gamification cleanup complete.\n";
        } catch (\Throwable $e) {
            echo "   Error: " . $e->getMessage() . "\n";
        }
    }

    /**
     * Gamification weekly digest emails (Monday 4am)
     *
     * NOTE: sendWeeklyDigests() iterates all tenants internally,
     * so we must NOT wrap it in forEachTenant() — that caused a nested
     * loop where every user received one copy per tenant (11× duplicates).
     */
    private function gamificationWeeklyDigestInternal(): void
    {
        try {
            $result = app(GamificationEmailService::class)->sendWeeklyDigests();
            echo "   Sent {$result['sent']}, skipped {$result['skipped']}, errors {$result['errors']}.\n";
            echo "   Gamification weekly digest complete.\n";
        } catch (\Throwable $e) {
            echo "   Error: " . $e->getMessage() . "\n";
        }
    }

    /**
     * Group weekly digest emails to group owners (Monday 9am)
     *
     * NOTE: GroupReportingService does not exist yet — this is a stub.
     * When implemented, do NOT wrap in forEachTenant() if the service
     * iterates tenants internally (same bug that caused 11× duplicate
     * gamification emails — see gamificationWeeklyDigestInternal).
     */
    private function groupWeeklyDigestsInternal(): void
    {
        try {
            if (!class_exists(\App\Services\GroupReportingService::class)) {
                echo "   GroupReportingService not implemented — skipping.\n";
                return;
            }

            $stats = GroupReportingService::sendAllWeeklyDigests();
            echo "   Sent {$stats['sent']}/{$stats['total_groups']} group digests.\n";
            echo "   Group weekly digests complete.\n";
        } catch (\Throwable $e) {
            echo "   Error: " . $e->getMessage() . "\n";
        }
    }

    /**
     * Goal reminders — send due reminders across all tenants (daily 8am)
     */
    private function goalRemindersInternal(): void
    {
        try {
            $totalSent = 0;
            $this->forEachTenant(function ($tenantId, $slug) use (&$totalSent) {
                if (!TenantContext::hasFeature('goals')) return;

                $sent = GoalReminderService::sendDueReminders();
                $totalSent += $sent;
                if ($sent > 0) {
                    echo "   [$slug] Sent $sent goal reminders.\n";
                }
            });
            echo "   Goal reminders complete ($totalSent total).\n";
        } catch (\Throwable $e) {
            echo "   Error: " . $e->getMessage() . "\n";
        }
    }

    /**
     * Inactive member detection — flag inactive/dormant members (daily 2am)
     */
    private function inactiveMemberDetectionInternal(): void
    {
        try {
            $totalFlagged = 0;
            $this->forEachTenant(function ($tenantId, $slug) use (&$totalFlagged) {
                $result = InactiveMemberService::detectInactive($tenantId);
                $totalFlagged += $result['total_flagged'];
                if ($result['total_flagged'] > 0) {
                    echo "   [$slug] Flagged {$result['flagged_inactive']} inactive, {$result['flagged_dormant']} dormant.\n";
                }
            });
            echo "   Inactive member detection complete ($totalFlagged total flagged).\n";
        } catch (\Throwable $e) {
            echo "   Error: " . $e->getMessage() . "\n";
        }
    }

    /**
     * Event reminders — send 24h and 1h reminders (every 15 minutes)
     */
    private function eventRemindersInternal(): void
    {
        try {
            $totalSent = 0;
            $service = app(EventReminderService::class);
            $this->forEachTenant(function ($tenantId, $slug) use (&$totalSent, $service) {
                if (!TenantContext::hasFeature('events')) return;

                $sent = $service->sendDueReminders($tenantId);
                $totalSent += $sent;
                if ($sent > 0) {
                    echo "   [$slug] Sent {$sent} event reminders.\n";
                }
            });

            // Cleanup old reminder tracking records (older than 90 days)
            try {
                DB::delete("DELETE FROM event_reminder_sent WHERE sent_at < DATE_SUB(NOW(), INTERVAL 90 DAY)");
            } catch (\Exception $e) {
                // Table may not exist yet
            }

            echo "   Event reminders complete ($totalSent sent).\n";
        } catch (\Throwable $e) {
            echo "   Error: " . $e->getMessage() . "\n";
        }
    }

    /**
     * Listing expiry — expire overdue listings across all tenants (daily 8am)
     */
    private function listingExpiryInternal(): void
    {
        try {
            $result = ListingExpiryService::processAllTenants();
            echo "   Listing expiry complete ({$result['total_expired']} expired across {$result['tenants_processed']} tenants).\n";
        } catch (\Throwable $e) {
            echo "   Error: " . $e->getMessage() . "\n";
        }
    }

    /**
     * Listing expiry reminders — send 3-day warning emails (daily 8am)
     */
    private function listingExpiryRemindersInternal(): void
    {
        try {
            $totalSent = 0;
            $service = app(ListingExpiryReminderService::class);
            $this->forEachTenant(function ($tenantId, $slug) use (&$totalSent, $service) {
                $result = $service->sendDueReminders();
                $totalSent += $result['sent'];
                if ($result['sent'] > 0) {
                    echo "   [$slug] Sent {$result['sent']} listing expiry reminders.\n";
                }
            });
            $service->cleanupOldRecords();
            echo "   Listing expiry reminders complete ($totalSent total).\n";
        } catch (\Throwable $e) {
            echo "   Error: " . $e->getMessage() . "\n";
        }
    }

    /**
     * J7: Expire overdue job vacancies across all tenants
     */
    private function jobExpiryInternal(): void
    {
        try {
            $totalExpired = 0;
            $this->forEachTenant(function (int $tenantId, string $slug) use (&$totalExpired) {
                $expired = JobVacancyService::expireOverdueJobs();
                $totalExpired += $expired;
                if ($expired > 0) {
                    echo "   [$slug] Expired $expired overdue jobs.\n";
                }
            });
            echo "   Job expiry complete ($totalExpired total).\n";
        } catch (\Throwable $e) {
            echo "   Error: " . $e->getMessage() . "\n";
        }
    }

    /**
     * J7: Expire featured status on jobs past their featured_until date
     */
    private function featuredJobExpiryInternal(): void
    {
        try {
            $totalExpired = 0;
            $this->forEachTenant(function (int $tenantId, string $slug) use (&$totalExpired) {
                $expired = JobVacancyService::expireFeaturedJobs();
                $totalExpired += $expired;
                if ($expired > 0) {
                    echo "   [$slug] Expired featured status on $expired jobs.\n";
                }
            });
            echo "   Featured job expiry complete ($totalExpired total).\n";
        } catch (\Throwable $e) {
            echo "   Error: " . $e->getMessage() . "\n";
        }
    }

    /**
     * V8: Generate upcoming recurring shift occurrences across all tenants
     */
    private function recurringShiftGenerationInternal(): void
    {
        try {
            $totalGenerated = 0;
            $totalPatterns = 0;
            $this->forEachTenant(function (int $tenantId, string $slug) use (&$totalGenerated, &$totalPatterns) {
                $result = RecurringShiftService::processAllPatterns(14);
                $totalGenerated += $result['shifts_generated'];
                $totalPatterns += $result['patterns_processed'];
                if ($result['shifts_generated'] > 0) {
                    echo "   [$slug] Generated {$result['shifts_generated']} shifts from {$result['patterns_processed']} patterns.\n";
                }
            });
            echo "   Recurring shift generation complete ($totalGenerated shifts from $totalPatterns patterns).\n";
        } catch (\Throwable $e) {
            echo "   Error: " . $e->getMessage() . "\n";
        }
    }

    // ─── Identity Verification Cron ─────────────────────────────────────

    /**
     * Send reminder emails for abandoned verification sessions (24h+ old).
     * Schedule: every hour.
     */
    public function verificationReminders()
    {
        $this->checkAccess();
        try {
            $sent = \App\Services\Identity\RegistrationOrchestrationService::sendVerificationReminders();
            echo json_encode(['success' => true, 'reminders_sent' => $sent]);
        } catch (\Throwable $e) {
            echo json_encode(['success' => false, 'error' => $e->getMessage()]);
        }
    }

    /**
     * Expire verification sessions older than 72 hours.
     * Schedule: daily.
     */
    public function expireVerifications()
    {
        $this->checkAccess();
        try {
            $expired = \App\Services\Identity\RegistrationOrchestrationService::expireAbandonedSessions();
            echo json_encode(['success' => true, 'sessions_expired' => $expired]);
        } catch (\Throwable $e) {
            echo json_encode(['success' => false, 'error' => $e->getMessage()]);
        }
    }

    /**
     * Purge completed/expired verification sessions older than 180 days.
     * Audit events are retained separately. Schedule: weekly.
     */
    public function purgeVerificationSessions()
    {
        $this->checkAccess();
        try {
            $purged = \App\Services\Identity\RegistrationOrchestrationService::purgeOldSessions(180);
            echo json_encode(['success' => true, 'sessions_purged' => $purged]);
        } catch (\Throwable $e) {
            echo json_encode(['success' => false, 'error' => $e->getMessage()]);
        }
    }

    // ─── Volunteer Reminders Cron ───────────────────────────────────────

    /**
     * Send pre-shift reminders (24h and 2h before). Schedule: every 30 minutes.
     */
    public function volunteerPreShiftReminders()
    {
        $this->checkAccess();
        $this->startJob('volunteer_pre_shift_reminders');
        try {
            $sent = \App\Services\VolunteerReminderService::sendPreShiftReminders();
            $this->logJob('success', "Sent {$sent} pre-shift reminders");
            echo json_encode(['success' => true, 'reminders_sent' => $sent]);
        } catch (\Throwable $e) {
            $this->logJob('error', $e->getMessage());
            echo json_encode(['success' => false, 'error' => $e->getMessage()]);
        }
    }

    /**
     * Send post-shift feedback requests. Schedule: every hour.
     */
    public function volunteerPostShiftFeedback()
    {
        $this->checkAccess();
        $this->startJob('volunteer_post_shift_feedback');
        try {
            $sent = \App\Services\VolunteerReminderService::sendPostShiftFeedback();
            $this->logJob('success', "Sent {$sent} feedback requests");
            echo json_encode(['success' => true, 'feedback_sent' => $sent]);
        } catch (\Throwable $e) {
            $this->logJob('error', $e->getMessage());
            echo json_encode(['success' => false, 'error' => $e->getMessage()]);
        }
    }

    /**
     * Nudge lapsed volunteers. Schedule: daily.
     */
    public function volunteerLapsedNudge()
    {
        $this->checkAccess();
        $this->startJob('volunteer_lapsed_nudge');
        try {
            $sent = \App\Services\VolunteerReminderService::nudgeLapsedVolunteers();
            $this->logJob('success', "Nudged {$sent} lapsed volunteers");
            echo json_encode(['success' => true, 'nudges_sent' => $sent]);
        } catch (\Throwable $e) {
            $this->logJob('error', $e->getMessage());
            echo json_encode(['success' => false, 'error' => $e->getMessage()]);
        }
    }

    /**
     * Send credential and training expiry warnings. Schedule: daily.
     */
    public function volunteerExpiryWarnings()
    {
        $this->checkAccess();
        $this->startJob('volunteer_expiry_warnings');
        try {
            $credentials = \App\Services\VolunteerReminderService::sendCredentialExpiryWarnings();
            $training = \App\Services\VolunteerReminderService::sendTrainingExpiryWarnings();
            $total = $credentials + $training;
            $this->logJob('success', "Sent {$total} expiry warnings (creds: {$credentials}, training: {$training})");
            echo json_encode(['success' => true, 'credential_warnings' => $credentials, 'training_warnings' => $training]);
        } catch (\Throwable $e) {
            $this->logJob('error', $e->getMessage());
            echo json_encode(['success' => false, 'error' => $e->getMessage()]);
        }
    }

    /**
     * Expire old guardian consents past their expiry date. Schedule: daily.
     */
    public function volunteerExpireConsents()
    {
        $this->checkAccess();
        $this->startJob('volunteer_expire_consents');
        try {
            $expired = \App\Services\GuardianConsentService::expireOldConsents();
            $this->logJob('success', "Expired {$expired} consents");
            echo json_encode(['success' => true, 'consents_expired' => $expired]);
        } catch (\Throwable $e) {
            $this->logJob('error', $e->getMessage());
            echo json_encode(['success' => false, 'error' => $e->getMessage()]);
        }
    }

    // ─── Outbound Webhook Retry Cron ────────────────────────────────────

    /**
     * Retry failed outbound webhook deliveries. Schedule: every 5 minutes.
     */
    public function retryFailedWebhooks()
    {
        $this->checkAccess();
        $this->startJob('retry_failed_webhooks');
        try {
            $retried = \App\Services\WebhookDispatchService::retryFailed();
            $this->logJob('success', "Retried {$retried} failed webhooks");
            echo json_encode(['success' => true, 'webhooks_retried' => $retried]);
        } catch (\Throwable $e) {
            $this->logJob('error', $e->getMessage());
            echo json_encode(['success' => false, 'error' => $e->getMessage()]);
        }
    }

    // ─── Volunteer Cron Internal Methods (for runAll scheduler) ─────────

    private function volunteerPreShiftRemindersInternal(): void
    {
        $this->forEachTenant(function ($tenantId, $slug) {
            try {
                $sent = \App\Services\VolunteerReminderService::sendPreShiftReminders();
                if ($sent > 0) {
                    echo "   [{$slug}] Pre-shift reminders: {$sent} sent.\n";
                }
            } catch (\Throwable $e) {
                echo "   [{$slug}] Pre-shift error: {$e->getMessage()}\n";
            }
        });
    }

    private function volunteerPostShiftFeedbackInternal(): void
    {
        $this->forEachTenant(function ($tenantId, $slug) {
            try {
                $sent = \App\Services\VolunteerReminderService::sendPostShiftFeedback();
                if ($sent > 0) {
                    echo "   [{$slug}] Post-shift feedback: {$sent} sent.\n";
                }
            } catch (\Throwable $e) {
                echo "   [{$slug}] Post-shift error: {$e->getMessage()}\n";
            }
        });
    }

    private function volunteerLapsedNudgeInternal(): void
    {
        $this->forEachTenant(function ($tenantId, $slug) {
            try {
                $sent = \App\Services\VolunteerReminderService::nudgeLapsedVolunteers();
                if ($sent > 0) {
                    echo "   [{$slug}] Lapsed nudges: {$sent} sent.\n";
                }
            } catch (\Throwable $e) {
                echo "   [{$slug}] Lapsed nudge error: {$e->getMessage()}\n";
            }
        });
    }

    private function volunteerExpiryWarningsInternal(): void
    {
        $this->forEachTenant(function ($tenantId, $slug) {
            try {
                $creds = \App\Services\VolunteerReminderService::sendCredentialExpiryWarnings();
                $train = \App\Services\VolunteerReminderService::sendTrainingExpiryWarnings();
                $total = $creds + $train;
                if ($total > 0) {
                    echo "   [{$slug}] Expiry warnings: {$total} sent (creds: {$creds}, training: {$train}).\n";
                }
            } catch (\Throwable $e) {
                echo "   [{$slug}] Expiry warning error: {$e->getMessage()}\n";
            }
        });
    }

    private function volunteerExpireConsentsInternal(): void
    {
        // Guardian consent expiry is not tenant-scoped (runs across all tenants in one query)
        try {
            $expired = \App\Services\GuardianConsentService::expireOldConsents();
            if ($expired > 0) {
                echo "   Expired {$expired} guardian consents.\n";
            }
        } catch (\Throwable $e) {
            echo "   Consent expiry error: {$e->getMessage()}\n";
        }
    }

    private function retryFailedWebhooksInternal(): void
    {
        $this->forEachTenant(function ($tenantId, $slug) {
            try {
                $retried = \App\Services\WebhookDispatchService::retryFailed();
                if ($retried > 0) {
                    echo "   [{$slug}] Webhook retries: {$retried}.\n";
                }
            } catch (\Throwable $e) {
                echo "   [{$slug}] Webhook retry error: {$e->getMessage()}\n";
            }
        });
    }

    /**
     * Regenerate XML sitemaps for all active tenants.
     * Clears cache and warms fresh sitemaps so crawlers get up-to-date URLs.
     */
    private function sitemapGenerateInternal(): void
    {
        try {
            $service = app(SitemapService::class);
            $service->clearCache();

            $tenants = DB::select("SELECT id, name, slug FROM tenants WHERE is_active = 1 ORDER BY id");
            $totalUrls = 0;

            foreach ($tenants as $tenant) {
                $xml = $service->generateForTenant((int) $tenant->id);
                $urlCount = substr_count($xml, '<url>');
                $totalUrls += $urlCount;
                $slug = $tenant->slug ?: 'main';
                echo "   [{$slug}] {$urlCount} URLs\n";
            }

            $service->generateIndex();
            echo "   Sitemap index + " . count($tenants) . " tenant sitemaps generated ({$totalUrls} total URLs).\n";
        } catch (\Throwable $e) {
            echo "   Sitemap generation error: {$e->getMessage()}\n";
        }
    }

    // ─── Admin Panel Wrapper Methods ────────────────────────────────────
    // Thin wrappers that let the admin panel manually trigger tasks that
    // normally run inside runAll(). Added 2026-04-02 to complete the
    // migration from old PHP scripts to CronJobRunner.

    public function gamificationDaily(): void
    {
        $this->checkAccess();
        $this->startJob('gamification-daily');
        ob_start();
        try { $this->gamificationDailyInternal(); $this->logJob('success', ob_get_clean()); }
        catch (\Throwable $e) { $out = ob_get_clean(); $this->logJob('error', $out . "\n" . $e->getMessage()); }
    }

    public function gamificationCampaigns(): void
    {
        $this->checkAccess();
        $this->startJob('gamification-campaigns');
        ob_start();
        try { $this->gamificationCampaignsInternal(); $this->logJob('success', ob_get_clean()); }
        catch (\Throwable $e) { $out = ob_get_clean(); $this->logJob('error', $out . "\n" . $e->getMessage()); }
    }

    public function gamificationLeaderboard(): void
    {
        $this->checkAccess();
        $this->startJob('gamification-leaderboard');
        ob_start();
        try { $this->gamificationLeaderboardSnapshotInternal(); $this->logJob('success', ob_get_clean()); }
        catch (\Throwable $e) { $out = ob_get_clean(); $this->logJob('error', $out . "\n" . $e->getMessage()); }
    }

    public function gamificationChallenges(): void
    {
        $this->checkAccess();
        $this->startJob('gamification-challenges');
        ob_start();
        try { $this->gamificationChallengeCheckInternal(); $this->logJob('success', ob_get_clean()); }
        catch (\Throwable $e) { $out = ob_get_clean(); $this->logJob('error', $out . "\n" . $e->getMessage()); }
    }

    public function gamificationWeeklyDigest(): void
    {
        $this->checkAccess();
        $this->startJob('gamification-weekly-digest');
        ob_start();
        try { $this->gamificationWeeklyDigestInternal(); $this->logJob('success', ob_get_clean()); }
        catch (\Throwable $e) { $out = ob_get_clean(); $this->logJob('error', $out . "\n" . $e->getMessage()); }
    }

    public function gamificationStreaks(): void
    {
        $this->checkAccess();
        $this->startJob('gamification-streaks');
        ob_start();
        try { $this->gamificationStreakMilestonesInternal(); $this->logJob('success', ob_get_clean()); }
        catch (\Throwable $e) { $out = ob_get_clean(); $this->logJob('error', $out . "\n" . $e->getMessage()); }
    }

    public function gamificationCleanup(): void
    {
        $this->checkAccess();
        $this->startJob('gamification-cleanup');
        ob_start();
        try { $this->gamificationCleanupInternal(); $this->logJob('success', ob_get_clean()); }
        catch (\Throwable $e) { $out = ob_get_clean(); $this->logJob('error', $out . "\n" . $e->getMessage()); }
    }

    public function groupWeeklyDigest(): void
    {
        $this->checkAccess();
        $this->startJob('group-weekly-digest');
        ob_start();
        try { $this->groupWeeklyDigestsInternal(); $this->logJob('success', ob_get_clean()); }
        catch (\Throwable $e) { $out = ob_get_clean(); $this->logJob('error', $out . "\n" . $e->getMessage()); }
    }

    public function abuseDetection(): void
    {
        $this->checkAccess();
        $this->startJob('abuse-detection');
        ob_start();
        try { $this->abuseDetectionInternal(); $this->logJob('success', ob_get_clean()); }
        catch (\Throwable $e) { $out = ob_get_clean(); $this->logJob('error', $out . "\n" . $e->getMessage()); }
    }

    public function abuseDailyReport(): void
    {
        $this->checkAccess();
        $this->startJob('abuse-daily-report');
        ob_start();
        try { $this->abuseDetectionDailyReportInternal(); $this->logJob('success', ob_get_clean()); }
        catch (\Throwable $e) { $out = ob_get_clean(); $this->logJob('error', $out . "\n" . $e->getMessage()); }
    }

    public function abuseCleanup(): void
    {
        $this->checkAccess();
        $this->startJob('abuse-cleanup');
        ob_start();
        try { $this->abuseDetectionCleanupInternal(); $this->logJob('success', ob_get_clean()); }
        catch (\Throwable $e) { $out = ob_get_clean(); $this->logJob('error', $out . "\n" . $e->getMessage()); }
    }

    public function eventReminders(): void
    {
        $this->checkAccess();
        $this->startJob('event-reminders');
        ob_start();
        try { $this->eventRemindersInternal(); $this->logJob('success', ob_get_clean()); }
        catch (\Throwable $e) { $out = ob_get_clean(); $this->logJob('error', $out . "\n" . $e->getMessage()); }
    }

    public function inactiveMembers(): void
    {
        $this->checkAccess();
        $this->startJob('inactive-members');
        ob_start();
        try { $this->inactiveMemberDetectionInternal(); $this->logJob('success', ob_get_clean()); }
        catch (\Throwable $e) { $out = ob_get_clean(); $this->logJob('error', $out . "\n" . $e->getMessage()); }
    }

    public function listingExpiry(): void
    {
        $this->checkAccess();
        $this->startJob('listing-expiry');
        ob_start();
        try { $this->listingExpiryInternal(); $this->logJob('success', ob_get_clean()); }
        catch (\Throwable $e) { $out = ob_get_clean(); $this->logJob('error', $out . "\n" . $e->getMessage()); }
    }

    public function listingExpiryReminders(): void
    {
        $this->checkAccess();
        $this->startJob('listing-expiry-reminders');
        ob_start();
        try { $this->listingExpiryRemindersInternal(); $this->logJob('success', ob_get_clean()); }
        catch (\Throwable $e) { $out = ob_get_clean(); $this->logJob('error', $out . "\n" . $e->getMessage()); }
    }

    public function jobExpiry(): void
    {
        $this->checkAccess();
        $this->startJob('job-expiry');
        ob_start();
        try { $this->jobExpiryInternal(); $this->logJob('success', ob_get_clean()); }
        catch (\Throwable $e) { $out = ob_get_clean(); $this->logJob('error', $out . "\n" . $e->getMessage()); }
    }

    public function balanceAlerts(): void
    {
        $this->checkAccess();
        $this->startJob('balance-alerts');
        ob_start();
        try { $this->balanceAlertsInternal(); $this->logJob('success', ob_get_clean()); }
        catch (\Throwable $e) { $out = ob_get_clean(); $this->logJob('error', $out . "\n" . $e->getMessage()); }
    }

    public function goalReminders(): void
    {
        $this->checkAccess();
        $this->startJob('goal-reminders');
        ob_start();
        try { $this->goalRemindersInternal(); $this->logJob('success', ob_get_clean()); }
        catch (\Throwable $e) { $out = ob_get_clean(); $this->logJob('error', $out . "\n" . $e->getMessage()); }
    }

    public function recurringShifts(): void
    {
        $this->checkAccess();
        $this->startJob('recurring-shifts');
        ob_start();
        try { $this->recurringShiftGenerationInternal(); $this->logJob('success', ob_get_clean()); }
        catch (\Throwable $e) { $out = ob_get_clean(); $this->logJob('error', $out . "\n" . $e->getMessage()); }
    }
}
