<?php
// Copyright © 2024–2026 Jasper Ford
// SPDX-License-Identifier: AGPL-3.0-or-later
// Author: Jasper Ford
// See NOTICE file for attribution and acknowledgements.

namespace App\Http\Controllers\Api;

use App\Core\TenantContext;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;

/**
 * OsPlacesController — server-side proxy for the Ordnance Survey Places API.
 *
 * Provides UPRN-backed UK address lookup/validation for the frontend
 * autocomplete when the tenant's `geocoding_provider` is `os_places`.
 * Proxying keeps the OS Data Hub key out of the browser and lets us
 * rate-limit per client. The key is shared with the OS Maps basemap
 * (`general.os_maps_api_key`, falling back to env `OS_MAPS_API_KEY`) —
 * one OS Data Hub project covers both products.
 *
 * Results are returned in WGS 84 (EPSG:4326) so they slot into the same
 * lat/lng pipeline as the Google Places and Nominatim providers, with the
 * UPRN included for address validation (AddressBase via PSGA).
 */
class OsPlacesController extends BaseApiController
{
    protected bool $isV2Api = true;

    private const FIND_URL = 'https://api.os.uk/search/places/v1/find';
    private const MAX_RESULTS = 7;
    private const MIN_QUERY_CHARS = 3;
    private const MAX_QUERY_CHARS = 200;

    /** GET /api/v2/geo/os-places/search?q=... */
    public function search(): JsonResponse
    {
        $this->rateLimit('os_places_search', 30, 60);

        $tenantId = TenantContext::getId();

        // Only serve tenants that have actually selected the OS Places
        // provider — this is not an open geocoding proxy.
        if ($this->readSetting($tenantId, 'geocoding_provider') !== 'os_places') {
            return $this->respondWithData(['enabled' => false, 'results' => []]);
        }

        $key = $this->readSetting($tenantId, 'os_maps_api_key');
        if ($key === '') {
            $key = (string) (getenv('OS_MAPS_API_KEY') ?: '');
        }
        if ($key === '') {
            return $this->respondWithData(['enabled' => false, 'results' => []]);
        }

        $query = trim((string) request()->query('q', ''));
        if (mb_strlen($query) < self::MIN_QUERY_CHARS) {
            return $this->respondWithData(['enabled' => true, 'results' => []]);
        }
        $query = mb_substr($query, 0, self::MAX_QUERY_CHARS);

        try {
            $response = Http::timeout(5)->get(self::FIND_URL, [
                'query' => $query,
                'key' => $key,
                'output_srs' => 'EPSG:4326',
                'maxresults' => self::MAX_RESULTS,
                'dataset' => 'DPA',
            ]);

            if (!$response->successful()) {
                Log::warning('[OsPlaces] upstream error', [
                    'status' => $response->status(),
                    'tenant_id' => $tenantId,
                ]);
                return $this->respondWithData(['enabled' => true, 'results' => []]);
            }

            $results = [];
            foreach ((array) $response->json('results', []) as $entry) {
                $record = $entry['DPA'] ?? $entry['LPI'] ?? null;
                if (!is_array($record)) {
                    continue;
                }

                $lat = $record['LAT'] ?? null;
                $lng = $record['LNG'] ?? null;
                $address = $record['ADDRESS'] ?? null;
                if (!is_numeric($lat) || !is_numeric($lng) || !is_string($address) || $address === '') {
                    continue;
                }

                $results[] = [
                    'uprn' => isset($record['UPRN']) ? (string) $record['UPRN'] : null,
                    'address' => $address,
                    'postcode' => isset($record['POSTCODE']) ? (string) $record['POSTCODE'] : null,
                    'post_town' => isset($record['POST_TOWN']) ? (string) $record['POST_TOWN'] : null,
                    'lat' => (float) $lat,
                    'lng' => (float) $lng,
                ];
            }

            return $this->respondWithData(['enabled' => true, 'results' => $results]);
        } catch (\Throwable $e) {
            Log::warning('[OsPlaces] lookup failed: ' . $e->getMessage(), ['tenant_id' => $tenantId]);
            return $this->respondWithData(['enabled' => true, 'results' => []]);
        }
    }

    private function readSetting(int $tenantId, string $key): string
    {
        if ($tenantId <= 0) {
            return '';
        }

        try {
            $value = DB::table('tenant_settings')
                ->where('tenant_id', $tenantId)
                ->where('setting_key', 'general.' . $key)
                ->value('setting_value');
            return is_string($value) ? $value : '';
        } catch (\Throwable) {
            return '';
        }
    }
}
