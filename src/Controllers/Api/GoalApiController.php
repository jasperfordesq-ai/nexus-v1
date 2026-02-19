<?php
// Copyright © 2024–2026 Jasper Ford
// SPDX-License-Identifier: AGPL-3.0-or-later
// Author: Jasper Ford
// See NOTICE file for attribution and acknowledgements.

namespace Nexus\Controllers\Api;

use Nexus\Core\Database;
use Nexus\Core\TenantContext;
use Nexus\Core\ApiAuth;

class GoalApiController
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

    public function index()
    {
        $userId = $this->getUserId();
        $db = Database::getConnection();

        // Fetch My Goals
        $goals = $db->query("
            SELECT * FROM goals 
            WHERE user_id = ? 
            ORDER BY created_at DESC
        ", [$userId])->fetchAll();

        // Calculate progress just in case
        foreach ($goals as &$g) {
            $percent = 0;
            if ($g['target_value'] > 0) {
                $percent = round(($g['current_value'] / $g['target_value']) * 100);
            }
            $g['percent'] = min(100, $percent);
        }

        $this->jsonResponse(['status' => 'success', 'data' => $goals]);
    }

    public function updateProgress()
    {
        // Security: Verify CSRF token for state-changing operations
        \Nexus\Core\Csrf::verifyOrDieJson();

        $userId = $this->getUserId();
        $input = json_decode(file_get_contents('php://input'), true);
        $goalId = $input['goal_id'] ?? null;
        $increment = $input['increment'] ?? 0;

        if (!$goalId) $this->jsonResponse(['error' => 'Missing ID'], 400);

        $db = Database::getConnection();

        // Verify Ownership
        $goal = $db->query("SELECT * FROM goals WHERE id = ? AND user_id = ?", [$goalId, $userId])->fetch();
        if (!$goal) $this->jsonResponse(['error' => 'Goal not found'], 404);

        // Update
        $newVal = $goal['current_value'] + $increment;
        $db->query("UPDATE goals SET current_value = ? WHERE id = ?", [$newVal, $goalId]);

        // Check Completion
        if ($newVal >= $goal['target_value'] && $goal['status'] !== 'completed') {
            $db->query("UPDATE goals SET status = 'completed', completed_at = NOW() WHERE id = ?", [$goalId]);
            \Nexus\Models\Gamification::awardPoints($userId, 10, 'Completed Goal: ' . $goal['title']);
        }

        $this->jsonResponse(['success' => true, 'new_value' => $newVal]);
    }

    /**
     * Offer to be a buddy/mentor for a goal
     */
    public function offerBuddy()
    {
        // Clear any previous output (e.g., from TenantContext debug)
        if (ob_get_level()) {
            ob_clean();
        }

        try {
            $userId = $this->getUserId();

            $input = json_decode(file_get_contents('php://input'), true);
            $goalId = $input['goal_id'] ?? null;

            if (!$goalId) {
                $this->jsonResponse(['success' => false, 'error' => 'Missing goal_id'], 400);
                return;
            }

            $db = Database::getConnection();

            // Get the goal (search by ID only, then verify it's public for buddy offers)
            $stmt = $db->prepare("SELECT * FROM goals WHERE id = ?");
            $stmt->execute([$goalId]);
            $goal = $stmt->fetch(\PDO::FETCH_ASSOC);

            if (!$goal) {
                $this->jsonResponse(['success' => false, 'error' => 'Goal not found'], 404);
                return;
            }

            // Store tenant ID for notification (from goal data, fallback to context)
            $tenantId = $goal['tenant_id'] ?? TenantContext::getId() ?? 1;

            // Verify goal is public (only public goals can have buddies)
            if (empty($goal['is_public'])) {
                $this->jsonResponse(['success' => false, 'error' => 'This goal is private'], 400);
                return;
            }

            // Can't be a buddy for your own goal
            if ($goal['user_id'] == $userId) {
                $this->jsonResponse(['success' => false, 'error' => 'You cannot be a buddy for your own goal'], 400);
                return;
            }

            // Check if already has a mentor
            if (!empty($goal['mentor_id'])) {
                $this->jsonResponse(['success' => false, 'error' => 'This goal already has a buddy'], 400);
                return;
            }

            // Set this user as mentor
            $stmt = $db->prepare("UPDATE goals SET mentor_id = ? WHERE id = ?");
            $stmt->execute([$userId, $goalId]);

            // Award points for becoming a buddy
            if (class_exists('\Nexus\Models\Gamification')) {
                \Nexus\Models\Gamification::awardPoints($userId, 5, 'Became a goal buddy');
            }

            // Create notification for goal owner
            try {
                $stmt = $db->prepare("
                    INSERT INTO notifications (tenant_id, user_id, type, title, message, link, created_at)
                    VALUES (?, ?, 'goal_buddy', 'New Goal Buddy!', 'Someone offered to be your goal buddy', ?, NOW())
                ");
                $stmt->execute([$tenantId, $goal['user_id'], '/goals/' . $goalId]);
            } catch (\Exception $e) {
                // Notifications table may not exist, continue without error
            }

            $this->jsonResponse(['success' => true, 'message' => 'You are now a buddy for this goal']);

        } catch (\Exception $e) {
            // Clear any output that may have been generated
            if (ob_get_level()) {
                ob_clean();
            }
            $this->jsonResponse(['success' => false, 'error' => $e->getMessage()], 500);
        }
    }
}
