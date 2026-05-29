<?php
// Copyright © 2024–2026 Jasper Ford
// SPDX-License-Identifier: AGPL-3.0-or-later
// Author: Jasper Ford
// See NOTICE file for attribution and acknowledgements.

namespace App\Services;

use App\Models\CourseSection;

/**
 * CourseSectionService — tenant-scoped section CRUD for the course builder.
 */
class CourseSectionService
{
    public static function create(int $courseId, array $data): CourseSection
    {
        return CourseSection::create([
            'course_id' => $courseId,
            'title' => trim((string) ($data['title'] ?? '')),
            'position' => (int) ($data['position'] ?? self::nextPosition($courseId)),
        ]);
    }

    public static function update(int $id, array $data): ?CourseSection
    {
        $section = CourseSection::find($id);
        if (!$section) {
            return null;
        }

        if (array_key_exists('title', $data)) {
            $section->title = trim((string) $data['title']);
        }
        if (array_key_exists('position', $data)) {
            $section->position = (int) $data['position'];
        }
        $section->save();

        return $section;
    }

    public static function delete(int $id): bool
    {
        $section = CourseSection::find($id);
        if (!$section) {
            return false;
        }

        // Orphan lessons rather than cascade-delete content silently.
        \App\Models\CourseLesson::where('section_id', $id)->update(['section_id' => null]);

        return (bool) $section->delete();
    }

    private static function nextPosition(int $courseId): int
    {
        return (int) CourseSection::where('course_id', $courseId)->max('position') + 1;
    }
}
