<?php
// Copyright © 2024–2026 Jasper Ford
// SPDX-License-Identifier: AGPL-3.0-or-later
// Author: Jasper Ford
// See NOTICE file for attribution and acknowledgements.

namespace App\Http\Controllers\Api;

use App\Core\EmailTemplate;
use App\Core\Mailer;
use App\Models\Notification;
use App\Models\User;
use App\Services\TenantSettingsService;
use App\Services\TokenService;
use App\Services\WebAuthnChallengeStore;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Facades\DB;
use App\Core\ApiErrorCodes;
use App\Core\TenantContext;
use lbuchs\WebAuthn\WebAuthn;
use lbuchs\WebAuthn\WebAuthnException;

/**
 * WebAuthnController -- WebAuthn/Passkey authentication.
 *
 * Converted from delegation to direct service calls.
 * Legacy: src/Controllers/Api/WebAuthnApiController.php
 */
class WebAuthnController extends BaseApiController
{
    protected bool $isV2Api = true;

    public function __construct(
        private readonly TenantSettingsService $tenantSettingsService,
        private readonly WebAuthnChallengeStore $webAuthnChallengeStore,
        private readonly TokenService $tokenService,
    ) {}

    /** POST /api/webauthn/register-challenge */
    public function registerChallenge(): JsonResponse
    {
        $this->rateLimit('webauthn_register_challenge', 10, 60);

        $userId = $this->requireAuth();
        $user = $this->getWebAuthnUser($userId);

        if (!$user) {
            return $this->respondWithError(ApiErrorCodes::RESOURCE_NOT_FOUND, 'User not found', null, 404);
        }

        // Generate random challenge
        $challenge = random_bytes(32);
        $challengeB64 = $this->base64UrlEncode($challenge);

        // Store challenge for stateless verification
        $challengeId = $this->webAuthnChallengeStore->create(
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
        $existingCredentials = DB::select(
            "SELECT credential_id FROM webauthn_credentials WHERE user_id = ? AND tenant_id = ?",
            [$userId, $tenantId]
        );

        $excludeCredentials = array_map(function ($row) {
            return [
                'type' => 'public-key',
                'id' => $row->credential_id,
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

        return $this->respondWithData($options);
    }

    /** POST /api/webauthn/register-verify */
    public function registerVerify(): JsonResponse
    {
        $userId = $this->requireAuth();
        $input = $this->getAllInput();

        // Retrieve the stored challenge
        $storedChallenge = $this->getStoredChallenge($input, 'register', $userId);
        if ($storedChallenge instanceof JsonResponse) {
            return $storedChallenge;
        }

        if (empty($input['id']) || empty($input['response']['clientDataJSON']) || empty($input['response']['attestationObject'])) {
            return $this->respondWithError(ApiErrorCodes::VALIDATION_REQUIRED_FIELD, 'Invalid credential data', null, 400);
        }

        // Decode the raw binary data from base64url
        $clientDataJSON = $this->base64UrlDecode($input['response']['clientDataJSON']);
        $attestationObject = $this->base64UrlDecode($input['response']['attestationObject']);

        // Decode the stored challenge back to raw bytes
        $challengeBytes = $this->base64UrlDecode($storedChallenge);

        // Use lbuchs/WebAuthn for proper verification
        $rpId = $this->getRpId();
        $rpName = TenantContext::get()['name'] ?? 'Project NEXUS';

        $data = null;
        try {
            $webAuthn = new WebAuthn($rpName, $rpId, ['none'], true);

            $data = $webAuthn->processCreate(
                $clientDataJSON,
                $attestationObject,
                $challengeBytes,
                false,  // requireUserVerification
                true,   // requireUserPresent
                false,  // failIfRootMismatch
                false   // requireCtsProfileMatch
            );
        } catch (WebAuthnException $e) {
            error_log('[WebAuthn] Registration verification failed: ' . $e->getMessage());
            return $this->respondWithError(ApiErrorCodes::AUTH_WEBAUTHN_FAILED, 'Passkey registration failed. Please try again.', null, 400);
        }

        // Store the credential
        $credentialIdB64 = $input['id'];
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

        DB::insert(
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

        // Security notification: bell for passkey registered
        try {
            $passkeyLabel = $deviceName ? "\"$deviceName\"" : 'A new passkey';
            Notification::createNotification(
                $userId,
                "$passkeyLabel has been registered on your account.",
                null,
                'passkey_registered'
            );
        } catch (\Throwable $e) {
            error_log("Failed to create passkey registered notification: " . $e->getMessage());
        }

        return $this->respondWithData(['message' => 'Passkey registered successfully']);
    }

    /** POST /api/webauthn/auth-challenge */
    public function authChallenge(): JsonResponse
    {
        $this->rateLimit('webauthn_auth_challenge', 10, 60);

        $input = $this->getAllInput();

        // Generate random challenge
        $challenge = random_bytes(32);
        $challengeB64 = $this->base64UrlEncode($challenge);

        // Determine user context (may be null for discoverable credential flow)
        $userId = null;
        $email = $input['email'] ?? null;

        // Check if user is already authenticated (re-auth scenario)
        $authUserId = $this->getOptionalUserId();

        // Build allowCredentials list
        $allowCredentials = [];
        $tenantId = TenantContext::getId();

        if ($authUserId) {
            $userId = $authUserId;
            $credentials = DB::select(
                "SELECT credential_id, transports FROM webauthn_credentials WHERE user_id = ? AND tenant_id = ?",
                [$userId, $tenantId]
            );

            $allowCredentials = $this->formatAllowCredentials(array_map(fn($r) => (array)$r, $credentials));
        } elseif ($email) {
            $results = DB::select(
                "SELECT wc.credential_id, wc.transports, u.id as user_id
                 FROM webauthn_credentials wc
                 JOIN users u ON wc.user_id = u.id
                 WHERE u.email = ? AND u.tenant_id = ?",
                [$email, $tenantId]
            );

            if (!empty($results)) {
                $userId = $results[0]->user_id;
                $allowCredentials = $this->formatAllowCredentials(array_map(fn($r) => (array)$r, $results));
            }
        }

        // Store challenge
        $challengeId = $this->webAuthnChallengeStore->create(
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

        return $this->respondWithData($options);
    }

    /** POST /api/webauthn/auth-verify */
    public function authVerify(): JsonResponse
    {
        $this->rateLimit('webauthn_auth_verify', 10, 60);

        $input = $this->getAllInput();

        // Retrieve stored challenge
        $storedChallenge = $this->getStoredAuthChallenge($input);
        if ($storedChallenge instanceof JsonResponse) {
            return $storedChallenge;
        }

        if (empty($input['id']) || empty($input['response']['clientDataJSON']) ||
            empty($input['response']['authenticatorData']) || empty($input['response']['signature'])) {
            return $this->respondWithError(ApiErrorCodes::VALIDATION_REQUIRED_FIELD, 'Invalid assertion data', null, 400);
        }

        // Find credential by ID
        $tenantId = TenantContext::getId();
        $credentialRow = DB::selectOne(
            "SELECT wc.*, u.id as uid, u.first_name, u.last_name, u.email, u.role, u.tenant_id
             FROM webauthn_credentials wc
             JOIN users u ON wc.user_id = u.id
             WHERE wc.credential_id = ? AND wc.tenant_id = ?",
            [$input['id'], $tenantId]
        );

        if (!$credentialRow) {
            return $this->respondWithError(ApiErrorCodes::AUTH_WEBAUTHN_CREDENTIAL_NOT_FOUND, 'Credential not found', null, 401);
        }

        $credential = (array)$credentialRow;

        // Decode binary data from base64url
        $clientDataJSON = $this->base64UrlDecode($input['response']['clientDataJSON']);
        $authenticatorData = $this->base64UrlDecode($input['response']['authenticatorData']);
        $signature = $this->base64UrlDecode($input['response']['signature']);
        $challengeBytes = $this->base64UrlDecode($storedChallenge);

        // Use lbuchs/WebAuthn for proper signature verification
        $rpId = $this->getRpId();
        $rpName = TenantContext::get()['name'] ?? 'Project NEXUS';

        $webAuthn = null;
        try {
            $webAuthn = new WebAuthn($rpName, $rpId, ['none'], true);

            $webAuthn->processGet(
                $clientDataJSON,
                $authenticatorData,
                $signature,
                $credential['public_key'],
                $challengeBytes,
                (int)$credential['sign_count'],
                false,
                true
            );
        } catch (WebAuthnException $e) {
            error_log('[WebAuthn] Authentication verification failed: ' . $e->getMessage());
            return $this->respondWithError(ApiErrorCodes::AUTH_WEBAUTHN_FAILED, 'Passkey authentication failed. Please try again.', null, 401);
        }

        // Update sign count
        $newSignCount = $webAuthn->getSignatureCounter();
        DB::update(
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
        $webauthnUser = DB::selectOne(
            "SELECT id, role, is_super_admin, is_tenant_super_admin, tenant_id, email_verified_at, is_approved FROM users WHERE id = ? AND tenant_id = ?",
            [(int)$credential['uid'], (int)$credential['tenant_id']]
        );

        if ($webauthnUser) {
            $gateBlock = TenantSettingsService::checkLoginGatesForUser((array)$webauthnUser);
            if ($gateBlock) {
                return $this->respondWithError($gateBlock['code'], $gateBlock['message'], null, 403);
            }
        }

        // Generate auth tokens
        $isMobile = $this->tokenService->isMobileRequest();
        $accessToken = $this->tokenService->generateToken(
            (int)$credential['uid'],
            (int)$credential['tenant_id'],
            ['role' => $credential['role'], 'email' => $credential['email']],
            $isMobile
        );
        $refreshToken = $this->tokenService->generateRefreshToken(
            (int)$credential['uid'],
            (int)$credential['tenant_id'],
            $isMobile
        );

        // Set up session for browser clients
        $wantsStateless = $this->tokenService->isMobileRequest() || isset($_SERVER['HTTP_X_STATELESS_AUTH']);
        if (!$wantsStateless) {
            // Ensure a PHP session is active before accessing $_SESSION
            if (session_status() === PHP_SESSION_NONE) {
                session_start();
            }
            $currentSessionUser = $_SESSION['user_id'] ?? null;
            if ($currentSessionUser === null) {
                $preservedLayout = $_SESSION['nexus_active_layout'] ?? $_SESSION['nexus_layout'] ?? null;
                if (session_status() === PHP_SESSION_ACTIVE) {
                    session_regenerate_id(true);
                }
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

        // Auth success response — follows the same contract as AuthController/TotpController
        // (success, user, tokens). Frontend explicitly handles this shape.
        return response()->json([
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
            'expires_in' => $this->tokenService->getAccessTokenExpiry($isMobile),
            'is_mobile' => $isMobile,
        ]);
    }

    /** POST /api/webauthn/remove */
    public function remove(): JsonResponse
    {
        $userId = $this->requireAuth();
        $input = $this->getAllInput();
        $credentialId = $input['credential_id'] ?? null;
        $tenantId = TenantContext::getId();

        if ($credentialId) {
            DB::delete(
                "DELETE FROM webauthn_credentials WHERE credential_id = ? AND user_id = ? AND tenant_id = ?",
                [$credentialId, $userId, $tenantId]
            );
        } else {
            DB::delete(
                "DELETE FROM webauthn_credentials WHERE user_id = ? AND tenant_id = ?",
                [$userId, $tenantId]
            );
        }

        // Security notification: bell + email for passkey removed
        try {
            Notification::createNotification(
                $userId,
                'A passkey was removed from your account. If you did not do this, secure your account immediately.',
                null,
                'passkey_removed'
            );
        } catch (\Throwable $e) {
            error_log("Failed to create passkey removed notification: " . $e->getMessage());
        }

        try {
            $user = User::query()->find($userId);
            if ($user && $user->email) {
                $mailer = Mailer::forCurrentTenant();
                $tenantName = TenantContext::get()['name'] ?? 'Project NEXUS';

                $html = EmailTemplate::render(
                    "Passkey Removed",
                    "A passkey was removed from your account.",
                    "A passkey has been removed from your account. If you did not make this change, please secure your account immediately by changing your password.",
                    null,
                    null,
                    $tenantName
                );

                $mailer->send($user->email, "Security Alert: Passkey Removed - " . $tenantName, $html);
            }
        } catch (\Throwable $e) {
            error_log("Failed to send passkey removed email: " . $e->getMessage());
        }

        return $this->respondWithData(['message' => 'Credential(s) removed']);
    }

    /** POST /api/webauthn/rename */
    public function rename(): JsonResponse
    {
        $userId = $this->requireAuth();
        $input = $this->getAllInput();
        $credentialId = $input['credential_id'] ?? null;
        $newName = $input['device_name'] ?? null;

        if (!$credentialId || !$newName) {
            return $this->respondWithError(ApiErrorCodes::VALIDATION_ERROR, 'credential_id and device_name are required', null, 400);
        }

        $newName = mb_substr(trim($newName), 0, 100);
        if (empty($newName)) {
            return $this->respondWithError(ApiErrorCodes::VALIDATION_ERROR, 'device_name cannot be empty', 'device_name', 400);
        }

        $tenantId = TenantContext::getId();
        $affected = DB::update(
            "UPDATE webauthn_credentials SET device_name = ? WHERE credential_id = ? AND user_id = ? AND tenant_id = ?",
            [$newName, $credentialId, $userId, $tenantId]
        );

        if ($affected === 0) {
            return $this->respondWithError(ApiErrorCodes::RESOURCE_NOT_FOUND, 'Credential not found', null, 404);
        }

        return $this->respondWithData(['device_name' => $newName]);
    }

    /** POST /api/webauthn/remove-all */
    public function removeAll(): JsonResponse
    {
        $userId = $this->requireAuth();
        $tenantId = TenantContext::getId();

        $result = DB::selectOne(
            "SELECT COUNT(*) as count FROM webauthn_credentials WHERE user_id = ? AND tenant_id = ?",
            [$userId, $tenantId]
        );
        $count = (int)$result->count;

        DB::delete(
            "DELETE FROM webauthn_credentials WHERE user_id = ? AND tenant_id = ?",
            [$userId, $tenantId]
        );

        // Security notification: bell + email for all passkeys removed
        if ($count > 0) {
            try {
                Notification::createNotification(
                    $userId,
                    "All passkeys ({$count}) were removed from your account. If you did not do this, secure your account immediately.",
                    null,
                    'passkey_removed'
                );
            } catch (\Throwable $e) {
                error_log("Failed to create passkeys removed notification: " . $e->getMessage());
            }

            try {
                $user = User::query()->find($userId);
                if ($user && $user->email) {
                    $mailer = Mailer::forCurrentTenant();
                    $tenantName = TenantContext::get()['name'] ?? 'Project NEXUS';

                    $html = EmailTemplate::render(
                        "All Passkeys Removed",
                        "All passkeys were removed from your account.",
                        "All {$count} passkey(s) have been removed from your account. If you did not make this change, please secure your account immediately by changing your password.",
                        null,
                        null,
                        $tenantName
                    );

                    $mailer->send($user->email, "Security Alert: All Passkeys Removed - " . $tenantName, $html);
                }
            } catch (\Throwable $e) {
                error_log("Failed to send passkeys removed email: " . $e->getMessage());
            }
        }

        return $this->respondWithData([
            'message' => "Removed {$count} passkey(s). You can now re-register on any device.",
            'removed_count' => $count,
        ]);
    }

    /** GET /api/webauthn/credentials */
    public function credentials(): JsonResponse
    {
        $userId = $this->requireAuth();
        $tenantId = TenantContext::getId();

        $results = DB::select(
            "SELECT credential_id, device_name, authenticator_type, created_at, last_used_at
             FROM webauthn_credentials
             WHERE user_id = ? AND tenant_id = ?
             ORDER BY created_at DESC",
            [$userId, $tenantId]
        );
        $credentials = array_map(fn($r) => (array)$r, $results);

        return $this->respondWithData([
            'credentials' => $credentials,
            'count' => count($credentials),
        ]);
    }

    /** GET /api/webauthn/status */
    public function status(): JsonResponse
    {
        $userId = $this->getOptionalUserId();

        if (!$userId) {
            return $this->respondWithData(['registered' => false, 'count' => 0]);
        }

        $tenantId = TenantContext::getId();
        $result = DB::selectOne(
            "SELECT COUNT(*) as count FROM webauthn_credentials WHERE user_id = ? AND tenant_id = ?",
            [$userId, $tenantId]
        );

        return $this->respondWithData([
            'registered' => $result->count > 0,
            'count' => (int)$result->count,
        ]);
    }

    // ========================================================================
    // HELPER METHODS
    // ========================================================================

    private function getWebAuthnUser(int $userId): ?array
    {
        $row = DB::selectOne(
            "SELECT id, CONCAT(first_name, ' ', last_name) as name, email FROM users WHERE id = ? AND tenant_id = ?",
            [$userId, TenantContext::getId()]
        );
        return $row ? (array)$row : null;
    }

    /**
     * Get the Relying Party ID (domain).
     */
    private function getRpId(): string
    {
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

        $host = preg_replace('/:\d+$/', '', $host);

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
     * Returns challenge string on success, or JsonResponse on error.
     *
     * @return string|JsonResponse
     */
    private function getStoredChallenge(array $input, string $expectedType, int $userId)
    {
        $challengeId = $input['challenge_id'] ?? null;

        if ($challengeId) {
            $challengeData = $this->webAuthnChallengeStore->get($challengeId);
            if (!$challengeData) {
                return $this->respondWithError(ApiErrorCodes::AUTH_WEBAUTHN_CHALLENGE_EXPIRED, 'Challenge expired or invalid', null, 401);
            }
            if ($challengeData['user_id'] !== $userId) {
                return $this->respondWithError(ApiErrorCodes::AUTH_WEBAUTHN_CHALLENGE_INVALID, 'Challenge user mismatch', null, 401);
            }
            if ($challengeData['type'] !== $expectedType) {
                return $this->respondWithError(ApiErrorCodes::AUTH_WEBAUTHN_CHALLENGE_INVALID, 'Invalid challenge type', null, 401);
            }
            // SECURITY: Verify challenge belongs to current tenant to prevent cross-tenant replay
            $currentTenantId = TenantContext::getId();
            if (!empty($challengeData['tenant_id']) && (int)$challengeData['tenant_id'] !== (int)$currentTenantId) {
                return $this->respondWithError(ApiErrorCodes::AUTH_WEBAUTHN_CHALLENGE_INVALID, 'Challenge tenant mismatch', null, 401);
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
            return $this->respondWithError(ApiErrorCodes::AUTH_WEBAUTHN_CHALLENGE_EXPIRED, 'Challenge expired', null, 401);
        }
        return $_SESSION['webauthn_challenge'];
    }

    /**
     * Get stored challenge for authentication verification.
     * Returns challenge string on success, or JsonResponse on error.
     *
     * @return string|JsonResponse
     */
    private function getStoredAuthChallenge(array $input)
    {
        $challengeId = $input['challenge_id'] ?? null;

        if ($challengeId) {
            $challengeData = $this->webAuthnChallengeStore->get($challengeId);
            if (!$challengeData) {
                return $this->respondWithError(ApiErrorCodes::AUTH_WEBAUTHN_CHALLENGE_EXPIRED, 'Challenge expired or invalid', null, 401);
            }
            if ($challengeData['type'] !== 'authenticate') {
                return $this->respondWithError(ApiErrorCodes::AUTH_WEBAUTHN_CHALLENGE_INVALID, 'Invalid challenge type', null, 401);
            }
            // SECURITY: Verify challenge belongs to current tenant to prevent cross-tenant replay
            $currentTenantId = TenantContext::getId();
            if (!empty($challengeData['tenant_id']) && (int)$challengeData['tenant_id'] !== (int)$currentTenantId) {
                return $this->respondWithError(ApiErrorCodes::AUTH_WEBAUTHN_CHALLENGE_INVALID, 'Challenge tenant mismatch', null, 401);
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
            return $this->respondWithError(ApiErrorCodes::AUTH_WEBAUTHN_CHALLENGE_EXPIRED, 'Challenge expired', null, 401);
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
            $this->webAuthnChallengeStore->consume($challengeId);
        }
        unset($_SESSION['webauthn_challenge'], $_SESSION['webauthn_challenge_expires']);
    }
}
