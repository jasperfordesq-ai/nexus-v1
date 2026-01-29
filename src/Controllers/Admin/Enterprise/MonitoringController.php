<?php

declare(strict_types=1);

namespace Nexus\Controllers\Admin\Enterprise;

use Nexus\Core\View;
use Nexus\Core\Database;
use Nexus\Services\Enterprise\ConfigService;

/**
 * Monitoring Controller
 *
 * Handles system monitoring, health checks, and log viewing.
 */
class MonitoringController extends BaseEnterpriseController
{
    private ConfigService $configService;

    public function __construct()
    {
        parent::__construct();
        $this->configService = ConfigService::getInstance();
    }

    /**
     * GET /admin/enterprise/monitoring
     * System monitoring dashboard
     */
    public function dashboard(): void
    {
        $status = $this->getSystemStatus();

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
     * Health check endpoint
     */
    public function healthCheck(): void
    {
        $isAjax = !empty($_SERVER['HTTP_X_REQUESTED_WITH']) &&
            strtolower($_SERVER['HTTP_X_REQUESTED_WITH']) === 'xmlhttprequest';
        $wantsJson = isset($_SERVER['HTTP_ACCEPT']) &&
            strpos($_SERVER['HTTP_ACCEPT'], 'application/json') !== false;

        if (!$isAjax && !$wantsJson) {
            View::render('admin/enterprise/monitoring/health');
            return;
        }

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

        // Redis check
        try {
            if (class_exists('Redis')) {
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

        // Disk space
        $diskPath = $_SERVER['DOCUMENT_ROOT'] ?? dirname(__DIR__, 4);
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
        $logPath = getenv('LOG_PATH') ?: dirname(__DIR__, 5) . '/logs';
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
        $logPath = getenv('LOG_PATH') ?: dirname(__DIR__, 5) . '/logs';
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
        $logPath = getenv('LOG_PATH') ?: dirname(__DIR__, 5) . '/logs';
        $filename = $_GET['file'] ?? null;

        if ($filename) {
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

        $logPath = getenv('LOG_PATH') ?: dirname(__DIR__, 5) . '/logs';
        $filePath = $logPath . '/' . basename($filename);

        if (!file_exists($filePath)) {
            http_response_code(404);
            echo json_encode(['error' => 'File not found']);
            return;
        }

        file_put_contents($filePath, '');

        $this->logger->info("Log file cleared", ['filename' => $filename, 'user_id' => $this->getCurrentUserId()]);

        echo json_encode(['success' => true, 'message' => 'Log file cleared']);
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
            'external_services' => $this->checkExternalServices(),
            'ini_settings' => $this->checkPhpIniSettings(),
        ];

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
        ];
    }

    private function checkExtensionRequirements(): array
    {
        $required = ['pdo', 'pdo_mysql', 'curl', 'json', 'mbstring', 'openssl', 'zip', 'gd', 'fileinfo', 'dom', 'session'];
        $extensions = [];
        $status = 'pass';

        foreach ($required as $ext) {
            $loaded = extension_loaded($ext);
            $extensions[] = [
                'name' => $ext,
                'required' => true,
                'loaded' => $loaded,
                'status' => $loaded ? 'pass' : 'fail',
            ];
            if (!$loaded) {
                $status = 'fail';
            }
        }

        return ['status' => $status, 'extensions' => $extensions];
    }

    private function checkExternalServices(): array
    {
        $services = [];
        $status = 'pass';

        // Database
        try {
            $startTime = microtime(true);
            Database::query('SELECT 1');
            $latency = round((microtime(true) - $startTime) * 1000, 2);
            $services[] = ['name' => 'Database (MySQL)', 'status' => 'pass', 'latency_ms' => $latency];
        } catch (\Exception $e) {
            $services[] = ['name' => 'Database (MySQL)', 'status' => 'fail', 'message' => $e->getMessage()];
            $status = 'fail';
        }

        return ['status' => $status, 'services' => $services];
    }

    private function checkPhpIniSettings(): array
    {
        $settings = [];
        $status = 'pass';

        $checks = [
            'memory_limit' => ['min' => '128M', 'recommended' => '256M'],
            'max_execution_time' => ['min' => 30, 'recommended' => 120],
            'upload_max_filesize' => ['min' => '8M', 'recommended' => '64M'],
        ];

        foreach ($checks as $setting => $requirements) {
            $current = ini_get($setting);
            $currentBytes = $this->parseIniSize($current);
            $minBytes = $this->parseIniSize($requirements['min']);

            $meetsMin = $currentBytes >= $minBytes || $currentBytes === -1;
            $settingStatus = $meetsMin ? 'pass' : 'fail';

            $settings[] = [
                'name' => $setting,
                'current' => $current ?: 'not set',
                'minimum' => (string) $requirements['min'],
                'status' => $settingStatus,
            ];

            if (!$meetsMin) {
                $status = 'fail';
            }
        }

        return ['status' => $status, 'settings' => $settings];
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

    /**
     * GET /admin/api/realtime
     * Server-Sent Events endpoint (disabled - use polling instead)
     */
    public function realtimeStream(): void
    {
        header('Content-Type: application/json');
        http_response_code(503);
        echo json_encode([
            'error' => 'Real-time streaming disabled',
            'message' => 'SSE endpoint is disabled. Using polling endpoint at /admin/api/realtime/poll',
            'use_polling' => true,
            'polling_endpoint' => '/admin/api/realtime/poll'
        ]);
    }

    /**
     * GET /admin/api/realtime/poll
     * Polling endpoint for real-time dashboard updates
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
            error_log("Sessions table query failed: " . $e->getMessage());
        }

        $activeSessions = 0;
        try {
            $result = $db->query(
                "SELECT COUNT(*) as count FROM sessions WHERE expires_at > NOW()"
            )->fetch();
            $activeSessions = $result['count'] ?? 0;
        } catch (\Exception $e) {
            error_log("Sessions table query failed: " . $e->getMessage());
        }

        $newUsersToday = 0;
        try {
            $result = $db->query(
                "SELECT COUNT(*) as count FROM users WHERE DATE(created_at) = CURDATE()"
            )->fetch();
            $newUsersToday = $result['count'] ?? 0;
        } catch (\Exception $e) {
            error_log("Users query failed: " . $e->getMessage());
        }

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
        $unreadCount = 0;
        $latestNotification = null;

        try {
            $result = Database::query(
                "SELECT COUNT(*) as count FROM notifications
                 WHERE user_id = ? AND is_read = 0 AND deleted_at IS NULL",
                [$userId]
            )->fetch();
            $unreadCount = $result['count'] ?? 0;

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

        $response = ['count' => (int) $unreadCount];

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
        $checkPath = $_SERVER['DOCUMENT_ROOT'] ?? dirname(__DIR__, 3);
        $freeSpace = @disk_free_space($checkPath);
        $totalSpace = @disk_total_space($checkPath);

        if ($freeSpace === false || $totalSpace === false || $totalSpace == 0) {
            $health['checks']['disk'] = 'unknown';
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
