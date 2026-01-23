<?php

namespace Nexus\Controllers\Api;

use Nexus\Core\ApiAuth;
use Nexus\Models\Deliverable;
use Nexus\Models\DeliverableMilestone;
use Nexus\Models\DeliverableComment;
use Nexus\Services\DeliverabilityTrackingService;

/**
 * DeliverabilityApiController
 *
 * REST API endpoints for deliverability tracking module.
 * Provides CRUD operations and analytics endpoints.
 */
class DeliverabilityApiController
{
    use ApiAuth;

    /**
     * Get all deliverables with optional filters
     * GET /api/deliverables
     */
    public function index()
    {
        $this->requireAuth();
        $userId = $this->getUserId();

        // Parse query parameters
        $filters = [];
        if (isset($_GET['status'])) {
            $filters['status'] = $_GET['status'];
        }
        if (isset($_GET['priority'])) {
            $filters['priority'] = $_GET['priority'];
        }
        if (isset($_GET['category'])) {
            $filters['category'] = $_GET['category'];
        }
        if (isset($_GET['assigned_to'])) {
            $filters['assigned_to'] = (int) $_GET['assigned_to'];
        }
        if (isset($_GET['owner_id'])) {
            $filters['owner_id'] = (int) $_GET['owner_id'];
        }
        if (isset($_GET['assigned_group_id'])) {
            $filters['assigned_group_id'] = (int) $_GET['assigned_group_id'];
        }
        if (isset($_GET['overdue']) && $_GET['overdue'] === 'true') {
            $filters['overdue'] = true;
        }

        // Filter to user's own deliverables if requested
        if (isset($_GET['my_deliverables']) && $_GET['my_deliverables'] === 'true') {
            $filters['assigned_to'] = $userId;
        }

        $limit = min(500, max(1, (int) ($_GET['limit'] ?? 50)));
        $offset = max(0, (int) ($_GET['offset'] ?? 0));

        $deliverables = Deliverable::getAll($filters, $limit, $offset);
        $total = Deliverable::getCount($filters);

        return $this->jsonResponse([
            'data' => $deliverables,
            'total' => $total,
            'limit' => $limit,
            'offset' => $offset
        ]);
    }

    /**
     * Get single deliverable by ID
     * GET /api/deliverables/{id}
     */
    public function show($id)
    {
        $this->requireAuth();

        $deliverable = Deliverable::findById($id);

        if (!$deliverable) {
            return $this->jsonResponse(['error' => 'Deliverable not found'], 404);
        }

        // Include milestones and comment count
        $deliverable['milestones'] = DeliverableMilestone::getByDeliverable($id);
        $deliverable['milestone_stats'] = DeliverableMilestone::getStats($id);
        $deliverable['comment_count'] = DeliverableComment::getCount($id);
        $deliverable['history'] = Deliverable::getHistory($id, 20);

        return $this->jsonResponse(['data' => $deliverable]);
    }

    /**
     * Create new deliverable
     * POST /api/deliverables
     */
    public function create()
    {
        $this->requireAuth();
        $userId = $this->getUserId();

        $data = json_decode(file_get_contents('php://input'), true);

        // Validate required fields
        if (empty($data['title'])) {
            return $this->jsonResponse(['error' => 'Title is required'], 400);
        }

        $options = [
            'category' => $data['category'] ?? 'general',
            'priority' => $data['priority'] ?? 'medium',
            'assigned_to' => $data['assigned_to'] ?? null,
            'assigned_group_id' => $data['assigned_group_id'] ?? null,
            'start_date' => $data['start_date'] ?? null,
            'due_date' => $data['due_date'] ?? null,
            'status' => $data['status'] ?? 'draft',
            'estimated_hours' => $data['estimated_hours'] ?? null,
            'tags' => $data['tags'] ?? null,
            'delivery_confidence' => $data['delivery_confidence'] ?? 'medium',
            'risk_level' => $data['risk_level'] ?? 'low',
            'risk_notes' => $data['risk_notes'] ?? null,
        ];

        $deliverable = DeliverabilityTrackingService::createDeliverable(
            $userId,
            $data['title'],
            $data['description'] ?? null,
            $options
        );

        if (!$deliverable) {
            return $this->jsonResponse(['error' => 'Failed to create deliverable'], 500);
        }

        return $this->jsonResponse(['data' => $deliverable, 'message' => 'Deliverable created successfully'], 201);
    }

    /**
     * Update deliverable
     * PUT /api/deliverables/{id}
     */
    public function update($id)
    {
        $this->requireAuth();
        $userId = $this->getUserId();

        $deliverable = Deliverable::findById($id);
        if (!$deliverable) {
            return $this->jsonResponse(['error' => 'Deliverable not found'], 404);
        }

        $data = json_decode(file_get_contents('php://input'), true);

        $result = Deliverable::update($id, $data, $userId);

        if (!$result) {
            return $this->jsonResponse(['error' => 'Failed to update deliverable'], 500);
        }

        $updated = Deliverable::findById($id);
        return $this->jsonResponse(['data' => $updated, 'message' => 'Deliverable updated successfully']);
    }

    /**
     * Update deliverable status
     * PUT /api/deliverables/{id}/status
     */
    public function updateStatus($id)
    {
        $this->requireAuth();
        $userId = $this->getUserId();

        $data = json_decode(file_get_contents('php://input'), true);

        if (empty($data['status'])) {
            return $this->jsonResponse(['error' => 'Status is required'], 400);
        }

        $validStatuses = ['draft', 'ready', 'in_progress', 'blocked', 'review', 'completed', 'cancelled', 'on_hold'];
        if (!in_array($data['status'], $validStatuses)) {
            return $this->jsonResponse(['error' => 'Invalid status'], 400);
        }

        $result = DeliverabilityTrackingService::updateDeliverableStatus($id, $data['status'], $userId);

        if (!$result) {
            return $this->jsonResponse(['error' => 'Failed to update status'], 500);
        }

        $updated = Deliverable::findById($id);
        return $this->jsonResponse(['data' => $updated, 'message' => 'Status updated successfully']);
    }

    /**
     * Update deliverable progress
     * PUT /api/deliverables/{id}/progress
     */
    public function updateProgress($id)
    {
        $this->requireAuth();
        $userId = $this->getUserId();

        $data = json_decode(file_get_contents('php://input'), true);

        if (!isset($data['progress'])) {
            return $this->jsonResponse(['error' => 'Progress percentage is required'], 400);
        }

        $progress = (float) $data['progress'];
        if ($progress < 0 || $progress > 100) {
            return $this->jsonResponse(['error' => 'Progress must be between 0 and 100'], 400);
        }

        $result = Deliverable::updateProgress($id, $progress, $userId);

        if (!$result) {
            return $this->jsonResponse(['error' => 'Failed to update progress'], 500);
        }

        $updated = Deliverable::findById($id);
        return $this->jsonResponse(['data' => $updated, 'message' => 'Progress updated successfully']);
    }

    /**
     * Complete deliverable
     * POST /api/deliverables/{id}/complete
     */
    public function complete($id)
    {
        $this->requireAuth();
        $userId = $this->getUserId();

        $data = json_decode(file_get_contents('php://input'), true);

        $options = [
            'actual_hours' => $data['actual_hours'] ?? null,
            'force_complete' => $data['force_complete'] ?? false,
        ];

        $result = DeliverabilityTrackingService::completeDeliverable($id, $userId, $options);

        if (!$result) {
            return $this->jsonResponse(['error' => 'Failed to complete deliverable'], 500);
        }

        $updated = Deliverable::findById($id);
        return $this->jsonResponse(['data' => $updated, 'message' => 'Deliverable completed successfully']);
    }

    /**
     * Assign deliverable
     * POST /api/deliverables/{id}/assign
     */
    public function assign($id)
    {
        $this->requireAuth();
        $userId = $this->getUserId();

        $data = json_decode(file_get_contents('php://input'), true);

        $assignedUserId = $data['user_id'] ?? null;
        $assignedGroupId = $data['group_id'] ?? null;

        if (!$assignedUserId && !$assignedGroupId) {
            return $this->jsonResponse(['error' => 'user_id or group_id is required'], 400);
        }

        $result = Deliverable::assign($id, $assignedUserId, $assignedGroupId, $userId);

        if (!$result) {
            return $this->jsonResponse(['error' => 'Failed to assign deliverable'], 500);
        }

        $updated = Deliverable::findById($id);
        return $this->jsonResponse(['data' => $updated, 'message' => 'Deliverable assigned successfully']);
    }

    /**
     * Delete deliverable
     * DELETE /api/deliverables/{id}
     */
    public function delete($id)
    {
        $this->requireAuth();
        $userId = $this->getUserId();

        $deliverable = Deliverable::findById($id);
        if (!$deliverable) {
            return $this->jsonResponse(['error' => 'Deliverable not found'], 404);
        }

        // Only owner can delete
        if ($deliverable['owner_id'] != $userId) {
            return $this->jsonResponse(['error' => 'Unauthorized'], 403);
        }

        $result = Deliverable::delete($id, $userId);

        if (!$result) {
            return $this->jsonResponse(['error' => 'Failed to delete deliverable'], 500);
        }

        return $this->jsonResponse(['message' => 'Deliverable deleted successfully']);
    }

    /**
     * Get deliverable milestones
     * GET /api/deliverables/{id}/milestones
     */
    public function milestones($id)
    {
        $this->requireAuth();

        $milestones = DeliverableMilestone::getByDeliverable($id);
        $stats = DeliverableMilestone::getStats($id);

        return $this->jsonResponse([
            'data' => $milestones,
            'stats' => $stats
        ]);
    }

    /**
     * Create milestone
     * POST /api/deliverables/{id}/milestones
     */
    public function createMilestone($id)
    {
        $this->requireAuth();

        $data = json_decode(file_get_contents('php://input'), true);

        if (empty($data['title'])) {
            return $this->jsonResponse(['error' => 'Title is required'], 400);
        }

        $options = [
            'description' => $data['description'] ?? null,
            'order_position' => $data['order_position'] ?? 0,
            'due_date' => $data['due_date'] ?? null,
            'estimated_hours' => $data['estimated_hours'] ?? null,
        ];

        $milestone = DeliverableMilestone::create($id, $data['title'], $options);

        if (!$milestone) {
            return $this->jsonResponse(['error' => 'Failed to create milestone'], 500);
        }

        return $this->jsonResponse(['data' => $milestone, 'message' => 'Milestone created successfully'], 201);
    }

    /**
     * Update milestone
     * PUT /api/milestones/{id}
     */
    public function updateMilestone($id)
    {
        $this->requireAuth();

        $data = json_decode(file_get_contents('php://input'), true);

        $result = DeliverableMilestone::update($id, $data);

        if (!$result) {
            return $this->jsonResponse(['error' => 'Failed to update milestone'], 500);
        }

        $updated = DeliverableMilestone::findById($id);
        return $this->jsonResponse(['data' => $updated, 'message' => 'Milestone updated successfully']);
    }

    /**
     * Complete milestone
     * POST /api/milestones/{id}/complete
     */
    public function completeMilestone($id)
    {
        $this->requireAuth();
        $userId = $this->getUserId();

        $result = DeliverableMilestone::complete($id, $userId);

        if (!$result) {
            return $this->jsonResponse(['error' => 'Failed to complete milestone'], 500);
        }

        // Recalculate parent deliverable progress
        $milestone = DeliverableMilestone::findById($id);
        if ($milestone) {
            DeliverabilityTrackingService::recalculateProgress($milestone['deliverable_id'], $userId);
        }

        $updated = DeliverableMilestone::findById($id);
        return $this->jsonResponse(['data' => $updated, 'message' => 'Milestone completed successfully']);
    }

    /**
     * Delete milestone
     * DELETE /api/milestones/{id}
     */
    public function deleteMilestone($id)
    {
        $this->requireAuth();

        $result = DeliverableMilestone::delete($id);

        if (!$result) {
            return $this->jsonResponse(['error' => 'Failed to delete milestone'], 500);
        }

        return $this->jsonResponse(['message' => 'Milestone deleted successfully']);
    }

    /**
     * Get deliverable comments
     * GET /api/deliverables/{id}/comments
     */
    public function comments($id)
    {
        $this->requireAuth();

        $comments = DeliverableComment::getByDeliverable($id);
        $count = DeliverableComment::getCount($id);

        return $this->jsonResponse([
            'data' => $comments,
            'count' => $count
        ]);
    }

    /**
     * Create comment
     * POST /api/deliverables/{id}/comments
     */
    public function createComment($id)
    {
        $this->requireAuth();
        $userId = $this->getUserId();

        $data = json_decode(file_get_contents('php://input'), true);

        if (empty($data['comment_text'])) {
            return $this->jsonResponse(['error' => 'Comment text is required'], 400);
        }

        $options = [
            'comment_type' => $data['comment_type'] ?? 'general',
            'parent_comment_id' => $data['parent_comment_id'] ?? null,
        ];

        $comment = DeliverableComment::create($id, $userId, $data['comment_text'], $options);

        if (!$comment) {
            return $this->jsonResponse(['error' => 'Failed to create comment'], 500);
        }

        return $this->jsonResponse(['data' => $comment, 'message' => 'Comment created successfully'], 201);
    }

    /**
     * Update comment
     * PUT /api/comments/{id}
     */
    public function updateComment($id)
    {
        $this->requireAuth();
        $userId = $this->getUserId();

        $data = json_decode(file_get_contents('php://input'), true);

        if (empty($data['comment_text'])) {
            return $this->jsonResponse(['error' => 'Comment text is required'], 400);
        }

        $result = DeliverableComment::update($id, $data['comment_text'], $userId);

        if (!$result) {
            return $this->jsonResponse(['error' => 'Failed to update comment or unauthorized'], 403);
        }

        $updated = DeliverableComment::findById($id);
        return $this->jsonResponse(['data' => $updated, 'message' => 'Comment updated successfully']);
    }

    /**
     * Delete comment
     * DELETE /api/comments/{id}
     */
    public function deleteComment($id)
    {
        $this->requireAuth();
        $userId = $this->getUserId();

        $result = DeliverableComment::delete($id, $userId);

        if (!$result) {
            return $this->jsonResponse(['error' => 'Failed to delete comment or unauthorized'], 403);
        }

        return $this->jsonResponse(['message' => 'Comment deleted successfully']);
    }

    /**
     * Get user dashboard
     * GET /api/deliverables/dashboard
     */
    public function dashboard()
    {
        $this->requireAuth();
        $userId = $this->getUserId();

        $dashboard = DeliverabilityTrackingService::getUserDashboard($userId);

        return $this->jsonResponse(['data' => $dashboard]);
    }

    /**
     * Get analytics
     * GET /api/deliverables/analytics
     */
    public function analytics()
    {
        $this->requireAuth();
        $userId = $this->getUserId();

        // Parse optional filters
        $filters = [];
        if (isset($_GET['user_id'])) {
            $filters['user_id'] = (int) $_GET['user_id'];
        }
        if (isset($_GET['status'])) {
            $filters['status'] = $_GET['status'];
        }

        $analytics = DeliverabilityTrackingService::getAnalytics($filters);

        return $this->jsonResponse(['data' => $analytics]);
    }

    /**
     * Generate report
     * GET /api/deliverables/report
     */
    public function report()
    {
        $this->requireAuth();

        // Parse filters from query params
        $filters = [];
        if (isset($_GET['status'])) {
            $filters['status'] = $_GET['status'];
        }
        if (isset($_GET['user_id'])) {
            $filters['user_id'] = (int) $_GET['user_id'];
        }

        $report = DeliverabilityTrackingService::generateReport($filters);

        return $this->jsonResponse(['data' => $report]);
    }

    /**
     * Get deliverable history
     * GET /api/deliverables/{id}/history
     */
    public function history($id)
    {
        $this->requireAuth();

        $limit = min(500, max(1, (int) ($_GET['limit'] ?? 100)));
        $history = Deliverable::getHistory($id, $limit);

        return $this->jsonResponse(['data' => $history]);
    }

    /**
     * JSON response helper
     *
     * @param array $data Response data
     * @param int $status HTTP status code
     * @return void
     */
    private function jsonResponse($data, $status = 200)
    {
        http_response_code($status);
        header('Content-Type: application/json');
        echo json_encode($data);
        exit;
    }
}
