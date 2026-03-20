<?php
// Copyright © 2024–2026 Jasper Ford
// SPDX-License-Identifier: AGPL-3.0-or-later
// Author: Jasper Ford
// See NOTICE file for attribution and acknowledgements.

namespace Nexus\Core;

/**
 * Thin delegate — forwards all calls to \App\Core\Validator which
 * holds the real implementation.
 *
 * This class is kept for backward compatibility: legacy Nexus\ namespace
 * code references it. The public API is identical.
 *
 * @see \App\Core\Validator  The authoritative implementation.
 * @deprecated Use \App\Core\Validator instead.
 */
class Validator
{
    public static function isPhone(string $phone): bool
    {
        return \App\Core\Validator::isPhone($phone);
    }

    public static function isEmail(string $email): bool
    {
        return \App\Core\Validator::isEmail($email);
    }

    public static function isUrl(string $url): bool
    {
        return \App\Core\Validator::isUrl($url);
    }
}
