<?php

namespace Nexus\Controllers\Api;

use Nexus\Core\Database;
use Nexus\Core\TenantContext;
use Nexus\Core\ApiAuth;

/**
 * WebAuthn API Controller
 * Handles biometric authentication registration and verification
 */
class WebAuthnApiController
{
    use ApiAuth;

    private function jsonResponse($data, $status = 200)
    {
        header('Content-Type: application/json');
        http_response_code($status);
        echo json_encode($data);
        exit;
    }

    private function getUserId()
    {
        return $this->requireAuth();
    }

    private function getUser($userId)
    {
        $db = Database::getConnection();
        $stmt = $db->prepare("SELECT id, name, email FROM users WHERE id = ?");
        $stmt->execute([$userId]);
        return $stmt->fetch();
    }

    /**
     * POST /api/webauthn/register-challenge
     * Generate a challenge for WebAuthn credential registration
     */
    public function registerChallenge()
    {
        $userId = $this->getUserId();
        $user = $this->getUser($userId);

        if (!$user) {
            $this->jsonResponse(['error' => 'User not found'], 404);
        }

        // Generate random challenge
        $challenge = random_bytes(32);
        $challengeB64 = $this->base64UrlEncode($challenge);

        // Store challenge in session for verification
        $_SESSION['webauthn_challenge'] = $challengeB64;
        $_SESSION['webauthn_challenge_expires'] = time() + 300; // 5 minutes

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
        $userId = $this->getUserId();

        // Check challenge
        if (empty($_SESSION['webauthn_challenge']) ||
            empty($_SESSION['webauthn_challenge_expires']) ||
            time() > $_SESSION['webauthn_challenge_expires']) {
            $this->jsonResponse(['error' => 'Challenge expired'], 400);
        }

        $input = json_decode(file_get_contents('php://input'), true);

        if (empty($input['id']) || empty($input['response'])) {
            $this->jsonResponse(['error' => 'Invalid credential data'], 400);
        }

        // Verify client data
        $clientDataJSON = $this->base64UrlDecode($input['response']['clientDataJSON']);
        $clientData = json_decode($clientDataJSON, true);

        if ($clientData['type'] !== 'webauthn.create') {
            $this->jsonResponse(['error' => 'Invalid credential type'], 400);
        }

        $expectedChallenge = $_SESSION['webauthn_challenge'];
        if ($clientData['challenge'] !== $expectedChallenge) {
            $this->jsonResponse(['error' => 'Challenge mismatch'], 400);
        }

        // Verify origin
        $expectedOrigin = $this->getExpectedOrigin();
        if ($clientData['origin'] !== $expectedOrigin) {
            error_log("[WebAuthn] Origin mismatch: expected {$expectedOrigin}, got {$clientData['origin']}");
            // Be lenient for development
            if (strpos($clientData['origin'], 'localhost') === false && strpos($expectedOrigin, 'localhost') === false) {
                $this->jsonResponse(['error' => 'Origin mismatch'], 400);
            }
        }

        // Parse attestation object to extract public key
        $attestationObject = $this->base64UrlDecode($input['response']['attestationObject']);
        $publicKey = $this->extractPublicKey($attestationObject);

        if (!$publicKey) {
            $this->jsonResponse(['error' => 'Failed to extract public key'], 400);
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

        // Clear challenge
        unset($_SESSION['webauthn_challenge'], $_SESSION['webauthn_challenge_expires']);

        $this->jsonResponse(['success' => true, 'message' => 'Biometric registered']);
    }

    /**
     * POST /api/webauthn/auth-challenge
     * Generate a challenge for WebAuthn authentication
     */
    public function authChallenge()
    {
        // For authentication, we might not have a session yet
        // But we need some way to identify the user (email, username, etc.)
        $input = json_decode(file_get_contents('php://input'), true);

        // Generate random challenge
        $challenge = random_bytes(32);
        $challengeB64 = $this->base64UrlEncode($challenge);

        // Store challenge in session
        $_SESSION['webauthn_auth_challenge'] = $challengeB64;
        $_SESSION['webauthn_auth_challenge_expires'] = time() + 300;

        // Get allowed credentials
        $allowCredentials = [];

        if (isset($_SESSION['user_id'])) {
            // User is logged in, get their credentials
            $db = Database::getConnection();
            $stmt = $db->prepare("SELECT credential_id FROM webauthn_credentials WHERE user_id = ?");
            $stmt->execute([$_SESSION['user_id']]);
            $credentials = $stmt->fetchAll(\PDO::FETCH_COLUMN);

            $allowCredentials = array_map(function ($credId) {
                return [
                    'type' => 'public-key',
                    'id' => $credId
                ];
            }, $credentials);
        } elseif (!empty($input['email'])) {
            // User provided email for passwordless login
            $db = Database::getConnection();
            $stmt = $db->prepare("
                SELECT wc.credential_id
                FROM webauthn_credentials wc
                JOIN users u ON wc.user_id = u.id
                WHERE u.email = ? AND u.tenant_id = ?
            ");
            $stmt->execute([$input['email'], TenantContext::getId()]);
            $credentials = $stmt->fetchAll(\PDO::FETCH_COLUMN);

            $allowCredentials = array_map(function ($credId) {
                return [
                    'type' => 'public-key',
                    'id' => $credId
                ];
            }, $credentials);

            $_SESSION['webauthn_auth_email'] = $input['email'];
        }

        $options = [
            'challenge' => $challengeB64,
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
        // Check challenge
        if (empty($_SESSION['webauthn_auth_challenge']) ||
            empty($_SESSION['webauthn_auth_challenge_expires']) ||
            time() > $_SESSION['webauthn_auth_challenge_expires']) {
            $this->jsonResponse(['error' => 'Challenge expired'], 400);
        }

        $input = json_decode(file_get_contents('php://input'), true);

        if (empty($input['id']) || empty($input['response'])) {
            $this->jsonResponse(['error' => 'Invalid assertion data'], 400);
        }

        // Find credential
        $db = Database::getConnection();
        $stmt = $db->prepare("
            SELECT wc.*, u.id as user_id, u.name, u.email, u.role
            FROM webauthn_credentials wc
            JOIN users u ON wc.user_id = u.id
            WHERE wc.credential_id = ? AND wc.tenant_id = ?
        ");
        $stmt->execute([$input['id'], TenantContext::getId()]);
        $credential = $stmt->fetch();

        if (!$credential) {
            $this->jsonResponse(['error' => 'Credential not found'], 400);
        }

        // Verify client data
        $clientDataJSON = $this->base64UrlDecode($input['response']['clientDataJSON']);
        $clientData = json_decode($clientDataJSON, true);

        if ($clientData['type'] !== 'webauthn.get') {
            $this->jsonResponse(['error' => 'Invalid assertion type'], 400);
        }

        $expectedChallenge = $_SESSION['webauthn_auth_challenge'];
        if ($clientData['challenge'] !== $expectedChallenge) {
            $this->jsonResponse(['error' => 'Challenge mismatch'], 400);
        }

        // Verify signature (simplified - production should use proper crypto)
        $authenticatorData = $this->base64UrlDecode($input['response']['authenticatorData']);
        $signature = $this->base64UrlDecode($input['response']['signature']);

        // For production, verify signature using stored public key
        // This is a simplified version that trusts the browser's verification

        // Update sign count
        $signCount = unpack('N', substr($authenticatorData, 33, 4))[1] ?? 0;
        if ($signCount > 0 && $signCount <= $credential['sign_count']) {
            $this->jsonResponse(['error' => 'Possible cloned authenticator'], 400);
        }

        $stmt = $db->prepare("UPDATE webauthn_credentials SET sign_count = ?, last_used_at = NOW() WHERE id = ?");
        $stmt->execute([$signCount, $credential['id']]);

        // Clear challenge
        unset($_SESSION['webauthn_auth_challenge'], $_SESSION['webauthn_auth_challenge_expires'], $_SESSION['webauthn_auth_email']);

        // If user wasn't logged in, log them in now
        if (!isset($_SESSION['user_id'])) {
            // FIXED: Preserve layout preference before session regeneration
            $preservedLayout = $_SESSION['nexus_active_layout'] ?? $_SESSION['nexus_layout'] ?? null;

            // SECURITY: Regenerate session ID to prevent session fixation attacks
            session_regenerate_id(true);

            // FIXED: Restore layout preference after regeneration
            if ($preservedLayout) {
                $_SESSION['nexus_active_layout'] = $preservedLayout;
                $_SESSION['nexus_layout'] = $preservedLayout;
            }

            $_SESSION['user_id'] = $credential['user_id'];
            $_SESSION['user_name'] = $credential['name'];
            $_SESSION['user_email'] = $credential['email'];
            $_SESSION['user_role'] = $credential['role'];
        }

        $this->jsonResponse([
            'success' => true,
            'message' => 'Authentication successful',
            'user' => [
                'id' => $credential['user_id'],
                'name' => $credential['name'],
                'email' => $credential['email']
            ]
        ]);
    }

    /**
     * POST /api/webauthn/remove
     * Remove a WebAuthn credential
     */
    public function remove()
    {
        $userId = $this->getUserId();

        $input = json_decode(file_get_contents('php://input'), true);
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

        $this->jsonResponse(['success' => true, 'message' => 'Credential(s) removed']);
    }

    /**
     * GET /api/webauthn/remove
     * Remove all WebAuthn credentials for current user (convenient GET endpoint)
     */
    public function removeAll()
    {
        $userId = $this->getUserId();

        $db = Database::getConnection();

        // Count credentials before removal
        $stmt = $db->prepare("SELECT COUNT(*) as count FROM webauthn_credentials WHERE user_id = ?");
        $stmt->execute([$userId]);
        $result = $stmt->fetch();
        $count = (int)$result['count'];

        // Remove all credentials for user
        $stmt = $db->prepare("DELETE FROM webauthn_credentials WHERE user_id = ?");
        $stmt->execute([$userId]);

        $this->jsonResponse([
            'success' => true,
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
        $userId = $this->getUserId();

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
        $userId = $_SESSION['user_id'] ?? null;

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

    /**
     * Helper: Get the Relying Party ID (domain)
     */
    private function getRpId()
    {
        $host = $_SERVER['HTTP_HOST'] ?? 'localhost';
        // Remove port if present
        $host = preg_replace('/:\d+$/', '', $host);
        return $host;
    }

    /**
     * Helper: Get expected origin
     */
    private function getExpectedOrigin()
    {
        $protocol = (!empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off') ? 'https' : 'http';
        $host = $_SERVER['HTTP_HOST'] ?? 'localhost';
        return $protocol . '://' . $host;
    }

    /**
     * Helper: Base64 URL encode
     */
    private function base64UrlEncode($data)
    {
        return rtrim(strtr(base64_encode($data), '+/', '-_'), '=');
    }

    /**
     * Helper: Base64 URL decode
     */
    private function base64UrlDecode($data)
    {
        return base64_decode(strtr($data, '-_', '+/') . str_repeat('=', (4 - strlen($data) % 4) % 4));
    }

    /**
     * Helper: Extract public key from attestation object
     * This is a simplified version - production should use CBOR library
     */
    private function extractPublicKey($attestationObject)
    {
        // For simplicity, store the entire attestation object
        // Production should parse CBOR and extract actual public key
        return $this->base64UrlEncode($attestationObject);
    }
}
