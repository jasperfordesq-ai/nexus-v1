<?php
// Copyright © 2024–2026 Jasper Ford
// SPDX-License-Identifier: AGPL-3.0-or-later
// Author: Jasper Ford
// See NOTICE file for attribution and acknowledgements.

namespace App\Services;

/**
 * WebAuthnChallengeStore — Laravel DI wrapper for legacy \Nexus\Services\WebAuthnChallengeStore.
 *
 * Provides dependency-injectable access to the legacy static service methods.
 */
class WebAuthnChallengeStore
{
    public function __construct()
    {
    }

    /**
     * Delegates to legacy WebAuthnChallengeStore::create().
     */
    public function create(string $challenge, ?int $userId, string $type = 'authenticate', array $metadata = []): string
    {
        return \Nexus\Services\WebAuthnChallengeStore::create($challenge, $userId, $type, $metadata);
    }

    /**
     * Delegates to legacy WebAuthnChallengeStore::get().
     */
    public function get(string $challengeId): ?array
    {
        return \Nexus\Services\WebAuthnChallengeStore::get($challengeId);
    }

    /**
     * Delegates to legacy WebAuthnChallengeStore::consume().
     */
    public function consume(string $challengeId): bool
    {
        return \Nexus\Services\WebAuthnChallengeStore::consume($challengeId);
    }

    /**
     * Delegates to legacy WebAuthnChallengeStore::delete().
     */
    public function delete(string $challengeId): bool
    {
        return \Nexus\Services\WebAuthnChallengeStore::delete($challengeId);
    }

    /**
     * Delegates to legacy WebAuthnChallengeStore::verify().
     */
    public function verify(string $challengeId, string $expectedChallenge, ?int $expectedUserId = null, ?string $expectedType = null): array
    {
        return \Nexus\Services\WebAuthnChallengeStore::verify($challengeId, $expectedChallenge, $expectedUserId, $expectedType);
    }
}
