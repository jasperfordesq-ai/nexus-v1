<?php
// Copyright © 2024–2026 Jasper Ford
// SPDX-License-Identifier: AGPL-3.0-or-later
// Author: Jasper Ford
// See NOTICE file for attribution and acknowledgements.

namespace Tests\Laravel\Feature\Courses;

use App\Core\TenantContext;
use App\Models\Course;
use App\Models\User;
use App\Services\CourseCreditService;
use App\Services\CourseEnrollmentService;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Tests\Laravel\TestCase;

/**
 * Paid-enrolment credit transfer (learner → author). Conservative, non-minting.
 */
class CourseCreditTest extends TestCase
{
    use DatabaseTransactions;

    private function paidCourse(int $authorId, float $cost): Course
    {
        // author_user_id / status / moderation_status / published_at are not
        // mass-assignable (Course::$fillable hardening) — set them explicitly.
        $course = new Course([
            'title' => 'Paid Course',
            'slug' => 'paid-course-' . uniqid(),
            'visibility' => 'members',
            'credit_cost' => $cost,
        ]);
        $course->tenant_id = $this->testTenantId;
        $course->author_user_id = $authorId;
        $course->status = 'published';
        $course->moderation_status = 'approved';
        $course->published_at = now();
        $course->save();

        // Re-assert the active tenant after model creation. WalletService resolves
        // the transfer recipient through the User model's tenant scope, so the
        // charge must run in the same tenant the author/learner were created in.
        // (Creating models in the test can reset the cached tenant id; in
        // production a single request tenant covers course/author/learner.)
        TenantContext::setById($this->testTenantId);

        return $course;
    }

    public function test_paid_enrolment_transfers_credits_from_learner_to_author(): void
    {
        $author = User::factory()->forTenant($this->testTenantId)->create(['status' => 'active', 'balance' => 0]);
        $learner = User::factory()->forTenant($this->testTenantId)->create(['status' => 'active', 'balance' => 10]);
        $course = $this->paidCourse($author->id, 3);

        $result = CourseCreditService::chargeEnrollment($course, $learner->id);

        $this->assertTrue($result['charged']);
        $this->assertEqualsWithDelta(3.0, (float) $result['amount'], 0.01);
        $this->assertEqualsWithDelta(7.0, (float) $learner->fresh()->balance, 0.01);
        $this->assertEqualsWithDelta(3.0, (float) $author->fresh()->balance, 0.01);
    }

    public function test_insufficient_balance_is_not_charged(): void
    {
        $author = User::factory()->forTenant($this->testTenantId)->create(['status' => 'active', 'balance' => 0]);
        $learner = User::factory()->forTenant($this->testTenantId)->create(['status' => 'active', 'balance' => 1]);
        $course = $this->paidCourse($author->id, 5);

        $result = CourseCreditService::chargeEnrollment($course, $learner->id);

        $this->assertFalse($result['charged']);
        $this->assertEqualsWithDelta(1.0, (float) $learner->fresh()->balance, 0.01);
    }

    public function test_free_course_is_not_charged(): void
    {
        $author = User::factory()->forTenant($this->testTenantId)->create(['status' => 'active', 'balance' => 0]);
        $learner = User::factory()->forTenant($this->testTenantId)->create(['status' => 'active', 'balance' => 5]);
        $course = $this->paidCourse($author->id, 0);

        $result = CourseCreditService::chargeEnrollment($course, $learner->id);

        $this->assertFalse($result['charged']);
        $this->assertEqualsWithDelta(5.0, (float) $learner->fresh()->balance, 0.01);
    }

    public function test_enrolling_twice_does_not_double_charge(): void
    {
        // Idempotency is enforced at the controller layer (isEnrolled short-circuit);
        // the service-level enrol itself is idempotent.
        $author = User::factory()->forTenant($this->testTenantId)->create(['status' => 'active', 'balance' => 0]);
        $learner = User::factory()->forTenant($this->testTenantId)->create(['status' => 'active', 'balance' => 10]);
        $course = $this->paidCourse($author->id, 2);

        $first = CourseEnrollmentService::enroll($course->id, $learner->id);
        $second = CourseEnrollmentService::enroll($course->id, $learner->id);

        $this->assertSame($first->id, $second->id);
    }

    public function test_enroll_service_charges_once_and_records_credits_paid(): void
    {
        $author = User::factory()->forTenant($this->testTenantId)->create(['status' => 'active', 'balance' => 0]);
        $learner = User::factory()->forTenant($this->testTenantId)->create(['status' => 'active', 'balance' => 10]);
        $course = $this->paidCourse($author->id, 4);

        $enrollment = CourseEnrollmentService::enrollWithPayment($course, $learner->id);
        $again = CourseEnrollmentService::enrollWithPayment($course, $learner->id);

        $this->assertSame($enrollment->id, $again->id);
        $this->assertEqualsWithDelta(4.0, (float) $enrollment->fresh()->credits_paid, 0.01);
        $this->assertEqualsWithDelta(6.0, (float) $learner->fresh()->balance, 0.01);
        $this->assertEqualsWithDelta(4.0, (float) $author->fresh()->balance, 0.01);
    }
}
