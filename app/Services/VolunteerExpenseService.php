<?php
// Copyright © 2024–2026 Jasper Ford
// SPDX-License-Identifier: AGPL-3.0-or-later
// Author: Jasper Ford
// See NOTICE file for attribution and acknowledgements.

namespace App\Services;

use App\Core\TenantContext;
use App\Models\VolExpense;
use App\Models\VolExpensePolicy;

/**
 * VolunteerExpenseService — manages volunteer expense submissions, reviews, and policies.
 *
 * Handles expense submission with policy validation, approval/rejection workflow,
 * payment tracking, reporting and CSV export, and per-tenant/org expense policies.
 *
 * All queries are tenant-scoped automatically via the HasTenantScope trait on models.
 */
class VolunteerExpenseService
{
    public function __construct()
    {
    }

    /**
     * Submit a new expense claim.
     *
     * @param int $userId The volunteer submitting the expense
     * @param array $data Required: organization_id, expense_type, amount, description
     * @return array The created expense record
     * @throws \InvalidArgumentException On validation failure
     */
    public static function submitExpense(int $userId, array $data): array
    {
        $tenantId = TenantContext::getId();

        // Validate required fields
        $required = ['organization_id', 'expense_type', 'amount', 'description'];
        foreach ($required as $field) {
            if (!array_key_exists($field, $data) || (empty($data[$field]) && $data[$field] !== 0)) {
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

        // Validate against expense policy
        $policy = self::getApplicablePolicy($tenantId, (int) $data['organization_id'], $data['expense_type']);

        if ($policy) {
            if (!empty($policy->max_amount) && $amount > (float) $policy->max_amount) {
                throw new \InvalidArgumentException(
                    "Amount exceeds the maximum allowed per expense ({$policy->max_amount})."
                );
            }

            if (!empty($policy->max_monthly)) {
                $monthStart = now()->startOfMonth()->toDateString();
                $monthEnd = now()->endOfMonth()->toDateString();

                $monthlyTotal = (float) VolExpense::where('user_id', $userId)
                    ->where('organization_id', $data['organization_id'])
                    ->whereBetween('submitted_at', [$monthStart, $monthEnd])
                    ->where('status', '!=', 'rejected')
                    ->sum('amount');

                if (($monthlyTotal + $amount) > (float) $policy->max_monthly) {
                    throw new \InvalidArgumentException(
                        "This expense would exceed your monthly limit ({$policy->max_monthly}). Current month total: {$monthlyTotal}."
                    );
                }
            }

            if (!empty($policy->requires_receipt_above)
                && $amount > (float) $policy->requires_receipt_above
                && empty($data['receipt_path'])
            ) {
                throw new \InvalidArgumentException(
                    "A receipt is required for expenses above {$policy->requires_receipt_above}."
                );
            }
        }

        // Insert expense
        $expense = VolExpense::create([
            'tenant_id' => $tenantId,
            'user_id' => $userId,
            'organization_id' => (int) $data['organization_id'],
            'opportunity_id' => $data['opportunity_id'] ?? null,
            'shift_id' => $data['shift_id'] ?? null,
            'expense_type' => $data['expense_type'],
            'amount' => $amount,
            'currency' => $data['currency'] ?? 'EUR',
            'description' => $data['description'],
            'receipt_path' => $data['receipt_path'] ?? null,
            'receipt_filename' => $data['receipt_filename'] ?? null,
            'status' => 'pending',
            'submitted_at' => now(),
        ]);

        return self::getExpense($expense->id) ?? [];
    }

    /**
     * Get paginated list of expenses with filters.
     *
     * @param array $filters Keys: user_id, organization_id, status, date_from, date_to, cursor, limit
     * @return array ['items' => [], 'cursor' => string|null, 'has_more' => bool]
     */
    public static function getExpenses(array $filters = []): array
    {
        $limit = min($filters['limit'] ?? 20, 50);
        $cursorId = null;

        if (!empty($filters['cursor'])) {
            $decoded = base64_decode($filters['cursor'], true);
            if ($decoded && is_numeric($decoded)) {
                $cursorId = (int) $decoded;
            }
        }

        $query = VolExpense::with(['user', 'organization']);

        if (!empty($filters['user_id'])) {
            $query->where('user_id', (int) $filters['user_id']);
        }
        if (!empty($filters['organization_id'])) {
            $query->where('organization_id', (int) $filters['organization_id']);
        }
        if (!empty($filters['status'])) {
            $query->where('status', $filters['status']);
        }
        if (!empty($filters['date_from'])) {
            $query->where('submitted_at', '>=', $filters['date_from']);
        }
        if (!empty($filters['date_to'])) {
            $query->where('submitted_at', '<=', $filters['date_to']);
        }
        if ($cursorId) {
            $query->where('id', '<', $cursorId);
        }

        $rows = $query->orderByDesc('submitted_at')
            ->orderByDesc('id')
            ->limit($limit + 1)
            ->get();

        $hasMore = $rows->count() > $limit;
        if ($hasMore) {
            $rows->pop();
        }

        $lastId = $rows->last()?->id;

        $items = $rows->map(function ($e) {
            $arr = $e->toArray();
            $arr['first_name'] = $e->user->first_name ?? '';
            $arr['last_name'] = $e->user->last_name ?? '';
            $arr['email'] = $e->user->email ?? '';
            $arr['organization_name'] = $e->organization->name ?? '';
            return $arr;
        })->toArray();

        return [
            'items' => $items,
            'cursor' => $hasMore && $lastId ? base64_encode((string) $lastId) : null,
            'has_more' => $hasMore,
        ];
    }

    /**
     * Get a single expense by ID (tenant-scoped).
     */
    public static function getExpense(int $id): ?array
    {
        $expense = VolExpense::with(['user', 'organization'])->find($id);

        if (!$expense) {
            return null;
        }

        $arr = $expense->toArray();
        $arr['first_name'] = $expense->user->first_name ?? '';
        $arr['last_name'] = $expense->user->last_name ?? '';
        $arr['email'] = $expense->user->email ?? '';
        $arr['organization_name'] = $expense->organization->name ?? '';

        return $arr;
    }

    /**
     * Review (approve or reject) an expense.
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

        $affected = VolExpense::where('id', $id)
            ->where('status', 'pending')
            ->update([
                'status' => $status,
                'reviewed_by' => $reviewerId,
                'review_notes' => $notes,
                'reviewed_at' => now(),
            ]);

        return $affected > 0;
    }

    /**
     * Mark an approved expense as paid.
     *
     * @param int $id Expense ID
     * @param int $adminId Admin who processed payment
     * @param string|null $paymentReference Optional payment reference/transaction ID
     * @return bool
     */
    public static function markPaid(int $id, int $adminId, ?string $paymentReference = null): bool
    {
        $affected = VolExpense::where('id', $id)
            ->where('status', 'approved')
            ->update([
                'status' => 'paid',
                'payment_reference' => $paymentReference,
                'paid_at' => now(),
            ]);

        return $affected > 0;
    }

    /**
     * Export expenses as an array of rows for CSV generation.
     *
     * @param int $tenantId
     * @param array|null $filters Optional filters: user_id, organization_id, status, date_from, date_to
     * @return array Array of associative arrays (one per expense row)
     */
    public static function exportExpenses(int $tenantId, ?array $filters): array
    {
        $query = VolExpense::query()
            ->join('users as u', 'vol_expenses.user_id', '=', 'u.id')
            ->join('vol_organizations as org', 'vol_expenses.organization_id', '=', 'org.id')
            ->select([
                'vol_expenses.id',
                'u.first_name',
                'u.last_name',
                'u.email',
                'org.name as organization_name',
                'vol_expenses.expense_type',
                'vol_expenses.amount',
                'vol_expenses.currency',
                'vol_expenses.description',
                'vol_expenses.submitted_at',
                'vol_expenses.status',
                'vol_expenses.reviewed_by',
                'vol_expenses.review_notes',
                'vol_expenses.reviewed_at',
                'vol_expenses.paid_at',
                'vol_expenses.payment_reference',
            ]);

        if (!empty($filters['user_id'])) {
            $query->where('vol_expenses.user_id', (int) $filters['user_id']);
        }
        if (!empty($filters['organization_id'])) {
            $query->where('vol_expenses.organization_id', (int) $filters['organization_id']);
        }
        if (!empty($filters['status'])) {
            $query->where('vol_expenses.status', $filters['status']);
        }
        if (!empty($filters['date_from'])) {
            $query->where('vol_expenses.submitted_at', '>=', $filters['date_from']);
        }
        if (!empty($filters['date_to'])) {
            $query->where('vol_expenses.submitted_at', '<=', $filters['date_to']);
        }

        return $query->orderByDesc('vol_expenses.submitted_at')
            ->orderByDesc('vol_expenses.id')
            ->get()
            ->map(fn ($row) => (array) $row->getAttributes())
            ->toArray();
    }

    /**
     * Get expense policies for a tenant.
     *
     * @param int $tenantId
     * @return array List of policy records
     */
    public static function getPolicies(int $tenantId): array
    {
        return VolExpensePolicy::orderBy('organization_id')
            ->orderBy('expense_type')
            ->get()
            ->map(fn ($row) => $row->toArray())
            ->toArray();
    }

    /**
     * Update an expense policy by ID.
     *
     * @param int $policyId The policy ID to update
     * @param array $data Fields to update: max_amount, max_monthly, requires_receipt_above, requires_approval
     * @param int $tenantId
     * @return bool True if a row was updated
     */
    public static function updatePolicy(int $policyId, array $data, int $tenantId): bool
    {
        $affected = VolExpensePolicy::where('id', $policyId)
            ->update([
                'max_amount' => $data['max_amount'] ?? null,
                'max_monthly' => $data['max_monthly'] ?? null,
                'requires_receipt_above' => $data['requires_receipt_above'] ?? 0,
                'requires_approval' => ($data['requires_approval'] ?? true) ? 1 : 0,
                'updated_at' => now(),
            ]);

        return $affected > 0;
    }

    /**
     * Get the most specific applicable policy for an expense type.
     * Org-level policy takes precedence over tenant-level.
     */
    private static function getApplicablePolicy(int $tenantId, int $organizationId, string $expenseType): ?VolExpensePolicy
    {
        // Try org-specific policy first
        $policy = VolExpensePolicy::where('organization_id', $organizationId)
            ->where('expense_type', $expenseType)
            ->first();

        if ($policy) {
            return $policy;
        }

        // Fall back to tenant-wide policy
        return VolExpensePolicy::whereNull('organization_id')
            ->where('expense_type', $expenseType)
            ->first();
    }
}
