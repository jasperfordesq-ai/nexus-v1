<?php
// Copyright © 2024–2026 Jasper Ford
// SPDX-License-Identifier: AGPL-3.0-or-later
// Author: Jasper Ford
// See NOTICE file for attribution and acknowledgements.

namespace App\Observers;

use App\Models\Event;
use App\Services\SearchService;
use Illuminate\Support\Facades\Log;

/**
 * Keeps the Meilisearch events index in sync with the events table.
 *
 * - created  → index new event so it appears in search immediately
 * - updated  → re-index so changed fields are searchable
 * - deleted  → remove from index so deleted events don't appear in results
 */
class EventObserver
{
    public function created(Event $event): void
    {
        try {
            SearchService::indexEvent($event);
        } catch (\Throwable $e) {
            Log::error('EventObserver: failed to index new event', [
                'event_id' => $event->id,
                'error'    => $e->getMessage(),
            ]);
        }
    }

    public function updated(Event $event): void
    {
        $searchableFields = ['title', 'description', 'location', 'status', 'start_time', 'allow_remote_attendance'];
        $dirty = array_keys($event->getDirty());

        if (empty(array_intersect($dirty, $searchableFields))) {
            return;
        }

        try {
            SearchService::indexEvent($event);
        } catch (\Throwable $e) {
            Log::error('EventObserver: failed to re-index updated event', [
                'event_id' => $event->id,
                'error'    => $e->getMessage(),
            ]);
        }
    }

    public function deleted(Event $event): void
    {
        try {
            SearchService::removeEvent($event->id);
        } catch (\Throwable $e) {
            Log::error('EventObserver: failed to remove deleted event from index', [
                'event_id' => $event->id,
                'error'    => $e->getMessage(),
            ]);
        }
    }
}
