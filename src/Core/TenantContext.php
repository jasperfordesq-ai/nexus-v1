<?php

namespace Nexus\Core;

class TenantContext
{
    private static $tenant = null;
    private static $basePath = '';

    /** @var int|null Tenant ID extracted from Bearer token (for mismatch detection) */
    private static $tokenTenantId = null;

    /** @var int|null Tenant ID from X-Tenant-ID header (for mismatch detection) */
    private static $headerTenantId = null;

    /**
     * Resolve the current tenant based on Path
     */
    public static function resolve()
    {
        // 1. Try to find tenant by DOMAIN first
        // If specific tenant domain (not master), enforce it.
        $host = $_SERVER['HTTP_HOST'] ?? '';
        if ($host) {
            // Strip www. prefix for consistent matching
            $host = preg_replace('/^www\./', '', $host);

            $db = Database::getConnection();
            $stmt = $db->prepare("SELECT * FROM tenants WHERE domain = ?");
            $stmt->execute([$host]);
            $domainTenant = $stmt->fetch();

            if ($domainTenant && $domainTenant['id'] != 1) {
                // Check if tenant is active
                if (empty($domainTenant['is_active'])) {
                    self::showInactiveTenantError($domainTenant['name'] ?? 'This community');
                    return;
                }

                // If it's a specific tenant domain (not Master), LOCK IT.
                // We do NOT allow path-based overrides on a tenant domain.
                self::$tenant = $domainTenant;
                self::$basePath = '';
                return;
            }
        }

        // 2. X-Tenant-ID Header Resolution (for API requests)
        // This allows stateless API clients to specify tenant without URL manipulation
        $headerTenantId = $_SERVER['HTTP_X_TENANT_ID'] ?? null;
        if ($headerTenantId !== null && is_numeric($headerTenantId)) {
            $headerTenantId = (int) $headerTenantId;
            self::$headerTenantId = $headerTenantId;

            // Extract tenant_id from Bearer token (if present) for mismatch detection
            $tokenTenantId = self::extractTenantIdFromBearerToken();
            if ($tokenTenantId !== null) {
                self::$tokenTenantId = $tokenTenantId;

                // Check for mismatch: header and token must agree if both present
                // EXCEPTION: Super admins can access any tenant (cross-tenant access)
                if ($tokenTenantId !== $headerTenantId) {
                    // Check if user is a super admin before rejecting
                    if (!self::isTokenUserSuperAdmin()) {
                        self::respondWithTenantMismatchError();
                        return;
                    }
                    // Super admin accessing different tenant - this is allowed
                }
            }

            // Validate the tenant ID exists
            $db = Database::getConnection();
            $stmt = $db->prepare("SELECT * FROM tenants WHERE id = ?");
            $stmt->execute([$headerTenantId]);
            $headerTenant = $stmt->fetch();

            if (!$headerTenant) {
                self::respondWithInvalidTenantError($headerTenantId);
                return;
            }

            // Check if tenant is active
            if (empty($headerTenant['is_active'])) {
                self::showInactiveTenantError($headerTenant['name'] ?? 'This community');
                return;
            }

            self::$tenant = $headerTenant;
            self::$basePath = '';
            return;
        }

        // 2.5. Bearer Token Tenant Resolution (fallback if no header)
        // For stateless requests where tenant is embedded in the token
        $tokenTenantId = self::extractTenantIdFromBearerToken();
        if ($tokenTenantId !== null) {
            self::$tokenTenantId = $tokenTenantId;

            // Only use token tenant if this looks like an API request without other resolution
            $requestUri = $_SERVER['REQUEST_URI'] ?? '';
            if (strpos($requestUri, '/api/') !== false) {
                $db = Database::getConnection();
                $stmt = $db->prepare("SELECT * FROM tenants WHERE id = ?");
                $stmt->execute([$tokenTenantId]);
                $tokenTenant = $stmt->fetch();

                if ($tokenTenant) {
                    if (empty($tokenTenant['is_active'])) {
                        self::showInactiveTenantError($tokenTenant['name'] ?? 'This community');
                        return;
                    }

                    self::$tenant = $tokenTenant;
                    self::$basePath = '';
                    return;
                }
            }
        }

        // 3. Path-Based Resolution (for Master/Platform Domain)
        $path = parse_url($_SERVER['REQUEST_URI'] ?? '/', PHP_URL_PATH);
        $segments = array_values(array_filter(explode('/', trim($path, '/'))));
        $firstSegment = $segments[0] ?? '';

        // Comprehensive Reserved List (Global Routes that belong to Master/System)
        // If a path starts with these, it is NOT a tenant slug.
        $reserved = [
            'login',
            'register',
            'dashboard',
            'admin',
            'super-admin',
            'logout',
            'api',
            'assets',
            'downloads',
            'uploads',
            'test-email',
            'sitemap.xml',
            'robots.txt',
            'cron',
            'mobile',
            'mobile-download',
            'home',
            'about',
            'contact',
            'terms',
            'how-it-works',
            'our-story',
            'impact-report',
            'guide',
            'timebanking-guide',
            'partner-with-us',
            'partner',
            'social-prescribing',
            'strategic-plan',
            'faq',
            'impact-summary',
            'migrate-messages',
            'legal',
            'newsletter',
            'onboarding',
            'post',
            'share-target',
            'accessibility',
            // Features (Global Routes handled by Master if no Tenant Slug)
            'wallet',
            'listings',
            'groups',
            'community-groups',
            'members',
            'profile',
            'reviews',
            'notifications',
            'connections',
            'messages',
            'compose', // Message Composition
            'events',
            'volunteering',
            'feed',
            'resources',
            'polls',
            'goals',
            'blog',
            'news', // Public News Alias
            'help', // New Help Center
            'search', // Unified Discovery Engine
            'proposals', // Governance Module
            'federation', // Multi-Tenant Federation
            'privacy',
            'password',
            'settings', // User Settings
            'dev', // Development tools (component library, storybook, etc.)
            'consent-required', // GDPR consent re-acceptance
            'consent', // GDPR consent accept/decline actions
        ];

        if (!empty($firstSegment) && !in_array($firstSegment, $reserved)) {
            $db = Database::getConnection();
            $stmt = $db->prepare("SELECT * FROM tenants WHERE slug = ?");
            $stmt->execute([$firstSegment]);
            $tenant = $stmt->fetch();

            if ($tenant) {
                // Check if tenant is active
                if (empty($tenant['is_active'])) {
                    self::showInactiveTenantError($tenant['name'] ?? 'This community');
                    return;
                }

                self::$tenant = $tenant;
                self::$basePath = '/' . $tenant['slug'];
                return;
            } else {
                // STRICT ISOLATION VS CUSTOM PAGES
                // Before 404ing, check if this is actually a known custom page for the Master Tenant
                // (Only applies if we are falling back to ID 1)
                $masterPagePath = __DIR__ . '/../../views/tenants/master/pages/' . $firstSegment . '.php';
                if (file_exists($masterPagePath)) {
                    // It's a custom page, not a tenant. Fallthrough to Master Logic.
                } else {
                    // STRICT ISOLATION:
                    // If path looks like a tenant slug but isn't one, and isn't a custom page...
                    http_response_code(404);
                    // Optional: Render a simple 404 view or text
                    echo "<h1>404 Not Found</h1><p>The requested tenant or page does not exist.</p>";
                    exit;
                }
            }
        }

        // 4. For reserved routes (admin, dashboard, etc.), use session tenant if available
        // This ensures admin areas use the logged-in user's tenant, not Master
        if (in_array($firstSegment, $reserved) && !empty($_SESSION['tenant_id'])) {
            $db = Database::getConnection();
            $stmt = $db->prepare("SELECT * FROM tenants WHERE id = ?");
            $stmt->execute([$_SESSION['tenant_id']]);
            $sessionTenant = $stmt->fetch();
            if ($sessionTenant) {
                // Check if tenant is active (except for super-admin routes)
                if (empty($sessionTenant['is_active']) && $firstSegment !== 'super-admin') {
                    self::showInactiveTenantError($sessionTenant['name'] ?? 'This community');
                    return;
                }

                self::$tenant = $sessionTenant;
                // Set basePath to tenant slug for non-master tenants so links work correctly
                // Master tenant (ID 1) uses empty basePath, other tenants use their slug
                self::$basePath = ($sessionTenant['id'] == 1) ? '' : '/' . $sessionTenant['slug'];
                return;
            }
        }

        // 5. Fallback: Master Tenant (ID 1)
        // This handles Root (/), Restricted Routes (/login, /about), and Master Domain usage.
        try {
            $db = Database::getConnection();
            $stmt = $db->query("SELECT * FROM tenants WHERE id = 1");
            $master = $stmt->fetch();
            if ($master) {
                self::$tenant = $master;
                self::$basePath = '';
                return;
            }
        } catch (\Exception $e) {
            // Fallback
        }

        // 6. Hard Fallback (if DB fails)
        self::$tenant = [
            'id' => 1,
            'name' => 'Project NEXUS',
            'features' => '{"listings": true, "groups": true, "blog": true}'
        ];
        self::$basePath = '';
    }

    public static function get()
    {
        if (self::$tenant === null) {
            self::resolve(); // Auto-resolve if not set
        }
        return self::$tenant;
    }

    public static function getId()
    {
        return self::get()['id'];
    }

    /**
     * Set tenant context by ID (for cron jobs, admin areas, etc.)
     */
    public static function setById($tenantId)
    {
        $db = Database::getConnection();
        $stmt = $db->prepare("SELECT * FROM tenants WHERE id = ?");
        $stmt->execute([$tenantId]);
        $tenant = $stmt->fetch();

        if ($tenant) {
            self::$tenant = $tenant;
            // Keep basePath as-is (empty for admin routes)
        }
    }

    public static function getBasePath()
    {
        return self::$basePath;
    }

    /**
     * Get a setting from tenant configuration
     *
     * @param string $key The configuration key (supports dot notation for nested values)
     * @param mixed $default Default value if key not found
     * @return mixed
     */
    public static function getSetting(string $key, $default = null)
    {
        $tenant = self::get();

        // Try tenant name for site_name
        if ($key === 'site_name') {
            return $tenant['name'] ?? $default;
        }

        // Try tenant domain for site_url
        if ($key === 'site_url') {
            $domain = $tenant['domain'] ?? '';
            if ($domain) {
                return 'https://' . $domain;
            }
            return $default;
        }

        // Check configuration JSON
        if (empty($tenant['configuration'])) {
            return $default;
        }

        $config = is_string($tenant['configuration'])
            ? json_decode($tenant['configuration'], true)
            : $tenant['configuration'];

        if (!is_array($config)) {
            return $default;
        }

        // Support dot notation (e.g., 'notifications.default_frequency')
        $keys = explode('.', $key);
        $value = $config;

        foreach ($keys as $k) {
            if (!is_array($value) || !array_key_exists($k, $value)) {
                return $default;
            }
            $value = $value[$k];
        }

        return $value;
    }

    public static function hasFeature($feature)
    {
        $tenant = self::get();
        if (empty($tenant['features'])) {
            return false;
        }

        $features = is_string($tenant['features'])
            ? json_decode($tenant['features'], true)
            : $tenant['features'];

        // Backwards compatibility: Blog is enabled by default if not strictly disabled
        if ($feature === 'blog' && !isset($features['blog'])) {
            return true;
        }

        return !empty($features[$feature]);
    }

    /**
     * Get the full domain URL for the current tenant
     *
     * @return string The full domain URL (e.g., 'https://hour-timebank.ie')
     */
    public static function getDomain()
    {
        $tenant = self::get();
        $domain = $tenant['domain'] ?? '';

        if ($domain) {
            return 'https://' . $domain;
        }

        // Fallback to site_url setting or construct from current host
        if (isset($_SERVER['HTTP_HOST'])) {
            $protocol = (!empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off') ? 'https' : 'http';
            return $protocol . '://' . $_SERVER['HTTP_HOST'];
        }

        return '';
    }

    /**
     * Get list of custom pages for the current tenant.
     * Scans views/tenants/{slug}/pages/ AND views/tenants/{slug}/{layout}/pages/
     * 
     * @param string|null $layout Optional layout name (e.g. 'modern')
     * @return array List of pages like [['url' => '/slug', 'name' => 'Name']]
     */
    public static function getCustomPages($layout = null)
    {
        $tenant = self::get();
        if (!$tenant || empty($tenant['slug'])) {
            return [];
        }

        $baseDir = __DIR__ . '/../../views/tenants/' . $tenant['slug'];
        $dirs = [];

        // 1. Standard Custom Pages (Shared)
        $dirs[] = $baseDir . '/pages';

        // 2. Layout Specific Pages (Overrides)
        if ($layout) {
            $dirs[] = $baseDir . '/' . $layout . '/pages';
        }

        $pages = [];
        $seen = [];

        foreach ($dirs as $dir) {
            if (!is_dir($dir)) continue;

            $files = glob($dir . '/*.php');
            foreach ($files as $file) {
                $slug = basename($file, '.php');

                // Avoid duplicates if a page exists in both (Layout takes precedence logically, but here we just list them)
                if (isset($seen[$slug])) continue;
                $seen[$slug] = true;

                // Convert "custom-page" to "Custom Page"
                $name = ucwords(str_replace('-', ' ', $slug));

                $pages[] = [
                    'url' => self::$basePath . '/' . $slug, // e.g. /hour-timebank/about
                    'name' => $name
                ];
            }
        }

        // Sort alphabetically by name
        usort($pages, function ($a, $b) {
            return strcmp($a['name'], $b['name']);
        });

        return $pages;
    }

    /**
     * Get the tenant ID from the X-Tenant-ID header (if provided)
     *
     * @return int|null
     */
    public static function getHeaderTenantId(): ?int
    {
        return self::$headerTenantId;
    }

    /**
     * Get the tenant ID from the Bearer token (if provided)
     *
     * @return int|null
     */
    public static function getTokenTenantId(): ?int
    {
        return self::$tokenTenantId;
    }

    /**
     * Extract tenant_id from Bearer token payload
     *
     * @return int|null
     */
    private static function extractTenantIdFromBearerToken(): ?int
    {
        $authHeader = $_SERVER['HTTP_AUTHORIZATION'] ?? $_SERVER['REDIRECT_HTTP_AUTHORIZATION'] ?? '';
        if (empty($authHeader) || !preg_match('/Bearer\s+(.+)$/i', $authHeader, $matches)) {
            return null;
        }

        $token = $matches[1];

        // Decode token payload without full validation (just to extract tenant_id)
        $parts = explode('.', $token);
        if (count($parts) !== 3) {
            return null;
        }

        $payloadEncoded = $parts[1];
        $remainder = strlen($payloadEncoded) % 4;
        if ($remainder) {
            $payloadEncoded .= str_repeat('=', 4 - $remainder);
        }

        $payload = json_decode(base64_decode(strtr($payloadEncoded, '-_', '+/')), true);
        if (!$payload || !isset($payload['tenant_id'])) {
            return null;
        }

        return (int) $payload['tenant_id'];
    }

    /**
     * Respond with JSON error for invalid tenant ID
     *
     * @param int $tenantId
     */
    private static function respondWithInvalidTenantError(int $tenantId): void
    {
        // Set a minimal tenant context so later code doesn't break
        self::$tenant = [
            'id' => 0,
            'name' => 'Invalid Tenant',
            'is_active' => 0,
            'features' => '{}'
        ];
        self::$basePath = '';

        header('Content-Type: application/json');
        http_response_code(400);
        echo json_encode([
            'data' => null,
            'errors' => [[
                'code' => ApiErrorCodes::INVALID_TENANT,
                'message' => 'Invalid tenant ID',
                'field' => null
            ]]
        ]);
        exit;
    }

    /**
     * Respond with JSON error for tenant mismatch (header vs token)
     */
    private static function respondWithTenantMismatchError(): void
    {
        // Set a minimal tenant context so later code doesn't break
        self::$tenant = [
            'id' => 0,
            'name' => 'Tenant Mismatch',
            'is_active' => 0,
            'features' => '{}'
        ];
        self::$basePath = '';

        header('Content-Type: application/json');
        http_response_code(403);
        echo json_encode([
            'data' => null,
            'errors' => [[
                'code' => ApiErrorCodes::TENANT_MISMATCH,
                'message' => 'Token tenant does not match requested tenant',
                'field' => null
            ]]
        ]);
        exit;
    }

    /**
     * Check if the user from the Bearer token is a super admin
     * Super admins can access any tenant regardless of their home tenant
     *
     * @return bool True if user is a super admin
     */
    private static function isTokenUserSuperAdmin(): bool
    {
        // Get Authorization header
        $authHeader = $_SERVER['HTTP_AUTHORIZATION'] ?? '';
        if (empty($authHeader) || !preg_match('/^Bearer\s+(.+)$/i', $authHeader, $matches)) {
            return false;
        }

        $token = $matches[1];

        try {
            // Decode the JWT to check claims
            // Use the TokenService if available, otherwise decode manually
            if (class_exists('\\Nexus\\Services\\TokenService')) {
                $payload = \Nexus\Services\TokenService::validateToken($token);
                $userId = $payload['user_id'] ?? $payload['sub'] ?? null;
                if ($payload && $userId) {
                    // Look up user to check super admin status
                    $db = Database::getConnection();
                    $stmt = $db->prepare("SELECT is_super_admin, is_tenant_super_admin, role FROM users WHERE id = ?");
                    $stmt->execute([$userId]);
                    $user = $stmt->fetch();

                    if ($user) {
                        return !empty($user['is_super_admin'])
                            || !empty($user['is_tenant_super_admin'])
                            || in_array($user['role'] ?? '', ['tenant_admin', 'admin'], true);
                    }
                }
            }
        } catch (\Exception $e) {
            // Token validation failed - not a super admin
            return false;
        }

        return false;
    }

    /**
     * Show error page for inactive tenants
     */
    private static function showInactiveTenantError(string $tenantName): void
    {
        http_response_code(503);

        // Set a minimal tenant context so the app doesn't break
        self::$tenant = [
            'id' => 0,
            'name' => $tenantName,
            'is_active' => 0,
            'features' => '{}'
        ];
        self::$basePath = '';

        echo '<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Community Unavailable</title>
    <style>
        * { margin: 0; padding: 0; box-sizing: border-box; }
        body {
            font-family: -apple-system, BlinkMacSystemFont, "Segoe UI", Roboto, sans-serif;
            background: linear-gradient(135deg, #1a1a2e 0%, #16213e 100%);
            min-height: 100vh;
            display: flex;
            align-items: center;
            justify-content: center;
            color: #fff;
        }
        .container {
            text-align: center;
            padding: 2rem;
            max-width: 500px;
        }
        .icon {
            font-size: 4rem;
            margin-bottom: 1.5rem;
            opacity: 0.8;
        }
        h1 {
            font-size: 1.75rem;
            margin-bottom: 1rem;
            font-weight: 600;
        }
        p {
            color: rgba(255,255,255,0.7);
            line-height: 1.6;
            margin-bottom: 1.5rem;
        }
        .tenant-name {
            color: #a78bfa;
            font-weight: 500;
        }
        a {
            display: inline-block;
            background: #6366f1;
            color: #fff;
            padding: 0.75rem 1.5rem;
            border-radius: 8px;
            text-decoration: none;
            font-weight: 500;
            transition: background 0.2s;
        }
        a:hover { background: #4f46e5; }
    </style>
</head>
<body>
    <div class="container">
        <div class="icon">🔒</div>
        <h1>Community Unavailable</h1>
        <p><span class="tenant-name">' . htmlspecialchars($tenantName) . '</span> is currently inactive and not accepting visitors.</p>
        <p>If you believe this is an error, please contact the community administrator.</p>
        <a href="/">Return to Home</a>
    </div>
</body>
</html>';
        exit;
    }
}
