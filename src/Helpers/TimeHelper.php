<?php
// Copyright © 2024–2026 Jasper Ford
// SPDX-License-Identifier: AGPL-3.0-or-later
// Author: Jasper Ford
// See NOTICE file for attribution and acknowledgements.

namespace Nexus\Helpers;

use App\Helpers\TimeHelper as AppTimeHelper;

/**
 * Legacy delegate — real implementation is now in App\Helpers\TimeHelper.
 *
 * @deprecated Use App\Helpers\TimeHelper directly.
 */
class TimeHelper
{
    public static function timeAgo($datetime): string
    {
        return AppTimeHelper::timeAgo($datetime);
    }

    public static function format($datetime, string $format = 'M j, Y'): string
    {
        return AppTimeHelper::format($datetime, $format);
    }

    public static function formatWithTime($datetime): string
    {
        return AppTimeHelper::formatWithTime($datetime);
    }
}
