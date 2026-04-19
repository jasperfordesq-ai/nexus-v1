<?php
// Copyright © 2024–2026 Jasper Ford
// SPDX-License-Identifier: AGPL-3.0-or-later
// Author: Jasper Ford
// See NOTICE file for attribution and acknowledgements.

declare(strict_types=1);

namespace App\Jobs;

use App\Models\User;
use App\Services\SearchService;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\Log;

/**
 * SyncUserSearchIndexJob — queue-backed wrapper for Meilisearch user index writes.
 *
 * Dispatched by:
 *  - App\Observers\UserObserver on created / updated / deleted events
 *  - App\Listeners\SendWelcomeNotification on registration
 *
 * Why queue: Meilisearch availability is not guaranteed at the exact moment a
 * DB write happens (container restart, brief network blip). If we called the
 * HTTP client inline we'd just log and give up — the user would be missing
 * from search until the next full sync script run. Queuing lets Laravel retry
 * automatically with exponential backoff.
 *
 * Action is one of:
 *   - "index"  — upsert the user's search document (expects a valid userId)
 *   - "remove" — delete the user's document from the index (expects the userId)
 */
class SyncUserSearchIndexJob implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    /** Attempt count: 3 tries = initial + 2 retries. */
    public int $tries = 3;

    /** Backoff in seconds between retries. */
    public array $backoff = [10, 30];

    /** Hard cap per attempt — Meilisearch writes should be fast. */
    public int $timeout = 15;

    public function __construct(
        public int $userId,
        public string $action = 'index',
    ) {
        if (!in_array($action, ['index', 'remove'], true)) {
            throw new \InvalidArgumentException("Unknown action: {$action}");
        }
        // Use the Queueable trait's setter rather than redeclaring the property
        // (trait and strict-typed property redeclaration conflict in PHP 8.2+).
        $this->onQueue('search');
    }

    public function handle(): void
    {
        if ($this->action === 'remove') {
            SearchService::removeUser($this->userId);
            return;
        }

        // Reload the user from DB — serialised state may be stale and the
        // observer path hands us a fresh model anyway. We want the latest.
        $user = User::query()->withoutGlobalScopes()->find($this->userId);
        if (!$user) {
            // User was deleted between dispatch and execution — clean up the index.
            SearchService::removeUser($this->userId);
            return;
        }

        SearchService::indexUser($user);
    }

    public function failed(\Throwable $e): void
    {
        Log::error('SyncUserSearchIndexJob permanently failed', [
            'user_id' => $this->userId,
            'action'  => $this->action,
            'error'   => $e->getMessage(),
        ]);
    }
}
