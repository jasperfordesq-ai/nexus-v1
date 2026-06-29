<?php
// Copyright © 2024–2026 Jasper Ford
// SPDX-License-Identifier: AGPL-3.0-or-later
// Author: Jasper Ford
// See NOTICE file for attribution and acknowledgements.

namespace Tests\Laravel\Feature\Controllers;

use Tests\Laravel\TestCase;

class PublicChangelogControllerTest extends TestCase
{
    public function test_public_changelog_returns_release_summary_items(): void
    {
        $response = $this->apiGet('/v2/public-changelog');

        $response->assertStatus(200);
        $response->assertJsonPath('data.route_key', 'changelog');
        $response->assertJsonPath('data.path', '/changelog');
        $response->assertJsonPath('data.content_source', 'public_changelog_markdown');
        $response->assertJsonPath('data.source_path', 'CHANGELOG.md');
        $response->assertJsonPath('data.items.0.id', 'unreleased');
        $response->assertJsonPath('data.items.0.title', 'Unreleased');
        $this->assertNotEmpty($response->json('data.items.0.description'));
    }
}
