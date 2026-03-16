<?php
// Copyright © 2024–2026 Jasper Ford
// SPDX-License-Identifier: AGPL-3.0-or-later
// Author: Jasper Ford
// See NOTICE file for attribution and acknowledgements.

namespace App\Services;

use App\Models\User;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Str;

/**
 * AuthService — Laravel DI-based service for authentication operations.
 *
 * Eloquent/DI counterpart to legacy static authentication logic.
 * All queries are tenant-scoped automatically via the HasTenantScope trait.
 */
class AuthService
{
    public function __construct(
        private readonly User $user,
    ) {}

    /**
     * Authenticate a user by email and password.
     *
     * @return array{user: array, token: string}|null
     */
    public function login(string $email, string $password, ?string $deviceType = null): ?array
    {
        /** @var User|null $user */
        $user = $this->user->newQuery()
            ->where('email', $email)
            ->where('status', 'active')
            ->first();

        if (! $user || ! Hash::check($password, $user->password)) {
            return null;
        }

        $token = $this->createApiToken($user->id, $deviceType);

        return [
            'user'  => $user->only(['id', 'first_name', 'last_name', 'email', 'role', 'avatar_url']),
            'token' => $token,
        ];
    }

    /**
     * Logout by revoking the given token.
     */
    public function logout(string $token): bool
    {
        return DB::table('api_tokens')
            ->where('token', hash('sha256', $token))
            ->delete() > 0;
    }

    /**
     * Validate an API token and return the associated user.
     */
    public function validateToken(string $token): ?User
    {
        $record = DB::table('api_tokens')
            ->where('token', hash('sha256', $token))
            ->where('expires_at', '>', now())
            ->first();

        if (! $record) {
            return null;
        }

        return $this->user->newQuery()->find($record->user_id);
    }

    /**
     * Refresh an existing token, extending its expiry.
     *
     * @return array{token: string, expires_at: string}|null
     */
    public function refreshToken(string $currentToken): ?array
    {
        $hashed = hash('sha256', $currentToken);

        $record = DB::table('api_tokens')
            ->where('token', $hashed)
            ->where('expires_at', '>', now())
            ->first();

        if (! $record) {
            return null;
        }

        $newToken = Str::random(64);
        $expiresAt = now()->addDays(30);

        DB::table('api_tokens')
            ->where('token', $hashed)
            ->update([
                'token'      => hash('sha256', $newToken),
                'expires_at' => $expiresAt,
                'updated_at' => now(),
            ]);

        return ['token' => $newToken, 'expires_at' => $expiresAt->toIso8601String()];
    }

    /**
     * Create a new API token for a user.
     */
    private function createApiToken(int $userId, ?string $deviceType = null): string
    {
        $token = Str::random(64);
        $expiresAt = $deviceType === 'mobile' ? now()->addDays(90) : now()->addDays(30);

        DB::table('api_tokens')->insert([
            'user_id'     => $userId,
            'token'       => hash('sha256', $token),
            'device_type' => $deviceType,
            'expires_at'  => $expiresAt,
            'created_at'  => now(),
            'updated_at'  => now(),
        ]);

        return $token;
    }
}
