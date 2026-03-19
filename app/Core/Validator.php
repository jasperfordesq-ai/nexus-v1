<?php
// Copyright © 2024–2026 Jasper Ford
// SPDX-License-Identifier: AGPL-3.0-or-later
// Author: Jasper Ford
// See NOTICE file for attribution and acknowledgements.

namespace App\Core;

/**
 * Input validation helpers.
 * Direct implementation replacing Nexus\Core\Validator delegation.
 *
 * NOTE: isIrishPhone() is intentionally NOT exposed here -- it is legacy only.
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
        $clean = preg_replace('/[\s\-\(\)\.]/', '', $phone);
        return (bool) preg_match('/^\+?\d{7,15}$/', $clean);
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
