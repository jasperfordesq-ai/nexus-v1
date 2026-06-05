<?php
// Copyright © 2024–2026 Jasper Ford
// SPDX-License-Identifier: AGPL-3.0-or-later
// Author: Jasper Ford
// See NOTICE file for attribution and acknowledgements.

namespace Tests\Laravel\Feature\Controllers;

use App\Core\TenantContext;
use App\Services\TenantFeatureConfig;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Illuminate\Support\Facades\DB;
use Tests\Laravel\TestCase;

class MapsConfigControllerTest extends TestCase
{
    use DatabaseTransactions;

    public function test_maps_kill_switch_keeps_google_places_autocomplete_available(): void
    {
        $apiKey = 'AIza' . str_repeat('A', 35);
        $previousKey = getenv('GOOGLE_MAPS_API_KEY');
        putenv('GOOGLE_MAPS_API_KEY=' . $apiKey);

        try {
            $features = TenantFeatureConfig::FEATURE_DEFAULTS;
            $features['maps'] = false;
            DB::table('tenants')
                ->where('id', $this->testTenantId)
                ->update(['features' => json_encode($features)]);

            DB::table('tenant_settings')->updateOrInsert(
                ['tenant_id' => $this->testTenantId, 'setting_key' => 'general.geocoding_provider'],
                [
                    'setting_value' => 'google',
                    'setting_type' => 'string',
                    'category' => 'general',
                    'updated_at' => now(),
                    'created_at' => now(),
                ]
            );

            TenantContext::setById($this->testTenantId);

            $response = $this->apiGet('/v2/config/google-maps');

            $response->assertStatus(200);
            $response->assertJsonPath('data.mapsEnabled', false);
            $response->assertJsonPath('data.mapProvider', 'google');
            $response->assertJsonPath('data.geocodingProvider', 'google');
            $response->assertJsonPath('data.googleMapsEnabled', false);
            $response->assertJsonPath('data.googlePlacesEnabled', true);
            $response->assertJsonPath('data.apiKey', $apiKey);
            $response->assertJsonPath('data.osmTileUrl', '');
        } finally {
            if ($previousKey === false) {
                putenv('GOOGLE_MAPS_API_KEY');
            } else {
                putenv('GOOGLE_MAPS_API_KEY=' . $previousKey);
            }
        }
    }
}
