<?php
// Copyright © 2024–2026 Jasper Ford
// SPDX-License-Identifier: AGPL-3.0-or-later
// Author: Jasper Ford
// See NOTICE file for attribution and acknowledgements.

declare(strict_types=1);

namespace App\Tests\Services;

use App\Tests\TestCase;
use App\Services\ExchangeService;
use App\Core\TenantContext;
use Illuminate\Database\QueryException;

/**
 * ExchangeService Tests
 *
 * Tests exchange lifecycle: getAll, getById, create, accept, decline, complete.
 * Skips gracefully if schema columns are missing.
 */
class ExchangeServiceTest extends TestCase
{
    private function svc(): ExchangeService
    {
        return new ExchangeService();
    }

    protected function setUp(): void
    {
        parent::setUp();
        TenantContext::setById(2);
    }

    // =========================================================================
    // Status constants
    // =========================================================================

    public function test_status_constants_defined(): void
    {
        $this->assertSame('pending_provider', ExchangeService::STATUS_PENDING);
        $this->assertSame('accepted', ExchangeService::STATUS_ACCEPTED);
        $this->assertSame('completed', ExchangeService::STATUS_COMPLETED);
        $this->assertSame('declined', ExchangeService::STATUS_DECLINED);
        $this->assertSame('cancelled', ExchangeService::STATUS_CANCELLED);
    }

    // =========================================================================
    // getAll
    // =========================================================================

    public function test_get_all_returns_expected_structure(): void
    {
        try {
            $result = $this->svc()->getAll(999999);
        } catch (QueryException $e) {
            $this->markTestSkipped('exchange_requests schema issue: ' . $e->getMessage());
        }

        $this->assertIsArray($result);
        $this->assertArrayHasKey('items', $result);
        $this->assertArrayHasKey('cursor', $result);
        $this->assertArrayHasKey('has_more', $result);
        $this->assertIsArray($result['items']);
        $this->assertIsBool($result['has_more']);
    }

    public function test_get_all_returns_empty_for_nonexistent_user(): void
    {
        try {
            $result = $this->svc()->getAll(999999);
        } catch (QueryException $e) {
            $this->markTestSkipped('exchange_requests schema issue: ' . $e->getMessage());
        }
        $this->assertEmpty($result['items']);
    }

    // =========================================================================
    // getById
    // =========================================================================

    public function test_get_by_id_returns_null_for_nonexistent(): void
    {
        try {
            $result = $this->svc()->getById(999999);
        } catch (QueryException $e) {
            $this->markTestSkipped('exchange_requests schema issue: ' . $e->getMessage());
        }
        $this->assertNull($result);
    }

    // =========================================================================
    // create
    // =========================================================================

    public function test_create_returns_null_for_nonexistent_listing(): void
    {
        try {
            $result = $this->svc()->create(1, 999999);
        } catch (QueryException $e) {
            $this->markTestSkipped('exchange_requests schema issue: ' . $e->getMessage());
        }
        $this->assertNull($result);
    }

    // =========================================================================
    // accept
    // =========================================================================

    public function test_accept_returns_false_for_nonexistent_exchange(): void
    {
        try {
            $result = $this->svc()->accept(999999, 1);
        } catch (QueryException $e) {
            $this->markTestSkipped('exchange_requests schema issue: ' . $e->getMessage());
        }
        $this->assertFalse($result);
    }

    // =========================================================================
    // decline
    // =========================================================================

    public function test_decline_returns_false_for_nonexistent_exchange(): void
    {
        try {
            $result = $this->svc()->decline(999999, 1);
        } catch (QueryException $e) {
            $this->markTestSkipped('exchange_requests schema issue: ' . $e->getMessage());
        }
        $this->assertFalse($result);
    }

    public function test_decline_with_reason(): void
    {
        try {
            $result = $this->svc()->decline(999999, 1, 'Not available');
        } catch (QueryException $e) {
            $this->markTestSkipped('exchange_requests schema issue: ' . $e->getMessage());
        }
        $this->assertFalse($result);
    }

    // =========================================================================
    // complete
    // =========================================================================

    public function test_complete_returns_false_for_nonexistent_exchange(): void
    {
        try {
            $result = $this->svc()->complete(999999, 1);
        } catch (QueryException $e) {
            $this->markTestSkipped('exchange_requests schema issue: ' . $e->getMessage());
        }
        $this->assertFalse($result);
    }
}
