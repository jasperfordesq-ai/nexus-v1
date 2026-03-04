<?php
// Copyright © 2024–2026 Jasper Ford
// SPDX-License-Identifier: AGPL-3.0-or-later
// Author: Jasper Ford
// See NOTICE file for attribution and acknowledgements.

/**
 * Meilisearch Sync Script
 *
 * Backfills all active listings and users into Meilisearch indexes.
 * Safe to re-run at any time — Meilisearch upserts are idempotent.
 *
 * Usage:
 *   php scripts/sync_search_index.php --tenant=2
 *   php scripts/sync_search_index.php --all-tenants
 *   php scripts/sync_search_index.php --all-tenants --type=listing
 *   php scripts/sync_search_index.php --tenant=2 --dry-run
 *   php scripts/sync_search_index.php --help
 *
 * Options:
 *   --tenant=<id>       Process a single tenant by ID
 *   --all-tenants       Process all active tenants
 *   --type=listing|user Process only one content type (default: both)
 *   --dry-run           Count rows without sending to Meilisearch
 *   --help              Show this message
 */

if (php_sapi_name() !== 'cli') {
    http_response_code(403);
    exit('CLI only');
}

require_once __DIR__ . '/../bootstrap.php';

use Nexus\Core\Database;
use Nexus\Core\TenantContext;
use Nexus\Services\SearchService;

// ============================================================
// Parse arguments
// ============================================================
$opts = getopt('', ['tenant:', 'all-tenants', 'type:', 'dry-run', 'help']);

if (isset($opts['help'])) {
    echo <<<HELP
sync_search_index.php — Backfill Meilisearch indexes from the database

Options:
  --tenant=<id>       Process a single tenant by ID
  --all-tenants       Process all active tenants
  --type=listing|user Process only one content type (default: both)
  --dry-run           Count items without indexing
  --help              Show this message

Examples:
  php scripts/sync_search_index.php --tenant=2
  php scripts/sync_search_index.php --all-tenants
  php scripts/sync_search_index.php --all-tenants --type=listing
  php scripts/sync_search_index.php --tenant=2 --dry-run

HELP;
    exit(0);
}

$dryRun     = isset($opts['dry-run']);
$typeFilter = $opts['type'] ?? null; // null = both

if ($typeFilter !== null && !in_array($typeFilter, ['listing', 'user'], true)) {
    fwrite(STDERR, "Error: --type must be 'listing' or 'user'\n");
    exit(1);
}

// Collect tenant IDs to process
$tenantIds = [];

if (isset($opts['all-tenants'])) {
    $rows = Database::query(
        "SELECT id FROM tenants WHERE status = 'active' ORDER BY id"
    )->fetchAll(\PDO::FETCH_ASSOC);
    $tenantIds = array_column($rows, 'id');
} elseif (isset($opts['tenant'])) {
    $tenantIds = [(int)$opts['tenant']];
} else {
    fwrite(STDERR, "Error: specify --tenant=<id> or --all-tenants\n");
    fwrite(STDERR, "Run with --help for usage information.\n");
    exit(1);
}

if (empty($tenantIds)) {
    fwrite(STDERR, "No active tenants found.\n");
    exit(1);
}

// ============================================================
// Ensure indexes exist (creates them if missing, sets attributes)
// ============================================================
if (!$dryRun) {
    echo "Configuring Meilisearch indexes...\n";
    SearchService::ensureIndexes();

    if (!SearchService::isAvailable()) {
        fwrite(STDERR, "Error: Meilisearch is not available. Check MEILISEARCH_HOST env var and ensure the service is running.\n");
        exit(1);
    }
    echo "Meilisearch is online.\n\n";
}

// ============================================================
// Process tenants
// ============================================================
echo "Sync search index" . ($dryRun ? " [DRY RUN]" : "") . "\n";
echo "Tenants  : " . implode(', ', $tenantIds) . "\n";
echo "Type     : " . ($typeFilter ?? 'listing + user') . "\n\n";

$totalProcessed = 0;
$totalSkipped   = 0;
$totalErrors    = 0;

foreach ($tenantIds as $tenantId) {
    $tenantId = (int)$tenantId;
    TenantContext::setId($tenantId);

    echo "=== Tenant {$tenantId} ===\n";

    if (!$typeFilter || $typeFilter === 'listing') {
        [$proc, $skip, $err] = syncListings($tenantId, $dryRun);
        $totalProcessed += $proc;
        $totalSkipped   += $skip;
        $totalErrors    += $err;
    }

    if (!$typeFilter || $typeFilter === 'user') {
        [$proc, $skip, $err] = syncUsers($tenantId, $dryRun);
        $totalProcessed += $proc;
        $totalSkipped   += $skip;
        $totalErrors    += $err;
    }
}

echo "\n=== Summary ===\n";
echo "Processed : {$totalProcessed}\n";
echo "Skipped   : {$totalSkipped}\n";
echo "Errors    : {$totalErrors}\n";
exit($totalErrors > 0 ? 1 : 0);

// ============================================================
// Helpers
// ============================================================

/**
 * Load and index all active listings for a tenant in batches of 100.
 * Returns [processed, skipped, errors].
 *
 * @return array{int, int, int}
 */
function syncListings(int $tenantId, bool $dryRun): array
{
    $rows = Database::query(
        "SELECT l.id, l.tenant_id, l.title, l.description, l.location, l.skills, l.status,
                CONCAT(u.first_name, ' ', u.last_name) as author_name
         FROM listings l
         LEFT JOIN users u ON l.user_id = u.id
         WHERE l.tenant_id = ? AND l.status = 'active'
         ORDER BY l.id",
        [$tenantId]
    )->fetchAll(\PDO::FETCH_ASSOC);

    $total = count($rows);

    if ($total === 0) {
        echo "  listings: 0 active rows — skipping\n";
        return [0, 0, 0];
    }

    echo "  listings: {$total} items\n";

    if ($dryRun) {
        return [0, $total, 0];
    }

    $processed = 0;
    $errors    = 0;
    $batches   = array_chunk($rows, 100);

    foreach ($batches as $batchIndex => $batch) {
        foreach ($batch as $row) {
            try {
                SearchService::indexListing($row);
                $processed++;
            } catch (\Throwable $e) {
                fwrite(STDERR, "    Error listing#{$row['id']}: " . $e->getMessage() . "\n");
                $errors++;
            }
        }

        $done = min(($batchIndex + 1) * 100, $total);
        echo "  listings: {$done}/{$total}\r";
    }

    echo "  listings: {$total}/{$total} processed\n";
    return [$processed, 0, $errors];
}

/**
 * Load and index all active users for a tenant in batches of 100.
 * Returns [processed, skipped, errors].
 *
 * @return array{int, int, int}
 */
function syncUsers(int $tenantId, bool $dryRun): array
{
    $rows = Database::query(
        "SELECT id, tenant_id, first_name, last_name, bio, skills, location, status
         FROM users
         WHERE tenant_id = ? AND status = 'active'
         ORDER BY id",
        [$tenantId]
    )->fetchAll(\PDO::FETCH_ASSOC);

    $total = count($rows);

    if ($total === 0) {
        echo "  users: 0 active rows — skipping\n";
        return [0, 0, 0];
    }

    echo "  users: {$total} items\n";

    if ($dryRun) {
        return [0, $total, 0];
    }

    $processed = 0;
    $errors    = 0;
    $batches   = array_chunk($rows, 100);

    foreach ($batches as $batchIndex => $batch) {
        foreach ($batch as $row) {
            try {
                SearchService::indexUser($row);
                $processed++;
            } catch (\Throwable $e) {
                fwrite(STDERR, "    Error user#{$row['id']}: " . $e->getMessage() . "\n");
                $errors++;
            }
        }

        $done = min(($batchIndex + 1) * 100, $total);
        echo "  users: {$done}/{$total}\r";
    }

    echo "  users: {$total}/{$total} processed\n";
    return [$processed, 0, $errors];
}
