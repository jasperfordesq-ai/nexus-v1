<?php

declare(strict_types=1);

namespace Nexus\Controllers\Api;

use Nexus\Core\Database;
use Nexus\Services\Enterprise\GdprService;

/**
 * GDPR API Controller
 *
 * Handles user-facing GDPR requests (consent updates, data export, account deletion)
 */
class GdprApiController
{
    private GdprService $gdprService;

    public function __construct()
    {
        $this->gdprService = new GdprService(Database::getInstance()->getConnection());
    }

    /**
     * Update user consent
     */
    public function updateConsent(): void
    {
        header('Content-Type: application/json');

        if (session_status() === PHP_SESSION_NONE) {
            session_start();
        }

        if (!isset($_SESSION['user_id'])) {
            http_response_code(401);
            echo json_encode(['success' => false, 'error' => 'Not authenticated']);
            return;
        }

        $data = json_decode(file_get_contents('php://input'), true);
        $consentId = $data['consent_id'] ?? null;
        $granted = $data['granted'] ?? false;

        if (!$consentId) {
            http_response_code(400);
            echo json_encode(['success' => false, 'error' => 'Missing consent_id']);
            return;
        }

        try {
            $userId = (int) $_SESSION['user_id'];
            $ip = $_SERVER['REMOTE_ADDR'] ?? '127.0.0.1';

            if ($granted) {
                $result = $this->gdprService->grantConsent($userId, (int) $consentId, 'web', $ip);
            } else {
                $result = $this->gdprService->withdrawConsent($userId, (int) $consentId, $ip);
            }

            echo json_encode(['success' => $result]);
        } catch (\Exception $e) {
            http_response_code(500);
            echo json_encode(['success' => false, 'error' => 'Failed to update consent']);
        }
    }

    /**
     * Create a GDPR request (data export, portability, etc.)
     */
    public function createRequest(): void
    {
        header('Content-Type: application/json');

        if (session_status() === PHP_SESSION_NONE) {
            session_start();
        }

        if (!isset($_SESSION['user_id'])) {
            http_response_code(401);
            echo json_encode(['success' => false, 'error' => 'Not authenticated']);
            return;
        }

        $data = json_decode(file_get_contents('php://input'), true);
        $type = $data['type'] ?? null;

        // Map user-friendly types to internal types
        $typeMap = [
            'data_export' => 'portability',
            'data_portability' => 'portability',
            'data_rectification' => 'rectification',
            'data_access' => 'access',
        ];

        if (!$type || !isset($typeMap[$type])) {
            http_response_code(400);
            echo json_encode(['success' => false, 'error' => 'Invalid request type']);
            return;
        }

        try {
            $userId = (int) $_SESSION['user_id'];
            $internalType = $typeMap[$type];

            $result = $this->gdprService->createRequest($userId, $internalType, [
                'notes' => $data['notes'] ?? null,
            ]);

            echo json_encode(['success' => true, 'request_id' => $result['id']]);
        } catch (\Exception $e) {
            http_response_code(500);
            echo json_encode(['success' => false, 'error' => $e->getMessage()]);
        }
    }

    /**
     * Delete user account (GDPR right to erasure)
     */
    public function deleteAccount(): void
    {
        header('Content-Type: application/json');

        if (session_status() === PHP_SESSION_NONE) {
            session_start();
        }

        if (!isset($_SESSION['user_id'])) {
            http_response_code(401);
            echo json_encode(['success' => false, 'error' => 'Not authenticated']);
            return;
        }

        $userId = (int) $_SESSION['user_id'];
        $password = $_POST['password'] ?? '';
        $reason = $_POST['reason'] ?? null;
        $feedback = $_POST['feedback'] ?? null;

        // Verify password
        if (!$this->verifyPassword($userId, $password)) {
            http_response_code(403);
            echo json_encode(['success' => false, 'error' => 'Invalid password']);
            return;
        }

        try {
            // Create erasure request (GDPR term for deletion)
            $result = $this->gdprService->createRequest($userId, 'erasure', [
                'notes' => $reason,
                'metadata' => [
                    'feedback' => $feedback,
                    'self_service' => true
                ]
            ]);

            // Log the user out
            session_destroy();

            echo json_encode([
                'success' => true,
                'request_id' => $result['id'],
                'message' => 'Your account deletion request has been submitted. You will receive confirmation via email.'
            ]);
        } catch (\Exception $e) {
            http_response_code(500);
            echo json_encode(['success' => false, 'error' => $e->getMessage()]);
        }
    }

    /**
     * Get user email by ID
     */
    private function getUserEmail(int $userId): string
    {
        $db = Database::getInstance()->getConnection();
        $stmt = $db->prepare("SELECT email FROM users WHERE id = ?");
        $stmt->execute([$userId]);
        $user = $stmt->fetch();

        return $user['email'] ?? '';
    }

    /**
     * Verify user password
     */
    private function verifyPassword(int $userId, string $password): bool
    {
        $db = Database::getInstance()->getConnection();
        $stmt = $db->prepare("SELECT password_hash FROM users WHERE id = ?");
        $stmt->execute([$userId]);
        $user = $stmt->fetch();

        if (!$user) {
            return false;
        }

        return password_verify($password, $user['password_hash']);
    }
}
