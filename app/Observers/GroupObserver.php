<?php
// Copyright © 2024–2026 Jasper Ford
// SPDX-License-Identifier: AGPL-3.0-or-later
// Author: Jasper Ford
// See NOTICE file for attribution and acknowledgements.

namespace App\Observers;

use App\Models\Group;
use App\Services\SearchService;
use Illuminate\Support\Facades\Log;

/**
 * Keeps the Meilisearch groups index in sync with the groups table.
 *
 * - created  → index new group so it appears in search immediately
 * - updated  → re-index so changed fields are searchable
 * - deleted  → remove from index so deleted groups don't appear in results
 */
class GroupObserver
{
    public function created(Group $group): void
    {
        try {
            SearchService::indexGroup($group);
        } catch (\Throwable $e) {
            Log::error('GroupObserver: failed to index new group', [
                'group_id' => $group->id,
                'error'    => $e->getMessage(),
            ]);
        }
    }

    public function updated(Group $group): void
    {
        $searchableFields = ['name', 'description', 'is_active', 'visibility'];
        $dirty = array_keys($group->getDirty());

        if (empty(array_intersect($dirty, $searchableFields))) {
            return;
        }

        try {
            SearchService::indexGroup($group);
        } catch (\Throwable $e) {
            Log::error('GroupObserver: failed to re-index updated group', [
                'group_id' => $group->id,
                'error'    => $e->getMessage(),
            ]);
        }
    }

    public function deleted(Group $group): void
    {
        try {
            SearchService::removeGroup($group->id);
        } catch (\Throwable $e) {
            Log::error('GroupObserver: failed to remove deleted group from index', [
                'group_id' => $group->id,
                'error'    => $e->getMessage(),
            ]);
        }
    }
}
