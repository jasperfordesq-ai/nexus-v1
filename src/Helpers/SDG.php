<?php
// Copyright © 2024–2026 Jasper Ford
// SPDX-License-Identifier: AGPL-3.0-or-later
// Author: Jasper Ford
// See NOTICE file for attribution and acknowledgements.

namespace Nexus\Helpers;

use App\Helpers\SDG as AppSDG;

/**
 * Legacy delegate — real implementation is now in App\Helpers\SDG.
 *
 * @deprecated Use App\Helpers\SDG directly.
 */
class SDG
{
    public static function all()
    {
        return AppSDG::all();
    }

    public static function get($id)
    {
        return AppSDG::get($id);
    }

}
