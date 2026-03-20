<?php
// Copyright © 2024–2026 Jasper Ford
// SPDX-License-Identifier: AGPL-3.0-or-later
// Author: Jasper Ford
// See NOTICE file for attribution and acknowledgements.

namespace App\Helpers;

use Nexus\Helpers\TimeHelper as LegacyTimeHelper;

/**
 * App-namespace wrapper for Nexus\Helpers\TimeHelper.
 *
 * Delegates to the legacy implementation. Once the Laravel migration is
 * complete this can be replaced with Carbon helpers.
 */
class TimeHelper
{
    public static function timeAgo($datetime): string
    {
        if (!class_exists(LegacyTimeHelper::class)) { return 'Unknown'; }
        return LegacyTimeHelper::timeAgo($datetime);
    }

    public static function format($datetime, string $format = 'M j, Y'): string
    {
        if (!class_exists(LegacyTimeHelper::class)) { return 'Unknown'; }
        return LegacyTimeHelper::format($datetime, $format);
    }

    public static function formatWithTime($datetime): string
    {
        if (!class_exists(LegacyTimeHelper::class)) { return 'Unknown'; }
        return LegacyTimeHelper::formatWithTime($datetime);
    }
}
