<?php
// Copyright © 2024–2026 Jasper Ford
// SPDX-License-Identifier: AGPL-3.0-or-later
// Author: Jasper Ford
// See NOTICE file for attribution and acknowledgements.

declare(strict_types=1);

namespace Tests\Laravel\Feature\Marketplace;

use App\Models\User;
use App\Services\MerchantOnboardingService;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Tests\Laravel\TestCase;

/**
 * AG48 — Merchant onboarding wizard step progression tests.
 */
class MerchantOnboardingWizardTest extends TestCase
{
    use DatabaseTransactions;

    private function ensureSchema(): bool
    {
        return Schema::hasTable('marketplace_seller_profiles');
    }

    public function test_status_for_new_user_returns_incomplete(): void
    {
        if (!$this->ensureSchema()) {
            $this->markTestSkipped('Onboarding tables not present.');
        }

        $user = User::factory()->forTenant($this->testTenantId)->create();
        $status = MerchantOnboardingService::getOnboardingStatus($this->testTenantId, $user->id);

        $this->assertFalse($status['onboarding_completed']);
    }

    public function test_step1_creates_profile_with_business_data(): void
    {
        if (!$this->ensureSchema()) {
            $this->markTestSkipped('Onboarding tables not present.');
        }

        $user = User::factory()->forTenant($this->testTenantId)->create();

        MerchantOnboardingService::saveStep1($this->testTenantId, $user->id, [
            'business_name' => 'Acme Wholesale',
            'display_name' => 'Acme',
            'bio' => 'Quality goods.',
            'seller_type' => 'business',
        ]);

        $row = DB::table('marketplace_seller_profiles')
            ->where('tenant_id', $this->testTenantId)
            ->where('user_id', $user->id)
            ->first();

        $this->assertNotNull($row);
        $this->assertSame('Acme Wholesale', $row->business_name);
        $this->assertSame('business', $row->seller_type);
    }

    public function test_complete_onboarding_sets_completion_timestamp(): void
    {
        if (!$this->ensureSchema()) {
            $this->markTestSkipped('Onboarding tables not present.');
        }

        $user = User::factory()->forTenant($this->testTenantId)->create();
        MerchantOnboardingService::saveStep1($this->testTenantId, $user->id, [
            'business_name' => 'Globex',
            'display_name' => 'Globex',
            'seller_type' => 'business',
        ]);

        $result = MerchantOnboardingService::completeOnboarding($this->testTenantId, $user->id);
        $this->assertArrayHasKey('badge_granted', $result);

        $row = DB::table('marketplace_seller_profiles')
            ->where('tenant_id', $this->testTenantId)
            ->where('user_id', $user->id)
            ->first();

        $this->assertNotNull($row->onboarding_completed_at);

        $status = MerchantOnboardingService::getOnboardingStatus($this->testTenantId, $user->id);
        $this->assertTrue($status['onboarding_completed']);
    }
}
