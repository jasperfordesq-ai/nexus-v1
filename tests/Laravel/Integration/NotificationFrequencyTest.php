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
 * The frequency cascade is: thread → group → global → tenant config → 'daily'.
 * After the production incident where the default was changed to 'instant'
 * (commit 7f5f270f, reverted in 91eecf10), these tests guard the default.
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
     * The final fallback frequency must be 'daily', never 'instant'.
     * This is the regression test for commit 7f5f270f.
     */
    public function test_default_frequency_fallback_is_daily_in_source_code(): void
    {
        $source = file_get_contents(app_path('Services/NotificationDispatcher.php'));

        $this->assertStringContainsString(
            "?? 'daily'",
            $source,
            'NotificationDispatcher default frequency must be daily, not instant'
        );

        $this->assertStringNotContainsString(
            "?? 'instant'",
            $source,
            'NotificationDispatcher must NOT default to instant — caused 2000+ email spam'
        );
    }

    /**
     * Test that getFrequencySetting returns 'daily' when no preferences exist.
     */
    public function test_global_default_returns_daily_when_no_settings(): void
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

        $this->assertEquals('daily', $result);
    }

    /**
     * Test that tenant config default_frequency overrides the 'daily' fallback.
     */
    public function test_tenant_config_overrides_daily_default(): void
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

        $this->assertEquals('weekly', $result);
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

        DB::table('notification_settings')->insert([
            'user_id' => $userId,
            'context_type' => 'global',
            'context_id' => 0,
            'frequency' => 'weekly',
        ]);

        $result = $this->callGetFrequencySetting($userId, 'group', 999);

        $this->assertEquals('weekly', $result);
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
