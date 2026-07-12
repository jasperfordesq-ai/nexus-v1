<?php
// Copyright © 2024–2026 Jasper Ford
// SPDX-License-Identifier: AGPL-3.0-or-later
// Author: Jasper Ford
// See NOTICE file for attribution and acknowledgements.

declare(strict_types=1);

namespace App\Support\Events;

use App\Models\EventOfflineSyncBatch;
use Illuminate\Support\Collection;

final readonly class EventOfflineSyncStageResult
{
    /** @param Collection<int,\App\Models\EventOfflineSyncItem> $items */
    public function __construct(
        public EventOfflineSyncBatch $batch,
        public Collection $items,
        public bool $staged,
    ) {
    }
}
