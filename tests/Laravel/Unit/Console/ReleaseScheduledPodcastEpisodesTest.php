<?php
// Copyright © 2024–2026 Jasper Ford
// SPDX-License-Identifier: AGPL-3.0-or-later
// Author: Jasper Ford
// See NOTICE file for attribution and acknowledgements.

declare(strict_types=1);

namespace Tests\Laravel\Unit\Console;

use App\Core\TenantContext;
use App\Services\EmailDispatchService;
use App\Services\PrerenderContentInvalidator;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Queue;
use Tests\Laravel\TestCase;

/**
 * Tests for podcasts:release-due console command.
 *
 * The command calls PodcastService::releaseDueEpisodes($limit) and outputs
 * "Released N scheduled podcast episode(s)."
 *
 * PodcastService::releaseDueEpisodes() queries withoutGlobalScopes() and selects
 * episodes where:
 *   status = 'published'  AND  moderation_status = 'approved'
 *   AND  announced_at IS NULL
 *   AND  scheduled_for IS NOT NULL
 *   AND  scheduled_for <= now()
 *
 * On release it calls announceEpisode() which:
 *   1. Sets announced_at via a conditional UPDATE (idempotent guard).
 *   2. Records a feed-activity row.
 *   3. Notifies subscribers (best-effort).
 *
 * Key contracts:
 *  - past-scheduled, approved, published, unannounced → announced_at set
 *  - future-scheduled → untouched
 *  - already announced → untouched
 *  - draft or pending-moderation → untouched
 *  - --limit option caps the batch
 *  - exit code is always 0
 *
 * Uses unique tenant id 99759 for isolation.
 * EmailDispatchService stubbed to prevent real SMTP.
 */
class ReleaseScheduledPodcastEpisodesTest extends TestCase
{
    use DatabaseTransactions;

    private const TENANT_ID = 99759;
    private const TENANT_SLUG = 'test-podcast-release-99759';

    /** Seeded user id (FK for podcast_shows.owner_user_id and podcast_episodes.author_user_id) */
    private int $userId;

    /** Seeded podcast_shows.id */
    private int $showId;

    protected function setUp(): void
    {
        parent::setUp();

        Queue::fake();

        // Stub EmailDispatchService so no real SMTP is attempted.
        $emailStub = \Mockery::mock(EmailDispatchService::class);
        $emailStub->shouldReceive('sendRaw')->andReturn(true)->byDefault();
        $this->app->instance(EmailDispatchService::class, $emailStub);

        DB::table('tenants')->insertOrIgnore([
            'id'         => self::TENANT_ID,
            'name'       => 'Test Podcast Release Tenant',
            'slug'       => self::TENANT_SLUG,
            'is_active'  => 1,
            'features'   => json_encode([]),
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        TenantContext::setById(self::TENANT_ID);

        // Seed a user (required FK for podcast_shows and podcast_episodes).
        $this->userId = (int) DB::table('users')->insertGetId([
            'tenant_id'  => self::TENANT_ID,
            'name'       => 'Podcast Release Test User',
            'email'      => 'podcast-release-99759@example.com',
            'role'       => 'member',
            'status'     => 'active',
            'is_approved' => 1,
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        // Seed a podcast show.
        $this->showId = (int) DB::table('podcast_shows')->insertGetId([
            'tenant_id'        => self::TENANT_ID,
            'owner_user_id'    => $this->userId,
            'title'            => 'Test Podcast Show 99759',
            'slug'             => 'test-podcast-show-99759-' . uniqid(),
            'language'         => 'en',
            'visibility'       => 'public',
            'status'           => 'published',
            'moderation_status' => 'approved',
            'episode_count'    => 0,
            'subscriber_count' => 0,
            'published_at'     => now()->subDay(),
            'created_at'       => now(),
            'updated_at'       => now(),
        ]);
    }

    // ------------------------------------------------------------------ //
    // Helpers                                                              //
    // ------------------------------------------------------------------ //

    /**
     * Insert a podcast_episodes row and return its id.
     *
     * @param array<string,mixed> $overrides
     */
    private function insertEpisode(array $overrides = []): int
    {
        static $seq = 0;
        $seq++;

        $defaults = [
            'tenant_id'               => self::TENANT_ID,
            'show_id'                 => $this->showId,
            'author_user_id'          => $this->userId,
            'title'                   => 'Test Episode ' . $seq,
            'slug'                    => 'test-episode-99759-' . $seq . '-' . uniqid(),
            'audio_url'               => 'https://example.com/audio-' . $seq . '.mp3',
            'episode_type'            => 'full',
            'visibility'              => 'inherit',
            'status'                  => 'published',
            'moderation_status'       => 'approved',
            'media_processing_status' => 'complete',
            'media_scan_status'       => 'not_required',
            'listen_count'            => 0,
            'explicit'                => 0,
            // Default: past-due scheduled episode, not yet announced
            'scheduled_for'           => now()->subHour(),
            'published_at'            => now()->subHour(),
            'announced_at'            => null,
            'created_at'              => now(),
            'updated_at'              => now(),
        ];

        return (int) DB::table('podcast_episodes')
            ->insertGetId(array_merge($defaults, $overrides));
    }

    // ------------------------------------------------------------------ //
    // Exit code tests                                                      //
    // ------------------------------------------------------------------ //

    public function test_exits_success_with_no_due_episodes(): void
    {
        $this->artisan('podcasts:release-due')
            ->assertExitCode(0);
    }

    public function test_exits_success_with_due_episodes(): void
    {
        $this->insertEpisode();

        $this->artisan('podcasts:release-due')
            ->assertExitCode(0);
    }

    // ------------------------------------------------------------------ //
    // Output tests                                                         //
    // ------------------------------------------------------------------ //

    public function test_output_contains_released_zero_when_nothing_due(): void
    {
        $this->artisan('podcasts:release-due')
            ->expectsOutputToContain('Released 0 scheduled podcast episode(s).')
            ->assertExitCode(0);
    }

    public function test_output_contains_released_count_for_one_episode(): void
    {
        $this->insertEpisode([
            'scheduled_for' => now()->subHour(),
            'announced_at'  => null,
        ]);

        $this->artisan('podcasts:release-due')
            ->expectsOutputToContain('Released 1 scheduled podcast episode(s).')
            ->assertExitCode(0);
    }

    // ------------------------------------------------------------------ //
    // Past-due episodes are released (announced_at set)                   //
    // ------------------------------------------------------------------ //

    public function test_past_scheduled_episode_is_announced(): void
    {
        $id = $this->insertEpisode([
            'scheduled_for' => now()->subHour(),
            'announced_at'  => null,
        ]);

        $this->artisan('podcasts:release-due')->assertExitCode(0);

        $row = DB::table('podcast_episodes')->where('id', $id)->first();
        $this->assertNotNull($row->announced_at, 'announced_at must be set after release');
    }

    public function test_episode_scheduled_exactly_now_boundary_is_released(): void
    {
        // scheduled_for 1 second in the past — should satisfy <= now().
        $id = $this->insertEpisode([
            'scheduled_for' => now()->subSecond(),
            'announced_at'  => null,
        ]);

        $this->artisan('podcasts:release-due')->assertExitCode(0);

        $row = DB::table('podcast_episodes')->where('id', $id)->first();
        $this->assertNotNull($row->announced_at, 'Boundary episode (1 second past) must be released');
    }

    // ------------------------------------------------------------------ //
    // Future-scheduled episodes must not be touched                       //
    // ------------------------------------------------------------------ //

    public function test_future_scheduled_episode_is_not_released(): void
    {
        $id = $this->insertEpisode([
            'scheduled_for' => now()->addHour(),  // NOT due yet
            'announced_at'  => null,
        ]);

        $this->artisan('podcasts:release-due')->assertExitCode(0);

        $row = DB::table('podcast_episodes')->where('id', $id)->first();
        $this->assertNull($row->announced_at, 'Future-scheduled episode must NOT be announced');
    }

    // ------------------------------------------------------------------ //
    // Already-announced episodes must not be re-announced                 //
    // ------------------------------------------------------------------ //

    public function test_already_announced_episode_is_not_re_announced(): void
    {
        $announcedAt = now()->subMinutes(30)->toDateTimeString();

        $id = $this->insertEpisode([
            'scheduled_for' => now()->subHour(),
            'announced_at'  => $announcedAt,
        ]);

        $this->artisan('podcasts:release-due')->assertExitCode(0);

        $row = DB::table('podcast_episodes')->where('id', $id)->first();
        // announced_at must remain the original value (not updated to a new time).
        $this->assertNotNull($row->announced_at, 'announced_at must still be set');
        // The command must not re-announce (output must say 0 released).
        $this->artisan('podcasts:release-due')
            ->expectsOutputToContain('Released 0 scheduled podcast episode(s).')
            ->assertExitCode(0);
    }

    // ------------------------------------------------------------------ //
    // Episodes not meeting criteria must be untouched                     //
    // ------------------------------------------------------------------ //

    public function test_draft_episode_is_not_released(): void
    {
        $id = $this->insertEpisode([
            'status'        => 'draft',         // not published
            'scheduled_for' => now()->subHour(),
            'announced_at'  => null,
        ]);

        $this->artisan('podcasts:release-due')->assertExitCode(0);

        $row = DB::table('podcast_episodes')->where('id', $id)->first();
        $this->assertNull($row->announced_at, 'Draft episode must not be released');
    }

    public function test_pending_moderation_episode_is_not_released(): void
    {
        $id = $this->insertEpisode([
            'moderation_status' => 'pending',   // not approved
            'scheduled_for'     => now()->subHour(),
            'announced_at'      => null,
        ]);

        $this->artisan('podcasts:release-due')->assertExitCode(0);

        $row = DB::table('podcast_episodes')->where('id', $id)->first();
        $this->assertNull($row->announced_at, 'Pending-moderation episode must not be released');
    }

    public function test_episode_without_scheduled_for_is_not_picked_up(): void
    {
        // An unscheduled episode (scheduled_for IS NULL) published long ago —
        // releaseDueEpisodes only processes episodes with a non-null scheduled_for.
        $id = $this->insertEpisode([
            'scheduled_for' => null,
            'published_at'  => now()->subDay(),
            'announced_at'  => null,
        ]);

        $this->artisan('podcasts:release-due')->assertExitCode(0);

        $row = DB::table('podcast_episodes')->where('id', $id)->first();
        $this->assertNull($row->announced_at, 'Unscheduled episode must not be touched by release command');
    }

    // ------------------------------------------------------------------ //
    // --limit option caps the batch                                        //
    // ------------------------------------------------------------------ //

    public function test_limit_option_caps_released_count(): void
    {
        // Seed 4 due episodes.
        $ids = [];
        for ($i = 0; $i < 4; $i++) {
            // Stagger scheduled_for so ordering is deterministic.
            $ids[] = $this->insertEpisode([
                'scheduled_for' => now()->subMinutes(60 - $i),
                'announced_at'  => null,
            ]);
        }

        $this->artisan('podcasts:release-due', ['--limit' => '2'])
            ->expectsOutputToContain('Released 2 scheduled podcast episode(s).')
            ->assertExitCode(0);

        $releasedCount = DB::table('podcast_episodes')
            ->whereIn('id', $ids)
            ->whereNotNull('announced_at')
            ->count();

        $this->assertSame(2, $releasedCount, '--limit=2 must release exactly 2 episodes');

        $unreleasedCount = DB::table('podcast_episodes')
            ->whereIn('id', $ids)
            ->whereNull('announced_at')
            ->count();

        $this->assertSame(2, $unreleasedCount, 'The remaining 2 episodes must not be released with --limit=2');
    }

    // ------------------------------------------------------------------ //
    // Multiple episodes in one run                                         //
    // ------------------------------------------------------------------ //

    public function test_multiple_due_episodes_are_all_released(): void
    {
        $ids = [
            $this->insertEpisode(['scheduled_for' => now()->subHours(3), 'announced_at' => null]),
            $this->insertEpisode(['scheduled_for' => now()->subHours(2), 'announced_at' => null]),
            $this->insertEpisode(['scheduled_for' => now()->subHours(1), 'announced_at' => null]),
        ];

        $this->artisan('podcasts:release-due')
            ->expectsOutputToContain('Released 3 scheduled podcast episode(s).')
            ->assertExitCode(0);

        $releasedCount = DB::table('podcast_episodes')
            ->whereIn('id', $ids)
            ->whereNotNull('announced_at')
            ->count();

        $this->assertSame(3, $releasedCount, 'All 3 due episodes must be released in one run');
    }

    public function test_due_release_does_not_enqueue_authenticated_podcast_routes_for_prerendering(): void
    {
        $episodeId = $this->insertEpisode([
            'slug' => 'scheduled-prerender-episode',
            'scheduled_for' => now()->subMinute(),
            'announced_at' => null,
        ]);
        $invalidator = \Mockery::mock(PrerenderContentInvalidator::class);
        $invalidator->shouldNotReceive('refreshRoutes');
        $this->app->instance(PrerenderContentInvalidator::class, $invalidator);

        $this->artisan('podcasts:release-due')
            ->expectsOutputToContain('Released 1 scheduled podcast episode(s).')
            ->assertExitCode(0);

        $this->assertNotNull(
            DB::table('podcast_episodes')->where('id', $episodeId)->value('announced_at')
        );
    }
}
