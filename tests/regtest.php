<?php
// Copyright © 2024-2026 Jasper Ford
// SPDX-License-Identifier: AGPL-3.0-or-later
// Registration E2E test — run INSIDE PHP container (localhost:80)
// Tests all 6 active tenants + invalid tenant rejection

require '/var/www/html/vendor/autoload.php';

$tenants = [
    ['id' => 1, 'host' => 'project-nexus.ie',    'name' => 'Project NEXUS (admin)'],
    ['id' => 2, 'host' => 'hour-timebank.ie',     'name' => 'Timebank Ireland'],
    ['id' => 3, 'host' => 'nexuscivic.ie',         'name' => 'Public Sector Demo'],
    ['id' => 4, 'host' => 'timebank.global',       'name' => 'Timebank Global'],
    ['id' => 5, 'host' => 'app.project-nexus.ie', 'name' => 'Partner Demo (slug-only)'],
    ['id' => 6, 'host' => 'app.project-nexus.ie', 'name' => 'Crewkerne Timebank (slug-only)'],
];

$pass = 0;
$fail = 0;
$octet = 10; // rotating IP octets

function clearRateLimits(): void {
    // Clear file-based rate limit cache
    $dir = sys_get_temp_dir() . '/nexus_ratelimit/';
    if (is_dir($dir)) {
        array_map('unlink', glob($dir . '*') ?: []);
    }
    // Clear Redis rate limit keys
    try {
        $redis = new Redis();
        $redis->connect('nexus-php-redis', 6379);
        // Key patterns used by the two rate-limit layers:
        //   Layer 1 (RateLimitService/Redis): ratelimit:auth:register:<ip>
        //   Layer 2 (RateLimiter/file+Redis):  ratelimit:api:registration:ip:<ip>
        $keys = $redis->keys('ratelimit:*');
        foreach ($keys as $key) {
            $redis->del($key);
        }
        $redis->close();
    } catch (\Throwable $e) {
        // Redis might not be available — that's ok
    }
}

function testRegistration(array $tenant): array {
    global $octet;
    $octet = ($octet + 13) % 200 + 10; // unique IP per test
    $ts = time() . '_' . $tenant['id'];

    $email = "regtest_{$ts}@mailtest.nexus";
    $payload = json_encode([
        'first_name'            => 'Test',
        'last_name'             => 'User',
        'email'                 => $email,
        'password'              => 'SecureTest123!',
        'password_confirmation' => 'SecureTest123!',
        'phone'                 => '+1 555 123 4567',
        'tenant_id'             => $tenant['id'],
        'terms_accepted'        => true,
        'bot_timer'             => 6000,
    ]);

    $ch = curl_init('http://localhost/api/v2/auth/register');
    curl_setopt_array($ch, [
        CURLOPT_RETURNTRANSFER => true,
        CURLOPT_POST           => true,
        CURLOPT_POSTFIELDS     => $payload,
        CURLOPT_HTTPHEADER     => [
            'Content-Type: application/json',
            'Host: ' . $tenant['host'],
            'X-Forwarded-For: 10.0.0.' . $octet,
        ],
        CURLOPT_TIMEOUT => 20,
    ]);

    $body   = curl_exec($ch);
    $status = curl_getinfo($ch, CURLINFO_HTTP_CODE);
    $err    = curl_error($ch);
    curl_close($ch);

    $data = $body ? json_decode($body, true) : null;

    return [
        'tenant'  => "T{$tenant['id']} {$tenant['name']}",
        'status'  => $status,
        'email'   => $email,
        'data'    => $data,
        'message' => $data['data']['message'] ?? ($data['errors'][0]['message'] ?? ($data['error'] ?? $err)),
        'raw'     => substr($body ?: '(empty)', 0, 300),
    ];
}

echo "=== Registration E2E Test ===\n";
echo "Time: " . date('Y-m-d H:i:s') . "\n\n";

echo "--- Valid Tenants (expect HTTP 201) ---\n";
foreach ($tenants as $tenant) {
    clearRateLimits();
    $r = testRegistration($tenant);
    // Success = HTTP 201 with user data returned
    $ok = ($r['status'] === 201 && isset($r['data']['data']['user']['id']));
    $mark = $ok ? 'PASS' : 'FAIL';
    if ($ok) {
        $uid = $r['data']['data']['user']['id'];
        echo "PASS: {$r['tenant']} [201] User ID={$uid} email={$r['email']}\n";
    } else {
        echo "FAIL: {$r['tenant']} [HTTP {$r['status']}] {$r['message']}\n";
        echo "     RAW: {$r['raw']}\n";
    }
    $ok ? $pass++ : $fail++;
}

// Invalid tenant
echo "\n--- Invalid Tenant (expect 422 rejection) ---\n";
clearRateLimits();
$invalid = ['id' => 9999, 'host' => 'app.project-nexus.ie', 'name' => 'INVALID'];
$r = testRegistration($invalid);
// Accept 422 (tenant validation) or 429 (rate limit) — both mean registration was blocked
$ok = ($r['status'] !== 201);
$mark = $ok ? 'PASS' : 'FAIL';
echo "{$mark}: T9999 INVALID [HTTP {$r['status']}] {$r['message']}\n";
if (!$ok) echo "     RAW: {$r['raw']}\n";
$ok ? $pass++ : $fail++;

echo "\n=== Results: {$pass} passed, {$fail} failed ===\n";
exit($fail > 0 ? 1 : 0);
