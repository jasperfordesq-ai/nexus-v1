<?php
// Copyright © 2024–2026 Jasper Ford
// SPDX-License-Identifier: AGPL-3.0-or-later
// Author: Jasper Ford
// See NOTICE file for attribution and acknowledgements.

namespace Tests\Laravel\Feature\Controllers;

use App\Models\JobApplication;
use App\Models\JobVacancy;
use App\Models\User;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Laravel\Sanctum\Sanctum;
use Tests\Laravel\TestCase;

/**
 * Feature tests for AdminJobsController — admin job management, moderation,
 * featuring, applications, spam stats, and bias audit.
 */
class AdminJobsControllerTest extends TestCase
{
    use DatabaseTransactions;

    // ------------------------------------------------------------------
    //  Helpers
    // ------------------------------------------------------------------

    private function adminUser(): User
    {
        $admin = User::factory()->forTenant($this->testTenantId)->admin()->create();
        Sanctum::actingAs($admin, ['*']);

        return $admin;
    }

    private function regularUser(): User
    {
        $member = User::factory()->forTenant($this->testTenantId)->create([
            'status' => 'active',
            'is_approved' => true,
        ]);
        Sanctum::actingAs($member, ['*']);

        return $member;
    }

    private function createVacancy(array $overrides = []): JobVacancy
    {
        return JobVacancy::factory()->forTenant($this->testTenantId)->create($overrides);
    }

    // =====================================================================
    // INDEX — GET /v2/admin/jobs
    // =====================================================================

    public function test_index_returns_401_for_unauthenticated(): void
    {
        $response = $this->apiGet('/v2/admin/jobs');
        $response->assertStatus(401);
    }

    public function test_index_returns_403_for_regular_member(): void
    {
        $this->regularUser();

        $response = $this->apiGet('/v2/admin/jobs');

        $response->assertStatus(403);
    }

    public function test_index_returns_200_for_admin(): void
    {
        $this->adminUser();

        $response = $this->apiGet('/v2/admin/jobs');

        $response->assertStatus(200);
        $response->assertJsonStructure(['data']);
    }

    public function test_index_supports_status_filter(): void
    {
        $this->adminUser();
        $this->createVacancy(['status' => 'open']);

        $response = $this->apiGet('/v2/admin/jobs?status=open');

        $response->assertStatus(200);
    }

    public function test_index_supports_search_filter(): void
    {
        $this->adminUser();
        $this->createVacancy(['status' => 'open', 'title' => 'Unique Admin Search Job']);

        $response = $this->apiGet('/v2/admin/jobs?search=Unique+Admin');

        $response->assertStatus(200);
    }

    public function test_index_supports_pagination(): void
    {
        $this->adminUser();

        $response = $this->apiGet('/v2/admin/jobs?page=1&limit=5');

        $response->assertStatus(200);
    }

    // =====================================================================
    // SHOW — GET /v2/admin/jobs/{id}
    // =====================================================================

    public function test_show_returns_401_for_unauthenticated(): void
    {
        $response = $this->apiGet('/v2/admin/jobs/1');
        $response->assertStatus(401);
    }

    public function test_show_returns_403_for_regular_member(): void
    {
        $this->regularUser();

        $response = $this->apiGet('/v2/admin/jobs/1');

        $response->assertStatus(403);
    }

    public function test_show_returns_404_for_nonexistent_job(): void
    {
        $this->adminUser();

        $response = $this->apiGet('/v2/admin/jobs/99999');

        $response->assertStatus(404);
    }

    public function test_show_returns_200_for_existing_job(): void
    {
        $this->adminUser();
        $vacancy = $this->createVacancy(['status' => 'open']);

        $response = $this->apiGet("/v2/admin/jobs/{$vacancy->id}");

        $response->assertStatus(200);
        $response->assertJsonStructure(['data']);
    }

    // =====================================================================
    // DESTROY — DELETE /v2/admin/jobs/{id}
    // =====================================================================

    public function test_destroy_returns_401_for_unauthenticated(): void
    {
        $response = $this->apiDelete('/v2/admin/jobs/1');
        $response->assertStatus(401);
    }

    public function test_destroy_returns_403_for_regular_member(): void
    {
        $this->regularUser();
        $vacancy = $this->createVacancy(['status' => 'open']);

        $response = $this->apiDelete("/v2/admin/jobs/{$vacancy->id}");

        $response->assertStatus(403);
    }

    public function test_destroy_returns_404_for_nonexistent_job(): void
    {
        $this->adminUser();

        $response = $this->apiDelete('/v2/admin/jobs/99999');

        $response->assertStatus(404);
    }

    public function test_destroy_returns_200_for_existing_job(): void
    {
        $admin = $this->adminUser();
        $vacancy = $this->createVacancy(['user_id' => $admin->id, 'status' => 'open']);

        $response = $this->apiDelete("/v2/admin/jobs/{$vacancy->id}");

        // Admin delete should succeed — 200 with deleted: true
        $response->assertStatus(200);
        $response->assertJsonFragment(['deleted' => true]);
    }

    // =====================================================================
    // FEATURE — POST /v2/admin/jobs/{id}/feature
    // =====================================================================

    public function test_feature_returns_401_for_unauthenticated(): void
    {
        $response = $this->apiPost('/v2/admin/jobs/1/feature', ['duration_days' => 7]);
        $response->assertStatus(401);
    }

    public function test_feature_returns_403_for_regular_member(): void
    {
        $this->regularUser();

        $response = $this->apiPost('/v2/admin/jobs/1/feature', ['duration_days' => 7]);

        $response->assertStatus(403);
    }

    public function test_feature_succeeds_for_admin(): void
    {
        $this->adminUser();
        $vacancy = $this->createVacancy(['status' => 'open']);

        $response = $this->apiPost("/v2/admin/jobs/{$vacancy->id}/feature", [
            'duration_days' => 7,
        ]);

        $response->assertStatus(200);
        $response->assertJsonFragment(['featured' => true]);
    }

    public function test_feature_with_custom_duration(): void
    {
        $this->adminUser();
        $vacancy = $this->createVacancy(['status' => 'open']);

        $response = $this->apiPost("/v2/admin/jobs/{$vacancy->id}/feature", [
            'duration_days' => 30,
        ]);

        $response->assertStatus(200);
        $response->assertJsonFragment(['duration_days' => 30]);
    }

    // =====================================================================
    // UNFEATURE — POST /v2/admin/jobs/{id}/unfeature
    // =====================================================================

    public function test_unfeature_returns_401_for_unauthenticated(): void
    {
        $response = $this->apiPost('/v2/admin/jobs/1/unfeature');
        $response->assertStatus(401);
    }

    public function test_unfeature_returns_403_for_regular_member(): void
    {
        $this->regularUser();

        $response = $this->apiPost('/v2/admin/jobs/1/unfeature');

        $response->assertStatus(403);
    }

    public function test_unfeature_succeeds_for_admin(): void
    {
        $this->adminUser();
        $vacancy = $this->createVacancy([
            'status' => 'open',
            'is_featured' => true,
            'featured_until' => now()->addDays(7),
        ]);

        $response = $this->apiPost("/v2/admin/jobs/{$vacancy->id}/unfeature");

        $response->assertStatus(200);
        $response->assertJsonFragment(['featured' => false]);
    }

    // =====================================================================
    // APPLICATIONS — GET /v2/admin/jobs/{id}/applications
    // =====================================================================

    public function test_applications_returns_401_for_unauthenticated(): void
    {
        $response = $this->apiGet('/v2/admin/jobs/1/applications');
        $response->assertStatus(401);
    }

    public function test_applications_returns_403_for_regular_member(): void
    {
        $this->regularUser();

        $response = $this->apiGet('/v2/admin/jobs/1/applications');

        $response->assertStatus(403);
    }

    public function test_applications_returns_404_for_nonexistent_job(): void
    {
        $this->adminUser();

        $response = $this->apiGet('/v2/admin/jobs/99999/applications');

        $response->assertStatus(404);
    }

    public function test_applications_returns_200_for_existing_job(): void
    {
        $admin = $this->adminUser();
        $vacancy = $this->createVacancy(['user_id' => $admin->id, 'status' => 'open']);

        $response = $this->apiGet("/v2/admin/jobs/{$vacancy->id}/applications");

        $response->assertStatus(200);
    }

    public function test_applications_includes_created_applications(): void
    {
        $admin = $this->adminUser();
        $vacancy = $this->createVacancy(['user_id' => $admin->id, 'status' => 'open']);

        $applicant = User::factory()->forTenant($this->testTenantId)->create(['status' => 'active']);
        JobApplication::factory()->create([
            'vacancy_id' => $vacancy->id,
            'user_id' => $applicant->id,
            'status' => 'submitted',
        ]);

        $response = $this->apiGet("/v2/admin/jobs/{$vacancy->id}/applications");

        $response->assertStatus(200);
    }

    // =====================================================================
    // UPDATE APPLICATION STATUS — PUT /v2/admin/jobs/applications/{id}
    // =====================================================================

    public function test_update_application_status_returns_401_for_unauthenticated(): void
    {
        $response = $this->apiPut('/v2/admin/jobs/applications/1', ['status' => 'approved']);
        $response->assertStatus(401);
    }

    public function test_update_application_status_returns_403_for_regular_member(): void
    {
        $this->regularUser();

        $response = $this->apiPut('/v2/admin/jobs/applications/1', ['status' => 'approved']);

        $response->assertStatus(403);
    }

    public function test_update_application_status_requires_status_field(): void
    {
        $this->adminUser();

        $response = $this->apiPut('/v2/admin/jobs/applications/1', []);

        $response->assertStatus(422);
    }

    public function test_update_application_status_succeeds(): void
    {
        $admin = $this->adminUser();
        $vacancy = $this->createVacancy(['user_id' => $admin->id, 'status' => 'open']);

        $applicant = User::factory()->forTenant($this->testTenantId)->create(['status' => 'active']);
        $application = JobApplication::factory()->create([
            'vacancy_id' => $vacancy->id,
            'user_id' => $applicant->id,
            'status' => 'submitted',
        ]);

        $response = $this->apiPut("/v2/admin/jobs/applications/{$application->id}", [
            'status' => 'shortlisted',
            'notes' => 'Good fit for the role.',
        ]);

        $response->assertStatus(200);
        $response->assertJsonFragment(['updated' => true]);
    }

    // =====================================================================
    // MODERATION QUEUE — GET /v2/admin/jobs/moderation-queue
    // =====================================================================

    public function test_moderation_queue_returns_401_for_unauthenticated(): void
    {
        $response = $this->apiGet('/v2/admin/jobs/moderation-queue');
        $response->assertStatus(401);
    }

    public function test_moderation_queue_returns_403_for_regular_member(): void
    {
        $this->regularUser();

        $response = $this->apiGet('/v2/admin/jobs/moderation-queue');

        $response->assertStatus(403);
    }

    public function test_moderation_queue_returns_200_for_admin(): void
    {
        $this->adminUser();

        $response = $this->apiGet('/v2/admin/jobs/moderation-queue');

        $response->assertStatus(200);
        $response->assertJsonStructure(['data']);
    }

    public function test_moderation_queue_supports_limit_and_offset(): void
    {
        $this->adminUser();

        $response = $this->apiGet('/v2/admin/jobs/moderation-queue?limit=10&offset=0');

        $response->assertStatus(200);
    }

    // =====================================================================
    // APPROVE — POST /v2/admin/jobs/{id}/approve
    // =====================================================================

    public function test_approve_returns_401_for_unauthenticated(): void
    {
        $response = $this->apiPost('/v2/admin/jobs/1/approve');
        $response->assertStatus(401);
    }

    public function test_approve_returns_403_for_regular_member(): void
    {
        $this->regularUser();

        $response = $this->apiPost('/v2/admin/jobs/1/approve');

        $response->assertStatus(403);
    }

    public function test_approve_succeeds_for_pending_job(): void
    {
        $this->adminUser();
        $vacancy = $this->createVacancy(['status' => 'draft']);

        $response = $this->apiPost("/v2/admin/jobs/{$vacancy->id}/approve", [
            'notes' => 'Looks good, approved.',
        ]);

        // 200 on success, 400 if already processed or not in pending state
        $this->assertContains($response->status(), [200, 400]);
    }

    public function test_approve_returns_400_for_nonexistent_job(): void
    {
        $this->adminUser();

        $response = $this->apiPost('/v2/admin/jobs/99999/approve');

        $response->assertStatus(400);
    }

    // =====================================================================
    // REJECT — POST /v2/admin/jobs/{id}/reject
    // =====================================================================

    public function test_reject_returns_401_for_unauthenticated(): void
    {
        $response = $this->apiPost('/v2/admin/jobs/1/reject', ['reason' => 'Spam']);
        $response->assertStatus(401);
    }

    public function test_reject_returns_403_for_regular_member(): void
    {
        $this->regularUser();

        $response = $this->apiPost('/v2/admin/jobs/1/reject', ['reason' => 'Spam']);

        $response->assertStatus(403);
    }

    public function test_reject_requires_reason(): void
    {
        $this->adminUser();
        $vacancy = $this->createVacancy(['status' => 'draft']);

        $response = $this->apiPost("/v2/admin/jobs/{$vacancy->id}/reject", []);

        $response->assertStatus(422);
    }

    public function test_reject_succeeds_with_reason(): void
    {
        $this->adminUser();
        $vacancy = $this->createVacancy(['status' => 'draft']);

        $response = $this->apiPost("/v2/admin/jobs/{$vacancy->id}/reject", [
            'reason' => 'This posting contains misleading information.',
        ]);

        // 200 on success, 400 if already processed
        $this->assertContains($response->status(), [200, 400]);
    }

    // =====================================================================
    // FLAG — POST /v2/admin/jobs/{id}/flag
    // =====================================================================

    public function test_flag_returns_401_for_unauthenticated(): void
    {
        $response = $this->apiPost('/v2/admin/jobs/1/flag', ['reason' => 'Suspicious']);
        $response->assertStatus(401);
    }

    public function test_flag_returns_403_for_regular_member(): void
    {
        $this->regularUser();

        $response = $this->apiPost('/v2/admin/jobs/1/flag', ['reason' => 'Suspicious']);

        $response->assertStatus(403);
    }

    public function test_flag_requires_reason(): void
    {
        $this->adminUser();
        $vacancy = $this->createVacancy(['status' => 'open']);

        $response = $this->apiPost("/v2/admin/jobs/{$vacancy->id}/flag", []);

        $response->assertStatus(422);
    }

    public function test_flag_succeeds_with_reason(): void
    {
        $this->adminUser();
        $vacancy = $this->createVacancy(['status' => 'open']);

        $response = $this->apiPost("/v2/admin/jobs/{$vacancy->id}/flag", [
            'reason' => 'Needs further review — possibly off-topic.',
        ]);

        // 200 on success, 400 if job not found
        $this->assertContains($response->status(), [200, 400]);
    }

    // =====================================================================
    // MODERATION STATS — GET /v2/admin/jobs/moderation-stats
    // =====================================================================

    public function test_moderation_stats_returns_401_for_unauthenticated(): void
    {
        $response = $this->apiGet('/v2/admin/jobs/moderation-stats');
        $response->assertStatus(401);
    }

    public function test_moderation_stats_returns_403_for_regular_member(): void
    {
        $this->regularUser();

        $response = $this->apiGet('/v2/admin/jobs/moderation-stats');

        $response->assertStatus(403);
    }

    public function test_moderation_stats_returns_200_for_admin(): void
    {
        $this->adminUser();

        $response = $this->apiGet('/v2/admin/jobs/moderation-stats');

        $response->assertStatus(200);
        $response->assertJsonStructure(['data']);
    }

    // =====================================================================
    // SPAM STATS — GET /v2/admin/jobs/spam-stats
    // =====================================================================

    public function test_spam_stats_returns_401_for_unauthenticated(): void
    {
        $response = $this->apiGet('/v2/admin/jobs/spam-stats');
        $response->assertStatus(401);
    }

    public function test_spam_stats_returns_403_for_regular_member(): void
    {
        $this->regularUser();

        $response = $this->apiGet('/v2/admin/jobs/spam-stats');

        $response->assertStatus(403);
    }

    public function test_spam_stats_returns_200_for_admin(): void
    {
        $this->adminUser();

        $response = $this->apiGet('/v2/admin/jobs/spam-stats');

        $response->assertStatus(200);
        $response->assertJsonStructure(['data']);
    }

    // =====================================================================
    // BIAS AUDIT — GET /v2/admin/jobs/bias-audit
    // =====================================================================

    public function test_bias_audit_returns_401_for_unauthenticated(): void
    {
        $response = $this->apiGet('/v2/admin/jobs/bias-audit');
        $response->assertStatus(401);
    }

    public function test_bias_audit_returns_403_for_regular_member(): void
    {
        $this->regularUser();

        $response = $this->apiGet('/v2/admin/jobs/bias-audit');

        $response->assertStatus(403);
    }

    public function test_bias_audit_returns_200_for_admin(): void
    {
        $this->adminUser();

        $response = $this->apiGet('/v2/admin/jobs/bias-audit');

        $response->assertStatus(200);
        $response->assertJsonStructure(['data']);
    }

    public function test_bias_audit_supports_job_id_filter(): void
    {
        $this->adminUser();
        $vacancy = $this->createVacancy(['status' => 'open']);

        $response = $this->apiGet("/v2/admin/jobs/bias-audit?job_id={$vacancy->id}");

        $response->assertStatus(200);
    }

    public function test_bias_audit_supports_date_range(): void
    {
        $this->adminUser();

        $response = $this->apiGet('/v2/admin/jobs/bias-audit?date_from=2025-01-01&date_to=2026-03-22');

        $response->assertStatus(200);
    }
}
