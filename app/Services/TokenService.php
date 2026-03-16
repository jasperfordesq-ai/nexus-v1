<?php
// Copyright © 2024–2026 Jasper Ford
// SPDX-License-Identifier: AGPL-3.0-or-later
// Author: Jasper Ford
// See NOTICE file for attribution and acknowledgements.

namespace App\Services;

use App\Models\User;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

/**
 * TokenService — Laravel DI-based service for API token management.
 *
 * Eloquent/DI counterpart to the legacy static \Nexus\Services\TokenService.
 * Provides simple, secure token lifecycle operations.
 */
class TokenService
{
    private const ACCESS_EXPIRY_WEB = 7200;        // 2 hours
    private const ACCESS_EXPIRY_MOBILE = 2592000;  // 30 days

    public function __construct(
        private readonly User $user,
    ) {}

    /**
     * Create a new API token for a user.
     *
     * @return array{token: string, expires_at: string}
     */
    public function createToken(int $userId, string $deviceType = 'web'): array
    {
        $token = Str::random(64);
        $seconds = $deviceType === 'mobile' ? self::ACCESS_EXPIRY_MOBILE : self::ACCESS_EXPIRY_WEB;
        $expiresAt = now()->addSeconds($seconds);

        DB::table('api_tokens')->insert([
            'user_id'     => $userId,
            'token'       => hash('sha256', $token),
            'device_type' => $deviceType,
            'expires_at'  => $expiresAt,
            'created_at'  => now(),
            'updated_at'  => now(),
        ]);

        return ['token' => $token, 'expires_at' => $expiresAt->toIso8601String()];
    }

    /**
     * Validate a token and return the associated user ID.
     */
    public function validateToken(string $token): ?int
    {
        $record = DB::table('api_tokens')
            ->where('token', hash('sha256', $token))
            ->where('expires_at', '>', now())
            ->first();

        return $record ? (int) $record->user_id : null;
    }

    /**
     * Revoke a specific token.
     */
    public function revokeToken(string $token): bool
    {
        return DB::table('api_tokens')
            ->where('token', hash('sha256', $token))
            ->delete() > 0;
    }

    /**
     * Revoke all tokens for a user.
     */
    public function revokeAllForUser(int $userId): int
    {
        return DB::table('api_tokens')
            ->where('user_id', $userId)
            ->delete();
    }
}
