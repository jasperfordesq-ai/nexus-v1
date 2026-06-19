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
 * Feature tests for the accessible (GOV.UK) Courses parity work:
 * category + level browse filters, and the interactive quiz lesson type
 * (render + graded submission).
 */
class CoursesFiltersQuizParityTest extends TestCase
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
            'status'      => 'active',
            'is_approved' => true,
        ], $overrides));

        Sanctum::actingAs($user, ['*']);

        return $user;
    }

    private function seedCategory(string $name = 'Web Skills'): int
    {
        return (int) DB::table('course_categories')->insertGetId([
            'tenant_id'  => $this->testTenantId,
            'name'       => $name,
            'slug'       => 'cat-' . uniqid(),
            'position'   => 0,
            'created_at' => now(),
            'updated_at' => now(),
        ]);
    }

    private function seedCourse(int $authorId, array $overrides = []): int
    {
        return (int) DB::table('courses')->insertGetId(array_merge([
            'tenant_id'        => $this->testTenantId,
            'author_user_id'   => $authorId,
            'title'            => 'Seeded Course',
            'slug'             => 'seeded-course-' . uniqid(),
            'level'            => 'beginner',
            'visibility'       => 'public',
            'status'           => 'published',
            'moderation_status' => 'approved',
            'credit_cost'      => 0,
            'created_at'       => now(),
            'updated_at'       => now(),
        ], $overrides));
    }

    private function seedQuizLesson(int $courseId): array
    {
        $sectionId = DB::table('course_sections')->insertGetId([
            'tenant_id'  => $this->testTenantId,
            'course_id'  => $courseId,
            'title'      => 'Section 1',
            'position'   => 0,
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        $lessonId = (int) DB::table('course_lessons')->insertGetId([
            'tenant_id'    => $this->testTenantId,
            'course_id'    => $courseId,
            'section_id'   => $sectionId,
            'title'        => 'Quiz Lesson',
            'content_type' => 'quiz',
            'body'         => '',
            'position'     => 0,
            'created_at'   => now(),
            'updated_at'   => now(),
        ]);

        $quizId = (int) DB::table('course_quizzes')->insertGetId([
            'tenant_id'         => $this->testTenantId,
            'course_id'         => $courseId,
            'lesson_id'         => $lessonId,
            'title'             => 'Knowledge Check',
            'pass_mark_percent' => 50,
            'max_attempts'      => 3,
            'created_at'        => now(),
            'updated_at'        => now(),
        ]);

        DB::table('course_questions')->insert([
            'tenant_id'  => $this->testTenantId,
            'quiz_id'    => $quizId,
            'type'       => 'mcq',
            'prompt'     => 'What is 2 + 2?',
            'options'    => json_encode([['id' => 'a', 'label' => 'Three'], ['id' => 'b', 'label' => 'Four']]),
            'correct'    => json_encode(['b']),
            'points'     => 1,
            'position'   => 0,
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        return ['lesson_id' => $lessonId, 'quiz_id' => $quizId];
    }

    private function seedEnrolment(int $courseId, int $userId): int
    {
        return (int) DB::table('course_enrollments')->insertGetId([
            'tenant_id'        => $this->testTenantId,
            'course_id'        => $courseId,
            'user_id'          => $userId,
            'status'           => 'active',
            'progress_percent' => 0,
            'enrolled_at'      => now(),
            'created_at'       => now(),
            'updated_at'       => now(),
        ]);
    }

    public function test_courses_browse_renders_category_and_level_filters(): void
    {
        $author = $this->authenticatedUser();
        $this->seedCategory('Web Skills');
        $this->seedCourse($author->id);

        $res = $this->get("/{$this->testTenantSlug}/alpha/courses");

        $res->assertOk();
        $res->assertSee(__('govuk_alpha.courses.category_label'));
        $res->assertSee(__('govuk_alpha.courses.all_levels'));
        $res->assertSee('Web Skills');
        $res->assertSee('name="level"', false);
    }

    public function test_courses_level_filter_excludes_non_matching(): void
    {
        $author = $this->authenticatedUser();
        $this->seedCourse($author->id, ['title' => 'Beginner Basics', 'level' => 'beginner']);
        $this->seedCourse($author->id, ['title' => 'Advanced Wizardry', 'level' => 'advanced']);

        $res = $this->get("/{$this->testTenantSlug}/alpha/courses?level=advanced");

        $res->assertOk();
        $res->assertSee('Advanced Wizardry');
        $res->assertDontSee('Beginner Basics');
    }

    public function test_courses_quiz_lesson_renders_questions(): void
    {
        $author = $this->authenticatedUser();
        $courseId = $this->seedCourse($author->id);
        $ids = $this->seedQuizLesson($courseId);

        $learner = $this->authenticatedUser();
        Sanctum::actingAs($learner, ['*']);
        $this->seedEnrolment($courseId, $learner->id);

        $res = $this->get("/{$this->testTenantSlug}/alpha/courses/{$courseId}/learn?lesson={$ids['lesson_id']}");

        $res->assertOk();
        $res->assertSee('What is 2 + 2?');
        $res->assertSee('name="answers[', false);
        $res->assertSee(__('govuk_alpha_commerce.learn.quiz_submit'));
    }

    public function test_courses_quiz_submit_grades_correct_answer(): void
    {
        $author = $this->authenticatedUser();
        $courseId = $this->seedCourse($author->id);
        $ids = $this->seedQuizLesson($courseId);

        $learner = $this->authenticatedUser();
        Sanctum::actingAs($learner, ['*']);
        $enrolId = $this->seedEnrolment($courseId, $learner->id);

        $res = $this->post("/{$this->testTenantSlug}/alpha/courses/{$courseId}/lessons/{$ids['lesson_id']}/quiz", [
            'answers' => [(string) $this->firstQuestionId($ids['quiz_id']) => 'b'],
        ]);

        $res->assertRedirect();
        $res->assertRedirectContains('status=quiz-passed');

        $this->assertDatabaseHas('course_quiz_attempts', [
            'quiz_id' => $ids['quiz_id'],
            'user_id' => $learner->id,
            'passed'  => 1,
        ]);
    }

    private function firstQuestionId(int $quizId): int
    {
        return (int) DB::table('course_questions')->where('quiz_id', $quizId)->value('id');
    }
}
