<?php
// Copyright © 2024–2026 Jasper Ford
// SPDX-License-Identifier: AGPL-3.0-or-later
// Author: Jasper Ford
// See NOTICE file for attribution and acknowledgements.

namespace Tests\Laravel\Unit\Services;

use App\Core\TenantContext;
use App\Services\MerchantOnboardingService;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Illuminate\Support\Facades\DB;
use Tests\Laravel\TestCase;

/**
 * MerchantOnboardingServiceTest
 *
 * Tests the 4-step self-serve onboarding wizard:
 *   - isAvailable() guard
 *   - getOrCreateProfile() idempotency
 *   - saveStep1 (business identity), saveStep2 (address/hours), saveStep3 (images)
 *   - completeOnboarding() sets onboarding_completed_at and grants the badge
 *   - getOnboardingStatus() reflects correct state before/after completion
 *
 * No HTTP mocked because MerchantOnboardingService performs no external calls.
 * Badge is granted to user_badges when the table exists (it does in nexus_test).
 */
class MerchantOnboardingServiceTest extends TestCase
{
    use DatabaseTransactions;

    private const TENANT_ID = 2;

    protected function setUp(): void
    {
        parent::setUp();
        TenantContext::setById(self::TENANT_ID);
    }

    // ─────────────────────────────────────────────────────────────────────────
    //  Helpers
    // ─────────────────────────────────────────────────────────────────────────

    private function insertUser(): int
    {
        $uid = uniqid('mob', true);
        return DB::table('users')->insertGetId([
            'tenant_id'  => self::TENANT_ID,
            'name'       => 'OnboardUser ' . $uid,
            'first_name' => 'Onboard',
            'last_name'  => 'User',
            'email'      => 'onboard.' . $uid . '@example.test',
            'status'     => 'active',
            'balance'    => 0,
            'role'       => 'member',
            'is_approved' => 1,
            'created_at' => now(),
            'updated_at' => now(),
        ]);
    }

    // ─────────────────────────────────────────────────────────────────────────
    //  isAvailable
    // ─────────────────────────────────────────────────────────────────────────

    public function test_is_available_returns_true_when_table_exists(): void
    {
        $this->assertTrue(MerchantOnboardingService::isAvailable());
    }

    // ─────────────────────────────────────────────────────────────────────────
    //  getOrCreateProfile
    // ─────────────────────────────────────────────────────────────────────────

    public function test_get_or_create_profile_inserts_blank_row(): void
    {
        $userId  = $this->insertUser();
        $profile = MerchantOnboardingService::getOrCreateProfile(self::TENANT_ID, $userId);

        $this->assertNotEmpty($profile);
        $this->assertSame(self::TENANT_ID, (int) $profile['tenant_id']);
        $this->assertSame($userId, (int) $profile['user_id']);
        $this->assertSame('business', $profile['seller_type']);

        $row = DB::table('marketplace_seller_profiles')
            ->where('tenant_id', self::TENANT_ID)
            ->where('user_id', $userId)
            ->first();
        $this->assertNotNull($row);
    }

    public function test_get_or_create_profile_is_idempotent(): void
    {
        $userId = $this->insertUser();

        $first  = MerchantOnboardingService::getOrCreateProfile(self::TENANT_ID, $userId);
        $second = MerchantOnboardingService::getOrCreateProfile(self::TENANT_ID, $userId);

        $this->assertSame((int) $first['id'], (int) $second['id']);

        $count = DB::table('marketplace_seller_profiles')
            ->where('tenant_id', self::TENANT_ID)
            ->where('user_id', $userId)
            ->count();
        $this->assertSame(1, $count);
    }

    // ─────────────────────────────────────────────────────────────────────────
    //  Step 1 — Business identity
    // ─────────────────────────────────────────────────────────────────────────

    public function test_save_step1_persists_business_identity_fields(): void
    {
        $userId = $this->insertUser();

        $profile = MerchantOnboardingService::saveStep1(self::TENANT_ID, $userId, [
            'business_name'          => 'Acme Ltd',
            'display_name'           => 'Acme',
            'bio'                    => 'We make everything.',
            'seller_type'            => 'business',
            'business_registration'  => 'IE123456',
        ]);

        $this->assertSame('Acme Ltd', $profile['business_name']);
        $this->assertSame('Acme', $profile['display_name']);
        $this->assertSame('business', $profile['seller_type']);
        $this->assertSame('IE123456', $profile['business_registration']);

        $row = DB::table('marketplace_seller_profiles')
            ->where('tenant_id', self::TENANT_ID)
            ->where('user_id', $userId)
            ->first();
        $this->assertSame('Acme Ltd', $row->business_name);
    }

    public function test_save_step1_ignores_non_allowed_fields(): void
    {
        $userId = $this->insertUser();

        // Passing a field not in the allowed list should not raise an error,
        // but the field should not be persisted.
        MerchantOnboardingService::saveStep1(self::TENANT_ID, $userId, [
            'business_name' => 'Safe Co',
            'stripe_account_id' => 'acct_should_not_be_set',
        ]);

        $row = DB::table('marketplace_seller_profiles')
            ->where('tenant_id', self::TENANT_ID)
            ->where('user_id', $userId)
            ->first();

        $this->assertNull($row->stripe_account_id);
        $this->assertSame('Safe Co', $row->business_name);
    }

    // ─────────────────────────────────────────────────────────────────────────
    //  Step 2 — Address and hours
    // ─────────────────────────────────────────────────────────────────────────

    public function test_save_step2_persists_address_as_json(): void
    {
        $userId  = $this->insertUser();
        $address = ['street' => '1 Main St', 'city' => 'Dublin', 'country' => 'IE'];

        $profile = MerchantOnboardingService::saveStep2(self::TENANT_ID, $userId, [
            'business_address' => $address,
        ]);

        $this->assertNotNull($profile['business_address']);
        $decoded = json_decode($profile['business_address'], true);
        $this->assertSame('Dublin', $decoded['city']);
    }

    public function test_save_step2_persists_opening_hours(): void
    {
        $userId = $this->insertUser();
        $hours  = ['mon' => '09:00-17:00', 'tue' => '09:00-17:00'];

        $profile = MerchantOnboardingService::saveStep2(self::TENANT_ID, $userId, [
            'business_address' => '{"line1":"x"}',
            'opening_hours'    => $hours,
        ]);

        $decoded = json_decode($profile['opening_hours'], true);
        $this->assertSame('09:00-17:00', $decoded['mon']);
    }

    // ─────────────────────────────────────────────────────────────────────────
    //  Step 3 — Images
    // ─────────────────────────────────────────────────────────────────────────

    public function test_save_step3_persists_avatar_and_cover_urls(): void
    {
        $userId = $this->insertUser();

        $profile = MerchantOnboardingService::saveStep3(
            self::TENANT_ID,
            $userId,
            'https://cdn.example.com/avatar.png',
            'https://cdn.example.com/cover.png'
        );

        $this->assertSame('https://cdn.example.com/avatar.png', $profile['avatar_url']);
        $this->assertSame('https://cdn.example.com/cover.png', $profile['cover_image_url']);

        $row = DB::table('marketplace_seller_profiles')
            ->where('tenant_id', self::TENANT_ID)
            ->where('user_id', $userId)
            ->first();
        $this->assertSame('https://cdn.example.com/avatar.png', $row->avatar_url);
    }

    public function test_save_step3_omits_cover_when_null(): void
    {
        $userId = $this->insertUser();

        // Pre-set a cover to confirm null argument does NOT overwrite it.
        DB::table('marketplace_seller_profiles')->where('tenant_id', self::TENANT_ID)
            ->where('user_id', $userId)
            ->updateOrInsert(
                ['tenant_id' => self::TENANT_ID, 'user_id' => $userId],
                ['cover_image_url' => 'https://cdn.example.com/old_cover.png',
                 'seller_type'     => 'business',
                 'created_at'      => now(), 'updated_at' => now()]
            );

        MerchantOnboardingService::saveStep3(
            self::TENANT_ID,
            $userId,
            'https://cdn.example.com/new_avatar.png',
            null // should NOT overwrite cover
        );

        $row = DB::table('marketplace_seller_profiles')
            ->where('tenant_id', self::TENANT_ID)
            ->where('user_id', $userId)
            ->first();
        $this->assertSame('https://cdn.example.com/old_cover.png', $row->cover_image_url);
    }

    // ─────────────────────────────────────────────────────────────────────────
    //  completeOnboarding
    // ─────────────────────────────────────────────────────────────────────────

    public function test_complete_onboarding_sets_completed_at_and_returns_badge_flag(): void
    {
        $userId = $this->insertUser();

        $result = MerchantOnboardingService::completeOnboarding(self::TENANT_ID, $userId);

        $this->assertArrayHasKey('badge_granted', $result);
        $this->assertNotNull($result['onboarding_completed_at']);

        $row = DB::table('marketplace_seller_profiles')
            ->where('tenant_id', self::TENANT_ID)
            ->where('user_id', $userId)
            ->first();
        $this->assertNotNull($row->onboarding_completed_at);
    }

    public function test_complete_onboarding_grants_marktplatz_partner_badge(): void
    {
        $userId = $this->insertUser();

        $result = MerchantOnboardingService::completeOnboarding(self::TENANT_ID, $userId);

        $this->assertTrue($result['badge_granted']);

        $badge = DB::table('user_badges')
            ->where('tenant_id', self::TENANT_ID)
            ->where('user_id', $userId)
            ->where('badge_key', 'marktplatz_partner')
            ->first();
        $this->assertNotNull($badge, 'marktplatz_partner badge should be in user_badges');
    }

    public function test_complete_onboarding_is_idempotent_for_badge(): void
    {
        $userId = $this->insertUser();

        // First completion — badge inserted.
        MerchantOnboardingService::completeOnboarding(self::TENANT_ID, $userId);

        // Second completion — insertOrIgnore should not duplicate or throw.
        $result = MerchantOnboardingService::completeOnboarding(self::TENANT_ID, $userId);

        $this->assertArrayHasKey('badge_granted', $result);

        $badgeCount = DB::table('user_badges')
            ->where('tenant_id', self::TENANT_ID)
            ->where('user_id', $userId)
            ->where('badge_key', 'marktplatz_partner')
            ->count();
        $this->assertSame(1, $badgeCount, 'Badge should not be duplicated on second completion');
    }

    // ─────────────────────────────────────────────────────────────────────────
    //  getOnboardingStatus
    // ─────────────────────────────────────────────────────────────────────────

    public function test_get_onboarding_status_returns_no_profile_when_none_exists(): void
    {
        $userId = $this->insertUser();

        $status = MerchantOnboardingService::getOnboardingStatus(self::TENANT_ID, $userId);

        $this->assertFalse($status['has_profile']);
        $this->assertFalse($status['onboarding_completed']);
        $this->assertNull($status['profile']);
    }

    public function test_get_onboarding_status_returns_incomplete_when_profile_exists_but_not_done(): void
    {
        $userId = $this->insertUser();
        MerchantOnboardingService::getOrCreateProfile(self::TENANT_ID, $userId);

        $status = MerchantOnboardingService::getOnboardingStatus(self::TENANT_ID, $userId);

        $this->assertTrue($status['has_profile']);
        $this->assertFalse($status['onboarding_completed']);
        $this->assertNotNull($status['profile']);
    }

    public function test_get_onboarding_status_returns_completed_after_complete_onboarding(): void
    {
        $userId = $this->insertUser();
        MerchantOnboardingService::completeOnboarding(self::TENANT_ID, $userId);

        $status = MerchantOnboardingService::getOnboardingStatus(self::TENANT_ID, $userId);

        $this->assertTrue($status['has_profile']);
        $this->assertTrue($status['onboarding_completed']);
    }
}
