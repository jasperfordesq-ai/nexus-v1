<?php
// Copyright © 2024–2026 Jasper Ford
// SPDX-License-Identifier: AGPL-3.0-or-later
// Author: Jasper Ford
// See NOTICE file for attribution and acknowledgements.

namespace App\Services\Identity;

use Illuminate\Support\Facades\DB;

/**
 * IdentityVerificationSessionService — CRUD and status management for verification sessions.
 */
class IdentityVerificationSessionService
{
    /**
     * Create a new verification session record.
     *
     * @param int    $tenantId
     * @param int    $userId
     * @param string $providerSlug
     * @param string $verificationLevel
     * @param array  $providerData Data returned from provider's createSession()
     * @return int The created session ID
     */
    public static function create(
        int $tenantId,
        int $userId,
        string $providerSlug,
        string $verificationLevel,
        array $providerData
    ): int {
        DB::statement(
            "INSERT INTO identity_verification_sessions
                (tenant_id, user_id, provider_slug, provider_session_id, verification_level,
                 status, redirect_url, client_token, expires_at)
             VALUES (?, ?, ?, ?, ?, 'created', ?, ?, ?)",
            [
                $tenantId,
                $userId,
                $providerSlug,
                $providerData['provider_session_id'] ?? null,
                $verificationLevel,
                $providerData['redirect_url'] ?? null,
                $providerData['client_token'] ?? null,
                $providerData['expires_at'] ?? null,
            ]
        );

        return (int) DB::getPdo()->lastInsertId();
    }

    /**
     * Get a session by ID.
     */
    public static function getById(int $sessionId): ?array
    {
        $row = DB::statement(
            "SELECT * FROM identity_verification_sessions WHERE id = ?",
            [$sessionId]
        )->fetch();

        return $row ?: null;
    }

    /**
     * Find a session by provider session ID and provider slug.
     */
    public static function findByProviderSession(string $providerSlug, string $providerSessionId): ?array
    {
        $row = DB::statement(
            "SELECT * FROM identity_verification_sessions
             WHERE provider_slug = ? AND provider_session_id = ?
             ORDER BY created_at DESC LIMIT 1",
            [$providerSlug, $providerSessionId]
        )->fetch();

        return $row ?: null;
    }

    /**
     * Get the latest session for a user.
     */
    public static function getLatestForUser(int $tenantId, int $userId): ?array
    {
        $row = DB::statement(
            "SELECT * FROM identity_verification_sessions
             WHERE tenant_id = ? AND user_id = ?
             ORDER BY created_at DESC LIMIT 1",
            [$tenantId, $userId]
        )->fetch();

        return $row ?: null;
    }

    /**
     * Get all sessions for a user.
     */
    public static function getAllForUser(int $tenantId, int $userId): array
    {
        return DB::statement(
            "SELECT * FROM identity_verification_sessions
             WHERE tenant_id = ? AND user_id = ?
             ORDER BY created_at DESC",
            [$tenantId, $userId]
        )->fetchAll();
    }

    /**
     * Update session status.
     */
    public static function updateStatus(
        int $sessionId,
        string $status,
        ?string $resultSummary = null,
        ?string $providerReference = null,
        ?string $failureReason = null
    ): void {
        $completedAt = in_array($status, ['passed', 'failed', 'expired', 'cancelled'], true)
            ? date('Y-m-d H:i:s')
            : null;

        DB::statement(
            "UPDATE identity_verification_sessions
             SET status = ?,
                 result_summary = COALESCE(?, result_summary),
                 provider_reference = COALESCE(?, provider_reference),
                 failure_reason = ?,
                 completed_at = COALESCE(?, completed_at),
                 updated_at = CURRENT_TIMESTAMP
             WHERE id = ?",
            [$status, $resultSummary, $providerReference, $failureReason, $completedAt, $sessionId]
        );
    }

    /**
     * Get sessions pending for a tenant (admin view).
     */
    public static function getPendingForTenant(int $tenantId, int $limit = 50, int $offset = 0): array
    {
        return DB::statement(
            "SELECT ivs.*, u.first_name, u.last_name, u.email
             FROM identity_verification_sessions ivs
             JOIN users u ON u.id = ivs.user_id
             WHERE ivs.tenant_id = ? AND ivs.status IN ('created', 'started', 'processing')
             ORDER BY ivs.created_at DESC
             LIMIT ? OFFSET ?",
            [$tenantId, $limit, $offset]
        )->fetchAll();
    }

    /**
     * Get abandoned sessions (created/started but not completed within $hoursOld hours).
     * Used by cron to send reminder emails.
     *
     * @param int $hoursOld  Sessions older than this many hours
     * @param int $limit
     * @return array Sessions with user info
     */
    public static function getAbandoned(int $hoursOld = 24, int $limit = 100): array
    {
        return DB::statement(
            "SELECT ivs.*, u.first_name, u.last_name, u.email, u.tenant_id
             FROM identity_verification_sessions ivs
             JOIN users u ON u.id = ivs.user_id
             WHERE ivs.status IN ('created', 'started')
               AND ivs.created_at < DATE_SUB(NOW(), INTERVAL ? HOUR)
               AND ivs.reminder_sent_at IS NULL
             ORDER BY ivs.created_at ASC
             LIMIT ?",
            [$hoursOld, $limit]
        )->fetchAll();
    }

    /**
     * Mark a session as having had a reminder sent.
     */
    public static function markReminderSent(int $sessionId): void
    {
        DB::statement(
            "UPDATE identity_verification_sessions SET reminder_sent_at = NOW() WHERE id = ?",
            [$sessionId]
        );
    }

    /**
     * Expire sessions older than the given hours that are still in created/started status.
     *
     * @param int $hoursOld  Sessions older than this many hours
     * @return int Number of sessions expired
     */
    public static function expireAbandoned(int $hoursOld = 72): int
    {
        $stmt = DB::statement(
            "UPDATE identity_verification_sessions
             SET status = 'expired', completed_at = NOW(), updated_at = NOW()
             WHERE status IN ('created', 'started')
               AND created_at < DATE_SUB(NOW(), INTERVAL ? HOUR)",
            [$hoursOld]
        );

        return $stmt->rowCount();
    }

    /**
     * Delete completed/expired sessions older than the retention period.
     * Keeps audit trail in identity_verification_events but cleans session table.
     *
     * @param int $retentionDays  Delete sessions older than this (default 180 days)
     * @return int Number of sessions deleted
     */
    public static function purgeOldSessions(int $retentionDays = 180): int
    {
        $stmt = DB::statement(
            "DELETE FROM identity_verification_sessions
             WHERE status IN ('passed', 'failed', 'expired', 'cancelled')
               AND completed_at IS NOT NULL
               AND completed_at < DATE_SUB(NOW(), INTERVAL ? DAY)",
            [$retentionDays]
        );

        return $stmt->rowCount();
    }
}
