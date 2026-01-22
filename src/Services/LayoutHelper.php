<?php

namespace Nexus\Services;

use Nexus\Core\Database;

/**
 * LayoutHelper - Centralized layout detection and management
 *
 * UNIFIED SESSION KEY: Uses ONLY 'nexus_active_layout' (the legacy key is removed)
 * DATABASE PERSISTENCE: Logged-in users have their preference stored in the DB
 *
 * This is the Single Source of Truth for layout detection across the application.
 */
class LayoutHelper
{
    /**
     * Valid layout names
     */
    private const VALID_LAYOUTS = ['modern', 'civicone'];

    /**
     * Default layout
     */
    private const DEFAULT_LAYOUT = 'modern';

    /**
     * THE session key prefix - tenant ID will be appended for isolation
     */
    private const SESSION_KEY_PREFIX = 'nexus_active_layout';

    /**
     * Get session key for layout
     *
     * NOTE: Using a single session key (not tenant-specific) because:
     * 1. API routes like /api/layout-switch don't always have tenant context
     * 2. User's layout preference should be consistent across the same browser session
     * 3. Tenant isolation happens at the domain level, not session key level
     *
     * @return string Session key (nexus_active_layout)
     */
    private static function getSessionKey(): string
    {
        return self::SESSION_KEY_PREFIX;
    }

    /**
     * Runtime override layout (not persisted to session or DB)
     */
    private static ?string $runtimeOverride = null;

    /**
     * Cache to avoid repeated DB queries in single request
     */
    private static ?string $cachedLayout = null;

    /**
     * Get the current active layout
     *
     * Priority order:
     * 1. Runtime override (testing only)
     * 2. User's saved preference from DB (if logged in)
     * 3. Session value (for anonymous users who switched)
     * 4. Tenant's default layout
     * 5. Hardcoded 'modern'
     *
     * @return string The active layout name (modern or civicone)
     */
    public static function get(): string
    {
        // 1. Runtime override (for testing/preview only)
        if (self::$runtimeOverride !== null) {
            return self::$runtimeOverride;
        }

        // 2. Already determined this request? Return cached value
        if (self::$cachedLayout !== null) {
            return self::$cachedLayout;
        }

        // Ensure session is started
        if (session_status() === PHP_SESSION_NONE) {
            session_start();
        }

        $layout = null;
        $sessionKey = self::getSessionKey();

        // 3. Logged-in user? Use their saved preference from DB
        if (!empty($_SESSION['user_id'])) {
            $layout = self::getFromDatabase((int) $_SESSION['user_id']);
        }

        // 4. No user preference? Check session (for anonymous users who switched)
        if ($layout === null && isset($_SESSION[$sessionKey])) {
            $sessionLayout = self::sanitize($_SESSION[$sessionKey]);
            if (self::isValid($sessionLayout)) {
                $layout = $sessionLayout;
            }
        }

        // 5. No session? Use tenant's default
        if ($layout === null) {
            $layout = self::getTenantDefaultLayout();
        }

        // 6. No tenant default? Use hardcoded default
        if ($layout === null) {
            $layout = self::DEFAULT_LAYOUT;
        }

        // Cache for this request only
        self::$cachedLayout = $layout;

        return $layout;
    }

    /**
     * Get the tenant's default layout from the database
     *
     * @return string|null The tenant's default layout, or null if not set
     */
    private static function getTenantDefaultLayout(): ?string
    {
        try {
            // Use TenantContext to get current tenant's default_layout
            if (class_exists('\Nexus\Core\TenantContext')) {
                $tenant = \Nexus\Core\TenantContext::get();
                if ($tenant && !empty($tenant['default_layout'])) {
                    $layout = self::sanitize($tenant['default_layout']);
                    if (self::isValid($layout)) {
                        return $layout;
                    }
                }
            }
        } catch (\Exception $e) {
            // Silently fail - use hardcoded default
        }

        return null;
    }

    /**
     * Set the active layout
     *
     * For logged-in users, this also persists to the database.
     *
     * @param string $layout The layout to set
     * @param bool $persistToDb Whether to save to database (default: true for logged-in users)
     * @return bool True if set successfully, false if invalid
     */
    public static function set(string $layout, bool $persistToDb = true): bool
    {
        // Sanitize and validate
        $layout = self::sanitize($layout);

        if (!self::isValid($layout)) {
            return false;
        }

        // Ensure session is started
        if (session_status() === PHP_SESSION_NONE) {
            session_start();
        }

        // Set in session (THE single key)
        $_SESSION[self::getSessionKey()] = $layout;

        // Clear the legacy key if it exists (cleanup)
        unset($_SESSION['nexus_layout']);

        // Update cache
        self::$cachedLayout = $layout;

        // Persist to database for logged-in users
        if ($persistToDb && !empty($_SESSION['user_id'])) {
            self::saveToDatabase((int) $_SESSION['user_id'], $layout);
        }

        return true;
    }

    /**
     * Get layout preference from database
     *
     * @param int $userId The user ID
     * @return string|null The layout or null if not found
     */
    public static function getFromDatabase(int $userId): ?string
    {
        try {
            $result = Database::query(
                "SELECT preferred_layout FROM users WHERE id = ? LIMIT 1",
                [$userId]
            )->fetch();

            if ($result && !empty($result['preferred_layout'])) {
                $layout = self::sanitize($result['preferred_layout']);
                return self::isValid($layout) ? $layout : null;
            }
        } catch (\Throwable $e) {
            error_log("LayoutHelper::getFromDatabase() error: " . $e->getMessage());
        }

        return null;
    }

    /**
     * Save layout preference to database
     *
     * @param int $userId The user ID
     * @param string $layout The layout to save
     * @return bool True if saved successfully
     */
    public static function saveToDatabase(int $userId, string $layout): bool
    {
        $layout = self::sanitize($layout);

        if (!self::isValid($layout)) {
            return false;
        }

        try {
            Database::query(
                "UPDATE users SET preferred_layout = ? WHERE id = ?",
                [$layout, $userId]
            );
            return true;
        } catch (\Throwable $e) {
            error_log("LayoutHelper::saveToDatabase() error: " . $e->getMessage());
            return false;
        }
    }

    /**
     * Initialize layout for a user session (call this on login)
     *
     * This is THE method to call when a user logs in.
     * It pulls from DB and sets the session.
     *
     * @param int $userId The user ID
     * @return string The layout that was set
     */
    public static function initializeForUser(int $userId): string
    {
        if (session_status() === PHP_SESSION_NONE) {
            session_start();
        }

        // Get from database
        $layout = self::getFromDatabase($userId);

        if ($layout === null) {
            $layout = self::DEFAULT_LAYOUT;
            // Save default to DB so it's set for next time
            self::saveToDatabase($userId, $layout);
        }

        // Set in session
        $_SESSION[self::getSessionKey()] = $layout;

        // Clear legacy key
        unset($_SESSION['nexus_layout']);

        // Update cache
        self::$cachedLayout = $layout;

        return $layout;
    }

    /**
     * Set a runtime-only layout override (not persisted)
     *
     * Use for preview mode or testing.
     *
     * @param string $layout The layout to force for this request
     */
    public static function setRuntimeOverride(string $layout): void
    {
        $layout = self::sanitize($layout);
        if (self::isValid($layout)) {
            self::$runtimeOverride = $layout;
        }
    }

    /**
     * Clear runtime override
     */
    public static function clearRuntimeOverride(): void
    {
        self::$runtimeOverride = null;
    }

    /**
     * Check if a layout is valid
     *
     * @param string $layout The layout to check
     * @return bool True if valid
     */
    public static function isValid(string $layout): bool
    {
        return in_array($layout, self::VALID_LAYOUTS, true);
    }

    /**
     * Sanitize layout name
     *
     * @param string $layout The layout to sanitize
     * @return string The sanitized layout
     */
    public static function sanitize(string $layout): string
    {
        return preg_replace('/[^a-z-]/', '', strtolower($layout));
    }

    /**
     * Check if a custom layout is active (not the default)
     *
     * @return bool True if the layout is not the default (modern)
     */
    public static function isCustom(): bool
    {
        return self::get() !== self::DEFAULT_LAYOUT;
    }

    /**
     * Reset to default layout (Modern)
     *
     * @param bool $persistToDb Whether to save to database
     */
    public static function reset(bool $persistToDb = true): void
    {
        self::set(self::DEFAULT_LAYOUT, $persistToDb);
        self::$runtimeOverride = null;
    }

    /**
     * Force default layout for this request only
     * Use when you need to ensure Modern loads regardless of session/DB
     */
    public static function forceDefault(): void
    {
        self::$runtimeOverride = self::DEFAULT_LAYOUT;
    }

    /**
     * Get all valid layouts
     *
     * @return array Array of valid layout names
     */
    public static function getValidLayouts(): array
    {
        return self::VALID_LAYOUTS;
    }

    /**
     * Get the default layout (tenant-aware)
     *
     * @return string The default layout name (tenant default or hardcoded default)
     */
    public static function getDefault(): string
    {
        $tenantDefault = self::getTenantDefaultLayout();
        return $tenantDefault ?? self::DEFAULT_LAYOUT;
    }

    /**
     * Get the current session key being used (for debugging)
     *
     * @return string The session key (tenant-specific)
     */
    public static function getCurrentSessionKey(): string
    {
        return self::getSessionKey();
    }

    /**
     * Clear internal cache (useful for testing)
     */
    public static function clearCache(): void
    {
        self::$cachedLayout = null;
        self::$runtimeOverride = null;
    }

    /**
     * Generate AJAX-friendly layout switch response
     *
     * @param string $targetLayout The layout to switch to
     * @return array Response array for JSON encoding
     */
    public static function generateSwitchResponse(string $targetLayout): array
    {
        $success = self::set($targetLayout);

        return [
            'success' => $success,
            'layout' => $success ? self::get() : null,
            'message' => $success
                ? "Switched to {$targetLayout} layout"
                : "Invalid layout: {$targetLayout}",
            'persisted' => $success && !empty($_SESSION['user_id'])
        ];
    }

    /**
     * Handle layout switching from request (POST only)
     *
     * @return bool True if layout was changed
     */
    public static function handleLayoutSwitch(): bool
    {
        if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
            return false;
        }

        $newLayout = $_POST['switch_layout'] ?? null;

        if ($newLayout && self::set($newLayout)) {
            return true;
        }

        return false;
    }

    /**
     * Add layout parameter to URL (DEPRECATED)
     *
     * Layout persistence is now handled via session + database.
     * This method is kept for backward compatibility but does nothing.
     *
     * @param string $url The base URL
     * @return string The URL unchanged
     * @deprecated Session + DB handles persistence now
     */
    public static function preserveLayoutInUrl(string $url): string
    {
        return $url;
    }

    /**
     * Get custom layout CSS (stub method)
     *
     * This method was referenced in header templates but never implemented.
     * Returns empty string to prevent fatal errors on older deployed code.
     *
     * @return string Empty string (no custom CSS)
     * @deprecated This feature was never completed - will be removed in future
     */
    public static function getCustomLayoutCSS(): string
    {
        return '';
    }

    /**
     * Migrate from dual session keys (cleanup helper)
     *
     * Call this once per session to clean up legacy keys.
     */
    public static function migrateSessionKeys(): void
    {
        if (session_status() === PHP_SESSION_NONE) {
            session_start();
        }

        // If legacy key exists but new key doesn't, migrate
        if (isset($_SESSION['nexus_layout']) && !isset($_SESSION[self::getSessionKey()])) {
            $legacyLayout = self::sanitize($_SESSION['nexus_layout']);
            if (self::isValid($legacyLayout)) {
                $_SESSION[self::getSessionKey()] = $legacyLayout;
            }
        }

        // Always clean up legacy key
        unset($_SESSION['nexus_layout']);
    }
}
