<?php

namespace Nexus\Services;

use Nexus\Core\Database;
use Nexus\Core\TenantContext;
use Nexus\Models\Notification;
use Nexus\Services\FederationEmailService;

/**
 * FederationPartnershipService
 *
 * Manages partnerships between tenants for federation.
 * Handles partnership requests, approvals, and permission management.
 */
class FederationPartnershipService
{
    // Partnership statuses
    const STATUS_PENDING = 'pending';
    const STATUS_ACTIVE = 'active';
    const STATUS_SUSPENDED = 'suspended';
    const STATUS_TERMINATED = 'terminated';

    // Federation levels
    const LEVEL_DISCOVERY = 1;    // Can see tenant exists
    const LEVEL_SOCIAL = 2;       // Can view profiles, message
    const LEVEL_ECONOMIC = 3;     // Can transact
    const LEVEL_INTEGRATED = 4;   // Full integration

    /**
     * Request a partnership with another tenant
     */
    public static function requestPartnership(
        int $requestingTenantId,
        int $targetTenantId,
        int $requestedBy,
        int $federationLevel = self::LEVEL_DISCOVERY,
        ?string $notes = null
    ): array {
        // Check if federation is enabled for requesting tenant
        $check = FederationFeatureService::isOperationAllowed('profiles', $requestingTenantId);
        if (!$check['allowed']) {
            return ['success' => false, 'error' => $check['reason']];
        }

        // Check if target tenant allows incoming partnerships
        $targetCheck = FederationFeatureService::isTenantFeatureEnabled(
            FederationFeatureService::TENANT_APPEAR_IN_DIRECTORY,
            $targetTenantId
        );
        if (!$targetCheck) {
            return ['success' => false, 'error' => 'Target tenant is not accepting federation requests'];
        }

        // Check for existing partnership
        $existing = self::getPartnership($requestingTenantId, $targetTenantId);
        if ($existing) {
            if ($existing['status'] === self::STATUS_ACTIVE) {
                return ['success' => false, 'error' => 'Partnership already exists'];
            }
            if ($existing['status'] === self::STATUS_PENDING) {
                return ['success' => false, 'error' => 'Partnership request already pending'];
            }
        }

        try {
            Database::query("
                INSERT INTO federation_partnerships (
                    tenant_id, partner_tenant_id, status, federation_level,
                    requested_at, requested_by, notes
                ) VALUES (?, ?, 'pending', ?, NOW(), ?, ?)
                ON DUPLICATE KEY UPDATE
                    status = 'pending',
                    federation_level = VALUES(federation_level),
                    requested_at = NOW(),
                    requested_by = VALUES(requested_by),
                    notes = VALUES(notes),
                    terminated_at = NULL,
                    terminated_by = NULL,
                    termination_reason = NULL
            ", [$requestingTenantId, $targetTenantId, $federationLevel, $requestedBy, $notes]);

            FederationAuditService::log(
                'partnership_requested',
                $requestingTenantId,
                $targetTenantId,
                $requestedBy,
                [
                    'federation_level' => $federationLevel,
                    'notes' => $notes
                ]
            );

            // Send email notification to target tenant admins
            $requestingTenant = Database::query(
                "SELECT name FROM tenants WHERE id = ?",
                [$requestingTenantId]
            )->fetch();

            if ($requestingTenant) {
                FederationEmailService::sendPartnershipRequestNotification(
                    $targetTenantId,
                    $requestingTenantId,
                    $requestingTenant['name'],
                    $federationLevel,
                    $notes
                );

                // Send in-app notification to target tenant admins
                $levelName = self::getLevelName($federationLevel);
                self::notifyTenantAdmins(
                    $targetTenantId,
                    "New federation partnership request from {$requestingTenant['name']} (Level: {$levelName})"
                );
            }

            return ['success' => true, 'message' => 'Partnership request sent'];

        } catch (\Exception $e) {
            error_log("FederationPartnershipService::requestPartnership error: " . $e->getMessage());
            return ['success' => false, 'error' => 'Failed to create partnership request'];
        }
    }

    /**
     * Approve a partnership request
     */
    public static function approvePartnership(
        int $partnershipId,
        int $approvedBy,
        array $permissions = []
    ): array {
        $partnership = self::getPartnershipById($partnershipId);
        if (!$partnership) {
            return ['success' => false, 'error' => 'Partnership not found'];
        }

        if ($partnership['status'] !== self::STATUS_PENDING) {
            return ['success' => false, 'error' => 'Partnership is not pending approval'];
        }

        // Set default permissions based on federation level
        $defaultPermissions = self::getDefaultPermissions($partnership['federation_level']);
        $permissions = array_merge($defaultPermissions, $permissions);

        try {
            Database::query("
                UPDATE federation_partnerships SET
                    status = 'active',
                    approved_at = NOW(),
                    approved_by = ?,
                    profiles_enabled = ?,
                    messaging_enabled = ?,
                    transactions_enabled = ?,
                    listings_enabled = ?,
                    events_enabled = ?,
                    groups_enabled = ?,
                    updated_at = NOW()
                WHERE id = ?
            ", [
                $approvedBy,
                $permissions['profiles'] ? 1 : 0,
                $permissions['messaging'] ? 1 : 0,
                $permissions['transactions'] ? 1 : 0,
                $permissions['listings'] ? 1 : 0,
                $permissions['events'] ? 1 : 0,
                $permissions['groups'] ? 1 : 0,
                $partnershipId
            ]);

            FederationAuditService::log(
                'partnership_approved',
                $partnership['partner_tenant_id'],
                $partnership['tenant_id'],
                $approvedBy,
                ['permissions' => $permissions]
            );

            // Send email notification to requesting tenant admins
            $approverTenant = Database::query(
                "SELECT name FROM tenants WHERE id = ?",
                [$partnership['partner_tenant_id']]
            )->fetch();

            if ($approverTenant) {
                FederationEmailService::sendPartnershipApprovedNotification(
                    $partnership['tenant_id'],
                    $partnership['partner_tenant_id'],
                    $approverTenant['name'],
                    $partnership['federation_level']
                );

                // Send in-app notification to requesting tenant admins
                $levelName = self::getLevelName($partnership['federation_level']);
                self::notifyTenantAdmins(
                    $partnership['tenant_id'],
                    "Partnership approved! {$approverTenant['name']} accepted your {$levelName} federation request"
                );
            }

            return ['success' => true, 'message' => 'Partnership approved'];

        } catch (\Exception $e) {
            error_log("FederationPartnershipService::approvePartnership error: " . $e->getMessage());
            return ['success' => false, 'error' => 'Failed to approve partnership'];
        }
    }

    /**
     * Counter-propose a partnership request with different terms
     */
    public static function counterPropose(
        int $partnershipId,
        int $proposedBy,
        int $newLevel,
        array $proposedPermissions = [],
        ?string $message = null
    ): array {
        $partnership = self::getPartnershipById($partnershipId);
        if (!$partnership) {
            return ['success' => false, 'error' => 'Partnership not found'];
        }

        if ($partnership['status'] !== self::STATUS_PENDING) {
            return ['success' => false, 'error' => 'Partnership is not pending'];
        }

        try {
            // Store the counter-proposal - swap the direction
            Database::query("
                UPDATE federation_partnerships SET
                    federation_level = ?,
                    counter_proposed_at = NOW(),
                    counter_proposed_by = ?,
                    counter_proposal_message = ?,
                    counter_proposed_level = ?,
                    counter_proposed_permissions = ?,
                    updated_at = NOW()
                WHERE id = ?
            ", [
                $newLevel,
                $proposedBy,
                $message,
                $newLevel,
                json_encode($proposedPermissions),
                $partnershipId
            ]);

            FederationAuditService::log(
                'partnership_counter_proposed',
                $partnership['partner_tenant_id'],
                $partnership['tenant_id'],
                $proposedBy,
                [
                    'original_level' => $partnership['federation_level'],
                    'proposed_level' => $newLevel,
                    'proposed_permissions' => $proposedPermissions,
                    'message' => $message
                ]
            );

            // Send email notification to original requester tenant admins
            $counterProposerTenant = Database::query(
                "SELECT name FROM tenants WHERE id = ?",
                [$partnership['partner_tenant_id']]
            )->fetch();

            if ($counterProposerTenant) {
                FederationEmailService::sendPartnershipCounterProposalNotification(
                    $partnership['tenant_id'],
                    $partnership['partner_tenant_id'],
                    $counterProposerTenant['name'],
                    $partnership['federation_level'],
                    $newLevel,
                    $message
                );

                // Send in-app notification to original requester tenant admins
                $newLevelName = self::getLevelName($newLevel);
                self::notifyTenantAdmins(
                    $partnership['tenant_id'],
                    "Counter-proposal received from {$counterProposerTenant['name']} - proposed {$newLevelName} level partnership"
                );
            }

            return ['success' => true, 'message' => 'Counter-proposal sent'];

        } catch (\Exception $e) {
            error_log("FederationPartnershipService::counterPropose error: " . $e->getMessage());
            return ['success' => false, 'error' => 'Failed to send counter-proposal'];
        }
    }

    /**
     * Accept a counter-proposal (by the original requester)
     */
    public static function acceptCounterProposal(
        int $partnershipId,
        int $acceptedBy
    ): array {
        $partnership = self::getPartnershipById($partnershipId);
        if (!$partnership) {
            return ['success' => false, 'error' => 'Partnership not found'];
        }

        if ($partnership['status'] !== self::STATUS_PENDING) {
            return ['success' => false, 'error' => 'Partnership is not pending'];
        }

        if (empty($partnership['counter_proposed_at'])) {
            return ['success' => false, 'error' => 'No counter-proposal to accept'];
        }

        // Get proposed permissions or use defaults for the level
        $proposedPermissions = [];
        if (!empty($partnership['counter_proposed_permissions'])) {
            $proposedPermissions = json_decode($partnership['counter_proposed_permissions'], true) ?: [];
        }
        $defaultPermissions = self::getDefaultPermissions($partnership['federation_level']);
        $permissions = array_merge($defaultPermissions, $proposedPermissions);

        try {
            Database::query("
                UPDATE federation_partnerships SET
                    status = 'active',
                    approved_at = NOW(),
                    approved_by = ?,
                    profiles_enabled = ?,
                    messaging_enabled = ?,
                    transactions_enabled = ?,
                    listings_enabled = ?,
                    events_enabled = ?,
                    groups_enabled = ?,
                    updated_at = NOW()
                WHERE id = ?
            ", [
                $acceptedBy,
                $permissions['profiles'] ? 1 : 0,
                $permissions['messaging'] ? 1 : 0,
                $permissions['transactions'] ? 1 : 0,
                $permissions['listings'] ? 1 : 0,
                $permissions['events'] ? 1 : 0,
                $permissions['groups'] ? 1 : 0,
                $partnershipId
            ]);

            FederationAuditService::log(
                'partnership_counter_accepted',
                $partnership['tenant_id'],
                $partnership['partner_tenant_id'],
                $acceptedBy,
                ['accepted_level' => $partnership['federation_level']]
            );

            // Send approval notification to the counter-proposer tenant (partnership is now active)
            $accepterTenant = Database::query(
                "SELECT name FROM tenants WHERE id = ?",
                [$partnership['tenant_id']]
            )->fetch();

            if ($accepterTenant) {
                FederationEmailService::sendPartnershipApprovedNotification(
                    $partnership['partner_tenant_id'],
                    $partnership['tenant_id'],
                    $accepterTenant['name'],
                    $partnership['federation_level']
                );

                // Send in-app notification to counter-proposer tenant admins
                $levelName = self::getLevelName($partnership['federation_level']);
                self::notifyTenantAdmins(
                    $partnership['partner_tenant_id'],
                    "Counter-proposal accepted! {$accepterTenant['name']} agreed to {$levelName} level partnership"
                );
            }

            return ['success' => true, 'message' => 'Counter-proposal accepted, partnership is now active'];

        } catch (\Exception $e) {
            error_log("FederationPartnershipService::acceptCounterProposal error: " . $e->getMessage());
            return ['success' => false, 'error' => 'Failed to accept counter-proposal'];
        }
    }

    /**
     * Reject a partnership request
     */
    public static function rejectPartnership(
        int $partnershipId,
        int $rejectedBy,
        ?string $reason = null
    ): array {
        $partnership = self::getPartnershipById($partnershipId);
        if (!$partnership) {
            return ['success' => false, 'error' => 'Partnership not found'];
        }

        try {
            Database::query("
                UPDATE federation_partnerships SET
                    status = 'terminated',
                    terminated_at = NOW(),
                    terminated_by = ?,
                    termination_reason = ?,
                    updated_at = NOW()
                WHERE id = ?
            ", [$rejectedBy, $reason ?? 'Request rejected', $partnershipId]);

            FederationAuditService::log(
                'partnership_rejected',
                $partnership['partner_tenant_id'],
                $partnership['tenant_id'],
                $rejectedBy,
                ['reason' => $reason]
            );

            // Send rejection notification to requesting tenant admins
            $rejecterTenant = Database::query(
                "SELECT name FROM tenants WHERE id = ?",
                [$partnership['partner_tenant_id']]
            )->fetch();

            if ($rejecterTenant) {
                FederationEmailService::sendPartnershipRejectedNotification(
                    $partnership['tenant_id'],
                    $partnership['partner_tenant_id'],
                    $rejecterTenant['name'],
                    $reason
                );

                // Send in-app notification to requesting tenant admins
                self::notifyTenantAdmins(
                    $partnership['tenant_id'],
                    "Partnership request declined by {$rejecterTenant['name']}"
                );
            }

            return ['success' => true, 'message' => 'Partnership request rejected'];

        } catch (\Exception $e) {
            error_log("FederationPartnershipService::rejectPartnership error: " . $e->getMessage());
            return ['success' => false, 'error' => 'Failed to reject partnership'];
        }
    }

    /**
     * Suspend an active partnership
     */
    public static function suspendPartnership(
        int $partnershipId,
        int $suspendedBy,
        ?string $reason = null
    ): array {
        $partnership = self::getPartnershipById($partnershipId);
        if (!$partnership) {
            return ['success' => false, 'error' => 'Partnership not found'];
        }

        if ($partnership['status'] !== self::STATUS_ACTIVE) {
            return ['success' => false, 'error' => 'Can only suspend active partnerships'];
        }

        try {
            Database::query("
                UPDATE federation_partnerships SET
                    status = 'suspended',
                    termination_reason = ?,
                    updated_at = NOW()
                WHERE id = ?
            ", [$reason, $partnershipId]);

            FederationAuditService::log(
                'partnership_suspended',
                $partnership['tenant_id'],
                $partnership['partner_tenant_id'],
                $suspendedBy,
                ['reason' => $reason],
                FederationAuditService::LEVEL_WARNING
            );

            // Send suspension notification to partner tenant admins
            $suspenderTenant = Database::query(
                "SELECT name FROM tenants WHERE id = ?",
                [$partnership['tenant_id']]
            )->fetch();

            if ($suspenderTenant) {
                FederationEmailService::sendPartnershipSuspendedNotification(
                    $partnership['partner_tenant_id'],
                    $partnership['tenant_id'],
                    $suspenderTenant['name'],
                    $reason
                );

                // Send in-app notification to partner tenant admins
                self::notifyTenantAdmins(
                    $partnership['partner_tenant_id'],
                    "Federation partnership suspended by {$suspenderTenant['name']}"
                );
            }

            return ['success' => true, 'message' => 'Partnership suspended'];

        } catch (\Exception $e) {
            error_log("FederationPartnershipService::suspendPartnership error: " . $e->getMessage());
            return ['success' => false, 'error' => 'Failed to suspend partnership'];
        }
    }

    /**
     * Reactivate a suspended partnership
     */
    public static function reactivatePartnership(
        int $partnershipId,
        int $reactivatedBy
    ): array {
        $partnership = self::getPartnershipById($partnershipId);
        if (!$partnership) {
            return ['success' => false, 'error' => 'Partnership not found'];
        }

        if ($partnership['status'] !== self::STATUS_SUSPENDED) {
            return ['success' => false, 'error' => 'Can only reactivate suspended partnerships'];
        }

        try {
            Database::query("
                UPDATE federation_partnerships SET
                    status = 'active',
                    termination_reason = NULL,
                    updated_at = NOW()
                WHERE id = ?
            ", [$partnershipId]);

            FederationAuditService::log(
                'partnership_reactivated',
                $partnership['tenant_id'],
                $partnership['partner_tenant_id'],
                $reactivatedBy,
                []
            );

            // Send reactivation notification to partner tenant admins
            $reactivatorTenant = Database::query(
                "SELECT name FROM tenants WHERE id = ?",
                [$partnership['tenant_id']]
            )->fetch();

            if ($reactivatorTenant) {
                FederationEmailService::sendPartnershipReactivatedNotification(
                    $partnership['partner_tenant_id'],
                    $partnership['tenant_id'],
                    $reactivatorTenant['name']
                );

                // Send in-app notification to partner tenant admins
                self::notifyTenantAdmins(
                    $partnership['partner_tenant_id'],
                    "Federation partnership reactivated by {$reactivatorTenant['name']}"
                );
            }

            return ['success' => true, 'message' => 'Partnership reactivated'];

        } catch (\Exception $e) {
            error_log("FederationPartnershipService::reactivatePartnership error: " . $e->getMessage());
            return ['success' => false, 'error' => 'Failed to reactivate partnership'];
        }
    }

    /**
     * Terminate a partnership permanently
     */
    public static function terminatePartnership(
        int $partnershipId,
        int $terminatedBy,
        ?string $reason = null
    ): array {
        $partnership = self::getPartnershipById($partnershipId);
        if (!$partnership) {
            return ['success' => false, 'error' => 'Partnership not found'];
        }

        try {
            Database::query("
                UPDATE federation_partnerships SET
                    status = 'terminated',
                    terminated_at = NOW(),
                    terminated_by = ?,
                    termination_reason = ?,
                    updated_at = NOW()
                WHERE id = ?
            ", [$terminatedBy, $reason, $partnershipId]);

            FederationAuditService::log(
                'partnership_terminated',
                $partnership['tenant_id'],
                $partnership['partner_tenant_id'],
                $terminatedBy,
                ['reason' => $reason],
                FederationAuditService::LEVEL_WARNING
            );

            // Send termination notification to partner tenant admins
            $terminatorTenant = Database::query(
                "SELECT name FROM tenants WHERE id = ?",
                [$partnership['tenant_id']]
            )->fetch();

            if ($terminatorTenant) {
                FederationEmailService::sendPartnershipTerminatedNotification(
                    $partnership['partner_tenant_id'],
                    $partnership['tenant_id'],
                    $terminatorTenant['name'],
                    $reason
                );

                // Send in-app notification to partner tenant admins
                self::notifyTenantAdmins(
                    $partnership['partner_tenant_id'],
                    "Federation partnership terminated by {$terminatorTenant['name']}"
                );
            }

            return ['success' => true, 'message' => 'Partnership terminated'];

        } catch (\Exception $e) {
            error_log("FederationPartnershipService::terminatePartnership error: " . $e->getMessage());
            return ['success' => false, 'error' => 'Failed to terminate partnership'];
        }
    }

    /**
     * Update partnership permissions
     */
    public static function updatePermissions(
        int $partnershipId,
        array $permissions,
        int $updatedBy
    ): array {
        $partnership = self::getPartnershipById($partnershipId);
        if (!$partnership) {
            return ['success' => false, 'error' => 'Partnership not found'];
        }

        try {
            Database::query("
                UPDATE federation_partnerships SET
                    profiles_enabled = ?,
                    messaging_enabled = ?,
                    transactions_enabled = ?,
                    listings_enabled = ?,
                    events_enabled = ?,
                    groups_enabled = ?,
                    updated_at = NOW()
                WHERE id = ?
            ", [
                isset($permissions['profiles']) ? ($permissions['profiles'] ? 1 : 0) : $partnership['profiles_enabled'],
                isset($permissions['messaging']) ? ($permissions['messaging'] ? 1 : 0) : $partnership['messaging_enabled'],
                isset($permissions['transactions']) ? ($permissions['transactions'] ? 1 : 0) : $partnership['transactions_enabled'],
                isset($permissions['listings']) ? ($permissions['listings'] ? 1 : 0) : $partnership['listings_enabled'],
                isset($permissions['events']) ? ($permissions['events'] ? 1 : 0) : $partnership['events_enabled'],
                isset($permissions['groups']) ? ($permissions['groups'] ? 1 : 0) : $partnership['groups_enabled'],
                $partnershipId
            ]);

            FederationAuditService::log(
                'partnership_permissions_updated',
                $partnership['tenant_id'],
                $partnership['partner_tenant_id'],
                $updatedBy,
                ['permissions' => $permissions]
            );

            return ['success' => true, 'message' => 'Permissions updated'];

        } catch (\Exception $e) {
            error_log("FederationPartnershipService::updatePermissions error: " . $e->getMessage());
            return ['success' => false, 'error' => 'Failed to update permissions'];
        }
    }

    // =========================================================================
    // QUERY METHODS
    // =========================================================================

    /**
     * Get partnership by ID
     */
    public static function getPartnershipById(int $id): ?array
    {
        try {
            $result = Database::query("
                SELECT p.*,
                    t1.name as tenant_name, t1.domain as tenant_domain,
                    t2.name as partner_name, t2.domain as partner_domain
                FROM federation_partnerships p
                LEFT JOIN tenants t1 ON p.tenant_id = t1.id
                LEFT JOIN tenants t2 ON p.partner_tenant_id = t2.id
                WHERE p.id = ?
            ", [$id])->fetch(\PDO::FETCH_ASSOC);

            return $result ?: null;

        } catch (\Exception $e) {
            error_log("FederationPartnershipService::getPartnershipById error: " . $e->getMessage());
            return null;
        }
    }

    /**
     * Get partnership between two tenants
     */
    public static function getPartnership(int $tenantId1, int $tenantId2): ?array
    {
        try {
            $result = Database::query("
                SELECT * FROM federation_partnerships
                WHERE (tenant_id = ? AND partner_tenant_id = ?)
                   OR (tenant_id = ? AND partner_tenant_id = ?)
            ", [$tenantId1, $tenantId2, $tenantId2, $tenantId1])->fetch(\PDO::FETCH_ASSOC);

            return $result ?: null;

        } catch (\Exception $e) {
            error_log("FederationPartnershipService::getPartnership error: " . $e->getMessage());
            return null;
        }
    }

    /**
     * Get all partnerships for a tenant
     */
    public static function getTenantPartnerships(int $tenantId, ?string $status = null): array
    {
        try {
            $sql = "
                SELECT p.*,
                    CASE WHEN p.tenant_id = ? THEN t2.name ELSE t1.name END as partner_name,
                    CASE WHEN p.tenant_id = ? THEN t2.domain ELSE t1.domain END as partner_domain,
                    CASE WHEN p.tenant_id = ? THEN p.partner_tenant_id ELSE p.tenant_id END as partner_id
                FROM federation_partnerships p
                LEFT JOIN tenants t1 ON p.tenant_id = t1.id
                LEFT JOIN tenants t2 ON p.partner_tenant_id = t2.id
                WHERE (p.tenant_id = ? OR p.partner_tenant_id = ?)
            ";
            $params = [$tenantId, $tenantId, $tenantId, $tenantId, $tenantId];

            if ($status) {
                $sql .= " AND p.status = ?";
                $params[] = $status;
            }

            $sql .= " ORDER BY p.created_at DESC";

            return Database::query($sql, $params)->fetchAll(\PDO::FETCH_ASSOC);

        } catch (\Exception $e) {
            error_log("FederationPartnershipService::getTenantPartnerships error: " . $e->getMessage());
            return [];
        }
    }

    /**
     * Get pending requests for a tenant (incoming - no counter-proposal yet)
     */
    public static function getPendingRequests(int $tenantId): array
    {
        try {
            return Database::query("
                SELECT p.*, t.name as requester_name, t.domain as requester_domain
                FROM federation_partnerships p
                JOIN tenants t ON p.tenant_id = t.id
                WHERE p.partner_tenant_id = ?
                AND p.status = 'pending'
                AND p.counter_proposed_at IS NULL
                ORDER BY p.requested_at DESC
            ", [$tenantId])->fetchAll(\PDO::FETCH_ASSOC);

        } catch (\Exception $e) {
            error_log("FederationPartnershipService::getPendingRequests error: " . $e->getMessage());
            return [];
        }
    }

    /**
     * Get outgoing requests that have counter-proposals awaiting response
     */
    public static function getCounterProposals(int $tenantId): array
    {
        try {
            return Database::query("
                SELECT p.*, t.name as partner_name, t.domain as partner_domain,
                    u.first_name as proposer_first_name, u.last_name as proposer_last_name
                FROM federation_partnerships p
                JOIN tenants t ON p.partner_tenant_id = t.id
                LEFT JOIN users u ON p.counter_proposed_by = u.id
                WHERE p.tenant_id = ?
                AND p.status = 'pending'
                AND p.counter_proposed_at IS NOT NULL
                ORDER BY p.counter_proposed_at DESC
            ", [$tenantId])->fetchAll(\PDO::FETCH_ASSOC);

        } catch (\Exception $e) {
            error_log("FederationPartnershipService::getCounterProposals error: " . $e->getMessage());
            return [];
        }
    }

    /**
     * Get outgoing pending requests (sent by this tenant)
     */
    public static function getOutgoingRequests(int $tenantId): array
    {
        try {
            return Database::query("
                SELECT p.*, t.name as partner_name, t.domain as partner_domain
                FROM federation_partnerships p
                JOIN tenants t ON p.partner_tenant_id = t.id
                WHERE p.tenant_id = ?
                AND p.status = 'pending'
                AND p.counter_proposed_at IS NULL
                ORDER BY p.requested_at DESC
            ", [$tenantId])->fetchAll(\PDO::FETCH_ASSOC);

        } catch (\Exception $e) {
            error_log("FederationPartnershipService::getOutgoingRequests error: " . $e->getMessage());
            return [];
        }
    }

    /**
     * Get all partnerships (for super admin)
     */
    public static function getAllPartnerships(?string $status = null, int $limit = 100): array
    {
        try {
            $sql = "
                SELECT p.*,
                    t1.name as tenant_name, t1.domain as tenant_domain,
                    t2.name as partner_name, t2.domain as partner_domain
                FROM federation_partnerships p
                LEFT JOIN tenants t1 ON p.tenant_id = t1.id
                LEFT JOIN tenants t2 ON p.partner_tenant_id = t2.id
            ";
            $params = [];

            if ($status) {
                $sql .= " WHERE p.status = ?";
                $params[] = $status;
            }

            $sql .= " ORDER BY p.created_at DESC LIMIT " . (int)$limit;

            return Database::query($sql, $params)->fetchAll(\PDO::FETCH_ASSOC);

        } catch (\Exception $e) {
            error_log("FederationPartnershipService::getAllPartnerships error: " . $e->getMessage());
            return [];
        }
    }

    /**
     * Get partnership statistics
     */
    public static function getStats(): array
    {
        try {
            $sql = "SELECT COUNT(*) as total, " .
                   "SUM(CASE WHEN status = 'active' THEN 1 ELSE 0 END) as active, " .
                   "SUM(CASE WHEN status = 'pending' THEN 1 ELSE 0 END) as pending, " .
                   "SUM(CASE WHEN status = 'suspended' THEN 1 ELSE 0 END) as suspended, " .
                   "SUM(CASE WHEN status = 'terminated' THEN 1 ELSE 0 END) as `terminated` " .
                   "FROM federation_partnerships";
            $stats = Database::query($sql)->fetch(\PDO::FETCH_ASSOC);

            $stats = $stats ?: [
                'total' => 0,
                'active' => 0,
                'pending' => 0,
                'suspended' => 0,
                'terminated' => 0
            ];

            // Get recent partnerships for dashboard display
            $stats['recent'] = Database::query("
                SELECT p.*,
                    t1.name as tenant_name,
                    t2.name as partner_name
                FROM federation_partnerships p
                LEFT JOIN tenants t1 ON p.tenant_id = t1.id
                LEFT JOIN tenants t2 ON p.partner_tenant_id = t2.id
                ORDER BY p.updated_at DESC
                LIMIT 5
            ")->fetchAll(\PDO::FETCH_ASSOC);

            return $stats;

        } catch (\Exception $e) {
            error_log("FederationPartnershipService::getStats error: " . $e->getMessage());
            return ['total' => 0, 'active' => 0, 'pending' => 0, 'suspended' => 0, 'terminated' => 0, 'recent' => []];
        }
    }

    // =========================================================================
    // HELPERS
    // =========================================================================

    /**
     * Get default permissions for a federation level
     */
    public static function getDefaultPermissions(int $level): array
    {
        switch ($level) {
            case self::LEVEL_INTEGRATED:
                return [
                    'profiles' => true,
                    'messaging' => true,
                    'transactions' => true,
                    'listings' => true,
                    'events' => true,
                    'groups' => true,
                ];

            case self::LEVEL_ECONOMIC:
                return [
                    'profiles' => true,
                    'messaging' => true,
                    'transactions' => true,
                    'listings' => true,
                    'events' => true,
                    'groups' => false,
                ];

            case self::LEVEL_SOCIAL:
                return [
                    'profiles' => true,
                    'messaging' => true,
                    'transactions' => false,
                    'listings' => true,
                    'events' => true,
                    'groups' => false,
                ];

            case self::LEVEL_DISCOVERY:
            default:
                return [
                    'profiles' => true,
                    'messaging' => false,
                    'transactions' => false,
                    'listings' => false,
                    'events' => false,
                    'groups' => false,
                ];
        }
    }

    /**
     * Get human-readable level name
     */
    public static function getLevelName(int $level): string
    {
        $names = [
            self::LEVEL_DISCOVERY => 'Discovery',
            self::LEVEL_SOCIAL => 'Social',
            self::LEVEL_ECONOMIC => 'Economic',
            self::LEVEL_INTEGRATED => 'Integrated',
        ];

        return $names[$level] ?? 'Unknown';
    }

    /**
     * Get level description
     */
    public static function getLevelDescription(int $level): string
    {
        $descriptions = [
            self::LEVEL_DISCOVERY => 'Basic visibility - can see tenant exists and view basic profiles',
            self::LEVEL_SOCIAL => 'Social features - can message and view listings/events',
            self::LEVEL_ECONOMIC => 'Full trading - can exchange time credits',
            self::LEVEL_INTEGRATED => 'Full integration - all features including groups',
        ];

        return $descriptions[$level] ?? '';
    }

    /**
     * Get admin user IDs for a tenant (for sending notifications)
     */
    private static function getTenantAdminIds(int $tenantId): array
    {
        try {
            $admins = Database::query("
                SELECT u.id
                FROM users u
                WHERE u.tenant_id = ?
                AND u.status = 'active'
                AND (u.role = 'admin' OR u.role = 'coordinator')
            ", [$tenantId])->fetchAll(\PDO::FETCH_COLUMN);

            return $admins ?: [];
        } catch (\Exception $e) {
            error_log("FederationPartnershipService::getTenantAdminIds error: " . $e->getMessage());
            return [];
        }
    }

    /**
     * Send in-app notification to all admins of a tenant
     */
    private static function notifyTenantAdmins(
        int $tenantId,
        string $message,
        string $link = '/admin/federation/partnerships',
        string $type = 'federation_partnership',
        bool $sendPush = true
    ): void {
        try {
            $adminIds = self::getTenantAdminIds($tenantId);
            foreach ($adminIds as $adminId) {
                Notification::create(
                    $adminId,
                    $message,
                    $link,
                    $type,
                    $sendPush
                );
            }
        } catch (\Exception $e) {
            error_log("FederationPartnershipService::notifyTenantAdmins error: " . $e->getMessage());
        }
    }
}
