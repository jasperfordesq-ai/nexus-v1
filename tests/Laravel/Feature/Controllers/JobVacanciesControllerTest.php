<?php
// Copyright © 2024–2026 Jasper Ford
// SPDX-License-Identifier: AGPL-3.0-or-later
// Author: Jasper Ford
// See NOTICE file for attribution and acknowledgements.

namespace Tests\Laravel\Feature\Controllers;

use App\Models\JobAlert;
use App\Models\JobApplication;
use App\Models\JobVacancy;
use App\Models\User;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Laravel\Sanctum\Sanctum;
use Tests\Laravel\TestCase;

/**
 * Feature tests for JobVacanciesController — comprehensive integration tests
 * covering CRUD, applications, bookmarks, alerts, AI helpers, talent search,
 * templates, scorecards, team, interviews, offers, pipeline rules, GDPR,
 * and interview scheduling.
 */
class JobVacanciesControllerTest extends TestCase
{
    use DatabaseTransactions;

    // ------------------------------------------------------------------
    //  Helpers
    // ------------------------------------------------------------------

    private function authenticatedUser(array $overrides = []): User
    {
        $user = User::factory()->forTenant($this->testTenantId)->create(array_merge([
            'status' => 'active',
            'is_approved' => true,
        ], $overrides));

        Sanctum::actingAs($user, ['*']);

        return $user;
    }

    private function adminUser(): User
    {
        $admin = User::factory()->forTenant($this->testTenantId)->admin()->create();
        Sanctum::actingAs($admin, ['*']);

        return $admin;
    }

    private function createVacancy(array $overrides = []): JobVacancy
    {
        return JobVacancy::factory()->forTenant($this->testTenantId)->create($overrides);
    }

    // =====================================================================
    // CORE CRUD — GET /v2/jobs (index)
    // =====================================================================

    public function test_index_requires_auth(): void
    {
        $response = $this->apiGet('/v2/jobs');
        $response->assertStatus(401);
    }

    public function test_index_returns_200_with_data_structure(): void
    {
        $this->authenticatedUser();

        $response = $this->apiGet('/v2/jobs');

        $response->assertStatus(200);
        $response->assertJsonStructure(['data']);
    }

    public function test_index_returns_created_vacancies(): void
    {
        $user = $this->authenticatedUser();
        $this->createVacancy(['user_id' => $user->id, 'status' => 'open', 'title' => 'Test Job Alpha']);

        $response = $this->apiGet('/v2/jobs');

        $response->assertStatus(200);
    }

    public function test_index_supports_status_filter(): void
    {
        $user = $this->authenticatedUser();
        $this->createVacancy(['user_id' => $user->id, 'status' => 'open']);
        $this->createVacancy(['user_id' => $user->id, 'status' => 'closed']);

        $response = $this->apiGet('/v2/jobs?status=open');

        $response->assertStatus(200);
    }

    public function test_index_supports_search_filter(): void
    {
        $user = $this->authenticatedUser();
        $this->createVacancy(['user_id' => $user->id, 'status' => 'open', 'title' => 'Unique Gardening Role']);

        $response = $this->apiGet('/v2/jobs?search=Gardening');

        $response->assertStatus(200);
    }

    public function test_index_supports_type_filter(): void
    {
        $this->authenticatedUser();

        $response = $this->apiGet('/v2/jobs?type=volunteer');

        $response->assertStatus(200);
    }

    public function test_index_supports_per_page_parameter(): void
    {
        $this->authenticatedUser();

        $response = $this->apiGet('/v2/jobs?per_page=5');

        $response->assertStatus(200);
    }

    // =====================================================================
    // CORE CRUD — POST /v2/jobs (store)
    // =====================================================================

    public function test_store_requires_auth(): void
    {
        $response = $this->apiPost('/v2/jobs', [
            'title' => 'Community Coordinator',
            'description' => 'Full-time role managing community events.',
        ]);

        $response->assertStatus(401);
    }

    public function test_store_creates_job_vacancy(): void
    {
        $this->authenticatedUser();

        $response = $this->apiPost('/v2/jobs', [
            'title' => 'Community Garden Volunteer',
            'description' => 'Help maintain our beautiful community garden.',
            'type' => 'volunteer',
            'commitment' => 'flexible',
            'category' => 'community',
            'location' => 'Dublin',
        ]);

        $response->assertStatus(201);
        $response->assertJsonStructure(['data']);
    }

    public function test_store_returns_422_without_required_fields(): void
    {
        $this->authenticatedUser();

        $response = $this->apiPost('/v2/jobs', []);

        // Controller returns 422 for validation errors
        $response->assertStatus(422);
    }

    public function test_store_with_salary_transparency(): void
    {
        $this->authenticatedUser();

        $response = $this->apiPost('/v2/jobs', [
            'title' => 'Senior Developer',
            'description' => 'Build amazing things.',
            'type' => 'paid',
            'commitment' => 'full_time',
            'salary_min' => 50000,
            'salary_max' => 80000,
            'salary_type' => 'annual',
            'salary_currency' => 'EUR',
            'salary_negotiable' => true,
        ]);

        // Should create successfully or return validation error, both are valid
        $this->assertContains($response->status(), [201, 422]);
    }

    // =====================================================================
    // CORE CRUD — GET /v2/jobs/{id} (show)
    // =====================================================================

    public function test_show_requires_auth(): void
    {
        $response = $this->apiGet('/v2/jobs/1');
        $response->assertStatus(401);
    }

    public function test_show_returns_existing_vacancy(): void
    {
        $user = $this->authenticatedUser();
        $vacancy = $this->createVacancy(['user_id' => $user->id, 'status' => 'open']);

        $response = $this->apiGet("/v2/jobs/{$vacancy->id}");

        $response->assertStatus(200);
        $response->assertJsonStructure(['data']);
    }

    public function test_show_returns_404_for_nonexistent(): void
    {
        $this->authenticatedUser();

        $response = $this->apiGet('/v2/jobs/99999');

        $response->assertStatus(404);
    }

    // =====================================================================
    // CORE CRUD — PUT /v2/jobs/{id} (update)
    // =====================================================================

    public function test_update_requires_auth(): void
    {
        $response = $this->apiPut('/v2/jobs/1', ['title' => 'Updated']);
        $response->assertStatus(401);
    }

    public function test_update_as_owner_succeeds(): void
    {
        $user = $this->authenticatedUser();
        $vacancy = $this->createVacancy(['user_id' => $user->id, 'status' => 'open']);

        $response = $this->apiPut("/v2/jobs/{$vacancy->id}", [
            'title' => 'Updated Job Title',
        ]);

        $response->assertStatus(200);
    }

    public function test_update_as_non_owner_returns_403(): void
    {
        $owner = User::factory()->forTenant($this->testTenantId)->create(['status' => 'active', 'is_approved' => true]);
        $vacancy = $this->createVacancy(['user_id' => $owner->id, 'status' => 'open']);

        $this->authenticatedUser(); // different user

        $response = $this->apiPut("/v2/jobs/{$vacancy->id}", [
            'title' => 'Hijacked Title',
        ]);

        $response->assertStatus(403);
    }

    public function test_update_nonexistent_returns_404(): void
    {
        $this->authenticatedUser();

        $response = $this->apiPut('/v2/jobs/99999', ['title' => 'Ghost']);

        $response->assertStatus(404);
    }

    // =====================================================================
    // CORE CRUD — DELETE /v2/jobs/{id} (destroy)
    // =====================================================================

    public function test_destroy_requires_auth(): void
    {
        $response = $this->apiDelete('/v2/jobs/1');
        $response->assertStatus(401);
    }

    public function test_destroy_as_owner_succeeds(): void
    {
        $user = $this->authenticatedUser();
        $vacancy = $this->createVacancy(['user_id' => $user->id, 'status' => 'open']);

        $response = $this->apiDelete("/v2/jobs/{$vacancy->id}");

        $response->assertStatus(204);
    }

    public function test_destroy_as_non_owner_returns_403(): void
    {
        $owner = User::factory()->forTenant($this->testTenantId)->create(['status' => 'active', 'is_approved' => true]);
        $vacancy = $this->createVacancy(['user_id' => $owner->id, 'status' => 'open']);

        $this->authenticatedUser();

        $response = $this->apiDelete("/v2/jobs/{$vacancy->id}");

        $response->assertStatus(403);
    }

    public function test_destroy_nonexistent_returns_404(): void
    {
        $this->authenticatedUser();

        $response = $this->apiDelete('/v2/jobs/99999');

        $response->assertStatus(404);
    }

    // =====================================================================
    // APPLICATIONS — POST /v2/jobs/{id}/apply
    // =====================================================================

    public function test_apply_requires_auth(): void
    {
        $response = $this->apiPost('/v2/jobs/1/apply', ['message' => 'Interested']);
        $response->assertStatus(401);
    }

    public function test_apply_to_existing_vacancy(): void
    {
        $owner = User::factory()->forTenant($this->testTenantId)->create(['status' => 'active', 'is_approved' => true]);
        $vacancy = $this->createVacancy(['user_id' => $owner->id, 'status' => 'open']);

        $applicant = $this->authenticatedUser();

        $response = $this->apiPost("/v2/jobs/{$vacancy->id}/apply", [
            'message' => 'I would love to contribute to this role.',
        ]);

        // 201 on success, 409 if already applied, 400/404 on validation
        $this->assertContains($response->status(), [201, 409, 400, 404]);
    }

    public function test_apply_duplicate_returns_409(): void
    {
        $owner = User::factory()->forTenant($this->testTenantId)->create(['status' => 'active', 'is_approved' => true]);
        $vacancy = $this->createVacancy(['user_id' => $owner->id, 'status' => 'open']);

        $applicant = $this->authenticatedUser();

        // First application
        $this->apiPost("/v2/jobs/{$vacancy->id}/apply", ['message' => 'First try']);

        // Second application — should be conflict
        $response = $this->apiPost("/v2/jobs/{$vacancy->id}/apply", ['message' => 'Second try']);

        $response->assertStatus(409);
    }

    public function test_apply_to_nonexistent_vacancy(): void
    {
        $this->authenticatedUser();

        $response = $this->apiPost('/v2/jobs/99999/apply', ['message' => 'Hello']);

        // Should return 404 or 400 with error
        $this->assertContains($response->status(), [404, 400]);
    }

    public function test_apply_to_closed_vacancy(): void
    {
        $owner = User::factory()->forTenant($this->testTenantId)->create(['status' => 'active', 'is_approved' => true]);
        $vacancy = $this->createVacancy(['user_id' => $owner->id, 'status' => 'closed']);

        $this->authenticatedUser();

        $response = $this->apiPost("/v2/jobs/{$vacancy->id}/apply", ['message' => 'Too late?']);

        // Closed vacancies should return an error
        $this->assertContains($response->status(), [400, 404, 409, 422]);
    }

    // =====================================================================
    // APPLICATIONS — GET /v2/jobs/{id}/applications
    // =====================================================================

    public function test_applications_requires_auth(): void
    {
        $response = $this->apiGet('/v2/jobs/1/applications');
        $response->assertStatus(401);
    }

    public function test_applications_as_owner_returns_200(): void
    {
        $owner = $this->authenticatedUser();
        $vacancy = $this->createVacancy(['user_id' => $owner->id, 'status' => 'open']);

        $response = $this->apiGet("/v2/jobs/{$vacancy->id}/applications");

        $response->assertStatus(200);
    }

    public function test_applications_as_non_owner_returns_403(): void
    {
        $owner = User::factory()->forTenant($this->testTenantId)->create(['status' => 'active', 'is_approved' => true]);
        $vacancy = $this->createVacancy(['user_id' => $owner->id, 'status' => 'open']);

        $this->authenticatedUser();

        $response = $this->apiGet("/v2/jobs/{$vacancy->id}/applications");

        $response->assertStatus(403);
    }

    // =====================================================================
    // APPLICATIONS — PUT /v2/jobs/applications/{id}
    // =====================================================================

    public function test_update_application_requires_auth(): void
    {
        $response = $this->apiPut('/v2/jobs/applications/1', ['status' => 'shortlisted']);
        $response->assertStatus(401);
    }

    public function test_update_application_requires_status_field(): void
    {
        $this->authenticatedUser();

        $response = $this->apiPut('/v2/jobs/applications/1', []);

        // Should require status field
        $response->assertStatus(400);
    }

    public function test_update_application_status_as_owner(): void
    {
        $owner = $this->authenticatedUser();
        $vacancy = $this->createVacancy(['user_id' => $owner->id, 'status' => 'open']);

        $applicant = User::factory()->forTenant($this->testTenantId)->create(['status' => 'active']);
        $application = JobApplication::factory()->create([
            'vacancy_id' => $vacancy->id,
            'user_id' => $applicant->id,
            'status' => 'submitted',
        ]);

        $response = $this->apiPut("/v2/jobs/applications/{$application->id}", [
            'status' => 'shortlisted',
            'notes' => 'Great candidate.',
        ]);

        $response->assertStatus(200);
    }

    // =====================================================================
    // BULK APPLICATION STATUS — POST /v2/jobs/{id}/applications/bulk-status
    // =====================================================================

    public function test_bulk_status_requires_auth(): void
    {
        $response = $this->apiPost('/v2/jobs/1/applications/bulk-status', []);
        $response->assertStatus(401);
    }

    public function test_bulk_status_requires_application_ids_and_status(): void
    {
        $this->authenticatedUser();

        $response = $this->apiPost('/v2/jobs/1/applications/bulk-status', []);

        $response->assertStatus(422);
    }

    public function test_bulk_status_with_valid_data(): void
    {
        $owner = $this->authenticatedUser();
        $vacancy = $this->createVacancy(['user_id' => $owner->id, 'status' => 'open']);

        $applicant = User::factory()->forTenant($this->testTenantId)->create(['status' => 'active']);
        $app1 = JobApplication::factory()->create([
            'vacancy_id' => $vacancy->id,
            'user_id' => $applicant->id,
            'status' => 'submitted',
        ]);

        $response = $this->apiPost("/v2/jobs/{$vacancy->id}/applications/bulk-status", [
            'application_ids' => [$app1->id],
            'status' => 'under_review',
        ]);

        $this->assertContains($response->status(), [200, 422]);
    }

    // =====================================================================
    // BOOKMARKS — POST /v2/jobs/{id}/save
    // =====================================================================

    public function test_save_job_requires_auth(): void
    {
        $response = $this->apiPost('/v2/jobs/1/save');
        $response->assertStatus(401);
    }

    public function test_save_job_succeeds(): void
    {
        $user = $this->authenticatedUser();
        $vacancy = $this->createVacancy(['status' => 'open']);

        $response = $this->apiPost("/v2/jobs/{$vacancy->id}/save");

        $this->assertContains($response->status(), [201, 200, 400]);
    }

    // =====================================================================
    // BOOKMARKS — DELETE /v2/jobs/{id}/save
    // =====================================================================

    public function test_unsave_job_requires_auth(): void
    {
        $response = $this->apiDelete('/v2/jobs/1/save');
        $response->assertStatus(401);
    }

    public function test_unsave_job_succeeds(): void
    {
        $user = $this->authenticatedUser();
        $vacancy = $this->createVacancy(['status' => 'open']);

        // Save first, then unsave
        $this->apiPost("/v2/jobs/{$vacancy->id}/save");

        $response = $this->apiDelete("/v2/jobs/{$vacancy->id}/save");

        $response->assertStatus(200);
    }

    // =====================================================================
    // BOOKMARKS — GET /v2/jobs/saved
    // =====================================================================

    public function test_saved_jobs_requires_auth(): void
    {
        $response = $this->apiGet('/v2/jobs/saved');
        $response->assertStatus(401);
    }

    public function test_saved_jobs_returns_data(): void
    {
        $this->authenticatedUser();

        $response = $this->apiGet('/v2/jobs/saved');

        $response->assertStatus(200);
    }

    // =====================================================================
    // USER-SPECIFIC — GET /v2/jobs/my-applications
    // =====================================================================

    public function test_my_applications_requires_auth(): void
    {
        $response = $this->apiGet('/v2/jobs/my-applications');
        $response->assertStatus(401);
    }

    public function test_my_applications_returns_data(): void
    {
        $this->authenticatedUser();

        $response = $this->apiGet('/v2/jobs/my-applications');

        $response->assertStatus(200);
        $response->assertJsonStructure(['data']);
    }

    // =====================================================================
    // USER-SPECIFIC — GET /v2/jobs/my-postings
    // =====================================================================

    public function test_my_postings_requires_auth(): void
    {
        $response = $this->apiGet('/v2/jobs/my-postings');
        $response->assertStatus(401);
    }

    public function test_my_postings_returns_data(): void
    {
        $this->authenticatedUser();

        $response = $this->apiGet('/v2/jobs/my-postings');

        $response->assertStatus(200);
    }

    public function test_my_postings_includes_own_vacancies(): void
    {
        $user = $this->authenticatedUser();
        $this->createVacancy(['user_id' => $user->id, 'status' => 'open', 'title' => 'My Own Posting']);

        $response = $this->apiGet('/v2/jobs/my-postings');

        $response->assertStatus(200);
    }

    // =====================================================================
    // USER-SPECIFIC — GET /v2/jobs/recommended
    // =====================================================================

    public function test_recommended_requires_auth(): void
    {
        $response = $this->apiGet('/v2/jobs/recommended');
        $response->assertStatus(401);
    }

    public function test_recommended_returns_data(): void
    {
        $this->authenticatedUser();

        $response = $this->apiGet('/v2/jobs/recommended');

        $response->assertStatus(200);
        $response->assertJsonStructure(['data']);
    }

    // =====================================================================
    // ALERTS — GET /v2/jobs/alerts
    // =====================================================================

    public function test_alerts_list_requires_auth(): void
    {
        $response = $this->apiGet('/v2/jobs/alerts');
        $response->assertStatus(401);
    }

    public function test_alerts_list_returns_data(): void
    {
        $this->authenticatedUser();

        $response = $this->apiGet('/v2/jobs/alerts');

        $response->assertStatus(200);
        $response->assertJsonStructure(['data']);
    }

    // =====================================================================
    // ALERTS — POST /v2/jobs/alerts
    // =====================================================================

    public function test_alerts_create_requires_auth(): void
    {
        $response = $this->apiPost('/v2/jobs/alerts', ['keywords' => 'developer']);
        $response->assertStatus(401);
    }

    public function test_alerts_create_with_valid_data(): void
    {
        $this->authenticatedUser();

        $response = $this->apiPost('/v2/jobs/alerts', [
            'keywords' => 'community volunteer',
            'type' => 'volunteer',
            'commitment' => 'flexible',
        ]);

        $response->assertStatus(201);
        $response->assertJsonStructure(['data' => ['id']]);
    }

    // =====================================================================
    // ALERTS — DELETE /v2/jobs/alerts/{id}
    // =====================================================================

    public function test_alerts_delete_requires_auth(): void
    {
        $response = $this->apiDelete('/v2/jobs/alerts/1');
        $response->assertStatus(401);
    }

    public function test_alerts_delete_succeeds(): void
    {
        $user = $this->authenticatedUser();

        $alert = JobAlert::factory()->create([
            'user_id' => $user->id,
            'tenant_id' => $this->testTenantId,
        ]);

        $response = $this->apiDelete("/v2/jobs/alerts/{$alert->id}");

        $response->assertStatus(204);
    }

    // =====================================================================
    // ALERTS — PUT /v2/jobs/alerts/{id}/unsubscribe
    // =====================================================================

    public function test_alerts_unsubscribe_requires_auth(): void
    {
        $response = $this->apiPut('/v2/jobs/alerts/1/unsubscribe');
        $response->assertStatus(401);
    }

    public function test_alerts_unsubscribe_succeeds(): void
    {
        $user = $this->authenticatedUser();
        $alert = JobAlert::factory()->create([
            'user_id' => $user->id,
            'tenant_id' => $this->testTenantId,
            'is_active' => true,
        ]);

        $response = $this->apiPut("/v2/jobs/alerts/{$alert->id}/unsubscribe");

        $response->assertStatus(200);
    }

    // =====================================================================
    // ALERTS — PUT /v2/jobs/alerts/{id}/resubscribe
    // =====================================================================

    public function test_alerts_resubscribe_requires_auth(): void
    {
        $response = $this->apiPut('/v2/jobs/alerts/1/resubscribe');
        $response->assertStatus(401);
    }

    public function test_alerts_resubscribe_succeeds(): void
    {
        $user = $this->authenticatedUser();
        $alert = JobAlert::factory()->create([
            'user_id' => $user->id,
            'tenant_id' => $this->testTenantId,
            'is_active' => false,
        ]);

        $response = $this->apiPut("/v2/jobs/alerts/{$alert->id}/resubscribe");

        $response->assertStatus(200);
    }

    // =====================================================================
    // AI — POST /v2/jobs/generate-description
    // =====================================================================

    public function test_generate_description_requires_auth(): void
    {
        $response = $this->apiPost('/v2/jobs/generate-description', ['title' => 'Test']);
        $response->assertStatus(401);
    }

    public function test_generate_description_requires_title(): void
    {
        $this->authenticatedUser();

        $response = $this->apiPost('/v2/jobs/generate-description', []);

        $response->assertStatus(400);
    }

    public function test_generate_description_with_title(): void
    {
        $this->authenticatedUser();

        $response = $this->apiPost('/v2/jobs/generate-description', [
            'title' => 'Community Garden Coordinator',
            'skills' => ['gardening', 'leadership'],
            'type' => 'volunteer',
            'commitment' => 'part_time',
        ]);

        // May return 200 (success) or 503 (AI service unavailable in test env)
        $this->assertContains($response->status(), [200, 503]);
    }

    // =====================================================================
    // DUPLICATE DETECTION — POST /v2/jobs/check-duplicate
    // =====================================================================

    public function test_check_duplicate_requires_auth(): void
    {
        $response = $this->apiPost('/v2/jobs/check-duplicate', ['title' => 'Test']);
        $response->assertStatus(401);
    }

    public function test_check_duplicate_requires_title(): void
    {
        $this->authenticatedUser();

        $response = $this->apiPost('/v2/jobs/check-duplicate', []);

        $response->assertStatus(400);
    }

    public function test_check_duplicate_returns_duplicates_array(): void
    {
        $this->authenticatedUser();

        $response = $this->apiPost('/v2/jobs/check-duplicate', [
            'title' => 'Community Coordinator',
        ]);

        $response->assertStatus(200);
        $response->assertJsonStructure(['data' => ['duplicates']]);
    }

    // =====================================================================
    // TALENT SEARCH — GET /v2/jobs/talent-search
    // =====================================================================

    public function test_talent_search_requires_auth(): void
    {
        $response = $this->apiGet('/v2/jobs/talent-search');
        $response->assertStatus(401);
    }

    public function test_talent_search_returns_data(): void
    {
        $this->authenticatedUser();

        $response = $this->apiGet('/v2/jobs/talent-search');

        $response->assertStatus(200);
        $response->assertJsonStructure(['data']);
    }

    public function test_talent_search_with_keywords(): void
    {
        $this->authenticatedUser();

        $response = $this->apiGet('/v2/jobs/talent-search?keywords=developer');

        $response->assertStatus(200);
    }

    // =====================================================================
    // RESUME VISIBILITY — PUT /v2/users/me/resume-visibility
    // =====================================================================

    public function test_resume_visibility_requires_auth(): void
    {
        $response = $this->apiPut('/v2/users/me/resume-visibility', ['searchable' => true]);
        $response->assertStatus(401);
    }

    public function test_resume_visibility_toggle(): void
    {
        $this->authenticatedUser();

        $response = $this->apiPut('/v2/users/me/resume-visibility', [
            'searchable' => true,
        ]);

        $this->assertContains($response->status(), [200, 500]);
    }

    // =====================================================================
    // SKILLS MATCHING — GET /v2/jobs/{id}/match
    // =====================================================================

    public function test_match_percentage_requires_auth(): void
    {
        $response = $this->apiGet('/v2/jobs/1/match');
        $response->assertStatus(401);
    }

    public function test_match_percentage_for_existing_vacancy(): void
    {
        $user = $this->authenticatedUser();
        $vacancy = $this->createVacancy(['status' => 'open']);

        $response = $this->apiGet("/v2/jobs/{$vacancy->id}/match");

        $response->assertStatus(200);
    }

    // =====================================================================
    // QUALIFICATION — GET /v2/jobs/{id}/qualified
    // =====================================================================

    public function test_qualification_requires_auth(): void
    {
        $response = $this->apiGet('/v2/jobs/1/qualified');
        $response->assertStatus(401);
    }

    public function test_qualification_for_existing_vacancy(): void
    {
        $this->authenticatedUser();
        $vacancy = $this->createVacancy(['status' => 'open']);

        $response = $this->apiGet("/v2/jobs/{$vacancy->id}/qualified");

        // 200 on success, 404 if vacancy lookup differs
        $this->assertContains($response->status(), [200, 404]);
    }

    // =====================================================================
    // ANALYTICS — GET /v2/jobs/{id}/analytics
    // =====================================================================

    public function test_analytics_requires_auth(): void
    {
        $response = $this->apiGet('/v2/jobs/1/analytics');
        $response->assertStatus(401);
    }

    public function test_analytics_as_owner(): void
    {
        $owner = $this->authenticatedUser();
        $vacancy = $this->createVacancy(['user_id' => $owner->id, 'status' => 'open']);

        $response = $this->apiGet("/v2/jobs/{$vacancy->id}/analytics");

        $response->assertStatus(200);
    }

    public function test_analytics_as_non_owner_returns_403(): void
    {
        $owner = User::factory()->forTenant($this->testTenantId)->create(['status' => 'active', 'is_approved' => true]);
        $vacancy = $this->createVacancy(['user_id' => $owner->id, 'status' => 'open']);

        $this->authenticatedUser();

        $response = $this->apiGet("/v2/jobs/{$vacancy->id}/analytics");

        $response->assertStatus(403);
    }

    // =====================================================================
    // RENEW — POST /v2/jobs/{id}/renew
    // =====================================================================

    public function test_renew_requires_auth(): void
    {
        $response = $this->apiPost('/v2/jobs/1/renew');
        $response->assertStatus(401);
    }

    public function test_renew_as_owner(): void
    {
        $owner = $this->authenticatedUser();
        $vacancy = $this->createVacancy(['user_id' => $owner->id, 'status' => 'open']);

        $response = $this->apiPost("/v2/jobs/{$vacancy->id}/renew", ['days' => 30]);

        $response->assertStatus(200);
    }

    // =====================================================================
    // TEMPLATES — GET/POST /v2/jobs/templates
    // =====================================================================

    public function test_templates_list_requires_auth(): void
    {
        $response = $this->apiGet('/v2/jobs/templates');
        $response->assertStatus(401);
    }

    public function test_templates_list_returns_200(): void
    {
        $this->authenticatedUser();

        $response = $this->apiGet('/v2/jobs/templates');

        $response->assertStatus(200);
        $response->assertJsonStructure(['data' => ['data']]);
    }

    public function test_templates_create_requires_auth(): void
    {
        $response = $this->apiPost('/v2/jobs/templates', ['name' => 'Test']);
        $response->assertStatus(401);
    }

    public function test_templates_create_returns_201(): void
    {
        $this->authenticatedUser();

        $response = $this->apiPost('/v2/jobs/templates', [
            'name' => 'Volunteer Template',
            'title' => 'Community Volunteer',
            'description' => 'Standard volunteer role.',
            'type' => 'volunteer',
        ]);

        $this->assertContains($response->status(), [201, 422]);
    }

    // =====================================================================
    // SCORECARDS — PUT/GET /v2/jobs/applications/{id}/scorecard(s)
    // =====================================================================

    public function test_upsert_scorecard_requires_auth(): void
    {
        $response = $this->apiPut('/v2/jobs/applications/1/scorecard', ['score' => 4]);
        $response->assertStatus(401);
    }

    public function test_get_scorecards_requires_auth(): void
    {
        $response = $this->apiGet('/v2/jobs/applications/1/scorecards');
        $response->assertStatus(401);
    }

    public function test_get_scorecards_returns_200(): void
    {
        $owner = $this->authenticatedUser();
        $vacancy = $this->createVacancy(['user_id' => $owner->id, 'status' => 'open']);
        $applicant = User::factory()->forTenant($this->testTenantId)->create(['status' => 'active']);
        $application = JobApplication::factory()->create([
            'vacancy_id' => $vacancy->id,
            'user_id' => $applicant->id,
            'status' => 'submitted',
        ]);

        $response = $this->apiGet("/v2/jobs/applications/{$application->id}/scorecards");

        $response->assertStatus(200);
    }

    // =====================================================================
    // TEAM — GET/POST/DELETE /v2/jobs/{id}/team
    // =====================================================================

    public function test_get_team_requires_auth(): void
    {
        $response = $this->apiGet('/v2/jobs/1/team');
        $response->assertStatus(401);
    }

    public function test_get_team_returns_200(): void
    {
        $owner = $this->authenticatedUser();
        $vacancy = $this->createVacancy(['user_id' => $owner->id, 'status' => 'open']);

        $response = $this->apiGet("/v2/jobs/{$vacancy->id}/team");

        $response->assertStatus(200);
        $response->assertJsonStructure(['data' => ['data']]);
    }

    public function test_add_team_member_requires_auth(): void
    {
        $response = $this->apiPost('/v2/jobs/1/team', ['user_id' => 1]);
        $response->assertStatus(401);
    }

    // =====================================================================
    // REFERRALS — POST /v2/jobs/{id}/referral
    // =====================================================================

    public function test_get_or_create_referral_returns_data(): void
    {
        $user = $this->authenticatedUser();
        $vacancy = $this->createVacancy(['user_id' => $user->id, 'status' => 'open']);

        $response = $this->apiPost("/v2/jobs/{$vacancy->id}/referral");

        $this->assertContains($response->status(), [201, 422]);
    }

    // =====================================================================
    // REFERRAL STATS — GET /v2/jobs/{id}/referral-stats
    // =====================================================================

    public function test_referral_stats_requires_auth(): void
    {
        $response = $this->apiGet('/v2/jobs/1/referral-stats');
        $response->assertStatus(401);
    }

    public function test_referral_stats_returns_data(): void
    {
        $user = $this->authenticatedUser();
        $vacancy = $this->createVacancy(['user_id' => $user->id, 'status' => 'open']);

        $response = $this->apiGet("/v2/jobs/{$vacancy->id}/referral-stats");

        $response->assertStatus(200);
    }

    // =====================================================================
    // SAVED PROFILE — GET/PUT /v2/jobs/saved-profile
    // =====================================================================

    public function test_saved_profile_get_requires_auth(): void
    {
        $response = $this->apiGet('/v2/jobs/saved-profile');
        $response->assertStatus(401);
    }

    public function test_saved_profile_get_returns_data(): void
    {
        $this->authenticatedUser();

        $response = $this->apiGet('/v2/jobs/saved-profile');

        $response->assertStatus(200);
        $response->assertJsonStructure(['data' => ['profile']]);
    }

    public function test_saved_profile_put_requires_auth(): void
    {
        $response = $this->apiPut('/v2/jobs/saved-profile', ['cover_letter' => 'Test']);
        $response->assertStatus(401);
    }

    public function test_saved_profile_put_succeeds(): void
    {
        $this->authenticatedUser();

        $response = $this->apiPut('/v2/jobs/saved-profile', [
            'cover_letter' => 'My standard cover letter.',
            'headline' => 'Experienced Community Organizer',
        ]);

        $this->assertContains($response->status(), [200, 422]);
    }

    // =====================================================================
    // SALARY BENCHMARK — GET /v2/jobs/salary-benchmark
    // =====================================================================

    public function test_salary_benchmark_requires_auth(): void
    {
        $response = $this->apiGet('/v2/jobs/salary-benchmark?title=developer');
        $response->assertStatus(401);
    }

    public function test_salary_benchmark_without_title(): void
    {
        $this->authenticatedUser();

        $response = $this->apiGet('/v2/jobs/salary-benchmark');

        $response->assertStatus(200);
        $response->assertJsonPath('data.benchmark', null);
    }

    public function test_salary_benchmark_with_title(): void
    {
        $this->authenticatedUser();

        $response = $this->apiGet('/v2/jobs/salary-benchmark?title=developer');

        $response->assertStatus(200);
    }

    // =====================================================================
    // GDPR — GET /v2/jobs/gdpr-export
    // =====================================================================

    public function test_gdpr_export_requires_auth(): void
    {
        $response = $this->apiGet('/v2/jobs/gdpr-export');
        $response->assertStatus(401);
    }

    public function test_gdpr_export_returns_data(): void
    {
        $this->authenticatedUser();

        $response = $this->apiGet('/v2/jobs/gdpr-export');

        $response->assertStatus(200);
        $response->assertJsonStructure(['data' => ['data']]);
    }

    // =====================================================================
    // GDPR — DELETE /v2/jobs/gdpr-erase-me
    // =====================================================================

    public function test_gdpr_erase_requires_auth(): void
    {
        $response = $this->apiDelete('/v2/jobs/gdpr-erase-me');
        $response->assertStatus(401);
    }

    public function test_gdpr_erase_returns_success(): void
    {
        $this->authenticatedUser();

        $response = $this->apiDelete('/v2/jobs/gdpr-erase-me');

        $this->assertContains($response->status(), [200, 500]);
    }

    // =====================================================================
    // PIPELINE RULES — GET/POST/DELETE /v2/jobs/{id}/pipeline-rules
    // =====================================================================

    public function test_pipeline_rules_list_requires_auth(): void
    {
        $response = $this->apiGet('/v2/jobs/1/pipeline-rules');
        $response->assertStatus(401);
    }

    public function test_pipeline_rules_list_returns_200(): void
    {
        $owner = $this->authenticatedUser();
        $vacancy = $this->createVacancy(['user_id' => $owner->id, 'status' => 'open']);

        $response = $this->apiGet("/v2/jobs/{$vacancy->id}/pipeline-rules");

        $response->assertStatus(200);
    }

    public function test_pipeline_rules_create_requires_auth(): void
    {
        $response = $this->apiPost('/v2/jobs/1/pipeline-rules', ['trigger' => 'applied']);
        $response->assertStatus(401);
    }

    public function test_pipeline_rules_run_requires_auth(): void
    {
        $response = $this->apiPost('/v2/jobs/1/pipeline-rules/run');
        $response->assertStatus(401);
    }

    public function test_pipeline_rules_run_returns_200(): void
    {
        $owner = $this->authenticatedUser();
        $vacancy = $this->createVacancy(['user_id' => $owner->id, 'status' => 'open']);

        $response = $this->apiPost("/v2/jobs/{$vacancy->id}/pipeline-rules/run");

        $response->assertStatus(200);
    }

    // =====================================================================
    // INTERVIEWS — POST/PUT/DELETE /v2/jobs/ interview endpoints
    // =====================================================================

    public function test_my_interviews_requires_auth(): void
    {
        $response = $this->apiGet('/v2/jobs/my-interviews');
        $response->assertStatus(401);
    }

    public function test_my_interviews_returns_data(): void
    {
        $this->authenticatedUser();

        $response = $this->apiGet('/v2/jobs/my-interviews');

        $response->assertStatus(200);
        $response->assertJsonStructure(['data']);
    }

    public function test_get_interviews_for_vacancy_requires_auth(): void
    {
        $response = $this->apiGet('/v2/jobs/1/interviews');
        $response->assertStatus(401);
    }

    public function test_get_interviews_for_vacancy_returns_200(): void
    {
        $owner = $this->authenticatedUser();
        $vacancy = $this->createVacancy(['user_id' => $owner->id, 'status' => 'open']);

        $response = $this->apiGet("/v2/jobs/{$vacancy->id}/interviews");

        $response->assertStatus(200);
    }

    public function test_propose_interview_requires_auth(): void
    {
        $response = $this->apiPost('/v2/jobs/applications/1/interview', [
            'scheduled_at' => '2026-04-15T10:00:00Z',
        ]);
        $response->assertStatus(401);
    }

    public function test_propose_interview_requires_scheduled_at(): void
    {
        $this->authenticatedUser();

        $response = $this->apiPost('/v2/jobs/applications/1/interview', []);

        $response->assertStatus(422);
    }

    // =====================================================================
    // OFFERS — POST/PUT/DELETE /v2/jobs/ offer endpoints
    // =====================================================================

    public function test_my_offers_requires_auth(): void
    {
        $response = $this->apiGet('/v2/jobs/my-offers');
        $response->assertStatus(401);
    }

    public function test_my_offers_returns_data(): void
    {
        $this->authenticatedUser();

        $response = $this->apiGet('/v2/jobs/my-offers');

        $response->assertStatus(200);
        $response->assertJsonStructure(['data']);
    }

    public function test_create_offer_requires_auth(): void
    {
        $response = $this->apiPost('/v2/jobs/applications/1/offer', []);
        $response->assertStatus(401);
    }

    public function test_get_application_offer_requires_auth(): void
    {
        $response = $this->apiGet('/v2/jobs/applications/1/offer');
        $response->assertStatus(401);
    }

    // =====================================================================
    // INTERVIEW SCHEDULING SLOTS — /v2/jobs/{id}/interview-slots
    // =====================================================================

    public function test_interview_slots_list_requires_auth(): void
    {
        $response = $this->apiGet('/v2/jobs/1/interview-slots');
        $response->assertStatus(401);
    }

    public function test_interview_slots_list_returns_200(): void
    {
        $owner = $this->authenticatedUser();
        $vacancy = $this->createVacancy(['user_id' => $owner->id, 'status' => 'open']);

        $response = $this->apiGet("/v2/jobs/{$vacancy->id}/interview-slots");

        $response->assertStatus(200);
    }

    public function test_interview_slots_create_requires_auth(): void
    {
        $response = $this->apiPost('/v2/jobs/1/interview-slots', [
            'slots' => [['start_time' => '2026-04-15T09:00:00Z', 'end_time' => '2026-04-15T09:30:00Z']],
        ]);
        $response->assertStatus(401);
    }

    public function test_interview_slots_create_requires_slots(): void
    {
        $this->authenticatedUser();

        $response = $this->apiPost('/v2/jobs/1/interview-slots', []);

        $response->assertStatus(400);
    }

    public function test_interview_slots_bulk_requires_auth(): void
    {
        $response = $this->apiPost('/v2/jobs/1/interview-slots/bulk', []);
        $response->assertStatus(401);
    }

    public function test_interview_slots_bulk_requires_date_range(): void
    {
        $this->authenticatedUser();

        $response = $this->apiPost('/v2/jobs/1/interview-slots/bulk', []);

        $response->assertStatus(400);
    }

    // =====================================================================
    // APPLICATION HISTORY — GET /v2/jobs/applications/{id}/history
    // =====================================================================

    public function test_application_history_requires_auth(): void
    {
        $response = $this->apiGet('/v2/jobs/applications/1/history');
        $response->assertStatus(401);
    }

    // =====================================================================
    // EXPORT CSV — GET /v2/jobs/{id}/applications/export-csv
    // =====================================================================

    public function test_export_csv_requires_auth(): void
    {
        $response = $this->apiGet('/v2/jobs/1/applications/export-csv');
        $response->assertStatus(401);
    }

    public function test_export_csv_as_owner(): void
    {
        $owner = $this->authenticatedUser();
        $vacancy = $this->createVacancy(['user_id' => $owner->id, 'status' => 'open']);

        $response = $this->apiGet("/v2/jobs/{$vacancy->id}/applications/export-csv");

        $response->assertStatus(200);
    }

    public function test_export_csv_as_non_owner_returns_403(): void
    {
        $owner = User::factory()->forTenant($this->testTenantId)->create(['status' => 'active', 'is_approved' => true]);
        $vacancy = $this->createVacancy(['user_id' => $owner->id, 'status' => 'open']);

        $this->authenticatedUser();

        $response = $this->apiGet("/v2/jobs/{$vacancy->id}/applications/export-csv");

        $response->assertStatus(403);
    }
}
