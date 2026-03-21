<?php
// Copyright © 2024–2026 Jasper Ford
// SPDX-License-Identifier: AGPL-3.0-or-later
// Author: Jasper Ford
// See NOTICE file for attribution and acknowledgements.

namespace App\Services;

use App\Core\SuperPanelAccess;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;

/**
 * SuperAdminAuditService — Native Laravel implementation.
 *
 * Logs and retrieves audit trail entries for the Super Admin Panel.
 * All entries are stored in the `super_admin_audit_log` table.
 */
class SuperAdminAuditService
{
    /**
     * Human-readable labels for each action type.
     */
    private const ACTION_LABELS = [
        'tenant_created'          => 'Tenant Created',
        'tenant_updated'          => 'Tenant Updated',
        'tenant_deleted'          => 'Tenant Deleted',
        'tenant_moved'            => 'Tenant Moved',
        'hub_toggled'             => 'Hub Toggled',
        'super_admin_granted'     => 'Super Admin Granted',
        'super_admin_revoked'     => 'Super Admin Revoked',
        'user_created'            => 'User Created',
        'user_updated'            => 'User Updated',
        'user_moved'              => 'User Moved',
        'bulk_users_moved'        => 'Bulk Users Moved',
        'bulk_tenants_updated'    => 'Bulk Tenants Updated',
    ];

    /**
     * FontAwesome icon class for each action type.
     */
    private const ACTION_ICONS = [
        'tenant_created'          => 'fa-plus-circle',
        'tenant_updated'          => 'fa-pen',
        'tenant_deleted'          => 'fa-trash',
        'tenant_moved'            => 'fa-arrows-alt',
        'hub_toggled'             => 'fa-toggle-on',
        'super_admin_granted'     => 'fa-user-shield',
        'super_admin_revoked'     => 'fa-user-slash',
        'user_created'            => 'fa-user-plus',
        'user_updated'            => 'fa-user-edit',
        'user_moved'              => 'fa-exchange-alt',
        'bulk_users_moved'        => 'fa-users',
        'bulk_tenants_updated'    => 'fa-building',
    ];

    public function __construct()
    {
    }

    /**
     * Log an audit entry to super_admin_audit_log.
     */
    public static function log(
        string $actionType,
        string $targetType,
        ?int $targetId = null,
        ?string $targetName = null,
        ?array $oldValues = null,
        ?array $newValues = null,
        ?string $description = null
    ): bool {
        try {
            $access = SuperPanelAccess::getAccess();
            $actorUserId = $access['user_id'] ?? ($_SESSION['user_id'] ?? 0);
            $actorTenantId = $access['tenant_id'] ?? ($_SESSION['tenant_id'] ?? 0);

            // Resolve actor name and email
            $actor = null;
            if ($actorUserId) {
                $actor = DB::selectOne(
                    "SELECT first_name, last_name, email FROM users WHERE id = ?",
                    [$actorUserId]
                );
            }

            $actorName = $actor
                ? trim(($actor->first_name ?? '') . ' ' . ($actor->last_name ?? ''))
                : 'System';
            $actorEmail = $actor->email ?? 'system@project-nexus.ie';

            DB::table('super_admin_audit_log')->insert([
                'actor_user_id'  => $actorUserId,
                'actor_tenant_id' => $actorTenantId,
                'actor_name'     => $actorName,
                'actor_email'    => $actorEmail,
                'action_type'    => $actionType,
                'target_type'    => $targetType,
                'target_id'      => $targetId,
                'target_name'    => $targetName,
                'old_values'     => $oldValues ? json_encode($oldValues) : null,
                'new_values'     => $newValues ? json_encode($newValues) : null,
                'description'    => $description,
                'ip_address'     => request()->ip(),
                'user_agent'     => request()->userAgent(),
                'created_at'     => now(),
            ]);

            return true;
        } catch (\Throwable $e) {
            Log::error('SuperAdminAuditService::log failed', [
                'error' => $e->getMessage(),
                'action_type' => $actionType,
            ]);
            return false;
        }
    }

    /**
     * Retrieve audit log entries with optional filtering.
     *
     * Supported filters: action_type, target_type, search, date_from, date_to, limit, offset.
     *
     * @return array<int, array>
     */
    public static function getLog(array $filters = []): array
    {
        try {
            $query = DB::table('super_admin_audit_log')
                ->orderByDesc('created_at');

            // Scope by access level — regional admins only see their subtree
            $access = SuperPanelAccess::getAccess();
            if ($access['granted'] && $access['level'] === 'regional' && !empty($access['tenant_path'])) {
                $tenantPath = $access['tenant_path'];
                // Get all tenant IDs in the subtree
                $subtreeIds = DB::table('tenants')
                    ->where('path', 'LIKE', $tenantPath . '%')
                    ->pluck('id')
                    ->all();

                if (!empty($subtreeIds)) {
                    $query->whereIn('actor_tenant_id', $subtreeIds);
                }
            }

            if (!empty($filters['action_type'])) {
                $query->where('action_type', $filters['action_type']);
            }

            if (!empty($filters['target_type'])) {
                $query->where('target_type', $filters['target_type']);
            }

            if (!empty($filters['search'])) {
                $search = '%' . $filters['search'] . '%';
                $query->where(function ($q) use ($search) {
                    $q->where('description', 'LIKE', $search)
                      ->orWhere('target_name', 'LIKE', $search)
                      ->orWhere('actor_name', 'LIKE', $search)
                      ->orWhere('actor_email', 'LIKE', $search);
                });
            }

            if (!empty($filters['date_from'])) {
                $query->where('created_at', '>=', $filters['date_from'] . ' 00:00:00');
            }

            if (!empty($filters['date_to'])) {
                $query->where('created_at', '<=', $filters['date_to'] . ' 23:59:59');
            }

            $limit = (int) ($filters['limit'] ?? 50);
            $offset = (int) ($filters['offset'] ?? 0);

            $rows = $query->limit($limit)->offset($offset)->get();

            return $rows->map(function ($row) {
                $row = (array) $row;
                // Decode JSON fields
                $row['old_values'] = $row['old_values'] ? json_decode($row['old_values'], true) : null;
                $row['new_values'] = $row['new_values'] ? json_decode($row['new_values'], true) : null;
                return $row;
            })->all();
        } catch (\Throwable $e) {
            Log::error('SuperAdminAuditService::getLog failed', ['error' => $e->getMessage()]);
            return [];
        }
    }

    /**
     * Get audit statistics for the last N days.
     *
     * Returns: total_actions, by_type (array of action_type + count), top_actors.
     */
    public static function getStats(int $days = 30): array
    {
        try {
            $since = now()->subDays($days)->toDateTimeString();

            $baseQuery = DB::table('super_admin_audit_log')
                ->where('created_at', '>=', $since);

            // Scope for regional admins
            $access = SuperPanelAccess::getAccess();
            if ($access['granted'] && $access['level'] === 'regional' && !empty($access['tenant_path'])) {
                $subtreeIds = DB::table('tenants')
                    ->where('path', 'LIKE', $access['tenant_path'] . '%')
                    ->pluck('id')
                    ->all();
                if (!empty($subtreeIds)) {
                    $baseQuery->whereIn('actor_tenant_id', $subtreeIds);
                }
            }

            $totalActions = (clone $baseQuery)->count();

            $byType = (clone $baseQuery)
                ->select('action_type', DB::raw('COUNT(*) as count'))
                ->groupBy('action_type')
                ->orderByDesc('count')
                ->get()
                ->map(fn($row) => (array) $row)
                ->all();

            $topActors = (clone $baseQuery)
                ->select('actor_user_id', 'actor_name', 'actor_email', DB::raw('COUNT(*) as action_count'))
                ->groupBy('actor_user_id', 'actor_name', 'actor_email')
                ->orderByDesc('action_count')
                ->limit(10)
                ->get()
                ->map(fn($row) => (array) $row)
                ->all();

            return [
                'total_actions' => $totalActions,
                'by_type' => $byType,
                'top_actors' => $topActors,
            ];
        } catch (\Throwable $e) {
            Log::error('SuperAdminAuditService::getStats failed', ['error' => $e->getMessage()]);
            return [
                'total_actions' => 0,
                'by_type' => [],
                'top_actors' => [],
            ];
        }
    }

    /**
     * Get a human-readable label for an action type.
     */
    public static function getActionLabel(string $actionType): string
    {
        return self::ACTION_LABELS[$actionType] ?? ucwords(str_replace('_', ' ', $actionType));
    }

    /**
     * Get a FontAwesome icon class for an action type.
     */
    public static function getActionIcon(string $actionType): string
    {
        return self::ACTION_ICONS[$actionType] ?? 'fa-circle';
    }
}
