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
use App\Services\VolunteerDonationService;

class VolunteerDonationServiceTest extends DatabaseTestCase
{
    private static int $testTenantId = 2;
    private static ?int $testUserId = null;
    private static array $createdDonationIds = [];
    private static array $createdGivingDayIds = [];

    public static function setUpBeforeClass(): void
    {
        parent::setUpBeforeClass();
        TenantContext::setById(self::$testTenantId);

        $ts = time() . '_' . mt_rand(1000, 9999);

        // Create test user
        Database::query(
            "INSERT INTO users (tenant_id, email, username, first_name, last_name, name, balance, is_approved, status, created_at)
             VALUES (?, ?, ?, 'VD', 'Tester', 'VD Tester', 0, 1, 'active', NOW())",
            [self::$testTenantId, "vd_test_{$ts}@test.com", "vd_test_{$ts}"]
        );
        self::$testUserId = (int) Database::getInstance()->lastInsertId();
    }

    public static function tearDownAfterClass(): void
    {
        // Clean up donations
        foreach (self::$createdDonationIds as $id) {
            try { Database::query("DELETE FROM vol_donations WHERE id = ?", [$id]); } catch (\Exception $e) {}
        }
        // Clean up giving days
        foreach (self::$createdGivingDayIds as $id) {
            try { Database::query("DELETE FROM vol_giving_days WHERE id = ?", [$id]); } catch (\Exception $e) {}
        }
        // Clean up donations by user (catch any missed)
        if (self::$testUserId) {
            try { Database::query("DELETE FROM vol_donations WHERE user_id = ?", [self::$testUserId]); } catch (\Exception $e) {}
            try { Database::query("DELETE FROM users WHERE id = ?", [self::$testUserId]); } catch (\Exception $e) {}
        }

        parent::tearDownAfterClass();
    }

    protected function setUp(): void
    {
        // Skip parent setUp() transaction — the service manages its own transactions
        TenantContext::setById(self::$testTenantId);
    }

    protected function tearDown(): void
    {
        // Skip parent tearDown() rollback — we do manual cleanup
    }

    // =========================================================================
    // Class / method existence
    // =========================================================================

    public function testClassExists(): void
    {
        $this->assertTrue(class_exists(VolunteerDonationService::class));
    }

    public function testMethodsAreStatic(): void
    {
        $methods = [
            'getDonations', 'createDonation', 'getGivingDays',
            'getGivingDayStats', 'adminGetGivingDays', 'createGivingDay',
            'updateGivingDay', 'exportDonations',
        ];

        foreach ($methods as $method) {
            $ref = new \ReflectionMethod(VolunteerDonationService::class, $method);
            $this->assertTrue($ref->isStatic(), "Method {$method} should be static");
        }
    }

    // =========================================================================
    // createDonation
    // =========================================================================

    public function testCreateDonationReturnsArrayWithExpectedKeys(): void
    {
        $result = VolunteerDonationService::createDonation(self::$testUserId, [
            'amount'         => 25.50,
            'currency'       => 'EUR',
            'payment_method' => 'card',
            'message'        => 'Test donation',
        ]);

        self::$createdDonationIds[] = $result['id'];

        $this->assertIsArray($result);
        $expectedKeys = [
            'id', 'tenant_id', 'user_id', 'opportunity_id', 'giving_day_id',
            'amount', 'currency', 'payment_method', 'payment_reference',
            'message', 'is_anonymous', 'status', 'created_at',
        ];
        foreach ($expectedKeys as $key) {
            $this->assertArrayHasKey($key, $result, "Missing key: {$key}");
        }
        $this->assertEquals(self::$testUserId, $result['user_id']);
        $this->assertEquals('25.50', $result['amount']);
        $this->assertEquals('EUR', $result['currency']);
        $this->assertEquals('pending', $result['status']);
    }

    public function testCreateDonationWithAmountZeroThrows(): void
    {
        $this->expectException(\InvalidArgumentException::class);

        VolunteerDonationService::createDonation(self::$testUserId, [
            'amount'         => 0,
            'payment_method' => 'card',
        ]);
    }

    public function testCreateDonationWithNegativeAmountThrows(): void
    {
        $this->expectException(\InvalidArgumentException::class);

        VolunteerDonationService::createDonation(self::$testUserId, [
            'amount'         => -10,
            'payment_method' => 'card',
        ]);
    }

    public function testCreateDonationWithInvalidCurrencyThrows(): void
    {
        $this->expectException(\InvalidArgumentException::class);

        VolunteerDonationService::createDonation(self::$testUserId, [
            'amount'         => 10,
            'currency'       => 'EURO',
            'payment_method' => 'card',
        ]);
    }

    public function testCreateDonationWithoutPaymentMethodThrows(): void
    {
        $this->expectException(\InvalidArgumentException::class);

        VolunteerDonationService::createDonation(self::$testUserId, [
            'amount' => 10,
        ]);
    }

    public function testCreateDonationDefaultsCurrencyToEur(): void
    {
        $result = VolunteerDonationService::createDonation(self::$testUserId, [
            'amount'         => 5,
            'payment_method' => 'cash',
        ]);

        self::$createdDonationIds[] = $result['id'];
        $this->assertEquals('EUR', $result['currency']);
    }

    public function testCreateDonationAnonymousFlag(): void
    {
        $result = VolunteerDonationService::createDonation(self::$testUserId, [
            'amount'         => 10,
            'payment_method' => 'card',
            'is_anonymous'   => true,
        ]);

        self::$createdDonationIds[] = $result['id'];
        $this->assertEquals(1, $result['is_anonymous']);
    }

    // =========================================================================
    // getDonations
    // =========================================================================

    public function testGetDonationsReturnsPaginatedStructure(): void
    {
        // Ensure at least one donation exists
        $donation = VolunteerDonationService::createDonation(self::$testUserId, [
            'amount'         => 15,
            'payment_method' => 'bank_transfer',
        ]);
        self::$createdDonationIds[] = $donation['id'];

        $result = VolunteerDonationService::getDonations();

        $this->assertIsArray($result);
        $this->assertArrayHasKey('items', $result);
        $this->assertArrayHasKey('next_cursor', $result);
        $this->assertIsArray($result['items']);
    }

    public function testGetDonationsWithLimitFilter(): void
    {
        // Create 3 donations
        for ($i = 0; $i < 3; $i++) {
            $d = VolunteerDonationService::createDonation(self::$testUserId, [
                'amount'         => 1 + $i,
                'payment_method' => 'card',
            ]);
            self::$createdDonationIds[] = $d['id'];
        }

        $result = VolunteerDonationService::getDonations(['limit' => 2]);
        $this->assertLessThanOrEqual(2, count($result['items']));
    }

    // =========================================================================
    // createGivingDay
    // =========================================================================

    public function testCreateGivingDayReturnsArray(): void
    {
        $result = VolunteerDonationService::createGivingDay(0, [
            'title'       => 'Test Giving Day ' . time(),
            'description' => 'A test giving day',
            'start_date'  => '2026-06-01',
            'end_date'    => '2026-06-30',
            'goal_amount' => 1000,
        ]);

        self::$createdGivingDayIds[] = $result['id'];

        $this->assertIsArray($result);
        $this->assertArrayHasKey('id', $result);
        $this->assertArrayHasKey('title', $result);
        $this->assertEquals('1000.00', $result['goal_amount']);
        $this->assertEquals('0.00', $result['raised_amount']);
        $this->assertEquals(1, $result['is_active']);
    }

    public function testCreateGivingDayRequiresTitle(): void
    {
        $this->expectException(\InvalidArgumentException::class);

        VolunteerDonationService::createGivingDay(0, [
            'start_date'  => '2026-06-01',
            'end_date'    => '2026-06-30',
            'goal_amount' => 500,
        ]);
    }

    public function testCreateGivingDayRequiresDates(): void
    {
        $this->expectException(\InvalidArgumentException::class);

        VolunteerDonationService::createGivingDay(0, [
            'title'       => 'Missing Dates',
            'goal_amount' => 500,
        ]);
    }

    public function testCreateGivingDayRequiresGoalAmountGreaterThanZero(): void
    {
        $this->expectException(\InvalidArgumentException::class);

        VolunteerDonationService::createGivingDay(0, [
            'title'       => 'Zero Goal',
            'start_date'  => '2026-06-01',
            'end_date'    => '2026-06-30',
            'goal_amount' => 0,
        ]);
    }

    public function testCreateGivingDayEndBeforeStartThrows(): void
    {
        $this->expectException(\InvalidArgumentException::class);

        VolunteerDonationService::createGivingDay(0, [
            'title'       => 'Bad Dates',
            'start_date'  => '2026-06-30',
            'end_date'    => '2026-06-01',
            'goal_amount' => 500,
        ]);
    }

    // =========================================================================
    // getGivingDays
    // =========================================================================

    public function testGetGivingDaysReturnsArray(): void
    {
        $result = VolunteerDonationService::getGivingDays();
        $this->assertIsArray($result);
    }

    // =========================================================================
    // getGivingDayStats
    // =========================================================================

    public function testGetGivingDayStatsReturnsExpectedKeys(): void
    {
        $givingDay = VolunteerDonationService::createGivingDay(0, [
            'title'       => 'Stats Day ' . time(),
            'start_date'  => '2026-07-01',
            'end_date'    => '2026-07-31',
            'goal_amount' => 500,
        ]);
        self::$createdGivingDayIds[] = $givingDay['id'];

        $stats = VolunteerDonationService::getGivingDayStats($givingDay['id']);

        $this->assertIsArray($stats);
        foreach (['total_raised', 'donor_count', 'goal_amount', 'progress_percent'] as $key) {
            $this->assertArrayHasKey($key, $stats, "Missing key: {$key}");
        }
        $this->assertEquals('500.00', $stats['goal_amount']);
        $this->assertEquals(0, $stats['donor_count']);
    }

    public function testGetGivingDayStatsNonExistentThrows(): void
    {
        $this->expectException(\RuntimeException::class);

        VolunteerDonationService::getGivingDayStats(999999999);
    }

    // =========================================================================
    // updateGivingDay
    // =========================================================================

    public function testUpdateGivingDay(): void
    {
        $givingDay = VolunteerDonationService::createGivingDay(0, [
            'title'       => 'Update Test ' . time(),
            'start_date'  => '2026-08-01',
            'end_date'    => '2026-08-31',
            'goal_amount' => 200,
        ]);
        self::$createdGivingDayIds[] = $givingDay['id'];

        $updated = VolunteerDonationService::updateGivingDay($givingDay['id'], [
            'title'       => 'Updated Title',
            'goal_amount' => 300,
        ]);

        $this->assertTrue($updated);
    }

    public function testUpdateGivingDayWithGoalAmountZeroThrows(): void
    {
        $givingDay = VolunteerDonationService::createGivingDay(0, [
            'title'       => 'Update Zero ' . time(),
            'start_date'  => '2026-09-01',
            'end_date'    => '2026-09-30',
            'goal_amount' => 100,
        ]);
        self::$createdGivingDayIds[] = $givingDay['id'];

        $this->expectException(\InvalidArgumentException::class);

        VolunteerDonationService::updateGivingDay($givingDay['id'], [
            'goal_amount' => 0,
        ]);
    }

    // =========================================================================
    // Giving day + donation integration
    // =========================================================================

    public function testCreateDonationWithGivingDayIncrementsRaisedAmount(): void
    {
        $givingDay = VolunteerDonationService::createGivingDay(0, [
            'title'       => 'Increment Test ' . time(),
            'start_date'  => '2026-10-01',
            'end_date'    => '2026-10-31',
            'goal_amount' => 1000,
        ]);
        self::$createdGivingDayIds[] = $givingDay['id'];

        // Verify initial raised amount is 0
        $statsBefore = VolunteerDonationService::getGivingDayStats($givingDay['id']);
        $this->assertEquals('0.00', $statsBefore['total_raised']);

        // Create a completed donation linked to this giving day
        $donation = VolunteerDonationService::createDonation(self::$testUserId, [
            'amount'         => 50.00,
            'payment_method' => 'card',
            'giving_day_id'  => $givingDay['id'],
            'status'         => 'completed',
        ]);
        self::$createdDonationIds[] = $donation['id'];

        // Verify raised amount incremented
        $statsAfter = VolunteerDonationService::getGivingDayStats($givingDay['id']);
        $this->assertEquals('50.00', $statsAfter['total_raised']);
        $this->assertEquals(1, $statsAfter['donor_count']);
        $this->assertEqualsWithDelta(5.0, $statsAfter['progress_percent'], 0.01);
    }

    // =========================================================================
    // exportDonations
    // =========================================================================

    public function testExportDonationsReturnsCsvWithHeaderRow(): void
    {
        // Ensure at least one donation
        $donation = VolunteerDonationService::createDonation(self::$testUserId, [
            'amount'         => 20,
            'payment_method' => 'cash',
            'message'        => 'Export test',
        ]);
        self::$createdDonationIds[] = $donation['id'];

        $csv = VolunteerDonationService::exportDonations();

        $this->assertIsString($csv);
        $this->assertNotEmpty($csv);

        $lines = explode("\n", trim($csv));
        $this->assertGreaterThanOrEqual(2, count($lines), 'CSV should have header + at least 1 data row');

        // Check header contains expected columns
        $header = $lines[0];
        $this->assertStringContainsString('ID', $header);
        $this->assertStringContainsString('Amount', $header);
        $this->assertStringContainsString('Currency', $header);
        $this->assertStringContainsString('Payment Method', $header);
        $this->assertStringContainsString('Status', $header);
    }

    // =========================================================================
    // adminGetGivingDays
    // =========================================================================

    public function testAdminGetGivingDaysIncludesInactive(): void
    {
        // Create an active giving day
        $active = VolunteerDonationService::createGivingDay(0, [
            'title'       => 'Admin Active ' . time(),
            'start_date'  => '2026-11-01',
            'end_date'    => '2026-11-30',
            'goal_amount' => 100,
        ]);
        self::$createdGivingDayIds[] = $active['id'];

        // Deactivate it
        VolunteerDonationService::updateGivingDay($active['id'], ['is_active' => false]);

        // Create another active one
        $active2 = VolunteerDonationService::createGivingDay(0, [
            'title'       => 'Admin Active2 ' . time(),
            'start_date'  => '2026-12-01',
            'end_date'    => '2026-12-31',
            'goal_amount' => 200,
        ]);
        self::$createdGivingDayIds[] = $active2['id'];

        $adminDays = VolunteerDonationService::adminGetGivingDays();
        $adminIds = array_column($adminDays, 'id');

        // Admin list should include both active and inactive
        $this->assertContains($active['id'], $adminIds, 'Inactive giving day should appear in admin list');
        $this->assertContains($active2['id'], $adminIds, 'Active giving day should appear in admin list');

        // Public list should only include active
        $publicDays = VolunteerDonationService::getGivingDays();
        $publicIds = array_column($publicDays, 'id');

        $this->assertNotContains($active['id'], $publicIds, 'Inactive giving day should NOT appear in public list');
    }
}
