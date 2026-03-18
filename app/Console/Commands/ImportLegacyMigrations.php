<?php
// Copyright © 2024–2026 Jasper Ford
// SPDX-License-Identifier: AGPL-3.0-or-later
// Author: Jasper Ford
// See NOTICE file for attribution and acknowledgements.

namespace App\Console\Commands;

use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * Import and run legacy SQL migrations from /migrations/.
 *
 * This command bridges the old raw-SQL migration workflow with Laravel.
 * It scans the /migrations/ directory for *.sql files, checks each one
 * against the laravel_migration_registry table, and applies any that
 * haven't been run yet.
 *
 * Usage:
 *   php artisan legacy:migrate                 # Run all pending
 *   php artisan legacy:migrate --dry-run       # Show what would run
 *   php artisan legacy:migrate --status        # Show applied/pending status
 *   php artisan legacy:migrate --mark-all      # Mark all as applied (no execution)
 *
 * @see database/migrations/2026_03_18_000002_create_migration_registry.php
 */
class ImportLegacyMigrations extends Command
{
    /**
     * The name and signature of the console command.
     */
    protected $signature = 'legacy:migrate
                            {--dry-run : Show pending migrations without executing them}
                            {--status : Show the status of all legacy migrations}
                            {--mark-all : Mark all legacy migrations as applied without executing}
                            {--force : Run in production without confirmation}';

    /**
     * The console command description.
     */
    protected $description = 'Import and apply legacy SQL migrations from /migrations/';

    /**
     * Path to legacy migrations relative to base_path().
     */
    private const LEGACY_DIR = 'migrations';

    /**
     * Execute the console command.
     */
    public function handle(): int
    {
        // Ensure the registry table exists
        if (! Schema::hasTable('laravel_migration_registry')) {
            $this->error('The laravel_migration_registry table does not exist.');
            $this->line('Run "php artisan migrate" first to create it.');
            return self::FAILURE;
        }

        $legacyPath = base_path(self::LEGACY_DIR);

        if (! is_dir($legacyPath)) {
            $this->error("Legacy migrations directory not found: {$legacyPath}");
            return self::FAILURE;
        }

        // Collect all .sql files (skip .php files — those are legacy PHP seeders)
        $files = $this->collectSqlFiles($legacyPath);

        if (empty($files)) {
            $this->info('No .sql files found in /migrations/.');
            return self::SUCCESS;
        }

        // Get already-applied filenames
        $applied = DB::table('laravel_migration_registry')
            ->pluck('filename')
            ->toArray();

        $pending = array_filter($files, fn (string $f) => ! in_array($f, $applied, true));

        // --status: display a table of all migrations
        if ($this->option('status')) {
            return $this->showStatus($files, $applied);
        }

        // --mark-all: record every file as applied without executing
        if ($this->option('mark-all')) {
            return $this->markAll($files, $applied);
        }

        if (empty($pending)) {
            $this->info('All legacy migrations have been applied. Nothing to do.');
            return self::SUCCESS;
        }

        $this->info(sprintf('Found %d pending legacy migration(s).', count($pending)));

        // --dry-run: just list them
        if ($this->option('dry-run')) {
            $this->table(['Pending Migration'], array_map(fn ($f) => [$f], $pending));
            return self::SUCCESS;
        }

        // Production safety check
        if (app()->isProduction() && ! $this->option('force')) {
            if (! $this->confirm('You are running in PRODUCTION. Continue?')) {
                $this->warn('Aborted.');
                return self::SUCCESS;
            }
        }

        // Execute each pending migration
        $success = 0;
        $failed  = 0;

        foreach ($pending as $filename) {
            $filePath = $legacyPath . DIRECTORY_SEPARATOR . $filename;

            $this->line("  Applying: {$filename}");

            try {
                $sql = file_get_contents($filePath);

                if ($sql === false) {
                    throw new \RuntimeException("Unable to read file: {$filePath}");
                }

                // Execute the raw SQL (may contain multiple statements)
                DB::unprepared($sql);

                // Record in registry
                DB::table('laravel_migration_registry')->insert([
                    'filename'   => $filename,
                    'applied_at' => now(),
                ]);

                $this->info("  Applied:  {$filename}");
                $success++;
            } catch (\Throwable $e) {
                $this->error("  FAILED:   {$filename}");
                $this->error("  Error:    {$e->getMessage()}");
                $failed++;

                // Stop on first failure to prevent cascading errors
                $this->warn('Stopping execution due to failure. Fix the issue and re-run.');
                break;
            }
        }

        $this->newLine();
        $this->info("Done. Applied: {$success}, Failed: {$failed}, Remaining: " . (count($pending) - $success - $failed));

        return $failed > 0 ? self::FAILURE : self::SUCCESS;
    }

    /**
     * Collect all .sql filenames sorted alphabetically.
     *
     * @return string[]
     */
    private function collectSqlFiles(string $directory): array
    {
        $files = [];

        foreach (scandir($directory) as $entry) {
            if ($entry === '.' || $entry === '..') {
                continue;
            }

            if (str_ends_with(strtolower($entry), '.sql')) {
                $files[] = $entry;
            }
        }

        sort($files);

        return $files;
    }

    /**
     * Display the status of all legacy migrations.
     */
    private function showStatus(array $files, array $applied): int
    {
        $rows = [];

        foreach ($files as $filename) {
            $isApplied = in_array($filename, $applied, true);
            $rows[] = [
                $filename,
                $isApplied ? 'Applied' : 'Pending',
            ];
        }

        $this->table(['Migration', 'Status'], $rows);

        $appliedCount = count(array_filter($files, fn ($f) => in_array($f, $applied, true)));
        $pendingCount = count($files) - $appliedCount;

        $this->info("Total: " . count($files) . " | Applied: {$appliedCount} | Pending: {$pendingCount}");

        return self::SUCCESS;
    }

    /**
     * Mark all migrations as applied without executing them.
     *
     * This is useful when connecting Laravel to a database that already
     * has all legacy migrations applied manually.
     */
    private function markAll(array $files, array $applied): int
    {
        $toMark = array_filter($files, fn ($f) => ! in_array($f, $applied, true));

        if (empty($toMark)) {
            $this->info('All legacy migrations are already marked as applied.');
            return self::SUCCESS;
        }

        if (! $this->confirm(sprintf('Mark %d migration(s) as applied without executing them?', count($toMark)))) {
            $this->warn('Aborted.');
            return self::SUCCESS;
        }

        foreach ($toMark as $filename) {
            DB::table('laravel_migration_registry')->insert([
                'filename'   => $filename,
                'applied_at' => now(),
            ]);
        }

        $this->info(sprintf('Marked %d migration(s) as applied.', count($toMark)));

        return self::SUCCESS;
    }
}
