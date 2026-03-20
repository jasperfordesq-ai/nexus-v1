<?php
// Copyright © 2024–2026 Jasper Ford
// SPDX-License-Identifier: AGPL-3.0-or-later
// Author: Jasper Ford
// See NOTICE file for attribution and acknowledgements.

declare(strict_types=1);

namespace Nexus\Tests\Services;

use Nexus\Tests\DatabaseTestCase;
use Nexus\Core\Database;
use App\Core\TenantContext;
use App\Services\VolunteerExpenseService;

/**
 * VolunteerExpenseService Tests
 *
 * Tests expense submission, review workflow, payment tracking,
 * reporting, CSV export, policies, and tenant scoping.
 */
class VolunteerExpenseServiceTest extends DatabaseTestCase
{
    protected static ?int $testTenantId = null;
    protected static ?int $testUserId = null;
    protected static ?int $testUser2Id = null;
    protected static ?int $testOrgId = null;
    protected static ?int $testOppId = null;
    protected static ?int $testExpenseId = null;

    public static function setUpBeforeClass(): void
    {
        parent::setUpBeforeClass();

        self::$testTenantId = 2;
        TenantContext::setById(self::$testTenantId);

        self::createTestData();
    }

    protected static function createTestData(): void
    {
        $ts = time();

        // Create test users
        Database::query(
            "INSERT INTO users (tenant_id, email, username, first_name, last_name, name, balance, is_approved, created_at)
             VALUES (?, ?, ?, ?, ?, ?, ?, 1, NOW())",
            [self::$testTenantId, "volexp_user1_{$ts}@test.com", "volexp_user1_{$ts}", 'Exp', 'One', 'Exp One', 100]
        );
        self::$testUserId = (int)Database::getInstance()->lastInsertId();

        Database::query(
            "INSERT INTO users (tenant_id, email, username, first_name, last_name, name, balance, is_approved, created_at)
             VALUES (?, ?, ?, ?, ?, ?, ?, 1, NOW())",
            [self::$testTenantId, "volexp_user2_{$ts}@test.com", "volexp_user2_{$ts}", 'Exp', 'Two', 'Exp Two', 50]
        );
        self::$testUser2Id = (int)Database::getInstance()->lastInsertId();

        // Create test organization
        Database::query(
            "INSERT INTO vol_organizations (tenant_id, user_id, name, description, status, created_at)
             VALUES (?, ?, ?, ?, 'approved', NOW())",
            [self::$testTenantId, self::$testUserId, "Test Expense Org {$ts}", 'Test organization for expense tests']
        );
        self::$testOrgId = (int)Database::getInstance()->lastInsertId();

        // Create test opportunity
        Database::query(
            "INSERT INTO vol_opportunities (tenant_id, organization_id, created_by, title, description, location, is_active, status, created_at)
             VALUES (?, ?, ?, ?, ?, ?, 1, 'open', NOW())",
            [self::$testTenantId, self::$testOrgId, self::$testUserId, "Test Expense Opp {$ts}", 'Opportunity for expense tests', 'Test Location']
        );
        self::$testOppId = (int)Database::getInstance()->lastInsertId();
    }

    public static function tearDownAfterClass(): void
    {
        // Clean up expenses and policies first (foreign key order)
        if (self::$testOrgId) {
            try {
                Database::query("DELETE FROM vol_expense_policies WHERE tenant_id = ? AND organization_id = ?", [self::$testTenantId, self::$testOrgId]);
            } catch (\Exception $e) {}
            try {
                Database::query("DELETE FROM vol_expenses WHERE tenant_id = ? AND organization_id = ?", [self::$testTenantId, self::$testOrgId]);
            } catch (\Exception $e) {}
        }

        // Clean up tenant-wide policies created by tests
        try {
            Database::query("DELETE FROM vol_expense_policies WHERE tenant_id = ? AND organization_id IS NULL AND expense_type = 'travel'", [self::$testTenantId]);
        } catch (\Exception $e) {}

        if (self::$testOppId) {
            try {
                Database::query("DELETE FROM vol_opportunities WHERE id = ?", [self::$testOppId]);
            } catch (\Exception $e) {}
        }
        if (self::$testOrgId) {
            try {
                Database::query("DELETE FROM vol_organizations WHERE id = ?", [self::$testOrgId]);
            } catch (\Exception $e) {}
        }
        if (self::$testUserId) {
            try {
                Database::query("DELETE FROM users WHERE id = ?", [self::$testUserId]);
            } catch (\Exception $e) {}
        }
        if (self::$testUser2Id) {
            try {
                Database::query("DELETE FROM users WHERE id = ?", [self::$testUser2Id]);
            } catch (\Exception $e) {}
        }

        parent::tearDownAfterClass();
    }

    // ==========================================
    // Class & Method Existence Tests
    // ==========================================

    public function testClassExists(): void
    {
        $this->assertTrue(class_exists(VolunteerExpenseService::class));
    }

    public function testMethodsExistAndAreStatic(): void
    {
        $methods = [
            'submitExpense',
            'getExpenses',
            'getExpense',
            'reviewExpense',
            'markPaid',
            'getExpenseReport',
            'exportExpenses',
            'getPolicies',
            'updatePolicy',
        ];

        $reflection = new \ReflectionClass(VolunteerExpenseService::class);
        foreach ($methods as $method) {
            $this->assertTrue($reflection->hasMethod($method), "Method {$method} should exist");
            $this->assertTrue($reflection->getMethod($method)->isStatic(), "Method {$method} should be static");
        }
    }

    // ==========================================
    // Submit Expense Tests
    // ==========================================

    public function testSubmitExpenseWithValidDataReturnsArray(): void
    {
        // Clean up any stale policies from prior test runs
        try {
            Database::query("DELETE FROM vol_expense_policies WHERE tenant_id = ? AND organization_id = ?", [self::$testTenantId, self::$testOrgId]);
        } catch (\Exception $e) {}

        $result = VolunteerExpenseService::submitExpense(self::$testUserId, [
            'organization_id' => self::$testOrgId,
            'expense_type' => 'travel',
            'amount' => 10.00,
            'currency' => 'EUR',
            'description' => 'Bus fare to volunteer site',
        ]);

        $this->assertIsArray($result);
        $this->assertArrayHasKey('id', $result);
        $this->assertArrayHasKey('expense_type', $result);
        $this->assertArrayHasKey('amount', $result);
        $this->assertArrayHasKey('status', $result);
        $this->assertEquals('pending', $result['status']);
        $this->assertEquals('travel', $result['expense_type']);

        // Store for later tests
        self::$testExpenseId = (int)$result['id'];
    }

    public function testSubmitExpenseWithAmountZeroThrowsException(): void
    {
        $this->expectException(\InvalidArgumentException::class);

        VolunteerExpenseService::submitExpense(self::$testUserId, [
            'organization_id' => self::$testOrgId,
            'expense_type' => 'meals',
            'amount' => 0,
            'description' => 'Free lunch',
            'expense_date' => date('Y-m-d'),
        ]);
    }

    public function testSubmitExpenseWithNegativeAmountThrowsException(): void
    {
        $this->expectException(\InvalidArgumentException::class);

        VolunteerExpenseService::submitExpense(self::$testUserId, [
            'organization_id' => self::$testOrgId,
            'expense_type' => 'supplies',
            'amount' => -10,
            'description' => 'Negative expense',
            'expense_date' => date('Y-m-d'),
        ]);
    }

    public function testSubmitExpenseWithInvalidTypeThrowsException(): void
    {
        $this->expectException(\InvalidArgumentException::class);

        VolunteerExpenseService::submitExpense(self::$testUserId, [
            'organization_id' => self::$testOrgId,
            'expense_type' => 'invalid_type',
            'amount' => 10,
            'description' => 'Bad type',
            'expense_date' => date('Y-m-d'),
        ]);
    }

    public function testSubmitExpenseMissingRequiredFieldThrowsException(): void
    {
        $this->expectException(\InvalidArgumentException::class);

        // Missing description
        VolunteerExpenseService::submitExpense(self::$testUserId, [
            'organization_id' => self::$testOrgId,
            'expense_type' => 'travel',
            'amount' => 15,
            'expense_date' => date('Y-m-d'),
        ]);
    }

    public function testSubmitExpenseMissingOrganizationThrowsException(): void
    {
        $this->expectException(\InvalidArgumentException::class);

        VolunteerExpenseService::submitExpense(self::$testUserId, [
            'expense_type' => 'travel',
            'amount' => 15,
            'description' => 'No org',
            'expense_date' => date('Y-m-d'),
        ]);
    }

    // ==========================================
    // Get Expenses Tests
    // ==========================================

    public function testGetExpensesReturnsPaginatedStructure(): void
    {
        $result = VolunteerExpenseService::getExpenses();

        $this->assertIsArray($result);
        $this->assertArrayHasKey('items', $result);
        $this->assertArrayHasKey('cursor', $result);
        $this->assertArrayHasKey('has_more', $result);
        $this->assertIsArray($result['items']);
        $this->assertIsBool($result['has_more']);
    }

    public function testGetExpensesFiltersByUserId(): void
    {
        $result = VolunteerExpenseService::getExpenses(['user_id' => self::$testUserId]);

        $this->assertIsArray($result);
        $this->assertArrayHasKey('items', $result);
        foreach ($result['items'] as $item) {
            $this->assertEquals(self::$testUserId, (int)$item['user_id']);
        }
    }

    public function testGetExpensesFiltersByOrganizationId(): void
    {
        $result = VolunteerExpenseService::getExpenses(['organization_id' => self::$testOrgId]);

        $this->assertIsArray($result);
        foreach ($result['items'] as $item) {
            $this->assertEquals(self::$testOrgId, (int)$item['organization_id']);
        }
    }

    // ==========================================
    // Get Single Expense Tests
    // ==========================================

    public function testGetExpenseByIdReturnsData(): void
    {
        $this->assertNotNull(self::$testExpenseId, 'Test expense must exist from previous test');

        $expense = VolunteerExpenseService::getExpense(self::$testExpenseId);

        $this->assertNotNull($expense);
        $this->assertIsArray($expense);
        $this->assertEquals(self::$testExpenseId, (int)$expense['id']);
        $this->assertArrayHasKey('organization_name', $expense);
        $this->assertArrayHasKey('first_name', $expense);
    }

    public function testGetExpenseByInvalidIdReturnsNull(): void
    {
        $expense = VolunteerExpenseService::getExpense(999999);
        $this->assertNull($expense);
    }

    // ==========================================
    // Review Expense Tests
    // ==========================================

    public function testReviewExpenseApprove(): void
    {
        // Create a fresh expense to approve
        $expense = VolunteerExpenseService::submitExpense(self::$testUserId, [
            'organization_id' => self::$testOrgId,
            'expense_type' => 'meals',
            'amount' => 12.00,
            'description' => 'Lunch during volunteer shift',
            'expense_date' => date('Y-m-d'),
        ]);

        $result = VolunteerExpenseService::reviewExpense(
            (int)$expense['id'],
            self::$testUser2Id,
            'approved',
            'Looks good'
        );

        $this->assertTrue($result);

        // Verify status changed
        $updated = VolunteerExpenseService::getExpense((int)$expense['id']);
        $this->assertEquals('approved', $updated['status']);
    }

    public function testReviewExpenseReject(): void
    {
        $expense = VolunteerExpenseService::submitExpense(self::$testUserId, [
            'organization_id' => self::$testOrgId,
            'expense_type' => 'equipment',
            'amount' => 500.00,
            'description' => 'Expensive equipment purchase',
            'expense_date' => date('Y-m-d'),
        ]);

        $result = VolunteerExpenseService::reviewExpense(
            (int)$expense['id'],
            self::$testUser2Id,
            'rejected',
            'Amount too high without pre-approval'
        );

        $this->assertTrue($result);

        $updated = VolunteerExpenseService::getExpense((int)$expense['id']);
        $this->assertEquals('rejected', $updated['status']);
    }

    public function testReviewExpenseWithInvalidStatusThrowsException(): void
    {
        $this->expectException(\InvalidArgumentException::class);

        VolunteerExpenseService::reviewExpense(
            self::$testExpenseId ?? 1,
            self::$testUser2Id,
            'invalid_status'
        );
    }

    // ==========================================
    // Mark Paid Tests
    // ==========================================

    public function testMarkPaidOnApprovedExpense(): void
    {
        // Clean any stale policies that could trigger receipt requirement
        try {
            Database::query("DELETE FROM vol_expense_policies WHERE tenant_id = ? AND organization_id = ?", [self::$testTenantId, self::$testOrgId]);
        } catch (\Exception $e) {}

        // Create and approve an expense
        $expense = VolunteerExpenseService::submitExpense(self::$testUserId, [
            'organization_id' => self::$testOrgId,
            'expense_type' => 'supplies',
            'amount' => 30.00,
            'description' => 'Cleaning supplies for event',
        ]);

        VolunteerExpenseService::reviewExpense(
            (int)$expense['id'],
            self::$testUser2Id,
            'approved'
        );

        $result = VolunteerExpenseService::markPaid(
            (int)$expense['id'],
            self::$testUser2Id,
            'PAY-REF-12345'
        );

        $this->assertTrue($result);

        $updated = VolunteerExpenseService::getExpense((int)$expense['id']);
        $this->assertEquals('paid', $updated['status']);
        $this->assertEquals('PAY-REF-12345', $updated['payment_reference']);
    }

    public function testMarkPaidOnPendingExpenseReturnsFalse(): void
    {
        // Create a pending expense (not approved)
        $expense = VolunteerExpenseService::submitExpense(self::$testUserId, [
            'organization_id' => self::$testOrgId,
            'expense_type' => 'other',
            'amount' => 5.00,
            'description' => 'Miscellaneous',
            'expense_date' => date('Y-m-d'),
        ]);

        // Try to mark paid without approval — should fail
        $result = VolunteerExpenseService::markPaid(
            (int)$expense['id'],
            self::$testUser2Id
        );

        $this->assertFalse($result);
    }

    // ==========================================
    // Export Tests
    // ==========================================

    public function testExportExpensesReturnsCsvWithHeader(): void
    {
        $csv = VolunteerExpenseService::exportExpenses();

        $this->assertIsString($csv);
        $this->assertNotEmpty($csv);

        // First line should be the CSV header
        $lines = explode("\n", trim($csv));
        $this->assertGreaterThanOrEqual(1, count($lines));

        $header = $lines[0];
        $this->assertStringContainsString('id', $header);
        $this->assertStringContainsString('expense_type', $header);
        $this->assertStringContainsString('amount', $header);
        $this->assertStringContainsString('status', $header);
    }

    // ==========================================
    // Report Tests
    // ==========================================

    public function testGetExpenseReportReturnsValidStructure(): void
    {
        $report = VolunteerExpenseService::getExpenseReport();

        $this->assertIsArray($report);
        $this->assertArrayHasKey('breakdown', $report);
        $this->assertArrayHasKey('totals', $report);
        $this->assertIsArray($report['breakdown']);
        $this->assertIsArray($report['totals']);
        $this->assertArrayHasKey('total_count', $report['totals']);
        $this->assertArrayHasKey('grand_total', $report['totals']);
    }

    // ==========================================
    // Policy Tests
    // ==========================================

    public function testUpdatePolicyCreatesNewPolicy(): void
    {
        $result = VolunteerExpenseService::updatePolicy([
            'organization_id' => self::$testOrgId,
            'expense_type' => 'travel',
            'max_amount' => 100.00,
            'max_monthly' => 500.00,
            'requires_receipt_above' => 25.00,
            'is_enabled' => true,
        ]);

        $this->assertTrue($result);

        // Verify policy was created
        $policies = VolunteerExpenseService::getPolicies(self::$testOrgId);
        $this->assertIsArray($policies);
        $this->assertNotEmpty($policies);

        $travelPolicy = null;
        foreach ($policies as $p) {
            if ($p['expense_type'] === 'travel') {
                $travelPolicy = $p;
                break;
            }
        }
        $this->assertNotNull($travelPolicy);
        $this->assertEquals(100.00, (float)$travelPolicy['max_amount']);
    }

    public function testUpdatePolicyUpsertsExistingPolicy(): void
    {
        // Update the policy we just created
        $result = VolunteerExpenseService::updatePolicy([
            'organization_id' => self::$testOrgId,
            'expense_type' => 'travel',
            'max_amount' => 200.00,
            'max_monthly' => 800.00,
        ]);

        $this->assertTrue($result);

        $policies = VolunteerExpenseService::getPolicies(self::$testOrgId);
        $travelPolicy = null;
        foreach ($policies as $p) {
            if ($p['expense_type'] === 'travel') {
                $travelPolicy = $p;
                break;
            }
        }
        $this->assertNotNull($travelPolicy);
        $this->assertEquals(200.00, (float)$travelPolicy['max_amount']);
    }

    public function testUpdatePolicyMissingExpenseTypeThrowsException(): void
    {
        $this->expectException(\InvalidArgumentException::class);

        VolunteerExpenseService::updatePolicy([
            'organization_id' => self::$testOrgId,
            'max_amount' => 50.00,
        ]);
    }

    public function testGetPoliciesWithoutOrgReturnsAllPolicies(): void
    {
        // Ensure at least one policy exists
        VolunteerExpenseService::updatePolicy([
            'organization_id' => self::$testOrgId,
            'expense_type' => 'supplies',
            'max_amount' => 50.00,
        ]);

        $policies = VolunteerExpenseService::getPolicies();

        $this->assertIsArray($policies);
        $this->assertNotEmpty($policies);
    }

    // ==========================================
    // Tenant Scoping Tests
    // ==========================================

    public function testTenantScopingPreventsAccessFromOtherTenant(): void
    {
        // Create an expense under tenant 2
        $expense = VolunteerExpenseService::submitExpense(self::$testUserId, [
            'organization_id' => self::$testOrgId,
            'expense_type' => 'supplies',
            'amount' => 20.00,
            'description' => 'Tenant scoping test expense',
        ]);
        $expenseId = (int)$expense['id'];

        // Verify accessible under tenant 2
        $found = VolunteerExpenseService::getExpense($expenseId);
        $this->assertNotNull($found);

        // Switch to a different tenant context
        TenantContext::setById(1);

        // Should NOT find the expense under tenant 1
        $notFound = VolunteerExpenseService::getExpense($expenseId);
        $this->assertNull($notFound);

        // Restore tenant context for remaining tests / teardown
        TenantContext::setById(self::$testTenantId);
    }
}
