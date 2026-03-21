#!/usr/bin/env php
<?php
// Copyright © 2024–2026 Jasper Ford
// SPDX-License-Identifier: AGPL-3.0-or-later
// Author: Jasper Ford
// See NOTICE file for attribution and acknowledgements.

/**
 * SAFE MIGRATION RUNNER with Built-in Protections
 * Prevents data loss with automated backups and dry-run mode
 *
 * Modes:
 *   --file=X          Run a single migration file (original behaviour)
 *   --pending         List migrations not yet applied
 *   --run-pending     Run all pending migrations in order
 *   --mark-applied    Mark migration(s) as applied without running SQL
 *                     Use with --file=X or --all
 */

require __DIR__ . '/../vendor/autoload.php';
$app = require_once __DIR__ . '/../bootstrap/app.php';
$app->make(Illuminate\Contracts\Console\Kernel::class)->bootstrap();

use Illuminate\Support\Facades\DB;

// ─── Color output helpers ─────────────────────────────────────────────────────
function success($msg) { echo "\033[32m✓ {$msg}\033[0m\n"; }
function error($msg)   { echo "\033[31m✗ {$msg}\033[0m\n"; }
function warn($msg)    { echo "\033[33m⚠ {$msg}\033[0m\n"; }
function info($msg)    { echo "\033[36m→ {$msg}\033[0m\n"; }

// ─── Helpers ──────────────────────────────────────────────────────────────────

/** Return all .sql files in migrations/ sorted alphabetically. */
function getAllMigrationFiles(): array {
    $dir = __DIR__ . '/../migrations';
    $files = glob($dir . '/*.sql');
    if ($files === false) return [];
    sort($files);
    return $files;
}

/** Return set of already-applied migration names from the migrations table. */
function getAppliedMigrations(\PDO $pdo): array {
    try {
        $rows = $pdo->query("SELECT migration_name FROM migrations WHERE migration_name IS NOT NULL")->fetchAll(\PDO::FETCH_COLUMN);
        return array_flip($rows); // name => index for O(1) lookup
    } catch (\Exception $e) {
        return [];
    }
}

/** Return migration files that have not been applied yet. */
function getPendingMigrations(\PDO $pdo): array {
    $applied = getAppliedMigrations($pdo);
    $pending = [];
    foreach (getAllMigrationFiles() as $path) {
        if (!isset($applied[basename($path)])) {
            $pending[] = $path;
        }
    }
    return $pending;
}

/** Record a migration as applied without running it. */
function markApplied(\PDO $pdo, string $name): void {
    try {
        $stmt = $pdo->prepare(
            "INSERT IGNORE INTO migrations (migration_name, backups, executed_at) VALUES (?, ?, NOW())"
        );
        $stmt->execute([$name, $name]);
    } catch (\Exception $e) {
        warn("Could not record '{$name}': " . $e->getMessage());
    }
}

/**
 * Scan SQL content for dangerous operations.
 * Returns array of findings.
 */
function scanDangerousOps(string $content): array {
    $patterns = [
        'DROP TABLE'    => 'CRITICAL - Will delete entire table',
        'DROP DATABASE' => 'CRITICAL - Will delete entire database',
        'TRUNCATE'      => 'CRITICAL - Will delete all records',
        'DELETE FROM'   => 'WARNING - Will delete records',
    ];
    $found = [];
    foreach ($patterns as $pattern => $description) {
        if (stripos($content, $pattern) !== false) {
            foreach (explode("\n", $content) as $lineNum => $line) {
                if (stripos($line, $pattern) !== false && !preg_match('/^\s*--/', $line)) {
                    $found[] = ['pattern' => $pattern, 'line' => $lineNum + 1,
                                'description' => $description, 'content' => trim($line)];
                }
            }
        }
    }
    return $found;
}

/**
 * Execute a single migration file.
 * Returns true on success, false on failure.
 */
function runMigration(string $migrationPath, bool $dryRun, bool $skipBackup, bool $force): bool {
    $migrationContent = file_get_contents($migrationPath);
    $name = basename($migrationPath);

    info("Migration: {$name}");

    // Scan for dangerous operations
    $dangerousOps = scanDangerousOps($migrationContent);

    if (!empty($dangerousOps)) {
        echo "\n";
        warn("⚠️  DANGEROUS OPERATIONS DETECTED in {$name}:");
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
                return false;
            }
        }
    }

    // Backup (unless dry-run or skipped)
    $backupFile = null;
    if (!$skipBackup && !$dryRun) {
        $backupDir = __DIR__ . '/../backups';
        if (!is_dir($backupDir)) {
            mkdir($backupDir, 0755, true);
        }
        $timestamp = date('Y_m_d_His');
        $backupFile = $backupDir . '/backup_before_' . pathinfo($name, PATHINFO_FILENAME) . '_' . $timestamp . '.sql';

        $dbName = getenv('DB_NAME');
        $dbUser = getenv('DB_USER');
        $dbPass = getenv('DB_PASS');
        $dbHost = getenv('DB_HOST') ?: 'localhost';

        $command = sprintf(
            'mysqldump -h %s -u %s -p%s %s > %s 2>&1',
            escapeshellarg($dbHost), escapeshellarg($dbUser),
            escapeshellarg($dbPass), escapeshellarg($dbName),
            escapeshellarg($backupFile)
        );
        exec($command, $output, $returnCode);

        if ($returnCode === 0 && file_exists($backupFile)) {
            $sizeKB = round(filesize($backupFile) / 1024, 2);
            success("Backup: {$backupFile} ({$sizeKB} KB)");
        } else {
            error("Failed to create backup!");
            if (!$force) {
                echo "Continue without backup? Type 'yes': ";
                $handle = fopen("php://stdin", "r");
                $ans = trim(fgets($handle));
                if ($ans !== 'yes') {
                    error("Aborted - backup failed");
                    return false;
                }
            }
        }
    }

    if ($dryRun) {
        echo "\n";
        info("DRY RUN — would execute:");
        echo "════════════════════════════════════════════════════════════\n";
        echo $migrationContent;
        echo "\n════════════════════════════════════════════════════════════\n\n";
        success("Dry run complete for {$name}");
        return true;
    }

    try {
        $pdo = DB::connection()->getPdo();
        $pdo->setAttribute(\PDO::ATTR_ERRMODE, \PDO::ERRMODE_EXCEPTION);
        $pdo->exec($migrationContent);
        success("Executed: {$name}");

        markApplied($pdo, $name);
        success("Recorded in migrations table");
        return true;

    } catch (\Exception $e) {
        echo "\n";
        error("Migration failed: " . $e->getMessage());
        if ($backupFile && file_exists($backupFile)) {
            $dbUser = getenv('DB_USER');
            $dbName = getenv('DB_NAME');
            warn("To restore: mysql -u {$dbUser} -p {$dbName} < {$backupFile}");
        }
        return false;
    }
}

// ─── Banner ───────────────────────────────────────────────────────────────────
echo "\n";
echo "╔════════════════════════════════════════════════════════════╗\n";
echo "║         SAFE MIGRATION RUNNER v3.0                         ║\n";
echo "║         With Automatic Backup & Safety Checks              ║\n";
echo "╚════════════════════════════════════════════════════════════╝\n\n";

// ─── Parse arguments ──────────────────────────────────────────────────────────
$dryRun      = in_array('--dry-run', $argv);
$skipBackup  = in_array('--skip-backup', $argv);
$force       = in_array('--force', $argv);
$modePending = in_array('--pending', $argv);
$modeRun     = in_array('--run-pending', $argv);
$modeMark    = in_array('--mark-applied', $argv);
$markAll     = in_array('--all', $argv);
$migrationFile = null;

foreach ($argv as $arg) {
    if (strpos($arg, '--file=') === 0) {
        $migrationFile = substr($arg, 7);
    }
}

// ─── Help ─────────────────────────────────────────────────────────────────────
if (in_array('--help', $argv)) {
    echo "Usage: php safe_migrate.php [options]\n\n";
    echo "Single file:\n";
    echo "  --file=filename.sql   Run a specific migration file\n\n";
    echo "Batch modes:\n";
    echo "  --pending             List migrations not yet applied\n";
    echo "  --run-pending         Run all pending migrations in order\n";
    echo "  --mark-applied --all  Mark ALL migration files as applied (bootstrap)\n";
    echo "  --mark-applied --file=X  Mark one file as applied without running it\n\n";
    echo "Flags:\n";
    echo "  --dry-run             Preview without executing\n";
    echo "  --skip-backup         Skip automatic backup (NOT RECOMMENDED)\n";
    echo "  --force               Skip safety confirmations (DANGEROUS)\n";
    echo "  --help                Show this help\n\n";
    echo "Examples:\n";
    echo "  php safe_migrate.php --pending\n";
    echo "  php safe_migrate.php --run-pending --dry-run\n";
    echo "  php safe_migrate.php --run-pending\n";
    echo "  php safe_migrate.php --mark-applied --all     # bootstrap on production\n";
    echo "  php safe_migrate.php --file=my_migration.sql\n\n";
    exit(0);
}

// ─── Environment check ────────────────────────────────────────────────────────
$env = getenv('APP_ENV') ?: 'production';
if ($env === 'production' && !$force && ($modeRun || $migrationFile)) {
    warn("Running on PRODUCTION environment!");
    echo "Are you sure you want to continue? Type 'yes' to proceed: ";
    $handle = fopen("php://stdin", "r");
    $line = trim(fgets($handle));
    if ($line !== 'yes') {
        error("Aborted by user");
        exit(1);
    }
}

if ($dryRun) {
    warn("DRY RUN MODE - No changes will be made to database");
    echo "\n";
}

// ─── MODE: --pending ──────────────────────────────────────────────────────────
if ($modePending) {
    $pdo = DB::connection()->getPdo();
    $pending = getPendingMigrations($pdo);

    if (empty($pending)) {
        success("All migrations are up to date.");
    } else {
        warn(count($pending) . " pending migration(s):");
        echo "\n";
        foreach ($pending as $path) {
            echo "  • " . basename($path) . "\n";
        }
        echo "\n";
        info("Run: php safe_migrate.php --run-pending");
    }
    exit(0);
}

// ─── MODE: --mark-applied ─────────────────────────────────────────────────────
if ($modeMark) {
    $pdo = DB::connection()->getPdo();

    if ($markAll) {
        $files = getAllMigrationFiles();
        info("Marking " . count($files) . " migration(s) as applied...");
        foreach ($files as $path) {
            markApplied($pdo, basename($path));
            echo "  ✓ " . basename($path) . "\n";
        }
        echo "\n";
        success("Done. Run --pending to verify.");
        exit(0);
    }

    if ($migrationFile) {
        markApplied($pdo, basename($migrationFile));
        success("Marked as applied: " . basename($migrationFile));
        exit(0);
    }

    error("--mark-applied requires --all or --file=filename.sql");
    exit(1);
}

// ─── MODE: --run-pending ──────────────────────────────────────────────────────
if ($modeRun) {
    $pdo = DB::connection()->getPdo();
    $pending = getPendingMigrations($pdo);

    if (empty($pending)) {
        success("Nothing to do — all migrations are already applied.");
        exit(0);
    }

    info(count($pending) . " pending migration(s) to run:");
    foreach ($pending as $path) {
        echo "  • " . basename($path) . "\n";
    }
    echo "\n";

    $passed = 0;
    $failed = 0;
    foreach ($pending as $path) {
        echo "────────────────────────────────────────────────────────────\n";
        $ok = runMigration($path, $dryRun, $skipBackup, $force);
        if ($ok) {
            $passed++;
        } else {
            $failed++;
            error("Stopping after failure in: " . basename($path));
            break;
        }
        echo "\n";
    }

    echo "\n";
    echo "╔════════════════════════════════════════════════════════════╗\n";
    echo "║                  BATCH RUN COMPLETE                        ║\n";
    echo "╚════════════════════════════════════════════════════════════╝\n\n";
    info("{$passed} succeeded, {$failed} failed");
    exit($failed > 0 ? 1 : 0);
}

// ─── MODE: --file=X (original single-file behaviour) ─────────────────────────
if ($migrationFile) {
    $migrationPath = __DIR__ . '/../migrations/' . $migrationFile;
    if (!file_exists($migrationPath)) {
        error("Migration file not found: {$migrationFile}");
        exit(1);
    }

    $ok = runMigration($migrationPath, $dryRun, $skipBackup, $force);

    if ($ok && !$dryRun) {
        echo "\n";
        echo "╔════════════════════════════════════════════════════════════╗\n";
        echo "║                  MIGRATION COMPLETE                        ║\n";
        echo "╚════════════════════════════════════════════════════════════╝\n\n";
    }
    exit($ok ? 0 : 1);
}

// ─── No mode specified ────────────────────────────────────────────────────────
error("No mode specified. Use --help for usage.");
exit(1);
