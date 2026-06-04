<?php
// Copyright © 2024–2026 Jasper Ford
// SPDX-License-Identifier: AGPL-3.0-or-later
// Author: Jasper Ford
// See NOTICE file for attribution and acknowledgements.

namespace App\Http\Controllers\Api\Concerns;

use App\Core\TenantContext;
use App\Models\Course;
use App\Models\CourseGroupLink;
use App\Models\User;
use App\Services\CourseInstructorService;
use App\Services\GroupService;
use Illuminate\Http\Exceptions\HttpResponseException;

/**
 * Shared guards for the Courses module controllers:
 * feature gate, instructor/admin authorization, ownership, and 404 lookups.
 */
trait InteractsWithCourses
{
    /**
     * Ensure the courses feature is enabled for the current tenant.
     *
     * @throws HttpResponseException
     */
    protected function ensureCoursesFeature(): void
    {
        if (!TenantContext::hasFeature('courses')) {
            throw new HttpResponseException(
                $this->respondWithError('FEATURE_DISABLED', __('api_controllers_2.courses.feature_disabled'), null, 403)
            );
        }
    }

    /**
     * Find a course by id or throw a 404 response.
     *
     * @throws HttpResponseException
     */
    protected function findCourseOrFail(int $id): Course
    {
        $course = Course::find($id);

        if (!$course) {
            throw new HttpResponseException(
                $this->respondWithError('RESOURCE_NOT_FOUND', __('api_controllers_2.courses.not_found'), null, 404)
            );
        }

        return $course;
    }

    /**
     * Require that the caller may author courses.
     *
     * Authoring is OPEN to any authenticated member by default — the platform
     * owner explicitly wants normal users to be able to publish courses. A tenant
     * may optionally restrict authoring to granted instructors + admins by turning
     * the `courses.allow_member_authoring` feature option OFF; in that case we fall
     * back to an instructor-or-admin check.
     *
     * Note: this only governs WHO can create a course. Editing an existing course
     * is still restricted to its owner or an admin via ensureCourseOwnerOrAdmin(),
     * and publishing still passes through tenant moderation.
     *
     * @throws HttpResponseException
     */
    protected function requireCourseAuthor(): int
    {
        $userId = $this->requireAuth();

        // Open authoring (default). Any authenticated member may author unless a
        // tenant has explicitly disabled member authoring.
        $allowMembers = filter_var(
            TenantContext::getSetting('courses.allow_member_authoring', true),
            FILTER_VALIDATE_BOOLEAN
        );
        if ($allowMembers) {
            return $userId;
        }

        // Restricted mode: instructor grant OR admin role.
        if (CourseInstructorService::isInstructor($userId)) {
            return $userId;
        }

        return $this->requireAdmin();
    }

    /**
     * @deprecated Use requireCourseAuthor(). Retained for compatibility.
     * @throws HttpResponseException
     */
    protected function requireInstructorOrAdmin(): int
    {
        return $this->requireCourseAuthor();
    }

    /**
     * Ensure the caller owns the course OR is an admin. Used for edit/publish/delete.
     *
     * @throws HttpResponseException
     */
    protected function ensureCourseOwnerOrAdmin(Course $course, int $userId): void
    {
        if ((int) $course->author_user_id === $userId) {
            return;
        }

        // Non-owner: only admins may modify someone else's course.
        $this->requireAdmin();
    }

    /**
     * Ensure a course may be viewed by the current public/member audience.
     *
     * Draft, archived, rejected, flagged, member-only, and group-only courses all
     * use a not-found response when the caller is outside the intended audience.
     * That avoids leaking private course existence through direct URLs.
     *
     * @throws HttpResponseException
     */
    protected function ensureCourseViewable(Course $course, ?int $userId): void
    {
        if ($this->canViewCourse($course, $userId)) {
            return;
        }

        throw new HttpResponseException(
            $this->respondWithError('RESOURCE_NOT_FOUND', __('api_controllers_2.courses.not_found'), null, 404)
        );
    }

    protected function canViewCourse(Course $course, ?int $userId): bool
    {
        if ($userId !== null && $this->canManageCourseAsUser($course, $userId)) {
            return true;
        }

        if ($course->status !== 'published' || $course->moderation_status !== 'approved') {
            return false;
        }

        return $this->canViewPublishedCourseAudience($course, $userId);
    }

    protected function canViewPublishedCourseAudience(Course $course, ?int $userId): bool
    {
        if ($course->visibility === 'public') {
            return true;
        }

        if ($course->visibility === 'members') {
            return $userId !== null;
        }

        if ($course->visibility === 'group') {
            return $userId !== null && $this->isCourseLinkedGroupMember($course, $userId);
        }

        return false;
    }

    protected function canManageCourseAsUser(Course $course, int $userId): bool
    {
        return (int) $course->author_user_id === $userId || $this->isCourseAdminUser($userId);
    }

    protected function isCourseAdminUser(int $userId): bool
    {
        $user = User::query()
            ->select(['id', 'role', 'is_admin', 'is_super_admin', 'is_tenant_super_admin', 'is_god'])
            ->find($userId);

        if (!$user) {
            return false;
        }

        $role = $user->role ?? 'member';

        return in_array($role, ['admin', 'tenant_admin', 'super_admin', 'god'], true)
            || (bool) ($user->is_admin ?? false)
            || (bool) ($user->is_super_admin ?? false)
            || (bool) ($user->is_tenant_super_admin ?? false)
            || (bool) ($user->is_god ?? false);
    }

    private function isCourseLinkedGroupMember(Course $course, int $userId): bool
    {
        $groupIds = CourseGroupLink::where('course_id', $course->id)
            ->pluck('group_id')
            ->map(fn ($value) => (int) $value)
            ->all();

        foreach ($groupIds as $groupId) {
            if (GroupService::isActiveMember($groupId, $userId) || GroupService::canModify($groupId, $userId)) {
                return true;
            }
        }

        return false;
    }
}
