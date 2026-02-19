<?php
// Copyright © 2024–2026 Jasper Ford
// SPDX-License-Identifier: AGPL-3.0-or-later
// Author: Jasper Ford
// See NOTICE file for attribution and acknowledgements.

declare(strict_types=1);

namespace Nexus\Tests\Controllers\Api;

/**
 * Tests for CoreApiController endpoints
 *
 * Tests core platform data endpoints including members, listings,
 * groups, messages, and notifications.
 */
class CoreApiControllerTest extends ApiTestCase
{
    /**
     * Test GET /api/members
     */
    public function testGetMembers(): void
    {
        $response = $this->get('/api/members', [
            'limit' => 10,
            'offset' => 0
        ]);

        $this->assertEquals('GET', $response['method']);
        $this->assertEquals('/api/members', $response['endpoint']);
        $this->assertArrayHasKey('limit', $response['data']);
    }

    /**
     * Test GET /api/members with search
     */
    public function testSearchMembers(): void
    {
        $response = $this->get('/api/members', [
            'search' => 'test',
            'limit' => 5
        ]);

        $this->assertArrayHasKey('search', $response['data']);
        $this->assertEquals('test', $response['data']['search']);
    }

    /**
     * Test GET /api/listings
     */
    public function testGetListings(): void
    {
        $response = $this->get('/api/listings', [
            'page' => 1,
            'limit' => 20
        ]);

        $this->assertEquals('GET', $response['method']);
        $this->assertEquals('/api/listings', $response['endpoint']);
    }

    /**
     * Test GET /api/listings with filters
     */
    public function testGetListingsWithFilters(): void
    {
        $response = $this->get('/api/listings', [
            'category' => 'services',
            'type' => 'offer',
            'limit' => 10
        ]);

        $this->assertArrayHasKey('category', $response['data']);
        $this->assertArrayHasKey('type', $response['data']);
    }

    /**
     * Test GET /api/groups
     */
    public function testGetGroups(): void
    {
        $response = $this->get('/api/groups');

        $this->assertEquals('/api/groups', $response['endpoint']);
    }

    /**
     * Test GET /api/messages
     */
    public function testGetMessages(): void
    {
        $response = $this->get('/api/messages', [
            'conversation_id' => 1,
            'limit' => 50
        ]);

        $this->assertEquals('/api/messages', $response['endpoint']);
        $this->assertArrayHasKey('conversation_id', $response['data']);
    }

    /**
     * Test POST /api/messages/send
     */
    public function testSendMessage(): void
    {
        $response = $this->post('/api/messages/send', [
            'recipient_id' => 2,
            'message' => 'Test message',
            'type' => 'text'
        ]);

        $this->assertEquals('POST', $response['method']);
        $this->assertArrayHasKey('recipient_id', $response['data']);
        $this->assertArrayHasKey('message', $response['data']);
    }

    /**
     * Test GET /api/messages/poll
     */
    public function testPollMessages(): void
    {
        $response = $this->get('/api/messages/poll', [
            'last_id' => 0
        ]);

        $this->assertEquals('/api/messages/poll', $response['endpoint']);
    }

    /**
     * Test GET /api/messages/unread-count
     */
    public function testGetUnreadMessagesCount(): void
    {
        $response = $this->get('/api/messages/unread-count');

        $this->assertEquals('/api/messages/unread-count', $response['endpoint']);
    }

    /**
     * Test POST /api/messages/typing
     */
    public function testSendTypingIndicator(): void
    {
        $response = $this->post('/api/messages/typing', [
            'conversation_id' => 1,
            'is_typing' => true
        ]);

        $this->assertEquals('POST', $response['method']);
        $this->assertArrayHasKey('conversation_id', $response['data']);
    }

    /**
     * Test GET /api/notifications
     */
    public function testGetNotifications(): void
    {
        $response = $this->get('/api/notifications', [
            'limit' => 20,
            'offset' => 0
        ]);

        $this->assertEquals('/api/notifications', $response['endpoint']);
    }

    /**
     * Test GET /api/notifications/check
     */
    public function testCheckNotifications(): void
    {
        $response = $this->get('/api/notifications/check');

        $this->assertEquals('/api/notifications/check', $response['endpoint']);
    }

    /**
     * Test GET /api/notifications/unread-count
     */
    public function testGetUnreadNotificationsCount(): void
    {
        $response = $this->get('/api/notifications/unread-count');

        $this->assertEquals('/api/notifications/unread-count', $response['endpoint']);
    }

    /**
     * Test POST /api/listings/delete
     */
    public function testDeleteListing(): void
    {
        $response = $this->post('/api/listings/delete', [
            'listing_id' => 123
        ]);

        $this->assertEquals('POST', $response['method']);
        $this->assertArrayHasKey('listing_id', $response['data']);
    }

    /**
     * Test POST /api/listings (create)
     */
    public function testCreateListing(): void
    {
        $response = $this->post('/api/listings', [
            'title' => 'Test Listing',
            'description' => 'Test description',
            'category' => 'services',
            'type' => 'offer',
            'price' => 10
        ]);

        $this->assertEquals('POST', $response['method']);
        $this->assertArrayHasKey('title', $response['data']);
        $this->assertArrayHasKey('description', $response['data']);
    }
}
