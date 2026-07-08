<?php
// Copyright © 2024–2026 Jasper Ford
// SPDX-License-Identifier: AGPL-3.0-or-later
// Author: Jasper Ford
// See NOTICE file for attribution and acknowledgements.

namespace Tests\Laravel\Feature\Controllers;

use Tests\Laravel\TestCase;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Illuminate\Support\Facades\DB;
use Laravel\Sanctum\Sanctum;
use App\Core\TenantContext;
use App\Models\User;
use App\Services\TenantFeatureConfig;

/**
 * Feature tests for VolunteerWellbeingController — emergency alerts, wellbeing, training, incidents.
 */
class VolunteerWellbeingControllerTest extends TestCase
{
    use DatabaseTransactions;

    private function authenticatedUser(): User
    {
        $user = User::factory()->forTenant($this->testTenantId)->create([
            'status' => 'active',
            'is_approved' => true,
        ]);

        Sanctum::actingAs($user, ['*']);

        return $user;
    }

    public function test_my_emergency_alerts_requires_auth(): void
    {
        $response = $this->apiGet('/v2/volunteering/emergency-alerts');

        $response->assertStatus(401);
    }

    public function test_wellbeing_dashboard_requires_auth(): void
    {
        $response = $this->apiGet('/v2/volunteering/wellbeing');

        $response->assertStatus(401);
    }

    public function test_my_training_requires_auth(): void
    {
        $response = $this->apiGet('/v2/volunteering/training');

        $response->assertStatus(401);
    }

    public function test_wellbeing_dashboard_authenticated_smoke(): void
    {
        $this->authenticatedUser();

        $response = $this->apiGet('/v2/volunteering/wellbeing');

        $this->assertLessThan(500, $response->status());
    }

    public function test_my_training_authenticated_smoke(): void
    {
        $this->authenticatedUser();

        $response = $this->apiGet('/v2/volunteering/training');

        $this->assertLessThan(500, $response->status());
    }

    // ------------------------------------------------------------------
    //  Admin wellbeing alerts — GET list + PUT status transition
    //  (contract consumed by the React coordinator dashboard)
    // ------------------------------------------------------------------

    private function enableVolunteeringFeature(int $tenantId): void
    {
        $features = TenantFeatureConfig::FEATURE_DEFAULTS;
        $features['volunteering'] = true;

        DB::table('tenants')->where('id', $tenantId)->update([
            'features' => json_encode($features),
        ]);

        TenantContext::setById($tenantId);
    }

    private function insertWellbeingAlert(int $userId, string $status = 'active', float $riskScore = 82.5): int
    {
        return (int) DB::table('vol_wellbeing_alerts')->insertGetId([
            'tenant_id' => $this->testTenantId,
            'user_id' => $userId,
            'risk_level' => 'high',
            'risk_score' => $riskScore,
            'indicators' => json_encode(['long_hours' => true]),
            'coordinator_notified' => 0,
            'status' => $status,
            'created_at' => now(),
            'updated_at' => now(),
        ]);
    }

    public function test_admin_wellbeing_alerts_requires_auth(): void
    {
        $response = $this->apiGet('/v2/admin/volunteering/wellbeing/alerts');

        $response->assertStatus(401);
    }

    public function test_admin_wellbeing_alerts_rejects_regular_member(): void
    {
        $this->enableVolunteeringFeature($this->testTenantId);

        $member = User::factory()->forTenant($this->testTenantId)->create();
        Sanctum::actingAs($member, ['*']);

        $response = $this->apiGet('/v2/admin/volunteering/wellbeing/alerts');

        $response->assertStatus(403);
    }

    public function test_admin_wellbeing_alerts_returns_active_by_default(): void
    {
        $this->enableVolunteeringFeature($this->testTenantId);

        $admin = User::factory()->forTenant($this->testTenantId)->admin()->create();
        $volunteer = User::factory()->forTenant($this->testTenantId)->create();

        $activeId = $this->insertWellbeingAlert($volunteer->id, 'active');
        $this->insertWellbeingAlert($volunteer->id, 'resolved', 40.0);

        Sanctum::actingAs($admin, ['*']);

        $response = $this->apiGet('/v2/admin/volunteering/wellbeing/alerts');

        $response->assertStatus(200);
        $items = $response->json('data');
        $this->assertIsArray($items);
        $ids = array_map(static fn ($row) => (int) $row['id'], $items);
        $this->assertContains($activeId, $ids);
        foreach ($items as $row) {
            $this->assertSame('active', $row['status']);
            $this->assertArrayHasKey('user_id', $row);
            $this->assertArrayHasKey('user_name', $row);
            $this->assertArrayHasKey('risk_level', $row);
            $this->assertArrayHasKey('created_at', $row);
        }
    }

    public function test_admin_wellbeing_alerts_filters_by_status(): void
    {
        $this->enableVolunteeringFeature($this->testTenantId);

        $admin = User::factory()->forTenant($this->testTenantId)->admin()->create();
        $volunteer = User::factory()->forTenant($this->testTenantId)->create();

        $resolvedId = $this->insertWellbeingAlert($volunteer->id, 'resolved');

        Sanctum::actingAs($admin, ['*']);

        $response = $this->apiGet('/v2/admin/volunteering/wellbeing/alerts?status=resolved');

        $response->assertStatus(200);
        $ids = array_map(static fn ($row) => (int) $row['id'], $response->json('data'));
        $this->assertContains($resolvedId, $ids);
    }

    public function test_admin_wellbeing_alerts_rejects_invalid_status(): void
    {
        $this->enableVolunteeringFeature($this->testTenantId);

        $admin = User::factory()->forTenant($this->testTenantId)->admin()->create();
        Sanctum::actingAs($admin, ['*']);

        $response = $this->apiGet('/v2/admin/volunteering/wellbeing/alerts?status=bogus');

        $response->assertStatus(422);
    }

    public function test_update_wellbeing_alert_transitions_status(): void
    {
        $this->enableVolunteeringFeature($this->testTenantId);

        $admin = User::factory()->forTenant($this->testTenantId)->admin()->create();
        $volunteer = User::factory()->forTenant($this->testTenantId)->create();
        $alertId = $this->insertWellbeingAlert($volunteer->id, 'active');

        Sanctum::actingAs($admin, ['*']);

        $response = $this->apiPut('/v2/admin/volunteering/wellbeing/alerts/' . $alertId, [
            'status' => 'acknowledged',
            'note' => 'Reached out to the volunteer.',
        ]);

        $response->assertStatus(200);
        $this->assertSame('acknowledged', DB::table('vol_wellbeing_alerts')
            ->where('id', $alertId)
            ->where('tenant_id', $this->testTenantId)
            ->value('status'));
    }

    public function test_update_wellbeing_alert_rejects_invalid_status(): void
    {
        $this->enableVolunteeringFeature($this->testTenantId);

        $admin = User::factory()->forTenant($this->testTenantId)->admin()->create();
        $volunteer = User::factory()->forTenant($this->testTenantId)->create();
        $alertId = $this->insertWellbeingAlert($volunteer->id, 'active');

        Sanctum::actingAs($admin, ['*']);

        $response = $this->apiPut('/v2/admin/volunteering/wellbeing/alerts/' . $alertId, [
            'status' => 'active',
        ]);

        $response->assertStatus(422);
    }

    public function test_update_wellbeing_alert_rejects_regular_member(): void
    {
        $this->enableVolunteeringFeature($this->testTenantId);

        $member = User::factory()->forTenant($this->testTenantId)->create();
        Sanctum::actingAs($member, ['*']);

        $response = $this->apiPut('/v2/admin/volunteering/wellbeing/alerts/1', [
            'status' => 'acknowledged',
        ]);

        $response->assertStatus(403);
    }
}
