<?php
// Copyright © 2024–2026 Jasper Ford
// SPDX-License-Identifier: AGPL-3.0-or-later
// Author: Jasper Ford
// See NOTICE file for attribution and acknowledgements.

namespace App\Tests\Services;

use App\Services\ContextualMessageService;
use App\Tests\TestCase;

class ContextualMessageServiceTest extends TestCase
{
    private ContextualMessageService $service;

    protected function setUp(): void
    {
        parent::setUp();
        $this->service = new ContextualMessageService();
    }

    public function testClassExists(): void
    {
        $this->assertTrue(class_exists(ContextualMessageService::class));
    }

    public function testGetContextInfoReturnsNullForInvalidType(): void
    {
        $result = $this->service->getContextInfo('invalid_type', 1);
        $this->assertNull($result);
    }

    public function testGetContextInfoReturnsNullForNonExistentListing(): void
    {
        $result = $this->service->getContextInfo('listing', 999999);
        $this->assertNull($result);
    }

    public function testGetContextInfoReturnsNullForNonExistentEvent(): void
    {
        $result = $this->service->getContextInfo('event', 999999);
        $this->assertNull($result);
    }

    public function testGetContextInfoReturnsNullForNonExistentJob(): void
    {
        $result = $this->service->getContextInfo('job', 999999);
        $this->assertNull($result);
    }

    public function testGetContextInfoReturnsNullForNonExistentVolunteering(): void
    {
        $result = $this->service->getContextInfo('volunteering', 999999);
        $this->assertNull($result);
    }

    public function testGetContextInfoReturnsNullForNonExistentGroup(): void
    {
        $result = $this->service->getContextInfo('group', 999999);
        $this->assertNull($result);
    }

    public function testGetContextInfoBatchReturnsArray(): void
    {
        $result = $this->service->getContextInfoBatch([]);
        $this->assertIsArray($result);
        $this->assertEmpty($result);
    }

    public function testGetContextInfoBatchDeduplicates(): void
    {
        $pairs = [
            ['type' => 'listing', 'id' => 999999],
            ['type' => 'listing', 'id' => 999999],
        ];
        $result = $this->service->getContextInfoBatch($pairs);
        $this->assertIsArray($result);
        // Non-existent items return nothing, but no errors
    }

    public function testEnrichMessagesWithContextReturnsOriginalWhenNoContext(): void
    {
        $messages = [
            ['id' => 1, 'content' => 'Hello'],
            ['id' => 2, 'content' => 'World'],
        ];

        $result = $this->service->enrichMessagesWithContext($messages);
        $this->assertIsArray($result);
        $this->assertCount(2, $result);
    }

    public function testEnrichMessagesAddsContextInfoKey(): void
    {
        $messages = [
            ['id' => 1, 'content' => 'Hello', 'context_type' => 'listing', 'context_id' => 999999],
        ];

        $result = $this->service->enrichMessagesWithContext($messages);
        $this->assertArrayHasKey('context_info', $result[0]);
        // Non-existent listing, so context_info should be null
        $this->assertNull($result[0]['context_info']);
    }

    public function testEnrichMessagesWithEmptyArray(): void
    {
        $result = $this->service->enrichMessagesWithContext([]);
        $this->assertIsArray($result);
        $this->assertEmpty($result);
    }

    public function testEnrichMessagesPreservesOriginalData(): void
    {
        $messages = [
            ['id' => 1, 'content' => 'Hello', 'sender_id' => 5],
        ];

        $result = $this->service->enrichMessagesWithContext($messages);
        $this->assertSame(1, $result[0]['id']);
        $this->assertSame('Hello', $result[0]['content']);
        $this->assertSame(5, $result[0]['sender_id']);
    }

    public function testValidTypesConstant(): void
    {
        $ref = new \ReflectionClass(ContextualMessageService::class);
        $constant = $ref->getConstant('VALID_TYPES');
        $this->assertIsArray($constant);
        $this->assertContains('listing', $constant);
        $this->assertContains('event', $constant);
        $this->assertContains('job', $constant);
        $this->assertContains('volunteering', $constant);
        $this->assertContains('group', $constant);
        $this->assertCount(5, $constant);
    }

    public function testSendWithContextMethodSignature(): void
    {
        $ref = new \ReflectionMethod(ContextualMessageService::class, 'sendWithContext');
        $params = $ref->getParameters();
        $this->assertCount(6, $params);
        $this->assertSame('senderId', $params[0]->getName());
        $this->assertSame('receiverId', $params[1]->getName());
        $this->assertSame('body', $params[2]->getName());
        $this->assertSame('contextType', $params[3]->getName());
        $this->assertTrue($params[3]->isOptional());
        $this->assertSame('contextId', $params[4]->getName());
        $this->assertTrue($params[4]->isOptional());
        $this->assertSame('subject', $params[5]->getName());
        $this->assertTrue($params[5]->isOptional());
    }
}
