<?php

namespace Nexus\Core;

class TenantContext
{
    private static $tenant = null;
    private static $basePath = '';

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

        // 2. Path-Based Resolution (for Master/Platform Domain)
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

        // 2.5 For reserved routes (admin, dashboard, etc.), use session tenant if available
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

        // 3. Fallback: Master Tenant (ID 1)
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

        // 4. Hard Fallback (if DB fails)
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
