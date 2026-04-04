<?php
// Copyright © 2024–2026 Jasper Ford
// SPDX-License-Identifier: AGPL-3.0-or-later
// Author: Jasper Ford
// See NOTICE file for attribution and acknowledgements.

namespace App\Services;

use Illuminate\Support\Facades\DB;
use App\Core\TenantContext;

/**
 * GroupDataExportService — GDPR-compliant data export for groups.
 *
 * Exports all group data: members, discussions, files, settings, custom fields.
 * Supports JSON and CSV formats.
 */
class GroupDataExportService
{
    /**
     * Export all data for a group as a structured array.
     */
    public static function exportAll(int $groupId): array
    {
        $tenantId = TenantContext::getId();

        $group = DB::table('groups')
            ->where('id', $groupId)
            ->where('tenant_id', $tenantId)
            ->first();

        if (!$group) return [];

        return [
            'export_date' => now()->toIso8601String(),
            'group' => (array) $group,
            'members' => self::exportMembers($groupId, $tenantId),
            'discussions' => self::exportDiscussions($groupId, $tenantId),
            'announcements' => self::exportAnnouncements($groupId, $tenantId),
            'files' => self::exportFileMetadata($groupId, $tenantId),
            'events' => self::exportEvents($groupId, $tenantId),
            'tags' => GroupTagService::getForGroup($groupId),
            'custom_fields' => GroupCustomFieldService::getValues($groupId),
            'settings' => self::exportSettings($groupId, $tenantId),
        ];
    }

    private static function exportMembers(int $groupId, int $tenantId): array
    {
        return DB::table('group_members as gm')
            ->join('users as u', 'gm.user_id', '=', 'u.id')
            ->where('gm.group_id', $groupId)
            ->where('u.tenant_id', $tenantId)
            ->select('u.id', 'u.name', 'u.email', 'gm.role', 'gm.status', 'gm.created_at as joined_at')
            ->get()
            ->map(fn ($row) => (array) $row)
            ->toArray();
    }

    private static function exportDiscussions(int $groupId, int $tenantId): array
    {
        $discussions = DB::table('group_discussions as gd')
            ->join('users as u', 'gd.user_id', '=', 'u.id')
            ->where('gd.group_id', $groupId)
            ->where('gd.tenant_id', $tenantId)
            ->select('gd.id', 'gd.title', 'gd.is_pinned', 'u.name as author', 'gd.created_at')
            ->orderBy('gd.created_at')
            ->get()
            ->toArray();

        foreach ($discussions as &$discussion) {
            $discussion = (array) $discussion;
            $discussion['posts'] = DB::table('group_posts as gp')
                ->join('users as u', 'gp.user_id', '=', 'u.id')
                ->where('gp.discussion_id', $discussion['id'])
                ->where('gp.tenant_id', $tenantId)
                ->select('gp.content', 'u.name as author', 'gp.created_at')
                ->orderBy('gp.created_at')
                ->get()
                ->map(fn ($row) => (array) $row)
                ->toArray();
        }

        return $discussions;
    }

    private static function exportAnnouncements(int $groupId, int $tenantId): array
    {
        return DB::table('group_announcements as ga')
            ->leftJoin('users as u', 'ga.created_by', '=', 'u.id')
            ->where('ga.group_id', $groupId)
            ->where('ga.tenant_id', $tenantId)
            ->select('ga.title', 'ga.content', 'ga.is_pinned', 'ga.priority', 'u.name as author', 'ga.created_at', 'ga.expires_at')
            ->orderBy('ga.created_at')
            ->get()
            ->map(fn ($row) => (array) $row)
            ->toArray();
    }

    private static function exportFileMetadata(int $groupId, int $tenantId): array
    {
        return DB::table('group_files as gf')
            ->leftJoin('users as u', 'gf.uploaded_by', '=', 'u.id')
            ->where('gf.group_id', $groupId)
            ->where('gf.tenant_id', $tenantId)
            ->select('gf.file_name', 'gf.file_type', 'gf.file_size', 'u.name as uploaded_by', 'gf.created_at')
            ->get()
            ->map(fn ($row) => (array) $row)
            ->toArray();
    }

    private static function exportEvents(int $groupId, int $tenantId): array
    {
        return DB::table('events')
            ->where('group_id', $groupId)
            ->where('tenant_id', $tenantId)
            ->select('id', 'title', 'description', 'start_time', 'end_time', 'location', 'created_at')
            ->get()
            ->map(fn ($row) => (array) $row)
            ->toArray();
    }

    private static function exportSettings(int $groupId, int $tenantId): array
    {
        return DB::table('group_policies')
            ->where('tenant_id', $tenantId)
            ->where('policy_key', 'LIKE', '%_' . $groupId)
            ->select('policy_key', 'policy_value', 'category')
            ->get()
            ->map(fn ($row) => (array) $row)
            ->toArray();
    }

    /**
     * Generate CSV content from an array of rows.
     */
    public static function toCsv(array $rows): string
    {
        if (empty($rows)) return '';

        $output = fopen('php://temp', 'r+');
        fputcsv($output, array_keys($rows[0]));

        foreach ($rows as $row) {
            fputcsv($output, array_values($row));
        }

        rewind($output);
        $csv = stream_get_contents($output);
        fclose($output);

        return $csv;
    }
}
