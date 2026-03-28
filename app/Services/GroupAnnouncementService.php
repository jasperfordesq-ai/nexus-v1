<?php
// Copyright © 2024–2026 Jasper Ford
// SPDX-License-Identifier: AGPL-3.0-or-later
// Author: Jasper Ford
// See NOTICE file for attribution and acknowledgements.

namespace App\Services;

use Illuminate\Support\Facades\DB;
use App\Core\TenantContext;

/**
 * GroupAnnouncementService — Laravel DI-based service for group announcements.
 *
 */
class GroupAnnouncementService
{
    private array $errors = [];

    public function getErrors(): array
    {
        return $this->errors;
    }

    /**
     * List announcements for a group.
     */
    public function list(int $groupId, int $userId, array $filters = []): ?array
    {
        $this->errors = [];
        $tenantId = TenantContext::getId();

        if (!$this->isMember($groupId, $userId, $tenantId)) {
            $this->errors[] = ['code' => 'FORBIDDEN', 'message' => 'You must be a member to view announcements'];
            return null;
        }

        $limit = min($filters['limit'] ?? 20, 100);
        $cursor = $filters['cursor'] ?? null;
        $includeExpired = $filters['include_expired'] ?? false;

        $cursorId = null;
        if ($cursor) {
            $decoded = base64_decode($cursor, true);
            if ($decoded && is_numeric($decoded)) {
                $cursorId = (int) $decoded;
            }
        }

        $query = DB::table('group_announcements as ga')
            ->join('users as u', 'ga.created_by', '=', 'u.id')
            ->where('ga.group_id', $groupId)
            ->where('ga.tenant_id', $tenantId)
            ->select('ga.*', 'u.name as author_name', 'u.avatar_url as author_avatar');

        if (!$includeExpired) {
            $query->where(function ($q) {
                $q->whereNull('ga.expires_at')
                  ->orWhere('ga.expires_at', '>', now());
            });
        }

        if ($cursorId) {
            $query->where('ga.id', '<', $cursorId);
        }

        $announcements = $query
            ->orderByDesc('ga.is_pinned')
            ->orderByDesc('ga.priority')
            ->orderByDesc('ga.created_at')
            ->orderByDesc('ga.id')
            ->limit($limit + 1)
            ->get()
            ->map(fn ($r) => (array) $r)
            ->all();

        $hasMore = count($announcements) > $limit;
        if ($hasMore) {
            array_pop($announcements);
        }

        $items = [];
        $lastId = null;

        foreach ($announcements as $a) {
            $lastId = $a['id'];
            $items[] = $this->formatAnnouncement($a);
        }

        return [
            'items' => $items,
            'cursor' => $hasMore && $lastId ? base64_encode((string) $lastId) : null,
            'has_more' => $hasMore,
        ];
    }

    /**
     * Get a single announcement.
     */
    public function getById(int $groupId, int $announcementId, int $userId): ?array
    {
        $this->errors = [];
        $tenantId = TenantContext::getId();

        if (!$this->isMember($groupId, $userId, $tenantId)) {
            $this->errors[] = ['code' => 'FORBIDDEN', 'message' => 'You must be a member to view announcements'];
            return null;
        }

        $row = DB::table('group_announcements as ga')
            ->join('users as u', 'ga.created_by', '=', 'u.id')
            ->where('ga.id', $announcementId)
            ->where('ga.group_id', $groupId)
            ->where('ga.tenant_id', $tenantId)
            ->select('ga.*', 'u.name as author_name', 'u.avatar_url as author_avatar')
            ->first();

        if (!$row) {
            $this->errors[] = ['code' => 'NOT_FOUND', 'message' => 'Announcement not found'];
            return null;
        }

        return $this->formatAnnouncement((array) $row);
    }

    /**
     * Create an announcement (admin only).
     */
    public function create(int $groupId, int $userId, array $data): ?array
    {
        $this->errors = [];
        $tenantId = TenantContext::getId();

        if (!$this->isAdmin($groupId, $userId, $tenantId)) {
            $this->errors[] = ['code' => 'FORBIDDEN', 'message' => 'Only group admins can create announcements'];
            return null;
        }

        $title = trim($data['title'] ?? '');
        $content = trim($data['content'] ?? '');

        if (empty($title)) {
            $this->errors[] = ['code' => 'VALIDATION_ERROR', 'message' => 'Title is required', 'field' => 'title'];
            return null;
        }
        if (mb_strlen($title) > 255) {
            $this->errors[] = ['code' => 'VALIDATION_ERROR', 'message' => 'Title must be 255 characters or less', 'field' => 'title'];
            return null;
        }
        if (empty($content)) {
            $this->errors[] = ['code' => 'VALIDATION_ERROR', 'message' => 'Content is required', 'field' => 'content'];
            return null;
        }

        $isPinned = (bool) ($data['is_pinned'] ?? false);
        $priority = max(0, (int) ($data['priority'] ?? 0));
        $expiresAt = !empty($data['expires_at']) ? date('Y-m-d H:i:s', strtotime($data['expires_at'])) : null;

        try {
            $id = DB::table('group_announcements')->insertGetId([
                'group_id'   => $groupId,
                'tenant_id'  => $tenantId,
                'title'      => $title,
                'content'    => $content,
                'is_pinned'  => $isPinned ? 1 : 0,
                'priority'   => $priority,
                'created_by' => $userId,
                'created_at' => now(),
                'expires_at' => $expiresAt,
            ]);

            return $this->getById($groupId, $id, $userId);
        } catch (\Throwable $e) {
            $this->errors[] = ['code' => 'SERVER_ERROR', 'message' => 'Failed to create announcement'];
            return null;
        }
    }

    /**
     * Update an announcement (admin only).
     */
    public function update(int $groupId, int $announcementId, int $userId, array $data): ?array
    {
        $this->errors = [];
        $tenantId = TenantContext::getId();

        if (!$this->isAdmin($groupId, $userId, $tenantId)) {
            $this->errors[] = ['code' => 'FORBIDDEN', 'message' => 'Only group admins can update announcements'];
            return null;
        }

        $exists = DB::table('group_announcements')
            ->where('id', $announcementId)
            ->where('group_id', $groupId)
            ->where('tenant_id', $tenantId)
            ->exists();

        if (!$exists) {
            $this->errors[] = ['code' => 'NOT_FOUND', 'message' => 'Announcement not found'];
            return null;
        }

        $updates = [];

        if (array_key_exists('title', $data)) {
            $title = trim($data['title']);
            if (empty($title)) {
                $this->errors[] = ['code' => 'VALIDATION_ERROR', 'message' => 'Title is required', 'field' => 'title'];
                return null;
            }
            $updates['title'] = $title;
        }
        if (array_key_exists('content', $data)) {
            $content = trim($data['content']);
            if (empty($content)) {
                $this->errors[] = ['code' => 'VALIDATION_ERROR', 'message' => 'Content is required', 'field' => 'content'];
                return null;
            }
            $updates['content'] = $content;
        }
        if (array_key_exists('is_pinned', $data)) {
            $updates['is_pinned'] = (bool) $data['is_pinned'] ? 1 : 0;
        }
        if (array_key_exists('priority', $data)) {
            $updates['priority'] = max(0, (int) $data['priority']);
        }
        if (array_key_exists('expires_at', $data)) {
            $updates['expires_at'] = $data['expires_at'] ? date('Y-m-d H:i:s', strtotime($data['expires_at'])) : null;
        }

        if (!empty($updates)) {
            $updates['updated_at'] = now();
            DB::table('group_announcements')
                ->where('id', $announcementId)
                ->where('tenant_id', $tenantId)
                ->update($updates);
        }

        return $this->getById($groupId, $announcementId, $userId);
    }

    /**
     * Delete an announcement (admin only).
     */
    public function delete(int $groupId, int $announcementId, int $userId): bool
    {
        $this->errors = [];
        $tenantId = TenantContext::getId();

        if (!$this->isAdmin($groupId, $userId, $tenantId)) {
            $this->errors[] = ['code' => 'FORBIDDEN', 'message' => 'Only group admins can delete announcements'];
            return false;
        }

        $deleted = DB::table('group_announcements')
            ->where('id', $announcementId)
            ->where('group_id', $groupId)
            ->where('tenant_id', $tenantId)
            ->delete();

        if ($deleted === 0) {
            $this->errors[] = ['code' => 'NOT_FOUND', 'message' => 'Announcement not found'];
            return false;
        }

        return true;
    }

    /**
     * Check if user is a member of the group.
     */
    private function isMember(int $groupId, int $userId, int $tenantId): bool
    {
        return DB::table('group_members')
            ->where('group_id', $groupId)
            ->where('user_id', $userId)
            ->exists();
    }

    /**
     * Check if user is an admin of the group (admin/owner role).
     */
    private function isAdmin(int $groupId, int $userId, int $tenantId): bool
    {
        // Group owner is always admin
        $isOwner = DB::table('groups')
            ->where('id', $groupId)
            ->where('tenant_id', $tenantId)
            ->where('owner_id', $userId)
            ->exists();

        if ($isOwner) {
            return true;
        }

        return DB::table('group_members')
            ->where('group_id', $groupId)
            ->where('user_id', $userId)
            ->whereIn('role', ['admin', 'owner'])
            ->exists();
    }

    /**
     * Format announcement for API response.
     */
    private function formatAnnouncement(array $a): array
    {
        $isExpired = !empty($a['expires_at']) && strtotime($a['expires_at']) < time();

        return [
            'id' => (int) $a['id'],
            'title' => $a['title'],
            'content' => $a['content'],
            'is_pinned' => (bool) $a['is_pinned'],
            'priority' => (int) $a['priority'],
            'is_expired' => $isExpired,
            'author' => [
                'id' => (int) $a['created_by'],
                'name' => $a['author_name'] ?? null,
                'avatar_url' => $a['author_avatar'] ?? null,
            ],
            'created_at' => $a['created_at'],
            'updated_at' => $a['updated_at'] ?? null,
            'expires_at' => $a['expires_at'] ?? null,
        ];
    }
}
