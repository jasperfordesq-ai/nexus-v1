<?php
// Copyright © 2024–2026 Jasper Ford
// SPDX-License-Identifier: AGPL-3.0-or-later
// Author: Jasper Ford
// See NOTICE file for attribution and acknowledgements.

namespace App\Services\AI;

/**
 * AIServiceFactory — Laravel DI wrapper for legacy \Nexus\Services\AI\AIServiceFactory.
 *
 * Provides dependency-injectable access to the legacy static service methods.
 */
class AIServiceFactory
{
    public function __construct()
    {
    }

    /**
     * Delegates to legacy AIServiceFactory::getProvider().
     */
    public function getProvider(?string $providerId = null): NexusServicesAIAIProviderInterface
    {
        return \Nexus\Services\AI\AIServiceFactory::getProvider($providerId);
    }

    /**
     * Delegates to legacy AIServiceFactory::getProviderWithFallback().
     */
    public function getProviderWithFallback(?string $preferredId = null): array
    {
        return \Nexus\Services\AI\AIServiceFactory::getProviderWithFallback($preferredId);
    }

    /**
     * Delegates to legacy AIServiceFactory::chatWithFallback().
     */
    public function chatWithFallback(array $messages, array $options = [], ?string $preferredProvider = null): array
    {
        return \Nexus\Services\AI\AIServiceFactory::chatWithFallback($messages, $options, $preferredProvider);
    }

    /**
     * Delegates to legacy AIServiceFactory::getProviderConfig().
     */
    public function getProviderConfig(string $providerId): array
    {
        return \Nexus\Services\AI\AIServiceFactory::getProviderConfig($providerId);
    }

    /**
     * Delegates to legacy AIServiceFactory::getDefaultProvider().
     */
    public function getDefaultProvider(): string
    {
        return \Nexus\Services\AI\AIServiceFactory::getDefaultProvider();
    }
}
