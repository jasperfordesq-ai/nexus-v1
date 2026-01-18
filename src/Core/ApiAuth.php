<?php

namespace Nexus\Core;

use Nexus\Services\TokenService;

/**
 * ApiAuth - Unified authentication for API endpoints
 *
 * Supports both session-based and Bearer token authentication.
 * This ensures mobile apps using JWT tokens and web apps using sessions
 * both work seamlessly with the same API endpoints.
 */
trait ApiAuth
{
    private ?int $authenticatedUserId = null;
    private ?int $authenticatedTenantId = null;

    /**
     * Require authentication - returns user ID or sends 401 response
     */
    protected function requireAuth(): int
    {
        $userId = $this->getAuthenticatedUserId();

        if (!$userId) {
            $this->sendUnauthorizedResponse();
        }

        return $userId;
    }

    /**
     * Get authenticated user ID (returns null if not authenticated)
     */
    protected function getAuthenticatedUserId(): ?int
    {
        if ($this->authenticatedUserId !== null) {
            return $this->authenticatedUserId;
        }

        // Try Bearer token first (preferred for API calls)
        $userId = $this->authenticateWithBearerToken();
        if ($userId) {
            return $userId;
        }

        // Fall back to session authentication
        return $this->authenticateWithSession();
    }

    /**
     * Authenticate using Bearer token from Authorization header
     */
    private function authenticateWithBearerToken(): ?int
    {
        $authHeader = $_SERVER['HTTP_AUTHORIZATION'] ?? $_SERVER['REDIRECT_HTTP_AUTHORIZATION'] ?? '';

        if (empty($authHeader) || !preg_match('/Bearer\s+(.+)$/i', $authHeader, $matches)) {
            return null;
        }

        $token = $matches[1];
        $payload = TokenService::validateToken($token);

        if (!$payload || ($payload['type'] ?? 'access') !== 'access') {
            return null;
        }

        $userId = $payload['user_id'] ?? null;
        if (!$userId) {
            return null;
        }

        $this->authenticatedUserId = (int) $userId;
        $this->authenticatedTenantId = isset($payload['tenant_id']) ? (int) $payload['tenant_id'] : null;

        // Sync to session for consistency
        if (session_status() === PHP_SESSION_NONE) {
            session_start();
        }
        if (empty($_SESSION['user_id'])) {
            $_SESSION['user_id'] = $this->authenticatedUserId;
            $_SESSION['tenant_id'] = $this->authenticatedTenantId;
            $_SESSION['user_role'] = $payload['role'] ?? 'member';
        }

        return $this->authenticatedUserId;
    }

    /**
     * Authenticate using PHP session
     */
    private function authenticateWithSession(): ?int
    {
        if (session_status() === PHP_SESSION_NONE) {
            session_start();
        }

        if (empty($_SESSION['user_id'])) {
            return null;
        }

        $this->authenticatedUserId = (int) $_SESSION['user_id'];
        $this->authenticatedTenantId = isset($_SESSION['tenant_id']) ? (int) $_SESSION['tenant_id'] : null;

        return $this->authenticatedUserId;
    }

    /**
     * Send 401 Unauthorized response and exit
     */
    private function sendUnauthorizedResponse(): void
    {
        header('Content-Type: application/json');
        http_response_code(401);
        echo json_encode(['error' => 'Unauthorized', 'code' => 'AUTH_REQUIRED']);
        exit;
    }
}
