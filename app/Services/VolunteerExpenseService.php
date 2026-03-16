<?php
// Copyright © 2024–2026 Jasper Ford
// SPDX-License-Identifier: AGPL-3.0-or-later
// Author: Jasper Ford
// See NOTICE file for attribution and acknowledgements.

namespace App\Services;

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
}
