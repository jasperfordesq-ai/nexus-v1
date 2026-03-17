<?php
// Copyright © 2024–2026 Jasper Ford
// SPDX-License-Identifier: AGPL-3.0-or-later
// Author: Jasper Ford
// See NOTICE file for attribution and acknowledgements.

namespace App\Core;

use Nexus\Core\Validator as LegacyValidator;

/**
 * App-namespace wrapper for Nexus\Core\Validator.
 *
 * Delegates to the legacy implementation. Once the Laravel migration is
 * complete this can be replaced with Laravel's Validator facade / custom rules.
 *
 * NOTE: isIrishPhone() is intentionally NOT exposed here — it is legacy only.
 * Project NEXUS is a global platform; use isPhone() for international E.164 format.
 */
class Validator
{
    /**
     * Validate a phone number in any international format.
     * Accepts E.164 and common local formats (7-15 digits after stripping formatting).
     */
    public static function isPhone(string $phone): bool
    {
        return LegacyValidator::isPhone($phone);
    }

    /**
     * Validate an email address.
     */
    public static function isEmail(string $email): bool
    {
        return (bool) filter_var($email, FILTER_VALIDATE_EMAIL);
    }

    /**
     * Validate a URL.
     */
    public static function isUrl(string $url): bool
    {
        return (bool) filter_var($url, FILTER_VALIDATE_URL);
    }
}
