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

class ProcessPodcastEpisodeMedia implements ShouldQueue
{
    use Dispatchable;
    use InteractsWithQueue;
    use Queueable;
    use SerializesModels;

    public function __construct(
        public int $tenantId,
        public int $episodeId
    ) {
    }

    public function handle(): void
    {
        if (!TenantContext::setById($this->tenantId)) {
            return;
        }

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
    }
}
