<?php
// Copyright © 2024–2026 Jasper Ford
// SPDX-License-Identifier: AGPL-3.0-or-later
// Author: Jasper Ford
// See NOTICE file for attribution and acknowledgements.

namespace App\Http\Controllers\Api;

use Illuminate\Http\JsonResponse;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use App\Core\TenantContext;
use App\Models\User;
use App\Services\GamificationService;
use App\Services\AchievementCampaignService;
use App\Services\BadgeDefinitionService;

/**
 * AdminGamificationController -- Admin gamification: stats, badges, campaigns, bulk awards.
 *
 * All methods require admin authentication.
 */
class AdminGamificationController extends BaseApiController
{
    protected bool $isV2Api = true;

    public function __construct(
        private readonly GamificationService $gamificationService,
        private readonly AchievementCampaignService $achievementCampaignService,
    ) {}

    /** GET /api/v2/admin/gamification/stats */
    public function stats(): JsonResponse
    {
        $this->requireAdmin();
        $tenantId = $this->getTenantId();

        $totalBadgesAwarded = 0;
        $activeUsers = 0;
        $totalXp = 0;
        $activeCampaigns = 0;
        $badgeDistribution = [];

        try { $totalBadgesAwarded = (int) DB::selectOne("SELECT COUNT(*) as cnt FROM user_badges ub JOIN users u ON ub.user_id = u.id WHERE u.tenant_id = ?", [$tenantId])->cnt; } catch (\Throwable $e) {}
        try { $activeUsers = (int) DB::selectOne("SELECT COUNT(DISTINCT ub.user_id) as cnt FROM user_badges ub JOIN users u ON ub.user_id = u.id WHERE u.tenant_id = ?", [$tenantId])->cnt; } catch (\Throwable $e) {}
        try { $totalXp = (int) DB::selectOne("SELECT COALESCE(SUM(xp), 0) as total FROM users WHERE tenant_id = ?", [$tenantId])->total; } catch (\Throwable $e) {}
        try { $activeCampaigns = (int) DB::selectOne("SELECT COUNT(*) as cnt FROM achievement_campaigns WHERE tenant_id = ? AND status = 'active'", [$tenantId])->cnt; } catch (\Throwable $e) {}

        try {
            $rows = DB::select(
                "SELECT ub.name as badge_name, COUNT(*) as count FROM user_badges ub JOIN users u ON ub.user_id = u.id
                 WHERE u.tenant_id = ? GROUP BY ub.badge_key, ub.name ORDER BY count DESC LIMIT 10",
                [$tenantId]
            );
            $badgeDistribution = array_map(fn($r) => ['badge_name' => $r->badge_name, 'count' => (int) $r->count], $rows);
        } catch (\Throwable $e) {}

        return $this->respondWithData([
            'total_badges_awarded' => $totalBadgesAwarded,
            'active_users' => $activeUsers,
            'total_xp_awarded' => $totalXp,
            'active_campaigns' => $activeCampaigns,
            'badge_distribution' => $badgeDistribution,
        ]);
    }

    /** GET /api/v2/admin/gamification/badges */
    public function badges(): JsonResponse
    {
        $this->requireAdmin();
        $tenantId = $this->getTenantId();

        $badges = [];

        try {
            $definitions = $this->gamificationService->getBadgeDefinitions();
            foreach ($definitions as $def) {
                $badgeKey = $def['key'] ?? '';
                $badges[] = ['id' => null, 'key' => $badgeKey, 'name' => $def['name'] ?? $badgeKey, 'description' => $def['msg'] ?? $def['description'] ?? '', 'icon' => $def['icon'] ?? 'award', 'type' => 'built_in', 'awarded_count' => 0];
            }
        } catch (\Throwable $e) {}

        try {
            $customBadges = DB::select("SELECT * FROM custom_badges WHERE tenant_id = ? ORDER BY created_at DESC", [$tenantId]);
            foreach ($customBadges as $cb) {
                $badges[] = ['id' => (int) $cb->id, 'key' => 'custom_' . $cb->id, 'name' => $cb->name, 'description' => $cb->description ?? '', 'icon' => $cb->icon ?? 'award', 'type' => 'custom', 'awarded_count' => 0];
            }
        } catch (\Throwable $e) {}

        try {
            $counts = DB::select("SELECT ub.badge_key, COUNT(*) as cnt FROM user_badges ub JOIN users u ON ub.user_id = u.id WHERE u.tenant_id = ? GROUP BY ub.badge_key", [$tenantId]);
            $countMap = [];
            foreach ($counts as $row) { $countMap[$row->badge_key] = (int) $row->cnt; }
            foreach ($badges as &$badge) { $badge['awarded_count'] = $countMap[$badge['key']] ?? 0; }
            unset($badge);
        } catch (\Throwable $e) {}

        return $this->respondWithData($badges);
    }

    /** POST /api/v2/admin/gamification/badges */
    public function createBadge(): JsonResponse
    {
        $this->requireAdmin();
        $tenantId = $this->getTenantId();

        $name = trim($this->input('name', ''));
        if (empty($name)) {
            return $this->respondWithError('VALIDATION_ERROR', 'Badge name is required', 'name', 422);
        }

        $description = trim($this->input('description', ''));
        $icon = trim($this->input('icon', 'award'));

        try {
            $badgeKey = 'custom_' . strtolower(preg_replace('/[^a-z0-9]+/i', '_', $name)) . '_' . time();
            DB::insert(
                "INSERT INTO custom_badges (tenant_id, badge_key, name, description, icon, badge_type, xp_reward, is_active, created_at) VALUES (?, ?, ?, ?, ?, 'custom', 0, 1, NOW())",
                [$tenantId, $badgeKey, $name, $description, $icon]
            );
            $id = (int) DB::getPdo()->lastInsertId();

            return $this->respondWithData(['id' => $id, 'key' => $badgeKey, 'name' => $name, 'description' => $description, 'icon' => $icon, 'type' => 'custom'], null, 201);
        } catch (\Throwable $e) {
            return $this->respondWithError('SERVER_ERROR', 'Failed to create badge', null, 500);
        }
    }

    /** DELETE /api/v2/admin/gamification/badges/{id} */
    public function deleteBadge(int $id): JsonResponse
    {
        $this->requireAdmin();
        $tenantId = $this->getTenantId();

        try {
            $badge = DB::selectOne("SELECT * FROM custom_badges WHERE id = ? AND tenant_id = ?", [$id, $tenantId]);
            if (!$badge) {
                return $this->respondWithError('NOT_FOUND', 'Badge not found', null, 404);
            }

            $actualKey = $badge->badge_key ?? ('custom_' . $id);
            try { DB::delete("DELETE FROM user_badges WHERE badge_key = ? AND user_id IN (SELECT id FROM users WHERE tenant_id = ?)", [$actualKey, $tenantId]); } catch (\Throwable $e) {}
            DB::delete("DELETE FROM custom_badges WHERE id = ? AND tenant_id = ?", [$id, $tenantId]);

            return $this->respondWithData(['deleted' => true]);
        } catch (\Throwable $e) {
            return $this->respondWithError('SERVER_ERROR', 'Failed to delete badge', null, 500);
        }
    }

    /** GET /api/v2/admin/gamification/campaigns */
    public function campaigns(): JsonResponse
    {
        $this->requireAdmin();
        try {
            $campaigns = $this->achievementCampaignService->getCampaigns();
            $formatted = array_map(fn($c) => [
                'id' => (int) $c['id'], 'name' => $c['name'] ?? '', 'description' => $c['description'] ?? '', 'status' => $c['status'] ?? 'draft',
                'badge_key' => $c['badge_key'] ?? null, 'badge_name' => $c['badge_key'] ?? '', 'target_audience' => $c['target_audience'] ?? 'all_users',
                'start_date' => $c['activated_at'] ?? null, 'end_date' => null, 'total_awards' => (int) ($c['total_awards'] ?? 0), 'created_at' => $c['created_at'] ?? '',
            ], $campaigns);
            return $this->respondWithData($formatted);
        } catch (\Throwable $e) {
            return $this->respondWithData([]);
        }
    }

    /** POST /api/v2/admin/gamification/campaigns */
    public function createCampaign(): JsonResponse
    {
        $this->requireAdmin();
        $name = trim($this->input('name', ''));
        if (empty($name)) return $this->respondWithError('VALIDATION_ERROR', 'Campaign name is required', 'name');

        try {
            $id = $this->achievementCampaignService->createCampaign([
                'name' => $name, 'description' => trim($this->input('description', '')), 'type' => $this->input('type', 'one_time'),
                'badge_key' => $this->input('badge_key', ''), 'xp_amount' => (int) $this->input('xp_amount', 0),
                'target_audience' => $this->input('target_audience', 'all_users'), 'audience_config' => $this->input('audience_config', []),
                'schedule' => $this->input('schedule'),
            ]);
            return $this->respondWithData(['id' => (int) $id, 'name' => $name, 'status' => 'draft'], null, 201);
        } catch (\Throwable $e) {
            Log::error('Failed to create campaign: ' . $e->getMessage());
            return $this->respondWithError('SERVER_ERROR', 'Failed to create campaign', null, 500);
        }
    }

    /** PUT /api/v2/admin/gamification/campaigns/{id} */
    public function updateCampaign(int $id): JsonResponse
    {
        $this->requireAdmin();
        $campaign = $this->achievementCampaignService->getCampaign($id);
        if (!$campaign) return $this->respondWithError('NOT_FOUND', 'Campaign not found', null, 404);

        $newStatus = $this->input('status');
        if ($newStatus && $newStatus !== $campaign['status']) {
            if ($newStatus === 'active') $this->achievementCampaignService->activateCampaign($id);
            elseif ($newStatus === 'paused') $this->achievementCampaignService->pauseCampaign($id);
        }

        try {
            $this->achievementCampaignService->updateCampaign($id, [
                'name' => trim($this->input('name', $campaign['name'])), 'description' => trim($this->input('description', $campaign['description'] ?? '')),
                'type' => $this->input('type', $campaign['type'] ?? 'one_time'), 'badge_key' => $this->input('badge_key', $campaign['badge_key'] ?? ''),
                'xp_amount' => (int) $this->input('xp_amount', $campaign['xp_amount'] ?? 0),
                'target_audience' => $this->input('target_audience', $campaign['target_audience'] ?? 'all_users'),
                'audience_config' => $this->input('audience_config', json_decode($campaign['audience_config'] ?? '{}', true)),
                'schedule' => $this->input('schedule', $campaign['schedule'] ?? null),
            ]);
            return $this->respondWithData(['id' => $id, 'updated' => true]);
        } catch (\Throwable $e) {
            Log::error('Failed to update campaign: ' . $e->getMessage());
            return $this->respondWithError('SERVER_ERROR', 'Failed to update campaign', null, 500);
        }
    }

    /** DELETE /api/v2/admin/gamification/campaigns/{id} */
    public function deleteCampaign(int $id): JsonResponse
    {
        $this->requireAdmin();
        $campaign = $this->achievementCampaignService->getCampaign($id);
        if (!$campaign) return $this->respondWithError('NOT_FOUND', 'Campaign not found', null, 404);
        try { $this->achievementCampaignService->deleteCampaign($id); return $this->respondWithData(['deleted' => true]); }
        catch (\Throwable $e) { return $this->respondWithError('SERVER_ERROR', 'Failed to delete campaign', null, 500); }
    }

    /** POST /api/v2/admin/gamification/recheck-all */
    public function recheckAll(): JsonResponse
    {
        $this->requireAdmin();
        $tenantId = $this->getTenantId();
        try {
            // Process in chunks to avoid loading all user IDs at once and to limit
            // memory usage. Each chunk processes 100 users before fetching the next batch.
            $checked = 0;
            $lastId = 0;
            $chunkSize = 100;

            do {
                $users = DB::select(
                    "SELECT id FROM users WHERE tenant_id = ? AND is_approved = 1 AND id > ? ORDER BY id ASC LIMIT ?",
                    [$tenantId, $lastId, $chunkSize]
                );

                foreach ($users as $user) {
                    $this->gamificationService->runAllBadgeChecks((int) $user->id);
                    $checked++;
                    $lastId = (int) $user->id;
                }
            } while (count($users) === $chunkSize);

            return $this->respondWithData(['users_checked' => $checked, 'message' => "Badge recheck completed for {$checked} users"]);
        } catch (\Throwable $e) {
            Log::error('Badge recheck failed: ' . $e->getMessage());
            return $this->respondWithError('SERVER_ERROR', 'Badge recheck failed', null, 500);
        }
    }

    /** POST /api/v2/admin/gamification/bulk-award */
    public function bulkAward(): JsonResponse
    {
        $this->requireAdmin();
        $badgeSlug = trim($this->input('badge_slug', ''));
        $userIds = $this->input('user_ids', []);

        if (empty($badgeSlug)) return $this->respondWithError('VALIDATION_ERROR', 'Badge slug is required', 'badge_slug', 422);
        if (empty($userIds) || !is_array($userIds)) return $this->respondWithError('VALIDATION_ERROR', 'User IDs array is required', 'user_ids', 422);

        // Filter user_ids to only include users belonging to the current tenant
        $tenantId = TenantContext::getId();
        $validUserIds = User::where('tenant_id', $tenantId)
            ->whereIn('id', $userIds)
            ->pluck('id')
            ->toArray();

        $awarded = 0; $errors = [];
        foreach ($validUserIds as $userId) {
            try { $this->gamificationService->awardBadgeByKey((int) $userId, $badgeSlug); $awarded++; }
            catch (\Throwable $e) { $errors[] = "User {$userId}: " . $e->getMessage(); }
        }

        return $this->respondWithData(['awarded' => $awarded, 'total_requested' => count($validUserIds), 'errors' => $errors]);
    }

    /** GET /api/v2/admin/gamification/badge-config */
    public function getBadgeConfig(): JsonResponse
    {
        $this->requireAdmin();
        $tenantId = $this->getTenantId();
        $badges = BadgeDefinitionService::getAllBadgesForAdmin($tenantId);

        return $this->respondWithData($badges);
    }

    /** PUT /api/v2/admin/gamification/badge-config/{badgeKey} */
    public function updateBadgeConfig(string $badgeKey): JsonResponse
    {
        $this->requireAdmin();
        $tenantId = $this->getTenantId();
        $data = request()->only(['is_enabled', 'custom_threshold', 'custom_name', 'custom_description', 'custom_icon']);

        $success = BadgeDefinitionService::updateTenantOverride($tenantId, $badgeKey, $data);

        if (!$success) {
            return $this->respondWithError('BADGE_UPDATE_FAILED', 'Failed to update badge configuration', null, 422);
        }

        return $this->respondWithData(['success' => true]);
    }

    /** POST /api/v2/admin/gamification/badge-config/{badgeKey}/reset */
    public function resetBadgeConfig(string $badgeKey): JsonResponse
    {
        $this->requireAdmin();
        $tenantId = $this->getTenantId();
        $success = BadgeDefinitionService::resetTenantOverride($tenantId, $badgeKey);

        return $this->respondWithData(['success' => $success]);
    }
}
