<?php
/**
 * healthcheck.php
 * Simple healthcheck endpoint for monitoring
 *
 * Returns JSON with system status and database connectivity
 * Does not expose sensitive information
 *
 * Usage: php healthcheck.php (CLI)
 * Or expose via web route: GET /healthcheck
 */

// Prevent caching
header('Cache-Control: no-cache, no-store, must-revalidate');
header('Pragma: no-cache');
header('Expires: 0');
header('Content-Type: application/json');

// Calculate uptime (if available)
function getUptime(): ?string
{
    // Linux uptime
    if (file_exists('/proc/uptime')) {
        $uptime = file_get_contents('/proc/uptime');
        $seconds = (int) explode(' ', $uptime)[0];
        $days = floor($seconds / 86400);
        $hours = floor(($seconds % 86400) / 3600);
        $minutes = floor(($seconds % 3600) / 60);
        return "{$days}d {$hours}h {$minutes}m";
    }

    // Windows uptime (approximate via boot time)
    if (PHP_OS_FAMILY === 'Windows') {
        $output = shell_exec('wmic os get lastbootuptime 2>nul');
        if ($output) {
            return 'Windows uptime not calculated';
        }
    }

    return null;
}

// Check database connectivity
function checkDatabase(): array
{
    $result = [
        'status' => 'unknown',
        'latency_ms' => null,
        'error' => null
    ];

    // Try to load database configuration
    $envFile = dirname(__DIR__, 2) . '/.env';
    if (!file_exists($envFile)) {
        $result['status'] = 'error';
        $result['error'] = 'Configuration not found';
        return $result;
    }

    // Parse .env (simple parser)
    $env = [];
    $lines = file($envFile, FILE_IGNORE_NEW_LINES | FILE_SKIP_EMPTY_LINES);
    foreach ($lines as $line) {
        if (strpos($line, '#') === 0) continue;
        if (strpos($line, '=') === false) continue;
        list($key, $value) = explode('=', $line, 2);
        $env[trim($key)] = trim($value, '"\'');
    }

    $host = $env['DB_HOST'] ?? 'localhost';
    $name = $env['DB_NAME'] ?? '';
    $user = $env['DB_USER'] ?? '';
    $pass = $env['DB_PASS'] ?? '';

    if (empty($name) || empty($user)) {
        $result['status'] = 'error';
        $result['error'] = 'Database configuration incomplete';
        return $result;
    }

    try {
        $start = microtime(true);

        $dsn = "mysql:host={$host};dbname={$name};charset=utf8mb4";
        $pdo = new PDO($dsn, $user, $pass, [
            PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
            PDO::ATTR_TIMEOUT => 5
        ]);

        // Simple query to verify connectivity
        $pdo->query('SELECT 1');

        $latency = (microtime(true) - $start) * 1000;

        $result['status'] = 'healthy';
        $result['latency_ms'] = round($latency, 2);
    } catch (PDOException $e) {
        $result['status'] = 'unhealthy';
        // Don't expose full error message (security)
        $result['error'] = 'Database connection failed';
    }

    return $result;
}

// Check Redis connectivity (optional)
function checkRedis(): ?array
{
    if (!extension_loaded('redis')) {
        return null; // Redis extension not installed
    }

    $result = [
        'status' => 'unknown',
        'latency_ms' => null,
        'error' => null
    ];

    try {
        $start = microtime(true);

        $redis = new Redis();
        $redis->connect('127.0.0.1', 6379, 2); // 2 second timeout
        $redis->ping();

        $latency = (microtime(true) - $start) * 1000;

        $result['status'] = 'healthy';
        $result['latency_ms'] = round($latency, 2);
    } catch (Exception $e) {
        $result['status'] = 'unhealthy';
        $result['error'] = 'Redis connection failed';
    }

    return $result;
}

// Check disk space
function checkDiskSpace(): array
{
    $path = dirname(__DIR__, 2); // Application root
    $free = disk_free_space($path);
    $total = disk_total_space($path);

    if ($free === false || $total === false) {
        return [
            'status' => 'unknown',
            'error' => 'Could not determine disk space'
        ];
    }

    $usedPercent = round((($total - $free) / $total) * 100, 1);

    $status = 'healthy';
    if ($usedPercent > 90) {
        $status = 'critical';
    } elseif ($usedPercent > 80) {
        $status = 'warning';
    }

    return [
        'status' => $status,
        'used_percent' => $usedPercent,
        'free_gb' => round($free / 1073741824, 1)
    ];
}

// Check PHP memory
function checkMemory(): array
{
    $memoryLimit = ini_get('memory_limit');
    $memoryUsage = memory_get_usage(true);

    // Parse memory limit to bytes
    $limitBytes = -1;
    if ($memoryLimit !== '-1') {
        $unit = strtoupper(substr($memoryLimit, -1));
        $value = (int) $memoryLimit;
        switch ($unit) {
            case 'G':
                $limitBytes = $value * 1073741824;
                break;
            case 'M':
                $limitBytes = $value * 1048576;
                break;
            case 'K':
                $limitBytes = $value * 1024;
                break;
            default:
                $limitBytes = $value;
        }
    }

    return [
        'used_mb' => round($memoryUsage / 1048576, 1),
        'limit' => $memoryLimit,
        'percent' => $limitBytes > 0 ? round(($memoryUsage / $limitBytes) * 100, 1) : null
    ];
}

// Build response
$checks = [
    'database' => checkDatabase(),
    'disk' => checkDiskSpace(),
];

// Optional: Add Redis check
$redisCheck = checkRedis();
if ($redisCheck !== null) {
    $checks['redis'] = $redisCheck;
}

// Determine overall status
$overallStatus = 'healthy';
foreach ($checks as $check) {
    if (($check['status'] ?? 'unknown') === 'unhealthy' || ($check['status'] ?? 'unknown') === 'critical') {
        $overallStatus = 'unhealthy';
        break;
    }
    if (($check['status'] ?? 'unknown') === 'warning') {
        $overallStatus = 'degraded';
    }
}

// Build response
$response = [
    'status' => $overallStatus,
    'timestamp' => date('c'),
    'checks' => $checks,
    'system' => [
        'php_version' => PHP_VERSION,
        'uptime' => getUptime(),
        'memory' => checkMemory()
    ]
];

// Set HTTP status based on health
$httpStatus = 200;
if ($overallStatus === 'unhealthy') {
    $httpStatus = 503; // Service Unavailable
} elseif ($overallStatus === 'degraded') {
    $httpStatus = 200; // Still OK, but with warnings
}

http_response_code($httpStatus);

// Output
echo json_encode($response, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES);

// For CLI usage
if (php_sapi_name() === 'cli') {
    echo "\n";
    exit($overallStatus === 'healthy' ? 0 : 1);
}
