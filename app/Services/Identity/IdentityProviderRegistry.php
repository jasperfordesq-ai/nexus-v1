<?php
// Copyright © 2024–2026 Jasper Ford
// SPDX-License-Identifier: AGPL-3.0-or-later
// Author: Jasper Ford
// See NOTICE file for attribution and acknowledgements.

namespace App\Services\Identity;

use Nexus\Services\Identity\IdentityProviderRegistry as LegacyRegistry;

/**
 * IdentityProviderRegistry — Laravel DI wrapper for legacy registry.
 *
 * Delegates to \Nexus\Services\Identity\IdentityProviderRegistry.
 */
class IdentityProviderRegistry
{
    public function __construct()
    {
    }

    /**
     * Register a provider instance.
     */
    public function register(\Nexus\Services\Identity\IdentityVerificationProviderInterface $provider): void
    {
        LegacyRegistry::register($provider);
    }

    /**
     * Get a provider by slug.
     *
     * @throws \InvalidArgumentException If provider not found
     */
    public function get(string $slug): \Nexus\Services\Identity\IdentityVerificationProviderInterface
    {
        return LegacyRegistry::get($slug);
    }

    /**
     * Check if a provider is registered.
     */
    public function has(string $slug): bool
    {
        return LegacyRegistry::has($slug);
    }

    /**
     * Get all registered providers.
     *
     * @return array<string, \Nexus\Services\Identity\IdentityVerificationProviderInterface>
     */
    public function all(): array
    {
        return LegacyRegistry::all();
    }

    /**
     * Get provider listing for admin UI.
     *
     * @return array<int, array{slug: string, name: string, levels: string[]}>
     */
    public function listForAdmin(): array
    {
        return LegacyRegistry::listForAdmin();
    }

    /**
     * Reset registry (for testing).
     */
    public function reset(): void
    {
        LegacyRegistry::reset();
    }
}
