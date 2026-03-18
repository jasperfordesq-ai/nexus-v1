<?php
// Copyright © 2024–2026 Jasper Ford
// SPDX-License-Identifier: AGPL-3.0-or-later
// Author: Jasper Ford
// See NOTICE file for attribution and acknowledgements.

declare(strict_types=1);

namespace Tests\Laravel\Migrated\Controllers\Api;

use Tests\Laravel\LegacyBridgeTestCase;

/**
 * Tests for FeedSocialApiController (Laravel migration)
 *
 * Migrated from: Nexus\Tests\Controllers\Api\FeedSocialApiControllerTest
 * Original base: ApiTestCase -> now LegacyBridgeTestCase
 *
 * Note: The original used legacy get/post/delete helpers with controllerAction
 * parameter. Migrated to legacyGet/legacyPost/legacyDelete which use Laravel
 * HTTP testing instead of direct controller invocation.
 */
class FeedSocialApiControllerTest extends LegacyBridgeTestCase
{
    public function testSharePost(): void
    {
        $response = $this->legacyPost('/api/v2/feed/posts/1/share', [
            'comment' => 'Check this out!',
        ]);

        $this->assertIsArray($response);
        $this->assertArrayHasKey('status', $response);
    }

    public function testUnsharePost(): void
    {
        $response = $this->legacyDelete('/api/v2/feed/posts/1/share');

        $this->assertIsArray($response);
    }

    public function testGetSharers(): void
    {
        $response = $this->legacyGet('/api/v2/feed/posts/1/sharers');

        $this->assertIsArray($response);
        $this->assertArrayHasKey('status', $response);
    }

    public function testGetTrendingHashtags(): void
    {
        $response = $this->legacyGet('/api/v2/feed/hashtags/trending');

        $this->assertIsArray($response);
        $this->assertArrayHasKey('status', $response);
    }

    public function testSearchHashtags(): void
    {
        $response = $this->legacyGet('/api/v2/feed/hashtags/search', ['q' => 'community']);

        $this->assertIsArray($response);
    }

    public function testGetHashtagPosts(): void
    {
        $response = $this->legacyGet('/api/v2/feed/hashtags/community');

        $this->assertIsArray($response);
    }
}
