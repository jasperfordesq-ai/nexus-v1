<?php
// Copyright © 2024–2026 Jasper Ford
// SPDX-License-Identifier: AGPL-3.0-or-later
// Author: Jasper Ford
// See NOTICE file for attribution and acknowledgements.

namespace Tests\Laravel\Unit\Services;

use App\Services\PodcastConfigurationService;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Tests\Laravel\TestCase;

class PodcastConfigurationServiceTest extends TestCase
{
    use DatabaseTransactions;

    public function test_defaults_allow_member_created_shows_without_moderation(): void
    {
        $this->assertTrue(PodcastConfigurationService::get(PodcastConfigurationService::CONFIG_ALLOW_MEMBER_SHOW_CREATION));
        $this->assertFalse(PodcastConfigurationService::get(PodcastConfigurationService::CONFIG_MODERATION_ENABLED));
    }

    public function test_get_all_exposes_top_level_podcast_options(): void
    {
        $config = PodcastConfigurationService::getAll();

        $this->assertSame(true, $config[PodcastConfigurationService::CONFIG_ALLOW_MEMBER_SHOW_CREATION]);
        $this->assertSame(false, $config[PodcastConfigurationService::CONFIG_MODERATION_ENABLED]);
        $this->assertSame(true, $config[PodcastConfigurationService::CONFIG_ENABLE_RSS_FEED]);
        $this->assertSame(true, $config[PodcastConfigurationService::CONFIG_ENABLE_TRANSCRIPTS]);
        $this->assertSame(true, $config[PodcastConfigurationService::CONFIG_ENABLE_CHAPTERS]);
        $this->assertSame('local', $config[PodcastConfigurationService::CONFIG_MEDIA_STORAGE_DRIVER]);
        $this->assertSame('s3', $config[PodcastConfigurationService::CONFIG_CLOUD_STORAGE_DISK]);
    }

    public function test_set_persists_tenant_scoped_values(): void
    {
        PodcastConfigurationService::set(PodcastConfigurationService::CONFIG_MODERATION_ENABLED, true);
        PodcastConfigurationService::set(PodcastConfigurationService::CONFIG_MAX_SHOWS_PER_USER, 3);

        $this->assertTrue(PodcastConfigurationService::get(PodcastConfigurationService::CONFIG_MODERATION_ENABLED));
        $this->assertSame(3, PodcastConfigurationService::get(PodcastConfigurationService::CONFIG_MAX_SHOWS_PER_USER));
    }
}
