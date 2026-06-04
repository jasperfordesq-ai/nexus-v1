<?php
// Copyright © 2024–2026 Jasper Ford
// SPDX-License-Identifier: AGPL-3.0-or-later
// Author: Jasper Ford
// See NOTICE file for attribution and acknowledgements.

namespace App\Services;

use App\Core\TenantContext;
use App\Models\Course;
use App\Models\CourseGroupLink;
use App\Models\Group;

/**
 * CourseGroupService — links courses to community groups so a group can surface
 * its recommended courses. Tenant-scoped via the CourseGroupLink model.
 */
class CourseGroupService
{
    /** Attach a course to a group (idempotent). */
    public static function attach(int $courseId, int $groupId): CourseGroupLink
    {
        if (!Group::where('id', $groupId)->exists()) {
            throw new \RuntimeException('group_not_found');
        }

        $link = CourseGroupLink::where('course_id', $courseId)
            ->where('group_id', $groupId)
            ->first();

        if ($link) {
            return $link;
        }

        $link = new CourseGroupLink([
            'course_id' => $courseId,
            'group_id' => $groupId,
        ]);
        $link->tenant_id = (int) TenantContext::getId();
        $link->save();

        return $link;
    }

    public static function detach(int $courseId, int $groupId): void
    {
        CourseGroupLink::where('course_id', $courseId)
            ->where('group_id', $groupId)
            ->delete();
    }

    /**
     * Published courses linked to a group (the group's recommended courses).
     *
     * @return array<int,array<string,mixed>>
     */
    public static function coursesForGroup(int $groupId, ?int $viewerUserId = null): array
    {
        $courseIds = CourseGroupLink::where('group_id', $groupId)->pluck('course_id')->all();
        if (!$courseIds) {
            return [];
        }

        $canSeeGroupOnly = $viewerUserId !== null
            && (GroupService::isActiveMember($groupId, $viewerUserId) || GroupService::canModify($groupId, $viewerUserId));

        return Course::whereIn('id', $courseIds)
            ->published()
            ->where(function ($query) use ($viewerUserId, $canSeeGroupOnly) {
                $query->where('visibility', 'public');

                if ($viewerUserId !== null) {
                    $query->orWhere('visibility', 'members');
                }

                if ($canSeeGroupOnly) {
                    $query->orWhere('visibility', 'group');
                }
            })
            ->with(['category:id,name,slug', 'author:id,name,avatar_url'])
            ->orderByDesc('published_at')
            ->get()
            ->toArray();
    }

    /**
     * Group ids a course is linked to.
     *
     * @return array<int,int>
     */
    public static function groupIdsForCourse(int $courseId): array
    {
        return CourseGroupLink::where('course_id', $courseId)->pluck('group_id')->map(fn ($v) => (int) $v)->all();
    }
}
