<?php
// Copyright © 2024–2026 Jasper Ford
// SPDX-License-Identifier: AGPL-3.0-or-later
// Author: Jasper Ford
// See NOTICE file for attribution and acknowledgements.

declare(strict_types=1);

namespace Tests\Laravel\Unit\Console;

use App\Core\TenantContext;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Queue;
use Tests\Laravel\TestCase;

/**
 * Tests for sitemap:generate console command (GenerateSitemap).
 *
 * Uses unique tenant id 99753 for isolation.
 *
 * SitemapService uses Cache::remember() for all output — using Cache::fake()
 * / the array driver (set in testing .env) means generation runs fresh each
 * time without touching the filesystem and without real HTTP calls.
 */
class GenerateSitemapTest extends TestCase
{
    use DatabaseTransactions;

    private const TENANT_ID   = 99753;
    private const TENANT_SLUG = 'test-sitemap-99753';
    private const CMD         = 'sitemap:generate';

    protected function setUp(): void
    {
        parent::setUp();

        Queue::fake();

        // Flush the in-process cache so results from prior tests don't bleed in.
        Cache::flush();

        DB::table('tenants')->insertOrIgnore([
            'id'            => self::TENANT_ID,
            'name'          => 'Sitemap Test Tenant',
            'slug'          => self::TENANT_SLUG,
            'is_active'     => 1,
            'features'      => json_encode([]),
            'configuration' => json_encode(['modules' => []]),
            'created_at'    => now(),
            'updated_at'    => now(),
        ]);

        TenantContext::setById(self::TENANT_ID);
    }

    // ------------------------------------------------------------------ //
    // --clear flag                                                        //
    // ------------------------------------------------------------------ //

    public function test_clear_exits_success_for_all_tenants(): void
    {
        $this->artisan(self::CMD, ['--clear' => true])
            ->assertExitCode(0);
    }

    public function test_clear_output_mentions_cache_cleared(): void
    {
        $this->artisan(self::CMD, ['--clear' => true])
            ->expectsOutputToContain('cache cleared')
            ->assertExitCode(0);
    }

    public function test_clear_with_specific_tenant_exits_success(): void
    {
        $this->artisan(self::CMD, ['--clear' => true, '--tenant' => self::TENANT_ID])
            ->assertExitCode(0);
    }

    public function test_clear_with_specific_tenant_output_mentions_tenant_id(): void
    {
        $this->artisan(self::CMD, ['--clear' => true, '--tenant' => self::TENANT_ID])
            ->expectsOutputToContain((string) self::TENANT_ID)
            ->assertExitCode(0);
    }

    // ------------------------------------------------------------------ //
    // --stats flag (no-op read path)                                      //
    // ------------------------------------------------------------------ //

    public function test_stats_exits_success_for_all_tenants(): void
    {
        $this->artisan(self::CMD, ['--stats' => true])
            ->assertExitCode(0);
    }

    public function test_stats_output_contains_statistics_heading(): void
    {
        $this->artisan(self::CMD, ['--stats' => true])
            ->expectsOutputToContain('statistics')
            ->assertExitCode(0);
    }

    public function test_stats_for_specific_tenant_exits_success(): void
    {
        $this->artisan(self::CMD, ['--stats' => true, '--tenant' => self::TENANT_ID])
            ->assertExitCode(0);
    }

    public function test_stats_for_specific_tenant_output_contains_tenant_name(): void
    {
        $this->artisan(self::CMD, ['--stats' => true, '--tenant' => self::TENANT_ID])
            ->expectsOutputToContain('Sitemap Test Tenant')
            ->assertExitCode(0);
    }

    public function test_stats_for_specific_tenant_output_contains_total_urls(): void
    {
        $this->artisan(self::CMD, ['--stats' => true, '--tenant' => self::TENANT_ID])
            ->expectsOutputToContain('Total URLs')
            ->assertExitCode(0);
    }

    // ------------------------------------------------------------------ //
    // --stats for inactive / missing tenant                               //
    // ------------------------------------------------------------------ //

    public function test_stats_for_inactive_tenant_exits_failure(): void
    {
        // Mark our test tenant inactive first.
        DB::table('tenants')->where('id', self::TENANT_ID)->update(['is_active' => 0]);

        $this->artisan(self::CMD, ['--stats' => true, '--tenant' => self::TENANT_ID])
            ->expectsOutputToContain('not found or inactive')
            ->assertExitCode(1);
    }

    // ------------------------------------------------------------------ //
    // generate for specific tenant                                        //
    // ------------------------------------------------------------------ //

    public function test_generate_for_specific_tenant_exits_success(): void
    {
        $this->artisan(self::CMD, ['--tenant' => self::TENANT_ID])
            ->assertExitCode(0);
    }

    public function test_generate_for_specific_tenant_output_contains_generated(): void
    {
        $this->artisan(self::CMD, ['--tenant' => self::TENANT_ID])
            ->expectsOutputToContain('Generated')
            ->assertExitCode(0);
    }

    public function test_generate_for_specific_tenant_output_contains_tenant_name(): void
    {
        $this->artisan(self::CMD, ['--tenant' => self::TENANT_ID])
            ->expectsOutputToContain('Sitemap Test Tenant')
            ->assertExitCode(0);
    }

    // ------------------------------------------------------------------ //
    // generate for missing / inactive tenant                             //
    // ------------------------------------------------------------------ //

    public function test_generate_for_nonexistent_tenant_exits_failure(): void
    {
        $this->artisan(self::CMD, ['--tenant' => 99999999])
            ->expectsOutputToContain('not found or inactive')
            ->assertExitCode(1);
    }

    public function test_generate_for_inactive_tenant_exits_failure(): void
    {
        DB::table('tenants')->where('id', self::TENANT_ID)->update(['is_active' => 0]);

        $this->artisan(self::CMD, ['--tenant' => self::TENANT_ID])
            ->expectsOutputToContain('not found or inactive')
            ->assertExitCode(1);
    }

    // ------------------------------------------------------------------ //
    // generate --all (no --tenant flag)                                  //
    // ------------------------------------------------------------------ //

    public function test_generate_all_exits_success_when_active_tenants_exist(): void
    {
        // At minimum our test tenant is active, so this path should run.
        $this->artisan(self::CMD)
            ->assertExitCode(0);
    }

    public function test_generate_all_output_contains_sitemap_index_generated(): void
    {
        $this->artisan(self::CMD)
            ->expectsOutputToContain('Sitemap index generated')
            ->assertExitCode(0);
    }

    public function test_generate_all_output_contains_total_urls(): void
    {
        $this->artisan(self::CMD)
            ->expectsOutputToContain('Total URLs')
            ->assertExitCode(0);
    }

    // ------------------------------------------------------------------ //
    // Generate all when NO active tenants exist                          //
    // ------------------------------------------------------------------ //

    public function test_generate_all_exits_success_with_warning_when_no_active_tenants(): void
    {
        // Deactivate ALL tenants for this sub-test.
        DB::table('tenants')->update(['is_active' => 0]);

        $this->artisan(self::CMD)
            ->expectsOutputToContain('No active tenants')
            ->assertExitCode(0);

        // Reactivate so tearDown / other tests are not broken.
        DB::table('tenants')->where('id', self::TENANT_ID)->update(['is_active' => 1]);
    }
}
