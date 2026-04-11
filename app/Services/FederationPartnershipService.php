<?php
// Copyright © 2024–2026 Jasper Ford
// SPDX-License-Identifier: AGPL-3.0-or-later
// Author: Jasper Ford
// See NOTICE file for attribution and acknowledgements.

declare(strict_types=1);

namespace App\Services;

use App\Core\TenantContext;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;

/**
 * FederationPartnershipService — Manages partnerships between tenants for federation.
 *
 * Handles partnership requests, approvals, counter-proposals, and permission management.
 */
class FederationPartnershipService
{
    /** Partnership level constants */
    public const LEVEL_DISCOVERY = 1;
    public const LEVEL_SOCIAL = 2;
    public const LEVEL_ECONOMIC = 3;
    public const LEVEL_INTEGRATED = 4;

    public function __construct(
        private readonly FederationFeatureService $featureService,
        private readonly FederationAuditService $auditService,
    ) {}

    /**
     * Request a partnership with another tenant.
     */
    public static function requestPartnership(
        int $requestingTenantId,
        int $targetTenantId,
        int $requestedBy,
        int $federationLevel = self::LEVEL_DISCOVERY,
        ?string $notes = null
    ): array {
        $check = app(FederationFeatureService::class)->isOperationAllowed('profiles', $requestingTenantId);
        if (!$check['allowed']) {
            return ['success' => false, 'error' => $check['reason']];
        }

        $targetCheck = app(FederationFeatureService::class)->isTenantFeatureEnabled(
            FederationFeatureService::TENANT_APPEAR_IN_DIRECTORY,
            $targetTenantId
        );
        if (!$targetCheck) {
            return ['success' => false, 'error' => 'Target tenant is not accepting federation requests'];
        }

        $existing = self::getPartnership($requestingTenantId, $targetTenantId);
        if ($existing) {
            if ($existing['status'] === 'active') {
                return ['success' => false, 'error' => 'Partnership already exists'];
            }
            if ($existing['status'] === 'pending') {
                return ['success' => false, 'error' => 'Partnership request already pending'];
            }
            if ($existing['status'] === 'terminated') {
                return ['success' => false, 'error' => 'Terminated partnerships cannot be re-requested'];
            }
            // Allow re-requesting after a previous rejection
            if ($existing['status'] === 'rejected') {
                DB::table('federation_partnerships')->where('id', $existing['id'])->delete();
            }
        }

        try {
            DB::statement(
                "INSERT INTO federation_partnerships (
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
                    termination_reason = NULL",
                [$requestingTenantId, $targetTenantId, $federationLevel, $requestedBy, $notes]
            );

            FederationAuditService::log(
                'partnership_requested',
                $requestingTenantId, $targetTenantId, $requestedBy,
                ['federation_level' => $federationLevel, 'notes' => $notes]
            );

            // Email the target tenant's admins about the incoming partnership request
            try {
                $requestingTenant = DB::selectOne(
                    "SELECT name FROM tenants WHERE id = ?",
                    [$requestingTenantId]
                );
                $requestingTenantName = $requestingTenant->name ?? 'A partner community';

                FederationEmailService::sendPartnershipRequestNotification(
                    $targetTenantId,
                    $requestingTenantId,
                    $requestingTenantName,
                    $federationLevel,
                    $notes
                );
            } catch (\Exception $emailEx) {
                Log::warning('FederationPartnershipService::requestPartnership email notification failed', [
                    'error' => $emailEx->getMessage(),
                ]);
            }

            return ['success' => true, 'message' => __('svc_notifications_2.federation.partnership_request_sent')];
        } catch (\Exception $e) {
            Log::error('FederationPartnershipService::requestPartnership error: ' . $e->getMessage());
            return ['success' => false, 'error' => 'Failed to create partnership request'];
        }
    }

    /**
     * Approve a partnership request.
     */
    public static function approvePartnership(int $partnershipId, int $approvedBy, array $permissions = []): array
    {
        $partnership = self::getPartnershipById($partnershipId);
        if (!$partnership) {
            return ['success' => false, 'error' => 'Partnership not found'];
        }
        if ($partnership['status'] !== 'pending') {
            return ['success' => false, 'error' => 'Partnership is not pending approval'];
        }

        // Only the receiving tenant can approve
        $tenantId = TenantContext::getId();
        if ((int) $partnership['partner_tenant_id'] !== $tenantId) {
            return ['success' => false, 'error' => 'Only the receiving tenant can approve a partnership request'];
        }

        $defaultPermissions = self::getDefaultPermissions($partnership['federation_level']);
        $permissions = array_merge($defaultPermissions, $permissions);

        try {
            DB::table('federation_partnerships')->where('id', $partnershipId)->update([
                'status' => 'active',
                'approved_at' => now(),
                'approved_by' => $approvedBy,
                'profiles_enabled' => $permissions['profiles'] ? 1 : 0,
                'messaging_enabled' => $permissions['messaging'] ? 1 : 0,
                'transactions_enabled' => $permissions['transactions'] ? 1 : 0,
                'listings_enabled' => $permissions['listings'] ? 1 : 0,
                'events_enabled' => $permissions['events'] ? 1 : 0,
                'groups_enabled' => $permissions['groups'] ? 1 : 0,
                'updated_at' => now(),
            ]);

            FederationAuditService::log(
                'partnership_approved',
                $partnership['partner_tenant_id'], $partnership['tenant_id'], $approvedBy,
                ['permissions' => $permissions]
            );

            return ['success' => true, 'message' => __('svc_notifications_2.federation.partnership_approved')];
        } catch (\Exception $e) {
            Log::error('FederationPartnershipService::approvePartnership error: ' . $e->getMessage());
            return ['success' => false, 'error' => 'Failed to approve partnership'];
        }
    }

    /**
     * Counter-propose a partnership request with different terms.
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

        $tenantId = TenantContext::getId();
        if ((int) $partnership['tenant_id'] !== $tenantId && (int) $partnership['partner_tenant_id'] !== $tenantId) {
            return ['success' => false, 'error' => 'Not authorized to counter-propose on this partnership'];
        }

        // Only the receiving party (partner_tenant_id) can counter-propose.
        // The original requester (tenant_id) should reject/approve instead.
        if ((int) $partnership['tenant_id'] === $tenantId) {
            return ['success' => false, 'error' => 'The original requester cannot counter-propose their own request'];
        }

        if ($partnership['status'] !== 'pending') {
            return ['success' => false, 'error' => 'Partnership is not pending'];
        }

        try {
            DB::table('federation_partnerships')->where('id', $partnershipId)->update([
                'federation_level' => $newLevel,
                'counter_proposed_at' => now(),
                'counter_proposed_by' => $proposedBy,
                'counter_proposal_message' => $message,
                'counter_proposed_level' => $newLevel,
                'counter_proposed_permissions' => json_encode($proposedPermissions),
                'updated_at' => now(),
            ]);

            FederationAuditService::log(
                'partnership_counter_proposed',
                $partnership['partner_tenant_id'], $partnership['tenant_id'], $proposedBy,
                [
                    'original_level' => $partnership['federation_level'],
                    'proposed_level' => $newLevel,
                    'proposed_permissions' => $proposedPermissions,
                    'message' => $message,
                ]
            );

            return ['success' => true, 'message' => __('svc_notifications_2.federation.counter_proposal_sent')];
        } catch (\Exception $e) {
            Log::error('FederationPartnershipService::counterPropose error: ' . $e->getMessage());
            return ['success' => false, 'error' => 'Failed to send counter-proposal'];
        }
    }

    /**
     * Accept a counter-proposal.
     */
    public static function acceptCounterProposal(int $partnershipId, int $acceptedBy): array
    {
        $partnership = self::getPartnershipById($partnershipId);
        if (!$partnership) {
            return ['success' => false, 'error' => 'Partnership not found'];
        }

        $tenantId = TenantContext::getId();
        if ((int) $partnership['tenant_id'] !== $tenantId) {
            return ['success' => false, 'error' => 'Only the original requester can accept a counter-proposal'];
        }

        if ($partnership['status'] !== 'pending') {
            return ['success' => false, 'error' => 'Partnership is not pending'];
        }
        if (empty($partnership['counter_proposed_at'])) {
            return ['success' => false, 'error' => 'No counter-proposal to accept'];
        }

        $proposedPermissions = [];
        if (!empty($partnership['counter_proposed_permissions'])) {
            $proposedPermissions = json_decode($partnership['counter_proposed_permissions'], true) ?: [];
        }
        $defaultPermissions = self::getDefaultPermissions($partnership['federation_level']);
        $permissions = array_merge($defaultPermissions, $proposedPermissions);

        try {
            DB::table('federation_partnerships')->where('id', $partnershipId)->update([
                'status' => 'active',
                'approved_at' => now(),
                'approved_by' => $acceptedBy,
                'profiles_enabled' => $permissions['profiles'] ? 1 : 0,
                'messaging_enabled' => $permissions['messaging'] ? 1 : 0,
                'transactions_enabled' => $permissions['transactions'] ? 1 : 0,
                'listings_enabled' => $permissions['listings'] ? 1 : 0,
                'events_enabled' => $permissions['events'] ? 1 : 0,
                'groups_enabled' => $permissions['groups'] ? 1 : 0,
                'updated_at' => now(),
            ]);

            FederationAuditService::log(
                'partnership_counter_accepted',
                $partnership['tenant_id'], $partnership['partner_tenant_id'], $acceptedBy,
                ['accepted_level' => $partnership['federation_level']]
            );

            return ['success' => true, 'message' => __('svc_notifications_2.federation.counter_proposal_accepted')];
        } catch (\Exception $e) {
            Log::error('FederationPartnershipService::acceptCounterProposal error: ' . $e->getMessage());
            return ['success' => false, 'error' => 'Failed to accept counter-proposal'];
        }
    }

    /**
     * Reject a partnership request.
     */
    public static function rejectPartnership(int $partnershipId, int $rejectedBy, ?string $reason = null): array
    {
        $partnership = self::getPartnershipById($partnershipId);
        if (!$partnership) {
            return ['success' => false, 'error' => 'Partnership not found'];
        }
        if ($partnership['status'] !== 'pending') {
            return ['success' => false, 'error' => 'Partnership is not pending approval'];
        }

        // Only the receiving tenant can reject
        $tenantId = TenantContext::getId();
        if ((int) $partnership['partner_tenant_id'] !== $tenantId) {
            return ['success' => false, 'error' => 'Only the receiving tenant can reject a partnership request'];
        }

        try {
            DB::table('federation_partnerships')->where('id', $partnershipId)->update([
                'status' => 'rejected',
                'terminated_at' => now(),
                'terminated_by' => $rejectedBy,
                'termination_reason' => $reason ?? 'Request rejected',
                'updated_at' => now(),
            ]);

            FederationAuditService::log(
                'partnership_rejected',
                $partnership['partner_tenant_id'], $partnership['tenant_id'], $rejectedBy,
                ['reason' => $reason]
            );

            return ['success' => true, 'message' => __('svc_notifications_2.federation.partnership_request_rejected')];
        } catch (\Exception $e) {
            Log::error('FederationPartnershipService::rejectPartnership error: ' . $e->getMessage());
            return ['success' => false, 'error' => 'Failed to reject partnership'];
        }
    }

    /**
     * Suspend an active partnership.
     */
    public static function suspendPartnership(int $partnershipId, int $suspendedBy, ?string $reason = null): array
    {
        $partnership = self::getPartnershipById($partnershipId);
        if (!$partnership) {
            return ['success' => false, 'error' => 'Partnership not found'];
        }
        if ($partnership['status'] !== 'active') {
            return ['success' => false, 'error' => 'Can only suspend active partnerships'];
        }

        // Either party can suspend
        $tenantId = TenantContext::getId();
        if ((int) $partnership['tenant_id'] !== $tenantId && (int) $partnership['partner_tenant_id'] !== $tenantId) {
            return ['success' => false, 'error' => 'Only a partner tenant can suspend this partnership'];
        }

        try {
            DB::table('federation_partnerships')->where('id', $partnershipId)->update([
                'status' => 'suspended',
                'termination_reason' => $reason,
                'updated_at' => now(),
            ]);

            FederationAuditService::log(
                'partnership_suspended',
                $partnership['tenant_id'], $partnership['partner_tenant_id'], $suspendedBy,
                ['reason' => $reason],
                FederationAuditService::LEVEL_WARNING
            );

            return ['success' => true, 'message' => __('svc_notifications_2.federation.partnership_suspended')];
        } catch (\Exception $e) {
            Log::error('FederationPartnershipService::suspendPartnership error: ' . $e->getMessage());
            return ['success' => false, 'error' => 'Failed to suspend partnership'];
        }
    }

    /**
     * Reactivate a suspended partnership.
     */
    public static function reactivatePartnership(int $partnershipId, int $reactivatedBy): array
    {
        $partnership = self::getPartnershipById($partnershipId);
        if (!$partnership) {
            return ['success' => false, 'error' => 'Partnership not found'];
        }
        if ($partnership['status'] !== 'suspended') {
            return ['success' => false, 'error' => 'Can only reactivate suspended partnerships'];
        }

        $tenantId = TenantContext::getId();
        if ((int) $partnership['tenant_id'] !== $tenantId && (int) $partnership['partner_tenant_id'] !== $tenantId) {
            return ['success' => false, 'error' => 'Only a partner tenant can reactivate this partnership'];
        }

        try {
            DB::table('federation_partnerships')->where('id', $partnershipId)->update([
                'status' => 'active',
                'termination_reason' => null,
                'updated_at' => now(),
            ]);

            FederationAuditService::log(
                'partnership_reactivated',
                $partnership['tenant_id'], $partnership['partner_tenant_id'], $reactivatedBy,
                []
            );

            return ['success' => true, 'message' => __('svc_notifications_2.federation.partnership_reactivated')];
        } catch (\Exception $e) {
            Log::error('FederationPartnershipService::reactivatePartnership error: ' . $e->getMessage());
            return ['success' => false, 'error' => 'Failed to reactivate partnership'];
        }
    }

    /**
     * Terminate a partnership permanently.
     */
    public static function terminatePartnership(int $partnershipId, int $terminatedBy, ?string $reason = null): array
    {
        $partnership = self::getPartnershipById($partnershipId);
        if (!$partnership) {
            return ['success' => false, 'error' => 'Partnership not found'];
        }

        $tenantId = TenantContext::getId();
        if ((int) $partnership['tenant_id'] !== $tenantId && (int) $partnership['partner_tenant_id'] !== $tenantId) {
            return ['success' => false, 'error' => 'Only a partner tenant can terminate this partnership'];
        }

        try {
            DB::table('federation_partnerships')->where('id', $partnershipId)->update([
                'status' => 'terminated',
                'terminated_at' => now(),
                'terminated_by' => $terminatedBy,
                'termination_reason' => $reason,
                'updated_at' => now(),
            ]);

            FederationAuditService::log(
                'partnership_terminated',
                $partnership['tenant_id'], $partnership['partner_tenant_id'], $terminatedBy,
                ['reason' => $reason],
                FederationAuditService::LEVEL_WARNING
            );

            return ['success' => true, 'message' => __('svc_notifications_2.federation.partnership_terminated')];
        } catch (\Exception $e) {
            Log::error('FederationPartnershipService::terminatePartnership error: ' . $e->getMessage());
            return ['success' => false, 'error' => 'Failed to terminate partnership'];
        }
    }

    /**
     * Update partnership permissions.
     */
    public static function updatePermissions(int $partnershipId, int $updatedBy, array $permissions): array
    {
        $partnership = self::getPartnershipById($partnershipId);
        if (!$partnership) {
            return ['success' => false, 'error' => 'Partnership not found'];
        }

        $tenantId = TenantContext::getId();
        if ((int) $partnership['tenant_id'] !== $tenantId && (int) $partnership['partner_tenant_id'] !== $tenantId) {
            return ['success' => false, 'error' => 'Only a partner tenant can update permissions on this partnership'];
        }

        try {
            DB::table('federation_partnerships')->where('id', $partnershipId)->update([
                'profiles_enabled' => isset($permissions['profiles']) ? ($permissions['profiles'] ? 1 : 0) : $partnership['profiles_enabled'],
                'messaging_enabled' => isset($permissions['messaging']) ? ($permissions['messaging'] ? 1 : 0) : $partnership['messaging_enabled'],
                'transactions_enabled' => isset($permissions['transactions']) ? ($permissions['transactions'] ? 1 : 0) : $partnership['transactions_enabled'],
                'listings_enabled' => isset($permissions['listings']) ? ($permissions['listings'] ? 1 : 0) : $partnership['listings_enabled'],
                'events_enabled' => isset($permissions['events']) ? ($permissions['events'] ? 1 : 0) : $partnership['events_enabled'],
                'groups_enabled' => isset($permissions['groups']) ? ($permissions['groups'] ? 1 : 0) : $partnership['groups_enabled'],
                'updated_at' => now(),
            ]);

            FederationAuditService::log(
                'partnership_permissions_updated',
                $partnership['tenant_id'], $partnership['partner_tenant_id'], $updatedBy,
                ['permissions' => $permissions]
            );

            return ['success' => true, 'message' => __('svc_notifications_2.federation.permissions_updated')];
        } catch (\Exception $e) {
            Log::error('FederationPartnershipService::updatePermissions error: ' . $e->getMessage());
            return ['success' => false, 'error' => 'Failed to update permissions'];
        }
    }

    // =========================================================================
    // QUERY METHODS
    // =========================================================================

    /**
     * Get partnership by ID.
     */
    public static function getPartnershipById(int $id, ?int $tenantId = null): ?array
    {
        try {
            $query = DB::table('federation_partnerships as p')
                ->leftJoin('tenants as t1', 'p.tenant_id', '=', 't1.id')
                ->leftJoin('tenants as t2', 'p.partner_tenant_id', '=', 't2.id')
                ->select(
                    'p.*',
                    't1.name as tenant_name', 't1.domain as tenant_domain',
                    't2.name as partner_name', 't2.domain as partner_domain'
                )
                ->where('p.id', $id);

            if ($tenantId !== null) {
                $query->where(function ($q) use ($tenantId) {
                    $q->where('p.tenant_id', $tenantId)
                      ->orWhere('p.partner_tenant_id', $tenantId);
                });
            }

            $result = $query->first();

            return $result ? (array) $result : null;
        } catch (\Exception $e) {
            Log::error('FederationPartnershipService::getPartnershipById error: ' . $e->getMessage());
            return null;
        }
    }

    /**
     * Get partnership between two tenants.
     */
    public static function getPartnership(int $tenantId1, int $tenantId2): ?array
    {
        try {
            $result = DB::table('federation_partnerships')
                ->where(function ($q) use ($tenantId1, $tenantId2) {
                    $q->where('tenant_id', $tenantId1)->where('partner_tenant_id', $tenantId2);
                })
                ->orWhere(function ($q) use ($tenantId1, $tenantId2) {
                    $q->where('tenant_id', $tenantId2)->where('partner_tenant_id', $tenantId1);
                })
                ->first();

            return $result ? (array) $result : null;
        } catch (\Exception $e) {
            Log::error('FederationPartnershipService::getPartnership error: ' . $e->getMessage());
            return null;
        }
    }

    /**
     * Get all partnerships for a tenant.
     */
    public static function getTenantPartnerships(int $tenantId, ?string $status = null): array
    {
        try {
            $query = DB::table('federation_partnerships as p')
                ->leftJoin('tenants as t1', 'p.tenant_id', '=', 't1.id')
                ->leftJoin('tenants as t2', 'p.partner_tenant_id', '=', 't2.id')
                ->select('p.*')
                ->selectRaw('CASE WHEN p.tenant_id = ? THEN t2.name ELSE t1.name END as partner_name', [$tenantId])
                ->selectRaw('CASE WHEN p.tenant_id = ? THEN t2.domain ELSE t1.domain END as partner_domain', [$tenantId])
                ->selectRaw('CASE WHEN p.tenant_id = ? THEN p.partner_tenant_id ELSE p.tenant_id END as partner_id', [$tenantId])
                ->where(function ($q) use ($tenantId) {
                    $q->where('p.tenant_id', $tenantId)
                      ->orWhere('p.partner_tenant_id', $tenantId);
                });

            if ($status) {
                $query->where('p.status', $status);
            }

            return $query->orderByDesc('p.created_at')
                ->get()
                ->map(fn ($row) => (array) $row)
                ->all();
        } catch (\Exception $e) {
            Log::error('FederationPartnershipService::getTenantPartnerships error: ' . $e->getMessage());
            return [];
        }
    }

    /**
     * Get pending requests for a tenant (incoming).
     */
    public static function getPendingRequests(int $tenantId): array
    {
        try {
            return DB::table('federation_partnerships as p')
                ->join('tenants as t', 'p.tenant_id', '=', 't.id')
                ->select('p.*', 't.name as requester_name', 't.domain as requester_domain')
                ->where('p.partner_tenant_id', $tenantId)
                ->where('p.status', 'pending')
                ->whereNull('p.counter_proposed_at')
                ->orderByDesc('p.requested_at')
                ->get()
                ->map(fn ($row) => (array) $row)
                ->all();
        } catch (\Exception $e) {
            Log::error('FederationPartnershipService::getPendingRequests error: ' . $e->getMessage());
            return [];
        }
    }

    /**
     * Get outgoing requests that have counter-proposals awaiting response.
     */
    public static function getCounterProposals(int $tenantId): array
    {
        try {
            return DB::table('federation_partnerships as p')
                ->join('tenants as t', 'p.partner_tenant_id', '=', 't.id')
                ->leftJoin('users as u', 'p.counter_proposed_by', '=', 'u.id')
                ->select(
                    'p.*',
                    't.name as partner_name', 't.domain as partner_domain',
                    'u.first_name as proposer_first_name', 'u.last_name as proposer_last_name'
                )
                ->where('p.tenant_id', $tenantId)
                ->where('p.status', 'pending')
                ->whereNotNull('p.counter_proposed_at')
                ->orderByDesc('p.counter_proposed_at')
                ->get()
                ->map(fn ($row) => (array) $row)
                ->all();
        } catch (\Exception $e) {
            Log::error('FederationPartnershipService::getCounterProposals error: ' . $e->getMessage());
            return [];
        }
    }

    /**
     * Get outgoing pending requests.
     */
    public static function getOutgoingRequests(int $tenantId): array
    {
        try {
            return DB::table('federation_partnerships as p')
                ->join('tenants as t', 'p.partner_tenant_id', '=', 't.id')
                ->select('p.*', 't.name as partner_name', 't.domain as partner_domain')
                ->where('p.tenant_id', $tenantId)
                ->where('p.status', 'pending')
                ->whereNull('p.counter_proposed_at')
                ->orderByDesc('p.requested_at')
                ->get()
                ->map(fn ($row) => (array) $row)
                ->all();
        } catch (\Exception $e) {
            Log::error('FederationPartnershipService::getOutgoingRequests error: ' . $e->getMessage());
            return [];
        }
    }

    /**
     * Get all partnerships (for super admin).
     */
    public static function getAllPartnerships(?string $status = null, int $limit = 100): array
    {
        try {
            $query = DB::table('federation_partnerships as p')
                ->leftJoin('tenants as t1', 'p.tenant_id', '=', 't1.id')
                ->leftJoin('tenants as t2', 'p.partner_tenant_id', '=', 't2.id')
                ->select(
                    'p.*',
                    't1.name as tenant_name', 't1.domain as tenant_domain',
                    't2.name as partner_name', 't2.domain as partner_domain'
                );

            if ($status) {
                $query->where('p.status', $status);
            }

            return $query->orderByDesc('p.created_at')
                ->limit($limit)
                ->get()
                ->map(fn ($row) => (array) $row)
                ->all();
        } catch (\Exception $e) {
            Log::error('FederationPartnershipService::getAllPartnerships error: ' . $e->getMessage());
            return [];
        }
    }

    /**
     * Get partnership statistics.
     */
    public static function getStats(): array
    {
        try {
            $stats = DB::table('federation_partnerships')
                ->selectRaw("
                    COUNT(*) as total,
                    SUM(CASE WHEN status = 'active' THEN 1 ELSE 0 END) as active,
                    SUM(CASE WHEN status = 'pending' THEN 1 ELSE 0 END) as pending,
                    SUM(CASE WHEN status = 'suspended' THEN 1 ELSE 0 END) as suspended,
                    SUM(CASE WHEN status = 'terminated' THEN 1 ELSE 0 END) as `terminated`
                ")
                ->first();

            $stats = $stats ? (array) $stats : [
                'total' => 0, 'active' => 0, 'pending' => 0,
                'suspended' => 0, 'terminated' => 0,
            ];

            $stats['recent'] = DB::table('federation_partnerships as p')
                ->leftJoin('tenants as t1', 'p.tenant_id', '=', 't1.id')
                ->leftJoin('tenants as t2', 'p.partner_tenant_id', '=', 't2.id')
                ->select('p.*', 't1.name as tenant_name', 't2.name as partner_name')
                ->orderByDesc('p.updated_at')
                ->limit(5)
                ->get()
                ->map(fn ($row) => (array) $row)
                ->all();

            return $stats;
        } catch (\Exception $e) {
            Log::error('FederationPartnershipService::getStats error: ' . $e->getMessage());
            return ['total' => 0, 'active' => 0, 'pending' => 0, 'suspended' => 0, 'terminated' => 0, 'recent' => []];
        }
    }

    // =========================================================================
    // HELPERS
    // =========================================================================

    /**
     * Get default permissions for a federation level.
     */
    public static function getDefaultPermissions(int $level): array
    {
        return match ($level) {
            self::LEVEL_INTEGRATED => [
                'profiles' => true, 'messaging' => true, 'transactions' => true,
                'listings' => true, 'events' => true, 'groups' => true,
            ],
            self::LEVEL_ECONOMIC => [
                'profiles' => true, 'messaging' => true, 'transactions' => true,
                'listings' => true, 'events' => true, 'groups' => false,
            ],
            self::LEVEL_SOCIAL => [
                'profiles' => true, 'messaging' => true, 'transactions' => false,
                'listings' => true, 'events' => true, 'groups' => false,
            ],
            default => [
                'profiles' => true, 'messaging' => false, 'transactions' => false,
                'listings' => false, 'events' => false, 'groups' => false,
            ],
        };
    }

    /**
     * Get human-readable level name.
     */
    public static function getLevelName(int $level): string
    {
        return match ($level) {
            self::LEVEL_DISCOVERY => 'Discovery',
            self::LEVEL_SOCIAL => 'Social',
            self::LEVEL_ECONOMIC => 'Economic',
            self::LEVEL_INTEGRATED => 'Integrated',
            default => 'Unknown',
        };
    }

    /**
     * Get level description.
     */
    public static function getLevelDescription(int $level): string
    {
        return match ($level) {
            self::LEVEL_DISCOVERY => 'Basic visibility - can see tenant exists and view basic profiles',
            self::LEVEL_SOCIAL => 'Social features - can message and view listings/events',
            self::LEVEL_ECONOMIC => 'Full trading - can exchange time credits',
            self::LEVEL_INTEGRATED => 'Full integration - all features including groups',
            default => '',
        };
    }
}
