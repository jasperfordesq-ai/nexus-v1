<?php
// Copyright © 2024–2026 Jasper Ford
// SPDX-License-Identifier: AGPL-3.0-or-later
// Author: Jasper Ford
// See NOTICE file for attribution and acknowledgements.

namespace Tests\Laravel\Feature\Controllers;

use Illuminate\Foundation\Testing\DatabaseTransactions;
use Tests\Laravel\TestCase;

class StaticPublicPageControllerTest extends TestCase
{
    use DatabaseTransactions;

    public function test_foundation_page_content_is_public_and_tenant_aware(): void
    {
        $response = $this->apiGet('/v2/public-page-content/about');

        $response->assertStatus(200);
        $response->assertJsonPath('data.route_key', 'about');
        $response->assertJsonPath('data.page_key', 'about');
        $response->assertJsonPath('data.path', '/about');
        $response->assertJsonPath('data.content_source', 'laravel_public_translations');
        $response->assertJsonPath('data.tenant.id', $this->testTenantId);
        $response->assertJsonPath('data.tenant.slug', $this->testTenantSlug);
        $response->assertJsonPath('data.tenant.name', 'Hour Timebank');
        $this->assertStringContainsString('Hour Timebank', $response->json('data.title'));
        $this->assertNotEmpty($response->json('data.lead'));
        $this->assertNotEmpty($response->json('data.sections'));
    }

    public function test_timebanking_guide_returns_structured_public_sections(): void
    {
        $response = $this->apiGet('/v2/public-page-content/timebanking-guide');

        $response->assertStatus(200);
        $response->assertJsonPath('data.route_key', 'timebankingGuide');
        $response->assertJsonPath('data.path', '/timebanking-guide');
        $response->assertJsonPath('data.translation_namespace', 'govuk_alpha.guide');
        $this->assertContains('steps', array_column($response->json('data.sections'), 'key'));
        $this->assertContains('getting_started', array_column($response->json('data.sections'), 'key'));
    }

    public function test_private_and_unknown_pages_are_not_exposed(): void
    {
        $this->apiGet('/v2/public-page-content/dashboard')->assertStatus(404);
        $this->apiGet('/v2/public-page-content/coupon-detail')->assertStatus(404);
    }
}
