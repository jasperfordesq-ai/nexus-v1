<?php
// Copyright © 2024–2026 Jasper Ford
// SPDX-License-Identifier: AGPL-3.0-or-later
// Author: Jasper Ford
// See NOTICE file for attribution and acknowledgements.

namespace Tests\Laravel\Feature\Volunteering;

use App\Core\TenantContext;
use App\Models\User;
use App\Services\VolunteerWellbeingService;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Illuminate\Support\Facades\DB;
use Tests\Laravel\TestCase;

/**
 * Burnout detection must not write on a read. The wellbeing dashboard /
 * my-status GET endpoints call detectBurnoutRisk() with the default
 * $persist = false, so viewing wellbeing never mutates vol_wellbeing_alerts;
 * the scheduled tenant assessment passes $persist = true and is the sole
 * write path.
 */
class VolunteerWellbeingAlertPersistenceTest extends TestCase
{
    use DatabaseTransactions;

    /**
     * Seed a deterministically at-risk volunteer: one approved 10h log 45 days
     * ago gives an hours trend of "declining_significantly" (+25, recent 30d
     * hours are 0 vs 10 in the prior 30d) plus an engagement gap > 30 days
     * (+10) = risk score 35, above the 30-point alert threshold.
     */
    private function seedAtRiskVolunteer(): int
    {
        TenantContext::setById($this->testTenantId);
        $user = User::factory()->forTenant($this->testTenantId)->create();

        DB::table('vol_logs')->insert([
            'tenant_id' => $this->testTenantId,
            'user_id' => $user->id,
            'organization_id' => null,
            'date_logged' => now()->subDays(45)->toDateString(),
            'hours' => 10.0,
            'status' => 'approved',
            'created_at' => now()->subDays(45),
            'updated_at' => now()->subDays(45),
        ]);

        return (int) $user->id;
    }

    public function test_read_path_does_not_write_wellbeing_alert(): void
    {
        $userId = $this->seedAtRiskVolunteer();

        // Creating the fixture user re-resolves TenantContext to the default
        // tenant; re-assert it so the assessment scopes to the seeded tenant.
        TenantContext::setById($this->testTenantId);
        $assessment = VolunteerWellbeingService::detectBurnoutRisk($userId); // $persist defaults to false

        $this->assertGreaterThanOrEqual(30, $assessment['risk_score'], 'fixture should be at-risk');
        $this->assertDatabaseMissing('vol_wellbeing_alerts', [
            'tenant_id' => $this->testTenantId,
            'user_id' => $userId,
        ]);
    }

    public function test_assessment_write_path_upserts_wellbeing_alert(): void
    {
        $userId = $this->seedAtRiskVolunteer();

        TenantContext::setById($this->testTenantId);
        VolunteerWellbeingService::detectBurnoutRisk($userId, true);

        $this->assertDatabaseHas('vol_wellbeing_alerts', [
            'tenant_id' => $this->testTenantId,
            'user_id' => $userId,
            'status' => 'active',
        ]);
    }
}
