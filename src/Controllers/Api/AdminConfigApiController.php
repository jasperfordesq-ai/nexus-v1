<?php

namespace Nexus\Controllers\Api;

use Nexus\Core\Database;
use Nexus\Core\TenantContext;
use Nexus\Core\ApiErrorCodes;
use Nexus\Services\RedisCache;

/**
 * AdminConfigApiController - V2 API for React admin system configuration
 *
 * Provides endpoints for managing tenant features, modules, cache, and jobs.
 * All endpoints require admin authentication.
 *
 * Endpoints:
 * - GET    /api/v2/admin/config            - Get current config (features + modules)
 * - PUT    /api/v2/admin/config/features   - Toggle a feature
 * - PUT    /api/v2/admin/config/modules    - Toggle a module
 * - GET    /api/v2/admin/cache/stats       - Get Redis cache stats
 * - POST   /api/v2/admin/cache/clear       - Clear cache
 * - GET    /api/v2/admin/jobs              - Get background jobs status
 * - POST   /api/v2/admin/jobs/{id}/run     - Trigger a job manually
 */
class AdminConfigApiController extends BaseApiController
{
    protected bool $isV2Api = true;

    /**
     * All known features with defaults
     */
    private const FEATURE_DEFAULTS = [
        'events' => true,
        'groups' => true,
        'gamification' => false,
        'goals' => false,
        'blog' => true,
        'resources' => false,
        'volunteering' => false,
        'exchange_workflow' => false,
        'organisations' => false,
        'federation' => false,
        'connections' => true,
        'reviews' => true,
        'polls' => false,
        'direct_messaging' => true,
    ];

    /**
     * All known modules with defaults
     */
    private const MODULE_DEFAULTS = [
        'listings' => true,
        'wallet' => true,
        'messages' => true,
        'dashboard' => true,
        'feed' => true,
        'notifications' => true,
        'profile' => true,
        'settings' => true,
    ];

    /**
     * GET /api/v2/admin/config
     */
    public function getConfig(): void
    {
        $this->requireAdmin();
        $tenantId = TenantContext::getId();

        $tenant = Database::query(
            "SELECT features, configuration FROM tenants WHERE id = ?",
            [$tenantId]
        )->fetch();

        $features = self::FEATURE_DEFAULTS;
        if ($tenant && !empty($tenant['features'])) {
            $dbFeatures = json_decode($tenant['features'], true) ?: [];
            foreach ($dbFeatures as $key => $value) {
                if (array_key_exists($key, $features)) {
                    $features[$key] = (bool) $value;
                }
            }
        }

        $modules = self::MODULE_DEFAULTS;
        if ($tenant && !empty($tenant['configuration'])) {
            $config = json_decode($tenant['configuration'], true) ?: [];
            $dbModules = $config['modules'] ?? [];
            foreach ($dbModules as $key => $value) {
                if (array_key_exists($key, $modules)) {
                    $modules[$key] = (bool) $value;
                }
            }
        }

        $this->respondWithData([
            'tenant_id' => $tenantId,
            'features' => $features,
            'modules' => $modules,
        ]);
    }

    /**
     * PUT /api/v2/admin/config/features
     *
     * Body: { "feature": "gamification", "enabled": true }
     */
    public function updateFeature(): void
    {
        $this->requireAdmin();
        $tenantId = TenantContext::getId();

        $input = $this->getAllInput();
        $featureName = $input['feature'] ?? null;
        $enabled = $input['enabled'] ?? null;

        if (!$featureName || !is_string($featureName)) {
            $this->respondWithError(ApiErrorCodes::VALIDATION_ERROR, 'Feature name is required', 'feature', 422);
        }

        if (!array_key_exists($featureName, self::FEATURE_DEFAULTS)) {
            $this->respondWithError(ApiErrorCodes::VALIDATION_ERROR, 'Unknown feature: ' . $featureName, 'feature', 422);
        }

        if ($enabled === null) {
            $this->respondWithError(ApiErrorCodes::VALIDATION_ERROR, 'Enabled value is required', 'enabled', 422);
        }

        // Read current features
        $tenant = Database::query(
            "SELECT features FROM tenants WHERE id = ?",
            [$tenantId]
        )->fetch();

        $features = [];
        if ($tenant && !empty($tenant['features'])) {
            $features = json_decode($tenant['features'], true) ?: [];
        }

        $features[$featureName] = (bool) $enabled;

        Database::query(
            "UPDATE tenants SET features = ? WHERE id = ?",
            [json_encode($features), $tenantId]
        );

        // Clear bootstrap cache
        RedisCache::delete('tenant_bootstrap', $tenantId);

        $this->respondWithData([
            'feature' => $featureName,
            'enabled' => (bool) $enabled,
        ]);
    }

    /**
     * PUT /api/v2/admin/config/modules
     *
     * Body: { "module": "wallet", "enabled": true }
     */
    public function updateModule(): void
    {
        $this->requireAdmin();
        $tenantId = TenantContext::getId();

        $input = $this->getAllInput();
        $moduleName = $input['module'] ?? null;
        $enabled = $input['enabled'] ?? null;

        if (!$moduleName || !is_string($moduleName)) {
            $this->respondWithError(ApiErrorCodes::VALIDATION_ERROR, 'Module name is required', 'module', 422);
        }

        if (!array_key_exists($moduleName, self::MODULE_DEFAULTS)) {
            $this->respondWithError(ApiErrorCodes::VALIDATION_ERROR, 'Unknown module: ' . $moduleName, 'module', 422);
        }

        if ($enabled === null) {
            $this->respondWithError(ApiErrorCodes::VALIDATION_ERROR, 'Enabled value is required', 'enabled', 422);
        }

        // Read current configuration
        $tenant = Database::query(
            "SELECT configuration FROM tenants WHERE id = ?",
            [$tenantId]
        )->fetch();

        $config = [];
        if ($tenant && !empty($tenant['configuration'])) {
            $config = json_decode($tenant['configuration'], true) ?: [];
        }

        if (!isset($config['modules'])) {
            $config['modules'] = self::MODULE_DEFAULTS;
        }

        $config['modules'][$moduleName] = (bool) $enabled;

        Database::query(
            "UPDATE tenants SET configuration = ? WHERE id = ?",
            [json_encode($config), $tenantId]
        );

        // Clear bootstrap cache
        RedisCache::delete('tenant_bootstrap', $tenantId);

        $this->respondWithData([
            'module' => $moduleName,
            'enabled' => (bool) $enabled,
        ]);
    }

    /**
     * GET /api/v2/admin/cache/stats
     */
    public function cacheStats(): void
    {
        $this->requireAdmin();

        $stats = RedisCache::getStats();

        $this->respondWithData([
            'redis_connected' => $stats['enabled'] ?? false,
            'redis_memory_used' => $stats['memory_used'] ?? '0B',
            'redis_keys_count' => $stats['total_keys'] ?? 0,
            'cache_hit_rate' => 0.0,
        ]);
    }

    /**
     * POST /api/v2/admin/cache/clear
     *
     * Body: { "type": "all" | "tenant" | "routes" | "views" }
     */
    public function clearCache(): void
    {
        $this->requireAdmin();
        $tenantId = TenantContext::getId();

        $input = $this->getAllInput();
        $type = $input['type'] ?? 'tenant';

        try {
            if ($type === 'all') {
                // Clear all tenants
                foreach ([1, 2, 3, 4, 5] as $tid) {
                    RedisCache::clearTenant($tid);
                }
            } else {
                RedisCache::clearTenant($tenantId);
            }
        } catch (\Throwable $e) {
            $this->respondWithError(ApiErrorCodes::SERVER_INTERNAL_ERROR, 'Failed to clear cache', null, 500);
        }

        $this->respondWithData(['cleared' => true, 'type' => $type]);
    }

    /**
     * GET /api/v2/admin/jobs
     */
    public function getJobs(): void
    {
        $this->requireAdmin();

        // Return known background jobs with their status
        $jobs = [
            [
                'id' => 'digest_emails',
                'name' => 'Email Digest Sender',
                'status' => 'idle',
                'last_run_at' => null,
                'next_run_at' => null,
            ],
            [
                'id' => 'badge_checker',
                'name' => 'Badge Award Checker',
                'status' => 'idle',
                'last_run_at' => null,
                'next_run_at' => null,
            ],
            [
                'id' => 'streak_updater',
                'name' => 'Login Streak Updater',
                'status' => 'idle',
                'last_run_at' => null,
                'next_run_at' => null,
            ],
        ];

        $this->respondWithData($jobs);
    }

    /**
     * POST /api/v2/admin/jobs/{id}/run
     */
    public function runJob(): void
    {
        $this->requireAdmin();

        // Placeholder — jobs would be triggered here
        $this->respondWithData(['triggered' => true]);
    }

    // ─────────────────────────────────────────────────────────────────────────
    // Cron Jobs (System)
    // ─────────────────────────────────────────────────────────────────────────

    /**
     * All known cron jobs with their configurations.
     * Mirrors CronJobController::getCronJobs() but returns V2 API format.
     */
    private function getCronJobDefinitions(): array
    {
        return [
            ['id' => 'daily-digest', 'name' => 'Daily Digest', 'command' => '/cron/daily-digest', 'schedule' => '0 8 * * *', 'category' => 'notifications', 'description' => 'Sends daily notification digest emails to users who opted for daily frequency.'],
            ['id' => 'weekly-digest', 'name' => 'Weekly Digest', 'command' => '/cron/weekly-digest', 'schedule' => '0 17 * * 5', 'category' => 'notifications', 'description' => 'Sends weekly notification digest emails (typically on Fridays at 5 PM).'],
            ['id' => 'process-queue', 'name' => 'Instant Notification Queue', 'command' => '/cron/process-queue', 'schedule' => '*/2 * * * *', 'category' => 'notifications', 'description' => 'Processes the instant notification queue, sending pending notifications immediately.'],
            ['id' => 'process-newsletters', 'name' => 'Process Scheduled Newsletters', 'command' => '/cron/process-newsletters', 'schedule' => '*/5 * * * *', 'category' => 'newsletters', 'description' => 'Checks for newsletters scheduled to be sent and initiates their sending process.'],
            ['id' => 'process-recurring', 'name' => 'Process Recurring Newsletters', 'command' => '/cron/process-recurring', 'schedule' => '*/15 * * * *', 'category' => 'newsletters', 'description' => 'Handles recurring/automated newsletters (e.g., weekly community updates).'],
            ['id' => 'process-newsletter-queue', 'name' => 'Newsletter Queue Processor', 'command' => '/cron/process-newsletter-queue', 'schedule' => '*/3 * * * *', 'category' => 'newsletters', 'description' => 'Processes the newsletter sending queue for large sends.'],
            ['id' => 'match-digest-daily', 'name' => 'Daily Match Digest', 'command' => '/cron/match-digest-daily', 'schedule' => '0 9 * * *', 'category' => 'matching', 'description' => 'Sends daily match recommendations to users.'],
            ['id' => 'match-digest-weekly', 'name' => 'Weekly Match Digest', 'command' => '/cron/match-digest-weekly', 'schedule' => '0 9 * * 1', 'category' => 'matching', 'description' => 'Sends weekly match recommendations summary.'],
            ['id' => 'notify-hot-matches', 'name' => 'Hot Match Notifications', 'command' => '/cron/notify-hot-matches', 'schedule' => '0 * * * *', 'category' => 'matching', 'description' => 'Notifies users of new high-scoring matches.'],
            ['id' => 'geocode-batch', 'name' => 'Batch Geocoding', 'command' => '/cron/geocode-batch', 'schedule' => '*/30 * * * *', 'category' => 'geocoding', 'description' => 'Geocodes users and listings missing lat/lng coordinates.'],
            ['id' => 'cleanup', 'name' => 'System Cleanup', 'command' => '/cron/cleanup', 'schedule' => '0 0 * * *', 'category' => 'maintenance', 'description' => 'Cleans expired tokens, old queue entries, and tracking data.'],
            ['id' => 'run-all', 'name' => 'Master Cron Runner', 'command' => '/cron/run-all', 'schedule' => '* * * * *', 'category' => 'master', 'description' => 'Runs all appropriate cron tasks based on the current time.'],
            ['id' => 'gamification-daily', 'name' => 'Gamification Daily Tasks', 'command' => 'scripts/cron/gamification_cron.php daily', 'schedule' => '0 3 * * *', 'category' => 'gamification', 'description' => 'Processes streak resets, daily bonuses, and badge checks.'],
            ['id' => 'gamification-weekly-digest', 'name' => 'Gamification Weekly Digest', 'command' => 'scripts/cron/gamification_cron.php weekly_digest', 'schedule' => '0 4 * * 1', 'category' => 'gamification', 'description' => 'Sends weekly progress email digests to users.'],
            ['id' => 'gamification-campaigns', 'name' => 'Process Achievement Campaigns', 'command' => 'scripts/cron/gamification_cron.php campaigns', 'schedule' => '0 * * * *', 'category' => 'gamification', 'description' => 'Processes recurring achievement campaigns.'],
            ['id' => 'gamification-leaderboard', 'name' => 'Leaderboard Snapshot', 'command' => 'scripts/cron/gamification_cron.php leaderboard_snapshot', 'schedule' => '0 0 * * *', 'category' => 'gamification', 'description' => 'Creates daily leaderboard snapshots.'],
            ['id' => 'gamification-challenges', 'name' => 'Check Challenge Expirations', 'command' => 'scripts/cron/gamification_cron.php check_challenges', 'schedule' => '30 * * * *', 'category' => 'gamification', 'description' => 'Expires completed challenges and updates statuses.'],
            ['id' => 'update-featured-groups', 'name' => 'Update Featured Groups', 'command' => '/admin-legacy/cron/update-featured-groups', 'schedule' => '0 8 * * *', 'category' => 'groups', 'description' => 'Updates featured groups based on ranking algorithms.'],
            ['id' => 'group-weekly-digest', 'name' => 'Group Weekly Digests', 'command' => 'scripts/cron/send_group_digests.php', 'schedule' => '0 9 * * 1', 'category' => 'groups', 'description' => 'Sends weekly analytics digest emails to group owners.'],
            ['id' => 'abuse-detection', 'name' => 'Timebanking Abuse Detection', 'command' => 'scripts/cron/abuse_detection_cron.php', 'schedule' => '0 2 * * *', 'category' => 'security', 'description' => 'Scans transactions for potential abuse patterns.'],
        ];
    }

    /**
     * Calculate next run time from cron expression (simple heuristic)
     */
    private function calculateNextRun(string $cronExpression): ?string
    {
        try {
            $parts = explode(' ', $cronExpression);
            if (count($parts) !== 5) return null;

            [$minute, $hour, $dayOfMonth, $month, $dayOfWeek] = $parts;
            $now = new \DateTime();
            $next = clone $now;

            if ($minute === '*' && $hour === '*') {
                $next->modify('+1 minute');
                return $next->format('Y-m-d H:i:s');
            }
            if (strpos($minute, '*/') === 0) {
                $interval = (int)substr($minute, 2);
                $currentMinute = (int)$now->format('i');
                $nextMinute = (int)(ceil($currentMinute / $interval) * $interval);
                if ($nextMinute >= 60) {
                    $next->modify('+1 hour');
                    $next->setTime((int)$next->format('H'), 0);
                } else {
                    $next->setTime((int)$now->format('H'), $nextMinute);
                }
                if ($next <= $now) $next->modify("+{$interval} minutes");
                return $next->format('Y-m-d H:i:s');
            }
            if ($minute !== '*' && $hour !== '*' && $dayOfWeek !== '*') {
                $targetDay = (int)$dayOfWeek;
                $currentDay = (int)$now->format('w');
                $daysUntil = ($targetDay - $currentDay + 7) % 7;
                if ($daysUntil === 0 && $now->format('H:i') >= sprintf('%02d:%02d', $hour, $minute)) {
                    $daysUntil = 7;
                }
                $next->modify("+{$daysUntil} days");
                $next->setTime((int)$hour, (int)$minute);
                return $next->format('Y-m-d H:i:s');
            }
            if ($minute !== '*' && $hour !== '*') {
                $next->setTime((int)$hour, (int)$minute);
                if ($next <= $now) $next->modify('+1 day');
                return $next->format('Y-m-d H:i:s');
            }
            if ($minute === '0' && $hour === '*') {
                $next->modify('+1 hour');
                $next->setTime((int)$next->format('H'), 0);
                return $next->format('Y-m-d H:i:s');
            }
            if ($minute === '30' && $hour === '*') {
                if ((int)$now->format('i') >= 30) $next->modify('+1 hour');
                $next->setTime((int)$next->format('H'), 30);
                return $next->format('Y-m-d H:i:s');
            }
            return null;
        } catch (\Exception $e) {
            return null;
        }
    }

    /**
     * GET /api/v2/admin/system/cron-jobs
     *
     * Returns all cron jobs with their status, last run info, and next run time.
     */
    public function getCronJobs(): void
    {
        $this->requireAdmin();

        $jobs = $this->getCronJobDefinitions();

        // Fetch last run info from cron_logs
        $lastRuns = [];
        try {
            $results = Database::query("
                SELECT cl1.job_id, cl1.status, cl1.executed_at
                FROM cron_logs cl1
                INNER JOIN (
                    SELECT job_id, MAX(executed_at) as max_date
                    FROM cron_logs
                    GROUP BY job_id
                ) cl2 ON cl1.job_id = cl2.job_id AND cl1.executed_at = cl2.max_date
            ")->fetchAll();

            foreach ($results as $row) {
                $lastRuns[$row['job_id']] = [
                    'last_run_at' => $row['executed_at'],
                    'last_status' => $row['status'] === 'running' ? null : $row['status'],
                ];
            }
        } catch (\Exception $e) {
            // cron_logs table may not exist yet
        }

        // Fetch per-job enabled/disabled settings
        $jobSettings = [];
        try {
            $results = Database::query("SELECT job_id, is_enabled FROM cron_job_settings")->fetchAll();
            foreach ($results as $row) {
                $jobSettings[$row['job_id']] = (int)$row['is_enabled'];
            }
        } catch (\Exception $e) {
            // Table may not exist yet
        }

        // Build response with sequential numeric IDs for React
        $response = [];
        $numericId = 1;
        foreach ($jobs as $job) {
            $jobId = $job['id'];
            $isEnabled = $jobSettings[$jobId] ?? 1;
            $lastRun = $lastRuns[$jobId] ?? null;

            $response[] = [
                'id' => $numericId,
                'slug' => $jobId,
                'name' => $job['name'],
                'command' => $job['command'],
                'schedule' => $job['schedule'],
                'status' => $isEnabled ? 'active' : 'disabled',
                'category' => $job['category'],
                'description' => $job['description'],
                'last_run_at' => $lastRun['last_run_at'] ?? null,
                'last_status' => $lastRun['last_status'] ?? null,
                'next_run_at' => $this->calculateNextRun($job['schedule']),
            ];
            $numericId++;
        }

        $this->respondWithData($response);
    }

    /**
     * POST /api/v2/admin/system/cron-jobs/{id}/run
     *
     * Triggers a cron job by its numeric ID.
     * Delegates to the existing CronJobController::run() logic.
     */
    public function runCronJob(): void
    {
        $this->requireAdmin();

        // Get numeric ID from URL
        $uri = $_SERVER['REQUEST_URI'] ?? '';
        preg_match('#/api/v2/admin/system/cron-jobs/(\d+)/run#', $uri, $matches);
        $numericId = (int)($matches[1] ?? 0);

        if ($numericId < 1) {
            $this->respondWithError(ApiErrorCodes::VALIDATION_ERROR, 'Invalid job ID', 'id', 400);
            return;
        }

        $jobs = $this->getCronJobDefinitions();
        $jobIndex = $numericId - 1;

        if (!isset($jobs[$jobIndex])) {
            $this->respondWithError(ApiErrorCodes::RESOURCE_NOT_FOUND, 'Cron job not found', 'id', 404);
            return;
        }

        $job = $jobs[$jobIndex];
        $jobSlug = $job['id'];

        // Check if job is disabled
        try {
            $setting = Database::query(
                "SELECT is_enabled FROM cron_job_settings WHERE job_id = ?",
                [$jobSlug]
            )->fetch();

            if ($setting && !$setting['is_enabled']) {
                $this->respondWithError(ApiErrorCodes::VALIDATION_ERROR, 'Cannot run disabled job. Enable it first.', 'status', 422);
                return;
            }
        } catch (\Exception $e) {
            // Table may not exist — allow run
        }

        // Ensure cron_logs table exists
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
                    INDEX idx_executed_at (executed_at)
                )
            ");
        } catch (\Exception $e) {
            // Already exists
        }

        // Log as running
        $startTime = microtime(true);
        $userId = $this->getAuthenticatedUserId();
        $tenantId = TenantContext::getId();

        try {
            Database::query(
                "INSERT INTO cron_logs (job_id, status, output, duration_seconds, executed_by, tenant_id) VALUES (?, 'running', 'Job started via API...', 0, ?, ?)",
                [$jobSlug, $userId, $tenantId]
            );
            $logId = Database::getConnection()->lastInsertId();
        } catch (\Exception $e) {
            $logId = null;
        }

        // Execute the job
        $output = '';
        $status = 'success';

        try {
            if (isset($job['command']) && strpos($job['command'], 'scripts/') === 0) {
                // Script-based job
                $parts = explode(' ', $job['command']);
                $script = dirname(__DIR__, 2) . '/' . $parts[0];
                $args = $parts[1] ?? '';

                if (file_exists($script)) {
                    if (!defined('CRON_INTERNAL_RUN')) {
                        define('CRON_INTERNAL_RUN', true);
                    }
                    $oldArgv = $GLOBALS['argv'] ?? [];
                    $oldArgc = $GLOBALS['argc'] ?? 0;
                    $GLOBALS['argv'] = [basename($script), $args];
                    $GLOBALS['argc'] = count($GLOBALS['argv']);

                    ob_start();
                    try {
                        include $script;
                        $output = ob_get_clean() ?: 'Completed (no output)';
                    } catch (\Throwable $e) {
                        $output = ob_get_clean() . "\nError: " . $e->getMessage();
                        $status = 'error';
                    }

                    $GLOBALS['argv'] = $oldArgv;
                    $GLOBALS['argc'] = $oldArgc;
                } else {
                    $output = "Script not found: {$script}";
                    $status = 'error';
                }
            } else {
                // Endpoint-based job
                if (!defined('CRON_INTERNAL_RUN')) {
                    define('CRON_INTERNAL_RUN', true);
                }

                $cronKey = \Nexus\Core\Env::get('CRON_KEY');
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

                $adminMethodMap = [
                    'update-featured-groups' => 'cronUpdateFeaturedGroups',
                ];

                if (isset($methodMap[$jobSlug])) {
                    $controller = new \Nexus\Controllers\CronController();
                    $method = $methodMap[$jobSlug];
                    ob_start();
                    $_GET['key'] = $cronKey;
                    $controller->$method();
                    unset($_GET['key']);
                    $output = ob_get_clean() ?: 'Completed (no output)';
                } elseif (isset($adminMethodMap[$jobSlug])) {
                    $controller = new \Nexus\Controllers\AdminController();
                    $method = $adminMethodMap[$jobSlug];
                    ob_start();
                    $_GET['key'] = $cronKey;
                    $controller->$method();
                    unset($_GET['key']);
                    $output = ob_get_clean() ?: 'Completed (no output)';
                } else {
                    $output = "No method mapping for job: {$jobSlug}";
                    $status = 'error';
                }
            }
        } catch (\Throwable $e) {
            $output = "Exception: " . $e->getMessage();
            $status = 'error';
        }

        $duration = microtime(true) - $startTime;

        // Update log entry
        if ($logId) {
            try {
                Database::query(
                    "UPDATE cron_logs SET status = ?, output = ?, duration_seconds = ? WHERE id = ?",
                    [$status, substr($output, 0, 65000), round($duration, 2), $logId]
                );
            } catch (\Exception $e) {
                // Ignore log update failure
            }
        }

        $this->respondWithData([
            'triggered' => true,
            'job_slug' => $jobSlug,
            'job_name' => $job['name'],
            'status' => $status,
            'duration' => round($duration, 2),
            'output' => substr($output, 0, 500),
        ]);
    }

    // ─────────────────────────────────────────────────────────────────────────
    // Tenant Settings (General)
    // ─────────────────────────────────────────────────────────────────────────

    /**
     * Tenant columns that can be read/updated via the settings endpoints.
     * Maps API field names to tenants table column names.
     */
    private const TENANT_DIRECT_COLUMNS = [
        'name', 'slug', 'domain', 'tagline', 'description',
        'contact_email', 'contact_phone', 'default_layout',
        'logo_url', 'favicon_url', 'primary_color', 'og_image_url',
        'meta_title', 'meta_description', 'h1_headline', 'hero_intro',
    ];

    /**
     * Settings stored in the tenant_settings key-value table under
     * the 'general.*' namespace.
     */
    private const GENERAL_SETTING_KEYS = [
        'timezone', 'registration_mode', 'welcome_message',
        'maintenance_mode', 'default_currency', 'date_format',
        'time_format', 'items_per_page', 'max_upload_size_mb',
    ];

    /**
     * Ensure the tenant_settings table exists.
     * Called lazily before any read/write to that table from this controller.
     */
    private function ensureTenantSettingsTable(): void
    {
        try {
            Database::query("
                CREATE TABLE IF NOT EXISTS `tenant_settings` (
                    `id` BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
                    `tenant_id` INT UNSIGNED NOT NULL DEFAULT 1,
                    `setting_key` VARCHAR(255) NOT NULL,
                    `setting_value` TEXT NULL,
                    `setting_type` ENUM('string','boolean','integer','float','json','array') DEFAULT 'string',
                    `description` TEXT NULL,
                    `is_encrypted` TINYINT(1) NOT NULL DEFAULT 0,
                    `created_at` TIMESTAMP NULL DEFAULT CURRENT_TIMESTAMP,
                    `updated_at` TIMESTAMP NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
                    `created_by` INT UNSIGNED NULL,
                    `updated_by` INT UNSIGNED NULL,
                    PRIMARY KEY (`id`),
                    UNIQUE KEY `unique_tenant_setting` (`tenant_id`, `setting_key`),
                    KEY `idx_tenant_id` (`tenant_id`),
                    KEY `idx_setting_key` (`setting_key`)
                ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci
            ");
        } catch (\Exception $e) {
            // Table already exists
        }
    }

    /**
     * Read all settings from tenant_settings for the given tenant that match a key prefix.
     *
     * @param int    $tenantId
     * @param string $prefix  e.g. 'general.', 'ai_', 'feed_algo_', 'image_', 'seo_', 'native_app_'
     * @return array  Associative [setting_key => setting_value]
     */
    private function readSettingsByPrefix(int $tenantId, string $prefix): array
    {
        $this->ensureTenantSettingsTable();

        $rows = Database::query(
            "SELECT setting_key, setting_value FROM tenant_settings WHERE tenant_id = ? AND setting_key LIKE ?",
            [$tenantId, $prefix . '%']
        )->fetchAll();

        $result = [];
        foreach ($rows as $row) {
            $result[$row['setting_key']] = $row['setting_value'];
        }
        return $result;
    }

    /**
     * Upsert a single setting in tenant_settings.
     *
     * @param int    $tenantId
     * @param string $key
     * @param string|null $value
     * @param string $type  One of: string, boolean, integer, float, json, array
     */
    private function upsertSetting(int $tenantId, string $key, ?string $value, string $type = 'string'): void
    {
        $userId = $this->getAuthenticatedUserId();
        Database::query(
            "INSERT INTO tenant_settings (tenant_id, setting_key, setting_value, setting_type, created_by, updated_by)
             VALUES (?, ?, ?, ?, ?, ?)
             ON DUPLICATE KEY UPDATE setting_value = VALUES(setting_value), setting_type = VALUES(setting_type), updated_by = VALUES(updated_by), updated_at = CURRENT_TIMESTAMP",
            [$tenantId, $key, $value, $type, $userId, $userId]
        );
    }

    /**
     * Clear the tenant bootstrap Redis cache after a settings write.
     */
    private function clearBootstrapCache(int $tenantId): void
    {
        RedisCache::delete('tenant_bootstrap', $tenantId);
    }

    /**
     * GET /api/v2/admin/settings
     *
     * Returns general tenant settings: direct tenant columns + key-value
     * settings stored under the 'general.*' prefix in tenant_settings.
     */
    public function getSettings(): void
    {
        $this->requireAdmin();
        $tenantId = TenantContext::getId();

        // 1. Read direct tenant columns
        $tenant = Database::query(
            "SELECT * FROM tenants WHERE id = ?",
            [$tenantId]
        )->fetch();

        if (!$tenant) {
            $this->respondWithError(ApiErrorCodes::RESOURCE_NOT_FOUND, 'Tenant not found', null, 404);
            return;
        }

        $directSettings = [];
        foreach (self::TENANT_DIRECT_COLUMNS as $col) {
            $directSettings[$col] = $tenant[$col] ?? null;
        }

        // 2. Read key-value settings with 'general.' prefix
        $kvSettings = $this->readSettingsByPrefix($tenantId, 'general.');
        $generalSettings = [];
        foreach (self::GENERAL_SETTING_KEYS as $key) {
            $generalSettings[$key] = $kvSettings['general.' . $key] ?? null;
        }

        $this->respondWithData([
            'tenant_id' => $tenantId,
            'tenant' => $directSettings,
            'settings' => $generalSettings,
        ]);
    }

    /**
     * PUT /api/v2/admin/settings
     *
     * Update general tenant settings. Accepts JSON body with key-value pairs.
     * Keys matching TENANT_DIRECT_COLUMNS update the tenants table directly.
     * Keys matching GENERAL_SETTING_KEYS update the tenant_settings table.
     *
     * Body: { "name": "My Timebank", "timezone": "Europe/Dublin", ... }
     */
    public function updateSettings(): void
    {
        $this->requireAdmin();
        $tenantId = TenantContext::getId();
        $input = $this->getAllInput();

        if (empty($input)) {
            $this->respondWithError(ApiErrorCodes::VALIDATION_ERROR, 'Request body is empty', null, 422);
            return;
        }

        $this->ensureTenantSettingsTable();

        // Separate into direct columns vs key-value settings
        $directUpdates = [];
        $kvUpdates = [];
        $unknownKeys = [];

        foreach ($input as $key => $value) {
            if (in_array($key, self::TENANT_DIRECT_COLUMNS, true)) {
                $directUpdates[$key] = $value;
            } elseif (in_array($key, self::GENERAL_SETTING_KEYS, true)) {
                $kvUpdates[$key] = $value;
            } else {
                $unknownKeys[] = $key;
            }
        }

        if (empty($directUpdates) && empty($kvUpdates)) {
            $this->respondWithError(ApiErrorCodes::VALIDATION_ERROR, 'No recognized settings provided. Unknown keys: ' . implode(', ', $unknownKeys), null, 422);
            return;
        }

        // Update direct tenant columns
        if (!empty($directUpdates)) {
            $setClauses = [];
            $params = [];
            foreach ($directUpdates as $col => $val) {
                $setClauses[] = "`{$col}` = ?";
                $params[] = $val;
            }
            $params[] = $tenantId;
            Database::query(
                "UPDATE tenants SET " . implode(', ', $setClauses) . " WHERE id = ?",
                $params
            );
        }

        // Upsert key-value settings
        foreach ($kvUpdates as $key => $value) {
            $this->upsertSetting($tenantId, 'general.' . $key, (string) $value);
        }

        $this->clearBootstrapCache($tenantId);

        $this->respondWithData([
            'updated' => true,
            'direct_columns_updated' => array_keys($directUpdates),
            'settings_updated' => array_keys($kvUpdates),
        ]);
    }

    // ─────────────────────────────────────────────────────────────────────────
    // AI Configuration
    // ─────────────────────────────────────────────────────────────────────────

    /**
     * GET /api/v2/admin/config/ai
     *
     * Returns AI configuration for the current tenant.
     * Uses the AiSettings model (reads from ai_settings table) which
     * handles encryption/decryption of sensitive keys.
     * API keys are masked in the response (last 4 chars only).
     */
    public function getAiConfig(): void
    {
        $this->requireAdmin();
        $tenantId = TenantContext::getId();

        $settings = \Nexus\Models\AiSettings::getAllForTenant($tenantId);

        // Build structured response
        $response = [
            'ai_enabled' => (bool) ($settings['ai_enabled'] ?? false),
            'ai_provider' => $settings['ai_provider'] ?? 'gemini',
            'models' => [
                'gemini' => $settings['gemini_model'] ?? 'gemini-pro',
                'openai' => $settings['openai_model'] ?? 'gpt-4-turbo',
                'anthropic' => $settings['claude_model'] ?? 'claude-sonnet-4-20250514',
                'ollama' => $settings['ollama_model'] ?? 'llama2',
            ],
            'api_keys' => [
                'gemini' => \Nexus\Models\AiSettings::getMasked($tenantId, 'gemini_api_key'),
                'openai' => \Nexus\Models\AiSettings::getMasked($tenantId, 'openai_api_key'),
                'anthropic' => \Nexus\Models\AiSettings::getMasked($tenantId, 'anthropic_api_key'),
            ],
            'api_key_set' => [
                'gemini' => \Nexus\Models\AiSettings::has($tenantId, 'gemini_api_key'),
                'openai' => \Nexus\Models\AiSettings::has($tenantId, 'openai_api_key'),
                'anthropic' => \Nexus\Models\AiSettings::has($tenantId, 'anthropic_api_key'),
            ],
            'features' => [
                'chat' => (bool) ($settings['ai_chat_enabled'] ?? false),
                'content_generation' => (bool) ($settings['ai_content_gen_enabled'] ?? false),
                'recommendations' => (bool) ($settings['ai_recommendations_enabled'] ?? false),
                'analytics' => (bool) ($settings['ai_analytics_enabled'] ?? false),
                'moderation' => (bool) ($settings['ai_moderation_enabled'] ?? false),
            ],
            'limits' => [
                'default_daily' => (int) ($settings['default_daily_limit'] ?? 50),
                'default_monthly' => (int) ($settings['default_monthly_limit'] ?? 1000),
            ],
            'ollama' => [
                'host' => $settings['ollama_host'] ?? 'http://localhost:11434',
            ],
        ];

        $this->respondWithData($response);
    }

    /**
     * PUT /api/v2/admin/config/ai
     *
     * Update AI configuration for the current tenant.
     * Uses the AiSettings model for encrypted key storage.
     *
     * Body: {
     *   "ai_enabled": true,
     *   "ai_provider": "openai",
     *   "gemini_api_key": "AIza...",
     *   "openai_api_key": "sk-...",
     *   "anthropic_api_key": "sk-ant-...",
     *   "gemini_model": "gemini-pro",
     *   "openai_model": "gpt-4-turbo",
     *   "claude_model": "claude-sonnet-4-20250514",
     *   "ollama_model": "llama2",
     *   "ollama_host": "http://localhost:11434",
     *   "ai_chat_enabled": true,
     *   "ai_content_gen_enabled": true,
     *   "ai_recommendations_enabled": true,
     *   "ai_analytics_enabled": false,
     *   "ai_moderation_enabled": false,
     *   "default_daily_limit": 50,
     *   "default_monthly_limit": 1000
     * }
     */
    public function updateAiConfig(): void
    {
        $this->requireAdmin();
        $tenantId = TenantContext::getId();
        $input = $this->getAllInput();

        if (empty($input)) {
            $this->respondWithError(ApiErrorCodes::VALIDATION_ERROR, 'Request body is empty', null, 422);
            return;
        }

        // Allowed keys (maps input key to ai_settings key)
        $allowedKeys = [
            'ai_enabled', 'ai_provider',
            'gemini_api_key', 'openai_api_key', 'anthropic_api_key',
            'gemini_model', 'openai_model', 'claude_model',
            'ollama_model', 'ollama_host',
            'ai_chat_enabled', 'ai_content_gen_enabled',
            'ai_recommendations_enabled', 'ai_analytics_enabled',
            'ai_moderation_enabled',
            'default_daily_limit', 'default_monthly_limit',
        ];

        $toSave = [];
        foreach ($input as $key => $value) {
            if (in_array($key, $allowedKeys, true)) {
                $toSave[$key] = is_bool($value) ? ($value ? '1' : '0') : (string) $value;
            }
        }

        if (empty($toSave)) {
            $this->respondWithError(ApiErrorCodes::VALIDATION_ERROR, 'No recognized AI settings provided', null, 422);
            return;
        }

        // Validate provider if provided
        if (isset($toSave['ai_provider'])) {
            $validProviders = ['gemini', 'openai', 'anthropic', 'ollama'];
            if (!in_array($toSave['ai_provider'], $validProviders, true)) {
                $this->respondWithError(ApiErrorCodes::VALIDATION_ERROR, 'Invalid AI provider. Must be one of: ' . implode(', ', $validProviders), 'ai_provider', 422);
                return;
            }
        }

        \Nexus\Models\AiSettings::setMultiple($tenantId, $toSave);

        $this->clearBootstrapCache($tenantId);

        $this->respondWithData([
            'updated' => true,
            'keys_updated' => array_keys($toSave),
        ]);
    }

    // ─────────────────────────────────────────────────────────────────────────
    // Feed Algorithm Configuration
    // ─────────────────────────────────────────────────────────────────────────

    /**
     * Default feed algorithm weights.
     */
    private const FEED_ALGO_DEFAULTS = [
        'feed_algo_recency_weight' => '0.35',
        'feed_algo_engagement_weight' => '0.25',
        'feed_algo_relevance_weight' => '0.20',
        'feed_algo_connection_weight' => '0.15',
        'feed_algo_diversity_weight' => '0.05',
        'feed_algo_recency_decay_hours' => '48',
        'feed_algo_engagement_half_life_hours' => '24',
        'feed_algo_boost_images' => '1',
        'feed_algo_boost_polls' => '1',
        'feed_algo_penalize_links_only' => '0',
        'feed_algo_min_score' => '0.01',
    ];

    /**
     * GET /api/v2/admin/config/feed-algorithm
     *
     * Returns feed algorithm configuration for the current tenant.
     * Reads from tenant_settings with 'feed_algo_' prefix and merges with defaults.
     */
    public function getFeedAlgorithmConfig(): void
    {
        $this->requireAdmin();
        $tenantId = TenantContext::getId();

        $stored = $this->readSettingsByPrefix($tenantId, 'feed_algo_');

        // Merge with defaults
        $config = [];
        foreach (self::FEED_ALGO_DEFAULTS as $key => $defaultValue) {
            $rawValue = $stored[$key] ?? $defaultValue;
            // Determine type from default
            if (in_array($key, ['feed_algo_boost_images', 'feed_algo_boost_polls', 'feed_algo_penalize_links_only'], true)) {
                $config[$key] = (bool) (int) $rawValue;
            } elseif (strpos($defaultValue, '.') !== false) {
                $config[$key] = (float) $rawValue;
            } else {
                $config[$key] = (int) $rawValue;
            }
        }

        $this->respondWithData([
            'tenant_id' => $tenantId,
            'algorithm' => $config,
        ]);
    }

    /**
     * PUT /api/v2/admin/config/feed-algorithm
     *
     * Update feed algorithm settings.
     * Body: { "feed_algo_recency_weight": 0.4, "feed_algo_engagement_weight": 0.3, ... }
     */
    public function updateFeedAlgorithmConfig(): void
    {
        $this->requireAdmin();
        $tenantId = TenantContext::getId();
        $input = $this->getAllInput();

        if (empty($input)) {
            $this->respondWithError(ApiErrorCodes::VALIDATION_ERROR, 'Request body is empty', null, 422);
            return;
        }

        $this->ensureTenantSettingsTable();

        $updated = [];
        foreach ($input as $key => $value) {
            if (!array_key_exists($key, self::FEED_ALGO_DEFAULTS)) {
                continue;
            }

            // Validate weights are between 0 and 1
            if (strpos($key, '_weight') !== false) {
                $floatVal = (float) $value;
                if ($floatVal < 0.0 || $floatVal > 1.0) {
                    $this->respondWithError(ApiErrorCodes::VALIDATION_ERROR, "Weight {$key} must be between 0 and 1", $key, 422);
                    return;
                }
            }

            $storeValue = is_bool($value) ? ($value ? '1' : '0') : (string) $value;
            $type = is_bool($value) ? 'boolean' : (is_float($value) ? 'float' : 'string');
            $this->upsertSetting($tenantId, $key, $storeValue, $type);
            $updated[] = $key;
        }

        if (empty($updated)) {
            $this->respondWithError(ApiErrorCodes::VALIDATION_ERROR, 'No recognized feed algorithm settings provided', null, 422);
            return;
        }

        $this->clearBootstrapCache($tenantId);

        $this->respondWithData([
            'updated' => true,
            'keys_updated' => $updated,
        ]);
    }

    // ─────────────────────────────────────────────────────────────────────────
    // Image Configuration
    // ─────────────────────────────────────────────────────────────────────────

    /**
     * Default image settings.
     */
    private const IMAGE_DEFAULTS = [
        'image_max_size_mb' => '10',
        'image_max_width' => '2048',
        'image_max_height' => '2048',
        'image_auto_webp' => '1',
        'image_auto_resize' => '1',
        'image_strip_exif' => '1',
        'image_webp_quality' => '85',
        'image_thumbnail_width' => '300',
        'image_thumbnail_height' => '300',
        'image_lazy_loading' => '1',
        'image_serving_enabled' => '1',
    ];

    /**
     * GET /api/v2/admin/config/images
     *
     * Returns image configuration for the current tenant.
     */
    public function getImageConfig(): void
    {
        $this->requireAdmin();
        $tenantId = TenantContext::getId();

        $stored = $this->readSettingsByPrefix($tenantId, 'image_');

        // Also check configuration JSON for legacy image_optimization settings
        $tenant = Database::query(
            "SELECT configuration FROM tenants WHERE id = ?",
            [$tenantId]
        )->fetch();
        $legacyConfig = [];
        if ($tenant && !empty($tenant['configuration'])) {
            $config = json_decode($tenant['configuration'], true) ?: [];
            $legacyConfig = $config['image_optimization'] ?? [];
        }

        // Build response merging defaults, legacy, and tenant_settings
        $config = [];
        foreach (self::IMAGE_DEFAULTS as $key => $defaultValue) {
            if (isset($stored[$key])) {
                $rawValue = $stored[$key];
            } else {
                $rawValue = $defaultValue;
            }

            // Cast based on key type
            if (in_array($key, ['image_auto_webp', 'image_auto_resize', 'image_strip_exif', 'image_lazy_loading', 'image_serving_enabled'], true)) {
                $config[$key] = (bool) (int) $rawValue;
            } else {
                $config[$key] = (int) $rawValue;
            }
        }

        // Include legacy settings if present (read-only reference)
        if (!empty($legacyConfig)) {
            $config['legacy'] = $legacyConfig;
        }

        $this->respondWithData([
            'tenant_id' => $tenantId,
            'images' => $config,
        ]);
    }

    /**
     * PUT /api/v2/admin/config/images
     *
     * Update image configuration for the current tenant.
     * Body: { "image_max_size_mb": 5, "image_auto_webp": true, ... }
     */
    public function updateImageConfig(): void
    {
        $this->requireAdmin();
        $tenantId = TenantContext::getId();
        $input = $this->getAllInput();

        if (empty($input)) {
            $this->respondWithError(ApiErrorCodes::VALIDATION_ERROR, 'Request body is empty', null, 422);
            return;
        }

        $this->ensureTenantSettingsTable();

        $updated = [];
        foreach ($input as $key => $value) {
            if (!array_key_exists($key, self::IMAGE_DEFAULTS)) {
                continue;
            }

            // Validate numeric ranges
            if ($key === 'image_max_size_mb') {
                $intVal = (int) $value;
                if ($intVal < 1 || $intVal > 50) {
                    $this->respondWithError(ApiErrorCodes::VALIDATION_ERROR, 'Max file size must be between 1 and 50 MB', $key, 422);
                    return;
                }
            }
            if ($key === 'image_webp_quality') {
                $intVal = (int) $value;
                if ($intVal < 50 || $intVal > 100) {
                    $this->respondWithError(ApiErrorCodes::VALIDATION_ERROR, 'WebP quality must be between 50 and 100', $key, 422);
                    return;
                }
            }
            if (in_array($key, ['image_max_width', 'image_max_height', 'image_thumbnail_width', 'image_thumbnail_height'], true)) {
                $intVal = (int) $value;
                if ($intVal < 50 || $intVal > 10000) {
                    $this->respondWithError(ApiErrorCodes::VALIDATION_ERROR, "Dimension {$key} must be between 50 and 10000", $key, 422);
                    return;
                }
            }

            $storeValue = is_bool($value) ? ($value ? '1' : '0') : (string) $value;
            $type = is_bool($value) ? 'boolean' : 'integer';
            $this->upsertSetting($tenantId, $key, $storeValue, $type);
            $updated[] = $key;
        }

        if (empty($updated)) {
            $this->respondWithError(ApiErrorCodes::VALIDATION_ERROR, 'No recognized image settings provided', null, 422);
            return;
        }

        $this->clearBootstrapCache($tenantId);

        $this->respondWithData([
            'updated' => true,
            'keys_updated' => $updated,
        ]);
    }

    // ─────────────────────────────────────────────────────────────────────────
    // SEO Configuration
    // ─────────────────────────────────────────────────────────────────────────

    /**
     * Default SEO settings.
     */
    private const SEO_DEFAULTS = [
        'seo_title_suffix' => '',
        'seo_meta_description' => '',
        'seo_meta_keywords' => '',
        'seo_auto_sitemap' => '1',
        'seo_canonical_urls' => '1',
        'seo_open_graph' => '1',
        'seo_twitter_cards' => '1',
        'seo_robots_txt' => '',
        'seo_google_verification' => '',
        'seo_bing_verification' => '',
    ];

    /**
     * GET /api/v2/admin/config/seo
     *
     * Returns SEO configuration for the current tenant.
     * Merges data from tenant_settings (seo_* prefix) with direct tenant columns.
     */
    public function getSeoConfig(): void
    {
        $this->requireAdmin();
        $tenantId = TenantContext::getId();

        // Read from tenant_settings
        $stored = $this->readSettingsByPrefix($tenantId, 'seo_');

        // Also read direct tenant SEO columns
        $tenant = Database::query(
            "SELECT meta_title, meta_description, h1_headline, hero_intro FROM tenants WHERE id = ?",
            [$tenantId]
        )->fetch();

        $config = [];
        foreach (self::SEO_DEFAULTS as $key => $defaultValue) {
            $rawValue = $stored[$key] ?? $defaultValue;
            if (in_array($key, ['seo_auto_sitemap', 'seo_canonical_urls', 'seo_open_graph', 'seo_twitter_cards'], true)) {
                $config[$key] = (bool) (int) $rawValue;
            } else {
                $config[$key] = $rawValue;
            }
        }

        // Include direct tenant SEO columns
        $config['tenant_meta_title'] = $tenant['meta_title'] ?? '';
        $config['tenant_meta_description'] = $tenant['meta_description'] ?? '';
        $config['tenant_h1_headline'] = $tenant['h1_headline'] ?? '';
        $config['tenant_hero_intro'] = $tenant['hero_intro'] ?? '';

        $this->respondWithData([
            'tenant_id' => $tenantId,
            'seo' => $config,
        ]);
    }

    /**
     * PUT /api/v2/admin/config/seo
     *
     * Update SEO configuration for the current tenant.
     * Body: { "seo_title_suffix": " | My Timebank", "seo_auto_sitemap": true, ... }
     *
     * Also accepts tenant_meta_title, tenant_meta_description, tenant_h1_headline,
     * tenant_hero_intro which update the tenants table directly.
     */
    public function updateSeoConfig(): void
    {
        $this->requireAdmin();
        $tenantId = TenantContext::getId();
        $input = $this->getAllInput();

        if (empty($input)) {
            $this->respondWithError(ApiErrorCodes::VALIDATION_ERROR, 'Request body is empty', null, 422);
            return;
        }

        $this->ensureTenantSettingsTable();

        $updated = [];

        // Handle tenant_settings keys
        foreach ($input as $key => $value) {
            if (array_key_exists($key, self::SEO_DEFAULTS)) {
                $storeValue = is_bool($value) ? ($value ? '1' : '0') : (string) $value;
                $type = is_bool($value) ? 'boolean' : 'string';
                $this->upsertSetting($tenantId, $key, $storeValue, $type);
                $updated[] = $key;
            }
        }

        // Handle direct tenant column updates (mapped from tenant_ prefix)
        $tenantColumnMap = [
            'tenant_meta_title' => 'meta_title',
            'tenant_meta_description' => 'meta_description',
            'tenant_h1_headline' => 'h1_headline',
            'tenant_hero_intro' => 'hero_intro',
        ];

        $directUpdates = [];
        foreach ($tenantColumnMap as $inputKey => $column) {
            if (array_key_exists($inputKey, $input)) {
                $directUpdates[$column] = $input[$inputKey];
                $updated[] = $inputKey;
            }
        }

        if (!empty($directUpdates)) {
            $setClauses = [];
            $params = [];
            foreach ($directUpdates as $col => $val) {
                $setClauses[] = "`{$col}` = ?";
                $params[] = $val;
            }
            $params[] = $tenantId;
            Database::query(
                "UPDATE tenants SET " . implode(', ', $setClauses) . " WHERE id = ?",
                $params
            );
        }

        if (empty($updated)) {
            $this->respondWithError(ApiErrorCodes::VALIDATION_ERROR, 'No recognized SEO settings provided', null, 422);
            return;
        }

        $this->clearBootstrapCache($tenantId);

        $this->respondWithData([
            'updated' => true,
            'keys_updated' => $updated,
        ]);
    }

    // ─────────────────────────────────────────────────────────────────────────
    // Native App / PWA Configuration
    // ─────────────────────────────────────────────────────────────────────────

    /**
     * Default native app settings.
     */
    private const NATIVE_APP_DEFAULTS = [
        'native_app_name' => '',
        'native_app_short_name' => '',
        'native_app_bundle_id' => '',
        'native_app_package_name' => '',
        'native_app_version' => '1.0.0',
        'native_app_push_enabled' => '0',
        'native_app_fcm_server_key' => '',
        'native_app_apns_key_id' => '',
        'native_app_apns_team_id' => '',
        'native_app_service_worker' => '1',
        'native_app_install_prompt' => '1',
        'native_app_theme_color' => '#1976D2',
        'native_app_background_color' => '#ffffff',
        'native_app_display' => 'standalone',
        'native_app_orientation' => 'portrait',
    ];

    /**
     * Sensitive native app keys that should be masked in GET responses.
     */
    private const NATIVE_APP_SENSITIVE_KEYS = [
        'native_app_fcm_server_key',
        'native_app_apns_key_id',
    ];

    /**
     * GET /api/v2/admin/config/native-app
     *
     * Returns native app / PWA configuration for the current tenant.
     */
    public function getNativeAppConfig(): void
    {
        $this->requireAdmin();
        $tenantId = TenantContext::getId();

        $stored = $this->readSettingsByPrefix($tenantId, 'native_app_');

        $config = [];
        foreach (self::NATIVE_APP_DEFAULTS as $key => $defaultValue) {
            $rawValue = $stored[$key] ?? $defaultValue;

            // Mask sensitive keys
            if (in_array($key, self::NATIVE_APP_SENSITIVE_KEYS, true) && !empty($rawValue)) {
                $config[$key] = str_repeat('*', max(0, strlen($rawValue) - 4)) . substr($rawValue, -4);
                $config[$key . '_set'] = true;
            } elseif (in_array($key, ['native_app_push_enabled', 'native_app_service_worker', 'native_app_install_prompt'], true)) {
                $config[$key] = (bool) (int) $rawValue;
            } else {
                $config[$key] = $rawValue;
            }
        }

        // Add boolean flags for whether sensitive keys are configured
        foreach (self::NATIVE_APP_SENSITIVE_KEYS as $sensitiveKey) {
            if (!isset($config[$sensitiveKey . '_set'])) {
                $config[$sensitiveKey . '_set'] = false;
            }
        }

        $this->respondWithData([
            'tenant_id' => $tenantId,
            'native_app' => $config,
        ]);
    }

    /**
     * PUT /api/v2/admin/config/native-app
     *
     * Update native app / PWA configuration for the current tenant.
     * Body: { "native_app_name": "My App", "native_app_push_enabled": true, ... }
     */
    public function updateNativeAppConfig(): void
    {
        $this->requireAdmin();
        $tenantId = TenantContext::getId();
        $input = $this->getAllInput();

        if (empty($input)) {
            $this->respondWithError(ApiErrorCodes::VALIDATION_ERROR, 'Request body is empty', null, 422);
            return;
        }

        $this->ensureTenantSettingsTable();

        $updated = [];
        foreach ($input as $key => $value) {
            if (!array_key_exists($key, self::NATIVE_APP_DEFAULTS)) {
                continue;
            }

            // Validate display mode
            if ($key === 'native_app_display') {
                $validDisplayModes = ['standalone', 'fullscreen', 'minimal-ui', 'browser'];
                if (!in_array($value, $validDisplayModes, true)) {
                    $this->respondWithError(ApiErrorCodes::VALIDATION_ERROR, 'Invalid display mode. Must be one of: ' . implode(', ', $validDisplayModes), $key, 422);
                    return;
                }
            }

            // Validate orientation
            if ($key === 'native_app_orientation') {
                $validOrientations = ['portrait', 'landscape', 'any'];
                if (!in_array($value, $validOrientations, true)) {
                    $this->respondWithError(ApiErrorCodes::VALIDATION_ERROR, 'Invalid orientation. Must be one of: ' . implode(', ', $validOrientations), $key, 422);
                    return;
                }
            }

            $storeValue = is_bool($value) ? ($value ? '1' : '0') : (string) $value;
            $type = is_bool($value) ? 'boolean' : 'string';
            $this->upsertSetting($tenantId, $key, $storeValue, $type);
            $updated[] = $key;
        }

        if (empty($updated)) {
            $this->respondWithError(ApiErrorCodes::VALIDATION_ERROR, 'No recognized native app settings provided', null, 422);
            return;
        }

        $this->clearBootstrapCache($tenantId);

        $this->respondWithData([
            'updated' => true,
            'keys_updated' => $updated,
        ]);
    }
}
