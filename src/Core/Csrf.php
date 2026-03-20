<?php
// Copyright © 2024–2026 Jasper Ford
// SPDX-License-Identifier: AGPL-3.0-or-later
// Author: Jasper Ford
// See NOTICE file for attribution and acknowledgements.

namespace Nexus\Core;

/**
 * Thin delegate — forwards all calls to \App\Core\Csrf which
 * holds the real implementation.
 *
 * This class is kept for backward compatibility: legacy Nexus\ namespace
 * code references it. The public API is identical.
 *
 * @see \App\Core\Csrf  The authoritative implementation.
 * @deprecated Use \App\Core\Csrf instead.
 */
class Csrf
{
    public static function generate(): string
    {
        return \App\Core\Csrf::generate();
    }

    public static function verify($token = null): bool
    {
        return \App\Core\Csrf::verify($token);
    }

    public static function verifyOrDie(): void
    {
        \App\Core\Csrf::verifyOrDie();
    }

    public static function verifyOrDieJson(): bool
    {
        return \App\Core\Csrf::verifyOrDieJson();
    }

    public static function input(): string
    {
        return \App\Core\Csrf::input();
    }

    public static function field(): string
    {
        return \App\Core\Csrf::field();
    }

    public static function token(): string
    {
        return \App\Core\Csrf::token();
    }
}
