<?php
// Copyright © 2024–2026 Jasper Ford
// SPDX-License-Identifier: AGPL-3.0-or-later
// Author: Jasper Ford
// See NOTICE file for attribution and acknowledgements.

namespace Nexus\Services\Identity;

/**
 * StripeIdentityProvider — Thin delegate forwarding to \App\Services\Identity\StripeIdentityProvider.
 *
 * The full implementation now lives in the App namespace.
 * This file exists for backwards compatibility only.
 *
 * @see \App\Services\Identity\StripeIdentityProvider
 */
class StripeIdentityProvider implements IdentityVerificationProviderInterface
{

    public function getSlug(): string
    {
        return (new \App\Services\Identity\StripeIdentityProvider())->getSlug();
    }

    public function getName(): string
    {
        return (new \App\Services\Identity\StripeIdentityProvider())->getName();
    }

    public function getSupportedLevels(): array
    {
        return (new \App\Services\Identity\StripeIdentityProvider())->getSupportedLevels();
    }

    public function createSession(int $userId, int $tenantId, string $level, array $metadata = []): array
    {
        return (new \App\Services\Identity\StripeIdentityProvider())->createSession($userId, $tenantId, $level, $metadata);
    }

    public function getSessionStatus(string $providerSessionId): array
    {
        return (new \App\Services\Identity\StripeIdentityProvider())->getSessionStatus($providerSessionId);
    }

    public function handleWebhook(array $payload, array $headers): array
    {
        return (new \App\Services\Identity\StripeIdentityProvider())->handleWebhook($payload, $headers);
    }

    public function verifyWebhookSignature(string $rawBody, array $headers): bool
    {
        return (new \App\Services\Identity\StripeIdentityProvider())->verifyWebhookSignature($rawBody, $headers);
    }

    public function cancelSession(string $providerSessionId): bool
    {
        return (new \App\Services\Identity\StripeIdentityProvider())->cancelSession($providerSessionId);
    }

    public function isAvailable(int $tenantId): bool
    {
        return (new \App\Services\Identity\StripeIdentityProvider())->isAvailable($tenantId);
    }
}
