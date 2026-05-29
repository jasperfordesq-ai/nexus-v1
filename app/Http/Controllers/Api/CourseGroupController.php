<?php
// Copyright © 2024–2026 Jasper Ford
// SPDX-License-Identifier: AGPL-3.0-or-later
// Author: Jasper Ford
// See NOTICE file for attribution and acknowledgements.

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Api\Concerns\InteractsWithCourses;
use App\Services\CourseGroupService;
use Illuminate\Http\JsonResponse;

/**
 * CourseGroupController — link/unlink courses to community groups and expose a
 * group's recommended courses. Linking is course-owner-or-admin; listing is open
 * to anyone who can see the group.
 */
class CourseGroupController extends BaseApiController
{
    use InteractsWithCourses;

    protected bool $isV2Api = true;

    /** POST /v2/courses/{courseId}/groups/{groupId} */
    public function attach(int $courseId, int $groupId): JsonResponse
    {
        $this->ensureCoursesFeature();
        $userId = $this->requireCourseAuthor();
        $course = $this->findCourseOrFail($courseId);
        $this->ensureCourseOwnerOrAdmin($course, $userId);

        $link = CourseGroupService::attach($courseId, $groupId);

        return $this->respondWithData($link, null, 201);
    }

    /** DELETE /v2/courses/{courseId}/groups/{groupId} */
    public function detach(int $courseId, int $groupId): JsonResponse
    {
        $this->ensureCoursesFeature();
        $userId = $this->requireCourseAuthor();
        $course = $this->findCourseOrFail($courseId);
        $this->ensureCourseOwnerOrAdmin($course, $userId);

        CourseGroupService::detach($courseId, $groupId);

        return $this->respondWithData(['detached' => true]);
    }

    /** GET /v2/groups/{groupId}/courses — a group's recommended (published) courses. */
    public function forGroup(int $groupId): JsonResponse
    {
        $this->ensureCoursesFeature();

        return $this->respondWithData(CourseGroupService::coursesForGroup($groupId));
    }

    /** GET /v2/courses/{courseId}/groups — group ids this course is linked to (owner/admin). */
    public function groupsForCourse(int $courseId): JsonResponse
    {
        $this->ensureCoursesFeature();
        $userId = $this->requireCourseAuthor();
        $course = $this->findCourseOrFail($courseId);
        $this->ensureCourseOwnerOrAdmin($course, $userId);

        return $this->respondWithData(CourseGroupService::groupIdsForCourse($courseId));
    }
}
