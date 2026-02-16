<?php

namespace Nexus\Core;

/**
 * View Rendering System
 *
 * Uses the 'modern' layout for all legacy PHP views.
 * The React frontend is the primary UI; these views are for admin-legacy and reference only.
 *
 * ISOLATION: Each layout is fully self-contained. No cross-layout fallbacks.
 * If a view doesn't exist in the active layout, it shows an error rather than
 * loading from another layout (which could cause styling/functionality issues).
 */
class View
{
    /**
     * Render a view file.
     *
     * @param string $viewPath Relative path to view (e.g., 'pages/about')
     * @param array $data Associative array of variables to pass to the view
     */
    public static function render($viewPath, $data = [])
    {
        // Security: Sanitize view path to prevent path traversal attacks
        // Remove null bytes, directory traversal sequences, and validate format
        $viewPath = str_replace(["\0", '..', "\r", "\n"], '', $viewPath);

        // Only allow alphanumeric, hyphens, underscores, and forward slashes
        if (!preg_match('/^[a-zA-Z0-9\/_-]+$/', $viewPath)) {
            self::showViewNotFoundError($viewPath, [], 'invalid');
            return;
        }

        // Security: Use EXTR_SKIP to prevent overwriting existing variables
        extract($data, EXTR_SKIP);

        $currentTenant = TenantContext::get();

        // Legacy CivicOne theme has been removed; always use modern layout
        $activeLayout = 'modern';

        // Build view paths - ISOLATED per layout (no cross-layout fallbacks)
        $viewPaths = [];

        // 1. Try Tenant-Specific Layout Override (Highest Priority)
        if (isset($currentTenant['slug'])) {
            $viewPaths[] = __DIR__ . '/../../views/tenants/' . $currentTenant['slug'] . '/' . $activeLayout . '/' . $viewPath . '.php';
        }

        // 2. Try Active Layout View (modern or civicone) - PRIMARY
        $viewPaths[] = __DIR__ . '/../../views/' . $activeLayout . '/' . $viewPath . '.php';

        // 3. Try Shared/Common View (layout-agnostic views in views/ root)
        // These are views that work with any layout (e.g., error pages, emails)
        $viewPaths[] = __DIR__ . '/../../views/' . $viewPath . '.php';

        // NO CROSS-LAYOUT FALLBACK - Each layout must be self-contained
        // This prevents Modern bugs from affecting CivicOne and vice versa

        // Find and render the first existing view
        foreach ($viewPaths as $path) {
            if (file_exists($path)) {
                require $path;
                return;
            }
        }

        // View not found - show error
        self::showViewNotFoundError($viewPath, $viewPaths, $activeLayout);
    }

    /**
     * Show a friendly error when view is not found
     */
    private static function showViewNotFoundError($viewPath, $searchedPaths, $activeLayout = 'unknown')
    {
        // Security: Log full path details for debugging, but don't expose to users
        error_log("View not found: $viewPath (layout: $activeLayout). Searched: " . implode(', ', $searchedPaths));

        // Show generic error in production, detailed in development
        $appEnv = getenv('APP_ENV') ?: ($_ENV['APP_ENV'] ?? 'production');
        $isDev = ($appEnv === 'development' || $appEnv === 'local');

        echo "<div style='background:#fee2e2; color:#991b1b; padding:20px; border:1px solid #f87171; border-radius:8px; margin:20px; font-family:sans-serif;'>";
        echo "<strong>View Not Found:</strong> " . htmlspecialchars($viewPath);
        echo "<br><strong>Active Layout:</strong> " . htmlspecialchars($activeLayout);

        if ($isDev) {
            // Only show file paths in development
            echo "<br><br><strong>Searched paths:</strong><ul style='margin:10px 0; padding-left:20px;'>";
            foreach ($searchedPaths as $path) {
                $exists = file_exists($path) ? ' ✓' : ' ✗';
                echo "<li><small>" . htmlspecialchars($path) . $exists . "</small></li>";
            }
            echo "</ul>";
            echo "<p style='margin-top:15px; font-size:0.9em;'><strong>Note:</strong> Layouts are isolated. Create this view in <code>views/{$activeLayout}/{$viewPath}.php</code></p>";
        } else {
            echo "<br><small>Please contact support if this error persists.</small>";
        }

        echo "</div>";
    }
}
