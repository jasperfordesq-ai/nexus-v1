<?php
// Copyright © 2024–2026 Jasper Ford
// SPDX-License-Identifier: AGPL-3.0-or-later
// Author: Jasper Ford
// See NOTICE file for attribution and acknowledgements.

namespace App\Services;

use App\Core\TenantContext;
use App\Models\AccountRelationship;
use App\Models\Notification;
use App\Models\User;
use Illuminate\Database\Eloquent\Builder;

/**
 * SubAccountService — Laravel DI-based service for family/guardian accounts.
 *
 * Manages parent-child account relationships with permission controls.
 * All queries are tenant-scoped automatically via the HasTenantScope trait.
 */
class SubAccountService
{
    public const RELATIONSHIP_TYPES = ['family', 'guardian', 'carer', 'organization'];

    public const DEFAULT_PERMISSIONS = [
        'can_view_activity'   => true,
        'can_manage_listings' => false,
        'can_transact'        => false,
        'can_view_messages'   => false,
    ];

    /** Maximum number of child accounts a parent can have */
    public const MAX_CHILDREN = 20;

    private array $errors = [];

    public function __construct(
        private readonly AccountRelationship $relationship,
        private readonly MemberActivityService $activityService,
    ) {}

    /**
     * Get validation errors from the last operation.
     */
    public function getErrors(): array
    {
        return $this->errors;
    }

    /**
     * Get child accounts linked to a parent user.
     */
    public function getChildren(int $parentUserId): array
    {
        return $this->relationship->newQuery()
            ->with('childUser:id,first_name,last_name,email,avatar_url')
            ->where('parent_user_id', $parentUserId)
            ->where('status', 'active')
            ->orderByDesc('created_at')
            ->get()
            ->map(function (AccountRelationship $rel) {
                $data = $rel->toArray();
                if ($rel->childUser) {
                    $data['first_name'] = $rel->childUser->first_name;
                    $data['last_name'] = $rel->childUser->last_name;
                    $data['email'] = $rel->childUser->email;
                    $data['avatar_url'] = $rel->childUser->avatar_url;
                }
                return $data;
            })
            ->all();
    }

    /**
     * Get child accounts managed by a parent user (with relationship details).
     */
    public function getChildAccounts(int $parentUserId): array
    {
        return $this->relationship->newQuery()
            ->join('users as u', 'account_relationships.child_user_id', '=', 'u.id')
            ->where('account_relationships.parent_user_id', $parentUserId)
            ->whereIn('account_relationships.status', ['active', 'pending'])
            ->select(
                'account_relationships.id as relationship_id',
                'account_relationships.relationship_type',
                'account_relationships.permissions',
                'account_relationships.status',
                'account_relationships.approved_at',
                'account_relationships.created_at',
                'u.id as user_id',
                'u.first_name',
                'u.last_name',
                'u.avatar_url',
                'u.email'
            )
            ->orderByDesc('account_relationships.created_at')
            ->get()
            ->map(fn ($r) => $r->toArray())
            ->all();
    }

    /**
     * Get parent accounts that manage this user.
     */
    public function getParentAccounts(int $childUserId): array
    {
        return $this->relationship->newQuery()
            ->join('users as u', 'account_relationships.parent_user_id', '=', 'u.id')
            ->where('account_relationships.child_user_id', $childUserId)
            ->whereIn('account_relationships.status', ['active', 'pending'])
            ->select(
                'account_relationships.id as relationship_id',
                'account_relationships.relationship_type',
                'account_relationships.permissions',
                'account_relationships.status',
                'account_relationships.approved_at',
                'account_relationships.created_at',
                'u.id as user_id',
                'u.first_name',
                'u.last_name',
                'u.avatar_url',
                'u.email'
            )
            ->orderByDesc('account_relationships.created_at')
            ->get()
            ->map(fn ($r) => $r->toArray())
            ->all();
    }

    /**
     * Request a parent-child relationship.
     *
     * @return int|null Relationship ID or null on failure.
     */
    public function requestRelationship(int $parentUserId, int $childUserId, string $type = 'family', array $permissions = []): ?int
    {
        $this->errors = [];

        if ($parentUserId === $childUserId) {
            $this->errors[] = ['code' => 'SELF_RELATIONSHIP', 'message' => 'Cannot create a relationship with yourself'];
            return null;
        }

        if (! in_array($type, self::RELATIONSHIP_TYPES, true)) {
            $this->errors[] = ['code' => 'INVALID_TYPE', 'message' => 'Invalid relationship type', 'field' => 'relationship_type'];
            return null;
        }

        // Verify both users exist in same tenant
        $parent = User::query()->where('id', $parentUserId)->first();
        $child = User::query()->where('id', $childUserId)->first();

        if (! $parent || ! $child) {
            $this->errors[] = ['code' => 'NOT_FOUND', 'message' => 'User not found'];
            return null;
        }

        // Check for existing relationship
        $existing = $this->relationship->newQuery()
            ->where('parent_user_id', $parentUserId)
            ->where('child_user_id', $childUserId)
            ->first();

        if ($existing) {
            if ($existing->status === 'active') {
                $this->errors[] = ['code' => 'ALREADY_EXISTS', 'message' => 'Relationship already exists'];
                return $existing->id;
            }

            if ($existing->status === 'pending') {
                $this->errors[] = ['code' => 'PENDING', 'message' => 'Relationship request is already pending'];
                return $existing->id;
            }

            // If revoked, allow re-request
            $existing->update([
                'status'            => 'pending',
                'relationship_type' => $type,
                'permissions'       => array_merge(self::DEFAULT_PERMISSIONS, $permissions),
                'approved_at'       => null,
            ]);

            return $existing->id;
        }

        // Prevent circular: child cannot also be parent of the requester
        $circular = $this->relationship->newQuery()
            ->where('parent_user_id', $childUserId)
            ->where('child_user_id', $parentUserId)
            ->whereIn('status', ['active', 'pending'])
            ->exists();

        if ($circular) {
            $this->errors[] = ['code' => 'CIRCULAR', 'message' => 'This user already manages your account'];
            return null;
        }

        // Prevent infinite nesting: a child account cannot also be a parent of other accounts
        $childIsParent = $this->relationship->newQuery()
            ->where('parent_user_id', $childUserId)
            ->whereIn('status', ['active', 'pending'])
            ->exists();

        if ($childIsParent) {
            $this->errors[] = ['code' => 'NESTING_NOT_ALLOWED', 'message' => 'This user already manages other accounts and cannot be added as a child'];
            return null;
        }

        // Prevent a user who is already a child from becoming a parent
        $parentIsChild = $this->relationship->newQuery()
            ->where('child_user_id', $parentUserId)
            ->whereIn('status', ['active', 'pending'])
            ->exists();

        if ($parentIsChild) {
            $this->errors[] = ['code' => 'NESTING_NOT_ALLOWED', 'message' => 'You are a managed account and cannot manage other accounts'];
            return null;
        }

        // Enforce maximum children limit
        $currentChildCount = $this->relationship->newQuery()
            ->where('parent_user_id', $parentUserId)
            ->whereIn('status', ['active', 'pending'])
            ->count();

        if ($currentChildCount >= self::MAX_CHILDREN) {
            $this->errors[] = ['code' => 'LIMIT_REACHED', 'message' => 'Maximum number of sub-accounts (' . self::MAX_CHILDREN . ') reached'];
            return null;
        }

        $mergedPermissions = array_merge(self::DEFAULT_PERMISSIONS, $permissions);

        $rel = $this->relationship->newInstance([
            'tenant_id'         => TenantContext::getId(),
            'parent_user_id'    => $parentUserId,
            'child_user_id'     => $childUserId,
            'relationship_type' => $type,
            'permissions'       => $mergedPermissions,
            'status'            => 'pending',
        ]);
        $rel->save();

        // Notify the child user
        try {
            $parentName = $parent->first_name . ' ' . $parent->last_name;

            Notification::create([
                'tenant_id'  => TenantContext::getId(),
                'user_id'    => $childUserId,
                'type'       => 'account',
                'message'    => __('svc_notifications.sub_account.management_request', ['name' => $parentName, 'type' => $type]),
                'link'       => '/settings',
                'is_read'    => false,
                'created_at' => now(),
            ]);
        } catch (\Exception $e) {
            // Non-critical
        }

        return $rel->id;
    }

    /**
     * Approve a pending relationship request.
     */
    public function approve(int $relationshipId, int $childUserId): bool
    {
        return $this->relationship->newQuery()
            ->where('id', $relationshipId)
            ->where('child_user_id', $childUserId)
            ->where('status', 'pending')
            ->update([
                'status'      => 'active',
                'approved_at' => now(),
                'updated_at'  => now(),
            ]) > 0;
    }

    /**
     * Approve a pending relationship request (alias).
     */
    public function approveRelationship(int $childUserId, int $relationshipId): bool
    {
        return $this->approve($relationshipId, $childUserId);
    }

    /**
     * Revoke an active relationship.
     */
    public function revoke(int $relationshipId, int $userId): bool
    {
        return $this->relationship->newQuery()
            ->where('id', $relationshipId)
            ->where(fn (Builder $q) => $q->where('parent_user_id', $userId)->orWhere('child_user_id', $userId))
            ->update([
                'status'     => 'revoked',
                'updated_at' => now(),
            ]) > 0;
    }

    /**
     * Revoke a relationship (alias).
     */
    public function revokeRelationship(int $userId, int $relationshipId): bool
    {
        return $this->revoke($relationshipId, $userId);
    }

    /**
     * Update permissions for a relationship (parent only).
     */
    public function updatePermissions(int $parentUserId, int $relationshipId, array $permissions): bool
    {
        $this->errors = [];

        /** @var AccountRelationship|null $existing */
        $existing = $this->relationship->newQuery()
            ->where('id', $relationshipId)
            ->where('parent_user_id', $parentUserId)
            ->where('status', 'active')
            ->first();

        if (! $existing) {
            $this->errors[] = ['code' => 'NOT_FOUND', 'message' => 'Relationship not found'];
            return false;
        }

        $currentPermissions = is_array($existing->permissions) ? $existing->permissions : [];
        $mergedPermissions = array_merge($currentPermissions, $permissions);

        $existing->update(['permissions' => $mergedPermissions]);

        return true;
    }

    /**
     * Check if a parent has a specific permission for a child.
     */
    public function hasPermission(int $parentUserId, int $childUserId, string $permission): bool
    {
        /** @var AccountRelationship|null $row */
        $row = $this->relationship->newQuery()
            ->where('parent_user_id', $parentUserId)
            ->where('child_user_id', $childUserId)
            ->where('status', 'active')
            ->first();

        if (! $row) {
            return false;
        }

        $perms = is_array($row->permissions) ? $row->permissions : [];
        return ! empty($perms[$permission]);
    }

    /**
     * Get activity summary for a child account (parent view).
     */
    public function getChildActivitySummary(int $parentUserId, int $childUserId): ?array
    {
        if (! $this->hasPermission($parentUserId, $childUserId, 'can_view_activity')) {
            $this->errors[] = ['code' => 'FORBIDDEN', 'message' => 'You do not have permission to view this activity'];
            return null;
        }

        return $this->activityService->getDashboardData($childUserId);
    }
}
