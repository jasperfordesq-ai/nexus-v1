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
 * IdeaTeamConversionService - Convert winning ideas into project teams
 *
 * THE KEY FEATURE of the Ideation pipeline. Creates a Group from an idea,
 * records the conversion in idea_team_links, and sets up the group with
 * source tracking fields.
 *
 * This service extends the existing convertIdeaToGroup logic from
 * IdeationChallengeService by adding formal tracking via idea_team_links.
 *
 * @package Nexus\Services
 */
class IdeaTeamConversionService
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
     * Convert an idea into a project team (Group)
     *
     * @param int $ideaId The idea to convert
     * @param int $userId The user performing the conversion
     * @param array $options Optional overrides: visibility, name, description
     * @return array|null Group data on success, null on failure
     */
    public static function convert(int $ideaId, int $userId, array $options = []): ?array
    {
        self::clearErrors();

        $tenantId = TenantContext::getId();

        // 1. Get the idea with tenant scope
        $idea = Database::query(
            "SELECT ci.*, ic.tenant_id, ic.title AS challenge_title, ic.id AS challenge_id_resolved
             FROM challenge_ideas ci
             INNER JOIN ideation_challenges ic ON ci.challenge_id = ic.id
             WHERE ci.id = ? AND ic.tenant_id = ?",
            [$ideaId, $tenantId]
        )->fetch();

        if (!$idea) {
            self::addError(ApiErrorCodes::RESOURCE_NOT_FOUND, 'Idea not found');
            return null;
        }

        // 2. Validate idea status
        $status = $idea['status'] ?? '';
        if (!in_array($status, ['shortlisted', 'winner'], true)) {
            self::addError(
                ApiErrorCodes::VALIDATION_INVALID_VALUE,
                'Only shortlisted or winning ideas can be converted to teams'
            );
            return null;
        }

        // 3. Permission check: admin or idea creator
        $isAdmin = self::isAdmin($userId);
        $isCreator = (int)$idea['user_id'] === $userId;

        if (!$isAdmin && !$isCreator) {
            self::addError(ApiErrorCodes::RESOURCE_FORBIDDEN, 'Only admins or the idea creator can convert ideas to teams');
            return null;
        }

        // 4. Check if already converted
        $existing = Database::query(
            "SELECT id, group_id FROM idea_team_links WHERE idea_id = ? AND tenant_id = ?",
            [$ideaId, $tenantId]
        )->fetch();

        if ($existing) {
            self::addError(
                ApiErrorCodes::RESOURCE_CONFLICT,
                'This idea has already been converted to a team'
            );
            return null;
        }

        // 5. Prepare group data
        $challengeId = (int)$idea['challenge_id'];
        $challengeTitle = $idea['challenge_title'] ?? 'Challenge';

        $groupName = $options['name'] ?? ($idea['title'] ?? 'Untitled Idea');
        $groupDescription = $options['description'] ?? (
            ($idea['description'] ?? '') . "\n\n---\nProject team created from idea in challenge: {$challengeTitle}"
        );
        $visibility = $options['visibility'] ?? 'public';
        if (!in_array($visibility, ['public', 'private'])) {
            $visibility = 'public';
        }

        // 6. Create the group
        $groupId = GroupService::create($userId, [
            'name' => $groupName,
            'description' => $groupDescription,
            'visibility' => $visibility,
        ]);

        if ($groupId === null) {
            $groupErrors = GroupService::getErrors();
            foreach ($groupErrors as $err) {
                self::addError(
                    $err['code'] ?? ApiErrorCodes::SERVER_INTERNAL_ERROR,
                    $err['message'] ?? 'Failed to create group'
                );
            }
            return null;
        }

        // 7. Set source columns on the group
        try {
            Database::query(
                "UPDATE `groups` SET source_idea_id = ?, source_challenge_id = ? WHERE id = ? AND tenant_id = ?",
                [$ideaId, $challengeId, $groupId, $tenantId]
            );
        } catch (\Throwable $e) {
            error_log("Failed to set source columns on group {$groupId}: " . $e->getMessage());
        }

        // 8. Record the conversion in idea_team_links
        try {
            Database::query(
                "INSERT INTO idea_team_links (idea_id, group_id, challenge_id, tenant_id, converted_by, converted_at)
                 VALUES (?, ?, ?, ?, ?, NOW())",
                [$ideaId, $groupId, $challengeId, $tenantId, $userId]
            );
        } catch (\Throwable $e) {
            error_log("Failed to record idea-team link: " . $e->getMessage());
            // Non-fatal — the group was already created
        }

        // 9. Award gamification points
        try {
            if (class_exists('\Nexus\Models\Gamification')) {
                \Nexus\Models\Gamification::awardPoints($userId, 15, 'Converted an idea into a project team');
            }
        } catch (\Throwable $e) {
            // Gamification is optional
        }

        // 10. Return the group data
        return GroupService::getById($groupId, $userId);
    }

    /**
     * Get all team links for a challenge
     *
     * @param int $challengeId
     * @return array
     */
    public static function getLinksForChallenge(int $challengeId): array
    {
        $tenantId = TenantContext::getId();

        return Database::query(
            "SELECT itl.*, ci.title AS idea_title, g.name AS group_name,
                    u.first_name, u.last_name
             FROM idea_team_links itl
             INNER JOIN challenge_ideas ci ON itl.idea_id = ci.id
             LEFT JOIN `groups` g ON itl.group_id = g.id
             LEFT JOIN users u ON itl.converted_by = u.id
             WHERE itl.challenge_id = ? AND itl.tenant_id = ?
             ORDER BY itl.converted_at DESC",
            [$challengeId, $tenantId]
        )->fetchAll();
    }

    /**
     * Get team link for a specific idea
     *
     * @param int $ideaId
     * @return array|null
     */
    public static function getLinkForIdea(int $ideaId): ?array
    {
        $tenantId = TenantContext::getId();

        $row = Database::query(
            "SELECT itl.*, g.name AS group_name
             FROM idea_team_links itl
             LEFT JOIN `groups` g ON itl.group_id = g.id
             WHERE itl.idea_id = ? AND itl.tenant_id = ?",
            [$ideaId, $tenantId]
        )->fetch();

        return $row ?: null;
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
