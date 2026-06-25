<?php
// Copyright © 2024–2026 Jasper Ford
// SPDX-License-Identifier: AGPL-3.0-or-later
// Author: Jasper Ford
// See NOTICE file for attribution and acknowledgements.

declare(strict_types=1);

namespace Tests\Laravel\Unit\Console;

use App\Core\TenantContext;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Queue;
use Tests\Laravel\TestCase;

/**
 * Tests for tenant:audit-agoris-caring-content console command.
 *
 * The command is READ-ONLY — it never writes anything.  All tests rely only
 * on the data seeded here (rolled back after each test via DatabaseTransactions).
 *
 * Uses unique tenant id 99752 for isolation.
 */
class AuditAgorisCaringContentTest extends TestCase
{
    use DatabaseTransactions;

    private const TENANT_ID   = 99752;
    private const TENANT_SLUG = 'test-agoris-audit-99752';
    private const CMD         = 'tenant:audit-agoris-caring-content';

    protected function setUp(): void
    {
        parent::setUp();

        Queue::fake();

        DB::table('tenants')->insertOrIgnore([
            'id'         => self::TENANT_ID,
            'name'       => 'Agoris Audit Test Tenant',
            'slug'       => self::TENANT_SLUG,
            'is_active'  => 1,
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        TenantContext::setById(self::TENANT_ID);
    }

    // ------------------------------------------------------------------ //
    // Tenant resolution guard                                             //
    // ------------------------------------------------------------------ //

    public function test_exits_failure_when_slug_not_found(): void
    {
        $this->artisan(self::CMD, ['tenant_slug' => 'no-such-slug-xyz-99752'])
            ->assertExitCode(1);
    }

    public function test_error_message_when_slug_not_found(): void
    {
        $this->artisan(self::CMD, ['tenant_slug' => 'no-such-slug-xyz-99752'])
            ->expectsOutputToContain('No tenant found')
            ->assertExitCode(1);
    }

    // ------------------------------------------------------------------ //
    // Safety guard: expected-tenant-id mismatch                          //
    // ------------------------------------------------------------------ //

    public function test_exits_failure_when_tenant_id_mismatch(): void
    {
        // The real tenant id is TENANT_ID but we claim it should be 99999.
        $this->artisan(self::CMD, [
            'tenant_slug'          => self::TENANT_SLUG,
            '--expected-tenant-id' => 99999,
        ])->assertExitCode(1);
    }

    public function test_error_message_on_tenant_id_mismatch(): void
    {
        $this->artisan(self::CMD, [
            'tenant_slug'          => self::TENANT_SLUG,
            '--expected-tenant-id' => 99999,
        ])->expectsOutputToContain('Safety stop')
          ->assertExitCode(1);
    }

    // ------------------------------------------------------------------ //
    // Happy path: valid tenant, no expected-id guard clash               //
    // ------------------------------------------------------------------ //

    public function test_exits_with_a_valid_exit_code_for_known_tenant(): void
    {
        // The tenant exists with no seeded data → score < 900 → exit FAILURE (1).
        // The guard clause does NOT fire because expected-id matches.
        $exitCode = $this->artisan(self::CMD, [
            'tenant_slug'          => self::TENANT_SLUG,
            '--expected-tenant-id' => self::TENANT_ID,
        ])->execute();

        $this->assertContains($exitCode, [0, 1], 'Exit code must be 0 (success) or 1 (failure)');
    }

    public function test_output_contains_tenant_name(): void
    {
        $this->artisan(self::CMD, [
            'tenant_slug'          => self::TENANT_SLUG,
            '--expected-tenant-id' => self::TENANT_ID,
        ])->expectsOutputToContain('Agoris Audit Test Tenant')
          ->assertExitCode(1); // Score will be below 900 for an empty tenant — expect FAILURE
    }

    public function test_output_contains_mode_read_only(): void
    {
        $this->artisan(self::CMD, [
            'tenant_slug'          => self::TENANT_SLUG,
            '--expected-tenant-id' => self::TENANT_ID,
        ])->expectsOutputToContain('read-only')
          ->assertExitCode(1);
    }

    public function test_output_contains_score_line(): void
    {
        $this->artisan(self::CMD, [
            'tenant_slug'          => self::TENANT_SLUG,
            '--expected-tenant-id' => self::TENANT_ID,
        ])->expectsOutputToContain('Agoris caring content score')
          ->assertExitCode(1);
    }

    public function test_output_contains_below_threshold_warning(): void
    {
        // Empty test tenant → score < 900 → warns about seeding.
        $this->artisan(self::CMD, [
            'tenant_slug'          => self::TENANT_SLUG,
            '--expected-tenant-id' => self::TENANT_ID,
        ])->expectsOutputToContain('Below showcase threshold')
          ->assertExitCode(1);
    }

    // ------------------------------------------------------------------ //
    // expected-tenant-id=0 disables the guard                            //
    // ------------------------------------------------------------------ //

    public function test_expected_tenant_id_zero_disables_guard(): void
    {
        // --expected-tenant-id=0 means "skip the id check".
        // Anything other than a FAILURE-from-guard is acceptable here.
        $this->artisan(self::CMD, [
            'tenant_slug'          => self::TENANT_SLUG,
            '--expected-tenant-id' => 0,
        ])->expectsOutputToContain('read-only')
          ->assertExitCode(1); // Still fails due to empty data
    }

    // ------------------------------------------------------------------ //
    // Generic-row detection                                               //
    // ------------------------------------------------------------------ //

    public function test_output_shows_generic_row_still_present_when_seeded(): void
    {
        // Insert a known generic seed title for this tenant.
        // listings.user_id has a FK → users.id, so we need a valid user row.
        if (!DB::getSchemaBuilder()->hasTable('listings')) {
            $this->markTestSkipped('listings table does not exist in test DB');
        }

        // Create a minimal user row to satisfy the FK.
        $userId = DB::table('users')->insertGetId([
            'tenant_id'  => self::TENANT_ID,
            'name'       => 'Fixture User 99752',
            'email'      => 'fixture-99752@example.com',
            'password'   => bcrypt('password'),
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        DB::table('listings')->insert([
            'tenant_id'   => self::TENANT_ID,
            'user_id'     => $userId,
            'title'       => 'Clean house',
            'type'        => 'offer',
            'status'      => 'active',
            'created_at'  => now(),
            'updated_at'  => now(),
        ]);

        $this->artisan(self::CMD, [
            'tenant_slug'          => self::TENANT_SLUG,
            '--expected-tenant-id' => self::TENANT_ID,
        ])->expectsOutputToContain('STILL PRESENT')
          ->assertExitCode(1);
    }

    // ------------------------------------------------------------------ //
    // Showcase settings audit                                             //
    // ------------------------------------------------------------------ //

    public function test_output_contains_showcase_settings_heading(): void
    {
        $this->artisan(self::CMD, [
            'tenant_slug'          => self::TENANT_SLUG,
            '--expected-tenant-id' => self::TENANT_ID,
        ])->expectsOutputToContain('Showcase settings')
          ->assertExitCode(1);
    }

    public function test_settings_key_shown_as_missing_when_absent(): void
    {
        $this->artisan(self::CMD, [
            'tenant_slug'          => self::TENANT_SLUG,
            '--expected-tenant-id' => self::TENANT_ID,
        ])->expectsOutputToContain('MISSING')
          ->assertExitCode(1);
    }

    // ------------------------------------------------------------------ //
    // Caring table coverage heading                                       //
    // ------------------------------------------------------------------ //

    public function test_output_contains_caring_table_coverage_heading(): void
    {
        $this->artisan(self::CMD, [
            'tenant_slug'          => self::TENANT_SLUG,
            '--expected-tenant-id' => self::TENANT_ID,
        ])->expectsOutputToContain('Caring table coverage')
          ->assertExitCode(1);
    }
}
