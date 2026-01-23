#!/usr/bin/env php
<?php
/**
 * SAFE MIGRATION RUNNER with Built-in Protections
 * Prevents data loss with automated backups and dry-run mode
 */

require_once __DIR__ . '/../bootstrap.php';

use Nexus\Core\Database;

// Color output helpers
function success($msg) { echo "\033[32m✓ {$msg}\033[0m\n"; }
function error($msg) { echo "\033[31m✗ {$msg}\033[0m\n"; }
function warn($msg) { echo "\033[33m⚠ {$msg}\033[0m\n"; }
function info($msg) { echo "\033[36m→ {$msg}\033[0m\n"; }

echo "\n";
echo "╔════════════════════════════════════════════════════════════╗\n";
echo "║         SAFE MIGRATION RUNNER v2.0                         ║\n";
echo "║         With Automatic Backup & Safety Checks              ║\n";
echo "╚════════════════════════════════════════════════════════════╝\n\n";

// Parse command line arguments
$dryRun = in_array('--dry-run', $argv);
$skipBackup = in_array('--skip-backup', $argv);
$force = in_array('--force', $argv);
$migrationFile = null;

foreach ($argv as $arg) {
    if (strpos($arg, '--file=') === 0) {
        $migrationFile = substr($arg, 7);
    }
}

if (in_array('--help', $argv)) {
    echo "Usage: php safe_migrate.php [options]\n\n";
    echo "Options:\n";
    echo "  --dry-run           Preview what would happen without executing\n";
    echo "  --file=filename     Run specific migration file\n";
    echo "  --skip-backup       Skip automatic backup (NOT RECOMMENDED)\n";
    echo "  --force             Skip safety confirmations (DANGEROUS)\n";
    echo "  --help              Show this help message\n\n";
    echo "Examples:\n";
    echo "  php safe_migrate.php --dry-run\n";
    echo "  php safe_migrate.php --file=my_migration.sql\n";
    echo "  php safe_migrate.php --dry-run --file=my_migration.sql\n\n";
    exit(0);
}

// Dry run mode banner
if ($dryRun) {
    warn("DRY RUN MODE - No changes will be made to database");
    echo "\n";
}

// Step 1: Safety Checks
info("Step 1: Running safety checks...");

// Check environment
$env = getenv('APP_ENV') ?: 'production';
if ($env === 'production' && !$force) {
    warn("Running on PRODUCTION environment!");
    echo "Are you sure you want to continue? Type 'yes' to proceed: ";
    $handle = fopen("php://stdin", "r");
    $line = trim(fgets($handle));
    if ($line !== 'yes') {
        error("Aborted by user");
        exit(1);
    }
}

// Step 2: Find migration file
if ($migrationFile) {
    $migrationPath = __DIR__ . '/../migrations/' . $migrationFile;
    if (!file_exists($migrationPath)) {
        error("Migration file not found: {$migrationFile}");
        exit(1);
    }
    $migrations = [$migrationPath];
} else {
    // Find latest migration not yet run
    error("Please specify --file=migration_name.sql");
    exit(1);
}

// Step 3: Scan migration for dangerous operations
info("Step 2: Scanning migration for dangerous operations...");

$migrationContent = file_get_contents($migrations[0]);
$dangerousOps = [];

// Check for dangerous operations
$patterns = [
    'DROP TABLE' => 'CRITICAL - Will delete entire table',
    'DROP DATABASE' => 'CRITICAL - Will delete entire database',
    'TRUNCATE' => 'CRITICAL - Will delete all records',
    'DELETE FROM' => 'WARNING - Will delete records',
];

foreach ($patterns as $pattern => $description) {
    if (stripos($migrationContent, $pattern) !== false) {
        // Make sure it's not in a comment
        $lines = explode("\n", $migrationContent);
        foreach ($lines as $lineNum => $line) {
            if (stripos($line, $pattern) !== false && !preg_match('/^\s*--/', $line)) {
                $dangerousOps[] = [
                    'pattern' => $pattern,
                    'line' => $lineNum + 1,
                    'description' => $description,
                    'content' => trim($line),
                ];
            }
        }
    }
}

if (!empty($dangerousOps)) {
    echo "\n";
    warn("⚠️  DANGEROUS OPERATIONS DETECTED:");
    echo "\n";
    foreach ($dangerousOps as $op) {
        error("  [{$op['description']}]");
        echo "  Line {$op['line']}: {$op['content']}\n\n";
    }

    if (!$force) {
        echo "This migration contains operations that could delete data.\n";
        echo "Type 'I UNDERSTAND THE RISK' to proceed: ";
        $handle = fopen("php://stdin", "r");
        $line = trim(fgets($handle));
        if ($line !== 'I UNDERSTAND THE RISK') {
            error("Aborted by user");
            exit(1);
        }
    }
}

// Step 4: Create backup (unless skipped or dry-run)
if (!$skipBackup && !$dryRun) {
    info("Step 3: Creating automatic backup...");

    $backupDir = __DIR__ . '/../backups';
    if (!is_dir($backupDir)) {
        mkdir($backupDir, 0755, true);
    }

    $timestamp = date('Y_m_d_His');
    $backupFile = $backupDir . '/backup_before_migration_' . $timestamp . '.sql';

    $dbName = getenv('DB_NAME');
    $dbUser = getenv('DB_USER');
    $dbPass = getenv('DB_PASS');
    $dbHost = getenv('DB_HOST') ?: 'localhost';

    // Create backup using mysqldump with properly escaped credentials
    $command = sprintf(
        'mysqldump -h %s -u %s -p%s %s > %s 2>&1',
        escapeshellarg($dbHost),
        escapeshellarg($dbUser),
        escapeshellarg($dbPass),
        escapeshellarg($dbName),
        escapeshellarg($backupFile)
    );
    exec($command, $output, $returnCode);

    if ($returnCode === 0 && file_exists($backupFile)) {
        $sizeKB = round(filesize($backupFile) / 1024, 2);
        success("Backup created: {$backupFile} ({$sizeKB} KB)");
    } else {
        error("Failed to create backup!");
        error("Command output: " . implode("\n", $output));

        if (!$force) {
            echo "\nBackup failed. Continue anyway? (NOT RECOMMENDED) Type 'yes': ";
            $handle = fopen("php://stdin", "r");
            $line = trim(fgets($handle));
            if ($line !== 'yes') {
                error("Aborted - backup failed");
                exit(1);
            }
        }
    }
} else if (!$skipBackup && $dryRun) {
    info("Step 3: Skipped (dry-run mode)");
} else {
    warn("Step 3: Skipped (--skip-backup flag)");
}

// Step 5: Execute migration
if ($dryRun) {
    echo "\n";
    info("DRY RUN - Showing what would be executed:");
    echo "\n";
    echo "════════════════════════════════════════════════════════════\n";
    echo $migrationContent;
    echo "\n════════════════════════════════════════════════════════════\n";
    echo "\n";
    success("Dry run complete - no changes made");
    exit(0);
}

info("Step 4: Executing migration...");

try {
    $pdo = Database::getInstance();
    $pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);

    // Execute the migration
    $pdo->exec($migrationContent);

    echo "\n";
    success("Migration executed successfully!");

    // Record migration in migrations table
    try {
        $stmt = $pdo->prepare("
            INSERT INTO migrations (migration, executed_at)
            VALUES (?, NOW())
        ");
        $stmt->execute([basename($migrations[0])]);
        success("Migration recorded in database");
    } catch (Exception $e) {
        warn("Could not record migration (migrations table might not exist)");
    }

} catch (Exception $e) {
    echo "\n";
    error("Migration failed: " . $e->getMessage());
    echo "\n";

    if (!$skipBackup && isset($backupFile) && file_exists($backupFile)) {
        warn("To restore backup, run:");
        echo "  mysql -u {$dbUser} -p {$dbName} < {$backupFile}\n";
    }

    exit(1);
}

echo "\n";
echo "╔════════════════════════════════════════════════════════════╗\n";
echo "║                  MIGRATION COMPLETE                        ║\n";
echo "╚════════════════════════════════════════════════════════════╝\n\n";

if (isset($backupFile)) {
    info("Backup available at: {$backupFile}");
    echo "\n";
}
