<?php
// Copyright © 2024–2026 Jasper Ford
// SPDX-License-Identifier: AGPL-3.0-or-later
// Author: Jasper Ford
// See NOTICE file for attribution and acknowledgements.

namespace App\Services;

use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Str;

/**
 * TwoFactorChallengeManager — manages 2FA challenge tokens during login.
 *
 * When a user with 2FA enabled logs in, a challenge token is created and returned.
 * The client must then verify the TOTP code or backup code using this token.
 * Tokens are stored in cache (Redis) with a 5-minute TTL and limited attempts.
 */
class TwoFactorChallengeManager
{
    /** @var int Challenge TTL in seconds (5 minutes) */
    private const CHALLENGE_TTL = 300;

    /** @var int Maximum verification attempts before lockout */
    private const MAX_ATTEMPTS = 5;

    /** @var string Cache key prefix */
    private const PREFIX = '2fa_challenge:';

    /**
     * Create a new 2FA challenge for the given user.
     *
     * @param int $userId The user requiring 2FA
     * @param array $methods Allowed verification methods (e.g. ['totp', 'backup_code'])
     * @return string The challenge token to return to the client
     */
    public function create(int $userId, array $methods = ['totp']): string
    {
        $token = Str::random(64);

        Cache::put(self::PREFIX . $token, [
            'user_id' => $userId,
            'methods' => $methods,
            'attempts' => 0,
            'created_at' => now()->toIso8601String(),
        ], self::CHALLENGE_TTL);

        return $token;
    }

    /**
     * Get the challenge data for a token.
     *
     * @return array|null The challenge data, or null if expired/invalid
     */
    public function get(string $token): ?array
    {
        return Cache::get(self::PREFIX . $token);
    }

    /**
     * Record a verification attempt against a challenge token.
     *
     * @return array ['allowed' => bool, 'attempts_remaining' => int]
     */
    public function recordAttempt(string $token): array
    {
        $data = Cache::get(self::PREFIX . $token);

        if (!$data) {
            return ['allowed' => false, 'attempts_remaining' => 0];
        }

        $data['attempts'] = ($data['attempts'] ?? 0) + 1;
        $remaining = self::MAX_ATTEMPTS - $data['attempts'];

        if ($remaining <= 0) {
            // Max attempts exceeded — delete the challenge
            Cache::forget(self::PREFIX . $token);
            return ['allowed' => false, 'attempts_remaining' => 0];
        }

        // Update the stored data with incremented attempt count
        Cache::put(self::PREFIX . $token, $data, self::CHALLENGE_TTL);

        return ['allowed' => true, 'attempts_remaining' => $remaining];
    }

    /**
     * Consume (use) a challenge token after successful verification.
     * The token is deleted and cannot be reused.
     */
    public function consume(string $token): bool
    {
        return Cache::forget(self::PREFIX . $token);
    }

    /**
     * Delete a challenge token (e.g. on cancellation or timeout).
     */
    public function delete(string $token): bool
    {
        return Cache::forget(self::PREFIX . $token);
    }
}
