<?php
// Copyright © 2024–2026 Jasper Ford
// SPDX-License-Identifier: AGPL-3.0-or-later
// Author: Jasper Ford
// See NOTICE file for attribution and acknowledgements.

namespace Tests\Laravel\Feature\Controllers;

use App\Core\TenantContext;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Http;
use Tests\Laravel\TestCase;

class OsPlacesControllerTest extends TestCase
{
    use DatabaseTransactions;

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

    public function test_returns_disabled_when_provider_is_not_os_places(): void
    {
        $this->setGeneralSetting('geocoding_provider', 'nominatim');
        TenantContext::setById($this->testTenantId);

        Http::fake();

        $response = $this->apiGet('/v2/geo/os-places/search?q=coventry');

        $response->assertStatus(200);
        $response->assertJsonPath('data.enabled', false);
        $response->assertJsonPath('data.results', []);
        Http::assertNothingSent();
    }

    public function test_returns_disabled_when_no_key_configured(): void
    {
        $previousKey = getenv('OS_MAPS_API_KEY');
        putenv('OS_MAPS_API_KEY');

        try {
            $this->setGeneralSetting('geocoding_provider', 'os_places');
            DB::table('tenant_settings')
                ->where('tenant_id', $this->testTenantId)
                ->where('setting_key', 'general.os_maps_api_key')
                ->delete();
            TenantContext::setById($this->testTenantId);

            Http::fake();

            $response = $this->apiGet('/v2/geo/os-places/search?q=coventry');

            $response->assertStatus(200);
            $response->assertJsonPath('data.enabled', false);
            Http::assertNothingSent();
        } finally {
            if ($previousKey !== false) {
                putenv('OS_MAPS_API_KEY=' . $previousKey);
            }
        }
    }

    public function test_proxies_query_and_maps_dpa_results_with_uprn(): void
    {
        $this->setGeneralSetting('geocoding_provider', 'os_places');
        $this->setGeneralSetting('os_maps_api_key', str_repeat('k', 32));
        TenantContext::setById($this->testTenantId);

        Http::fake([
            'api.os.uk/*' => Http::response([
                'header' => ['totalresults' => 1],
                'results' => [
                    [
                        'DPA' => [
                            'UPRN' => '100070123456',
                            'ADDRESS' => '1, MUCH PARK STREET, COVENTRY, CV1 2LT',
                            'POST_TOWN' => 'COVENTRY',
                            'POSTCODE' => 'CV1 2LT',
                            'LAT' => 52.405,
                            'LNG' => -1.507,
                        ],
                    ],
                    // Malformed entry must be skipped, not crash the mapper
                    ['DPA' => ['ADDRESS' => 'NO COORDS HOUSE']],
                ],
            ], 200),
        ]);

        $response = $this->apiGet('/v2/geo/os-places/search?q=much+park+street');

        $response->assertStatus(200);
        $response->assertJsonPath('data.enabled', true);
        $response->assertJsonCount(1, 'data.results');
        $response->assertJsonPath('data.results.0.uprn', '100070123456');
        $response->assertJsonPath('data.results.0.address', '1, MUCH PARK STREET, COVENTRY, CV1 2LT');
        $response->assertJsonPath('data.results.0.postcode', 'CV1 2LT');
        $response->assertJsonPath('data.results.0.lat', 52.405);
        $response->assertJsonPath('data.results.0.lng', -1.507);

        // The OS Data Hub key must never appear in our API response
        $this->assertStringNotContainsString(str_repeat('k', 32), $response->getContent());

        Http::assertSent(function ($request) {
            return str_contains($request->url(), 'api.os.uk/search/places/v1/find')
                && $request['output_srs'] === 'EPSG:4326'
                && $request['query'] === 'much park street';
        });
    }

    public function test_short_queries_return_empty_without_calling_upstream(): void
    {
        $this->setGeneralSetting('geocoding_provider', 'os_places');
        $this->setGeneralSetting('os_maps_api_key', str_repeat('k', 32));
        TenantContext::setById($this->testTenantId);

        Http::fake();

        $response = $this->apiGet('/v2/geo/os-places/search?q=ab');

        $response->assertStatus(200);
        $response->assertJsonPath('data.enabled', true);
        $response->assertJsonPath('data.results', []);
        Http::assertNothingSent();
    }

    public function test_upstream_failure_degrades_to_empty_results(): void
    {
        $this->setGeneralSetting('geocoding_provider', 'os_places');
        $this->setGeneralSetting('os_maps_api_key', str_repeat('k', 32));
        TenantContext::setById($this->testTenantId);

        Http::fake(['api.os.uk/*' => Http::response('upstream error', 500)]);

        $response = $this->apiGet('/v2/geo/os-places/search?q=coventry');

        $response->assertStatus(200);
        $response->assertJsonPath('data.enabled', true);
        $response->assertJsonPath('data.results', []);
    }
}
