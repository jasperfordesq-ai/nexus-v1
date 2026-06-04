<?php
// Copyright © 2024–2026 Jasper Ford
// SPDX-License-Identifier: AGPL-3.0-or-later
// Author: Jasper Ford
// See NOTICE file for attribution and acknowledgements.

namespace App\Jobs;

use App\Core\TenantContext;
use App\Models\PodcastEpisode;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\Log;

class ProcessPodcastEpisodeMedia implements ShouldQueue
{
    use Dispatchable;
    use InteractsWithQueue;
    use Queueable;
    use SerializesModels;

    /** Cap retries so a permanently-bad upload can't requeue forever. */
    public int $tries = 3;

    /** Seconds to wait between retries (rides out transient storage/DB hiccups). */
    public int $backoff = 30;

    public function __construct(
        public int $tenantId,
        public int $episodeId
    ) {
    }

    public function handle(): void
    {
        try {
            TenantContext::runForTenant($this->tenantId, function (): void {
                $episode = PodcastEpisode::find($this->episodeId);
                if (!$episode || !$episode->audio_storage_path) {
                    return;
                }

                // Provision hook: real scanners/transcoders can replace this once
                // cloud/object storage processing infrastructure is configured. Until
                // then, never label unscanned media as clean.
                $episode->media_scan_status = $episode->media_scan_status === 'pending' ? 'scan_unavailable' : $episode->media_scan_status;
                $episode->media_processing_status = $episode->media_processing_status === 'pending' ? 'ready_for_processing' : $episode->media_processing_status;
                $episode->media_duration_source = $episode->duration_seconds ? ($episode->media_duration_source ?: 'creator') : $episode->media_duration_source;
                $episode->save();
            });
        } catch (\InvalidArgumentException) {
            // Bad/missing tenant context — there is nothing to process; do not retry.
            return;
        }
        // Any other throwable propagates so the queue retries transient failures;
        // once $tries is exhausted the job lands in failed() below.
    }

    /**
     * After all retries are exhausted, surface the stuck media as "failed" so it is
     * visible to operators instead of silently sitting in pending/ready_for_processing.
     */
    public function failed(\Throwable $e): void
    {
        try {
            TenantContext::runForTenant($this->tenantId, function (): void {
                $episode = PodcastEpisode::find($this->episodeId);
                if ($episode && in_array($episode->media_processing_status, ['pending', 'ready_for_processing'], true)) {
                    $episode->media_processing_status = 'failed';
                    $episode->save();
                }
            });
        } catch (\Throwable) {
            // The failure handler must never throw.
        }

        Log::warning('ProcessPodcastEpisodeMedia permanently failed', [
            'tenant_id' => $this->tenantId,
            'episode_id' => $this->episodeId,
            'error' => $e->getMessage(),
        ]);
    }
}
