<?php
// Copyright © 2024–2026 Jasper Ford
// SPDX-License-Identifier: AGPL-3.0-or-later
// Author: Jasper Ford
// See NOTICE file for attribution and acknowledgements.

namespace Tests\Laravel\Unit\Jobs;

use App\Core\TenantContext;
use App\Jobs\CleanupPodcastMedia;
use App\Jobs\ProcessPodcastEpisodeMedia;
use App\Models\PodcastEpisode;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Queue;
use Illuminate\Support\Facades\Storage;
use Tests\Laravel\TestCase;

/**
 * ProcessPodcastEpisodeMediaTest
 *
 * Tests the real media pipeline: getID3 content analysis (genuine-audio
 * verification + duration auto-detection), honest scan statuses without a
 * scanner, source-missing failure, and the failed() handler. Audio bytes are
 * generated in-test (a minimal valid PCM WAV) so no binary fixtures are
 * committed; genuinely-not-audio content is a run of zero bytes.
 */
class ProcessPodcastEpisodeMediaTest extends TestCase
{
    use DatabaseTransactions;

    private const TENANT_ID = 2;

    private int $showId;
    private int $userId;

    protected function setUp(): void
    {
        parent::setUp();
        TenantContext::setById(self::TENANT_ID);
        Storage::fake('local');

        // Seed a minimal user (author) for the FK constraint on podcast_shows.
        $this->userId = DB::table('users')->insertGetId([
            'tenant_id'  => self::TENANT_ID,
            'name'       => 'PodTestUser',
            'first_name' => 'Pod',
            'last_name'  => 'Test',
            'email'      => 'podtest.' . uniqid('', true) . '@example.test',
            'status'     => 'active',
            'balance'    => 0,
            'role'       => 'member',
            'is_approved'=> 1,
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        // Seed a minimal podcast_shows row.
        $this->showId = DB::table('podcast_shows')->insertGetId([
            'tenant_id'    => self::TENANT_ID,
            'owner_user_id'=> $this->userId,
            'title'        => 'Test Show',
            'slug'         => 'test-show-' . uniqid('', true),
            'language'     => 'en',
            'visibility'   => 'public',
            'status'       => 'draft',
            'created_at'   => now(),
            'updated_at'   => now(),
        ]);
    }

    // ── helpers ───────────────────────────────────────────────────────────────

    /** Minimal valid PCM WAV (8kHz mono 16-bit square wave). getID3 reports it as ~2s of audio. */
    private static function tinyWavBytes(int $seconds = 2): string
    {
        $rate = 8000;
        $samples = $rate * $seconds;
        $data = '';
        for ($i = 0; $i < $samples; $i++) {
            $data .= pack('v', (($i >> 4) % 2) ? 8000 : 57536);
        }

        return 'RIFF' . pack('V', 36 + strlen($data)) . 'WAVE'
            . 'fmt ' . pack('V', 16) . pack('v', 1) . pack('v', 1) . pack('V', $rate) . pack('V', $rate * 2) . pack('v', 2) . pack('v', 16)
            . 'data' . pack('V', strlen($data)) . $data;
    }

    private function insertEpisode(array $overrides = []): int
    {
        return DB::table('podcast_episodes')->insertGetId(array_merge([
            'tenant_id'                => self::TENANT_ID,
            'show_id'                  => $this->showId,
            'author_user_id'           => $this->userId,
            'title'                    => 'Test Episode',
            'slug'                     => 'test-ep-' . uniqid('', true),
            'audio_url'                => 'https://example.com/audio.mp3',
            'audio_storage_path'       => 'podcasts/audio.mp3',
            'media_processing_status'  => 'pending',
            'media_scan_status'        => 'pending',
            'media_duration_source'    => null,
            'duration_seconds'         => null,
            'status'                   => 'draft',
            'created_at'               => now(),
            'updated_at'               => now(),
        ], $overrides));
    }

    /** Insert an episode whose stored object actually exists on the faked local disk. */
    private function insertEpisodeWithStoredAudio(string $contents, array $overrides = []): int
    {
        $path = 'podcasts/' . self::TENANT_ID . '/audio-' . uniqid('', true) . '.wav';
        Storage::disk('local')->put($path, $contents);

        return $this->insertEpisode(array_merge(['audio_storage_path' => $path], $overrides));
    }

    private function fetchEpisode(int $id): object
    {
        return DB::table('podcast_episodes')->where('id', $id)->first();
    }

    // ── content analysis ─────────────────────────────────────────────────────

    /** Genuine audio with no creator duration → duration auto-detected, processing complete. */
    public function test_handle_detects_duration_and_marks_complete(): void
    {
        $episodeId = $this->insertEpisodeWithStoredAudio(self::tinyWavBytes(2));

        (new ProcessPodcastEpisodeMedia(self::TENANT_ID, $episodeId))->handle();

        $row = $this->fetchEpisode($episodeId);
        $this->assertSame('complete', $row->media_processing_status);
        $this->assertSame('detected', $row->media_duration_source);
        $this->assertSame(2, (int) $row->duration_seconds);
        $this->assertNull($row->media_failure_reason);
        // No scanner configured → status stays honest, never 'clean'.
        $this->assertSame('scan_unavailable', $row->media_scan_status);
    }

    /** A creator-supplied duration is never overwritten by detection. */
    public function test_handle_keeps_creator_duration_and_source(): void
    {
        $episodeId = $this->insertEpisodeWithStoredAudio(self::tinyWavBytes(2), [
            'duration_seconds'      => 300,
            'media_duration_source' => null,
        ]);

        (new ProcessPodcastEpisodeMedia(self::TENANT_ID, $episodeId))->handle();

        $row = $this->fetchEpisode($episodeId);
        $this->assertSame(300, (int) $row->duration_seconds);
        $this->assertSame('creator', $row->media_duration_source);
        $this->assertSame('complete', $row->media_processing_status);
    }

    /** Bytes that are not audio (e.g. a renamed binary that fooled the MIME check) → failed/not_audio. */
    public function test_handle_fails_non_audio_content_with_reason(): void
    {
        Queue::fake([CleanupPodcastMedia::class]);
        $episodeId = $this->insertEpisodeWithStoredAudio(str_repeat("\0", 4096));
        $path = (string) $this->fetchEpisode($episodeId)->audio_storage_path;

        (new ProcessPodcastEpisodeMedia(self::TENANT_ID, $episodeId))->handle();

        $row = $this->fetchEpisode($episodeId);
        $this->assertSame('failed', $row->media_processing_status);
        $this->assertSame('not_audio', $row->media_failure_reason);
        $this->assertSame('scan_unavailable', $row->media_scan_status);
        $this->assertSame('draft', $row->status);
        $this->assertSame($path, $row->audio_storage_path);
        $this->assertSame('podcast-hosted://quarantined', $row->audio_url);
        $this->assertDatabaseHas('podcast_media_cleanup_tasks', [
            'tenant_id' => self::TENANT_ID,
            'source_episode_id' => $episodeId,
            'path' => $path,
            'reason' => 'content_rejected',
            'status' => 'pending',
        ]);
    }

    /** Malware quarantine is private immediately but retains its deletion pointer. */
    public function test_malware_quarantine_retains_pointer_until_durable_cleanup_succeeds(): void
    {
        Queue::fake([CleanupPodcastMedia::class]);
        $episodeId = $this->insertEpisodeWithStoredAudio(self::tinyWavBytes());
        $episode = PodcastEpisode::findOrFail($episodeId);
        $path = (string) $episode->audio_storage_path;

        $method = new \ReflectionMethod(ProcessPodcastEpisodeMedia::class, 'quarantineRejectedEpisode');
        $method->invoke(new ProcessPodcastEpisodeMedia(self::TENANT_ID, $episodeId), $episode, 'infected');

        $row = $this->fetchEpisode($episodeId);
        $this->assertSame('infected', $row->media_scan_status);
        $this->assertSame('failed', $row->media_processing_status);
        $this->assertSame('draft', $row->status);
        $this->assertSame('podcast-hosted://quarantined', $row->audio_url);
        $this->assertSame($path, $row->audio_storage_path);
        $this->assertDatabaseHas('podcast_media_cleanup_tasks', [
            'tenant_id' => self::TENANT_ID,
            'source_episode_id' => $episodeId,
            'path' => $path,
            'reason' => 'malware_rejected',
            'status' => 'pending',
        ]);
    }

    /** Legacy 'ready_for_processing' interim rows are picked up and completed on the next run. */
    public function test_handle_completes_legacy_ready_for_processing_rows(): void
    {
        $episodeId = $this->insertEpisodeWithStoredAudio(self::tinyWavBytes(2), [
            'media_scan_status'       => 'scan_unavailable',
            'media_processing_status' => 'ready_for_processing',
        ]);

        (new ProcessPodcastEpisodeMedia(self::TENANT_ID, $episodeId))->handle();

        $this->assertSame('complete', $this->fetchEpisode($episodeId)->media_processing_status);
    }

    /** Non-pending scan status must not be overwritten by a reprocess. */
    public function test_handle_does_not_overwrite_non_pending_scan_status(): void
    {
        $episodeId = $this->insertEpisodeWithStoredAudio(self::tinyWavBytes(2), [
            'media_scan_status'       => 'clean',
            'media_processing_status' => 'pending',
        ]);

        (new ProcessPodcastEpisodeMedia(self::TENANT_ID, $episodeId))->handle();

        $row = $this->fetchEpisode($episodeId);
        $this->assertSame('clean', $row->media_scan_status, 'Non-pending scan_status must be preserved');
        $this->assertSame('complete', $row->media_processing_status);
    }

    /** Terminal statuses on both axes → early return, nothing rewritten. */
    public function test_handle_skips_when_nothing_is_pending(): void
    {
        $episodeId = $this->insertEpisodeWithStoredAudio(self::tinyWavBytes(2), [
            'media_scan_status'       => 'clean',
            'media_processing_status' => 'failed',
            'media_duration_source'   => null,
        ]);

        (new ProcessPodcastEpisodeMedia(self::TENANT_ID, $episodeId))->handle();

        $row = $this->fetchEpisode($episodeId);
        $this->assertSame('failed', $row->media_processing_status, 'Terminal processing status must be preserved');
        $this->assertNull($row->media_duration_source, 'Early return must not touch other columns');
    }

    /** Stored object missing from the disk → failed/source_missing, scan honestly unavailable. */
    public function test_handle_missing_source_marks_failed(): void
    {
        $episodeId = $this->insertEpisode([
            'audio_storage_path' => 'podcasts/' . self::TENANT_ID . '/vanished.wav',
        ]);

        (new ProcessPodcastEpisodeMedia(self::TENANT_ID, $episodeId))->handle();

        $row = $this->fetchEpisode($episodeId);
        $this->assertSame('failed', $row->media_processing_status);
        $this->assertSame('source_missing', $row->media_failure_reason);
        $this->assertSame('scan_unavailable', $row->media_scan_status);
    }

    // ── guard clauses ────────────────────────────────────────────────────────

    /** When episode has no audio_storage_path the job returns early without touching DB. */
    public function test_handle_skips_episode_with_no_audio_storage_path(): void
    {
        $episodeId = $this->insertEpisode([
            'audio_storage_path'      => null,
            'media_scan_status'       => 'pending',
            'media_processing_status' => 'pending',
        ]);

        (new ProcessPodcastEpisodeMedia(self::TENANT_ID, $episodeId))->handle();

        $row = $this->fetchEpisode($episodeId);
        $this->assertSame('pending', $row->media_scan_status);
        $this->assertSame('pending', $row->media_processing_status);
    }

    /** Non-existent episode ID → job exits silently, no exception. */
    public function test_handle_silently_exits_for_missing_episode(): void
    {
        $job = new ProcessPodcastEpisodeMedia(self::TENANT_ID, 9999999);
        $job->handle(); // must not throw
        $this->assertTrue(true);
    }

    /** Invalid (non-existent) tenant ID → catches InvalidArgumentException silently. */
    public function test_handle_catches_invalid_tenant_gracefully(): void
    {
        $job = new ProcessPodcastEpisodeMedia(99999999, 1);
        $job->handle(); // must not throw
        $this->assertTrue(true);
    }

    // ── failed() handler ─────────────────────────────────────────────────────

    /** failed() marks a pending episode 'failed' with a processing_error reason. */
    public function test_failed_marks_pending_episode_as_failed(): void
    {
        $episodeId = $this->insertEpisode([
            'media_processing_status' => 'pending',
        ]);

        $job = new ProcessPodcastEpisodeMedia(self::TENANT_ID, $episodeId);
        $job->failed(new \RuntimeException('simulated'));

        $row = $this->fetchEpisode($episodeId);
        $this->assertSame('failed', $row->media_processing_status);
        $this->assertSame('processing_error', $row->media_failure_reason);
    }

    /** failed() with ready_for_processing status also marks it failed. */
    public function test_failed_marks_ready_for_processing_episode_as_failed(): void
    {
        $episodeId = $this->insertEpisode([
            'media_processing_status' => 'ready_for_processing',
        ]);

        $job = new ProcessPodcastEpisodeMedia(self::TENANT_ID, $episodeId);
        $job->failed(new \RuntimeException('simulated'));

        $this->assertSame('failed', $this->fetchEpisode($episodeId)->media_processing_status);
    }

    /** A more specific failure reason (e.g. not_audio) is not clobbered by failed(). */
    public function test_failed_preserves_existing_failure_reason(): void
    {
        $episodeId = $this->insertEpisode([
            'media_processing_status' => 'pending',
            'media_failure_reason'    => 'not_audio',
        ]);

        $job = new ProcessPodcastEpisodeMedia(self::TENANT_ID, $episodeId);
        $job->failed(new \RuntimeException('simulated'));

        $this->assertSame('not_audio', $this->fetchEpisode($episodeId)->media_failure_reason);
    }

    /** Job properties match the documented configuration. */
    public function test_job_has_correct_tries_and_backoff_configuration(): void
    {
        $job = new ProcessPodcastEpisodeMedia(self::TENANT_ID, 1);
        $this->assertSame(3, $job->tries);
        $this->assertSame(30, $job->backoff);
    }
}
