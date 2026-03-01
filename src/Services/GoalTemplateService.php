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
 * GoalTemplateService - Business logic for goal templates
 *
 * Admins create goal templates with pre-filled milestones and settings.
 * Users can start new goals from these templates.
 *
 * @package Nexus\Services
 */
class GoalTemplateService
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
     * Get all goal templates for the tenant
     *
     * @param array $filters Keys: category, cursor, limit
     * @return array ['items' => [...], 'cursor' => ?string, 'has_more' => bool]
     */
    public static function getAll(array $filters = []): array
    {
        $tenantId = TenantContext::getId();
        $limit = $filters['limit'] ?? 50;
        $cursor = $filters['cursor'] ?? null;
        $category = $filters['category'] ?? null;

        $params = [$tenantId];
        $where = ["gt.tenant_id = ?", "gt.is_public = 1"];

        if ($category) {
            $where[] = "gt.category = ?";
            $params[] = $category;
        }

        if ($cursor) {
            $cursorId = base64_decode($cursor);
            if ($cursorId !== false) {
                $where[] = "gt.id < ?";
                $params[] = (int)$cursorId;
            }
        }

        $whereClause = implode(' AND ', $where);
        $params[] = $limit + 1;

        $sql = "
            SELECT
                gt.*,
                u.first_name as creator_first_name,
                u.last_name as creator_last_name
            FROM goal_templates gt
            LEFT JOIN users u ON gt.created_by = u.id
            WHERE {$whereClause}
            ORDER BY gt.created_at DESC, gt.id DESC
            LIMIT ?
        ";

        $templates = Database::query($sql, $params)->fetchAll();

        $hasMore = count($templates) > $limit;
        if ($hasMore) {
            array_pop($templates);
        }

        $nextCursor = null;
        if ($hasMore && !empty($templates)) {
            $lastItem = end($templates);
            $nextCursor = base64_encode((string)$lastItem['id']);
        }

        $items = array_map(function ($t) {
            $milestones = null;
            if (!empty($t['default_milestones'])) {
                $milestones = json_decode($t['default_milestones'], true);
            }

            return [
                'id' => (int)$t['id'],
                'title' => $t['title'],
                'description' => $t['description'],
                'default_milestones' => $milestones,
                'category' => $t['category'],
                'default_target_value' => (float)$t['default_target_value'],
                'created_at' => $t['created_at'],
                'creator' => $t['created_by'] ? [
                    'id' => (int)$t['created_by'],
                    'name' => trim(($t['creator_first_name'] ?? '') . ' ' . ($t['creator_last_name'] ?? '')),
                ] : null,
            ];
        }, $templates);

        return [
            'items' => $items,
            'cursor' => $nextCursor,
            'has_more' => $hasMore,
        ];
    }

    /**
     * Get a single template by ID
     *
     * @param int $id
     * @return array|null
     */
    public static function getById(int $id): ?array
    {
        $tenantId = TenantContext::getId();

        $template = Database::query(
            "SELECT gt.*, u.first_name as creator_first_name, u.last_name as creator_last_name
             FROM goal_templates gt
             LEFT JOIN users u ON gt.created_by = u.id
             WHERE gt.id = ? AND gt.tenant_id = ?",
            [$id, $tenantId]
        )->fetch();

        if (!$template) {
            return null;
        }

        $milestones = null;
        if (!empty($template['default_milestones'])) {
            $milestones = json_decode($template['default_milestones'], true);
        }

        return [
            'id' => (int)$template['id'],
            'title' => $template['title'],
            'description' => $template['description'],
            'default_milestones' => $milestones,
            'category' => $template['category'],
            'default_target_value' => (float)$template['default_target_value'],
            'is_public' => (bool)$template['is_public'],
            'created_at' => $template['created_at'],
            'creator' => $template['created_by'] ? [
                'id' => (int)$template['created_by'],
                'name' => trim(($template['creator_first_name'] ?? '') . ' ' . ($template['creator_last_name'] ?? '')),
            ] : null,
        ];
    }

    /**
     * Create a goal template (admin only)
     *
     * @param int $userId Admin user ID
     * @param array $data
     * @return int|null Template ID on success
     */
    public static function create(int $userId, array $data): ?int
    {
        self::clearErrors();

        $tenantId = TenantContext::getId();
        $title = trim($data['title'] ?? '');
        $description = trim($data['description'] ?? '');
        $category = trim($data['category'] ?? '');
        $defaultTargetValue = (float)($data['default_target_value'] ?? 0);
        $defaultMilestones = $data['default_milestones'] ?? null;
        $isPublic = !empty($data['is_public'] ?? true);

        if (empty($title)) {
            self::addError(ApiErrorCodes::VALIDATION_REQUIRED_FIELD, 'Template title is required', 'title');
            return null;
        }

        if (strlen($title) > 255) {
            self::addError(ApiErrorCodes::VALIDATION_TOO_LONG, 'Title cannot exceed 255 characters', 'title');
            return null;
        }

        // Validate milestones format
        if ($defaultMilestones !== null && !is_array($defaultMilestones)) {
            self::addError(ApiErrorCodes::VALIDATION_INVALID_VALUE, 'default_milestones must be an array', 'default_milestones');
            return null;
        }

        try {
            Database::query(
                "INSERT INTO goal_templates (tenant_id, title, description, default_milestones, category, default_target_value, is_public, created_by, created_at)
                 VALUES (?, ?, ?, ?, ?, ?, ?, ?, NOW())",
                [
                    $tenantId,
                    $title,
                    $description ?: null,
                    $defaultMilestones !== null ? json_encode($defaultMilestones) : null,
                    $category ?: null,
                    $defaultTargetValue,
                    $isPublic ? 1 : 0,
                    $userId,
                ]
            );

            return (int)Database::lastInsertId();
        } catch (\Throwable $e) {
            error_log("Goal template creation failed: " . $e->getMessage());
            self::addError(ApiErrorCodes::SERVER_INTERNAL_ERROR, 'Failed to create template');
            return null;
        }
    }

    /**
     * Create a goal from a template
     *
     * @param int $templateId
     * @param int $userId
     * @param array $overrides Optional overrides: title, description, deadline, is_public, target_value
     * @return int|null Goal ID on success
     */
    public static function createGoalFromTemplate(int $templateId, int $userId, array $overrides = []): ?int
    {
        self::clearErrors();

        $template = self::getById($templateId);

        if (!$template) {
            self::addError(ApiErrorCodes::RESOURCE_NOT_FOUND, 'Template not found');
            return null;
        }

        // Build goal data from template with optional overrides
        $goalData = [
            'title' => $overrides['title'] ?? $template['title'],
            'description' => $overrides['description'] ?? $template['description'],
            'target_value' => $overrides['target_value'] ?? $template['default_target_value'],
            'deadline' => $overrides['deadline'] ?? null,
            'is_public' => $overrides['is_public'] ?? false,
        ];

        // Create the goal using GoalService
        $goalId = GoalService::create($userId, $goalData);

        if ($goalId === null) {
            self::$errors = array_merge(self::$errors, GoalService::getErrors());
            return null;
        }

        // Link the goal to its template
        $tenantId = TenantContext::getId();
        try {
            Database::query(
                "UPDATE goals SET template_id = ? WHERE id = ? AND tenant_id = ?",
                [$templateId, $goalId, $tenantId]
            );
        } catch (\Throwable $e) {
            // Non-critical — goal was created, template link is bonus
            error_log("Failed to link goal to template: " . $e->getMessage());
        }

        // Log creation from template
        GoalProgressService::logEvent($goalId, $tenantId, 'created', null, null, $userId, [
            'from_template' => $templateId,
            'template_title' => $template['title'],
        ]);

        return $goalId;
    }

    /**
     * Get available template categories for the tenant
     *
     * @return array List of unique category strings
     */
    public static function getCategories(): array
    {
        $tenantId = TenantContext::getId();

        $rows = Database::query(
            "SELECT DISTINCT category FROM goal_templates
             WHERE tenant_id = ? AND category IS NOT NULL AND category != '' AND is_public = 1
             ORDER BY category ASC",
            [$tenantId]
        )->fetchAll();

        return array_map(function ($row) {
            return $row['category'];
        }, $rows);
    }
}
