<?php
// Copyright © 2024–2026 Jasper Ford
// SPDX-License-Identifier: AGPL-3.0-or-later
// Author: Jasper Ford
// See NOTICE file for attribution and acknowledgements.

namespace App\Services;

/**
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
        \Illuminate\Support\Facades\Log::warning('Legacy delegation removed: ' . __METHOD__);
        return '';
    }

    /**
     * Delegates to legacy WebAuthnChallengeStore::get().
     */
    public function get(string $challengeId): ?array
    {
        \Illuminate\Support\Facades\Log::warning('Legacy delegation removed: ' . __METHOD__);
        return null;
    }

    /**
     * Delegates to legacy WebAuthnChallengeStore::consume().
     */
    public function consume(string $challengeId): bool
    {
        \Illuminate\Support\Facades\Log::warning('Legacy delegation removed: ' . __METHOD__);
        return false;
    }

    /**
     * Delegates to legacy WebAuthnChallengeStore::delete().
     */
    public function delete(string $challengeId): bool
    {
        \Illuminate\Support\Facades\Log::warning('Legacy delegation removed: ' . __METHOD__);
        return false;
    }

    /**
     * Delegates to legacy WebAuthnChallengeStore::verify().
     */
    public function verify(string $challengeId, string $expectedChallenge, ?int $expectedUserId = null, ?string $expectedType = null): array
    {
        \Illuminate\Support\Facades\Log::warning('Legacy delegation removed: ' . __METHOD__);
        return [];
    }
}
