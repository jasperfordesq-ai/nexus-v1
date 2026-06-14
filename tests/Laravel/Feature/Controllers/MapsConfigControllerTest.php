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

    /**
     * Explicitly enable the maps feature for the test tenant. Maps default OFF
     * platform-wide, so tile-selection tests must opt in rather than rely on the
     * global default.
     */
    private function enableMapsFeature(): void
    {
        $features = TenantFeatureConfig::FEATURE_DEFAULTS;
        $features['maps'] = true;
        DB::table('tenants')
            ->where('id', $this->testTenantId)
            ->update(['features' => json_encode($features)]);
    }

    private function setGeneralSetting(string $key, string $value): void
    {
        DB::table('tenant_settings')->updateOrInsert(
            ['tenant_id' => $this->testTenantId, 'setting_key' => 'general.' . $key],
            [
                'setting_value' => $value,
                'setting_type' => 'string',
                'category' => 'general',
                'updated_at' => now(),
                'created_at' => now(),
            ]
        );
    }

    public function test_ordnance_survey_provider_serves_os_maps_tiles_when_key_set(): void
    {
        $osKey = str_repeat('k', 32);
        $this->enableMapsFeature();
        $this->setGeneralSetting('map_provider', 'ordnance_survey');
        $this->setGeneralSetting('os_maps_api_key', $osKey);

        TenantContext::setById($this->testTenantId);

        $response = $this->apiGet('/v2/config/google-maps');

        $response->assertStatus(200);
        $response->assertJsonPath('data.mapProvider', 'ordnance_survey');
        $response->assertJsonPath('data.osmTileProvider', 'ordnance_survey');
        $response->assertJsonPath('data.tenantOverrides.os_maps_api_key', true);

        $tileUrl = $response->json('data.osmTileUrl');
        $this->assertStringContainsString('api.os.uk/maps/raster/v1/zxy/', $tileUrl);
        $this->assertStringContainsString($osKey, $tileUrl);

        $this->assertStringContainsString('Crown copyright', $response->json('data.osmTileAttribution'));
    }

    public function test_ordnance_survey_provider_falls_back_to_free_osm_without_key(): void
    {
        $previousKey = getenv('OS_MAPS_API_KEY');
        putenv('OS_MAPS_API_KEY');

        try {
            $this->enableMapsFeature();
            $this->setGeneralSetting('map_provider', 'ordnance_survey');
            DB::table('tenant_settings')
                ->where('tenant_id', $this->testTenantId)
                ->where('setting_key', 'general.os_maps_api_key')
                ->delete();

            TenantContext::setById($this->testTenantId);

            $response = $this->apiGet('/v2/config/google-maps');

            $response->assertStatus(200);
            $response->assertJsonPath('data.mapProvider', 'ordnance_survey');
            // Maps never go blank on a missing key — degrade to free OSM tiles
            $response->assertJsonPath('data.osmTileProvider', 'osm');
            $response->assertJsonPath('data.osmTileUrl', 'https://tile.openstreetmap.org/{z}/{x}/{y}.png');
            $response->assertJsonPath('data.tenantOverrides.os_maps_api_key', false);
        } finally {
            if ($previousKey !== false) {
                putenv('OS_MAPS_API_KEY=' . $previousKey);
            }
        }
    }
}
