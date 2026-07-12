<?php
// Copyright © 2024–2026 Jasper Ford
// SPDX-License-Identifier: AGPL-3.0-or-later
// Author: Jasper Ford
// See NOTICE file for attribution and acknowledgements.

namespace App\Observers;

use App\Enums\GroupStatus;
use App\Models\Group;
use App\Observers\Concerns\IndexesEmbeddings;
use App\Services\GroupSearchService;
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
    use IndexesEmbeddings;

    public function created(Group $group): void
    {
        if ($group->status !== GroupStatus::Active) {
            $this->removeFromDiscovery($group);
            return;
        }

        try {
            SearchService::indexGroup($group);
        } catch (\Throwable $e) {
            Log::error('GroupObserver: failed to index new group', [
                'group_id' => $group->id,
                'error'    => $e->getMessage(),
            ]);
        }
        $this->reindexEmbedding($group, 'group');
    }

    public function updated(Group $group): void
    {
        $searchableFields = ['name', 'description', 'status', 'is_active', 'visibility'];
        $dirty = array_keys($group->getDirty());

        if (empty(array_intersect($dirty, $searchableFields))) {
            return;
        }

        if ($group->status !== GroupStatus::Active) {
            $this->removeFromDiscovery($group);
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
        $this->reindexEmbedding($group, 'group');
    }

    public function deleted(Group $group): void
    {
        $this->removeFromDiscovery($group);
    }

    private function removeFromDiscovery(Group $group): void
    {
        GroupSearchService::removeGroupContent((int) $group->id);

        try {
            SearchService::removeGroup($group->id);
        } catch (\Throwable $e) {
            Log::error('GroupObserver: failed to remove deleted group from index', [
                'group_id' => $group->id,
                'error'    => $e->getMessage(),
            ]);
        }
        $this->deleteEmbedding($group, 'group');
    }
}
