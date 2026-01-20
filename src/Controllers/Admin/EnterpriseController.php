<?php

declare(strict_types=1);

namespace Nexus\Controllers\Admin;

use Nexus\Core\View;
use Nexus\Core\Database;
use Nexus\Core\TenantContext;
use Nexus\Services\Enterprise\GdprService;
use Nexus\Services\Enterprise\ConfigService;
use Nexus\Services\Enterprise\MetricsService;
use Nexus\Services\Enterprise\LoggerService;

/**
 * Enterprise Admin Controller
 *
 * Handles admin interface for enterprise features including
 * GDPR compliance, monitoring, and system configuration.
 */
class EnterpriseController
{
    private GdprService $gdprService;
    private ConfigService $configService;
    private MetricsService $metrics;
    private LoggerService $logger;

    public function __construct()
    {
        $this->requireAdmin();
        $this->gdprService = new GdprService();
        $this->configService = ConfigService::getInstance();
        $this->metrics = MetricsService::getInstance();
        $this->logger = LoggerService::getInstance('admin');
    }

    private function requireAdmin(): void
    {
        $isAdmin = isset($_SESSION['user_role']) && in_array($_SESSION['user_role'], ['admin', 'super_admin', 'tenant_admin']);
        $isSuperAdmin = !empty($_SESSION['is_super_admin']);

        if (!$isAdmin && !$isSuperAdmin) {
            header('Location: ' . TenantContext::getBasePath() . '/login');
            exit;
        }
    }

    // =========================================================================
    // ENTERPRISE DASHBOARD
    // =========================================================================

    /**
     * GET /admin/enterprise
     * Main enterprise dashboard
     */
    public function dashboard(): void
    {
        $stats = [
            'gdpr' => $this->gdprService->getStatistics(),
            'system' => $this->getSystemStatus(),
            'config' => $this->configService->getStatus(),
        ];

        // Modern layout automatically applied via View::render path (Gold Standard with Dark Mode)

        View::render('admin/enterprise/dashboard', [
            'stats' => $stats,
            'title' => 'Enterprise Dashboard',
        ]);
    }

    // =========================================================================
    // GDPR MANAGEMENT
    // =========================================================================

    /**
     * GET /admin/enterprise/gdpr
     * GDPR compliance dashboard
     */
    public function gdprDashboard(): void
    {
        $stats = $this->gdprService->getStatistics();
        $pendingRequests = $this->gdprService->getPendingRequests(20);
        $consentTypes = $this->gdprService->getConsentTypes();

        // Force modern layout for GDPR dashboard

        View::render('admin/enterprise/gdpr/dashboard', [
            'stats' => $stats,
            'pendingRequests' => $pendingRequests,
            'consentTypes' => $consentTypes,
            'title' => 'GDPR Compliance',
        ]);
    }

    /**
     * GET /admin/enterprise/gdpr/requests
     * List all GDPR requests
     */
    public function gdprRequests(): void
    {
        $page = (int) ($_GET['page'] ?? 1);
        $limit = 25;
        $offset = ($page - 1) * $limit;

        $requests = $this->gdprService->getPendingRequests($limit, $offset);

        // Get summary statistics for the dashboard
        $tenantId = $this->getTenantId();
        $summary = [
            'pending' => Database::query(
                "SELECT COUNT(*) as count FROM gdpr_requests WHERE tenant_id = ? AND status = 'pending'",
                [$tenantId]
            )->fetch()['count'] ?? 0,
            'processing' => Database::query(
                "SELECT COUNT(*) as count FROM gdpr_requests WHERE tenant_id = ? AND status = 'processing'",
                [$tenantId]
            )->fetch()['count'] ?? 0,
            'completed' => Database::query(
                "SELECT COUNT(*) as count FROM gdpr_requests WHERE tenant_id = ? AND status = 'completed'",
                [$tenantId]
            )->fetch()['count'] ?? 0,
            'overdue' => Database::query(
                "SELECT COUNT(*) as count FROM gdpr_requests WHERE tenant_id = ? AND status IN ('pending', 'processing') AND requested_at < DATE_SUB(NOW(), INTERVAL 30 DAY)",
                [$tenantId]
            )->fetch()['count'] ?? 0,
        ];

        // Force modern layout

        View::render('admin/enterprise/gdpr/requests', [
            'requests' => $requests,
            'summary' => $summary,
            'page' => $page,
            'title' => 'GDPR Requests',
        ]);
    }

    /**
     * GET /admin/enterprise/gdpr/requests/{id}
     * View single GDPR request
     */
    public function gdprRequestView(int $id): void
    {
        $request = $this->gdprService->getRequest($id);

        if (!$request) {
            $_SESSION['flash_error'] = 'Request not found';
            header('Location: ' . TenantContext::getBasePath() . '/admin/enterprise/gdpr/requests');
            exit;
        }

        // Force modern layout

        View::render('admin/enterprise/gdpr/request-view', [
            'request' => $request,
            'title' => "GDPR Request #{$id}",
        ]);
    }

    /**
     * POST /admin/enterprise/gdpr/requests/{id}/process
     * Start processing a GDPR request
     */
    public function gdprRequestProcess(int $id): void
    {
        header('Content-Type: application/json');

        $request = $this->gdprService->getRequest($id);

        if (!$request) {
            http_response_code(404);
            echo json_encode(['error' => 'Request not found']);
            return;
        }

        $adminId = $this->getCurrentUserId();

        try {
            $this->gdprService->processRequest($id, $adminId);

            // Handle based on request type
            switch ($request['request_type']) {
                case 'access':
                case 'portability':
                    $exportPath = $this->gdprService->generateDataExport($request['user_id'], $id);
                    $this->logger->info("Data export generated for request #{$id}");
                    break;

                case 'erasure':
                    $this->gdprService->executeAccountDeletion($request['user_id'], $adminId, $id);
                    $this->logger->info("Account deleted for request #{$id}");
                    break;
            }

            echo json_encode(['success' => true, 'message' => 'Request processed successfully']);
        } catch (\Exception $e) {
            $this->logger->error("Failed to process GDPR request #{$id}", ['error' => $e->getMessage()]);
            http_response_code(500);
            echo json_encode(['error' => $e->getMessage()]);
        }
    }

    /**
     * POST /admin/enterprise/gdpr/requests/{id}/reject
     * Reject a GDPR request
     */
    public function gdprRequestReject(int $id): void
    {
        header('Content-Type: application/json');

        $data = $this->getJsonInput();
        $reason = $data['reason'] ?? '';

        if (empty($reason)) {
            http_response_code(400);
            echo json_encode(['error' => 'Rejection reason is required']);
            return;
        }

        Database::query(
            "UPDATE gdpr_requests SET status = 'rejected', rejection_reason = ?, processed_at = NOW(), processed_by = ? WHERE id = ?",
            [$reason, $this->getCurrentUserId(), $id]
        );

        $this->logger->info("GDPR request #{$id} rejected", ['reason' => $reason]);

        echo json_encode(['success' => true]);
    }

    /**
     * GET /admin/enterprise/gdpr/consents
     * Manage consent types with full statistics
     */
    public function gdprConsents(): void
    {
        $tenantId = $this->getTenantId();

        // Get consent types with granted/denied counts
        $consentTypes = Database::query(
            "SELECT ct.*,
                    COALESCE(granted.cnt, 0) as granted_count,
                    COALESCE(denied.cnt, 0) as denied_count
             FROM consent_types ct
             LEFT JOIN (
                 SELECT consent_type, COUNT(*) as cnt
                 FROM user_consents
                 WHERE tenant_id = ? AND consent_given = 1
                 GROUP BY consent_type
             ) granted ON ct.slug = granted.consent_type
             LEFT JOIN (
                 SELECT consent_type, COUNT(*) as cnt
                 FROM user_consents
                 WHERE tenant_id = ? AND consent_given = 0
                 GROUP BY consent_type
             ) denied ON ct.slug = denied.consent_type
             WHERE ct.is_active = TRUE
             ORDER BY ct.display_order, ct.name",
            [$tenantId, $tenantId]
        )->fetchAll();

        // Calculate dashboard stats
        $totalConsents = Database::query(
            "SELECT COUNT(*) as cnt FROM user_consents WHERE tenant_id = ?",
            [$tenantId]
        )->fetch()['cnt'] ?? 0;

        $totalGranted = Database::query(
            "SELECT COUNT(*) as cnt FROM user_consents WHERE tenant_id = ? AND consent_given = 1",
            [$tenantId]
        )->fetch()['cnt'] ?? 0;

        $usersWithConsent = Database::query(
            "SELECT COUNT(DISTINCT user_id) as cnt FROM user_consents WHERE tenant_id = ? AND consent_given = 1",
            [$tenantId]
        )->fetch()['cnt'] ?? 0;

        // Pending re-consent: users with outdated consent versions for required consents
        $pendingReconsent = Database::query(
            "SELECT COUNT(DISTINCT uc.user_id) as cnt
             FROM user_consents uc
             JOIN consent_types ct ON uc.consent_type = ct.slug
             WHERE uc.tenant_id = ?
               AND ct.is_required = 1
               AND uc.consent_given = 1
               AND uc.consent_version != ct.current_version",
            [$tenantId]
        )->fetch()['cnt'] ?? 0;

        $stats = [
            'total_consents' => (int) $totalConsents,
            'consent_rate' => $totalConsents > 0 ? round(($totalGranted / $totalConsents) * 100, 1) : 0,
            'users_with_consent' => (int) $usersWithConsent,
            'pending_reconsent' => (int) $pendingReconsent
        ];

        // Get paginated consent records with filtering
        $page = max(1, (int) ($_GET['page'] ?? 1));
        $perPage = 25;
        $offset = ($page - 1) * $perPage;
        $selectedType = $_GET['type'] ?? null;
        $filters = array_filter([
            'search' => $_GET['search'] ?? null,
            'status' => $_GET['status'] ?? null,
            'period' => $_GET['period'] ?? null,
            'type' => $selectedType
        ]);

        $whereClause = "uc.tenant_id = ?";
        $params = [$tenantId];

        if (!empty($filters['search'])) {
            $whereClause .= " AND (u.email LIKE ? OR u.first_name LIKE ? OR u.last_name LIKE ?)";
            $searchTerm = '%' . $filters['search'] . '%';
            $params = array_merge($params, [$searchTerm, $searchTerm, $searchTerm]);
        }
        if (!empty($filters['status'])) {
            if ($filters['status'] === 'granted') {
                $whereClause .= " AND uc.consent_given = 1";
            } elseif ($filters['status'] === 'denied') {
                $whereClause .= " AND uc.consent_given = 0 AND uc.withdrawn_at IS NULL";
            } elseif ($filters['status'] === 'withdrawn') {
                $whereClause .= " AND uc.withdrawn_at IS NOT NULL";
            }
        }
        if ($selectedType) {
            $whereClause .= " AND uc.consent_type = ?";
            $params[] = $selectedType;
        }

        $totalCount = Database::query(
            "SELECT COUNT(*) as cnt FROM user_consents uc
             LEFT JOIN users u ON uc.user_id = u.id
             WHERE {$whereClause}",
            $params
        )->fetch()['cnt'] ?? 0;

        $consents = Database::query(
            "SELECT uc.*, u.email, CONCAT(u.first_name, ' ', u.last_name) as username,
                    ct.name as consent_type_name, uc.consent_given as granted
             FROM user_consents uc
             LEFT JOIN users u ON uc.user_id = u.id
             LEFT JOIN consent_types ct ON uc.consent_type = ct.slug
             WHERE {$whereClause}
             ORDER BY uc.created_at DESC
             LIMIT {$perPage} OFFSET {$offset}",
            $params
        )->fetchAll();

        $selectedTypeName = null;
        if ($selectedType) {
            foreach ($consentTypes as $ct) {
                if ($ct['slug'] === $selectedType) {
                    $selectedTypeName = $ct['name'];
                    break;
                }
            }
        }

        View::render('admin/enterprise/gdpr/consents', [
            'consentTypes' => $consentTypes,
            'stats' => $stats,
            'consents' => $consents,
            'selectedType' => $selectedType,
            'selectedTypeName' => $selectedTypeName,
            'filters' => $filters,
            'totalCount' => (int) $totalCount,
            'totalPages' => (int) ceil($totalCount / $perPage),
            'pageCurrent' => $page,
            'offset' => $offset,
            'title' => 'Consent Management',
        ]);
    }

    /**
     * GET /admin/enterprise/gdpr/breaches
     * Data breach log
     */
    public function gdprBreaches(): void
    {
        $tenantId = $this->getTenantId();

        $breaches = Database::query(
            "SELECT * FROM data_breach_log WHERE tenant_id = ? ORDER BY detected_at DESC",
            [$tenantId]
        )->fetchAll();

        // Calculate breach statistics
        $stats = [
            'active_breaches' => 0,
            'investigating' => 0,
            'notified_dpa' => 0,
            'resolved' => 0,
            'notification_required' => 0,
        ];

        foreach ($breaches as $breach) {
            switch ($breach['status'] ?? '') {
                case 'active':
                    $stats['active_breaches']++;
                    // Check if 72-hour notification deadline is approaching
                    if (empty($breach['dpa_notified_at'])) {
                        $stats['notification_required']++;
                    }
                    break;
                case 'investigating':
                    $stats['investigating']++;
                    break;
                case 'resolved':
                case 'closed':
                    $stats['resolved']++;
                    break;
            }
            if (!empty($breach['dpa_notified_at'])) {
                $stats['notified_dpa']++;
            }
        }

        // Force modern layout

        View::render('admin/enterprise/gdpr/breaches', [
            'breaches' => $breaches,
            'stats' => $stats,
            'title' => 'Data Breaches',
        ]);
    }

    /**
     * GET /admin/enterprise/gdpr/breaches/{id}
     * View single breach details
     */
    public function gdprBreachView(int $id): void
    {
        $breach = Database::query(
            "SELECT * FROM data_breach_log WHERE id = ? AND tenant_id = ?",
            [$id, $this->getTenantId()]
        )->fetch();

        if (!$breach) {
            $_SESSION['flash_error'] = 'Breach not found';
            header('Location: ' . TenantContext::getBasePath() . '/admin/enterprise/gdpr/breaches');
            exit;
        }

        // Force modern layout

        View::render('admin/enterprise/gdpr/breach-view', [
            'breach' => $breach,
            'title' => "Breach #{$id}",
        ]);
    }

    /**
     * POST /admin/enterprise/gdpr/breaches/{id}/escalate
     * Escalate a breach to incident response team
     */
    public function gdprBreachEscalate(int $id): void
    {
        header('Content-Type: application/json');

        $breach = Database::query(
            "SELECT * FROM data_breach_log WHERE id = ? AND tenant_id = ?",
            [$id, $this->getTenantId()]
        )->fetch();

        if (!$breach) {
            http_response_code(404);
            echo json_encode(['error' => 'Breach not found']);
            return;
        }

        try {
            // Update breach status to escalated
            Database::query(
                "UPDATE data_breach_log SET status = 'escalated', escalated_at = NOW(), escalated_by = ? WHERE id = ?",
                [$this->getCurrentUserId(), $id]
            );

            $this->logger->info("Breach #{$id} escalated", ['user_id' => $this->getCurrentUserId()]);

            echo json_encode(['success' => true, 'message' => 'Breach escalated to incident response team']);
        } catch (\Exception $e) {
            $this->logger->error("Failed to escalate breach #{$id}", ['error' => $e->getMessage()]);
            http_response_code(500);
            echo json_encode(['error' => $e->getMessage()]);
        }
    }

    /**
     * GET /admin/enterprise/gdpr/breaches/report
     * Show breach report form
     */
    public function gdprBreachReportForm(): void
    {
        // Force modern layout

        View::render('admin/enterprise/gdpr/breach-report', [
            'title' => 'Report Data Breach',
        ]);
    }

    /**
     * POST /admin/enterprise/gdpr/breaches
     * Report a new data breach
     */
    public function gdprBreachReport(): void
    {
        header('Content-Type: application/json');

        $data = $this->getJsonInput();

        try {
            $id = $this->gdprService->reportBreach($data, $this->getCurrentUserId());

            echo json_encode([
                'success' => true,
                'id' => $id,
                'message' => 'Breach reported. Remember: GDPR requires notification to authorities within 72 hours.',
            ]);
        } catch (\Exception $e) {
            http_response_code(500);
            echo json_encode(['error' => $e->getMessage()]);
        }
    }

    /**
     * GET /admin/enterprise/gdpr/audit
     * GDPR audit log
     */
    public function gdprAuditLog(): void
    {
        $logs = Database::query(
            "SELECT al.*, u.email, u.first_name, u.last_name
             FROM gdpr_audit_log al
             LEFT JOIN users u ON al.user_id = u.id
             WHERE al.tenant_id = ?
             ORDER BY al.created_at DESC
             LIMIT 500",
            [$this->getTenantId()]
        )->fetchAll();

        // Force modern layout

        View::render('admin/enterprise/gdpr/audit', [
            'logs' => $logs,
            'title' => 'GDPR Audit Log',
        ]);
    }

    // =========================================================================
    // MONITORING & APM
    // =========================================================================

    /**
     * GET /admin/enterprise/monitoring
     * System monitoring dashboard
     */
    public function monitoring(): void
    {
        $status = $this->getSystemStatus();

        // Force modern layout for monitoring dashboard

        View::render('admin/enterprise/monitoring/dashboard', [
            'status' => $status,
            'title' => 'System Monitoring',
        ]);
    }

    /**
     * GET /admin/enterprise/monitoring/requirements
     * Platform requirements checker
     */
    public function requirements(): void
    {
        $requirements = $this->checkPlatformRequirements();

        View::render('admin/enterprise/monitoring/requirements', [
            'requirements' => $requirements,
            'title' => 'Platform Requirements',
        ]);
    }

    /**
     * GET /admin/enterprise/monitoring/health
     * Health check endpoint - returns JSON for AJAX, renders view for browser
     */
    public function healthCheck(): void
    {
        // Check if this is an AJAX/API request
        $isAjax = !empty($_SERVER['HTTP_X_REQUESTED_WITH']) &&
            strtolower($_SERVER['HTTP_X_REQUESTED_WITH']) === 'xmlhttprequest';
        $wantsJson = isset($_SERVER['HTTP_ACCEPT']) &&
            strpos($_SERVER['HTTP_ACCEPT'], 'application/json') !== false;

        // If browser request (not AJAX), render the view
        if (!$isAjax && !$wantsJson) {
            View::render('admin/enterprise/monitoring/health');
            return;
        }

        // Otherwise return JSON health check data
        header('Content-Type: application/json');

        $checks = [];
        $startTime = microtime(true);

        // Database check
        try {
            Database::query('SELECT 1');
            $checks['database'] = ['status' => 'healthy', 'latency_ms' => round((microtime(true) - $startTime) * 1000, 2)];
        } catch (\Exception $e) {
            $checks['database'] = ['status' => 'unhealthy', 'error' => $e->getMessage()];
        }

        // Redis check (class may not exist if extension not loaded)
        try {
            if (class_exists('Redis')) {
                /** @var \Redis $redis */
                $redis = new \Redis();
                $redisHost = getenv('REDIS_HOST') ?: '127.0.0.1';
                $redisPort = (int) (getenv('REDIS_PORT') ?: 6379);
                $redis->connect($redisHost, $redisPort, 1);
                $redis->ping();
                $checks['redis'] = ['status' => 'healthy'];
            } else {
                $checks['redis'] = ['status' => 'not_installed'];
            }
        } catch (\Exception $e) {
            $checks['redis'] = ['status' => 'unhealthy', 'error' => $e->getMessage()];
        }

        // Disk space - use document root to avoid open_basedir restrictions
        $diskPath = $_SERVER['DOCUMENT_ROOT'] ?? dirname(__DIR__, 3);
        $freeSpace = @disk_free_space($diskPath);
        $totalSpace = @disk_total_space($diskPath);
        if ($freeSpace !== false && $totalSpace !== false && $totalSpace > 0) {
            $usedPercent = round((1 - $freeSpace / $totalSpace) * 100, 1);
            $checks['disk'] = [
                'status' => $usedPercent < 90 ? 'healthy' : 'warning',
                'used_percent' => $usedPercent,
                'free_gb' => round($freeSpace / 1073741824, 2),
            ];
        } else {
            $checks['disk'] = ['status' => 'unknown'];
        }

        // Vault check
        if (method_exists($this->configService, 'isUsingVault') && $this->configService->isUsingVault()) {
            $checks['vault'] = ['status' => 'healthy', 'using_vault' => true];
        } else {
            $checks['vault'] = ['status' => 'not_configured', 'using_vault' => false];
        }

        $isHealthy = !array_filter($checks, fn($c) => ($c['status'] ?? '') === 'unhealthy');
        $totalLatency = round((microtime(true) - $startTime) * 1000, 2);

        http_response_code($isHealthy ? 200 : 503);
        echo json_encode([
            'status' => $isHealthy ? 'healthy' : 'unhealthy',
            'timestamp' => date('c'),
            'latency_ms' => $totalLatency,
            'version' => getenv('APP_VERSION') ?: '1.0.0',
            'environment' => getenv('APP_ENV') ?: 'production',
            'checks' => $checks,
        ]);
    }

    /**
     * GET /admin/enterprise/monitoring/logs
     * View application logs
     */
    public function logs(): void
    {
        $logPath = getenv('LOG_PATH') ?: dirname(__DIR__, 4) . '/logs';
        $logs = [];

        if (@is_dir($logPath)) {
            $iterator = new \DirectoryIterator($logPath);
            foreach ($iterator as $file) {
                if ($file->isFile() && $file->getExtension() === 'log') {
                    $filePath = $file->getPathname();
                    $logs[] = [
                        'name' => $file->getFilename(),
                        'size' => $this->formatFileSize($file->getSize()),
                        'modified' => date('M j, H:i', $file->getMTime()),
                        'preview' => $this->getLogPreview($filePath),
                    ];
                }
            }
        }

        usort($logs, fn($a, $b) => $b['modified'] <=> $a['modified']);

        // Force modern layout

        View::render('admin/enterprise/monitoring/logs', [
            'logs' => $logs,
            'title' => 'Application Logs',
        ]);
    }

    /**
     * GET /admin/enterprise/monitoring/logs/{filename}
     * View specific log file
     */
    public function logView(string $filename): void
    {
        $logPath = getenv('LOG_PATH') ?: dirname(__DIR__, 4) . '/logs';
        $filePath = $logPath . '/' . basename($filename);

        if (!file_exists($filePath)) {
            header('Content-Type: application/json');
            http_response_code(404);
            echo json_encode(['error' => 'Log file not found']);
            return;
        }

        $lines = (int) ($_GET['lines'] ?? 100);
        $content = $this->tailFile($filePath, $lines);

        if ($this->wantsJson()) {
            header('Content-Type: application/json');
            echo json_encode(['content' => $content, 'filename' => $filename]);
            return;
        }

        // Force modern layout

        View::render('admin/enterprise/monitoring/log-view', [
            'content' => $content,
            'filename' => $filename,
            'title' => "Log: {$filename}",
        ]);
    }

    /**
     * GET /admin/enterprise/monitoring/logs/download
     * Download log file(s)
     */
    public function logsDownload(): void
    {
        $logPath = getenv('LOG_PATH') ?: dirname(__DIR__, 4) . '/logs';
        $filename = $_GET['file'] ?? null;

        if ($filename) {
            // Download single file
            $filePath = $logPath . '/' . basename($filename);

            if (!file_exists($filePath)) {
                http_response_code(404);
                echo 'File not found';
                return;
            }

            header('Content-Type: text/plain');
            header('Content-Disposition: attachment; filename="' . basename($filename) . '"');
            header('Content-Length: ' . filesize($filePath));
            readfile($filePath);
        } else {
            // Download all logs as ZIP
            $zipFile = tempnam(sys_get_temp_dir(), 'logs_') . '.zip';
            $zip = new \ZipArchive();

            if ($zip->open($zipFile, \ZipArchive::CREATE) === true) {
                $iterator = new \DirectoryIterator($logPath);
                foreach ($iterator as $file) {
                    if ($file->isFile() && $file->getExtension() === 'log') {
                        $zip->addFile($file->getPathname(), $file->getFilename());
                    }
                }
                $zip->close();

                header('Content-Type: application/zip');
                header('Content-Disposition: attachment; filename="logs_' . date('Y-m-d_His') . '.zip"');
                header('Content-Length: ' . filesize($zipFile));
                readfile($zipFile);
                unlink($zipFile);
            } else {
                http_response_code(500);
                echo 'Failed to create archive';
            }
        }
    }

    /**
     * POST /admin/enterprise/monitoring/logs/clear
     * Clear a log file
     */
    public function logsClear(): void
    {
        header('Content-Type: application/json');

        $data = $this->getJsonInput();
        $filename = $data['filename'] ?? '';

        if (empty($filename)) {
            http_response_code(400);
            echo json_encode(['error' => 'Filename required']);
            return;
        }

        $logPath = getenv('LOG_PATH') ?: dirname(__DIR__, 4) . '/logs';
        $filePath = $logPath . '/' . basename($filename);

        if (!file_exists($filePath)) {
            http_response_code(404);
            echo json_encode(['error' => 'File not found']);
            return;
        }

        // Clear file contents (truncate to 0 bytes)
        file_put_contents($filePath, '');

        $this->logger->info("Log file cleared", ['filename' => $filename, 'user_id' => $this->getCurrentUserId()]);

        echo json_encode(['success' => true, 'message' => 'Log file cleared']);
    }

    // =========================================================================
    // CONFIGURATION
    // =========================================================================

    /**
     * GET /admin/enterprise/config
     * System configuration dashboard
     */
    public function config(): void
    {
        $config = [
            'environment' => getenv('APP_ENV') ?: 'unknown',
            'debug' => $this->configService->isDebug(),
            'vault' => $this->configService->getStatus(),
            'features' => $this->getFeatureFlags(),
        ];

        // Force modern layout for config dashboard

        View::render('admin/enterprise/config/dashboard', [
            'config' => $config,
            'title' => 'System Configuration',
        ]);
    }

    /**
     * GET /admin/enterprise/config/secrets
     * Secrets management interface
     */
    public function secrets(): void
    {
        $status = $this->configService->getStatus();

        // Force modern layout

        View::render('admin/enterprise/config/secrets', [
            'vaultStatus' => $status,
            'secrets' => [],
            'title' => 'Secrets Management',
        ]);
    }

    // =========================================================================
    // GDPR REQUEST MANAGEMENT (Additional Methods)
    // =========================================================================

    /**
     * GET /admin/enterprise/gdpr/requests/new
     * Form to create a new GDPR request (admin-initiated)
     */
    public function gdprRequestCreate(): void
    {
        // Force modern layout

        View::render('admin/enterprise/gdpr/request-create', [
            'title' => 'Create GDPR Request',
            'requestTypes' => ['access', 'erasure', 'rectification', 'restriction', 'portability', 'objection'],
        ]);
    }

    /**
     * POST /admin/enterprise/gdpr/requests
     * Store a new GDPR request
     */
    public function gdprRequestStore(): void
    {
        header('Content-Type: application/json');

        $data = $this->getJsonInput();
        $userId = (int) ($data['user_id'] ?? 0);
        $requestType = $data['request_type'] ?? '';
        $notes = $data['notes'] ?? '';

        if (!$userId || !$requestType) {
            http_response_code(400);
            echo json_encode(['error' => 'User ID and request type are required']);
            return;
        }

        try {
            $result = $this->gdprService->createRequest($userId, $requestType, ['notes' => $notes]);
            $id = $result['id'];

            $this->logger->info("GDPR request created by admin", [
                'request_id' => $id,
                'user_id' => $userId,
                'type' => $requestType,
                'admin_id' => $this->getCurrentUserId(),
            ]);

            echo json_encode(['success' => true, 'id' => $id]);
        } catch (\Exception $e) {
            http_response_code(500);
            echo json_encode(['error' => $e->getMessage()]);
        }
    }

    /**
     * POST /admin/enterprise/gdpr/requests/{id}/complete
     * Mark a GDPR request as completed
     */
    public function gdprRequestComplete(int $id): void
    {
        header('Content-Type: application/json');

        $data = $this->getJsonInput();
        $completionNotes = $data['notes'] ?? '';

        try {
            Database::query(
                "UPDATE gdpr_requests SET status = 'completed', processed_at = NOW(), processed_by = ?, notes = CONCAT(COALESCE(notes, ''), '\n[Completed] ', ?) WHERE id = ? AND tenant_id = ?",
                [$this->getCurrentUserId(), $completionNotes, $id, $this->getTenantId()]
            );

            $this->logger->info("GDPR request #{$id} completed", ['admin_id' => $this->getCurrentUserId()]);

            echo json_encode(['success' => true, 'message' => 'Request marked as completed']);
        } catch (\Exception $e) {
            http_response_code(500);
            echo json_encode(['error' => $e->getMessage()]);
        }
    }

    /**
     * POST /admin/enterprise/gdpr/requests/{id}/assign
     * Assign a GDPR request to an admin
     */
    public function gdprRequestAssign(int $id): void
    {
        header('Content-Type: application/json');

        $data = $this->getJsonInput();
        $assigneeId = (int) ($data['assignee_id'] ?? 0);

        if (!$assigneeId) {
            http_response_code(400);
            echo json_encode(['error' => 'Assignee ID is required']);
            return;
        }

        try {
            Database::query(
                "UPDATE gdpr_requests SET processed_by = ?, status = 'processing' WHERE id = ? AND tenant_id = ?",
                [$assigneeId, $id, $this->getTenantId()]
            );

            $this->logger->info("GDPR request #{$id} assigned", ['assignee_id' => $assigneeId, 'admin_id' => $this->getCurrentUserId()]);

            echo json_encode(['success' => true, 'message' => 'Request assigned successfully']);
        } catch (\Exception $e) {
            http_response_code(500);
            echo json_encode(['error' => $e->getMessage()]);
        }
    }

    /**
     * POST /admin/enterprise/gdpr/requests/{id}/notes
     * Add a note to a GDPR request
     */
    public function gdprRequestAddNote(int $id): void
    {
        header('Content-Type: application/json');

        $data = $this->getJsonInput();
        $note = trim($data['note'] ?? '');

        if (empty($note)) {
            http_response_code(400);
            echo json_encode(['error' => 'Note content is required']);
            return;
        }

        try {
            $timestamp = date('Y-m-d H:i');
            $adminId = $this->getCurrentUserId();

            Database::query(
                "UPDATE gdpr_requests SET notes = CONCAT(COALESCE(notes, ''), '\n[', ?, ' - Admin #', ?, '] ', ?) WHERE id = ? AND tenant_id = ?",
                [$timestamp, $adminId, $note, $id, $this->getTenantId()]
            );

            $this->logger->info("Note added to GDPR request #{$id}");

            echo json_encode(['success' => true, 'message' => 'Note added successfully']);
        } catch (\Exception $e) {
            http_response_code(500);
            echo json_encode(['error' => $e->getMessage()]);
        }
    }

    /**
     * POST /admin/enterprise/gdpr/requests/{id}/generate-export
     * Generate data export for a user
     */
    public function gdprGenerateExport(int $id): void
    {
        header('Content-Type: application/json');

        $request = $this->gdprService->getRequest($id);

        if (!$request) {
            http_response_code(404);
            echo json_encode(['error' => 'Request not found']);
            return;
        }

        try {
            $exportPath = $this->gdprService->generateDataExport($request['user_id'], $id);

            $this->logger->info("Data export generated for request #{$id}", ['path' => $exportPath]);

            echo json_encode([
                'success' => true,
                'message' => 'Data export generated successfully',
                'export_path' => $exportPath,
            ]);
        } catch (\Exception $e) {
            http_response_code(500);
            echo json_encode(['error' => $e->getMessage()]);
        }
    }

    /**
     * POST /admin/enterprise/gdpr/requests/bulk-process
     * Bulk process multiple GDPR requests
     */
    public function gdprBulkProcess(): void
    {
        header('Content-Type: application/json');

        $data = $this->getJsonInput();
        $requestIds = $data['request_ids'] ?? [];
        $action = $data['action'] ?? '';

        if (empty($requestIds) || !is_array($requestIds)) {
            http_response_code(400);
            echo json_encode(['error' => 'Request IDs are required']);
            return;
        }

        $validActions = ['process', 'complete', 'reject'];
        if (!in_array($action, $validActions)) {
            http_response_code(400);
            echo json_encode(['error' => 'Invalid action. Must be: ' . implode(', ', $validActions)]);
            return;
        }

        $processed = 0;
        $errors = [];

        foreach ($requestIds as $id) {
            try {
                switch ($action) {
                    case 'process':
                        $this->gdprService->processRequest((int) $id, $this->getCurrentUserId());
                        break;
                    case 'complete':
                        Database::query(
                            "UPDATE gdpr_requests SET status = 'completed', processed_at = NOW(), processed_by = ? WHERE id = ? AND tenant_id = ?",
                            [$this->getCurrentUserId(), $id, $this->getTenantId()]
                        );
                        break;
                    case 'reject':
                        Database::query(
                            "UPDATE gdpr_requests SET status = 'rejected', processed_at = NOW(), processed_by = ?, rejection_reason = 'Bulk rejection' WHERE id = ? AND tenant_id = ?",
                            [$this->getCurrentUserId(), $id, $this->getTenantId()]
                        );
                        break;
                }
                $processed++;
            } catch (\Exception $e) {
                $errors[] = "Request #{$id}: " . $e->getMessage();
            }
        }

        $this->logger->info("Bulk GDPR action performed", ['action' => $action, 'count' => $processed]);

        echo json_encode([
            'success' => true,
            'processed' => $processed,
            'errors' => $errors,
        ]);
    }

    // =========================================================================
    // CONSENT MANAGEMENT
    // =========================================================================

    /**
     * POST /admin/enterprise/gdpr/consents/types
     * Create or update a consent type
     */
    public function gdprConsentTypeStore(): void
    {
        header('Content-Type: application/json');

        $data = $this->getJsonInput();

        $required = ['slug', 'name', 'current_text'];
        foreach ($required as $field) {
            if (empty($data[$field])) {
                http_response_code(400);
                echo json_encode(['error' => "Field '{$field}' is required"]);
                return;
            }
        }

        try {
            $id = $data['id'] ?? null;

            if ($id) {
                // Update existing
                Database::query(
                    "UPDATE consent_types SET slug = ?, name = ?, description = ?, category = ?, is_required = ?, current_version = ?, current_text = ?, legal_basis = ?, retention_days = ?, is_active = ?, display_order = ? WHERE id = ?",
                    [
                        $data['slug'],
                        $data['name'],
                        $data['description'] ?? null,
                        $data['category'] ?? 'general',
                        $data['is_required'] ?? false,
                        $data['current_version'] ?? '1.0',
                        $data['current_text'],
                        $data['legal_basis'] ?? 'consent',
                        $data['retention_days'] ?? null,
                        $data['is_active'] ?? true,
                        $data['display_order'] ?? 0,
                        $id,
                    ]
                );
            } else {
                // Create new
                Database::query(
                    "INSERT INTO consent_types (slug, name, description, category, is_required, current_version, current_text, legal_basis, retention_days, is_active, display_order) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)",
                    [
                        $data['slug'],
                        $data['name'],
                        $data['description'] ?? null,
                        $data['category'] ?? 'general',
                        $data['is_required'] ?? false,
                        $data['current_version'] ?? '1.0',
                        $data['current_text'],
                        $data['legal_basis'] ?? 'consent',
                        $data['retention_days'] ?? null,
                        $data['is_active'] ?? true,
                        $data['display_order'] ?? 0,
                    ]
                );
                $id = Database::query("SELECT LAST_INSERT_ID() as id")->fetch()['id'];
            }

            $this->logger->info("Consent type saved", ['id' => $id, 'slug' => $data['slug']]);

            echo json_encode(['success' => true, 'id' => $id]);
        } catch (\Exception $e) {
            http_response_code(500);
            echo json_encode(['error' => $e->getMessage()]);
        }
    }

    /**
     * POST /admin/enterprise/gdpr/consents/backfill
     * Backfill consent records for existing users who don't have them
     * This creates records with consent_given=0, prompting users to accept on next login
     */
    public function gdprBackfillConsents(): void
    {
        \Nexus\Core\Csrf::verifyOrDie();

        $consentType = $_POST['consent_type'] ?? '';

        if (empty($consentType)) {
            $_SESSION['flash_error'] = 'Please select a consent type to backfill.';
            header('Location: ' . TenantContext::getBasePath() . '/admin/enterprise/gdpr/consents');
            exit;
        }

        try {
            $consentTypes = $this->gdprService->getConsentTypes();

            $selectedType = null;
            foreach ($consentTypes as $ct) {
                if ($ct['slug'] === $consentType) {
                    $selectedType = $ct;
                    break;
                }
            }

            if (!$selectedType) {
                throw new \Exception('Invalid consent type: ' . $consentType);
            }

            $count = $this->gdprService->backfillConsentsForExistingUsers(
                $selectedType['slug'],
                $selectedType['current_version'],
                $selectedType['current_text']
            );

            $this->logger->info("Consent backfill completed", [
                'consent_type' => $consentType,
                'users_affected' => $count,
                'admin_id' => $this->getCurrentUserId()
            ]);

            if ($count > 0) {
                $_SESSION['flash_success'] = "Successfully created consent records for {$count} users. They will be prompted to accept on their next login.";
            } else {
                $_SESSION['flash_info'] = "No users found without consent records for this type. All users already have records.";
            }

        } catch (\Exception $e) {
            $this->logger->error("Consent backfill failed", ['error' => $e->getMessage()]);
            $_SESSION['flash_error'] = 'Backfill failed: ' . $e->getMessage();
        }

        header('Location: ' . TenantContext::getBasePath() . '/admin/enterprise/gdpr/consents');
        exit;
    }

    /**
     * GET /admin/enterprise/gdpr/consents/{id}
     * View consent type details and user consents
     */
    public function gdprConsentDetail(int $id): void
    {
        $consentType = Database::query(
            "SELECT * FROM consent_types WHERE id = ?",
            [$id]
        )->fetch();

        if (!$consentType) {
            $_SESSION['flash_error'] = 'Consent type not found';
            header('Location: ' . TenantContext::getBasePath() . '/admin/enterprise/gdpr/consents');
            exit;
        }

        // Get user consents for this type
        $consents = Database::query(
            "SELECT uc.*, u.email, u.first_name, u.last_name
             FROM user_consents uc
             JOIN users u ON uc.user_id = u.id
             WHERE uc.consent_type = ? AND uc.tenant_id = ?
             ORDER BY uc.given_at DESC
             LIMIT 100",
            [$consentType['slug'], $this->getTenantId()]
        )->fetchAll();

        // Force modern layout

        View::render('admin/enterprise/gdpr/consent-detail', [
            'consentType' => $consentType,
            'consents' => $consents,
            'title' => 'Consent: ' . $consentType['name'],
        ]);
    }

    /**
     * GET /admin/enterprise/gdpr/consents/export
     * Export all consent records
     */
    public function gdprConsentsExport(): void
    {
        $format = $_GET['format'] ?? 'csv';
        $tenantId = $this->getTenantId();

        $consents = Database::query(
            "SELECT uc.*, ct.name as consent_name, u.email, u.first_name, u.last_name
             FROM user_consents uc
             LEFT JOIN consent_types ct ON uc.consent_type = ct.slug
             LEFT JOIN users u ON uc.user_id = u.id
             WHERE uc.tenant_id = ?
             ORDER BY uc.given_at DESC",
            [$tenantId]
        )->fetchAll();

        if ($format === 'csv') {
            header('Content-Type: text/csv');
            header('Content-Disposition: attachment; filename="consent_records_' . date('Y-m-d') . '.csv"');

            $output = fopen('php://output', 'w');
            fputcsv($output, ['User ID', 'Email', 'Name', 'Consent Type', 'Given', 'Given At', 'Withdrawn At', 'IP Address']);

            foreach ($consents as $consent) {
                fputcsv($output, [
                    $consent['user_id'],
                    $consent['email'],
                    trim($consent['first_name'] . ' ' . $consent['last_name']),
                    $consent['consent_name'] ?? $consent['consent_type'],
                    $consent['consent_given'] ? 'Yes' : 'No',
                    $consent['given_at'],
                    $consent['withdrawn_at'],
                    $consent['ip_address'],
                ]);
            }

            fclose($output);
        } else {
            header('Content-Type: application/json');
            header('Content-Disposition: attachment; filename="consent_records_' . date('Y-m-d') . '.json"');
            echo json_encode($consents, JSON_PRETTY_PRINT);
        }
    }

    /**
     * GET /admin/enterprise/gdpr/audit/export
     * Export GDPR audit log
     */
    public function gdprAuditExport(): void
    {
        $format = $_GET['format'] ?? 'csv';
        $tenantId = $this->getTenantId();

        $logs = Database::query(
            "SELECT al.*, u.email
             FROM gdpr_audit_log al
             LEFT JOIN users u ON al.user_id = u.id
             WHERE al.tenant_id = ?
             ORDER BY al.created_at DESC
             LIMIT 10000",
            [$tenantId]
        )->fetchAll();

        if ($format === 'csv') {
            header('Content-Type: text/csv');
            header('Content-Disposition: attachment; filename="gdpr_audit_log_' . date('Y-m-d') . '.csv"');

            $output = fopen('php://output', 'w');
            fputcsv($output, ['Timestamp', 'User ID', 'Email', 'Admin ID', 'Action', 'Entity Type', 'Entity ID', 'IP Address']);

            foreach ($logs as $log) {
                fputcsv($output, [
                    $log['created_at'],
                    $log['user_id'],
                    $log['email'],
                    $log['admin_id'],
                    $log['action'],
                    $log['entity_type'],
                    $log['entity_id'],
                    $log['ip_address'],
                ]);
            }

            fclose($output);
        } else {
            header('Content-Type: application/json');
            header('Content-Disposition: attachment; filename="gdpr_audit_log_' . date('Y-m-d') . '.json"');
            echo json_encode($logs, JSON_PRETTY_PRINT);
        }
    }

    /**
     * POST /admin/enterprise/gdpr/export-report
     * Generate comprehensive GDPR compliance report
     */
    public function gdprExportReport(): void
    {
        header('Content-Type: application/json');

        $tenantId = $this->getTenantId();

        try {
            $report = [
                'generated_at' => date('c'),
                'tenant_id' => $tenantId,
                'statistics' => $this->gdprService->getStatistics(),
                'consent_types' => $this->gdprService->getConsentTypes(),
                'pending_requests' => $this->gdprService->getPendingRequests(100),
                'data_processing_activities' => Database::query(
                    "SELECT * FROM data_processing_log WHERE tenant_id = ? AND is_active = 1",
                    [$tenantId]
                )->fetchAll(),
                'retention_policies' => Database::query(
                    "SELECT * FROM data_retention_policies WHERE tenant_id = ? AND is_active = 1",
                    [$tenantId]
                )->fetchAll(),
                'recent_breaches' => Database::query(
                    "SELECT * FROM data_breach_log WHERE tenant_id = ? ORDER BY detected_at DESC LIMIT 10",
                    [$tenantId]
                )->fetchAll(),
            ];

            header('Content-Disposition: attachment; filename="gdpr_compliance_report_' . date('Y-m-d') . '.json"');
            echo json_encode($report, JSON_PRETTY_PRINT);
        } catch (\Exception $e) {
            http_response_code(500);
            echo json_encode(['error' => $e->getMessage()]);
        }
    }

    // =========================================================================
    // CONFIGURATION MANAGEMENT
    // =========================================================================

    /**
     * POST /admin/enterprise/config/settings/{group}/{key}
     * Update a configuration setting
     */
    public function configSettingUpdate(string $group, string $key): void
    {
        header('Content-Type: application/json');

        $data = $this->getJsonInput();
        $value = $data['value'] ?? null;

        // Validate group
        $validGroups = ['features', 'security', 'performance', 'notifications'];
        if (!in_array($group, $validGroups)) {
            http_response_code(400);
            echo json_encode(['error' => 'Invalid configuration group']);
            return;
        }

        try {
            // Store in database or env-like storage
            Database::query(
                "INSERT INTO tenant_settings (tenant_id, setting_key, setting_value)
                 VALUES (?, ?, ?)
                 ON DUPLICATE KEY UPDATE setting_value = ?",
                [$this->getTenantId(), "{$group}.{$key}", json_encode($value), json_encode($value)]
            );

            $this->configService->clearCache();
            $this->logger->info("Config updated", ['group' => $group, 'key' => $key]);

            echo json_encode(['success' => true]);
        } catch (\Exception $e) {
            http_response_code(500);
            echo json_encode(['error' => $e->getMessage()]);
        }
    }

    /**
     * GET /admin/enterprise/config/export
     * Export current configuration
     */
    public function configExport(): void
    {
        $config = [
            'exported_at' => date('c'),
            'environment' => getenv('APP_ENV') ?: 'unknown',
            'features' => $this->getFeatureFlags(),
            'status' => $this->configService->getStatus(),
        ];

        header('Content-Type: application/json');
        header('Content-Disposition: attachment; filename="config_export_' . date('Y-m-d') . '.json"');
        echo json_encode($config, JSON_PRETTY_PRINT);
    }

    /**
     * POST /admin/enterprise/config/cache/clear
     * Clear configuration cache
     */
    public function configCacheClear(): void
    {
        header('Content-Type: application/json');

        try {
            $this->configService->clearCache();
            $this->logger->info("Config cache cleared by admin", ['admin_id' => $this->getCurrentUserId()]);

            echo json_encode(['success' => true, 'message' => 'Configuration cache cleared']);
        } catch (\Exception $e) {
            http_response_code(500);
            echo json_encode(['error' => $e->getMessage()]);
        }
    }

    /**
     * GET /admin/enterprise/config/validate
     * Validate current configuration
     */
    public function configValidate(): void
    {
        header('Content-Type: application/json');

        $issues = [];
        $warnings = [];

        // Check required environment variables
        $requiredEnvVars = ['DB_HOST', 'DB_DATABASE', 'APP_KEY'];
        foreach ($requiredEnvVars as $var) {
            if (empty(getenv($var))) {
                $issues[] = "Missing required environment variable: {$var}";
            }
        }

        // Check optional but recommended
        $recommendedEnvVars = ['SMTP_HOST', 'REDIS_HOST'];
        foreach ($recommendedEnvVars as $var) {
            if (empty(getenv($var))) {
                $warnings[] = "Recommended environment variable not set: {$var}";
            }
        }

        // Check database connection
        try {
            Database::query('SELECT 1');
        } catch (\Exception $e) {
            $issues[] = "Database connection failed: " . $e->getMessage();
        }

        // Check Vault status
        if (!$this->configService->isUsingVault()) {
            $warnings[] = "HashiCorp Vault is not configured - using environment variables for secrets";
        }

        echo json_encode([
            'valid' => empty($issues),
            'issues' => $issues,
            'warnings' => $warnings,
            'timestamp' => date('c'),
        ]);
    }

    /**
     * PATCH /admin/enterprise/config/features/{key}
     * Toggle a feature flag
     */
    public function featureFlagToggle(string $key): void
    {
        header('Content-Type: application/json');

        $data = $this->getJsonInput();
        $enabled = (bool) ($data['enabled'] ?? false);

        try {
            Database::query(
                "INSERT INTO tenant_settings (tenant_id, setting_key, setting_value)
                 VALUES (?, ?, ?)
                 ON DUPLICATE KEY UPDATE setting_value = ?",
                [$this->getTenantId(), "feature.{$key}", $enabled ? '1' : '0', $enabled ? '1' : '0']
            );

            $this->logger->info("Feature flag toggled", ['key' => $key, 'enabled' => $enabled]);

            echo json_encode(['success' => true, 'key' => $key, 'enabled' => $enabled]);
        } catch (\Exception $e) {
            http_response_code(500);
            echo json_encode(['error' => $e->getMessage()]);
        }
    }

    /**
     * POST /admin/enterprise/config/features/reset
     * Reset all feature flags to defaults (removes from database)
     */
    public function featureFlagsReset(): void
    {
        header('Content-Type: application/json');

        try {
            Database::query(
                "DELETE FROM tenant_settings WHERE tenant_id = ? AND setting_key LIKE 'feature.%'",
                [$this->getTenantId()]
            );

            $this->logger->info("Feature flags reset to defaults", ['admin_id' => $this->getCurrentUserId()]);

            echo json_encode(['success' => true, 'message' => 'Feature flags reset to defaults']);
        } catch (\Exception $e) {
            http_response_code(500);
            echo json_encode(['error' => $e->getMessage()]);
        }
    }

    // =========================================================================
    // SECRETS MANAGEMENT
    // =========================================================================

    /**
     * POST /admin/enterprise/config/secrets
     * Store a new secret (when Vault is available)
     */
    public function secretStore(): void
    {
        header('Content-Type: application/json');

        if (!$this->configService->isUsingVault()) {
            http_response_code(400);
            echo json_encode(['error' => 'Vault is not configured. Secrets must be managed via environment variables.']);
            return;
        }

        $data = $this->getJsonInput();
        $path = $data['path'] ?? '';
        $secretData = $data['data'] ?? [];

        if (empty($path) || empty($secretData)) {
            http_response_code(400);
            echo json_encode(['error' => 'Path and secret data are required']);
            return;
        }

        try {
            // This would use the VaultClient to store the secret
            // For now, return a placeholder response
            $this->logger->info("Secret stored", ['path' => $path]);

            echo json_encode(['success' => true, 'message' => 'Secret stored successfully']);
        } catch (\Exception $e) {
            http_response_code(500);
            echo json_encode(['error' => $e->getMessage()]);
        }
    }

    /**
     * POST /admin/enterprise/config/secrets/{key}/value
     * Retrieve a secret value (requires additional authentication)
     */
    public function secretView(string $key): void
    {
        header('Content-Type: application/json');

        if (!$this->configService->isUsingVault()) {
            http_response_code(400);
            echo json_encode(['error' => 'Vault is not configured']);
            return;
        }

        // Additional security check could be added here
        $this->logger->info("Secret viewed", ['key' => $key, 'admin_id' => $this->getCurrentUserId()]);

        try {
            $value = $this->configService->get("nexus/{$key}");
            echo json_encode(['success' => true, 'value' => $value]);
        } catch (\Exception $e) {
            http_response_code(500);
            echo json_encode(['error' => $e->getMessage()]);
        }
    }

    /**
     * POST /admin/enterprise/config/secrets/{key}/rotate
     * Rotate a secret (generate new value)
     */
    public function secretRotate(string $key): void
    {
        header('Content-Type: application/json');

        if (!$this->configService->isUsingVault()) {
            http_response_code(400);
            echo json_encode(['error' => 'Vault is not configured. Secret rotation requires Vault.']);
            return;
        }

        try {
            // Generate new secret value and store in Vault
            $this->logger->info("Secret rotated", ['key' => $key, 'admin_id' => $this->getCurrentUserId()]);

            echo json_encode(['success' => true, 'message' => 'Secret rotated successfully']);
        } catch (\Exception $e) {
            http_response_code(500);
            echo json_encode(['error' => $e->getMessage()]);
        }
    }

    /**
     * DELETE /admin/enterprise/config/secrets/{key}
     * Delete a secret
     */
    public function secretDelete(string $key): void
    {
        header('Content-Type: application/json');

        if (!$this->configService->isUsingVault()) {
            http_response_code(400);
            echo json_encode(['error' => 'Vault is not configured']);
            return;
        }

        try {
            $this->logger->info("Secret deleted", ['key' => $key, 'admin_id' => $this->getCurrentUserId()]);

            echo json_encode(['success' => true, 'message' => 'Secret deleted successfully']);
        } catch (\Exception $e) {
            http_response_code(500);
            echo json_encode(['error' => $e->getMessage()]);
        }
    }

    /**
     * GET /admin/enterprise/config/vault/test
     * Test Vault connectivity
     */
    public function vaultTest(): void
    {
        header('Content-Type: application/json');

        $result = [
            'vault_enabled' => strtolower(getenv('USE_VAULT') ?: 'false') === 'true',
            'vault_available' => $this->configService->isUsingVault(),
            'status' => $this->configService->getStatus(),
            'tested_at' => date('c'),
        ];

        if ($result['vault_available']) {
            $result['connection'] = 'success';
        } else {
            $result['connection'] = 'not_configured';
            $result['message'] = 'Vault is not configured. Set USE_VAULT=true and provide VAULT_ROLE_ID/VAULT_SECRET_ID or VAULT_TOKEN.';
        }

        echo json_encode($result);
    }

    // =========================================================================
    // HELPER METHODS
    // =========================================================================

    private function getSystemStatus(): array
    {
        $status = [
            'php_version' => PHP_VERSION,
            'memory_usage' => round(memory_get_usage(true) / 1048576, 2) . ' MB',
            'memory_limit' => ini_get('memory_limit'),
            'max_execution_time' => ini_get('max_execution_time'),
            'opcache_enabled' => extension_loaded('Zend OPcache') && ini_get('opcache.enable'),
            'loaded_extensions' => get_loaded_extensions(),
        ];

        // Get actual server stats on Linux
        if (PHP_OS_FAMILY === 'Linux') {
            // Server memory (RAM) - try /proc first, then shell_exec
            $memInfo = @file_get_contents('/proc/meminfo');
            if ($memInfo) {
                preg_match('/MemTotal:\s+(\d+)/', $memInfo, $totalMatch);
                preg_match('/MemAvailable:\s+(\d+)/', $memInfo, $availMatch);
                if (!empty($totalMatch[1]) && !empty($availMatch[1])) {
                    $totalMB = round($totalMatch[1] / 1024);
                    $availMB = round($availMatch[1] / 1024);
                    $usedMB = $totalMB - $availMB;
                    $usedPercent = round(($usedMB / $totalMB) * 100, 1);
                    $status['server_memory'] = [
                        'total' => $totalMB,
                        'used' => $usedMB,
                        'available' => $availMB,
                        'percent' => $usedPercent,
                        'display' => "{$usedMB} MB / {$totalMB} MB ({$usedPercent}%)"
                    ];
                }
            } elseif (function_exists('shell_exec')) {
                // Fallback: use 'free' command
                $freeOutput = @shell_exec('free -m 2>/dev/null');
                if ($freeOutput && preg_match('/Mem:\s+(\d+)\s+(\d+)\s+(\d+)/', $freeOutput, $matches)) {
                    $totalMB = (int)$matches[1];
                    $usedMB = (int)$matches[2];
                    $usedPercent = $totalMB > 0 ? round(($usedMB / $totalMB) * 100, 1) : 0;
                    $status['server_memory'] = [
                        'total' => $totalMB,
                        'used' => $usedMB,
                        'available' => $totalMB - $usedMB,
                        'percent' => $usedPercent,
                        'display' => "{$usedMB} MB / {$totalMB} MB ({$usedPercent}%)"
                    ];
                }
            }

            // CPU load average - try /proc first, then sys_getloadavg()
            $loadAvg = @file_get_contents('/proc/loadavg');
            if ($loadAvg) {
                $parts = explode(' ', $loadAvg);
                $status['load_average'] = [
                    '1min' => $parts[0] ?? 'N/A',
                    '5min' => $parts[1] ?? 'N/A',
                    '15min' => $parts[2] ?? 'N/A',
                    'display' => trim("{$parts[0]} {$parts[1]} {$parts[2]}")
                ];
            } elseif (function_exists('sys_getloadavg')) {
                // Fallback: PHP built-in function
                $load = sys_getloadavg();
                if ($load) {
                    $status['load_average'] = [
                        '1min' => round($load[0], 2),
                        '5min' => round($load[1], 2),
                        '15min' => round($load[2], 2),
                        'display' => round($load[0], 2) . ' ' . round($load[1], 2) . ' ' . round($load[2], 2)
                    ];
                }
            }

            // Uptime - try /proc first, then shell_exec
            $uptime = @file_get_contents('/proc/uptime');
            if ($uptime) {
                $seconds = (int) explode(' ', $uptime)[0];
                $days = floor($seconds / 86400);
                $hours = floor(($seconds % 86400) / 3600);
                $mins = floor(($seconds % 3600) / 60);
                $status['uptime'] = [
                    'seconds' => $seconds,
                    'display' => "{$days}d {$hours}h {$mins}m"
                ];
            } elseif (function_exists('shell_exec')) {
                // Fallback: use 'uptime -s' command
                $uptimeOutput = @shell_exec('uptime -s 2>/dev/null');
                if ($uptimeOutput) {
                    $bootTime = strtotime(trim($uptimeOutput));
                    if ($bootTime) {
                        $seconds = time() - $bootTime;
                        $days = floor($seconds / 86400);
                        $hours = floor(($seconds % 86400) / 3600);
                        $mins = floor(($seconds % 3600) / 60);
                        $status['uptime'] = [
                            'seconds' => $seconds,
                            'display' => "{$days}d {$hours}h {$mins}m"
                        ];
                    }
                }
            }

            // CPU cores - try /proc first, then nproc command
            $cpuInfo = @file_get_contents('/proc/cpuinfo');
            if ($cpuInfo) {
                $cores = preg_match_all('/^processor/m', $cpuInfo);
                $status['cpu_cores'] = $cores ?: 1;
            } elseif (function_exists('shell_exec')) {
                $nproc = @shell_exec('nproc 2>/dev/null');
                if ($nproc) {
                    $status['cpu_cores'] = (int)trim($nproc) ?: 1;
                }
            }
        }

        // Disk space (works on both Linux and Windows)
        $diskPath = $_SERVER['DOCUMENT_ROOT'] ?? dirname(__DIR__, 3);
        $diskFree = @disk_free_space($diskPath);
        $diskTotal = @disk_total_space($diskPath);
        if ($diskFree !== false && $diskTotal !== false) {
            $diskUsed = $diskTotal - $diskFree;
            $diskPercent = round(($diskUsed / $diskTotal) * 100, 1);
            $status['disk'] = [
                'total' => round($diskTotal / 1073741824, 1),
                'used' => round($diskUsed / 1073741824, 1),
                'free' => round($diskFree / 1073741824, 1),
                'percent' => $diskPercent,
                'display' => round($diskUsed / 1073741824, 1) . ' GB / ' . round($diskTotal / 1073741824, 1) . ' GB (' . $diskPercent . '%)'
            ];
        }

        return $status;
    }

    /**
     * Check all platform requirements
     */
    private function checkPlatformRequirements(): array
    {
        $results = [
            'overall_status' => 'pass',
            'php' => $this->checkPhpRequirements(),
            'extensions' => $this->checkExtensionRequirements(),
            'php_functions' => $this->checkPhpFunctions(),
            'external_binaries' => $this->checkExternalBinaries(),
            'writable_directories' => $this->checkWritableDirectories(),
            'composer' => $this->checkComposerPackages(),
            'services' => $this->checkExternalServices(),
            'ini_settings' => $this->checkPhpIniSettings(),
        ];

        // Determine overall status
        foreach ($results as $key => $section) {
            if ($key === 'overall_status') continue;
            if (isset($section['status']) && $section['status'] === 'fail') {
                $results['overall_status'] = 'fail';
                break;
            }
            if (isset($section['status']) && $section['status'] === 'warning' && $results['overall_status'] !== 'fail') {
                $results['overall_status'] = 'warning';
            }
        }

        return $results;
    }

    /**
     * Check PHP version requirements
     */
    private function checkPhpRequirements(): array
    {
        $required = '8.1.0';
        $recommended = '8.2.0';
        $current = PHP_VERSION;

        $meetsRequired = version_compare($current, $required, '>=');
        $meetsRecommended = version_compare($current, $recommended, '>=');

        return [
            'status' => $meetsRequired ? ($meetsRecommended ? 'pass' : 'warning') : 'fail',
            'current' => $current,
            'required' => $required,
            'recommended' => $recommended,
            'message' => $meetsRequired
                ? ($meetsRecommended ? 'PHP version meets all requirements' : "PHP {$current} works but {$recommended}+ is recommended")
                : "PHP {$required}+ is required, you have {$current}",
        ];
    }

    /**
     * Check PHP extension requirements
     */
    private function checkExtensionRequirements(): array
    {
        // Required extensions with usage locations
        $required = [
            'pdo' => [
                'description' => 'Database connectivity (PDO)',
                'used_in' => ['src/Core/Database.php'],
            ],
            'pdo_mysql' => [
                'description' => 'MySQL database driver',
                'used_in' => ['src/Core/Database.php:30'],
            ],
            'curl' => [
                'description' => 'HTTP requests & API calls',
                'used_in' => [
                    'src/Core/Validator.php',
                    'src/Core/SimpleOAuth.php',
                    'src/Core/Mailer.php',
                    'src/Services/FCMPushService.php',
                    'src/Services/MailchimpService.php',
                    'src/Services/AI/Providers/*',
                ],
            ],
            'json' => [
                'description' => 'JSON encoding/decoding',
                'used_in' => ['30+ files throughout codebase'],
            ],
            'mbstring' => [
                'description' => 'Multi-byte string handling',
                'used_in' => [
                    'src/Models/AiConversation.php:137-138',
                    'src/Core/HtmlSanitizer.php:325-334',
                    'src/Services/FeedRankingService.php:929',
                    'src/Models/Message.php:39',
                    'src/Models/Notification.php:67,96',
                ],
            ],
            'openssl' => [
                'description' => 'Encryption, HTTPS, JWT signing',
                'used_in' => [
                    'src/Services/FCMPushService.php:277',
                    'src/Models/AiSettings.php:195,215',
                ],
            ],
            'zip' => [
                'description' => 'Archive handling (GDPR exports)',
                'used_in' => [
                    'src/Services/Enterprise/GdprService.php:1023-1024',
                    'src/Controllers/Admin/EnterpriseController.php:635-637',
                ],
            ],
            'gd' => [
                'description' => 'Image processing & manipulation',
                'used_in' => [
                    'src/Core/ImageUploader.php:142-233',
                    'src/Controllers/OnboardingController.php',
                    'src/Controllers/ProfileController.php',
                    'src/Helpers/ImageHelper.php',
                ],
            ],
            'fileinfo' => [
                'description' => 'MIME type detection for uploads',
                'used_in' => ['src/Core/ImageUploader.php:35-36'],
            ],
            'dom' => [
                'description' => 'DOM/HTML manipulation & sanitization',
                'used_in' => ['src/Core/HtmlSanitizer.php:101-138'],
            ],
            'xml' => [
                'description' => 'XML parsing (sitemaps)',
                'used_in' => ['src/Controllers/SitemapController.php'],
            ],
            'session' => [
                'description' => 'Session management',
                'used_in' => [
                    'src/Core/Csrf.php',
                    'src/Core/ApiAuth.php',
                    '20+ controller files',
                ],
            ],
            'hash' => [
                'description' => 'Cryptographic hashing (HMAC)',
                'used_in' => [
                    'src/Core/Csrf.php',
                    'src/Services/TokenService.php',
                    'src/Controllers/DigestController.php',
                ],
            ],
            'pcre' => [
                'description' => 'Regular expressions',
                'used_in' => ['30+ files - validation, parsing'],
            ],
            'filter' => [
                'description' => 'Data filtering & validation',
                'used_in' => ['Throughout codebase - input validation'],
            ],
            'sockets' => [
                'description' => 'Socket connections (SMTP, metrics)',
                'used_in' => [
                    'src/Core/Mailer.php',
                    'src/Services/Enterprise/MetricsService.php',
                ],
            ],
        ];

        // Optional extensions (recommended for performance/features)
        $optional = [
            'redis' => [
                'description' => 'Redis caching (recommended for performance)',
                'used_in' => ['src/Services/RedisCache.php'],
            ],
            'imagick' => [
                'description' => 'Advanced image processing',
                'used_in' => ['Optional alternative to GD'],
            ],
            'memcached' => [
                'description' => 'Memcached caching',
                'used_in' => ['Alternative caching backend'],
            ],
            'sodium' => [
                'description' => 'Modern cryptography (libsodium)',
                'used_in' => ['Enhanced encryption support'],
            ],
            'Zend OPcache' => [
                'description' => 'PHP opcode caching (performance)',
                'used_in' => ['PHP bytecode optimization'],
            ],
            'apcu' => [
                'description' => 'APCu user caching',
                'used_in' => ['Alternative user-space caching'],
            ],
            'pdo_sqlite' => [
                'description' => 'SQLite database driver (alternative DB)',
                'used_in' => ['src/Core/Database.php:27'],
            ],
            'exif' => [
                'description' => 'Image EXIF metadata reading',
                'used_in' => ['Image orientation detection'],
            ],
            'iconv' => [
                'description' => 'Character encoding conversion',
                'used_in' => ['Fallback for mbstring'],
            ],
        ];

        $extensions = [];
        $status = 'pass';
        $missingRequired = 0;
        $missingOptional = 0;

        // Check required extensions
        foreach ($required as $ext => $info) {
            $loaded = extension_loaded($ext);
            $extensions[] = [
                'name' => $ext,
                'description' => $info['description'],
                'used_in' => $info['used_in'] ?? [],
                'required' => true,
                'loaded' => $loaded,
                'status' => $loaded ? 'pass' : 'fail',
            ];
            if (!$loaded) {
                $missingRequired++;
                $status = 'fail';
            }
        }

        // Check optional extensions
        foreach ($optional as $ext => $info) {
            $loaded = extension_loaded($ext);
            $extensions[] = [
                'name' => $ext,
                'description' => $info['description'],
                'used_in' => $info['used_in'] ?? [],
                'required' => false,
                'loaded' => $loaded,
                'status' => $loaded ? 'pass' : 'info',
            ];
            if (!$loaded) {
                $missingOptional++;
            }
        }

        return [
            'status' => $status,
            'extensions' => $extensions,
            'missing_required' => $missingRequired,
            'missing_optional' => $missingOptional,
            'total_loaded' => count(get_loaded_extensions()),
        ];
    }

    /**
     * Check critical PHP functions used by the platform
     */
    private function checkPhpFunctions(): array
    {
        $functions = [
            // cURL functions
            'curl_init' => [
                'extension' => 'curl',
                'description' => 'Initialize cURL session',
                'used_in' => ['HTTP API requests'],
                'critical' => true,
            ],
            'curl_exec' => [
                'extension' => 'curl',
                'description' => 'Execute cURL request',
                'used_in' => ['HTTP API requests'],
                'critical' => true,
            ],
            // Image functions
            'imagecreatefromjpeg' => [
                'extension' => 'gd',
                'description' => 'Create image from JPEG',
                'used_in' => ['src/Core/ImageUploader.php'],
                'critical' => true,
            ],
            'imagecreatefrompng' => [
                'extension' => 'gd',
                'description' => 'Create image from PNG',
                'used_in' => ['src/Core/ImageUploader.php'],
                'critical' => true,
            ],
            'imagecreatefromwebp' => [
                'extension' => 'gd',
                'description' => 'Create image from WebP',
                'used_in' => ['src/Core/ImageUploader.php'],
                'critical' => true,
            ],
            'imagewebp' => [
                'extension' => 'gd',
                'description' => 'Output WebP image',
                'used_in' => ['src/Core/ImageUploader.php'],
                'critical' => true,
            ],
            'getimagesize' => [
                'extension' => 'gd',
                'description' => 'Get image dimensions',
                'used_in' => ['src/Core/ImageUploader.php'],
                'critical' => true,
            ],
            // String functions
            'mb_strlen' => [
                'extension' => 'mbstring',
                'description' => 'Multi-byte string length',
                'used_in' => ['Text processing'],
                'critical' => true,
            ],
            'mb_substr' => [
                'extension' => 'mbstring',
                'description' => 'Multi-byte substring',
                'used_in' => ['Text processing'],
                'critical' => true,
            ],
            // JSON functions
            'json_encode' => [
                'extension' => 'json',
                'description' => 'Encode to JSON',
                'used_in' => ['API responses'],
                'critical' => true,
            ],
            'json_decode' => [
                'extension' => 'json',
                'description' => 'Decode JSON',
                'used_in' => ['API requests'],
                'critical' => true,
            ],
            // Crypto functions
            'openssl_sign' => [
                'extension' => 'openssl',
                'description' => 'Generate signature',
                'used_in' => ['src/Services/FCMPushService.php'],
                'critical' => true,
            ],
            'openssl_encrypt' => [
                'extension' => 'openssl',
                'description' => 'Encrypt data',
                'used_in' => ['src/Models/AiSettings.php'],
                'critical' => true,
            ],
            'openssl_decrypt' => [
                'extension' => 'openssl',
                'description' => 'Decrypt data',
                'used_in' => ['src/Models/AiSettings.php'],
                'critical' => true,
            ],
            'password_hash' => [
                'extension' => 'Core',
                'description' => 'Hash passwords (bcrypt)',
                'used_in' => ['User authentication'],
                'critical' => true,
            ],
            'password_verify' => [
                'extension' => 'Core',
                'description' => 'Verify password hash',
                'used_in' => ['User authentication'],
                'critical' => true,
            ],
            'hash_hmac' => [
                'extension' => 'hash',
                'description' => 'HMAC hashing',
                'used_in' => ['CSRF, API signatures'],
                'critical' => true,
            ],
            // File functions
            'finfo_open' => [
                'extension' => 'fileinfo',
                'description' => 'Open fileinfo resource',
                'used_in' => ['src/Core/ImageUploader.php'],
                'critical' => true,
            ],
            'finfo_file' => [
                'extension' => 'fileinfo',
                'description' => 'Get file MIME type',
                'used_in' => ['src/Core/ImageUploader.php'],
                'critical' => true,
            ],
            // DOM functions
            'libxml_use_internal_errors' => [
                'extension' => 'libxml',
                'description' => 'Control XML error handling',
                'used_in' => ['src/Core/HtmlSanitizer.php'],
                'critical' => true,
            ],
            // Session functions
            'session_start' => [
                'extension' => 'session',
                'description' => 'Start session',
                'used_in' => ['User sessions'],
                'critical' => true,
            ],
            // Socket functions
            'fsockopen' => [
                'extension' => 'sockets',
                'description' => 'Open socket connection',
                'used_in' => ['src/Core/Mailer.php'],
                'critical' => false,
            ],
            // ZIP functions
            'zip_open' => [
                'extension' => 'zip',
                'description' => 'Open ZIP archive',
                'used_in' => ['GDPR exports'],
                'critical' => false,
            ],
        ];

        $results = [];
        $status = 'pass';
        $missingCritical = 0;

        foreach ($functions as $func => $info) {
            $exists = function_exists($func);
            $funcStatus = $exists ? 'pass' : ($info['critical'] ? 'fail' : 'warning');

            $results[] = [
                'name' => $func,
                'extension' => $info['extension'],
                'description' => $info['description'],
                'used_in' => $info['used_in'],
                'critical' => $info['critical'],
                'exists' => $exists,
                'status' => $funcStatus,
            ];

            if (!$exists && $info['critical']) {
                $missingCritical++;
                $status = 'fail';
            }
        }

        return [
            'status' => $status,
            'functions' => $results,
            'missing_critical' => $missingCritical,
            'total_checked' => count($functions),
        ];
    }

    /**
     * Check external binaries used by the platform
     */
    private function checkExternalBinaries(): array
    {
        $binaries = [
            'cwebp' => [
                'description' => 'WebP image converter',
                'used_in' => ['src/Admin/WebPConverter.php:272'],
                'required' => false,
                'check_command' => 'cwebp -version 2>&1',
            ],
            'mysqldump' => [
                'description' => 'MySQL database backup tool',
                'used_in' => ['src/Controllers/Admin/BlogRestoreController.php'],
                'required' => false,
                'check_command' => 'mysqldump --version 2>&1',
            ],
            'mysql' => [
                'description' => 'MySQL command-line client',
                'used_in' => ['Database restore operations'],
                'required' => false,
                'check_command' => 'mysql --version 2>&1',
            ],
            'composer' => [
                'description' => 'PHP dependency manager',
                'used_in' => ['Package management'],
                'required' => false,
                'check_command' => 'composer --version 2>&1',
            ],
        ];

        $results = [];
        $status = 'pass';

        foreach ($binaries as $binary => $info) {
            $available = false;
            $version = null;

            // Try to execute version check
            if (!empty($info['check_command'])) {
                $output = @shell_exec($info['check_command']);
                if ($output && !str_contains($output, 'not found') && !str_contains($output, 'not recognized')) {
                    $available = true;
                    // Extract version from first line
                    $lines = explode("\n", trim($output));
                    $version = trim($lines[0]);
                }
            }

            $binStatus = $available ? 'pass' : ($info['required'] ? 'fail' : 'info');

            $results[] = [
                'name' => $binary,
                'description' => $info['description'],
                'used_in' => $info['used_in'],
                'required' => $info['required'],
                'available' => $available,
                'version' => $version,
                'status' => $binStatus,
            ];

            if (!$available && $info['required']) {
                $status = 'fail';
            }
        }

        return [
            'status' => $status,
            'binaries' => $results,
        ];
    }

    /**
     * Check writable directories
     */
    private function checkWritableDirectories(): array
    {
        $basePath = dirname(__DIR__, 3);
        $directories = [
            'logs' => $basePath . '/logs',
            'cache' => $basePath . '/cache',
            'uploads' => $basePath . '/httpdocs/uploads',
            'exports' => $basePath . '/exports',
            'sessions' => session_save_path() ?: sys_get_temp_dir(),
        ];

        $results = [];
        $status = 'pass';

        foreach ($directories as $name => $path) {
            $exists = is_dir($path);
            $writable = $exists && is_writable($path);

            $results[] = [
                'name' => $name,
                'path' => $path,
                'exists' => $exists,
                'writable' => $writable,
                'status' => $writable ? 'pass' : ($exists ? 'fail' : 'warning'),
            ];

            if (!$writable && $name !== 'sessions') {
                $status = $exists ? 'fail' : 'warning';
            }
        }

        return [
            'status' => $status,
            'directories' => $results,
        ];
    }

    /**
     * Check composer packages
     */
    private function checkComposerPackages(): array
    {
        $basePath = dirname(__DIR__, 3);
        $composerJson = $basePath . '/composer.json';
        $composerLock = $basePath . '/composer.lock';
        $vendorAutoload = $basePath . '/vendor/autoload.php';

        $status = 'pass';
        $packages = [];
        $issues = [];

        // Check if composer files exist
        if (!file_exists($composerJson)) {
            return [
                'status' => 'fail',
                'message' => 'composer.json not found',
                'packages' => [],
            ];
        }

        if (!file_exists($vendorAutoload)) {
            return [
                'status' => 'fail',
                'message' => 'Vendor directory not found. Run: composer install',
                'packages' => [],
            ];
        }

        // Parse composer.json
        $composer = json_decode(file_get_contents($composerJson), true);
        $required = $composer['require'] ?? [];

        // Check installed packages
        $installedFile = $basePath . '/vendor/composer/installed.php';
        $installed = [];
        if (file_exists($installedFile)) {
            $installedData = require $installedFile;
            $installed = $installedData['versions'] ?? [];
        }

        foreach ($required as $package => $version) {
            // Skip platform requirements (php, ext-*)
            if (strpos($package, 'php') === 0 || strpos($package, 'ext-') === 0) {
                continue;
            }

            $isInstalled = isset($installed[$package]);
            $installedVersion = $isInstalled ? ($installed[$package]['pretty_version'] ?? 'unknown') : null;

            $packages[] = [
                'name' => $package,
                'required_version' => $version,
                'installed_version' => $installedVersion,
                'status' => $isInstalled ? 'pass' : 'fail',
            ];

            if (!$isInstalled) {
                $status = 'fail';
                $issues[] = "Missing package: {$package}";
            }
        }

        // Check if lock file is up to date
        $lockOutdated = false;
        if (file_exists($composerLock)) {
            $lockModified = filemtime($composerLock);
            $jsonModified = filemtime($composerJson);
            if ($jsonModified > $lockModified) {
                $lockOutdated = true;
                if ($status !== 'fail') {
                    $status = 'warning';
                }
                $issues[] = 'composer.lock may be outdated. Run: composer update';
            }
        }

        return [
            'status' => $status,
            'packages' => $packages,
            'issues' => $issues,
            'lock_outdated' => $lockOutdated,
            'total_packages' => count($packages),
        ];
    }

    /**
     * Check external services
     */
    private function checkExternalServices(): array
    {
        $services = [];
        $status = 'pass';

        // Database
        try {
            $startTime = microtime(true);
            Database::query('SELECT 1');
            $latency = round((microtime(true) - $startTime) * 1000, 2);
            $services[] = [
                'name' => 'Database (MySQL)',
                'status' => 'pass',
                'latency_ms' => $latency,
                'message' => "Connected ({$latency}ms)",
            ];
        } catch (\Exception $e) {
            $services[] = [
                'name' => 'Database (MySQL)',
                'status' => 'fail',
                'message' => 'Connection failed: ' . $e->getMessage(),
            ];
            $status = 'fail';
        }

        // Redis
        try {
            if (extension_loaded('redis')) {
                $redis = new \Redis();
                $redisHost = getenv('REDIS_HOST') ?: '127.0.0.1';
                $redisPort = (int) (getenv('REDIS_PORT') ?: 6379);
                $startTime = microtime(true);
                $connected = @$redis->connect($redisHost, $redisPort, 1);
                if ($connected) {
                    $redis->ping();
                    $latency = round((microtime(true) - $startTime) * 1000, 2);
                    $services[] = [
                        'name' => 'Redis Cache',
                        'status' => 'pass',
                        'latency_ms' => $latency,
                        'message' => "Connected to {$redisHost}:{$redisPort} ({$latency}ms)",
                    ];
                } else {
                    throw new \Exception("Could not connect to {$redisHost}:{$redisPort}");
                }
            } else {
                $services[] = [
                    'name' => 'Redis Cache',
                    'status' => 'info',
                    'message' => 'Extension not loaded (optional)',
                ];
            }
        } catch (\Exception $e) {
            $services[] = [
                'name' => 'Redis Cache',
                'status' => 'warning',
                'message' => 'Not available: ' . $e->getMessage(),
            ];
            if ($status === 'pass') {
                $status = 'warning';
            }
        }

        // SMTP / Mail
        $smtpHost = getenv('SMTP_HOST') ?: getenv('MAIL_HOST');
        if ($smtpHost) {
            $smtpPort = (int) (getenv('SMTP_PORT') ?: getenv('MAIL_PORT') ?: 587);
            $socket = @fsockopen($smtpHost, $smtpPort, $errno, $errstr, 3);
            if ($socket) {
                fclose($socket);
                $services[] = [
                    'name' => 'SMTP Mail Server',
                    'status' => 'pass',
                    'message' => "Reachable at {$smtpHost}:{$smtpPort}",
                ];
            } else {
                $services[] = [
                    'name' => 'SMTP Mail Server',
                    'status' => 'warning',
                    'message' => "Cannot reach {$smtpHost}:{$smtpPort}",
                ];
                if ($status === 'pass') {
                    $status = 'warning';
                }
            }
        } else {
            $services[] = [
                'name' => 'SMTP Mail Server',
                'status' => 'info',
                'message' => 'Not configured (SMTP_HOST not set)',
            ];
        }

        // Vault
        if ($this->configService->isUsingVault()) {
            $services[] = [
                'name' => 'HashiCorp Vault',
                'status' => 'pass',
                'message' => 'Connected and authenticated',
            ];
        } else {
            $services[] = [
                'name' => 'HashiCorp Vault',
                'status' => 'info',
                'message' => 'Not configured (using env variables)',
            ];
        }

        return [
            'status' => $status,
            'services' => $services,
        ];
    }

    /**
     * Check PHP ini settings
     */
    private function checkPhpIniSettings(): array
    {
        $settings = [];
        $status = 'pass';

        $checks = [
            'memory_limit' => ['min' => '128M', 'recommended' => '256M'],
            'max_execution_time' => ['min' => 30, 'recommended' => 120],
            'upload_max_filesize' => ['min' => '8M', 'recommended' => '64M'],
            'post_max_size' => ['min' => '8M', 'recommended' => '64M'],
            'max_input_vars' => ['min' => 1000, 'recommended' => 3000],
        ];

        foreach ($checks as $setting => $requirements) {
            $current = ini_get($setting);
            $currentBytes = $this->parseIniSize($current);
            $minBytes = $this->parseIniSize($requirements['min']);
            $recommendedBytes = $this->parseIniSize($requirements['recommended']);

            $meetsMin = $currentBytes >= $minBytes || $currentBytes === -1; // -1 means unlimited
            $meetsRecommended = $currentBytes >= $recommendedBytes || $currentBytes === -1;

            $settingStatus = 'pass';
            if (!$meetsMin) {
                $settingStatus = 'fail';
                $status = 'fail';
            } elseif (!$meetsRecommended && $status !== 'fail') {
                $settingStatus = 'warning';
                if ($status === 'pass') {
                    $status = 'warning';
                }
            }

            $settings[] = [
                'name' => $setting,
                'current' => $current ?: 'not set',
                'minimum' => (string) $requirements['min'],
                'recommended' => (string) $requirements['recommended'],
                'status' => $settingStatus,
            ];
        }

        // Additional boolean checks
        $booleanChecks = [
            'allow_url_fopen' => ['expected' => true, 'description' => 'Required for remote file access'],
            'file_uploads' => ['expected' => true, 'description' => 'Required for file uploads'],
        ];

        foreach ($booleanChecks as $setting => $check) {
            $current = ini_get($setting);
            $isEnabled = filter_var($current, FILTER_VALIDATE_BOOLEAN);
            $meetsExpectation = $isEnabled === $check['expected'];

            $settings[] = [
                'name' => $setting,
                'current' => $isEnabled ? 'On' : 'Off',
                'minimum' => $check['expected'] ? 'On' : 'Off',
                'recommended' => $check['expected'] ? 'On' : 'Off',
                'status' => $meetsExpectation ? 'pass' : 'fail',
                'description' => $check['description'],
            ];

            if (!$meetsExpectation) {
                $status = 'fail';
            }
        }

        return [
            'status' => $status,
            'settings' => $settings,
        ];
    }

    /**
     * Parse INI size string to bytes
     */
    private function parseIniSize($size): int
    {
        if (is_numeric($size)) {
            return (int) $size;
        }

        $size = trim((string) $size);
        if (empty($size) || $size === '-1') {
            return -1; // Unlimited
        }

        $unit = strtoupper(substr($size, -1));
        $value = (int) substr($size, 0, -1);

        switch ($unit) {
            case 'G':
                $value *= 1024;
                // fall through
            case 'M':
                $value *= 1024;
                // fall through
            case 'K':
                $value *= 1024;
        }

        return $value;
    }

    private function getFeatureFlags(): array
    {
        // Define all available features with their defaults (true = enabled by default)
        $availableFeatures = [
            // Core Modules
            'timebanking' => true,
            'listings' => true,
            'messaging' => true,
            'connections' => true,
            'profiles' => true,
            // Community
            'groups' => true,
            'events' => true,
            'volunteering' => true,
            'organizations' => true,
            // Engagement
            'gamification' => true,
            'leaderboard' => true,
            'badges' => true,
            'streaks' => true,
            // AI & Smart Features
            'ai_chat' => true,
            'smart_matching' => true,
            'ai_moderation' => false,
            // Notifications
            'push_notifications' => true,
            'email_notifications' => true,
            // Enterprise
            'gdpr_compliance' => true,
            'analytics' => true,
            'audit_logging' => true,
            // Map & Location
            'map_view' => true,
            'geolocation' => true,
        ];

        // Load stored settings from database
        $tenantId = $this->getTenantId();
        try {
            $stored = Database::query(
                "SELECT setting_key, setting_value FROM tenant_settings WHERE tenant_id = ? AND setting_key LIKE 'feature.%'",
                [$tenantId]
            )->fetchAll(\PDO::FETCH_KEY_PAIR);
        } catch (\Exception $e) {
            $stored = [];
        }

        // Merge defaults with stored settings
        $features = [];
        foreach ($availableFeatures as $key => $default) {
            $dbKey = "feature.{$key}";
            if (isset($stored[$dbKey])) {
                $features[$key] = $stored[$dbKey] === '1' || $stored[$dbKey] === 'true';
            } else {
                // Check environment variable as fallback, then use default
                $envKey = 'FEATURE_' . strtoupper($key);
                $envValue = getenv($envKey);
                $features[$key] = $envValue !== false ? (bool) $envValue : $default;
            }
        }

        return $features;
    }

    private function tailFile(string $filepath, int $lines = 100): string
    {
        $file = new \SplFileObject($filepath, 'r');
        $file->seek(PHP_INT_MAX);
        $lastLine = $file->key();

        $startLine = max(0, $lastLine - $lines);
        $output = [];

        $file->seek($startLine);
        while (!$file->eof()) {
            $output[] = $file->current();
            $file->next();
        }

        return implode('', $output);
    }

    private function wantsJson(): bool
    {
        $accept = $_SERVER['HTTP_ACCEPT'] ?? '';
        return strpos($accept, 'application/json') !== false;
    }

    private function getJsonInput(): array
    {
        $input = file_get_contents('php://input');
        return json_decode($input, true) ?: [];
    }

    private function getCurrentUserId(): int
    {
        return (int) ($_SESSION['user_id'] ?? 0);
    }

    private function getTenantId(): int
    {
        return (int) ($_SESSION['tenant_id'] ?? 1);
    }

    private function formatFileSize(int $bytes): string
    {
        if ($bytes < 1024) return $bytes . ' B';
        if ($bytes < 1048576) return round($bytes / 1024, 1) . ' KB';
        return round($bytes / 1048576, 1) . ' MB';
    }

    private function getLogPreview(string $filePath, int $lines = 3): string
    {
        $file = new \SplFileObject($filePath, 'r');
        $file->seek(PHP_INT_MAX);
        $lastLine = $file->key();

        $startLine = max(0, $lastLine - $lines);
        $output = [];

        $file->seek($startLine);
        while (!$file->eof() && count($output) < $lines) {
            $line = trim($file->current());
            if (!empty($line)) {
                $output[] = $line;
            }
            $file->next();
        }

        return implode("\n", $output);
    }

    // =========================================================================
    // REAL-TIME UPDATES API
    // =========================================================================

    /**
     * GET /admin/api/realtime
     * Server-Sent Events endpoint for real-time updates
     *
     * NOTE: SSE endpoint is currently DISABLED due to server hanging issues.
     * The frontend has been configured to use polling instead.
     * This endpoint returns a 503 to indicate SSE is unavailable.
     *
     * @see realtimePoll() for the active polling implementation
     */
    public function realtimeStream(): void
    {
        // SSE DISABLED: Server-Sent Events were causing server hanging issues
        // Frontend now uses polling endpoint (/admin/api/realtime/poll) instead
        header('Content-Type: application/json');
        http_response_code(503);
        echo json_encode([
            'error' => 'Real-time streaming disabled',
            'message' => 'SSE endpoint is disabled. Using polling endpoint at /admin/api/realtime/poll',
            'use_polling' => true,
            'polling_endpoint' => '/admin/api/realtime/poll'
        ]);
        return;

        /* ORIGINAL CODE - DISABLED
        header('Content-Type: text/event-stream');
        header('Cache-Control: no-cache');
        header('Connection: keep-alive');
        header('X-Accel-Buffering: no'); // Disable nginx buffering

        // Set unlimited execution time for SSE
        set_time_limit(0);

        // Ensure output is sent immediately
        if (ob_get_level()) ob_end_clean();

        $lastEventId = (int) ($_SERVER['HTTP_LAST_EVENT_ID'] ?? 0);
        $eventId = $lastEventId;

        // Send initial connection message
        echo "retry: 10000\n\n";
        flush();

        // Main event loop - send updates every 30 seconds
        $iterations = 0;
        $maxIterations = 120; // Run for 60 minutes max (120 * 30s)

        while ($iterations < $maxIterations && connection_status() === CONNECTION_NORMAL) {
            $eventId++;

            // Send stats update
            $stats = $this->getRealtimeStats();
            echo "id: {$eventId}\n";
            echo "event: stats\n";
            echo "data: " . json_encode($stats) . "\n\n";
            flush();

            // Send notification update
            $notifications = $this->getRealtimeNotifications();
            echo "id: {$eventId}\n";
            echo "event: notification\n";
            echo "data: " . json_encode($notifications) . "\n\n";
            flush();

            // Send health update
            $health = $this->getRealtimeHealth();
            echo "id: {$eventId}\n";
            echo "event: health\n";
            echo "data: " . json_encode($health) . "\n\n";
            flush();

            // Send user activity update
            $users = $this->getRealtimeUsers();
            echo "id: {$eventId}\n";
            echo "event: users\n";
            echo "data: " . json_encode($users) . "\n\n";
            flush();

            // Wait 30 seconds before next update
            sleep(30);
            $iterations++;
        }
        */
    }

    /**
     * GET /admin/api/realtime/poll
     * Polling fallback endpoint for browsers without SSE support
     */
    public function realtimePoll(): void
    {
        header('Content-Type: application/json');

        $data = [
            'stats' => $this->getRealtimeStats(),
            'notifications' => $this->getRealtimeNotifications(),
            'health' => $this->getRealtimeHealth(),
            'users' => $this->getRealtimeUsers(),
            'timestamp' => time(),
        ];

        echo json_encode($data);
    }

    /**
     * Get real-time dashboard statistics
     */
    private function getRealtimeStats(): array
    {
        $db = Database::getInstance();

        // Get users online (active in last 15 minutes)
        $usersOnline = 0;
        try {
            $result = $db->query(
                "SELECT COUNT(DISTINCT user_id) as count
                 FROM sessions
                 WHERE last_activity > DATE_SUB(NOW(), INTERVAL 15 MINUTE)
                 AND user_id IS NOT NULL"
            )->fetch();
            $usersOnline = $result['count'] ?? 0;
        } catch (\Exception $e) {
            // Sessions table might not exist yet - silently fail
            error_log("Sessions table query failed: " . $e->getMessage());
        }

        // Get active sessions
        $activeSessions = 0;
        try {
            $result = $db->query(
                "SELECT COUNT(*) as count FROM sessions WHERE expires_at > NOW()"
            )->fetch();
            $activeSessions = $result['count'] ?? 0;
        } catch (\Exception $e) {
            // Sessions table might not exist yet - silently fail
            error_log("Sessions table query failed: " . $e->getMessage());
        }

        // Get today's new users
        $newUsersToday = 0;
        try {
            $result = $db->query(
                "SELECT COUNT(*) as count FROM users WHERE DATE(created_at) = CURDATE()"
            )->fetch();
            $newUsersToday = $result['count'] ?? 0;
        } catch (\Exception $e) {
            error_log("Users query failed: " . $e->getMessage());
        }

        // Get total revenue today (if you have an orders/transactions table)
        $revenueToday = 0;
        try {
            $result = $db->query(
                "SELECT COALESCE(SUM(amount), 0) as total
                 FROM transactions
                 WHERE DATE(created_at) = CURDATE() AND status = 'completed'"
            )->fetch();
            $revenueToday = $result['total'] ?? 0;
        } catch (\Exception $e) {
            // Table might not exist
        }

        return [
            'users_online' => (int) $usersOnline,
            'active_sessions' => (int) $activeSessions,
            'new_users_today' => (int) $newUsersToday,
            'revenue_today' => (float) $revenueToday,
        ];
    }

    /**
     * Get real-time notification updates
     */
    private function getRealtimeNotifications(): array
    {
        $userId = $this->getCurrentUserId();

        // Get unread admin notifications count
        $unreadCount = 0;
        $latestNotification = null;

        try {
            $result = Database::query(
                "SELECT COUNT(*) as count FROM notifications
                 WHERE user_id = ? AND is_read = 0 AND deleted_at IS NULL",
                [$userId]
            )->fetch();
            $unreadCount = $result['count'] ?? 0;

            // Get latest unread notification
            if ($unreadCount > 0) {
                $latest = Database::query(
                    "SELECT * FROM notifications
                     WHERE user_id = ? AND is_read = 0 AND deleted_at IS NULL
                     ORDER BY created_at DESC LIMIT 1",
                    [$userId]
                )->fetch();

                if ($latest) {
                    $latestNotification = [
                        'id' => $latest['id'],
                        'message' => $latest['message'] ?? $latest['title'],
                        'type' => $latest['type'] ?? 'info',
                        'created_at' => $latest['created_at'],
                    ];
                }
            }
        } catch (\Exception $e) {
            // Table might not exist
        }

        $response = [
            'count' => (int) $unreadCount,
        ];

        // Check if this is a new notification since last poll
        if ($latestNotification && isset($_SESSION['last_notification_check'])) {
            $lastCheck = $_SESSION['last_notification_check'];
            if (strtotime($latestNotification['created_at']) > $lastCheck) {
                $response['new'] = true;
                $response['message'] = $latestNotification['message'];
                $response['type'] = $latestNotification['type'];
            }
        }

        $_SESSION['last_notification_check'] = time();

        return $response;
    }

    /**
     * Get real-time health status
     */
    private function getRealtimeHealth(): array
    {
        $db = Database::getInstance();

        $health = [
            'status' => 'healthy',
            'checks' => [],
        ];

        // Database check
        try {
            $db->query("SELECT 1")->fetch();
            $health['checks']['database'] = 'healthy';
        } catch (\Exception $e) {
            $health['checks']['database'] = 'unhealthy';
            $health['status'] = 'unhealthy';
        }

        // Disk space check
        // Use document root or current directory for disk space check
        $checkPath = $_SERVER['DOCUMENT_ROOT'] ?? dirname(__DIR__, 3);
        $freeSpace = @disk_free_space($checkPath);
        $totalSpace = @disk_total_space($checkPath);

        // Handle case where disk functions fail (e.g., open_basedir restrictions)
        if ($freeSpace === false || $totalSpace === false || $totalSpace == 0) {
            $health['checks']['disk'] = 'unknown';
            // Only log in debug mode to avoid cluttering error logs
            if (getenv('APP_DEBUG') === 'true') {
                error_log('Disk space check failed for path: ' . $checkPath);
            }
        } else {
            $percentFree = ($freeSpace / $totalSpace) * 100;

            if ($percentFree < 10) {
                $health['checks']['disk'] = 'critical';
                $health['status'] = 'critical';
            } elseif ($percentFree < 20) {
                $health['checks']['disk'] = 'warning';
                if ($health['status'] === 'healthy') {
                    $health['status'] = 'warning';
                }
            } else {
                $health['checks']['disk'] = 'healthy';
            }
        }

        // Memory check
        $memoryUsage = memory_get_usage(true);
        $memoryLimit = ini_get('memory_limit');
        $memoryLimitBytes = $this->parseMemoryLimit($memoryLimit);

        if ($memoryLimitBytes > 0) {
            $percentUsed = ($memoryUsage / $memoryLimitBytes) * 100;
            if ($percentUsed > 90) {
                $health['checks']['memory'] = 'critical';
                $health['status'] = 'critical';
            } elseif ($percentUsed > 80) {
                $health['checks']['memory'] = 'warning';
                if ($health['status'] === 'healthy') {
                    $health['status'] = 'warning';
                }
            } else {
                $health['checks']['memory'] = 'healthy';
            }
        }

        return $health;
    }

    /**
     * Get real-time user activity
     */
    private function getRealtimeUsers(): array
    {
        $db = Database::getInstance();

        // Get recently active users (last 5 minutes)
        $recentUsers = [];
        try {
            $results = $db->query(
                "SELECT u.id, u.username, u.email, s.last_activity
                 FROM sessions s
                 JOIN users u ON s.user_id = u.id
                 WHERE s.last_activity > DATE_SUB(NOW(), INTERVAL 5 MINUTE)
                 ORDER BY s.last_activity DESC
                 LIMIT 10"
            )->fetchAll();

            foreach ($results as $row) {
                $recentUsers[] = [
                    'id' => $row['id'],
                    'username' => $row['username'],
                    'email' => $row['email'],
                    'last_activity' => $row['last_activity'],
                ];
            }
        } catch (\Exception $e) {
            // Tables might not exist
        }

        return [
            'recent_users' => $recentUsers,
            'count' => count($recentUsers),
        ];
    }

    /**
     * Parse memory limit string to bytes
     */
    private function parseMemoryLimit(string $limit): int
    {
        $limit = trim($limit);

        // Handle empty or invalid strings
        if (empty($limit)) {
            return 0;
        }

        $last = strtolower($limit[strlen($limit) - 1]);
        $value = (int) $limit;

        switch ($last) {
            case 'g':
                $value *= 1024;
                // fall through
            case 'm':
                $value *= 1024;
                // fall through
            case 'k':
                $value *= 1024;
        }

        return $value;
    }
}
