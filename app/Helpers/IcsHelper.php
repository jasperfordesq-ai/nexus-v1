<?php
// Copyright © 2024–2026 Jasper Ford
// SPDX-License-Identifier: AGPL-3.0-or-later
// Author: Jasper Ford
// See NOTICE file for attribution and acknowledgements.

namespace App\Helpers;

use Nexus\Helpers\IcsHelper as LegacyIcsHelper;

/**
 * App-namespace wrapper for Nexus\Helpers\IcsHelper.
 *
 * Delegates to the legacy implementation. Once the Laravel migration is
 * complete this can be replaced with a Laravel-native ICS generator.
 */
class IcsHelper
{
    public static function generate($summary, $description, $location, $start, $end)
    {
        if (!class_exists(LegacyIcsHelper::class)) { return ''; }
        return LegacyIcsHelper::generate($summary, $description, $location, $start, $end);
    }
}
