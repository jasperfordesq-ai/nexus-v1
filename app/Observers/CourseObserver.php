<?php
// Copyright © 2024–2026 Jasper Ford
// SPDX-License-Identifier: AGPL-3.0-or-later
// Author: Jasper Ford
// See NOTICE file for attribution and acknowledgements.

namespace App\Observers;

use App\Models\Course;
use App\Observers\Concerns\IndexesEmbeddings;

/**
 * CourseObserver — keeps a course's AI semantic-search embedding in sync.
 * Mirrors MarketplaceListingObserver. Failures never block the model write.
 */
class CourseObserver
{
    use IndexesEmbeddings;

    public function created(Course $course): void
    {
        $this->reindexEmbedding($course, 'course');
    }

    public function updated(Course $course): void
    {
        $dirty = array_keys($course->getDirty());
        $searchable = ['title', 'summary', 'description', 'status'];
        if (empty(array_intersect($dirty, $searchable))) {
            return;
        }
        $this->reindexEmbedding($course, 'course');
    }

    public function deleted(Course $course): void
    {
        $this->deleteEmbedding($course, 'course');
    }
}
