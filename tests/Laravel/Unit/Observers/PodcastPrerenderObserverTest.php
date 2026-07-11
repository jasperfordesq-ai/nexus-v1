<?php
// Copyright © 2024–2026 Jasper Ford
// SPDX-License-Identifier: AGPL-3.0-or-later
// Author: Jasper Ford
// See NOTICE file for attribution and acknowledgements.

declare(strict_types=1);

namespace Tests\Laravel\Unit\Observers;

use App\Models\PodcastEpisode;
use App\Models\PodcastShow;
use App\Observers\PodcastEpisodePrerenderObserver;
use App\Observers\PodcastShowPrerenderObserver;
use App\Services\PrerenderContentInvalidator;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Illuminate\Support\Facades\DB;
use Mockery;
use Tests\Laravel\TestCase;

class PodcastPrerenderObserverTest extends TestCase
{
    use DatabaseTransactions;

    private Mockery\MockInterface $invalidator;

    protected function setUp(): void
    {
        parent::setUp();

        $this->invalidator = Mockery::mock(PrerenderContentInvalidator::class);
        $this->app->instance(PrerenderContentInvalidator::class, $this->invalidator);
    }

    public function test_show_save_refreshes_list_old_and_new_show_paths_and_dependent_episodes(): void
    {
        $show = new PodcastShow();
        $show->setRawAttributes(['id' => 71, 'tenant_id' => 2, 'slug' => 'old-show'], true);
        $show->slug = 'new-show';

        $episodeOne = new PodcastEpisode(['slug' => 'episode-one']);
        $episodeTwo = new PodcastEpisode(['slug' => 'episode-two']);
        $show->setRelation('episodes', collect([$episodeOne, $episodeTwo]));

        $this->invalidator->shouldReceive('refreshRoutes')
            ->once()
            ->with(2, [
                '/podcasts',
                '/podcasts/old-show',
                '/podcasts/new-show',
                '/podcasts/old-show/episode-one',
                '/podcasts/new-show/episode-one',
                '/podcasts/old-show/episode-two',
                '/podcasts/new-show/episode-two',
            ]);

        (new PodcastShowPrerenderObserver())->saved($show);

        $this->assertTrue(true);
    }

    public function test_show_delete_captures_episode_paths_before_the_delete(): void
    {
        $showId = $this->insertShow(2, 'deleted-show');
        DB::table('podcast_episodes')->insert($this->episodeRow(2, $showId, 'deleted-episode'));

        $show = new PodcastShow();
        $show->setRawAttributes(['id' => $showId, 'tenant_id' => 2, 'slug' => 'deleted-show'], true);

        $this->invalidator->shouldReceive('refreshRoutes')
            ->once()
            ->with(2, [
                '/podcasts',
                '/podcasts/deleted-show',
                '/podcasts/deleted-show/deleted-episode',
            ]);

        $observer = new PodcastShowPrerenderObserver();
        $observer->deleting($show);
        DB::table('podcast_episodes')->where('show_id', $showId)->delete();
        $observer->deleted($show);

        $this->assertTrue(true);
    }

    public function test_episode_save_refreshes_list_show_and_old_and_new_detail_paths(): void
    {
        $showId = $this->insertShow(2, 'community-show');
        $episode = new PodcastEpisode();
        $episode->setRawAttributes([
            'id' => 81,
            'tenant_id' => 2,
            'show_id' => $showId,
            'slug' => 'old-episode',
            'moderation_status' => 'pending',
        ], true);
        $episode->slug = 'new-episode';
        $episode->moderation_status = 'approved';

        $this->invalidator->shouldReceive('refreshRoutes')
            ->once()
            ->with(2, [
                '/podcasts',
                '/podcasts/community-show',
                '/podcasts/community-show/old-episode',
                '/podcasts/community-show/new-episode',
            ]);

        (new PodcastEpisodePrerenderObserver())->saved($episode);

        $this->assertTrue(true);
    }

    public function test_episode_delete_refreshes_list_show_and_removed_detail_route(): void
    {
        $showId = $this->insertShow(2, 'delete-parent');
        $episode = new PodcastEpisode();
        $episode->setRawAttributes([
            'id' => 82,
            'tenant_id' => 2,
            'show_id' => $showId,
            'slug' => 'delete-me',
        ], true);

        $this->invalidator->shouldReceive('refreshRoutes')
            ->once()
            ->with(2, [
                '/podcasts',
                '/podcasts/delete-parent',
                '/podcasts/delete-parent/delete-me',
            ]);

        (new PodcastEpisodePrerenderObserver())->deleted($episode);

        $this->assertTrue(true);
    }

    public function test_episode_lookup_is_tenant_scoped(): void
    {
        $showId = $this->insertShow(2, 'right-tenant-show');
        DB::table('podcast_shows')->where('id', $showId)->update(['tenant_id' => 999]);

        $episode = new PodcastEpisode();
        $episode->setRawAttributes([
            'id' => 83,
            'tenant_id' => 2,
            'show_id' => $showId,
            'slug' => 'tenant-safe',
        ], true);

        $this->invalidator->shouldReceive('refreshRoutes')
            ->once()
            ->with(2, ['/podcasts']);

        (new PodcastEpisodePrerenderObserver())->saved($episode);

        $this->assertTrue(true);
    }

    public function test_podcast_observers_are_registered_with_eloquent(): void
    {
        $dispatcher = PodcastShow::getEventDispatcher();
        $this->assertNotNull($dispatcher);
        $this->assertNotEmpty($dispatcher->getListeners('eloquent.saved: ' . PodcastShow::class));
        $this->assertNotEmpty($dispatcher->getListeners('eloquent.deleted: ' . PodcastEpisode::class));
    }

    private function insertShow(int $tenantId, string $slug): int
    {
        return (int) DB::table('podcast_shows')->insertGetId([
            'tenant_id' => $tenantId,
            'owner_user_id' => 1,
            'title' => 'Prerender observer show',
            'slug' => $slug,
            'language' => 'en',
            'visibility' => 'public',
            'status' => 'published',
            'moderation_status' => 'approved',
            'created_at' => now(),
            'updated_at' => now(),
        ]);
    }

    /** @return array<string,mixed> */
    private function episodeRow(int $tenantId, int $showId, string $slug): array
    {
        return [
            'tenant_id' => $tenantId,
            'show_id' => $showId,
            'author_user_id' => 1,
            'title' => 'Prerender observer episode',
            'slug' => $slug,
            'audio_url' => 'https://example.test/episode.mp3',
            'episode_type' => 'full',
            'visibility' => 'inherit',
            'status' => 'published',
            'moderation_status' => 'approved',
            'created_at' => now(),
            'updated_at' => now(),
        ];
    }
}
