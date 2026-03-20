<?php
// Copyright © 2024–2026 Jasper Ford
// SPDX-License-Identifier: AGPL-3.0-or-later
// Author: Jasper Ford
// See NOTICE file for attribution and acknowledgements.

namespace Nexus\Services\Identity;

/**
 * MockIdentityProvider — Thin delegate forwarding to \App\Services\Identity\MockIdentityProvider.
 *
 * The full implementation now lives in the App namespace.
 * This file exists for backwards compatibility only.
 *
 * @see \App\Services\Identity\MockIdentityProvider
 */
class MockIdentityProvider implements IdentityVerificationProviderInterface
{

    public function getSlug(): string
    {
        return (new \App\Services\Identity\MockIdentityProvider())->getSlug();
    }

    public function getName(): string
    {
        return (new \App\Services\Identity\MockIdentityProvider())->getName();
    }

    public function getSupportedLevels(): array
    {
        return (new \App\Services\Identity\MockIdentityProvider())->getSupportedLevels();
    }

    public function createSession(int $userId, int $tenantId, string $level, array $metadata = []): array
    {
        return (new \App\Services\Identity\MockIdentityProvider())->createSession($userId, $tenantId, $level, $metadata);
    }

    public function getSessionStatus(string $providerSessionId): array
    {
        return (new \App\Services\Identity\MockIdentityProvider())->getSessionStatus($providerSessionId);
    }

    public function handleWebhook(array $payload, array $headers): array
    {
        return (new \App\Services\Identity\MockIdentityProvider())->handleWebhook($payload, $headers);
    }

    public function verifyWebhookSignature(string $rawBody, array $headers): bool
    {
        return (new \App\Services\Identity\MockIdentityProvider())->verifyWebhookSignature($rawBody, $headers);
    }

    public function cancelSession(string $providerSessionId): bool
    {
        return (new \App\Services\Identity\MockIdentityProvider())->cancelSession($providerSessionId);
    }

    public function isAvailable(int $tenantId): bool
    {
        return (new \App\Services\Identity\MockIdentityProvider())->isAvailable($tenantId);
    }
}
