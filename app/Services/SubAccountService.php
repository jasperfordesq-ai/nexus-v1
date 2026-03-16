<?php
// Copyright © 2024–2026 Jasper Ford
// SPDX-License-Identifier: AGPL-3.0-or-later
// Author: Jasper Ford
// See NOTICE file for attribution and acknowledgements.

namespace App\Services;

use Illuminate\Support\Facades\DB;

/**
 * SubAccountService — Laravel DI-based service for family/guardian accounts.
 *
 * Eloquent/DI counterpart to the legacy static \Nexus\Services\SubAccountService.
 * Manages parent-child account relationships with permission controls.
 */
class SubAccountService
{
    public const RELATIONSHIP_TYPES = ['family', 'guardian', 'carer', 'organization'];

    /**
     * Get child accounts linked to a parent user.
     */
    public function getChildren(int $parentUserId): array
    {
        return DB::table('account_relationships as ar')
            ->join('users as u', 'ar.child_user_id', '=', 'u.id')
            ->where('ar.parent_user_id', $parentUserId)
            ->where('ar.status', 'active')
            ->select('ar.*', 'u.first_name', 'u.last_name', 'u.email', 'u.avatar_url')
            ->orderByDesc('ar.created_at')
            ->get()
            ->map(fn ($r) => (array) $r)
            ->all();
    }

    /**
     * Request a parent-child relationship.
     *
     * @return int|null Relationship ID or null on failure.
     */
    public function requestRelationship(int $parentUserId, int $childUserId, string $type = 'family', array $permissions = []): ?int
    {
        if ($parentUserId === $childUserId) {
            return null;
        }
        if (! in_array($type, self::RELATIONSHIP_TYPES, true)) {
            return null;
        }

        $existing = DB::table('account_relationships')
            ->where('parent_user_id', $parentUserId)
            ->where('child_user_id', $childUserId)
            ->first();

        if ($existing) {
            return (int) $existing->id;
        }

        $defaultPerms = [
            'can_view_activity'    => true,
            'can_manage_listings'  => false,
            'can_transact'         => false,
            'can_view_messages'    => false,
        ];

        return DB::table('account_relationships')->insertGetId([
            'parent_user_id'    => $parentUserId,
            'child_user_id'     => $childUserId,
            'relationship_type' => $type,
            'permissions'       => json_encode(array_merge($defaultPerms, $permissions)),
            'status'            => 'pending',
            'created_at'        => now(),
            'updated_at'        => now(),
        ]);
    }

    /**
     * Approve a pending relationship request.
     */
    public function approve(int $relationshipId, int $childUserId): bool
    {
        return DB::table('account_relationships')
            ->where('id', $relationshipId)
            ->where('child_user_id', $childUserId)
            ->where('status', 'pending')
            ->update([
                'status'     => 'active',
                'updated_at' => now(),
            ]) > 0;
    }

    /**
     * Revoke an active relationship.
     */
    public function revoke(int $relationshipId, int $userId): bool
    {
        return DB::table('account_relationships')
            ->where('id', $relationshipId)
            ->where(fn ($q) => $q->where('parent_user_id', $userId)->orWhere('child_user_id', $userId))
            ->update([
                'status'     => 'revoked',
                'updated_at' => now(),
            ]) > 0;
    }
}
