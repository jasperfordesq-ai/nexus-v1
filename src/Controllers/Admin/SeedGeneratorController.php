<?php

namespace Nexus\Controllers\Admin;

use Nexus\Core\Database;
use Nexus\Core\View;

class SeedGeneratorController
{
    private $tenantId;
    private $userId;
    private $seedAdminEmail;
    private $seedAdminPassword;

    public function __construct()
    {
        // Security: Admin only
        $this->requireAdmin();

        $this->tenantId = $_SESSION['tenant_id'] ?? 1;
        $this->userId = $_SESSION['user_id'] ?? null;

        // SECURITY: Get admin credentials from environment or generate secure defaults
        $this->seedAdminEmail = getenv('SEED_ADMIN_EMAIL') ?: 'admin@nexus.local';
        $this->seedAdminPassword = getenv('SEED_ADMIN_PASSWORD') ?: $this->generateSecurePassword();
    }

    /**
     * Generate a cryptographically secure random password
     */
    private function generateSecurePassword(): string
    {
        // Generate a 24-character password with mixed case, numbers, and symbols
        $chars = 'abcdefghijklmnopqrstuvwxyzABCDEFGHIJKLMNOPQRSTUVWXYZ0123456789!@#$%^&*';
        $password = '';
        $max = strlen($chars) - 1;
        for ($i = 0; $i < 24; $i++) {
            $password .= $chars[random_int(0, $max)];
        }
        return $password;
    }

    /**
     * Require admin access
     */
    private function requireAdmin()
    {
        $isAdmin = isset($_SESSION['user_role']) && in_array($_SESSION['user_role'], ['admin', 'super_admin', 'tenant_admin']);

        if (!$isAdmin) {
            http_response_code(403);
            echo "Access denied. Admin privileges required.";
            exit;
        }
    }

    /**
     * Show the seed generator interface
     */
    public function index()
    {
        // Get database statistics
        $stats = $this->getDatabaseStats();

        // Get table information
        $tables = $this->getTableInfo();

        return View::render('admin/seed-generator/index', [
            'title' => 'Database Seed Generator',
            'stats' => $stats,
            'tables' => $tables,
        ]);
    }

    /**
     * Generate production seeding script
     */
    public function generateProduction()
    {
        $this->generateScript('production');
    }

    /**
     * Generate demo seeding script
     */
    public function generateDemo()
    {
        $this->generateScript('demo');
    }

    /**
     * Preview generated script
     */
    public function preview()
    {
        $type = $_GET['type'] ?? 'production';
        $format = $_GET['format'] ?? 'html';

        // If format is 'html', show the preview page
        if ($format === 'html') {
            // Get database statistics for the preview page
            $stats = $this->getDatabaseStats();

            // Render preview page
            require __DIR__ . '/../../../views/admin-legacy/seed-generator/preview.php';
            return;
        }

        // If format is 'tables-only', show list of tables
        if ($format === 'tables-only') {
            $this->showTablesList($type);
            return;
        }

        // If format is 'code-only', show generated code
        if ($format === 'code-only') {
            $script = $this->buildScript($type);
            header('Content-Type: text/plain; charset=utf-8');
            echo '<pre style="color: #e2e8f0; font-family: \'Courier New\', monospace; font-size: 13px; line-height: 1.6; margin: 0; padding: 0;">';
            echo htmlspecialchars($script);
            echo '</pre>';
            return;
        }

        // Default: show the script
        $script = $this->buildScript($type);
        header('Content-Type: text/plain; charset=utf-8');
        echo $script;
    }

    /**
     * Show list of all database tables for preview
     */
    private function showTablesList($type)
    {
        $tables = $this->getAllTables();

        echo '<!DOCTYPE html>';
        echo '<html><head><meta charset="UTF-8">';
        echo '<style>';
        echo 'body { font-family: "Segoe UI", Tahoma, sans-serif; margin: 20px; background: #1e293b; color: #e2e8f0; }';
        echo '.table-list { display: grid; grid-template-columns: repeat(auto-fill, minmax(200px, 1fr)); gap: 12px; }';
        echo '.table-item { background: #334155; padding: 12px; border-radius: 8px; border-left: 3px solid #10b981; }';
        echo '.table-name { font-weight: 600; color: #10b981; margin-bottom: 4px; }';
        echo '.table-count { font-size: 12px; color: #94a3b8; }';
        echo '.section-header { color: #f1f5f9; margin: 24px 0 16px 0; padding-bottom: 8px; border-bottom: 2px solid #475569; }';
        echo '.success-badge { background: #d1fae5; color: #065f46; padding: 4px 12px; border-radius: 12px; font-size: 12px; font-weight: 600; }';
        echo '</style></head><body>';

        echo '<h2 class="section-header">✓ Generator Can See ALL ' . count($tables) . ' Database Tables</h2>';
        echo '<div style="background: #0f766e; padding: 16px; border-radius: 8px; margin-bottom: 24px;">';
        echo '<strong style="color: #ccfbf1;">FULL DATABASE ACCESS CONFIRMED</strong><br>';
        echo '<span style="color: #99f6e4; font-size: 14px;">The generator has complete read access to your entire database structure.</span>';
        echo '</div>';

        echo '<div class="table-list">';
        foreach ($tables as $table) {
            $tableName = $table['Tables_in_' . getenv('DB_NAME')];

            // Get row count
            try {
                $stmt = Database::query("SELECT COUNT(*) as count FROM `{$tableName}`");
                $count = $stmt->fetch(\PDO::FETCH_ASSOC)['count'];
            } catch (\Exception $e) {
                $count = 'N/A';
            }

            echo '<div class="table-item">';
            echo '<div class="table-name">' . htmlspecialchars($tableName) . '</div>';
            echo '<div class="table-count">' . number_format($count) . ' records</div>';
            echo '</div>';
        }
        echo '</div>';

        echo '<div style="margin-top: 32px; padding: 16px; background: #0f766e; border-radius: 8px;">';
        echo '<h3 style="color: #ccfbf1; margin: 0 0 8px 0;">What This Means:</h3>';
        echo '<ul style="color: #99f6e4; margin: 0; padding-left: 20px;">';
        echo '<li>Generator can analyze all ' . count($tables) . ' tables</li>';
        echo '<li>Complete visibility into your database structure</li>';
        echo '<li>Will include all relevant data in ' . ($type === 'production' ? 'production' : 'demo') . ' script</li>';
        echo '<li>No tables are hidden or inaccessible</li>';
        echo '</ul>';
        echo '</div>';

        echo '</body></html>';
    }

    /**
     * Download generated script
     */
    public function download()
    {
        $type = $_GET['type'] ?? 'production';
        $format = $_GET['format'] ?? 'php'; // 'php' or 'sql'

        if ($format === 'sql') {
            $this->downloadSQL($type);
        } else {
            $this->downloadPHP($type);
        }
    }

    /**
     * Download as PHP script
     */
    private function downloadPHP($type)
    {
        $script = $this->buildScript($type);
        $filename = 'seed_' . $type . '_' . date('Y_m_d_His') . '.php';

        header('Content-Type: text/plain; charset=utf-8');
        header('Content-Disposition: attachment; filename="' . $filename . '"');
        header('Content-Length: ' . strlen($script));

        echo $script;
        exit;
    }

    /**
     * Download as SQL file
     */
    private function downloadSQL($type)
    {
        $sql = $this->buildSQLScript($type);
        $filename = 'seed_' . $type . '_' . date('Y_m_d_His') . '.sql';

        header('Content-Type: text/plain; charset=utf-8');
        header('Content-Disposition: attachment; filename="' . $filename . '"');
        header('Content-Length: ' . strlen($sql));

        echo $sql;
        exit;
    }

    /**
     * Build SQL script instead of PHP
     */
    private function buildSQLScript($type)
    {
        $isDemoMode = ($type === 'demo');

        $sql = "-- ============================================================================\n";
        $sql .= "-- Nexus Database Seeder - " . ucfirst($type) . " Environment\n";
        $sql .= "-- Auto-generated on " . date('F j, Y \a\t g:i A') . "\n";
        $sql .= "-- Generated by: Nexus Seed Generator v1.0\n";
        $sql .= "-- ============================================================================\n\n";

        $sql .= "-- IMPORTANT: Review this script before running!\n";
        $sql .= "-- This script will INSERT data into your database.\n\n";

        $sql .= "SET FOREIGN_KEY_CHECKS=0;\n";
        $sql .= "SET SQL_MODE = \"NO_AUTO_VALUE_ON_ZERO\";\n";
        $sql .= "START TRANSACTION;\n\n";

        // Get tables in priority order
        $tables = $this->getPriorityTables();

        foreach ($tables as $tableName) {
            $sql .= $this->generateTableSQL($tableName, $isDemoMode);
        }

        $sql .= "\n-- ============================================================================\n";
        $sql .= "-- Finalize\n";
        $sql .= "-- ============================================================================\n\n";
        $sql .= "COMMIT;\n";
        $sql .= "SET FOREIGN_KEY_CHECKS=1;\n\n";

        $sql .= "-- ============================================================================\n";
        $sql .= "-- SEEDING COMPLETED!\n";
        $sql .= "-- ============================================================================\n";

        if (!$isDemoMode) {
            $sql .= "\n-- Super Admin Login:\n";
            $sql .= "-- Email: {$this->seedAdminEmail}\n";
            $sql .= "-- Password: [SET VIA SEED_ADMIN_PASSWORD ENV VAR]\n";
        }

        return $sql;
    }

    /**
     * Generate SQL INSERT statements for a table
     */
    private function generateTableSQL($tableName, $isDemoMode)
    {
        // Skip certain tables
        $skipTables = ['migrations', 'sessions', 'password_resets', 'personal_access_tokens'];
        if (in_array($tableName, $skipTables)) {
            return '';
        }

        // Check if table has tenant_id column (with error handling)
        try {
            $columns = $this->getTableColumns($tableName);
        } catch (\Exception $e) {
            return "\n-- Table '{$tableName}' does not exist or is not accessible, skipping...\n";
        }

        $hasTenantId = false;
        foreach ($columns as $col) {
            if ($col['Field'] === 'tenant_id') {
                $hasTenantId = true;
                break;
            }
        }

        // Special handling for users table
        if ($tableName === 'users') {
            return $this->generateUserSQL($isDemoMode);
        }

        // Get table data (with error handling)
        try {
            if ($hasTenantId) {
                $stmt = Database::query("SELECT * FROM `{$tableName}` WHERE tenant_id = ?", [$this->tenantId]);
            } else {
                $stmt = Database::query("SELECT * FROM `{$tableName}`");
            }
            $rows = $stmt->fetchAll(\PDO::FETCH_ASSOC);
        } catch (\Exception $e) {
            return "\n-- Error reading table '{$tableName}': " . $e->getMessage() . ", skipping...\n";
        }

        if (empty($rows)) {
            return "\n-- Table '{$tableName}' is empty, skipping...\n";
        }

        // Production mode: skip most data tables
        if (!$isDemoMode && $tableName !== 'tenants') {
            return "\n-- Production mode: Skipping demo data for {$tableName}\n";
        }

        $sql = "\n-- ============================================================================\n";
        $sql .= "-- Seeding: {$tableName} (" . count($rows) . " records)\n";
        $sql .= "-- ============================================================================\n\n";

        $pdo = Database::getInstance();

        foreach ($rows as $row) {
            $columnNames = array_keys($row);
            $values = [];

            foreach ($row as $col => $value) {
                if ($value === null) {
                    $values[] = 'NULL';
                } else {
                    // Use PDO::quote for proper SQL escaping (handles all character sets safely)
                    $values[] = $pdo->quote($value);
                }
            }

            $sql .= "INSERT INTO `{$tableName}` (`" . implode('`, `', $columnNames) . "`) VALUES (" . implode(', ', $values) . ");\n";
        }

        $sql .= "\n";

        return $sql;
    }

    /**
     * Generate SQL for users table
     */
    private function generateUserSQL($isDemoMode)
    {
        $sql = "\n-- ============================================================================\n";
        $sql .= "-- Seeding: users\n";
        $sql .= "-- ============================================================================\n\n";

        // Always create super admin first
        $hashedPassword = password_hash($this->seedAdminPassword, PASSWORD_BCRYPT);
        $sql .= "-- Super Admin Account\n";
        $sql .= "INSERT INTO `users` (`tenant_id`, `email`, `name`, `password`, `role`, `is_verified`, `xp`, `level`, `points`, `created_at`) VALUES\n";
        $sql .= "(1, '{$this->seedAdminEmail}', 'Admin User', '{$hashedPassword}', 'admin', 1, 10000, 20, 10000, NOW());\n\n";

        if ($isDemoMode) {
            $sql .= "-- Demo Users (secure random passwords)\n";
            for ($i = 1; $i <= 5; $i++) {
                $demoPassword = bin2hex(random_bytes(16));
                $hashedDemo = password_hash($demoPassword, PASSWORD_BCRYPT);
                $sql .= "INSERT INTO `users` (`tenant_id`, `email`, `name`, `password`, `role`, `is_verified`, `xp`, `level`, `points`, `created_at`) VALUES\n";
                $sql .= "(1, 'demo.user{$i}@nexus.test', 'Demo User {$i}', '{$hashedDemo}', 'user', 1, " . rand(0, 1000) . ", " . rand(1, 5) . ", " . rand(0, 500) . ", NOW());\n";
            }
            $sql .= "\n";
        } else {
            $sql .= "-- Production mode: Only super admin created\n\n";
        }

        return $sql;
    }

    /**
     * Analyze database and generate seeding script
     */
    private function generateScript($type)
    {
        try {
            $script = $this->buildScript($type);

            // Save to file
            $filename = __DIR__ . '/../../../scripts/generated/seed_' . $type . '_' . date('Y_m_d_His') . '.php';
            $dir = dirname($filename);

            if (!is_dir($dir)) {
                mkdir($dir, 0755, true);
            }

            file_put_contents($filename, $script);

            $_SESSION['flash_success'] = "Seeding script generated successfully! Saved to: scripts/generated/" . basename($filename);

        } catch (\Exception $e) {
            $_SESSION['flash_error'] = "Error generating script: " . $e->getMessage();
        }

        header('Location: /admin-legacy/seed-generator');
        exit;
    }

    /**
     * Build the complete seeding script
     */
    private function buildScript($type)
    {
        $isDemoMode = ($type === 'demo');

        $script = $this->getScriptHeader($type);
        $script .= $this->getScriptBootstrap();

        // Generate seeders for each table
        $tables = $this->getPriorityTables();

        foreach ($tables as $table) {
            $script .= $this->generateTableSeeder($table, $isDemoMode);
        }

        $script .= $this->getScriptFooter();

        return $script;
    }

    /**
     * Get script header with metadata
     */
    private function getScriptHeader($type)
    {
        $date = date('F j, Y');
        $time = date('g:i A');
        $typeLabel = ucfirst($type);

        return <<<PHP
#!/usr/bin/env php
<?php
/**
 * Nexus Database Seeder - {$typeLabel} Environment
 *
 * Auto-generated on {$date} at {$time}
 * Generated by: Nexus Seed Generator v1.0
 *
 * This script regenerates the database structure and data for {$type} environments.
 *


PHP;
    }

    /**
     * Get bootstrap code
     */
    private function getScriptBootstrap()
    {
        $adminEmail = $this->seedAdminEmail;
        $adminPassword = password_hash($this->seedAdminPassword, PASSWORD_BCRYPT);

        return <<<'PHP'
 * IMPORTANT NOTES:
 * - This script uses the CURRENT database state as a template
 * - All passwords are securely hashed with bcrypt
 * - Super admin user will be created automatically
 * - Run this AFTER migrations are complete
 *
 * Usage:
 *   php scripts/generated/seed_{type}_{timestamp}.php
 */

require_once __DIR__ . '/../../bootstrap.php';

use Nexus\Core\Database;

// Color output helpers
function color($text, $color = 'green') {
    $colors = [
        'green' => "\033[32m",
        'red' => "\033[31m",
        'yellow' => "\033[33m",
        'blue' => "\033[34m",
        'cyan' => "\033[36m",
        'reset' => "\033[0m"
    ];
    return $colors[$color] . $text . $colors['reset'];
}

function success($msg) { echo color("✓ ", "green") . $msg . "\n"; }
function error($msg) { echo color("✗ ", "red") . $msg . "\n"; }
function info($msg) { echo color("→ ", "blue") . $msg . "\n"; }
function warn($msg) { echo color("⚠ ", "yellow") . $msg . "\n"; }

// Banner
echo "\n";
echo color("╔════════════════════════════════════════════════════════════╗\n", "cyan");
echo color("║         NEXUS DATABASE SEEDER - PRODUCTION                 ║\n", "cyan");
echo color("╚════════════════════════════════════════════════════════════╝\n", "cyan");
echo "\n";

// Safety check
echo "This will seed the database with production-ready data.\n";
echo "Continue? (y/n): ";
$handle = fopen("php://stdin", "r");
$line = fgets($handle);
if (trim($line) !== 'y') {
    info("Aborted.");
    exit(0);
}
echo "\n";

// Initialize database
try {
    $pdo = Database::getInstance();
    $pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
} catch (Exception $e) {
    error("Database connection failed: " . $e->getMessage());
    exit(1);
}

$startTime = microtime(true);
info("Starting database seeding...\n");

// Track created IDs
$createdIds = [
    'users' => [],
    'groups' => [],
    'posts' => [],
    'events' => [],
];

PHP;
    }

    /**
     * Generate seeder for a specific table
     */
    private function generateTableSeeder($tableName, $isDemoMode)
    {
        // Skip certain tables
        $skipTables = ['migrations', 'sessions', 'password_resets', 'personal_access_tokens'];
        if (in_array($tableName, $skipTables)) {
            return '';
        }

        // Check if table has tenant_id column (with error handling)
        try {
            $columns = $this->getTableColumns($tableName);
        } catch (\Exception $e) {
            // Table doesn't exist or can't be accessed - skip it
            return "\n// Table '{$tableName}' does not exist or is not accessible, skipping...\n";
        }

        $hasTenantId = false;
        foreach ($columns as $col) {
            if ($col['Field'] === 'tenant_id') {
                $hasTenantId = true;
                break;
            }
        }

        // Get table data (with error handling)
        try {
            if ($hasTenantId) {
                $stmt = Database::query("SELECT * FROM `{$tableName}` WHERE tenant_id = ?", [$this->tenantId]);
            } else {
                $stmt = Database::query("SELECT * FROM `{$tableName}`");
            }
            $rows = $stmt->fetchAll(\PDO::FETCH_ASSOC);
        } catch (\Exception $e) {
            // Error reading table - skip it
            return "\n// Error reading table '{$tableName}': " . $e->getMessage() . ", skipping...\n";
        }

        if (empty($rows)) {
            return "\n// Table '{$tableName}' is empty, skipping...\n";
        }

        $script = "\n";
        $script .= "// ============================================================================\n";
        $script .= "// Seeding: {$tableName} (" . count($rows) . " records)\n";
        $script .= "// ============================================================================\n";
        $script .= "info(\"Seeding {$tableName}...\");\n\n";

        // Special handling for users table
        if ($tableName === 'users') {
            $script .= $this->generateUserSeeder($rows, $isDemoMode);
        } else {
            $script .= $this->generateGenericSeeder($tableName, $rows, $columns, $isDemoMode);
        }

        $script .= "success(\"Seeded {$tableName}: \" . count(\$createdIds['{$tableName}'] ?? []) . \" records\");\n";
        $script .= "\n";

        return $script;
    }

    /**
     * Generate user seeder with special handling
     */
    private function generateUserSeeder($rows, $isDemoMode)
    {
        $script = "\$createdIds['users'] = [];\n\n";

        // Always create super admin first
        $script .= "// Create super admin user (YOU)\n";
        $script .= "\$stmt = \$pdo->prepare(\"\n";
        $script .= "    INSERT INTO users (\n";
        $script .= "        tenant_id, email, name, password, role, is_verified, \n";
        $script .= "        xp, level, points, created_at\n";
        $script .= "    ) VALUES (\n";
        $script .= "        :tenant_id, :email, :name, :password, :role, :is_verified,\n";
        $script .= "        :xp, :level, :points, :created_at\n";
        $script .= "    )\n";
        $script .= "\");\n\n";

        $script .= "\$stmt->execute([\n";
        $script .= "    'tenant_id' => 1,\n";
        $script .= "    'email' => getenv('SEED_ADMIN_EMAIL') ?: 'admin@nexus.local',\n";
        $script .= "    'name' => 'Admin User',\n";
        $script .= "    'password' => password_hash(getenv('SEED_ADMIN_PASSWORD') ?: bin2hex(random_bytes(16)), PASSWORD_BCRYPT),\n";
        $script .= "    'role' => 'admin',\n";
        $script .= "    'is_verified' => 1,\n";
        $script .= "    'xp' => 10000,\n";
        $script .= "    'level' => 20,\n";
        $script .= "    'points' => 10000,\n";
        $script .= "    'created_at' => date('Y-m-d H:i:s'),\n";
        $script .= "]);\n";
        $script .= "\$superAdminId = \$pdo->lastInsertId();\n";
        $script .= "\$createdIds['users'][] = \$superAdminId;\n";
        $script .= "success(\"Created super admin: \" . (getenv('SEED_ADMIN_EMAIL') ?: 'admin@nexus.local'));\n\n";

        if ($isDemoMode) {
            // In demo mode, create additional test users with secure passwords
            $script .= "// Create additional demo users\n";
            $script .= "\$demoUsers = [\n";

            // Add a few demo users with hashed passwords
            $demoCount = min(10, count($rows));
            for ($i = 1; $i <= $demoCount; $i++) {
                $securePassword = bin2hex(random_bytes(16));
                $hashedPassword = password_hash($securePassword, PASSWORD_BCRYPT);

                $script .= "    [\n";
                $script .= "        'email' => 'demo.user{$i}@nexus.test',\n";
                $script .= "        'name' => 'Demo User {$i}',\n";
                $script .= "        'password' => '{$hashedPassword}',\n";
                $script .= "        'role' => 'user',\n";
                $script .= "    ],\n";
            }

            $script .= "];\n\n";

            $script .= "foreach (\$demoUsers as \$user) {\n";
            $script .= "    \$stmt = \$pdo->prepare(\"\n";
            $script .= "        INSERT INTO users (tenant_id, email, name, password, role, is_verified, xp, level, points, created_at)\n";
            $script .= "        VALUES (:tenant_id, :email, :name, :password, :role, 1, :xp, :level, :points, :created_at)\n";
            $script .= "    \");\n";
            $script .= "    \$stmt->execute([\n";
            $script .= "        'tenant_id' => 1,\n";
            $script .= "        'email' => \$user['email'],\n";
            $script .= "        'name' => \$user['name'],\n";
            $script .= "        'password' => \$user['password'],\n";
            $script .= "        'role' => \$user['role'],\n";
            $script .= "        'xp' => rand(0, 1000),\n";
            $script .= "        'level' => rand(1, 5),\n";
            $script .= "        'points' => rand(0, 500),\n";
            $script .= "        'created_at' => date('Y-m-d H:i:s'),\n";
            $script .= "    ]);\n";
            $script .= "    \$createdIds['users'][] = \$pdo->lastInsertId();\n";
            $script .= "}\n";
            $script .= "success(\"Created \" . count(\$demoUsers) . \" demo users\");\n\n";
        } else {
            $script .= "// Production mode: Only super admin created\n";
            $script .= "// No test users or weak passwords\n\n";
        }

        return $script;
    }

    /**
     * Generate generic seeder for any table
     */
    private function generateGenericSeeder($tableName, $rows, $columns, $isDemoMode)
    {
        $script = "\$createdIds['{$tableName}'] = [];\n";

        // In production mode, limit data seeding
        if (!$isDemoMode) {
            $script .= "// Production mode: Skipping demo data for {$tableName}\n";
            return $script;
        }

        // Demo mode: seed data
        $script .= "\$data_{$tableName} = [\n";

        foreach ($rows as $row) {
            $script .= "    [\n";
            foreach ($row as $col => $value) {
                // Skip sensitive or auto-generated fields
                if (in_array($col, ['id', 'password', 'remember_token', 'api_token'])) {
                    continue;
                }

                $escapedValue = $this->escapeValue($value);
                $script .= "        '{$col}' => {$escapedValue},\n";
            }
            $script .= "    ],\n";
        }

        $script .= "];\n\n";

        // Generate insert statement
        $columnList = array_filter(array_keys($rows[0]), function($col) {
            return !in_array($col, ['id', 'password', 'remember_token', 'api_token']);
        });

        $script .= "foreach (\$data_{$tableName} as \$row) {\n";
        $script .= "    try {\n";
        $script .= "        \$stmt = \$pdo->prepare(\"\n";
        $script .= "            INSERT INTO {$tableName} (" . implode(', ', $columnList) . ")\n";
        $script .= "            VALUES (:" . implode(', :', $columnList) . ")\n";
        $script .= "        \");\n";
        $script .= "        \$stmt->execute(\$row);\n";
        $script .= "        \$createdIds['{$tableName}'][] = \$pdo->lastInsertId();\n";
        $script .= "    } catch (Exception \$e) {\n";
        $script .= "        warn(\"Could not insert into {$tableName}: \" . \$e->getMessage());\n";
        $script .= "    }\n";
        $script .= "}\n\n";

        return $script;
    }

    /**
     * Escape PHP value for script generation
     */
    private function escapeValue($value)
    {
        if ($value === null) {
            return 'null';
        }

        if (is_numeric($value)) {
            return $value;
        }

        if (is_bool($value)) {
            return $value ? 'true' : 'false';
        }

        // Escape string
        return "'" . addslashes($value) . "'";
    }

    /**
     * Get script footer
     */
    private function getScriptFooter()
    {
        return <<<'PHP'

// ============================================================================
// COMPLETION
// ============================================================================

$endTime = microtime(true);
$duration = round($endTime - $startTime, 2);

echo "\n";
echo color("╔════════════════════════════════════════════════════════════╗\n", "green");
echo color("║                  SEEDING COMPLETED!                        ║\n", "green");
echo color("╚════════════════════════════════════════════════════════════╝\n", "green");
echo "\n";
success("Database seeded successfully in {$duration} seconds");
echo "\n";
info("Summary:");
foreach ($createdIds as $table => $ids) {
    echo "  " . ucfirst($table) . ": " . color(count($ids), 'green') . " records\n";
}
echo "\n";
info("Super Admin Login:");
echo "  Email: " . color(getenv('SEED_ADMIN_EMAIL') ?: 'admin@nexus.local', "cyan") . "\n";
echo "  Password: " . color("[SET VIA SEED_ADMIN_PASSWORD ENV VAR]", "cyan") . "\n";
echo "\n";

PHP;
    }

    /**
     * Get database statistics
     */
    private function getDatabaseStats()
    {
        $stats = [];

        // Get table counts
        $tables = ['users', 'groups', 'feed_posts', 'events', 'listings', 'transactions', 'user_badges'];

        foreach ($tables as $table) {
            try {
                $stmt = Database::query("SELECT COUNT(*) as count FROM `{$table}` WHERE tenant_id = ?", [$this->tenantId]);
                $result = $stmt->fetch(\PDO::FETCH_ASSOC);
                $stats[$table] = $result['count'];
            } catch (\Exception $e) {
                $stats[$table] = 0;
            }
        }

        // Get total tables
        $stmt = Database::query("SHOW TABLES");
        $allTables = $stmt->fetchAll(\PDO::FETCH_COLUMN);
        $stats['total_tables'] = count($allTables);

        return $stats;
    }

    /**
     * Get table information
     */
    private function getTableInfo()
    {
        $stmt = Database::query("SHOW TABLES");
        $tables = $stmt->fetchAll(\PDO::FETCH_COLUMN);

        $tableInfo = [];
        foreach ($tables as $table) {
            // Get row count
            try {
                $countStmt = Database::query("SELECT COUNT(*) as count FROM `{$table}`");
                $countResult = $countStmt->fetch(\PDO::FETCH_ASSOC);
                $rowCount = $countResult['count'];
            } catch (\Exception $e) {
                $rowCount = 0;
            }

            // Get size
            $sizeStmt = Database::query("
                SELECT
                    ROUND(((data_length + index_length) / 1024), 2) AS size_kb
                FROM information_schema.TABLES
                WHERE table_schema = DATABASE()
                AND table_name = ?
            ", [$table]);
            $sizeResult = $sizeStmt->fetch(\PDO::FETCH_ASSOC);

            $tableInfo[] = [
                'name' => $table,
                'rows' => $rowCount,
                'size' => $sizeResult['size_kb'] ?? 0,
            ];
        }

        return $tableInfo;
    }

    /**
     * Get columns for a table
     */
    private function getTableColumns($tableName)
    {
        $stmt = Database::query("SHOW COLUMNS FROM `{$tableName}`");
        return $stmt->fetchAll(\PDO::FETCH_ASSOC);
    }

    /**
     * Get all tables in database
     */
    private function getAllTables()
    {
        $stmt = Database::query("SHOW TABLES");
        return $stmt->fetchAll(\PDO::FETCH_ASSOC);
    }

    /**
     * Get tables in priority order (respecting foreign keys)
     */
    private function getPriorityTables()
    {
        // Get all actual tables from database
        $allTables = $this->getAllTables();
        $actualTableNames = array_column($allTables, 'Tables_in_' . getenv('DB_NAME'));

        // Define ideal priority order
        $priorityOrder = [
            'tenants',
            'users',
            'groups',
            'group_members',
            'feed_posts',
            'post_likes',
            'events',
            'event_rsvps',  // Changed from event_rsvp
            'listings',
            'transactions',
            'user_badges',
            'notifications',
            'messages',
            'reviews',
        ];

        // Only return tables that actually exist
        $existingPriorityTables = [];
        foreach ($priorityOrder as $table) {
            if (in_array($table, $actualTableNames)) {
                $existingPriorityTables[] = $table;
            }
        }

        // Add remaining tables that aren't in priority order
        foreach ($actualTableNames as $table) {
            if (!in_array($table, $existingPriorityTables)) {
                $existingPriorityTables[] = $table;
            }
        }

        return $existingPriorityTables;
    }
}
