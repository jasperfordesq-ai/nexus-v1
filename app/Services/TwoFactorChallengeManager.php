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
        \Illuminate\Support\Facades\Log::warning('Legacy delegation removed: ' . __METHOD__);
        return '';
    }

    /**
     * Delegates to legacy TwoFactorChallengeManager::get().
     */
    public function get(string $token): ?array
    {
        \Illuminate\Support\Facades\Log::warning('Legacy delegation removed: ' . __METHOD__);
        return null;
    }

    /**
     * Delegates to legacy TwoFactorChallengeManager::recordAttempt().
     */
    public function recordAttempt(string $token): array
    {
        \Illuminate\Support\Facades\Log::warning('Legacy delegation removed: ' . __METHOD__);
        return [];
    }

    /**
     * Delegates to legacy TwoFactorChallengeManager::consume().
     */
    public function consume(string $token): bool
    {
        \Illuminate\Support\Facades\Log::warning('Legacy delegation removed: ' . __METHOD__);
        return false;
    }

    /**
     * Delegates to legacy TwoFactorChallengeManager::delete().
     */
    public function delete(string $token): bool
    {
        \Illuminate\Support\Facades\Log::warning('Legacy delegation removed: ' . __METHOD__);
        return false;
    }
}
