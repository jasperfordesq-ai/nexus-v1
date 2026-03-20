<?php
// Copyright © 2024–2026 Jasper Ford
// SPDX-License-Identifier: AGPL-3.0-or-later
// Author: Jasper Ford
// See NOTICE file for attribution and acknowledgements.

namespace Nexus\Helpers;

use App\Helpers\IcsHelper as AppIcsHelper;

/**
 * Legacy delegate — real implementation is now in App\Helpers\IcsHelper.
 *
 * @deprecated Use App\Helpers\IcsHelper directly.
 */
class IcsHelper
{
    public static function generate($summary, $description, $location, $start, $end)
    {
        return AppIcsHelper::generate($summary, $description, $location, $start, $end);
    }
}
