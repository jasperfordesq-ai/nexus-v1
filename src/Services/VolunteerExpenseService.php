<?php
// Copyright © 2024–2026 Jasper Ford
// SPDX-License-Identifier: AGPL-3.0-or-later
// Author: Jasper Ford
// See NOTICE file for attribution and acknowledgements.

namespace Nexus\Services;

use Nexus\Core\Database;
use Nexus\Core\TenantContext;
use Nexus\Services\WebhookDispatchService;

/**
 * VolunteerExpenseService - Manages volunteer expense submissions, reviews, and policies
 *
 * Handles:
 * - Expense submission with policy validation
 * - Approval/rejection workflow
 * - Payment tracking
 * - Reporting and CSV export
 * - Per-tenant/org expense policies
 */
class VolunteerExpenseService
{
    /**
     * Submit a new expense claim
     *
     * @param int $userId The volunteer submitting the expense
     * @param array $data [
     *   'organization_id' => int (required),
     *   'opportunity_id' => ?int,
     *   'expense_type' => string (required: travel|meals|supplies|equipment|parking|other),
     *   'amount' => float (required),
     *   'currency' => string (default: 'EUR'),
     *   'description' => string (required),
     *   'receipt_path' => ?string,
     *   'receipt_filename' => ?string,
     *   'shift_id' => ?int,
     * ]
     * @return array The created expense record
     * @throws \InvalidArgumentException On validation failure
     */
    public static function submitExpense(int $userId, array $data): array
    {
        $db = Database::getConnection();
        $tenantId = TenantContext::getId();

        // --- Validate required fields ---
        $required = ['organization_id', 'expense_type', 'amount', 'description'];
        foreach ($required as $field) {
            if (empty($data[$field]) && $data[$field] !== 0) {
                throw new \InvalidArgumentException("Field '{$field}' is required.");
            }
        }

        $validTypes = ['travel', 'meals', 'supplies', 'equipment', 'parking', 'other'];
        if (!in_array($data['expense_type'], $validTypes, true)) {
            throw new \InvalidArgumentException("Invalid expense_type. Must be one of: " . implode(', ', $validTypes));
        }

        $amount = (float) $data['amount'];
        if ($amount <= 0) {
            throw new \InvalidArgumentException("Amount must be greater than zero.");
        }

        // --- Validate against expense policy ---
        $policy = self::getApplicablePolicy($tenantId, (int) $data['organization_id'], $data['expense_type']);

        if ($policy) {
            // Check per-expense max amount
            if (!empty($policy['max_amount']) && $amount > (float) $policy['max_amount']) {
                throw new \InvalidArgumentException(
                    "Amount exceeds the maximum allowed per expense ({$policy['max_amount']})."
                );
            }

            // Check monthly limit
            if (!empty($policy['max_monthly'])) {
                $monthStart = date('Y-m-01');
                $monthEnd = date('Y-m-t');
                $stmt = $db->prepare(
                    "SELECT COALESCE(SUM(amount), 0) as total
                     FROM vol_expenses
                     WHERE tenant_id = ? AND user_id = ? AND organization_id = ?
                     AND submitted_at BETWEEN ? AND ?
                     AND status != 'rejected'"
                );
                $stmt->execute([$tenantId, $userId, $data['organization_id'], $monthStart, $monthEnd]);
                $monthlyTotal = (float) $stmt->fetchColumn();

                if (($monthlyTotal + $amount) > (float) $policy['max_monthly']) {
                    throw new \InvalidArgumentException(
                        "This expense would exceed your monthly limit ({$policy['max_monthly']}). Current month total: {$monthlyTotal}."
                    );
                }
            }

            // Check receipt requirement
            if (!empty($policy['requires_receipt_above'])
                && $amount > (float) $policy['requires_receipt_above']
                && empty($data['receipt_path'])
            ) {
                throw new \InvalidArgumentException(
                    "A receipt is required for expenses above {$policy['requires_receipt_above']}."
                );
            }
        }

        // --- Insert expense ---
        $stmt = $db->prepare(
            "INSERT INTO vol_expenses
             (tenant_id, user_id, organization_id, opportunity_id, shift_id, expense_type,
              amount, currency, description, receipt_path, receipt_filename, status, submitted_at)
             VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, 'pending', NOW())"
        );
        $stmt->execute([
            $tenantId,
            $userId,
            (int) $data['organization_id'],
            $data['opportunity_id'] ?? null,
            $data['shift_id'] ?? null,
            $data['expense_type'],
            $amount,
            $data['currency'] ?? 'EUR',
            $data['description'],
            $data['receipt_path'] ?? null,
            $data['receipt_filename'] ?? null,
        ]);

        $expenseId = (int) $db->lastInsertId();

        // Webhook: expense.submitted
        try {
            WebhookDispatchService::dispatch('expense.submitted', [
                'user_id' => $userId,
                'expense_id' => $expenseId,
                'amount' => $amount,
            ]);
        } catch (\Throwable $e) {
            error_log("Webhook dispatch failed for expense.submitted: " . $e->getMessage());
        }

        return self::getExpense($expenseId);
    }

    /**
     * Get paginated list of expenses with filters
     *
     * @param array $filters [
     *   'user_id' => ?int,
     *   'organization_id' => ?int,
     *   'status' => ?string (pending|approved|rejected|paid),
     *   'date_from' => ?string (Y-m-d),
     *   'date_to' => ?string (Y-m-d),
     *   'cursor' => ?string (base64-encoded ID),
     *   'limit' => int (default 20, max 50),
     * ]
     * @return array ['items' => [], 'cursor' => string|null, 'has_more' => bool]
     */
    public static function getExpenses(array $filters = []): array
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
            SELECT e.*, u.first_name, u.last_name, u.email,
                   org.name as organization_name
            FROM vol_expenses e
            JOIN users u ON e.user_id = u.id
            JOIN vol_organizations org ON e.organization_id = org.id
            WHERE e.tenant_id = ?
        ";
        $params = [$tenantId];

        if (!empty($filters['user_id'])) {
            $sql .= " AND e.user_id = ?";
            $params[] = (int) $filters['user_id'];
        }

        if (!empty($filters['organization_id'])) {
            $sql .= " AND e.organization_id = ?";
            $params[] = (int) $filters['organization_id'];
        }

        if (!empty($filters['status'])) {
            $sql .= " AND e.status = ?";
            $params[] = $filters['status'];
        }

        if (!empty($filters['date_from'])) {
            $sql .= " AND e.submitted_at >= ?";
            $params[] = $filters['date_from'];
        }

        if (!empty($filters['date_to'])) {
            $sql .= " AND e.submitted_at <= ?";
            $params[] = $filters['date_to'];
        }

        if ($cursorId) {
            $sql .= " AND e.id < ?";
            $params[] = $cursorId;
        }

        $sql .= " ORDER BY e.submitted_at DESC, e.id DESC";
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
     * Get a single expense by ID (tenant-scoped)
     */
    public static function getExpense(int $id): ?array
    {
        $db = Database::getConnection();
        $tenantId = TenantContext::getId();

        $stmt = $db->prepare(
            "SELECT e.*, u.first_name, u.last_name, u.email,
                    org.name as organization_name
             FROM vol_expenses e
             JOIN users u ON e.user_id = u.id
             JOIN vol_organizations org ON e.organization_id = org.id
             WHERE e.id = ? AND e.tenant_id = ?"
        );
        $stmt->execute([$id, $tenantId]);
        $row = $stmt->fetch(\PDO::FETCH_ASSOC);

        return $row ?: null;
    }

    /**
     * Review (approve or reject) an expense
     *
     * @param int $id Expense ID
     * @param int $reviewerId The admin/org-admin reviewing
     * @param string $status 'approved' or 'rejected'
     * @param string|null $notes Optional reviewer notes
     * @return bool
     */
    public static function reviewExpense(int $id, int $reviewerId, string $status, ?string $notes = null): bool
    {
        if (!in_array($status, ['approved', 'rejected'], true)) {
            throw new \InvalidArgumentException("Review status must be 'approved' or 'rejected'.");
        }

        $db = Database::getConnection();
        $tenantId = TenantContext::getId();

        $stmt = $db->prepare(
            "UPDATE vol_expenses
             SET status = ?, reviewed_by = ?, review_notes = ?, reviewed_at = NOW()
             WHERE id = ? AND tenant_id = ? AND status = 'pending'"
        );
        $stmt->execute([$status, $reviewerId, $notes, $id, $tenantId]);

        $updated = $stmt->rowCount() > 0;

        // Webhook: expense.approved (only for approvals)
        if ($updated && $status === 'approved') {
            try {
                WebhookDispatchService::dispatch('expense.approved', [
                    'expense_id' => $id,
                    'reviewed_by' => $reviewerId,
                    'status' => $status,
                ]);
            } catch (\Throwable $e) {
                error_log("Webhook dispatch failed for expense.approved: " . $e->getMessage());
            }
        }

        return $updated;
    }

    /**
     * Mark an approved expense as paid
     *
     * @param int $id Expense ID
     * @param int $adminId Admin who processed payment
     * @param string|null $paymentReference Optional payment reference/transaction ID
     * @return bool
     */
    public static function markPaid(int $id, int $adminId, ?string $paymentReference = null): bool
    {
        $db = Database::getConnection();
        $tenantId = TenantContext::getId();

        $stmt = $db->prepare(
            "UPDATE vol_expenses
             SET status = 'paid', payment_reference = ?, paid_at = NOW()
             WHERE id = ? AND tenant_id = ? AND status = 'approved'"
        );
        $stmt->execute([$paymentReference, $id, $tenantId]);

        return $stmt->rowCount() > 0;
    }

    /**
     * Get aggregated expense report
     *
     * @param array $filters [
     *   'user_id' => ?int,
     *   'organization_id' => ?int,
     *   'status' => ?string,
     *   'date_from' => ?string (Y-m-d),
     *   'date_to' => ?string (Y-m-d),
     *   'group_by' => string (type|status|organization|month) -- default 'type',
     * ]
     * @return array ['breakdown' => [...], 'totals' => [...]]
     */
    public static function getExpenseReport(array $filters = []): array
    {
        $db = Database::getConnection();
        $tenantId = TenantContext::getId();

        $groupBy = $filters['group_by'] ?? 'type';
        $validGroups = [
            'type' => 'e.expense_type',
            'status' => 'e.status',
            'organization' => 'e.organization_id',
            'month' => "DATE_FORMAT(e.submitted_at, '%Y-%m')",
        ];

        $groupColumn = $validGroups[$groupBy] ?? $validGroups['type'];

        $sql = "
            SELECT {$groupColumn} as group_key,
                   COUNT(*) as count,
                   SUM(e.amount) as total_amount,
                   AVG(e.amount) as avg_amount,
                   MIN(e.amount) as min_amount,
                   MAX(e.amount) as max_amount
            FROM vol_expenses e
            WHERE e.tenant_id = ?
        ";
        $params = [$tenantId];

        if (!empty($filters['user_id'])) {
            $sql .= " AND e.user_id = ?";
            $params[] = (int) $filters['user_id'];
        }

        if (!empty($filters['organization_id'])) {
            $sql .= " AND e.organization_id = ?";
            $params[] = (int) $filters['organization_id'];
        }

        if (!empty($filters['status'])) {
            $sql .= " AND e.status = ?";
            $params[] = $filters['status'];
        }

        if (!empty($filters['date_from'])) {
            $sql .= " AND e.submitted_at >= ?";
            $params[] = $filters['date_from'];
        }

        if (!empty($filters['date_to'])) {
            $sql .= " AND e.submitted_at <= ?";
            $params[] = $filters['date_to'];
        }

        $sql .= " GROUP BY group_key ORDER BY total_amount DESC";

        $stmt = $db->prepare($sql);
        $stmt->execute($params);
        $breakdown = $stmt->fetchAll(\PDO::FETCH_ASSOC);

        // Overall totals — apply the SAME filters as the breakdown query
        $totalSql = "
            SELECT COUNT(*) as total_count,
                   COALESCE(SUM(amount), 0) as grand_total,
                   COALESCE(SUM(CASE WHEN status = 'pending' THEN amount ELSE 0 END), 0) as pending_total,
                   COALESCE(SUM(CASE WHEN status = 'approved' THEN amount ELSE 0 END), 0) as approved_total,
                   COALESCE(SUM(CASE WHEN status = 'paid' THEN amount ELSE 0 END), 0) as paid_total,
                   COALESCE(SUM(CASE WHEN status = 'rejected' THEN amount ELSE 0 END), 0) as rejected_total
            FROM vol_expenses
            WHERE tenant_id = ?
        ";
        $totalParams = [$tenantId];

        if (!empty($filters['user_id'])) {
            $totalSql .= " AND user_id = ?";
            $totalParams[] = (int) $filters['user_id'];
        }

        if (!empty($filters['organization_id'])) {
            $totalSql .= " AND organization_id = ?";
            $totalParams[] = (int) $filters['organization_id'];
        }

        if (!empty($filters['status'])) {
            $totalSql .= " AND status = ?";
            $totalParams[] = $filters['status'];
        }

        if (!empty($filters['date_from'])) {
            $totalSql .= " AND submitted_at >= ?";
            $totalParams[] = $filters['date_from'];
        }

        if (!empty($filters['date_to'])) {
            $totalSql .= " AND submitted_at <= ?";
            $totalParams[] = $filters['date_to'];
        }

        $stmt = $db->prepare($totalSql);
        $stmt->execute($totalParams);
        $totals = $stmt->fetch(\PDO::FETCH_ASSOC);

        return [
            'breakdown' => $breakdown,
            'totals' => $totals,
        ];
    }

    /**
     * Export expenses as a CSV string
     *
     * @param array $filters Same filters as getExpenses()
     * @return string CSV content
     */
    public static function exportExpenses(array $filters = []): string
    {
        $db = Database::getConnection();
        $tenantId = TenantContext::getId();

        $sql = "
            SELECT e.id, u.first_name, u.last_name, u.email,
                   org.name as organization_name,
                   e.expense_type, e.amount, e.currency, e.description,
                   e.submitted_at, e.status,
                   e.reviewed_by, e.review_notes, e.reviewed_at,
                   e.paid_at, e.payment_reference
            FROM vol_expenses e
            JOIN users u ON e.user_id = u.id
            JOIN vol_organizations org ON e.organization_id = org.id
            WHERE e.tenant_id = ?
        ";
        $params = [$tenantId];

        if (!empty($filters['user_id'])) {
            $sql .= " AND e.user_id = ?";
            $params[] = (int) $filters['user_id'];
        }

        if (!empty($filters['organization_id'])) {
            $sql .= " AND e.organization_id = ?";
            $params[] = (int) $filters['organization_id'];
        }

        if (!empty($filters['status'])) {
            $sql .= " AND e.status = ?";
            $params[] = $filters['status'];
        }

        if (!empty($filters['date_from'])) {
            $sql .= " AND e.submitted_at >= ?";
            $params[] = $filters['date_from'];
        }

        if (!empty($filters['date_to'])) {
            $sql .= " AND e.submitted_at <= ?";
            $params[] = $filters['date_to'];
        }

        $sql .= " ORDER BY e.submitted_at DESC, e.id DESC";

        $stmt = $db->prepare($sql);
        $stmt->execute($params);
        $rows = $stmt->fetchAll(\PDO::FETCH_ASSOC);

        $output = fopen('php://temp', 'r+');
        if (!$output) {
            return '';
        }

        if (!empty($rows)) {
            fputcsv($output, array_keys($rows[0]));
        } else {
            fputcsv($output, [
                'id', 'first_name', 'last_name', 'email', 'organization_name',
                'expense_type', 'amount', 'currency', 'description',
                'submitted_at', 'status', 'reviewed_by', 'review_notes',
                'reviewed_at', 'paid_at', 'payment_reference',
            ]);
        }

        foreach ($rows as $row) {
            fputcsv($output, $row);
        }

        rewind($output);
        $csv = stream_get_contents($output);
        fclose($output);

        return $csv;
    }

    /**
     * Get expense policies for a tenant, optionally filtered by organization
     *
     * @param int|null $organizationId Filter by specific organization (null = all policies)
     * @return array List of policies
     */
    public static function getPolicies(?int $organizationId = null): array
    {
        $db = Database::getConnection();
        $tenantId = TenantContext::getId();

        if ($organizationId !== null) {
            $stmt = $db->prepare(
                "SELECT * FROM vol_expense_policies
                 WHERE tenant_id = ? AND organization_id = ?
                 ORDER BY expense_type"
            );
            $stmt->execute([$tenantId, $organizationId]);
        } else {
            $stmt = $db->prepare(
                "SELECT * FROM vol_expense_policies
                 WHERE tenant_id = ?
                 ORDER BY organization_id, expense_type"
            );
            $stmt->execute([$tenantId]);
        }

        return $stmt->fetchAll(\PDO::FETCH_ASSOC);
    }

    /**
     * Create or update an expense policy (upsert)
     *
     * @param array $data [
     *   'organization_id' => ?int (null = tenant-wide default),
     *   'expense_type' => string (required),
     *   'max_amount' => ?float,
     *   'max_monthly' => ?float,
     *   'requires_receipt_above' => ?float,
     *   'requires_approval' => bool (default true),
     * ]
     * @return bool
     */
    public static function updatePolicy(array $data): bool
    {
        $db = Database::getConnection();
        $tenantId = TenantContext::getId();

        if (empty($data['expense_type'])) {
            throw new \InvalidArgumentException("expense_type is required.");
        }

        $organizationId = $data['organization_id'] ?? null;

        // Upsert: check if policy exists
        $stmt = $db->prepare(
            "SELECT id FROM vol_expense_policies
             WHERE tenant_id = ? AND expense_type = ?
             AND (organization_id = ? OR (organization_id IS NULL AND ? IS NULL))"
        );
        $stmt->execute([$tenantId, $data['expense_type'], $organizationId, $organizationId]);
        $existing = $stmt->fetchColumn();

        if ($existing) {
            $stmt = $db->prepare(
                "UPDATE vol_expense_policies
                 SET max_amount = ?, max_monthly = ?, requires_receipt_above = ?,
                     requires_approval = ?, updated_at = NOW()
                 WHERE id = ? AND tenant_id = ?"
            );
            $stmt->execute([
                $data['max_amount'] ?? null,
                $data['max_monthly'] ?? null,
                $data['requires_receipt_above'] ?? 0,
                ($data['requires_approval'] ?? true) ? 1 : 0,
                $existing,
                $tenantId,
            ]);
        } else {
            $stmt = $db->prepare(
                "INSERT INTO vol_expense_policies
                 (tenant_id, organization_id, expense_type, max_amount, max_monthly,
                  requires_receipt_above, requires_approval, created_at, updated_at)
                 VALUES (?, ?, ?, ?, ?, ?, ?, NOW(), NOW())"
            );
            $stmt->execute([
                $tenantId,
                $organizationId,
                $data['expense_type'],
                $data['max_amount'] ?? null,
                $data['max_monthly'] ?? null,
                $data['requires_receipt_above'] ?? 0,
                ($data['requires_approval'] ?? true) ? 1 : 0,
            ]);
        }

        return true;
    }

    // ========================================
    // PRIVATE HELPERS
    // ========================================

    /**
     * Get the most specific applicable policy for an expense type.
     * Org-level policy takes precedence over tenant-level.
     */
    private static function getApplicablePolicy(int $tenantId, int $organizationId, string $expenseType): ?array
    {
        $db = Database::getConnection();

        // Try org-specific policy first
        $stmt = $db->prepare(
            "SELECT * FROM vol_expense_policies
             WHERE tenant_id = ? AND organization_id = ? AND expense_type = ?"
        );
        $stmt->execute([$tenantId, $organizationId, $expenseType]);
        $policy = $stmt->fetch(\PDO::FETCH_ASSOC);

        if ($policy) {
            return $policy;
        }

        // Fall back to tenant-wide policy
        $stmt = $db->prepare(
            "SELECT * FROM vol_expense_policies
             WHERE tenant_id = ? AND organization_id IS NULL AND expense_type = ?"
        );
        $stmt->execute([$tenantId, $expenseType]);
        $policy = $stmt->fetch(\PDO::FETCH_ASSOC);

        return $policy ?: null;
    }
}
