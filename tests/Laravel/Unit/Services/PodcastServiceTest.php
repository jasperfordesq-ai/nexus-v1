<?php
// Copyright © 2024–2026 Jasper Ford
// SPDX-License-Identifier: AGPL-3.0-or-later
// Author: Jasper Ford
// See NOTICE file for attribution and acknowledgements.

namespace Tests\Laravel\Unit\Services;

use App\Core\TenantContext;
use App\Models\PodcastEpisode;
use App\Models\PodcastShow;
use App\Services\PodcastService;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Queue;
use Illuminate\Support\Facades\Storage;
use Tests\Laravel\TestCase;

/**
 * PodcastServiceTest
 *
 * Covers the main public surface of PodcastService (1 763 lines):
 *   show CRUD, episode CRUD, publish/archive/delete, visibility/access guards,
 *   subscribe toggle, RSS feed XML structure, validateFeed, and reportEpisode.
 *
 * Strategy:
 *  - Insert minimal tenant-2 fixtures via DB::table() using verified schema columns.
 *  - Storage::fake('local') for any hosted-audio path; Queue::fake() to intercept
 *    ProcessPodcastEpisodeMedia dispatch without running it.
 *  - FeedActivityService is bound via app() inside a try/catch in PodcastService,
 *    so failures there only emit a warning — no need to mock it here.
 *  - PodcastConfigurationService reads from DB (cached 300s); we use the defaults
 *    (moderation OFF, media_processing/scanning ON, chapters/transcripts/reactions/
 *    listen_analytics all ON, max_audio_size_mb 250) — those are the table defaults
 *    for any tenant that has no explicit overrides.
 *
 * Skipped (noted below):
 *  - releaseDueEpisodes: requires a scheduler-style cross-tenant context; integration-only.
 *  - storeHostedAudio with cloud disk: needs a real S3/mocked cloud driver; tested via
 *    local disk path instead.
 *  - notifySubscribersOfEpisode: fires inside a try/catch via Notification::createNotification
 *    which needs the full notification pipeline — omitted here (covered by feature tests).
 */
class PodcastServiceTest extends TestCase
{
    use DatabaseTransactions;

    private const TENANT_ID = 2;

    /** @var int seeded owner user id */
    private int $ownerId;

    protected function setUp(): void
    {
        parent::setUp();
        TenantContext::setById(self::TENANT_ID);
        Storage::fake('local');
        Queue::fake();

        $this->ownerId = $this->insertUser('show_owner');
    }

    // ──────────────────────────────────────────────────────────────────────────
    // Helpers
    // ──────────────────────────────────────────────────────────────────────────

    private function insertUser(string $suffix = ''): int
    {
        $uid = uniqid($suffix, true);
        return DB::table('users')->insertGetId([
            'tenant_id'   => self::TENANT_ID,
            'name'        => 'Test User ' . $uid,
            'first_name'  => 'Test',
            'last_name'   => 'User',
            'email'       => 'testuser.' . $uid . '@example.test',
            'status'      => 'active',
            'balance'     => 0,
            'role'        => 'member',
            'is_approved' => 1,
            'created_at'  => now(),
            'updated_at'  => now(),
        ]);
    }

    /**
     * Create a show via the service and return it.
     */
    private function createShow(array $overrides = []): PodcastShow
    {
        return PodcastService::createShow($this->ownerId, array_merge([
            'title'       => 'Test Podcast Show',
            'visibility'  => 'public',
            'language'    => 'en',
        ], $overrides));
    }

    /**
     * Create + publish a show (so it appears in browse / RSS).
     */
    private function createPublishedShow(array $overrides = []): PodcastShow
    {
        $show = $this->createShow($overrides);
        return PodcastService::publishShow($show);
    }

    /**
     * Create a minimal episode with a remote audio_url (no file upload).
     */
    private function createEpisode(PodcastShow $show, array $overrides = []): PodcastEpisode
    {
        return PodcastService::createEpisode($show, $this->ownerId, array_merge([
            'title'     => 'Test Episode',
            'audio_url' => 'https://example.com/audio.mp3',
            'audio_mime' => 'audio/mpeg',
        ], $overrides));
    }

    /**
     * Create + publish an episode.
     */
    private function createPublishedEpisode(PodcastShow $show, array $overrides = []): PodcastEpisode
    {
        $episode = $this->createEpisode($show, $overrides);
        return PodcastService::publishEpisode($episode);
    }

    // ──────────────────────────────────────────────────────────────────────────
    // Show CRUD
    // ──────────────────────────────────────────────────────────────────────────

    public function test_createShow_persists_and_returns_PodcastShow(): void
    {
        $show = $this->createShow(['title' => 'My New Show', 'category' => 'Technology']);

        $this->assertInstanceOf(PodcastShow::class, $show);
        $this->assertNotNull($show->id);
        $this->assertSame('My New Show', $show->title);
        $this->assertSame('my-new-show', $show->slug);
        $this->assertSame('draft', $show->status);
        $this->assertSame('Technology', $show->category);

        $this->assertDatabaseHas('podcast_shows', [
            'id'        => $show->id,
            'tenant_id' => self::TENANT_ID,
            'title'     => 'My New Show',
        ]);
    }

    public function test_createShow_throws_when_title_is_empty(): void
    {
        $this->expectException(\InvalidArgumentException::class);
        PodcastService::createShow($this->ownerId, ['title' => '']);
    }

    public function test_createShow_generates_unique_slug_on_duplicate_title(): void
    {
        $show1 = $this->createShow(['title' => 'Duplicate Title']);
        $show2 = $this->createShow(['title' => 'Duplicate Title']);

        $this->assertNotSame($show1->slug, $show2->slug);
        // Second should be suffixed with -2
        $this->assertStringStartsWith('duplicate-title-', $show2->slug);
    }

    public function test_updateShow_changes_fields_in_db(): void
    {
        $show = $this->createShow(['title' => 'Original Title']);

        PodcastService::updateShow($show, [
            'title'    => 'Updated Title',
            'category' => 'News',
            'explicit' => true,
        ]);

        $this->assertDatabaseHas('podcast_shows', [
            'id'       => $show->id,
            'title'    => 'Updated Title',
            'category' => 'News',
            'explicit' => 1,
        ]);
    }

    public function test_publishShow_sets_status_to_published(): void
    {
        $show = $this->createShow();
        $published = PodcastService::publishShow($show);

        $this->assertSame('published', $published->status);
        $this->assertNotNull($published->published_at);
        $this->assertDatabaseHas('podcast_shows', ['id' => $show->id, 'status' => 'published']);
    }

    public function test_archiveShow_sets_status_to_archived(): void
    {
        $show = $this->createPublishedShow();
        PodcastService::archiveShow($show);

        $this->assertDatabaseHas('podcast_shows', ['id' => $show->id, 'status' => 'archived']);
    }

    public function test_deleteShow_removes_show_and_episodes_from_db(): void
    {
        $show    = $this->createShow();
        $showId  = $show->id;
        $episode = $this->createEpisode($show);
        $episodeId = $episode->id;

        PodcastService::deleteShow($show);

        $this->assertDatabaseMissing('podcast_shows', ['id' => $showId]);
        $this->assertDatabaseMissing('podcast_episodes', ['id' => $episodeId]);
    }

    // ──────────────────────────────────────────────────────────────────────────
    // Episode CRUD
    // ──────────────────────────────────────────────────────────────────────────

    public function test_createEpisode_persists_and_returns_episode(): void
    {
        $show    = $this->createShow();
        $episode = $this->createEpisode($show, [
            'title'          => 'Pilot Episode',
            'audio_url'      => 'https://cdn.example.com/ep1.mp3',
            'audio_mime'     => 'audio/mpeg',
            'duration_seconds' => 1800,
            'episode_number' => 1,
        ]);

        $this->assertInstanceOf(PodcastEpisode::class, $episode);
        $this->assertNotNull($episode->id);
        $this->assertSame('Pilot Episode', $episode->title);
        $this->assertSame('draft', $episode->status);
        $this->assertSame(1800, $episode->duration_seconds);

        $this->assertDatabaseHas('podcast_episodes', [
            'id'      => $episode->id,
            'show_id' => $show->id,
        ]);
    }

    public function test_createEpisode_throws_when_title_is_empty(): void
    {
        $show = $this->createShow();
        $this->expectException(\InvalidArgumentException::class);
        PodcastService::createEpisode($show, $this->ownerId, [
            'title'     => '',
            'audio_url' => 'https://example.com/ep.mp3',
        ]);
    }

    public function test_createEpisode_throws_when_audio_url_is_invalid(): void
    {
        $show = $this->createShow();
        $this->expectException(\InvalidArgumentException::class);
        PodcastService::createEpisode($show, $this->ownerId, [
            'title'     => 'Bad Audio',
            'audio_url' => 'not-a-url',
        ]);
    }

    public function test_updateEpisode_changes_title_and_summary(): void
    {
        $show    = $this->createShow();
        $episode = $this->createEpisode($show);

        PodcastService::updateEpisode($episode, [
            'title'   => 'Renamed Episode',
            'summary' => 'A short summary.',
        ]);

        $this->assertDatabaseHas('podcast_episodes', [
            'id'      => $episode->id,
            'title'   => 'Renamed Episode',
            'summary' => 'A short summary.',
        ]);
    }

    public function test_publishEpisode_sets_published_status(): void
    {
        $show    = $this->createShow();
        $episode = $this->createEpisode($show);

        $published = PodcastService::publishEpisode($episode);

        $this->assertSame('published', $published->status);
        $this->assertNotNull($published->published_at);
        $this->assertDatabaseHas('podcast_episodes', ['id' => $episode->id, 'status' => 'published']);
    }

    public function test_archiveEpisode_sets_status_to_archived(): void
    {
        $show    = $this->createShow();
        $episode = $this->createEpisode($show);

        PodcastService::archiveEpisode($episode);

        $this->assertDatabaseHas('podcast_episodes', ['id' => $episode->id, 'status' => 'archived']);
    }

    public function test_deleteEpisode_removes_episode_from_db(): void
    {
        $show      = $this->createShow();
        $episode   = $this->createEpisode($show);
        $episodeId = $episode->id;

        PodcastService::deleteEpisode($episode);

        $this->assertDatabaseMissing('podcast_episodes', ['id' => $episodeId]);
    }

    // ──────────────────────────────────────────────────────────────────────────
    // Episode count refresh
    // ──────────────────────────────────────────────────────────────────────────

    public function test_publishEpisode_increments_show_episode_count(): void
    {
        $show = $this->createPublishedShow();
        $this->assertSame(0, $show->fresh()->episode_count);

        $episode = $this->createEpisode($show);
        PodcastService::publishEpisode($episode);

        $this->assertSame(1, $show->fresh()->episode_count);
    }

    public function test_deleteEpisode_decrements_show_episode_count(): void
    {
        $show    = $this->createPublishedShow();
        $episode = $this->createPublishedEpisode($show);

        $this->assertSame(1, $show->fresh()->episode_count);

        PodcastService::deleteEpisode($episode);

        $this->assertSame(0, $show->fresh()->episode_count);
    }

    // ──────────────────────────────────────────────────────────────────────────
    // Browse / tenant scoping
    // ──────────────────────────────────────────────────────────────────────────

    public function test_browse_returns_only_published_approved_shows(): void
    {
        $published = $this->createPublishedShow(['title' => 'Live Show']);
        $draft     = $this->createShow(['title' => 'Draft Show']);

        $result = PodcastService::browse(['per_page' => 50]);

        $ids = array_column($result['items'], 'id');
        $this->assertContains($published->id, $ids, 'Published show should appear in browse');
        $this->assertNotContains($draft->id, $ids, 'Draft show must NOT appear in browse');
    }

    public function test_browse_pagination_returns_correct_page_and_total(): void
    {
        // Create 3 published shows for this test run; total may be higher due to
        // other data in tenant 2, but per_page=1 page=1 must return exactly 1 item.
        for ($i = 1; $i <= 3; $i++) {
            $this->createPublishedShow(['title' => "Browse Pager Show {$i} " . uniqid()]);
        }

        $result = PodcastService::browse(['per_page' => 1, 'page' => 1]);

        $this->assertCount(1, $result['items']);
        $this->assertSame(1, $result['page']);
        $this->assertSame(1, $result['per_page']);
        $this->assertGreaterThanOrEqual(3, $result['total']);
    }

    // ──────────────────────────────────────────────────────────────────────────
    // Subscription toggle
    // ──────────────────────────────────────────────────────────────────────────

    public function test_toggleSubscription_subscribes_and_then_unsubscribes(): void
    {
        $show       = $this->createShow();
        $subscriberId = $this->insertUser('subscriber');

        // First call → subscribe
        $subscribed = PodcastService::toggleSubscription($show, $subscriberId);
        $this->assertTrue($subscribed);
        $this->assertDatabaseHas('podcast_show_subscriptions', [
            'tenant_id' => self::TENANT_ID,
            'show_id'   => $show->id,
            'user_id'   => $subscriberId,
        ]);

        // Second call → unsubscribe
        $unsubscribed = PodcastService::toggleSubscription($show, $subscriberId);
        $this->assertFalse($unsubscribed);
        $this->assertDatabaseMissing('podcast_show_subscriptions', [
            'show_id' => $show->id,
            'user_id' => $subscriberId,
        ]);
    }

    public function test_toggleSubscription_increments_and_decrements_subscriber_count(): void
    {
        $show         = $this->createShow();
        $subscriberId = $this->insertUser('sub_count');

        PodcastService::toggleSubscription($show, $subscriberId);
        $this->assertSame(1, $show->fresh()->subscriber_count);

        PodcastService::toggleSubscription($show, $subscriberId);
        $this->assertSame(0, $show->fresh()->subscriber_count);
    }

    // ──────────────────────────────────────────────────────────────────────────
    // Access control
    // ──────────────────────────────────────────────────────────────────────────

    public function test_canViewShow_admin_bypasses_visibility(): void
    {
        $show = $this->createShow(); // draft, not published
        $this->assertTrue(PodcastService::canViewShow($show, null, true));
    }

    public function test_canViewShow_owner_bypasses_published_check(): void
    {
        $show = $this->createShow(); // draft
        $this->assertTrue(PodcastService::canViewShow($show, $this->ownerId, false));
    }

    public function test_canViewShow_public_show_is_visible_to_guests(): void
    {
        $show = $this->createPublishedShow(['visibility' => 'public']);
        $this->assertTrue(PodcastService::canViewShow($show, null, false));
    }

    public function test_canViewShow_members_only_show_not_visible_to_guest(): void
    {
        $show = $this->createPublishedShow(['visibility' => 'members']);
        $this->assertFalse(PodcastService::canViewShow($show, null, false));
    }

    public function test_canViewShow_members_only_show_visible_to_member(): void
    {
        $show     = $this->createPublishedShow(['visibility' => 'members']);
        $memberId = $this->insertUser('viewer');
        $this->assertTrue(PodcastService::canViewShow($show, $memberId, false));
    }

    public function test_canViewEpisode_returns_false_for_future_scheduled_episode(): void
    {
        $show    = $this->createPublishedShow();
        $episode = $this->createEpisode($show, [
            'audio_url'      => 'https://example.com/ep.mp3',
            'scheduled_for'  => now()->addDay()->toDateTimeString(),
        ]);
        // Manually set to published+approved without going through the service publish
        // path (which would check isEpisodeLive and skip announce, but still sets status).
        $episode->status            = 'published';
        $episode->moderation_status = 'approved';
        $episode->published_at      = now()->addDay();
        $episode->save();

        $memberId = $this->insertUser('future_viewer');
        $this->assertFalse(PodcastService::canViewEpisode($episode, $show, $memberId, false));
    }

    // ──────────────────────────────────────────────────────────────────────────
    // RSS feed
    // ──────────────────────────────────────────────────────────────────────────

    public function test_buildRss_returns_valid_rss_xml_with_channel_tags(): void
    {
        $show = $this->createPublishedShow([
            'title'       => 'RSS Test Show',
            'description' => 'A show for RSS testing.',
            'language'    => 'en',
        ]);

        $rss = PodcastService::buildRss($show);

        $this->assertStringContainsString('<?xml version="1.0" encoding="UTF-8"?>', $rss);
        $this->assertStringContainsString('<rss version="2.0"', $rss);
        $this->assertStringContainsString('<channel>', $rss);
        $this->assertStringContainsString('</channel>', $rss);
        $this->assertStringContainsString('</rss>', $rss);
        $this->assertStringContainsString('<title>RSS Test Show</title>', $rss);
        $this->assertStringContainsString('<language>en</language>', $rss);
    }

    public function test_buildRss_includes_published_episode_items(): void
    {
        $show    = $this->createPublishedShow(['title' => 'Episode RSS Show']);
        $episode = $this->createPublishedEpisode($show, [
            'title'      => 'Episode One',
            'audio_url'  => 'https://cdn.example.com/ep1.mp3',
            'audio_mime' => 'audio/mpeg',
        ]);

        $rss = PodcastService::buildRss($show);

        $this->assertStringContainsString('<item>', $rss);
        $this->assertStringContainsString('<title>Episode One</title>', $rss);
        $this->assertStringContainsString('https://cdn.example.com/ep1.mp3', $rss);
        $this->assertStringContainsString('<enclosure ', $rss);
    }

    public function test_buildRss_skips_draft_episode(): void
    {
        $show    = $this->createPublishedShow(['title' => 'Draft Episode RSS Show']);
        // Draft episode (not published) — must NOT appear in RSS
        $this->createEpisode($show, [
            'title'     => 'Draft Hidden Episode',
            'audio_url' => 'https://cdn.example.com/draft.mp3',
        ]);

        $rss = PodcastService::buildRss($show);

        $this->assertStringNotContainsString('Draft Hidden Episode', $rss);
        $this->assertStringNotContainsString('<item>', $rss);
    }

    public function test_buildRss_escapes_xml_special_chars_in_title(): void
    {
        $show = $this->createPublishedShow(['title' => 'Show & "Quotes" <test>']);

        $rss = PodcastService::buildRss($show);

        $this->assertStringContainsString('Show &amp; &quot;Quotes&quot; &lt;test&gt;', $rss);
    }

    // ──────────────────────────────────────────────────────────────────────────
    // validateFeed
    // ──────────────────────────────────────────────────────────────────────────

    public function test_validateFeed_returns_error_for_draft_show(): void
    {
        $show = $this->createShow();

        $result = PodcastService::validateFeed($show);

        $this->assertFalse($result['valid']);
        $this->assertContains('show_not_public', $result['errors']);
    }

    public function test_validateFeed_returns_error_when_no_public_episodes(): void
    {
        $show = $this->createPublishedShow([
            'title'       => 'Valid Show No Episodes',
            'description' => 'Full description here.',
            'language'    => 'en',
            'owner_email' => 'owner@example.com',
            'artwork_url' => 'https://example.com/art.jpg',
        ]);

        $result = PodcastService::validateFeed($show);

        $this->assertFalse($result['valid']);
        $this->assertContains('missing_public_episodes', $result['errors']);
    }

    public function test_validateFeed_valid_when_show_and_episode_complete(): void
    {
        $show = $this->createPublishedShow([
            'title'       => 'Complete Valid Show ' . uniqid(),
            'description' => 'Description for the show.',
            'language'    => 'en',
            'owner_email' => 'host@example.com',
            'artwork_url' => 'https://example.com/artwork.jpg',
        ]);
        $this->createPublishedEpisode($show, [
            'title'      => 'Valid Episode',
            'audio_url'  => 'https://cdn.example.com/audio.mp3',
            'audio_mime' => 'audio/mpeg',
            'audio_bytes' => 5000000,
        ]);

        $result = PodcastService::validateFeed($show);

        $this->assertTrue($result['valid'], 'Feed validation should pass: ' . implode(', ', $result['errors']));
        $this->assertEmpty($result['errors']);
    }

    // ──────────────────────────────────────────────────────────────────────────
    // Hosted audio upload (local disk)
    // ──────────────────────────────────────────────────────────────────────────

    public function test_storeHostedAudio_stores_file_and_updates_episode(): void
    {
        $show    = $this->createShow();
        $episode = $this->createEpisode($show, [
            'title'     => 'Audio Upload Episode',
            'audio_url' => 'https://example.com/placeholder.mp3',
        ]);

        $file = UploadedFile::fake()->create('episode.mp3', 1024, 'audio/mpeg');

        PodcastService::storeHostedAudio($episode, $file);

        $this->assertNotNull($episode->audio_storage_path);
        $this->assertSame('audio/mpeg', $episode->audio_mime);
        Storage::disk('local')->assertExists($episode->audio_storage_path);
    }

    public function test_storeHostedAudio_dispatches_media_job_when_processing_enabled(): void
    {
        // PodcastConfigurationService defaults: processing=ON, scanning=ON
        $show    = $this->createShow();
        $episode = $this->createEpisode($show, [
            'title'     => 'Job Dispatch Episode',
            'audio_url' => 'https://example.com/ph.mp3',
        ]);
        // Reset media status to pending so the job dispatch condition triggers.
        $episode->media_processing_status = 'pending';
        $episode->save();

        $file = UploadedFile::fake()->create('ep.mp3', 512, 'audio/mpeg');
        PodcastService::storeHostedAudio($episode, $file);

        Queue::assertPushed(\App\Jobs\ProcessPodcastEpisodeMedia::class);
    }

    public function test_storeHostedAudio_rejects_unsupported_mime_type(): void
    {
        $show    = $this->createShow();
        $episode = $this->createEpisode($show, [
            'title'     => 'Bad Mime Episode',
            'audio_url' => 'https://example.com/ph.mp3',
        ]);

        $file = UploadedFile::fake()->create('video.mp4', 512, 'video/mp4');

        $this->expectException(\InvalidArgumentException::class);
        PodcastService::storeHostedAudio($episode, $file);
    }

    // ──────────────────────────────────────────────────────────────────────────
    // reportEpisode / resolveEpisodeReports
    // ──────────────────────────────────────────────────────────────────────────

    public function test_reportEpisode_inserts_report_row(): void
    {
        $show      = $this->createPublishedShow();
        $episode   = $this->createPublishedEpisode($show);
        $reporterId = $this->insertUser('reporter');

        PodcastService::reportEpisode($episode, $reporterId, 'spam', 'This is spam content.');

        $this->assertDatabaseHas('podcast_episode_reports', [
            'tenant_id'         => self::TENANT_ID,
            'episode_id'        => $episode->id,
            'reporter_user_id'  => $reporterId,
            'reason'            => 'spam',
            'status'            => 'open',
        ]);
    }

    public function test_resolveEpisodeReports_closes_open_reports(): void
    {
        $adminId   = $this->insertUser('admin_resolver');
        $show      = $this->createPublishedShow();
        $episode   = $this->createPublishedEpisode($show);
        $reporterId = $this->insertUser('rpt2');

        PodcastService::reportEpisode($episode, $reporterId, 'offensive', null);

        $result = PodcastService::resolveEpisodeReports($episode, $adminId, 'resolved');

        $this->assertSame(0, $result['open_reports']);
        $this->assertDatabaseHas('podcast_episode_reports', [
            'episode_id' => $episode->id,
            'status'     => 'resolved',
        ]);
    }

    public function test_resolveEpisodeReports_throws_on_invalid_status(): void
    {
        $show    = $this->createPublishedShow();
        $episode = $this->createPublishedEpisode($show);

        $this->expectException(\InvalidArgumentException::class);
        PodcastService::resolveEpisodeReports($episode, $this->ownerId, 'bogus_status');
    }

    // ──────────────────────────────────────────────────────────────────────────
    // ownedShowCount
    // ──────────────────────────────────────────────────────────────────────────

    public function test_ownedShowCount_returns_correct_count(): void
    {
        $initialCount = PodcastService::ownedShowCount($this->ownerId);

        $this->createShow(['title' => 'Count Show 1']);
        $this->createShow(['title' => 'Count Show 2']);

        $this->assertSame($initialCount + 2, PodcastService::ownedShowCount($this->ownerId));
    }

    // ──────────────────────────────────────────────────────────────────────────
    // getDistinctCategories
    // ──────────────────────────────────────────────────────────────────────────

    public function test_getDistinctCategories_returns_sorted_category_strings(): void
    {
        $this->createPublishedShow(['title' => 'Cat Show Z ' . uniqid(), 'category' => 'Zoology']);
        $this->createPublishedShow(['title' => 'Cat Show A ' . uniqid(), 'category' => 'Art']);

        $categories = PodcastService::getDistinctCategories();

        $this->assertIsArray($categories);
        $this->assertContains('Zoology', $categories);
        $this->assertContains('Art', $categories);
        // Should be sorted alphabetically; Art < Zoology
        $artPos    = array_search('Art', $categories, true);
        $zoologyPos = array_search('Zoology', $categories, true);
        $this->assertLessThan($zoologyPos, $artPos, 'Categories should be alphabetically sorted');
    }

    // ──────────────────────────────────────────────────────────────────────────
    // moderateShow / moderateEpisode
    // ──────────────────────────────────────────────────────────────────────────

    public function test_moderateShow_approve_sets_moderation_status(): void
    {
        $show = $this->createPublishedShow();
        // Manually put in pending so moderation makes sense
        $show->moderation_status = 'pending';
        $show->save();

        PodcastService::moderateShow($show, $this->ownerId, 'approve', null);

        $this->assertDatabaseHas('podcast_shows', [
            'id'               => $show->id,
            'moderation_status' => 'approved',
        ]);
    }

    public function test_moderateShow_reject_reverts_show_to_draft(): void
    {
        $show = $this->createPublishedShow();

        PodcastService::moderateShow($show, $this->ownerId, 'reject', 'Does not meet guidelines.');

        $this->assertDatabaseHas('podcast_shows', [
            'id'     => $show->id,
            'status' => 'draft',
        ]);
    }

    public function test_moderateEpisode_reject_reverts_episode_to_draft(): void
    {
        $show    = $this->createPublishedShow();
        $episode = $this->createPublishedEpisode($show);

        PodcastService::moderateEpisode($episode, $this->ownerId, 'reject', 'Policy violation.');

        $this->assertDatabaseHas('podcast_episodes', [
            'id'     => $episode->id,
            'status' => 'draft',
        ]);
    }

    // ── verifyMediaDisk (storage doctor) ────────────────────────────────────

    public function test_verifyMediaDisk_round_trips_probe_on_faked_disk(): void
    {
        Storage::fake('s3');

        $result = PodcastService::verifyMediaDisk('s3');

        $this->assertTrue($result['ok']);
        $this->assertSame('s3', $result['disk']);
        $this->assertSame(
            ['configured' => true, 'driver_installed' => true, 'write' => true, 'read' => true, 'delete' => true],
            $result['checks']
        );
        $this->assertNull($result['error']);
        // Probe object cleaned up after itself.
        $this->assertSame([], Storage::disk('s3')->allFiles('podcasts/.doctor'));
    }

    public function test_verifyMediaDisk_fails_cleanly_for_unconfigured_disk(): void
    {
        $result = PodcastService::verifyMediaDisk('nope-not-a-disk');

        $this->assertFalse($result['ok']);
        $this->assertFalse($result['checks']['configured']);
        $this->assertSame('disk_not_configured', $result['error']);
    }
}
