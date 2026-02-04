<?php

declare(strict_types=1);

namespace Nexus\Controllers\Api;

use Nexus\Core\Database;
use Nexus\Services\Enterprise\GdprService;

/**
 * GDPR API Controller
 *
 * Handles user-facing GDPR requests (consent updates, data export, account deletion).
 * Supports both session-based and Bearer token authentication.
 *
 * Response Format:
 * Success: { "data": {...} }
 * Error:   { "errors": [{ "code": "...", "message": "..." }] }
 */
class GdprApiController extends BaseApiController
{
    private GdprService $gdprService;

    public function __construct()
    {
        $this->gdprService = new GdprService();
    }

    /**
     * POST /api/gdpr/consent
     *
     * Update user consent preference.
     *
     * Request Body (JSON):
     * {
     *   "consent_id": int (required),
     *   "granted": bool (required)
     * }
     *
     * Response: 200 OK with success status
     */
    public function updateConsent(): void
    {
        $userId = $this->getUserId();
        $this->verifyCsrf();
        $this->rateLimit('gdpr_consent', 30, 60);

        $consentId = $this->input('consent_id');
        $granted = $this->inputBool('granted', false);

        if (!$consentId) {
            $this->respondWithError('VALIDATION_ERROR', 'Missing consent_id', 'consent_id', 400);
        }

        try {
            $ip = $_SERVER['REMOTE_ADDR'] ?? '127.0.0.1';
            $platform = $this->isStatelessRequest() ? 'mobile' : 'web';

            if ($granted) {
                $result = $this->gdprService->grantConsent($userId, (int) $consentId, $platform, $ip);
            } else {
                $result = $this->gdprService->withdrawConsent($userId, (int) $consentId, $ip);
            }

            $this->respondWithData([
                'updated' => $result,
                'consent_id' => (int) $consentId,
                'granted' => $granted
            ]);
        } catch (\Exception $e) {
            $this->respondWithError('CONSENT_UPDATE_FAILED', 'Failed to update consent', null, 500);
        }
    }

    /**
     * POST /api/gdpr/request
     *
     * Create a GDPR data request (export, portability, rectification, access).
     *
     * Request Body (JSON):
     * {
     *   "type": "data_export" | "data_portability" | "data_rectification" | "data_access" (required),
     *   "notes": string (optional)
     * }
     *
     * Response: 201 Created with request ID
     */
    public function createRequest(): void
    {
        $userId = $this->getUserId();
        $this->verifyCsrf();
        $this->rateLimit('gdpr_request', 5, 3600); // 5 requests per hour max

        $type = $this->input('type');
        $notes = $this->input('notes');

        // Map user-friendly types to internal types
        $typeMap = [
            'data_export' => 'portability',
            'data_portability' => 'portability',
            'data_rectification' => 'rectification',
            'data_access' => 'access',
        ];

        if (!$type || !isset($typeMap[$type])) {
            $this->respondWithError(
                'VALIDATION_ERROR',
                'Invalid request type. Valid types: data_export, data_portability, data_rectification, data_access',
                'type',
                400
            );
        }

        try {
            $internalType = $typeMap[$type];

            $result = $this->gdprService->createRequest($userId, $internalType, [
                'notes' => $notes,
            ]);

            $this->respondWithData([
                'request_id' => $result['id'],
                'type' => $type,
                'status' => 'pending',
                'message' => 'Your request has been submitted and will be processed within 30 days.'
            ], null, 201);
        } catch (\Exception $e) {
            $this->respondWithError('REQUEST_FAILED', $e->getMessage(), null, 500);
        }
    }

    /**
     * POST /api/gdpr/delete-account
     *
     * Request account deletion (GDPR right to erasure).
     * Requires password verification for security.
     *
     * Request Body (JSON):
     * {
     *   "password": string (required),
     *   "reason": string (optional),
     *   "feedback": string (optional)
     * }
     *
     * Response: 200 OK with request ID and confirmation message
     */
    public function deleteAccount(): void
    {
        $userId = $this->getUserId();
        $this->verifyCsrf();
        $this->rateLimit('gdpr_delete', 3, 3600); // 3 attempts per hour

        $password = $this->input('password', '');
        $reason = $this->input('reason');
        $feedback = $this->input('feedback');

        if (empty($password)) {
            $this->respondWithError('VALIDATION_ERROR', 'Password is required', 'password', 400);
        }

        // Verify password
        if (!$this->verifyPassword($userId, $password)) {
            $this->respondWithError('INVALID_PASSWORD', 'Invalid password', 'password', 403);
        }

        try {
            // Create erasure request (GDPR term for deletion)
            $result = $this->gdprService->createRequest($userId, 'erasure', [
                'notes' => $reason,
                'metadata' => [
                    'feedback' => $feedback,
                    'self_service' => true,
                    'requested_via' => $this->isStatelessRequest() ? 'api' : 'web'
                ]
            ]);

            // For session-based auth, destroy the session
            // For Bearer-based auth, the client should discard their tokens
            if (!$this->isStatelessRequest() && session_status() === PHP_SESSION_ACTIVE) {
                session_destroy();
            }

            $this->respondWithData([
                'request_id' => $result['id'],
                'message' => 'Your account deletion request has been submitted. You will receive confirmation via email.',
                'logout_required' => true
            ]);
        } catch (\Exception $e) {
            $this->respondWithError('DELETE_FAILED', $e->getMessage(), null, 500);
        }
    }

    /**
     * Verify user password
     */
    private function verifyPassword(int $userId, string $password): bool
    {
        $db = Database::getInstance();
        $stmt = $db->prepare("SELECT password_hash FROM users WHERE id = ?");
        $stmt->execute([$userId]);
        $user = $stmt->fetch();

        if (!$user) {
            return false;
        }

        return password_verify($password, $user['password_hash']);
    }
}
