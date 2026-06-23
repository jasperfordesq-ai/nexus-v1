<?php
// Copyright © 2024–2026 Jasper Ford
// SPDX-License-Identifier: AGPL-3.0-or-later
// Author: Jasper Ford
// See NOTICE file for attribution and acknowledgements.

namespace Tests\Laravel\Unit\Services;

use App\Core\TenantContext;
use App\Services\CourseCohortService;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Illuminate\Support\Facades\DB;
use Tests\Laravel\TestCase;

class CourseCohortServiceTest extends TestCase
{
    use DatabaseTransactions;

    private int $tenantId = 99310;
    private int $courseId;

    protected function setUp(): void
    {
        parent::setUp();

        DB::table('tenants')->insertOrIgnore([
            'id'                => $this->tenantId,
            'name'              => 'Test Cohort Tenant',
            'slug'              => 'test-99310',
            'is_active'         => true,
            'depth'             => 0,
            'allows_subtenants' => false,
            'created_at'        => now(),
            'updated_at'        => now(),
        ]);

        TenantContext::setById($this->tenantId);

        // Insert an author user and a course to use as FK target
        $userId = DB::table('users')->insertGetId([
            'tenant_id'  => $this->tenantId,
            'name'       => 'Cohort Author',
            'email'      => 'cohortauthor99310@example.com',
            'password'   => bcrypt('secret'),
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        $this->courseId = DB::table('courses')->insertGetId([
            'tenant_id'         => $this->tenantId,
            'author_user_id'    => $userId,
            'title'             => 'Cohort Course',
            'slug'              => 'cohort-course-99310',
            'status'            => 'draft',
            'moderation_status' => 'pending',
            'level'             => 'beginner',
            'visibility'        => 'members',
            'enrollment_type'   => 'cohort',
            'created_at'        => now(),
            'updated_at'        => now(),
        ]);
    }

    // ── forCourse() ──────────────────────────────────────────────────

    public function test_forCourse_returns_empty_array_when_no_cohorts(): void
    {
        $result = CourseCohortService::forCourse($this->courseId);

        $this->assertIsArray($result);
        $this->assertEmpty($result);
    }

    public function test_forCourse_returns_cohorts_ordered_by_start_date(): void
    {
        DB::table('course_cohorts')->insert([
            [
                'tenant_id'  => $this->tenantId,
                'course_id'  => $this->courseId,
                'name'       => 'Cohort B',
                'start_date' => '2026-09-01 00:00:00',
                'created_at' => now(),
                'updated_at' => now(),
            ],
            [
                'tenant_id'  => $this->tenantId,
                'course_id'  => $this->courseId,
                'name'       => 'Cohort A',
                'start_date' => '2026-06-01 00:00:00',
                'created_at' => now(),
                'updated_at' => now(),
            ],
        ]);

        $result = CourseCohortService::forCourse($this->courseId);

        $this->assertCount(2, $result);
        $this->assertSame('Cohort A', $result[0]['name']);
        $this->assertSame('Cohort B', $result[1]['name']);
    }

    public function test_forCourse_does_not_return_cohorts_from_another_course(): void
    {
        // Create a second course
        $userId2 = DB::table('users')->insertGetId([
            'tenant_id'  => $this->tenantId,
            'name'       => 'Author 2',
            'email'      => 'author2-99310@example.com',
            'password'   => bcrypt('s'),
            'created_at' => now(),
            'updated_at' => now(),
        ]);
        $otherCourseId = DB::table('courses')->insertGetId([
            'tenant_id'         => $this->tenantId,
            'author_user_id'    => $userId2,
            'title'             => 'Other Course',
            'slug'              => 'other-course-99310',
            'status'            => 'draft',
            'moderation_status' => 'pending',
            'level'             => 'beginner',
            'visibility'        => 'members',
            'enrollment_type'   => 'cohort',
            'created_at'        => now(),
            'updated_at'        => now(),
        ]);

        DB::table('course_cohorts')->insert([
            'tenant_id'  => $this->tenantId,
            'course_id'  => $otherCourseId,
            'name'       => 'Foreign Cohort',
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        $result = CourseCohortService::forCourse($this->courseId);

        $this->assertEmpty($result);
    }

    // ── create() ─────────────────────────────────────────────────────

    public function test_create_persists_cohort_with_all_fields(): void
    {
        $cohort = CourseCohortService::create($this->courseId, [
            'name'       => 'Spring 2027',
            'start_date' => '2027-03-01 09:00:00',
            'end_date'   => '2027-06-01 09:00:00',
            'capacity'   => 30,
        ]);

        $this->assertSame($this->courseId, (int) $cohort->course_id);
        $this->assertSame('Spring 2027', $cohort->name);
        $this->assertSame(30, $cohort->capacity);
        $this->assertNotNull($cohort->start_date);
        $this->assertNotNull($cohort->end_date);

        $this->assertDatabaseHas('course_cohorts', [
            'tenant_id' => $this->tenantId,
            'course_id' => $this->courseId,
            'name'      => 'Spring 2027',
            'capacity'  => 30,
        ]);
    }

    public function test_create_allows_null_capacity_and_dates(): void
    {
        $cohort = CourseCohortService::create($this->courseId, [
            'name' => 'Open Cohort',
        ]);

        $this->assertSame('Open Cohort', $cohort->name);
        $this->assertNull($cohort->capacity);
        $this->assertNull($cohort->start_date);
        $this->assertNull($cohort->end_date);
    }

    public function test_create_trims_name(): void
    {
        $cohort = CourseCohortService::create($this->courseId, ['name' => '  Trimmed  ']);

        $this->assertSame('Trimmed', $cohort->name);
    }

    public function test_create_sets_capacity_as_integer(): void
    {
        $cohort = CourseCohortService::create($this->courseId, [
            'name'     => 'Cap Test',
            'capacity' => '25',
        ]);

        $this->assertSame(25, $cohort->capacity);
    }

    // ── delete() ─────────────────────────────────────────────────────

    public function test_delete_returns_false_for_nonexistent_cohort(): void
    {
        $result = CourseCohortService::delete($this->courseId, 999999);

        $this->assertFalse($result);
    }

    public function test_delete_returns_false_when_cohort_belongs_to_different_course(): void
    {
        // Create cohort attached to the real course
        $cohort = CourseCohortService::create($this->courseId, ['name' => 'Wrong Course Cohort']);

        // Try to delete it using a wrong course id
        $result = CourseCohortService::delete($this->courseId + 999, $cohort->id);

        $this->assertFalse($result);
        // The cohort must still exist
        $this->assertDatabaseHas('course_cohorts', ['id' => $cohort->id]);
    }

    public function test_delete_removes_cohort_and_returns_true(): void
    {
        $cohort = CourseCohortService::create($this->courseId, ['name' => 'To Delete Cohort']);

        $result = CourseCohortService::delete($this->courseId, $cohort->id);

        $this->assertTrue($result);
        $this->assertDatabaseMissing('course_cohorts', ['id' => $cohort->id]);
    }

    public function test_delete_only_deletes_matched_cohort_not_others(): void
    {
        $cohortA = CourseCohortService::create($this->courseId, ['name' => 'Keep Me']);
        $cohortB = CourseCohortService::create($this->courseId, ['name' => 'Delete Me']);

        CourseCohortService::delete($this->courseId, $cohortB->id);

        $this->assertDatabaseHas('course_cohorts', ['id' => $cohortA->id]);
        $this->assertDatabaseMissing('course_cohorts', ['id' => $cohortB->id]);
    }
}
