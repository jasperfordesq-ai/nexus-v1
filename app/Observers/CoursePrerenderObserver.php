<?php
// Copyright © 2024–2026 Jasper Ford
// SPDX-License-Identifier: AGPL-3.0-or-later
// Author: Jasper Ford
// See NOTICE file for attribution and acknowledgements.

declare(strict_types=1);

namespace App\Observers;

use App\Models\Course;
use App\Observers\Concerns\InvalidatesPrerenderContent;
use Illuminate\Contracts\Events\ShouldHandleEventsAfterCommit;

final class CoursePrerenderObserver implements ShouldHandleEventsAfterCommit
{
    use InvalidatesPrerenderContent;

    public function saved(Course $course): void
    {
        $this->refresh($course, 'saved');
    }

    public function deleted(Course $course): void
    {
        $this->refresh($course, 'deleted');
    }

    private function refresh(Course $course, string $event): void
    {
        $routes = ['/courses'];
        foreach ($this->originalAndCurrentString($course, 'slug') as $slug) {
            $routes[] = "/courses/{$slug}";
        }

        $this->refreshPrerenderRoutes($course, $routes, $event);
    }
}
