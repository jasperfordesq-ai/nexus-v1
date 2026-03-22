<?php
// Copyright © 2024–2026 Jasper Ford
// SPDX-License-Identifier: AGPL-3.0-or-later
// Author: Jasper Ford
// See NOTICE file for attribution and acknowledgements.

namespace App\Events;

use App\Models\JobVacancy;
use App\Models\User;
use Illuminate\Foundation\Events\Dispatchable;
use Illuminate\Queue\SerializesModels;

/**
 * Fired when a new job vacancy is created with status = 'open'.
 *
 * Does not broadcast — triggers the NotifyJobAlertSubscribers listener only.
 */
class JobVacancyCreated
{
    use Dispatchable, SerializesModels;

    public function __construct(
        public readonly JobVacancy $vacancy,
        public readonly User $creator,
        public readonly int $tenantId,
    ) {}
}
