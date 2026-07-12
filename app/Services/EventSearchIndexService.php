<?php
// Copyright © 2024–2026 Jasper Ford
// SPDX-License-Identifier: AGPL-3.0-or-later
// Author: Jasper Ford
// See NOTICE file for attribution and acknowledgements.

declare(strict_types=1);

namespace App\Services;

use App\Models\Event;

/** Small injectable boundary around the static shared-search infrastructure. */
class EventSearchIndexService
{
    public function synchronize(Event $event): void
    {
        SearchService::indexEvent($event);
    }

    public function remove(int $eventId): void
    {
        SearchService::removeEvent($eventId);
    }
}
