<?php
// Copyright © 2024–2026 Jasper Ford
// SPDX-License-Identifier: AGPL-3.0-or-later
// Author: Jasper Ford
// See NOTICE file for attribution and acknowledgements.

declare(strict_types=1);

namespace Tests\Laravel\Unit\Console\Verein;

use App\Core\TenantContext;
use App\Services\EmailDispatchService;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Queue;
use Tests\Laravel\TestCase;

/**
 * Tests for verein:generate-annual-dues (GenerateAnnualDues).
 *
 * Unique tenant id: 99727 — never overlaps with any other test file.
 *
 * The command iterates verein_membership_fees (filtered by --tenant) and
 * calls VereinDuesService::generateAnnualDues() for each active fee config.
 * That service creates one verein_member_dues row per active org_member
 * (org_type=volunteer, status=active) and is idempotent.
 *
 * Email sending via EmailDispatchService is stubbed to return true so no
 * real SMTP calls are made.
 */
class GenerateAnnualDuesTest extends TestCase
{
    use DatabaseTransactions;

    private const TENANT_ID = 99727;
    private const YEAR      = 2099; // Far future — avoids clashing with live data.

    /** Seeded org id (int) — set in setUp. */
    private int $orgId;

    /** Seeded user ids */
    private int $user1Id;
    private int $user2Id;

    protected function setUp(): void
    {
        parent::setUp();
        Queue::fake();

        // Stub EmailDispatchService so no real SMTP calls are made.
        $stub = $this->createMock(EmailDispatchService::class);
        $stub->method('send')->willReturn(true);
        $this->app->instance(EmailDispatchService::class, $stub);

        // Seed tenant row.
        DB::table('tenants')->updateOrInsert(
            ['id' => self::TENANT_ID],
            [
                'name'       => 'Verein GenerateAnnualDues Test Tenant',
                'slug'       => 'verein-generate-99727',
                'created_at' => now(),
                'updated_at' => now(),
            ]
        );

        // Seed two users for this tenant.
        $this->user1Id = DB::table('users')->insertGetId([
            'tenant_id'  => self::TENANT_ID,
            'name'       => 'Verein User One 99727',
            'first_name' => 'Verein',
            'last_name'  => 'UserOne',
            'email'      => 'verein-u1-99727@example-test.invalid',
            'status'     => 'active',
            'preferred_language' => 'en',
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        $this->user2Id = DB::table('users')->insertGetId([
            'tenant_id'  => self::TENANT_ID,
            'name'       => 'Verein User Two 99727',
            'first_name' => 'Verein',
            'last_name'  => 'UserTwo',
            'email'      => 'verein-u2-99727@example-test.invalid',
            'status'     => 'active',
            'preferred_language' => 'en',
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        // Seed a club organisation (org_type must be 'club' for assertOrganizationIsClub).
        $ownerUserId = $this->user1Id;
        $this->orgId = DB::table('vol_organizations')->insertGetId([
            'tenant_id'  => self::TENANT_ID,
            'user_id'    => $ownerUserId,
            'name'       => 'Verein Test Club 99727',
            'org_type'   => 'club',
            'status'     => 'active',
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        // Seed an active fee config for the club.
        DB::table('verein_membership_fees')->updateOrInsert(
            ['organization_id' => $this->orgId],
            [
                'tenant_id'       => self::TENANT_ID,
                'fee_amount_cents' => 12000, // CHF 120.00
                'currency'        => 'CHF',
                'billing_cycle'   => 'annual',
                'grace_period_days' => 30,
                'is_active'       => 1,
                'created_at'      => now(),
                'updated_at'      => now(),
            ]
        );

        // Make both users active volunteer members of the club.
        foreach ([$this->user1Id, $this->user2Id] as $uid) {
            DB::table('org_members')->updateOrInsert(
                ['org_type' => 'volunteer', 'organization_id' => $this->orgId, 'user_id' => $uid],
                [
                    'tenant_id'       => self::TENANT_ID,
                    'organization_id' => $this->orgId,
                    'org_type'        => 'volunteer',
                    'user_id'         => $uid,
                    'role'            => 'member',
                    'status'          => 'active',
                    'created_at'      => now(),
                    'updated_at'      => now(),
                ]
            );
        }

        TenantContext::setById(self::TENANT_ID);
    }

    // ─────────────────────────────────────────────────────────────────────────
    // Happy path: dues rows created for active members
    // ─────────────────────────────────────────────────────────────────────────

    public function test_generates_dues_rows_for_active_members(): void
    {
        $this->artisan('verein:generate-annual-dues', [
            '--year'         => self::YEAR,
            '--tenant'       => self::TENANT_ID,
            '--organization' => $this->orgId,
        ])->assertExitCode(0);

        $dues = DB::table('verein_member_dues')
            ->where('organization_id', $this->orgId)
            ->where('tenant_id', self::TENANT_ID)
            ->where('membership_year', self::YEAR)
            ->get();

        $this->assertCount(2, $dues, 'Two active members → two dues rows');
    }

    public function test_dues_rows_have_correct_amount(): void
    {
        $this->artisan('verein:generate-annual-dues', [
            '--year'         => self::YEAR,
            '--tenant'       => self::TENANT_ID,
            '--organization' => $this->orgId,
        ])->assertExitCode(0);

        $row = DB::table('verein_member_dues')
            ->where('organization_id', $this->orgId)
            ->where('tenant_id', self::TENANT_ID)
            ->where('user_id', $this->user1Id)
            ->where('membership_year', self::YEAR)
            ->first();

        $this->assertNotNull($row, 'Dues row for user1 must exist');
        $this->assertSame(12000, (int) $row->amount_cents, 'Amount must match fee config (CHF 120.00 = 12000 cents)');
        $this->assertSame('CHF', $row->currency);
        $this->assertSame('pending', $row->status);
        $this->assertSame(self::YEAR, (int) $row->membership_year);
    }

    public function test_due_date_is_set_to_jan_31_of_the_year(): void
    {
        $this->artisan('verein:generate-annual-dues', [
            '--year'         => self::YEAR,
            '--tenant'       => self::TENANT_ID,
            '--organization' => $this->orgId,
        ])->assertExitCode(0);

        $row = DB::table('verein_member_dues')
            ->where('organization_id', $this->orgId)
            ->where('tenant_id', self::TENANT_ID)
            ->where('user_id', $this->user1Id)
            ->where('membership_year', self::YEAR)
            ->first();

        $this->assertNotNull($row);
        $this->assertSame(self::YEAR . '-01-31', $row->due_date, 'Due date must be Jan 31 of the membership year');
    }

    // ─────────────────────────────────────────────────────────────────────────
    // Idempotency: re-running the same year does NOT duplicate rows
    // ─────────────────────────────────────────────────────────────────────────

    public function test_is_idempotent_second_run_skips_existing_rows(): void
    {
        $args = [
            '--year'         => self::YEAR,
            '--tenant'       => self::TENANT_ID,
            '--organization' => $this->orgId,
        ];

        $this->artisan('verein:generate-annual-dues', $args)->assertExitCode(0);
        $this->artisan('verein:generate-annual-dues', $args)->assertExitCode(0);

        $count = DB::table('verein_member_dues')
            ->where('organization_id', $this->orgId)
            ->where('tenant_id', self::TENANT_ID)
            ->where('membership_year', self::YEAR)
            ->count();

        $this->assertSame(2, $count, 'Second run must not duplicate dues rows');
    }

    // ─────────────────────────────────────────────────────────────────────────
    // Inactive / non-volunteer members are skipped
    // ─────────────────────────────────────────────────────────────────────────

    public function test_inactive_member_is_skipped(): void
    {
        // Insert a third user who is inactive.
        $inactiveUserId = DB::table('users')->insertGetId([
            'tenant_id'  => self::TENANT_ID,
            'name'       => 'Verein Inactive 99727',
            'email'      => 'verein-inactive-99727@example-test.invalid',
            'status'     => 'inactive',
            'preferred_language' => 'en',
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        DB::table('org_members')->updateOrInsert(
            ['org_type' => 'volunteer', 'organization_id' => $this->orgId, 'user_id' => $inactiveUserId],
            [
                'tenant_id'       => self::TENANT_ID,
                'organization_id' => $this->orgId,
                'org_type'        => 'volunteer',
                'user_id'         => $inactiveUserId,
                'role'            => 'member',
                'status'          => 'removed', // NOT active
                'created_at'      => now(),
                'updated_at'      => now(),
            ]
        );

        $this->artisan('verein:generate-annual-dues', [
            '--year'         => self::YEAR,
            '--tenant'       => self::TENANT_ID,
            '--organization' => $this->orgId,
        ])->assertExitCode(0);

        $count = DB::table('verein_member_dues')
            ->where('organization_id', $this->orgId)
            ->where('tenant_id', self::TENANT_ID)
            ->where('membership_year', self::YEAR)
            ->count();

        // Only the 2 active members should have dues rows, not the inactive one.
        $this->assertSame(2, $count, 'Non-active org_members must be skipped');

        $inactiveRow = DB::table('verein_member_dues')
            ->where('organization_id', $this->orgId)
            ->where('user_id', $inactiveUserId)
            ->where('membership_year', self::YEAR)
            ->first();

        $this->assertNull($inactiveRow, 'Inactive/removed member must have no dues row');
    }

    // ─────────────────────────────────────────────────────────────────────────
    // No-op when the fee config is inactive
    // ─────────────────────────────────────────────────────────────────────────

    public function test_no_rows_generated_when_fee_config_is_inactive(): void
    {
        // Deactivate the fee config.
        DB::table('verein_membership_fees')
            ->where('organization_id', $this->orgId)
            ->where('tenant_id', self::TENANT_ID)
            ->update(['is_active' => 0]);

        $this->artisan('verein:generate-annual-dues', [
            '--year'         => self::YEAR,
            '--tenant'       => self::TENANT_ID,
            '--organization' => $this->orgId,
        ])->assertExitCode(0); // command catches the RuntimeException per-org

        $count = DB::table('verein_member_dues')
            ->where('organization_id', $this->orgId)
            ->where('tenant_id', self::TENANT_ID)
            ->where('membership_year', self::YEAR)
            ->count();

        $this->assertSame(0, $count, 'Inactive fee config must produce zero dues rows');
    }

    // ─────────────────────────────────────────────────────────────────────────
    // --tenant filter: only our tenant's configs are processed
    // ─────────────────────────────────────────────────────────────────────────

    public function test_tenant_filter_excludes_other_tenants(): void
    {
        // Seed a minimal second tenant + org + fee config.
        $otherTenantId = 99726; // not our TENANT_ID
        DB::table('tenants')->updateOrInsert(
            ['id' => $otherTenantId],
            ['name' => 'Other Tenant 99726', 'slug' => 'other-99726', 'created_at' => now(), 'updated_at' => now()]
        );

        $otherUserId = DB::table('users')->insertGetId([
            'tenant_id' => $otherTenantId,
            'name'      => 'Other User 99726',
            'email'     => 'other-99726@example-test.invalid',
            'status'    => 'active',
            'preferred_language' => 'en',
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        $otherOrgId = DB::table('vol_organizations')->insertGetId([
            'tenant_id' => $otherTenantId,
            'user_id'   => $otherUserId,
            'name'      => 'Other Club 99726',
            'org_type'  => 'club',
            'status'    => 'active',
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        DB::table('verein_membership_fees')->updateOrInsert(
            ['organization_id' => $otherOrgId],
            [
                'tenant_id'        => $otherTenantId,
                'fee_amount_cents' => 5000,
                'currency'         => 'CHF',
                'billing_cycle'    => 'annual',
                'grace_period_days' => 30,
                'is_active'        => 1,
                'created_at'       => now(),
                'updated_at'       => now(),
            ]
        );

        DB::table('org_members')->updateOrInsert(
            ['org_type' => 'volunteer', 'organization_id' => $otherOrgId, 'user_id' => $otherUserId],
            [
                'tenant_id'       => $otherTenantId,
                'organization_id' => $otherOrgId,
                'org_type'        => 'volunteer',
                'user_id'         => $otherUserId,
                'role'            => 'member',
                'status'          => 'active',
                'created_at'      => now(),
                'updated_at'      => now(),
            ]
        );

        // Run scoped to OUR tenant only.
        $this->artisan('verein:generate-annual-dues', [
            '--year'   => self::YEAR,
            '--tenant' => self::TENANT_ID,
        ])->assertExitCode(0);

        // Other tenant's org must have zero dues rows.
        $otherCount = DB::table('verein_member_dues')
            ->where('organization_id', $otherOrgId)
            ->where('tenant_id', $otherTenantId)
            ->where('membership_year', self::YEAR)
            ->count();

        $this->assertSame(0, $otherCount, '--tenant filter must not generate dues for other tenants');

        // Our tenant's org must have the expected rows.
        $ourCount = DB::table('verein_member_dues')
            ->where('organization_id', $this->orgId)
            ->where('tenant_id', self::TENANT_ID)
            ->where('membership_year', self::YEAR)
            ->count();

        $this->assertSame(2, $ourCount, 'Our tenant rows must still be generated');
    }

    // ─────────────────────────────────────────────────────────────────────────
    // Command returns SUCCESS (0) even with no matching configs
    // ─────────────────────────────────────────────────────────────────────────

    public function test_exits_success_when_no_configs_match(): void
    {
        // Pass an org filter that matches nothing.
        $this->artisan('verein:generate-annual-dues', [
            '--year'         => self::YEAR,
            '--tenant'       => self::TENANT_ID,
            '--organization' => 9999999,
        ])->assertExitCode(0);

        // No assertion on counts — the test proves assertExitCode(0) is the
        // only outcome; but we still need ≥1 assertion to avoid risky.
        $this->assertSame(0, DB::table('verein_member_dues')
            ->where('tenant_id', self::TENANT_ID)
            ->where('membership_year', self::YEAR)
            ->where('organization_id', 9999999)
            ->count());
    }
}
