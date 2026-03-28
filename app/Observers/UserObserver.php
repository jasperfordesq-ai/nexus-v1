<?php
// Copyright © 2024–2026 Jasper Ford
// SPDX-License-Identifier: AGPL-3.0-or-later
// Author: Jasper Ford
// See NOTICE file for attribution and acknowledgements.

namespace App\Observers;

use App\Models\User;
use App\Services\SearchService;
use Illuminate\Support\Facades\Log;

/**
 * Keeps the Meilisearch users index in sync with the users table.
 *
 * Without this observer, user profile updates (name, bio, etc.) are NOT
 * reflected in search results until the next full sync script run.
 */
class UserObserver
{
    public function updated(User $user): void
    {
        // Only re-index if searchable fields changed
        $searchableFields = ['first_name', 'last_name', 'organization_name', 'bio', 'status', 'profile_type', 'avatar_url'];
        $dirty = array_keys($user->getDirty());

        if (empty(array_intersect($dirty, $searchableFields))) {
            return;
        }

        try {
            SearchService::indexUser($user);
        } catch (\Throwable $e) {
            Log::error('UserObserver: failed to re-index updated user', [
                'user_id' => $user->id,
                'error'   => $e->getMessage(),
            ]);
        }
    }

    public function deleted(User $user): void
    {
        try {
            SearchService::removeUser($user->id);
        } catch (\Throwable $e) {
            Log::error('UserObserver: failed to remove deleted user from index', [
                'user_id' => $user->id,
                'error'   => $e->getMessage(),
            ]);
        }
    }
}
