<?php
// Copyright © 2024–2026 Jasper Ford
// SPDX-License-Identifier: AGPL-3.0-or-later
// Author: Jasper Ford
// See NOTICE file for attribution and acknowledgements.

namespace Tests\Laravel\Unit\Jobs;

use App\Jobs\ProcessPodcastEpisodeMedia;
use App\Core\TenantContext;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Illuminate\Support\Facades\DB;
use Tests\Laravel\TestCase;

/**
 * ProcessPodcastEpisodeMediaTest
 *
 * Tests the handle() and failed() methods of the podcast media-processing job.
 * Uses DatabaseTransactions so every inserted row is rolled back after each test.
 * No actual audio processing happens (the job is a provision hook stub), so no
 * Storage::fake() is required — we just assert DB column transitions.
 */
class ProcessPodcastEpisodeMediaTest extends TestCase
{
    use DatabaseTransactions;

    private const TENANT_ID = 2;

    // IDs chosen high enough to avoid colliding with real fixture data.
    private int $showId;
    private int $userId;

    protected function setUp(): void
    {
        parent::setUp();
        TenantContext::setById(self::TENANT_ID);

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

    private function fetchEpisode(int $id): object
    {
        return DB::table('podcast_episodes')->where('id', $id)->first();
    }

    // ── tests ─────────────────────────────────────────────────────────────────

    /** Episode with audio_storage_path and pending statuses → transitions to safe interim states. */
    public function test_handle_transitions_pending_scan_and_processing_status(): void
    {
        $episodeId = $this->insertEpisode([
            'media_scan_status'       => 'pending',
            'media_processing_status' => 'pending',
        ]);

        $job = new ProcessPodcastEpisodeMedia(self::TENANT_ID, $episodeId);
        $job->handle();

        $row = $this->fetchEpisode($episodeId);
        $this->assertSame('scan_unavailable', $row->media_scan_status);
        $this->assertSame('ready_for_processing', $row->media_processing_status);
    }

    /** Non-pending scan status must not be overwritten. */
    public function test_handle_does_not_overwrite_non_pending_scan_status(): void
    {
        $episodeId = $this->insertEpisode([
            'media_scan_status'       => 'clean',
            'media_processing_status' => 'pending',
        ]);

        $job = new ProcessPodcastEpisodeMedia(self::TENANT_ID, $episodeId);
        $job->handle();

        $row = $this->fetchEpisode($episodeId);
        $this->assertSame('clean', $row->media_scan_status, 'Non-pending scan_status must be preserved');
    }

    /** Non-pending processing status must not be overwritten. */
    public function test_handle_does_not_overwrite_non_pending_processing_status(): void
    {
        $episodeId = $this->insertEpisode([
            'media_scan_status'       => 'pending',
            'media_processing_status' => 'failed',
        ]);

        $job = new ProcessPodcastEpisodeMedia(self::TENANT_ID, $episodeId);
        $job->handle();

        $row = $this->fetchEpisode($episodeId);
        $this->assertSame('failed', $row->media_processing_status, 'Non-pending processing_status must be preserved');
    }

    /** When episode has no audio_storage_path the job returns early without touching DB. */
    public function test_handle_skips_episode_with_no_audio_storage_path(): void
    {
        $episodeId = $this->insertEpisode([
            'audio_storage_path'      => null,
            'media_scan_status'       => 'pending',
            'media_processing_status' => 'pending',
        ]);

        $job = new ProcessPodcastEpisodeMedia(self::TENANT_ID, $episodeId);
        $job->handle();

        $row = $this->fetchEpisode($episodeId);
        // Statuses must remain unchanged because handle() returned early.
        $this->assertSame('pending', $row->media_scan_status);
        $this->assertSame('pending', $row->media_processing_status);
    }

    /** Non-existent episode ID → job exits silently, no exception. */
    public function test_handle_silently_exits_for_missing_episode(): void
    {
        $job = new ProcessPodcastEpisodeMedia(self::TENANT_ID, 9999999);
        $job->handle(); // must not throw
        $this->assertTrue(true); // reached here → pass
    }

    /** Invalid (non-existent) tenant ID → catches InvalidArgumentException silently. */
    public function test_handle_catches_invalid_tenant_gracefully(): void
    {
        $job = new ProcessPodcastEpisodeMedia(99999999, 1);
        $job->handle(); // must not throw
        $this->assertTrue(true);
    }

    /**
     * Episode with known duration_seconds and null media_duration_source:
     * handle() should set media_duration_source to 'creator'.
     */
    public function test_handle_sets_duration_source_to_creator_when_duration_known(): void
    {
        $episodeId = $this->insertEpisode([
            'duration_seconds'      => 300,
            'media_duration_source' => null,
        ]);

        $job = new ProcessPodcastEpisodeMedia(self::TENANT_ID, $episodeId);
        $job->handle();

        $row = $this->fetchEpisode($episodeId);
        $this->assertSame('creator', $row->media_duration_source);
    }

    /**
     * Episode with no duration — media_duration_source must stay null.
     */
    public function test_handle_leaves_duration_source_null_when_no_duration(): void
    {
        $episodeId = $this->insertEpisode([
            'duration_seconds'      => null,
            'media_duration_source' => null,
        ]);

        $job = new ProcessPodcastEpisodeMedia(self::TENANT_ID, $episodeId);
        $job->handle();

        $row = $this->fetchEpisode($episodeId);
        $this->assertNull($row->media_duration_source);
    }

    /**
     * failed() marks a pending/ready episode as 'failed' in the DB.
     */
    public function test_failed_marks_pending_episode_as_failed(): void
    {
        $episodeId = $this->insertEpisode([
            'media_processing_status' => 'pending',
        ]);

        $job = new ProcessPodcastEpisodeMedia(self::TENANT_ID, $episodeId);
        $job->failed(new \RuntimeException('simulated'));

        $row = $this->fetchEpisode($episodeId);
        $this->assertSame('failed', $row->media_processing_status);
    }

    /**
     * failed() with ready_for_processing status also marks it failed.
     */
    public function test_failed_marks_ready_for_processing_episode_as_failed(): void
    {
        $episodeId = $this->insertEpisode([
            'media_processing_status' => 'ready_for_processing',
        ]);

        $job = new ProcessPodcastEpisodeMedia(self::TENANT_ID, $episodeId);
        $job->failed(new \RuntimeException('simulated'));

        $row = $this->fetchEpisode($episodeId);
        $this->assertSame('failed', $row->media_processing_status);
    }

    /**
     * Job properties match the documented configuration.
     */
    public function test_job_has_correct_tries_and_backoff_configuration(): void
    {
        $job = new ProcessPodcastEpisodeMedia(self::TENANT_ID, 1);
        $this->assertSame(3, $job->tries);
        $this->assertSame(30, $job->backoff);
    }
}
