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
 * Two independent toggles per tenant:
 *   - `maps` feature flag — kill switch for map *display*. When off, no API
 *     key is returned and map components render their fallback.
 *   - `map_provider` setting — which provider the map components should use
 *     (`google` | `openstreetmap`). Default: `google`.
 *   - `geocoding_provider` setting — which provider drives address autocomplete
 *     (`google` | `nominatim`). Default: `google`. Autocomplete is always on
 *     regardless of the `maps` kill switch — communities still need to enter
 *     addresses; only the cost-bearing display is gated.
 *
 * The Google API key is only returned when both the `maps` feature is enabled
 * AND `map_provider === 'google'`. This guarantees no Google Maps Platform
 * billing for tenants on OpenStreetMap or with the kill switch engaged.
 */
class MapsConfigController extends BaseApiController
{
    protected bool $isV2Api = true;

    private const ALLOWED_MAP_PROVIDERS = ['google', 'openstreetmap'];
    private const ALLOWED_GEOCODING_PROVIDERS = ['google', 'nominatim'];
    private const DEFAULT_MAP_PROVIDER = 'google';
    private const DEFAULT_GEOCODING_PROVIDER = 'google';
    private const NOMINATIM_BASE_URL = 'https://nominatim.openstreetmap.org';

    public function show(): JsonResponse
    {
        $mapsEnabled = TenantContext::hasFeature('maps');
        $mapProvider = $this->resolveProvider('map_provider', self::ALLOWED_MAP_PROVIDERS, self::DEFAULT_MAP_PROVIDER);
        $geocodingProvider = $this->resolveProvider('geocoding_provider', self::ALLOWED_GEOCODING_PROVIDERS, self::DEFAULT_GEOCODING_PROVIDER);

        // Google API key is only delivered when (a) the maps kill switch is
        // ON for display, AND (b) the chosen map provider is google. Either
        // condition off means no key reaches the browser.
        $googleEnabled = $mapsEnabled && $mapProvider === 'google';
        $apiKey = $googleEnabled ? (string) (getenv('GOOGLE_MAPS_API_KEY') ?: '') : '';
        $mapId = $googleEnabled ? (string) (getenv('GOOGLE_MAPS_MAP_ID') ?: '') : '';

        return $this->respondWithData([
            // Legacy keys (kept for backward compatibility with existing GoogleMapsProvider)
            'enabled' => $googleEnabled && $apiKey !== '',
            'apiKey' => $apiKey,
            'mapId' => $mapId !== '' ? $mapId : null,

            // New provider-aware keys
            'mapsEnabled' => $mapsEnabled,
            'mapProvider' => $mapProvider,
            'geocodingProvider' => $geocodingProvider,
            'nominatimBaseUrl' => self::NOMINATIM_BASE_URL,
        ]);
    }

    private function resolveProvider(string $key, array $allowed, string $default): string
    {
        $tenantId = TenantContext::getId();
        if ($tenantId <= 0) {
            return $default;
        }

        try {
            $value = DB::table('tenant_settings')
                ->where('tenant_id', $tenantId)
                ->where('setting_key', 'general.' . $key)
                ->value('setting_value');

            if (is_string($value) && in_array($value, $allowed, true)) {
                return $value;
            }
        } catch (\Throwable $e) {
            // tenant_settings table may not exist yet
        }

        return $default;
    }
}
