<?php
// Copyright © 2024–2026 Jasper Ford
// SPDX-License-Identifier: AGPL-3.0-or-later
// Author: Jasper Ford
// See NOTICE file for attribution and acknowledgements.

namespace App\Services;

use App\Core\EmailTemplateBuilder;
use App\Core\Mailer;
use App\Core\TenantContext;
use App\Events\GroupCreated;
use App\Events\GroupDeleted;
use App\Events\GroupMemberJoined;
use App\Events\GroupMemberLeft;
use App\Events\GroupUpdated;
use App\Models\Group;
use App\Models\GroupDiscussion;
use App\Models\GroupPost;
use App\Models\Notification;
use App\Models\User;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;

/**
 * GroupService — Laravel DI-based service for group operations.
 *
 * All queries are tenant-scoped automatically via the HasTenantScope trait.
 */
class GroupService
{
    public function __construct(
        private readonly Group $group,
    ) {}

    /**
     * Get groups with cursor-based pagination.
     *
     * @return array{items: array, cursor: string|null, has_more: bool}
     */
    public static function getAll(array $filters = []): array
    {
        $limit = min((int) ($filters['limit'] ?? 20), 100);
        $cursor = $filters['cursor'] ?? null;

        $query = Group::query()
            ->with(['creator:id,first_name,last_name,avatar_url'])
            ->withCount('activeMembers');

        // Show featured groups (regardless of hierarchy) + top-level non-featured groups
        if (empty($filters['parent_id'])) {
            $query->where(function (Builder $q) {
                $q->where('is_featured', true)
                  ->orWhere(function (Builder $q2) {
                      $q2->whereNull('parent_id')->orWhere('parent_id', 0);
                  });
            });
        }

        if (! empty($filters['visibility'])) {
            $query->where('visibility', $filters['visibility']);
        }

        if (! empty($filters['type_id'])) {
            $query->where('type_id', (int) $filters['type_id']);
        }

        if (! empty($filters['user_id'])) {
            // Direct subquery on group_members avoids a JOIN to users + withCount-triggered N+1
            $uid = (int) $filters['user_id'];
            $query->whereIn('id', function ($sub) use ($uid) {
                $sub->select('group_id')
                    ->from('group_members')
                    ->where('user_id', $uid)
                    ->where('status', 'active');
            });
        }

        if (! empty($filters['search'])) {
            $term = '%' . $filters['search'] . '%';
            $query->where(function (Builder $q) use ($term) {
                $q->where('name', 'LIKE', $term)
                  ->orWhere('description', 'LIKE', $term);
            });
        }

        if ($cursor !== null) {
            $cursorId = base64_decode($cursor, true);
            if ($cursorId !== false && is_numeric($cursorId)) {
                $query->where('id', '<', (int) $cursorId);
            }
        }

        $query->orderByDesc('is_featured')->orderByDesc('id');

        $items = $query->limit($limit + 1)->get();
        $hasMore = $items->count() > $limit;
        if ($hasMore) {
            $items->pop();
        }

        $enriched = $items->map(function (Group $group) {
            $data = $group->toArray();
            return self::enrichGroupData($data, $group);
        })->all();

        return [
            'items'    => $enriched,
            'cursor'   => $hasMore && $items->isNotEmpty() ? base64_encode((string) $items->last()->id) : null,
            'has_more' => $hasMore,
        ];
    }

    /**
     * Batch-load viewer membership data for multiple groups (avoids N+1).
     *
     * @param  int[] $groupIds
     * @return array<int, array{status: string, role: string|null, is_admin: bool}> Map of group_id => membership
     */
    public static function getViewerMembershipsBatch(array $groupIds, int $userId): array
    {
        if (empty($groupIds)) {
            return [];
        }
        $tenantId = TenantContext::getId();
        $placeholders = implode(',', array_fill(0, count($groupIds), '?'));
        $params = array_merge($groupIds, [$tenantId, $userId]);
        $rows = DB::select(
            "SELECT group_id, status, role FROM group_members WHERE group_id IN ({$placeholders}) AND group_id IN (SELECT id FROM groups WHERE tenant_id = ?) AND user_id = ?",
            $params
        );
        $map = [];
        foreach ($rows as $row) {
            $map[(int) $row->group_id] = [
                'status'   => $row->status ?? 'none',
                'role'     => $row->role,
                'is_admin' => in_array($row->role ?? '', ['admin', 'owner']),
            ];
        }
        return $map;
    }

    /**
     * Get a single group by ID.
     */
    public static function getById(int $id, ?int $currentUserId = null): ?array
    {
        /** @var Group|null $group */
        $group = Group::query()
            ->with(['creator:id,first_name,last_name,organization_name,profile_type,avatar_url'])
            ->withCount('activeMembers')
            ->find($id);

        if (! $group) {
            return null;
        }

        $data = $group->toArray();
        $data = self::enrichGroupData($data, $group);

        // Replace eager-loaded creator relation with safe public fields only
        $creator = $group->creator;
        if ($creator) {
            $data['creator'] = [
                'id'         => $creator->id,
                'name'       => ($creator->profile_type === 'organisation' && $creator->organization_name)
                                    ? $creator->organization_name
                                    : trim($creator->first_name . ' ' . $creator->last_name),
                'avatar'     => $creator->avatar_url,
                'avatar_url' => $creator->avatar_url,
            ];
        }

        // Fetch immediate child groups (sub_groups) so the frontend can render the subgroups tab
        $tenantId = TenantContext::getId();
        $subGroups = DB::table('groups')
            ->where('tenant_id', $tenantId)
            ->where('parent_id', $id)
            ->where(function ($q) {
                $q->where('is_active', 1)->orWhereNull('is_active');
            })
            ->orderBy('name')
            ->select(['id', 'name', 'description', 'image_url', 'visibility', 'cached_member_count', 'type_id', 'parent_id'])
            ->get()
            ->map(fn($g) => [
                'id'           => (int) $g->id,
                'name'         => $g->name,
                'description'  => $g->description,
                'image_url'    => $g->image_url,
                'visibility'   => $g->visibility,
                'member_count' => (int) ($g->cached_member_count ?? 0),
                'type_id'      => $g->type_id,
                'parent_id'    => (int) $g->parent_id,
            ])
            ->all();
        $data['sub_groups'] = $subGroups;

        if ($currentUserId) {
            $membership = DB::table('group_members')
                ->whereIn('group_id', fn ($q) => $q->select('id')->from('groups')->where('tenant_id', $tenantId))
                ->where('group_id', $id)
                ->where('user_id', $currentUserId)
                ->first();

            // Flat fields (legacy)
            $data['my_role'] = $membership?->role;
            $data['my_status'] = $membership?->status;

            // Nested viewer_membership (frontend expects this structure)
            $data['viewer_membership'] = $membership ? [
                'status'   => $membership->status ?? 'none',
                'role'     => $membership->role,
                'is_admin' => in_array($membership->role ?? '', ['admin', 'owner']),
            ] : null;

            // Recent members (last 5 active members)
            $recentMembers = DB::table('group_members')
                ->join('users', 'group_members.user_id', '=', 'users.id')
                ->whereIn('group_members.group_id', fn ($q) => $q->select('id')->from('groups')->where('tenant_id', $tenantId))
                ->where('group_members.group_id', $id)
                ->where('group_members.status', 'active')
                ->orderByDesc('group_members.created_at')
                ->limit(5)
                ->select(['users.id', 'users.first_name', 'users.last_name', 'users.avatar_url'])
                ->get();

            $data['recent_members'] = $recentMembers->map(fn($m) => [
                'id'         => (int) $m->id,
                'first_name' => $m->first_name,
                'last_name'  => $m->last_name,
                'name'       => trim(($m->first_name ?? '') . ' ' . ($m->last_name ?? '')),
                'avatar_url' => $m->avatar_url,
                'avatar'     => $m->avatar_url,
            ])->all();
        }

        return $data;
    }

    /**
     * Enrich group data with frontend-compatible field aliases.
     */
    private static function enrichGroupData(array $data, Group $group): array
    {
        $memberCount = $data['active_members_count'] ?? $group->cached_member_count ?? 0;
        $data['member_count'] = $memberCount;
        $data['members_count'] = $memberCount;

        return $data;
    }

    /**
     * Create a new group.
     */
    public static function create(int $userId, array $data): Group
    {
        $group = DB::transaction(function () use ($userId, $data) {
            $group = new Group([
                'owner_id'             => $userId,
                'name'                 => trim($data['name']),
                'description'          => trim($data['description'] ?? ''),
                'visibility'           => $data['visibility'] ?? 'public',
                'image_url'            => $data['image_url'] ?? null,
                'location'             => $data['location'] ?? null,
                'latitude'             => $data['latitude'] ?? null,
                'longitude'            => $data['longitude'] ?? null,
                'type_id'              => $data['type_id'] ?? null,
                'federated_visibility' => $data['federated_visibility'] ?? 'none',
            ]);

            $group->save();

            // Auto-join creator as admin
            $group->attachMember($userId, [
                'role'   => 'admin',
                'status' => 'active',
            ]);

            $group->cached_member_count = 1;
            $group->save();

            // Log group creation
            try { GroupAuditService::log(GroupAuditService::ACTION_GROUP_CREATED, $group->id, $userId, ['name' => $group->name]); } catch (\Throwable $e) { \Log::warning('GroupService: failed to log group creation audit', ['group_id' => $group->id, 'error' => $e->getMessage()]); }

            $fresh = $group->fresh(['creator']);

            try {
                GroupCreated::dispatch($fresh ?? $group, (int) TenantContext::getId());
            } catch (\Throwable $e) {
                Log::warning('Failed to dispatch GroupCreated', [
                    'group_id' => $group->id ?? null,
                    'error'    => $e->getMessage(),
                ]);
            }

            return $fresh ?? $group;
        });

        // Send creation confirmation email to the group creator
        try {
            $creator = DB::table('users')
                ->where('id', $userId)
                ->where('tenant_id', TenantContext::getId())
                ->select(['email', 'first_name', 'name'])
                ->first();

            if ($creator && !empty($creator->email)) {
                $firstName  = $creator->first_name ?? $creator->name ?? 'there';
                $tenantName = TenantContext::getSetting('site_name', 'Project NEXUS');
                $groupUrl   = TenantContext::getFrontendUrl() . TenantContext::getSlugPrefix() . '/groups/' . $group->id;
                $groupName  = $group->name ?? '';

                $html = EmailTemplateBuilder::make()
                    ->theme('success')
                    ->title(__('emails_created.group.title'))
                    ->previewText(__('emails_created.group.preview', ['name' => $groupName, 'community' => $tenantName]))
                    ->greeting($firstName)
                    ->paragraph(__('emails_created.group.body', ['community' => $tenantName]))
                    ->highlight($groupName)
                    ->button(__('emails_created.group.cta'), $groupUrl)
                    ->render();

                Mailer::forCurrentTenant()->send(
                    $creator->email,
                    __('emails_created.group.subject', ['name' => $groupName, 'community' => $tenantName]),
                    $html
                );
            }
        } catch (\Throwable $e) {
            Log::warning('[GroupService] creation email failed: ' . $e->getMessage());
        }

        return $group;
    }

    /**
     * Join a group.
     */
    public static function join(int $groupId, int $userId): array
    {
        /** @var Group|null $group */
        $group = Group::query()->find($groupId);
        if (!$group) {
            return ['success' => false, 'error' => __('api.group_not_found')];
        }

        $existing = DB::table('group_members')
            ->where('group_id', $groupId)
            ->where('user_id', $userId)
            ->first();

        if ($existing) {
            // Prevent banned users from rejoining
            if (($existing->status ?? '') === 'banned') {
                return ['success' => false, 'error' => __('api.group_banned')];
            }
            return ['success' => false, 'error' => __('api.group_already_member')];
        }

        $status = $group->visibility === 'private' ? 'pending' : 'active';

        $group->attachMember($userId, [
            'role'   => 'member',
            'status' => $status,
        ]);

        if ($status === 'active') {
            $group->increment('cached_member_count');

            // Send welcome message + fire webhook + log audit
            try { GroupWelcomeService::sendWelcome($groupId, $userId); } catch (\Throwable $e) { \Log::warning('GroupService: failed to send welcome message on join', ['group_id' => $groupId, 'user_id' => $userId, 'error' => $e->getMessage()]); }
            try { GroupWebhookService::fire($groupId, GroupWebhookService::EVENT_MEMBER_JOINED, ['user_id' => $userId]); } catch (\Throwable $e) { \Log::warning('GroupService: failed to fire member_joined webhook', ['group_id' => $groupId, 'user_id' => $userId, 'error' => $e->getMessage()]); }
            try { GroupAuditService::log(GroupAuditService::ACTION_MEMBER_JOINED, $groupId, $userId); } catch (\Throwable $e) { \Log::warning('GroupService: failed to log member_joined audit', ['group_id' => $groupId, 'user_id' => $userId, 'error' => $e->getMessage()]); }
            try { GroupChallengeService::incrementProgress($groupId, 'members'); } catch (\Throwable $e) { \Log::warning('GroupService: failed to increment challenge progress for members', ['group_id' => $groupId, 'error' => $e->getMessage()]); }

            try {
                GroupMemberJoined::dispatch($groupId, $userId, (int) TenantContext::getId());
            } catch (\Throwable $e) {
                Log::warning('Failed to dispatch GroupMemberJoined', [
                    'group_id' => $groupId,
                    'user_id'  => $userId,
                    'error'    => $e->getMessage(),
                ]);
            }
        }

        return ['success' => true, 'status' => $status];
    }

    /**
     * Leave a group.
     */
    public static function leave(int $groupId, int $userId): bool
    {
        /** @var Group|null $group */
        $group = Group::query()->find($groupId);
        if (!$group) {
            self::$errors[] = ['code' => 'NOT_FOUND', 'message' => __('api.group_not_found')];
            return false;
        }

        $detached = $group->members()->detach($userId);

        if ($detached > 0) {
            $group->decrement('cached_member_count');
            try { GroupWebhookService::fire($groupId, GroupWebhookService::EVENT_MEMBER_LEFT, ['user_id' => $userId]); } catch (\Throwable $e) { \Log::warning('GroupService: failed to fire member_left webhook', ['group_id' => $groupId, 'user_id' => $userId, 'error' => $e->getMessage()]); }
            try { GroupAuditService::log(GroupAuditService::ACTION_MEMBER_LEFT, $groupId, $userId); } catch (\Throwable $e) { \Log::warning('GroupService: failed to log member_left audit', ['group_id' => $groupId, 'user_id' => $userId, 'error' => $e->getMessage()]); }

            try {
                GroupMemberLeft::dispatch($groupId, $userId, (int) TenantContext::getId());
            } catch (\Throwable $e) {
                Log::warning('Failed to dispatch GroupMemberLeft', [
                    'group_id' => $groupId,
                    'user_id'  => $userId,
                    'error'    => $e->getMessage(),
                ]);
            }
        }

        return $detached > 0;
    }

    // -----------------------------------------------------------------
    //  Validation errors
    // -----------------------------------------------------------------

    /** @var array */
    private static array $errors = [];

    /**
     * Get validation errors from the last operation.
     */
    public static function getErrors(): array
    {
        return self::$errors;
    }

    /**
     * Validate group data and return boolean.
     *
     * @return bool True if valid, false if errors (check getErrors()).
     */
    public static function validate(array $data): bool
    {
        self::$errors = [];

        $name = $data['name'] ?? null;
        $visibility = $data['visibility'] ?? null;

        // name is required and max 255
        if ($name === null || $name === '') {
            self::$errors[] = ['code' => 'VALIDATION_ERROR', 'message' => __('api.name_required'), 'field' => 'name'];
        } elseif (mb_strlen($name) > 255) {
            self::$errors[] = ['code' => 'VALIDATION_ERROR', 'message' => __('api.group_name_max_255'), 'field' => 'name'];
        }

        // visibility must be public or private (if provided)
        if ($visibility !== null && !in_array($visibility, ['public', 'private'], true)) {
            self::$errors[] = ['code' => 'VALIDATION_ERROR', 'message' => __('api.group_visibility_invalid'), 'field' => 'visibility'];
        }

        return empty(self::$errors);
    }

    // -----------------------------------------------------------------
    //  Update
    // -----------------------------------------------------------------

    /**
     * Update a group.
     */
    public static function update(int $id, int $userId, array $data): bool
    {
        self::$errors = [];

        /** @var Group|null $group */
        $group = Group::query()->find($id);

        if (! $group) {
            self::$errors[] = ['code' => 'NOT_FOUND', 'message' => __('api.group_not_found')];
            return false;
        }

        if (! self::canModify($id, $userId)) {
            self::$errors[] = ['code' => 'FORBIDDEN', 'message' => __('api.group_edit_forbidden')];
            return false;
        }

        $allowed = ['name', 'description', 'visibility', 'location', 'latitude', 'longitude', 'federated_visibility'];
        $updates = collect($data)->only($allowed)->all();

        if (! empty($updates)) {
            $group->fill($updates);
            $group->save();

            try {
                GroupUpdated::dispatch($group->fresh() ?? $group, (int) TenantContext::getId());
            } catch (\Throwable $e) {
                Log::warning('Failed to dispatch GroupUpdated', [
                    'group_id' => $group->id ?? null,
                    'error'    => $e->getMessage(),
                ]);
            }
        }

        return true;
    }

    // -----------------------------------------------------------------
    //  Delete
    // -----------------------------------------------------------------

    /**
     * Delete a group.
     */
    public static function delete(int $id, int $userId): bool
    {
        self::$errors = [];

        /** @var Group|null $group */
        $group = Group::query()->find($id);

        if (! $group) {
            self::$errors[] = ['code' => 'NOT_FOUND', 'message' => __('api.group_not_found')];
            return false;
        }

        // Only owner or platform admin can delete
        if ((int) $group->owner_id !== $userId && ! self::isPlatformAdmin($userId)) {
            self::$errors[] = ['code' => 'FORBIDDEN', 'message' => __('api.group_delete_forbidden')];
            return false;
        }

        $groupName = $group->name;

        return DB::transaction(function () use ($group, $id, $userId, $groupName) {
            // Fetch active members before deleting (to notify them)
            $memberIds = DB::table('group_members')
                ->where('group_id', $id)
                ->where('status', 'active')
                ->where('user_id', '!=', $userId)
                ->pluck('user_id')
                ->all();

            // Delete group members
            DB::table('group_members')->where('group_id', $id)->delete();

            // Delete discussion posts, then discussions
            $discussionIds = GroupDiscussion::withoutGlobalScopes()
                ->where('group_id', $id)
                ->pluck('id')
                ->all();

            if (! empty($discussionIds)) {
                GroupPost::withoutGlobalScopes()
                    ->whereIn('discussion_id', $discussionIds)
                    ->delete();

                GroupDiscussion::withoutGlobalScopes()
                    ->where('group_id', $id)
                    ->delete();
            }

            // Disassociate events from this group (preserve events, clear group_id)
            DB::table('events')
                ->where('group_id', $id)
                ->update(['group_id' => null]);

            // Delete chatroom messages and chatrooms
            $chatroomIds = DB::table('group_chatrooms')
                ->where('group_id', $id)
                ->pluck('id')
                ->all();

            if (! empty($chatroomIds)) {
                $placeholders = implode(',', array_fill(0, count($chatroomIds), '?'));
                DB::delete("DELETE FROM group_chatroom_messages WHERE chatroom_id IN ({$placeholders})", $chatroomIds);
                DB::delete("DELETE FROM group_chatroom_pinned_messages WHERE chatroom_id IN ({$placeholders})", $chatroomIds);
                DB::table('group_chatrooms')->where('group_id', $id)->delete();
            }

            // Delete the group itself
            $group->delete();

            try {
                GroupDeleted::dispatch($id, (int) TenantContext::getId(), $groupName);
            } catch (\Throwable $e) {
                Log::warning('Failed to dispatch GroupDeleted', [
                    'group_id' => $id,
                    'error'    => $e->getMessage(),
                ]);
            }

            return true;
        });
    }

    // -----------------------------------------------------------------
    //  Members
    // -----------------------------------------------------------------

    /**
     * Get members of a group with cursor-based pagination.
     */
    public static function getMembers(int $groupId, array $filters = []): array
    {
        $limit = min((int) ($filters['limit'] ?? 20), 100);
        $role = $filters['role'] ?? null;
        $cursor = $filters['cursor'] ?? null;

        $query = DB::table('group_members')
            ->join('users', 'group_members.user_id', '=', 'users.id')
            ->where('group_members.group_id', $groupId)
            ->whereIn('group_members.group_id', function ($q) {
                $q->select('id')->from('groups')->where('tenant_id', TenantContext::getId());
            })
            ->where('group_members.status', 'active')
            ->select([
                'group_members.id as membership_id',
                'group_members.user_id',
                'group_members.role',
                'group_members.created_at as joined_at',
                'users.first_name',
                'users.last_name',
                'users.avatar_url',
            ]);

        if ($role) {
            $query->where('group_members.role', $role);
        }

        if ($cursor !== null) {
            $cursorId = base64_decode($cursor, true);
            if ($cursorId !== false && is_numeric($cursorId)) {
                $query->where('group_members.id', '>', (int) $cursorId);
            }
        }

        $query->orderByRaw("FIELD(group_members.role, 'owner', 'admin', 'member')")
              ->orderBy('group_members.id');

        $members = $query->limit($limit + 1)->get();

        $hasMore = $members->count() > $limit;
        if ($hasMore) {
            $members->pop();
        }

        $items = $members->map(fn ($m) => [
            'id'         => (int) $m->user_id,
            'name'       => trim(($m->first_name ?? '') . ' ' . ($m->last_name ?? '')),
            'avatar_url' => $m->avatar_url,
            'role'       => $m->role,
            'joined_at'  => $m->joined_at,
        ])->all();

        $lastId = $members->isNotEmpty() ? $members->last()->membership_id : null;

        return [
            'items'    => $items,
            'cursor'   => $hasMore && $lastId ? base64_encode((string) $lastId) : null,
            'has_more' => $hasMore,
        ];
    }

    /**
     * Update a member's role in a group.
     */
    public static function updateMemberRole(int $groupId, int $targetUserId, int $actingUserId, string $role): bool
    {
        self::$errors = [];

        if (! in_array($role, ['admin', 'member'])) {
            self::$errors[] = ['code' => 'VALIDATION_ERROR', 'message' => __('api.invalid_role'), 'field' => 'role'];
            return false;
        }

        if (! self::canModify($groupId, $actingUserId)) {
            self::$errors[] = ['code' => 'FORBIDDEN', 'message' => __('api.group_manage_members_forbidden')];
            return false;
        }

        // Check target is an active member
        $membership = DB::table('group_members')
            ->where('group_id', $groupId)
            ->where('user_id', $targetUserId)
            ->first();

        if (! $membership || $membership->status !== 'active') {
            self::$errors[] = ['code' => 'NOT_MEMBER', 'message' => __('api.group_user_not_member')];
            return false;
        }

        // Can't change owner's role
        /** @var Group|null $group */
        $group = Group::query()->find($groupId);
        if (!$group) {
            self::$errors[] = ['code' => 'NOT_FOUND', 'message' => __('api.group_not_found')];
            return false;
        }
        if ((int) $group->owner_id === $targetUserId) {
            self::$errors[] = ['code' => 'FORBIDDEN', 'message' => __('api.group_cannot_change_owner_role')];
            return false;
        }

        DB::table('group_members')
            ->where('group_id', $groupId)
            ->where('user_id', $targetUserId)
            ->update(['role' => $role]);

        // Email promoted member when they become an admin
        if ($role === 'admin') {
            try {
                $tenantId = TenantContext::getId();
                $member = DB::table('users')
                    ->where('id', $targetUserId)
                    ->where('tenant_id', $tenantId)
                    ->select(['email', 'first_name', 'name'])
                    ->first();
                if ($member && !empty($member->email)) {
                    $firstName  = $member->first_name ?? $member->name ?? 'there';
                    $community  = TenantContext::getName();
                    $groupName  = htmlspecialchars($group->name, ENT_QUOTES, 'UTF-8');
                    $roleLabel  = ucfirst($role);
                    $groupUrl   = TenantContext::getFrontendUrl() . TenantContext::getSlugPrefix() . '/groups/' . $groupId;
                    $html = EmailTemplateBuilder::make()
                        ->theme('success')
                        ->title(__('emails_commerce.group_promoted.title'))
                        ->greeting($firstName)
                        ->paragraph(__('emails_commerce.group_promoted.body', ['role' => $roleLabel, 'group_name' => $groupName, 'community' => $community]))
                        ->paragraph(__('emails_commerce.group_promoted.responsibilities', ['role' => $roleLabel]))
                        ->button(__('emails_commerce.group_promoted.cta'), $groupUrl)
                        ->render();
                    Mailer::forCurrentTenant()->send(
                        $member->email,
                        __('emails_commerce.group_promoted.subject', ['role' => $roleLabel, 'group_name' => $groupName, 'community' => $community]),
                        $html
                    );
                }
            } catch (\Throwable $e) {
                Log::warning('[GroupService] group_promoted email failed: ' . $e->getMessage());
            }
        }

        return true;
    }

    /**
     * Remove a member from a group.
     */
    public static function removeMember(int $groupId, int $targetUserId, int $actingUserId): bool
    {
        self::$errors = [];

        if (! self::canModify($groupId, $actingUserId)) {
            self::$errors[] = ['code' => 'FORBIDDEN', 'message' => __('api.group_remove_members_forbidden')];
            return false;
        }

        // Can't remove the owner
        /** @var Group|null $group */
        $group = Group::query()->find($groupId);
        if (!$group) {
            self::$errors[] = ['code' => 'NOT_FOUND', 'message' => __('api.group_not_found')];
            return false;
        }
        if ((int) $group->owner_id === $targetUserId) {
            self::$errors[] = ['code' => 'FORBIDDEN', 'message' => __('api.group_cannot_remove_owner')];
            return false;
        }

        // Can't remove yourself this way (use leave instead)
        if ($targetUserId === $actingUserId) {
            self::$errors[] = ['code' => 'VALIDATION_ERROR', 'message' => __('api.group_use_leave_endpoint')];
            return false;
        }

        $detached = $group->members()->detach($targetUserId);

        if ($detached > 0) {
            $group->decrement('cached_member_count');
        }

        // Email removed member
        try {
            $tenantId = TenantContext::getId();
            $member = DB::table('users')
                ->where('id', $targetUserId)
                ->where('tenant_id', $tenantId)
                ->select(['email', 'first_name', 'name'])
                ->first();
            if ($member && !empty($member->email)) {
                $firstName = $member->first_name ?? $member->name ?? 'there';
                $community = TenantContext::getName();
                $groupName = htmlspecialchars($group->name, ENT_QUOTES, 'UTF-8');
                $browseUrl = TenantContext::getFrontendUrl() . TenantContext::getSlugPrefix() . '/groups';
                $html = EmailTemplateBuilder::make()
                    ->theme('warning')
                    ->title(__('emails_commerce.group_removed.title'))
                    ->greeting($firstName)
                    ->paragraph(__('emails_commerce.group_removed.body', ['group_name' => $groupName, 'community' => $community]))
                    ->paragraph(__('emails_commerce.group_removed.suggestion'))
                    ->button(__('emails_commerce.group_removed.cta'), $browseUrl)
                    ->render();
                Mailer::forCurrentTenant()->send(
                    $member->email,
                    __('emails_commerce.group_removed.subject', ['group_name' => $groupName, 'community' => $community]),
                    $html
                );
            }
        } catch (\Throwable $e) {
            Log::warning('[GroupService] group_removed email failed: ' . $e->getMessage());
        }

        // In-app bell to removed member
        Notification::create([
            'user_id'    => $targetUserId,
            'message'    => __('api_controllers_3.admin_bells.group_member_removed', ['group' => $group->name]),
            'link'       => '/groups',
            'type'       => 'group_member_removed',
            'created_at' => now(),
        ]);

        return true;
    }

    // -----------------------------------------------------------------
    //  Join requests
    // -----------------------------------------------------------------

    /**
     * Get pending join requests for a group (admin only).
     */
    public static function getPendingRequests(int $groupId, int $adminUserId): ?array
    {
        self::$errors = [];

        if (! self::canModify($groupId, $adminUserId)) {
            self::$errors[] = ['code' => 'FORBIDDEN', 'message' => __('api.group_view_join_requests_forbidden')];
            return null;
        }

        $pending = DB::table('group_members')
            ->join('users', 'group_members.user_id', '=', 'users.id')
            ->where('group_members.group_id', $groupId)
            ->where('group_members.status', 'pending')
            ->select(['users.id', 'users.first_name', 'users.last_name', 'users.avatar_url'])
            ->get();

        return $pending->map(fn ($p) => [
            'id'         => (int) $p->id,
            'name'       => trim(($p->first_name ?? '') . ' ' . ($p->last_name ?? '')),
            'avatar_url' => $p->avatar_url,
        ])->all();
    }

    /**
     * Handle a join request (accept/reject).
     */
    public static function handleJoinRequest(int $groupId, int $requesterId, int $adminUserId, string $action): bool
    {
        self::$errors = [];

        if (! in_array($action, ['accept', 'reject'])) {
            self::$errors[] = ['code' => 'VALIDATION_ERROR', 'message' => __('api.group_action_accept_or_reject'), 'field' => 'action'];
            return false;
        }

        if (! self::canModify($groupId, $adminUserId)) {
            self::$errors[] = ['code' => 'FORBIDDEN', 'message' => __('api.group_handle_join_requests_forbidden')];
            return false;
        }

        // Check requester has pending status
        $membership = DB::table('group_members')
            ->where('group_id', $groupId)
            ->where('user_id', $requesterId)
            ->first();

        if (! $membership || $membership->status !== 'pending') {
            self::$errors[] = ['code' => 'NOT_FOUND', 'message' => __('api.pending_request_not_found')];
            return false;
        }

        if ($action === 'accept') {
            DB::table('group_members')
                ->where('group_id', $groupId)
                ->where('user_id', $requesterId)
                ->update(['status' => 'active']);

            /** @var Group $group */
            $group = Group::query()->find($groupId);
            if ($group) {
                $group->increment('cached_member_count');
            }

            // Send welcome message + fire webhook + log audit
            try { GroupWelcomeService::sendWelcome($groupId, $requesterId); } catch (\Throwable $e) { \Log::warning('GroupService: failed to send welcome message after join request approval', ['group_id' => $groupId, 'requester_id' => $requesterId, 'error' => $e->getMessage()]); }
            try { GroupWebhookService::fire($groupId, GroupWebhookService::EVENT_MEMBER_JOINED, ['user_id' => $requesterId]); } catch (\Throwable $e) { \Log::warning('GroupService: failed to fire member_joined webhook after join request approval', ['group_id' => $groupId, 'requester_id' => $requesterId, 'error' => $e->getMessage()]); }
            try { GroupAuditService::log(GroupAuditService::ACTION_MEMBER_JOINED, $groupId, $requesterId, ['approved_by' => $adminUserId]); } catch (\Throwable $e) { \Log::warning('GroupService: failed to log member_joined audit after join request approval', ['group_id' => $groupId, 'requester_id' => $requesterId, 'error' => $e->getMessage()]); }
            try { GroupChallengeService::incrementProgress($groupId, 'members'); } catch (\Throwable $e) { \Log::warning('GroupService: failed to increment challenge progress for members after join request approval', ['group_id' => $groupId, 'error' => $e->getMessage()]); }
        } else {
            // Reject — remove the pending row
            DB::table('group_members')
                ->where('group_id', $groupId)
                ->where('user_id', $requesterId)
                ->delete();
        }

        return true;
    }

    // -----------------------------------------------------------------
    //  Discussions
    // -----------------------------------------------------------------

    /**
     * Get discussions in a group.
     */
    public static function getDiscussions(int $groupId, int $userId, array $filters = []): ?array
    {
        self::$errors = [];

        // Check membership — group_id FK to groups (which is tenant-scoped),
        // so group_id + user_id + status is sufficient. Some legacy data has
        // group_members.tenant_id=1 instead of the group's actual tenant.
        $isMember = DB::table('group_members')
            ->where('group_id', $groupId)
            ->where('user_id', $userId)
            ->where('status', 'active')
            ->exists();

        if (! $isMember) {
            self::$errors[] = ['code' => 'FORBIDDEN', 'message' => __('api.group_member_required_view_discussions')];
            return null;
        }

        $limit = min((int) ($filters['limit'] ?? 20), 100);
        $cursor = $filters['cursor'] ?? null;

        $query = GroupDiscussion::query()
            ->with(['user:id,first_name,last_name,avatar_url'])
            ->withCount('posts')
            ->where('group_id', $groupId);

        if ($cursor !== null) {
            $cursorId = base64_decode($cursor, true);
            if ($cursorId !== false && is_numeric($cursorId)) {
                $query->where('id', '<', (int) $cursorId);
            }
        }

        $query->orderByDesc('is_pinned')->orderByDesc('id');

        $discussions = $query->limit($limit + 1)->get();
        $hasMore = $discussions->count() > $limit;
        if ($hasMore) {
            $discussions->pop();
        }

        $items = $discussions->map(function (GroupDiscussion $d) {
            $user = $d->user;
            return [
                'id'            => $d->id,
                'title'         => $d->title,
                'author'        => [
                    'id'         => (int) $d->user_id,
                    'name'       => $user ? trim(($user->first_name ?? '') . ' ' . ($user->last_name ?? '')) : 'Unknown',
                    'avatar_url' => $user?->avatar_url,
                ],
                'reply_count'   => (int) ($d->posts_count ?? 0),
                'is_pinned'     => (bool) $d->is_pinned,
                'created_at'    => $d->created_at?->toISOString(),
                'last_reply_at' => null,
            ];
        })->all();

        return [
            'items'    => $items,
            'cursor'   => $hasMore && $discussions->isNotEmpty() ? base64_encode((string) $discussions->last()->id) : null,
            'has_more' => $hasMore,
        ];
    }

    /**
     * Create a discussion in a group.
     */
    public static function createDiscussion(int $groupId, int $userId, array $data): ?array
    {
        self::$errors = [];

        // Check membership — group_id FK to groups (which is tenant-scoped),
        // so group_id + user_id + status is sufficient. Some legacy data has
        // group_members.tenant_id=1 instead of the group's actual tenant.
        $isMember = DB::table('group_members')
            ->where('group_id', $groupId)
            ->where('user_id', $userId)
            ->where('status', 'active')
            ->exists();

        if (! $isMember) {
            self::$errors[] = ['code' => 'FORBIDDEN', 'message' => __('api.group_member_required_create_discussions')];
            return null;
        }

        $title = trim($data['title'] ?? '');
        $content = trim($data['content'] ?? '');

        if (empty($title)) {
            self::$errors[] = ['code' => 'VALIDATION_ERROR', 'message' => __('api.title_required'), 'field' => 'title'];
            return null;
        }

        if (empty($content)) {
            self::$errors[] = ['code' => 'VALIDATION_ERROR', 'message' => __('api.group_content_required'), 'field' => 'content'];
            return null;
        }

        // Sanitize to prevent XSS — strip HTML tags from title, allow basic formatting in content
        $title = strip_tags($title);
        $content = strip_tags($content, '<p><br><b><i><strong><em><ul><ol><li><a><blockquote>');

        return DB::transaction(function () use ($groupId, $userId, $title, $content) {
            $discussion = GroupDiscussion::create([
                'group_id' => $groupId,
                'user_id'  => $userId,
                'title'    => $title,
            ]);

            GroupPost::create([
                'discussion_id' => $discussion->id,
                'user_id'       => $userId,
                'content'       => $content,
            ]);

            $discussion->load('user:id,first_name,last_name,avatar_url');
            $user = $discussion->user;

            // Fire integrations
            try { GroupWebhookService::fire($groupId, GroupWebhookService::EVENT_DISCUSSION_CREATED, ['discussion_id' => $discussion->id, 'title' => $title]); } catch (\Throwable $e) { \Log::warning('GroupService: failed to fire discussion_created webhook', ['group_id' => $groupId, 'discussion_id' => $discussion->id, 'error' => $e->getMessage()]); }
            try { GroupAuditService::log(GroupAuditService::ACTION_DISCUSSION_CREATED, $groupId, $userId, ['discussion_id' => $discussion->id]); } catch (\Throwable $e) { \Log::warning('GroupService: failed to log discussion_created audit', ['group_id' => $groupId, 'discussion_id' => $discussion->id, 'error' => $e->getMessage()]); }
            try { GroupChallengeService::incrementProgress($groupId, 'discussions'); } catch (\Throwable $e) { \Log::warning('GroupService: failed to increment challenge progress for discussions', ['group_id' => $groupId, 'error' => $e->getMessage()]); }
            try { GroupMentionService::notifyMentioned($groupId, $userId, $content, 'discussion', $discussion->id); } catch (\Throwable $e) { \Log::warning('GroupService: failed to notify mentioned users in discussion', ['group_id' => $groupId, 'discussion_id' => $discussion->id, 'error' => $e->getMessage()]); }

            return [
                'id'            => $discussion->id,
                'title'         => $discussion->title,
                'content'       => $content,
                'author'        => [
                    'id'         => (int) $discussion->user_id,
                    'name'       => $user ? trim(($user->first_name ?? '') . ' ' . ($user->last_name ?? '')) : 'Unknown',
                    'avatar_url' => $user?->avatar_url,
                ],
                'reply_count'   => 0,
                'is_pinned'     => false,
                'created_at'    => $discussion->created_at?->toISOString(),
                'last_reply_at' => null,
            ];
        });
    }

    /**
     * Get messages in a group discussion.
     */
    public static function getDiscussionMessages(int $groupId, int $discussionId, int $userId, array $filters = []): ?array
    {
        self::$errors = [];

        // Check membership
        $isMember = DB::table('group_members')
            ->where('group_id', $groupId)
            ->where('user_id', $userId)
            ->where('status', 'active')
            ->exists();

        if (! $isMember) {
            self::$errors[] = ['code' => 'FORBIDDEN', 'message' => __('api.group_member_required_view_discussions')];
            return null;
        }

        // Verify discussion belongs to group
        $discussion = GroupDiscussion::query()
            ->with(['user:id,first_name,last_name,avatar_url'])
            ->where('group_id', $groupId)
            ->find($discussionId);

        if (! $discussion) {
            self::$errors[] = ['code' => 'NOT_FOUND', 'message' => __('api.group_discussion_not_found')];
            return null;
        }

        $limit = min((int) ($filters['limit'] ?? 50), 100);
        $cursor = $filters['cursor'] ?? null;

        $query = GroupPost::query()
            ->with(['user:id,first_name,last_name,avatar_url'])
            ->where('discussion_id', $discussionId);

        if ($cursor !== null) {
            $cursorId = base64_decode($cursor, true);
            if ($cursorId !== false && is_numeric($cursorId)) {
                $query->where('id', '>', (int) $cursorId);
            }
        }

        $query->orderBy('id');

        $posts = $query->limit($limit + 1)->get();
        $hasMore = $posts->count() > $limit;
        if ($hasMore) {
            $posts->pop();
        }

        $items = $posts->map(function (GroupPost $p) use ($userId) {
            $user = $p->user;
            return [
                'id'         => $p->id,
                'content'    => $p->content,
                'author'     => [
                    'id'         => (int) $p->user_id,
                    'name'       => $user ? trim(($user->first_name ?? '') . ' ' . ($user->last_name ?? '')) : 'Unknown',
                    'avatar_url' => $user?->avatar_url,
                ],
                'is_own'     => (int) $p->user_id === $userId,
                'created_at' => $p->created_at?->toISOString(),
            ];
        })->all();

        // Get metadata
        $totalReplies = GroupPost::withoutGlobalScopes()
            ->where('discussion_id', $discussionId)
            ->count();

        $firstContent = GroupPost::withoutGlobalScopes()
            ->where('discussion_id', $discussionId)
            ->orderBy('id')
            ->value('content');

        $lastReplyAt = GroupPost::withoutGlobalScopes()
            ->where('discussion_id', $discussionId)
            ->max('created_at');

        $user = $discussion->user;

        return [
            'discussion' => [
                'id'            => $discussion->id,
                'title'         => $discussion->title,
                'content'       => $firstContent ?? '',
                'author'        => [
                    'id'         => (int) $discussion->user_id,
                    'name'       => $user ? trim(($user->first_name ?? '') . ' ' . ($user->last_name ?? '')) : 'Unknown',
                    'avatar_url' => $user?->avatar_url,
                ],
                'reply_count'   => $totalReplies,
                'is_pinned'     => (bool) $discussion->is_pinned,
                'created_at'    => $discussion->created_at?->toISOString(),
                'last_reply_at' => $lastReplyAt,
            ],
            'items'    => $items,
            'cursor'   => $hasMore && $posts->isNotEmpty() ? base64_encode((string) $posts->last()->id) : null,
            'has_more' => $hasMore,
        ];
    }

    /**
     * Post a message to a group discussion.
     */
    public static function postToDiscussion(int $groupId, int $discussionId, int $userId, array $data): ?array
    {
        self::$errors = [];

        // Check membership
        $isMember = DB::table('group_members')
            ->where('group_id', $groupId)
            ->where('user_id', $userId)
            ->where('status', 'active')
            ->exists();

        if (! $isMember) {
            self::$errors[] = ['code' => 'FORBIDDEN', 'message' => __('api.group_member_required_post')];
            return null;
        }

        // Verify discussion belongs to group
        $discussion = GroupDiscussion::query()
            ->where('group_id', $groupId)
            ->find($discussionId);

        if (! $discussion) {
            self::$errors[] = ['code' => 'NOT_FOUND', 'message' => __('api.group_discussion_not_found')];
            return null;
        }

        $content = trim($data['content'] ?? '');
        if (empty($content)) {
            self::$errors[] = ['code' => 'VALIDATION_ERROR', 'message' => __('api.group_content_required'), 'field' => 'content'];
            return null;
        }

        // Sanitize to prevent XSS — allow basic formatting tags
        $content = strip_tags($content, '<p><br><b><i><strong><em><ul><ol><li><a><blockquote>');

        $post = GroupPost::create([
            'discussion_id' => $discussionId,
            'user_id'       => $userId,
            'content'       => $content,
        ]);

        // Fire integrations
        try { GroupWebhookService::fire($groupId, GroupWebhookService::EVENT_POST_CREATED, ['post_id' => $post->id, 'discussion_id' => $discussionId]); } catch (\Throwable $e) { \Log::warning('GroupService: failed to fire post_created webhook', ['group_id' => $groupId, 'post_id' => $post->id, 'error' => $e->getMessage()]); }
        try { GroupAuditService::log(GroupAuditService::ACTION_POST_CREATED, $groupId, $userId, ['post_id' => $post->id]); } catch (\Throwable $e) { \Log::warning('GroupService: failed to log post_created audit', ['group_id' => $groupId, 'post_id' => $post->id, 'error' => $e->getMessage()]); }
        try { GroupChallengeService::incrementProgress($groupId, 'posts'); } catch (\Throwable $e) { \Log::warning('GroupService: failed to increment challenge progress for posts', ['group_id' => $groupId, 'error' => $e->getMessage()]); }
        try { GroupMentionService::notifyMentioned($groupId, $userId, $content, 'post', $post->id); } catch (\Throwable $e) { \Log::warning('GroupService: failed to notify mentioned users in post', ['group_id' => $groupId, 'post_id' => $post->id, 'error' => $e->getMessage()]); }

        $post->load('user:id,first_name,last_name,avatar_url');
        $user = $post->user;

        return [
            'id'         => $post->id,
            'content'    => $post->content,
            'author'     => [
                'id'         => (int) $post->user_id,
                'name'       => $user ? trim(($user->first_name ?? '') . ' ' . ($user->last_name ?? '')) : 'Unknown',
                'avatar_url' => $user?->avatar_url,
            ],
            'is_own'     => true,
            'created_at' => $post->created_at?->toISOString(),
        ];
    }

    // -----------------------------------------------------------------
    //  Images
    // -----------------------------------------------------------------

    /**
     * Update a group's image (avatar or cover).
     */
    public static function updateImage(int $groupId, int $userId, string $imageUrl, string $type = 'avatar'): bool
    {
        self::$errors = [];

        if (! self::canModify($groupId, $userId)) {
            self::$errors[] = ['code' => 'FORBIDDEN', 'message' => __('api.group_modify_forbidden')];
            return false;
        }

        /** @var Group|null $group */
        $group = Group::query()->find($groupId);

        if (! $group) {
            return false;
        }

        $field = $type === 'cover' ? 'cover_image_url' : 'image_url';
        $group->{$field} = $imageUrl;
        $group->save();

        return true;
    }

    // -----------------------------------------------------------------
    //  Helpers
    // -----------------------------------------------------------------

    /**
     * Check if a user can modify a group (is admin/owner).
     */
    private static function canModify(int $groupId, int $userId): bool
    {
        // Check if platform admin
        if (self::isPlatformAdmin($userId)) {
            return true;
        }

        $membership = DB::table('group_members')
            ->where('group_id', $groupId)
            ->where('user_id', $userId)
            ->where('status', 'active')
            ->first();

        return $membership && in_array($membership->role, ['owner', 'admin']);
    }

    /**
     * Check if user is a platform admin.
     */
    private static function isPlatformAdmin(int $userId): bool
    {
        $user = User::find($userId);
        if (! $user) {
            return false;
        }

        $role = $user->role ?? '';
        return in_array($role, ['admin', 'super_admin', 'god'])
            || $user->is_super_admin
            || $user->is_tenant_super_admin;
    }
}
