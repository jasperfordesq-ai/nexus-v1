<?php
// Copyright © 2024–2026 Jasper Ford
// SPDX-License-Identifier: AGPL-3.0-or-later
// Author: Jasper Ford
// See NOTICE file for attribution and acknowledgements.

namespace App\Http\Controllers\Api;

use App\Core\TenantContext;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Facades\DB;

/**
 * Public browser configuration for maps and location services.
 *
 * Per-tenant settings considered (in order of precedence over env defaults):
 *   - `maps` feature flag — kill switch for map *display*. When off, no
 *     Google API key is returned and OSM tiles are also withheld.
 *   - `map_provider` — `google` | `openstreetmap`. Default: `google`.
 *   - `geocoding_provider` — `google` | `nominatim`. Default: `google`.
 *   - `google_maps_api_key` — tenant override for Google billing. Falls
 *     back to env `GOOGLE_MAPS_API_KEY` when not set.
 *   - `google_maps_map_id` — tenant override for Map ID styling. Falls
 *     back to env `GOOGLE_MAPS_MAP_ID`.
 *   - `maptiler_api_key` — tenant override that switches OSM tiles from
 *     the free `tile.openstreetmap.org` service to MapTiler's paid host
 *     (proper dark mode, vector tiles, no OSMF policy concerns at scale).
 *
 * Browser API keys are intentionally public — they reach JS and network
 * requests. Protect them via Console-side restrictions (HTTP referrer,
 * IP, API). The kill switch + per-tenant key model means no Google
 * billing can occur for opted-out tenants regardless of frontend behavior.
 */
class MapsConfigController extends BaseApiController
{
    protected bool $isV2Api = true;

    private const ALLOWED_MAP_PROVIDERS = ['google', 'openstreetmap'];
    private const ALLOWED_GEOCODING_PROVIDERS = ['google', 'nominatim'];
    private const DEFAULT_MAP_PROVIDER = 'google';
    private const DEFAULT_GEOCODING_PROVIDER = 'google';
    private const NOMINATIM_BASE_URL = 'https://nominatim.openstreetmap.org';

    /** Free OSM tile service — fine at low/moderate scale, subject to OSMF policy. */
    private const OSM_FREE_TILE_URL = 'https://tile.openstreetmap.org/{z}/{x}/{y}.png';
    private const OSM_FREE_ATTRIBUTION = '&copy; <a href="https://www.openstreetmap.org/copyright" target="_blank" rel="noopener">OpenStreetMap</a> contributors';

    /** MapTiler raster tiles — paid, production-grade, includes dark style. */
    private const MAPTILER_TILE_URL = 'https://api.maptiler.com/maps/streets-v2/{z}/{x}/{y}@2x.png?key=';
    private const MAPTILER_ATTRIBUTION = '&copy; <a href="https://www.maptiler.com/copyright/" target="_blank" rel="noopener">MapTiler</a> &copy; <a href="https://www.openstreetmap.org/copyright" target="_blank" rel="noopener">OpenStreetMap</a> contributors';

    public function show(): JsonResponse
    {
        $tenantId = TenantContext::getId();
        $mapsEnabled = TenantContext::hasFeature('maps');
        $mapProvider = $this->resolveSetting($tenantId, 'map_provider', self::ALLOWED_MAP_PROVIDERS, self::DEFAULT_MAP_PROVIDER);
        $geocodingProvider = $this->resolveSetting($tenantId, 'geocoding_provider', self::ALLOWED_GEOCODING_PROVIDERS, self::DEFAULT_GEOCODING_PROVIDER);

        // Google API key — tenant-scoped override falling back to env.
        $tenantGoogleKey = $this->readSetting($tenantId, 'google_maps_api_key');
        $tenantMapId     = $this->readSetting($tenantId, 'google_maps_map_id');
        $tenantTilerKey  = $this->readSetting($tenantId, 'maptiler_api_key');

        $envGoogleKey = (string) (getenv('GOOGLE_MAPS_API_KEY') ?: '');
        $envMapId     = (string) (getenv('GOOGLE_MAPS_MAP_ID') ?: '');

        $resolvedGoogleKey = $tenantGoogleKey !== '' ? $tenantGoogleKey : $envGoogleKey;
        $resolvedMapId     = $tenantMapId !== '' ? $tenantMapId : $envMapId;

        // Google API key is only delivered when (a) the maps kill switch is
        // ON AND (b) the chosen map provider is Google. Either condition off
        // means no key reaches the browser.
        $googleEnabled = $mapsEnabled && $mapProvider === 'google';
        $googleApiKey  = $googleEnabled ? $resolvedGoogleKey : '';
        $googleMapId   = $googleEnabled ? $resolvedMapId : '';

        // OSM tile URL — MapTiler if a tenant key is set, otherwise the
        // free OSM service. Only delivered when maps are enabled AND the
        // OSM map branch is selected.
        $osmEnabled = $mapsEnabled && $mapProvider === 'openstreetmap';
        if ($osmEnabled && $tenantTilerKey !== '') {
            $osmTileUrl = self::MAPTILER_TILE_URL . rawurlencode($tenantTilerKey);
            $osmTileAttribution = self::MAPTILER_ATTRIBUTION;
        } elseif ($osmEnabled) {
            $osmTileUrl = self::OSM_FREE_TILE_URL;
            $osmTileAttribution = self::OSM_FREE_ATTRIBUTION;
        } else {
            $osmTileUrl = '';
            $osmTileAttribution = '';
        }

        return $this->respondWithData([
            // Legacy keys (kept for backward compatibility)
            'enabled' => $googleEnabled && $googleApiKey !== '',
            'apiKey'  => $googleApiKey,
            'mapId'   => $googleMapId !== '' ? $googleMapId : null,

            // Provider-aware fields
            'mapsEnabled'        => $mapsEnabled,
            'mapProvider'        => $mapProvider,
            'geocodingProvider'  => $geocodingProvider,
            'nominatimBaseUrl'   => self::NOMINATIM_BASE_URL,

            // OSM tile config (MapTiler if tenant key set, else free OSM)
            'osmTileUrl'         => $osmTileUrl,
            'osmTileAttribution' => $osmTileAttribution,
            'osmTileProvider'    => $osmEnabled
                ? ($tenantTilerKey !== '' ? 'maptiler' : 'osm')
                : null,

            // Telemetry for the admin UI / tests — flags whether the
            // tenant has overridden any platform default.
            'tenantOverrides' => [
                'google_maps_api_key' => $tenantGoogleKey !== '',
                'google_maps_map_id'  => $tenantMapId !== '',
                'maptiler_api_key'    => $tenantTilerKey !== '',
            ],
        ]);
    }

    private function resolveSetting(int $tenantId, string $key, array $allowed, string $default): string
    {
        $value = $this->readSetting($tenantId, $key);
        return ($value !== '' && in_array($value, $allowed, true)) ? $value : $default;
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
