<?php

namespace Nexus\Services;

use Nexus\Core\TenantContext;

/**
 * LayoutValidator - Layout validation and switching service
 *
 * Provides validation, access control, and switching for different layouts.
 */
class LayoutValidator
{
    /**
     * Valid layouts configuration
     */
    private const LAYOUTS = [
        'modern' => [
            'name' => 'Modern',
            'slug' => 'modern',
            'access' => 'public',
            'description' => 'Modern default layout'
        ],
        'civicone' => [
            'name' => 'CivicOne',
            'slug' => 'civicone',
            'access' => 'public',
            'description' => 'CivicOne community layout'
        ],
    ];

    /**
     * Get the current active layout
     *
     * @return string The active layout slug
     */
    public static function getCurrentLayout(): string
    {
        return LayoutHelper::get();
    }

    /**
     * Get all available layouts with access information
     *
     * @return array Array of layout configurations
     */
    public static function getAvailableLayouts(): array
    {
        return array_values(self::LAYOUTS);
    }

    /**
     * Get layout information by slug
     *
     * @param string $slug The layout slug
     * @return array|null Layout configuration or null if not found
     */
    public static function getLayout(string $slug): ?array
    {
        return self::LAYOUTS[$slug] ?? null;
    }

    /**
     * Check if a layout is valid
     *
     * @param string $slug The layout slug to validate
     * @return bool True if valid, false otherwise
     */
    public static function isValid(string $slug): bool
    {
        return isset(self::LAYOUTS[$slug]);
    }

    /**
     * Handle layout switch request
     *
     * @param string $layoutSlug The layout to switch to
     * @return array Result array with 'success' and 'message' keys
     */
    public static function handleSwitchRequest(string $layoutSlug): array
    {
        // Sanitize input
        $layoutSlug = preg_replace('/[^a-z-]/', '', strtolower($layoutSlug));

        // Validate layout exists
        if (!self::isValid($layoutSlug)) {
            if (session_status() !== PHP_SESSION_NONE) {
                $_SESSION['error'] = 'Invalid layout selected';
            }
            return [
                'success' => false,
                'message' => 'Invalid layout selected'
            ];
        }

        // Get layout info
        $layoutInfo = self::getLayout($layoutSlug);

        // For now, all layouts have public access
        // In the future, you could add permission checks here
        // if ($layoutInfo['access'] === 'premium' && !userHasPremium()) { ... }

        // Set the layout
        $success = LayoutHelper::set($layoutSlug);

        if ($success) {
            // Also update in session for compatibility
            if (session_status() !== PHP_SESSION_NONE) {
                $_SESSION['nexus_layout'] = $layoutSlug;
                $_SESSION['success'] = 'Layout switched to ' . $layoutInfo['name'];
            }

            // Optionally save to user preferences if user is logged in
            if (isset($_SESSION['user_id'])) {
                self::saveUserLayoutPreference($_SESSION['user_id'], $layoutSlug);
            }

            return [
                'success' => true,
                'message' => 'Layout switched successfully'
            ];
        }

        if (session_status() !== PHP_SESSION_NONE) {
            $_SESSION['error'] = 'Failed to switch layout';
        }

        return [
            'success' => false,
            'message' => 'Failed to switch layout'
        ];
    }

    /**
     * Save user's layout preference to database
     *
     * @param int $userId The user ID
     * @param string $layoutSlug The layout slug
     * @return bool True on success, false on failure
     */
    private static function saveUserLayoutPreference(int $userId, string $layoutSlug): bool
    {
        try {
            $tenantId = TenantContext::getId();

            // Check if user preferences table exists and update
            // This is optional - comment out if you don't have this table
            /*
            \Nexus\Core\Database::query(
                "UPDATE users SET preferred_layout = ? WHERE id = ? AND tenant_id = ?",
                [$layoutSlug, $userId, $tenantId]
            );
            */

            return true;
        } catch (\Exception $e) {
            // Silently fail - user preference is not critical
            return false;
        }
    }

    /**
     * Load user's saved layout preference
     *
     * @param int $userId The user ID
     * @return string|null The saved layout slug or null if none
     */
    public static function getUserLayoutPreference(int $userId): ?string
    {
        try {
            $tenantId = TenantContext::getId();

            // This is optional - comment out if you don't have this column
            /*
            $result = \Nexus\Core\Database::query(
                "SELECT preferred_layout FROM users WHERE id = ? AND tenant_id = ? LIMIT 1",
                [$userId, $tenantId]
            )->fetch();

            return $result['preferred_layout'] ?? null;
            */

            return null;
        } catch (\Exception $e) {
            return null;
        }
    }
}
