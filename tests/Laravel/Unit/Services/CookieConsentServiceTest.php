<?php
// Copyright © 2024–2026 Jasper Ford
// SPDX-License-Identifier: AGPL-3.0-or-later
// Author: Jasper Ford
// See NOTICE file for attribution and acknowledgements.

namespace Tests\Laravel\Unit\Services;

use Tests\Laravel\TestCase;
use App\Services\CookieConsentService;
use Illuminate\Support\Facades\DB;

class CookieConsentServiceTest extends TestCase
{
    public function test_getConsent_returns_null_without_user_or_ip(): void
    {
        $service = new CookieConsentService();
        // No userId and no IP → returns null
        $result = $service->getConsent(null, 1, null);
        $this->assertNull($result);
    }

    public function test_getConsent_returns_null_when_no_record(): void
    {
        DB::shouldReceive('table')->with('cookie_consents')->andReturnSelf();
        DB::shouldReceive('where')->andReturnSelf();
        DB::shouldReceive('whereNull')->andReturnSelf();
        DB::shouldReceive('orWhere')->andReturnSelf();
        DB::shouldReceive('orderByDesc')->andReturnSelf();
        DB::shouldReceive('first')->andReturnNull();

        $service = new CookieConsentService();
        $result = $service->getConsent(1, 1);
        $this->assertNull($result);
    }

    public function test_checkCategory_returns_true_for_essential_without_tenant(): void
    {
        $service = new CookieConsentService();
        // No tenantId → always returns true for essential
        $this->assertTrue($service->checkCategory('essential', 1, null));
    }

    public function test_checkCategory_returns_false_for_analytics_without_consent(): void
    {
        DB::shouldReceive('table')->with('cookie_consents')->andReturnSelf();
        DB::shouldReceive('where')->andReturnSelf();
        DB::shouldReceive('whereNull')->andReturnSelf();
        DB::shouldReceive('orWhere')->andReturnSelf();
        DB::shouldReceive('orderByDesc')->andReturnSelf();
        DB::shouldReceive('first')->andReturnNull();

        $service = new CookieConsentService();
        $this->assertFalse($service->checkCategory('analytics', 1, 1));
    }

    public function test_isConsentValid_returns_false_when_withdrawn(): void
    {
        $this->assertFalse(CookieConsentService::isConsentValid([
            'withdrawal_date' => '2026-01-01',
        ]));
    }

    public function test_isConsentValid_returns_false_when_expired(): void
    {
        $this->assertFalse(CookieConsentService::isConsentValid([
            'expires_at' => '2020-01-01',
        ]));
    }

    public function test_isConsentValid_returns_false_for_wrong_version(): void
    {
        $this->assertFalse(CookieConsentService::isConsentValid([
            'consent_version' => '0.1',
        ]));
    }

    public function test_isConsentValid_returns_true_for_valid_consent(): void
    {
        $this->assertTrue(CookieConsentService::isConsentValid([
            'consent_version' => '1.0',
            'expires_at' => date('Y-m-d', strtotime('+1 year')),
        ]));
    }

    public function test_hasConsent_returns_false_when_no_consent(): void
    {
        DB::shouldReceive('table')->with('cookie_consents')->andReturnSelf();
        DB::shouldReceive('where')->andReturnSelf();
        DB::shouldReceive('whereNull')->andReturnSelf();
        DB::shouldReceive('orWhere')->andReturnSelf();
        DB::shouldReceive('orderByDesc')->andReturnSelf();
        DB::shouldReceive('first')->andReturnNull();

        // hasConsent requires tenantId as 2nd arg, called via instance
        $service = new CookieConsentService();
        $this->assertFalse($service->hasConsent(1, 1));
    }

    public function test_getConsentSummary_returns_no_consent_without_tenant(): void
    {
        // Without tenantId, returns no consent
        $result = CookieConsentService::getConsentSummary(1, null);
        $this->assertFalse($result['has_consent']);
    }

    public function test_withdrawConsent_returns_false_without_identifiers(): void
    {
        $this->assertFalse(CookieConsentService::withdrawConsent(null, null));
    }

    public function test_cleanExpiredConsents_returns_integer(): void
    {
        DB::shouldReceive('table')->with('cookie_consents')->andReturnSelf();
        DB::shouldReceive('where')->andReturnSelf();
        DB::shouldReceive('delete')->andReturn(5);

        $this->assertSame(5, CookieConsentService::cleanExpiredConsents());
    }

    public function test_getStatistics_returns_expected_keys(): void
    {
        DB::shouldReceive('table')->with('cookie_consents')->andReturnSelf();
        DB::shouldReceive('count')->andReturn(10);
        DB::shouldReceive('where')->andReturnSelf();

        $result = CookieConsentService::getStatistics();
        $this->assertArrayHasKey('total', $result);
        $this->assertArrayHasKey('functional', $result);
        $this->assertArrayHasKey('analytics', $result);
        $this->assertArrayHasKey('marketing', $result);
    }
}
