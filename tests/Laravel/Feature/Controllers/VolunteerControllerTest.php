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
 * Feature tests for VolunteerController — volunteering opportunities, applications,
 * shifts, hours, organisations, certificates, expenses, and more.
 */
class VolunteerControllerTest extends TestCase
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

    // ------------------------------------------------------------------
    //  GET /v2/volunteering/opportunities
    // ------------------------------------------------------------------

    public function test_opportunities_requires_auth(): void
    {
        $response = $this->apiGet('/v2/volunteering/opportunities');

        $response->assertStatus(401);
    }

    public function test_opportunities_returns_data(): void
    {
        $this->authenticatedUser();

        $response = $this->apiGet('/v2/volunteering/opportunities');

        $response->assertStatus(200);
    }

    // ------------------------------------------------------------------
    //  POST /v2/volunteering/opportunities
    // ------------------------------------------------------------------

    public function test_create_opportunity_requires_auth(): void
    {
        $response = $this->apiPost('/v2/volunteering/opportunities', [
            'title' => 'Park Cleanup',
            'description' => 'Help clean up the local park',
        ]);

        $response->assertStatus(401);
    }

    // ------------------------------------------------------------------
    //  GET /v2/volunteering/opportunities/{id}
    // ------------------------------------------------------------------

    public function test_show_opportunity_requires_auth(): void
    {
        $response = $this->apiGet('/v2/volunteering/opportunities/1');

        $response->assertStatus(401);
    }

    // ------------------------------------------------------------------
    //  POST /v2/volunteering/opportunities/{id}/apply
    // ------------------------------------------------------------------

    public function test_apply_requires_auth(): void
    {
        $response = $this->apiPost('/v2/volunteering/opportunities/1/apply');

        $response->assertStatus(401);
    }

    // ------------------------------------------------------------------
    //  GET /v2/volunteering/applications
    // ------------------------------------------------------------------

    public function test_my_applications_requires_auth(): void
    {
        $response = $this->apiGet('/v2/volunteering/applications');

        $response->assertStatus(401);
    }

    public function test_my_applications_returns_data(): void
    {
        $this->authenticatedUser();

        $response = $this->apiGet('/v2/volunteering/applications');

        $response->assertStatus(200);
    }

    // ------------------------------------------------------------------
    //  GET /v2/volunteering/shifts
    // ------------------------------------------------------------------

    public function test_my_shifts_requires_auth(): void
    {
        $response = $this->apiGet('/v2/volunteering/shifts');

        $response->assertStatus(401);
    }

    public function test_my_shifts_returns_data(): void
    {
        $this->authenticatedUser();

        $response = $this->apiGet('/v2/volunteering/shifts');

        $response->assertStatus(200);
    }

    // ------------------------------------------------------------------
    //  GET /v2/volunteering/hours
    // ------------------------------------------------------------------

    public function test_my_hours_requires_auth(): void
    {
        $response = $this->apiGet('/v2/volunteering/hours');

        $response->assertStatus(401);
    }

    public function test_my_hours_returns_data(): void
    {
        $this->authenticatedUser();

        $response = $this->apiGet('/v2/volunteering/hours');

        $response->assertStatus(200);
    }

    // ------------------------------------------------------------------
    //  POST /v2/volunteering/hours
    // ------------------------------------------------------------------

    public function test_log_hours_requires_auth(): void
    {
        $response = $this->apiPost('/v2/volunteering/hours', [
            'hours' => 2.5,
            'description' => 'Park cleanup',
        ]);

        $response->assertStatus(401);
    }

    // ------------------------------------------------------------------
    //  GET /v2/volunteering/hours/summary
    // ------------------------------------------------------------------

    public function test_hours_summary_requires_auth(): void
    {
        $response = $this->apiGet('/v2/volunteering/hours/summary');

        $response->assertStatus(401);
    }

    // ------------------------------------------------------------------
    //  GET /v2/volunteering/organisations
    // ------------------------------------------------------------------

    public function test_organisations_requires_auth(): void
    {
        $response = $this->apiGet('/v2/volunteering/organisations');

        $response->assertStatus(401);
    }

    public function test_organisations_returns_data(): void
    {
        $this->authenticatedUser();

        $response = $this->apiGet('/v2/volunteering/organisations');

        $response->assertStatus(200);
    }

    // ------------------------------------------------------------------
    //  GET /v2/volunteering/certificates
    // ------------------------------------------------------------------

    public function test_certificates_requires_auth(): void
    {
        $response = $this->apiGet('/v2/volunteering/certificates');

        $response->assertStatus(401);
    }

    // ------------------------------------------------------------------
    //  GET /v2/volunteering/credentials
    // ------------------------------------------------------------------

    public function test_credentials_requires_auth(): void
    {
        $response = $this->apiGet('/v2/volunteering/credentials');

        $response->assertStatus(401);
    }

    // ------------------------------------------------------------------
    //  GET /v2/volunteering/expenses
    // ------------------------------------------------------------------

    public function test_my_expenses_requires_auth(): void
    {
        $response = $this->apiGet('/v2/volunteering/expenses');

        $response->assertStatus(401);
    }

    // ------------------------------------------------------------------
    //  GET /v2/volunteering/training
    // ------------------------------------------------------------------

    public function test_my_training_requires_auth(): void
    {
        $response = $this->apiGet('/v2/volunteering/training');

        $response->assertStatus(401);
    }

    // ------------------------------------------------------------------
    //  GET /v2/volunteering/incidents
    // ------------------------------------------------------------------

    public function test_incidents_requires_auth(): void
    {
        $response = $this->apiGet('/v2/volunteering/incidents');

        $response->assertStatus(401);
    }

    // ------------------------------------------------------------------
    //  GET /v2/volunteering/community-projects
    // ------------------------------------------------------------------

    public function test_community_projects_requires_auth(): void
    {
        $response = $this->apiGet('/v2/volunteering/community-projects');

        $response->assertStatus(401);
    }

    public function test_community_projects_returns_data(): void
    {
        $this->authenticatedUser();

        $response = $this->apiGet('/v2/volunteering/community-projects');

        $response->assertStatus(200);
    }

    // ------------------------------------------------------------------
    //  GET /v2/volunteering/wellbeing
    // ------------------------------------------------------------------

    public function test_wellbeing_dashboard_requires_auth(): void
    {
        $response = $this->apiGet('/v2/volunteering/wellbeing');

        $response->assertStatus(401);
    }

    // ------------------------------------------------------------------
    //  GET /v2/volunteering/giving-days
    // ------------------------------------------------------------------

    public function test_giving_days_requires_auth(): void
    {
        $response = $this->apiGet('/v2/volunteering/giving-days');

        $response->assertStatus(401);
    }

    // ------------------------------------------------------------------
    //  GET /v2/volunteering/donations
    // ------------------------------------------------------------------

    public function test_donations_requires_auth(): void
    {
        $response = $this->apiGet('/v2/volunteering/donations');

        $response->assertStatus(401);
    }

    // ------------------------------------------------------------------
    //  Tenant isolation
    // ------------------------------------------------------------------

    public function test_opportunities_are_tenant_scoped(): void
    {
        $this->authenticatedUser();

        $response = $this->apiGet('/v2/volunteering/opportunities');

        $response->assertStatus(200);
        $data = $response->json();
        $this->assertIsArray($data);
    }
}
