<?php
// Copyright © 2024–2026 Jasper Ford
// SPDX-License-Identifier: AGPL-3.0-or-later
// Author: Jasper Ford
// See NOTICE file for attribution and acknowledgements.

declare(strict_types=1);

namespace App\Observers;

use App\Models\Course;
use App\Models\CourseLesson;
use App\Observers\Concerns\InvalidatesPrerenderContent;
use Illuminate\Contracts\Events\ShouldHandleEventsAfterCommit;

final class CourseLessonPrerenderObserver implements ShouldHandleEventsAfterCommit
{
    use InvalidatesPrerenderContent;

    public function saved(CourseLesson $lesson): void
    {
        $this->refresh($lesson, 'saved');
    }

    public function deleted(CourseLesson $lesson): void
    {
        $this->refresh($lesson, 'deleted');
    }

    private function refresh(CourseLesson $lesson, string $event): void
    {
        $tenantId = (int) ($lesson->tenant_id ?? 0);
        $courseIds = $this->originalAndCurrentId($lesson, 'course_id');
        $slugs = $tenantId > 0 && $courseIds !== []
            ? Course::withoutGlobalScopes()
                ->where('tenant_id', $tenantId)
                ->whereIn('id', $courseIds)
                ->pluck('slug')
                ->filter()
                ->map(static fn (mixed $slug): string => (string) $slug)
                ->values()
                ->all()
            : [];

        $routes = array_map(static fn (string $slug): string => "/courses/{$slug}", $slugs);
        $this->refreshPrerenderRoutes($lesson, $routes, $event);
    }
}
