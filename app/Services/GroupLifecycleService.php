<?php
// Copyright © 2024–2026 Jasper Ford
// SPDX-License-Identifier: AGPL-3.0-or-later
// Author: Jasper Ford
// See NOTICE file for attribution and acknowledgements.

namespace App\Services;

use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use App\Core\TenantContext;

/**
 * GroupLifecycleService — Manages group states, archiving, auto-archive,
 * ownership transfer, merge, and cloning.
 *
 * Lifecycle: draft → pending_approval → active → dormant → archived → deleted
 */
class GroupLifecycleService
{
    const STATUS_DRAFT = 'draft';
    const STATUS_PENDING = 'pending_approval';
    const STATUS_ACTIVE = 'active';
    const STATUS_DORMANT = 'dormant';
    const STATUS_ARCHIVED = 'archived';
    const STATUS_DELETED = 'deleted';

    /** Days of inactivity before marking dormant */
    const DORMANT_THRESHOLD_DAYS = 90;

    /** Days dormant before auto-archiving */
    const ARCHIVE_THRESHOLD_DAYS = 180;

    /**
     * Get the current lifecycle status of a group.
     */
    public static function getStatus(int $groupId): ?string
    {
        $tenantId = TenantContext::getId();
        $group = DB::table('groups')
            ->where('id', $groupId)
            ->where('tenant_id', $tenantId)
            ->first();

        if (!$group) {
            return null;
        }
        // groups table only has is_active boolean, not a status column
        return ($group->is_active ?? true) ? self::STATUS_ACTIVE : self::STATUS_ARCHIVED;
    }

    /**
     * Transition a group to a new status.
     */
    public static function transition(int $groupId, string $newStatus, int $performedBy, string $reason = ''): bool
    {
        $tenantId = TenantContext::getId();

        $validStatuses = [
            self::STATUS_DRAFT, self::STATUS_PENDING, self::STATUS_ACTIVE,
            self::STATUS_DORMANT, self::STATUS_ARCHIVED, self::STATUS_DELETED,
        ];

        if (!in_array($newStatus, $validStatuses, true)) {
            return false;
        }

        // groups table only has is_active boolean, not a status column
        $affected = DB::table('groups')
            ->where('id', $groupId)
            ->where('tenant_id', $tenantId)
            ->update([
                'is_active' => in_array($newStatus, [self::STATUS_ACTIVE, self::STATUS_DORMANT], true),
                'updated_at' => now(),
            ]);

        if ($affected > 0) {
            // Log the transition
            try {
                GroupAuditService::log(
                    'group_status_changed',
                    $groupId,
                    $performedBy,
                    ['new_status' => $newStatus, 'reason' => $reason]
                );
            } catch (\Exception $e) {
                Log::warning('GroupLifecycle: Failed to log transition', ['error' => $e->getMessage()]);
            }
        }

        return $affected > 0;
    }

    /**
     * Archive a group — preserves content, locks new activity.
     */
    public static function archive(int $groupId, int $performedBy, string $reason = ''): bool
    {
        return self::transition($groupId, self::STATUS_ARCHIVED, $performedBy, $reason);
    }

    /**
     * Unarchive a group — restores full activity.
     */
    public static function unarchive(int $groupId, int $performedBy): bool
    {
        return self::transition($groupId, self::STATUS_ACTIVE, $performedBy, 'Unarchived');
    }

    /**
     * Transfer ownership of a group.
     */
    public static function transferOwnership(int $groupId, int $newOwnerId, int $performedBy): bool
    {
        $tenantId = TenantContext::getId();

        // Verify new owner is a member
        $isMember = DB::table('group_members')
            ->where('group_id', $groupId)
            ->where('user_id', $newOwnerId)
            ->where('status', 'active')
            ->exists();

        if (!$isMember) {
            return false;
        }

        $group = DB::table('groups')
            ->where('id', $groupId)
            ->where('tenant_id', $tenantId)
            ->first();

        if (!$group) {
            return false;
        }

        $oldOwnerId = $group->owner_id;

        DB::transaction(function () use ($groupId, $newOwnerId, $oldOwnerId, $tenantId) {
            // Update group owner
            DB::table('groups')
                ->where('id', $groupId)
                ->where('tenant_id', $tenantId)
                ->update(['owner_id' => $newOwnerId, 'updated_at' => now()]);

            // Update membership roles
            DB::table('group_members')
                ->where('group_id', $groupId)
                ->where('user_id', $newOwnerId)
                ->update(['role' => 'owner', 'updated_at' => now()]);

            DB::table('group_members')
                ->where('group_id', $groupId)
                ->where('user_id', $oldOwnerId)
                ->update(['role' => 'admin', 'updated_at' => now()]);
        });

        try {
            GroupAuditService::log(
                GroupAuditService::ACTION_GROUP_UPDATED,
                $groupId,
                $performedBy,
                ['action' => 'ownership_transferred', 'from' => $oldOwnerId, 'to' => $newOwnerId]
            );
        } catch (\Exception $e) {
            // Non-critical
        }

        return true;
    }

    /**
     * Merge source group into target group.
     */
    public static function mergeGroups(int $sourceGroupId, int $targetGroupId, int $performedBy): bool
    {
        $tenantId = TenantContext::getId();

        $source = DB::table('groups')->where('id', $sourceGroupId)->where('tenant_id', $tenantId)->first();
        $target = DB::table('groups')->where('id', $targetGroupId)->where('tenant_id', $tenantId)->first();

        if (!$source || !$target) {
            return false;
        }

        DB::transaction(function () use ($sourceGroupId, $targetGroupId, $tenantId) {
            // Migrate members (skip duplicates via INSERT IGNORE)
            $sourceMembers = DB::table('group_members')
                ->where('group_id', $sourceGroupId)
                ->where('status', 'active')
                ->get();

            foreach ($sourceMembers as $member) {
                $exists = DB::table('group_members')
                    ->where('group_id', $targetGroupId)
                    ->where('user_id', $member->user_id)
                    ->exists();

                if (!$exists) {
                    DB::table('group_members')->insert([
                        'tenant_id' => $tenantId,
                        'group_id' => $targetGroupId,
                        'user_id' => $member->user_id,
                        'role' => 'member', // Demote to member in merged group
                        'status' => 'active',
                        'created_at' => $member->created_at,
                        'updated_at' => now(),
                    ]);
                }
            }

            // Migrate discussions
            DB::table('group_discussions')
                ->where('group_id', $sourceGroupId)
                ->where('tenant_id', $tenantId)
                ->update(['group_id' => $targetGroupId]);

            // Migrate files
            DB::table('group_files')
                ->where('group_id', $sourceGroupId)
                ->where('tenant_id', $tenantId)
                ->update(['group_id' => $targetGroupId]);

            // Migrate events
            DB::table('events')
                ->where('group_id', $sourceGroupId)
                ->where('tenant_id', $tenantId)
                ->update(['group_id' => $targetGroupId]);

            // Migrate announcements
            DB::table('group_announcements')
                ->where('group_id', $sourceGroupId)
                ->where('tenant_id', $tenantId)
                ->update(['group_id' => $targetGroupId]);

            // Update cached member count
            $newCount = DB::table('group_members')
                ->where('group_id', $targetGroupId)
                ->where('status', 'active')
                ->count();

            DB::table('groups')
                ->where('id', $targetGroupId)
                ->update(['cached_member_count' => $newCount, 'updated_at' => now()]);

            // Mark source group as deleted
            DB::table('groups')
                ->where('id', $sourceGroupId)
                ->update(['is_active' => false, 'updated_at' => now()]);
        });

        try {
            GroupAuditService::log(
                GroupAuditService::ACTION_GROUP_UPDATED,
                $targetGroupId,
                $performedBy,
                ['action' => 'groups_merged', 'source_group_id' => $sourceGroupId, 'source_name' => $source->name]
            );
        } catch (\Exception $e) {
            // Non-critical
        }

        return true;
    }

    /**
     * Clone a group with settings (optionally with members).
     */
    public static function cloneGroup(int $sourceGroupId, string $newName, int $ownerId, bool $cloneMembers = false): ?int
    {
        $tenantId = TenantContext::getId();

        $source = DB::table('groups')->where('id', $sourceGroupId)->where('tenant_id', $tenantId)->first();
        if (!$source) {
            return null;
        }

        $newGroupId = DB::table('groups')->insertGetId([
            'tenant_id' => $tenantId,
            'owner_id' => $ownerId,
            'name' => $newName,
            'description' => $source->description,
            'visibility' => $source->visibility,
            'type_id' => $source->type_id,
            'location' => $source->location,
            'latitude' => $source->latitude,
            'longitude' => $source->longitude,
            'is_active' => true,
            'cached_member_count' => 1,
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        // Add owner as member
        DB::table('group_members')->insert([
            'tenant_id' => $tenantId,
            'group_id' => $newGroupId,
            'user_id' => $ownerId,
            'role' => 'owner',
            'status' => 'active',
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        // Clone members if requested
        if ($cloneMembers) {
            $members = DB::table('group_members')
                ->where('group_id', $sourceGroupId)
                ->where('status', 'active')
                ->where('user_id', '!=', $ownerId)
                ->get();

            foreach ($members as $member) {
                DB::table('group_members')->insert([
                    'tenant_id' => $tenantId,
                    'group_id' => $newGroupId,
                    'user_id' => $member->user_id,
                    'role' => 'member',
                    'status' => 'active',
                    'created_at' => now(),
                    'updated_at' => now(),
                ]);
            }

            $count = DB::table('group_members')
                ->where('group_id', $newGroupId)
                ->where('status', 'active')
                ->count();

            DB::table('groups')->where('id', $newGroupId)->update(['cached_member_count' => $count]);
        }

        // Clone tags
        $tags = DB::table('group_tag_assignments')->where('group_id', $sourceGroupId)->get();
        foreach ($tags as $tag) {
            DB::table('group_tag_assignments')->insert([
                'group_id' => $newGroupId,
                'tag_id' => $tag->tag_id,
            ]);
        }

        return $newGroupId;
    }

    /**
     * Check for dormant/inactive groups and update their status.
     * Intended to be run as a scheduled command.
     */
    public static function checkInactiveGroups(int $tenantId): array
    {
        $dormantThreshold = now()->subDays(self::DORMANT_THRESHOLD_DAYS);
        $archiveThreshold = now()->subDays(self::ARCHIVE_THRESHOLD_DAYS);

        $stats = ['dormant' => 0, 'archived' => 0, 'warned' => 0];

        // Find active groups with no recent activity
        $activeGroups = DB::table('groups')
            ->where('tenant_id', $tenantId)
            ->where('is_active', true)
            ->get();

        foreach ($activeGroups as $group) {
            $lastActivity = self::getLastActivityDate($group->id, $tenantId);

            if ($lastActivity && $lastActivity < $archiveThreshold) {
                // Auto-archive if beyond archive threshold
                DB::table('groups')
                    ->where('id', $group->id)
                    ->update(['is_active' => false, 'updated_at' => now()]);
                $stats['archived']++;
            } elseif ($lastActivity && $lastActivity < $dormantThreshold) {
                // Mark as dormant
                DB::table('groups')
                    ->where('id', $group->id)
                    ->update(['updated_at' => now()]);
                $stats['dormant']++;
            }
        }

        return $stats;
    }

    /**
     * Get the last activity date for a group.
     */
    private static function getLastActivityDate(int $groupId, int $tenantId): ?\DateTimeInterface
    {
        $dates = [];

        $lastPost = DB::table('group_posts as gp')
            ->join('group_discussions as gd', 'gp.discussion_id', '=', 'gd.id')
            ->where('gd.group_id', $groupId)
            ->where('gp.tenant_id', $tenantId)
            ->max('gp.created_at');
        if ($lastPost) $dates[] = $lastPost;

        $lastDiscussion = DB::table('group_discussions')
            ->where('group_id', $groupId)
            ->where('tenant_id', $tenantId)
            ->max('created_at');
        if ($lastDiscussion) $dates[] = $lastDiscussion;

        $lastMember = DB::table('group_members')
            ->where('group_id', $groupId)
            ->where('status', 'active')
            ->max('created_at');
        if ($lastMember) $dates[] = $lastMember;

        if (empty($dates)) {
            return null;
        }

        return new \DateTime(max($dates));
    }

    /**
     * Bulk archive groups.
     */
    public static function bulkArchive(array $groupIds, int $performedBy): int
    {
        $tenantId = TenantContext::getId();

        $affected = DB::table('groups')
            ->where('tenant_id', $tenantId)
            ->whereIn('id', $groupIds)
            ->where('is_active', true)
            ->update(['is_active' => false, 'updated_at' => now()]);

        return $affected;
    }

    /**
     * Bulk unarchive groups.
     */
    public static function bulkUnarchive(array $groupIds, int $performedBy): int
    {
        $tenantId = TenantContext::getId();

        $affected = DB::table('groups')
            ->where('tenant_id', $tenantId)
            ->whereIn('id', $groupIds)
            ->where('status', self::STATUS_ARCHIVED)
            ->update(['is_active' => true, 'updated_at' => now()]);

        return $affected;
    }
}
