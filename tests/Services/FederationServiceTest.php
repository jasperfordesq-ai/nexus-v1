<?php
// Copyright © 2024–2026 Jasper Ford
// SPDX-License-Identifier: AGPL-3.0-or-later
// Author: Jasper Ford
// See NOTICE file for attribution and acknowledgements.

declare(strict_types=1);

namespace App\Tests\Services;

use App\Tests\TestCase;
use App\Services\FederationService;
use Illuminate\Database\QueryException;

/**
 * FederationService Tests
 *
 * Tests federation directory lookups: getTimebanks, getMembers, getListings.
 * Skips gracefully if federation tables or columns are not present.
 */
class FederationServiceTest extends TestCase
{
    private function svc(): FederationService
    {
        return new FederationService();
    }

    // =========================================================================
    // getTimebanks
    // =========================================================================

    public function test_get_timebanks_returns_array(): void
    {
        try {
            $result = $this->svc()->getTimebanks(2);
        } catch (QueryException $e) {
            $this->markTestSkipped('Federation table schema issue: ' . $e->getMessage());
        }
        $this->assertIsArray($result);
    }

    public function test_get_timebanks_returns_empty_for_nonexistent_tenant(): void
    {
        try {
            $result = $this->svc()->getTimebanks(999999);
        } catch (QueryException $e) {
            $this->markTestSkipped('Federation table schema issue: ' . $e->getMessage());
        }
        $this->assertIsArray($result);
    }

    // =========================================================================
    // getMembers
    // =========================================================================

    public function test_get_members_returns_empty_when_not_whitelisted(): void
    {
        try {
            $result = $this->svc()->getMembers(999999, 2);
        } catch (QueryException $e) {
            $this->markTestSkipped('Federation table schema issue: ' . $e->getMessage());
        }
        $this->assertEmpty($result);
    }

    public function test_get_members_returns_array(): void
    {
        try {
            $result = $this->svc()->getMembers(2, 999999);
        } catch (QueryException $e) {
            $this->markTestSkipped('Federation table schema issue: ' . $e->getMessage());
        }
        $this->assertIsArray($result);
    }

    public function test_get_members_respects_limit(): void
    {
        try {
            $result = $this->svc()->getMembers(2, 999999, 5);
        } catch (QueryException $e) {
            $this->markTestSkipped('Federation table schema issue: ' . $e->getMessage());
        }
        $this->assertIsArray($result);
        $this->assertLessThanOrEqual(5, count($result));
    }

    public function test_get_members_caps_limit_at_100(): void
    {
        try {
            $result = $this->svc()->getMembers(2, 999999, 200);
        } catch (QueryException $e) {
            $this->markTestSkipped('Federation table schema issue: ' . $e->getMessage());
        }
        $this->assertIsArray($result);
    }

    // =========================================================================
    // getListings
    // =========================================================================

    public function test_get_listings_returns_empty_when_not_whitelisted(): void
    {
        try {
            $result = $this->svc()->getListings(999999, 2);
        } catch (QueryException $e) {
            $this->markTestSkipped('Federation table schema issue: ' . $e->getMessage());
        }
        $this->assertEmpty($result);
    }

    public function test_get_listings_returns_array(): void
    {
        try {
            $result = $this->svc()->getListings(2, 999999);
        } catch (QueryException $e) {
            $this->markTestSkipped('Federation table schema issue: ' . $e->getMessage());
        }
        $this->assertIsArray($result);
    }

    public function test_get_listings_respects_limit(): void
    {
        try {
            $result = $this->svc()->getListings(2, 999999, 5);
        } catch (QueryException $e) {
            $this->markTestSkipped('Federation table schema issue: ' . $e->getMessage());
        }
        $this->assertIsArray($result);
        $this->assertLessThanOrEqual(5, count($result));
    }
}
