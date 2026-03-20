<?php
// Copyright © 2024–2026 Jasper Ford
// SPDX-License-Identifier: AGPL-3.0-or-later
// Author: Jasper Ford
// See NOTICE file for attribution and acknowledgements.

namespace Nexus\Services\Identity;

/**
 * JumioProvider — Thin delegate forwarding to \App\Services\Identity\JumioProvider.
 *
 * The full implementation now lives in the App namespace.
 * This file exists for backwards compatibility only.
 *
 * @see \App\Services\Identity\JumioProvider
 */
class JumioProvider implements IdentityVerificationProviderInterface
{

    public function getSlug(): string
    {
        return (new \App\Services\Identity\JumioProvider())->getSlug();
    }

    public function getName(): string
    {
        return (new \App\Services\Identity\JumioProvider())->getName();
    }

    public function getSupportedLevels(): array
    {
        return (new \App\Services\Identity\JumioProvider())->getSupportedLevels();
    }

    public function createSession(int $userId, int $tenantId, string $level, array $metadata = []): array
    {
        return (new \App\Services\Identity\JumioProvider())->createSession($userId, $tenantId, $level, $metadata);
    }

    public function getSessionStatus(string $providerSessionId): array
    {
        return (new \App\Services\Identity\JumioProvider())->getSessionStatus($providerSessionId);
    }

    public function handleWebhook(array $payload, array $headers): array
    {
        return (new \App\Services\Identity\JumioProvider())->handleWebhook($payload, $headers);
    }

    public function verifyWebhookSignature(string $rawBody, array $headers): bool
    {
        return (new \App\Services\Identity\JumioProvider())->verifyWebhookSignature($rawBody, $headers);
    }

    public function cancelSession(string $providerSessionId): bool
    {
        return (new \App\Services\Identity\JumioProvider())->cancelSession($providerSessionId);
    }

    public function isAvailable(int $tenantId): bool
    {
        return (new \App\Services\Identity\JumioProvider())->isAvailable($tenantId);
    }
}
