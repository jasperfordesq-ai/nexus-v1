<?php
// Copyright © 2024–2026 Jasper Ford
// SPDX-License-Identifier: AGPL-3.0-or-later
// Author: Jasper Ford
// See NOTICE file for attribution and acknowledgements.

namespace App\Http\Controllers\Api;

use Illuminate\Http\JsonResponse;
use Illuminate\Http\Response;
use App\Http\Requests\Volunteering\SubmitExpenseRequest;
use App\Services\VolunteerExpenseService;
use App\Core\TenantContext;

/**
 * VolunteerExpenseController -- Expense submissions, reviews, policies, and exports.
 */
class VolunteerExpenseController extends BaseApiController
{
    protected bool $isV2Api = true;

    public function __construct(
        private readonly VolunteerExpenseService $volunteerExpenseService,
    ) {}

    private function ensureFeature(): void
    {
        if (!TenantContext::hasFeature('volunteering')) {
            throw new \Illuminate\Http\Exceptions\HttpResponseException(
                $this->respondWithError('FEATURE_DISABLED', 'Volunteering module is not enabled for this community', null, 403)
            );
        }
    }

    private function getErrorStatus(array $errors): int
    {
        foreach ($errors as $error) {
            $code = $error['code'] ?? '';
            if ($code === 'NOT_FOUND') return 404;
            if ($code === 'FORBIDDEN') return 403;
            if ($code === 'ALREADY_EXISTS') return 409;
            if ($code === 'FEATURE_DISABLED') return 403;
        }
        return 400;
    }

    public function myExpenses(): JsonResponse
    {
        $this->ensureFeature();
        $userId = $this->getUserId();
        $this->rateLimit('vol_expenses_list', 30, 60);

        $filters = [
            'user_id' => $userId,
            'status' => $this->query('status'),
            'date_from' => $this->query('date_from'),
            'date_to' => $this->query('date_to'),
            'cursor' => $this->query('cursor'),
            'limit' => $this->queryInt('per_page', 20, 1, 50),
        ];

        $result = $this->volunteerExpenseService->getExpenses($filters);
        return $this->respondWithData($result);
    }

    public function submitExpense(SubmitExpenseRequest $request): JsonResponse
    {
        $this->ensureFeature();
        $userId = $this->getUserId();
        $this->rateLimit('vol_expense_submit', 10, 3600);

        $data = $this->getAllInput();

        try {
            $result = $this->volunteerExpenseService->submitExpense($userId, $data);
        } catch (\InvalidArgumentException $e) {
            return $this->respondWithError('VALIDATION_ERROR', $e->getMessage(), null, 422);
        }

        if (isset($result['error'])) {
            return $this->respondWithError('VALIDATION_ERROR', $result['error'], null, 422);
        }

        return $this->respondWithData($result, null, 201);
    }

    public function getExpense($id): JsonResponse
    {
        $this->ensureFeature();
        $userId = $this->getUserId();
        $this->rateLimit('vol_expense_get', 30, 60);

        $expense = $this->volunteerExpenseService->getExpense((int) $id);
        if (!$expense || (int) $expense['user_id'] !== $userId) {
            return $this->respondWithError('NOT_FOUND', 'Expense not found', null, 404);
        }

        return $this->respondWithData($expense);
    }

    public function adminExpenses(): JsonResponse
    {
        $this->ensureFeature();
        $this->requireAdmin();
        $this->rateLimit('vol_admin_expenses', 30, 60);

        $filters = [
            'status' => $this->query('status'),
            'user_id' => $this->query('user_id') ? (int) $this->query('user_id') : null,
            'organization_id' => $this->query('organization_id') ? (int) $this->query('organization_id') : null,
            'date_from' => $this->query('date_from'),
            'date_to' => $this->query('date_to'),
            'cursor' => $this->query('cursor'),
            'limit' => $this->queryInt('per_page', 20, 1, 50),
        ];

        $result = $this->volunteerExpenseService->getExpenses($filters);
        return $this->respondWithData($result);
    }

    public function reviewExpense($id): JsonResponse
    {
        $this->ensureFeature();
        $adminId = $this->requireAdmin();
        $this->rateLimit('vol_expense_review', 30, 60);

        $data = $this->getAllInput();
        $status = $data['status'] ?? '';

        $allowedStatuses = ['approved', 'rejected', 'paid'];
        if (!in_array($status, $allowedStatuses, true)) {
            return $this->respondWithError('VALIDATION_ERROR', 'Invalid status. Must be one of: ' . implode(', ', $allowedStatuses), 'status', 422);
        }

        try {
            if ($status === 'paid') {
                $result = $this->volunteerExpenseService->markPaid((int) $id, $adminId, $data['payment_reference'] ?? null);
            } else {
                $result = $this->volunteerExpenseService->reviewExpense((int) $id, $adminId, $status, $data['review_notes'] ?? null);
            }
        } catch (\InvalidArgumentException $e) {
            return $this->respondWithError('FORBIDDEN', $e->getMessage(), null, 403);
        }

        if (!$result) {
            return $this->respondWithError('NOT_FOUND', 'Expense not found or invalid status', null, 404);
        }

        return $this->respondWithData(['success' => true]);
    }

    /** Returns raw CSV for expense export */
    public function exportExpenses(): Response
    {
        $this->ensureFeature();
        $this->requireAdmin();

        $filters = [
            'status' => $this->query('status'),
            'date_from' => $this->query('date_from'),
            'date_to' => $this->query('date_to'),
        ];

        $rows = $this->volunteerExpenseService->exportExpenses(TenantContext::getId(), $filters);
        $csv = '';
        if (!empty($rows)) {
            $csv .= implode(',', array_keys((array) $rows[0])) . "\n";
            foreach ($rows as $row) {
                $csv .= implode(',', array_map(fn($v) => '"' . str_replace('"', '""', (string)$v) . '"', array_values((array) $row))) . "\n";
            }
        }

        return response($csv, 200)
            ->header('Content-Type', 'text/csv')
            ->header('Content-Disposition', 'attachment; filename="volunteer_expenses_' . date('Y-m-d') . '.csv"');
    }

    public function getExpensePolicies(): JsonResponse
    {
        $this->ensureFeature();
        $this->requireAdmin();

        $policies = $this->volunteerExpenseService->getPolicies(TenantContext::getId());
        return $this->respondWithData($policies);
    }

    public function updateExpensePolicy(): JsonResponse
    {
        $this->ensureFeature();
        $this->requireAdmin();
        $this->rateLimit('vol_expense_policy_update', 10, 60);

        $data = $this->getAllInput();

        if (empty($data['expense_type'])) {
            return $this->respondWithError('VALIDATION_ERROR', 'expense_type is required', 'expense_type', 422);
        }

        $policyFields = ['max_amount', 'requires_receipt', 'auto_approve_below', 'description', 'enabled'];
        $hasPolicyField = false;
        foreach ($policyFields as $field) {
            if (array_key_exists($field, $data)) {
                $hasPolicyField = true;
                break;
            }
        }
        if (!$hasPolicyField) {
            return $this->respondWithError('VALIDATION_ERROR', 'At least one policy field is required (e.g., max_amount, requires_receipt, auto_approve_below, description, enabled)', null, 422);
        }

        $result = $this->volunteerExpenseService->updatePolicy((int)($data['id'] ?? 0), $data, TenantContext::getId());
        return $this->respondWithData(['success' => $result]);
    }
}
