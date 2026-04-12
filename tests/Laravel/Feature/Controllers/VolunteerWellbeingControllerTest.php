<?php
// Copyright © 2024–2026 Jasper Ford
// SPDX-License-Identifier: AGPL-3.0-or-later
// Author: Jasper Ford
// See NOTICE file for attribution and acknowledgements.

namespace Tests\Laravel\Feature\Controllers;

use Tests\Laravel\TestCase;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Laravel\Sanctum\Sanctum;
use App\Models\User;

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
}
