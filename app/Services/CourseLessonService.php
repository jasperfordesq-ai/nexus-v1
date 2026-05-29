<?php
// Copyright © 2024–2026 Jasper Ford
// SPDX-License-Identifier: AGPL-3.0-or-later
// Author: Jasper Ford
// See NOTICE file for attribution and acknowledgements.

namespace App\Services;

use App\Models\CourseLesson;

/**
 * CourseLessonService — tenant-scoped lesson CRUD for the course builder.
 */
class CourseLessonService
{
    private const FIELDS = [
        'section_id', 'title', 'content_type', 'body', 'video_url',
        'attachment_url', 'embed_url', 'position', 'min_watch_percent',
        'drip_type', 'drip_offset_days', 'drip_date', 'is_preview',
    ];

    public static function create(int $courseId, array $data): CourseLesson
    {
        $payload = [
            'course_id' => $courseId,
            'title' => trim((string) ($data['title'] ?? '')),
            'content_type' => $data['content_type'] ?? 'text',
            'position' => (int) ($data['position'] ?? self::nextPosition($courseId)),
        ];

        foreach (self::FIELDS as $field) {
            if ($field !== 'title' && array_key_exists($field, $data)) {
                $payload[$field] = $data[$field];
            }
        }

        return CourseLesson::create($payload);
    }

    public static function update(int $id, array $data): ?CourseLesson
    {
        $lesson = CourseLesson::find($id);
        if (!$lesson) {
            return null;
        }

        if (array_key_exists('title', $data)) {
            $lesson->title = trim((string) $data['title']);
        }
        foreach (self::FIELDS as $field) {
            if ($field !== 'title' && array_key_exists($field, $data)) {
                $lesson->{$field} = $data[$field];
            }
        }
        $lesson->save();

        return $lesson;
    }

    public static function delete(int $id): bool
    {
        $lesson = CourseLesson::find($id);
        if (!$lesson) {
            return false;
        }

        return (bool) $lesson->delete();
    }

    /**
     * Ordered count of lessons in a course (used for progress percentage).
     */
    public static function countForCourse(int $courseId): int
    {
        return CourseLesson::where('course_id', $courseId)->count();
    }

    private static function nextPosition(int $courseId): int
    {
        return (int) CourseLesson::where('course_id', $courseId)->max('position') + 1;
    }
}
