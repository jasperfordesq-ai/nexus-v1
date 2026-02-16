<?php

namespace Nexus\Services;

use Nexus\Core\Database;
use Nexus\Core\TenantContext;

/**
 * VettingService - DBS/Garda vetting record management for TOL2 compliance
 *
 * Manages vetting records lifecycle: creation, verification, rejection, expiry tracking.
 * All queries are tenant-scoped. The user's quick-lookup vetting_status column is
 * automatically kept in sync whenever records change.
 */
class VettingService
{
    /**
     * Get all vetting records for a user
     */
    public static function getUserRecords(int $userId): array
    {
        $tenantId = TenantContext::getId();
        $stmt = Database::query(
            "SELECT vr.*, u.first_name, u.last_name, u.email,
                    vb.first_name as verifier_first_name, vb.last_name as verifier_last_name
             FROM vetting_records vr
             JOIN users u ON u.id = vr.user_id
             LEFT JOIN users vb ON vb.id = vr.verified_by
             WHERE vr.tenant_id = ? AND vr.user_id = ?
             ORDER BY vr.created_at DESC",
            [$tenantId, $userId]
        );
        return $stmt->fetchAll();
    }

    /**
     * Get a single vetting record by ID
     */
    public static function getById(int $id): ?array
    {
        $tenantId = TenantContext::getId();
        $stmt = Database::query(
            "SELECT vr.*, u.first_name, u.last_name, u.email, u.avatar_url,
                    vb.first_name as verifier_first_name, vb.last_name as verifier_last_name
             FROM vetting_records vr
             JOIN users u ON u.id = vr.user_id
             LEFT JOIN users vb ON vb.id = vr.verified_by
             WHERE vr.id = ? AND vr.tenant_id = ?",
            [$id, $tenantId]
        );
        $record = $stmt->fetch();
        return $record ?: null;
    }

    /**
     * Get all vetting records with filters (for admin listing)
     */
    public static function getAll(array $filters = []): array
    {
        $tenantId = TenantContext::getId();
        $where = ['vr.tenant_id = ?'];
        $params = [$tenantId];

        if (!empty($filters['status'])) {
            $where[] = 'vr.status = ?';
            $params[] = $filters['status'];
        }
        if (!empty($filters['vetting_type'])) {
            $where[] = 'vr.vetting_type = ?';
            $params[] = $filters['vetting_type'];
        }
        if (!empty($filters['expiring_soon'])) {
            $where[] = 'vr.expiry_date BETWEEN CURDATE() AND DATE_ADD(CURDATE(), INTERVAL 30 DAY)';
        }
        if (!empty($filters['expired'])) {
            $where[] = 'vr.expiry_date < CURDATE() AND vr.status = "verified"';
        }
        if (!empty($filters['search'])) {
            $where[] = '(u.first_name LIKE ? OR u.last_name LIKE ? OR u.email LIKE ? OR vr.reference_number LIKE ?)';
            $search = '%' . $filters['search'] . '%';
            $params = array_merge($params, [$search, $search, $search, $search]);
        }

        $page = max(1, (int)($filters['page'] ?? 1));
        $perPage = min(100, max(10, (int)($filters['per_page'] ?? 25)));
        $offset = ($page - 1) * $perPage;

        $whereClause = implode(' AND ', $where);

        // Count total
        $countStmt = Database::query(
            "SELECT COUNT(*) as cnt FROM vetting_records vr
             JOIN users u ON u.id = vr.user_id
             WHERE {$whereClause}",
            $params
        );
        $total = (int)$countStmt->fetch()['cnt'];

        // Fetch records
        $stmt = Database::query(
            "SELECT vr.*, u.first_name, u.last_name, u.email, u.avatar_url,
                    vb.first_name as verifier_first_name, vb.last_name as verifier_last_name
             FROM vetting_records vr
             JOIN users u ON u.id = vr.user_id
             LEFT JOIN users vb ON vb.id = vr.verified_by
             WHERE {$whereClause}
             ORDER BY vr.created_at DESC
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
                 FROM vetting_records WHERE tenant_id = ?",
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
     * Create a new vetting record
     */
    public static function create(array $data): int
    {
        $tenantId = TenantContext::getId();

        Database::query(
            "INSERT INTO vetting_records (tenant_id, user_id, vetting_type, status, reference_number,
             issue_date, expiry_date, notes, works_with_children, works_with_vulnerable_adults,
             requires_enhanced_check, created_at)
             VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, NOW())",
            [
                $tenantId,
                $data['user_id'],
                $data['vetting_type'] ?? 'dbs_basic',
                $data['status'] ?? 'pending',
                $data['reference_number'] ?? null,
                $data['issue_date'] ?? null,
                $data['expiry_date'] ?? null,
                $data['notes'] ?? null,
                $data['works_with_children'] ?? 0,
                $data['works_with_vulnerable_adults'] ?? 0,
                $data['requires_enhanced_check'] ?? 0,
            ]
        );

        $id = Database::lastInsertId();

        // Update user's vetting_status
        self::updateUserVettingStatus($data['user_id']);

        return (int)$id;
    }

    /**
     * Update a vetting record
     */
    public static function update(int $id, array $data): bool
    {
        $tenantId = TenantContext::getId();

        $fields = [];
        $params = [];

        $allowed = ['vetting_type', 'status', 'reference_number', 'issue_date', 'expiry_date',
                     'notes', 'works_with_children', 'works_with_vulnerable_adults', 'requires_enhanced_check'];

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
            "UPDATE vetting_records SET " . implode(', ', $fields) . " WHERE id = ? AND tenant_id = ?",
            $params
        );

        // Get user_id and update their status
        $record = Database::query(
            "SELECT user_id FROM vetting_records WHERE id = ? AND tenant_id = ?",
            [$id, $tenantId]
        )->fetch();
        if ($record) {
            self::updateUserVettingStatus((int)$record['user_id']);
        }

        return true;
    }

    /**
     * Verify a vetting record (broker action)
     */
    public static function verify(int $id, int $verifiedBy): bool
    {
        $tenantId = TenantContext::getId();

        Database::query(
            "UPDATE vetting_records SET status = 'verified', verified_by = ?, verified_at = NOW()
             WHERE id = ? AND tenant_id = ?",
            [$verifiedBy, $id, $tenantId]
        );

        $record = Database::query(
            "SELECT user_id FROM vetting_records WHERE id = ? AND tenant_id = ?",
            [$id, $tenantId]
        )->fetch();
        if ($record) {
            self::updateUserVettingStatus((int)$record['user_id']);
        }

        return true;
    }

    /**
     * Reject a vetting record
     */
    public static function reject(int $id, int $rejectedBy, string $reason = ''): bool
    {
        $tenantId = TenantContext::getId();

        Database::query(
            "UPDATE vetting_records SET status = 'rejected', verified_by = ?, verified_at = NOW(),
             notes = CONCAT(IFNULL(notes, ''), '\nRejected: ', ?)
             WHERE id = ? AND tenant_id = ?",
            [$rejectedBy, $reason, $id, $tenantId]
        );

        $record = Database::query(
            "SELECT user_id FROM vetting_records WHERE id = ? AND tenant_id = ?",
            [$id, $tenantId]
        )->fetch();
        if ($record) {
            self::updateUserVettingStatus((int)$record['user_id']);
        }

        return true;
    }

    /**
     * Delete a vetting record
     */
    public static function delete(int $id): bool
    {
        $tenantId = TenantContext::getId();

        $record = Database::query(
            "SELECT user_id FROM vetting_records WHERE id = ? AND tenant_id = ?",
            [$id, $tenantId]
        )->fetch();

        Database::query(
            "DELETE FROM vetting_records WHERE id = ? AND tenant_id = ?",
            [$id, $tenantId]
        );

        if ($record) {
            self::updateUserVettingStatus((int)$record['user_id']);
        }

        return true;
    }

    /**
     * Update user's quick-lookup vetting status based on their records
     */
    private static function updateUserVettingStatus(int $userId): void
    {
        $tenantId = TenantContext::getId();

        try {
            $best = Database::query(
                "SELECT status, expiry_date FROM vetting_records
                 WHERE tenant_id = ? AND user_id = ?
                 ORDER BY FIELD(status, 'verified', 'submitted', 'pending', 'expired', 'rejected', 'revoked'), created_at DESC
                 LIMIT 1",
                [$tenantId, $userId]
            )->fetch();

            if (!$best) {
                Database::query(
                    "UPDATE users SET vetting_status = 'none', vetting_expires_at = NULL WHERE id = ?",
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
                "UPDATE users SET vetting_status = ?, vetting_expires_at = ? WHERE id = ?",
                [$status, $expiresAt, $userId]
            );
        } catch (\Exception $e) {
            // Columns may not exist yet
        }
    }

    /**
     * Check if a user has valid vetting for a specific type
     */
    public static function hasValidVetting(int $userId, ?string $type = null): bool
    {
        $tenantId = TenantContext::getId();
        $where = "tenant_id = ? AND user_id = ? AND status = 'verified' AND (expiry_date IS NULL OR expiry_date >= CURDATE())";
        $params = [$tenantId, $userId];

        if ($type) {
            $where .= ' AND vetting_type = ?';
            $params[] = $type;
        }

        $row = Database::query(
            "SELECT COUNT(*) as cnt FROM vetting_records WHERE {$where}",
            $params
        )->fetch();
        return ((int)($row['cnt'] ?? 0)) > 0;
    }
}
