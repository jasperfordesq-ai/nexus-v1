<?php

namespace Nexus\Controllers\Admin;

use Nexus\Core\View;
use Nexus\Core\TenantContext;
use Nexus\Core\Csrf;
use Nexus\Core\Database;
use Nexus\Services\BrokerControlConfigService;
use Nexus\Services\ListingRiskTagService;
use Nexus\Services\ExchangeWorkflowService;
use Nexus\Services\BrokerMessageVisibilityService;

/**
 * BrokerControlsController - Admin Dashboard for Broker Control Features
 *
 * Provides admin interface for:
 * - Configuring broker control features (messaging, risk tagging, exchanges, visibility)
 * - Managing exchange requests
 * - Reviewing risk-tagged listings
 * - Reviewing broker message copies
 * - Managing user monitoring
 */
class BrokerControlsController
{
    /**
     * Require admin role
     */
    private function requireAdmin(): void
    {
        if (!isset($_SESSION['user_id'])) {
            header('Location: ' . TenantContext::getBasePath() . '/login');
            exit;
        }

        $role = $_SESSION['user_role'] ?? '';
        $isAdmin = in_array($role, ['admin', 'tenant_admin', 'broker']);
        $isSuper = !empty($_SESSION['is_super_admin']);
        $isAdminSession = !empty($_SESSION['is_admin']);

        if (!$isAdmin && !$isSuper && !$isAdminSession) {
            header('HTTP/1.0 403 Forbidden');
            echo "<h1>403 Forbidden</h1><p>Access Denied.</p>";
            exit;
        }
    }

    /**
     * Dashboard - overview of broker control features
     */
    public function index(): void
    {
        $this->requireAdmin();

        $tenantId = TenantContext::getId();
        $config = BrokerControlConfigService::getConfig();
        $featureSummary = BrokerControlConfigService::getFeatureSummary();

        // Get pending counts
        $pendingExchanges = 0;
        $unreviewedMessages = 0;
        $highRiskListings = 0;
        $usersUnderMonitoring = 0;

        try {
            // Pending exchanges awaiting broker action
            $stmt = Database::query(
                "SELECT COUNT(*) as count FROM exchange_requests
                 WHERE tenant_id = ? AND status IN ('pending_broker', 'disputed')",
                [$tenantId]
            );
            $pendingExchanges = (int) ($stmt->fetch()['count'] ?? 0);

            // Unreviewed message copies
            $stmt = Database::query(
                "SELECT COUNT(*) as count FROM broker_message_copies
                 WHERE tenant_id = ? AND reviewed_at IS NULL",
                [$tenantId]
            );
            $unreviewedMessages = (int) ($stmt->fetch()['count'] ?? 0);

            // High-risk listings
            $stmt = Database::query(
                "SELECT COUNT(*) as count FROM listing_risk_tags
                 WHERE tenant_id = ? AND risk_level IN ('high', 'critical')",
                [$tenantId]
            );
            $highRiskListings = (int) ($stmt->fetch()['count'] ?? 0);

            // Users under monitoring
            $stmt = Database::query(
                "SELECT COUNT(*) as count FROM user_messaging_restrictions
                 WHERE tenant_id = ? AND under_monitoring = 1",
                [$tenantId]
            );
            $usersUnderMonitoring = (int) ($stmt->fetch()['count'] ?? 0);
        } catch (\Exception $e) {
            // Tables may not exist yet
            error_log("BrokerControlsController: Stats query failed - " . $e->getMessage());
        }

        // Recent activity
        $recentActivity = [];
        try {
            $stmt = Database::query(
                "SELECT
                    'exchange' as type,
                    er.id,
                    er.status,
                    er.created_at,
                    u1.name as requester_name,
                    u2.name as provider_name,
                    l.title as listing_title
                 FROM exchange_requests er
                 JOIN users u1 ON er.requester_id = u1.id
                 JOIN users u2 ON er.provider_id = u2.id
                 JOIN listings l ON er.listing_id = l.id
                 WHERE er.tenant_id = ?
                 ORDER BY er.created_at DESC
                 LIMIT 10",
                [$tenantId]
            );
            $recentActivity = $stmt->fetchAll();
        } catch (\Exception $e) {
            // Table may not exist yet
        }

        View::render('admin/broker-controls/index', [
            'config' => $config,
            'feature_summary' => $featureSummary,
            'pending_exchanges' => $pendingExchanges,
            'unreviewed_messages' => $unreviewedMessages,
            'high_risk_listings' => $highRiskListings,
            'users_under_monitoring' => $usersUnderMonitoring,
            'recent_activity' => $recentActivity,
            'page_title' => 'Broker Controls Dashboard',
        ]);
    }

    /**
     * Configuration page - manage all broker control settings
     */
    public function configuration(): void
    {
        $this->requireAdmin();

        // Handle POST - save configuration
        if ($_SERVER['REQUEST_METHOD'] === 'POST') {
            Csrf::verifyOrDie();

            $config = [
                'messaging' => [
                    'direct_messaging_enabled' => isset($_POST['direct_messaging_enabled']),
                    'first_contact_monitoring' => isset($_POST['first_contact_monitoring']),
                    'new_member_monitoring_days' => (int) ($_POST['new_member_monitoring_days'] ?? 30),
                    'require_exchange_for_listings' => isset($_POST['require_exchange_for_listings']),
                ],
                'risk_tagging' => [
                    'enabled' => isset($_POST['risk_tagging_enabled']),
                    'high_risk_requires_approval' => isset($_POST['high_risk_requires_approval']),
                    'notify_on_high_risk_match' => isset($_POST['notify_on_high_risk_match']),
                ],
                'exchange_workflow' => [
                    'enabled' => isset($_POST['exchange_workflow_enabled']),
                    'require_broker_approval' => isset($_POST['require_broker_approval']),
                    'auto_approve_low_risk' => isset($_POST['auto_approve_low_risk']),
                    'max_hours_without_approval' => (float) ($_POST['max_hours_without_approval'] ?? 4),
                    'confirmation_deadline_hours' => (int) ($_POST['confirmation_deadline_hours'] ?? 72),
                    'expiry_hours' => (int) ($_POST['expiry_hours'] ?? 168),
                ],
                'broker_visibility' => [
                    'enabled' => isset($_POST['broker_visibility_enabled']),
                    'copy_first_contact' => isset($_POST['copy_first_contact']),
                    'copy_new_member_messages' => isset($_POST['copy_new_member_messages']),
                    'copy_high_risk_listing_messages' => isset($_POST['copy_high_risk_listing_messages']),
                    'random_sample_percentage' => (int) ($_POST['random_sample_percentage'] ?? 0),
                    'retention_days' => (int) ($_POST['retention_days'] ?? 365),
                ],
            ];

            if (BrokerControlConfigService::updateConfig($config)) {
                $_SESSION['flash_success'] = 'Broker Controls configuration saved successfully!';
            } else {
                $_SESSION['flash_error'] = 'Failed to save configuration.';
            }

            header('Location: ' . TenantContext::getBasePath() . '/admin/broker-controls/configuration');
            exit;
        }

        // GET - show form
        $config = BrokerControlConfigService::getConfig();

        View::render('admin/broker-controls/configuration', [
            'config' => $config,
            'page_title' => 'Broker Controls Configuration',
        ]);
    }

    // =========================================================================
    // EXCHANGE REQUESTS
    // =========================================================================

    /**
     * List exchange requests pending broker action
     */
    public function exchanges(): void
    {
        $this->requireAdmin();

        $tenantId = TenantContext::getId();
        $status = $_GET['status'] ?? 'pending';
        $page = max(1, (int) ($_GET['page'] ?? 1));
        $limit = 20;
        $offset = ($page - 1) * $limit;

        $statusFilter = match ($status) {
            'pending' => "('pending_broker', 'disputed')",
            'active' => "('pending_provider', 'accepted', 'scheduled', 'in_progress', 'pending_confirmation')",
            'completed' => "('completed')",
            'cancelled' => "('cancelled', 'expired')",
            default => "('pending_broker', 'disputed')",
        };

        try {
            // Get exchanges with user and listing info
            $exchanges = Database::query(
                "SELECT er.*,
                        u1.name as requester_name, u1.avatar_url as requester_avatar,
                        u2.name as provider_name, u2.avatar_url as provider_avatar,
                        l.title as listing_title, l.type as listing_type,
                        lrt.risk_level
                 FROM exchange_requests er
                 JOIN users u1 ON er.requester_id = u1.id
                 JOIN users u2 ON er.provider_id = u2.id
                 JOIN listings l ON er.listing_id = l.id
                 LEFT JOIN listing_risk_tags lrt ON l.id = lrt.listing_id
                 WHERE er.tenant_id = ? AND er.status IN {$statusFilter}
                 ORDER BY
                    CASE er.status
                        WHEN 'disputed' THEN 1
                        WHEN 'pending_broker' THEN 2
                        ELSE 3
                    END,
                    er.created_at DESC
                 LIMIT ? OFFSET ?",
                [$tenantId, $limit, $offset]
            )->fetchAll();

            // Get total count
            $totalCount = Database::query(
                "SELECT COUNT(*) as count FROM exchange_requests
                 WHERE tenant_id = ? AND status IN {$statusFilter}",
                [$tenantId]
            )->fetch()['count'] ?? 0;
        } catch (\Exception $e) {
            $exchanges = [];
            $totalCount = 0;
            error_log("BrokerControlsController::exchanges - " . $e->getMessage());
        }

        View::render('admin/broker-controls/exchanges/index', [
            'exchanges' => $exchanges,
            'status' => $status,
            'page' => $page,
            'total_count' => $totalCount,
            'total_pages' => ceil($totalCount / $limit),
            'page_title' => 'Exchange Requests',
        ]);
    }

    /**
     * View single exchange details
     */
    public function showExchange(int $id): void
    {
        $this->requireAdmin();

        $tenantId = TenantContext::getId();

        try {
            $exchange = Database::query(
                "SELECT er.*,
                        u1.name as requester_name, u1.email as requester_email,
                        u1.avatar_url as requester_avatar, u1.created_at as requester_joined,
                        u2.name as provider_name, u2.email as provider_email,
                        u2.avatar_url as provider_avatar, u2.created_at as provider_joined,
                        l.title as listing_title, l.type as listing_type, l.description as listing_description,
                        lrt.risk_level, lrt.risk_notes, lrt.risk_category,
                        b.name as broker_name
                 FROM exchange_requests er
                 JOIN users u1 ON er.requester_id = u1.id
                 JOIN users u2 ON er.provider_id = u2.id
                 JOIN listings l ON er.listing_id = l.id
                 LEFT JOIN listing_risk_tags lrt ON l.id = lrt.listing_id
                 LEFT JOIN users b ON er.broker_id = b.id
                 WHERE er.id = ? AND er.tenant_id = ?",
                [$id, $tenantId]
            )->fetch();

            if (!$exchange) {
                $_SESSION['flash_error'] = 'Exchange not found.';
                header('Location: ' . TenantContext::getBasePath() . '/admin/broker-controls/exchanges');
                exit;
            }

            // Get exchange history
            $history = Database::query(
                "SELECT eh.*, u.name as actor_name
                 FROM exchange_history eh
                 LEFT JOIN users u ON eh.actor_id = u.id
                 WHERE eh.exchange_id = ?
                 ORDER BY eh.created_at DESC",
                [$id]
            )->fetchAll();
        } catch (\Exception $e) {
            $_SESSION['flash_error'] = 'Error loading exchange: ' . $e->getMessage();
            header('Location: ' . TenantContext::getBasePath() . '/admin/broker-controls/exchanges');
            exit;
        }

        View::render('admin/broker-controls/exchanges/show', [
            'exchange' => $exchange,
            'history' => $history,
            'page_title' => 'Exchange Details #' . $id,
        ]);
    }

    /**
     * Approve an exchange request
     */
    public function approveExchange(int $id): void
    {
        $this->requireAdmin();
        Csrf::verifyOrDie();

        $brokerId = $_SESSION['user_id'];
        $notes = trim($_POST['notes'] ?? '');
        $conditions = trim($_POST['conditions'] ?? '');

        if (class_exists('\\Nexus\\Services\\ExchangeWorkflowService')) {
            $success = ExchangeWorkflowService::approveExchange($id, $brokerId, $notes, $conditions);
        } else {
            // Fallback if service not yet created
            $tenantId = TenantContext::getId();
            try {
                Database::query(
                    "UPDATE exchange_requests
                     SET status = 'accepted', broker_id = ?, broker_notes = ?,
                         broker_conditions = ?, broker_approved_at = NOW(), updated_at = NOW()
                     WHERE id = ? AND tenant_id = ?",
                    [$brokerId, $notes, $conditions, $id, $tenantId]
                );
                $success = true;
            } catch (\Exception $e) {
                $success = false;
                error_log("BrokerControlsController::approveExchange - " . $e->getMessage());
            }
        }

        if ($success) {
            $_SESSION['flash_success'] = 'Exchange approved successfully.';
        } else {
            $_SESSION['flash_error'] = 'Failed to approve exchange.';
        }

        header('Location: ' . TenantContext::getBasePath() . '/admin/broker-controls/exchanges');
        exit;
    }

    /**
     * Reject an exchange request
     */
    public function rejectExchange(int $id): void
    {
        $this->requireAdmin();
        Csrf::verifyOrDie();

        $brokerId = $_SESSION['user_id'];
        $reason = trim($_POST['reason'] ?? '');

        if (empty($reason)) {
            $_SESSION['flash_error'] = 'Please provide a reason for rejection.';
            header('Location: ' . TenantContext::getBasePath() . '/admin/broker-controls/exchanges/' . $id);
            exit;
        }

        if (class_exists('\\Nexus\\Services\\ExchangeWorkflowService')) {
            $success = ExchangeWorkflowService::rejectExchange($id, $brokerId, $reason);
        } else {
            $tenantId = TenantContext::getId();
            try {
                Database::query(
                    "UPDATE exchange_requests
                     SET status = 'cancelled', broker_id = ?, broker_notes = ?,
                         cancelled_by = ?, cancelled_at = NOW(), cancellation_reason = ?, updated_at = NOW()
                     WHERE id = ? AND tenant_id = ?",
                    [$brokerId, $reason, $brokerId, $reason, $id, $tenantId]
                );
                $success = true;
            } catch (\Exception $e) {
                $success = false;
                error_log("BrokerControlsController::rejectExchange - " . $e->getMessage());
            }
        }

        if ($success) {
            $_SESSION['flash_success'] = 'Exchange rejected.';
        } else {
            $_SESSION['flash_error'] = 'Failed to reject exchange.';
        }

        header('Location: ' . TenantContext::getBasePath() . '/admin/broker-controls/exchanges');
        exit;
    }

    // =========================================================================
    // RISK TAGS
    // =========================================================================

    /**
     * List all risk-tagged listings
     */
    public function riskTags(): void
    {
        $this->requireAdmin();

        $tenantId = TenantContext::getId();
        $riskLevel = $_GET['level'] ?? null;

        try {
            $sql = "SELECT lrt.*,
                           l.title as listing_title, l.type as listing_type,
                           u.name as owner_name,
                           b.name as tagged_by_name
                    FROM listing_risk_tags lrt
                    JOIN listings l ON lrt.listing_id = l.id
                    JOIN users u ON l.user_id = u.id
                    LEFT JOIN users b ON lrt.tagged_by = b.id
                    WHERE lrt.tenant_id = ?";
            $params = [$tenantId];

            if ($riskLevel && in_array($riskLevel, ['low', 'medium', 'high', 'critical'])) {
                $sql .= " AND lrt.risk_level = ?";
                $params[] = $riskLevel;
            }

            $sql .= " ORDER BY FIELD(lrt.risk_level, 'critical', 'high', 'medium', 'low'), lrt.created_at DESC";

            $riskTags = Database::query($sql, $params)->fetchAll();
        } catch (\Exception $e) {
            $riskTags = [];
            error_log("BrokerControlsController::riskTags - " . $e->getMessage());
        }

        View::render('admin/broker-controls/risk-tags/index', [
            'risk_tags' => $riskTags,
            'risk_level' => $riskLevel,
            'page_title' => 'Risk Tagged Listings',
        ]);
    }

    /**
     * Tag a listing with risk assessment
     */
    public function tagListing(int $listingId): void
    {
        $this->requireAdmin();

        $tenantId = TenantContext::getId();

        // Handle POST - save tag
        if ($_SERVER['REQUEST_METHOD'] === 'POST') {
            Csrf::verifyOrDie();

            $brokerId = $_SESSION['user_id'];

            if (class_exists('\\Nexus\\Services\\ListingRiskTagService')) {
                $data = [
                    'risk_level' => $_POST['risk_level'] ?? 'low',
                    'risk_category' => $_POST['risk_category'] ?? null,
                    'risk_notes' => $_POST['risk_notes'] ?? null,
                    'member_visible_notes' => $_POST['member_visible_notes'] ?? null,
                    'requires_approval' => isset($_POST['requires_approval']),
                    'insurance_required' => isset($_POST['insurance_required']),
                    'dbs_required' => isset($_POST['dbs_required']),
                ];
                $success = ListingRiskTagService::tagListing($listingId, $data, $brokerId) !== null;
            } else {
                // Fallback
                try {
                    $existing = Database::query(
                        "SELECT id FROM listing_risk_tags WHERE listing_id = ?",
                        [$listingId]
                    )->fetch();

                    if ($existing) {
                        Database::query(
                            "UPDATE listing_risk_tags SET
                                risk_level = ?, risk_category = ?, risk_notes = ?,
                                member_visible_notes = ?, requires_approval = ?,
                                insurance_required = ?, dbs_required = ?,
                                tagged_by = ?, updated_at = NOW()
                             WHERE listing_id = ?",
                            [
                                $_POST['risk_level'] ?? 'low',
                                $_POST['risk_category'] ?? null,
                                $_POST['risk_notes'] ?? null,
                                $_POST['member_visible_notes'] ?? null,
                                isset($_POST['requires_approval']) ? 1 : 0,
                                isset($_POST['insurance_required']) ? 1 : 0,
                                isset($_POST['dbs_required']) ? 1 : 0,
                                $brokerId,
                                $listingId
                            ]
                        );
                    } else {
                        Database::query(
                            "INSERT INTO listing_risk_tags
                             (tenant_id, listing_id, risk_level, risk_category, risk_notes,
                              member_visible_notes, requires_approval, insurance_required,
                              dbs_required, tagged_by)
                             VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?)",
                            [
                                $tenantId,
                                $listingId,
                                $_POST['risk_level'] ?? 'low',
                                $_POST['risk_category'] ?? null,
                                $_POST['risk_notes'] ?? null,
                                $_POST['member_visible_notes'] ?? null,
                                isset($_POST['requires_approval']) ? 1 : 0,
                                isset($_POST['insurance_required']) ? 1 : 0,
                                isset($_POST['dbs_required']) ? 1 : 0,
                                $brokerId
                            ]
                        );
                    }
                    $success = true;
                } catch (\Exception $e) {
                    $success = false;
                    error_log("BrokerControlsController::tagListing - " . $e->getMessage());
                }
            }

            if ($success) {
                $_SESSION['flash_success'] = 'Risk tag saved successfully.';
            } else {
                $_SESSION['flash_error'] = 'Failed to save risk tag.';
            }

            header('Location: ' . TenantContext::getBasePath() . '/admin/broker-controls/risk-tags');
            exit;
        }

        // GET - show form
        try {
            $listing = Database::query(
                "SELECT l.*, u.name as owner_name, c.name as category_name
                 FROM listings l
                 JOIN users u ON l.user_id = u.id
                 LEFT JOIN categories c ON l.category_id = c.id
                 WHERE l.id = ? AND l.tenant_id = ?",
                [$listingId, $tenantId]
            )->fetch();

            if (!$listing) {
                $_SESSION['flash_error'] = 'Listing not found.';
                header('Location: ' . TenantContext::getBasePath() . '/admin/broker-controls/risk-tags');
                exit;
            }

            $existingTag = Database::query(
                "SELECT * FROM listing_risk_tags WHERE listing_id = ?",
                [$listingId]
            )->fetch();
        } catch (\Exception $e) {
            $_SESSION['flash_error'] = 'Error loading listing: ' . $e->getMessage();
            header('Location: ' . TenantContext::getBasePath() . '/admin/broker-controls/risk-tags');
            exit;
        }

        View::render('admin/broker-controls/risk-tags/form', [
            'listing' => $listing,
            'existing_tag' => $existingTag,
            'page_title' => 'Tag Listing: ' . $listing['title'],
        ]);
    }

    /**
     * Remove risk tag from a listing
     */
    public function removeTag(int $listingId): void
    {
        $this->requireAdmin();
        Csrf::verifyOrDie();

        $tenantId = TenantContext::getId();

        try {
            Database::query(
                "DELETE FROM listing_risk_tags WHERE listing_id = ? AND tenant_id = ?",
                [$listingId, $tenantId]
            );
            $_SESSION['flash_success'] = 'Risk tag removed.';
        } catch (\Exception $e) {
            $_SESSION['flash_error'] = 'Failed to remove risk tag.';
            error_log("BrokerControlsController::removeTag - " . $e->getMessage());
        }

        header('Location: ' . TenantContext::getBasePath() . '/admin/broker-controls/risk-tags');
        exit;
    }

    // =========================================================================
    // MESSAGE REVIEW
    // =========================================================================

    /**
     * List unreviewed message copies
     */
    public function messages(): void
    {
        $this->requireAdmin();

        $tenantId = TenantContext::getId();
        $filter = $_GET['filter'] ?? 'unreviewed';

        try {
            $sql = "SELECT bmc.*,
                           s.name as sender_name, s.avatar_url as sender_avatar,
                           r.name as receiver_name, r.avatar_url as receiver_avatar,
                           l.title as listing_title
                    FROM broker_message_copies bmc
                    JOIN users s ON bmc.sender_id = s.id
                    JOIN users r ON bmc.receiver_id = r.id
                    LEFT JOIN listings l ON bmc.related_listing_id = l.id
                    WHERE bmc.tenant_id = ?";

            if ($filter === 'unreviewed') {
                $sql .= " AND bmc.reviewed_at IS NULL";
            } elseif ($filter === 'flagged') {
                $sql .= " AND bmc.flagged = 1";
            }

            $sql .= " ORDER BY bmc.created_at DESC LIMIT 100";

            $messages = Database::query($sql, [$tenantId])->fetchAll();
        } catch (\Exception $e) {
            $messages = [];
            error_log("BrokerControlsController::messages - " . $e->getMessage());
        }

        View::render('admin/broker-controls/messages/index', [
            'messages' => $messages,
            'filter' => $filter,
            'page_title' => 'Message Review',
        ]);
    }

    /**
     * Mark a message as reviewed
     */
    public function reviewMessage(int $id): void
    {
        $this->requireAdmin();
        Csrf::verifyOrDie();

        $brokerId = $_SESSION['user_id'];
        $tenantId = TenantContext::getId();

        try {
            Database::query(
                "UPDATE broker_message_copies SET reviewed_by = ?, reviewed_at = NOW()
                 WHERE id = ? AND tenant_id = ?",
                [$brokerId, $id, $tenantId]
            );
            $_SESSION['flash_success'] = 'Message marked as reviewed.';
        } catch (\Exception $e) {
            $_SESSION['flash_error'] = 'Failed to mark message as reviewed.';
            error_log("BrokerControlsController::reviewMessage - " . $e->getMessage());
        }

        header('Location: ' . TenantContext::getBasePath() . '/admin/broker-controls/messages');
        exit;
    }

    /**
     * Flag a message for concern
     */
    public function flagMessage(int $id): void
    {
        $this->requireAdmin();
        Csrf::verifyOrDie();

        $brokerId = $_SESSION['user_id'];
        $tenantId = TenantContext::getId();
        $reason = trim($_POST['reason'] ?? '');
        $severity = $_POST['severity'] ?? 'concern';

        try {
            Database::query(
                "UPDATE broker_message_copies
                 SET flagged = 1, flag_reason = ?, flag_severity = ?,
                     reviewed_by = ?, reviewed_at = NOW()
                 WHERE id = ? AND tenant_id = ?",
                [$reason, $severity, $brokerId, $id, $tenantId]
            );
            $_SESSION['flash_success'] = 'Message flagged successfully.';
        } catch (\Exception $e) {
            $_SESSION['flash_error'] = 'Failed to flag message.';
            error_log("BrokerControlsController::flagMessage - " . $e->getMessage());
        }

        header('Location: ' . TenantContext::getBasePath() . '/admin/broker-controls/messages');
        exit;
    }

    // =========================================================================
    // USER MONITORING
    // =========================================================================

    /**
     * List users under monitoring
     */
    public function userMonitoring(): void
    {
        $this->requireAdmin();

        $tenantId = TenantContext::getId();

        try {
            $users = Database::query(
                "SELECT umr.*, u.name, u.email, u.avatar_url, u.created_at as user_joined,
                        b.name as restricted_by_name
                 FROM user_messaging_restrictions umr
                 JOIN users u ON umr.user_id = u.id
                 LEFT JOIN users b ON umr.restricted_by = b.id
                 WHERE umr.tenant_id = ? AND umr.under_monitoring = 1
                 ORDER BY umr.monitoring_started_at DESC",
                [$tenantId]
            )->fetchAll();
        } catch (\Exception $e) {
            $users = [];
            error_log("BrokerControlsController::userMonitoring - " . $e->getMessage());
        }

        View::render('admin/broker-controls/monitoring/index', [
            'users' => $users,
            'page_title' => 'User Monitoring',
        ]);
    }

    /**
     * Toggle user monitoring
     */
    public function setMonitoring(int $userId): void
    {
        $this->requireAdmin();
        Csrf::verifyOrDie();

        $brokerId = $_SESSION['user_id'];
        $tenantId = TenantContext::getId();
        $enabled = isset($_POST['enable']);
        $reason = trim($_POST['reason'] ?? '');

        try {
            // Check if restriction record exists
            $existing = Database::query(
                "SELECT id FROM user_messaging_restrictions WHERE tenant_id = ? AND user_id = ?",
                [$tenantId, $userId]
            )->fetch();

            if ($existing) {
                Database::query(
                    "UPDATE user_messaging_restrictions SET
                        under_monitoring = ?, monitoring_reason = ?,
                        monitoring_started_at = CASE WHEN ? = 1 THEN NOW() ELSE monitoring_started_at END,
                        restricted_by = ?, updated_at = NOW()
                     WHERE tenant_id = ? AND user_id = ?",
                    [$enabled ? 1 : 0, $reason, $enabled ? 1 : 0, $brokerId, $tenantId, $userId]
                );
            } else {
                Database::query(
                    "INSERT INTO user_messaging_restrictions
                     (tenant_id, user_id, under_monitoring, monitoring_reason,
                      monitoring_started_at, restricted_by, restricted_at)
                     VALUES (?, ?, ?, ?, NOW(), ?, NOW())",
                    [$tenantId, $userId, $enabled ? 1 : 0, $reason, $brokerId]
                );
            }

            $_SESSION['flash_success'] = $enabled ? 'User added to monitoring.' : 'User removed from monitoring.';
        } catch (\Exception $e) {
            $_SESSION['flash_error'] = 'Failed to update monitoring status.';
            error_log("BrokerControlsController::setMonitoring - " . $e->getMessage());
        }

        header('Location: ' . TenantContext::getBasePath() . '/admin/broker-controls/monitoring');
        exit;
    }

    // =========================================================================
    // STATISTICS
    // =========================================================================

    /**
     * Statistics dashboard
     */
    public function stats(): void
    {
        $this->requireAdmin();

        $tenantId = TenantContext::getId();
        $days = (int) ($_GET['days'] ?? 30);

        $stats = [
            'exchanges' => [],
            'messages' => [],
            'risk_tags' => [],
        ];

        try {
            // Exchange stats
            $stats['exchanges'] = Database::query(
                "SELECT
                    COUNT(*) as total,
                    SUM(CASE WHEN status = 'completed' THEN 1 ELSE 0 END) as completed,
                    SUM(CASE WHEN status = 'cancelled' THEN 1 ELSE 0 END) as cancelled,
                    SUM(CASE WHEN status = 'disputed' THEN 1 ELSE 0 END) as disputed,
                    AVG(CASE WHEN final_hours IS NOT NULL THEN final_hours ELSE NULL END) as avg_hours
                 FROM exchange_requests
                 WHERE tenant_id = ? AND created_at >= DATE_SUB(NOW(), INTERVAL ? DAY)",
                [$tenantId, $days]
            )->fetch();

            // Message copy stats
            $stats['messages'] = Database::query(
                "SELECT
                    COUNT(*) as total,
                    SUM(CASE WHEN reviewed_at IS NOT NULL THEN 1 ELSE 0 END) as reviewed,
                    SUM(CASE WHEN flagged = 1 THEN 1 ELSE 0 END) as flagged
                 FROM broker_message_copies
                 WHERE tenant_id = ? AND created_at >= DATE_SUB(NOW(), INTERVAL ? DAY)",
                [$tenantId, $days]
            )->fetch();

            // Risk tag distribution
            $stats['risk_tags'] = Database::query(
                "SELECT risk_level, COUNT(*) as count
                 FROM listing_risk_tags
                 WHERE tenant_id = ?
                 GROUP BY risk_level",
                [$tenantId]
            )->fetchAll();
        } catch (\Exception $e) {
            error_log("BrokerControlsController::stats - " . $e->getMessage());
        }

        View::render('admin/broker-controls/stats', [
            'stats' => $stats,
            'days' => $days,
            'page_title' => 'Broker Controls Statistics',
        ]);
    }
}
