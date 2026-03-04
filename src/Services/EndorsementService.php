<?php
// Copyright © 2024–2026 Jasper Ford
// SPDX-License-Identifier: AGPL-3.0-or-later
// Author: Jasper Ford
// See NOTICE file for attribution and acknowledgements.

namespace Nexus\Services;

use Nexus\Core\Database;
use Nexus\Core\TenantContext;
use Nexus\Models\Notification;

/**
 * EndorsementService - Skill endorsement system (LinkedIn-style)
 *
 * Provides:
 * - Endorse a member's skill
 * - Withdraw endorsement
 * - Get endorsement counts per skill
 * - Get "top endorsed" members
 * - Check if user has endorsed another's skill
 */
class EndorsementService
{
    private static array $errors = [];

    public static function getErrors(): array
    {
        return self::$errors;
    }

    /**
     * Endorse a member's skill
     *
     * @param int $endorserId Who is endorsing
     * @param int $endorsedId Who is being endorsed
     * @param string $skillName Name of the skill
     * @param int|null $skillId Optional user_skills.id
     * @param string|null $comment Optional comment
     * @return int|null Endorsement ID
     */
    public static function endorse(int $endorserId, int $endorsedId, string $skillName, ?int $skillId = null, ?string $comment = null): ?int
    {
        self::$errors = [];
        $tenantId = TenantContext::getId();

        // Cannot endorse yourself
        if ($endorserId === $endorsedId) {
            self::$errors[] = ['code' => 'SELF_ENDORSEMENT', 'message' => 'You cannot endorse yourself'];
            return null;
        }

        // Check skill name
        $skillName = trim($skillName);
        if (empty($skillName) || strlen($skillName) > 100) {
            self::$errors[] = ['code' => 'VALIDATION_ERROR', 'message' => 'Skill name is required (max 100 chars)', 'field' => 'skill_name'];
            return null;
        }

        // Check endorsed user exists in same tenant
        $endorsed = Database::query(
            "SELECT id, first_name, last_name FROM users WHERE id = ? AND tenant_id = ?",
            [$endorsedId, $tenantId]
        )->fetch(\PDO::FETCH_ASSOC);

        if (!$endorsed) {
            self::$errors[] = ['code' => 'NOT_FOUND', 'message' => 'Member not found'];
            return null;
        }

        // Check for existing endorsement
        $existing = Database::query(
            "SELECT id FROM skill_endorsements WHERE endorser_id = ? AND endorsed_id = ? AND skill_name = ? AND tenant_id = ?",
            [$endorserId, $endorsedId, $skillName, $tenantId]
        )->fetch();

        if ($existing) {
            self::$errors[] = ['code' => 'ALREADY_ENDORSED', 'message' => 'You have already endorsed this skill'];
            return null;
        }

        // Validate comment length
        if ($comment !== null) {
            $comment = trim($comment);
            if (strlen($comment) > 500) {
                $comment = substr($comment, 0, 500);
            }
        }

        Database::query(
            "INSERT INTO skill_endorsements (endorser_id, endorsed_id, skill_id, skill_name, tenant_id, comment)
             VALUES (?, ?, ?, ?, ?, ?)",
            [$endorserId, $endorsedId, $skillId, $skillName, $tenantId, $comment]
        );

        $endorsementId = (int)Database::lastInsertId();

        // Send notification
        try {
            $endorserName = Database::query(
                "SELECT CONCAT(first_name, ' ', last_name) as name FROM users WHERE id = ?",
                [$endorserId]
            )->fetchColumn();

            $basePath = TenantContext::getSlugPrefix();
            Notification::create(
                $endorsedId,
                "{$endorserName} endorsed your skill: {$skillName}",
                "{$basePath}/profile/{$endorsedId}",
                'endorsement'
            );

            // Send email notification
            $endorsedUser = Database::query(
                "SELECT email, first_name, name FROM users WHERE id = ? AND tenant_id = ?",
                [$endorsedId, $tenantId]
            )->fetch();

            if ($endorsedUser && !empty($endorsedUser['email'])) {
                $tenantName = TenantContext::getSetting('site_name', 'Project NEXUS');
                $frontendUrl = TenantContext::getFrontendUrl();
                $profileUrl = $frontendUrl . $basePath . '/profile/' . $endorsedId;

                $html = \Nexus\Core\EmailTemplate::render(
                    "New Skill Endorsement",
                    "{$endorserName} endorsed your skill",
                    "<strong>Skill:</strong> " . htmlspecialchars($skillName) . "<br><br>" .
                    ($comment ? "<strong>Comment:</strong> " . htmlspecialchars($comment) . "<br><br>" : "") .
                    "Endorsements help build trust in your community profile.",
                    "View Profile",
                    $profileUrl,
                    $tenantName
                );

                $mailer = new \Nexus\Core\Mailer();
                $mailer->send($endorsedUser['email'], "New Skill Endorsement - {$tenantName}", $html);
            }
        } catch (\Exception $e) {
            // Non-critical
        }

        return $endorsementId;
    }

    /**
     * Remove an endorsement
     */
    public static function removeEndorsement(int $endorserId, int $endorsedId, string $skillName): bool
    {
        $tenantId = TenantContext::getId();

        Database::query(
            "DELETE FROM skill_endorsements WHERE endorser_id = ? AND endorsed_id = ? AND skill_name = ? AND tenant_id = ?",
            [$endorserId, $endorsedId, $skillName, $tenantId]
        );

        return true;
    }

    /**
     * Get endorsements received by a user, grouped by skill
     */
    public static function getEndorsementsForUser(int $userId): array
    {
        $tenantId = TenantContext::getId();

        return Database::query(
            "SELECT se.skill_name, COUNT(*) as count,
                    GROUP_CONCAT(CONCAT(u.first_name, ' ', u.last_name) ORDER BY se.created_at DESC SEPARATOR ', ') as endorsed_by_names,
                    GROUP_CONCAT(u.id ORDER BY se.created_at DESC) as endorsed_by_ids,
                    GROUP_CONCAT(u.avatar_url ORDER BY se.created_at DESC) as endorsed_by_avatars,
                    MAX(se.created_at) as latest_endorsement
             FROM skill_endorsements se
             JOIN users u ON se.endorser_id = u.id
             WHERE se.endorsed_id = ? AND se.tenant_id = ?
             GROUP BY se.skill_name
             ORDER BY count DESC",
            [$userId, $tenantId]
        )->fetchAll(\PDO::FETCH_ASSOC);
    }

    /**
     * Get detailed endorsements for a specific skill
     */
    public static function getSkillEndorsements(int $userId, string $skillName): array
    {
        $tenantId = TenantContext::getId();

        return Database::query(
            "SELECT se.id, se.comment, se.created_at,
                    u.id as endorser_id,
                    CONCAT(u.first_name, ' ', u.last_name) as endorser_name,
                    u.avatar_url as endorser_avatar
             FROM skill_endorsements se
             JOIN users u ON se.endorser_id = u.id
             WHERE se.endorsed_id = ? AND se.skill_name = ? AND se.tenant_id = ?
             ORDER BY se.created_at DESC",
            [$userId, $skillName, $tenantId]
        )->fetchAll(\PDO::FETCH_ASSOC);
    }

    /**
     * Check if a user has endorsed another's skill
     */
    public static function hasEndorsed(int $endorserId, int $endorsedId, string $skillName): bool
    {
        $tenantId = TenantContext::getId();

        $row = Database::query(
            "SELECT 1 FROM skill_endorsements WHERE endorser_id = ? AND endorsed_id = ? AND skill_name = ? AND tenant_id = ? LIMIT 1",
            [$endorserId, $endorsedId, $skillName, $tenantId]
        )->fetch();

        return (bool)$row;
    }

    /**
     * Get endorsement counts for multiple skills of a user (batch)
     */
    public static function getEndorsementCounts(int $userId): array
    {
        $tenantId = TenantContext::getId();

        $rows = Database::query(
            "SELECT skill_name, COUNT(*) as count
             FROM skill_endorsements
             WHERE endorsed_id = ? AND tenant_id = ?
             GROUP BY skill_name",
            [$userId, $tenantId]
        )->fetchAll(\PDO::FETCH_ASSOC);

        $counts = [];
        foreach ($rows as $row) {
            $counts[$row['skill_name']] = (int)$row['count'];
        }

        return $counts;
    }

    /**
     * Get top endorsed members across the tenant
     *
     * @param int $limit
     * @return array
     */
    public static function getTopEndorsedMembers(int $limit = 10): array
    {
        $tenantId = TenantContext::getId();

        return Database::query(
            "SELECT se.endorsed_id as user_id,
                    CONCAT(u.first_name, ' ', u.last_name) as name,
                    u.avatar_url,
                    COUNT(*) as total_endorsements,
                    COUNT(DISTINCT se.skill_name) as skills_endorsed
             FROM skill_endorsements se
             JOIN users u ON se.endorsed_id = u.id
             WHERE se.tenant_id = ?
             GROUP BY se.endorsed_id
             ORDER BY total_endorsements DESC
             LIMIT ?",
            [$tenantId, $limit]
        )->fetchAll(\PDO::FETCH_ASSOC);
    }

    /**
     * Get endorsement stats for a user (for badges)
     */
    public static function getStats(int $userId): array
    {
        $tenantId = TenantContext::getId();

        $received = Database::query(
            "SELECT COUNT(*) as total FROM skill_endorsements WHERE endorsed_id = ? AND tenant_id = ?",
            [$userId, $tenantId]
        )->fetchColumn();

        $given = Database::query(
            "SELECT COUNT(*) as total FROM skill_endorsements WHERE endorser_id = ? AND tenant_id = ?",
            [$userId, $tenantId]
        )->fetchColumn();

        $uniqueSkills = Database::query(
            "SELECT COUNT(DISTINCT skill_name) as total FROM skill_endorsements WHERE endorsed_id = ? AND tenant_id = ?",
            [$userId, $tenantId]
        )->fetchColumn();

        return [
            'endorsements_received' => (int)$received,
            'endorsements_given' => (int)$given,
            'skills_endorsed' => (int)$uniqueSkills,
        ];
    }
}
