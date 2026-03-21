<?php
// Copyright © 2024–2026 Jasper Ford
// SPDX-License-Identifier: AGPL-3.0-or-later
// Author: Jasper Ford
// See NOTICE file for attribution and acknowledgements.

declare(strict_types=1);

namespace App\Tests\Services;

use App\Tests\TestCase;
use App\Services\KnowledgeBaseService;
use App\Core\TenantContext;

/**
 * KnowledgeBaseService Tests
 */
class KnowledgeBaseServiceTest extends TestCase
{
    private KnowledgeBaseService $service;
    private static int $testTenantId = 2;

    protected function setUp(): void
    {
        parent::setUp();
        TenantContext::setById(self::$testTenantId);
        $this->service = new KnowledgeBaseService();
    }

    public function test_service_can_be_instantiated(): void
    {
        $this->assertInstanceOf(KnowledgeBaseService::class, $this->service);
    }

    public function test_get_errors_returns_empty_array_initially(): void
    {
        $this->assertSame([], $this->service->getErrors());
    }

    public function test_get_all_returns_paginated_structure(): void
    {
        $result = $this->service->getAll();
        $this->assertIsArray($result);
        $this->assertArrayHasKey('items', $result);
        $this->assertArrayHasKey('cursor', $result);
        $this->assertArrayHasKey('has_more', $result);
        $this->assertIsArray($result['items']);
        $this->assertIsBool($result['has_more']);
    }

    public function test_get_all_with_limit(): void
    {
        $result = $this->service->getAll(['limit' => 3]);
        $this->assertLessThanOrEqual(3, count($result['items']));
    }

    public function test_get_all_with_search_filter(): void
    {
        $result = $this->service->getAll(['search' => 'nonexistent-term-xyz']);
        $this->assertIsArray($result);
        $this->assertArrayHasKey('items', $result);
    }

    public function test_get_all_with_category_filter(): void
    {
        $result = $this->service->getAll(['category_id' => 999999]);
        $this->assertIsArray($result);
        $this->assertEmpty($result['items']);
    }

    public function test_get_all_with_parent_article_filter_root(): void
    {
        $result = $this->service->getAll(['parent_article_id' => 0]);
        $this->assertIsArray($result);
        $this->assertArrayHasKey('items', $result);
    }

    public function test_get_all_published_only_default(): void
    {
        $result = $this->service->getAll();
        $this->assertIsArray($result);
        // Default is published_only = true
    }

    public function test_get_all_include_unpublished(): void
    {
        $result = $this->service->getAll(['published_only' => false]);
        $this->assertIsArray($result);
        $this->assertArrayHasKey('items', $result);
    }

    public function test_get_by_id_returns_null_for_nonexistent(): void
    {
        $result = $this->service->getById(999999);
        $this->assertNull($result);
    }

    public function test_get_by_id_without_incrementing_views(): void
    {
        $result = $this->service->getById(999999, false);
        $this->assertNull($result);
    }

    public function test_search_returns_array(): void
    {
        $result = $this->service->search('test');
        $this->assertIsArray($result);
    }

    public function test_search_respects_limit(): void
    {
        $result = $this->service->search('a', 3);
        $this->assertIsArray($result);
        $this->assertLessThanOrEqual(3, count($result));
    }

    public function test_search_returns_empty_for_gibberish(): void
    {
        $result = $this->service->search('zzzznonexistentxyzabc123');
        $this->assertIsArray($result);
        $this->assertEmpty($result);
    }

    public function test_delete_returns_false_for_nonexistent(): void
    {
        $result = $this->service->delete(999999);
        $this->assertFalse($result);
    }

    public function test_submit_feedback_returns_false_for_nonexistent_article(): void
    {
        $result = $this->service->submitFeedback(999999, 1, true);
        $this->assertFalse($result);
    }

    public function test_submit_feedback_accepts_null_user(): void
    {
        $result = $this->service->submitFeedback(999999, null, true);
        $this->assertFalse($result);
    }

    public function test_submit_feedback_with_comment(): void
    {
        $result = $this->service->submitFeedback(999999, null, false, 'Not helpful at all');
        $this->assertFalse($result);
    }
}
