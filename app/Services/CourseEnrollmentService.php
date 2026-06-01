<?php
// Copyright © 2024–2026 Jasper Ford
// SPDX-License-Identifier: AGPL-3.0-or-later
// Author: Jasper Ford
// See NOTICE file for attribution and acknowledgements.

namespace App\Services;

use App\Models\Course;
use App\Models\CourseEnrollment;
use Carbon\Carbon;
use Illuminate\Support\Facades\DB;

/**
 * CourseEnrollmentService — tenant-scoped enrollment lifecycle.
 * Phase 1 supports free + members-only enrollment. Credit-based enrollment and
 * teach/learn-to-earn rewards are layered in via CourseCreditService in Phase 3.
 */
class CourseEnrollmentService
{
    public static function isEnrolled(int $courseId, int $userId): bool
    {
        return CourseEnrollment::where('course_id', $courseId)
            ->where('user_id', $userId)
            ->exists();
    }

    public static function find(int $courseId, int $userId): ?CourseEnrollment
    {
        return CourseEnrollment::where('course_id', $courseId)
            ->where('user_id', $userId)
            ->first();
    }

    /**
     * Enroll a user. Idempotent — returns the existing enrollment if present.
     */
    public static function enroll(int $courseId, int $userId, ?int $cohortId = null): CourseEnrollment
    {
        $existing = self::find($courseId, $userId);
        if ($existing) {
            return $existing;
        }

        $enrollment = CourseEnrollment::create([
            'course_id' => $courseId,
            'user_id' => $userId,
            'cohort_id' => $cohortId,
            'status' => 'active',
            'progress_percent' => 0,
            'enrolled_at' => Carbon::now(),
        ]);

        Course::where('id', $courseId)->increment('enrollment_count');

        CourseNotificationService::enrolled($courseId, $userId);

        return $enrollment;
    }

    /**
     * Enroll a user and charge any configured course credit cost exactly once.
     */
    public static function enrollWithPayment(Course $course, int $userId, ?int $cohortId = null): CourseEnrollment
    {
        $existing = self::find((int) $course->id, $userId);
        if ($existing) {
            return $existing;
        }

        return DB::transaction(function () use ($course, $userId, $cohortId) {
            $payment = CourseCreditService::chargeEnrollment($course, $userId);
            $cost = (float) $course->credit_cost;

            if ($cost > 0 && (int) $course->author_user_id !== $userId && empty($payment['charged'])) {
                throw new \RuntimeException((string) ($payment['reason'] ?? 'insufficient_credits'));
            }

            $enrollment = self::enroll((int) $course->id, $userId, $cohortId);
            $enrollment->credits_paid = (float) ($payment['amount'] ?? 0);
            $enrollment->save();

            return $enrollment;
        });
    }

    /**
     * Courses the user is enrolled in, with the course attached.
     *
     * @return array<int,array<string,mixed>>
     */
    public static function forUser(int $userId): array
    {
        return CourseEnrollment::where('user_id', $userId)
            ->with(['course:id,title,slug,cover_image,level,author_user_id'])
            ->orderByDesc('last_accessed_at')
            ->orderByDesc('enrolled_at')
            ->get()
            ->toArray();
    }

    /**
     * Enrollment roster for a course (instructor view).
     *
     * @return array<int,array<string,mixed>>
     */
    public static function roster(int $courseId): array
    {
        return CourseEnrollment::where('course_id', $courseId)
            ->with(['user:id,name,avatar_url'])
            ->orderByDesc('enrolled_at')
            ->get()
            ->toArray();
    }

    public static function drop(int $courseId, int $userId): bool
    {
        $enrollment = self::find($courseId, $userId);
        if (!$enrollment) {
            return false;
        }

        $enrollment->status = 'dropped';
        $enrollment->save();

        return true;
    }
}
