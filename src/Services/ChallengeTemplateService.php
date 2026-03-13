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
 * ChallengeTemplateService - Reusable challenge templates
 *
 * Admins create templates with pre-set fields (title, description, tags,
 * category, evaluation criteria). When creating a new challenge, users
 * can "Start from template" to pre-fill the form.
 *
 * @package Nexus\Services
 */
class ChallengeTemplateService
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
     * List all templates for the current tenant
     */
    public static function getAll(): array
    {
        $tenantId = TenantContext::getId();

        $templates = Database::query(
            "SELECT ct.*, u.first_name, u.last_name,
                    cc.name AS category_name
             FROM challenge_templates ct
             LEFT JOIN users u ON ct.created_by = u.id
             LEFT JOIN challenge_categories cc ON ct.default_category_id = cc.id
             WHERE ct.tenant_id = ?
             ORDER BY ct.title ASC",
            [$tenantId]
        )->fetchAll();

        foreach ($templates as &$tpl) {
            $tpl['creator'] = [
                'id' => (int)$tpl['created_by'],
                'name' => trim(($tpl['first_name'] ?? '') . ' ' . ($tpl['last_name'] ?? '')),
            ];

            // Decode JSON fields
            if (isset($tpl['default_tags']) && is_string($tpl['default_tags'])) {
                $decoded = json_decode($tpl['default_tags'], true);
                $tpl['default_tags'] = is_array($decoded) ? $decoded : [];
            } else {
                $tpl['default_tags'] = [];
            }

            if (isset($tpl['evaluation_criteria']) && is_string($tpl['evaluation_criteria'])) {
                $decoded = json_decode($tpl['evaluation_criteria'], true);
                $tpl['evaluation_criteria'] = is_array($decoded) ? $decoded : [];
            } else {
                $tpl['evaluation_criteria'] = [];
            }

            unset($tpl['first_name'], $tpl['last_name']);
        }

        return $templates;
    }

    /**
     * Get a single template by ID
     */
    public static function getById(int $id): ?array
    {
        $tenantId = TenantContext::getId();

        $tpl = Database::query(
            "SELECT ct.*, u.first_name, u.last_name, cc.name AS category_name
             FROM challenge_templates ct
             LEFT JOIN users u ON ct.created_by = u.id
             LEFT JOIN challenge_categories cc ON ct.default_category_id = cc.id
             WHERE ct.id = ? AND ct.tenant_id = ?",
            [$id, $tenantId]
        )->fetch();

        if (!$tpl) {
            return null;
        }

        $tpl['creator'] = [
            'id' => (int)$tpl['created_by'],
            'name' => trim(($tpl['first_name'] ?? '') . ' ' . ($tpl['last_name'] ?? '')),
        ];

        if (isset($tpl['default_tags']) && is_string($tpl['default_tags'])) {
            $decoded = json_decode($tpl['default_tags'], true);
            $tpl['default_tags'] = is_array($decoded) ? $decoded : [];
        } else {
            $tpl['default_tags'] = [];
        }

        if (isset($tpl['evaluation_criteria']) && is_string($tpl['evaluation_criteria'])) {
            $decoded = json_decode($tpl['evaluation_criteria'], true);
            $tpl['evaluation_criteria'] = is_array($decoded) ? $decoded : [];
        } else {
            $tpl['evaluation_criteria'] = [];
        }

        unset($tpl['first_name'], $tpl['last_name']);

        return $tpl;
    }

    /**
     * Create a template
     *
     * @return int|null Template ID
     */
    public static function create(int $userId, array $data): ?int
    {
        self::clearErrors();

        if (!self::isAdmin($userId)) {
            self::addError(ApiErrorCodes::RESOURCE_FORBIDDEN, 'Only admins can manage templates');
            return null;
        }

        $tenantId = TenantContext::getId();
        $title = trim($data['title'] ?? '');

        if (empty($title)) {
            self::addError(ApiErrorCodes::VALIDATION_REQUIRED_FIELD, 'Title is required', 'title');
            return null;
        }

        $description = !empty($data['description']) ? trim($data['description']) : null;
        $defaultTags = isset($data['default_tags']) && is_array($data['default_tags'])
            ? json_encode($data['default_tags'])
            : null;
        $defaultCategoryId = isset($data['default_category_id']) ? (int)$data['default_category_id'] : null;
        $evaluationCriteria = isset($data['evaluation_criteria']) && is_array($data['evaluation_criteria'])
            ? json_encode($data['evaluation_criteria'])
            : null;
        $prizeDescription = !empty($data['prize_description']) ? trim($data['prize_description']) : null;
        $maxIdeasPerUser = isset($data['max_ideas_per_user']) ? (int)$data['max_ideas_per_user'] : null;

        try {
            Database::query(
                "INSERT INTO challenge_templates
                    (tenant_id, title, description, default_tags, default_category_id, evaluation_criteria, prize_description, max_ideas_per_user, created_by)
                 VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?)",
                [$tenantId, $title, $description, $defaultTags, $defaultCategoryId, $evaluationCriteria, $prizeDescription, $maxIdeasPerUser, $userId]
            );

            return (int)Database::lastInsertId();
        } catch (\Throwable $e) {
            error_log("Template creation failed: " . $e->getMessage());
            self::addError(ApiErrorCodes::SERVER_INTERNAL_ERROR, 'Failed to create template');
            return null;
        }
    }

    /**
     * Update a template
     */
    public static function update(int $id, int $userId, array $data): bool
    {
        self::clearErrors();

        if (!self::isAdmin($userId)) {
            self::addError(ApiErrorCodes::RESOURCE_FORBIDDEN, 'Only admins can manage templates');
            return false;
        }

        $template = self::getById($id);
        if (!$template) {
            self::addError(ApiErrorCodes::RESOURCE_NOT_FOUND, 'Template not found');
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

        if (array_key_exists('default_tags', $data)) {
            $updates[] = "default_tags = ?";
            $params[] = is_array($data['default_tags']) ? json_encode($data['default_tags']) : null;
        }

        if (array_key_exists('default_category_id', $data)) {
            $updates[] = "default_category_id = ?";
            $params[] = $data['default_category_id'] !== null ? (int)$data['default_category_id'] : null;
        }

        if (array_key_exists('evaluation_criteria', $data)) {
            $updates[] = "evaluation_criteria = ?";
            $params[] = is_array($data['evaluation_criteria']) ? json_encode($data['evaluation_criteria']) : null;
        }

        if (array_key_exists('prize_description', $data)) {
            $updates[] = "prize_description = ?";
            $params[] = !empty($data['prize_description']) ? trim($data['prize_description']) : null;
        }

        if (array_key_exists('max_ideas_per_user', $data)) {
            $updates[] = "max_ideas_per_user = ?";
            $params[] = $data['max_ideas_per_user'] !== null ? (int)$data['max_ideas_per_user'] : null;
        }

        if (empty($updates)) {
            return true;
        }

        $params[] = $id;
        $params[] = $tenantId;

        try {
            Database::query(
                "UPDATE challenge_templates SET " . implode(', ', $updates) . " WHERE id = ? AND tenant_id = ?",
                $params
            );
            return true;
        } catch (\Throwable $e) {
            error_log("Template update failed: " . $e->getMessage());
            self::addError(ApiErrorCodes::SERVER_INTERNAL_ERROR, 'Failed to update template');
            return false;
        }
    }

    /**
     * Delete a template
     */
    public static function delete(int $id, int $userId): bool
    {
        self::clearErrors();

        if (!self::isAdmin($userId)) {
            self::addError(ApiErrorCodes::RESOURCE_FORBIDDEN, 'Only admins can manage templates');
            return false;
        }

        $template = self::getById($id);
        if (!$template) {
            self::addError(ApiErrorCodes::RESOURCE_NOT_FOUND, 'Template not found');
            return false;
        }

        $tenantId = TenantContext::getId();

        try {
            Database::query(
                "DELETE FROM challenge_templates WHERE id = ? AND tenant_id = ?",
                [$id, $tenantId]
            );
            return true;
        } catch (\Throwable $e) {
            error_log("Template deletion failed: " . $e->getMessage());
            self::addError(ApiErrorCodes::SERVER_INTERNAL_ERROR, 'Failed to delete template');
            return false;
        }
    }

    /**
     * Create a challenge from a template
     *
     * Returns the pre-filled data array that can be passed to IdeationChallengeService::createChallenge
     */
    public static function getTemplateData(int $templateId): ?array
    {
        $template = self::getById($templateId);
        if (!$template) {
            return null;
        }

        return [
            'title' => $template['title'] ?? '',
            'description' => $template['description'] ?? '',
            'category_id' => $template['default_category_id'] ?? null,
            'tags' => $template['default_tags'] ?? [],
            'evaluation_criteria' => $template['evaluation_criteria'] ?? [],
            'prize_description' => $template['prize_description'] ?? null,
            'max_ideas_per_user' => $template['max_ideas_per_user'] ?? null,
        ];
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
