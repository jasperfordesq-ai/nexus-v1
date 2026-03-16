<?php
// Copyright © 2024–2026 Jasper Ford
// SPDX-License-Identifier: AGPL-3.0-or-later
// Author: Jasper Ford
// See NOTICE file for attribution and acknowledgements.

namespace App\Services;

/**
 * TwoFactorChallengeManager — Laravel DI wrapper for legacy \Nexus\Services\TwoFactorChallengeManager.
 *
 * Provides dependency-injectable access to the legacy static service methods.
 */
class TwoFactorChallengeManager
{
    public function __construct()
    {
    }

    /**
     * Delegates to legacy TwoFactorChallengeManager::create().
     */
    public function create(int $userId, array $methods = ['totp']): string
    {
        return \Nexus\Services\TwoFactorChallengeManager::create($userId, $methods);
    }

    /**
     * Delegates to legacy TwoFactorChallengeManager::get().
     */
    public function get(string $token): ?array
    {
        return \Nexus\Services\TwoFactorChallengeManager::get($token);
    }

    /**
     * Delegates to legacy TwoFactorChallengeManager::recordAttempt().
     */
    public function recordAttempt(string $token): array
    {
        return \Nexus\Services\TwoFactorChallengeManager::recordAttempt($token);
    }

    /**
     * Delegates to legacy TwoFactorChallengeManager::consume().
     */
    public function consume(string $token): bool
    {
        return \Nexus\Services\TwoFactorChallengeManager::consume($token);
    }

    /**
     * Delegates to legacy TwoFactorChallengeManager::delete().
     */
    public function delete(string $token): bool
    {
        return \Nexus\Services\TwoFactorChallengeManager::delete($token);
    }
}
