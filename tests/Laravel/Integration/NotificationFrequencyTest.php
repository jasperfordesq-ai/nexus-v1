<?php
// Copyright © 2024–2026 Jasper Ford
// SPDX-License-Identifier: AGPL-3.0-or-later
// Author: Jasper Ford
// See NOTICE file for attribution and acknowledgements.

namespace Tests\Laravel\Integration;

use App\Core\TenantContext;
use App\Models\User;
use App\Services\NotificationDispatcher;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Illuminate\Support\Facades\DB;
use Tests\Laravel\TestCase;

/**
 * Integration test: verify notification frequency cascade logic.
 *
 * The frequency cascade is: thread → group → global → tenant config → 'off'.
 *
 * Design history:
 *  - A production incident once flipped the default to 'instant' (commit
 *    7f5f270f, reverted in 91eecf10). These tests still guard against 'instant'
 *    ever becoming the silent default.
 *  - 2026-05-17 (commit 464603657 "daily-digest opt-in"): the silent default
 *    changed from 'daily' to 'off'. Members now opt INTO the digest; we do not
 *    email until they say yes. Critical/social types are forced to 'instant'
 *    inside dispatch() so 'off' never silences direct social actions.
 *  - 2026-05-18 (commit 14c68496b "Replace weekly digests with monthly
 *    defaults"): any 'weekly' frequency (DB value or config default) is
 *    normalized to 'monthly'.
 */
class NotificationFrequencyTest extends TestCase
{
    use DatabaseTransactions;

    private int $userId;

    protected function setUp(): void
    {
        parent::setUp();
        $user = User::factory()->forTenant($this->testTenantId)->create(['status' => 'active']);
        $this->userId = $user->id;
    }

    /**
     * The silent fallback frequency must be the opt-in default ('off'), and must
     * NEVER be 'instant' (the 7f5f270f incident that caused 2000+ email spam).
     */
    public function test_default_frequency_fallback_is_optin_off_in_source_code(): void
    {
        $source = file_get_contents(app_path('Services/NotificationDispatcher.php'));

        // Members opt IN to digests — the un-configured global default is 'off'.
        $this->assertStringContainsString(
            "?? 'off'",
            $source,
            "NotificationDispatcher silent default frequency must be 'off' (opt-in digest)"
        );

        // The original regression guard still holds: never default to 'instant'.
        $this->assertStringNotContainsString(
            "?? 'instant'",
            $source,
            'NotificationDispatcher must NOT default to instant — caused 2000+ email spam'
        );
    }

    /**
     * Test that getFrequencySetting returns the opt-in default 'off' when no
     * preferences and no tenant config default_frequency exist.
     */
    public function test_global_default_returns_off_when_no_settings(): void
    {
        $tenant = TenantContext::get();
        $config = json_decode($tenant['configuration'] ?? '{}', true);
        unset($config['notifications']['default_frequency']);
        DB::table('tenants')
            ->where('id', TenantContext::getId())
            ->update(['configuration' => json_encode($config)]);

        TenantContext::setById(TenantContext::getId());

        $result = $this->callGetFrequencySetting(
            $this->userId,
            'global',
            0
        );

        $this->assertEquals('off', $result);
    }

    /**
     * Test that tenant config default_frequency overrides the 'off' fallback.
     * 'weekly' is normalized to 'monthly' (commit 14c68496b).
     */
    public function test_tenant_config_overrides_default_and_weekly_maps_to_monthly(): void
    {
        $tenant = TenantContext::get();
        $config = json_decode($tenant['configuration'] ?? '{}', true);
        $config['notifications']['default_frequency'] = 'weekly';
        DB::table('tenants')
            ->where('id', TenantContext::getId())
            ->update(['configuration' => json_encode($config)]);

        TenantContext::setById(TenantContext::getId());

        $result = $this->callGetFrequencySetting(
            $this->userId,
            'global',
            0
        );

        // weekly digests were replaced by monthly defaults.
        $this->assertEquals('monthly', $result);
    }

    /**
     * Test that a user's global notification_settings row overrides config default.
     */
    public function test_user_global_setting_overrides_tenant_config(): void
    {
        $userId = $this->userId;

        DB::table('notification_settings')->insert([
            'user_id' => $userId,
            'context_type' => 'global',
            'context_id' => 0,
            'frequency' => 'instant',
        ]);

        $result = $this->callGetFrequencySetting($userId, 'global', 0);

        $this->assertEquals('instant', $result);
    }

    /**
     * Test that group context falls back to global when no group setting exists.
     */
    public function test_group_falls_back_to_global(): void
    {
        $userId = $this->userId;

        // Use 'daily' (not 'weekly') so this asserts the group→global fallback
        // path specifically, without the weekly→monthly normalization muddying
        // the assertion.
        DB::table('notification_settings')->insert([
            'user_id' => $userId,
            'context_type' => 'global',
            'context_id' => 0,
            'frequency' => 'daily',
        ]);

        $result = $this->callGetFrequencySetting($userId, 'group', 999);

        $this->assertEquals('daily', $result);
    }

    /**
     * Test that 'off' frequency is respected (user opted out).
     */
    public function test_off_frequency_is_respected(): void
    {
        $userId = $this->userId;

        DB::table('notification_settings')->insert([
            'user_id' => $userId,
            'context_type' => 'global',
            'context_id' => 0,
            'frequency' => 'off',
        ]);

        $result = $this->callGetFrequencySetting($userId, 'global', 0);

        $this->assertEquals('off', $result);
    }

    // =========================================================================
    // Helper: call private getFrequencySetting via reflection
    // =========================================================================

    private function callGetFrequencySetting(int $userId, string $contextType, int $contextId): ?string
    {
        $reflection = new \ReflectionMethod(NotificationDispatcher::class, 'getFrequencySetting');
        $reflection->setAccessible(true);

        return $reflection->invoke(null, $userId, $contextType, $contextId);
    }
}
