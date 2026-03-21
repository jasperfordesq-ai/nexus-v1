<?php
// Copyright © 2024–2026 Jasper Ford
// SPDX-License-Identifier: AGPL-3.0-or-later
// Author: Jasper Ford
// See NOTICE file for attribution and acknowledgements.

namespace Tests\Laravel\Unit\Services;

use Tests\Laravel\TestCase;
use App\Services\CookieInventoryService;
use App\Models\CookieInventoryItem;
use Mockery;

class CookieInventoryServiceTest extends TestCase
{
    public function test_getCookie_returns_null_when_not_found(): void
    {
        $this->markTestIncomplete('Requires integration test — Eloquent models cannot use shouldReceive()');
    }

    public function test_getCookie_returns_array_when_found(): void
    {
        $this->markTestIncomplete('Requires integration test — Eloquent models cannot use shouldReceive()');
    }

    public function test_addCookie_throws_when_missing_required_field(): void
    {
        $this->expectException(\InvalidArgumentException::class);
        CookieInventoryService::addCookie(['cookie_name' => 'test']);
    }

    public function test_addCookie_throws_for_invalid_category(): void
    {
        $this->expectException(\InvalidArgumentException::class);
        CookieInventoryService::addCookie([
            'cookie_name' => 'test',
            'category' => 'invalid_category',
            'purpose' => 'Testing',
            'duration' => 'Session',
        ]);
    }

    public function test_addCookie_returns_id_on_success(): void
    {
        $this->markTestIncomplete('Requires integration test — Eloquent models cannot use shouldReceive()');
    }

    public function test_updateCookie_returns_false_when_not_found(): void
    {
        $this->markTestIncomplete('Requires integration test — Eloquent models cannot use shouldReceive()');
    }

    public function test_updateCookie_returns_false_when_no_allowed_fields(): void
    {
        $this->markTestIncomplete('Requires integration test — Eloquent models cannot use shouldReceive()');
    }

    public function test_deleteCookie_returns_false_when_not_found(): void
    {
        $this->markTestIncomplete('Requires integration test — Eloquent models cannot use shouldReceive()');
    }

    public function test_deleteCookie_returns_true_on_success(): void
    {
        $this->markTestIncomplete('Requires integration test — Eloquent models cannot use shouldReceive()');
    }

    public function test_getCookieCounts_returns_expected_keys(): void
    {
        $this->markTestIncomplete('Requires integration test — Eloquent models cannot use shouldReceive()');
    }
}
