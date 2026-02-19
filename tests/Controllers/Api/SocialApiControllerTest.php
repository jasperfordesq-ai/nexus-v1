<?php
// Copyright © 2024–2026 Jasper Ford
// SPDX-License-Identifier: AGPL-3.0-or-later
// Author: Jasper Ford
// See NOTICE file for attribution and acknowledgements.

declare(strict_types=1);

namespace Nexus\Tests\Controllers\Api;

/**
 * Tests for SocialApiController endpoints
 *
 * Tests social features including likes, comments, reactions,
 * shares, and feed posts.
 */
class SocialApiControllerTest extends ApiTestCase
{
    /**
     * Test GET /api/social/test
     */
    public function testSocialApiTest(): void
    {
        $response = $this->get('/api/social/test');

        $this->assertEquals('/api/social/test', $response['endpoint']);
    }

    /**
     * Test POST /api/social/like
     */
    public function testLikePost(): void
    {
        $response = $this->post('/api/social/like', [
            'post_id' => 1,
            'type' => 'feed_post'
        ]);

        $this->assertEquals('POST', $response['method']);
        $this->assertArrayHasKey('post_id', $response['data']);
    }

    /**
     * Test POST /api/social/likers
     */
    public function testGetLikers(): void
    {
        $response = $this->post('/api/social/likers', [
            'post_id' => 1,
            'type' => 'feed_post'
        ]);

        $this->assertEquals('POST', $response['method']);
        $this->assertArrayHasKey('post_id', $response['data']);
    }

    /**
     * Test POST /api/social/comments
     */
    public function testGetComments(): void
    {
        $response = $this->post('/api/social/comments', [
            'post_id' => 1,
            'limit' => 20
        ]);

        $this->assertEquals('POST', $response['method']);
        $this->assertArrayHasKey('post_id', $response['data']);
    }

    /**
     * Test POST /api/social/reply
     */
    public function testReplyToPost(): void
    {
        $response = $this->post('/api/social/reply', [
            'post_id' => 1,
            'comment' => 'Test comment',
            'parent_id' => null
        ]);

        $this->assertEquals('POST', $response['method']);
        $this->assertArrayHasKey('post_id', $response['data']);
        $this->assertArrayHasKey('comment', $response['data']);
    }

    /**
     * Test POST /api/social/edit-comment
     */
    public function testEditComment(): void
    {
        $response = $this->post('/api/social/edit-comment', [
            'comment_id' => 1,
            'comment' => 'Updated comment'
        ]);

        $this->assertEquals('POST', $response['method']);
        $this->assertArrayHasKey('comment_id', $response['data']);
        $this->assertArrayHasKey('comment', $response['data']);
    }

    /**
     * Test POST /api/social/delete-comment
     */
    public function testDeleteComment(): void
    {
        $response = $this->post('/api/social/delete-comment', [
            'comment_id' => 1
        ]);

        $this->assertEquals('POST', $response['method']);
        $this->assertArrayHasKey('comment_id', $response['data']);
    }

    /**
     * Test POST /api/social/reaction
     */
    public function testAddReaction(): void
    {
        $response = $this->post('/api/social/reaction', [
            'post_id' => 1,
            'reaction_type' => 'love'
        ]);

        $this->assertEquals('POST', $response['method']);
        $this->assertArrayHasKey('post_id', $response['data']);
        $this->assertArrayHasKey('reaction_type', $response['data']);
    }

    /**
     * Test POST /api/social/share
     */
    public function testSharePost(): void
    {
        $response = $this->post('/api/social/share', [
            'post_id' => 1,
            'share_text' => 'Check this out!'
        ]);

        $this->assertEquals('POST', $response['method']);
        $this->assertArrayHasKey('post_id', $response['data']);
    }

    /**
     * Test POST /api/social/delete
     */
    public function testDeletePost(): void
    {
        $response = $this->post('/api/social/delete', [
            'post_id' => 1
        ]);

        $this->assertEquals('POST', $response['method']);
        $this->assertArrayHasKey('post_id', $response['data']);
    }

    /**
     * Test POST /api/social/mention-search
     */
    public function testMentionSearch(): void
    {
        $response = $this->post('/api/social/mention-search', [
            'query' => 'test',
            'limit' => 10
        ]);

        $this->assertEquals('POST', $response['method']);
        $this->assertArrayHasKey('query', $response['data']);
    }

    /**
     * Test POST /api/social/feed
     */
    public function testGetFeed(): void
    {
        $response = $this->post('/api/social/feed', [
            'page' => 1,
            'limit' => 20,
            'filter' => 'all'
        ]);

        $this->assertEquals('POST', $response['method']);
        $this->assertArrayHasKey('page', $response['data']);
        $this->assertArrayHasKey('limit', $response['data']);
    }

    /**
     * Test POST /api/social/create-post
     */
    public function testCreatePost(): void
    {
        $response = $this->post('/api/social/create-post', [
            'content' => 'Test post content',
            'visibility' => 'public',
            'attachments' => []
        ]);

        $this->assertEquals('POST', $response['method']);
        $this->assertArrayHasKey('content', $response['data']);
        $this->assertArrayHasKey('visibility', $response['data']);
    }

    /**
     * Test creating post with mentions
     */
    public function testCreatePostWithMentions(): void
    {
        $response = $this->post('/api/social/create-post', [
            'content' => 'Hello @testuser, check this out!',
            'visibility' => 'public',
            'mentions' => [2, 3]
        ]);

        $this->assertArrayHasKey('content', $response['data']);
        $this->assertArrayHasKey('mentions', $response['data']);
    }
}
