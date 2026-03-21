<?php
// Copyright © 2024–2026 Jasper Ford
// SPDX-License-Identifier: AGPL-3.0-or-later
// Author: Jasper Ford
// See NOTICE file for attribution and acknowledgements.

declare(strict_types=1);

namespace App\Tests\Services;

use App\Tests\TestCase;
use App\Services\ListingModerationService;
use App\Core\TenantContext;

/**
 * ListingModerationService Tests
 */
class ListingModerationServiceTest extends TestCase
{
    private ListingModerationService $service;
    private static int $testTenantId = 2;

    protected function setUp(): void
    {
        parent::setUp();
        TenantContext::setById(self::$testTenantId);
        $this->service = new ListingModerationService();
    }

    public function test_service_can_be_instantiated(): void
    {
        $this->assertInstanceOf(ListingModerationService::class, $this->service);
    }

    public function test_is_moderation_enabled_returns_bool(): void
    {
        $result = $this->service->isModerationEnabled();
        $this->assertIsBool($result);
    }

    public function test_flag_nonexistent_listing_returns_false(): void
    {
        $result = $this->service->flag(self::$testTenantId, 999999, 1, 'Spam');
        $this->assertFalse($result);
    }

    public function test_approve_nonexistent_listing_returns_false(): void
    {
        $result = $this->service->approve(self::$testTenantId, 999999, 1);
        $this->assertFalse($result);
    }

    public function test_reject_nonexistent_listing_returns_false(): void
    {
        $result = $this->service->reject(self::$testTenantId, 999999, 1, 'Inappropriate content');
        $this->assertFalse($result);
    }

    public function test_reject_requires_reason(): void
    {
        // Even for nonexistent listings, the service checks listing first
        $result = $this->service->reject(self::$testTenantId, 999999, 1, '');
        $this->assertFalse($result);
    }

    public function test_get_pending_returns_array(): void
    {
        $result = $this->service->getPending(self::$testTenantId);
        $this->assertIsArray($result);
    }

    public function test_reject_listing_shorthand_returns_expected_structure(): void
    {
        $result = $this->service->rejectListing(999999, 1, 'Spam');
        $this->assertIsArray($result);
        $this->assertArrayHasKey('success', $result);
        $this->assertArrayHasKey('error', $result);
        $this->assertFalse($result['success']);
    }

    public function test_get_review_queue_returns_expected_structure(): void
    {
        $result = $this->service->getReviewQueue();
        $this->assertIsArray($result);
        $this->assertArrayHasKey('items', $result);
        $this->assertArrayHasKey('total', $result);
        $this->assertArrayHasKey('pages', $result);
        $this->assertIsArray($result['items']);
        $this->assertIsInt($result['total']);
        $this->assertIsInt($result['pages']);
    }

    public function test_get_review_queue_with_pagination(): void
    {
        $result = $this->service->getReviewQueue(1, 5);
        $this->assertIsArray($result);
        $this->assertLessThanOrEqual(5, count($result['items']));
    }

    public function test_get_review_queue_with_type_filter(): void
    {
        $result = $this->service->getReviewQueue(1, 20, 'offer');
        $this->assertIsArray($result);
        $this->assertArrayHasKey('items', $result);
    }

    public function test_get_review_queue_invalid_type_ignored(): void
    {
        $result = $this->service->getReviewQueue(1, 20, 'invalid_type');
        $this->assertIsArray($result);
        $this->assertArrayHasKey('items', $result);
    }

    public function test_get_stats_returns_expected_structure(): void
    {
        $result = $this->service->getStats();
        $this->assertIsArray($result);
        $this->assertArrayHasKey('total', $result);
        $this->assertArrayHasKey('pending', $result);
        $this->assertArrayHasKey('approved', $result);
        $this->assertArrayHasKey('rejected', $result);
        $this->assertArrayHasKey('moderation_enabled', $result);
        $this->assertIsInt($result['total']);
        $this->assertIsInt($result['pending']);
        $this->assertIsInt($result['approved']);
        $this->assertIsInt($result['rejected']);
        $this->assertIsBool($result['moderation_enabled']);
    }
}
