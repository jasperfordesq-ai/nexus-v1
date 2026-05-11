<?php
// Copyright © 2024–2026 Jasper Ford
// SPDX-License-Identifier: AGPL-3.0-or-later
// Author: Jasper Ford
// See NOTICE file for attribution and acknowledgements.

namespace App\Observers;

use App\Models\JobVacancy;
use App\Observers\Concerns\IndexesEmbeddings;

class JobVacancyObserver
{
    use IndexesEmbeddings;

    public function created(JobVacancy $job): void
    {
        $this->reindexEmbedding($job, 'job');
    }

    public function updated(JobVacancy $job): void
    {
        $dirty = array_keys($job->getDirty());
        $searchable = ['title', 'tagline', 'description', 'location', 'skills_required', 'status'];
        if (empty(array_intersect($dirty, $searchable))) {
            return;
        }
        $this->reindexEmbedding($job, 'job');
    }

    public function deleted(JobVacancy $job): void
    {
        $this->deleteEmbedding($job, 'job');
    }
}
