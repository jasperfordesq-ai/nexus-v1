<?php
// Copyright © 2024–2026 Jasper Ford
// SPDX-License-Identifier: AGPL-3.0-or-later
// Author: Jasper Ford
// See NOTICE file for attribution and acknowledgements.

/**
 * Batch geocode all users who have a location string but no lat/lon.
 * Run once after deploying geo-ranking to populate existing users' coordinates.
 *
 * Usage:
 *   php scripts/batch_geocode_users.php [--limit=200] [--tenant=2]
 */

declare(strict_types=1);

require __DIR__ . '/../vendor/autoload.php';
$app = require_once __DIR__ . '/../bootstrap/app.php';
$app->make(Illuminate\Contracts\Console\Kernel::class)->bootstrap();

use Illuminate\Support\Facades\DB;
use App\Core\TenantContext;
use App\Services\GeocodingService;

// Parse CLI args
$options = getopt('', ['limit:', 'tenant:']);
$limit   = isset($options['limit']) ? (int)$options['limit'] : 200;
$onlyTenantId = isset($options['tenant']) ? (int)$options['tenant'] : null;

echo "=== Batch Geocode Users ===\n";
echo "Limit per tenant: {$limit}\n";
if ($onlyTenantId) {
    echo "Tenant filter: {$onlyTenantId}\n";
}
echo "\n";

// Fetch active tenants
$whereClause = $onlyTenantId ? "WHERE is_active = 1 AND id = {$onlyTenantId}" : "WHERE is_active = 1";
$tenants = array_map(fn($r) => (array) $r, DB::select("SELECT id, name FROM tenants {$whereClause} ORDER BY id"));

if (empty($tenants)) {
    echo "No active tenants found.\n";
    exit(0);
}

$totals = ['tenants' => 0, 'processed' => 0, 'success' => 0, 'failed' => 0];

foreach ($tenants as $tenant) {
    $tenantId   = (int)$tenant['id'];
    $tenantName = $tenant['name'];

    TenantContext::setById($tenantId);

    echo "Tenant #{$tenantId} ({$tenantName}):\n";

    $result = GeocodingService::batchGeocodeUsers($limit);

    echo "  Processed: {$result['processed']}  Success: {$result['success']}  Failed: {$result['failed']}\n";

    $totals['tenants']++;
    $totals['processed'] += $result['processed'];
    $totals['success']   += $result['success'];
    $totals['failed']    += $result['failed'];
}

echo "\n=== Summary ===\n";
echo "Tenants processed : {$totals['tenants']}\n";
echo "Users processed   : {$totals['processed']}\n";
echo "Geocoded OK       : {$totals['success']}\n";
echo "Failed            : {$totals['failed']}\n";
