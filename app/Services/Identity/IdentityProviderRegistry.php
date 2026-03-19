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
        if (!class_exists(LegacyRegistry::class)) {
            return;
        }
        LegacyRegistry::register($provider);
    }

    /**
     * Get a provider by slug.
     *
     * @throws \InvalidArgumentException If provider not found
     */
    public function get(string $slug): \Nexus\Services\Identity\IdentityVerificationProviderInterface
    {
        if (!class_exists(LegacyRegistry::class)) {
            throw new \RuntimeException('Legacy IdentityProviderRegistry is not available');
        }
        return LegacyRegistry::get($slug);
    }

    /**
     * Check if a provider is registered.
     */
    public function has(string $slug): bool
    {
        if (!class_exists(LegacyRegistry::class)) {
            return false;
        }
        return LegacyRegistry::has($slug);
    }

    /**
     * Get all registered providers.
     *
     * @return array<string, \Nexus\Services\Identity\IdentityVerificationProviderInterface>
     */
    public function all(): array
    {
        if (!class_exists(LegacyRegistry::class)) {
            return [];
        }
        return LegacyRegistry::all();
    }

    /**
     * Get provider listing for admin UI.
     *
     * @return array<int, array{slug: string, name: string, levels: string[]}>
     */
    public function listForAdmin(): array
    {
        if (!class_exists(LegacyRegistry::class)) {
            return [];
        }
        return LegacyRegistry::listForAdmin();
    }

    /**
     * Reset registry (for testing).
     */
    public function reset(): void
    {
        if (!class_exists(LegacyRegistry::class)) {
            return;
        }
        LegacyRegistry::reset();
    }
}
