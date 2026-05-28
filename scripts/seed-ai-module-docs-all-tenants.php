<?php
// Copyright © 2024–2026 Jasper Ford
// SPDX-License-Identifier: AGPL-3.0-or-later
// Author: Jasper Ford
// See NOTICE file for attribution and acknowledgements.
//
// One-shot backfill: seed AI module docs for every active tenant.
// Idempotent — won't overwrite custom admin edits.
// Run: docker exec nexus-php-app php scripts/seed-ai-module-docs-all-tenants.php

require __DIR__ . '/../vendor/autoload.php';
$app = require __DIR__ . '/../bootstrap/app.php';
$app->make(Illuminate\Contracts\Console\Kernel::class)->bootstrap();

$svc = new App\Services\AI\AiModuleDocsService();
$tenants = Illuminate\Support\Facades\DB::table('tenants')
    ->where('is_active', 1)
    ->orderBy('id')
    ->get(['id', 'slug']);

$total = 0;
foreach ($tenants as $t) {
    $n = $svc->seedDefaultsForTenant((int) $t->id);
    $total += $n;
    echo sprintf("tenant %3d (%-25s) inserted: %d\n", $t->id, $t->slug ?: '<root>', $n);
}
echo "----\nTotal inserted across all tenants: {$total}\n";
