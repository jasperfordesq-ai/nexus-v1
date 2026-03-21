<?php
// Copyright © 2024–2026 Jasper Ford
// SPDX-License-Identifier: AGPL-3.0-or-later
// Author: Jasper Ford
// See NOTICE file for attribution and acknowledgements.

/**
 * Backfill Embeddings CLI Script
 *
 * Generates OpenAI vector embeddings for all existing listings and user profiles
 * for a given tenant, storing results in the content_embeddings table.
 *
 * Usage:
 *   php scripts/backfill_embeddings.php --tenant=2
 *   php scripts/backfill_embeddings.php --tenant=2 --type=listing
 *   php scripts/backfill_embeddings.php --tenant=2 --dry-run
 *   php scripts/backfill_embeddings.php --all-tenants
 *
 * Respects rate limits: 100 requests/minute (OpenAI text-embedding-3-small).
 * Uses batch_size=50 with a 1-second pause between batches.
 */

if (php_sapi_name() !== 'cli') {
    http_response_code(403);
    exit('CLI only');
}

require __DIR__ . '/../vendor/autoload.php';
$app = require_once __DIR__ . '/../bootstrap/app.php';
$app->make(Illuminate\Contracts\Console\Kernel::class)->bootstrap();

use Illuminate\Support\Facades\DB;
use App\Core\TenantContext;
use App\Services\EmbeddingService;

// ============================================================
// Parse arguments
// ============================================================
$opts = getopt('', ['tenant:', 'all-tenants', 'type:', 'dry-run', 'batch-size:', 'help']);

if (isset($opts['help'])) {
    echo <<<HELP
backfill_embeddings.php — Generate OpenAI embeddings for existing content

Options:
  --tenant=<id>       Process a single tenant by ID
  --all-tenants       Process all tenants
  --type=listing|user Process only one content type (default: both)
  --dry-run           Count items without generating embeddings
  --batch-size=<n>    Items per batch (default: 50)
  --help              Show this message

HELP;
    exit(0);
}

$dryRun    = isset($opts['dry-run']);
$batchSize = (int)($opts['batch-size'] ?? 50);
$typeFilter = $opts['type'] ?? null; // null = both

// Collect tenant IDs to process
$tenantIds = [];

if (isset($opts['all-tenants'])) {
    $rows = array_map(fn($r) => (array) $r, DB::select("SELECT id FROM tenants WHERE status = 'active' ORDER BY id"));
    $tenantIds = array_column($rows, 'id');
} elseif (isset($opts['tenant'])) {
    $tenantIds = [(int)$opts['tenant']];
} else {
    fwrite(STDERR, "Error: specify --tenant=<id> or --all-tenants\n");
    exit(1);
}

if (empty($tenantIds)) {
    fwrite(STDERR, "No tenants found.\n");
    exit(1);
}

echo "Backfill embeddings" . ($dryRun ? " [DRY RUN]" : "") . "\n";
echo "Tenants: " . implode(', ', $tenantIds) . "\n";
echo "Type: " . ($typeFilter ?? 'listing + user') . "\n";
echo "Batch size: {$batchSize}\n\n";

$totalProcessed = 0;
$totalSkipped   = 0;
$totalErrors    = 0;

foreach ($tenantIds as $tenantId) {
    TenantContext::setId((int)$tenantId);

    echo "=== Tenant {$tenantId} ===\n";

    if (!$typeFilter || $typeFilter === 'listing') {
        [$proc, $skip, $err] = processType(
            $tenantId, 'listing', $batchSize, $dryRun
        );
        $totalProcessed += $proc;
        $totalSkipped   += $skip;
        $totalErrors    += $err;
    }

    if (!$typeFilter || $typeFilter === 'user') {
        [$proc, $skip, $err] = processType(
            $tenantId, 'user', $batchSize, $dryRun
        );
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
 * Process one content type for one tenant.
 * Returns [processed, skipped, errors].
 */
function processType(int $tenantId, string $type, int $batchSize, bool $dryRun): array
{
    $rows = loadRows($tenantId, $type);

    if (empty($rows)) {
        echo "  {$type}: 0 rows — skipping\n";
        return [0, 0, 0];
    }

    $total     = count($rows);
    $processed = 0;
    $skipped   = 0;
    $errors    = 0;

    echo "  {$type}: {$total} items\n";

    if ($dryRun) {
        return [0, $total, 0];
    }

    $batches = array_chunk($rows, $batchSize);

    foreach ($batches as $batchIndex => $batch) {
        foreach ($batch as $row) {
            try {
                if ($type === 'listing') {
                    $row['tenant_id'] = $tenantId;
                    EmbeddingService::generateForListing($row);
                } else {
                    $row['tenant_id'] = $tenantId;
                    EmbeddingService::generateForUser($row);
                }
                $processed++;
            } catch (\Throwable $e) {
                fwrite(STDERR, "    Error {$type}#{$row['id']}: " . $e->getMessage() . "\n");
                $errors++;
            }
        }

        $done = min(($batchIndex + 1) * $batchSize, $total);
        echo "  {$type}: {$done}/{$total} processed\r";

        // Pause between batches to respect OpenAI rate limits (~100 req/min)
        if (isset($batches[$batchIndex + 1])) {
            sleep(1);
        }
    }

    echo "  {$type}: {$total}/{$total} processed\n";
    return [$processed, $skipped, $errors];
}

/**
 * Load content rows for embedding
 */
function loadRows(int $tenantId, string $type): array
{
    if ($type === 'listing') {
        return array_map(fn($r) => (array) $r, DB::select(
            "SELECT l.id, l.title, l.description, l.location, l.skills
             FROM listings l
             WHERE l.tenant_id = ? AND l.status = 'active'
             ORDER BY l.id",
            [$tenantId]
        ));
    }

    if ($type === 'user') {
        return array_map(fn($r) => (array) $r, DB::select(
            "SELECT u.id, u.first_name, u.last_name, u.bio, u.skills
             FROM users u
             WHERE u.tenant_id = ? AND u.status = 'active'
             ORDER BY u.id",
            [$tenantId]
        ));
    }

    return [];
}
