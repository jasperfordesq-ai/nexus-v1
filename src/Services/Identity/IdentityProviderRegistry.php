<?php
// Copyright © 2024–2026 Jasper Ford
// SPDX-License-Identifier: AGPL-3.0-or-later
// Author: Jasper Ford
// See NOTICE file for attribution and acknowledgements.

namespace Nexus\Services\Identity;

/**
 * IdentityProviderRegistry — Factory/registry for identity verification providers.
 *
 * Providers register themselves by slug. The orchestration service
 * resolves the correct provider for a tenant's configured policy.
 */
class IdentityProviderRegistry
{
    /** @var array<string, IdentityVerificationProviderInterface> */
    private static array $providers = [];

    /** @var bool */
    private static bool $initialized = false;

    /**
     * Register a provider instance.
     */
    public static function register(IdentityVerificationProviderInterface $provider): void
    {
        self::$providers[$provider->getSlug()] = $provider;
    }

    /**
     * Get a provider by slug.
     *
     * @throws \InvalidArgumentException If provider not found
     */
    public static function get(string $slug): IdentityVerificationProviderInterface
    {
        self::ensureInitialized();

        if (!isset(self::$providers[$slug])) {
            throw new \InvalidArgumentException("Identity verification provider '{$slug}' is not registered.");
        }

        return self::$providers[$slug];
    }

    /**
     * Check if a provider is registered.
     */
    public static function has(string $slug): bool
    {
        self::ensureInitialized();
        return isset(self::$providers[$slug]);
    }

    /**
     * Get all registered providers.
     *
     * @return array<string, IdentityVerificationProviderInterface>
     */
    public static function all(): array
    {
        self::ensureInitialized();
        return self::$providers;
    }

    /**
     * Get provider listing for admin UI (slug, name, supported levels).
     *
     * @return array<int, array{slug: string, name: string, levels: string[]}>
     */
    public static function listForAdmin(): array
    {
        self::ensureInitialized();
        $list = [];

        foreach (self::$providers as $provider) {
            $list[] = [
                'slug' => $provider->getSlug(),
                'name' => $provider->getName(),
                'levels' => $provider->getSupportedLevels(),
            ];
        }

        return $list;
    }

    /**
     * Initialize built-in providers on first access.
     */
    private static function ensureInitialized(): void
    {
        if (self::$initialized) {
            return;
        }

        self::$initialized = true;

        // Register built-in providers
        self::register(new MockIdentityProvider());
        self::register(new StripeIdentityProvider());

        // Future providers registered here:
        // self::register(new VeriffProvider());
        // self::register(new JumioProvider());
    }

    /**
     * Reset registry (for testing).
     */
    public static function reset(): void
    {
        self::$providers = [];
        self::$initialized = false;
    }
}
