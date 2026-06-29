<?php
// Copyright © 2024–2026 Jasper Ford
// SPDX-License-Identifier: AGPL-3.0-or-later
// Author: Jasper Ford
// See NOTICE file for attribution and acknowledgements.

namespace Tests\Laravel\Feature\Controllers;

use Illuminate\Foundation\Testing\DatabaseTransactions;
use Tests\Laravel\TestCase;

class PublicStaticRouteContentControllerTest extends TestCase
{
    use DatabaseTransactions;

    public function test_developer_page_content_uses_existing_public_locale_assets(): void
    {
        $response = $this->apiGet('/v2/public-static-route-content/developers');

        $response->assertStatus(200);
        $response->assertJsonPath('data.route_key', 'developers');
        $response->assertJsonPath('data.path', '/developers');
        $response->assertJsonPath('data.content_source', 'react_public_locale');
        $response->assertJsonPath('data.locale_file', 'common.json');
        $response->assertJsonPath('data.translation_namespace', 'common.developers');
        $response->assertJsonPath('data.tenant.id', $this->testTenantId);
        $response->assertJsonPath('data.title', 'Developers');
        $this->assertContains('features', array_column($response->json('data.sections'), 'key'));
        $this->assertContains('oauth', array_column($response->json('data.sections.0.items'), 'key'));
    }

    public function test_hour_public_page_content_uses_existing_about_locale_assets(): void
    {
        $response = $this->apiGet('/v2/public-static-route-content/impact-summary');

        $response->assertStatus(200);
        $response->assertJsonPath('data.route_key', 'hourImpactSummary');
        $response->assertJsonPath('data.path', '/impact-summary');
        $response->assertJsonPath('data.locale_file', 'about.json');
        $response->assertJsonPath('data.translation_namespace', 'about.impact_summary');
        $this->assertStringContainsString('€16', $response->json('data.title'));
        $this->assertContains('highlights', array_column($response->json('data.sections'), 'key'));
    }

    public function test_unknown_static_route_content_is_not_exposed(): void
    {
        $this->apiGet('/v2/public-static-route-content/dashboard')->assertStatus(404);
        $this->apiGet('/v2/public-static-route-content/platform-terms')->assertStatus(404);
    }
}
