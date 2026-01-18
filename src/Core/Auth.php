<?php

namespace Nexus\Core;

/**
 * Auth - Core authentication helper
 *
 * Provides authentication utilities for the application.
 * Works alongside AdminAuth for admin-level access control.
 */
class Auth
{
    /**
     * Get the currently logged-in user
     *
     * @return array|null User data array or null if not logged in
     */
    public static function user(): ?array
    {
        if (!self::check()) {
            return null;
        }

        $userId = (int) $_SESSION['user_id'];

        try {
            $user = Database::query(
                "SELECT * FROM users WHERE id = ? LIMIT 1",
                [$userId]
            )->fetch();

            return $user ?: null;
        } catch (\Throwable $e) {
            error_log("Auth::user() error: " . $e->getMessage());
            return null;
        }
    }

    /**
     * Check if user is logged in
     *
     * @return bool
     */
    public static function check(): bool
    {
        return !empty($_SESSION['user_id']);
    }

    /**
     * Get the current user ID
     *
     * @return int|null
     */
    public static function id(): ?int
    {
        return self::check() ? (int) $_SESSION['user_id'] : null;
    }

    /**
     * Require user to be logged in, redirect to login if not
     *
     * @param bool $jsonResponse Return JSON error instead of redirect
     * @return array User data
     */
    public static function require(bool $jsonResponse = false): array
    {
        if (!self::check()) {
            if ($jsonResponse) {
                header('Content-Type: application/json');
                http_response_code(401);
                echo json_encode(['success' => false, 'error' => 'Not authenticated']);
                exit;
            }
            header('Location: ' . TenantContext::getBasePath() . '/login');
            exit;
        }

        $user = self::user();
        if (!$user) {
            // Session exists but user not found in DB
            self::logout();
            if ($jsonResponse) {
                header('Content-Type: application/json');
                http_response_code(401);
                echo json_encode(['success' => false, 'error' => 'Session expired']);
                exit;
            }
            header('Location: ' . TenantContext::getBasePath() . '/login');
            exit;
        }

        return $user;
    }

    /**
     * Require admin access
     *
     * @param bool $jsonResponse Return JSON error instead of redirect
     * @return array User data
     */
    public static function requireAdmin(bool $jsonResponse = false): array
    {
        $user = self::require($jsonResponse);

        if (!self::isAdmin($user)) {
            if ($jsonResponse) {
                header('Content-Type: application/json');
                http_response_code(403);
                echo json_encode(['success' => false, 'error' => 'Access denied']);
                exit;
            }
            http_response_code(403);
            echo "<h1>403 Forbidden</h1><p>Admin access required.</p>";
            exit;
        }

        return $user;
    }

    /**
     * Check if user has admin privileges
     *
     * @param array|null $user User data (uses current user if null)
     * @return bool
     */
    public static function isAdmin(?array $user = null): bool
    {
        if ($user === null) {
            $user = self::user();
        }

        if (!$user) {
            return false;
        }

        // Check god mode
        if (!empty($_SESSION['is_god']) || !empty($user['is_god'])) {
            return true;
        }

        // Check super admin
        if (!empty($_SESSION['is_super_admin']) || !empty($user['is_super_admin'])) {
            return true;
        }

        // Check role
        $role = $user['role'] ?? $_SESSION['user_role'] ?? '';
        return in_array($role, ['admin', 'tenant_admin']) || !empty($_SESSION['is_admin']);
    }

    /**
     * Validate CSRF token
     *
     * @param string|null $token Token to validate
     * @return bool
     */
    public static function validateCsrf(?string $token = null): bool
    {
        return Csrf::verify($token);
    }

    /**
     * Log the user out
     */
    public static function logout(): void
    {
        unset(
            $_SESSION['user_id'],
            $_SESSION['user_role'],
            $_SESSION['is_admin'],
            $_SESSION['is_super_admin'],
            $_SESSION['is_god']
        );
    }

    /**
     * Get the user's role
     *
     * @return string|null
     */
    public static function role(): ?string
    {
        if (!self::check()) {
            return null;
        }

        return $_SESSION['user_role'] ?? null;
    }
}
