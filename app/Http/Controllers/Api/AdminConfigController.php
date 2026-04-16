<?php
// Copyright © 2024–2026 Jasper Ford
// SPDX-License-Identifier: AGPL-3.0-or-later
// Author: Jasper Ford
// See NOTICE file for attribution and acknowledgements.

namespace App\Http\Controllers\Api;

use App\Services\FederationFeatureService;
use App\Services\FeedRankingService;
use App\Services\GroupConfigurationService;
use App\Services\JobConfigurationService;
use App\Services\ListingConfigurationService;
use App\Services\ListingRankingService;
use App\Services\VolunteeringConfigurationService;
use App\Services\MemberRankingService;
use App\Services\RedisCache;
use App\Services\SearchService;
use App\Services\SmartMatchingEngine;
use App\Services\TenantFeatureConfig;
use App\Services\TenantSettingsService;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Facades\DB;
use App\Core\TenantContext;

/**
 * AdminConfigController -- Admin system configuration, features, modules, cache, jobs,
 * cron, AI, feed algorithm, SEO, image, language, native app, algorithm info.
 *
 * Fully converted from legacy delegation to direct service calls.
 */
class AdminConfigController extends BaseApiController
{
    protected bool $isV2Api = true;

    public function __construct(
        private readonly ListingRankingService $listingRankingService,
        private readonly SmartMatchingEngine $smartMatchingEngine,
        private readonly RedisCache $redisCache,
        private readonly FederationFeatureService $federationFeatureService,
        private readonly FeedRankingService $feedRankingService,
        private readonly MemberRankingService $memberRankingService,
        private readonly SearchService $searchService,
        private readonly TenantSettingsService $tenantSettingsService,
    ) {}

    // ─────────────────────────────────────────────────────────────────────────
    // Constants (from legacy)
    // ─────────────────────────────────────────────────────────────────────────

    private const TENANT_DIRECT_COLUMNS = [
        'name', 'slug', 'domain', 'tagline', 'description',
        'contact_email', 'contact_phone', 'default_layout',
        'logo_url', 'favicon_url', 'primary_color', 'og_image_url',
        'meta_title', 'meta_description', 'h1_headline', 'hero_intro',
    ];

    private const GENERAL_SETTING_KEYS = [
        'timezone', 'registration_mode', 'welcome_message',
        'maintenance_mode', 'default_currency', 'date_format',
        'time_format', 'items_per_page', 'max_upload_size_mb',
        'email_verification', 'admin_approval', 'welcome_credits',
        'footer_text',
    ];

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

    private const SEO_DEFAULTS = [
        'seo_title_suffix' => '',
        'seo_meta_description' => '',
        'seo_meta_keywords' => '',
        'seo_og_image_url' => '',
        'seo_auto_sitemap' => '1',
        'seo_canonical_urls' => '1',
        'seo_open_graph' => '1',
        'seo_twitter_cards' => '1',
        'seo_robots_txt' => '',
        'seo_google_verification' => '',
        'seo_bing_verification' => '',
    ];

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

    private const NATIVE_APP_SENSITIVE_KEYS = ['native_app_fcm_server_key', 'native_app_apns_key_id'];

    private const VALID_LANGUAGES = ['en', 'ga', 'de', 'fr', 'it', 'pt', 'es', 'nl', 'pl', 'ja', 'ar'];

    // =========================================================================
    // Config (already native)
    // =========================================================================

    /** GET /api/v2/admin/config */
    public function getConfig(): JsonResponse
    {
        $this->requireAdmin();
        $tenantId = $this->getTenantId();

        $tenant = DB::selectOne("SELECT features, configuration FROM tenants WHERE id = ?", [$tenantId]);

        $features = TenantFeatureConfig::FEATURE_DEFAULTS;
        if ($tenant && !empty($tenant->features)) {
            $dbFeatures = json_decode($tenant->features, true) ?: [];
            foreach ($dbFeatures as $key => $value) {
                if (array_key_exists($key, $features)) {
                    $features[$key] = (bool) $value;
                }
            }
        }

        $modules = TenantFeatureConfig::MODULE_DEFAULTS;
        if ($tenant && !empty($tenant->configuration)) {
            $config = json_decode($tenant->configuration, true) ?: [];
            $dbModules = $config['modules'] ?? [];
            foreach ($dbModules as $key => $value) {
                if (array_key_exists($key, $modules)) {
                    $modules[$key] = (bool) $value;
                }
            }
        }

        return $this->respondWithData([
            'tenant_id' => $tenantId,
            'features' => $features,
            'modules' => $modules,
        ]);
    }

    /** PUT /api/v2/admin/config/features */
    public function updateFeature(): JsonResponse
    {
        $this->requireAdmin();
        $tenantId = $this->getTenantId();

        $featureName = $this->input('feature');
        $enabled = $this->input('enabled');

        if (!$featureName || !is_string($featureName)) {
            return $this->respondWithError('VALIDATION_ERROR', __('api.feature_name_required'), 'feature', 422);
        }
        if (!array_key_exists($featureName, TenantFeatureConfig::FEATURE_DEFAULTS)) {
            return $this->respondWithError('VALIDATION_ERROR', __('api.unknown_feature', ['feature' => $featureName]), 'feature', 422);
        }
        if ($enabled === null) {
            return $this->respondWithError('VALIDATION_ERROR', __('api.enabled_required'), 'enabled', 422);
        }

        $tenant = DB::selectOne("SELECT features FROM tenants WHERE id = ?", [$tenantId]);
        $features = ($tenant && !empty($tenant->features)) ? (json_decode($tenant->features, true) ?: []) : [];
        $features[$featureName] = (bool) $enabled;

        DB::update("UPDATE tenants SET features = ? WHERE id = ?", [json_encode($features), $tenantId]);

        if ($featureName === 'federation') {
            if ((bool) $enabled) {
                $this->federationFeatureService->enableTenantFeature(FederationFeatureService::TENANT_FEDERATION_ENABLED, $tenantId);
            } else {
                $this->federationFeatureService->disableTenantFeature(FederationFeatureService::TENANT_FEDERATION_ENABLED, $tenantId);
            }
        }

        $this->redisCache->delete('tenant_bootstrap', $tenantId);

        return $this->respondWithData(['feature' => $featureName, 'enabled' => (bool) $enabled]);
    }

    /** PUT /api/v2/admin/config/modules */
    public function updateModule(): JsonResponse
    {
        $this->requireAdmin();
        $tenantId = $this->getTenantId();

        $moduleName = $this->input('module');
        $enabled = $this->input('enabled');

        if (!$moduleName || !is_string($moduleName)) {
            return $this->respondWithError('VALIDATION_ERROR', __('api.module_name_required'), 'module', 422);
        }
        if (!array_key_exists($moduleName, TenantFeatureConfig::MODULE_DEFAULTS)) {
            return $this->respondWithError('VALIDATION_ERROR', __('api.unknown_module', ['module' => $moduleName]), 'module', 422);
        }
        if ($enabled === null) {
            return $this->respondWithError('VALIDATION_ERROR', __('api.enabled_required'), 'enabled', 422);
        }

        $tenant = DB::selectOne("SELECT configuration FROM tenants WHERE id = ?", [$tenantId]);
        $config = ($tenant && !empty($tenant->configuration)) ? (json_decode($tenant->configuration, true) ?: []) : [];
        if (!isset($config['modules'])) {
            $config['modules'] = TenantFeatureConfig::MODULE_DEFAULTS;
        }
        $config['modules'][$moduleName] = (bool) $enabled;

        DB::update("UPDATE tenants SET configuration = ? WHERE id = ?", [json_encode($config), $tenantId]);
        $this->redisCache->delete('tenant_bootstrap', $tenantId);

        return $this->respondWithData(['module' => $moduleName, 'enabled' => (bool) $enabled]);
    }

    // =========================================================================
    // Cache (already native)
    // =========================================================================

    /** GET /api/v2/admin/config/cache-stats */
    public function cacheStats(): JsonResponse
    {
        $this->requireAdmin();
        $stats = $this->redisCache->getStats();

        return $this->respondWithData([
            'redis_connected' => $stats['enabled'] ?? false,
            'redis_memory_used' => $stats['memory_used'] ?? '0B',
            'redis_keys_count' => $stats['total_keys'] ?? 0,
            'cache_hit_rate' => 0.0,
        ]);
    }

    /** POST /api/v2/admin/config/clear-cache */
    public function clearCache(): JsonResponse
    {
        $this->requireAdmin();
        $tenantId = $this->getTenantId();
        $type = $this->input('type', 'tenant');

        try {
            if ($type === 'all') {
                // Cross-tenant cache clear requires super admin privileges
                $this->requireSuperAdmin();
                foreach ([1, 2, 3, 4, 5] as $tid) {
                    $this->redisCache->clearTenant($tid);
                }
            } else {
                $this->redisCache->clearTenant($tenantId);
            }
        } catch (\Throwable $e) {
            return $this->respondWithError('SERVER_ERROR', __('api.failed_to_clear_cache'), null, 500);
        }

        return $this->respondWithData(['cleared' => true, 'type' => $type]);
    }

    // =========================================================================
    // Jobs (already native)
    // =========================================================================

    /** GET /api/v2/admin/config/jobs */
    public function getJobs(): JsonResponse
    {
        $this->requireAdmin();

        $jobs = [
            ['id' => 'digest_emails', 'name' => 'Email Digest Sender', 'status' => 'idle', 'last_run_at' => null, 'next_run_at' => null],
            ['id' => 'badge_checker', 'name' => 'Badge Award Checker', 'status' => 'idle', 'last_run_at' => null, 'next_run_at' => null],
            ['id' => 'streak_updater', 'name' => 'Login Streak Updater', 'status' => 'idle', 'last_run_at' => null, 'next_run_at' => null],
        ];

        return $this->respondWithData($jobs);
    }

    /** POST /api/v2/admin/config/jobs/run */
    public function runJob(): JsonResponse
    {
        $this->requireAdmin();
        return $this->respondWithData(['triggered' => true]);
    }

    // =========================================================================
    // Cron Jobs
    // =========================================================================

    /** GET /api/v2/admin/config/cron-jobs */
    public function getCronJobs(): JsonResponse
    {
        $this->requireAdmin();
        $tenantId = TenantContext::getId();

        $jobs = $this->getCronJobDefinitions();

        $lastRuns = [];
        try {
            $results = DB::select("
                SELECT cl1.job_id, cl1.status, cl1.executed_at
                FROM cron_logs cl1
                INNER JOIN (
                    SELECT job_id, MAX(executed_at) as max_date
                    FROM cron_logs
                    WHERE tenant_id = ?
                    GROUP BY job_id
                ) cl2 ON cl1.job_id = cl2.job_id AND cl1.executed_at = cl2.max_date
                WHERE cl1.tenant_id = ?
            ", [$tenantId, $tenantId]);

            foreach ($results as $row) {
                $lastRuns[$row->job_id] = [
                    'last_run_at' => $row->executed_at,
                    'last_status' => $row->status === 'running' ? null : $row->status,
                ];
            }
        } catch (\Throwable $e) {
            // cron_logs table may not exist yet
        }

        $jobSettings = [];
        try {
            $results = DB::select("SELECT job_id, is_enabled FROM cron_job_settings");
            foreach ($results as $row) {
                $jobSettings[$row->job_id] = (int) $row->is_enabled;
            }
        } catch (\Throwable $e) {
            // Table may not exist yet
        }

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

        return $this->respondWithData($response);
    }

    /** POST /api/v2/admin/config/cron-jobs/run */
    public function runCronJob(): JsonResponse
    {
        $adminId = $this->requireAdmin();
        $tenantId = TenantContext::getId();

        $uri = request()->getRequestUri();
        preg_match('#/api/v2/admin/system/cron-jobs/(\d+)/run#', $uri, $matches);
        $numericId = (int) ($matches[1] ?? 0);

        if ($numericId < 1) {
            return $this->respondWithError('VALIDATION_ERROR', __('api.invalid_id', ['resource' => 'job']), 'id', 400);
        }

        $jobs = $this->getCronJobDefinitions();
        $jobIndex = $numericId - 1;

        if (!isset($jobs[$jobIndex])) {
            return $this->respondWithError('NOT_FOUND', __('api.cron_job_not_found'), 'id', 404);
        }

        $job = $jobs[$jobIndex];
        $jobSlug = $job['id'];

        try {
            $setting = DB::selectOne("SELECT is_enabled FROM cron_job_settings WHERE job_id = ?", [$jobSlug]);
            if ($setting && !$setting->is_enabled) {
                return $this->respondWithError('VALIDATION_ERROR', __('api.cannot_run_disabled_job'), 'status', 422);
            }
        } catch (\Throwable $e) {
            // Table may not exist
        }

        // Ensure cron_logs table exists
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
                    INDEX idx_executed_at (executed_at)
                )
            ");
        } catch (\Throwable $e) {
            // Already exists
        }

        $startTime = microtime(true);
        $logId = null;

        try {
            DB::insert(
                "INSERT INTO cron_logs (job_id, status, output, duration_seconds, executed_by, tenant_id) VALUES (?, 'running', 'Job started via API...', 0, ?, ?)",
                [$jobSlug, $adminId, $tenantId]
            );
            $logId = DB::getPdo()->lastInsertId();
        } catch (\Throwable $e) {
            // Ignore log creation failure
        }

        $output = '';
        $status = 'success';

        // Build allowlist of permitted script paths from hardcoded job definitions
        $allowedScripts = [];
        foreach ($this->getCronJobDefinitions() as $def) {
            if (isset($def['command']) && strpos($def['command'], 'scripts/') === 0) {
                $scriptParts = explode(' ', $def['command']);
                $allowedScripts[] = $scriptParts[0];
            }
        }

        try {
            if (isset($job['command']) && strpos($job['command'], 'scripts/') === 0) {
                $parts = explode(' ', $job['command']);
                $scriptRelative = $parts[0];
                $args = $parts[1] ?? '';

                // Validate script is in the allowlist and contains no path traversal
                if (!in_array($scriptRelative, $allowedScripts, true) || str_contains($scriptRelative, '..')) {
                    $output = "Script not permitted: {$scriptRelative}";
                    $status = 'error';
                } else {
                    $script = dirname(__DIR__, 4) . '/' . $scriptRelative;

                    if (file_exists($script)) {
                        if (!defined('CRON_INTERNAL_RUN')) {
                            define('CRON_INTERNAL_RUN', true);
                        }
                        $oldArgv = $GLOBALS['argv'] ?? [];
                        $oldArgc = $GLOBALS['argc'] ?? 0;
                        $GLOBALS['argv'] = [basename($script), $args];
                        $GLOBALS['argc'] = count($GLOBALS['argv']);

                        // Legacy script inclusion — retained for backward compatibility with admin-triggered scripts
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
                }
            } else {
                if (!defined('CRON_INTERNAL_RUN')) {
                    define('CRON_INTERNAL_RUN', true);
                }

                $cronKey = \App\Core\Env::get('CRON_KEY');

                // Maps admin panel job IDs → CronJobRunner public method names.
                // Every job in getCronJobDefinitions() must have an entry here
                // or in $adminMethodMap below.
                $methodMap = [
                    // Master
                    'run-all' => 'runAll',
                    // Notifications
                    'process-queue' => 'runInstantQueue',
                    'daily-digest' => 'dailyDigest',
                    'weekly-digest' => 'weeklyDigest',
                    // Newsletters
                    'process-newsletters' => 'processNewsletters',
                    'process-recurring' => 'processRecurring',
                    'process-newsletter-queue' => 'processNewsletterQueue',
                    // Matching
                    'notify-hot-matches' => 'notifyHotMatches',
                    'match-digest-daily' => 'matchDigestDaily',
                    'match-digest-weekly' => 'matchDigestWeekly',
                    // Gamification
                    'gamification-daily' => 'gamificationDaily',
                    'gamification-campaigns' => 'gamificationCampaigns',
                    'gamification-leaderboard' => 'gamificationLeaderboard',
                    'gamification-challenges' => 'gamificationChallenges',
                    'gamification-weekly-digest' => 'gamificationWeeklyDigest',
                    'gamification-streaks' => 'gamificationStreaks',
                    'gamification-cleanup' => 'gamificationCleanup',
                    // Groups
                    'group-weekly-digest' => 'groupWeeklyDigest',
                    // Security
                    'abuse-detection' => 'abuseDetection',
                    'abuse-daily-report' => 'abuseDailyReport',
                    'abuse-cleanup' => 'abuseCleanup',
                    // Verification
                    'verification-reminders' => 'verificationReminders',
                    'expire-verifications' => 'expireVerifications',
                    'purge-verification-sessions' => 'purgeVerificationSessions',
                    // Volunteering
                    'volunteer-pre-shift' => 'volunteerPreShiftReminders',
                    'volunteer-post-shift' => 'volunteerPostShiftFeedback',
                    'volunteer-lapsed-nudge' => 'volunteerLapsedNudge',
                    'volunteer-expiry-warnings' => 'volunteerExpiryWarnings',
                    'recurring-shifts' => 'recurringShifts',
                    'volunteer-expire-consents' => 'volunteerExpireConsents',
                    // Maintenance
                    'cleanup' => 'cleanup',
                    'geocode-batch' => 'geocodeBatch',
                    'event-reminders' => 'eventReminders',
                    'inactive-members' => 'inactiveMembers',
                    'listing-expiry' => 'listingExpiry',
                    'listing-expiry-reminders' => 'listingExpiryReminders',
                    'job-expiry' => 'jobExpiry',
                    'federation-weekly-digest' => 'federationWeeklyDigest',
                    'balance-alerts' => 'balanceAlerts',
                    'goal-reminders' => 'goalReminders',
                    'retry-failed-webhooks' => 'retryFailedWebhooks',
                ];

                $adminMethodMap = [
                    'update-featured-groups' => 'cronUpdateFeaturedGroups',
                ];

                if (isset($methodMap[$jobSlug])) {
                    // CronJobRunner handles all cron jobs via direct service calls.
                    // Scheduling is handled by bootstrap/app.php (Laravel scheduler).
                    if (!class_exists('\App\Services\CronJobRunner')) {
                        $output = "CronJobRunner not available — job '{$jobSlug}' requires App\\Services\\CronJobRunner";
                        $status = 'error';
                    } else {
                        $controller = new \App\Services\CronJobRunner();
                        $method = $methodMap[$jobSlug];
                        ob_start();
                        request()->query->set('key', $cronKey);
                        $controller->$method();
                        request()->query->remove('key');
                        $output = ob_get_clean() ?: 'Completed (no output)';
                    }
                } elseif (isset($adminMethodMap[$jobSlug])) {
                    // Direct service call — no legacy controller delegation
                    if ($jobSlug === 'update-featured-groups') {
                        $stats = \App\Services\SmartGroupRankingService::updateAllFeaturedGroups();
                        $output = 'Featured groups updated. Stats: ' . json_encode($stats);
                    } else {
                        $output = "No direct implementation for admin job: {$jobSlug}";
                        $status = 'error';
                    }
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

        if ($logId) {
            try {
                DB::update(
                    "UPDATE cron_logs SET status = ?, output = ?, duration_seconds = ? WHERE id = ?",
                    [$status, substr($output, 0, 65000), round($duration, 2), $logId]
                );
            } catch (\Throwable $e) {
                // Ignore log update failure
            }
        }

        return $this->respondWithData([
            'triggered' => true,
            'job_slug' => $jobSlug,
            'job_name' => $job['name'],
            'status' => $status,
            'duration' => round($duration, 2),
            'output' => substr($output, 0, 500),
        ]);
    }

    // =========================================================================
    // Settings
    // =========================================================================

    /** GET /api/v2/admin/config/settings */
    public function getSettings(): JsonResponse
    {
        $this->requireAdmin();
        $tenantId = TenantContext::getId();

        $tenantRow = DB::selectOne("SELECT * FROM tenants WHERE id = ?", [$tenantId]);
        if (!$tenantRow) {
            return $this->respondWithError('NOT_FOUND', __('api.tenant_not_found'), null, 404);
        }
        $tenant = (array)$tenantRow;

        $directSettings = [];
        foreach (self::TENANT_DIRECT_COLUMNS as $col) {
            $directSettings[$col] = $tenant[$col] ?? null;
        }

        $kvSettings = $this->readSettingsByPrefix($tenantId, 'general.');
        $generalSettings = [];
        foreach (self::GENERAL_SETTING_KEYS as $key) {
            $generalSettings[$key] = $kvSettings['general.' . $key] ?? null;
        }

        return $this->respondWithData([
            'tenant_id' => $tenantId,
            'tenant' => $directSettings,
            'settings' => $generalSettings,
        ]);
    }

    /** PUT /api/v2/admin/config/settings */
    public function updateSettings(): JsonResponse
    {
        $adminId = $this->requireAdmin();
        $tenantId = TenantContext::getId();
        $input = $this->getAllInput();

        if (empty($input)) {
            return $this->respondWithError('VALIDATION_ERROR', __('api.request_body_empty'), null, 422);
        }

        $this->ensureTenantSettingsTable();

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
            return $this->respondWithError('VALIDATION_ERROR', __('api.no_recognized_settings', ['keys' => implode(', ', $unknownKeys)]), null, 422);
        }

        if (!empty($directUpdates)) {
            $setClauses = [];
            $params = [];
            foreach ($directUpdates as $col => $val) {
                $setClauses[] = "`{$col}` = ?";
                $params[] = $val;
            }
            $params[] = $tenantId;
            DB::update("UPDATE tenants SET " . implode(', ', $setClauses) . " WHERE id = ?", $params);
        }

        if (isset($kvUpdates['welcome_credits'])) {
            $wc = (int) $kvUpdates['welcome_credits'];
            if ($wc < 0 || $wc > 100) {
                return $this->respondWithError('VALIDATION_ERROR', __('api.welcome_credits_range'), 'welcome_credits', 422);
            }
            $kvUpdates['welcome_credits'] = (string) $wc;
        }

        // Validate max_upload_size_mb — prevent dangerously large values
        if (isset($kvUpdates['max_upload_size_mb'])) {
            $maxMb = (int) $kvUpdates['max_upload_size_mb'];
            if ($maxMb < 1 || $maxMb > 50) {
                return $this->respondWithError('VALIDATION_ERROR', __('api.max_upload_size_range'), 'max_upload_size_mb', 422);
            }
            $kvUpdates['max_upload_size_mb'] = (string) $maxMb;
        }

        // Validate items_per_page — prevent abuse
        if (isset($kvUpdates['items_per_page'])) {
            $ipp = (int) $kvUpdates['items_per_page'];
            if ($ipp < 5 || $ipp > 100) {
                return $this->respondWithError('VALIDATION_ERROR', __('api.items_per_page_range'), 'items_per_page', 422);
            }
            $kvUpdates['items_per_page'] = (string) $ipp;
        }

        // Validate maintenance_mode — must be a boolean
        if (isset($kvUpdates['maintenance_mode'])) {
            if (!in_array((string) $kvUpdates['maintenance_mode'], ['true', 'false', '1', '0'], true)) {
                return $this->respondWithError('VALIDATION_ERROR', __('api.maintenance_mode_boolean'), 'maintenance_mode', 422);
            }
        }

        // Validate registration_mode — restricted values
        if (isset($kvUpdates['registration_mode'])) {
            if (!in_array($kvUpdates['registration_mode'], ['open', 'closed', 'invite_only'], true)) {
                return $this->respondWithError('VALIDATION_ERROR', __('api.registration_mode_invalid'), 'registration_mode', 422);
            }
        }

        // Validate default_currency — must be a 3-letter ISO 4217 code (lowercase).
        // Stripe accepts ~135 currencies; allow any 3-letter code and normalize to lowercase.
        if (isset($kvUpdates['default_currency'])) {
            $cur = strtolower(trim((string) $kvUpdates['default_currency']));
            if (!preg_match('/^[a-z]{3}$/', $cur)) {
                return $this->respondWithError('VALIDATION_ERROR', __('api.default_currency_invalid', []), 'default_currency', 422);
            }
            $kvUpdates['default_currency'] = $cur;
        }

        foreach ($kvUpdates as $key => $value) {
            $this->upsertSetting($tenantId, 'general.' . $key, (string) $value, $adminId);
        }

        // Audit log for settings changes
        \Illuminate\Support\Facades\Log::info('Admin config settings updated', [
            'admin_id' => $adminId,
            'tenant_id' => $tenantId,
            'direct_columns' => array_keys($directUpdates),
            'settings' => array_keys($kvUpdates),
        ]);

        $this->redisCache->delete('tenant_bootstrap', $tenantId);

        return $this->respondWithData([
            'updated' => true,
            'direct_columns_updated' => array_keys($directUpdates),
            'settings_updated' => array_keys($kvUpdates),
        ]);
    }

    // =========================================================================
    // AI Config
    // =========================================================================

    /** GET /api/v2/admin/config/ai */
    public function getAiConfig(): JsonResponse
    {
        $this->requireAdmin();
        $tenantId = TenantContext::getId();

        $settings = \App\Models\AiSettings::getAllForTenant($tenantId);

        return $this->respondWithData([
            'ai_enabled' => (bool) ($settings['ai_enabled'] ?? false),
            'ai_provider' => $settings['ai_provider'] ?? 'gemini',
            'models' => [
                'gemini' => $settings['gemini_model'] ?? 'gemini-pro',
                'openai' => $settings['openai_model'] ?? 'gpt-4-turbo',
                'anthropic' => $settings['claude_model'] ?? 'claude-sonnet-4-20250514',
                'ollama' => $settings['ollama_model'] ?? 'llama2',
            ],
            'api_keys' => [
                'gemini' => \App\Models\AiSettings::getMasked($tenantId, 'gemini_api_key'),
                'openai' => \App\Models\AiSettings::getMasked($tenantId, 'openai_api_key'),
                'anthropic' => \App\Models\AiSettings::getMasked($tenantId, 'anthropic_api_key'),
            ],
            'api_key_set' => [
                'gemini' => \App\Models\AiSettings::has($tenantId, 'gemini_api_key'),
                'openai' => \App\Models\AiSettings::has($tenantId, 'openai_api_key'),
                'anthropic' => \App\Models\AiSettings::has($tenantId, 'anthropic_api_key'),
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
        ]);
    }

    /** PUT /api/v2/admin/config/ai */
    public function updateAiConfig(): JsonResponse
    {
        $this->requireAdmin();
        $tenantId = TenantContext::getId();
        $input = $this->getAllInput();

        if (empty($input)) {
            return $this->respondWithError('VALIDATION_ERROR', __('api.request_body_empty'), null, 422);
        }

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
            return $this->respondWithError('VALIDATION_ERROR', __('api.no_recognized_ai_settings'), null, 422);
        }

        if (isset($toSave['ai_provider'])) {
            $validProviders = ['gemini', 'openai', 'anthropic', 'ollama'];
            if (!in_array($toSave['ai_provider'], $validProviders, true)) {
                return $this->respondWithError('VALIDATION_ERROR', __('api.invalid_ai_provider', ['providers' => implode(', ', $validProviders)]), 'ai_provider', 422);
            }
        }

        \App\Models\AiSettings::setMultiple($tenantId, $toSave);
        $this->redisCache->delete('tenant_bootstrap', $tenantId);

        return $this->respondWithData(['updated' => true, 'keys_updated' => array_keys($toSave)]);
    }

    // =========================================================================
    // Feed Algorithm
    // =========================================================================

    /** GET /api/v2/admin/config/feed-algorithm */
    public function getFeedAlgorithmConfig(): JsonResponse
    {
        $this->requireAdmin();
        $tenantId = TenantContext::getId();

        $stored = $this->readSettingsByPrefix($tenantId, 'feed_algo_');

        $config = [];
        foreach (self::FEED_ALGO_DEFAULTS as $key => $defaultValue) {
            $rawValue = $stored[$key] ?? $defaultValue;
            if (in_array($key, ['feed_algo_boost_images', 'feed_algo_boost_polls', 'feed_algo_penalize_links_only'], true)) {
                $config[$key] = (bool) (int) $rawValue;
            } elseif (strpos($defaultValue, '.') !== false) {
                $config[$key] = (float) $rawValue;
            } else {
                $config[$key] = (int) $rawValue;
            }
        }

        return $this->respondWithData(['tenant_id' => $tenantId, 'algorithm' => $config]);
    }

    /** PUT /api/v2/admin/config/feed-algorithm */
    public function updateFeedAlgorithmConfig(): JsonResponse
    {
        $adminId = $this->requireAdmin();
        $tenantId = TenantContext::getId();
        $input = $this->getAllInput();

        if (empty($input)) {
            return $this->respondWithError('VALIDATION_ERROR', __('api.request_body_empty'), null, 422);
        }

        $this->ensureTenantSettingsTable();

        $updated = [];
        foreach ($input as $key => $value) {
            if (!array_key_exists($key, self::FEED_ALGO_DEFAULTS)) continue;

            if (strpos($key, '_weight') !== false) {
                $floatVal = (float) $value;
                if ($floatVal < 0.0 || $floatVal > 1.0) {
                    return $this->respondWithError('VALIDATION_ERROR', __('api.weight_range', ['key' => $key]), $key, 422);
                }
            }

            $storeValue = is_bool($value) ? ($value ? '1' : '0') : (string) $value;
            $type = is_bool($value) ? 'boolean' : (is_float($value) ? 'float' : 'string');
            $this->upsertSetting($tenantId, $key, $storeValue, $adminId, $type);
            $updated[] = $key;
        }

        if (empty($updated)) {
            return $this->respondWithError('VALIDATION_ERROR', __('api.no_recognized_feed_settings'), null, 422);
        }

        $this->redisCache->delete('tenant_bootstrap', $tenantId);

        return $this->respondWithData(['updated' => true, 'keys_updated' => $updated]);
    }

    // =========================================================================
    // Image Config
    // =========================================================================

    /** GET /api/v2/admin/config/image */
    public function getImageConfig(): JsonResponse
    {
        $this->requireAdmin();
        $tenantId = TenantContext::getId();

        $stored = $this->readSettingsByPrefix($tenantId, 'image_');

        $tenantRow = DB::selectOne("SELECT configuration FROM tenants WHERE id = ?", [$tenantId]);
        $legacyConfig = [];
        if ($tenantRow && !empty($tenantRow->configuration)) {
            $config = json_decode($tenantRow->configuration, true) ?: [];
            $legacyConfig = $config['image_optimization'] ?? [];
        }

        $imageConfig = [];
        foreach (self::IMAGE_DEFAULTS as $key => $defaultValue) {
            $rawValue = $stored[$key] ?? $defaultValue;
            if (in_array($key, ['image_auto_webp', 'image_auto_resize', 'image_strip_exif', 'image_lazy_loading', 'image_serving_enabled'], true)) {
                $imageConfig[$key] = (bool) (int) $rawValue;
            } else {
                $imageConfig[$key] = (int) $rawValue;
            }
        }

        if (!empty($legacyConfig)) {
            $imageConfig['legacy'] = $legacyConfig;
        }

        return $this->respondWithData(['tenant_id' => $tenantId, 'images' => $imageConfig]);
    }

    /** PUT /api/v2/admin/config/image */
    public function updateImageConfig(): JsonResponse
    {
        $adminId = $this->requireAdmin();
        $tenantId = TenantContext::getId();
        $input = $this->getAllInput();

        if (empty($input)) {
            return $this->respondWithError('VALIDATION_ERROR', __('api.request_body_empty'), null, 422);
        }

        $this->ensureTenantSettingsTable();

        $updated = [];
        foreach ($input as $key => $value) {
            if (!array_key_exists($key, self::IMAGE_DEFAULTS)) continue;

            if ($key === 'image_max_size_mb' && ((int) $value < 1 || (int) $value > 50)) {
                return $this->respondWithError('VALIDATION_ERROR', __('api.max_file_size_range'), $key, 422);
            }
            if ($key === 'image_webp_quality' && ((int) $value < 50 || (int) $value > 100)) {
                return $this->respondWithError('VALIDATION_ERROR', __('api.webp_quality_range'), $key, 422);
            }
            if (in_array($key, ['image_max_width', 'image_max_height', 'image_thumbnail_width', 'image_thumbnail_height'], true) && ((int) $value < 50 || (int) $value > 10000)) {
                return $this->respondWithError('VALIDATION_ERROR', __('api.dimension_range', ['key' => $key]), $key, 422);
            }

            $storeValue = is_bool($value) ? ($value ? '1' : '0') : (string) $value;
            $this->upsertSetting($tenantId, $key, $storeValue, $adminId, is_bool($value) ? 'boolean' : 'integer');
            $updated[] = $key;
        }

        if (empty($updated)) {
            return $this->respondWithError('VALIDATION_ERROR', __('api.no_recognized_image_settings'), null, 422);
        }

        $this->redisCache->delete('tenant_bootstrap', $tenantId);

        return $this->respondWithData(['updated' => true, 'keys_updated' => $updated]);
    }

    // =========================================================================
    // SEO Config
    // =========================================================================

    /** GET /api/v2/admin/config/seo */
    public function getSeoConfig(): JsonResponse
    {
        $this->requireAdmin();
        $tenantId = TenantContext::getId();

        $stored = $this->readSettingsByPrefix($tenantId, 'seo_');

        $tenantRow = DB::selectOne("SELECT meta_title, meta_description, h1_headline, hero_intro FROM tenants WHERE id = ?", [$tenantId]);

        $config = [];
        foreach (self::SEO_DEFAULTS as $key => $defaultValue) {
            $rawValue = $stored[$key] ?? $defaultValue;
            if (in_array($key, ['seo_auto_sitemap', 'seo_canonical_urls', 'seo_open_graph', 'seo_twitter_cards'], true)) {
                $config[$key] = (bool) (int) $rawValue;
            } else {
                $config[$key] = $rawValue;
            }
        }

        $config['tenant_meta_title'] = $tenantRow->meta_title ?? '';
        $config['tenant_meta_description'] = $tenantRow->meta_description ?? '';
        $config['tenant_h1_headline'] = $tenantRow->h1_headline ?? '';
        $config['tenant_hero_intro'] = $tenantRow->hero_intro ?? '';

        return $this->respondWithData(['tenant_id' => $tenantId, 'seo' => $config]);
    }

    /** PUT /api/v2/admin/config/seo */
    public function updateSeoConfig(): JsonResponse
    {
        $adminId = $this->requireAdmin();
        $tenantId = TenantContext::getId();
        $input = $this->getAllInput();

        if (empty($input)) {
            return $this->respondWithError('VALIDATION_ERROR', __('api.request_body_empty'), null, 422);
        }

        $this->ensureTenantSettingsTable();

        $updated = [];

        foreach ($input as $key => $value) {
            if (array_key_exists($key, self::SEO_DEFAULTS)) {
                $storeValue = is_bool($value) ? ($value ? '1' : '0') : (string) $value;
                $this->upsertSetting($tenantId, $key, $storeValue, $adminId, is_bool($value) ? 'boolean' : 'string');
                $updated[] = $key;
            }
        }

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
            DB::update("UPDATE tenants SET " . implode(', ', $setClauses) . " WHERE id = ?", $params);
        }

        if (empty($updated)) {
            return $this->respondWithError('VALIDATION_ERROR', __('api.no_recognized_seo_settings'), null, 422);
        }

        $this->redisCache->delete('tenant_bootstrap', $tenantId);

        return $this->respondWithData(['updated' => true, 'keys_updated' => $updated]);
    }

    // =========================================================================
    // Sitemap Management
    // =========================================================================

    /** GET /api/v2/admin/config/sitemap-stats */
    public function getSitemapStats(): JsonResponse
    {
        $this->requireAdmin();
        $tenantId = TenantContext::getId();

        $sitemapService = app(\App\Services\SitemapService::class);
        $stats = $sitemapService->getStats($tenantId);

        $tenant = DB::selectOne("SELECT slug, domain FROM tenants WHERE id = ?", [$tenantId]);
        $frontendBase = rtrim(env('FRONTEND_URL', 'https://app.project-nexus.ie'), '/');
        $sitemapUrl = !empty($tenant->domain)
            ? 'https://' . rtrim($tenant->domain, '/') . '/sitemap.xml'
            : $frontendBase . '/sitemap.xml';

        return $this->respondWithData([
            'sitemap_url' => $sitemapUrl,
            'total_urls' => $stats['total_urls'],
            'content_types' => $stats['content_types'],
        ]);
    }

    /** POST /api/v2/admin/config/sitemap-clear-cache */
    public function clearSitemapCache(): JsonResponse
    {
        $this->requireAdmin();
        $tenantId = TenantContext::getId();

        $sitemapService = app(\App\Services\SitemapService::class);
        $cleared = $sitemapService->clearCache($tenantId);

        return $this->respondWithData(['cleared' => $cleared]);
    }

    // =========================================================================
    // Native App Config
    // =========================================================================

    /** GET /api/v2/admin/config/native-app */
    public function getNativeAppConfig(): JsonResponse
    {
        $this->requireAdmin();
        $tenantId = TenantContext::getId();

        $stored = $this->readSettingsByPrefix($tenantId, 'native_app_');

        $config = [];
        foreach (self::NATIVE_APP_DEFAULTS as $key => $defaultValue) {
            $rawValue = $stored[$key] ?? $defaultValue;

            if (in_array($key, self::NATIVE_APP_SENSITIVE_KEYS, true) && !empty($rawValue)) {
                $config[$key] = str_repeat('*', max(0, strlen($rawValue) - 4)) . substr($rawValue, -4);
                $config[$key . '_set'] = true;
            } elseif (in_array($key, ['native_app_push_enabled', 'native_app_service_worker', 'native_app_install_prompt'], true)) {
                $config[$key] = (bool) (int) $rawValue;
            } else {
                $config[$key] = $rawValue;
            }
        }

        foreach (self::NATIVE_APP_SENSITIVE_KEYS as $sensitiveKey) {
            if (!isset($config[$sensitiveKey . '_set'])) {
                $config[$sensitiveKey . '_set'] = false;
            }
        }

        return $this->respondWithData(['tenant_id' => $tenantId, 'native_app' => $config]);
    }

    /** PUT /api/v2/admin/config/native-app */
    public function updateNativeAppConfig(): JsonResponse
    {
        $adminId = $this->requireAdmin();
        $tenantId = TenantContext::getId();
        $input = $this->getAllInput();

        if (empty($input)) {
            return $this->respondWithError('VALIDATION_ERROR', __('api.request_body_empty'), null, 422);
        }

        $this->ensureTenantSettingsTable();

        $updated = [];
        foreach ($input as $key => $value) {
            if (!array_key_exists($key, self::NATIVE_APP_DEFAULTS)) continue;

            if ($key === 'native_app_display') {
                $valid = ['standalone', 'fullscreen', 'minimal-ui', 'browser'];
                if (!in_array($value, $valid, true)) {
                    return $this->respondWithError('VALIDATION_ERROR', __('api.invalid_display_mode', ['modes' => implode(', ', $valid)]), $key, 422);
                }
            }

            if ($key === 'native_app_orientation') {
                $valid = ['portrait', 'landscape', 'any'];
                if (!in_array($value, $valid, true)) {
                    return $this->respondWithError('VALIDATION_ERROR', __('api.invalid_orientation', ['orientations' => implode(', ', $valid)]), $key, 422);
                }
            }

            $storeValue = is_bool($value) ? ($value ? '1' : '0') : (string) $value;
            $this->upsertSetting($tenantId, $key, $storeValue, $adminId, is_bool($value) ? 'boolean' : 'string');
            $updated[] = $key;
        }

        if (empty($updated)) {
            return $this->respondWithError('VALIDATION_ERROR', __('api.no_recognized_native_app_settings'), null, 422);
        }

        $this->redisCache->delete('tenant_bootstrap', $tenantId);

        return $this->respondWithData(['updated' => true, 'keys_updated' => $updated]);
    }

    // =========================================================================
    // Algorithm Info (public endpoint)
    // =========================================================================

    /** GET /api/v2/config/algorithms — public, returns which algorithms are active */
    public function getAlgorithmInfo(): JsonResponse
    {
        $feedEnabled = $this->feedRankingService->isEnabled();
        $feed = [
            'name' => $feedEnabled ? 'EdgeRank' : 'Chronological',
            'key' => $feedEnabled ? 'edgerank' : 'chronological',
            'description' => $feedEnabled
                ? 'Ranked by engagement, freshness, social connections, and content quality'
                : 'Showing newest posts first',
        ];

        $listingsEnabled = $this->listingRankingService->isEnabled();
        $listings = [
            'name' => $listingsEnabled ? 'MatchRank' : 'Newest First',
            'key' => $listingsEnabled ? 'matchrank' : 'newest',
            'description' => $listingsEnabled
                ? 'Ranked by relevance, proximity, engagement, and reciprocity'
                : 'Showing newest listings first',
        ];

        $membersEnabled = $this->memberRankingService->isEnabled();
        $members = [
            'name' => $membersEnabled ? 'CommunityRank' : 'Alphabetical',
            'key' => $membersEnabled ? 'communityrank' : 'alphabetical',
            'description' => $membersEnabled
                ? 'Ranked by activity, contributions, reputation, and connections'
                : 'Sorted alphabetically by name',
        ];

        $matchingConfig = $this->smartMatchingEngine->getConfig();
        $matchingEnabled = !empty($matchingConfig['enabled']);
        $matching = [
            'name' => $matchingEnabled ? 'SmartMatch' : 'Disabled',
            'key' => $matchingEnabled ? 'smartmatch' : 'disabled',
            'description' => $matchingEnabled
                ? 'AI-powered matching based on skills, proximity, and reciprocity'
                : 'Smart matching is not active',
        ];

        return $this->respondWithData([
            'feed' => $feed, 'listings' => $listings, 'members' => $members, 'matching' => $matching,
        ]);
    }

    // =========================================================================
    // Algorithm Config
    // =========================================================================

    /** GET /api/v2/admin/config/algorithm */
    public function getAlgorithmConfig(): JsonResponse
    {
        $this->requireAdmin();

        return $this->respondWithData([
            'feed' => $this->feedRankingService->getConfig(),
            'listings' => $this->listingRankingService->getConfig(),
            'members' => $this->memberRankingService->getConfig(),
        ]);
    }

    /** PUT /api/v2/admin/config/algorithm/{area} */
    public function updateAlgorithmConfig($area): JsonResponse
    {
        $this->requireAdmin();
        $tenantId = TenantContext::getId();

        $validAreas = ['feed', 'listings', 'members'];
        if (!in_array($area, $validAreas, true)) {
            return $this->respondWithError('VALIDATION_ERROR', __('api.algorithm_area_invalid'), 'area', 422);
        }

        $input = $this->getAllInput();
        if (empty($input)) {
            return $this->respondWithError('VALIDATION_ERROR', __('api.request_body_empty'), null, 422);
        }

        $currentConfig = match ($area) {
            'feed' => $this->feedRankingService->getConfig(),
            'listings' => $this->listingRankingService->getConfig(),
            'members' => $this->memberRankingService->getConfig(),
        };

        $updated = [];
        foreach ($input as $key => $value) {
            if (!array_key_exists($key, $currentConfig)) continue;

            if (str_ends_with($key, '_weight') || str_ends_with($key, '_boost') || str_ends_with($key, '_minimum')) {
                $val = (float) $value;
                if ($val < 0.0 || $val > 10.0) {
                    return $this->respondWithError('VALIDATION_ERROR', __('api.algorithm_value_range', ['key' => $key]), $key, 422);
                }
            }

            $updated[$key] = $value;
        }

        if (empty($updated)) {
            return $this->respondWithError('VALIDATION_ERROR', __('api.no_recognized_algorithm_settings'), null, 422);
        }

        $tenantRow = DB::selectOne("SELECT configuration FROM tenants WHERE id = ?", [$tenantId]);
        $config = [];
        if ($tenantRow && !empty($tenantRow->configuration)) {
            $config = json_decode($tenantRow->configuration, true) ?: [];
        }

        if (!isset($config['algorithms'])) { $config['algorithms'] = []; }
        if (!isset($config['algorithms'][$area])) { $config['algorithms'][$area] = []; }

        foreach ($updated as $key => $value) {
            $config['algorithms'][$area][$key] = $value;
        }

        DB::update("UPDATE tenants SET configuration = ? WHERE id = ?", [json_encode($config), $tenantId]);

        match ($area) {
            'feed' => $this->feedRankingService->clearCache(),
            'listings' => $this->listingRankingService->clearCache(),
            'members' => $this->memberRankingService->clearCache(),
            default => null,
        };

        $this->redisCache->delete('tenant_bootstrap', $tenantId);

        return $this->respondWithData(['updated' => true, 'area' => $area, 'keys_updated' => array_keys($updated)]);
    }

    /** GET /api/v2/admin/config/algorithm-health */
    public function getAlgorithmHealth(): JsonResponse
    {
        $this->requireAdmin();
        $tenantId = TenantContext::getId();

        $fulltextIndexes = [];
        $indexChecks = [
            ['table' => 'listings', 'index' => 'ft_listings_search'],
            ['table' => 'users', 'index' => 'ft_users_search'],
            ['table' => 'feed_activity', 'index' => 'ft_feed_search'],
        ];

        foreach ($indexChecks as $check) {
            try {
                $row = DB::selectOne(
                    "SELECT COUNT(*) as cnt FROM information_schema.STATISTICS WHERE table_schema = DATABASE() AND table_name = ? AND index_name = ?",
                    [$check['table'], $check['index']]
                );
                $fulltextIndexes[$check['table']] = (int) ($row->cnt ?? 0) > 0;
            } catch (\Throwable $e) {
                $fulltextIndexes[$check['table']] = false;
            }
        }

        $cfData = [];
        try {
            $row = DB::selectOne(
                "SELECT COUNT(*) as cnt FROM listing_favorites lf JOIN listings l ON lf.listing_id = l.id WHERE l.tenant_id = ?",
                [$tenantId]
            );
            $cfData['listing_interactions'] = (int) ($row->cnt ?? 0);
        } catch (\Throwable $e) {
            $cfData['listing_interactions'] = 0;
        }

        try {
            $row = DB::selectOne(
                "SELECT COUNT(DISTINCT CONCAT(LEAST(sender_id, receiver_id), '-', GREATEST(sender_id, receiver_id))) as cnt
                 FROM transactions WHERE tenant_id = ? AND status = 'completed'",
                [$tenantId]
            );
            $cfData['member_interactions'] = (int) ($row->cnt ?? 0);
        } catch (\Throwable $e) {
            $cfData['member_interactions'] = 0;
        }

        $embeddings = [];
        try {
            $embResults = DB::select(
                "SELECT content_type, COUNT(*) as cnt FROM content_embeddings WHERE tenant_id = ? GROUP BY content_type",
                [$tenantId]
            );
            $embRows = [];
            foreach ($embResults as $r) { $embRows[$r->content_type] = $r->cnt; }

            $embeddings['listing_count'] = (int) ($embRows['listing'] ?? 0);
            $embeddings['user_count'] = (int) ($embRows['user'] ?? 0);
            $embeddings['total'] = $embeddings['listing_count'] + $embeddings['user_count'];
        } catch (\Throwable $e) {
            $embeddings = ['listing_count' => 0, 'user_count' => 0, 'total' => 0];
        }

        $enabled = [
            'edgerank' => $this->feedRankingService->isEnabled(),
            'matchrank' => $this->listingRankingService->isEnabled(),
            'communityrank' => $this->memberRankingService->isEnabled(),
        ];

        $searchHealth = [
            'meilisearch_available' => $this->searchService->isAvailable(),
            'listing_index_count' => 0,
        ];

        return $this->respondWithData([
            'fulltext' => $fulltextIndexes,
            'collaborative_filtering' => $cfData,
            'embeddings' => $embeddings,
            'enabled' => $enabled,
            'search' => $searchHealth,
        ]);
    }

    // =========================================================================
    // Language Config
    // =========================================================================

    /** GET /api/v2/admin/config/language */
    public function getLanguageConfig(): JsonResponse
    {
        $this->requireAdmin();
        $tenantId = TenantContext::getId();

        $tenantRow = DB::selectOne("SELECT configuration FROM tenants WHERE id = ?", [$tenantId]);

        $config = [];
        if ($tenantRow && !empty($tenantRow->configuration)) {
            $config = json_decode($tenantRow->configuration, true) ?: [];
        }

        return $this->respondWithData([
            'default_language' => $config['default_language'] ?? 'en',
            'supported_languages' => $config['supported_languages'] ?? ['en'],
        ]);
    }

    /** PUT /api/v2/admin/config/language */
    public function updateLanguageConfig(): JsonResponse
    {
        $this->requireAdmin();
        $tenantId = TenantContext::getId();
        $input = $this->getAllInput();

        if (isset($input['default_language'])) {
            if (!in_array($input['default_language'], self::VALID_LANGUAGES, true)) {
                return $this->respondWithError('VALIDATION_ERROR', __('api.invalid_default_language'), 'default_language', 400);
            }
        }

        if (isset($input['supported_languages'])) {
            if (!is_array($input['supported_languages'])) {
                return $this->respondWithError('VALIDATION_ERROR', __('api.supported_languages_must_be_array'), 'supported_languages', 400);
            }
            if (!in_array('en', $input['supported_languages'], true)) {
                $input['supported_languages'][] = 'en';
            }
            foreach ($input['supported_languages'] as $lang) {
                if (!in_array($lang, self::VALID_LANGUAGES, true)) {
                    return $this->respondWithError('VALIDATION_ERROR', __('api.invalid_language', ['lang' => $lang]), 'supported_languages', 400);
                }
            }
        }

        $tenantRow = DB::selectOne("SELECT configuration FROM tenants WHERE id = ?", [$tenantId]);
        $config = [];
        if ($tenantRow && !empty($tenantRow->configuration)) {
            $config = json_decode($tenantRow->configuration, true) ?: [];
        }

        if (isset($input['supported_languages'])) {
            $config['supported_languages'] = array_values($input['supported_languages']);
        }

        if (isset($input['default_language'])) {
            $supported = $config['supported_languages'] ?? ['en'];
            if (!in_array($input['default_language'], $supported, true)) {
                $config['default_language'] = 'en';
            } else {
                $config['default_language'] = $input['default_language'];
            }
        }

        DB::update("UPDATE tenants SET configuration = ? WHERE id = ?", [json_encode($config), $tenantId]);
        $this->redisCache->delete('tenant_bootstrap', $tenantId);

        return $this->respondWithData([
            'default_language' => $config['default_language'] ?? 'en',
            'supported_languages' => $config['supported_languages'] ?? ['en'],
        ]);
    }

    // =========================================================================
    // Private Helpers
    // =========================================================================

    private function ensureTenantSettingsTable(): void
    {
        try {
            DB::statement("
                CREATE TABLE IF NOT EXISTS `tenant_settings` (
                    `id` BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
                    `tenant_id` INT UNSIGNED NOT NULL,
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
        } catch (\Throwable $e) {
            // Already exists
        }
    }

    private function readSettingsByPrefix(int $tenantId, string $prefix): array
    {
        $this->ensureTenantSettingsTable();

        $rows = DB::select(
            "SELECT setting_key, setting_value FROM tenant_settings WHERE tenant_id = ? AND setting_key LIKE ?",
            [$tenantId, $prefix . '%']
        );

        $result = [];
        foreach ($rows as $row) {
            $result[$row->setting_key] = $row->setting_value;
        }
        return $result;
    }

    private function upsertSetting(int $tenantId, string $key, ?string $value, int $userId, string $type = 'string'): void
    {
        DB::statement(
            "INSERT INTO tenant_settings (tenant_id, setting_key, setting_value, setting_type, created_by, updated_by)
             VALUES (?, ?, ?, ?, ?, ?)
             ON DUPLICATE KEY UPDATE setting_value = VALUES(setting_value), setting_type = VALUES(setting_type), updated_by = VALUES(updated_by), updated_at = CURRENT_TIMESTAMP",
            [$tenantId, $key, $value, $type, $userId, $userId]
        );
    }

    /**
     * Complete list of all cron jobs managed by CronJobRunner.
     *
     * All jobs run automatically via the Laravel scheduler (artisan schedule:run)
     * which calls CronJobRunner::runAll() every minute. The command field is
     * the CronJobRunner method name (no HTTP endpoints — those were removed
     * 2026-04-02 to prevent duplicate email sends).
     */
    private function getCronJobDefinitions(): array
    {
        return [
            // ── Master ──
            ['id' => 'run-all', 'name' => 'Master Cron Runner', 'command' => 'runAll', 'schedule' => '* * * * *', 'category' => 'master', 'description' => 'Runs all appropriate cron tasks based on the current time. This is the only scheduled entry — all other jobs run inside it.'],

            // ── Notifications ──
            ['id' => 'process-queue', 'name' => 'Instant Notification Queue', 'command' => 'runInstantQueue', 'schedule' => '* * * * *', 'category' => 'notifications', 'description' => 'Processes the instant notification queue, sending pending notifications immediately.'],
            ['id' => 'daily-digest', 'name' => 'Daily Digest', 'command' => 'dailyDigest', 'schedule' => '0 8 * * *', 'category' => 'notifications', 'description' => 'Sends daily notification digest emails to users who opted for daily frequency.'],
            ['id' => 'weekly-digest', 'name' => 'Weekly Digest', 'command' => 'weeklyDigest', 'schedule' => '0 17 * * 5', 'category' => 'notifications', 'description' => 'Sends weekly notification digest emails (Fridays at 5 PM).'],

            // ── Newsletters ──
            ['id' => 'process-newsletters', 'name' => 'Process Scheduled Newsletters', 'command' => 'processNewsletters', 'schedule' => '*/5 * * * *', 'category' => 'newsletters', 'description' => 'Checks for newsletters scheduled to be sent and initiates their sending process.'],
            ['id' => 'process-recurring', 'name' => 'Process Recurring Newsletters', 'command' => 'processRecurring', 'schedule' => '*/15 * * * *', 'category' => 'newsletters', 'description' => 'Handles recurring/automated newsletters (e.g., weekly community updates).'],
            ['id' => 'process-newsletter-queue', 'name' => 'Newsletter Queue Processor', 'command' => 'processNewsletterQueue', 'schedule' => '* * * * *', 'category' => 'newsletters', 'description' => 'Processes the newsletter sending queue in batches for large sends.'],

            // ── Matching ──
            ['id' => 'notify-hot-matches', 'name' => 'Hot Match Notifications', 'command' => 'notifyHotMatches', 'schedule' => '0 * * * *', 'category' => 'matching', 'description' => 'Notifies users of new high-scoring matches.'],
            ['id' => 'match-digest-daily', 'name' => 'Daily Match Digest', 'command' => 'matchDigestDaily', 'schedule' => '0 9 * * *', 'category' => 'matching', 'description' => 'Sends daily match recommendations to users.'],
            ['id' => 'match-digest-weekly', 'name' => 'Weekly Match Digest', 'command' => 'matchDigestWeekly', 'schedule' => '0 9 * * 1', 'category' => 'matching', 'description' => 'Sends weekly match recommendations summary (Mondays 9 AM).'],

            // ── Gamification ──
            ['id' => 'gamification-daily', 'name' => 'Gamification Daily Tasks', 'command' => 'gamificationDaily', 'schedule' => '0 3 * * *', 'category' => 'gamification', 'description' => 'Processes streak resets, daily bonuses, and badge checks.'],
            ['id' => 'gamification-campaigns', 'name' => 'Process Achievement Campaigns', 'command' => 'gamificationCampaigns', 'schedule' => '0 * * * *', 'category' => 'gamification', 'description' => 'Processes recurring achievement campaigns.'],
            ['id' => 'gamification-leaderboard', 'name' => 'Leaderboard Snapshot', 'command' => 'gamificationLeaderboard', 'schedule' => '0 0 * * *', 'category' => 'gamification', 'description' => 'Creates daily leaderboard snapshots and finalizes seasons.'],
            ['id' => 'gamification-challenges', 'name' => 'Check Challenge Expirations', 'command' => 'gamificationChallenges', 'schedule' => '30 * * * *', 'category' => 'gamification', 'description' => 'Expires completed challenges and updates statuses.'],
            ['id' => 'gamification-weekly-digest', 'name' => 'Gamification Weekly Digest', 'command' => 'gamificationWeeklyDigest', 'schedule' => '0 4 * * 1', 'category' => 'gamification', 'description' => 'Sends weekly progress email digests to users.'],
            ['id' => 'gamification-streaks', 'name' => 'Gamification Streak Milestones', 'command' => 'gamificationStreaks', 'schedule' => '0 1 * * *', 'category' => 'gamification', 'description' => 'Checks and awards streak milestones (7/14/30/60/90/180/365 days).'],
            ['id' => 'gamification-cleanup', 'name' => 'Gamification Cleanup', 'command' => 'gamificationCleanup', 'schedule' => '0 3 * * 0', 'category' => 'gamification', 'description' => 'Cleans old XP notifications, campaign awards, and analytics data.'],

            // ── Groups ──
            ['id' => 'update-featured-groups', 'name' => 'Update Featured Groups', 'command' => 'updateFeaturedGroups', 'schedule' => '0 8 * * *', 'category' => 'groups', 'description' => 'Updates featured groups based on ranking algorithms.'],
            ['id' => 'group-weekly-digest', 'name' => 'Group Weekly Digests', 'command' => 'groupWeeklyDigest', 'schedule' => '0 9 * * 1', 'category' => 'groups', 'description' => 'Sends weekly analytics digest emails to group owners.'],

            // ── Security ──
            ['id' => 'abuse-detection', 'name' => 'Abuse Detection', 'command' => 'abuseDetection', 'schedule' => '0 * * * *', 'category' => 'security', 'description' => 'Scans transactions for potential abuse patterns.'],
            ['id' => 'abuse-daily-report', 'name' => 'Abuse Daily Report', 'command' => 'abuseDailyReport', 'schedule' => '0 7 * * *', 'category' => 'security', 'description' => 'Sends daily abuse detection report to admins.'],
            ['id' => 'abuse-cleanup', 'name' => 'Abuse Alert Cleanup', 'command' => 'abuseCleanup', 'schedule' => '0 2 * * 0', 'category' => 'security', 'description' => 'Archives old alerts and auto-dismisses low-severity items.'],

            // ── Identity Verification ──
            ['id' => 'verification-reminders', 'name' => 'Verification Reminders', 'command' => 'verificationReminders', 'schedule' => '0 */6 * * *', 'category' => 'verification', 'description' => 'Sends reminders to users with incomplete identity verifications.'],
            ['id' => 'expire-verifications', 'name' => 'Expire Abandoned Verifications', 'command' => 'expireVerifications', 'schedule' => '30 4 * * *', 'category' => 'verification', 'description' => 'Expires verification sessions abandoned for 72+ hours.'],
            ['id' => 'purge-verification-sessions', 'name' => 'Purge Old Verification Data', 'command' => 'purgeVerificationSessions', 'schedule' => '30 3 * * 0', 'category' => 'verification', 'description' => 'Purges completed/expired verification sessions older than 180 days.'],

            // ── Volunteering ──
            ['id' => 'volunteer-pre-shift', 'name' => 'Volunteer Pre-Shift Reminders', 'command' => 'volunteerPreShiftReminders', 'schedule' => '*/30 * * * *', 'category' => 'volunteering', 'description' => 'Sends reminders 24h and 2h before volunteer shifts.'],
            ['id' => 'volunteer-post-shift', 'name' => 'Volunteer Post-Shift Feedback', 'command' => 'volunteerPostShiftFeedback', 'schedule' => '*/30 * * * *', 'category' => 'volunteering', 'description' => 'Sends feedback request after completed shifts.'],
            ['id' => 'volunteer-lapsed-nudge', 'name' => 'Lapsed Volunteer Nudge', 'command' => 'volunteerLapsedNudge', 'schedule' => '0 5 * * *', 'category' => 'volunteering', 'description' => 'Nudges volunteers who haven\'t been active recently.'],
            ['id' => 'volunteer-expiry-warnings', 'name' => 'Volunteer Credential Expiry', 'command' => 'volunteerExpiryWarnings', 'schedule' => '0 5 * * *', 'category' => 'volunteering', 'description' => 'Warns volunteers about expiring credentials and training.'],
            ['id' => 'recurring-shifts', 'name' => 'Generate Recurring Shifts', 'command' => 'recurringShifts', 'schedule' => '0 6 * * *', 'category' => 'volunteering', 'description' => 'Auto-generates volunteer shifts 14 days ahead from recurring templates.'],
            ['id' => 'volunteer-expire-consents', 'name' => 'Expire Guardian Consents', 'command' => 'volunteerExpireConsents', 'schedule' => '0 5 * * *', 'category' => 'volunteering', 'description' => 'Expires guardian consent records that have passed their expiry date.'],

            // ── Maintenance ──
            ['id' => 'cleanup', 'name' => 'System Cleanup', 'command' => 'cleanup', 'schedule' => '0 0 * * *', 'category' => 'maintenance', 'description' => 'Cleans expired tokens, old queue entries, API tokens, and tracking data.'],
            ['id' => 'geocode-batch', 'name' => 'Batch Geocoding', 'command' => 'geocodeBatch', 'schedule' => '*/30 * * * *', 'category' => 'maintenance', 'description' => 'Geocodes users and listings missing lat/lng coordinates.'],
            ['id' => 'event-reminders', 'name' => 'Event Reminders', 'command' => 'eventReminders', 'schedule' => '*/15 * * * *', 'category' => 'notifications', 'description' => 'Sends reminders 24h and 1h before events.'],
            ['id' => 'inactive-members', 'name' => 'Inactive Member Detection', 'command' => 'inactiveMembers', 'schedule' => '0 2 * * *', 'category' => 'maintenance', 'description' => 'Detects and flags inactive members for follow-up.'],
            ['id' => 'listing-expiry', 'name' => 'Listing Expiry Processing', 'command' => 'listingExpiry', 'schedule' => '0 8 * * *', 'category' => 'maintenance', 'description' => 'Expires listings that have passed their expiry date.'],
            ['id' => 'listing-expiry-reminders', 'name' => 'Listing Expiry Reminders', 'command' => 'listingExpiryReminders', 'schedule' => '0 8 * * *', 'category' => 'notifications', 'description' => 'Warns listing owners 3 days before their listing expires.'],
            ['id' => 'job-expiry', 'name' => 'Job Vacancy Expiry', 'command' => 'jobExpiry', 'schedule' => '0 8 * * *', 'category' => 'maintenance', 'description' => 'Expires job vacancies that have passed their closing date.'],
            ['id' => 'federation-weekly-digest', 'name' => 'Federation Weekly Digest', 'command' => 'federationWeeklyDigest', 'schedule' => '0 9 * * 1', 'category' => 'notifications', 'description' => 'Sends federation activity digest to opted-in tenants.'],
            ['id' => 'balance-alerts', 'name' => 'Balance Alerts', 'command' => 'balanceAlerts', 'schedule' => '0 8 * * *', 'category' => 'notifications', 'description' => 'Checks organization wallet balances and sends low/critical alerts.'],
            ['id' => 'goal-reminders', 'name' => 'Goal Reminders', 'command' => 'goalReminders', 'schedule' => '0 8 * * *', 'category' => 'notifications', 'description' => 'Sends reminders for goals that are due or behind schedule.'],
            ['id' => 'retry-failed-webhooks', 'name' => 'Retry Failed Webhooks', 'command' => 'retryFailedWebhooks', 'schedule' => '*/5 * * * *', 'category' => 'maintenance', 'description' => 'Retries webhook deliveries that previously failed.'],
        ];
    }

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
                $interval = (int) substr($minute, 2);
                $currentMinute = (int) $now->format('i');
                $nextMinute = (int) (ceil($currentMinute / $interval) * $interval);
                if ($nextMinute >= 60) {
                    $next->modify('+1 hour');
                    $next->setTime((int) $next->format('H'), 0);
                } else {
                    $next->setTime((int) $now->format('H'), $nextMinute);
                }
                if ($next <= $now) $next->modify("+{$interval} minutes");
                return $next->format('Y-m-d H:i:s');
            }
            if ($minute !== '*' && $hour !== '*' && $dayOfWeek !== '*') {
                $targetDay = (int) $dayOfWeek;
                $currentDay = (int) $now->format('w');
                $daysUntil = ($targetDay - $currentDay + 7) % 7;
                if ($daysUntil === 0 && $now->format('H:i') >= sprintf('%02d:%02d', $hour, $minute)) {
                    $daysUntil = 7;
                }
                $next->modify("+{$daysUntil} days");
                $next->setTime((int) $hour, (int) $minute);
                return $next->format('Y-m-d H:i:s');
            }
            if ($minute !== '*' && $hour !== '*') {
                $next->setTime((int) $hour, (int) $minute);
                if ($next <= $now) $next->modify('+1 day');
                return $next->format('Y-m-d H:i:s');
            }
            if ($minute === '0' && $hour === '*') {
                $next->modify('+1 hour');
                $next->setTime((int) $next->format('H'), 0);
                return $next->format('Y-m-d H:i:s');
            }
            if ($minute === '30' && $hour === '*') {
                if ((int) $now->format('i') >= 30) $next->modify('+1 hour');
                $next->setTime((int) $next->format('H'), 30);
                return $next->format('Y-m-d H:i:s');
            }
            return null;
        } catch (\Throwable $e) {
            return null;
        }
    }

    // =========================================================================
    // Groups Module Configuration
    // =========================================================================

    /** GET /api/v2/admin/config/groups */
    public function getGroupConfig(): JsonResponse
    {
        $this->requireAdmin();

        $all = GroupConfigurationService::getAll();

        return $this->respondWithData([
            'config' => $all,
            'defaults' => GroupConfigurationService::DEFAULTS,
        ]);
    }

    /** PUT /api/v2/admin/config/groups */
    public function updateGroupConfig(): JsonResponse
    {
        $this->requireAdmin();
        $tenantId = $this->getTenantId();

        $key = $this->input('key');
        $value = $this->input('value');

        if (!$key || !is_string($key)) {
            return $this->respondWithError('VALIDATION_ERROR', 'Configuration key is required', 'key', 422);
        }

        if (!array_key_exists($key, GroupConfigurationService::DEFAULTS)) {
            return $this->respondWithError('VALIDATION_ERROR', "Unknown configuration key: {$key}", 'key', 422);
        }

        if ($value === null) {
            return $this->respondWithError('VALIDATION_ERROR', 'Value is required', 'value', 422);
        }

        // Type coercion based on default type
        $defaultValue = GroupConfigurationService::DEFAULTS[$key];
        if (is_bool($defaultValue)) {
            $value = filter_var($value, FILTER_VALIDATE_BOOLEAN);
        } elseif (is_int($defaultValue)) {
            $value = (int) $value;
        }

        GroupConfigurationService::set($key, $value);

        // Invalidate bootstrap cache so frontend picks up changes
        $this->redisCache->delete('tenant_bootstrap', $tenantId);

        return $this->respondWithData([
            'key' => $key,
            'value' => $value,
        ]);
    }

    /** PUT /api/v2/admin/config/groups/bulk */
    public function updateGroupConfigBulk(): JsonResponse
    {
        $this->requireAdmin();
        $tenantId = $this->getTenantId();

        $settings = $this->input('settings');
        if (!is_array($settings) || empty($settings)) {
            return $this->respondWithError('VALIDATION_ERROR', 'Settings array is required', 'settings', 422);
        }

        $updated = [];
        foreach ($settings as $key => $value) {
            if (!is_string($key) || !array_key_exists($key, GroupConfigurationService::DEFAULTS)) {
                continue;
            }

            $defaultValue = GroupConfigurationService::DEFAULTS[$key];
            if (is_bool($defaultValue)) {
                $value = filter_var($value, FILTER_VALIDATE_BOOLEAN);
            } elseif (is_int($defaultValue)) {
                $value = (int) $value;
            }

            GroupConfigurationService::set($key, $value);
            $updated[$key] = $value;
        }

        $this->redisCache->delete('tenant_bootstrap', $tenantId);

        return $this->respondWithData(['updated' => $updated]);
    }

    // =========================================================================
    // Listings Module Configuration
    // =========================================================================

    /** GET /api/v2/admin/config/listings */
    public function getListingConfig(): JsonResponse
    {
        $this->requireAdmin();

        $all = ListingConfigurationService::getAll();

        return $this->respondWithData([
            'config' => $all,
            'defaults' => ListingConfigurationService::DEFAULTS,
        ]);
    }

    /** PUT /api/v2/admin/config/listings */
    public function updateListingConfig(): JsonResponse
    {
        $this->requireAdmin();
        $tenantId = $this->getTenantId();

        $key = $this->input('key');
        $value = $this->input('value');

        // Type-check key before truth-testing so arrays/objects don't slip through
        if (!is_string($key) || $key === '') {
            return $this->respondWithError('VALIDATION_ERROR', 'Configuration key is required', 'key', 422);
        }

        if (!array_key_exists($key, ListingConfigurationService::DEFAULTS)) {
            return $this->respondWithError('VALIDATION_ERROR', "Unknown configuration key: {$key}", 'key', 422);
        }

        if ($value === null) {
            return $this->respondWithError('VALIDATION_ERROR', 'Value is required', 'value', 422);
        }

        $defaultValue = ListingConfigurationService::DEFAULTS[$key];
        if (is_bool($defaultValue)) {
            $value = filter_var($value, FILTER_VALIDATE_BOOLEAN);
        } elseif (is_int($defaultValue)) {
            $value = (int) $value;
        } else {
            $value = (string) $value;
        }

        ListingConfigurationService::set($key, $value);
        $this->redisCache->delete('tenant_bootstrap', $tenantId);

        return $this->respondWithData(['key' => $key, 'value' => $value]);
    }

    /** PUT /api/v2/admin/config/listings/bulk */
    public function updateListingConfigBulk(): JsonResponse
    {
        $this->requireAdmin();
        $tenantId = $this->getTenantId();

        $settings = $this->input('settings');
        if (!is_array($settings) || empty($settings)) {
            return $this->respondWithError('VALIDATION_ERROR', 'Settings array is required', 'settings', 422);
        }

        $updated = [];
        foreach ($settings as $key => $value) {
            if (!is_string($key) || !array_key_exists($key, ListingConfigurationService::DEFAULTS)) {
                continue;
            }

            $defaultValue = ListingConfigurationService::DEFAULTS[$key];
            if (is_bool($defaultValue)) {
                $value = filter_var($value, FILTER_VALIDATE_BOOLEAN);
            } elseif (is_int($defaultValue)) {
                $value = (int) $value;
            }

            ListingConfigurationService::set($key, $value);
            $updated[$key] = $value;
        }

        $this->redisCache->delete('tenant_bootstrap', $tenantId);

        return $this->respondWithData(['updated' => $updated]);
    }

    // =========================================================================
    // Volunteering Module Configuration
    // =========================================================================

    /** GET /api/v2/admin/config/volunteering */
    public function getVolunteeringConfig(): JsonResponse
    {
        $this->requireAdmin();

        return $this->respondWithData([
            'config' => VolunteeringConfigurationService::getAll(),
            'defaults' => VolunteeringConfigurationService::DEFAULTS,
        ]);
    }

    /** PUT /api/v2/admin/config/volunteering/bulk */
    public function updateVolunteeringConfigBulk(): JsonResponse
    {
        $this->requireAdmin();
        $tenantId = $this->getTenantId();

        $settings = $this->input('settings');
        if (!is_array($settings) || empty($settings)) {
            return $this->respondWithError('VALIDATION_ERROR', 'Settings array is required', 'settings', 422);
        }

        $updated = [];
        foreach ($settings as $key => $value) {
            if (!is_string($key) || !array_key_exists($key, VolunteeringConfigurationService::DEFAULTS)) {
                continue;
            }

            $defaultValue = VolunteeringConfigurationService::DEFAULTS[$key];
            if (is_bool($defaultValue)) {
                $value = filter_var($value, FILTER_VALIDATE_BOOLEAN);
            } elseif (is_int($defaultValue)) {
                $value = (int) $value;
            }

            VolunteeringConfigurationService::set($key, $value);
            $updated[$key] = $value;
        }

        $this->redisCache->delete('tenant_bootstrap', $tenantId);

        return $this->respondWithData(['updated' => $updated]);
    }

    // =========================================================================
    // Jobs Module Configuration
    // =========================================================================

    /** GET /api/v2/admin/config/jobs */
    public function getJobConfig(): JsonResponse
    {
        $this->requireAdmin();

        return $this->respondWithData([
            'config' => JobConfigurationService::getAll(),
            'defaults' => JobConfigurationService::DEFAULTS,
        ]);
    }

    /** PUT /api/v2/admin/config/jobs/bulk */
    public function updateJobConfigBulk(): JsonResponse
    {
        $this->requireAdmin();
        $tenantId = $this->getTenantId();

        $settings = $this->input('settings');
        if (!is_array($settings) || empty($settings)) {
            return $this->respondWithError('VALIDATION_ERROR', 'Settings array is required', 'settings', 422);
        }

        $updated = [];
        foreach ($settings as $key => $value) {
            if (!is_string($key) || !array_key_exists($key, JobConfigurationService::DEFAULTS)) {
                continue;
            }

            $defaultValue = JobConfigurationService::DEFAULTS[$key];
            if (is_bool($defaultValue)) {
                $value = filter_var($value, FILTER_VALIDATE_BOOLEAN);
            } elseif (is_int($defaultValue)) {
                $value = (int) $value;
            }

            JobConfigurationService::set($key, $value);
            $updated[$key] = $value;
        }

        $this->redisCache->delete('tenant_bootstrap', $tenantId);

        return $this->respondWithData(['updated' => $updated]);
    }

    // =========================================================================
    // Landing Page Configuration
    // =========================================================================

    /**
     * GET /api/v2/admin/config/landing-page
     *
     * Returns the landing page configuration for the current tenant.
     */
    public function getLandingPageConfig(): JsonResponse
    {
        $this->requireAdmin();
        $tenantId = $this->getTenantId();

        $row = DB::table('tenant_settings')
            ->where('tenant_id', $tenantId)
            ->where('setting_key', 'landing_page.config')
            ->value('setting_value');

        $config = null;
        if (!empty($row)) {
            $decoded = json_decode($row, true);
            if (is_array($decoded)) {
                $config = $decoded;
            }
        }

        return $this->respondWithData([
            'config' => $config,
        ]);
    }

    /**
     * PUT /api/v2/admin/config/landing-page
     *
     * Saves the landing page configuration for the current tenant.
     * Validates the structure before saving.
     */
    public function updateLandingPageConfig(): JsonResponse
    {
        $this->requireAdmin();
        $tenantId = $this->getTenantId();

        $config = $this->input('config');

        if ($config !== null && !is_array($config)) {
            return $this->respondWithError('VALIDATION_ERROR', 'Config must be an object or null', null, 422);
        }

        // Validate structure if config provided
        if ($config !== null) {
            if (!isset($config['sections']) || !is_array($config['sections'])) {
                return $this->respondWithError('VALIDATION_ERROR', 'Config must have a sections array', null, 422);
            }

            $validTypes = ['hero', 'feature_pills', 'stats', 'how_it_works', 'core_values', 'cta'];
            foreach ($config['sections'] as $i => $section) {
                if (!isset($section['id'], $section['type'], $section['enabled'], $section['order'])) {
                    return $this->respondWithError('VALIDATION_ERROR', "Section $i missing required fields (id, type, enabled, order)", null, 422);
                }
                if (!in_array($section['type'], $validTypes, true)) {
                    return $this->respondWithError('VALIDATION_ERROR', "Section $i has invalid type: {$section['type']}", null, 422);
                }
            }
        }

        if ($config === null) {
            // Delete the setting to revert to defaults
            DB::table('tenant_settings')
                ->where('tenant_id', $tenantId)
                ->where('setting_key', 'landing_page.config')
                ->delete();
        } else {
            // Upsert the setting
            $existing = DB::table('tenant_settings')
                ->where('tenant_id', $tenantId)
                ->where('setting_key', 'landing_page.config')
                ->exists();

            $jsonValue = json_encode($config, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);

            if ($existing) {
                DB::table('tenant_settings')
                    ->where('tenant_id', $tenantId)
                    ->where('setting_key', 'landing_page.config')
                    ->update(['setting_value' => $jsonValue]);
            } else {
                DB::table('tenant_settings')->insert([
                    'tenant_id' => $tenantId,
                    'setting_key' => 'landing_page.config',
                    'setting_value' => $jsonValue,
                    'setting_type' => 'json',
                ]);
            }
        }

        // Clear tenant bootstrap cache so changes take effect immediately
        $this->redisCache->delete('tenant_bootstrap', $tenantId);
        $this->tenantSettingsService->clearCacheForTenant($tenantId);

        return $this->respondWithData(['success' => true]);
    }

    // =========================================================================
    // Translation Configuration (INT9)
    // =========================================================================

    /** GET /api/v2/admin/config/translation */
    public function getTranslationConfig(): JsonResponse
    {
        $this->requireAdmin();

        $all = \App\Services\TranslationConfigurationService::getAll();

        return $this->respondWithData([
            'config' => $all,
            'defaults' => \App\Services\TranslationConfigurationService::DEFAULTS,
        ]);
    }

    /** PUT /api/v2/admin/config/translation */
    public function updateTranslationConfig(): JsonResponse
    {
        $this->requireAdmin();
        $tenantId = $this->getTenantId();

        $key = $this->input('key');
        $value = $this->input('value');

        if (!$key || !is_string($key)) {
            return $this->respondWithError('VALIDATION_ERROR', 'Configuration key is required', 'key', 422);
        }

        if (!array_key_exists($key, \App\Services\TranslationConfigurationService::DEFAULTS)) {
            return $this->respondWithError('VALIDATION_ERROR', "Unknown configuration key: {$key}", 'key', 422);
        }

        if ($value === null) {
            return $this->respondWithError('VALIDATION_ERROR', 'Value is required', 'value', 422);
        }

        $defaultValue = \App\Services\TranslationConfigurationService::DEFAULTS[$key];
        if (is_bool($defaultValue)) {
            $value = filter_var($value, FILTER_VALIDATE_BOOLEAN);
        } elseif (is_int($defaultValue)) {
            $value = max(1, min(1000, (int) $value));
        }

        \App\Services\TranslationConfigurationService::set($key, $value);
        $this->redisCache->delete('tenant_bootstrap', $tenantId);

        return $this->respondWithData(['key' => $key, 'value' => $value]);
    }

    /** PUT /api/v2/admin/config/translation/bulk */
    public function updateTranslationConfigBulk(): JsonResponse
    {
        $this->requireAdmin();
        $tenantId = $this->getTenantId();

        $settings = $this->input('settings');
        if (!is_array($settings) || empty($settings)) {
            return $this->respondWithError('VALIDATION_ERROR', 'Settings array is required', 'settings', 422);
        }

        $updated = [];
        foreach ($settings as $key => $value) {
            if (!is_string($key) || !array_key_exists($key, \App\Services\TranslationConfigurationService::DEFAULTS)) {
                continue;
            }

            $defaultValue = \App\Services\TranslationConfigurationService::DEFAULTS[$key];
            if (is_bool($defaultValue)) {
                $value = filter_var($value, FILTER_VALIDATE_BOOLEAN);
            } elseif (is_int($defaultValue)) {
                $value = max(1, min(1000, (int) $value));
            }

            \App\Services\TranslationConfigurationService::set($key, $value);
            $updated[$key] = $value;
        }

        $this->redisCache->delete('tenant_bootstrap', $tenantId);

        return $this->respondWithData(['updated' => $updated]);
    }

    // =========================================================================
    // Translation Glossary CRUD (INT10)
    // =========================================================================

    /** GET /api/v2/admin/translation/glossary */
    public function getGlossary(): JsonResponse
    {
        $this->requireAdmin();
        $tenantId = $this->getTenantId();

        if (!DB::getSchemaBuilder()->hasTable('translation_glossaries')) {
            return $this->respondWithData(['items' => [], 'total' => 0]);
        }

        $language = $this->query('language');
        $query = DB::table('translation_glossaries')->where('tenant_id', $tenantId);

        if ($language) {
            $query->where('target_language', $language);
        }

        $items = $query->orderBy('source_term')->get();

        return $this->respondWithData(['items' => $items, 'total' => $items->count()]);
    }

    /** POST /api/v2/admin/translation/glossary */
    public function createGlossaryEntry(): JsonResponse
    {
        $this->requireAdmin();
        $tenantId = $this->getTenantId();

        $sourceTerm = trim($this->input('source_term', ''));
        $targetTerm = trim($this->input('target_term', ''));
        $targetLanguage = trim($this->input('target_language', ''));

        if (empty($sourceTerm)) {
            return $this->respondWithError('VALIDATION_ERROR', 'Source term is required', 'source_term', 422);
        }
        if (empty($targetTerm)) {
            return $this->respondWithError('VALIDATION_ERROR', 'Target term is required', 'target_term', 422);
        }
        if (empty($targetLanguage)) {
            return $this->respondWithError('VALIDATION_ERROR', 'Target language is required', 'target_language', 422);
        }
        if (mb_strlen($sourceTerm) > 255 || mb_strlen($targetTerm) > 255) {
            return $this->respondWithError('VALIDATION_ERROR', 'Terms must be 255 characters or fewer', null, 422);
        }
        if (!preg_match('/^[a-z]{2,3}(-[A-Za-z]{2,4})?$/', $targetLanguage)) {
            return $this->respondWithError('VALIDATION_ERROR', 'target_language must be a valid ISO 639-1 code (e.g. en, fr, de)', 'target_language', 422);
        }

        try {
            $id = DB::table('translation_glossaries')->insertGetId([
                'tenant_id' => $tenantId,
                'source_term' => $sourceTerm,
                'target_term' => $targetTerm,
                'target_language' => $targetLanguage,
                'is_active' => true,
                'created_at' => now(),
                'updated_at' => now(),
            ]);
        } catch (\Illuminate\Database\QueryException $e) {
            if (str_contains($e->getMessage(), 'Duplicate entry') || str_contains($e->getMessage(), '1062')) {
                return $this->respondWithError('DUPLICATE', "A glossary entry for \"{$sourceTerm}\" in {$targetLanguage} already exists", 'source_term', 409);
            }
            throw $e;
        }

        return $this->respondWithData(['id' => $id], null, 201);
    }

    /** DELETE /api/v2/admin/translation/glossary/{id} */
    public function deleteGlossaryEntry(int $id): JsonResponse
    {
        $this->requireAdmin();
        $tenantId = $this->getTenantId();

        $deleted = DB::table('translation_glossaries')
            ->where('id', $id)
            ->where('tenant_id', $tenantId)
            ->delete();

        if (!$deleted) {
            return $this->respondWithError('NOT_FOUND', 'Glossary entry not found', null, 404);
        }

        return $this->respondWithData(['deleted' => true]);
    }

    // =========================================================================
    // Identity Verification Configuration
    // =========================================================================

    private const IDENTITY_DEFAULTS = [
        'identity_verification_fee_cents' => 500,
    ];

    /** GET /api/v2/admin/config/identity */
    public function getIdentityConfig(): JsonResponse
    {
        $this->requireAdmin();
        $tenantId = TenantContext::getId();

        $config = [];
        foreach (self::IDENTITY_DEFAULTS as $key => $default) {
            $config[$key] = (int) $this->tenantSettingsService->get($tenantId, $key, (string) $default);
        }

        return $this->respondWithData([
            'config' => $config,
            'defaults' => self::IDENTITY_DEFAULTS,
        ]);
    }

    /** PUT /api/v2/admin/config/identity/bulk */
    public function updateIdentityConfigBulk(): JsonResponse
    {
        $this->requireAdmin();
        $tenantId = $this->getTenantId();

        $settings = $this->input('settings');
        if (!is_array($settings) || empty($settings)) {
            return $this->respondWithError('VALIDATION_ERROR', 'Settings array is required', 'settings', 422);
        }

        $updated = [];
        foreach ($settings as $key => $value) {
            if (!array_key_exists($key, self::IDENTITY_DEFAULTS)) {
                continue;
            }
            $value = max(0, (int) $value);
            $this->tenantSettingsService->set($tenantId, $key, (string) $value, 'integer');
            $updated[$key] = $value;
        }

        $this->redisCache->delete('tenant_bootstrap', $tenantId);
        $this->tenantSettingsService->clearCacheForTenant($tenantId);

        return $this->respondWithData(['updated' => $updated]);
    }
}
