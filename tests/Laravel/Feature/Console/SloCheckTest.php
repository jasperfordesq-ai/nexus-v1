<?php
// Copyright © 2024–2026 Jasper Ford
// SPDX-License-Identifier: AGPL-3.0-or-later
// Author: Jasper Ford
// See NOTICE file for attribution and acknowledgements.

namespace Tests\Laravel\Feature\Console;

use App\Core\TenantContext;
use App\Models\Listing;
use App\Models\User;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Illuminate\Support\Facades\DB;
use Tests\Laravel\TestCase;

/**
 * Regression guard for the `slo:check` exchange-completion SLO alert.
 *
 * Asserts the alert fires (non-zero exit + "SLO BREACH") when the completion
 * rate drops below target, stays green when healthy, does not false-alarm on
 * thin data, and correctly excludes outcomes outside the measurement window.
 *
 * Scoped to a freshly-created throwaway tenant via `--tenant=` so the assertions
 * are deterministic regardless of pre-existing rows in the shared nexus_test DB.
 */
class SloCheckTest extends TestCase
{
    use DatabaseTransactions;

    private int $tenantId;
    private int $listingId;
    private int $requesterId;
    private int $providerId;

    protected function setUp(): void
    {
        parent::setUp();

        $this->tenantId = (int) DB::table('tenants')->insertGetId([
            'name' => 'SLO Drill Tenant',
            'slug' => 'slo-drill-' . uniqid(),
            'domain' => null,
            'is_active' => true,
            'depth' => 0,
            'allows_subtenants' => false,
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        // Re-pin so factory observers operate against this throwaway tenant.
        TenantContext::setById($this->tenantId);

        $this->providerId = (int) User::factory()->forTenant($this->tenantId)->create()->id;
        $this->requesterId = (int) User::factory()->forTenant($this->tenantId)->create()->id;
        $this->listingId = (int) Listing::factory()
            ->forTenant($this->tenantId)
            ->offer()
            ->create(['user_id' => $this->providerId])
            ->id;
    }

    public function test_breach_when_completion_rate_below_target(): void
    {
        // 1 completed + 4 disputed = 20% completion << 99.5% target.
        $this->seedExchange('completed', ['completed_at' => now()->subDay()]);
        for ($i = 0; $i < 4; $i++) {
            $this->seedExchange('disputed', ['updated_at' => now()->subDay()]);
        }

        $this->artisan('slo:check', ['--tenant' => $this->tenantId, '--min-sample' => 1])
            ->expectsOutputToContain('SLO BREACH')
            ->assertExitCode(1);
    }

    public function test_healthy_when_all_completed(): void
    {
        for ($i = 0; $i < 5; $i++) {
            $this->seedExchange('completed', ['completed_at' => now()->subDays(2)]);
        }

        $this->artisan('slo:check', ['--tenant' => $this->tenantId, '--min-sample' => 1])
            ->expectsOutputToContain('Exchange SLO OK')
            ->assertExitCode(0);
    }

    public function test_insufficient_data_does_not_alert(): void
    {
        // Two terminal rows is below the default min-sample (20): must not alert,
        // even though the raw ratio (0%) would otherwise breach.
        $this->seedExchange('disputed', ['updated_at' => now()->subDay()]);
        $this->seedExchange('disputed', ['updated_at' => now()->subDay()]);

        $this->artisan('slo:check', ['--tenant' => $this->tenantId])
            ->expectsOutputToContain('insufficient data')
            ->assertExitCode(0);
    }

    public function test_outcomes_outside_window_are_excluded(): void
    {
        // 1 completed in-window + 1 disputed 40 days ago (outside the 28d window).
        // The stale dispute must NOT count, so completion = 100% -> healthy.
        $this->seedExchange('completed', ['completed_at' => now()->subDay()]);
        $this->seedExchange('disputed', ['updated_at' => now()->subDays(40)]);

        $this->artisan('slo:check', ['--tenant' => $this->tenantId, '--min-sample' => 1])
            ->expectsOutputToContain('Exchange SLO OK')
            ->assertExitCode(0);
    }

    /**
     * @param array<string,mixed> $overrides
     */
    private function seedExchange(string $status, array $overrides = []): void
    {
        DB::table('exchange_requests')->insert(array_merge([
            'tenant_id' => $this->tenantId,
            'listing_id' => $this->listingId,
            'requester_id' => $this->requesterId,
            'provider_id' => $this->providerId,
            'proposed_hours' => 1.00,
            'status' => $status,
            'created_at' => now()->subDays(2),
            'updated_at' => now()->subDays(2),
        ], $overrides));
    }
}
