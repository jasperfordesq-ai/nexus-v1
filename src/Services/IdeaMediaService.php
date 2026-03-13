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
 * IdeaMediaService - Manage rich media attachments on idea submissions
 *
 * Supports images, videos, documents, and link embeds. Each idea can have
 * multiple media items with optional captions and ordering.
 *
 * @package Nexus\Services
 */
class IdeaMediaService
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
     * Get all media for an idea
     *
     * @param int $ideaId
     * @return array
     */
    public static function getMediaForIdea(int $ideaId): array
    {
        $tenantId = TenantContext::getId();

        return Database::query(
            "SELECT m.* FROM idea_media m
             INNER JOIN challenge_ideas ci ON m.idea_id = ci.id
             INNER JOIN ideation_challenges ic ON ci.challenge_id = ic.id
             WHERE m.idea_id = ? AND ic.tenant_id = ?
             ORDER BY m.sort_order ASC, m.id ASC",
            [$ideaId, $tenantId]
        )->fetchAll();
    }

    /**
     * Add media to an idea
     *
     * @param int $ideaId
     * @param int $userId
     * @param array $data ['media_type', 'url', 'caption', 'sort_order']
     * @return int|null Media ID
     */
    public static function addMedia(int $ideaId, int $userId, array $data): ?int
    {
        self::clearErrors();

        $tenantId = TenantContext::getId();

        // Verify idea exists and belongs to this tenant, and user is the owner or admin
        $idea = Database::query(
            "SELECT ci.*, ic.tenant_id
             FROM challenge_ideas ci
             INNER JOIN ideation_challenges ic ON ci.challenge_id = ic.id
             WHERE ci.id = ? AND ic.tenant_id = ?",
            [$ideaId, $tenantId]
        )->fetch();

        if (!$idea) {
            self::addError(ApiErrorCodes::RESOURCE_NOT_FOUND, 'Idea not found');
            return null;
        }

        $isOwner = (int)$idea['user_id'] === $userId;
        $isAdmin = self::isAdmin($userId);

        if (!$isOwner && !$isAdmin) {
            self::addError(ApiErrorCodes::RESOURCE_FORBIDDEN, 'Only the idea owner or an admin can add media');
            return null;
        }

        $url = trim($data['url'] ?? '');
        if (empty($url)) {
            self::addError(ApiErrorCodes::VALIDATION_REQUIRED_FIELD, 'URL is required', 'url');
            return null;
        }

        $mediaType = $data['media_type'] ?? 'image';
        if (!in_array($mediaType, ['image', 'video', 'document', 'link'])) {
            $mediaType = 'image';
        }

        $caption = !empty($data['caption']) ? trim($data['caption']) : null;
        $sortOrder = isset($data['sort_order']) ? (int)$data['sort_order'] : 0;

        try {
            Database::query(
                "INSERT INTO idea_media (idea_id, tenant_id, media_type, url, caption, sort_order) VALUES (?, ?, ?, ?, ?, ?)",
                [$ideaId, $tenantId, $mediaType, $url, $caption, $sortOrder]
            );

            return (int)Database::lastInsertId();
        } catch (\Throwable $e) {
            error_log("Idea media creation failed: " . $e->getMessage());
            self::addError(ApiErrorCodes::SERVER_INTERNAL_ERROR, 'Failed to add media');
            return null;
        }
    }

    /**
     * Delete media from an idea
     *
     * @param int $mediaId
     * @param int $userId
     * @return bool
     */
    public static function deleteMedia(int $mediaId, int $userId): bool
    {
        self::clearErrors();

        $tenantId = TenantContext::getId();

        $media = Database::query(
            "SELECT m.*, ci.user_id AS idea_owner_id
             FROM idea_media m
             INNER JOIN challenge_ideas ci ON m.idea_id = ci.id
             WHERE m.id = ? AND m.tenant_id = ?",
            [$mediaId, $tenantId]
        )->fetch();

        if (!$media) {
            self::addError(ApiErrorCodes::RESOURCE_NOT_FOUND, 'Media not found');
            return false;
        }

        $isOwner = (int)$media['idea_owner_id'] === $userId;
        $isAdmin = self::isAdmin($userId);

        if (!$isOwner && !$isAdmin) {
            self::addError(ApiErrorCodes::RESOURCE_FORBIDDEN, 'Only the idea owner or an admin can delete media');
            return false;
        }

        try {
            Database::query(
                "DELETE FROM idea_media WHERE id = ? AND tenant_id = ?",
                [$mediaId, $tenantId]
            );
            return true;
        } catch (\Throwable $e) {
            error_log("Idea media deletion failed: " . $e->getMessage());
            self::addError(ApiErrorCodes::SERVER_INTERNAL_ERROR, 'Failed to delete media');
            return false;
        }
    }

    private static function isAdmin(int $userId): bool
    {
        $user = Database::query(
            "SELECT role FROM users WHERE id = ? AND tenant_id = ?",
            [$userId, TenantContext::getId()]
        )->fetch();

        return $user && in_array($user['role'] ?? '', ['admin', 'tenant_admin', 'tenant_super_admin', 'super_admin']);
    }
}
