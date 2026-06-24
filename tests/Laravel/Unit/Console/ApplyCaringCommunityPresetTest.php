<?php
// Copyright © 2024–2026 Jasper Ford
// SPDX-License-Identifier: AGPL-3.0-or-later
// Author: Jasper Ford
// See NOTICE file for attribution and acknowledgements.

declare(strict_types=1);

namespace Tests\Laravel\Unit\Console;

use App\Core\TenantContext;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Queue;
use Tests\Laravel\TestCase;

/**
 * Tests for tenant:apply-caring-community-preset console command.
 *
 * Uses unique tenant id 99737 for isolation.
 */
class ApplyCaringCommunityPresetTest extends TestCase
{
    use DatabaseTransactions;

    private const TENANT_ID   = 99737;
    private const TENANT_SLUG = 'test-preset-99737';

    /**
     * The canonical preset keys that must end up true/false after application.
     * Source of truth: App\Console\Commands\ApplyCaringCommunityPreset::PRESET
     */
    private const PRESET_ON  = [
        'caring_community', 'volunteering', 'exchange_workflow', 'organisations',
        'federation', 'events', 'groups', 'group_exchanges', 'connections',
        'direct_messaging', 'resources', 'reviews', 'polls', 'gamification',
        'goals', 'search', 'ai_chat', 'message_translation',
    ];
    private const PRESET_OFF = ['job_vacancies', 'ideation_challenges', 'marketplace', 'blog'];

    protected function setUp(): void
    {
        parent::setUp();

        Queue::fake();

        DB::table('tenants')->insertOrIgnore([
            'id'         => self::TENANT_ID,
            'name'       => 'Test Preset Tenant',
            'slug'       => self::TENANT_SLUG,
            'is_active'  => 1,
            'features'   => json_encode([]),
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        TenantContext::setById(self::TENANT_ID);
    }

    // ------------------------------------------------------------------ //
    // Helper                                                               //
    // ------------------------------------------------------------------ //

    /** Return the current features JSON decoded for our test tenant. */
    private function currentFeatures(): array
    {
        $raw = DB::table('tenants')->where('id', self::TENANT_ID)->value('features');
        return is_string($raw) ? (json_decode($raw, true) ?: []) : [];
    }

    // ------------------------------------------------------------------ //
    // Tests                                                                //
    // ------------------------------------------------------------------ //

    public function test_exits_failure_when_slug_not_found(): void
    {
        $this->artisan('tenant:apply-caring-community-preset', ['slug' => 'no-such-slug-99737'])
            ->assertExitCode(1);
    }

    public function test_exits_success_with_valid_slug(): void
    {
        $this->artisan('tenant:apply-caring-community-preset', ['slug' => self::TENANT_SLUG])
            ->assertExitCode(0);
    }

    public function test_preset_on_features_are_enabled_after_apply(): void
    {
        $this->artisan('tenant:apply-caring-community-preset', ['slug' => self::TENANT_SLUG])
            ->assertExitCode(0);

        $features = $this->currentFeatures();

        foreach (self::PRESET_ON as $feature) {
            $this->assertTrue(
                (bool) ($features[$feature] ?? false),
                "Expected feature '{$feature}' to be TRUE after preset apply"
            );
        }
    }

    public function test_preset_off_features_are_disabled_after_apply(): void
    {
        $this->artisan('tenant:apply-caring-community-preset', ['slug' => self::TENANT_SLUG])
            ->assertExitCode(0);

        $features = $this->currentFeatures();

        foreach (self::PRESET_OFF as $feature) {
            $this->assertFalse(
                (bool) ($features[$feature] ?? true),
                "Expected feature '{$feature}' to be FALSE after preset apply"
            );
        }
    }

    public function test_dry_run_does_not_write_to_database(): void
    {
        // Set a known starting state that differs from the preset.
        DB::table('tenants')
            ->where('id', self::TENANT_ID)
            ->update(['features' => json_encode(['caring_community' => false])]);

        $this->artisan('tenant:apply-caring-community-preset', [
            'slug'      => self::TENANT_SLUG,
            '--dry-run' => true,
        ])->assertExitCode(0);

        // The DB must be unchanged — caring_community must still be false.
        $features = $this->currentFeatures();
        $this->assertFalse(
            (bool) ($features['caring_community'] ?? true),
            'Dry-run must not persist any changes to the database'
        );
    }

    public function test_dry_run_output_contains_dry_run_notice(): void
    {
        $this->artisan('tenant:apply-caring-community-preset', [
            'slug'      => self::TENANT_SLUG,
            '--dry-run' => true,
        ])
            ->expectsOutputToContain('DRY RUN')
            ->assertExitCode(0);
    }

    public function test_idempotent_apply_exits_success_with_no_changes_message(): void
    {
        // Apply once so preset is already in place.
        $this->artisan('tenant:apply-caring-community-preset', ['slug' => self::TENANT_SLUG])
            ->assertExitCode(0);

        // Apply again — should report no changes.
        $this->artisan('tenant:apply-caring-community-preset', ['slug' => self::TENANT_SLUG])
            ->expectsOutputToContain('no changes needed')
            ->assertExitCode(0);
    }

    public function test_idempotent_apply_does_not_alter_features(): void
    {
        // Apply twice; the features JSON must be identical both times.
        $this->artisan('tenant:apply-caring-community-preset', ['slug' => self::TENANT_SLUG])->assertExitCode(0);
        $after1 = $this->currentFeatures();

        $this->artisan('tenant:apply-caring-community-preset', ['slug' => self::TENANT_SLUG])->assertExitCode(0);
        $after2 = $this->currentFeatures();

        foreach (self::PRESET_ON as $f) {
            $this->assertSame($after1[$f] ?? null, $after2[$f] ?? null, "Feature '{$f}' changed between runs");
        }
        foreach (self::PRESET_OFF as $f) {
            $this->assertSame($after1[$f] ?? null, $after2[$f] ?? null, "Feature '{$f}' changed between runs");
        }
    }

    public function test_apply_overwrites_existing_conflicting_features(): void
    {
        // Start with marketplace=true (preset wants it false) and caring_community=false.
        DB::table('tenants')
            ->where('id', self::TENANT_ID)
            ->update(['features' => json_encode(['marketplace' => true, 'caring_community' => false])]);

        $this->artisan('tenant:apply-caring-community-preset', ['slug' => self::TENANT_SLUG])
            ->assertExitCode(0);

        $features = $this->currentFeatures();
        $this->assertFalse((bool) ($features['marketplace'] ?? true), 'marketplace must be flipped to false');
        $this->assertTrue((bool) ($features['caring_community'] ?? false), 'caring_community must be flipped to true');
    }

    public function test_output_contains_tenant_info(): void
    {
        $this->artisan('tenant:apply-caring-community-preset', ['slug' => self::TENANT_SLUG])
            ->expectsOutputToContain(self::TENANT_SLUG)
            ->assertExitCode(0);
    }
}
