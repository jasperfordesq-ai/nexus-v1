<?php
// Copyright © 2024–2026 Jasper Ford
// SPDX-License-Identifier: AGPL-3.0-or-later
// Author: Jasper Ford
// See NOTICE file for attribution and acknowledgements.

namespace App\Jobs;

use App\Core\TenantContext;
use App\Models\PodcastEpisode;
use App\Services\PodcastMediaAnalyzer;
use App\Services\PodcastMediaCleanupService;
use App\Services\PodcastMediaScanner;
use App\Services\PodcastService;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Storage;

/**
 * Post-upload pipeline for hosted podcast audio:
 *
 *  1. Malware scan via ClamAV when CLAMAV_ADDRESS is configured. Infected
 *     uploads are quarantined (made private immediately, with deletion tracked
 *     in a durable retry ledger) and never served. Without a scanner the status stays honest:
 *     'scan_unavailable', never 'clean'.
 *  2. Content analysis via getID3: verifies the bytes really are audio
 *     (the upload path only checks the client-supplied MIME type) and
 *     auto-detects duration when the creator didn't supply one.
 *
 * Waveform generation (media_waveform_json) remains deferred — it needs
 * ffmpeg/audiowaveform infrastructure that pure PHP cannot provide.
 */
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

                $needsScan = $episode->media_scan_status === 'pending';
                $needsProcessing = in_array($episode->media_processing_status, ['pending', 'ready_for_processing'], true);
                if (!$needsScan && !$needsProcessing) {
                    return;
                }

                [$localPath, $isTempCopy] = $this->resolveLocalCopy($episode);
                if ($localPath === null) {
                    $episode->media_processing_status = 'failed';
                    $episode->media_failure_reason = 'source_missing';
                    if ($needsScan) {
                        $episode->media_scan_status = 'scan_unavailable';
                    }
                    $episode->save();
                    PodcastService::reconcileMediaLifecycle($episode);

                    return;
                }

                try {
                    if ($needsScan) {
                        $verdict = PodcastMediaScanner::isConfigured()
                            ? PodcastMediaScanner::scan($localPath)
                            : 'unavailable';

                        if ($verdict === 'infected') {
                            $this->quarantineInfectedEpisode($episode);

                            return;
                        }

                        $episode->media_scan_status = $verdict === 'clean' ? 'clean' : 'scan_unavailable';
                    }

                    if ($needsProcessing) {
                        $analysis = PodcastMediaAnalyzer::analyze($localPath);
                        if (!$analysis['is_audio']) {
                            $this->quarantineRejectedEpisode($episode, 'not_audio');
                            return;
                        } else {
                            if (!$episode->duration_seconds && $analysis['duration_seconds']) {
                                $episode->duration_seconds = $analysis['duration_seconds'];
                                $episode->media_duration_source = 'detected';
                            } elseif ($episode->duration_seconds) {
                                $episode->media_duration_source = $episode->media_duration_source ?: 'creator';
                            }
                            $episode->media_processing_status = 'complete';
                            $episode->media_failure_reason = null;
                        }
                    }

                    $episode->save();
                    PodcastService::reconcileMediaLifecycle($episode);
                } finally {
                    if ($isTempCopy && is_file($localPath)) {
                        @unlink($localPath);
                    }
                }
            });
        } catch (\InvalidArgumentException) {
            // Bad/missing tenant context — there is nothing to process; do not retry.
            return;
        }
        // Any other throwable propagates so the queue retries transient failures;
        // once $tries is exhausted the job lands in failed() below.
    }

    /**
     * Materialise the stored object as a local file for scanning/analysis.
     * Local disk: use the real path directly. Cloud disks: stream to a temp
     * file the caller must clean up.
     *
     * @return array{0: ?string, 1: bool} [absolute path or null, is temp copy]
     */
    private function resolveLocalCopy(PodcastEpisode $episode): array
    {
        $disk = (string) ($episode->audio_storage_disk ?: 'local');
        $path = (string) $episode->audio_storage_path;

        try {
            if ($disk === 'local') {
                $absolute = Storage::disk('local')->path($path);

                return [is_file($absolute) ? $absolute : null, false];
            }

            $storage = Storage::disk($disk);
            if (!$storage->exists($path)) {
                return [null, false];
            }

            $stream = $storage->readStream($path);
            if (!is_resource($stream)) {
                return [null, false];
            }

            $temp = tempnam(sys_get_temp_dir(), 'podmedia_');
            if ($temp === false) {
                fclose($stream);

                return [null, false];
            }

            $out = fopen($temp, 'wb');
            if (!is_resource($out)) {
                fclose($stream);
                @unlink($temp);

                return [null, false];
            }

            stream_copy_to_stream($stream, $out);
            fclose($stream);
            fclose($out);

            return [$temp, true];
        } catch (\Throwable $e) {
            Log::warning('ProcessPodcastEpisodeMedia could not materialise media for analysis', [
                'episode_id' => $this->episodeId,
                'disk' => $disk,
                'error' => $e->getMessage(),
            ]);

            return [null, false];
        }
    }

    /**
     * A positive malware verdict makes the episode non-servable and records a
     * durable deletion task. The row keeps status 'infected'/'failed' so
     * moderation can see what happened.
     */
    private function quarantineInfectedEpisode(PodcastEpisode $episode): void
    {
        $this->quarantineRejectedEpisode($episode, 'infected');

        Log::warning('Podcast episode audio failed malware scan; stored object quarantined', [
            'tenant_id' => $this->tenantId,
            'episode_id' => $this->episodeId,
        ]);
    }

    /**
     * Put rejected media in a private, non-servable state and durably enqueue
     * deletion. The hidden pointer remains until deletion is confirmed, so a
     * false/exception from storage can always be retried.
     */
    private function quarantineRejectedEpisode(PodcastEpisode $episode, string $reason): void
    {
        $disk = (string) ($episode->audio_storage_disk ?: 'local');
        $path = (string) $episode->audio_storage_path;

        DB::transaction(function () use ($episode, $reason, $disk, $path): void {
            if ($reason === 'infected') {
                $episode->media_scan_status = 'infected';
            }
            $episode->media_processing_status = 'failed';
            $episode->media_failure_reason = $reason;
            $episode->status = 'draft';
            $episode->audio_url = 'podcast-hosted://quarantined';
            // audio_storage_* are deliberately retained until the cleanup
            // worker proves the bytes are absent. They are hidden by the model.
            $episode->save();

            app(PodcastMediaCleanupService::class)->enqueueStorageObject(
                $disk,
                $path,
                $reason === 'infected' ? 'malware_rejected' : 'content_rejected',
                (int) $episode->id,
            );

            PodcastService::reconcileMediaLifecycle($episode);
        });
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
                    $episode->media_failure_reason = $episode->media_failure_reason ?: 'processing_error';
                    $episode->save();
                    PodcastService::reconcileMediaLifecycle($episode);
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
