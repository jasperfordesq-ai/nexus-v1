<?php
// Copyright © 2024–2026 Jasper Ford
// SPDX-License-Identifier: AGPL-3.0-or-later
// Author: Jasper Ford
// See NOTICE file for attribution and acknowledgements.

namespace Tests\Laravel\Feature\Courses;

use App\Core\TenantContext;
use App\Exceptions\MaxAttemptsExceededException;
use App\Models\Course;
use App\Models\CourseLesson;
use App\Models\CourseQuestion;
use App\Models\CourseQuiz;
use App\Services\CourseEnrollmentService;
use App\Services\CourseProgressService;
use App\Services\CourseQuizService;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Tests\Laravel\TestCase;

/**
 * Service-level tests for enrollment, progress→completion, quiz grading,
 * and tenant isolation in the Courses module.
 */
class CourseProgressAndQuizTest extends TestCase
{
    use DatabaseTransactions;

    private function makeCourseWithLessons(int $lessonCount = 2): Course
    {
        $course = Course::create([
            'author_user_id' => 1,
            'title' => 'Test Course',
            'slug' => 'test-course-' . uniqid(),
            'status' => 'published',
            'moderation_status' => 'approved',
            'visibility' => 'members',
            'published_at' => now(),
        ]);

        for ($i = 1; $i <= $lessonCount; $i++) {
            CourseLesson::create([
                'course_id' => $course->id,
                'title' => "Lesson {$i}",
                'content_type' => 'text',
                'position' => $i,
            ]);
        }

        return $course;
    }

    public function test_enroll_is_idempotent(): void
    {
        $course = $this->makeCourseWithLessons(1);

        $first = CourseEnrollmentService::enroll($course->id, 42);
        $second = CourseEnrollmentService::enroll($course->id, 42);

        $this->assertSame($first->id, $second->id);
    }

    public function test_completing_all_lessons_completes_course(): void
    {
        $course = $this->makeCourseWithLessons(2);
        $lessons = CourseLesson::where('course_id', $course->id)->orderBy('position')->get();

        $enrollment = CourseEnrollmentService::enroll($course->id, 42);

        $r1 = CourseProgressService::completeLesson($enrollment, $lessons[0]->id, 42);
        $this->assertFalse($r1['course_completed']);
        $this->assertEqualsWithDelta(50.0, (float) $r1['progress_percent'], 0.01);

        $r2 = CourseProgressService::completeLesson($enrollment->fresh(), $lessons[1]->id, 42);
        $this->assertTrue($r2['course_completed']);
        $this->assertEqualsWithDelta(100.0, (float) $r2['progress_percent'], 0.01);

        $this->assertSame('completed', $enrollment->fresh()->status);
    }

    public function test_quiz_auto_grades_mcq(): void
    {
        $course = $this->makeCourseWithLessons(1);
        $quiz = CourseQuiz::create([
            'course_id' => $course->id,
            'title' => 'Quiz',
            'pass_mark_percent' => 50,
        ]);
        CourseQuestion::create([
            'quiz_id' => $quiz->id,
            'type' => 'mcq',
            'prompt' => '2+2?',
            'options' => [['id' => 'a', 'label' => '3'], ['id' => 'b', 'label' => '4']],
            'correct' => ['b'],
            'points' => 1,
            'position' => 1,
        ]);

        $pass = CourseQuizService::submitAttempt($quiz->id, 42, [], null);
        $this->assertSame(0.0, (float) $pass['score_percent']); // no answer → 0

        $correct = CourseQuizService::submitAttempt($quiz->id, 42, [], null);
        $this->assertNotNull($correct['attempt']->id);
    }

    public function test_quiz_grades_correct_answer_as_pass(): void
    {
        $course = $this->makeCourseWithLessons(1);
        $quiz = CourseQuiz::create([
            'course_id' => $course->id,
            'title' => 'Quiz',
            'pass_mark_percent' => 100,
        ]);
        $q = CourseQuestion::create([
            'quiz_id' => $quiz->id,
            'type' => 'mcq',
            'prompt' => 'Pick b',
            'options' => [['id' => 'a', 'label' => 'A'], ['id' => 'b', 'label' => 'B']],
            'correct' => ['b'],
            'points' => 1,
            'position' => 1,
        ]);

        $result = CourseQuizService::submitAttempt($quiz->id, 42, [$q->id => 'b'], null);
        $this->assertSame(100.0, (float) $result['score_percent']);
        $this->assertTrue($result['passed']);
    }

    public function test_quiz_enforces_max_attempts(): void
    {
        $course = $this->makeCourseWithLessons(1);
        $quiz = CourseQuiz::create([
            'course_id' => $course->id,
            'title' => 'Limited',
            'pass_mark_percent' => 50,
            'max_attempts' => 1,
        ]);
        CourseQuestion::create([
            'quiz_id' => $quiz->id,
            'type' => 'mcq',
            'prompt' => 'Pick b',
            'options' => [['id' => 'a', 'label' => 'A'], ['id' => 'b', 'label' => 'B']],
            'correct' => ['b'],
            'points' => 1,
            'position' => 1,
        ]);

        // First attempt is allowed.
        $first = CourseQuizService::submitAttempt($quiz->id, 42, [], null);
        $this->assertNotNull($first['attempt']->id);

        // A second attempt exceeds max_attempts and is rejected (enforced inside
        // the row-locked transaction, so it can't be raced by concurrent requests).
        $this->expectException(MaxAttemptsExceededException::class);
        CourseQuizService::submitAttempt($quiz->id, 42, [], null);
    }

    public function test_course_is_tenant_scoped(): void
    {
        $course = $this->makeCourseWithLessons(1);

        // Switch to another tenant — the course must not be visible.
        TenantContext::setById(999);
        $this->assertNull(Course::find($course->id));

        // Back to the original tenant — visible again.
        TenantContext::setById($this->testTenantId);
        $this->assertNotNull(Course::find($course->id));
    }
}
