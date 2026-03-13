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
 * ChallengeTagService - CRUD for challenge tags
 *
 * Tags are a per-tenant pool of labels (interest, skill, general) that can
 * be attached to ideation challenges via the challenge_tag_links pivot table.
 *
 * @package Nexus\Services
 */
class ChallengeTagService
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
     * List all tags for the current tenant, optionally filtered by type
     *
     * @param string|null $tagType Filter by 'interest', 'skill', or 'general'
     * @return array
     */
    public static function getAll(?string $tagType = null): array
    {
        $tenantId = TenantContext::getId();
        $params = [$tenantId];
        $where = "tenant_id = ?";

        if ($tagType && in_array($tagType, ['interest', 'skill', 'general'])) {
            $where .= " AND tag_type = ?";
            $params[] = $tagType;
        }

        return Database::query(
            "SELECT * FROM challenge_tags WHERE {$where} ORDER BY name ASC",
            $params
        )->fetchAll();
    }

    /**
     * Get a single tag by ID
     */
    public static function getById(int $id): ?array
    {
        $tenantId = TenantContext::getId();

        $row = Database::query(
            "SELECT * FROM challenge_tags WHERE id = ? AND tenant_id = ?",
            [$id, $tenantId]
        )->fetch();

        return $row ?: null;
    }

    /**
     * Create a new tag
     *
     * @return int|null Tag ID
     */
    public static function create(int $userId, array $data): ?int
    {
        self::clearErrors();

        if (!self::isAdmin($userId)) {
            self::addError(ApiErrorCodes::RESOURCE_FORBIDDEN, 'Only admins can manage tags');
            return null;
        }

        $tenantId = TenantContext::getId();
        $name = trim($data['name'] ?? '');

        if (empty($name)) {
            self::addError(ApiErrorCodes::VALIDATION_REQUIRED_FIELD, 'Name is required', 'name');
            return null;
        }

        $slug = self::generateSlug($name);
        $tagType = $data['tag_type'] ?? 'general';
        if (!in_array($tagType, ['interest', 'skill', 'general'])) {
            $tagType = 'general';
        }

        // Check for duplicate slug
        $existing = Database::query(
            "SELECT id FROM challenge_tags WHERE tenant_id = ? AND slug = ?",
            [$tenantId, $slug]
        )->fetch();

        if ($existing) {
            self::addError(ApiErrorCodes::RESOURCE_CONFLICT, 'A tag with this name already exists', 'name');
            return null;
        }

        try {
            Database::query(
                "INSERT INTO challenge_tags (tenant_id, name, slug, tag_type) VALUES (?, ?, ?, ?)",
                [$tenantId, $name, $slug, $tagType]
            );

            return (int)Database::lastInsertId();
        } catch (\Throwable $e) {
            error_log("Tag creation failed: " . $e->getMessage());
            self::addError(ApiErrorCodes::SERVER_INTERNAL_ERROR, 'Failed to create tag');
            return null;
        }
    }

    /**
     * Delete a tag
     */
    public static function delete(int $id, int $userId): bool
    {
        self::clearErrors();

        if (!self::isAdmin($userId)) {
            self::addError(ApiErrorCodes::RESOURCE_FORBIDDEN, 'Only admins can manage tags');
            return false;
        }

        $tag = self::getById($id);
        if (!$tag) {
            self::addError(ApiErrorCodes::RESOURCE_NOT_FOUND, 'Tag not found');
            return false;
        }

        $tenantId = TenantContext::getId();

        try {
            // FK cascade will handle challenge_tag_links
            Database::query(
                "DELETE FROM challenge_tags WHERE id = ? AND tenant_id = ?",
                [$id, $tenantId]
            );

            return true;
        } catch (\Throwable $e) {
            error_log("Tag deletion failed: " . $e->getMessage());
            self::addError(ApiErrorCodes::SERVER_INTERNAL_ERROR, 'Failed to delete tag');
            return false;
        }
    }

    /**
     * Get all tags for a specific challenge
     *
     * @param int $challengeId
     * @return array Array of tag records
     */
    public static function getTagsForChallenge(int $challengeId): array
    {
        $tenantId = TenantContext::getId();
        return Database::query(
            "SELECT t.* FROM challenge_tags t
             INNER JOIN challenge_tag_links ctl ON t.id = ctl.tag_id
             WHERE ctl.challenge_id = ? AND t.tenant_id = ?
             ORDER BY t.name ASC",
            [$challengeId, $tenantId]
        )->fetchAll();
    }

    /**
     * Sync tags for a challenge (replace all links)
     *
     * @param int $challengeId
     * @param array $tagIds Array of tag IDs
     */
    public static function syncTagsForChallenge(int $challengeId, array $tagIds): void
    {
        $tenantId = TenantContext::getId();

        // Remove existing links
        Database::query(
            "DELETE FROM challenge_tag_links WHERE challenge_id = ?",
            [$challengeId]
        );

        // Insert new links
        foreach ($tagIds as $tagId) {
            $tagId = (int)$tagId;
            if ($tagId > 0) {
                try {
                    Database::query(
                        "INSERT IGNORE INTO challenge_tag_links (challenge_id, tag_id) VALUES (?, ?)",
                        [$challengeId, $tagId]
                    );
                } catch (\Throwable $e) {
                    // Skip invalid FK references silently
                }
            }
        }
    }

    /**
     * Find or create tags by name (auto-create for ad-hoc tagging)
     *
     * @param array $tagNames Array of tag name strings
     * @return array Array of tag IDs
     */
    public static function findOrCreateByNames(array $tagNames): array
    {
        $tenantId = TenantContext::getId();
        $tagIds = [];

        foreach ($tagNames as $name) {
            $name = trim($name);
            if (empty($name)) {
                continue;
            }

            $slug = self::generateSlug($name);

            $existing = Database::query(
                "SELECT id FROM challenge_tags WHERE tenant_id = ? AND slug = ?",
                [$tenantId, $slug]
            )->fetch();

            if ($existing) {
                $tagIds[] = (int)$existing['id'];
            } else {
                try {
                    Database::query(
                        "INSERT INTO challenge_tags (tenant_id, name, slug, tag_type) VALUES (?, ?, ?, 'general')",
                        [$tenantId, $name, $slug]
                    );
                    $tagIds[] = (int)Database::lastInsertId();
                } catch (\Throwable $e) {
                    // Duplicate slug race condition — try to fetch
                    $retry = Database::query(
                        "SELECT id FROM challenge_tags WHERE tenant_id = ? AND slug = ?",
                        [$tenantId, $slug]
                    )->fetch();
                    if ($retry) {
                        $tagIds[] = (int)$retry['id'];
                    }
                }
            }
        }

        return $tagIds;
    }

    private static function generateSlug(string $name): string
    {
        $slug = mb_strtolower($name);
        $slug = preg_replace('/[^a-z0-9\s-]/', '', $slug);
        $slug = preg_replace('/[\s-]+/', '-', $slug);
        return trim($slug, '-');
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
