<?php
// Copyright © 2024–2026 Jasper Ford
// SPDX-License-Identifier: AGPL-3.0-or-later
// Author: Jasper Ford
// See NOTICE file for attribution and acknowledgements.

namespace Tests\Laravel\Feature\Services;

use App\Core\TenantContext;
use App\Models\PodcastEpisode;
use App\Services\PodcastConfigurationService;
use App\Services\PodcastService;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Illuminate\Support\Facades\Cache;
use Tests\Laravel\TestCase;

/**
 * Regression guard for Sentry NEXUS-PHP-2K
 * (Error: Class "League\Flysystem\AwsS3V3\PortableVisibilityConverter" not found
 *  in PodcastService::cloudMediaUrl).
 *
 * When a podcast episode is flagged as cloud-stored but the configured cloud
 * disk driver is unavailable (e.g. the `s3` disk without the AWS Flysystem
 * package installed, or a typo'd disk name), generating its audio URL used to
 * throw a fatal and 500 the request. It must instead fall back to the in-app
 * media proxy route, which always works.
 */
class PodcastEpisodeAudioUrlFallbackTest extends TestCase
{
    use DatabaseTransactions;

    protected function setUp(): void
    {
        parent::setUp();
        Cache::flush();
        TenantContext::setById($this->testTenantId);
    }

    public function test_unresolvable_cloud_disk_falls_back_to_media_proxy_instead_of_throwing(): void
    {
        // No CDN base, and a disk name that has no configured driver — the same
        // class of failure as a missing AWS Flysystem package in production.
        PodcastConfigurationService::set(PodcastConfigurationService::CONFIG_CLOUD_CDN_BASE_URL, '');
        PodcastConfigurationService::set(PodcastConfigurationService::CONFIG_CLOUD_STORAGE_DISK, 'definitely_not_a_configured_disk');

        $episode = new PodcastEpisode();
        $episode->id = 4242;
        $episode->tenant_id = $this->testTenantId;
        $episode->audio_storage_disk = 's3';
        $episode->audio_storage_path = 'episodes/test.mp3';

        $url = PodcastService::episodeAudioUrl($episode, false);

        $this->assertStringContainsString(
            '/api/v2/podcasts/media/' . $this->testTenantId . '/4242/audio',
            $url,
            'Expected a fallback to the in-app media proxy when the cloud disk driver is unresolvable.'
        );
    }
}
