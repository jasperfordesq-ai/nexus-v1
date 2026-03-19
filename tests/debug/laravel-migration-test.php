<?php
// Copyright © 2024–2026 Jasper Ford
// SPDX-License-Identifier: AGPL-3.0-or-later
// Author: Jasper Ford
// See NOTICE file for attribution and acknowledgements.

/**
 * Laravel Migration Verification Test
 * Uses curl to test endpoints independently
 */

$baseUrl = 'http://localhost';
$pass = 0;
$fail = 0;
$errors = [];

function test($method, $path, $expectedStatus, $label = '') {
    global $baseUrl, $pass, $fail, $errors;
    $ch = curl_init();
    curl_setopt($ch, CURLOPT_URL, $baseUrl . $path);
    curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
    curl_setopt($ch, CURLOPT_TIMEOUT, 10);
    curl_setopt($ch, CURLOPT_CUSTOMREQUEST, $method);
    curl_setopt($ch, CURLOPT_HTTPHEADER, [
        'X-Tenant-ID: 2',
        'Accept: application/json',
    ]);
    if ($method === 'POST') {
        curl_setopt($ch, CURLOPT_POSTFIELDS, '{}');
        curl_setopt($ch, CURLOPT_HTTPHEADER, [
            'X-Tenant-ID: 2',
            'Accept: application/json',
            'Content-Type: application/json',
        ]);
    }
    $body = curl_exec($ch);
    $status = curl_getinfo($ch, CURLINFO_HTTP_CODE);
    curl_close($ch);
    
    $name = $label ?: "$method $path";
    if ($status === $expectedStatus) {
        $pass++;
    } else {
        $fail++;
        $errors[] = "$name: expected $expectedStatus, got $status";
    }
}

echo "=== Laravel Migration Verification Test ===\n\n";

// PUBLIC ENDPOINTS (200)
echo "[Public endpoints]\n";
test('GET', '/api/laravel/health', 200, 'health');
test('GET', '/api/v2/tenant/bootstrap', 200, 'tenant-bootstrap');
test('GET', '/api/v2/tenants', 200, 'tenants-list');
test('GET', '/api/v2/platform/stats', 200, 'platform-stats');
test('GET', '/api/v2/listings', 200, 'listings');
test('GET', '/api/v2/events', 200, 'events');
test('GET', '/api/v2/groups', 200, 'groups');
test('GET', '/api/v2/blog', 200, 'blog');
test('GET', '/api/v2/blog/categories', 200, 'blog-categories');
test('GET', '/api/v2/help/faqs', 200, 'help-faqs');
test('GET', '/api/v2/resources', 200, 'resources');
test('GET', '/api/v2/resources/categories', 200, 'resource-cats');
test('GET', '/api/v2/kb', 200, 'knowledge-base');
test('GET', '/api/v2/jobs', 200, 'jobs');
test('GET', '/api/v2/goals', 401, 'goals');  // auth required
test('GET', '/api/v2/ideation-challenges', 200, 'ideation');
test('GET', '/api/v2/polls', 401, 'polls');  // auth required
test('GET', '/api/v2/volunteering/opportunities', 401, 'vol-opps');  // auth required
test('GET', '/api/v2/volunteering/organisations', 401, 'vol-orgs');  // auth required
test('GET', '/api/v2/comments?type=listing&id=1', 200, 'comments');
test('GET', '/api/v2/skills/categories', 200, 'skills-cats');
test('GET', '/api/v2/community/stats', 200, 'community-stats');
test('GET', '/api/v1/federation', 200, 'federation-v1');
test('GET', '/api/v2/search/trending', 200, 'search');
test('GET', '/api/v2/search/trending', 200, 'search-trending');

// AUTH-REQUIRED (401)
echo "[Auth-required endpoints]\n";
test('GET', '/api/v2/users/me', 401, 'me');
test('GET', '/api/v2/feed', 401, 'feed');
test('GET', '/api/v2/notifications', 401, 'notifications');
test('GET', '/api/v2/notifications/counts', 401, 'notif-counts');
test('GET', '/api/v2/messages', 401, 'messages');
test('GET', '/api/v2/messages/unread-count', 401, 'msg-unread');
test('GET', '/api/v2/wallet/balance', 401, 'wallet-balance');
test('GET', '/api/v2/wallet/transactions', 401, 'wallet-txns');
test('GET', '/api/v2/connections', 401, 'connections');
test('GET', '/api/v2/reviews/pending', 401, 'reviews-pending');
test('GET', '/api/v2/exchanges', 401, 'exchanges');
test('GET', '/api/v2/gamification/profile', 401, 'gam-profile');
test('GET', '/api/v2/gamification/badges', 401, 'gam-badges');
test('GET', '/api/v2/users/me/skills', 401, 'my-skills');
test('GET', '/api/v2/users/me/availability', 401, 'my-avail');
test('GET', '/api/v2/users/me/activity/dashboard', 401, 'my-activity');
test('GET', '/api/v2/users/me/sub-accounts', 401, 'sub-accounts');
test('GET', '/api/v2/users/me/match-preferences', 401, 'match-prefs');
test('GET', '/api/v2/listings/saved', 401, 'saved-listings');
test('GET', '/api/v2/me/stats', 401, 'my-stats');

// ADMIN (401)
echo "[Admin endpoints]\n";
test('GET', '/api/v2/admin/dashboard/stats', 401, 'admin-dash');
test('GET', '/api/v2/admin/users', 401, 'admin-users');
test('GET', '/api/v2/admin/listings', 401, 'admin-listings');
test('GET', '/api/v2/admin/settings', 401, 'admin-settings');
test('GET', '/api/v2/admin/categories', 401, 'admin-cats');
test('GET', '/api/v2/admin/reports', 401, 'admin-reports');
test('GET', '/api/v2/admin/newsletters', 401, 'admin-newsletter');
test('GET', '/api/v2/admin/volunteering', 401, 'admin-vol');
test('GET', '/api/v2/admin/events', 401, 'admin-events');

// WRITE (401)
echo "[Write endpoints]\n";
test('POST', '/api/v2/listings', 401, 'create-listing');
test('POST', '/api/v2/events', 401, 'create-event');
test('POST', '/api/v2/groups', 401, 'create-group');
test('POST', '/api/v2/messages', 401, 'send-message');
test('POST', '/api/v2/reviews', 401, 'create-review');
test('POST', '/api/v2/exchanges', 401, 'create-exchange');
test('POST', '/api/v2/feed/posts', 401, 'create-post');
test('POST', '/api/v2/wallet/transfer', 401, 'wallet-transfer');
test('POST', '/api/v2/connections/request', 401, 'conn-request');

// RESULTS
echo "\n=== RESULTS ===\n";
echo "Passed: $pass / " . ($pass + $fail) . "\n";
echo "Failed: $fail\n";

if (!empty($errors)) {
    echo "\n=== FAILURES ===\n";
    foreach ($errors as $err) {
        echo "  $err\n";
    }
}

echo "\n" . ($fail === 0 ? 'ALL TESTS PASSED!' : 'SOME TESTS FAILED') . "\n";
exit($fail > 0 ? 1 : 0);
