<?php
// Copyright © 2024–2026 Jasper Ford
// SPDX-License-Identifier: AGPL-3.0-or-later
// Author: Jasper Ford
// See NOTICE file for attribution and acknowledgements.

namespace App\Core;

/**
 * App-namespace wrapper for Nexus\Core\TotpEncryption.
 * Delegates all calls to the legacy implementation.
 */
class TotpEncryption
{
    public static function encrypt(string $data): string
    {
        return \Nexus\Core\TotpEncryption::encrypt($data);
    }

    public static function decrypt(string $data): string
    {
        return \Nexus\Core\TotpEncryption::decrypt($data);
    }
}
