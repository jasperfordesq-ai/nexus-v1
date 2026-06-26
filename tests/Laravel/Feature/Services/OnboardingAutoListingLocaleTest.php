<?php
// Copyright © 2024–2026 Jasper Ford
// SPDX-License-Identifier: AGPL-3.0-or-later
// Author: Jasper Ford
// See NOTICE file for attribution and acknowledgements.

namespace Tests\Laravel\Feature\Services;

use App\Core\TenantContext;
use App\I18n\LocaleContext;
use App\Models\Category;
use App\Models\User;
use App\Services\OnboardingConfigService;
use App\Services\OnboardingService;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Illuminate\Support\Facades\DB;
use Tests\Laravel\TestCase;

/**
 * Regression: onboarding auto-created listings must render their title and
 * description in the CREATING member's preferred language — not hardcoded
 * English. Without the LocaleContext wrap in OnboardingService::autoCreateListings,
 * a non-English member onboarding on a tenant with auto-listing enabled got
 * English listing copy persisted as their own listings (i18n recipient-locale rule).
 */
class OnboardingAutoListingLocaleTest extends TestCase
{
    use DatabaseTransactions;

    protected function setUp(): void
    {
        parent::setUp();

        // Force 'draft' mode so autoCreateListings actually creates rows
        // (default is 'disabled', which creates nothing).
        DB::table('tenant_settings')->updateOrInsert(
            ['tenant_id' => $this->testTenantId, 'setting_key' => 'onboarding.listing_creation_mode'],
            ['setting_value' => 'draft', 'setting_type' => 'string', 'updated_at' => now()]
        );
        OnboardingConfigService::clearConfigCache($this->testTenantId);
    }

    /**
     * Run a callback with the test tenant explicitly active. Tests run in the
     * console, where model observers can reset TenantContext to null (then it
     * re-resolves to the default tenant 1). runForTenant pins the scope for the
     * service call + the scoped listing read, then restores.
     */
    private function asTestTenant(callable $fn): mixed
    {
        return TenantContext::runForTenant($this->testTenantId, $fn);
    }

    public function test_auto_listing_title_renders_in_member_preferred_language(): void
    {
        $category = Category::factory()->forTenant($this->testTenantId)->create(['name' => 'Gardening']);
        $user = User::factory()->forTenant($this->testTenantId)->create(['preferred_language' => 'ga']);

        $ids = $this->asTestTenant(
            fn () => OnboardingService::autoCreateListings($user->id, [$category->id], [])
        );

        $this->assertNotEmpty($ids, 'draft mode should auto-create a listing');

        // Read the row directly — the auto-created listing is status='draft',
        // which the Listing model's default scope hides.
        $title = DB::table('listings')
            ->where('id', $ids[0])
            ->where('tenant_id', $this->testTenantId)
            ->value('title');
        $this->assertNotNull($title);

        $expectedGa = LocaleContext::withLocale('ga', fn () => __('api.onboarding.auto_listing.offer_title', ['category' => 'Gardening']));
        $expectedEn = LocaleContext::withLocale('en', fn () => __('api.onboarding.auto_listing.offer_title', ['category' => 'Gardening']));

        $this->assertSame($expectedGa, $title, 'auto-listing title must render in the member preferred language (ga)');
        $this->assertNotSame($expectedEn, $title, 'auto-listing title must NOT be hardcoded English for a non-English member');
        // Guard against a regression back to the original hardcoded template.
        $this->assertStringNotContainsString('I can help with', (string) $title);
    }

    public function test_auto_listing_request_copy_is_localised(): void
    {
        $category = Category::factory()->forTenant($this->testTenantId)->create(['name' => 'Plumbing']);
        $user = User::factory()->forTenant($this->testTenantId)->create(['preferred_language' => 'ga']);

        $ids = $this->asTestTenant(
            fn () => OnboardingService::autoCreateListings($user->id, [], [$category->id])
        );

        $this->assertNotEmpty($ids, 'draft mode should auto-create a request listing');

        $title = DB::table('listings')
            ->where('id', $ids[0])
            ->where('tenant_id', $this->testTenantId)
            ->value('title');
        $this->assertNotNull($title);

        $expectedGa = LocaleContext::withLocale('ga', fn () => __('api.onboarding.auto_listing.request_title', ['category' => 'Plumbing']));
        $this->assertSame($expectedGa, $title);
        $this->assertStringNotContainsString('Looking for help with', (string) $title);
    }
}
