<?php
// Copyright © 2024–2026 Jasper Ford
// SPDX-License-Identifier: AGPL-3.0-or-later
// Author: Jasper Ford
// See NOTICE file for attribution and acknowledgements.

namespace Nexus\Services;

use Nexus\Core\Database;
use Nexus\Core\TenantContext;

/**
 * InsuranceCertificateService - Insurance certificate management for compliance
 *
 * Manages insurance certificate lifecycle: creation, verification, rejection, expiry tracking.
 * All queries are tenant-scoped. The user's quick-lookup insurance_status column is
 * automatically kept in sync whenever records change.
 */
class InsuranceCertificateService
{
    /**
     * Get all insurance certificates for a user
     */
    public static function getUserCertificates(int $userId): array
    {
        $tenantId = TenantContext::getId();
        $stmt = Database::query(
            "SELECT ic.*, u.first_name, u.last_name, u.email,
                    vb.first_name as verifier_first_name, vb.last_name as verifier_last_name
             FROM insurance_certificates ic
             JOIN users u ON u.id = ic.user_id
             LEFT JOIN users vb ON vb.id = ic.verified_by
             WHERE ic.tenant_id = ? AND ic.user_id = ?
             ORDER BY ic.created_at DESC",
            [$tenantId, $userId]
        );
        return $stmt->fetchAll();
    }

    /**
     * Get a single insurance certificate by ID
     */
    public static function getById(int $id): ?array
    {
        $tenantId = TenantContext::getId();
        $stmt = Database::query(
            "SELECT ic.*, u.first_name, u.last_name, u.email, u.avatar_url,
                    vb.first_name as verifier_first_name, vb.last_name as verifier_last_name
             FROM insurance_certificates ic
             JOIN users u ON u.id = ic.user_id
             LEFT JOIN users vb ON vb.id = ic.verified_by
             WHERE ic.id = ? AND ic.tenant_id = ?",
            [$id, $tenantId]
        );
        $record = $stmt->fetch();
        return $record ?: null;
    }

    /**
     * Get all insurance certificates with filters (for admin listing)
     */
    public static function getAll(array $filters = []): array
    {
        $tenantId = TenantContext::getId();
        $where = ['ic.tenant_id = ?'];
        $params = [$tenantId];

        if (!empty($filters['status'])) {
            $where[] = 'ic.status = ?';
            $params[] = $filters['status'];
        }
        if (!empty($filters['insurance_type'])) {
            $where[] = 'ic.insurance_type = ?';
            $params[] = $filters['insurance_type'];
        }
        if (!empty($filters['expiring_soon'])) {
            $where[] = 'ic.expiry_date BETWEEN CURDATE() AND DATE_ADD(CURDATE(), INTERVAL 30 DAY)';
        }
        if (!empty($filters['expired'])) {
            $where[] = 'ic.expiry_date < CURDATE() AND ic.status = "verified"';
        }
        if (!empty($filters['search'])) {
            $where[] = '(u.first_name LIKE ? OR u.last_name LIKE ? OR u.email LIKE ? OR ic.policy_number LIKE ? OR ic.provider_name LIKE ?)';
            $search = '%' . $filters['search'] . '%';
            $params = array_merge($params, [$search, $search, $search, $search, $search]);
        }

        $page = max(1, (int)($filters['page'] ?? 1));
        $perPage = min(100, max(10, (int)($filters['per_page'] ?? 25)));
        $offset = ($page - 1) * $perPage;

        $whereClause = implode(' AND ', $where);

        // Count total
        $countStmt = Database::query(
            "SELECT COUNT(*) as cnt FROM insurance_certificates ic
             JOIN users u ON u.id = ic.user_id
             WHERE {$whereClause}",
            $params
        );
        $total = (int)$countStmt->fetch()['cnt'];

        // Fetch records
        $stmt = Database::query(
            "SELECT ic.*, u.first_name, u.last_name, u.email, u.avatar_url,
                    vb.first_name as verifier_first_name, vb.last_name as verifier_last_name
             FROM insurance_certificates ic
             JOIN users u ON u.id = ic.user_id
             LEFT JOIN users vb ON vb.id = ic.verified_by
             WHERE {$whereClause}
             ORDER BY ic.created_at DESC
             LIMIT {$perPage} OFFSET {$offset}",
            $params
        );

        return [
            'data' => $stmt->fetchAll(),
            'pagination' => [
                'total' => $total,
                'page' => $page,
                'per_page' => $perPage,
                'total_pages' => (int)ceil($total / $perPage),
            ]
        ];
    }

    /**
     * Get summary stats for broker dashboard
     */
    public static function getStats(): array
    {
        $tenantId = TenantContext::getId();

        $stats = ['pending' => 0, 'verified' => 0, 'expired' => 0, 'expiring_soon' => 0, 'total' => 0];

        try {
            $row = Database::query(
                "SELECT
                    COUNT(*) as total,
                    SUM(CASE WHEN status = 'pending' OR status = 'submitted' THEN 1 ELSE 0 END) as pending,
                    SUM(CASE WHEN status = 'verified' AND (expiry_date IS NULL OR expiry_date >= CURDATE()) THEN 1 ELSE 0 END) as verified,
                    SUM(CASE WHEN status = 'verified' AND expiry_date < CURDATE() THEN 1 ELSE 0 END) as expired,
                    SUM(CASE WHEN status = 'verified' AND expiry_date BETWEEN CURDATE() AND DATE_ADD(CURDATE(), INTERVAL 30 DAY) THEN 1 ELSE 0 END) as expiring_soon
                 FROM insurance_certificates WHERE tenant_id = ?",
                [$tenantId]
            )->fetch();
            $stats = [
                'total' => (int)($row['total'] ?? 0),
                'pending' => (int)($row['pending'] ?? 0),
                'verified' => (int)($row['verified'] ?? 0),
                'expired' => (int)($row['expired'] ?? 0),
                'expiring_soon' => (int)($row['expiring_soon'] ?? 0),
            ];
        } catch (\Exception $e) {
            // Table may not exist yet
        }

        return $stats;
    }

    /**
     * Create a new insurance certificate
     */
    public static function create(array $data): int
    {
        $tenantId = TenantContext::getId();

        Database::query(
            "INSERT INTO insurance_certificates (tenant_id, user_id, insurance_type, provider_name,
             policy_number, coverage_amount, start_date, expiry_date, certificate_file_path,
             status, notes, created_at)
             VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, NOW())",
            [
                $tenantId,
                $data['user_id'],
                $data['insurance_type'] ?? 'public_liability',
                $data['provider_name'] ?? null,
                $data['policy_number'] ?? null,
                $data['coverage_amount'] ?? null,
                $data['start_date'] ?? null,
                $data['expiry_date'] ?? null,
                $data['certificate_file_path'] ?? null,
                $data['status'] ?? 'pending',
                $data['notes'] ?? null,
            ]
        );

        $id = Database::lastInsertId();

        // Update user's insurance_status
        self::updateUserInsuranceStatus($data['user_id']);

        return (int)$id;
    }

    /**
     * Update an insurance certificate
     */
    public static function update(int $id, array $data): bool
    {
        $tenantId = TenantContext::getId();

        $fields = [];
        $params = [];

        $allowed = ['insurance_type', 'provider_name', 'policy_number', 'coverage_amount',
                     'start_date', 'expiry_date', 'certificate_file_path', 'status', 'notes'];

        foreach ($allowed as $field) {
            if (array_key_exists($field, $data)) {
                $fields[] = "{$field} = ?";
                $params[] = $data[$field];
            }
        }

        if (empty($fields)) {
            return false;
        }

        $params[] = $id;
        $params[] = $tenantId;

        Database::query(
            "UPDATE insurance_certificates SET " . implode(', ', $fields) . " WHERE id = ? AND tenant_id = ?",
            $params
        );

        // Get user_id and update their status
        $record = Database::query(
            "SELECT user_id FROM insurance_certificates WHERE id = ? AND tenant_id = ?",
            [$id, $tenantId]
        )->fetch();
        if ($record) {
            self::updateUserInsuranceStatus((int)$record['user_id']);
        }

        return true;
    }

    /**
     * Verify an insurance certificate (broker action)
     */
    public static function verify(int $id, int $verifiedBy): bool
    {
        $tenantId = TenantContext::getId();

        Database::query(
            "UPDATE insurance_certificates SET status = 'verified', verified_by = ?, verified_at = NOW()
             WHERE id = ? AND tenant_id = ?",
            [$verifiedBy, $id, $tenantId]
        );

        $record = Database::query(
            "SELECT user_id FROM insurance_certificates WHERE id = ? AND tenant_id = ?",
            [$id, $tenantId]
        )->fetch();
        if ($record) {
            self::updateUserInsuranceStatus((int)$record['user_id']);
        }

        return true;
    }

    /**
     * Reject an insurance certificate
     */
    public static function reject(int $id, int $rejectedBy, string $reason = ''): bool
    {
        $tenantId = TenantContext::getId();

        Database::query(
            "UPDATE insurance_certificates SET status = 'rejected', verified_by = ?, verified_at = NOW(),
             notes = CONCAT(IFNULL(notes, ''), '\nRejected: ', ?)
             WHERE id = ? AND tenant_id = ?",
            [$rejectedBy, $reason, $id, $tenantId]
        );

        $record = Database::query(
            "SELECT user_id FROM insurance_certificates WHERE id = ? AND tenant_id = ?",
            [$id, $tenantId]
        )->fetch();
        if ($record) {
            self::updateUserInsuranceStatus((int)$record['user_id']);
        }

        return true;
    }

    /**
     * Delete an insurance certificate
     */
    public static function delete(int $id): bool
    {
        $tenantId = TenantContext::getId();

        $record = Database::query(
            "SELECT user_id FROM insurance_certificates WHERE id = ? AND tenant_id = ?",
            [$id, $tenantId]
        )->fetch();

        Database::query(
            "DELETE FROM insurance_certificates WHERE id = ? AND tenant_id = ?",
            [$id, $tenantId]
        );

        if ($record) {
            self::updateUserInsuranceStatus((int)$record['user_id']);
        }

        return true;
    }

    /**
     * Update user's quick-lookup insurance status based on their certificates
     */
    private static function updateUserInsuranceStatus(int $userId): void
    {
        $tenantId = TenantContext::getId();

        try {
            $best = Database::query(
                "SELECT status, expiry_date FROM insurance_certificates
                 WHERE tenant_id = ? AND user_id = ?
                 ORDER BY FIELD(status, 'verified', 'submitted', 'pending', 'expired', 'rejected', 'revoked'), created_at DESC
                 LIMIT 1",
                [$tenantId, $userId]
            )->fetch();

            if (!$best) {
                Database::query(
                    "UPDATE users SET insurance_status = 'none', insurance_expires_at = NULL WHERE id = ?",
                    [$userId]
                );
                return;
            }

            $status = 'none';
            $expiresAt = $best['expiry_date'];

            if ($best['status'] === 'verified') {
                if ($expiresAt && strtotime($expiresAt) < time()) {
                    $status = 'expired';
                } else {
                    $status = 'verified';
                }
            } elseif (in_array($best['status'], ['pending', 'submitted'])) {
                $status = 'pending';
            }

            Database::query(
                "UPDATE users SET insurance_status = ?, insurance_expires_at = ? WHERE id = ?",
                [$status, $expiresAt, $userId]
            );
        } catch (\Exception $e) {
            // Columns may not exist yet
        }
    }

    /**
     * Check if a user has valid insurance (optionally of a specific type)
     */
    public static function hasValidInsurance(int $userId, ?string $type = null): bool
    {
        $tenantId = TenantContext::getId();
        $where = "tenant_id = ? AND user_id = ? AND status = 'verified' AND (expiry_date IS NULL OR expiry_date >= CURDATE())";
        $params = [$tenantId, $userId];

        if ($type) {
            $where .= ' AND insurance_type = ?';
            $params[] = $type;
        }

        $row = Database::query(
            "SELECT COUNT(*) as cnt FROM insurance_certificates WHERE {$where}",
            $params
        )->fetch();
        return ((int)($row['cnt'] ?? 0)) > 0;
    }
}
