<?php
// Copyright © 2024–2026 Jasper Ford
// SPDX-License-Identifier: AGPL-3.0-or-later
// Author: Jasper Ford
// See NOTICE file for attribution and acknowledgements.

namespace App\Observers;

use App\Jobs\SyncUserSearchIndexJob;
use App\Models\User;
use Illuminate\Support\Facades\Log;

/**
 * Keeps the Meilisearch users index in sync with the users table.
 *
 * Dispatches SyncUserSearchIndexJob (Redis-queued, auto-retrying) rather than
 * calling SearchService directly — so a transient Meilisearch outage during a
 * signup or profile edit doesn't leave the user missing from search forever.
 *
 * Without this observer, user profile updates (name, bio, etc.) are NOT
 * reflected in search results until the next full sync script run.
 */
class UserObserver
{
    /**
     * Fields that, when changed, require a re-index.
     * MUST stay in sync with the document shape built in SearchService::indexUser
     * and the searchable attributes declared in SearchService::ensureIndexes.
     */
    private const SEARCHABLE_FIELDS = [
        'first_name',
        'last_name',
        'organization_name',
        'bio',
        'skills',
        'location',
        'status',
        'profile_type',
        'avatar_url',
    ];

    public function created(User $user): void
    {
        $this->dispatchIndex($user->id, 'created');
    }

    public function updated(User $user): void
    {
        $dirty = array_keys($user->getDirty());
        if (empty(array_intersect($dirty, self::SEARCHABLE_FIELDS))) {
            return;
        }
        $this->dispatchIndex($user->id, 'updated');
    }

    public function deleted(User $user): void
    {
        try {
            SyncUserSearchIndexJob::dispatch($user->id, 'remove');
        } catch (\Throwable $e) {
            Log::error('UserObserver: failed to dispatch remove-from-index job', [
                'user_id' => $user->id,
                'error'   => $e->getMessage(),
            ]);
        }
    }

    private function dispatchIndex(int $userId, string $reason): void
    {
        try {
            SyncUserSearchIndexJob::dispatch($userId, 'index');
        } catch (\Throwable $e) {
            // Dispatching shouldn't normally fail (Redis connection). If it does,
            // log — the periodic sync script is the backstop.
            Log::error('UserObserver: failed to dispatch index job', [
                'user_id' => $userId,
                'reason'  => $reason,
                'error'   => $e->getMessage(),
            ]);
        }
    }
}
