<?php

declare(strict_types=1);

namespace Nexus\Controllers\Admin;

use Nexus\Core\View;
use Nexus\Core\Database;
use Nexus\Core\TenantContext;

/**
 * Test Runner Controller
 *
 * Provides admin UI for running and monitoring API tests
 */
class TestRunnerController
{
    public function __construct()
    {
        $this->requireAdmin();
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

    /**
     * Show test runner dashboard
     */
    public function index(): void
    {
        $tenantId = TenantContext::getId();

        // Get recent test runs
        $recentRuns = $this->getRecentTestRuns(10);

        // Get test statistics
        $stats = $this->getTestStatistics();

        // Available test suites
        $suites = [
            'all' => ['name' => 'All Tests', 'description' => 'Run complete test suite', 'icon' => '🧪'],
            'auth' => ['name' => 'Authentication', 'description' => 'Login, session, tokens', 'icon' => '🔐'],
            'core' => ['name' => 'Core API', 'description' => 'Members, listings, messages', 'icon' => '⚙️'],
            'social' => ['name' => 'Social Features', 'description' => 'Likes, comments, feed', 'icon' => '👥'],
            'wallet' => ['name' => 'Wallet/Timebanking', 'description' => 'Transfers, balance', 'icon' => '💰'],
            'gamification' => ['name' => 'Gamification', 'description' => 'Badges, rewards, challenges', 'icon' => '🏆'],
            'ai' => ['name' => 'AI Features', 'description' => 'Chat, content generation', 'icon' => '🤖'],
            'push' => ['name' => 'Push Notifications', 'description' => 'Web push, subscriptions', 'icon' => '🔔'],
            'webauthn' => ['name' => 'WebAuthn', 'description' => 'Passwordless auth', 'icon' => '🔑'],
        ];

        View::render('admin/test-runner/dashboard', [
            'title' => 'API Test Runner',
            'recentRuns' => $recentRuns,
            'stats' => $stats,
            'suites' => $suites,
        ]);
    }

    /**
     * Run tests via AJAX
     */
    public function runTests(): void
    {
        header('Content-Type: application/json');

        if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
            http_response_code(405);
            echo json_encode(['error' => 'Method not allowed']);
            return;
        }

        $suite = $_POST['suite'] ?? 'all';
        $saveResults = isset($_POST['save_results']) && $_POST['save_results'] === 'true';

        // Validate suite
        $validSuites = ['all', 'auth', 'core', 'social', 'wallet', 'gamification', 'ai', 'push', 'webauthn'];
        if (!in_array($suite, $validSuites)) {
            http_response_code(400);
            echo json_encode(['error' => 'Invalid test suite']);
            return;
        }

        // Run tests
        $result = $this->executeTests($suite);

        // Save results if requested
        if ($saveResults && $result['success']) {
            $this->saveTestRun($suite, $result);
        }

        echo json_encode($result);
    }

    /**
     * Execute PHPUnit tests
     */
    private function executeTests(string $suite): array
    {
        $startTime = microtime(true);

        // Build command
        $basePath = dirname(dirname(dirname(__DIR__)));
        $phpunitConfig = $basePath . '/phpunit.xml';

        // Use correct PHPUnit executable based on OS
        if (PHP_OS_FAMILY === 'Windows') {
            $phpunit = '"' . $basePath . '/vendor/bin/phpunit.bat"';
        } else {
            // Find PHP CLI executable (PHP_BINARY may be php-fpm which won't work)
            $phpCli = $this->findPhpCli();

            // Use php to run phpunit directly to avoid permission issues
            $phpunitScript = $basePath . '/vendor/phpunit/phpunit/phpunit';
            if (!file_exists($phpunitScript)) {
                $phpunitScript = $basePath . '/vendor/bin/phpunit';
            }
            $phpunit = $phpCli . ' "' . $phpunitScript . '"';
        }

        // Check phpunit exists (check the script file, not the command)
        $phpunitCheck = PHP_OS_FAMILY === 'Windows'
            ? $basePath . '/vendor/bin/phpunit.bat'
            : $basePath . '/vendor/phpunit/phpunit/phpunit';

        if (!file_exists($phpunitCheck) && !file_exists($basePath . '/vendor/bin/phpunit')) {
            return [
                'success' => false,
                'error' => 'PHPUnit not found. Please run: composer install',
                'duration' => 0,
            ];
        }


        // Build test path
        $testPath = $basePath . '/tests/Controllers/Api/';
        if ($suite !== 'all') {
            $testMap = [
                'auth' => 'AuthControllerTest.php',
                'core' => 'CoreApiControllerTest.php',
                'social' => 'SocialApiControllerTest.php',
                'wallet' => 'WalletApiControllerTest.php',
                'gamification' => 'GamificationApiControllerTest.php',
                'ai' => 'AiApiControllerTest.php',
                'push' => 'PushApiControllerTest.php',
                'webauthn' => 'WebAuthnApiControllerTest.php',
            ];
            $testPath .= $testMap[$suite] ?? '';
        }

        // Execute tests - use config if it exists, otherwise run without it
        if (file_exists($phpunitConfig)) {
            $command = sprintf(
                '%s --configuration "%s" --colors=never --testdox "%s" 2>&1',
                $phpunit,
                $phpunitConfig,
                $testPath
            );
        } else {
            // Run without config file - use bootstrap if available
            $bootstrap = $basePath . '/tests/bootstrap.php';
            if (file_exists($bootstrap)) {
                $command = sprintf(
                    '%s --bootstrap "%s" --colors=never --testdox "%s" 2>&1',
                    $phpunit,
                    $bootstrap,
                    $testPath
                );
            } else {
                $command = sprintf(
                    '%s --colors=never --testdox "%s" 2>&1',
                    $phpunit,
                    $testPath
                );
            }
        }

        $output = [];
        $exitCode = 0;
        exec($command, $output, $exitCode);

        $duration = round(microtime(true) - $startTime, 2);

        // Parse output
        $outputText = implode("\n", $output);
        $result = $this->parseTestOutput($outputText, $exitCode, $duration);
        $result['suite'] = $suite;

        return $result;
    }

    /**
     * Find PHP CLI executable (not php-fpm)
     *
     * Note: Due to open_basedir restrictions, we cannot use file_exists() to check
     * paths outside the web root. We derive the CLI path from PHP_BINARY instead.
     */
    private function findPhpCli(): string
    {
        // Check if PHP_BINARY is already CLI (not fpm/cgi)
        if (PHP_BINARY && strpos(PHP_BINARY, 'fpm') === false && strpos(PHP_BINARY, 'cgi') === false) {
            return PHP_BINARY;
        }

        // Derive CLI path from PHP_BINARY (can't use file_exists due to open_basedir)
        if (PHP_BINARY) {
            // Plesk style: /opt/plesk/php/8.4/sbin/php-fpm -> /opt/plesk/php/8.4/bin/php
            if (preg_match('#^(/opt/plesk/php/[\d.]+)/#', PHP_BINARY, $matches)) {
                return $matches[1] . '/bin/php';
            }

            // Generic: replace sbin/php-fpm with bin/php
            $cliPath = preg_replace('#/sbin/php-fpm.*$#', '/bin/php', PHP_BINARY);
            if ($cliPath !== PHP_BINARY) {
                return $cliPath;
            }

            // Simple replacement: php-fpm -> php in same directory
            $cliFromFpm = str_replace(['php-fpm', 'php-cgi'], 'php', PHP_BINARY);
            if ($cliFromFpm !== PHP_BINARY) {
                return $cliFromFpm;
            }
        }

        // Fallback to common path (Plesk 8.4 is most likely based on error messages)
        return '/opt/plesk/php/8.4/bin/php';
    }

    /**
     * Parse PHPUnit output
     */
    private function parseTestOutput(string $output, int $exitCode, float $duration): array
    {
        $success = $exitCode === 0 || $exitCode === 1; // 1 = warnings only

        // Extract test counts
        preg_match('/Tests:\s*(\d+)/', $output, $testsMatch);
        preg_match('/Assertions:\s*(\d+)/', $output, $assertionsMatch);
        preg_match('/Errors:\s*(\d+)/', $output, $errorsMatch);
        preg_match('/Failures:\s*(\d+)/', $output, $failuresMatch);
        preg_match('/Skipped:\s*(\d+)/', $output, $skippedMatch);

        $tests = isset($testsMatch[1]) ? (int)$testsMatch[1] : 0;
        $assertions = isset($assertionsMatch[1]) ? (int)$assertionsMatch[1] : 0;
        $errors = isset($errorsMatch[1]) ? (int)$errorsMatch[1] : 0;
        $failures = isset($failuresMatch[1]) ? (int)$failuresMatch[1] : 0;
        $skipped = isset($skippedMatch[1]) ? (int)$skippedMatch[1] : 0;

        // Check if actually passed (no errors or failures)
        $actuallyPassed = $errors === 0 && $failures === 0 && $tests > 0;

        return [
            'success' => $actuallyPassed,
            'tests' => $tests,
            'assertions' => $assertions,
            'errors' => $errors,
            'failures' => $failures,
            'skipped' => $skipped,
            'duration' => $duration,
            'output' => $output,
            'exit_code' => $exitCode,
        ];
    }

    /**
     * Save test run to database
     */
    private function saveTestRun(string $suite, array $result): void
    {
        $tenantId = TenantContext::getId();
        $userId = $_SESSION['user_id'] ?? null;

        // Create test_runs table if it doesn't exist
        $this->ensureTestRunsTableExists();

        Database::query(
            "INSERT INTO test_runs (tenant_id, user_id, suite, tests, assertions, errors, failures, skipped, duration, success, output, created_at)
             VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, NOW())",
            [
                $tenantId,
                $userId,
                $suite,
                $result['tests'],
                $result['assertions'],
                $result['errors'],
                $result['failures'],
                $result['skipped'] ?? 0,
                $result['duration'],
                $result['success'] ? 1 : 0,
                $result['output'],
            ]
        );
    }

    /**
     * Ensure test_runs table exists
     */
    private function ensureTestRunsTableExists(): void
    {
        $sql = "CREATE TABLE IF NOT EXISTS test_runs (
            id INT AUTO_INCREMENT PRIMARY KEY,
            tenant_id INT NOT NULL,
            user_id INT NULL,
            suite VARCHAR(50) NOT NULL,
            tests INT NOT NULL DEFAULT 0,
            assertions INT NOT NULL DEFAULT 0,
            errors INT NOT NULL DEFAULT 0,
            failures INT NOT NULL DEFAULT 0,
            skipped INT NOT NULL DEFAULT 0,
            duration DECIMAL(10,2) NOT NULL DEFAULT 0,
            success TINYINT(1) NOT NULL DEFAULT 0,
            output TEXT NULL,
            created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
            INDEX idx_tenant (tenant_id, created_at),
            INDEX idx_suite (suite),
            INDEX idx_success (success)
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci";

        Database::query($sql);
    }

    /**
     * Get recent test runs
     */
    private function getRecentTestRuns(int $limit = 10): array
    {
        $this->ensureTestRunsTableExists();
        $tenantId = TenantContext::getId();

        // Use direct limit value (safe because $limit is type-hinted as int)
        $sql = "SELECT r.*, u.username, u.first_name, u.last_name
                FROM test_runs r
                LEFT JOIN users u ON r.user_id = u.id
                WHERE r.tenant_id = ?
                ORDER BY r.created_at DESC
                LIMIT " . (int)$limit;

        $stmt = Database::query($sql, [$tenantId]);
        return $stmt->fetchAll() ?: [];
    }

    /**
     * Get test statistics
     */
    private function getTestStatistics(): array
    {
        $this->ensureTestRunsTableExists();
        $tenantId = TenantContext::getId();

        $sql = "SELECT
                    COUNT(*) as total_runs,
                    SUM(success) as successful_runs,
                    SUM(tests) as total_tests,
                    SUM(assertions) as total_assertions,
                    AVG(duration) as avg_duration,
                    MAX(created_at) as last_run
                FROM test_runs
                WHERE tenant_id = ?";

        $stmt = Database::query($sql, [$tenantId]);
        $stats = $stmt->fetch();

        if (!$stats || $stats['total_runs'] == 0) {
            return [
                'total_runs' => 0,
                'successful_runs' => 0,
                'success_rate' => 0,
                'total_tests' => 0,
                'total_assertions' => 0,
                'avg_duration' => 0,
                'last_run' => null,
            ];
        }

        return [
            'total_runs' => (int)$stats['total_runs'],
            'successful_runs' => (int)$stats['successful_runs'],
            'success_rate' => $stats['total_runs'] > 0
                ? round(($stats['successful_runs'] / $stats['total_runs']) * 100, 1)
                : 0,
            'total_tests' => (int)$stats['total_tests'],
            'total_assertions' => (int)$stats['total_assertions'],
            'avg_duration' => round((float)$stats['avg_duration'], 2),
            'last_run' => $stats['last_run'],
        ];
    }

    /**
     * View test run details
     */
    public function viewRun(): void
    {
        $runId = $_GET['id'] ?? null;
        if (!$runId) {
            header('Location: ' . TenantContext::getBasePath() . '/admin-legacy/tests');
            return;
        }

        $tenantId = TenantContext::getId();

        $sql = "SELECT r.*, u.username, u.first_name, u.last_name
                FROM test_runs r
                LEFT JOIN users u ON r.user_id = u.id
                WHERE r.id = ? AND r.tenant_id = ?";

        $stmt = Database::query($sql, [$runId, $tenantId]);
        $run = $stmt->fetch();

        if (!$run) {
            header('Location: ' . TenantContext::getBasePath() . '/admin-legacy/tests');
            return;
        }

        View::render('admin/test-runner/view', [
            'title' => 'Test Run Details',
            'run' => $run,
        ]);
    }
}
