<?php

declare(strict_types=1);

namespace Nexus\Controllers\Admin\Enterprise;

use Nexus\Core\Database;
use Nexus\Core\TenantContext;
use Nexus\Services\Enterprise\LoggerService;

/**
 * Base Enterprise Controller
 *
 * Provides shared functionality for all enterprise admin controllers.
 */
abstract class BaseEnterpriseController
{
    protected LoggerService $logger;

    public function __construct()
    {
        $this->requireAdmin();
        $this->logger = LoggerService::getInstance('admin');
    }

    protected function requireAdmin(): void
    {
        $isAdmin = isset($_SESSION['user_role']) && in_array($_SESSION['user_role'], ['admin', 'super_admin', 'tenant_admin']);
        $isSuperAdmin = !empty($_SESSION['is_super_admin']);

        if (!$isAdmin && !$isSuperAdmin) {
            header('Location: ' . TenantContext::getBasePath() . '/login');
            exit;
        }
    }

    protected function getJsonInput(): array
    {
        $input = file_get_contents('php://input');
        return json_decode($input, true) ?: [];
    }

    protected function getCurrentUserId(): int
    {
        return (int) ($_SESSION['user_id'] ?? 0);
    }

    protected function getTenantId(): int
    {
        return (int) ($_SESSION['tenant_id'] ?? 1);
    }

    protected function wantsJson(): bool
    {
        $accept = $_SERVER['HTTP_ACCEPT'] ?? '';
        return strpos($accept, 'application/json') !== false;
    }

    protected function jsonResponse(array $data, int $status = 200): void
    {
        header('Content-Type: application/json');
        http_response_code($status);
        echo json_encode($data);
    }

    protected function formatFileSize(int $bytes): string
    {
        if ($bytes < 1024) return $bytes . ' B';
        if ($bytes < 1048576) return round($bytes / 1024, 1) . ' KB';
        return round($bytes / 1048576, 1) . ' MB';
    }

    /**
     * Get system status information
     */
    protected function getSystemStatus(): array
    {
        $status = [
            'php_version' => PHP_VERSION,
            'memory_usage' => round(memory_get_usage(true) / 1048576, 2) . ' MB',
            'memory_limit' => ini_get('memory_limit'),
            'max_execution_time' => ini_get('max_execution_time'),
            'opcache_enabled' => extension_loaded('Zend OPcache') && ini_get('opcache.enable'),
            'loaded_extensions' => get_loaded_extensions(),
        ];

        // Get server stats on Linux
        if (PHP_OS_FAMILY === 'Linux') {
            $status = array_merge($status, $this->getLinuxServerStats());
        }

        // Disk space (works on both Linux and Windows)
        $diskPath = $_SERVER['DOCUMENT_ROOT'] ?? dirname(__DIR__, 4);
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
     * Get Linux-specific server statistics
     */
    private function getLinuxServerStats(): array
    {
        $stats = [];

        // Server memory (RAM)
        $memInfo = @file_get_contents('/proc/meminfo');
        if ($memInfo) {
            preg_match('/MemTotal:\s+(\d+)/', $memInfo, $totalMatch);
            preg_match('/MemAvailable:\s+(\d+)/', $memInfo, $availMatch);
            if (!empty($totalMatch[1]) && !empty($availMatch[1])) {
                $totalMB = round($totalMatch[1] / 1024);
                $availMB = round($availMatch[1] / 1024);
                $usedMB = $totalMB - $availMB;
                $usedPercent = round(($usedMB / $totalMB) * 100, 1);
                $stats['server_memory'] = [
                    'total' => $totalMB,
                    'used' => $usedMB,
                    'available' => $availMB,
                    'percent' => $usedPercent,
                    'display' => "{$usedMB} MB / {$totalMB} MB ({$usedPercent}%)"
                ];
            }
        }

        // CPU load average
        $loadAvg = @file_get_contents('/proc/loadavg');
        if ($loadAvg) {
            $parts = explode(' ', $loadAvg);
            $stats['load_average'] = [
                '1min' => $parts[0] ?? 'N/A',
                '5min' => $parts[1] ?? 'N/A',
                '15min' => $parts[2] ?? 'N/A',
                'display' => trim("{$parts[0]} {$parts[1]} {$parts[2]}")
            ];
        }

        // Uptime
        $uptime = @file_get_contents('/proc/uptime');
        if ($uptime) {
            $seconds = (int) explode(' ', $uptime)[0];
            $days = floor($seconds / 86400);
            $hours = floor(($seconds % 86400) / 3600);
            $mins = floor(($seconds % 3600) / 60);
            $stats['uptime'] = [
                'seconds' => $seconds,
                'display' => "{$days}d {$hours}h {$mins}m"
            ];
        }

        // CPU cores
        $cpuInfo = @file_get_contents('/proc/cpuinfo');
        if ($cpuInfo) {
            $cores = preg_match_all('/^processor/m', $cpuInfo);
            $stats['cpu_cores'] = $cores ?: 1;
        }

        return $stats;
    }

    /**
     * Parse INI size string to bytes
     */
    protected function parseIniSize($size): int
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
}
