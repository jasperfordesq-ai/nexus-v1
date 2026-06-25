<?php
// Copyright © 2024–2026 Jasper Ford
// SPDX-License-Identifier: AGPL-3.0-or-later
// Author: Jasper Ford
// See NOTICE file for attribution and acknowledgements.

namespace Tests\Laravel\Unit\Models;

use App\Core\TenantContext;
use App\Models\Concerns\HasTenantScope;
use App\Models\Course;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Queue;
use Tests\Laravel\TestCase;

class CourseTest extends TestCase
{
    use DatabaseTransactions;

    private const TENANT_ID = 99765;

    protected function setUp(): void
    {
        parent::setUp();
        Queue::fake();

        DB::table('tenants')->updateOrInsert(
            ['id' => self::TENANT_ID],
            [
                'name'              => 'Test Tenant 99765',
                'slug'              => 'test-99765',
                'domain'            => null,
                'is_active'         => true,
                'depth'             => 0,
                'allows_subtenants' => false,
                'created_at'        => now(),
                'updated_at'        => now(),
            ]
        );
        TenantContext::setById(self::TENANT_ID);
    }

    // -------------------------------------------------------------------
    // Helpers
    // -------------------------------------------------------------------

    private function seedAuthorUser(): int
    {
        return DB::table('users')->insertGetId([
            'tenant_id'   => self::TENANT_ID,
            'name'        => 'Course Author',
            'email'       => 'course-author-' . uniqid() . '@example.test',
            'is_active'   => 1,
            'is_approved' => 1,
            'status'      => 'active',
            'created_at'  => now(),
        ]);
    }

    private function seedCourse(array $overrides = []): Course
    {
        $authorId = $this->seedAuthorUser();

        $defaults = [
            'tenant_id'      => self::TENANT_ID,
            'author_user_id' => $authorId,
            'title'          => 'Test Course ' . uniqid(),
            'slug'           => 'test-course-' . uniqid(),
            'level'          => 'beginner',
            'visibility'     => 'members',
            'enrollment_type' => 'self_paced',
            'status'         => 'draft',
            'moderation_status' => 'pending',
            'credit_cost'    => '0.00',
            'learner_credit_reward' => '0.00',
            'instructor_credit_reward' => '0.00',
            'enrollment_count' => 0,
            'completion_count' => 0,
            'rating_avg'     => '0.00',
            'rating_count'   => 0,
            'created_at'     => now(),
            'updated_at'     => now(),
        ];

        $data = array_merge($defaults, $overrides);
        $id = DB::table('courses')->insertGetId($data);

        return Course::findOrFail($id);
    }

    // -------------------------------------------------------------------
    // scopePublished — matching rows included
    // -------------------------------------------------------------------

    public function test_scope_published_returns_published_and_approved_courses(): void
    {
        $course = $this->seedCourse([
            'status'            => 'published',
            'moderation_status' => 'approved',
        ]);

        $found = Course::published()->get();
        $ids   = $found->pluck('id')->toArray();

        $this->assertContains($course->id, $ids);
    }

    // -------------------------------------------------------------------
    // scopePublished — draft excluded
    // -------------------------------------------------------------------

    public function test_scope_published_excludes_draft_courses(): void
    {
        $course = $this->seedCourse([
            'status'            => 'draft',
            'moderation_status' => 'approved',
        ]);

        $ids = Course::published()->pluck('id')->toArray();
        $this->assertNotContains($course->id, $ids);
    }

    // -------------------------------------------------------------------
    // scopePublished — pending moderation excluded
    // -------------------------------------------------------------------

    public function test_scope_published_excludes_courses_pending_moderation(): void
    {
        $course = $this->seedCourse([
            'status'            => 'published',
            'moderation_status' => 'pending',
        ]);

        $ids = Course::published()->pluck('id')->toArray();
        $this->assertNotContains($course->id, $ids);
    }

    // -------------------------------------------------------------------
    // scopePublished — archived excluded
    // -------------------------------------------------------------------

    public function test_scope_published_excludes_archived_courses(): void
    {
        $course = $this->seedCourse([
            'status'            => 'archived',
            'moderation_status' => 'approved',
        ]);

        $ids = Course::published()->pluck('id')->toArray();
        $this->assertNotContains($course->id, $ids);
    }

    // -------------------------------------------------------------------
    // scopePublished — rejected moderation excluded
    // -------------------------------------------------------------------

    public function test_scope_published_excludes_rejected_courses(): void
    {
        $course = $this->seedCourse([
            'status'            => 'published',
            'moderation_status' => 'rejected',
        ]);

        $ids = Course::published()->pluck('id')->toArray();
        $this->assertNotContains($course->id, $ids);
    }

    // -------------------------------------------------------------------
    // Casts
    // -------------------------------------------------------------------

    public function test_prerequisites_cast_to_array(): void
    {
        $course = $this->seedCourse([
            'prerequisites' => json_encode(['course-101', 'course-102']),
        ]);

        $this->assertIsArray($course->prerequisites);
        $this->assertEquals(['course-101', 'course-102'], $course->prerequisites);
    }

    public function test_decimal_fields_cast_correctly(): void
    {
        $course = $this->seedCourse([
            'credit_cost'               => '3.50',
            'learner_credit_reward'     => '1.25',
            'instructor_credit_reward'  => '2.00',
        ]);

        // Cast is 'decimal:2' so values come back as string-numerics
        $this->assertEquals('3.50', $course->credit_cost);
        $this->assertEquals('1.25', $course->learner_credit_reward);
        $this->assertEquals('2.00', $course->instructor_credit_reward);
    }

    // -------------------------------------------------------------------
    // Tenant scope
    // -------------------------------------------------------------------

    public function test_tenant_scope_excludes_other_tenant_rows(): void
    {
        DB::table('tenants')->updateOrInsert(
            ['id' => self::TENANT_ID + 1],
            [
                'name'              => 'Other Tenant',
                'slug'              => 'other-99765',
                'domain'            => null,
                'is_active'         => true,
                'depth'             => 0,
                'allows_subtenants' => false,
                'created_at'        => now(),
                'updated_at'        => now(),
            ]
        );

        $otherAuthorId = DB::table('users')->insertGetId([
            'tenant_id'   => self::TENANT_ID + 1,
            'name'        => 'Other Author',
            'email'       => 'other-course-author-' . uniqid() . '@example.test',
            'is_active'   => 1,
            'is_approved' => 1,
            'status'      => 'active',
            'created_at'  => now(),
        ]);

        $otherId = DB::table('courses')->insertGetId([
            'tenant_id'                => self::TENANT_ID + 1,
            'author_user_id'           => $otherAuthorId,
            'title'                    => 'Other Tenant Course',
            'slug'                     => 'other-tenant-course-' . uniqid(),
            'level'                    => 'beginner',
            'visibility'               => 'members',
            'enrollment_type'          => 'self_paced',
            'status'                   => 'published',
            'moderation_status'        => 'approved',
            'credit_cost'              => '0.00',
            'learner_credit_reward'    => '0.00',
            'instructor_credit_reward' => '0.00',
            'enrollment_count'         => 0,
            'completion_count'         => 0,
            'rating_avg'               => '0.00',
            'rating_count'             => 0,
            'created_at'               => now(),
            'updated_at'               => now(),
        ]);

        TenantContext::setById(self::TENANT_ID);
        $found = Course::find($otherId);
        $this->assertNull($found, 'Tenant scope should exclude courses from another tenant');
    }

    // -------------------------------------------------------------------
    // Traits
    // -------------------------------------------------------------------

    public function test_uses_has_tenant_scope_trait(): void
    {
        $this->assertContains(HasTenantScope::class, class_uses_recursive(Course::class));
    }
}
