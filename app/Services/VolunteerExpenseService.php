<?php
// Copyright © 2024–2026 Jasper Ford
// SPDX-License-Identifier: AGPL-3.0-or-later
// Author: Jasper Ford
// See NOTICE file for attribution and acknowledgements.

namespace App\Services;

use App\Core\EmailTemplateBuilder;
use App\Core\Mailer;
use App\Core\TenantContext;
use App\I18n\LocaleContext;
use App\Models\VolExpense;
use App\Models\VolExpensePolicy;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;

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

        $organizationId = (int) $data['organization_id'];
        $opportunityId = !empty($data['opportunity_id']) ? (int) $data['opportunity_id'] : null;

        $org = DB::table('vol_organizations')
            ->where('id', $organizationId)
            ->where('tenant_id', $tenantId)
            ->whereIn('status', ['approved', 'active'])
            ->first(['id']);

        if (!$org) {
            throw new \RuntimeException(__('api.organization_not_found'), 404);
        }

        if ($opportunityId !== null) {
            $opportunity = DB::table('vol_opportunities')
                ->where('id', $opportunityId)
                ->where('organization_id', $organizationId)
                ->where('tenant_id', $tenantId)
                ->first(['id']);

            if (!$opportunity) {
                throw new \RuntimeException(__('api.opportunity_not_found'), 404);
            }

            $hasApprovedApplication = DB::table('vol_applications')
                ->where('opportunity_id', $opportunityId)
                ->where('user_id', $userId)
                ->where('tenant_id', $tenantId)
                ->where('status', 'approved')
                ->exists();

            if (!$hasApprovedApplication) {
                throw new \RuntimeException(__('api.volunteer_approved_application_required'), 403);
            }
        } elseif (!self::userCanClaimAgainstOrganization($tenantId, $userId, $organizationId)) {
            throw new \RuntimeException(__('api.volunteer_org_relationship_required'), 403);
        }

        if (!empty($data['shift_id'])) {
            $shiftQuery = DB::table('vol_shifts')
                ->where('id', (int) $data['shift_id'])
                ->where('tenant_id', $tenantId);

            if ($opportunityId !== null) {
                $shiftQuery->where('opportunity_id', $opportunityId);
            } else {
                $shiftQuery->whereIn('opportunity_id', function ($query) use ($organizationId, $tenantId) {
                    $query->select('id')
                        ->from('vol_opportunities')
                        ->where('organization_id', $organizationId)
                        ->where('tenant_id', $tenantId);
                });
            }

            if (!$shiftQuery->exists()) {
                throw new \RuntimeException(__('api.volunteer_shift_not_found'), 404);
            }
        }

        // Validate against expense policy
        $policy = self::getApplicablePolicy($tenantId, $organizationId, $data['expense_type']);

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
                    ->where('tenant_id', $tenantId)
                    ->where('organization_id', $organizationId)
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
            'organization_id' => $organizationId,
            'opportunity_id' => $opportunityId,
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

        $query = VolExpense::with(['user:id,first_name,last_name,avatar_url', 'organization']);

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
            $arr['volunteer_name'] = trim(($arr['first_name'] ?? '') . ' ' . ($arr['last_name'] ?? ''));
            $arr['type'] = $arr['expense_type'] ?? '';
            $arr['has_receipt'] = !empty($arr['receipt_path']);
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
        $expense = VolExpense::with(['user:id,first_name,last_name,avatar_url', 'organization'])
            ->where('tenant_id', TenantContext::getId())
            ->find($id);

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

        // Prevent admins from approving their own expenses
        $expense = VolExpense::where('tenant_id', TenantContext::getId())->find($id);
        if ($expense && (int) $expense->user_id === $reviewerId) {
            throw new \InvalidArgumentException("You cannot review your own expense.");
        }

        $affected = VolExpense::where('id', $id)
            ->where('tenant_id', TenantContext::getId())
            ->where('status', 'pending')
            ->update([
                'status' => $status,
                'reviewed_by' => $reviewerId,
                'review_notes' => $notes,
                'reviewed_at' => now(),
            ]);

        if ($affected > 0) {
            try {
                $expense = VolExpense::where('tenant_id', TenantContext::getId())->find($id);
                if ($expense) {
                    $isApproved = $status === 'approved';
                    $subjectKey = $isApproved ? 'emails_misc.expense.approved_subject' : 'emails_misc.expense.rejected_subject';
                    $titleKey   = $isApproved ? 'emails_misc.expense.approved_title' : 'emails_misc.expense.rejected_title';
                    $bodyKey    = $isApproved ? 'emails_misc.expense.approved_body' : 'emails_misc.expense.rejected_body';
                    $params     = [
                        'amount'   => number_format((float) $expense->amount, 2),
                        'currency' => $expense->currency ?? 'EUR',
                        'type'     => $expense->expense_type,
                    ];

                    $link    = '/volunteering/expenses/' . $id;
                    $fullUrl = TenantContext::getFrontendUrl() . TenantContext::getSlugPrefix() . $link;
                    $user = DB::table('users')->where('id', $expense->user_id)->where('tenant_id', TenantContext::getId())->select(['email', 'first_name', 'name', 'preferred_language'])->first();
                    if ($user && !empty($user->email)) {
                        LocaleContext::withLocale($user, function () use ($user, $expense, $isApproved, $subjectKey, $titleKey, $bodyKey, $params, $fullUrl, $notes) {
                            $firstName = $user->first_name ?? $user->name ?? __('emails.common.fallback_name');
                            $builder = EmailTemplateBuilder::make()
                                ->title(__($titleKey))
                                ->greeting($firstName)
                                ->paragraph(__($bodyKey, $params));
                            if (!$isApproved && !empty($notes)) {
                                $builder->paragraph('<strong>' . __('emails_misc.expense.rejected_notes_label') . ':</strong> ' . htmlspecialchars($notes, ENT_QUOTES, 'UTF-8'));
                            }
                            $renderedHtml = $builder->button(__('emails_misc.expense.' . ($isApproved ? 'approved' : 'rejected') . '_cta'), $fullUrl)->render();
                            if (!Mailer::forCurrentTenant()->send($user->email, __($subjectKey, $params), $renderedHtml)) {
                                Log::warning('[VolunteerExpenseService] reviewExpense email failed', ['user_id' => $expense->user_id]);
                            }
                        });
                    }
                }
            } catch (\Throwable $e) {
                Log::warning('[VolunteerExpenseService] reviewExpense email error: ' . $e->getMessage());
            }
        }

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
            ->where('tenant_id', TenantContext::getId())
            ->where('status', 'approved')
            ->update([
                'status' => 'paid',
                'payment_reference' => $paymentReference,
                'paid_at' => now(),
            ]);

        if ($affected > 0) {
            try {
                $expense = VolExpense::where('tenant_id', TenantContext::getId())->find($id);
                if ($expense) {
                    $tenantId  = TenantContext::getId();
                    $user      = DB::table('users')->where('id', $expense->user_id)->where('tenant_id', $tenantId)->select(['email', 'first_name', 'name', 'preferred_language'])->first();
                    if ($user && !empty($user->email)) {
                        LocaleContext::withLocale($user, function () use ($user, $expense, $id, $paymentReference) {
                            $firstName = $user->first_name ?? $user->name ?? __('emails.common.fallback_name');
                            $params    = [
                                'amount'   => number_format((float) $expense->amount, 2),
                                'currency' => $expense->currency ?? 'EUR',
                                'type'     => $expense->expense_type,
                            ];
                            $link    = '/volunteering/expenses/' . $id;
                            $fullUrl = TenantContext::getFrontendUrl() . TenantContext::getSlugPrefix() . $link;

                            $builder = EmailTemplateBuilder::make()
                                ->title(__('emails_misc.expense.paid_title'))
                                ->greeting($firstName)
                                ->paragraph(__('emails_misc.expense.paid_body', $params));

                            if (!empty($paymentReference)) {
                                $builder->paragraph('<strong>' . __('emails_misc.expense.paid_ref_label') . ':</strong> ' . htmlspecialchars($paymentReference, ENT_QUOTES, 'UTF-8'));
                            }

                            $html = $builder->button(__('emails_misc.expense.paid_cta'), $fullUrl)->render();

                            if (!Mailer::forCurrentTenant()->send($user->email, __('emails_misc.expense.paid_subject', $params), $html)) {
                                Log::warning('[VolunteerExpenseService] markPaid email failed', ['user_id' => $expense->user_id]);
                            }
                        });
                    }
                }
            } catch (\Throwable $e) {
                Log::warning('[VolunteerExpenseService] markPaid email error: ' . $e->getMessage());
            }
        }

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
            ->where('vol_expenses.tenant_id', $tenantId)
            ->join('users as u', function ($join) {
                $join->on('vol_expenses.user_id', '=', 'u.id')
                    ->on('vol_expenses.tenant_id', '=', 'u.tenant_id');
            })
            ->leftJoin('vol_organizations as org', function ($join) {
                $join->on('vol_expenses.organization_id', '=', 'org.id')
                    ->on('vol_expenses.tenant_id', '=', 'org.tenant_id');
            })
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
        return VolExpensePolicy::where('tenant_id', $tenantId)
            ->orderBy('organization_id')
            ->orderBy('expense_type')
            ->get()
            ->map(function ($row) {
                $data = $row->toArray();
                $data['type'] = $data['expense_type'] ?? '';
                return $data;
            })
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
        if ($policyId <= 0 && !empty($data['expense_type'])) {
            $query = VolExpensePolicy::where('tenant_id', $tenantId)
                ->where('expense_type', $data['expense_type']);

            if (!empty($data['organization_id'])) {
                $query->where('organization_id', (int) $data['organization_id']);
            } else {
                $query->whereNull('organization_id');
            }

            $policyId = (int) ($query->value('id') ?? 0);
        }

        if ($policyId <= 0) {
            return false;
        }

        $affected = VolExpensePolicy::where('id', $policyId)
            ->where('tenant_id', $tenantId)
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
            ->where('tenant_id', $tenantId)
            ->where('expense_type', $expenseType)
            ->first();

        if ($policy) {
            return $policy;
        }

        // Fall back to tenant-wide policy
        return VolExpensePolicy::whereNull('organization_id')
            ->where('tenant_id', $tenantId)
            ->where('expense_type', $expenseType)
            ->first();
    }

    private static function userCanClaimAgainstOrganization(int $tenantId, int $userId, int $organizationId): bool
    {
        $hasApprovedApplication = DB::table('vol_applications as a')
            ->join('vol_opportunities as opp', function ($join) {
                $join->on('opp.id', '=', 'a.opportunity_id')
                    ->whereColumn('opp.tenant_id', 'a.tenant_id');
            })
            ->where('a.user_id', $userId)
            ->where('a.tenant_id', $tenantId)
            ->where('a.status', 'approved')
            ->where('opp.organization_id', $organizationId)
            ->exists();

        if ($hasApprovedApplication) {
            return true;
        }

        return DB::table('vol_organizations as org')
            ->leftJoin('org_members as om', function ($join) use ($userId) {
                $join->on('om.organization_id', '=', 'org.id')
                    ->whereColumn('om.tenant_id', 'org.tenant_id')
                    ->where('om.user_id', $userId)
                    ->where('om.status', 'active');
            })
            ->where('org.id', $organizationId)
            ->where('org.tenant_id', $tenantId)
            ->where(function ($query) use ($userId) {
                $query->where('org.user_id', $userId)
                    ->orWhereNotNull('om.user_id');
            })
            ->exists();
    }
}
