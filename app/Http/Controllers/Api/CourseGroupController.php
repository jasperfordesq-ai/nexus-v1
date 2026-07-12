<?php
// Copyright © 2024–2026 Jasper Ford
// SPDX-License-Identifier: AGPL-3.0-or-later
// Author: Jasper Ford
// See NOTICE file for attribution and acknowledgements.

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Api\Concerns\InteractsWithCourses;
use App\Models\Group;
use App\Services\CourseGroupService;
use App\Services\GroupAccessService;
use Illuminate\Http\JsonResponse;

/**
 * CourseGroupController — link/unlink courses to community groups and expose a
 * group's recommended courses. Linking is course-owner-or-admin; listing is an
 * authenticated route filtered to the viewer's group access.
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
        $groupGuard = $this->requireManageableGroup($groupId, $userId);
        if ($groupGuard instanceof JsonResponse) {
            return $groupGuard;
        }

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
        $groupGuard = $this->requireManageableGroup($groupId, $userId);
        if ($groupGuard instanceof JsonResponse) {
            return $groupGuard;
        }

        CourseGroupService::detach($courseId, $groupId);

        return $this->respondWithData(['detached' => true]);
    }

    /** GET /v2/groups/{groupId}/courses — a group's recommended (published) courses. */
    public function forGroup(int $groupId): JsonResponse
    {
        $this->ensureCoursesFeature();
        $userId = $this->getOptionalUserId() ?? $this->resolveSanctumUserOptionally();

        if (!GroupAccessService::canViewMemberContent($groupId, $userId)) {
            return $this->respondWithData([]);
        }

        return $this->respondWithData(CourseGroupService::coursesForGroup($groupId, $userId));
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

    private function requireManageableGroup(int $groupId, int $userId): ?JsonResponse
    {
        if (!Group::where('id', $groupId)->exists()) {
            return $this->respondWithError('RESOURCE_NOT_FOUND', __('api.group_not_found'), null, 404);
        }

        if (!GroupAccessService::canIntegrate($groupId, $userId)) {
            return $this->respondWithError('FORBIDDEN', __('api.group_modify_forbidden'), null, 403);
        }

        return null;
    }
}
