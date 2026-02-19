<?php
// Copyright © 2024–2026 Jasper Ford
// SPDX-License-Identifier: AGPL-3.0-or-later
// Author: Jasper Ford
// See NOTICE file for attribution and acknowledgements.

namespace Nexus\Core;

use Nexus\Services\TokenService;

/**
 * ApiAuth - Unified authentication for API endpoints
 *
 * Supports both session-based and Bearer token authentication.
 * This ensures mobile apps using JWT tokens and web apps using sessions
 * both work seamlessly with the same API endpoints.
 *
 * IMPORTANT: Bearer token authentication is STATELESS - it does not sync
 * to $_SESSION. Use the getAuthenticatedUserId(), getAuthenticatedTenantId(),
 * and getAuthenticatedUserRole() methods to access user data instead of
 * reading from $_SESSION directly.
 */
trait ApiAuth
{
    private ?int $authenticatedUserId = null;
    private ?int $authenticatedTenantId = null;
    private ?string $authenticatedUserRole = null;
    private bool $isBearerAuthenticated = false;
    private ?array $tokenPayload = null;

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
     * Get authenticated tenant ID
     * Works for both Bearer token and session authentication
     */
    protected function getAuthenticatedTenantId(): ?int
    {
        // Ensure authentication has been attempted
        if ($this->authenticatedUserId === null) {
            $this->getAuthenticatedUserId();
        }

        return $this->authenticatedTenantId;
    }

    /**
     * Get authenticated user's role
     * Works for both Bearer token and session authentication
     */
    protected function getAuthenticatedUserRole(): ?string
    {
        // Ensure authentication has been attempted
        if ($this->authenticatedUserId === null) {
            $this->getAuthenticatedUserId();
        }

        return $this->authenticatedUserRole;
    }

    /**
     * Check if the current request is authenticated via Bearer token
     * Useful for determining if the request is stateless
     */
    protected function isStatelessRequest(): bool
    {
        // Ensure authentication has been attempted
        if ($this->authenticatedUserId === null) {
            $this->getAuthenticatedUserId();
        }

        return $this->isBearerAuthenticated;
    }

    /**
     * Get the full token payload for Bearer-authenticated requests
     * Returns null for session-authenticated requests
     */
    protected function getTokenPayload(): ?array
    {
        // Ensure authentication has been attempted
        if ($this->authenticatedUserId === null) {
            $this->getAuthenticatedUserId();
        }

        return $this->tokenPayload;
    }

    /**
     * Authenticate using Bearer token from Authorization header
     *
     * NOTE: This method is STATELESS - it does NOT sync to $_SESSION.
     * All user data (user_id, tenant_id, role) should be accessed via
     * the trait methods, not from $_SESSION.
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

        // Store authentication state without touching session
        $this->authenticatedUserId = (int) $userId;
        $this->authenticatedTenantId = isset($payload['tenant_id']) ? (int) $payload['tenant_id'] : null;
        $this->authenticatedUserRole = $payload['role'] ?? 'member';
        $this->isBearerAuthenticated = true;
        $this->tokenPayload = $payload;

        // STATELESS: Do NOT sync to $_SESSION for Bearer token auth
        // This ensures API requests remain fully stateless and don't
        // create server-side session state that could cause issues
        // with horizontal scaling or session fixation attacks.

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
        $this->authenticatedUserRole = $_SESSION['user_role'] ?? 'member';
        $this->isBearerAuthenticated = false;
        $this->tokenPayload = null;

        return $this->authenticatedUserId;
    }

    /**
     * Send 401 Unauthorized response and exit
     */
    private function sendUnauthorizedResponse(): never
    {
        header('Content-Type: application/json');
        http_response_code(401);
        echo json_encode(['error' => 'Unauthorized', 'code' => 'AUTH_REQUIRED']);
        exit;
    }
}
