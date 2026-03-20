<?php
// Copyright © 2024–2026 Jasper Ford
// SPDX-License-Identifier: AGPL-3.0-or-later
// Author: Jasper Ford
// See NOTICE file for attribution and acknowledgements.

namespace Nexus\Services\AI;

/**
 * AIServiceFactory — Thin delegate forwarding to \App\Services\AI\AIServiceFactory.
 *
 * The full implementation now lives in the App namespace.
 * This file exists for backwards compatibility only.
 *
 * @see \App\Services\AI\AIServiceFactory
 */
class AIServiceFactory
{

    public static function getProvider(?string $providerId = null): AIProviderInterface
    {
        return \App\Services\AI\AIServiceFactory::getProvider($providerId);
    }

    public static function getProviderWithFallback(?string $preferredId = null): array
    {
        return \App\Services\AI\AIServiceFactory::getProviderWithFallback($preferredId);
    }

    public static function chatWithFallback(array $messages, array $options = [], ?string $preferredProvider = null): array
    {
        return \App\Services\AI\AIServiceFactory::chatWithFallback($messages, $options, $preferredProvider);
    }

    public static function getProviderConfig(string $providerId): array
    {
        return \App\Services\AI\AIServiceFactory::getProviderConfig($providerId);
    }

    public static function getDefaultProvider(): string
    {
        return \App\Services\AI\AIServiceFactory::getDefaultProvider();
    }

    public static function isEnabled(): bool
    {
        return \App\Services\AI\AIServiceFactory::isEnabled();
    }

    public static function isFeatureEnabled(string $feature): bool
    {
        return \App\Services\AI\AIServiceFactory::isFeatureEnabled($feature);
    }

    public static function getAvailableProviders(): array
    {
        return \App\Services\AI\AIServiceFactory::getAvailableProviders();
    }

    public static function getSystemPrompt(): string
    {
        return \App\Services\AI\AIServiceFactory::getSystemPrompt();
    }

    public static function getLimitsConfig(): array
    {
        return \App\Services\AI\AIServiceFactory::getLimitsConfig();
    }

    public static function clearCache(): void
    {
        \App\Services\AI\AIServiceFactory::clearCache();
    }
}
