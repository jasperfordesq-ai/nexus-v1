<?php
// Copyright © 2024–2026 Jasper Ford
// SPDX-License-Identifier: AGPL-3.0-or-later
// Author: Jasper Ford
// See NOTICE file for attribution and acknowledgements.

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Api\Concerns\InteractsWithCourses;
use App\Models\CourseDiscussion;
use App\Models\CourseLesson;
use App\Services\CourseDiscussionService;
use App\Services\CourseEnrollmentService;
use Illuminate\Http\JsonResponse;

/**
 * CourseDiscussionController — threaded per-lesson discussions.
 * Viewing/posting requires being enrolled (or the course author / an admin).
 * Deleting a comment is owner-or-admin; hiding is admin-only.
 */
class CourseDiscussionController extends BaseApiController
{
    use InteractsWithCourses;

    protected bool $isV2Api = true;

    /** GET /v2/courses/{courseId}/lessons/{lessonId}/discussions */
    public function index(int $courseId, int $lessonId): JsonResponse
    {
        $this->ensureCoursesFeature();
        $userId = $this->requireAuth();
        $this->ensureParticipant($courseId, $lessonId, $userId);

        return $this->respondWithData(CourseDiscussionService::listForLesson($courseId, $lessonId));
    }

    /** POST /v2/courses/{courseId}/lessons/{lessonId}/discussions */
    public function store(int $courseId, int $lessonId): JsonResponse
    {
        $this->ensureCoursesFeature();
        $userId = $this->requireAuth();
        $this->ensureParticipant($courseId, $lessonId, $userId);

        $body = trim((string) $this->input('body', ''));
        if ($body === '') {
            return $this->respondWithError('VALIDATION_FAILED', __('api_controllers_2.courses.comment_required'), 'body', 422);
        }

        $parentId = $this->inputInt('parent_id', null, 1);
        $comment = CourseDiscussionService::create($courseId, $lessonId, $userId, $body, $parentId);

        return $this->respondWithData($comment->load('user:id,name,avatar_url'), null, 201);
    }

    /** DELETE /v2/courses/discussions/{id} — owner or admin. */
    public function destroy(int $id): JsonResponse
    {
        $this->ensureCoursesFeature();
        $userId = $this->requireAuth();

        $comment = CourseDiscussionService::find($id);
        if (!$comment) {
            return $this->respondWithError('RESOURCE_NOT_FOUND', __('api_controllers_2.courses.not_found'), null, 404);
        }
        if ((int) $comment->user_id !== $userId) {
            $this->requireAdmin();
        }

        CourseDiscussionService::delete($id);

        return $this->respondWithData(['deleted' => true]);
    }

    /** POST /v2/admin/courses/discussions/{id}/hide — admin moderation. */
    public function hide(int $id): JsonResponse
    {
        $this->ensureCoursesFeature();
        $this->requireAdmin();

        if (!CourseDiscussionService::setStatus($id, 'hidden')) {
            return $this->respondWithError('RESOURCE_NOT_FOUND', __('api_controllers_2.courses.not_found'), null, 404);
        }

        return $this->respondWithData(['hidden' => true]);
    }

    /**
     * Ensure the caller may view/post in this lesson's discussion: enrolled
     * learner, the course author, or an admin. Also validates lesson↔course.
     *
     * @throws \Illuminate\Http\Exceptions\HttpResponseException
     */
    private function ensureParticipant(int $courseId, int $lessonId, int $userId): void
    {
        $lessonOk = CourseLesson::where('id', $lessonId)->where('course_id', $courseId)->exists();
        if (!$lessonOk) {
            throw new \Illuminate\Http\Exceptions\HttpResponseException(
                $this->respondWithError('RESOURCE_NOT_FOUND', __('api_controllers_2.courses.not_found'), null, 404)
            );
        }

        if (CourseEnrollmentService::isEnrolled($courseId, $userId)) {
            return;
        }

        // Course author may participate without enrolling.
        $course = $this->findCourseOrFail($courseId);
        if ((int) $course->author_user_id === $userId) {
            return;
        }

        // Otherwise must be an admin.
        $this->requireAdmin();
    }
}
