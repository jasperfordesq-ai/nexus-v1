<?php
// Copyright © 2024–2026 Jasper Ford
// SPDX-License-Identifier: AGPL-3.0-or-later
// Author: Jasper Ford
// See NOTICE file for attribution and acknowledgements.

declare(strict_types=1);

namespace App\Services;

use App\Core\ImageUploader;
use App\Core\TenantContext;
use App\Jobs\CleanupPodcastMedia;
use App\Models\PodcastEpisode;
use App\Models\PodcastMediaCleanupTask;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Storage;
use RuntimeException;
use Throwable;

/**
 * Durable storage-deletion outbox for podcast media and artwork.
 *
 * A domain row can be deleted only after an entry containing the last storage
 * pointer is committed in the same transaction. Queue failure therefore never
 * turns surviving bytes into an untracked orphan. Rejected episode media keeps
 * its hidden pointer until this service proves the object is gone.
 */
final class PodcastMediaCleanupService
{
    public const KIND_STORAGE = 'storage';
    public const KIND_PODCAST_IMAGE = 'podcast_image';

    /**
     * Record an audio object for cleanup and schedule it only after commit.
     */
    public function enqueueStorageObject(
        string $disk,
        string $path,
        string $reason,
        ?int $sourceEpisodeId = null,
    ): PodcastMediaCleanupTask {
        return $this->enqueue(
            self::KIND_STORAGE,
            $disk,
            $path,
            $reason,
            $sourceEpisodeId,
        );
    }

    /** Record a current-tenant podcast image for cleanup after commit. */
    public function enqueuePodcastImage(string $path, string $reason): ?PodcastMediaCleanupTask
    {
        $safePath = PodcastService::safePodcastArtworkPath($path);
        if ($safePath === null) {
            return null;
        }

        return $this->enqueue(
            self::KIND_PODCAST_IMAGE,
            null,
            $safePath,
            $reason,
            null,
        );
    }

    /** Delete one object and complete its ledger entry. Throws for queue retry. */
    public function process(int $taskId): void
    {
        $task = DB::transaction(function () use ($taskId): ?PodcastMediaCleanupTask {
            /** @var PodcastMediaCleanupTask|null $task */
            $task = PodcastMediaCleanupTask::query()->lockForUpdate()->find($taskId);
            if ($task === null || $task->status === 'completed') {
                return null;
            }

            // A duplicate queue delivery must not race the worker that already
            // owns this entry. Stale processing claims are reclaimable.
            if ($task->status === 'processing'
                && $task->updated_at !== null
                && $task->updated_at->isAfter(now()->subMinutes(15))) {
                return null;
            }

            $task->status = 'processing';
            $task->attempts = (int) $task->attempts + 1;
            $task->last_error = null;
            $task->save();

            return $task;
        });

        if ($task === null) {
            return;
        }

        try {
            $this->deleteTaskAsset($task);
        } catch (Throwable $exception) {
            $this->releaseForRetry($task, $exception);
            throw $exception;
        }

        DB::transaction(function () use ($task): void {
            /** @var PodcastMediaCleanupTask|null $locked */
            $locked = PodcastMediaCleanupTask::query()->lockForUpdate()->find($task->id);
            if ($locked === null) {
                throw new RuntimeException('Podcast media cleanup ledger entry disappeared');
            }

            if ($locked->source_episode_id !== null
                && $locked->kind === self::KIND_STORAGE) {
                PodcastEpisode::query()
                    ->whereKey((int) $locked->source_episode_id)
                    ->where('audio_storage_disk', (string) $locked->disk)
                    ->where('audio_storage_path', $locked->path)
                    ->update([
                        'audio_storage_disk' => null,
                        'audio_storage_path' => null,
                        'updated_at' => now(),
                    ]);
            }

            $locked->status = 'completed';
            $locked->completed_at = now();
            $locked->available_at = null;
            $locked->last_error = null;
            $locked->save();
        });
    }

    /**
     * Dispatch pending entries and reclaim workers that died while processing.
     *
     * @return int number of jobs dispatched
     */
    public function dispatchDue(int $limit = 100): int
    {
        $limit = max(1, min(1000, $limit));
        $staleBefore = now()->subMinutes(15);

        $tasks = PodcastMediaCleanupTask::withoutGlobalScopes()
            ->where(function ($query) use ($staleBefore): void {
                $query->where(function ($pending): void {
                    $pending->where('status', 'pending')
                        ->where(function ($due): void {
                            $due->whereNull('available_at')->orWhere('available_at', '<=', now());
                        });
                })->orWhere(function ($processing) use ($staleBefore): void {
                    $processing->whereIn('status', ['queued', 'processing'])
                        ->where('updated_at', '<=', $staleBefore);
                });
            })
            ->orderBy('id')
            ->limit($limit)
            ->get(['id', 'tenant_id']);

        $dispatched = 0;
        foreach ($tasks as $task) {
            $claimed = PodcastMediaCleanupTask::withoutGlobalScopes()
                ->whereKey($task->id)
                ->where(function ($query) use ($staleBefore): void {
                    $query->where(function ($pending): void {
                        $pending->where('status', 'pending')
                            ->where(function ($due): void {
                                $due->whereNull('available_at')->orWhere('available_at', '<=', now());
                            });
                    })
                        ->orWhere(function ($stale) use ($staleBefore): void {
                            $stale->whereIn('status', ['queued', 'processing'])
                                ->where('updated_at', '<=', $staleBefore);
                        });
                })
                ->update(['status' => 'queued', 'updated_at' => now()]);

            if ($claimed !== 1) {
                continue;
            }

            try {
                CleanupPodcastMedia::dispatch((int) $task->tenant_id, (int) $task->id);
                $dispatched++;
            } catch (Throwable $exception) {
                PodcastMediaCleanupTask::withoutGlobalScopes()
                    ->whereKey($task->id)
                    ->update([
                        'status' => 'pending',
                        'available_at' => now()->addMinute(),
                        'last_error' => mb_substr($exception->getMessage(), 0, 2000),
                        'updated_at' => now(),
                    ]);
                Log::warning('Podcast cleanup job dispatch failed', [
                    'task_id' => $task->id,
                    'error' => $exception->getMessage(),
                ]);
            }
        }

        return $dispatched;
    }

    /** Keep a terminally failed queue item eligible for the scheduled sweeper. */
    public function releaseAfterTerminalFailure(int $taskId, Throwable $exception): void
    {
        PodcastMediaCleanupTask::query()
            ->whereKey($taskId)
            ->where('status', '!=', 'completed')
            ->update([
                'status' => 'pending',
                'available_at' => now()->addMinutes(15),
                'last_error' => mb_substr($exception->getMessage(), 0, 2000),
                'updated_at' => now(),
            ]);
    }

    private function enqueue(
        string $kind,
        ?string $disk,
        string $path,
        string $reason,
        ?int $sourceEpisodeId,
    ): PodcastMediaCleanupTask {
        $tenantId = TenantContext::getId();
        if ($path === '') {
            throw new RuntimeException('Podcast media cleanup path is required');
        }

        $assetKey = hash('sha256', $kind . "\0" . ($disk ?? '') . "\0" . $path);
        /** @var PodcastMediaCleanupTask $task */
        $task = PodcastMediaCleanupTask::query()->updateOrCreate(
            ['asset_key' => $assetKey],
            [
                'kind' => $kind,
                'disk' => $disk,
                'path' => $path,
                'source_episode_id' => $sourceEpisodeId,
                'reason' => mb_substr($reason, 0, 50),
                'status' => 'pending',
                'available_at' => now(),
                'last_error' => null,
                'completed_at' => null,
            ],
        );

        DB::afterCommit(function () use ($tenantId, $task): void {
            try {
                CleanupPodcastMedia::dispatch($tenantId, (int) $task->id);
            } catch (Throwable $exception) {
                // The committed ledger is the source of truth. The scheduled
                // dispatcher will retry even if the queue broker is down now.
                Log::warning('Podcast cleanup initial dispatch failed', [
                    'task_id' => $task->id,
                    'tenant_id' => $tenantId,
                    'error' => $exception->getMessage(),
                ]);
            }
        });

        return $task;
    }

    private function deleteTaskAsset(PodcastMediaCleanupTask $task): void
    {
        if ($task->kind === self::KIND_STORAGE) {
            $storage = Storage::disk((string) ($task->disk ?: 'local'));
            if (! $storage->exists($task->path)) {
                return;
            }
            if (! $storage->delete($task->path)) {
                throw new RuntimeException('Podcast media storage deletion returned false');
            }

            return;
        }

        if ($task->kind === self::KIND_PODCAST_IMAGE) {
            $physicalPath = base_path('httpdocs' . $task->path);
            if (! is_file($physicalPath)) {
                return;
            }
            if (! ImageUploader::deleteTenantUpload($task->path, 'podcasts')) {
                throw new RuntimeException('Podcast image storage deletion returned false');
            }

            return;
        }

        throw new RuntimeException('Unsupported podcast media cleanup kind');
    }

    private function releaseForRetry(
        PodcastMediaCleanupTask $task,
        Throwable $exception,
    ): void {
        $delaySeconds = min(3600, 60 * (2 ** min(6, max(0, (int) $task->attempts - 1))));
        PodcastMediaCleanupTask::query()
            ->whereKey($task->id)
            ->update([
                'status' => 'pending',
                'available_at' => now()->addSeconds($delaySeconds),
                'last_error' => mb_substr($exception->getMessage(), 0, 2000),
                'updated_at' => now(),
            ]);

        Log::warning('Podcast media cleanup failed; durable retry retained', [
            'task_id' => $task->id,
            'tenant_id' => TenantContext::getId(),
            'kind' => $task->kind,
            'disk' => $task->disk,
            'path' => $task->path,
            'attempts' => $task->attempts,
            'error' => $exception->getMessage(),
        ]);
    }
}
