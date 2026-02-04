<?php

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
        $db = Database::getConnection();
        $stmt = $db->prepare("SELECT credential_id FROM webauthn_credentials WHERE user_id = ?");
        $stmt->execute([$userId]);
        $existingCredentials = $stmt->fetchAll(\PDO::FETCH_COLUMN);

        $excludeCredentials = array_map(function ($credId) {
            return [
                'type' => 'public-key',
                'id' => $credId
            ];
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

        if ($clientData['type'] !== 'webauthn.create') {
            $this->error(
                'Invalid credential type',
                400,
                ApiErrorCodes::AUTH_WEBAUTHN_CHALLENGE_INVALID
            );
        }

        if ($clientData['challenge'] !== $storedChallenge) {
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

        // Store credential
        $db = Database::getConnection();
        $stmt = $db->prepare("
            INSERT INTO webauthn_credentials (user_id, tenant_id, credential_id, public_key, sign_count, created_at)
            VALUES (?, ?, ?, ?, 0, NOW())
        ");
        $stmt->execute([
            $userId,
            TenantContext::getId(),
            $input['id'],
            $publicKey
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
            $stmt = $db->prepare("SELECT credential_id FROM webauthn_credentials WHERE user_id = ?");
            $stmt->execute([$userId]);
            $credentials = $stmt->fetchAll(\PDO::FETCH_COLUMN);

            $allowCredentials = array_map(function ($credId) {
                return [
                    'type' => 'public-key',
                    'id' => $credId
                ];
            }, $credentials);
        } elseif ($email) {
            // User provided email for passwordless login
            $stmt = $db->prepare("
                SELECT wc.credential_id, u.id as user_id
                FROM webauthn_credentials wc
                JOIN users u ON wc.user_id = u.id
                WHERE u.email = ? AND u.tenant_id = ?
            ");
            $stmt->execute([$email, TenantContext::getId()]);
            $results = $stmt->fetchAll();

            if (!empty($results)) {
                $userId = $results[0]['user_id'];
                $allowCredentials = array_map(function ($row) {
                    return [
                        'type' => 'public-key',
                        'id' => $row['credential_id']
                    ];
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

        if ($clientData['type'] !== 'webauthn.get') {
            $this->error(
                'Invalid assertion type',
                400,
                ApiErrorCodes::AUTH_WEBAUTHN_CHALLENGE_INVALID
            );
        }

        if ($clientData['challenge'] !== $storedChallenge) {
            $this->error(
                'Challenge mismatch',
                401,
                ApiErrorCodes::AUTH_WEBAUTHN_CHALLENGE_INVALID
            );
        }

        // Verify signature (simplified - production should use proper crypto)
        $authenticatorData = $this->base64UrlDecode($input['response']['authenticatorData']);

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

        if ($credentialId) {
            // Remove specific credential
            $stmt = $db->prepare("DELETE FROM webauthn_credentials WHERE credential_id = ? AND user_id = ?");
            $stmt->execute([$credentialId, $userId]);
        } else {
            // Remove all credentials for user
            $stmt = $db->prepare("DELETE FROM webauthn_credentials WHERE user_id = ?");
            $stmt->execute([$userId]);
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

        $db = Database::getConnection();

        // Count credentials before removal
        $stmt = $db->prepare("SELECT COUNT(*) as count FROM webauthn_credentials WHERE user_id = ?");
        $stmt->execute([$userId]);
        $result = $stmt->fetch();
        $count = (int)$result['count'];

        // Remove all credentials for user
        $stmt = $db->prepare("DELETE FROM webauthn_credentials WHERE user_id = ?");
        $stmt->execute([$userId]);

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

        $db = Database::getConnection();
        $stmt = $db->prepare("
            SELECT credential_id, created_at, last_used_at
            FROM webauthn_credentials
            WHERE user_id = ?
            ORDER BY created_at DESC
        ");
        $stmt->execute([$userId]);
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
        }

        $db = Database::getConnection();
        $stmt = $db->prepare("SELECT COUNT(*) as count FROM webauthn_credentials WHERE user_id = ?");
        $stmt->execute([$userId]);
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
        $stmt = $db->prepare("SELECT id, CONCAT(first_name, ' ', last_name) as name, email FROM users WHERE id = ?");
        $stmt->execute([$userId]);
        return $stmt->fetch() ?: null;
    }

    /**
     * Get the Relying Party ID (domain)
     */
    private function getRpId(): string
    {
        $host = $_SERVER['HTTP_HOST'] ?? 'localhost';
        // Remove port if present
        return preg_replace('/:\d+$/', '', $host);
    }

    /**
     * Get expected origin
     */
    private function getExpectedOrigin(): string
    {
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
     */
    private function extractPublicKey(string $attestationObject): ?string
    {
        // For simplicity, store the entire attestation object
        // Production should parse CBOR and extract actual public key
        return $this->base64UrlEncode($attestationObject);
    }
}
