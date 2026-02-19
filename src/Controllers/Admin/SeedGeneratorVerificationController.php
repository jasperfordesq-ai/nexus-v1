<?php
// Copyright © 2024–2026 Jasper Ford
// SPDX-License-Identifier: AGPL-3.0-or-later
// Author: Jasper Ford
// See NOTICE file for attribution and acknowledgements.

namespace Nexus\Controllers\Admin;

use Nexus\Core\Database;

class SeedGeneratorVerificationController
{
    private $tenantId;

    public function __construct()
    {
        // Security: Admin only
        $this->requireAdmin();
        $this->tenantId = $_SESSION['tenant_id'] ?? 1;
    }

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
     * Show verification dashboard
     */
    public function index()
    {
        // Run all verification checks
        $verification = [
            'timestamp' => date('Y-m-d H:i:s'),
            'database' => $this->verifyDatabaseConnection(),
            'tables' => $this->verifyTableAccess(),
            'data' => $this->verifyDataAccess(),
            'safety' => $this->verifySafetyGuarantees(),
            'controller' => $this->verifyControllerCode(),
        ];

        require __DIR__ . '/../../../views/admin/seed-generator/verification.php';
    }

    /**
     * Verify database connection
     */
    private function verifyDatabaseConnection()
    {
        try {
            $pdo = Database::getInstance();

            // Get database name
            $stmt = Database::query("SELECT DATABASE() as db_name");
            $result = $stmt->fetch(\PDO::FETCH_ASSOC);

            // Get MySQL version
            $versionStmt = Database::query("SELECT VERSION() as version");
            $version = $versionStmt->fetch(\PDO::FETCH_ASSOC);

            return [
                'status' => 'success',
                'database_name' => $result['db_name'],
                'mysql_version' => $version['version'],
                'connection' => 'Active and healthy',
            ];
        } catch (\Exception $e) {
            return [
                'status' => 'error',
                'message' => $e->getMessage(),
            ];
        }
    }

    /**
     * Verify table access - Can we see ALL tables?
     */
    private function verifyTableAccess()
    {
        try {
            // Get all tables
            $stmt = Database::query("SHOW TABLES");
            $tables = $stmt->fetchAll(\PDO::FETCH_COLUMN);

            // Get detailed info for each table
            $tableDetails = [];
            $totalRecords = 0;
            $tablesWithData = 0;

            foreach ($tables as $tableName) {
                try {
                    // Get column count
                    $colStmt = Database::query("SHOW COLUMNS FROM `{$tableName}`");
                    $columns = $colStmt->fetchAll(\PDO::FETCH_ASSOC);
                    $columnCount = count($columns);

                    // Get row count
                    $countStmt = Database::query("SELECT COUNT(*) as count FROM `{$tableName}`");
                    $countResult = $countStmt->fetch(\PDO::FETCH_ASSOC);
                    $rowCount = $countResult['count'];

                    // Check for tenant_id column
                    $hasTenantId = false;
                    foreach ($columns as $col) {
                        if ($col['Field'] === 'tenant_id') {
                            $hasTenantId = true;
                            break;
                        }
                    }

                    $totalRecords += $rowCount;
                    if ($rowCount > 0) {
                        $tablesWithData++;
                    }

                    $tableDetails[] = [
                        'name' => $tableName,
                        'columns' => $columnCount,
                        'rows' => $rowCount,
                        'has_tenant_id' => $hasTenantId,
                        'readable' => true,
                    ];
                } catch (\Exception $e) {
                    $tableDetails[] = [
                        'name' => $tableName,
                        'columns' => 0,
                        'rows' => 0,
                        'has_tenant_id' => false,
                        'readable' => false,
                        'error' => $e->getMessage(),
                    ];
                }
            }

            return [
                'status' => 'success',
                'total_tables' => count($tables),
                'tables_with_data' => $tablesWithData,
                'total_records' => $totalRecords,
                'tables' => $tableDetails,
                'all_readable' => !in_array(false, array_column($tableDetails, 'readable')),
            ];
        } catch (\Exception $e) {
            return [
                'status' => 'error',
                'message' => $e->getMessage(),
            ];
        }
    }

    /**
     * Verify data access - Can we read actual data?
     */
    private function verifyDataAccess()
    {
        $criticalTables = ['users', 'groups', 'feed_posts', 'events', 'listings', 'transactions'];
        $results = [];

        foreach ($criticalTables as $table) {
            try {
                // Check if table has tenant_id
                $colStmt = Database::query("SHOW COLUMNS FROM `{$table}`");
                $columns = $colStmt->fetchAll(\PDO::FETCH_ASSOC);
                $hasTenantId = false;
                foreach ($columns as $col) {
                    if ($col['Field'] === 'tenant_id') {
                        $hasTenantId = true;
                        break;
                    }
                }

                // Try to read data
                if ($hasTenantId) {
                    $stmt = Database::query("SELECT * FROM `{$table}` WHERE tenant_id = ? LIMIT 1", [$this->tenantId]);
                } else {
                    $stmt = Database::query("SELECT * FROM `{$table}` LIMIT 1");
                }

                $row = $stmt->fetch(\PDO::FETCH_ASSOC);

                $results[$table] = [
                    'status' => 'success',
                    'readable' => true,
                    'has_data' => $row !== false,
                    'column_count' => $row ? count($row) : 0,
                    'sample_columns' => $row ? array_keys($row) : [],
                ];
            } catch (\Exception $e) {
                $results[$table] = [
                    'status' => 'error',
                    'readable' => false,
                    'message' => $e->getMessage(),
                ];
            }
        }

        return $results;
    }

    /**
     * Verify safety guarantees - Prove generator cannot modify data
     */
    private function verifySafetyGuarantees()
    {
        $controllerFile = __DIR__ . '/SeedGeneratorController.php';
        $controllerCode = file_get_contents($controllerFile);

        // Search for dangerous operations in controller
        $dangerousOperations = [
            'INSERT' => substr_count($controllerCode, 'INSERT INTO'),
            'UPDATE' => substr_count($controllerCode, 'UPDATE '),
            'DELETE' => substr_count($controllerCode, 'DELETE FROM'),
            'DROP' => substr_count($controllerCode, 'DROP TABLE'),
            'TRUNCATE' => substr_count($controllerCode, 'TRUNCATE TABLE'),
        ];

        // Search for read-only operations
        $readOperations = [
            'SELECT' => substr_count($controllerCode, 'SELECT'),
            'SHOW' => substr_count($controllerCode, 'SHOW'),
        ];

        return [
            'controller_file' => $controllerFile,
            'file_exists' => file_exists($controllerFile),
            'file_size' => filesize($controllerFile),
            'dangerous_operations' => $dangerousOperations,
            'read_operations' => $readOperations,
            'is_read_only' => array_sum($dangerousOperations) === 0,
        ];
    }

    /**
     * Verify controller code integrity
     */
    private function verifyControllerCode()
    {
        $controllerFile = __DIR__ . '/SeedGeneratorController.php';

        if (!file_exists($controllerFile)) {
            return [
                'status' => 'error',
                'message' => 'Controller file not found',
            ];
        }

        $code = file_get_contents($controllerFile);

        // Get all method names
        preg_match_all('/public function (\w+)\(/', $code, $publicMethods);
        preg_match_all('/private function (\w+)\(/', $code, $privateMethods);

        // Count lines
        $lines = explode("\n", $code);

        return [
            'status' => 'success',
            'file_path' => $controllerFile,
            'line_count' => count($lines),
            'public_methods' => $publicMethods[1],
            'private_methods' => $privateMethods[1],
            'total_methods' => count($publicMethods[1]) + count($privateMethods[1]),
            'namespace' => 'Nexus\Controllers\Admin',
        ];
    }

    /**
     * Run live test - Generate script and verify it's safe
     */
    public function runLiveTest()
    {
        try {
            // Import the controller
            require_once __DIR__ . '/SeedGeneratorController.php';
            $generator = new SeedGeneratorController();

            // Generate a test script
            ob_start();
            $_GET['type'] = 'production';
            $generator->preview();
            $generatedScript = ob_get_clean();

            // Analyze generated script
            $scriptAnalysis = [
                'length' => strlen($generatedScript),
                'lines' => substr_count($generatedScript, "\n"),
                'contains_insert' => substr_count($generatedScript, 'INSERT INTO'),
                'contains_delete' => substr_count($generatedScript, 'DELETE FROM'),
                'contains_update' => substr_count($generatedScript, 'UPDATE '),
                'contains_drop' => substr_count($generatedScript, 'DROP TABLE'),
                'contains_truncate' => substr_count($generatedScript, 'TRUNCATE TABLE'),
                'contains_confirmation' => strpos($generatedScript, 'Continue? (y/n)') !== false,
                'contains_admin_email' => strpos($generatedScript, 'jasper.ford.esq@gmail.com') !== false,
            ];

            header('Content-Type: application/json');
            echo json_encode([
                'status' => 'success',
                'test_run_at' => date('Y-m-d H:i:s'),
                'script_analysis' => $scriptAnalysis,
                'is_safe' => ($scriptAnalysis['contains_delete'] === 0 &&
                             $scriptAnalysis['contains_update'] === 0 &&
                             $scriptAnalysis['contains_drop'] === 0 &&
                             $scriptAnalysis['contains_truncate'] === 0),
            ], JSON_PRETTY_PRINT);

        } catch (\Exception $e) {
            header('Content-Type: application/json');
            echo json_encode([
                'status' => 'error',
                'message' => $e->getMessage(),
            ], JSON_PRETTY_PRINT);
        }
    }
}
