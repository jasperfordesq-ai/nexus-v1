<?php
// Copyright © 2024–2026 Jasper Ford
// SPDX-License-Identifier: AGPL-3.0-or-later
// Author: Jasper Ford
// See NOTICE file for attribution and acknowledgements.

declare(strict_types=1);

namespace Tests\Laravel\Feature\Controllers;

use App\Core\TenantContext;
use App\Models\User;
use App\Services\TenantFeatureConfig;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Illuminate\Support\Facades\DB;
use Laravel\Sanctum\Sanctum;
use Tests\Laravel\TestCase;

/**
 * Feature tests for VolunteerExpenseController.
 *
 * Covers:
 *  - GET /v2/volunteering/expenses — my expenses (auth, feature gate)
 *  - GET /v2/admin/volunteering/expenses — admin-only listing
 *  - Feature flag disabled -> 403
 *  - reviewExpense validation of status enum
 */
class VolunteerExpenseControllerTest extends TestCase
{
    use DatabaseTransactions;

    private function enableVolunteeringFeature(int $tenantId): void
    {
        $features = TenantFeatureConfig::FEATURE_DEFAULTS;
        $features['volunteering'] = true;

        DB::table('tenants')->where('id', $tenantId)->update([
            'features' => json_encode($features),
        ]);

        TenantContext::setById($tenantId);
    }

    public function test_my_expenses_requires_auth(): void
    {
        $this->enableVolunteeringFeature($this->testTenantId);

        $response = $this->apiGet('/v2/volunteering/expenses');

        $this->assertContains($response->getStatusCode(), [401, 403]);
    }

    public function test_my_expenses_returns_data_for_authenticated_user(): void
    {
        $this->enableVolunteeringFeature($this->testTenantId);

        $user = User::factory()->forTenant($this->testTenantId)->create();
        Sanctum::actingAs($user);

        $response = $this->apiGet('/v2/volunteering/expenses');

        $response->assertStatus(200);
        $response->assertJsonStructure(['data']);
    }

    public function test_my_expenses_returns_403_when_feature_disabled(): void
    {
        DB::table('tenants')->where('id', $this->testTenantId)->update([
            'features' => json_encode(['volunteering' => false]),
        ]);
        TenantContext::setById($this->testTenantId);

        $user = User::factory()->forTenant($this->testTenantId)->create();
        Sanctum::actingAs($user);

        $response = $this->apiGet('/v2/volunteering/expenses');

        $response->assertStatus(403);
    }

    public function test_admin_expenses_rejects_regular_member(): void
    {
        $this->enableVolunteeringFeature($this->testTenantId);

        $member = User::factory()->forTenant($this->testTenantId)->create();
        Sanctum::actingAs($member);

        $response = $this->apiGet('/v2/admin/volunteering/expenses');

        $response->assertStatus(403);
    }

    public function test_review_expense_validates_status(): void
    {
        $this->enableVolunteeringFeature($this->testTenantId);

        $admin = User::factory()->forTenant($this->testTenantId)->admin()->create();
        Sanctum::actingAs($admin);

        $response = $this->apiPut('/v2/admin/volunteering/expenses/1', [
            'status' => 'not_a_valid_status',
        ]);

        $response->assertStatus(422);
    }
}
