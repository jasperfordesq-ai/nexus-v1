<?php
/**
 * Admin API Smoke Test
 *
 * Tests every admin API endpoint to identify which ones work vs fail.
 * Run inside Docker: docker exec nexus-php-app php scripts/admin-smoke-test.php
 *
 * Step 1: Gets a JWT token by logging in
 * Step 2: Hits every admin GET endpoint with that token
 * Step 3: Reports pass/fail for each
 */

$baseUrl = 'http://localhost';

// Step 1: Login to get a token
// First, find admin credentials from the DB
$dbHost = getenv('DB_HOST') ?: 'db';
$dbName = getenv('DB_NAME') ?: 'nexus';
$dbUser = getenv('DB_USER') ?: 'nexus';
$dbPass = getenv('DB_PASS') ?: 'nexus_secret';

try {
    $pdo = new PDO("mysql:host=$dbHost;dbname=$dbName;charset=utf8mb4", $dbUser, $dbPass);
    $pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
    $pdo->setAttribute(PDO::ATTR_DEFAULT_FETCH_MODE, PDO::FETCH_ASSOC);
} catch (PDOException $e) {
    echo "DB Connection failed: " . $e->getMessage() . "\n";
    exit(1);
}

// Find the best admin user: prefer super_admin with is_super_admin=1
$stmt = $pdo->prepare("SELECT id, email, role, tenant_id, is_super_admin, is_tenant_super_admin FROM users WHERE tenant_id = 2 AND is_super_admin = 1 AND status = 'active' LIMIT 1");
$stmt->execute();
$admin = $stmt->fetch();

if (!$admin) {
    $stmt = $pdo->prepare("SELECT id, email, role, tenant_id, is_super_admin, is_tenant_super_admin FROM users WHERE tenant_id = 2 AND role IN ('admin', 'super_admin', 'god') AND status = 'active' LIMIT 1");
    $stmt->execute();
    $admin = $stmt->fetch();
}

if (!$admin) {
    $stmt = $pdo->prepare("SELECT id, email, role, tenant_id, is_super_admin, is_tenant_super_admin FROM users WHERE (is_super_admin = 1 OR role = 'admin') AND status = 'active' LIMIT 1");
    $stmt->execute();
    $admin = $stmt->fetch();
}

if (!$admin) {
    echo "ERROR: No admin user found in any tenant\n";
    exit(1);
}

$tenantId = $admin['tenant_id'] ?? 2;

// Determine effective role for the JWT
$effectiveRole = $admin['role'] ?? 'member';
$isSuperAdmin = !empty($admin['is_super_admin']) || !empty($admin['is_tenant_super_admin']);
if ($isSuperAdmin && !in_array($effectiveRole, ['super_admin', 'god'])) {
    $effectiveRole = 'super_admin';
}

echo "Found admin: {$admin['email']} (db_role: {$admin['role']}, effective_role: {$effectiveRole}, is_super_admin: " . ($isSuperAdmin ? 'YES' : 'NO') . ", id: {$admin['id']}, tenant: {$tenantId})\n";

// Use TokenService directly since we're inside the app container
require_once __DIR__ . '/../vendor/autoload.php';
require_once __DIR__ . '/../src/helpers.php';

use Nexus\Services\TokenService;

// The JWT must include role and is_super_admin claims
$token = TokenService::generateToken((int)$admin['id'], (int)$tenantId, [
    'role' => $effectiveRole,
    'is_super_admin' => $isSuperAdmin,
]);
echo "Generated JWT token for user {$admin['id']} with role: {$effectiveRole}\n\n";

$baseUrl = 'http://localhost';

// Define all GET endpoints to test (we only test GET for read safety)
$endpoints = [
    // Dashboard
    ['GET', '/api/v2/admin/dashboard/stats', 'Dashboard Stats'],
    ['GET', '/api/v2/admin/dashboard/trends', 'Dashboard Trends'],
    ['GET', '/api/v2/admin/dashboard/activity', 'Dashboard Activity'],

    // Users
    ['GET', '/api/v2/admin/users?limit=5', 'Users List'],

    // Listings
    ['GET', '/api/v2/admin/listings?limit=5', 'Listings List'],

    // Categories & Attributes
    ['GET', '/api/v2/admin/categories', 'Categories List'],
    ['GET', '/api/v2/admin/attributes', 'Attributes List'],

    // Config & Settings
    ['GET', '/api/v2/admin/config', 'Config'],
    ['GET', '/api/v2/admin/config/ai', 'AI Config'],
    ['GET', '/api/v2/admin/config/feed-algorithm', 'Feed Algorithm Config'],
    ['GET', '/api/v2/admin/config/images', 'Image Config'],
    ['GET', '/api/v2/admin/config/seo', 'SEO Config'],
    ['GET', '/api/v2/admin/config/native-app', 'Native App Config'],
    ['GET', '/api/v2/admin/settings', 'Settings'],

    // Cache & Jobs
    ['GET', '/api/v2/admin/cache/stats', 'Cache Stats'],
    ['GET', '/api/v2/admin/jobs', 'Background Jobs'],

    // System
    ['GET', '/api/v2/admin/system/activity-log', 'Activity Log'],
    ['GET', '/api/v2/admin/system/cron-jobs', 'Cron Jobs'],

    // Matching
    ['GET', '/api/v2/admin/matching/config', 'Matching Config'],
    ['GET', '/api/v2/admin/matching/stats', 'Matching Stats'],
    ['GET', '/api/v2/admin/matching/approvals', 'Match Approvals'],
    ['GET', '/api/v2/admin/matching/approvals/stats', 'Match Approval Stats'],

    // Blog
    ['GET', '/api/v2/admin/blog?limit=5', 'Blog List'],

    // Gamification
    ['GET', '/api/v2/admin/gamification/stats', 'Gamification Stats'],
    ['GET', '/api/v2/admin/gamification/badges', 'Gamification Badges'],
    ['GET', '/api/v2/admin/gamification/campaigns', 'Gamification Campaigns'],

    // Groups
    ['GET', '/api/v2/admin/groups?limit=5', 'Groups List'],
    ['GET', '/api/v2/admin/groups/analytics', 'Groups Analytics'],
    ['GET', '/api/v2/admin/groups/approvals', 'Groups Approvals'],
    ['GET', '/api/v2/admin/groups/moderation', 'Groups Moderation'],

    // Timebanking
    ['GET', '/api/v2/admin/timebanking/stats', 'Timebanking Stats'],
    ['GET', '/api/v2/admin/timebanking/alerts', 'Timebanking Alerts'],
    ['GET', '/api/v2/admin/timebanking/org-wallets', 'Org Wallets'],
    ['GET', '/api/v2/admin/timebanking/user-report', 'User Report'],

    // Enterprise
    ['GET', '/api/v2/admin/enterprise/dashboard', 'Enterprise Dashboard'],
    ['GET', '/api/v2/admin/enterprise/roles', 'Enterprise Roles'],
    ['GET', '/api/v2/admin/enterprise/permissions', 'Enterprise Permissions'],
    ['GET', '/api/v2/admin/enterprise/config', 'Enterprise Config'],
    ['GET', '/api/v2/admin/enterprise/config/secrets', 'Secrets Vault'],
    ['GET', '/api/v2/admin/enterprise/monitoring', 'System Monitoring'],
    ['GET', '/api/v2/admin/enterprise/monitoring/health', 'Health Check'],
    ['GET', '/api/v2/admin/enterprise/monitoring/logs', 'Error Logs'],

    // GDPR
    ['GET', '/api/v2/admin/enterprise/gdpr/dashboard', 'GDPR Dashboard'],
    ['GET', '/api/v2/admin/enterprise/gdpr/requests', 'GDPR Requests'],
    ['GET', '/api/v2/admin/enterprise/gdpr/consents', 'GDPR Consents'],
    ['GET', '/api/v2/admin/enterprise/gdpr/breaches', 'GDPR Breaches'],
    ['GET', '/api/v2/admin/enterprise/gdpr/audit', 'GDPR Audit'],

    // Legal Documents
    ['GET', '/api/v2/admin/legal-documents', 'Legal Documents'],

    // Broker
    ['GET', '/api/v2/admin/broker/dashboard', 'Broker Dashboard'],
    ['GET', '/api/v2/admin/broker/exchanges?limit=5', 'Broker Exchanges'],
    ['GET', '/api/v2/admin/broker/risk-tags', 'Risk Tags'],
    ['GET', '/api/v2/admin/broker/messages?limit=5', 'Broker Messages'],
    ['GET', '/api/v2/admin/broker/monitoring', 'User Monitoring'],
    ['GET', '/api/v2/admin/broker/configuration', 'Broker Config'],

    // Vetting
    ['GET', '/api/v2/admin/vetting/stats', 'Vetting Stats'],
    ['GET', '/api/v2/admin/vetting?limit=5', 'Vetting List'],

    // Newsletters
    ['GET', '/api/v2/admin/newsletters?limit=5', 'Newsletters List'],
    ['GET', '/api/v2/admin/newsletters/subscribers', 'Newsletter Subscribers'],
    ['GET', '/api/v2/admin/newsletters/segments', 'Newsletter Segments'],
    ['GET', '/api/v2/admin/newsletters/templates', 'Newsletter Templates'],
    ['GET', '/api/v2/admin/newsletters/analytics', 'Newsletter Analytics'],

    // Volunteering
    ['GET', '/api/v2/admin/volunteering', 'Volunteering Overview'],
    ['GET', '/api/v2/admin/volunteering/approvals', 'Volunteering Approvals'],
    ['GET', '/api/v2/admin/volunteering/organizations', 'Volunteering Orgs'],

    // Federation
    ['GET', '/api/v2/admin/federation/settings', 'Federation Settings'],
    ['GET', '/api/v2/admin/federation/partnerships', 'Federation Partnerships'],
    ['GET', '/api/v2/admin/federation/directory', 'Federation Directory'],
    ['GET', '/api/v2/admin/federation/directory/profile', 'Federation Profile'],
    ['GET', '/api/v2/admin/federation/analytics', 'Federation Analytics'],
    ['GET', '/api/v2/admin/federation/api-keys', 'Federation API Keys'],
    ['GET', '/api/v2/admin/federation/data', 'Federation Data Mgmt'],

    // Content (Pages, Menus, Plans)
    ['GET', '/api/v2/admin/pages?limit=5', 'CMS Pages'],
    ['GET', '/api/v2/admin/menus', 'CMS Menus'],
    ['GET', '/api/v2/admin/plans', 'Plans'],
    ['GET', '/api/v2/admin/subscriptions', 'Subscriptions'],

    // Tools
    ['GET', '/api/v2/admin/tools/redirects', 'Redirects'],
    ['GET', '/api/v2/admin/tools/404-errors', 'Error 404 Tracking'],
    ['GET', '/api/v2/admin/tools/webp-stats', 'WebP Stats'],
    ['GET', '/api/v2/admin/tools/blog-backups', 'Blog Backups'],
    ['GET', '/api/v2/admin/tools/seo-audit', 'SEO Audit'],

    // Deliverability
    ['GET', '/api/v2/admin/deliverability/dashboard', 'Deliverability Dashboard'],
    ['GET', '/api/v2/admin/deliverability?limit=5', 'Deliverables List'],
    ['GET', '/api/v2/admin/deliverability/analytics', 'Deliverability Analytics'],

    // Community Analytics
    ['GET', '/api/v2/admin/community-analytics', 'Community Analytics'],
    ['GET', '/api/v2/admin/community-analytics/geography', 'Community Geography'],

    // Impact Report
    ['GET', '/api/v2/admin/impact-report', 'Impact Report'],

    // Super Admin
    ['GET', '/api/v2/admin/super/dashboard', 'Super Dashboard'],
    ['GET', '/api/v2/admin/super/tenants', 'Super Tenants List'],
    ['GET', '/api/v2/admin/super/tenants/hierarchy', 'Tenant Hierarchy'],
    ['GET', '/api/v2/admin/super/users?limit=5', 'Super Users List'],
    ['GET', '/api/v2/admin/super/audit', 'Super Audit Log'],
    ['GET', '/api/v2/admin/super/federation', 'Super Federation'],
    ['GET', '/api/v2/admin/super/federation/system-controls', 'Federation System Controls'],
    ['GET', '/api/v2/admin/super/federation/whitelist', 'Federation Whitelist'],
    ['GET', '/api/v2/admin/super/federation/partnerships', 'Federation Partnerships (Super)'],
];

// Results storage
$results = [];
$pass = 0;
$fail = 0;
$total = count($endpoints);

echo "Testing $total admin API endpoints...\n";
echo str_repeat('=', 90) . "\n";
echo sprintf("%-4s %-40s %-6s %-8s %s\n", '#', 'Endpoint', 'Status', 'Time', 'Error');
echo str_repeat('-', 90) . "\n";

foreach ($endpoints as $i => [$method, $path, $label]) {
    $url = $baseUrl . $path;
    $start = microtime(true);

    $ch = curl_init($url);
    curl_setopt_array($ch, [
        CURLOPT_RETURNTRANSFER => true,
        CURLOPT_HTTPHEADER => [
            "Authorization: Bearer $token",
            "X-Tenant-ID: $tenantId",
            "Content-Type: application/json",
            "Accept: application/json",
        ],
        CURLOPT_TIMEOUT => 15,
        CURLOPT_FOLLOWLOCATION => true,
    ]);

    $response = curl_exec($ch);
    $statusCode = (int)curl_getinfo($ch, CURLINFO_HTTP_CODE);
    $elapsed = round((microtime(true) - $start) * 1000);

    if (curl_errno($ch)) {
        $response = 'CURL_ERROR: ' . curl_error($ch);
    }
    curl_close($ch);

    $error = '';
    $passed = ($statusCode >= 200 && $statusCode < 300);

    if (!$passed) {
        $decoded = json_decode($response, true);
        if ($decoded) {
            $error = $decoded['error'] ?? $decoded['message'] ?? '';
            if (isset($decoded['debug'])) {
                $error .= ' | ' . substr($decoded['debug'], 0, 100);
            }
        } else {
            $error = substr($response ?? 'No response', 0, 100);
        }
    }

    if ($passed) {
        $pass++;
        $status = "\033[32mPASS\033[0m";
    } else {
        $fail++;
        $status = "\033[31mFAIL\033[0m";
    }

    $num = $i + 1;
    echo sprintf("%-4d %-40s %s %3d  %5dms  %s\n", $num, $label, $status, $statusCode, $elapsed, substr($error, 0, 60));

    $results[] = [
        'label' => $label,
        'path' => $path,
        'status' => $statusCode,
        'passed' => $passed,
        'time_ms' => $elapsed,
        'error' => $error,
    ];
}

echo str_repeat('=', 90) . "\n\n";

// Summary
echo "RESULTS SUMMARY\n";
echo str_repeat('=', 40) . "\n";
echo "Total endpoints tested: $total\n";
echo "\033[32mPassed: $pass\033[0m\n";
echo "\033[31mFailed: $fail\033[0m\n";
$pct = $total > 0 ? round($pass / $total * 100) : 0;
echo "Pass rate: {$pct}%\n\n";

// Group failures by status code
if ($fail > 0) {
    echo "FAILURES BY STATUS CODE\n";
    echo str_repeat('-', 40) . "\n";

    $byStatus = [];
    foreach ($results as $r) {
        if (!$r['passed']) {
            $byStatus[$r['status']][] = $r;
        }
    }

    foreach ($byStatus as $code => $items) {
        echo "\n--- HTTP $code (" . count($items) . " endpoints) ---\n";
        foreach ($items as $item) {
            echo "  {$item['label']}: {$item['path']}\n";
            if ($item['error']) {
                echo "    Error: {$item['error']}\n";
            }
        }
    }
}

echo "\n\nDone.\n";
