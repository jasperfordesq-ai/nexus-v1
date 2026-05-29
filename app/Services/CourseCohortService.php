<?php
// Copyright © 2024–2026 Jasper Ford
// SPDX-License-Identifier: AGPL-3.0-or-later
// Author: Jasper Ford
// See NOTICE file for attribution and acknowledgements.

namespace App\Services;

use App\Models\CourseCohort;

/**
 * CourseCohortService — tenant-scoped cohort CRUD for cohort-paced courses.
 */
class CourseCohortService
{
    /**
     * @return array<int,array<string,mixed>>
     */
    public static function forCourse(int $courseId): array
    {
        return CourseCohort::where('course_id', $courseId)
            ->orderBy('start_date')
            ->get()
            ->toArray();
    }

    public static function create(int $courseId, array $data): CourseCohort
    {
        return CourseCohort::create([
            'course_id' => $courseId,
            'name' => trim((string) ($data['name'] ?? '')),
            'start_date' => $data['start_date'] ?? null,
            'end_date' => $data['end_date'] ?? null,
            'capacity' => isset($data['capacity']) ? (int) $data['capacity'] : null,
        ]);
    }

    public static function delete(int $courseId, int $cohortId): bool
    {
        $cohort = CourseCohort::where('id', $cohortId)->where('course_id', $courseId)->first();
        if (!$cohort) {
            return false;
        }
        return (bool) $cohort->delete();
    }
}
