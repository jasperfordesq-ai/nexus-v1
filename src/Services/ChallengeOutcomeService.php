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
 * ChallengeOutcomeService - Track implementation status of winning ideas
 *
 * After a challenge closes, tracks whether the winning idea was actually
 * implemented. Provides an impact dashboard per challenge.
 *
 * @package Nexus\Services
 */
class ChallengeOutcomeService
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
     * Get outcome for a challenge
     */
    public static function getForChallenge(int $challengeId): ?array
    {
        $tenantId = TenantContext::getId();

        $outcome = Database::query(
            "SELECT co.*, ci.title AS idea_title, ci.description AS idea_description,
                    u.first_name AS idea_author_first, u.last_name AS idea_author_last
             FROM challenge_outcomes co
             LEFT JOIN challenge_ideas ci ON co.winning_idea_id = ci.id
             LEFT JOIN users u ON ci.user_id = u.id
             WHERE co.challenge_id = ? AND co.tenant_id = ?",
            [$challengeId, $tenantId]
        )->fetch();

        if (!$outcome) {
            return null;
        }

        if ($outcome['winning_idea_id']) {
            $outcome['winning_idea'] = [
                'id' => (int)$outcome['winning_idea_id'],
                'title' => $outcome['idea_title'] ?? '',
                'description' => $outcome['idea_description'] ?? '',
                'author' => trim(($outcome['idea_author_first'] ?? '') . ' ' . ($outcome['idea_author_last'] ?? '')),
            ];
        } else {
            $outcome['winning_idea'] = null;
        }

        unset($outcome['idea_title'], $outcome['idea_description'], $outcome['idea_author_first'], $outcome['idea_author_last']);

        return $outcome;
    }

    /**
     * Create or update an outcome for a challenge
     *
     * @return int|null Outcome ID
     */
    public static function upsert(int $challengeId, int $userId, array $data): ?int
    {
        self::clearErrors();

        if (!self::isAdmin($userId)) {
            self::addError(ApiErrorCodes::RESOURCE_FORBIDDEN, 'Only admins can manage outcomes');
            return null;
        }

        $tenantId = TenantContext::getId();

        // Verify challenge exists
        $challenge = Database::query(
            "SELECT id, status FROM ideation_challenges WHERE id = ? AND tenant_id = ?",
            [$challengeId, $tenantId]
        )->fetch();

        if (!$challenge) {
            self::addError(ApiErrorCodes::RESOURCE_NOT_FOUND, 'Challenge not found');
            return null;
        }

        $status = $data['status'] ?? 'not_started';
        if (!in_array($status, ['not_started', 'in_progress', 'implemented', 'abandoned'])) {
            self::addError(ApiErrorCodes::VALIDATION_INVALID_VALUE, 'Invalid outcome status', 'status');
            return null;
        }

        $winningIdeaId = isset($data['winning_idea_id']) ? (int)$data['winning_idea_id'] : null;
        $impactDescription = !empty($data['impact_description']) ? trim($data['impact_description']) : null;

        // Validate winning idea belongs to this challenge
        if ($winningIdeaId) {
            $idea = Database::query(
                "SELECT id FROM challenge_ideas WHERE id = ? AND challenge_id = ? AND tenant_id = ?",
                [$winningIdeaId, $challengeId, $tenantId]
            )->fetch();

            if (!$idea) {
                self::addError(ApiErrorCodes::VALIDATION_INVALID_VALUE, 'Winning idea does not belong to this challenge', 'winning_idea_id');
                return null;
            }
        }

        // Check if outcome already exists
        $existing = Database::query(
            "SELECT id FROM challenge_outcomes WHERE challenge_id = ? AND tenant_id = ?",
            [$challengeId, $tenantId]
        )->fetch();

        try {
            if ($existing) {
                // Update
                Database::query(
                    "UPDATE challenge_outcomes SET winning_idea_id = ?, status = ?, impact_description = ?
                     WHERE challenge_id = ? AND tenant_id = ?",
                    [$winningIdeaId, $status, $impactDescription, $challengeId, $tenantId]
                );
                return (int)$existing['id'];
            } else {
                // Insert
                Database::query(
                    "INSERT INTO challenge_outcomes (challenge_id, winning_idea_id, tenant_id, status, impact_description)
                     VALUES (?, ?, ?, ?, ?)",
                    [$challengeId, $winningIdeaId, $tenantId, $status, $impactDescription]
                );
                return (int)Database::lastInsertId();
            }
        } catch (\Throwable $e) {
            error_log("Outcome upsert failed: " . $e->getMessage());
            self::addError(ApiErrorCodes::SERVER_INTERNAL_ERROR, 'Failed to save outcome');
            return null;
        }
    }

    /**
     * Get outcomes dashboard (all outcomes for a tenant)
     */
    public static function getDashboard(): array
    {
        $tenantId = TenantContext::getId();

        $outcomes = Database::query(
            "SELECT co.*, ic.title AS challenge_title, ic.status AS challenge_status,
                    ci.title AS idea_title
             FROM challenge_outcomes co
             INNER JOIN ideation_challenges ic ON co.challenge_id = ic.id
             LEFT JOIN challenge_ideas ci ON co.winning_idea_id = ci.id
             WHERE co.tenant_id = ?
             ORDER BY co.updated_at DESC",
            [$tenantId]
        )->fetchAll();

        // Summary stats
        $stats = [
            'total' => count($outcomes),
            'implemented' => 0,
            'in_progress' => 0,
            'not_started' => 0,
            'abandoned' => 0,
        ];

        foreach ($outcomes as $o) {
            $s = $o['status'] ?? 'not_started';
            if (isset($stats[$s])) {
                $stats[$s]++;
            }
        }

        return [
            'outcomes' => $outcomes,
            'stats' => $stats,
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
