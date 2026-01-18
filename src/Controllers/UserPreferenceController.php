<?php

namespace Nexus\Controllers;

use Nexus\Core\Database;
use Nexus\Core\Csrf;
use Nexus\Models\User;

class UserPreferenceController
{
    /**
     * Update notification settings via API
     * POST /api/notifications/settings
     * Body: { context_type: 'thread'|'group'|'global', context_id: int|null, frequency: 'instant'|'daily'|'weekly'|'off' }
     * OR: { push_enabled: 1|0 } for push notification preference
     */
    public function updateSettings()
    {
        header('Content-Type: application/json');

        if (!isset($_SESSION['user_id'])) {
            http_response_code(401);
            echo json_encode(['success' => false, 'message' => 'Unauthorized']);
            exit;
        }

        // Parse Input
        $input = json_decode(file_get_contents('php://input'), true);

        // Handle push_enabled preference update (from PWA subscription)
        if (isset($input['push_enabled'])) {
            try {
                $userId = $_SESSION['user_id'];
                $pushEnabled = $input['push_enabled'] ? 1 : 0;

                // Get current preferences and update push_enabled
                $currentPrefs = User::getNotificationPreferences($userId);
                $currentPrefs['push_enabled'] = $pushEnabled;
                User::updateNotificationPreferences($userId, $currentPrefs);

                echo json_encode(['success' => true, 'push_enabled' => $pushEnabled]);
                exit;
            } catch (\Exception $e) {
                http_response_code(500);
                echo json_encode(['success' => false, 'message' => $e->getMessage()]);
                exit;
            }
        }

        $contextType = $input['context_type'] ?? null;
        $contextId = $input['context_id'] ?? null;
        $frequency = $input['frequency'] ?? null;

        // Validation
        if (!in_array($contextType, ['global', 'group', 'thread'])) {
            http_response_code(400);
            echo json_encode(['success' => false, 'message' => 'Invalid context type']);
            exit;
        }

        if (!in_array($frequency, ['instant', 'daily', 'weekly', 'off'])) {
            http_response_code(400);
            echo json_encode(['success' => false, 'message' => 'Invalid frequency']);
            exit;
        }

        if ($contextType === 'global') {
            $contextId = null; // Enforce null for global
            // Or schema demands 0? My migration script logic?
            // "context_id INT NULL"
            // "UNIQUE KEY (user_id, context_type, context_id)" relies on NULL handling.
            // In MySQL unique index, multiple NULLs are allowed!
            // Wait. If multiple NULLs allowed, then unique key doesn't prevent duplicates for Global!
            // Crap.
            // If I insert (User, Global, NULL, Daily) twice, it allows it?
            // Yes, standard SQL allows multiple NULLs in Unique Constraint.
            // FIX: For Global, use context_id = 0.
            $contextId = 0;
        } else {
            if (!$contextId) {
                http_response_code(400);
                echo json_encode(['success' => false, 'message' => 'Context ID required']);
                exit;
            }
        }

        try {
            $db = Database::getInstance();
            $userId = $_SESSION['user_id'];

            // Upsert
            $sql = "INSERT INTO notification_settings (user_id, context_type, context_id, frequency) 
                    VALUES (?, ?, ?, ?)
                    ON DUPLICATE KEY UPDATE frequency = VALUES(frequency)";

            $stmt = $db->prepare($sql);
            $stmt->execute([$userId, $contextType, $contextId, $frequency]);

            echo json_encode(['success' => true]);
        } catch (\Exception $e) {
            http_response_code(500);
            echo json_encode(['success' => false, 'message' => $e->getMessage()]);
        }
        exit;
    }
}
