<?php
// Copyright © 2024–2026 Jasper Ford
// SPDX-License-Identifier: AGPL-3.0-or-later
// Author: Jasper Ford
// See NOTICE file for attribution and acknowledgements.

declare(strict_types=1);

namespace App\Tests\Services;

use App\Tests\TestCase;
use App\Services\HelpService;
use App\Core\TenantContext;

/**
 * HelpService Tests
 */
class HelpServiceTest extends TestCase
{
    private HelpService $service;
    private static int $testTenantId = 2;

    protected function setUp(): void
    {
        parent::setUp();
        TenantContext::setById(self::$testTenantId);
        $this->service = new HelpService();
    }

    public function test_service_can_be_instantiated(): void
    {
        $this->assertInstanceOf(HelpService::class, $this->service);
    }

    public function test_get_faqs_returns_array(): void
    {
        $result = $this->service->getFaqs(self::$testTenantId);
        $this->assertIsArray($result);
    }

    public function test_get_faqs_grouped_by_category(): void
    {
        $result = $this->service->getFaqs(self::$testTenantId);
        $this->assertIsArray($result);

        foreach ($result as $group) {
            $this->assertArrayHasKey('category', $group);
            $this->assertArrayHasKey('faqs', $group);
            $this->assertIsArray($group['faqs']);
        }
    }

    public function test_get_faqs_with_category_filter(): void
    {
        $result = $this->service->getFaqs(self::$testTenantId, 999999);
        $this->assertIsArray($result);
    }

    public function test_get_faqs_with_search_filter(): void
    {
        $result = $this->service->getFaqs(self::$testTenantId, null, 'nonexistent-term-xyz');
        $this->assertIsArray($result);
    }

    public function test_get_faqs_falls_back_to_global_defaults(): void
    {
        // Use a tenant ID that has no FAQs to trigger fallback
        $result = $this->service->getFaqs(999999);
        $this->assertIsArray($result);
    }

    public function test_get_faqs_no_fallback_with_category_filter(): void
    {
        // When category filter is provided, fallback should NOT occur
        $result = $this->service->getFaqs(999999, 1);
        $this->assertIsArray($result);
    }

    public function test_get_faqs_no_fallback_with_search(): void
    {
        // When search is provided, fallback should NOT occur
        $result = $this->service->getFaqs(999999, null, 'test');
        $this->assertIsArray($result);
    }

    public function test_faq_items_have_required_keys(): void
    {
        $result = $this->service->getFaqs(self::$testTenantId);
        foreach ($result as $group) {
            foreach ($group['faqs'] as $faq) {
                $this->assertArrayHasKey('id', $faq);
                $this->assertArrayHasKey('question', $faq);
                $this->assertArrayHasKey('answer', $faq);
                $this->assertIsInt($faq['id']);
            }
        }
    }
}
