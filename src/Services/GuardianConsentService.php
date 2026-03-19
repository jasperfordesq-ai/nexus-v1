<?php
// Copyright © 2024–2026 Jasper Ford
// SPDX-License-Identifier: AGPL-3.0-or-later
// Author: Jasper Ford
// See NOTICE file for attribution and acknowledgements.

namespace Nexus\Services;

use Nexus\Core\Database;
use Nexus\Core\TenantContext;
use Nexus\Core\Mailer;

/**
 * GuardianConsentService - Manages parental/guardian consent for minor volunteers
 *
 * Handles:
 * - Consent request creation with secure tokens
 * - Guardian consent granting via email link
 * - Consent withdrawal
 * - Active consent verification (blanket or opportunity-specific)
 * - Admin consent management
 * - Automated expiry of stale consents (cron)
 * - Minor status detection based on date_of_birth
 */
class GuardianConsentService
{
    /**
     * Request guardian consent for a minor user
     *
     * @param int $minorUserId The minor user who needs consent
     * @param array $guardianData [
     *   'guardian_name' => string (required),
     *   'guardian_email' => string (required),
     *   'guardian_phone' => ?string,
     *   'relationship' => string (required: parent|guardian|other),
     * ]
     * @param int|null $opportunityId If set, consent is for a specific opportunity; null = blanket consent
     * @return array The created consent record
     * @throws \InvalidArgumentException On validation failure
     */
    public static function requestConsent(int $minorUserId, array $guardianData, ?int $opportunityId = null): array
    {
        $db = Database::getConnection();
        $tenantId = TenantContext::getId();

        // Validate required guardian fields
        $required = ['guardian_name', 'guardian_email', 'relationship'];
        foreach ($required as $field) {
            if (empty($guardianData[$field])) {
                throw new \InvalidArgumentException("Field '{$field}' is required.");
            }
        }

        if (!filter_var($guardianData['guardian_email'], FILTER_VALIDATE_EMAIL)) {
            throw new \InvalidArgumentException("Invalid guardian email address.");
        }

        $validRelationships = ['parent', 'guardian', 'other'];
        if (!in_array($guardianData['relationship'], $validRelationships, true)) {
            throw new \InvalidArgumentException("Invalid relationship. Must be one of: " . implode(', ', $validRelationships));
        }

        // Verify the user is actually a minor
        if (!self::isMinor($minorUserId)) {
            throw new \InvalidArgumentException("User is not a minor. Guardian consent is not required.");
        }

        // Generate secure token
        $token = bin2hex(random_bytes(32));
        $expiresAt = date('Y-m-d H:i:s', strtotime('+30 days'));

        $stmt = $db->prepare(
            "INSERT INTO vol_guardian_consents
             (tenant_id, minor_user_id, opportunity_id, guardian_name, guardian_email,
              guardian_phone, relationship, consent_token, status, expires_at, created_at)
             VALUES (?, ?, ?, ?, ?, ?, ?, ?, 'pending', ?, NOW())"
        );
        $stmt->execute([
            $tenantId,
            $minorUserId,
            $opportunityId,
            $guardianData['guardian_name'],
            $guardianData['guardian_email'],
            $guardianData['guardian_phone'] ?? null,
            $guardianData['relationship'],
            $token,
            $expiresAt,
        ]);

        $consentId = (int) $db->lastInsertId();

        // Get minor user details for the email
        $minorStmt = $db->prepare(
            "SELECT first_name, last_name FROM users WHERE id = ? AND tenant_id = ?"
        );
        $minorStmt->execute([$minorUserId, $tenantId]);
        $minor = $minorStmt->fetch(\PDO::FETCH_ASSOC);

        $minorName = $minor ? trim($minor['first_name'] . ' ' . $minor['last_name']) : 'A minor user';

        // Get opportunity title if applicable
        $opportunityTitle = null;
        if ($opportunityId) {
            $oppStmt = $db->prepare(
                "SELECT opp.title FROM vol_opportunities opp
                 JOIN vol_organizations org ON opp.organization_id = org.id
                 WHERE opp.id = ? AND org.tenant_id = ?"
            );
            $oppStmt->execute([$opportunityId, $tenantId]);
            $opportunityTitle = $oppStmt->fetchColumn();
        }

        // Send consent request email to guardian
        self::sendConsentEmail(
            $guardianData['guardian_email'],
            $guardianData['guardian_name'],
            $minorName,
            $token,
            $opportunityTitle,
            $tenantId
        );

        // Return the created record
        return self::getConsentById($consentId);
    }

    /**
     * Grant consent via secure token (called when guardian clicks email link)
     *
     * @param string $token The consent verification token
     * @param string $ip The IP address of the guardian granting consent
     * @return bool True if consent was successfully granted
     */
    public static function grantConsent(string $token, string $ip): bool
    {
        $db = Database::getConnection();

        // Find the consent record by token (no tenant scope needed - token is globally unique)
        $stmt = $db->prepare(
            "SELECT id, tenant_id, status, expires_at
             FROM vol_guardian_consents
             WHERE consent_token = ?"
        );
        $stmt->execute([$token]);
        $consent = $stmt->fetch(\PDO::FETCH_ASSOC);

        if (!$consent) {
            return false;
        }

        // Check if already processed
        if ($consent['status'] !== 'pending') {
            return false;
        }

        // Check if expired
        if ($consent['expires_at'] && strtotime($consent['expires_at']) < time()) {
            // Mark as expired
            $db->prepare(
                "UPDATE vol_guardian_consents SET status = 'expired' WHERE id = ?"
            )->execute([$consent['id']]);
            return false;
        }

        // Grant consent
        $stmt = $db->prepare(
            "UPDATE vol_guardian_consents
             SET status = 'active', consent_given_at = NOW(), consent_ip = ?
             WHERE id = ? AND tenant_id = ?"
        );
        $stmt->execute([$ip, $consent['id'], $consent['tenant_id']]);

        return $stmt->rowCount() > 0;
    }

    /**
     * Withdraw an active consent
     *
     * @param int $consentId The consent record ID
     * @param int $userId The authenticated user ID (must be the minor_user_id on the consent)
     * @return bool True if consent was successfully withdrawn
     */
    public static function withdrawConsent(int $consentId, int $userId): bool
    {
        $db = Database::getConnection();
        $tenantId = TenantContext::getId();

        $stmt = $db->prepare(
            "UPDATE vol_guardian_consents
             SET status = 'withdrawn', consent_withdrawn_at = NOW()
             WHERE id = ? AND tenant_id = ? AND minor_user_id = ? AND status = 'active'"
        );
        $stmt->execute([$consentId, $tenantId, $userId]);

        return $stmt->rowCount() > 0;
    }

    /**
     * Check if a minor has active consent
     *
     * @param int $minorUserId The minor user ID
     * @param int|null $opportunityId If set, checks for specific opportunity consent; also checks blanket consent
     * @return bool True if active consent exists
     */
    public static function checkConsent(int $minorUserId, ?int $opportunityId = null): bool
    {
        $db = Database::getConnection();
        $tenantId = TenantContext::getId();

        if ($opportunityId !== null) {
            // Check for specific opportunity consent OR blanket consent
            $stmt = $db->prepare(
                "SELECT COUNT(*) FROM vol_guardian_consents
                 WHERE tenant_id = ? AND minor_user_id = ? AND status = 'active'
                 AND (expires_at IS NULL OR expires_at > NOW())
                 AND (opportunity_id = ? OR opportunity_id IS NULL)"
            );
            $stmt->execute([$tenantId, $minorUserId, $opportunityId]);
        } else {
            // Check for any active consent (blanket)
            $stmt = $db->prepare(
                "SELECT COUNT(*) FROM vol_guardian_consents
                 WHERE tenant_id = ? AND minor_user_id = ? AND status = 'active'
                 AND (expires_at IS NULL OR expires_at > NOW())"
            );
            $stmt->execute([$tenantId, $minorUserId]);
        }

        return (int) $stmt->fetchColumn() > 0;
    }

    /**
     * Get all consent records for a minor
     *
     * @param int $minorUserId The minor user ID
     * @return array List of consent records
     */
    public static function getConsentsForMinor(int $minorUserId): array
    {
        $db = Database::getConnection();
        $tenantId = TenantContext::getId();

        $stmt = $db->prepare(
            "SELECT gc.*, opp.title as opportunity_title
             FROM vol_guardian_consents gc
             LEFT JOIN vol_opportunities opp ON gc.opportunity_id = opp.id
             WHERE gc.tenant_id = ? AND gc.minor_user_id = ?
             ORDER BY gc.created_at DESC"
        );
        $stmt->execute([$tenantId, $minorUserId]);

        return $stmt->fetchAll(\PDO::FETCH_ASSOC);
    }

    /**
     * Get consents for admin view with pagination and filters
     *
     * @param array $filters [
     *   'status' => ?string (pending|active|withdrawn|expired),
     *   'search' => ?string (searches guardian_name, guardian_email, minor user name),
     *   'cursor' => ?string (base64-encoded ID),
     *   'limit' => int (default 20, max 50),
     * ]
     * @return array ['items' => [], 'cursor' => string|null, 'has_more' => bool]
     */
    public static function getConsentsForAdmin(array $filters = []): array
    {
        $db = Database::getConnection();
        $tenantId = TenantContext::getId();

        $limit = min($filters['limit'] ?? 20, 50);
        $cursorId = null;

        if (!empty($filters['cursor'])) {
            $decoded = base64_decode($filters['cursor'], true);
            if ($decoded && is_numeric($decoded)) {
                $cursorId = (int) $decoded;
            }
        }

        $sql = "
            SELECT gc.*, u.first_name as minor_first_name, u.last_name as minor_last_name,
                   u.email as minor_email, opp.title as opportunity_title
            FROM vol_guardian_consents gc
            JOIN users u ON gc.minor_user_id = u.id
            LEFT JOIN vol_opportunities opp ON gc.opportunity_id = opp.id
            WHERE gc.tenant_id = ?
        ";
        $params = [$tenantId];

        if (!empty($filters['status'])) {
            $sql .= " AND gc.status = ?";
            $params[] = $filters['status'];
        }

        if (!empty($filters['search'])) {
            $escapedSearch = addcslashes($filters['search'], '%_');
            $searchTerm = '%' . $escapedSearch . '%';
            $sql .= " AND (gc.guardian_name LIKE ? OR gc.guardian_email LIKE ? OR u.first_name LIKE ? OR u.last_name LIKE ?)";
            $params[] = $searchTerm;
            $params[] = $searchTerm;
            $params[] = $searchTerm;
            $params[] = $searchTerm;
        }

        if ($cursorId) {
            $sql .= " AND gc.id < ?";
            $params[] = $cursorId;
        }

        $sql .= " ORDER BY gc.created_at DESC, gc.id DESC";
        $sql .= " LIMIT " . ($limit + 1);

        $stmt = $db->prepare($sql);
        $stmt->execute($params);
        $rows = $stmt->fetchAll(\PDO::FETCH_ASSOC);

        $hasMore = count($rows) > $limit;
        if ($hasMore) {
            array_pop($rows);
        }

        $lastId = null;
        foreach ($rows as $row) {
            $lastId = $row['id'];
        }

        return [
            'items' => $rows,
            'cursor' => $hasMore && $lastId ? base64_encode((string) $lastId) : null,
            'has_more' => $hasMore,
        ];
    }

    /**
     * Expire old consents past their expires_at date (cron method)
     *
     * @return int Number of consents expired
     */
    public static function expireOldConsents(): int
    {
        $db = Database::getConnection();

        // Expire pending consents that have passed their expiration date (all tenants)
        $stmt = $db->prepare(
            "UPDATE vol_guardian_consents
             SET status = 'expired'
             WHERE status = 'pending'
             AND expires_at IS NOT NULL
             AND expires_at < NOW()"
        );
        $stmt->execute();

        return $stmt->rowCount();
    }

    /**
     * Check if a user is a minor (under 18) based on their date_of_birth
     *
     * @param int $userId The user ID to check
     * @return bool True if the user is under 18
     */
    public static function isMinor(int $userId): bool
    {
        $db = Database::getConnection();
        $tenantId = TenantContext::getId();

        $stmt = $db->prepare(
            "SELECT date_of_birth FROM users WHERE id = ? AND tenant_id = ?"
        );
        $stmt->execute([$userId, $tenantId]);
        $dob = $stmt->fetchColumn();

        if (!$dob) {
            // If no date of birth is set, we cannot determine minor status
            return false;
        }

        $birthDate = new \DateTime($dob);
        $today = new \DateTime();
        $age = $today->diff($birthDate)->y;

        return $age < 18;
    }

    // ========================================
    // PRIVATE HELPERS
    // ========================================

    /**
     * Get a single consent record by ID (tenant-scoped)
     */
    private static function getConsentById(int $id): ?array
    {
        $db = Database::getConnection();
        $tenantId = TenantContext::getId();

        $stmt = $db->prepare(
            "SELECT gc.*, opp.title as opportunity_title
             FROM vol_guardian_consents gc
             LEFT JOIN vol_opportunities opp ON gc.opportunity_id = opp.id
             WHERE gc.id = ? AND gc.tenant_id = ?"
        );
        $stmt->execute([$id, $tenantId]);
        $row = $stmt->fetch(\PDO::FETCH_ASSOC);

        return $row ?: null;
    }

    /**
     * Send the consent request email to the guardian
     */
    private static function sendConsentEmail(
        string $guardianEmail,
        string $guardianName,
        string $minorName,
        string $token,
        ?string $opportunityTitle,
        int $tenantId
    ): void {
        // Build consent URL
        $baseUrl = rtrim($_ENV['APP_URL'] ?? 'https://app.project-nexus.ie', '/');
        $consentUrl = "{$baseUrl}/guardian-consent?token={$token}";

        $safeGuardianName = htmlspecialchars($guardianName, ENT_QUOTES, 'UTF-8');
        $safeMinorName = htmlspecialchars($minorName, ENT_QUOTES, 'UTF-8');
        $safeOpportunityTitle = $opportunityTitle
            ? htmlspecialchars($opportunityTitle, ENT_QUOTES, 'UTF-8')
            : null;

        $opportunityLine = $safeOpportunityTitle
            ? "<p><strong>Volunteering Opportunity:</strong> {$safeOpportunityTitle}</p>"
            : "<p>This is a blanket consent request covering all volunteering activities.</p>";

        $subject = "Guardian Consent Required - {$safeMinorName} Volunteering";
        $body = "
            <h2>Guardian Consent Request</h2>
            <p>Dear {$safeGuardianName},</p>
            <p>{$safeMinorName} has requested your consent to participate in volunteering activities on our platform.</p>
            {$opportunityLine}
            <p>To grant your consent, please click the link below:</p>
            <p><a href=\"{$consentUrl}\" style=\"display:inline-block;padding:12px 24px;background:#0070f3;color:#fff;text-decoration:none;border-radius:6px;\">Grant Consent</a></p>
            <p>This consent request will expire in 30 days.</p>
            <p>If you did not expect this request, please disregard this email.</p>
            <p>Thank you,<br>The Project NEXUS Team</p>
        ";

        try {
            $mailer = new Mailer($tenantId);
            $mailer->send($guardianEmail, $subject, $body);
        } catch (\Exception $e) {
            // Log the failure but don't prevent the consent record from being created
            error_log("GuardianConsentService: Failed to send consent email to {$guardianEmail}: " . $e->getMessage());
        }
    }
}
