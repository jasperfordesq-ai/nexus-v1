<?php
// Copyright © 2024–2026 Jasper Ford
// SPDX-License-Identifier: AGPL-3.0-or-later
// Author: Jasper Ford
// See NOTICE file for attribution and acknowledgements.

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Api\Concerns\InteractsWithCourses;
use App\Models\CourseLesson;
use App\Models\CourseLessonProgress;
use App\Models\CourseReview;
use App\Services\CourseEnrollmentService;
use App\Services\CourseProgressService;
use Illuminate\Http\JsonResponse;

/**
 * CourseEnrollmentController — learner enrollment, progress, and reviews.
 * All endpoints require authentication (registered under auth:sanctum).
 */
class CourseEnrollmentController extends BaseApiController
{
    use InteractsWithCourses;

    protected bool $isV2Api = true;

    /** POST /v2/courses/{id}/enroll */
    public function enroll(int $id): JsonResponse
    {
        $this->ensureCoursesFeature();
        $userId = $this->requireAuth();

        $course = $this->findCourseOrFail($id);

        if ($course->status !== 'published' || $course->moderation_status !== 'approved') {
            return $this->respondWithError('COURSE_NOT_AVAILABLE', __('api_controllers_2.courses.not_available'), null, 422);
        }

        // Phase 1: free + members-only. Credit-cost enrollment (CourseCreditService)
        // arrives in Phase 3; reject paid courses cleanly until then.
        if ((float) $course->credit_cost > 0) {
            return $this->respondWithError('NOT_IMPLEMENTED', __('api_controllers_2.courses.paid_not_available'), null, 422);
        }

        $enrollment = CourseEnrollmentService::enroll($id, $userId);

        return $this->respondWithData($enrollment, null, 201);
    }

    /** GET /v2/me/courses — courses the caller is enrolled in. */
    public function myCourses(): JsonResponse
    {
        $this->ensureCoursesFeature();
        $userId = $this->requireAuth();

        return $this->respondWithData(CourseEnrollmentService::forUser($userId));
    }

    /** GET /v2/courses/{id}/progress — enrollment + per-lesson progress. */
    public function progress(int $id): JsonResponse
    {
        $this->ensureCoursesFeature();
        $userId = $this->requireAuth();

        $enrollment = CourseEnrollmentService::find($id, $userId);
        if (!$enrollment) {
            return $this->respondWithError('NOT_ENROLLED', __('api_controllers_2.courses.not_enrolled'), null, 404);
        }

        $lessonProgress = CourseLessonProgress::where('enrollment_id', $enrollment->id)
            ->get(['lesson_id', 'status', 'watch_percent', 'completed_at'])
            ->toArray();

        return $this->respondWithData([
            'enrollment' => $enrollment->toArray(),
            'lessons' => $lessonProgress,
        ]);
    }

    /** POST /v2/courses/{id}/lessons/{lessonId}/complete */
    public function completeLesson(int $id, int $lessonId): JsonResponse
    {
        $this->ensureCoursesFeature();
        $userId = $this->requireAuth();

        $enrollment = CourseEnrollmentService::find($id, $userId);
        if (!$enrollment) {
            return $this->respondWithError('NOT_ENROLLED', __('api_controllers_2.courses.not_enrolled'), null, 404);
        }

        // Lesson must belong to this course.
        $lessonValid = CourseLesson::where('id', $lessonId)->where('course_id', $id)->exists();
        if (!$lessonValid) {
            return $this->respondWithError('RESOURCE_NOT_FOUND', __('api_controllers_2.courses.not_found'), null, 404);
        }

        $watchPercent = $this->inputInt('watch_percent', 100, 0, 100);
        $result = CourseProgressService::completeLesson($enrollment, $lessonId, $userId, $watchPercent);

        return $this->respondWithData([
            'progress_percent' => $result['progress_percent'],
            'course_completed' => $result['course_completed'],
        ]);
    }

    /** DELETE /v2/courses/{id}/enroll — drop a course. */
    public function drop(int $id): JsonResponse
    {
        $this->ensureCoursesFeature();
        $userId = $this->requireAuth();

        CourseEnrollmentService::drop($id, $userId);

        return $this->respondWithData(['dropped' => true]);
    }

    /** POST /v2/courses/{id}/reviews — leave a review (must be enrolled). */
    public function review(int $id): JsonResponse
    {
        $this->ensureCoursesFeature();
        $userId = $this->requireAuth();

        if (!CourseEnrollmentService::isEnrolled($id, $userId)) {
            return $this->respondWithError('NOT_ENROLLED', __('api_controllers_2.courses.review_requires_enrollment'), null, 403);
        }

        $rating = $this->inputInt('rating', 0, 1, 5);
        if (!$rating) {
            return $this->respondWithError('VALIDATION_FAILED', __('api_controllers_2.courses.rating_required'), 'rating', 422);
        }

        $review = CourseReview::updateOrCreate(
            ['course_id' => $id, 'user_id' => $userId],
            ['rating' => $rating, 'body' => (string) $this->input('body', ''), 'status' => 'approved']
        );

        $this->recomputeCourseRating($id);

        return $this->respondWithData($review, null, 201);
    }

    /** Recompute the cached rating aggregate on the course. */
    private function recomputeCourseRating(int $courseId): void
    {
        $agg = CourseReview::where('course_id', $courseId)
            ->where('status', 'approved')
            ->selectRaw('AVG(rating) as avg_rating, COUNT(*) as cnt')
            ->first();

        \App\Models\Course::where('id', $courseId)->update([
            'rating_avg' => round((float) ($agg->avg_rating ?? 0), 2),
            'rating_count' => (int) ($agg->cnt ?? 0),
        ]);
    }
}
