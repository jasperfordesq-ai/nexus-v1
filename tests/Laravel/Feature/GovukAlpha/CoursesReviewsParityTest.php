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
 * Feature tests for the accessible (GOV.UK) course ratings/reviews on the course
 * detail page: render approved reviews + aggregate, hide pending, and the
 * enrolled-only submit path.
 */
class CoursesReviewsParityTest extends TestCase
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

        $row = DB::table('tenants')->where('id', $this->testTenantId)->value('features');
        $current = $row ? (json_decode($row, true) ?: []) : [];
        $current['courses'] = true;
        DB::table('tenants')->where('id', $this->testTenantId)->update(['features' => json_encode($current)]);
        TenantContext::reset();
        TenantContext::setById($this->testTenantId);
    }

    private function authenticatedUser(array $o = []): User
    {
        $user = User::factory()->forTenant($this->testTenantId)->create(array_merge(['status' => 'active', 'is_approved' => true], $o));
        Sanctum::actingAs($user, ['*']);
        return $user;
    }

    private function seedCourse(int $authorId, array $o = []): int
    {
        return (int) DB::table('courses')->insertGetId(array_merge([
            'tenant_id' => $this->testTenantId, 'author_user_id' => $authorId,
            'title' => 'Review Course ' . uniqid(), 'slug' => 'rev-' . uniqid(),
            'level' => 'beginner', 'visibility' => 'public', 'status' => 'published',
            'moderation_status' => 'approved', 'credit_cost' => 0,
            'created_at' => now(), 'updated_at' => now(),
        ], $o));
    }

    private function seedReview(int $courseId, int $userId, int $rating, string $body, string $status = 'approved'): void
    {
        DB::table('course_reviews')->insert([
            'tenant_id' => $this->testTenantId, 'course_id' => $courseId, 'user_id' => $userId,
            'rating' => $rating, 'body' => $body, 'status' => $status, 'created_at' => now(),
        ]);
    }

    private function seedEnrolment(int $courseId, int $userId, string $status = 'active'): void
    {
        DB::table('course_enrollments')->insert([
            'tenant_id' => $this->testTenantId, 'course_id' => $courseId, 'user_id' => $userId,
            'status' => $status, 'progress_percent' => 0, 'enrolled_at' => now(),
            'created_at' => now(), 'updated_at' => now(),
        ]);
    }

    public function test_detail_shows_approved_review_and_hides_pending(): void
    {
        $author = $this->authenticatedUser();
        $courseId = $this->seedCourse($author->id, ['rating_avg' => 5.0, 'rating_count' => 1]);
        $reviewer = $this->authenticatedUser(['first_name' => 'Happy', 'last_name' => 'Learner']);
        $this->seedReview($courseId, $reviewer->id, 5, 'Brilliant course, learned loads.');
        $hidden = $this->authenticatedUser(['first_name' => 'Pending', 'last_name' => 'Person']);
        $this->seedReview($courseId, $hidden->id, 1, 'This is a pending hidden review body.', 'pending');

        $viewer = $this->authenticatedUser();
        Sanctum::actingAs($viewer, ['*']);
        $res = $this->get("/{$this->testTenantSlug}/alpha/courses/{$courseId}");
        $res->assertOk();
        $res->assertSee(__('govuk_alpha.courses.reviews_label'));
        $res->assertSee('Brilliant course, learned loads.');
        $res->assertDontSee('This is a pending hidden review body.');
    }

    public function test_enrolled_learner_can_submit_review(): void
    {
        $author = $this->authenticatedUser();
        $courseId = $this->seedCourse($author->id);
        $learner = $this->authenticatedUser();
        Sanctum::actingAs($learner, ['*']);
        $this->seedEnrolment($courseId, $learner->id, 'active');

        $res = $this->post("/{$this->testTenantSlug}/alpha/courses/{$courseId}/reviews", [
            'rating' => 4, 'body' => 'Really useful, recommend it.',
        ]);
        $res->assertRedirect();
        $res->assertRedirectContains('status=review-saved');
        $this->assertDatabaseHas('course_reviews', [
            'course_id' => $courseId, 'user_id' => $learner->id, 'rating' => 4, 'status' => 'approved',
        ]);
        // Aggregate recomputed onto the course.
        $this->assertEquals(1, (int) DB::table('courses')->where('id', $courseId)->value('rating_count'));
    }

    public function test_non_enrolled_cannot_submit_review(): void
    {
        $author = $this->authenticatedUser();
        $courseId = $this->seedCourse($author->id);
        $stranger = $this->authenticatedUser();
        Sanctum::actingAs($stranger, ['*']);

        $res = $this->post("/{$this->testTenantSlug}/alpha/courses/{$courseId}/reviews", ['rating' => 5, 'body' => 'x']);
        $res->assertRedirect();
        $res->assertRedirectContains('status=review-not-enrolled');
        $this->assertDatabaseMissing('course_reviews', ['course_id' => $courseId, 'user_id' => $stranger->id]);
    }

    public function test_invalid_rating_rejected(): void
    {
        $author = $this->authenticatedUser();
        $courseId = $this->seedCourse($author->id);
        $learner = $this->authenticatedUser();
        Sanctum::actingAs($learner, ['*']);
        $this->seedEnrolment($courseId, $learner->id, 'completed');

        $res = $this->post("/{$this->testTenantSlug}/alpha/courses/{$courseId}/reviews", ['rating' => 9, 'body' => 'x']);
        $res->assertRedirect();
        $res->assertRedirectContains('status=review-invalid');
        $this->assertDatabaseMissing('course_reviews', ['course_id' => $courseId, 'user_id' => $learner->id]);
    }
}
