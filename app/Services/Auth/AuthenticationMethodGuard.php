<?php
// Copyright © 2024–2026 Jasper Ford
// SPDX-License-Identifier: AGPL-3.0-or-later
// Author: Jasper Ford
// See NOTICE file for attribution and acknowledgements.

namespace App\Services\Auth;

use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * Prevents account-management actions from deleting a member's final primary
 * sign-in method. Callers that mutate methods should run inside a transaction
 * and request the user-row lock so competing removals serialize.
 */
final class AuthenticationMethodGuard
{
    public static function hasAlternativeToPasskeys(int $userId, int $tenantId, bool $lockUser = false): bool
    {
        $user = self::findUser($userId, $tenantId, $lockUser);
        if ($user === null) {
            return false;
        }

        if (self::hasPassword($user)) {
            return true;
        }

        return self::oauthIdentityCount($userId, $tenantId) > 0;
    }

    public static function hasAlternativeToOauthProvider(
        int $userId,
        int $tenantId,
        string $provider,
        bool $lockUser = false
    ): bool {
        $user = self::findUser($userId, $tenantId, $lockUser);
        if ($user === null) {
            return false;
        }

        if (self::hasPassword($user)) {
            return true;
        }

        if (Schema::hasTable('webauthn_credentials') && DB::table('webauthn_credentials')
            ->where('user_id', $userId)
            ->where('tenant_id', $tenantId)
            ->exists()) {
            return true;
        }

        return self::oauthIdentityCount($userId, $tenantId, $provider) > 0;
    }

    private static function findUser(int $userId, int $tenantId, bool $lockUser): ?object
    {
        $query = DB::table('users')
            ->where('id', $userId)
            ->where('tenant_id', $tenantId)
            ->select(['password', 'password_hash']);

        if ($lockUser) {
            $query->lockForUpdate();
        }

        return $query->first();
    }

    private static function hasPassword(object $user): bool
    {
        return !empty($user->password_hash) || !empty($user->password);
    }

    private static function oauthIdentityCount(int $userId, int $tenantId, ?string $excludedProvider = null): int
    {
        if (!Schema::hasTable('oauth_identities')) {
            return 0;
        }

        $query = DB::table('oauth_identities')
            ->where('user_id', $userId)
            ->where('tenant_id', $tenantId);

        if ($excludedProvider !== null) {
            $query->where('provider', '!=', $excludedProvider);
        }

        return $query->count();
    }
}
