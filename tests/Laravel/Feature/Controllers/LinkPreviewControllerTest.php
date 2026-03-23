<?php
// Copyright © 2024–2026 Jasper Ford
// SPDX-License-Identifier: AGPL-3.0-or-later
// Author: Jasper Ford
// See NOTICE file for attribution and acknowledgements.

namespace Tests\Laravel\Feature\Controllers;

use App\Models\User;
use App\Services\LinkPreviewService;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Laravel\Sanctum\Sanctum;
use Mockery;
use Tests\Laravel\TestCase;

/**
 * Feature tests for LinkPreviewController — GET and POST link preview endpoints.
 */
class LinkPreviewControllerTest extends TestCase
{
    use DatabaseTransactions;

    private function authenticatedUser(array $overrides = []): User
    {
        $user = User::factory()->forTenant($this->testTenantId)->create(array_merge([
            'status' => 'active',
            'is_approved' => true,
        ], $overrides));

        Sanctum::actingAs($user, ['*']);

        return $user;
    }

    // ------------------------------------------------------------------
    //  GET /api/v2/link-preview?url=...
    // ------------------------------------------------------------------

    public function test_show_requires_authentication(): void
    {
        $response = $this->apiGet('/v2/link-preview?url=https://example.com');

        $response->assertStatus(401);
    }

    public function test_show_requires_url_parameter(): void
    {
        $this->authenticatedUser();

        $response = $this->apiGet('/v2/link-preview');

        $response->assertStatus(400);
        $response->assertJsonPath('errors.0.code', 'VALIDATION_ERROR');
    }

    public function test_show_rejects_invalid_url(): void
    {
        $this->authenticatedUser();

        $response = $this->apiGet('/v2/link-preview?url=not-a-valid-url');

        $response->assertStatus(400);
        $response->assertJsonPath('errors.0.code', 'VALIDATION_ERROR');
    }

    public function test_show_returns_preview_data(): void
    {
        $this->authenticatedUser();

        $previewData = [
            'url' => 'https://example.com',
            'title' => 'Example Domain',
            'description' => 'This domain is for use in illustrative examples.',
            'image' => 'https://example.com/image.jpg',
            'image_url' => 'https://example.com/image.jpg',
            'siteName' => 'Example',
            'site_name' => 'Example',
            'favicon_url' => 'https://example.com/favicon.ico',
            'domain' => 'example.com',
            'content_type' => 'website',
            'embed_html' => null,
        ];

        $mock = Mockery::mock(LinkPreviewService::class);
        $mock->shouldReceive('fetchPreview')
            ->once()
            ->with('https://example.com')
            ->andReturn($previewData);
        $this->app->instance(LinkPreviewService::class, $mock);

        $response = $this->apiGet('/v2/link-preview?url=' . urlencode('https://example.com'));

        $response->assertStatus(200);
        $response->assertJsonStructure(['data']);
        $response->assertJsonPath('data.title', 'Example Domain');
        $response->assertJsonPath('data.domain', 'example.com');
    }

    public function test_show_returns_404_when_preview_not_found(): void
    {
        $this->authenticatedUser();

        $mock = Mockery::mock(LinkPreviewService::class);
        $mock->shouldReceive('fetchPreview')
            ->once()
            ->andReturn(null);
        $this->app->instance(LinkPreviewService::class, $mock);

        $response = $this->apiGet('/v2/link-preview?url=' . urlencode('https://unreachable.example.com'));

        $response->assertStatus(404);
        $response->assertJsonPath('errors.0.code', 'NOT_FOUND');
    }

    public function test_show_rejects_empty_url(): void
    {
        $this->authenticatedUser();

        $response = $this->apiGet('/v2/link-preview?url=');

        $response->assertStatus(400);
    }

    // ------------------------------------------------------------------
    //  POST /api/v2/link-preview
    // ------------------------------------------------------------------

    public function test_fetch_requires_authentication(): void
    {
        $response = $this->apiPost('/v2/link-preview', ['url' => 'https://example.com']);

        $response->assertStatus(401);
    }

    public function test_fetch_requires_url_in_body(): void
    {
        $this->authenticatedUser();

        $response = $this->apiPost('/v2/link-preview', []);

        $response->assertStatus(400);
        $response->assertJsonPath('errors.0.code', 'VALIDATION_ERROR');
    }

    public function test_fetch_rejects_invalid_url(): void
    {
        $this->authenticatedUser();

        $response = $this->apiPost('/v2/link-preview', ['url' => 'ftp://invalid']);

        $response->assertStatus(400);
        $response->assertJsonPath('errors.0.code', 'VALIDATION_ERROR');
    }

    public function test_fetch_returns_preview_data(): void
    {
        $this->authenticatedUser();

        $previewData = [
            'url' => 'https://github.com',
            'title' => 'GitHub',
            'description' => 'Where the world builds software.',
            'image' => 'https://github.com/og.png',
            'image_url' => 'https://github.com/og.png',
            'siteName' => 'GitHub',
            'site_name' => 'GitHub',
            'favicon_url' => 'https://github.com/favicon.ico',
            'domain' => 'github.com',
            'content_type' => 'website',
            'embed_html' => null,
        ];

        $mock = Mockery::mock(LinkPreviewService::class);
        $mock->shouldReceive('fetchPreview')
            ->once()
            ->with('https://github.com')
            ->andReturn($previewData);
        $this->app->instance(LinkPreviewService::class, $mock);

        $response = $this->apiPost('/v2/link-preview', ['url' => 'https://github.com']);

        $response->assertStatus(200);
        $response->assertJsonStructure(['data']);
        $response->assertJsonPath('data.title', 'GitHub');
        $response->assertJsonPath('data.domain', 'github.com');
    }

    public function test_fetch_returns_404_when_preview_not_found(): void
    {
        $this->authenticatedUser();

        $mock = Mockery::mock(LinkPreviewService::class);
        $mock->shouldReceive('fetchPreview')
            ->once()
            ->andReturn(null);
        $this->app->instance(LinkPreviewService::class, $mock);

        $response = $this->apiPost('/v2/link-preview', ['url' => 'https://unreachable.example.com']);

        $response->assertStatus(404);
        $response->assertJsonPath('errors.0.code', 'NOT_FOUND');
    }

    public function test_fetch_rejects_non_url_string(): void
    {
        $this->authenticatedUser();

        $response = $this->apiPost('/v2/link-preview', ['url' => 'just-some-text']);

        $response->assertStatus(400);
    }
}
