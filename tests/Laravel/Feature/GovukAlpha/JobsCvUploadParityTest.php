<?php
// Copyright © 2024–2026 Jasper Ford
// SPDX-License-Identifier: AGPL-3.0-or-later
// Author: Jasper Ford
// See NOTICE file for attribution and acknowledgements.

namespace Tests\Laravel\Feature\GovukAlpha;

use App\Core\TenantContext;
use App\Models\JobApplication;
use App\Models\JobVacancy;
use App\Models\User;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
use Laravel\Sanctum\Sanctum;
use Tests\Laravel\TestCase;

/**
 * Feature tests for the accessible (GOV.UK) job-application CV upload + download
 * parity work: applicants can attach a PDF/DOC/DOCX CV, invalid types are
 * rejected, and the employer can download the CV (authorisation-gated, mirroring
 * JobVacanciesController::downloadCv).
 */
class JobsCvUploadParityTest extends TestCase
{
    use DatabaseTransactions;

    protected function setUp(): void
    {
        parent::setUp();

        $this->app['auth']->forgetGuards();

        foreach ([
            'HTTP_X_TENANT_ID',
            'HTTP_X_TENANT_SLUG',
            'HTTP_AUTHORIZATION',
            'REDIRECT_HTTP_AUTHORIZATION',
        ] as $serverKey) {
            unset($_SERVER[$serverKey]);
        }

        TenantContext::reset();
        TenantContext::setById($this->testTenantId);

        $this->enableJobsFeature();
    }

    private function enableJobsFeature(): void
    {
        $row = DB::table('tenants')->where('id', $this->testTenantId)->value('features');
        $current = $row ? (json_decode($row, true) ?: []) : [];
        $current['job_vacancies'] = true;
        DB::table('tenants')->where('id', $this->testTenantId)->update(['features' => json_encode($current)]);
        TenantContext::reset();
        TenantContext::setById($this->testTenantId);
    }

    private function authenticatedUser(array $overrides = []): User
    {
        $user = User::factory()->forTenant($this->testTenantId)->create(array_merge([
            'status'      => 'active',
            'is_approved' => true,
        ], $overrides));

        Sanctum::actingAs($user, ['*']);

        return $user;
    }

    private function createJob(int $userId, array $overrides = []): JobVacancy
    {
        return JobVacancy::factory()->forTenant($this->testTenantId)->create(array_merge([
            'user_id'           => $userId,
            'status'            => 'open',
            'moderation_status' => 'approved',
            'deadline'          => null,
        ], $overrides));
    }

    public function test_apply_with_pdf_cv_stores_file_and_application(): void
    {
        Storage::fake('local');

        $owner = $this->authenticatedUser();
        $job = $this->createJob($owner->id);

        $applicant = $this->authenticatedUser();
        Sanctum::actingAs($applicant, ['*']);

        $cv = UploadedFile::fake()->create('resume.pdf', 80, 'application/pdf');

        $res = $this->post("/{$this->testTenantSlug}/accessible/jobs/{$job->id}/apply", [
            'cover_letter' => 'I would be a great fit.',
            'cv'           => $cv,
        ]);

        $res->assertRedirect();
        $res->assertRedirectContains('status=applied');

        $application = JobApplication::where('vacancy_id', $job->id)
            ->where('user_id', $applicant->id)
            ->first();

        $this->assertNotNull($application);
        $this->assertNotEmpty($application->cv_path);
        $this->assertSame('resume.pdf', $application->cv_filename);
        Storage::disk('local')->assertExists($application->cv_path);
    }

    public function test_apply_rejects_non_document_cv(): void
    {
        Storage::fake('local');

        $owner = $this->authenticatedUser();
        $job = $this->createJob($owner->id);

        $applicant = $this->authenticatedUser();
        Sanctum::actingAs($applicant, ['*']);

        $cv = UploadedFile::fake()->create('notes.txt', 10, 'text/plain');

        $res = $this->post("/{$this->testTenantSlug}/accessible/jobs/{$job->id}/apply", [
            'cover_letter' => 'Trying a disallowed file.',
            'cv'           => $cv,
        ]);

        $res->assertRedirect();
        $res->assertRedirectContains('status=cv-invalid');

        $this->assertSame(0, JobApplication::where('vacancy_id', $job->id)
            ->where('user_id', $applicant->id)
            ->count());
    }

    public function test_cv_download_allowed_for_poster(): void
    {
        Storage::fake('local');

        $owner = $this->authenticatedUser();
        $job = $this->createJob($owner->id);

        $applicant = $this->authenticatedUser(['first_name' => 'Cv', 'last_name' => 'Owner']);
        $path = "job-applications/{$this->testTenantId}/seeded-" . uniqid() . '.pdf';
        Storage::disk('local')->put($path, '%PDF-1.4 fake');

        $application = JobApplication::factory()->forTenant($this->testTenantId)->create([
            'vacancy_id'  => $job->id,
            'user_id'     => $applicant->id,
            'status'      => 'pending',
            'stage'       => 'applied',
            'cv_path'     => $path,
            'cv_filename' => 'resume.pdf',
        ]);

        // Poster downloads.
        Sanctum::actingAs($owner, ['*']);
        $res = $this->get("/{$this->testTenantSlug}/accessible/jobs/applications/{$application->id}/cv");
        $res->assertOk();
        $res->assertDownload('resume.pdf');
    }

    public function test_cv_download_forbidden_for_unrelated_member(): void
    {
        Storage::fake('local');

        $owner = $this->authenticatedUser();
        $job = $this->createJob($owner->id);

        $applicant = $this->authenticatedUser();
        $path = "job-applications/{$this->testTenantId}/seeded-" . uniqid() . '.pdf';
        Storage::disk('local')->put($path, '%PDF-1.4 fake');

        $application = JobApplication::factory()->forTenant($this->testTenantId)->create([
            'vacancy_id'  => $job->id,
            'user_id'     => $applicant->id,
            'status'      => 'pending',
            'stage'       => 'applied',
            'cv_path'     => $path,
            'cv_filename' => 'resume.pdf',
        ]);

        // An unrelated member (not applicant, not poster, not admin).
        $stranger = $this->authenticatedUser(['role' => 'member']);
        Sanctum::actingAs($stranger, ['*']);

        $res = $this->get("/{$this->testTenantSlug}/accessible/jobs/applications/{$application->id}/cv");
        $res->assertForbidden();
    }
}
