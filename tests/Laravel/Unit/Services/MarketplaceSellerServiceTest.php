<?php
// Copyright © 2024–2026 Jasper Ford
// SPDX-License-Identifier: AGPL-3.0-or-later
// Author: Jasper Ford
// See NOTICE file for attribution and acknowledgements.

namespace Tests\Laravel\Unit\Services;

use App\Models\MarketplaceSellerProfile;
use App\Services\MarketplaceSellerService;
use Mockery;
use Tests\Laravel\TestCase;

class MarketplaceSellerServiceTest extends TestCase
{
    // ── update: copies only whitelisted fields ───────────────────────

    public function test_update_writes_only_whitelisted_fields_to_profile(): void
    {
        $profile = Mockery::mock(MarketplaceSellerProfile::class)->makePartial();
        $profile->shouldReceive('save')->once()->andReturn(true);

        $data = [
            'display_name' => 'Alice Seller',
            'bio' => 'Friendly seller',
            'business_name' => 'Acme Ltd',
            'random_field' => 'should not be copied',
            'id' => 9999, // sensitive — must not be copied
        ];

        $result = MarketplaceSellerService::update($profile, $data);

        $this->assertSame('Alice Seller', $result->display_name);
        $this->assertSame('Friendly seller', $result->bio);
        $this->assertSame('Acme Ltd', $result->business_name);
        // Unwhitelisted keys must NOT be assigned
        $this->assertFalse(isset($profile->random_field));
    }

    public function test_update_allows_nullifying_a_field_that_is_explicitly_set_to_null(): void
    {
        $profile = Mockery::mock(MarketplaceSellerProfile::class)->makePartial();
        $profile->display_name = 'Old Name';
        $profile->shouldReceive('save')->once()->andReturn(true);

        MarketplaceSellerService::update($profile, ['display_name' => null]);

        $this->assertNull($profile->display_name);
    }

    public function test_update_ignores_fields_that_are_not_present_in_input(): void
    {
        $profile = Mockery::mock(MarketplaceSellerProfile::class)->makePartial();
        $profile->display_name = 'Unchanged';
        $profile->shouldReceive('save')->once()->andReturn(true);

        // Only updating bio — display_name must stay untouched
        MarketplaceSellerService::update($profile, ['bio' => 'New bio']);

        $this->assertSame('Unchanged', $profile->display_name);
        $this->assertSame('New bio', $profile->bio);
    }

    public function test_update_covers_all_documented_fillable_keys(): void
    {
        $profile = Mockery::mock(MarketplaceSellerProfile::class)->makePartial();
        $profile->shouldReceive('save')->once()->andReturn(true);

        $data = [
            'display_name' => 'A',
            'bio' => 'B',
            'cover_image_url' => 'https://example.test/cover.jpg',
            'avatar_url' => 'https://example.test/av.jpg',
            'seller_type' => 'business',
            'business_name' => 'Acme',
            'business_registration' => 'REG-1',
            'vat_number' => 'VAT-1',
            'business_address' => '1 Test St',
        ];

        MarketplaceSellerService::update($profile, $data);

        foreach ($data as $k => $v) {
            $this->assertSame($v, $profile->{$k}, "Field {$k} was not copied");
        }
    }
}
