<?php

// Copyright © 2024–2026 Jasper Ford
// SPDX-License-Identifier: AGPL-3.0-or-later
// Author: Jasper Ford
// See NOTICE file for attribution and acknowledgements.

namespace App\Core;

use Illuminate\Support\Facades\DB;

/**
 * TenantContext — the authoritative multi-tenant resolution engine.
 *
 * Resolves the current tenant through 7 strategies (in priority order):
 *   1. Domain match
 *   2. X-Tenant-ID header
 *   3. X-Tenant-Slug header
 *   4. Bearer token tenant_id (API fallback)
 *   5. URL path slug
 *   6. Session tenant_id (reserved routes)
 *   7. Fallback to Master tenant (ID 1)
 *
 * All public methods are static to maintain backward compatibility with
 */
class TenantContext
{
    private static $tenant = null;
    private static $basePath = '';

    /**
     * Per-request memoized tenant ID. Avoids repeated array dereference of
     * self::$tenant['id'] on every getId() call (called thousands of times
     * per request from tenant-scoped queries). Cleared by setById/reset.
     *
     * Static is request-scoped under PHP-FPM/mod_php, but Octane-style
     * persistent workers MUST call reset() between requests.
     */
    private static ?int $cachedId = null;

    /** @var int|null Tenant ID extracted from Bearer token (for mismatch detection) */
    private static $tokenTenantId = null;

    /** @var int|null Tenant ID from X-Tenant-ID header (for mismatch detection) */
    private static $headerTenantId = null;

    /**
     * Query a single tenant row and return as associative array (or false/null).
     *
     * @param string $column Column to match
     * @param mixed  $value  Value to match
     * @return array|null Associative array of tenant data, or null if not found
     */
    private static function fetchTenant(string $column, $value): ?array
    {
        $row = DB::table('tenants')->where($column, $value)->first();
        return $row ? (array) $row : null;
    }

    /**
     * Resolve the current tenant based on Path
     */
    public static function resolve()
    {
        // 1. Try to find tenant by DOMAIN first
        // If specific tenant domain (not master), enforce it.
        $host = $_SERVER['HTTP_HOST'] ?? '';
        if ($host) {
            // Strip optional port (e.g. "example.com:8080" → "example.com")
            if (strpos($host, ':') !== false) {
                $host = explode(':', $host, 2)[0];
            }
            // Strip www. prefix for consistent matching
            $host = preg_replace('/^www\./', '', (string) $host);

            // SECURITY: Validate the Host header is a real hostname before hitting
            // the DB. An attacker-controlled Host header (or header injection via
            // a broken reverse proxy) could otherwise feed garbage into our
            // tenants.domain lookup or trigger log-injection downstream.
            $hostIsValid = is_string($host)
                && $host !== ''
                && filter_var($host, FILTER_VALIDATE_DOMAIN, FILTER_FLAG_HOSTNAME) !== false;

            $domainTenant = $hostIsValid ? self::fetchTenant('domain', $host) : null;

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
            $headerTenant = self::fetchTenant('id', $headerTenantId);

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

        // 2.3. X-Tenant-Slug Header Resolution (for mobile/SPA API requests)
        // Allows clients to specify tenant by slug rather than numeric ID.
        // Same security model as X-Tenant-ID: token tenant must match unless superadmin.
        $headerSlug = $_SERVER['HTTP_X_TENANT_SLUG'] ?? null;
        if ($headerSlug !== null && $headerSlug !== '') {
            $slugTenant = self::fetchTenant('slug', trim($headerSlug));

            if ($slugTenant) {
                $slugTenantId = (int)$slugTenant['id'];

                // Check for token mismatch — same logic as X-Tenant-ID step
                $tokenTenantId = self::extractTenantIdFromBearerToken();
                if ($tokenTenantId !== null) {
                    self::$tokenTenantId = $tokenTenantId;
                    if ($tokenTenantId !== $slugTenantId && !self::isTokenUserSuperAdmin()) {
                        self::respondWithTenantMismatchError();
                        return;
                    }
                }

                if (empty($slugTenant['is_active'])) {
                    self::showInactiveTenantError($slugTenant['name'] ?? 'This community');
                    return;
                }

                self::$tenant = $slugTenant;
                self::$basePath = '';
                return;
            }
            // Unknown slug — fall through to other resolution methods
        }

        // 2.5. Bearer Token Tenant Resolution (fallback if no header)
        // For stateless requests where tenant is embedded in the token
        $tokenTenantId = self::extractTenantIdFromBearerToken();
        if ($tokenTenantId !== null) {
            self::$tokenTenantId = $tokenTenantId;

            // Only use token tenant if this looks like an API request without other resolution
            $requestUri = $_SERVER['REQUEST_URI'] ?? '';
            if (strpos($requestUri, '/api/') !== false) {
                $tokenTenant = self::fetchTenant('id', $tokenTenantId);

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
            'admin-legacy',
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
            $tenant = self::fetchTenant('slug', $firstSegment);

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
                // STRICT ISOLATION:
                // If path looks like a tenant slug but isn't one, 404.
                // (Legacy custom-page fallthrough removed — views/ is decommissioned.)
                if (($_ENV['APP_ENV'] ?? getenv('APP_ENV')) === 'testing' || (function_exists('app') && app()->environment('testing'))) {
                    throw new \Symfony\Component\HttpKernel\Exception\HttpException(404, 'The requested tenant or page does not exist.');
                }
                http_response_code(404);
                echo "<h1>404 Not Found</h1><p>The requested tenant or page does not exist.</p>";
                exit;
            }
        }

        // 4. For reserved routes (admin, dashboard, etc.), use session tenant if available
        // This ensures admin areas use the logged-in user's tenant, not Master
        if (in_array($firstSegment, $reserved) && !empty($_SESSION['tenant_id'])) {
            $sessionTenant = self::fetchTenant('id', $_SESSION['tenant_id']);
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
            $master = self::fetchTenant('id', 1);
            if ($master) {
                self::$tenant = $master;
                self::$basePath = '';
                return;
            }
        } catch (\Exception $e) {
            // Fallback
        }

        // 6. Hard Fallback (if DB fails) — uses TenantFeatureConfig defaults.
        // This path should never run in healthy production — if it does, the
        // database lookup at step 5 has failed. Log so ops sees it.
        try {
            \Illuminate\Support\Facades\Log::warning(
                'TenantContext hard-fallback to synthetic Master tenant — DB unavailable',
                [
                    'host' => $_SERVER['HTTP_HOST'] ?? null,
                    'uri'  => $_SERVER['REQUEST_URI'] ?? null,
                ]
            );
        } catch (\Throwable $e) {
            // If even logging fails, swallow — we still need to render something.
        }
        self::$tenant = [
            'id' => 1,
            'name' => 'Project NEXUS',
            'features' => json_encode(\App\Services\TenantFeatureConfig::FEATURE_DEFAULTS)
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
        if (self::$cachedId !== null) {
            return self::$cachedId;
        }
        $id = self::get()['id'] ?? null;
        if ($id !== null) {
            self::$cachedId = (int) $id;
        }
        return $id;
    }

    /**
     * Reset all per-request tenant state. Required for Octane/Swoole-style
     * persistent workers; safe no-op under PHP-FPM/mod_php.
     */
    public static function reset(): void
    {
        self::$tenant = null;
        self::$basePath = '';
        self::$cachedId = null;
        self::$tokenTenantId = null;
        self::$headerTenantId = null;
    }

    /**
     * Set tenant context by ID (for cron jobs, admin areas, etc.)
     *
     * @param int $tenantId
     * @return bool True if tenant was found and set, false otherwise
     */
    public static function setById($tenantId): bool
    {
        $tenant = self::fetchTenant('id', $tenantId);

        if ($tenant) {
            self::$tenant = $tenant;
            self::$cachedId = (int) $tenant['id'];
            // Keep basePath as-is (empty for admin routes)
            return true;
        }

        \Illuminate\Support\Facades\Log::warning("TenantContext::setById() — tenant ID {$tenantId} not found");
        return false;
    }

    public static function getBasePath()
    {
        return self::$basePath;
    }

    /**
     * Get the tenant slug prefix for building frontend URLs (e.g. "/hour-timebank").
     * Unlike getBasePath(), this always derives the prefix from the tenant slug,
     * so it works correctly even in API contexts where basePath is empty.
     *
     * @return string The slug prefix (e.g. "/hour-timebank") or empty string for master tenant
     */
    public static function getSlugPrefix(): string
    {
        $tenant = self::get();

        // Custom-domain tenants don't need slug in URLs — tenant is identified by domain
        if (!empty($tenant['domain']) && ($tenant['id'] ?? 0) > 1) {
            return '';
        }

        $slug = $tenant['slug'] ?? '';
        return $slug ? '/' . $slug : '';
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

    /**
     * Get the frontend URL for user-facing links (emails, notifications).
     * Priority: FRONTEND_URL env -> tenant site_url -> APP_URL env -> fallback.
     *
     * FRONTEND_URL is checked first because tenant site_url may point to the
     * legacy PHP domain (e.g. hour-timebank.ie) while the React frontend
     * lives at app.project-nexus.ie.
     */
    public static function getFrontendUrl(): string
    {
        // 1. Tenant custom domain (highest priority — e.g. pairc-goodman.com)
        try {
            $tenant = self::get();
            if (!empty($tenant['domain']) && ($tenant['id'] ?? 0) > 1) {
                return 'https://' . rtrim($tenant['domain'], '/');
            }
        } catch (\Throwable $e) {
            // Tenant not resolved yet — fall through
        }

        // 2. Explicit FRONTEND_URL env var (React app URL)
        $frontendUrl = Env::get('FRONTEND_URL');
        if ($frontendUrl) {
            return rtrim($frontendUrl, '/');
        }

        // 3. Tenant setting (may be legacy PHP domain — use as fallback)
        $siteUrl = self::getSetting('site_url');
        if ($siteUrl) {
            return rtrim($siteUrl, '/');
        }

        // 4. Fallback to APP_URL (may be API domain — not ideal)
        $appUrl = Env::get('APP_URL');
        if ($appUrl) {
            return rtrim($appUrl, '/');
        }

        return 'https://app.project-nexus.ie';
    }

    /**
     * Get the tenant's preferred payment currency (ISO 4217, lowercase).
     *
     * Resolution order:
     *   1. tenant_settings table key `default_currency` (per-tenant override)
     *   2. tenants.configuration JSON `general.currency` (legacy/alt override)
     *   3. config('stripe.default_currency') / STRIPE_DEFAULT_CURRENCY env
     *   4. hardcoded 'eur' fallback
     *
     * @return string Lowercase 3-letter ISO currency code (e.g. 'eur', 'usd', 'gbp')
     */
    public static function getCurrency(): string
    {
        $currency = null;

        // 1. Per-tenant override in tenant_settings key-value table.
        // Two key conventions exist historically — check both:
        //   - 'general.default_currency' (AdminSettingsController)
        //   - 'default_currency'         (AdminConfigController)
        try {
            $tenantId = (int) (self::get()['id'] ?? 0);
            if ($tenantId > 0 && class_exists(\App\Services\TenantSettingsService::class)) {
                foreach (['general.default_currency', 'default_currency'] as $settingKey) {
                    $value = app(\App\Services\TenantSettingsService::class)->get($tenantId, $settingKey);
                    if (is_string($value) && strlen(trim($value)) === 3) {
                        $currency = trim($value);
                        break;
                    }
                }
            }
        } catch (\Throwable $e) {
            // Ignore — fall through to other sources
        }

        // 2. Fallback to tenants.configuration JSON (general.currency)
        if (!$currency) {
            $json = self::getSetting('general.currency');
            if (is_string($json) && strlen(trim($json)) === 3) {
                $currency = trim($json);
            }
        }

        // 3. Fallback to config / env
        if (!$currency) {
            // config path is services.stripe.default_currency (top-level 'stripe'
            // doesn't exist as a standalone config file).
            $currency = (string) config('services.stripe.default_currency', Env::get('STRIPE_DEFAULT_CURRENCY', 'eur'));
        }

        // 4. Final guard
        $currency = strtolower(trim($currency));
        return strlen($currency) === 3 ? $currency : 'eur';
    }

    public static function hasFeature($feature)
    {
        $tenant = self::get();

        $dbFeatures = null;
        if (!empty($tenant['features'])) {
            $dbFeatures = is_string($tenant['features'])
                ? json_decode($tenant['features'], true)
                : $tenant['features'];
        }

        // Merge with defaults so new tenants (features=NULL) get correct defaults
        $features = self::mergeFeatureDefaults($dbFeatures);

        return !empty($features[$feature]);
    }

    public static function hasModule(string $module): bool
    {
        $modules = self::getSetting('modules');
        $modules = is_array($modules) ? $modules : null;
        $effectiveModules = \App\Services\TenantFeatureConfig::mergeModules($modules);

        return !empty($effectiveModules[$module]);
    }

    /**
     * Merge DB feature flags with defaults so new/null tenants get correct values.
     */
    private static function mergeFeatureDefaults(?array $dbFeatures): array
    {
        $result = \App\Services\TenantFeatureConfig::FEATURE_DEFAULTS;

        if ($dbFeatures === null) {
            return $result;
        }

        foreach ($dbFeatures as $key => $value) {
            $result[$key] = (bool) $value;
        }

        return $result;
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
     * Custom-pages helper retained for API compatibility but always returns
     * empty: legacy views/ tree is decommissioned and the React frontend owns
     * all UI now. Callers should treat an empty array as "no custom pages".
     *
     * @param string|null $layout Unused (kept for signature stability).
     * @return array Always [].
     */
    public static function getCustomPages($layout = null)
    {
        return [];
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
     * Extract tenant_id from Bearer token payload.
     *
     * SECURITY: When TokenService is available the JWT signature is validated
     * before any claim is trusted. A forged or tampered token returns null,
     * preventing tenant-ID spoofing via crafted Bearer tokens.
     *
     * During very early bootstrap (before the service container is ready)
     * TokenService may not be resolvable; in that case we fall back to an
     * unvalidated decode. This is safe because auth:sanctum middleware
     * performs full signature validation later in the request lifecycle and
     * will reject any request whose token does not verify — this method only
     * provides an early-routing hint.
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

        // Preferred path: validate signature before trusting claims
        if (class_exists(\App\Services\TokenService::class)) {
            try {
                $tokenService = new \App\Services\TokenService();
                $payload = $tokenService->validateToken($token);
                if (!$payload || !isset($payload['tenant_id'])) {
                    return null;
                }
                return (int) $payload['tenant_id'];
            } catch (\Throwable $e) {
                // Invalid/malformed token — auth middleware will handle the 401
                return null;
            }
        }

        // Fallback (early bootstrap only): decode without signature validation.
        // The auth:sanctum middleware enforces full validation on every request.
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

        if (($_ENV['APP_ENV'] ?? getenv('APP_ENV')) === 'testing' || (function_exists('app') && app()->environment('testing'))) {
            throw new \Symfony\Component\HttpKernel\Exception\HttpException(400, json_encode([
                'data' => null,
                'errors' => [['code' => ApiErrorCodes::INVALID_TENANT, 'message' => 'Invalid tenant ID', 'field' => null]]
            ]));
        }
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

        if (($_ENV['APP_ENV'] ?? getenv('APP_ENV')) === 'testing' || (function_exists('app') && app()->environment('testing'))) {
            throw new \Symfony\Component\HttpKernel\Exception\HttpException(403, json_encode([
                'data' => null,
                'errors' => [['code' => ApiErrorCodes::TENANT_MISMATCH, 'message' => 'Token tenant does not match requested tenant', 'field' => null]]
            ]));
        }
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
            // Use the App TokenService (instance method) if available
            if (class_exists(\App\Services\TokenService::class)) {
                $tokenService = new \App\Services\TokenService();
                $payload = $tokenService->validateToken($token);
                $userId = $payload['user_id'] ?? $payload['sub'] ?? null;
                if ($payload && $userId) {
                    // Look up user to check super admin status
                    $userRow = DB::table('users')
                        ->where('id', $userId)
                        ->first(['is_super_admin', 'is_god', 'is_tenant_super_admin', 'role']);

                    if ($userRow) {
                        // SECURITY: Only actual super admins can access cross-tenant data.
                        // Regular admins (role=admin, role=tenant_admin) must NOT be allowed
                        // to spoof X-Tenant-ID headers to access other tenants' data.
                        return !empty($userRow->is_super_admin)
                            || !empty($userRow->is_god)
                            || in_array(($userRow->role ?? ''), ['super_admin', 'god'], true);
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
        if (($_ENV['APP_ENV'] ?? getenv('APP_ENV')) === 'testing' || (function_exists('app') && app()->environment('testing'))) {
            throw new \Symfony\Component\HttpKernel\Exception\HttpException(503, 'Community Unavailable: ' . $tenantName);
        }
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
        <div class="icon">&#x1F512;</div>
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
