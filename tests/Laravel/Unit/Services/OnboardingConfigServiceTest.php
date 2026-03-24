<?php
// Copyright © 2024–2026 Jasper Ford
// SPDX-License-Identifier: AGPL-3.0-or-later
// Author: Jasper Ford
// See NOTICE file for attribution and acknowledgements.

namespace Tests\Laravel\Unit\Services;

use App\Models\User;
use App\Services\OnboardingConfigService;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Illuminate\Support\Facades\DB;
use Tests\Laravel\TestCase;

class OnboardingConfigServiceTest extends TestCase
{
    use DatabaseTransactions;

    public function test_getConfig_returns_defaults_when_no_settings_exist(): void
    {
        // Clear any seeded settings
        DB::table('tenant_settings')
            ->where('tenant_id', $this->testTenantId)
            ->where('setting_key', 'LIKE', 'onboarding.%')
            ->delete();

        $config = OnboardingConfigService::getConfig($this->testTenantId);

        $this->assertTrue($config['enabled']);
        $this->assertTrue($config['mandatory']);
        $this->assertTrue($config['avatar_required']);
        $this->assertTrue($config['bio_required']);
        $this->assertEquals(10, $config['bio_min_length']);
        $this->assertEquals('disabled', $config['listing_creation_mode']);
        $this->assertFalse($config['step_safeguarding_enabled']);
        $this->assertFalse($config['require_completion_for_visibility']);
    }

    public function test_getConfig_reads_stored_values_over_defaults(): void
    {
        DB::table('tenant_settings')->updateOrInsert(
            ['tenant_id' => $this->testTenantId, 'setting_key' => 'onboarding.enabled'],
            ['setting_value' => '0', 'setting_type' => 'boolean']
        );
        DB::table('tenant_settings')->updateOrInsert(
            ['tenant_id' => $this->testTenantId, 'setting_key' => 'onboarding.bio_min_length'],
            ['setting_value' => '50', 'setting_type' => 'integer']
        );

        $config = OnboardingConfigService::getConfig($this->testTenantId);

        $this->assertFalse($config['enabled']);
        $this->assertEquals(50, $config['bio_min_length']);
    }

    public function test_getActiveSteps_returns_default_five_steps(): void
    {
        $steps = OnboardingConfigService::getActiveSteps($this->testTenantId);

        $slugs = array_column($steps, 'slug');
        $this->assertContains('welcome', $slugs);
        $this->assertContains('profile', $slugs);
        $this->assertContains('interests', $slugs);
        $this->assertContains('skills', $slugs);
        $this->assertContains('confirm', $slugs);
        $this->assertNotContains('safeguarding', $slugs); // Off by default
    }

    public function test_getActiveSteps_includes_safeguarding_when_enabled(): void
    {
        DB::table('tenant_settings')->updateOrInsert(
            ['tenant_id' => $this->testTenantId, 'setting_key' => 'onboarding.step_safeguarding_enabled'],
            ['setting_value' => '1', 'setting_type' => 'boolean']
        );

        $steps = OnboardingConfigService::getActiveSteps($this->testTenantId);

        $slugs = array_column($steps, 'slug');
        $this->assertContains('safeguarding', $slugs);
    }

    public function test_getActiveSteps_excludes_disabled_steps(): void
    {
        DB::table('tenant_settings')->updateOrInsert(
            ['tenant_id' => $this->testTenantId, 'setting_key' => 'onboarding.step_interests_enabled'],
            ['setting_value' => '0', 'setting_type' => 'boolean']
        );

        $steps = OnboardingConfigService::getActiveSteps($this->testTenantId);

        $slugs = array_column($steps, 'slug');
        $this->assertNotContains('interests', $slugs);
    }

    public function test_validateCompletion_passes_with_avatar_and_bio(): void
    {
        $user = User::factory()->forTenant($this->testTenantId)->create([
            'avatar_url' => 'https://example.com/photo.jpg',
            'bio' => 'A bio that is long enough to pass validation easily.',
        ]);

        $unmet = OnboardingConfigService::validateCompletion($this->testTenantId, $user->id);

        $this->assertEmpty($unmet);
    }

    public function test_validateCompletion_fails_without_avatar(): void
    {
        $user = User::factory()->forTenant($this->testTenantId)->create([
            'avatar_url' => null,
            'bio' => 'A bio that is long enough.',
        ]);

        $unmet = OnboardingConfigService::validateCompletion($this->testTenantId, $user->id);

        $this->assertContains('avatar_required', $unmet);
    }

    public function test_validateCompletion_fails_without_bio(): void
    {
        $user = User::factory()->forTenant($this->testTenantId)->create([
            'avatar_url' => 'https://example.com/photo.jpg',
            'bio' => null,
        ]);

        $unmet = OnboardingConfigService::validateCompletion($this->testTenantId, $user->id);

        $this->assertContains('bio_required', $unmet);
    }

    public function test_isProfileVisible_returns_true_by_default(): void
    {
        $user = User::factory()->forTenant($this->testTenantId)->create([
            'onboarding_completed' => false,
            'avatar_url' => null,
            'bio' => null,
        ]);

        // Default gating settings are all false
        $this->assertTrue(OnboardingConfigService::isProfileVisible($this->testTenantId, $user->id));
    }

    public function test_isProfileVisible_respects_completion_gating(): void
    {
        DB::table('tenant_settings')->updateOrInsert(
            ['tenant_id' => $this->testTenantId, 'setting_key' => 'onboarding.require_completion_for_visibility'],
            ['setting_value' => '1', 'setting_type' => 'boolean']
        );

        $user = User::factory()->forTenant($this->testTenantId)->create([
            'onboarding_completed' => false,
        ]);

        $this->assertFalse(OnboardingConfigService::isProfileVisible($this->testTenantId, $user->id));

        $user->update(['onboarding_completed' => true]);
        $this->assertTrue(OnboardingConfigService::isProfileVisible($this->testTenantId, $user->id));
    }

    public function test_getListingCreationMode_returns_disabled_by_default(): void
    {
        $this->assertEquals('disabled', OnboardingConfigService::getListingCreationMode($this->testTenantId));
    }

    public function test_getListingCreationMode_returns_configured_mode(): void
    {
        DB::table('tenant_settings')->updateOrInsert(
            ['tenant_id' => $this->testTenantId, 'setting_key' => 'onboarding.listing_creation_mode'],
            ['setting_value' => 'draft', 'setting_type' => 'string']
        );

        $this->assertEquals('draft', OnboardingConfigService::getListingCreationMode($this->testTenantId));
    }

    public function test_getListingCreationMode_rejects_invalid_mode(): void
    {
        DB::table('tenant_settings')->updateOrInsert(
            ['tenant_id' => $this->testTenantId, 'setting_key' => 'onboarding.listing_creation_mode'],
            ['setting_value' => 'INVALID', 'setting_type' => 'string']
        );

        $this->assertEquals('disabled', OnboardingConfigService::getListingCreationMode($this->testTenantId));
    }
}
