<?php
// Copyright © 2024–2026 Jasper Ford
// SPDX-License-Identifier: AGPL-3.0-or-later
// Author: Jasper Ford
// See NOTICE file for attribution and acknowledgements.

namespace Tests\Laravel\Feature\GovukAlpha;

use App\Core\TenantContext;
use App\Models\User;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Illuminate\Support\Facades\DB;
use Laravel\Sanctum\Sanctum;
use Tests\Laravel\TestCase;

/**
 * Feature tests for the accessible (GOV.UK) course-detail prerequisites list +
 * completion-certificate download.
 */
class CoursesPrereqCertParityTest extends TestCase
{
    use DatabaseTransactions;

    protected function setUp(): void
    {
        parent::setUp();
        $this->app['auth']->forgetGuards();
        foreach (['HTTP_X_TENANT_ID', 'HTTP_X_TENANT_SLUG', 'HTTP_AUTHORIZATION', 'REDIRECT_HTTP_AUTHORIZATION'] as $k) {
            unset($_SERVER[$k]);
        }
        TenantContext::reset();
        TenantContext::setById($this->testTenantId);
        $this->enableCourses();
    }

    private function enableCourses(): void
    {
        $row = DB::table('tenants')->where('id', $this->testTenantId)->value('features');
        $current = $row ? (json_decode($row, true) ?: []) : [];
        $current['courses'] = true;
        DB::table('tenants')->where('id', $this->testTenantId)->update(['features' => json_encode($current)]);
        TenantContext::reset();
        TenantContext::setById($this->testTenantId);
    }

    private function authenticatedUser(array $overrides = []): User
    {
        $user = User::factory()->forTenant($this->testTenantId)->create(array_merge([
            'status' => 'active', 'is_approved' => true,
        ], $overrides));
        Sanctum::actingAs($user, ['*']);
        return $user;
    }

    private function seedCourse(int $authorId, array $overrides = []): int
    {
        return (int) DB::table('courses')->insertGetId(array_merge([
            'tenant_id'         => $this->testTenantId,
            'author_user_id'    => $authorId,
            'title'             => 'Seeded Course ' . uniqid(),
            'slug'              => 'seeded-' . uniqid(),
            'level'             => 'beginner',
            'visibility'        => 'public',
            'status'            => 'published',
            'moderation_status' => 'approved',
            'credit_cost'       => 0,
            'created_at'        => now(),
            'updated_at'        => now(),
        ], $overrides));
    }

    private function seedEnrolment(int $courseId, int $userId, string $status): int
    {
        return (int) DB::table('course_enrollments')->insertGetId([
            'tenant_id'        => $this->testTenantId,
            'course_id'        => $courseId,
            'user_id'          => $userId,
            'status'           => $status,
            'progress_percent' => $status === 'completed' ? 100 : 0,
            'enrolled_at'      => now(),
            'completed_at'     => $status === 'completed' ? now() : null,
            'created_at'       => now(),
            'updated_at'       => now(),
        ]);
    }

    public function test_course_detail_shows_prerequisites(): void
    {
        $author = $this->authenticatedUser();
        $prereqId = $this->seedCourse($author->id, ['title' => 'Intro Prerequisite']);
        $courseId = $this->seedCourse($author->id, [
            'title' => 'Advanced Course', 'prerequisites' => json_encode([$prereqId]),
        ]);

        $viewer = $this->authenticatedUser();
        Sanctum::actingAs($viewer, ['*']);

        $res = $this->get("/{$this->testTenantSlug}/accessible/courses/{$courseId}");
        $res->assertOk();
        $res->assertSee(__('govuk_alpha.courses.prerequisites_label'));
        $res->assertSee('Intro Prerequisite');
    }

    public function test_certificate_locked_when_not_completed(): void
    {
        $author = $this->authenticatedUser();
        $courseId = $this->seedCourse($author->id);

        $learner = $this->authenticatedUser();
        Sanctum::actingAs($learner, ['*']);
        $this->seedEnrolment($courseId, $learner->id, 'active');

        $res = $this->get("/{$this->testTenantSlug}/accessible/courses/{$courseId}/certificate");
        $res->assertRedirect();
        $res->assertRedirectContains('status=certificate-locked');
    }

    public function test_certificate_downloads_when_completed(): void
    {
        $author = $this->authenticatedUser();
        $courseId = $this->seedCourse($author->id, ['title' => 'Completed Masterclass']);

        $learner = $this->authenticatedUser();
        Sanctum::actingAs($learner, ['*']);
        $this->seedEnrolment($courseId, $learner->id, 'completed');

        $res = $this->get("/{$this->testTenantSlug}/accessible/courses/{$courseId}/certificate");
        $res->assertOk();
        $this->assertStringContainsString('text/html', $res->headers->get('Content-Type'));
        $res->assertSee('Completed Masterclass');
    }
}
