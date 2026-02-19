<?php
// Copyright © 2024–2026 Jasper Ford
// SPDX-License-Identifier: AGPL-3.0-or-later
// Author: Jasper Ford
// See NOTICE file for attribution and acknowledgements.

namespace Nexus\Core;

class Validator
{
    public static function isIrishPhone($phone)
    {
        // Remove spaces, dashes, parens
        $clean = preg_replace('/[\s\-\(\)]/', '', $phone);

        // Regex for Irish Mobile:
        // Starts with +3538 or 003538 or 08
        // Followed by 3,5,6,7,9
        // Followed by 7 digits
        // e.g. 0871234567, +353871234567
        if (preg_match('/^(\+353|00353|0)8[3-9]\d{7}$/', $clean)) {
            return true;
        }

        // Landlines (simplified): 01, 021, etc.
        // Starts with +353 or 0, followed by 1-9, total length 9-10 digits (excl prefix)
        // Just generic check for Irish prefix + reasonable length
        if (preg_match('/^(\+353|00353|0)[1-9]\d{7,9}$/', $clean)) {
            return true;
        }

        return false;
    }

    /**
     * Validate a location string using GeocodingService (Google Maps).
     * Returns null if valid (or if geocoding is unavailable), error string if invalid.
     */
    public static function validateIrishLocation($location)
    {
        $apiKey = getenv('GOOGLE_MAPS_API_KEY');
        if (!$apiKey) return null; // Skip validation if no API key configured

        $result = \Nexus\Services\GeocodingService::geocode($location);
        if (!$result) {
            return "We could not verify that location. Please try simpler terms (e.g. 'Cork', 'Dublin 4').";
        }

        return null; // Valid
    }
}
