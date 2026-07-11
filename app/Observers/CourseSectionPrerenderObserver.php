<?php
// Copyright © 2024–2026 Jasper Ford
// SPDX-License-Identifier: AGPL-3.0-or-later
// Author: Jasper Ford
// See NOTICE file for attribution and acknowledgements.

declare(strict_types=1);

namespace App\Observers;

use App\Models\Course;
use App\Models\CourseSection;
use App\Observers\Concerns\InvalidatesPrerenderContent;
use Illuminate\Contracts\Events\ShouldHandleEventsAfterCommit;

final class CourseSectionPrerenderObserver implements ShouldHandleEventsAfterCommit
{
    use InvalidatesPrerenderContent;

    public function saved(CourseSection $section): void
    {
        $this->refresh($section, 'saved');
    }

    public function deleted(CourseSection $section): void
    {
        $this->refresh($section, 'deleted');
    }

    private function refresh(CourseSection $section, string $event): void
    {
        $tenantId = (int) ($section->tenant_id ?? 0);
        $courseIds = $this->originalAndCurrentId($section, 'course_id');
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
        $this->refreshPrerenderRoutes($section, $routes, $event);
    }
}
