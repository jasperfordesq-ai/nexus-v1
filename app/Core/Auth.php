<?php
// Copyright © 2024–2026 Jasper Ford
// SPDX-License-Identifier: AGPL-3.0-or-later
// Author: Jasper Ford
// See NOTICE file for attribution and acknowledgements.

namespace App\Core;

/**
 * App-namespace wrapper for Nexus\Core\Auth.
 * Delegates all calls to the legacy implementation.
 */
class Auth
{
    public static function user(): ?object
    {
        return \Nexus\Core\Auth::user();
    }

    public static function check(): bool
    {
        return \Nexus\Core\Auth::check();
    }

    public static function id(): ?int
    {
        return \Nexus\Core\Auth::id();
    }

    public static function isAdmin(): bool
    {
        return \Nexus\Core\Auth::isAdmin();
    }

    public static function isSuperAdmin(): bool
    {
        return \Nexus\Core\Auth::isSuperAdmin();
    }

    public static function authenticate(): void
    {
        \Nexus\Core\Auth::authenticate();
    }
}
