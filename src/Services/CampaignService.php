<?php
// Copyright © 2024–2026 Jasper Ford
// SPDX-License-Identifier: AGPL-3.0-or-later
// Author: Jasper Ford
// See NOTICE file for attribution and acknowledgements.

namespace Nexus\Services;

use Nexus\Core\Database;
use Nexus\Core\TenantContext;
use Nexus\Core\ApiErrorCodes;

/**
 * CampaignService - Manage campaigns that group multiple challenges
 *
 * Campaigns are broader thematic initiatives that can encompass multiple
 * ideation challenges. Each campaign has a status, dates, and a set of
 * linked challenges.
 *
 * @package Nexus\Services
 */
class CampaignService
{
    /** @var array Collected errors */
    private static array $errors = [];

    public static function getErrors(): array
    {
        return self::$errors;
    }

    private static function clearErrors(): void
    {
        self::$errors = [];
    }

    private static function addError(string $code, string $message, ?string $field = null): void
    {
        $error = ['code' => $code, 'message' => $message];
        if ($field !== null) {
            $error['field'] = $field;
        }
        self::$errors[] = $error;
    }

    /**
     * List campaigns for the current tenant
     */
    public static function getAll(array $filters = []): array
    {
        $tenantId = TenantContext::getId();
        $limit = $filters['limit'] ?? 20;
        $cursor = $filters['cursor'] ?? null;

        $params = [$tenantId];
        $where = ["c.tenant_id = ?"];

        $status = $filters['status'] ?? null;
        if ($status && in_array($status, ['draft', 'active', 'completed', 'archived'])) {
            $where[] = "c.status = ?";
            $params[] = $status;
        }

        if ($cursor) {
            $cursorId = base64_decode($cursor);
            if ($cursorId !== false) {
                $where[] = "c.id < ?";
                $params[] = (int)$cursorId;
            }
        }

        $whereClause = implode(' AND ', $where);
        $params[] = $limit + 1;

        $items = Database::query(
            "SELECT c.*,
                    u.first_name, u.last_name,
                    (SELECT COUNT(*) FROM campaign_challenges cc WHERE cc.campaign_id = c.id) AS challenge_count
             FROM campaigns c
             LEFT JOIN users u ON c.created_by = u.id
             WHERE {$whereClause}
             ORDER BY c.created_at DESC, c.id DESC
             LIMIT ?",
            $params
        )->fetchAll();

        $hasMore = count($items) > $limit;
        if ($hasMore) {
            array_pop($items);
        }

        $nextCursor = null;
        if ($hasMore && !empty($items)) {
            $lastItem = end($items);
            $nextCursor = base64_encode((string)$lastItem['id']);
        }

        foreach ($items as &$item) {
            $item['creator'] = [
                'id' => (int)$item['created_by'],
                'name' => trim(($item['first_name'] ?? '') . ' ' . ($item['last_name'] ?? '')),
            ];
            $item['challenge_count'] = (int)($item['challenge_count'] ?? 0);
            unset($item['first_name'], $item['last_name']);
        }

        return [
            'items' => $items,
            'cursor' => $nextCursor,
            'has_more' => $hasMore,
        ];
    }

    /**
     * Get a campaign by ID with its linked challenges
     */
    public static function getById(int $id): ?array
    {
        $tenantId = TenantContext::getId();

        $campaign = Database::query(
            "SELECT c.*, u.first_name, u.last_name
             FROM campaigns c
             LEFT JOIN users u ON c.created_by = u.id
             WHERE c.id = ? AND c.tenant_id = ?",
            [$id, $tenantId]
        )->fetch();

        if (!$campaign) {
            return null;
        }

        $campaign['creator'] = [
            'id' => (int)$campaign['created_by'],
            'name' => trim(($campaign['first_name'] ?? '') . ' ' . ($campaign['last_name'] ?? '')),
        ];
        unset($campaign['first_name'], $campaign['last_name']);

        // Get linked challenges
        $campaign['challenges'] = Database::query(
            "SELECT ic.id, ic.title, ic.status, ic.ideas_count, ic.cover_image, cc.sort_order
             FROM campaign_challenges cc
             INNER JOIN ideation_challenges ic ON cc.challenge_id = ic.id
             WHERE cc.campaign_id = ? AND ic.tenant_id = ?
             ORDER BY cc.sort_order ASC, ic.title ASC",
            [$id, $tenantId]
        )->fetchAll();

        return $campaign;
    }

    /**
     * Create a campaign
     *
     * @return int|null Campaign ID
     */
    public static function create(int $userId, array $data): ?int
    {
        self::clearErrors();

        if (!self::isAdmin($userId)) {
            self::addError(ApiErrorCodes::RESOURCE_FORBIDDEN, 'Only admins can create campaigns');
            return null;
        }

        $tenantId = TenantContext::getId();
        $title = trim($data['title'] ?? '');

        if (empty($title)) {
            self::addError(ApiErrorCodes::VALIDATION_REQUIRED_FIELD, 'Title is required', 'title');
            return null;
        }

        $description = !empty($data['description']) ? trim($data['description']) : null;
        $coverImage = !empty($data['cover_image']) ? trim($data['cover_image']) : null;
        $status = $data['status'] ?? 'draft';
        if (!in_array($status, ['draft', 'active', 'completed', 'archived'])) {
            $status = 'draft';
        }
        $startDate = !empty($data['start_date']) ? $data['start_date'] : null;
        $endDate = !empty($data['end_date']) ? $data['end_date'] : null;

        try {
            Database::query(
                "INSERT INTO campaigns (tenant_id, title, description, cover_image, status, start_date, end_date, created_by)
                 VALUES (?, ?, ?, ?, ?, ?, ?, ?)",
                [$tenantId, $title, $description, $coverImage, $status, $startDate, $endDate, $userId]
            );

            return (int)Database::lastInsertId();
        } catch (\Throwable $e) {
            error_log("Campaign creation failed: " . $e->getMessage());
            self::addError(ApiErrorCodes::SERVER_INTERNAL_ERROR, 'Failed to create campaign');
            return null;
        }
    }

    /**
     * Update a campaign
     */
    public static function update(int $id, int $userId, array $data): bool
    {
        self::clearErrors();

        if (!self::isAdmin($userId)) {
            self::addError(ApiErrorCodes::RESOURCE_FORBIDDEN, 'Only admins can update campaigns');
            return false;
        }

        $campaign = self::getById($id);
        if (!$campaign) {
            self::addError(ApiErrorCodes::RESOURCE_NOT_FOUND, 'Campaign not found');
            return false;
        }

        $tenantId = TenantContext::getId();
        $updates = [];
        $params = [];

        if (isset($data['title'])) {
            $title = trim($data['title']);
            if (empty($title)) {
                self::addError(ApiErrorCodes::VALIDATION_REQUIRED_FIELD, 'Title cannot be empty', 'title');
                return false;
            }
            $updates[] = "title = ?";
            $params[] = $title;
        }

        if (array_key_exists('description', $data)) {
            $updates[] = "description = ?";
            $params[] = !empty($data['description']) ? trim($data['description']) : null;
        }

        if (array_key_exists('cover_image', $data)) {
            $updates[] = "cover_image = ?";
            $params[] = !empty($data['cover_image']) ? trim($data['cover_image']) : null;
        }

        if (isset($data['status'])) {
            if (in_array($data['status'], ['draft', 'active', 'completed', 'archived'])) {
                $updates[] = "status = ?";
                $params[] = $data['status'];
            }
        }

        if (array_key_exists('start_date', $data)) {
            $updates[] = "start_date = ?";
            $params[] = !empty($data['start_date']) ? $data['start_date'] : null;
        }

        if (array_key_exists('end_date', $data)) {
            $updates[] = "end_date = ?";
            $params[] = !empty($data['end_date']) ? $data['end_date'] : null;
        }

        if (empty($updates)) {
            return true;
        }

        $params[] = $id;
        $params[] = $tenantId;

        try {
            Database::query(
                "UPDATE campaigns SET " . implode(', ', $updates) . " WHERE id = ? AND tenant_id = ?",
                $params
            );
            return true;
        } catch (\Throwable $e) {
            error_log("Campaign update failed: " . $e->getMessage());
            self::addError(ApiErrorCodes::SERVER_INTERNAL_ERROR, 'Failed to update campaign');
            return false;
        }
    }

    /**
     * Delete a campaign
     */
    public static function delete(int $id, int $userId): bool
    {
        self::clearErrors();

        if (!self::isAdmin($userId)) {
            self::addError(ApiErrorCodes::RESOURCE_FORBIDDEN, 'Only admins can delete campaigns');
            return false;
        }

        $campaign = self::getById($id);
        if (!$campaign) {
            self::addError(ApiErrorCodes::RESOURCE_NOT_FOUND, 'Campaign not found');
            return false;
        }

        $tenantId = TenantContext::getId();

        try {
            // FK cascade deletes campaign_challenges links
            Database::query(
                "DELETE FROM campaigns WHERE id = ? AND tenant_id = ?",
                [$id, $tenantId]
            );
            return true;
        } catch (\Throwable $e) {
            error_log("Campaign deletion failed: " . $e->getMessage());
            self::addError(ApiErrorCodes::SERVER_INTERNAL_ERROR, 'Failed to delete campaign');
            return false;
        }
    }

    /**
     * Link a challenge to a campaign
     */
    public static function linkChallenge(int $campaignId, int $challengeId, int $userId, int $sortOrder = 0): bool
    {
        self::clearErrors();

        if (!self::isAdmin($userId)) {
            self::addError(ApiErrorCodes::RESOURCE_FORBIDDEN, 'Only admins can manage campaign links');
            return false;
        }

        $tenantId = TenantContext::getId();

        // Verify both exist in this tenant
        $campaign = Database::query(
            "SELECT id FROM campaigns WHERE id = ? AND tenant_id = ?",
            [$campaignId, $tenantId]
        )->fetch();

        if (!$campaign) {
            self::addError(ApiErrorCodes::RESOURCE_NOT_FOUND, 'Campaign not found');
            return false;
        }

        $challenge = Database::query(
            "SELECT id FROM ideation_challenges WHERE id = ? AND tenant_id = ?",
            [$challengeId, $tenantId]
        )->fetch();

        if (!$challenge) {
            self::addError(ApiErrorCodes::RESOURCE_NOT_FOUND, 'Challenge not found');
            return false;
        }

        try {
            Database::query(
                "INSERT IGNORE INTO campaign_challenges (campaign_id, challenge_id, sort_order) VALUES (?, ?, ?)",
                [$campaignId, $challengeId, $sortOrder]
            );
            return true;
        } catch (\Throwable $e) {
            error_log("Campaign link failed: " . $e->getMessage());
            self::addError(ApiErrorCodes::SERVER_INTERNAL_ERROR, 'Failed to link challenge');
            return false;
        }
    }

    /**
     * Unlink a challenge from a campaign
     */
    public static function unlinkChallenge(int $campaignId, int $challengeId, int $userId): bool
    {
        self::clearErrors();

        if (!self::isAdmin($userId)) {
            self::addError(ApiErrorCodes::RESOURCE_FORBIDDEN, 'Only admins can manage campaign links');
            return false;
        }

        try {
            Database::query(
                "DELETE FROM campaign_challenges WHERE campaign_id = ? AND challenge_id = ?",
                [$campaignId, $challengeId]
            );
            return true;
        } catch (\Throwable $e) {
            error_log("Campaign unlink failed: " . $e->getMessage());
            self::addError(ApiErrorCodes::SERVER_INTERNAL_ERROR, 'Failed to unlink challenge');
            return false;
        }
    }

    private static function isAdmin(int $userId): bool
    {
        $tenantId = TenantContext::getId();
        $user = Database::query(
            "SELECT role FROM users WHERE id = ? AND tenant_id = ?",
            [$userId, $tenantId]
        )->fetch();

        return $user && in_array($user['role'] ?? '', ['admin', 'tenant_admin', 'tenant_super_admin', 'super_admin']);
    }
}
