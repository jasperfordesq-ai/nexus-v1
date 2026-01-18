<?php

namespace Nexus\Controllers\Admin;

use Nexus\Core\View;
use Nexus\Core\TenantContext;
use Nexus\Core\Database;
use Nexus\Models\Group;
use Nexus\Services\GroupConfigurationService;
use Nexus\Services\GroupPermissionManager;
use Nexus\Services\GroupAuditService;
use Nexus\Services\GroupPolicyRepository;
use Nexus\Services\GroupModerationService;
use Nexus\Services\GroupApprovalWorkflowService;
use Nexus\Services\GroupFeatureToggleService;

class GroupAdminController
{
    /**
     * List all groups for admin management
     */
    public function index()
    {
        // Must be admin
        if (empty($_SESSION['is_admin'])) {
            http_response_code(403);
            die("Access denied");
        }

        $tenantId = TenantContext::getId();

        // Get filters
        $search = $_GET['search'] ?? '';
        $typeFilter = $_GET['type'] ?? '';
        $statusFilter = $_GET['status'] ?? '';
        $page = max(1, (int)($_GET['page'] ?? 1));
        $perPage = 20;

        // Build query
        $sql = "SELECT g.*, gt.name as type_name, gt.is_hub,
                       COUNT(DISTINCT gm.id) as member_count,
                       COUNT(DISTINCT child.id) as child_count,
                       u.first_name as owner_first_name,
                       u.last_name as owner_last_name
                FROM `groups` g
                LEFT JOIN group_types gt ON g.type_id = gt.id
                LEFT JOIN group_members gm ON g.id = gm.group_id AND gm.status = 'active'
                LEFT JOIN `groups` child ON child.parent_id = g.id AND child.tenant_id = g.tenant_id
                LEFT JOIN users u ON g.owner_id = u.id
                WHERE g.tenant_id = ?";

        $params = [$tenantId];

        if (!empty($search)) {
            $sql .= " AND (g.name LIKE ? OR g.description LIKE ?)";
            $params[] = "%$search%";
            $params[] = "%$search%";
        }

        if (!empty($typeFilter)) {
            $sql .= " AND g.type_id = ?";
            $params[] = $typeFilter;
        }

        if ($statusFilter === 'featured') {
            $sql .= " AND g.is_featured = 1";
        } elseif ($statusFilter === 'pending') {
            $sql .= " AND g.status = 'pending'";
        }

        $sql .= " GROUP BY g.id
                  ORDER BY g.is_featured DESC, g.created_at DESC
                  LIMIT $perPage OFFSET " . (($page - 1) * $perPage);

        $groups = Database::query($sql, $params)->fetchAll();

        // Get total count for pagination
        $countSql = "SELECT COUNT(DISTINCT g.id) FROM `groups` g WHERE g.tenant_id = ?";
        $countParams = [$tenantId];
        if (!empty($search)) {
            $countSql .= " AND (g.name LIKE ? OR g.description LIKE ?)";
            $countParams[] = "%$search%";
            $countParams[] = "%$search%";
        }
        $totalGroups = Database::query($countSql, $countParams)->fetchColumn();

        // Get group types for filter
        $groupTypes = Database::query(
            "SELECT * FROM group_types WHERE tenant_id = ? ORDER BY name",
            [$tenantId]
        )->fetchAll();

        // Get analytics
        $analytics = $this->getAnalytics();

        View::render('admin/groups/index', [
            'groups' => $groups,
            'groupTypes' => $groupTypes,
            'analytics' => $analytics,
            'search' => $search,
            'typeFilter' => $typeFilter,
            'statusFilter' => $statusFilter,
            'currentPage' => $page,
            'totalPages' => ceil($totalGroups / $perPage),
            'totalGroups' => $totalGroups,
            'pageTitle' => 'Manage Groups'
        ]);
    }

    /**
     * Toggle featured status for a group
     */
    public function toggleFeatured()
    {
        if (empty($_SESSION['is_admin'])) {
            http_response_code(403);
            die("Access denied");
        }

        $groupId = $_POST['group_id'] ?? 0;

        // Get current status
        $group = Group::findById($groupId);
        if (!$group) {
            http_response_code(404);
            die("Group not found");
        }

        // Toggle featured status
        $newStatus = empty($group['is_featured']) ? 1 : 0;

        Database::query(
            "UPDATE `groups` SET is_featured = ? WHERE id = ?",
            [$newStatus, $groupId]
        );

        header('Location: ' . TenantContext::getBasePath() . '/admin/groups?featured=' . $newStatus);
        exit;
    }

    /**
     * Delete a group
     */
    public function delete()
    {
        if (empty($_SESSION['is_admin'])) {
            http_response_code(403);
            die("Access denied");
        }

        $groupId = $_POST['group_id'] ?? 0;

        $group = Group::findById($groupId);
        if (!$group) {
            http_response_code(404);
            die("Group not found");
        }

        // Log the deletion
        GroupAuditService::logGroupDeleted($groupId, $_SESSION['user_id'], 'Deleted by admin');

        // Delete group members first
        Database::query("DELETE FROM group_members WHERE group_id = ?", [$groupId]);

        // Delete the group
        Database::query("DELETE FROM `groups` WHERE id = ?", [$groupId]);

        header('Location: ' . TenantContext::getBasePath() . '/admin/groups?deleted=1');
        exit;
    }

    /**
     * Get analytics dashboard
     */
    public function analytics()
    {
        if (empty($_SESSION['is_admin'])) {
            http_response_code(403);
            die("Access denied");
        }

        $analytics = $this->getAnalytics();
        $tenantId = TenantContext::getId();

        // Get growth data (last 30 days) - with fallback if created_at doesn't exist
        try {
            $growthData = Database::query(
                "SELECT DATE(created_at) as date, COUNT(*) as count
                 FROM `groups`
                 WHERE tenant_id = ? AND created_at >= DATE_SUB(NOW(), INTERVAL 30 DAY)
                 GROUP BY DATE(created_at)
                 ORDER BY date ASC",
                [$tenantId]
            )->fetchAll();
        } catch (\Exception $e) {
            // Fallback if created_at column doesn't exist
            $growthData = [];
        }

        // Get member growth
        try {
            $memberGrowth = Database::query(
                "SELECT DATE(created_at) as date, COUNT(*) as count
                 FROM group_members
                 WHERE created_at >= DATE_SUB(NOW(), INTERVAL 30 DAY)
                 AND status = 'active'
                 GROUP BY DATE(created_at)
                 ORDER BY date ASC"
            )->fetchAll();
        } catch (\Exception $e) {
            // Fallback if created_at column doesn't exist
            $memberGrowth = [];
        }

        // Get top groups by members
        $topGroups = Database::query(
            "SELECT g.id, g.name, COUNT(gm.id) as member_count
             FROM `groups` g
             LEFT JOIN group_members gm ON g.id = gm.group_id AND gm.status = 'active'
             WHERE g.tenant_id = ?
             GROUP BY g.id
             ORDER BY member_count DESC
             LIMIT 10",
            [$tenantId]
        )->fetchAll();

        // Get recent activity
        $recentActivity = GroupAuditService::getRecentActivity([], 20);

        View::render('admin/groups/analytics', [
            'analytics' => $analytics,
            'growthData' => $growthData,
            'memberGrowth' => $memberGrowth,
            'topGroups' => $topGroups,
            'recentActivity' => $recentActivity,
            'pageTitle' => 'Group Analytics'
        ]);
    }

    /**
     * Group Recommendations Performance Dashboard
     */
    public function recommendations()
    {
        if (empty($_SESSION['is_admin'])) {
            http_response_code(403);
            die("Access denied");
        }

        View::render('admin/groups/recommendations', [
            'pageTitle' => 'Group Recommendations'
        ]);
    }

    /**
     * View group details
     */
    public function view()
    {
        if (empty($_SESSION['is_admin'])) {
            http_response_code(403);
            die("Access denied");
        }

        $groupId = $_GET['id'] ?? 0;
        $group = Group::findById($groupId);

        if (!$group) {
            http_response_code(404);
            die("Group not found");
        }

        // Get members with details
        $members = Database::query(
            "SELECT gm.*, u.first_name, u.last_name, u.email, u.profile_image_url,
                    CONCAT(u.first_name, ' ', u.last_name) as name
             FROM group_members gm
             JOIN users u ON gm.user_id = u.id
             WHERE gm.group_id = ?
             ORDER BY gm.role DESC, gm.created_at ASC",
            [$groupId]
        )->fetchAll();

        // Get pending requests
        $pendingRequests = Database::query(
            "SELECT gm.*, u.first_name, u.last_name, u.email,
                    CONCAT(u.first_name, ' ', u.last_name) as name
             FROM group_members gm
             JOIN users u ON gm.user_id = u.id
             WHERE gm.group_id = ? AND gm.status = 'pending'
             ORDER BY gm.created_at DESC",
            [$groupId]
        )->fetchAll();

        // Get discussions count
        $discussionCount = Database::query(
            "SELECT COUNT(*) FROM group_discussions WHERE group_id = ?",
            [$groupId]
        )->fetchColumn();

        // Get recent audit log
        $auditLog = GroupAuditService::getGroupLog($groupId, [], 20);

        View::render('admin/groups/view', [
            'group' => $group,
            'members' => $members,
            'pendingRequests' => $pendingRequests,
            'discussionCount' => $discussionCount,
            'auditLog' => $auditLog,
            'pageTitle' => 'Group Details: ' . $group['name']
        ]);
    }

    /**
     * Manage group members
     */
    public function manageMembers()
    {
        if (empty($_SESSION['is_admin'])) {
            http_response_code(403);
            die("Access denied");
        }

        $groupId = $_POST['group_id'] ?? 0;
        $action = $_POST['action'] ?? '';
        $userId = $_POST['user_id'] ?? 0;

        $group = Group::findById($groupId);
        if (!$group) {
            echo json_encode(['success' => false, 'message' => 'Group not found']);
            exit;
        }

        switch ($action) {
            case 'approve':
                Database::query(
                    "UPDATE group_members SET status = 'active' WHERE group_id = ? AND user_id = ?",
                    [$groupId, $userId]
                );
                GroupAuditService::logMemberApproved($groupId, $_SESSION['user_id'], $userId);
                break;

            case 'reject':
                Database::query(
                    "DELETE FROM group_members WHERE group_id = ? AND user_id = ?",
                    [$groupId, $userId]
                );
                GroupAuditService::logMemberRejected($groupId, $_SESSION['user_id'], $userId, 'Rejected by admin');
                break;

            case 'kick':
                Database::query(
                    "DELETE FROM group_members WHERE group_id = ? AND user_id = ?",
                    [$groupId, $userId]
                );
                GroupAuditService::logMemberKicked($groupId, $_SESSION['user_id'], $userId, 'Kicked by admin');
                break;

            case 'promote':
                Database::query(
                    "UPDATE group_members SET role = 'admin' WHERE group_id = ? AND user_id = ?",
                    [$groupId, $userId]
                );
                GroupAuditService::logMemberRoleChanged($groupId, $_SESSION['user_id'], $userId, 'member', 'admin');
                break;

            case 'demote':
                Database::query(
                    "UPDATE group_members SET role = 'member' WHERE group_id = ? AND user_id = ?",
                    [$groupId, $userId]
                );
                GroupAuditService::logMemberRoleChanged($groupId, $_SESSION['user_id'], $userId, 'admin', 'member');
                break;

            default:
                echo json_encode(['success' => false, 'message' => 'Invalid action']);
                exit;
        }

        echo json_encode(['success' => true, 'message' => 'Action completed successfully']);
        exit;
    }

    /**
     * Configuration settings page
     */
    public function settings()
    {
        if (empty($_SESSION['is_admin'])) {
            http_response_code(403);
            die("Access denied");
        }

        // Handle form submission
        if ($_SERVER['REQUEST_METHOD'] === 'POST') {
            $configs = [];
            foreach ($_POST as $key => $value) {
                if (strpos($key, 'config_') === 0) {
                    $configKey = substr($key, 7); // Remove 'config_' prefix

                    // Convert checkbox values
                    if ($value === 'on') {
                        $value = true;
                    } elseif ($value === 'off' || $value === '') {
                        $value = false;
                    } elseif (is_numeric($value)) {
                        $value = strpos($value, '.') !== false ? (float)$value : (int)$value;
                    }

                    $configs[$configKey] = $value;
                }
            }

            GroupConfigurationService::setMultiple($configs);

            header('Location: ' . TenantContext::getBasePath() . '/admin/groups/settings?saved=1');
            exit;
        }

        $config = GroupConfigurationService::getAll();
        $schema = GroupConfigurationService::getConfigSchema();

        View::render('admin/groups/settings', [
            'config' => $config,
            'schema' => $schema,
            'pageTitle' => 'Group Settings'
        ]);
    }

    /**
     * Policies management page
     */
    public function policies()
    {
        if (empty($_SESSION['is_admin'])) {
            http_response_code(403);
            die("Access denied");
        }

        // Handle form submission
        if ($_SERVER['REQUEST_METHOD'] === 'POST') {
            $action = $_POST['action'] ?? '';

            if ($action === 'save_policy') {
                $key = $_POST['policy_key'] ?? '';
                $value = $_POST['policy_value'] ?? '';
                $category = $_POST['category'] ?? GroupPolicyRepository::CATEGORY_FEATURES;
                $type = $_POST['type'] ?? GroupPolicyRepository::TYPE_STRING;
                $description = $_POST['description'] ?? '';

                // Parse value based on type
                if ($type === GroupPolicyRepository::TYPE_BOOLEAN) {
                    $value = ($value === 'true' || $value === '1');
                } elseif ($type === GroupPolicyRepository::TYPE_NUMBER) {
                    $value = is_numeric($value) ? (strpos($value, '.') !== false ? (float)$value : (int)$value) : 0;
                } elseif ($type === GroupPolicyRepository::TYPE_LIST || $type === GroupPolicyRepository::TYPE_JSON) {
                    $value = json_decode($value, true) ?? [];
                }

                GroupPolicyRepository::setPolicy($key, $value, $category, $type, $description);
            } elseif ($action === 'delete_policy') {
                $key = $_POST['policy_key'] ?? '';
                GroupPolicyRepository::deletePolicy($key);
            }

            header('Location: ' . TenantContext::getBasePath() . '/admin/groups/policies?saved=1');
            exit;
        }

        $policies = GroupPolicyRepository::getAllPolicies();
        $policyCounts = GroupPolicyRepository::getPolicyCounts();

        View::render('admin/groups/policies', [
            'policies' => $policies,
            'policyCounts' => $policyCounts,
            'pageTitle' => 'Group Policies'
        ]);
    }

    /**
     * Batch operations
     */
    public function batchOperations()
    {
        if (empty($_SESSION['is_admin'])) {
            http_response_code(403);
            echo json_encode(['success' => false, 'message' => 'Access denied']);
            exit;
        }

        $action = $_POST['action'] ?? '';
        $groupIds = $_POST['group_ids'] ?? [];

        if (empty($groupIds) || !is_array($groupIds)) {
            echo json_encode(['success' => false, 'message' => 'No groups selected']);
            exit;
        }

        // OPTIMIZED: Use single query for bulk operations instead of loops
        $count = 0;
        $tenantId = TenantContext::getId();

        // Sanitize and validate IDs
        $groupIds = array_map('intval', $groupIds);
        $groupIds = array_filter($groupIds, function ($id) {
            return $id > 0;
        });

        if (empty($groupIds)) {
            echo json_encode(['success' => false, 'message' => 'Invalid group IDs']);
            exit;
        }

        // Build placeholders for IN clause
        $placeholders = implode(',', array_fill(0, count($groupIds), '?'));
        $params = array_merge([$tenantId], $groupIds);

        switch ($action) {
            case 'feature':
                // Single UPDATE for all groups
                Database::query(
                    "UPDATE `groups` SET is_featured = 1 WHERE tenant_id = ? AND id IN ($placeholders)",
                    $params
                );
                $count = count($groupIds);

                // Log each group feature action
                foreach ($groupIds as $groupId) {
                    GroupAuditService::logGroupFeatured($groupId, $_SESSION['user_id'], true);
                }
                break;

            case 'unfeature':
                // Single UPDATE for all groups
                Database::query(
                    "UPDATE `groups` SET is_featured = 0 WHERE tenant_id = ? AND id IN ($placeholders)",
                    $params
                );
                $count = count($groupIds);

                // Log each group unfeature action
                foreach ($groupIds as $groupId) {
                    GroupAuditService::logGroupFeatured($groupId, $_SESSION['user_id'], false);
                }
                break;

            case 'delete':
                // Log deletions first (before data is removed)
                foreach ($groupIds as $groupId) {
                    GroupAuditService::logGroupDeleted($groupId, $_SESSION['user_id'], 'Batch deletion by admin');
                }

                // Single DELETE for group members
                Database::query(
                    "DELETE FROM group_members WHERE group_id IN ($placeholders)",
                    $groupIds
                );

                // Single DELETE for groups (with tenant check)
                Database::query(
                    "DELETE FROM `groups` WHERE tenant_id = ? AND id IN ($placeholders)",
                    $params
                );
                $count = count($groupIds);
                break;
        }

        echo json_encode(['success' => true, 'message' => "$count groups updated"]);
        exit;
    }

    /**
     * Export group data
     */
    public function export()
    {
        if (empty($_SESSION['is_admin'])) {
            http_response_code(403);
            die("Access denied");
        }

        $format = $_GET['format'] ?? 'csv';
        $groupId = $_GET['group_id'] ?? null;

        if ($groupId) {
            // Export single group audit log
            $csv = GroupAuditService::exportToCSV($groupId);
            $group = Group::findById($groupId);
            $filename = 'group_' . ($group['name'] ?? $groupId) . '_audit_log.csv';
        } else {
            // Export all groups
            $tenantId = TenantContext::getId();
            $groups = Database::query(
                "SELECT g.*, gt.name as type_name,
                        COUNT(DISTINCT gm.id) as member_count
                 FROM `groups` g
                 LEFT JOIN group_types gt ON g.type_id = gt.id
                 LEFT JOIN group_members gm ON g.id = gm.group_id AND gm.status = 'active'
                 WHERE g.tenant_id = ?
                 GROUP BY g.id",
                [$tenantId]
            )->fetchAll();

            $output = fopen('php://temp', 'r+');
            fwrite($output, "\xEF\xBB\xBF"); // UTF-8 BOM

            fputcsv($output, ['ID', 'Name', 'Type', 'Visibility', 'Members', 'Featured', 'Location', 'Created At']);

            foreach ($groups as $group) {
                fputcsv($output, [
                    $group['id'],
                    $group['name'],
                    $group['type_name'],
                    $group['visibility'],
                    $group['member_count'],
                    $group['is_featured'] ? 'Yes' : 'No',
                    $group['location'] ?? '',
                    $group['created_at']
                ]);
            }

            rewind($output);
            $csv = stream_get_contents($output);
            fclose($output);

            $filename = 'groups_export_' . date('Y-m-d') . '.csv';
        }

        header('Content-Type: text/csv; charset=utf-8');
        header('Content-Disposition: attachment; filename="' . $filename . '"');
        echo $csv;
        exit;
    }

    /**
     * Moderation dashboard
     */
    public function moderation()
    {
        if (empty($_SESSION['is_admin'])) {
            http_response_code(403);
            die("Access denied");
        }

        // Get pending flags
        $pendingFlags = GroupModerationService::getPendingFlags();

        // Get recent moderation history
        $moderationHistory = GroupModerationService::getModerationHistory([], 20);

        // Get statistics
        $stats = GroupModerationService::getStatistics(30);

        View::render('admin/groups/moderation', [
            'pendingFlags' => $pendingFlags,
            'moderationHistory' => $moderationHistory,
            'stats' => $stats,
            'pageTitle' => 'Content Moderation'
        ]);
    }

    /**
     * Process moderation action
     */
    public function moderateFlag()
    {
        if (empty($_SESSION['is_admin'])) {
            echo json_encode(['success' => false, 'message' => 'Access denied']);
            exit;
        }

        $flagId = $_POST['flag_id'] ?? 0;
        $action = $_POST['action'] ?? '';
        $notes = $_POST['notes'] ?? '';

        $success = GroupModerationService::moderateContent($flagId, $action, $_SESSION['user_id'], $notes);

        echo json_encode([
            'success' => $success,
            'message' => $success ? 'Moderation action completed' : 'Failed to complete action'
        ]);
        exit;
    }

    /**
     * Approval workflow dashboard
     */
    public function approvals()
    {
        if (empty($_SESSION['is_admin'])) {
            http_response_code(403);
            die("Access denied");
        }

        // Get pending approval requests
        $pendingRequests = GroupApprovalWorkflowService::getPendingRequests();

        // Get approval history
        $approvalHistory = GroupApprovalWorkflowService::getApprovalHistory([], 20);

        // Get statistics
        $stats = GroupApprovalWorkflowService::getStatistics(30);

        View::render('admin/groups/approvals', [
            'pendingRequests' => $pendingRequests,
            'approvalHistory' => $approvalHistory,
            'stats' => $stats,
            'pageTitle' => 'Group Approvals'
        ]);
    }

    /**
     * Process approval action
     */
    public function processApproval()
    {
        if (empty($_SESSION['is_admin'])) {
            echo json_encode(['success' => false, 'message' => 'Access denied']);
            exit;
        }

        $requestId = $_POST['request_id'] ?? 0;
        $action = $_POST['action'] ?? '';
        $notes = $_POST['notes'] ?? '';

        $success = false;
        $message = 'Invalid action';

        switch ($action) {
            case 'approve':
                $success = GroupApprovalWorkflowService::approveGroup($requestId, $_SESSION['user_id'], $notes);
                $message = $success ? 'Group approved successfully' : 'Failed to approve group';
                break;

            case 'reject':
                $success = GroupApprovalWorkflowService::rejectGroup($requestId, $_SESSION['user_id'], $notes);
                $message = $success ? 'Group rejected' : 'Failed to reject group';
                break;

            case 'request_changes':
                $success = GroupApprovalWorkflowService::requestChanges($requestId, $_SESSION['user_id'], $notes);
                $message = $success ? 'Changes requested' : 'Failed to request changes';
                break;
        }

        echo json_encode([
            'success' => $success,
            'message' => $message
        ]);
        exit;
    }

    /**
     * Feature toggles management page
     */
    public function features()
    {
        if (empty($_SESSION['is_admin'])) {
            http_response_code(403);
            die("Access denied");
        }

        // Handle form submission
        if ($_SERVER['REQUEST_METHOD'] === 'POST') {
            $action = $_POST['action'] ?? '';

            if ($action === 'update_features') {
                $features = [];
                foreach ($_POST as $key => $value) {
                    if (strpos($key, 'feature_') === 0) {
                        $featureKey = substr($key, 8); // Remove 'feature_' prefix
                        $features[$featureKey] = ($value === '1' || $value === 'on');
                    }
                }

                GroupFeatureToggleService::bulkSet($features);

                header('Location: ' . TenantContext::getBasePath() . '/admin/groups/features?saved=1');
                exit;
            } elseif ($action === 'reset_defaults') {
                GroupFeatureToggleService::resetToDefaults();

                header('Location: ' . TenantContext::getBasePath() . '/admin/groups/features?reset=1');
                exit;
            }
        }

        // Get features grouped by category
        $featuresByCategory = GroupFeatureToggleService::getFeaturesByCategory();

        // Get statistics
        $stats = GroupFeatureToggleService::getStatistics();

        View::render('admin/groups/features', [
            'featuresByCategory' => $featuresByCategory,
            'stats' => $stats,
            'pageTitle' => 'Feature Toggles'
        ]);
    }

    /**
     * Toggle a single feature via AJAX
     */
    public function toggleFeature()
    {
        if (empty($_SESSION['is_admin'])) {
            echo json_encode(['success' => false, 'message' => 'Access denied']);
            exit;
        }

        $feature = $_POST['feature'] ?? '';
        $enabled = $_POST['enabled'] ?? false;
        $enabled = ($enabled === 'true' || $enabled === '1' || $enabled === true);

        if (empty($feature)) {
            echo json_encode(['success' => false, 'message' => 'Feature key required']);
            exit;
        }

        // Validate dependencies if enabling
        if ($enabled) {
            $validation = GroupFeatureToggleService::validateDependencies($feature);
            if (!$validation['valid']) {
                $missingLabels = [];
                foreach ($validation['missing'] as $missing) {
                    $def = GroupFeatureToggleService::getFeatureDefinition($missing);
                    $missingLabels[] = $def['label'] ?? $missing;
                }
                echo json_encode([
                    'success' => false,
                    'message' => 'Cannot enable: Missing dependencies - ' . implode(', ', $missingLabels)
                ]);
                exit;
            }
        }

        $success = $enabled
            ? GroupFeatureToggleService::enable($feature)
            : GroupFeatureToggleService::disable($feature);

        $definition = GroupFeatureToggleService::getFeatureDefinition($feature);
        $label = $definition['label'] ?? $feature;

        echo json_encode([
            'success' => $success,
            'message' => $success
                ? ($enabled ? "$label enabled" : "$label disabled")
                : 'Failed to update feature'
        ]);
        exit;
    }

    /**
     * Get analytics summary
     */
    private function getAnalytics()
    {
        $tenantId = TenantContext::getId();

        // Total groups
        $totalGroups = Database::query(
            "SELECT COUNT(*) FROM `groups` WHERE tenant_id = ?",
            [$tenantId]
        )->fetchColumn();

        // Total members
        $totalMembers = Database::query(
            "SELECT COUNT(DISTINCT gm.user_id)
             FROM group_members gm
             JOIN `groups` g ON gm.group_id = g.id
             WHERE g.tenant_id = ? AND gm.status = 'active'",
            [$tenantId]
        )->fetchColumn();

        // Groups created this month
        $newGroupsThisMonth = Database::query(
            "SELECT COUNT(*) FROM `groups`
             WHERE tenant_id = ? AND created_at >= DATE_SUB(NOW(), INTERVAL 30 DAY)",
            [$tenantId]
        )->fetchColumn();

        // Average members per group
        $avgMembers = Database::query(
            "SELECT AVG(member_count) FROM (
                SELECT COUNT(*) as member_count
                FROM group_members gm
                JOIN `groups` g ON gm.group_id = g.id
                WHERE g.tenant_id = ? AND gm.status = 'active'
                GROUP BY gm.group_id
             ) as counts",
            [$tenantId]
        )->fetchColumn();

        // Featured groups
        $featuredGroups = Database::query(
            "SELECT COUNT(*) FROM `groups` WHERE tenant_id = ? AND is_featured = 1",
            [$tenantId]
        )->fetchColumn();

        // Groups by type
        $groupsByType = Database::query(
            "SELECT gt.name, COUNT(g.id) as count
             FROM group_types gt
             LEFT JOIN `groups` g ON gt.id = g.type_id AND g.tenant_id = ?
             WHERE gt.tenant_id = ?
             GROUP BY gt.id
             ORDER BY count DESC",
            [$tenantId, $tenantId]
        )->fetchAll();

        // Pending approvals
        $pendingApprovals = Database::query(
            "SELECT COUNT(*) FROM group_members gm
             JOIN `groups` g ON gm.group_id = g.id
             WHERE g.tenant_id = ? AND gm.status = 'pending'",
            [$tenantId]
        )->fetchColumn();

        return [
            'totalGroups' => $totalGroups,
            'totalMembers' => $totalMembers,
            'newGroupsThisMonth' => $newGroupsThisMonth,
            'avgMembers' => round($avgMembers, 1),
            'featuredGroups' => $featuredGroups,
            'groupsByType' => $groupsByType,
            'pendingApprovals' => $pendingApprovals,
        ];
    }
}
