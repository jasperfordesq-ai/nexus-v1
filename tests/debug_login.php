<?php
// Copyright © 2024–2026 Jasper Ford
// SPDX-License-Identifier: AGPL-3.0-or-later
// Author: Jasper Ford
// See NOTICE file for attribution and acknowledgements.

require_once __DIR__ . '/../vendor/autoload.php';
$app = require_once __DIR__ . '/../bootstrap/app.php';
$kernel = $app->make(Illuminate\Contracts\Console\Kernel::class);
$kernel->bootstrap();

$hash = \Illuminate\Support\Facades\Hash::make('secret123');
echo "Laravel hash: " . $hash . PHP_EOL;
echo "password_verify: " . (password_verify('secret123', $hash) ? 'true' : 'false') . PHP_EOL;
echo "Hash driver: " . config('hashing.driver', 'not-configured') . PHP_EOL;

// Check if user can be created and found
\App\Core\TenantContext::setById(2);
echo "Tenant: " . \App\Core\TenantContext::getId() . PHP_EOL;

// Try raw query
$users = \Illuminate\Support\Facades\DB::select("SELECT COUNT(*) as cnt FROM users");
echo "Users count: " . $users[0]->cnt . PHP_EOL;
