<?php
// Copyright © 2024–2026 Jasper Ford
// SPDX-License-Identifier: AGPL-3.0-or-later
// Author: Jasper Ford
// See NOTICE file for attribution and acknowledgements.

namespace Nexus\Services;

use Nexus\Core\Database;
use Nexus\Core\TenantContext;

/**
 * FederationGateway
 *
 * The main "kill switch controller" for all federation operations.
 * This service wraps all cross-tenant operations and ensures:
 *
 * 1. Feature flags are checked at every layer
 * 2. Every operation is logged for audit
 * 3. Operations fail safely if any check fails
 * 4. Easy integration point for all federated features
 *
 * USAGE:
 * Before ANY cross-tenant operation, call the appropriate gateway method.
 * Example:
 *   $result = FederationGateway::canViewProfile($viewerTenantId, $targetTenantId, $targetUserId);
 *   if (!$result['allowed']) {
 *       // Handle denial - $result['reason'] explains why
 *       return;
 *   }
 *   // Proceed with operation
 *
 * This is THE SINGLE POINT OF CONTROL for federation.
 */
class FederationGateway
{
    // =========================================================================
    // PROFILE OPERATIONS
    // =========================================================================

    /**
     * Check if a user can view a profile from another tenant
     *
     * @param int $viewerTenantId Tenant of the viewing user
     * @param int $targetTenantId Tenant where the profile lives
     * @param int $targetUserId The user whose profile is being viewed
     * @param int|null $viewerUserId The user viewing (for privacy checks)
     * @return array ['allowed' => bool, 'reason' => string|null]
     */
    public static function canViewProfile(
        int $viewerTenantId,
        int $targetTenantId,
        int $targetUserId,
        ?int $viewerUserId = null
    ): array {
        // Same tenant - always allowed (no federation needed)
        if ($viewerTenantId === $targetTenantId) {
            return ['allowed' => true, 'reason' => null];
        }

        // Check system and tenant feature flags
        $sourceCheck = FederationFeatureService::isOperationAllowed('profiles', $viewerTenantId);
        if (!$sourceCheck['allowed']) {
            return $sourceCheck;
        }

        $targetCheck = FederationFeatureService::isOperationAllowed('profiles', $targetTenantId);
        if (!$targetCheck['allowed']) {
            return $targetCheck;
        }

        // Check partnership between tenants
        $partnershipCheck = self::checkPartnership($viewerTenantId, $targetTenantId, 'profiles');
        if (!$partnershipCheck['allowed']) {
            return $partnershipCheck;
        }

        // Check user-level privacy settings
        $userCheck = self::checkUserPrivacy($targetUserId, 'profile', $viewerUserId);
        if (!$userCheck['allowed']) {
            return $userCheck;
        }

        // All checks passed - log the view
        if ($viewerUserId) {
            FederationAuditService::logProfileView(
                $viewerUserId,
                $viewerTenantId,
                $targetUserId,
                $targetTenantId
            );
        }

        return ['allowed' => true, 'reason' => null];
    }

    // =========================================================================
    // MESSAGING OPERATIONS
    // =========================================================================

    /**
     * Check if a user can send a message to a user in another tenant
     */
    public static function canSendMessage(
        int $senderUserId,
        int $senderTenantId,
        int $recipientUserId,
        int $recipientTenantId
    ): array {
        // Same tenant - no federation check needed
        if ($senderTenantId === $recipientTenantId) {
            return ['allowed' => true, 'reason' => null];
        }

        // Check system and tenant feature flags
        $sourceCheck = FederationFeatureService::isOperationAllowed('messaging', $senderTenantId);
        if (!$sourceCheck['allowed']) {
            return $sourceCheck;
        }

        $targetCheck = FederationFeatureService::isOperationAllowed('messaging', $recipientTenantId);
        if (!$targetCheck['allowed']) {
            return $targetCheck;
        }

        // Check partnership
        $partnershipCheck = self::checkPartnership($senderTenantId, $recipientTenantId, 'messaging');
        if (!$partnershipCheck['allowed']) {
            return $partnershipCheck;
        }

        // Check recipient privacy settings
        $userCheck = self::checkUserPrivacy($recipientUserId, 'messaging', $senderUserId);
        if (!$userCheck['allowed']) {
            FederationAuditService::log(
                'cross_tenant_message_blocked',
                $senderTenantId,
                $recipientTenantId,
                $senderUserId,
                [
                    'recipient_user_id' => $recipientUserId,
                    'block_reason' => $userCheck['reason']
                ],
                FederationAuditService::LEVEL_INFO
            );
            return $userCheck;
        }

        return ['allowed' => true, 'reason' => null];
    }

    /**
     * Record a cross-tenant message (call after message is sent)
     */
    public static function recordMessage(
        int $senderUserId,
        int $senderTenantId,
        int $recipientUserId,
        int $recipientTenantId,
        int $messageId
    ): void {
        if ($senderTenantId !== $recipientTenantId) {
            FederationAuditService::logMessage(
                $senderUserId,
                $senderTenantId,
                $recipientUserId,
                $recipientTenantId,
                $messageId
            );
        }
    }

    // =========================================================================
    // TRANSACTION OPERATIONS
    // =========================================================================

    /**
     * Check if a cross-tenant transaction is allowed
     */
    public static function canPerformTransaction(
        int $initiatorUserId,
        int $initiatorTenantId,
        int $counterpartyUserId,
        int $counterpartyTenantId
    ): array {
        // Same tenant - no federation check needed
        if ($initiatorTenantId === $counterpartyTenantId) {
            return ['allowed' => true, 'reason' => null];
        }

        // Check system and tenant feature flags
        $sourceCheck = FederationFeatureService::isOperationAllowed('transactions', $initiatorTenantId);
        if (!$sourceCheck['allowed']) {
            return $sourceCheck;
        }

        $targetCheck = FederationFeatureService::isOperationAllowed('transactions', $counterpartyTenantId);
        if (!$targetCheck['allowed']) {
            return $targetCheck;
        }

        // Check partnership with transaction rights
        $partnershipCheck = self::checkPartnership($initiatorTenantId, $counterpartyTenantId, 'transactions');
        if (!$partnershipCheck['allowed']) {
            return $partnershipCheck;
        }

        // Check user-level transaction settings
        $userCheck = self::checkUserPrivacy($counterpartyUserId, 'transactions', $initiatorUserId);
        if (!$userCheck['allowed']) {
            return $userCheck;
        }

        return ['allowed' => true, 'reason' => null];
    }

    /**
     * Record a cross-tenant transaction (call after transaction is completed)
     */
    public static function recordTransaction(
        int $initiatorUserId,
        int $initiatorTenantId,
        int $counterpartyUserId,
        int $counterpartyTenantId,
        int $transactionId,
        string $transactionType,
        float $amount
    ): void {
        if ($initiatorTenantId !== $counterpartyTenantId) {
            FederationAuditService::logTransaction(
                $initiatorUserId,
                $initiatorTenantId,
                $counterpartyUserId,
                $counterpartyTenantId,
                $transactionId,
                $transactionType,
                $amount
            );
        }
    }

    // =========================================================================
    // LISTING OPERATIONS
    // =========================================================================

    /**
     * Check if a user can view listings from another tenant
     */
    public static function canViewListings(
        int $viewerTenantId,
        int $targetTenantId
    ): array {
        // Same tenant - always allowed
        if ($viewerTenantId === $targetTenantId) {
            return ['allowed' => true, 'reason' => null];
        }

        // Check system and tenant feature flags
        $sourceCheck = FederationFeatureService::isOperationAllowed('listings', $viewerTenantId);
        if (!$sourceCheck['allowed']) {
            return $sourceCheck;
        }

        $targetCheck = FederationFeatureService::isOperationAllowed('listings', $targetTenantId);
        if (!$targetCheck['allowed']) {
            return $targetCheck;
        }

        // Check partnership
        $partnershipCheck = self::checkPartnership($viewerTenantId, $targetTenantId, 'listings');
        if (!$partnershipCheck['allowed']) {
            return $partnershipCheck;
        }

        return ['allowed' => true, 'reason' => null];
    }

    /**
     * Get list of tenant IDs whose listings can be viewed from the current tenant
     */
    public static function getAccessibleListingTenants(?int $viewerTenantId = null): array
    {
        $viewerTenantId = $viewerTenantId ?? TenantContext::getId();

        // Always include own tenant
        $accessibleTenants = [$viewerTenantId];

        // Check if listings federation is enabled for viewer
        $viewerCheck = FederationFeatureService::isOperationAllowed('listings', $viewerTenantId);
        if (!$viewerCheck['allowed']) {
            return $accessibleTenants;
        }

        // Get partner tenants with listing access
        $partners = self::getPartnerTenants($viewerTenantId, 'listings');
        foreach ($partners as $partnerId) {
            // Verify the partner also has listings enabled
            $partnerCheck = FederationFeatureService::isOperationAllowed('listings', $partnerId);
            if ($partnerCheck['allowed']) {
                $accessibleTenants[] = $partnerId;
            }
        }

        return array_unique($accessibleTenants);
    }

    // =========================================================================
    // EVENT OPERATIONS
    // =========================================================================

    /**
     * Check if a user can view/join events from another tenant
     */
    public static function canViewEvents(
        int $viewerTenantId,
        int $targetTenantId
    ): array {
        // Same tenant - always allowed
        if ($viewerTenantId === $targetTenantId) {
            return ['allowed' => true, 'reason' => null];
        }

        // Check system and tenant feature flags
        $sourceCheck = FederationFeatureService::isOperationAllowed('events', $viewerTenantId);
        if (!$sourceCheck['allowed']) {
            return $sourceCheck;
        }

        $targetCheck = FederationFeatureService::isOperationAllowed('events', $targetTenantId);
        if (!$targetCheck['allowed']) {
            return $targetCheck;
        }

        // Check partnership
        return self::checkPartnership($viewerTenantId, $targetTenantId, 'events');
    }

    // =========================================================================
    // GROUP OPERATIONS
    // =========================================================================

    /**
     * Check if a user can join a group in another tenant
     */
    public static function canJoinGroup(
        int $userTenantId,
        int $groupTenantId,
        int $groupId
    ): array {
        // Same tenant - no federation check needed
        if ($userTenantId === $groupTenantId) {
            return ['allowed' => true, 'reason' => null];
        }

        // Check system and tenant feature flags
        $sourceCheck = FederationFeatureService::isOperationAllowed('groups', $userTenantId);
        if (!$sourceCheck['allowed']) {
            return $sourceCheck;
        }

        $targetCheck = FederationFeatureService::isOperationAllowed('groups', $groupTenantId);
        if (!$targetCheck['allowed']) {
            return $targetCheck;
        }

        // Check partnership
        $partnershipCheck = self::checkPartnership($userTenantId, $groupTenantId, 'groups');
        if (!$partnershipCheck['allowed']) {
            return $partnershipCheck;
        }

        // Check group-specific federation settings
        $groupCheck = self::checkGroupFederation($groupId);
        if (!$groupCheck['allowed']) {
            return $groupCheck;
        }

        return ['allowed' => true, 'reason' => null];
    }

    // =========================================================================
    // SEARCH OPERATIONS
    // =========================================================================

    /**
     * Perform a federated search and log it
     *
     * @param string $searchType 'members', 'listings', 'events', 'groups'
     * @param array $filters Search filters
     * @param callable $searchCallback Function that performs the actual search
     * @param int|null $actorUserId User performing the search
     * @return array Search results from callback
     */
    public static function performFederatedSearch(
        string $searchType,
        array $filters,
        callable $searchCallback,
        ?int $actorUserId = null
    ): array {
        // Map search type to operation
        $operationMap = [
            'members' => 'profiles',
            'listings' => 'listings',
            'events' => 'events',
            'groups' => 'groups'
        ];

        $operation = $operationMap[$searchType] ?? null;
        if (!$operation) {
            return [];
        }

        // Check if federated search is allowed
        $check = FederationFeatureService::isOperationAllowed($operation);
        if (!$check['allowed']) {
            // Return empty results if federation not allowed
            return [];
        }

        // Perform the search
        $results = $searchCallback($filters);
        $resultsCount = count($results);

        // Log the search
        FederationAuditService::logSearch($searchType, $filters, $resultsCount, $actorUserId);

        return $results;
    }

    // =========================================================================
    // PARTNERSHIP HELPERS
    // =========================================================================

    /**
     * Check if two tenants have an active partnership with the required permission
     */
    private static function checkPartnership(
        int $tenantId1,
        int $tenantId2,
        string $permission
    ): array {
        // Ensure partnership tables exist
        self::ensurePartnershipTableExists();

        try {
            // Check for active partnership with required permission
            $partnership = Database::query("
                SELECT * FROM federation_partnerships
                WHERE status = 'active'
                AND (
                    (tenant_id = ? AND partner_tenant_id = ?)
                    OR (tenant_id = ? AND partner_tenant_id = ?)
                )
            ", [$tenantId1, $tenantId2, $tenantId2, $tenantId1])->fetch(\PDO::FETCH_ASSOC);

            if (!$partnership) {
                return [
                    'allowed' => false,
                    'reason' => 'No active partnership between these timebanks',
                    'level' => 'partnership'
                ];
            }

            // Check permission level
            $permissionColumn = "{$permission}_enabled";
            if (isset($partnership[$permissionColumn]) && !$partnership[$permissionColumn]) {
                return [
                    'allowed' => false,
                    'reason' => "Partnership does not include {$permission} access",
                    'level' => 'partnership_permission'
                ];
            }

            return ['allowed' => true, 'reason' => null];

        } catch (\Exception $e) {
            error_log("FederationGateway::checkPartnership error: " . $e->getMessage());
            return [
                'allowed' => false,
                'reason' => 'Partnership verification failed',
                'level' => 'error'
            ];
        }
    }

    /**
     * Get list of partner tenant IDs with a specific permission
     */
    private static function getPartnerTenants(int $tenantId, string $permission): array
    {
        self::ensurePartnershipTableExists();

        try {
            $permissionColumn = "{$permission}_enabled";

            $results = Database::query("
                SELECT
                    CASE
                        WHEN tenant_id = ? THEN partner_tenant_id
                        ELSE tenant_id
                    END as partner_id
                FROM federation_partnerships
                WHERE status = 'active'
                AND (tenant_id = ? OR partner_tenant_id = ?)
                AND ({$permissionColumn} = 1 OR {$permissionColumn} IS NULL)
            ", [$tenantId, $tenantId, $tenantId])->fetchAll(\PDO::FETCH_COLUMN);

            return $results ?: [];

        } catch (\Exception $e) {
            error_log("FederationGateway::getPartnerTenants error: " . $e->getMessage());
            return [];
        }
    }

    // =========================================================================
    // USER PRIVACY HELPERS
    // =========================================================================

    /**
     * Check user-level privacy settings for federation
     */
    private static function checkUserPrivacy(
        int $targetUserId,
        string $privacyType,
        ?int $viewerUserId = null
    ): array {
        self::ensureUserSettingsTableExists();

        try {
            // Get user's federation privacy settings
            $settings = Database::query("
                SELECT * FROM federation_user_settings
                WHERE user_id = ?
            ", [$targetUserId])->fetch(\PDO::FETCH_ASSOC);

            // If no settings, check if user has opt-in enabled
            if (!$settings) {
                // Default is private (no federation visibility)
                return [
                    'allowed' => false,
                    'reason' => 'User has not enabled cross-tenant visibility',
                    'level' => 'user_privacy'
                ];
            }

            // Check master opt-in
            if (empty($settings['federation_optin'])) {
                return [
                    'allowed' => false,
                    'reason' => 'User has not opted into federation',
                    'level' => 'user_privacy'
                ];
            }

            // Check specific privacy type
            $privacyColumnMap = [
                'profile' => 'profile_visible_federated',
                'messaging' => 'messaging_enabled_federated',
                'transactions' => 'transactions_enabled_federated',
            ];

            $column = $privacyColumnMap[$privacyType] ?? null;
            if ($column && isset($settings[$column]) && !$settings[$column]) {
                return [
                    'allowed' => false,
                    'reason' => "User has disabled cross-tenant {$privacyType}",
                    'level' => 'user_privacy'
                ];
            }

            return ['allowed' => true, 'reason' => null];

        } catch (\Exception $e) {
            error_log("FederationGateway::checkUserPrivacy error: " . $e->getMessage());
            // Fail safe - deny access on error
            return [
                'allowed' => false,
                'reason' => 'Privacy verification failed',
                'level' => 'error'
            ];
        }
    }

    // =========================================================================
    // GROUP FEDERATION HELPERS
    // =========================================================================

    /**
     * Check if a specific group allows federated membership
     */
    private static function checkGroupFederation(int $groupId): array
    {
        try {
            // Check group's federation settings
            $group = Database::query("
                SELECT allow_federated_members FROM `groups`
                WHERE id = ?
            ", [$groupId])->fetch(\PDO::FETCH_ASSOC);

            if (!$group) {
                return [
                    'allowed' => false,
                    'reason' => 'Group not found',
                    'level' => 'group'
                ];
            }

            if (empty($group['allow_federated_members'])) {
                return [
                    'allowed' => false,
                    'reason' => 'Group does not accept members from other timebanks',
                    'level' => 'group'
                ];
            }

            return ['allowed' => true, 'reason' => null];

        } catch (\Exception $e) {
            // Column might not exist yet - that's okay, deny by default
            return [
                'allowed' => false,
                'reason' => 'Group federation settings not configured',
                'level' => 'group'
            ];
        }
    }

    // =========================================================================
    // TABLE CREATION
    // =========================================================================

    /**
     * Ensure partnership table exists
     */
    private static function ensurePartnershipTableExists(): void
    {
        static $checked = false;
        if ($checked) return;

        try {
            Database::query("SELECT 1 FROM federation_partnerships LIMIT 1");
        } catch (\Exception $e) {
            Database::query("
                CREATE TABLE IF NOT EXISTS federation_partnerships (
                    id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
                    tenant_id INT UNSIGNED NOT NULL,
                    partner_tenant_id INT UNSIGNED NOT NULL,

                    -- Partnership status
                    status ENUM('pending', 'active', 'suspended', 'terminated') NOT NULL DEFAULT 'pending',
                    federation_level TINYINT UNSIGNED NOT NULL DEFAULT 1
                        COMMENT '1=Discovery, 2=Social, 3=Economic, 4=Integrated',

                    -- Permission flags (all default OFF)
                    profiles_enabled TINYINT(1) NOT NULL DEFAULT 0,
                    messaging_enabled TINYINT(1) NOT NULL DEFAULT 0,
                    transactions_enabled TINYINT(1) NOT NULL DEFAULT 0,
                    listings_enabled TINYINT(1) NOT NULL DEFAULT 0,
                    events_enabled TINYINT(1) NOT NULL DEFAULT 0,
                    groups_enabled TINYINT(1) NOT NULL DEFAULT 0,

                    -- Request tracking
                    requested_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
                    requested_by INT UNSIGNED NULL,
                    approved_at TIMESTAMP NULL,
                    approved_by INT UNSIGNED NULL,
                    terminated_at TIMESTAMP NULL,
                    terminated_by INT UNSIGNED NULL,
                    termination_reason VARCHAR(500) NULL,

                    -- Timestamps
                    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
                    updated_at TIMESTAMP NULL ON UPDATE CURRENT_TIMESTAMP,

                    -- Constraints
                    UNIQUE KEY unique_partnership (tenant_id, partner_tenant_id),
                    INDEX idx_status (status),
                    INDEX idx_tenant (tenant_id),
                    INDEX idx_partner (partner_tenant_id)
                ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci
            ");
        }

        $checked = true;
    }

    /**
     * Ensure user settings table exists
     */
    private static function ensureUserSettingsTableExists(): void
    {
        static $checked = false;
        if ($checked) return;

        try {
            Database::query("SELECT 1 FROM federation_user_settings LIMIT 1");
        } catch (\Exception $e) {
            Database::query("
                CREATE TABLE IF NOT EXISTS federation_user_settings (
                    user_id INT UNSIGNED PRIMARY KEY,

                    -- Master opt-in (user must explicitly enable federation)
                    federation_optin TINYINT(1) NOT NULL DEFAULT 0
                        COMMENT 'User has explicitly opted into federation',

                    -- Visibility settings (all default OFF)
                    profile_visible_federated TINYINT(1) NOT NULL DEFAULT 0
                        COMMENT 'Profile visible to partner timebanks',
                    messaging_enabled_federated TINYINT(1) NOT NULL DEFAULT 0
                        COMMENT 'Can receive messages from partner timebanks',
                    transactions_enabled_federated TINYINT(1) NOT NULL DEFAULT 0
                        COMMENT 'Can transact with partner timebank members',

                    -- Discovery preferences
                    appear_in_federated_search TINYINT(1) NOT NULL DEFAULT 0
                        COMMENT 'Appear in federated member search',
                    show_skills_federated TINYINT(1) NOT NULL DEFAULT 0
                        COMMENT 'Show skills in federated profile',
                    show_location_federated TINYINT(1) NOT NULL DEFAULT 0
                        COMMENT 'Show location in federated profile',

                    -- Timestamps
                    opted_in_at TIMESTAMP NULL
                        COMMENT 'When user first opted in',
                    updated_at TIMESTAMP NULL ON UPDATE CURRENT_TIMESTAMP,

                    INDEX idx_optin (federation_optin),
                    INDEX idx_searchable (appear_in_federated_search)
                ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci
            ");
        }

        $checked = true;
    }

    // =========================================================================
    // UTILITY METHODS
    // =========================================================================

    /**
     * Get a summary of federation status for display
     */
    public static function getStatusSummary(?int $tenantId = null): array
    {
        $tenantId = $tenantId ?? TenantContext::getId();

        return [
            'globally_enabled' => FederationFeatureService::isGloballyEnabled(),
            'tenant_enabled' => FederationFeatureService::isTenantFederationEnabled($tenantId),
            'tenant_whitelisted' => FederationFeatureService::isTenantWhitelisted($tenantId),
            'features' => [
                'profiles' => FederationFeatureService::isOperationAllowed('profiles', $tenantId),
                'messaging' => FederationFeatureService::isOperationAllowed('messaging', $tenantId),
                'transactions' => FederationFeatureService::isOperationAllowed('transactions', $tenantId),
                'listings' => FederationFeatureService::isOperationAllowed('listings', $tenantId),
                'events' => FederationFeatureService::isOperationAllowed('events', $tenantId),
                'groups' => FederationFeatureService::isOperationAllowed('groups', $tenantId),
            ]
        ];
    }

    /**
     * Quick check if ANY federation is possible
     * Use this for UI to show/hide federation features
     */
    public static function isFederationAvailable(?int $tenantId = null): bool
    {
        return FederationFeatureService::isTenantFederationEnabled($tenantId);
    }

    /**
     * Check if two tenants have an active partnership (any permission)
     */
    public static function hasActivePartnership(int $tenantId1, int $tenantId2): bool
    {
        self::ensurePartnershipTableExists();

        try {
            $partnership = Database::query("
                SELECT id FROM federation_partnerships
                WHERE status = 'active'
                AND (
                    (tenant_id = ? AND partner_tenant_id = ?)
                    OR (tenant_id = ? AND partner_tenant_id = ?)
                )
                LIMIT 1
            ", [$tenantId1, $tenantId2, $tenantId2, $tenantId1])->fetch(\PDO::FETCH_ASSOC);

            return $partnership !== false;
        } catch (\Exception $e) {
            error_log("FederationGateway::hasActivePartnership error: " . $e->getMessage());
            return false;
        }
    }
}
