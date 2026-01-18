<?php

declare(strict_types=1);

namespace Nexus\Tests\Controllers\Api;

/**
 * Tests for AiApiController endpoints
 *
 * Tests AI-powered features including chat, conversations,
 * content generation, and AI provider management.
 */
class AiApiControllerTest extends ApiTestCase
{
    /**
     * Test POST /api/ai/chat
     */
    public function testAiChat(): void
    {
        $response = $this->post('/api/ai/chat', [
            'message' => 'Hello, how can I help?',
            'conversation_id' => null,
            'model' => 'gpt-4'
        ]);

        $this->assertEquals('POST', $response['method']);
        $this->assertArrayHasKey('message', $response['data']);
    }

    /**
     * Test POST /api/ai/chat/stream
     */
    public function testAiChatStream(): void
    {
        $response = $this->post('/api/ai/chat/stream', [
            'message' => 'Tell me a story',
            'conversation_id' => 1
        ]);

        $this->assertEquals('/api/ai/chat/stream', $response['endpoint']);
        $this->assertArrayHasKey('message', $response['data']);
    }

    /**
     * Test GET /api/ai/conversations
     */
    public function testListConversations(): void
    {
        $response = $this->get('/api/ai/conversations', [
            'limit' => 10,
            'offset' => 0
        ]);

        $this->assertEquals('/api/ai/conversations', $response['endpoint']);
    }

    /**
     * Test GET /api/ai/conversations/{id}
     */
    public function testGetConversation(): void
    {
        $response = $this->get('/api/ai/conversations/1');

        $this->assertEquals('/api/ai/conversations/1', $response['endpoint']);
    }

    /**
     * Test POST /api/ai/conversations
     */
    public function testCreateConversation(): void
    {
        $response = $this->post('/api/ai/conversations', [
            'title' => 'New AI Conversation',
            'model' => 'gpt-4'
        ]);

        $this->assertEquals('POST', $response['method']);
        $this->assertArrayHasKey('title', $response['data']);
    }

    /**
     * Test DELETE /api/ai/conversations/{id}
     */
    public function testDeleteConversation(): void
    {
        $response = $this->delete('/api/ai/conversations/1');

        $this->assertEquals('DELETE', $response['method']);
        $this->assertEquals('/api/ai/conversations/1', $response['endpoint']);
    }

    /**
     * Test GET /api/ai/providers
     */
    public function testGetProviders(): void
    {
        $response = $this->get('/api/ai/providers');

        $this->assertEquals('/api/ai/providers', $response['endpoint']);
    }

    /**
     * Test GET /api/ai/limits
     */
    public function testGetLimits(): void
    {
        $response = $this->get('/api/ai/limits');

        $this->assertEquals('/api/ai/limits', $response['endpoint']);
    }

    /**
     * Test POST /api/ai/test-provider
     */
    public function testTestProvider(): void
    {
        $response = $this->post('/api/ai/test-provider', [
            'provider' => 'openai',
            'api_key' => 'test_key'
        ]);

        $this->assertEquals('POST', $response['method']);
        $this->assertArrayHasKey('provider', $response['data']);
    }

    /**
     * Test POST /api/ai/generate/listing
     */
    public function testGenerateListing(): void
    {
        $response = $this->post('/api/ai/generate/listing', [
            'prompt' => 'Generate a listing for web design services',
            'category' => 'services'
        ]);

        $this->assertEquals('POST', $response['method']);
        $this->assertArrayHasKey('prompt', $response['data']);
    }

    /**
     * Test POST /api/ai/generate/event
     */
    public function testGenerateEvent(): void
    {
        $response = $this->post('/api/ai/generate/event', [
            'prompt' => 'Create a community meetup event',
            'date' => '2026-02-15'
        ]);

        $this->assertEquals('POST', $response['method']);
        $this->assertArrayHasKey('prompt', $response['data']);
    }

    /**
     * Test POST /api/ai/generate/message
     */
    public function testGenerateMessage(): void
    {
        $response = $this->post('/api/ai/generate/message', [
            'context' => 'Thanking someone for their help',
            'tone' => 'friendly'
        ]);

        $this->assertEquals('POST', $response['method']);
        $this->assertArrayHasKey('context', $response['data']);
    }

    /**
     * Test POST /api/ai/generate/bio
     */
    public function testGenerateBio(): void
    {
        $response = $this->post('/api/ai/generate/bio', [
            'interests' => ['coding', 'music', 'hiking'],
            'occupation' => 'Software Developer'
        ]);

        $this->assertEquals('POST', $response['method']);
        $this->assertArrayHasKey('interests', $response['data']);
    }

    /**
     * Test POST /api/ai/generate/newsletter
     */
    public function testGenerateNewsletter(): void
    {
        $response = $this->post('/api/ai/generate/newsletter', [
            'topics' => ['community updates', 'upcoming events'],
            'tone' => 'professional'
        ]);

        $this->assertEquals('POST', $response['method']);
        $this->assertArrayHasKey('topics', $response['data']);
    }

    /**
     * Test POST /api/ai/generate/blog
     */
    public function testGenerateBlog(): void
    {
        $response = $this->post('/api/ai/generate/blog', [
            'topic' => 'The benefits of timebanking',
            'length' => 'medium'
        ]);

        $this->assertEquals('POST', $response['method']);
        $this->assertArrayHasKey('topic', $response['data']);
    }

    /**
     * Test POST /api/ai/generate/page
     */
    public function testGeneratePage(): void
    {
        $response = $this->post('/api/ai/generate/page', [
            'page_type' => 'about',
            'organization' => 'Test Community'
        ]);

        $this->assertEquals('POST', $response['method']);
        $this->assertArrayHasKey('page_type', $response['data']);
    }
}
