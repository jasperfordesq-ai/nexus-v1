<?php
// Copyright © 2024–2026 Jasper Ford
// SPDX-License-Identifier: AGPL-3.0-or-later
// Author: Jasper Ford
// See NOTICE file for attribution and acknowledgements.

declare(strict_types=1);

namespace Tests\Laravel\Unit\Observers;

use App\Models\Course;
use App\Models\CourseLesson;
use App\Models\CourseSection;
use App\Observers\CourseLessonPrerenderObserver;
use App\Observers\CoursePrerenderObserver;
use App\Observers\CourseSectionPrerenderObserver;
use App\Services\PrerenderContentInvalidator;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Illuminate\Support\Facades\DB;
use Mockery;
use Tests\Laravel\TestCase;

class CourseContentPrerenderObserverTest extends TestCase
{
    use DatabaseTransactions;

    private Mockery\MockInterface $invalidator;

    protected function setUp(): void
    {
        parent::setUp();

        $this->invalidator = Mockery::mock(PrerenderContentInvalidator::class);
        $this->app->instance(PrerenderContentInvalidator::class, $this->invalidator);
    }

    public function test_course_save_refreshes_list_and_both_slug_routes_after_a_slug_change(): void
    {
        $course = new Course();
        $course->setRawAttributes([
            'id' => 41,
            'tenant_id' => 2,
            'slug' => 'old-course',
            'moderation_status' => 'pending',
        ], true);
        $course->slug = 'new-course';
        $course->moderation_status = 'approved';

        $this->invalidator->shouldReceive('refreshRoutes')
            ->once()
            ->with(2, ['/courses', '/courses/old-course', '/courses/new-course']);

        (new CoursePrerenderObserver())->saved($course);

        $this->assertTrue(true);
    }

    public function test_course_delete_refreshes_list_and_removed_detail_route(): void
    {
        $course = new Course();
        $course->setRawAttributes(['id' => 42, 'tenant_id' => 2, 'slug' => 'retired-course'], true);

        $this->invalidator->shouldReceive('refreshRoutes')
            ->once()
            ->with(2, ['/courses', '/courses/retired-course']);

        (new CoursePrerenderObserver())->deleted($course);

        $this->assertTrue(true);
    }

    public function test_course_observer_skips_models_without_a_tenant(): void
    {
        $course = new Course();
        $course->setRawAttributes(['id' => 43, 'tenant_id' => null, 'slug' => 'no-tenant'], true);

        $this->invalidator->shouldNotReceive('refreshRoutes');

        (new CoursePrerenderObserver())->saved($course);

        $this->assertTrue(true);
    }

    public function test_section_save_refreshes_the_parent_course_detail(): void
    {
        $courseId = $this->insertCourse('section-course');
        $section = new CourseSection();
        $section->setRawAttributes([
            'id' => 51,
            'tenant_id' => 2,
            'course_id' => $courseId,
        ], true);

        $this->invalidator->shouldReceive('refreshRoutes')
            ->once()
            ->with(2, ['/courses/section-course']);

        (new CourseSectionPrerenderObserver())->saved($section);

        $this->assertTrue(true);
    }

    public function test_section_delete_refreshes_the_parent_course_detail(): void
    {
        $courseId = $this->insertCourse('section-delete-course');
        $section = new CourseSection();
        $section->setRawAttributes([
            'id' => 52,
            'tenant_id' => 2,
            'course_id' => $courseId,
        ], true);

        $this->invalidator->shouldReceive('refreshRoutes')
            ->once()
            ->with(2, ['/courses/section-delete-course']);

        (new CourseSectionPrerenderObserver())->deleted($section);

        $this->assertTrue(true);
    }

    public function test_lesson_save_and_delete_each_refresh_the_parent_course_detail(): void
    {
        $courseId = $this->insertCourse('lesson-course');
        $lesson = new CourseLesson();
        $lesson->setRawAttributes([
            'id' => 61,
            'tenant_id' => 2,
            'course_id' => $courseId,
        ], true);

        $this->invalidator->shouldReceive('refreshRoutes')
            ->twice()
            ->with(2, ['/courses/lesson-course']);

        $observer = new CourseLessonPrerenderObserver();
        $observer->saved($lesson);
        $observer->deleted($lesson);

        $this->assertTrue(true);
    }

    public function test_all_course_content_observers_are_registered_with_eloquent(): void
    {
        $dispatcher = Course::getEventDispatcher();
        $this->assertNotNull($dispatcher);
        $this->assertNotEmpty($dispatcher->getListeners('eloquent.saved: ' . Course::class));
        $this->assertNotEmpty($dispatcher->getListeners('eloquent.saved: ' . CourseSection::class));
        $this->assertNotEmpty($dispatcher->getListeners('eloquent.saved: ' . CourseLesson::class));
    }

    private function insertCourse(string $slug): int
    {
        return (int) DB::table('courses')->insertGetId([
            'tenant_id' => 2,
            'author_user_id' => 1,
            'title' => 'Prerender observer course',
            'slug' => $slug,
            'level' => 'beginner',
            'visibility' => 'public',
            'enrollment_type' => 'self_paced',
            'status' => 'published',
            'moderation_status' => 'approved',
            'created_at' => now(),
            'updated_at' => now(),
        ]);
    }
}
