<?php
// Copyright © 2024–2026 Jasper Ford
// SPDX-License-Identifier: AGPL-3.0-or-later
// Author: Jasper Ford
// See NOTICE file for attribution and acknowledgements.

namespace Tests\Laravel\Unit\Services;

use App\Services\LinkPreviewService;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Http;
use Tests\Laravel\TestCase;

/**
 * Video/media provider embeds must be built from the URL alone, with NO
 * outbound fetch.
 *
 * Regression: YouTube (and other providers) block server-side scraping from
 * datacenter IPs. The old code only reached the embed builder AFTER a
 * successful OG scrape, so on production that scrape failed and 0 video
 * previews were ever produced — even though the video id was in the URL.
 */
class LinkPreviewVideoEmbedTest extends TestCase
{
    private LinkPreviewService $service;

    protected function setUp(): void
    {
        parent::setUp();
        $this->service = new LinkPreviewService();
    }

    /**
     * @dataProvider providerEmbedCases
     */
    public function test_fetchPreview_builds_provider_embed_without_fetching(
        string $url,
        string $expectedSiteName,
        string $expectedEmbedContains
    ): void {
        Http::fake(); // records any real request so we can assert none happened
        DB::shouldReceive('table->where->where->first')->andReturn(null); // cache miss
        DB::shouldReceive('table->updateOrInsert')->andReturn(1);         // storePreview

        $result = $this->service->fetchPreview($url);

        $this->assertNotNull($result, "Expected a provider embed for {$url}");
        $this->assertSame('video', $result['content_type']);
        $this->assertSame($expectedSiteName, $result['site_name']);
        $this->assertStringContainsString($expectedEmbedContains, (string) $result['embed_html']);
        Http::assertNothingSent();
    }

    public static function providerEmbedCases(): array
    {
        return [
            'youtube watch'  => ['https://www.youtube.com/watch?v=dQw4w9WgXcQ', 'YouTube', 'youtube-nocookie.com/embed/dQw4w9WgXcQ'],
            'youtu.be short' => ['https://youtu.be/dQw4w9WgXcQ', 'YouTube', 'youtube-nocookie.com/embed/dQw4w9WgXcQ'],
            'youtube shorts' => ['https://www.youtube.com/shorts/abcDEF12345', 'YouTube', 'embed/abcDEF12345'],
            'vimeo'          => ['https://vimeo.com/123456789', 'Vimeo', 'player.vimeo.com/video/123456789'],
            'spotify track'  => ['https://open.spotify.com/track/4cOdK2wGLETKBW3PvgPWqT', 'Spotify', 'open.spotify.com/embed/track/4cOdK2wGLETKBW3PvgPWqT'],
            'soundcloud'     => ['https://soundcloud.com/artist/some-track', 'SoundCloud', 'w.soundcloud.com/player/'],
            'twitch vod'     => ['https://www.twitch.tv/videos/123456789', 'Twitch', 'player.twitch.tv/?video=123456789'],
            'tiktok'         => ['https://www.tiktok.com/@user/video/1234567890123456789', 'TikTok', 'tiktok.com/embed/v2/1234567890123456789'],
        ];
    }

    public function test_fetchPreview_ignores_non_video_provider_paths(): void
    {
        // A bare provider domain with no embeddable id must NOT short-circuit to
        // a video embed — it should fall through to the normal scrape path.
        Http::fake(['*' => Http::response('<html><head><title>x</title></head></html>', 200, ['Content-Type' => 'text/html'])]);
        DB::shouldReceive('table->where->where->first')->andReturn(null);
        DB::shouldReceive('table->updateOrInsert')->andReturn(1);

        $result = $this->service->fetchPreview('https://www.youtube.com/feed/subscriptions');

        // Either a non-video preview or null, but never a video embed.
        $this->assertNotSame('video', $result['content_type'] ?? null);
    }
}
