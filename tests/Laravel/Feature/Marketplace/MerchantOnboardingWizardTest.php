<?php
// Copyright © 2024–2026 Jasper Ford
// SPDX-License-Identifier: AGPL-3.0-or-later
// Author: Jasper Ford
// See NOTICE file for attribution and acknowledgements.

declare(strict_types=1);

namespace Tests\Laravel\Feature\Marketplace;

use App\Models\User;
use App\Core\TenantContext;
use App\Services\MerchantOnboardingService;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Facades\Storage;
use Illuminate\Http\UploadedFile;
use Laravel\Sanctum\Sanctum;
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

    private function enableMarketplaceFeature(): void
    {
        $tenant = DB::table('tenants')->where('id', $this->testTenantId)->first(['features']);
        $features = json_decode((string) ($tenant->features ?? '{}'), true) ?: [];
        $features['marketplace'] = true;

        DB::table('tenants')
            ->where('id', $this->testTenantId)
            ->update(['features' => json_encode($features)]);

        TenantContext::setById($this->testTenantId);
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

    public function test_image_upload_returns_url_for_onboarding_step3(): void
    {
        if (!$this->ensureSchema()) {
            $this->markTestSkipped('Onboarding tables not present.');
        }

        Storage::fake('public');
        $this->enableMarketplaceFeature();
        $user = User::factory()->forTenant($this->testTenantId)->create([
            'status' => 'active',
            'is_approved' => true,
        ]);
        Sanctum::actingAs($user, ['*']);

        $response = $this->post('/api/v2/merchant-onboarding/image', [
            'avatar' => UploadedFile::fake()->image('avatar.jpg', 200, 200),
        ], $this->withTenantHeader());

        $response->assertSuccessful();
        $response->assertJsonPath('data.filename', fn (string $filename): bool => str_ends_with($filename, '.jpg'));
        $this->assertStringContainsString('/storage/tenant_' . $this->testTenantId . '/marketplace/sellers/', $response->json('data.url'));
        Storage::disk('public')->assertExists($response->json('data.path'));
    }
}
