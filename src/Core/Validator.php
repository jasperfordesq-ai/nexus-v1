<?php

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

    public static function validateIrishLocation($location)
    {
        $token = getenv('MAPBOX_ACCESS_TOKEN');
        if (!$token) return null; // Skip if no token configured

        // Security: Sanitize location input to prevent SSRF
        $location = preg_replace('/[\x00-\x1F\x7F]/', '', $location); // Remove control chars
        $location = trim($location);

        // Block URL-like inputs and IP addresses
        if (strlen($location) > 500 ||
            preg_match('/^(https?|ftp|file|data|javascript|vbscript):/i', $location) ||
            preg_match('/^\d{1,3}\.\d{1,3}\.\d{1,3}\.\d{1,3}/', $location)) {
            return "Invalid location format.";
        }

        $url = "https://api.mapbox.com/geocoding/v5/mapbox.places/" . urlencode($location) . ".json?access_token=$token&country=ie&limit=1";

        $ch = curl_init();
        curl_setopt($ch, CURLOPT_URL, $url);
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
        $resp = curl_exec($ch);

        if (curl_errno($ch)) {
            curl_close($ch);
            return null; // Fail safe if API is down
        }
        curl_close($ch);

        $json = json_decode($resp, true);

        if (empty($json['features'])) {
            return "We could not verify that location as being in Ireland. Please try simpler terms (e.g. 'Cork', 'Dublin 4').";
        }

        return null; // Valid
    }
}
