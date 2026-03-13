<?php
// Copyright (c) 2024-2026 Jasper Ford
// SPDX-License-Identifier: AGPL-3.0-or-later
// Author: Jasper Ford
// See NOTICE file for attribution and acknowledgements.
// Verify onboarding categories endpoint returns data per tenant
require '/var/www/html/vendor/autoload.php';

$tenants = [
    ['id' => 4, 'host' => 'timebank.global',     'name' => 'Timebank Global'],
    ['id' => 3, 'host' => 'nexuscivic.ie',         'name' => 'Public Sector Demo'],
    ['id' => 6, 'host' => 'app.project-nexus.ie', 'name' => 'Crewkerne Timebank'],
];

// Clear rate limits first
$dir = sys_get_temp_dir() . '/nexus_ratelimit/';
if (is_dir($dir)) array_map('unlink', glob($dir . '*') ?: []);
try {
    $redis = new Redis();
    $redis->connect('nexus-php-redis', 6379);
    foreach ($redis->keys('ratelimit:*') as $k) $redis->del($k);
    $redis->close();
} catch (\Throwable $e) {}

function registerAndGetToken(string $host, int $tenantId): ?string {
    $ts = time() . '_' . $tenantId;
    $email = "ontst_{$ts}@mailtest.nexus";

    $reg = json_encode([
        'first_name'            => 'Cat',
        'last_name'             => 'Test',
        'email'                 => $email,
        'password'              => 'SecureTest123!',
        'password_confirmation' => 'SecureTest123!',
        'terms_accepted'        => true,
        'tenant_id'             => $tenantId,
        'bot_timer'             => 6000,
    ]);

    $ch = curl_init('http://localhost/api/v2/auth/register');
    curl_setopt_array($ch, [
        CURLOPT_RETURNTRANSFER => true,
        CURLOPT_POST           => true,
        CURLOPT_POSTFIELDS     => $reg,
        CURLOPT_HTTPHEADER     => ['Content-Type: application/json', 'Host: ' . $host],
        CURLOPT_TIMEOUT        => 15,
    ]);
    $body   = curl_exec($ch);
    $status = curl_getinfo($ch, CURLINFO_HTTP_CODE);
    curl_close($ch);

    $data = json_decode($body, true);
    $token = $data['data']['access_token'] ?? null;
    if (!$token) {
        echo "  [register HTTP {$status}] " . substr($body, 0, 150) . "\n";
    }

    // Clear rate limits after each registration
    $dir = sys_get_temp_dir() . '/nexus_ratelimit/';
    if (is_dir($dir)) array_map('unlink', glob($dir . '*') ?: []);
    try {
        $redis = new Redis();
        $redis->connect('nexus-php-redis', 6379);
        foreach ($redis->keys('ratelimit:*') as $k) $redis->del($k);
        $redis->close();
    } catch (\Throwable $e) {}

    return $token;
}

function checkCategories(string $host, string $token): array {
    $ch = curl_init('http://localhost/api/v2/onboarding/categories');
    curl_setopt_array($ch, [
        CURLOPT_RETURNTRANSFER => true,
        CURLOPT_HTTPHEADER     => ['Host: ' . $host, 'Authorization: Bearer ' . $token],
        CURLOPT_TIMEOUT        => 15,
    ]);
    $body   = curl_exec($ch);
    $status = curl_getinfo($ch, CURLINFO_HTTP_CODE);
    curl_close($ch);
    $data   = json_decode($body, true);
    return ['status' => $status, 'count' => count($data['data'] ?? []), 'raw' => substr($body, 0, 200)];
}

echo "=== Onboarding Categories Test ===\n\n";

foreach ($tenants as $t) {
    echo "Testing T{$t['id']} {$t['name']}...\n";
    $token = registerAndGetToken($t['host'], $t['id']);
    if (!$token) {
        echo "FAIL: could not get token\n\n";
        continue;
    }
    $result = checkCategories($t['host'], $token);
    $ok = ($result['status'] === 200 && $result['count'] > 0);
    echo ($ok ? 'PASS' : 'FAIL') . ": HTTP {$result['status']} — {$result['count']} categories returned\n";
    if (!$ok) echo "     RAW: {$result['raw']}\n";
    echo "\n";
}

echo "=== Done ===\n";
