<?php
// Copyright © 2024–2026 Jasper Ford
// SPDX-License-Identifier: AGPL-3.0-or-later
// Author: Jasper Ford
// See NOTICE file for attribution and acknowledgements.

namespace Tests\Laravel\Unit\Services;

use Tests\Laravel\TestCase;
use App\Services\AhvPensionExportService;
use App\Core\TenantContext;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Illuminate\Support\Facades\DB;

/**
 * AhvPensionExportServiceTest
 *
 * Tests the build() export structure and approved-contribution query logic.
 * All DB fixtures are inserted via DatabaseTransactions and rolled back automatically.
 *
 * The service reads from vol_logs (status='approved') and users tables.
 * vol_logs has a FK to users.id, so we insert a real user first.
 *
 * Skipped: the Schema::hasTable('vol_logs') guard — the table always exists in
 * the test DB, so the branch that returns [] is unreachable in this environment.
 */
class AhvPensionExportServiceTest extends TestCase
{
    use DatabaseTransactions;

    private const TENANT_ID = 2;

    private AhvPensionExportService $service;

    protected function setUp(): void
    {
        parent::setUp();
        TenantContext::setById(self::TENANT_ID);
        $this->service = new AhvPensionExportService();
    }

    // ── helpers ───────────────────────────────────────────────────────────────

    /**
     * Insert a minimal user row and return the generated ID.
     */
    private function insertUser(): int
    {
        $uid = uniqid('ahvtest_', true);
        return DB::table('users')->insertGetId([
            'tenant_id'  => self::TENANT_ID,
            'name'       => 'AHV Test ' . $uid,
            'first_name' => 'AHV',
            'last_name'  => 'User',
            'email'      => 'ahvtest.' . $uid . '@example.test',
            'status'     => 'active',
            'balance'    => 0.00,
            'role'       => 'member',
            'is_approved'=> 1,
            'created_at' => now(),
            'updated_at' => now(),
        ]);
    }

    /**
     * Insert a vol_log row and return the generated ID.
     *
     * @param array<string,mixed> $overrides
     */
    private function insertVolLog(int $userId, array $overrides = []): int
    {
        return DB::table('vol_logs')->insertGetId(array_merge([
            'tenant_id'   => self::TENANT_ID,
            'user_id'     => $userId,
            'date_logged' => '2024-03-15',
            'hours'       => 2.00,
            'status'      => 'approved',
            'created_at'  => now(),
            'updated_at'  => now(),
        ], $overrides));
    }

    // ── Return structure ──────────────────────────────────────────────────────

    public function test_build_returns_all_top_level_keys(): void
    {
        $userId = $this->insertUser();
        $result = $this->service->build(self::TENANT_ID, $userId);

        $this->assertArrayHasKey('format_version', $result);
        $this->assertArrayHasKey('generated_at', $result);
        $this->assertArrayHasKey('official_interface', $result);
        $this->assertArrayHasKey('tenant', $result);
        $this->assertArrayHasKey('member', $result);
        $this->assertArrayHasKey('period', $result);
        $this->assertArrayHasKey('summary', $result);
        $this->assertArrayHasKey('contribution_rows', $result);
    }

    public function test_build_format_version_is_provisional(): void
    {
        $userId = $this->insertUser();
        $result = $this->service->build(self::TENANT_ID, $userId);

        $this->assertSame(AhvPensionExportService::FORMAT_VERSION, $result['format_version']);
        $this->assertSame('0.1-provisional', $result['format_version']);
    }

    public function test_build_official_interface_flags_are_correct(): void
    {
        $userId = $this->insertUser();
        $result = $this->service->build(self::TENANT_ID, $userId);

        $iface = $result['official_interface'];
        $this->assertSame('pending_official_ahv_specification', $iface['status']);
        $this->assertFalse($iface['official_submission_supported']);
        $this->assertSame('evidence_pack', $iface['export_type']);
    }

    // ── Tenant / member population ────────────────────────────────────────────

    public function test_build_populates_tenant_id_and_slug(): void
    {
        $userId = $this->insertUser();
        $result = $this->service->build(self::TENANT_ID, $userId);

        $this->assertSame(self::TENANT_ID, $result['tenant']['id']);
        $this->assertSame('hour-timebank', $result['tenant']['slug']);
    }

    public function test_build_populates_member_id(): void
    {
        $userId = $this->insertUser();
        $result = $this->service->build(self::TENANT_ID, $userId);

        $this->assertSame($userId, $result['member']['id']);
    }

    // ── Member name resolution ────────────────────────────────────────────────

    public function test_build_member_name_uses_name_field_when_present(): void
    {
        $uid  = uniqid('nm_', true);
        $userId = DB::table('users')->insertGetId([
            'tenant_id'  => self::TENANT_ID,
            'name'       => 'Full Name',
            'first_name' => 'Full',
            'last_name'  => 'Name',
            'email'      => 'nm.' . $uid . '@example.test',
            'status'     => 'active',
            'balance'    => 0,
            'role'       => 'member',
            'is_approved'=> 1,
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        $result = $this->service->build(self::TENANT_ID, $userId);
        $this->assertSame('Full Name', $result['member']['name']);
    }

    public function test_build_member_name_falls_back_to_first_last(): void
    {
        $uid    = uniqid('fl_', true);
        $userId = DB::table('users')->insertGetId([
            'tenant_id'  => self::TENANT_ID,
            'name'       => '',   // empty — service falls back to first+last
            'first_name' => 'Alice',
            'last_name'  => 'Brennan',
            'email'      => 'fl.' . $uid . '@example.test',
            'status'     => 'active',
            'balance'    => 0,
            'role'       => 'member',
            'is_approved'=> 1,
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        // Override the name to empty string explicitly
        DB::table('users')->where('id', $userId)->update(['name' => '']);

        $result = $this->service->build(self::TENANT_ID, $userId);
        $this->assertSame('Alice Brennan', $result['member']['name']);
    }

    // ── Approved rows only ────────────────────────────────────────────────────

    public function test_build_includes_only_approved_vol_logs(): void
    {
        $userId = $this->insertUser();

        $this->insertVolLog($userId, ['status' => 'approved', 'hours' => 3.00]);
        $this->insertVolLog($userId, ['status' => 'pending',  'hours' => 1.00]);
        $this->insertVolLog($userId, ['status' => 'declined', 'hours' => 2.00]);

        $result = $this->service->build(self::TENANT_ID, $userId);

        $this->assertSame(1, $result['summary']['row_count']);
        $this->assertSame(3.0, $result['summary']['approved_hours']);
    }

    public function test_build_returns_empty_rows_when_no_approved_logs(): void
    {
        $userId = $this->insertUser();
        $this->insertVolLog($userId, ['status' => 'pending']);

        $result = $this->service->build(self::TENANT_ID, $userId);

        $this->assertSame(0, $result['summary']['row_count']);
        $this->assertSame(0.0, $result['summary']['approved_hours']);
        $this->assertCount(0, $result['contribution_rows']);
    }

    // ── Hour aggregation ──────────────────────────────────────────────────────

    public function test_build_totals_hours_across_multiple_logs(): void
    {
        $userId = $this->insertUser();

        $this->insertVolLog($userId, ['hours' => 2.50, 'date_logged' => '2024-01-10']);
        $this->insertVolLog($userId, ['hours' => 1.75, 'date_logged' => '2024-02-20']);
        $this->insertVolLog($userId, ['hours' => 0.50, 'date_logged' => '2024-03-05']);

        $result = $this->service->build(self::TENANT_ID, $userId);

        $this->assertSame(3, $result['summary']['row_count']);
        $this->assertSame(4.75, $result['summary']['approved_hours']);
    }

    // ── Year breakdown ────────────────────────────────────────────────────────

    public function test_build_produces_correct_year_totals(): void
    {
        $userId = $this->insertUser();

        $this->insertVolLog($userId, ['hours' => 3.00, 'date_logged' => '2023-06-01']);
        $this->insertVolLog($userId, ['hours' => 2.00, 'date_logged' => '2023-11-30']);
        $this->insertVolLog($userId, ['hours' => 5.00, 'date_logged' => '2024-03-15']);

        $result = $this->service->build(self::TENANT_ID, $userId);

        $years = $result['summary']['years'];
        $this->assertCount(2, $years, 'Should produce one entry per year');

        $byYear = array_column($years, null, 'year');

        $this->assertArrayHasKey(2023, $byYear);
        $this->assertSame(5.0, $byYear[2023]['approved_hours']);
        $this->assertSame(2, $byYear[2023]['row_count']);

        $this->assertArrayHasKey(2024, $byYear);
        $this->assertSame(5.0, $byYear[2024]['approved_hours']);
        $this->assertSame(1, $byYear[2024]['row_count']);
    }

    // ── Date filters ──────────────────────────────────────────────────────────

    public function test_build_filters_by_from_date(): void
    {
        $userId = $this->insertUser();

        $this->insertVolLog($userId, ['hours' => 1.00, 'date_logged' => '2023-12-31']);
        $this->insertVolLog($userId, ['hours' => 2.00, 'date_logged' => '2024-01-01']);
        $this->insertVolLog($userId, ['hours' => 3.00, 'date_logged' => '2024-06-01']);

        $result = $this->service->build(self::TENANT_ID, $userId, '2024-01-01', null);

        $this->assertSame(2, $result['summary']['row_count']);
        $this->assertSame(5.0, $result['summary']['approved_hours']);
    }

    public function test_build_filters_by_to_date(): void
    {
        $userId = $this->insertUser();

        $this->insertVolLog($userId, ['hours' => 2.00, 'date_logged' => '2024-01-15']);
        $this->insertVolLog($userId, ['hours' => 4.00, 'date_logged' => '2024-06-30']);
        $this->insertVolLog($userId, ['hours' => 1.00, 'date_logged' => '2024-07-01']);

        $result = $this->service->build(self::TENANT_ID, $userId, null, '2024-06-30');

        $this->assertSame(2, $result['summary']['row_count']);
        $this->assertSame(6.0, $result['summary']['approved_hours']);
    }

    public function test_build_filters_by_both_date_bounds(): void
    {
        $userId = $this->insertUser();

        $this->insertVolLog($userId, ['hours' => 1.00, 'date_logged' => '2023-12-31']);
        $this->insertVolLog($userId, ['hours' => 3.00, 'date_logged' => '2024-03-01']);
        $this->insertVolLog($userId, ['hours' => 2.00, 'date_logged' => '2024-09-01']);

        $result = $this->service->build(self::TENANT_ID, $userId, '2024-01-01', '2024-08-31');

        $this->assertSame(1, $result['summary']['row_count']);
        $this->assertSame(3.0, $result['summary']['approved_hours']);
    }

    // ── Period passthrough ────────────────────────────────────────────────────

    public function test_build_reflects_period_in_output(): void
    {
        $userId = $this->insertUser();
        $result = $this->service->build(self::TENANT_ID, $userId, '2024-01-01', '2024-12-31');

        $this->assertSame('2024-01-01', $result['period']['from']);
        $this->assertSame('2024-12-31', $result['period']['to']);
    }

    // ── Row shape ─────────────────────────────────────────────────────────────

    public function test_build_contribution_row_has_required_fields(): void
    {
        $userId = $this->insertUser();
        $this->insertVolLog($userId, ['hours' => 1.50, 'date_logged' => '2024-04-20']);

        $result = $this->service->build(self::TENANT_ID, $userId);
        $row = $result['contribution_rows'][0];

        $this->assertSame('vol_log', $row['source']);
        $this->assertSame('approved', $row['status']);
        $this->assertSame(2024, $row['year']);
        $this->assertSame(1.5, $row['hours']);
        $this->assertSame('2024-04-20', (string) $row['date']);
        $this->assertIsInt($row['record_id']);
    }

    // ── Tenant isolation ──────────────────────────────────────────────────────

    public function test_build_excludes_other_tenant_logs(): void
    {
        $userId  = $this->insertUser();

        // Insert a vol_log for a different tenant's user (bypass FK with raw tenant_id)
        $uid2    = uniqid('other_', true);
        $user2   = DB::table('users')->insertGetId([
            'tenant_id'  => 999,
            'name'       => 'Other ' . $uid2,
            'first_name' => 'Other',
            'last_name'  => 'User',
            'email'      => 'other.' . $uid2 . '@example.test',
            'status'     => 'active',
            'balance'    => 0,
            'role'       => 'member',
            'is_approved'=> 1,
            'created_at' => now(),
            'updated_at' => now(),
        ]);
        DB::table('vol_logs')->insert([
            'tenant_id'   => 999,
            'user_id'     => $user2,
            'date_logged' => '2024-05-01',
            'hours'       => 99.00,
            'status'      => 'approved',
            'created_at'  => now(),
            'updated_at'  => now(),
        ]);

        // Our user has zero logs
        $result = $this->service->build(self::TENANT_ID, $userId);

        $this->assertSame(0, $result['summary']['row_count']);
    }
}
