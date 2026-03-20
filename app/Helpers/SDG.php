<?php
// Copyright © 2024–2026 Jasper Ford
// SPDX-License-Identifier: AGPL-3.0-or-later
// Author: Jasper Ford
// See NOTICE file for attribution and acknowledgements.

namespace App\Helpers;

use Nexus\Helpers\SDG as LegacySDG;

/**
 * App-namespace wrapper for Nexus\Helpers\SDG.
 *
 * Delegates to the legacy implementation.
 */
class SDG
{
    public static function all()
    {
        if (!class_exists(LegacySDG::class)) { return []; }
        return LegacySDG::all();
    }

    public static function get($id)
    {
        if (!class_exists(LegacySDG::class)) { return null; }
        return LegacySDG::get($id);
    }
}
