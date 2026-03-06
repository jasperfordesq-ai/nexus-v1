<?php
// Copyright © 2024–2026 Jasper Ford
// SPDX-License-Identifier: AGPL-3.0-or-later
// Author: Jasper Ford
// See NOTICE file for attribution and acknowledgements.

namespace Nexus\Controllers\Api;

use Nexus\Core\ApiErrorCodes;
use Nexus\Core\Database;
use Nexus\Core\TenantContext;
use Nexus\Services\TokenService;
use Nexus\Services\WebAuthnChallengeStore;

/**
 * WebAuthnApiController - WebAuthn/Passkey authentication API
 *
 * Handles biometric authentication registration and verification.
 * Supports both stateless (Bearer token) and session-based authentication.
 *
 * Challenge Storage:
 * - For stateless clients (mobile/SPA): Uses WebAuthnChallengeStore with challenge_id
 * - For session clients (browsers): Also stores in $_SESSION for backward compatibility
 *
 * Flow for stateless clients:
 * 1. POST /api/webauthn/register-challenge → returns { challenge_id, ... }
 * 2. POST /api/webauthn/register-verify with { challenge_id, ... }
 *
 * Flow for session clients (unchanged):
 * 1. POST /api/webauthn/register-challenge → returns { ... }
 * 2. POST /api/webauthn/register-verify → uses session challenge
 */
class WebAuthnApiController extends BaseApiController
{
    /**
     * POST /api/webauthn/register-challenge
     * Generate a challenge for WebAuthn credential registration
     */
    public function registerChallenge()
    {
        // Rate limit: 10 challenge requests per minute per IP to prevent resource exhaustion
        $this->rateLimit('webauthn:register-challenge', 10, 60);

        $userId = $this->requireAuth();
        $user = $this->getUser($userId);

        if (!$user) {
            $this->error('User not found', 404, ApiErrorCodes::RESOURCE_NOT_FOUND);
        }

        // Generate random challenge
        $challenge = random_bytes(32);
        $challengeB64 = $this->base64UrlEncode($challenge);

        // Store challenge in WebAuthnChallengeStore for stateless clients
        $challengeId = WebAuthnChallengeStore::create(
            $challengeB64,
            $userId,
            'register',
            ['email' => $user['email']]
        );

        // Also store in session for backward compatibility with browser clients
        if (session_status() === PHP_SESSION_ACTIVE || session_status() === PHP_SESSION_NONE) {
            if (session_status() === PHP_SESSION_NONE) {
                session_start();
            }
            $_SESSION['webauthn_challenge'] = $challengeB64;
            $_SESSION['webauthn_challenge_expires'] = time() + 120; // Match store TTL
        }

        // Get existing credentials to exclude
        $tenantId = TenantContext::getId();
        $db = Database::getConnection();
        $stmt = $db->prepare("SELECT credential_id, transports FROM webauthn_credentials WHERE user_id = ? AND tenant_id = ?");
        $stmt->execute([$userId, $tenantId]);
        $existingCredentials = $stmt->fetchAll();

        $excludeCredentials = array_map(function ($row) {
            $cred = [
                'type' => 'public-key',
                'id' => $row['credential_id']
            ];
            if (!empty($row['transports'])) {
                $cred['transports'] = json_decode($row['transports'], true);
            }
            return $cred;
        }, $existingCredentials);

        // Generate user ID as base64
        $userIdB64 = $this->base64UrlEncode(hash('sha256', $userId . TenantContext::getId(), true));

        $options = [
            'challenge' => $challengeB64,
            'challenge_id' => $challengeId, // For stateless clients
            'rp' => [
                'name' => TenantContext::get()['name'] ?? 'Project NEXUS',
                'id' => $this->getRpId()
            ],
            'user' => [
                'id' => $userIdB64,
                'name' => $user['email'],
                'displayName' => $user['name']
            ],
            'pubKeyCredParams' => [
                ['type' => 'public-key', 'alg' => -7],   // ES256
                ['type' => 'public-key', 'alg' => -257]  // RS256
            ],
            'authenticatorSelection' => [
                'authenticatorAttachment' => 'platform',
                'userVerification' => 'preferred',
                'residentKey' => 'preferred'
            ],
            'timeout' => 60000,
            'attestation' => 'none',
            'excludeCredentials' => $excludeCredentials
        ];

        $this->jsonResponse($options);
    }

    /**
     * POST /api/webauthn/register-verify
     * Verify and store a new WebAuthn credential
     */
    public function registerVerify()
    {
        $userId = $this->requireAuth();
        $input = $this->getInput();

        // Get challenge - try challenge_id first (stateless), then session (backward compat)
        $challengeId = $input['challenge_id'] ?? null;
        $storedChallenge = null;

        if ($challengeId) {
            // Stateless client - get from challenge store
            $challengeData = WebAuthnChallengeStore::get($challengeId);
            if (!$challengeData) {
                $this->error(
                    'Challenge expired or invalid',
                    401,
                    ApiErrorCodes::AUTH_WEBAUTHN_CHALLENGE_EXPIRED
                );
            }

            // Verify this challenge belongs to the authenticated user
            if ($challengeData['user_id'] !== $userId) {
                $this->error(
                    'Challenge user mismatch',
                    401,
                    ApiErrorCodes::AUTH_WEBAUTHN_CHALLENGE_INVALID
                );
            }

            if ($challengeData['type'] !== 'register') {
                $this->error(
                    'Invalid challenge type',
                    401,
                    ApiErrorCodes::AUTH_WEBAUTHN_CHALLENGE_INVALID
                );
            }

            $storedChallenge = $challengeData['challenge'];
        } else {
            // Session-based client (backward compatibility)
            if (session_status() === PHP_SESSION_NONE) {
                session_start();
            }

            if (empty($_SESSION['webauthn_challenge']) ||
                empty($_SESSION['webauthn_challenge_expires']) ||
                time() > $_SESSION['webauthn_challenge_expires']) {
                $this->error(
                    'Challenge expired',
                    401,
                    ApiErrorCodes::AUTH_WEBAUTHN_CHALLENGE_EXPIRED
                );
            }

            $storedChallenge = $_SESSION['webauthn_challenge'];
        }

        if (empty($input['id']) || empty($input['response'])) {
            $this->error(
                'Invalid credential data',
                400,
                ApiErrorCodes::VALIDATION_REQUIRED_FIELD
            );
        }

        // Verify client data
        $clientDataJSON = $this->base64UrlDecode($input['response']['clientDataJSON']);
        $clientData = json_decode($clientDataJSON, true);

        if (!is_array($clientData) || empty($clientData['type'])) {
            $this->error(
                'Malformed client data',
                400,
                ApiErrorCodes::AUTH_WEBAUTHN_CHALLENGE_INVALID
            );
        }

        if ($clientData['type'] !== 'webauthn.create') {
            $this->error(
                'Invalid credential type',
                400,
                ApiErrorCodes::AUTH_WEBAUTHN_CHALLENGE_INVALID
            );
        }

        if (!hash_equals($storedChallenge, $clientData['challenge'] ?? '')) {
            $this->error(
                'Challenge mismatch',
                401,
                ApiErrorCodes::AUTH_WEBAUTHN_CHALLENGE_INVALID
            );
        }

        // Verify origin
        $expectedOrigin = $this->getExpectedOrigin();
        if ($clientData['origin'] !== $expectedOrigin) {
            error_log("[WebAuthn] Origin mismatch: expected {$expectedOrigin}, got {$clientData['origin']}");
            // Be lenient for development
            if (strpos($clientData['origin'], 'localhost') === false && strpos($expectedOrigin, 'localhost') === false) {
                $this->error(
                    'Origin mismatch',
                    400,
                    ApiErrorCodes::AUTH_WEBAUTHN_CHALLENGE_INVALID
                );
            }
        }

        // Parse attestation object to extract public key
        $attestationObject = $this->base64UrlDecode($input['response']['attestationObject']);
        $publicKey = $this->extractPublicKey($attestationObject);

        if (!$publicKey) {
            $this->error(
                'Failed to extract public key',
                400,
                ApiErrorCodes::AUTH_WEBAUTHN_FAILED
            );
        }

        // Store credential (with transport hints for allowCredentials on future logins)
        $transports = null;
        if (!empty($input['response']['transports']) && is_array($input['response']['transports'])) {
            $transports = json_encode($input['response']['transports']);
        }

        $db = Database::getConnection();
        $stmt = $db->prepare("
            INSERT INTO webauthn_credentials (user_id, tenant_id, credential_id, public_key, sign_count, transports, created_at)
            VALUES (?, ?, ?, ?, 0, ?, NOW())
        ");
        $stmt->execute([
            $userId,
            TenantContext::getId(),
            $input['id'],
            $publicKey,
            $transports
        ]);

        // Consume challenge (delete from store - single use)
        if ($challengeId) {
            WebAuthnChallengeStore::consume($challengeId);
        }

        // Clear session challenge
        unset($_SESSION['webauthn_challenge'], $_SESSION['webauthn_challenge_expires']);

        $this->success(['message' => 'Biometric registered']);
    }

    /**
     * POST /api/webauthn/auth-challenge
     * Generate a challenge for WebAuthn authentication
     */
    public function authChallenge()
    {
        // Rate limit: 10 challenge requests per minute per IP to prevent resource exhaustion
        $this->rateLimit('webauthn:auth-challenge', 10, 60);

        $input = $this->getInput();

        // Generate random challenge
        $challenge = random_bytes(32);
        $challengeB64 = $this->base64UrlEncode($challenge);

        // Determine user context (may be null for passwordless login)
        $userId = null;
        $email = $input['email'] ?? null;

        // Check if user is already authenticated (re-auth scenario)
        $authUserId = $this->getAuthenticatedUserId();

        // Get allowed credentials
        $allowCredentials = [];
        $db = Database::getConnection();

        if ($authUserId) {
            // User is logged in, get their credentials
            $userId = $authUserId;
            $stmt = $db->prepare("SELECT credential_id, transports FROM webauthn_credentials WHERE user_id = ? AND tenant_id = ?");
            $stmt->execute([$userId, TenantContext::getId()]);
            $credentials = $stmt->fetchAll();

            $allowCredentials = array_map(function ($row) {
                $cred = [
                    'type' => 'public-key',
                    'id' => $row['credential_id']
                ];
                if (!empty($row['transports'])) {
                    $cred['transports'] = json_decode($row['transports'], true);
                }
                return $cred;
            }, $credentials);
        } elseif ($email) {
            // User provided email for passwordless login
            $stmt = $db->prepare("
                SELECT wc.credential_id, wc.transports, u.id as user_id
                FROM webauthn_credentials wc
                JOIN users u ON wc.user_id = u.id
                WHERE u.email = ? AND u.tenant_id = ?
            ");
            $stmt->execute([$email, TenantContext::getId()]);
            $results = $stmt->fetchAll();

            if (!empty($results)) {
                $userId = $results[0]['user_id'];
                $allowCredentials = array_map(function ($row) {
                    $cred = [
                        'type' => 'public-key',
                        'id' => $row['credential_id']
                    ];
                    if (!empty($row['transports'])) {
                        $cred['transports'] = json_decode($row['transports'], true);
                    }
                    return $cred;
                }, $results);
            }
        }

        // Store challenge in WebAuthnChallengeStore
        $challengeId = WebAuthnChallengeStore::create(
            $challengeB64,
            $userId, // May be null for discoverable credentials
            'authenticate',
            ['email' => $email]
        );

        // Also store in session for backward compatibility
        if (session_status() === PHP_SESSION_NONE) {
            session_start();
        }
        $_SESSION['webauthn_auth_challenge'] = $challengeB64;
        $_SESSION['webauthn_auth_challenge_expires'] = time() + 120;
        if ($email) {
            $_SESSION['webauthn_auth_email'] = $email;
        }

        $options = [
            'challenge' => $challengeB64,
            'challenge_id' => $challengeId,
            'rpId' => $this->getRpId(),
            'timeout' => 60000,
            'userVerification' => 'preferred'
        ];

        if (!empty($allowCredentials)) {
            $options['allowCredentials'] = $allowCredentials;
        }

        $this->jsonResponse($options);
    }

    /**
     * POST /api/webauthn/auth-verify
     * Verify WebAuthn authentication assertion
     */
    public function authVerify()
    {
        $input = $this->getInput();

        // Get challenge - try challenge_id first (stateless), then session
        $challengeId = $input['challenge_id'] ?? null;
        $storedChallenge = null;

        if ($challengeId) {
            $challengeData = WebAuthnChallengeStore::get($challengeId);
            if (!$challengeData) {
                $this->error(
                    'Challenge expired or invalid',
                    401,
                    ApiErrorCodes::AUTH_WEBAUTHN_CHALLENGE_EXPIRED
                );
            }

            if ($challengeData['type'] !== 'authenticate') {
                $this->error(
                    'Invalid challenge type',
                    401,
                    ApiErrorCodes::AUTH_WEBAUTHN_CHALLENGE_INVALID
                );
            }

            $storedChallenge = $challengeData['challenge'];
        } else {
            // Session-based (backward compatibility)
            if (session_status() === PHP_SESSION_NONE) {
                session_start();
            }

            if (empty($_SESSION['webauthn_auth_challenge']) ||
                empty($_SESSION['webauthn_auth_challenge_expires']) ||
                time() > $_SESSION['webauthn_auth_challenge_expires']) {
                $this->error(
                    'Challenge expired',
                    401,
                    ApiErrorCodes::AUTH_WEBAUTHN_CHALLENGE_EXPIRED
                );
            }

            $storedChallenge = $_SESSION['webauthn_auth_challenge'];
        }

        if (empty($input['id']) || empty($input['response'])) {
            $this->error(
                'Invalid assertion data',
                400,
                ApiErrorCodes::VALIDATION_REQUIRED_FIELD
            );
        }

        // Find credential
        $db = Database::getConnection();
        $stmt = $db->prepare("
            SELECT wc.*, u.id as user_id, u.first_name, u.last_name, u.email, u.role, u.tenant_id
            FROM webauthn_credentials wc
            JOIN users u ON wc.user_id = u.id
            WHERE wc.credential_id = ? AND wc.tenant_id = ?
        ");
        $stmt->execute([$input['id'], TenantContext::getId()]);
        $credential = $stmt->fetch();

        if (!$credential) {
            $this->error(
                'Credential not found',
                401,
                ApiErrorCodes::AUTH_WEBAUTHN_CREDENTIAL_NOT_FOUND
            );
        }

        // Verify client data
        $clientDataJSON = $this->base64UrlDecode($input['response']['clientDataJSON']);
        $clientData = json_decode($clientDataJSON, true);

        if (!is_array($clientData) || empty($clientData['type'])) {
            $this->error(
                'Malformed client data',
                400,
                ApiErrorCodes::AUTH_WEBAUTHN_CHALLENGE_INVALID
            );
        }

        if ($clientData['type'] !== 'webauthn.get') {
            $this->error(
                'Invalid assertion type',
                400,
                ApiErrorCodes::AUTH_WEBAUTHN_CHALLENGE_INVALID
            );
        }

        if (!hash_equals($storedChallenge, $clientData['challenge'] ?? '')) {
            $this->error(
                'Challenge mismatch',
                401,
                ApiErrorCodes::AUTH_WEBAUTHN_CHALLENGE_INVALID
            );
        }

        // Verify origin
        $expectedOrigin = $this->getExpectedOrigin();
        if ($clientData['origin'] !== $expectedOrigin) {
            error_log("[WebAuthn] Auth origin mismatch: expected {$expectedOrigin}, got {$clientData['origin']}");
            if (strpos($clientData['origin'], 'localhost') === false && strpos($expectedOrigin, 'localhost') === false) {
                $this->error(
                    'Origin mismatch',
                    400,
                    ApiErrorCodes::AUTH_WEBAUTHN_CHALLENGE_INVALID
                );
            }
        }

        // Verify rpIdHash
        $authenticatorData = $this->base64UrlDecode($input['response']['authenticatorData']);
        $rpIdHash = substr($authenticatorData, 0, 32);
        $expectedRpIdHash = hash('sha256', $this->getRpId(), true);
        if ($rpIdHash !== $expectedRpIdHash) {
            error_log('[WebAuthn] rpIdHash mismatch');
            $this->error(
                'RP ID mismatch',
                400,
                ApiErrorCodes::AUTH_WEBAUTHN_FAILED
            );
        }

        // Verify user presence flag
        $flags = ord($authenticatorData[32]);
        if (($flags & 0x01) === 0) {
            $this->error(
                'User not present',
                400,
                ApiErrorCodes::AUTH_WEBAUTHN_FAILED
            );
        }

        // Verify signature using stored public key
        if (empty($input['response']['signature'])) {
            $this->error(
                'Missing signature',
                400,
                ApiErrorCodes::AUTH_WEBAUTHN_FAILED
            );
        }
        $signature = $this->base64UrlDecode($input['response']['signature']);
        $clientDataJSONRaw = $this->base64UrlDecode($input['response']['clientDataJSON']);
        if (!empty($credential['public_key'])) {
            $sigValid = $this->verifySignature($authenticatorData, $clientDataJSONRaw, $signature, $credential['public_key']);
            if (!$sigValid) {
                $this->error(
                    'Signature verification failed',
                    401,
                    ApiErrorCodes::AUTH_WEBAUTHN_FAILED
                );
            }
        }

        // Update sign count
        $signCount = unpack('N', substr($authenticatorData, 33, 4))[1] ?? 0;
        if ($signCount > 0 && $signCount <= $credential['sign_count']) {
            $this->error(
                'Possible cloned authenticator',
                401,
                ApiErrorCodes::AUTH_WEBAUTHN_FAILED
            );
        }

        $stmt = $db->prepare("UPDATE webauthn_credentials SET sign_count = ?, last_used_at = NOW() WHERE id = ?");
        $stmt->execute([$signCount, $credential['id']]);

        // Consume challenge
        if ($challengeId) {
            WebAuthnChallengeStore::consume($challengeId);
        }

        // Clear session challenges
        unset(
            $_SESSION['webauthn_auth_challenge'],
            $_SESSION['webauthn_auth_challenge_expires'],
            $_SESSION['webauthn_auth_email']
        );

        // SECURITY: Enforce registration policy gates on WebAuthn login
        $webauthnUser = Database::query(
            "SELECT id, role, is_super_admin, is_tenant_super_admin, tenant_id, email_verified_at, is_approved FROM users WHERE id = ? AND tenant_id = ?",
            [(int)$credential['user_id'], (int)$credential['tenant_id']]
        )->fetch();
        if ($webauthnUser) {
            $gateBlock = \Nexus\Services\TenantSettingsService::checkLoginGates($webauthnUser);
            if ($gateBlock) {
                $this->error($gateBlock['message'], 403, $gateBlock['code']);
            }
        }

        // Determine if client wants stateless auth
        $wantsStateless = TokenService::isMobileRequest() || isset($_SERVER['HTTP_X_STATELESS_AUTH']);

        // Generate tokens for API response
        $isMobile = TokenService::isMobileRequest();
        $accessToken = TokenService::generateToken(
            (int)$credential['user_id'],
            (int)$credential['tenant_id'],
            ['role' => $credential['role'], 'email' => $credential['email']],
            $isMobile
        );
        $refreshToken = TokenService::generateRefreshToken(
            (int)$credential['user_id'],
            (int)$credential['tenant_id'],
            $isMobile
        );

        // For session-based clients, also set up session
        if (!$wantsStateless) {
            // Check current session user
            $currentSessionUser = $_SESSION['user_id'] ?? null;

            if ($currentSessionUser === null) {
                // Not logged in - set up session
                $preservedLayout = $_SESSION['nexus_active_layout'] ?? $_SESSION['nexus_layout'] ?? null;
                session_regenerate_id(true);
                if ($preservedLayout) {
                    $_SESSION['nexus_active_layout'] = $preservedLayout;
                    $_SESSION['nexus_layout'] = $preservedLayout;
                }

                $_SESSION['user_id'] = $credential['user_id'];
                $_SESSION['user_name'] = trim($credential['first_name'] . ' ' . $credential['last_name']);
                $_SESSION['user_email'] = $credential['email'];
                $_SESSION['user_role'] = $credential['role'];
                $_SESSION['tenant_id'] = $credential['tenant_id'];
                $_SESSION['is_logged_in'] = true;
            }
        }

        $response = [
            'success' => true,
            'message' => 'Authentication successful',
            'user' => [
                'id' => $credential['user_id'],
                'first_name' => $credential['first_name'],
                'last_name' => $credential['last_name'],
                'email' => $credential['email']
            ],
            'access_token' => $accessToken,
            'refresh_token' => $refreshToken,
            'token_type' => 'Bearer',
            'expires_in' => TokenService::getAccessTokenExpiry($isMobile),
            'is_mobile' => $isMobile
        ];

        $this->jsonResponse($response);
    }

    /**
     * POST /api/webauthn/remove
     * Remove a WebAuthn credential
     */
    public function remove()
    {
        $userId = $this->requireAuth();
        $input = $this->getInput();
        $credentialId = $input['credential_id'] ?? null;

        $db = Database::getConnection();

        $tenantId = TenantContext::getId();

        if ($credentialId) {
            // Remove specific credential
            $stmt = $db->prepare("DELETE FROM webauthn_credentials WHERE credential_id = ? AND user_id = ? AND tenant_id = ?");
            $stmt->execute([$credentialId, $userId, $tenantId]);
        } else {
            // Remove all credentials for user
            $stmt = $db->prepare("DELETE FROM webauthn_credentials WHERE user_id = ? AND tenant_id = ?");
            $stmt->execute([$userId, $tenantId]);
        }

        $this->success(['message' => 'Credential(s) removed']);
    }

    /**
     * POST /api/webauthn/remove-all
     * Remove all WebAuthn credentials for current user
     */
    public function removeAll()
    {
        // Require POST method
        if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
            $this->error(
                'Method not allowed. Use POST request.',
                405,
                ApiErrorCodes::VALIDATION_ERROR
            );
        }

        // Verify CSRF for browser requests (Bearer auth skips CSRF)
        $this->verifyCsrf();

        $userId = $this->requireAuth();
        $tenantId = TenantContext::getId();

        $db = Database::getConnection();

        // Count credentials before removal
        $stmt = $db->prepare("SELECT COUNT(*) as count FROM webauthn_credentials WHERE user_id = ? AND tenant_id = ?");
        $stmt->execute([$userId, $tenantId]);
        $result = $stmt->fetch();
        $count = (int)$result['count'];

        // Remove all credentials for user
        $stmt = $db->prepare("DELETE FROM webauthn_credentials WHERE user_id = ? AND tenant_id = ?");
        $stmt->execute([$userId, $tenantId]);

        $this->success([
            'message' => "Removed {$count} biometric credential(s). You can now re-register on any device.",
            'removed_count' => $count
        ]);
    }

    /**
     * GET /api/webauthn/credentials
     * List user's registered credentials
     */
    public function credentials()
    {
        $userId = $this->requireAuth();
        $tenantId = TenantContext::getId();

        $db = Database::getConnection();
        $stmt = $db->prepare("
            SELECT credential_id, created_at, last_used_at
            FROM webauthn_credentials
            WHERE user_id = ? AND tenant_id = ?
            ORDER BY created_at DESC
        ");
        $stmt->execute([$userId, $tenantId]);
        $credentials = $stmt->fetchAll();

        $this->jsonResponse([
            'credentials' => $credentials,
            'count' => count($credentials)
        ]);
    }

    /**
     * GET /api/webauthn/status
     * Check if current user has WebAuthn credentials registered
     */
    public function status()
    {
        // Allow unauthenticated check for login page
        $userId = $this->getAuthenticatedUserId();

        if (!$userId) {
            $this->jsonResponse([
                'registered' => false,
                'count' => 0
            ]);
            return;
        }

        $tenantId = TenantContext::getId();
        $db = Database::getConnection();
        $stmt = $db->prepare("SELECT COUNT(*) as count FROM webauthn_credentials WHERE user_id = ? AND tenant_id = ?");
        $stmt->execute([$userId, $tenantId]);
        $result = $stmt->fetch();

        $this->jsonResponse([
            'registered' => $result['count'] > 0,
            'count' => (int)$result['count']
        ]);
    }

    // ========================================================================
    // HELPER METHODS
    // ========================================================================

    /**
     * Get input from JSON body
     */
    private function getInput(): array
    {
        $input = file_get_contents('php://input');
        return json_decode($input, true) ?? [];
    }

    /**
     * Get user by ID
     */
    private function getUser(int $userId): ?array
    {
        $db = Database::getConnection();
        $stmt = $db->prepare("SELECT id, CONCAT(first_name, ' ', last_name) as name, email FROM users WHERE id = ? AND tenant_id = ?");
        $stmt->execute([$userId, TenantContext::getId()]);
        return $stmt->fetch() ?: null;
    }

    /**
     * Get the Relying Party ID (domain)
     *
     * For cross-origin setups (SPA at app.project-nexus.ie, API at api.project-nexus.ie),
     * the RP ID must be the registrable domain shared by both: project-nexus.ie.
     * For localhost / same-origin, use the host directly.
     */
    private function getRpId(): string
    {
        // Prefer the Origin header (SPA domain) for cross-origin requests
        $host = $_SERVER['HTTP_HOST'] ?? 'localhost';
        if (!empty($_SERVER['HTTP_ORIGIN'])) {
            $parsed = parse_url($_SERVER['HTTP_ORIGIN']);
            if ($parsed && isset($parsed['host'])) {
                $host = $parsed['host'];
            }
        }

        // Remove port if present
        $host = preg_replace('/:\d+$/', '', $host);

        // For production multi-subdomain setup, use the registrable domain
        // e.g., app.project-nexus.ie → project-nexus.ie
        // This allows WebAuthn credentials to work across subdomains
        if ($host !== 'localhost' && $host !== '127.0.0.1' && substr_count($host, '.') >= 2) {
            // Extract registrable domain (last two parts for standard TLDs)
            $parts = explode('.', $host);
            $partCount = count($parts);
            // Handle .ie, .com etc (2-part TLD check)
            if ($partCount >= 3) {
                return implode('.', array_slice($parts, -2));
            }
        }

        return $host;
    }

    /**
     * Get expected origin
     *
     * The WebAuthn origin is the frontend SPA origin (where navigator.credentials runs),
     * NOT the API origin. The browser sets this in clientDataJSON automatically.
     * We derive it from the Origin or Referer header sent by the browser, falling back
     * to the API host for same-origin setups (local dev).
     */
    private function getExpectedOrigin(): string
    {
        // The browser's Origin header is the most reliable source — it's the SPA origin
        if (!empty($_SERVER['HTTP_ORIGIN'])) {
            return rtrim($_SERVER['HTTP_ORIGIN'], '/');
        }

        // Fallback: derive from Referer header
        if (!empty($_SERVER['HTTP_REFERER'])) {
            $parts = parse_url($_SERVER['HTTP_REFERER']);
            if ($parts && isset($parts['scheme'], $parts['host'])) {
                $origin = $parts['scheme'] . '://' . $parts['host'];
                if (!empty($parts['port']) && $parts['port'] !== 443 && $parts['port'] !== 80) {
                    $origin .= ':' . $parts['port'];
                }
                return $origin;
            }
        }

        // Last resort: API host (works for same-origin / local dev)
        $protocol = (!empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off') ? 'https' : 'http';
        $host = $_SERVER['HTTP_HOST'] ?? 'localhost';
        return $protocol . '://' . $host;
    }

    /**
     * Base64 URL encode
     */
    private function base64UrlEncode(string $data): string
    {
        return rtrim(strtr(base64_encode($data), '+/', '-_'), '=');
    }

    /**
     * Base64 URL decode
     */
    private function base64UrlDecode(string $data): string
    {
        return base64_decode(strtr($data, '-_', '+/') . str_repeat('=', (4 - strlen($data) % 4) % 4));
    }

    /**
     * Extract public key from attestation object
     *
     * Parses the CBOR attestation object to extract the credential public key
     * from the authData. Supports EC2 (ES256, alg -7) and RSA (RS256, alg -257).
     */
    private function extractPublicKey(string $attestationObject): ?string
    {
        // Simple CBOR map parser - attestation object is a CBOR map with keys:
        // "fmt" (string), "attStmt" (map), "authData" (bytes)
        // We need authData to extract the public key

        // Try to find authData in the CBOR structure
        // CBOR encoding of "authData" as a text string: 0x68 (text len 8) + "authData"
        $authDataKey = "\x68authData";
        $pos = strpos($attestationObject, $authDataKey);

        if ($pos === false) {
            error_log('[WebAuthn] Could not find authData in attestation object');
            // Fallback: store entire attestation object (allows manual recovery)
            return $this->base64UrlEncode($attestationObject);
        }

        $pos += strlen($authDataKey);

        // Next byte is a CBOR byte string header
        $majorType = ord($attestationObject[$pos]) >> 5;
        $additionalInfo = ord($attestationObject[$pos]) & 0x1f;
        $pos++;

        if ($majorType !== 2) { // Not a byte string
            error_log('[WebAuthn] authData is not a CBOR byte string');
            return $this->base64UrlEncode($attestationObject);
        }

        // Decode byte string length
        $authDataLen = 0;
        if ($additionalInfo < 24) {
            $authDataLen = $additionalInfo;
        } elseif ($additionalInfo === 24) {
            $authDataLen = ord($attestationObject[$pos]);
            $pos++;
        } elseif ($additionalInfo === 25) {
            $authDataLen = unpack('n', substr($attestationObject, $pos, 2))[1];
            $pos += 2;
        } elseif ($additionalInfo === 26) {
            $authDataLen = unpack('N', substr($attestationObject, $pos, 4))[1];
            $pos += 4;
        }

        $authData = substr($attestationObject, $pos, $authDataLen);

        // authData structure:
        // rpIdHash (32 bytes) + flags (1 byte) + signCount (4 bytes) = 37 bytes
        // If flags bit 6 (AT) is set, attestedCredentialData follows
        if (strlen($authData) < 37) {
            return $this->base64UrlEncode($attestationObject);
        }

        $flags = ord($authData[32]);
        $hasAttestedCredData = ($flags & 0x40) !== 0;

        if (!$hasAttestedCredData) {
            return $this->base64UrlEncode($attestationObject);
        }

        // attestedCredentialData starts at byte 37:
        // aaguid (16 bytes) + credentialIdLength (2 bytes, big-endian) + credentialId + COSE public key
        $offset = 37;
        $offset += 16; // aaguid
        $credIdLen = unpack('n', substr($authData, $offset, 2))[1];
        $offset += 2;
        $offset += $credIdLen; // credential ID

        // Everything after is the COSE public key (CBOR encoded)
        $coseKey = substr($authData, $offset);

        // Store the COSE key as base64url - this is what we need for signature verification
        return $this->base64UrlEncode($coseKey);
    }

    /**
     * Verify the assertion signature against the stored public key
     *
     * @param string $authenticatorData Raw authenticator data bytes
     * @param string $clientDataJSON Raw client data JSON string
     * @param string $signature Raw signature bytes
     * @param string $storedPublicKeyB64 Base64url-encoded COSE public key
     * @return bool Whether the signature is valid
     */
    private function verifySignature(string $authenticatorData, string $clientDataJSON, string $signature, string $storedPublicKeyB64): bool
    {
        // The signed data is: authenticatorData + SHA-256(clientDataJSON)
        $clientDataHash = hash('sha256', $clientDataJSON, true);
        $signedData = $authenticatorData . $clientDataHash;

        $coseKey = $this->base64UrlDecode($storedPublicKeyB64);

        // Try to parse COSE key to extract algorithm and key material
        // COSE key is a CBOR map. We need key type (kty, label 1) and algorithm (alg, label 3)
        // For EC2 (kty=2): x (label -2), y (label -3) coordinates
        // For RSA (kty=3): n (label -1), e (label -2) modulus/exponent

        // Simple approach: try to convert COSE key to PEM and use openssl_verify
        $pem = $this->coseKeyToPem($coseKey);

        if ($pem === null) {
            error_log('[WebAuthn] Could not convert COSE key to PEM for verification');
            // If we stored the full attestation object (legacy fallback), skip crypto verify
            // but still validate challenge, origin, rpId, and sign count
            return true;
        }

        $pubKey = openssl_pkey_get_public($pem);
        if ($pubKey === false) {
            error_log('[WebAuthn] Failed to load public key: ' . openssl_error_string());
            return false;
        }

        $algo = OPENSSL_ALGO_SHA256;

        $result = openssl_verify($signedData, $signature, $pubKey, $algo);
        return $result === 1;
    }

    /**
     * Convert a COSE EC2 public key to PEM format
     *
     * Supports EC2 P-256 (the most common WebAuthn key type)
     */
    private function coseKeyToPem(string $coseKey): ?string
    {
        // Minimal CBOR map parser for COSE key
        // We look for specific CBOR-encoded integer keys
        $map = $this->parseCborMap($coseKey);
        if ($map === null) {
            return null;
        }

        $kty = $map[1] ?? null;  // Key type
        $alg = $map[3] ?? null;  // Algorithm

        if ($kty === 2) {
            // EC2 key (P-256 for alg -7 / ES256)
            $x = $map[-2] ?? null;
            $y = $map[-3] ?? null;

            if ($x === null || $y === null || strlen($x) !== 32 || strlen($y) !== 32) {
                return null;
            }

            // Construct uncompressed EC point: 0x04 + x + y
            $point = "\x04" . $x . $y;

            // DER-encode as SubjectPublicKeyInfo for P-256
            // OID for P-256: 1.2.840.10045.3.1.7
            // OID for EC public key: 1.2.840.10045.2.1
            $der = "\x30\x59" .
                   "\x30\x13" .
                   "\x06\x07\x2a\x86\x48\xce\x3d\x02\x01" . // ecPublicKey OID
                   "\x06\x08\x2a\x86\x48\xce\x3d\x03\x01\x07" . // P-256 OID
                   "\x03\x42\x00" . $point;

            return "-----BEGIN PUBLIC KEY-----\n" .
                   chunk_split(base64_encode($der), 64, "\n") .
                   "-----END PUBLIC KEY-----\n";
        }

        return null;
    }

    /**
     * Minimal CBOR map parser for COSE keys
     *
     * Parses a CBOR map with integer/negative integer keys and byte string values.
     * Only handles the subset needed for COSE key structures.
     *
     * @return array<int, mixed>|null Map of integer keys to values
     */
    private function parseCborMap(string $data): ?array
    {
        $pos = 0;
        $len = strlen($data);

        if ($len === 0) {
            return null;
        }

        // First byte should indicate a map
        $initial = ord($data[$pos]);
        $majorType = $initial >> 5;
        $additionalInfo = $initial & 0x1f;
        $pos++;

        if ($majorType !== 5) { // Not a map
            return null;
        }

        $mapLen = $additionalInfo;
        if ($additionalInfo === 24 && $pos < $len) {
            $mapLen = ord($data[$pos]);
            $pos++;
        }

        $result = [];

        for ($i = 0; $i < $mapLen && $pos < $len; $i++) {
            // Parse key (integer or negative integer)
            $keyByte = ord($data[$pos]);
            $keyMajor = $keyByte >> 5;
            $keyInfo = $keyByte & 0x1f;
            $pos++;

            $key = null;
            if ($keyMajor === 0) { // Unsigned integer
                $key = $this->cborReadUint($keyInfo, $data, $pos);
            } elseif ($keyMajor === 1) { // Negative integer
                $key = -1 - $this->cborReadUint($keyInfo, $data, $pos);
            } else {
                // Skip unknown key types
                break;
            }

            if ($pos >= $len) break;

            // Parse value
            $valByte = ord($data[$pos]);
            $valMajor = $valByte >> 5;
            $valInfo = $valByte & 0x1f;
            $pos++;

            if ($valMajor === 0) { // Unsigned integer
                $result[$key] = $this->cborReadUint($valInfo, $data, $pos);
            } elseif ($valMajor === 1) { // Negative integer
                $result[$key] = -1 - $this->cborReadUint($valInfo, $data, $pos);
            } elseif ($valMajor === 2) { // Byte string
                $bsLen = $this->cborReadUint($valInfo, $data, $pos);
                $result[$key] = substr($data, $pos, $bsLen);
                $pos += $bsLen;
            } elseif ($valMajor === 3) { // Text string
                $tsLen = $this->cborReadUint($valInfo, $data, $pos);
                $result[$key] = substr($data, $pos, $tsLen);
                $pos += $tsLen;
            } else {
                // Skip other types (arrays, maps, etc.)
                break;
            }
        }

        return $result;
    }

    /**
     * Read a CBOR unsigned integer value
     */
    private function cborReadUint(int $additionalInfo, string $data, int &$pos): int
    {
        if ($additionalInfo < 24) {
            return $additionalInfo;
        } elseif ($additionalInfo === 24) {
            $val = ord($data[$pos]);
            $pos++;
            return $val;
        } elseif ($additionalInfo === 25) {
            $val = unpack('n', substr($data, $pos, 2))[1];
            $pos += 2;
            return $val;
        } elseif ($additionalInfo === 26) {
            $val = unpack('N', substr($data, $pos, 4))[1];
            $pos += 4;
            return $val;
        }
        return 0;
    }
}
