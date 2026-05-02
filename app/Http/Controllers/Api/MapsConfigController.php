<?php
// Copyright © 2024–2026 Jasper Ford
// SPDX-License-Identifier: AGPL-3.0-or-later
// Author: Jasper Ford
// See NOTICE file for attribution and acknowledgements.

namespace App\Http\Controllers\Api;

use Illuminate\Http\JsonResponse;

/**
 * Public browser configuration for Google Maps.
 *
 * The browser API key is intentionally public but should be fetched only by
 * Maps surfaces, not baked into every frontend asset at build time.
 */
class MapsConfigController extends BaseApiController
{
    protected bool $isV2Api = true;

    public function show(): JsonResponse
    {
        $apiKey = (string) (getenv('GOOGLE_MAPS_API_KEY') ?: '');
        $mapId = (string) (getenv('GOOGLE_MAPS_MAP_ID') ?: '');

        return $this->respondWithData([
            'enabled' => $apiKey !== '',
            'apiKey' => $apiKey,
            'mapId' => $mapId !== '' ? $mapId : null,
        ]);
    }
}
