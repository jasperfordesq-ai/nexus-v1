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
use lbuchs\WebAuthn\WebAuthn;
use lbuchs\WebAuthn\WebAuthnException;


/**
 * WebAuthnApiController - WebAuthn/Passkey authentication API
 *
 * Uses lbuchs/WebAuthn library for proper CBOR parsing, attestation
 * verification, and signature validation. Supports ES256, RS256, EdDSA.
 *
 * Challenge Storage:
 * - Uses WebAuthnChallengeStore (Redis/file) with challenge_id for stateless clients
 * - Also stores in $_SESSION for backward compatibility
 *
 * Flow:
 * 1. POST /api/webauthn/register-challenge → returns creation options + challenge_id
 * 2. POST /api/webauthn/register-verify    → verifies attestation, stores credential
 * 3. POST /api/webauthn/auth-challenge     → returns request options + challenge_id
 * 4. POST /api/webauthn/auth-verify        → verifies assertion, returns auth tokens
 */
class WebAuthnApiController extends BaseApiController
{
    /**
     * POST /api/webauthn/register-challenge
     * Generate creation options for WebAuthn credential registration
     */
    public function registerChallenge()
    {
        $this->rateLimit('webauthn:register-challenge', 10, 60);

        $userId = $this->requireAuth();
        $user = $this->getUser($userId);

        if (!$user) {
            $this->error('User not found', 404, ApiErrorCodes::RESOURCE_NOT_FOUND);
        }

        // Generate random challenge
        $challenge = random_bytes(32);
        $challengeB64 = $this->base64UrlEncode($challenge);

        // Store challenge for stateless verification
        $challengeId = WebAuthnChallengeStore::create(
            $challengeB64,
            $userId,
            'register',
            ['email' => $user['email']]
        );

        // Also store in session for backward compatibility
        if (session_status() === PHP_SESSION_ACTIVE || session_status() === PHP_SESSION_NONE) {
            if (session_status() === PHP_SESSION_NONE) {
                session_start();
            }
            $_SESSION['webauthn_challenge'] = $challengeB64;
            $_SESSION['webauthn_challenge_expires'] = time() + 120;
        }

        // Get existing credentials to exclude (prevent re-registration)
        $tenantId = TenantContext::getId();
        $existingCredentials = Database::query(
            "SELECT credential_id FROM webauthn_credentials WHERE user_id = ? AND tenant_id = ?",
            [$userId, $tenantId]
        )->fetchAll();

        $excludeCredentials = array_map(function ($row) {
            return [
                'type' => 'public-key',
                'id' => $row['credential_id'],
            ];
        }, $existingCredentials);

        // Generate stable user handle (opaque, unique per user+tenant)
        $userHandle = $this->base64UrlEncode(
            hash('sha256', $userId . ':' . $tenantId, true)
        );

        $options = [
            'challenge' => $challengeB64,
            'challenge_id' => $challengeId,
            'rp' => [
                'name' => TenantContext::get()['name'] ?? 'Project NEXUS',
                'id' => $this->getRpId(),
            ],
            'user' => [
                'id' => $userHandle,
                'name' => $user['email'],
                'displayName' => $user['name'],
            ],
            'pubKeyCredParams' => [
                ['type' => 'public-key', 'alg' => -7],   // ES256
                ['type' => 'public-key', 'alg' => -257],  // RS256
            ],
            'authenticatorSelection' => [
                'userVerification' => 'preferred',
                'residentKey' => 'preferred',
                'requireResidentKey' => false,
            ],
            'timeout' => 60000,
            'attestation' => 'none',
            'excludeCredentials' => $excludeCredentials,
        ];

        $this->jsonResponse($options);
    }

    /**
     * POST /api/webauthn/register-verify
     * Verify attestation and store a new WebAuthn credential
     */
    public function registerVerify()
    {
        $userId = $this->requireAuth();
        $input = $this->getInput();

        // Retrieve the stored challenge
        $storedChallenge = $this->getStoredChallenge($input, 'register', $userId);

        if (empty($input['id']) || empty($input['response']['clientDataJSON']) || empty($input['response']['attestationObject'])) {
            $this->error('Invalid credential data', 400, ApiErrorCodes::VALIDATION_REQUIRED_FIELD);
        }

        // Decode the raw binary data from base64url
        $clientDataJSON = $this->base64UrlDecode($input['response']['clientDataJSON']);
        $attestationObject = $this->base64UrlDecode($input['response']['attestationObject']);

        // Decode the stored challenge back to raw bytes
        $challengeBytes = $this->base64UrlDecode($storedChallenge);

        // Use lbuchs/WebAuthn for proper verification
        $rpId = $this->getRpId();
        $rpName = TenantContext::get()['name'] ?? 'Project NEXUS';

        try {
            $webAuthn = new WebAuthn($rpName, $rpId, ['none'], true);

            $data = $webAuthn->processCreate(
                $clientDataJSON,
                $attestationObject,
                $challengeBytes,
                false,  // requireUserVerification
                true,   // requireUserPresent
                false,  // failIfRootMismatch (attestation=none)
                false   // requireCtsProfileMatch
            );
        } catch (WebAuthnException $e) {
            error_log('[WebAuthn] Registration verification failed: ' . $e->getMessage());
            $this->error(
                'Passkey registration failed: ' . $e->getMessage(),
                400,
                ApiErrorCodes::AUTH_WEBAUTHN_FAILED
            );
        }

        // Store the credential — public key is in PEM format from the library
        $credentialIdB64 = $input['id']; // Already base64url from browser
        $publicKeyPem = $data->credentialPublicKey;
        $signCount = $data->signatureCounter ?? 0;

        // Transport hints for future allowCredentials
        $transports = null;
        $transportData = $input['transports'] ?? $input['response']['transports'] ?? null;
        if (!empty($transportData) && is_array($transportData)) {
            $transports = json_encode($transportData);
        }

        // Device name from client
        $deviceName = null;
        if (!empty($input['device_name']) && is_string($input['device_name'])) {
            $deviceName = mb_substr(trim($input['device_name']), 0, 100);
        }

        // Authenticator attachment type
        $authenticatorType = $input['authenticatorAttachment'] ?? null;

        $tenantId = TenantContext::getId();

        Database::query(
            "INSERT INTO webauthn_credentials
                (user_id, tenant_id, credential_id, public_key, sign_count, transports, device_name, authenticator_type, created_at)
             VALUES (?, ?, ?, ?, ?, ?, ?, ?, NOW())",
            [
                $userId,
                $tenantId,
                $credentialIdB64,
                $publicKeyPem,
                $signCount,
                $transports,
                $deviceName,
                $authenticatorType,
            ]
        );

        // Consume the challenge (single-use)
        $this->consumeChallenge($input);

        $this->success(['message' => 'Passkey registered successfully']);
    }

    /**
     * POST /api/webauthn/auth-challenge
     * Generate request options for WebAuthn authentication
     */
    public function authChallenge()
    {
        $this->rateLimit('webauthn:auth-challenge', 10, 60);

        $input = $this->getInput();

        // Generate random challenge
        $challenge = random_bytes(32);
        $challengeB64 = $this->base64UrlEncode($challenge);

        // Determine user context (may be null for discoverable credential flow)
        $userId = null;
        $email = $input['email'] ?? null;

        // Check if user is already authenticated (re-auth scenario)
        $authUserId = $this->getAuthenticatedUserId();

        // Build allowCredentials list
        $allowCredentials = [];
        $tenantId = TenantContext::getId();

        if ($authUserId) {
            $userId = $authUserId;
            $credentials = Database::query(
                "SELECT credential_id, transports FROM webauthn_credentials WHERE user_id = ? AND tenant_id = ?",
                [$userId, $tenantId]
            )->fetchAll();

            $allowCredentials = $this->formatAllowCredentials($credentials);
        } elseif ($email) {
            $results = Database::query(
                "SELECT wc.credential_id, wc.transports, u.id as user_id
                 FROM webauthn_credentials wc
                 JOIN users u ON wc.user_id = u.id
                 WHERE u.email = ? AND u.tenant_id = ?",
                [$email, $tenantId]
            )->fetchAll();

            if (!empty($results)) {
                $userId = $results[0]['user_id'];
                $allowCredentials = $this->formatAllowCredentials($results);
            }
        }
        // If no email and not authenticated: empty allowCredentials = discoverable credential flow

        // Store challenge
        $challengeId = WebAuthnChallengeStore::create(
            $challengeB64,
            $userId,
            'authenticate',
            ['email' => $email]
        );

        // Session backup
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
            'userVerification' => 'preferred',
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

        // Retrieve stored challenge
        $storedChallenge = $this->getStoredAuthChallenge($input);

        if (empty($input['id']) || empty($input['response']['clientDataJSON']) ||
            empty($input['response']['authenticatorData']) || empty($input['response']['signature'])) {
            $this->error('Invalid assertion data', 400, ApiErrorCodes::VALIDATION_REQUIRED_FIELD);
        }

        // Find credential by ID
        $tenantId = TenantContext::getId();
        $credential = Database::query(
            "SELECT wc.*, u.id as uid, u.first_name, u.last_name, u.email, u.role, u.tenant_id
             FROM webauthn_credentials wc
             JOIN users u ON wc.user_id = u.id
             WHERE wc.credential_id = ? AND wc.tenant_id = ?",
            [$input['id'], $tenantId]
        )->fetch();

        if (!$credential) {
            $this->error('Credential not found', 401, ApiErrorCodes::AUTH_WEBAUTHN_CREDENTIAL_NOT_FOUND);
        }

        // Decode binary data from base64url
        $clientDataJSON = $this->base64UrlDecode($input['response']['clientDataJSON']);
        $authenticatorData = $this->base64UrlDecode($input['response']['authenticatorData']);
        $signature = $this->base64UrlDecode($input['response']['signature']);
        $challengeBytes = $this->base64UrlDecode($storedChallenge);

        // Use lbuchs/WebAuthn for proper signature verification
        $rpId = $this->getRpId();
        $rpName = TenantContext::get()['name'] ?? 'Project NEXUS';

        try {
            $webAuthn = new WebAuthn($rpName, $rpId, ['none'], true);

            $webAuthn->processGet(
                $clientDataJSON,
                $authenticatorData,
                $signature,
                $credential['public_key'],  // PEM format stored in DB
                $challengeBytes,
                (int)$credential['sign_count'],
                false,  // requireUserVerification
                true    // requireUserPresent
            );
        } catch (WebAuthnException $e) {
            error_log('[WebAuthn] Authentication verification failed: ' . $e->getMessage());
            $this->error(
                'Passkey authentication failed: ' . $e->getMessage(),
                401,
                ApiErrorCodes::AUTH_WEBAUTHN_FAILED
            );
        }

        // Update sign count
        $newSignCount = $webAuthn->getSignatureCounter();
        Database::query(
            "UPDATE webauthn_credentials SET sign_count = ?, last_used_at = NOW() WHERE id = ? AND tenant_id = ?",
            [$newSignCount ?? 0, $credential['id'], $tenantId]
        );

        // Consume challenge
        $this->consumeChallenge($input);
        unset(
            $_SESSION['webauthn_auth_challenge'],
            $_SESSION['webauthn_auth_challenge_expires'],
            $_SESSION['webauthn_auth_email']
        );

        // Enforce login gates (email verified, approved, etc.)
        $webauthnUser = Database::query(
            "SELECT id, role, is_super_admin, is_tenant_super_admin, tenant_id, email_verified_at, is_approved FROM users WHERE id = ? AND tenant_id = ?",
            [(int)$credential['uid'], (int)$credential['tenant_id']]
        )->fetch();

        if ($webauthnUser) {
            $gateBlock = \Nexus\Services\TenantSettingsService::checkLoginGates($webauthnUser);
            if ($gateBlock) {
                $this->error($gateBlock['message'], 403, $gateBlock['code']);
            }
        }

        // Generate auth tokens
        $isMobile = TokenService::isMobileRequest();
        $accessToken = TokenService::generateToken(
            (int)$credential['uid'],
            (int)$credential['tenant_id'],
            ['role' => $credential['role'], 'email' => $credential['email']],
            $isMobile
        );
        $refreshToken = TokenService::generateRefreshToken(
            (int)$credential['uid'],
            (int)$credential['tenant_id'],
            $isMobile
        );

        // Set up session for browser clients
        $wantsStateless = TokenService::isMobileRequest() || isset($_SERVER['HTTP_X_STATELESS_AUTH']);
        if (!$wantsStateless) {
            $currentSessionUser = $_SESSION['user_id'] ?? null;
            if ($currentSessionUser === null) {
                $preservedLayout = $_SESSION['nexus_active_layout'] ?? $_SESSION['nexus_layout'] ?? null;
                session_regenerate_id(true);
                if ($preservedLayout) {
                    $_SESSION['nexus_active_layout'] = $preservedLayout;
                    $_SESSION['nexus_layout'] = $preservedLayout;
                }
                $_SESSION['user_id'] = $credential['uid'];
                $_SESSION['user_name'] = trim($credential['first_name'] . ' ' . $credential['last_name']);
                $_SESSION['user_email'] = $credential['email'];
                $_SESSION['user_role'] = $credential['role'];
                $_SESSION['tenant_id'] = $credential['tenant_id'];
                $_SESSION['is_logged_in'] = true;
            }
        }

        $this->jsonResponse([
            'success' => true,
            'message' => 'Authentication successful',
            'user' => [
                'id' => $credential['uid'],
                'first_name' => $credential['first_name'],
                'last_name' => $credential['last_name'],
                'email' => $credential['email'],
            ],
            'access_token' => $accessToken,
            'refresh_token' => $refreshToken,
            'token_type' => 'Bearer',
            'expires_in' => TokenService::getAccessTokenExpiry($isMobile),
            'is_mobile' => $isMobile,
        ]);
    }

    /**
     * POST /api/webauthn/remove
     */
    public function remove()
    {
        if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
            $this->error('Method not allowed. Use POST request.', 405, ApiErrorCodes::VALIDATION_ERROR);
        }

        $this->verifyCsrf();
        $userId = $this->requireAuth();
        $input = $this->getInput();
        $credentialId = $input['credential_id'] ?? null;
        $tenantId = TenantContext::getId();

        if ($credentialId) {
            Database::query(
                "DELETE FROM webauthn_credentials WHERE credential_id = ? AND user_id = ? AND tenant_id = ?",
                [$credentialId, $userId, $tenantId]
            );
        } else {
            Database::query(
                "DELETE FROM webauthn_credentials WHERE user_id = ? AND tenant_id = ?",
                [$userId, $tenantId]
            );
        }

        $this->success(['message' => 'Credential(s) removed']);
    }

    /**
     * POST /api/webauthn/rename
     * Rename a WebAuthn credential's device name
     */
    public function rename()
    {
        if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
            $this->error('Method not allowed. Use POST request.', 405, ApiErrorCodes::VALIDATION_ERROR);
        }

        $this->verifyCsrf();
        $userId = $this->requireAuth();
        $input = $this->getInput();
        $credentialId = $input['credential_id'] ?? null;
        $newName = $input['device_name'] ?? null;

        if (!$credentialId || !$newName) {
            $this->error('credential_id and device_name are required', 400, ApiErrorCodes::VALIDATION_ERROR);
        }

        $newName = mb_substr(trim($newName), 0, 100);
        if (empty($newName)) {
            $this->error('device_name cannot be empty', 400, ApiErrorCodes::VALIDATION_ERROR);
        }

        $tenantId = TenantContext::getId();
        $stmt = Database::query(
            "UPDATE webauthn_credentials SET device_name = ? WHERE credential_id = ? AND user_id = ? AND tenant_id = ?",
            [$newName, $credentialId, $userId, $tenantId]
        );

        if ($stmt->rowCount() === 0) {
            $this->error('Credential not found', 404, ApiErrorCodes::NOT_FOUND);
        }

        $this->jsonResponse(['success' => true, 'device_name' => $newName]);
    }

    /**
     * POST /api/webauthn/remove-all
     */
    public function removeAll()
    {
        if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
            $this->error('Method not allowed. Use POST request.', 405, ApiErrorCodes::VALIDATION_ERROR);
        }

        $this->verifyCsrf();
        $userId = $this->requireAuth();
        $tenantId = TenantContext::getId();

        $result = Database::query(
            "SELECT COUNT(*) as count FROM webauthn_credentials WHERE user_id = ? AND tenant_id = ?",
            [$userId, $tenantId]
        )->fetch();
        $count = (int)$result['count'];

        Database::query(
            "DELETE FROM webauthn_credentials WHERE user_id = ? AND tenant_id = ?",
            [$userId, $tenantId]
        );

        $this->success([
            'message' => "Removed {$count} passkey(s). You can now re-register on any device.",
            'removed_count' => $count,
        ]);
    }

    /**
     * GET /api/webauthn/credentials
     */
    public function credentials()
    {
        $userId = $this->requireAuth();
        $tenantId = TenantContext::getId();

        $credentials = Database::query(
            "SELECT credential_id, device_name, authenticator_type, created_at, last_used_at
             FROM webauthn_credentials
             WHERE user_id = ? AND tenant_id = ?
             ORDER BY created_at DESC",
            [$userId, $tenantId]
        )->fetchAll();

        $this->jsonResponse([
            'credentials' => $credentials,
            'count' => count($credentials),
        ]);
    }

    /**
     * GET /api/webauthn/status
     */
    public function status()
    {
        $userId = $this->getAuthenticatedUserId();

        if (!$userId) {
            $this->jsonResponse(['registered' => false, 'count' => 0]);
            return;
        }

        $tenantId = TenantContext::getId();
        $result = Database::query(
            "SELECT COUNT(*) as count FROM webauthn_credentials WHERE user_id = ? AND tenant_id = ?",
            [$userId, $tenantId]
        )->fetch();

        $this->jsonResponse([
            'registered' => $result['count'] > 0,
            'count' => (int)$result['count'],
        ]);
    }

    // ========================================================================
    // HELPER METHODS
    // ========================================================================

    private function getInput(): array
    {
        $input = file_get_contents('php://input');
        return json_decode($input, true) ?? [];
    }

    private function getUser(int $userId): ?array
    {
        return Database::query(
            "SELECT id, CONCAT(first_name, ' ', last_name) as name, email FROM users WHERE id = ? AND tenant_id = ?",
            [$userId, TenantContext::getId()]
        )->fetch() ?: null;
    }

    /**
     * Get the Relying Party ID (domain).
     *
     * For cross-origin setups (SPA at app.project-nexus.ie, API at api.project-nexus.ie),
     * the RP ID must be the registrable domain shared by both: project-nexus.ie
     */
    private function getRpId(): string
    {
        // Environment variable takes priority — allows explicit RP ID configuration
        $envRpId = $_ENV['WEBAUTHN_RP_ID'] ?? $_SERVER['WEBAUTHN_RP_ID'] ?? getenv('WEBAUTHN_RP_ID');
        if ($envRpId && is_string($envRpId) && $envRpId !== '') {
            return $envRpId;
        }

        $host = $_SERVER['HTTP_HOST'] ?? 'localhost';
        if (!empty($_SERVER['HTTP_ORIGIN'])) {
            $parsed = parse_url($_SERVER['HTTP_ORIGIN']);
            if ($parsed && isset($parsed['host'])) {
                $host = $parsed['host'];
            }
        }

        // Remove port
        $host = preg_replace('/:\d+$/', '', $host);

        // For production multi-subdomain setup, extract registrable domain
        // This handles 2-level TLDs (example.com) but NOT multi-level TLDs (.co.uk)
        // For multi-level TLDs, set WEBAUTHN_RP_ID environment variable
        if ($host !== 'localhost' && $host !== '127.0.0.1' && substr_count($host, '.') >= 2) {
            $parts = explode('.', $host);
            if (count($parts) >= 3) {
                return implode('.', array_slice($parts, -2));
            }
        }

        return $host;
    }

    private function base64UrlEncode(string $data): string
    {
        return rtrim(strtr(base64_encode($data), '+/', '-_'), '=');
    }

    private function base64UrlDecode(string $data): string
    {
        return base64_decode(strtr($data, '-_', '+/') . str_repeat('=', (4 - strlen($data) % 4) % 4));
    }

    /**
     * Format allowCredentials array for auth challenge response
     */
    private function formatAllowCredentials(array $credentials): array
    {
        return array_map(function ($row) {
            $cred = [
                'type' => 'public-key',
                'id' => $row['credential_id'],
            ];
            if (!empty($row['transports'])) {
                $cred['transports'] = json_decode($row['transports'], true);
            }
            return $cred;
        }, $credentials);
    }

    /**
     * Get stored challenge for registration verification.
     * Tries challenge_id (stateless) first, then session fallback.
     */
    private function getStoredChallenge(array $input, string $expectedType, int $userId): string
    {
        $challengeId = $input['challenge_id'] ?? null;

        if ($challengeId) {
            $challengeData = WebAuthnChallengeStore::get($challengeId);
            if (!$challengeData) {
                $this->error('Challenge expired or invalid', 401, ApiErrorCodes::AUTH_WEBAUTHN_CHALLENGE_EXPIRED);
            }
            if ($challengeData['user_id'] !== $userId) {
                $this->error('Challenge user mismatch', 401, ApiErrorCodes::AUTH_WEBAUTHN_CHALLENGE_INVALID);
            }
            if ($challengeData['type'] !== $expectedType) {
                $this->error('Invalid challenge type', 401, ApiErrorCodes::AUTH_WEBAUTHN_CHALLENGE_INVALID);
            }
            return $challengeData['challenge'];
        }

        // Session fallback
        if (session_status() === PHP_SESSION_NONE) {
            session_start();
        }
        if (empty($_SESSION['webauthn_challenge']) ||
            empty($_SESSION['webauthn_challenge_expires']) ||
            time() > $_SESSION['webauthn_challenge_expires']) {
            $this->error('Challenge expired', 401, ApiErrorCodes::AUTH_WEBAUTHN_CHALLENGE_EXPIRED);
        }
        return $_SESSION['webauthn_challenge'];
    }

    /**
     * Get stored challenge for authentication verification.
     */
    private function getStoredAuthChallenge(array $input): string
    {
        $challengeId = $input['challenge_id'] ?? null;

        if ($challengeId) {
            $challengeData = WebAuthnChallengeStore::get($challengeId);
            if (!$challengeData) {
                $this->error('Challenge expired or invalid', 401, ApiErrorCodes::AUTH_WEBAUTHN_CHALLENGE_EXPIRED);
            }
            if ($challengeData['type'] !== 'authenticate') {
                $this->error('Invalid challenge type', 401, ApiErrorCodes::AUTH_WEBAUTHN_CHALLENGE_INVALID);
            }
            return $challengeData['challenge'];
        }

        // Session fallback
        if (session_status() === PHP_SESSION_NONE) {
            session_start();
        }
        if (empty($_SESSION['webauthn_auth_challenge']) ||
            empty($_SESSION['webauthn_auth_challenge_expires']) ||
            time() > $_SESSION['webauthn_auth_challenge_expires']) {
            $this->error('Challenge expired', 401, ApiErrorCodes::AUTH_WEBAUTHN_CHALLENGE_EXPIRED);
        }
        return $_SESSION['webauthn_auth_challenge'];
    }

    /**
     * Consume (delete) a challenge after successful verification
     */
    private function consumeChallenge(array $input): void
    {
        $challengeId = $input['challenge_id'] ?? null;
        if ($challengeId) {
            WebAuthnChallengeStore::consume($challengeId);
        }
        unset($_SESSION['webauthn_challenge'], $_SESSION['webauthn_challenge_expires']);
    }
}
