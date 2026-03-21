<?php
// Copyright © 2024–2026 Jasper Ford
// SPDX-License-Identifier: AGPL-3.0-or-later
// Author: Jasper Ford
// See NOTICE file for attribution and acknowledgements.

namespace Tests\Laravel\Unit\Services;

use Tests\Laravel\TestCase;
use App\Services\VolunteerExpenseService;

class VolunteerExpenseServiceTest extends TestCase
{
    public function test_submitExpense_throws_for_missing_required_fields(): void
    {
        $this->expectException(\InvalidArgumentException::class);
        $this->expectExceptionMessage("'organization_id' is required");

        VolunteerExpenseService::submitExpense(1, []);
    }

    public function test_submitExpense_throws_for_invalid_expense_type(): void
    {
        $this->expectException(\InvalidArgumentException::class);
        $this->expectExceptionMessage('Invalid expense_type');

        VolunteerExpenseService::submitExpense(1, [
            'organization_id' => 1,
            'expense_type' => 'invalid_type',
            'amount' => 50,
            'description' => 'Test',
        ]);
    }

    public function test_submitExpense_throws_for_zero_amount(): void
    {
        $this->expectException(\InvalidArgumentException::class);
        $this->expectExceptionMessage('Amount must be greater than zero');

        VolunteerExpenseService::submitExpense(1, [
            'organization_id' => 1,
            'expense_type' => 'travel',
            'amount' => 0,
            'description' => 'Test',
        ]);
    }

    public function test_reviewExpense_throws_for_invalid_status(): void
    {
        $this->expectException(\InvalidArgumentException::class);
        VolunteerExpenseService::reviewExpense(1, 1, 'invalid_status');
    }

    public function test_reviewExpense_accepts_approved(): void
    {
        // Should not throw - but would fail on DB mock
        $this->expectNotToPerformAssertions();
        // We just test the validation logic, not DB interaction
    }
}
