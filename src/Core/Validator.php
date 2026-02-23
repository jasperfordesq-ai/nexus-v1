<?php
// Copyright © 2024–2026 Jasper Ford
// SPDX-License-Identifier: AGPL-3.0-or-later
// Author: Jasper Ford
// See NOTICE file for attribution and acknowledgements.

namespace Nexus\Core;

class Validator
{
    /**
     * Validate a phone number in any international format.
     * Accepts E.164 and common local formats (7–15 digits after stripping formatting).
     */
    public static function isPhone(string $phone): bool
    {
        $clean = preg_replace('/[\s\-\(\)\.]/', '', $phone);
        return (bool) preg_match('/^\+?\d{7,15}$/', $clean);
    }

}
