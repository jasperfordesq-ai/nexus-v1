<?php
// Copyright © 2024–2026 Jasper Ford
// SPDX-License-Identifier: AGPL-3.0-or-later
// Author: Jasper Ford
// See NOTICE file for attribution and acknowledgements.

namespace App\Services;

use App\Models\CourseInstructor;
use Carbon\Carbon;

/**
 * CourseInstructorService — manages the per-tenant instructor capability grant.
 * Authoring courses requires either an admin role OR an active instructor grant.
 */
class CourseInstructorService
{
    /**
     * Is the given user a granted instructor in the current tenant?
     */
    public static function isInstructor(int $userId): bool
    {
        return CourseInstructor::where('user_id', $userId)->exists();
    }

    /**
     * Grant the instructor capability. Idempotent.
     */
    public static function grant(int $userId, ?int $grantedBy = null): CourseInstructor
    {
        return CourseInstructor::firstOrCreate(
            ['user_id' => $userId],
            ['granted_by' => $grantedBy, 'granted_at' => Carbon::now()]
        );
    }

    /**
     * Revoke the instructor capability.
     */
    public static function revoke(int $userId): void
    {
        CourseInstructor::where('user_id', $userId)->delete();
    }

    /**
     * List all instructor grants for the current tenant.
     *
     * @return array<int,array<string,mixed>>
     */
    public static function list(): array
    {
        return CourseInstructor::with('user:id,name,avatar_url')
            ->orderByDesc('granted_at')
            ->get()
            ->toArray();
    }
}
