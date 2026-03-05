<?php
// Copyright © 2024–2026 Jasper Ford
// SPDX-License-Identifier: AGPL-3.0-or-later

declare(strict_types=1);

namespace Nexus\Tests\Controllers\Api;

class FeedSocialApiControllerTest extends ApiTestCase
{
    public function testSharePost(): void
    {
        $response = $this->post('/api/v2/feed/posts/1/share', [
            'comment' => 'Check this out!',
        ], [], 'Nexus\Controllers\Api\FeedSocialApiController@sharePost');

        $this->assertIsArray($response);
        $this->assertArrayHasKey('status', $response);
    }

    public function testUnsharePost(): void
    {
        $response = $this->delete('/api/v2/feed/posts/1/share', [], [],
            'Nexus\Controllers\Api\FeedSocialApiController@unsharePost');

        $this->assertIsArray($response);
    }

    public function testGetSharers(): void
    {
        $response = $this->get('/api/v2/feed/posts/1/sharers', [], [],
            'Nexus\Controllers\Api\FeedSocialApiController@getSharers');

        $this->assertIsArray($response);
        $this->assertArrayHasKey('status', $response);
    }

    public function testGetTrendingHashtags(): void
    {
        $response = $this->get('/api/v2/feed/hashtags/trending', [], [],
            'Nexus\Controllers\Api\FeedSocialApiController@getTrendingHashtags');

        $this->assertIsArray($response);
        $this->assertArrayHasKey('status', $response);
    }

    public function testSearchHashtags(): void
    {
        $response = $this->get('/api/v2/feed/hashtags/search', ['q' => 'community'], [],
            'Nexus\Controllers\Api\FeedSocialApiController@searchHashtags');

        $this->assertIsArray($response);
    }

    public function testGetHashtagPosts(): void
    {
        $response = $this->get('/api/v2/feed/hashtags/community', [], [],
            'Nexus\Controllers\Api\FeedSocialApiController@getHashtagPosts');

        $this->assertIsArray($response);
    }
}
