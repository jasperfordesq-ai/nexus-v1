<?php
// Copyright © 2024–2026 Jasper Ford
// SPDX-License-Identifier: AGPL-3.0-or-later
// Author: Jasper Ford
// See NOTICE file for attribution and acknowledgements.

namespace App\Http\Controllers\Api\Concerns;

use App\Core\TenantContext;
use App\Models\Course;
use App\Services\CourseInstructorService;
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
}
