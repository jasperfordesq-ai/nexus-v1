<?php
// Copyright © 2024–2026 Jasper Ford
// SPDX-License-Identifier: AGPL-3.0-or-later
// Author: Jasper Ford
// See NOTICE file for attribution and acknowledgements.

namespace App\Services;

use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;

/**
 * VolunteerExpenseService — Laravel DI wrapper for legacy \Nexus\Services\VolunteerExpenseService.
 *
 * Provides dependency-injectable access to the legacy static service methods.
 */
class VolunteerExpenseService
{
    public function __construct()
    {
    }

    /**
     * Delegates to legacy VolunteerExpenseService::submitExpense().
     */
    public function submitExpense(int $userId, array $data): array
    {
        return \Nexus\Services\VolunteerExpenseService::submitExpense($userId, $data);
    }

    /**
     * Delegates to legacy VolunteerExpenseService::getExpenses().
     */
    public function getExpenses(array $filters = []): array
    {
        return \Nexus\Services\VolunteerExpenseService::getExpenses($filters);
    }

    /**
     * Delegates to legacy VolunteerExpenseService::getExpense().
     */
    public function getExpense(int $id): ?array
    {
        return \Nexus\Services\VolunteerExpenseService::getExpense($id);
    }

    /**
     * Delegates to legacy VolunteerExpenseService::reviewExpense().
     */
    public function reviewExpense(int $id, int $reviewerId, string $status, ?string $notes = null): bool
    {
        return \Nexus\Services\VolunteerExpenseService::reviewExpense($id, $reviewerId, $status, $notes);
    }

    /**
     * Delegates to legacy VolunteerExpenseService::markPaid().
     */
    public function markPaid(int $id, int $adminId, ?string $paymentReference = null): bool
    {
        return \Nexus\Services\VolunteerExpenseService::markPaid($id, $adminId, $paymentReference);
    }

    /**
     * Export expenses as an array of rows for CSV generation.
     *
     * @param int $tenantId
     * @param array|null $filters Optional filters: user_id, organization_id, status, date_from, date_to
     * @return array Array of associative arrays (one per expense row)
     */
    public function exportExpenses(int $tenantId, ?array $filters): array
    {
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

        $results = DB::select($sql, $params);

        return array_map(fn ($row) => (array) $row, $results);
    }

    /**
     * Get expense policies for a tenant.
     *
     * @param int $tenantId
     * @return array List of policy records
     */
    public function getPolicies(int $tenantId): array
    {
        $results = DB::select(
            "SELECT * FROM vol_expense_policies
             WHERE tenant_id = ?
             ORDER BY organization_id, expense_type",
            [$tenantId]
        );

        return array_map(fn ($row) => (array) $row, $results);
    }

    /**
     * Update an expense policy by ID (upsert-style via primary key).
     *
     * @param int $policyId The policy ID to update
     * @param array $data Fields to update: max_amount, max_monthly, requires_receipt_above, requires_approval
     * @param int $tenantId
     * @return bool True if a row was updated
     */
    public function updatePolicy(int $policyId, array $data, int $tenantId): bool
    {
        $affected = DB::update(
            "UPDATE vol_expense_policies
             SET max_amount = ?, max_monthly = ?, requires_receipt_above = ?,
                 requires_approval = ?, updated_at = NOW()
             WHERE id = ? AND tenant_id = ?",
            [
                $data['max_amount'] ?? null,
                $data['max_monthly'] ?? null,
                $data['requires_receipt_above'] ?? 0,
                ($data['requires_approval'] ?? true) ? 1 : 0,
                $policyId,
                $tenantId,
            ]
        );

        return $affected > 0;
    }
}
