<?php
// Copyright © 2024–2026 Jasper Ford
// SPDX-License-Identifier: AGPL-3.0-or-later
// Author: Jasper Ford
// See NOTICE file for attribution and acknowledgements.

namespace Nexus\Services\Identity;

/**
 * IdentityProviderRegistry — Thin delegate forwarding to \App\Services\Identity\IdentityProviderRegistry.
 *
 * The full implementation now lives in the App namespace.
 * This file exists for backwards compatibility only.
 *
 * @see \App\Services\Identity\IdentityProviderRegistry
 */
class IdentityProviderRegistry
{

    public static function register($provider): void
    {
        \App\Services\Identity\IdentityProviderRegistry::register($provider);
    }

    public static function get(string $slug)
    {
        return \App\Services\Identity\IdentityProviderRegistry::get($slug);
    }

    public static function has(string $slug): bool
    {
        return \App\Services\Identity\IdentityProviderRegistry::has($slug);
    }

    public static function all(): array
    {
        return \App\Services\Identity\IdentityProviderRegistry::all();
    }

    public static function listForAdmin(): array
    {
        return \App\Services\Identity\IdentityProviderRegistry::listForAdmin();
    }

    public static function reset(): void
    {
        \App\Services\Identity\IdentityProviderRegistry::reset();
    }
}
