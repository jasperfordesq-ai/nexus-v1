<?php
// Copyright © 2024–2026 Jasper Ford
// SPDX-License-Identifier: AGPL-3.0-or-later
// Author: Jasper Ford
// See NOTICE file for attribution and acknowledgements.

namespace App\Services;

use App\Models\CourseDiscussion;

/**
 * CourseDiscussionService — threaded per-lesson discussions, tenant-scoped via
 * the CourseDiscussion model's HasTenantScope trait.
 */
class CourseDiscussionService
{
    /**
     * Visible top-level discussions for a lesson, each with its visible replies.
     *
     * @return array<int,array<string,mixed>>
     */
    public static function listForLesson(int $courseId, int $lessonId): array
    {
        $rows = CourseDiscussion::where('course_id', $courseId)
            ->where('lesson_id', $lessonId)
            ->where('status', 'visible')
            ->with('user:id,name,avatar_url')
            ->orderBy('created_at')
            ->get();

        $byParent = [];
        foreach ($rows as $row) {
            $byParent[$row->parent_id ?? 0][] = $row;
        }

        $build = static function ($row) use ($byParent) {
            $data = $row->toArray();
            $data['replies'] = array_map(
                static fn ($r) => $r->toArray(),
                $byParent[$row->id] ?? []
            );
            return $data;
        };

        return array_map($build, $byParent[0] ?? []);
    }

    public static function create(int $courseId, int $lessonId, int $userId, string $body, ?int $parentId = null): CourseDiscussion
    {
        // A reply's parent must belong to the same lesson (defence against cross-thread injection).
        if ($parentId !== null) {
            $parentOk = CourseDiscussion::where('id', $parentId)
                ->where('course_id', $courseId)
                ->where('lesson_id', $lessonId)
                ->exists();
            if (!$parentOk) {
                $parentId = null;
            }
        }

        return CourseDiscussion::create([
            'course_id' => $courseId,
            'lesson_id' => $lessonId,
            'user_id' => $userId,
            'parent_id' => $parentId,
            'body' => $body,
            'status' => 'visible',
        ]);
    }

    public static function find(int $id): ?CourseDiscussion
    {
        return CourseDiscussion::find($id);
    }

    /**
     * Hide a comment (soft moderation). Replies remain but the hidden node is
     * filtered out of learner views.
     */
    public static function setStatus(int $id, string $status): bool
    {
        $row = CourseDiscussion::find($id);
        if (!$row) {
            return false;
        }
        $row->status = $status;
        return (bool) $row->save();
    }

    public static function delete(int $id): bool
    {
        $row = CourseDiscussion::find($id);
        if (!$row) {
            return false;
        }
        // Remove direct replies too.
        CourseDiscussion::where('parent_id', $id)->delete();
        return (bool) $row->delete();
    }
}
