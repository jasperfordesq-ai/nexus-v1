<?php
// Copyright © 2024–2026 Jasper Ford
// SPDX-License-Identifier: AGPL-3.0-or-later
// Author: Jasper Ford
// See NOTICE file for attribution and acknowledgements.

declare(strict_types=1);

namespace App\Support\Events;

use App\Models\EventOfflineSyncBatch;
use App\Models\EventOfflineSyncDecision;

final readonly class EventOfflineSyncDecisionResult
{
    public function __construct(
        public EventOfflineSyncDecision $decision,
        public EventOfflineSyncBatch $batch,
        public bool $recorded,
    ) {
    }
}
