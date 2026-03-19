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
        if (!class_exists('\Nexus\Core\Auth')) { return null; }
        return \Nexus\Core\Auth::user();
    }

    public static function check(): bool
    {
        if (!class_exists('\Nexus\Core\Auth')) { return false; }
        return \Nexus\Core\Auth::check();
    }

    public static function id(): ?int
    {
        if (!class_exists('\Nexus\Core\Auth')) { return null; }
        return \Nexus\Core\Auth::id();
    }

    public static function isAdmin(): bool
    {
        if (!class_exists('\Nexus\Core\Auth')) { return false; }
        return \Nexus\Core\Auth::isAdmin();
    }

    public static function isSuperAdmin(): bool
    {
        if (!class_exists('\Nexus\Core\Auth')) { return false; }
        return \Nexus\Core\Auth::isSuperAdmin();
    }

    public static function authenticate(): void
    {
        if (!class_exists('\Nexus\Core\Auth')) { return; }
        \Nexus\Core\Auth::authenticate();
    }
}
