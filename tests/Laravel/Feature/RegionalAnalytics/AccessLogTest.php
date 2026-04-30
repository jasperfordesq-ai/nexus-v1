<?php
// Copyright © 2024–2026 Jasper Ford
// SPDX-License-Identifier: AGPL-3.0-or-later
// Author: Jasper Ford
// See NOTICE file for attribution and acknowledgements.

declare(strict_types=1);

namespace Tests\Laravel\Feature\RegionalAnalytics;

use Illuminate\Foundation\Testing\DatabaseTransactions;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Str;
use Tests\Laravel\TestCase;

/**
 * AG59 — Hitting a partner endpoint with a valid subscription_token must
 * write a row into regional_analytics_access_log.
 */
class AccessLogTest extends TestCase
{
    use DatabaseTransactions;

    public function test_partner_dashboard_call_writes_access_log_row(): void
    {
        if (! Schema::hasTable('regional_analytics_subscriptions') || ! Schema::hasTable('regional_analytics_access_log')) {
            $this->markTestSkipped('Regional analytics tables not present.');
        }

        $token = Str::random(64);
        DB::table('regional_analytics_subscriptions')->insert([
            'tenant_id' => $this->testTenantId,
            'partner_name' => 'Test Municipality',
            'partner_type' => 'municipality',
            'contact_email' => 'test@example.com',
            'plan_tier' => 'basic',
            'status' => 'active',
            'subscription_token' => $token,
            'monthly_price_cents' => 29900,
            'currency' => 'CHF',
            'enabled_modules' => json_encode(['trends']),
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        $countBefore = (int) DB::table('regional_analytics_access_log')->count();

        $response = $this->getJson('/api/partner-analytics/me/dashboard?token=' . $token);
        $response->assertStatus(200);

        $countAfter = (int) DB::table('regional_analytics_access_log')->count();

        $this->assertGreaterThan($countBefore, $countAfter, 'Access log row should have been inserted.');

        $latest = DB::table('regional_analytics_access_log')
            ->orderBy('id', 'desc')
            ->first();
        $this->assertNotNull($latest);
        $this->assertSame($this->testTenantId, (int) $latest->tenant_id);
        $this->assertStringContainsString('/partner-analytics/me/dashboard', (string) $latest->accessed_endpoint);
    }

    public function test_invalid_token_returns_401_and_no_log_row(): void
    {
        if (! Schema::hasTable('regional_analytics_access_log')) {
            $this->markTestSkipped('Regional analytics tables not present.');
        }

        $countBefore = (int) DB::table('regional_analytics_access_log')->count();

        $response = $this->getJson('/api/partner-analytics/me/dashboard?token=not_a_real_token');
        $response->assertStatus(401);

        $this->assertSame($countBefore, (int) DB::table('regional_analytics_access_log')->count());
    }
}
