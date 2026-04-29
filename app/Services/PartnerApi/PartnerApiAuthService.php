<?php
// Copyright © 2024–2026 Jasper Ford
// SPDX-License-Identifier: AGPL-3.0-or-later
// Author: Jasper Ford
// See NOTICE file for attribution and acknowledgements.

declare(strict_types=1);

namespace App\Services\PartnerApi;

use App\Core\TenantContext;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

/**
 * AG60 — Partner API auth service.
 *
 * Issues client credentials, validates them, mints OAuth bearer tokens
 * (client_credentials grant), and resolves access tokens back to a
 * partner row for the auth middleware. All queries are tenant-scoped.
 *
 * Token storage: only the SHA-256 hash of an access token is persisted —
 * the raw token is shown to the partner once (in the OAuth response) and
 * never stored. Client secrets are bcrypt-hashed.
 */
class PartnerApiAuthService
{
    public const DEFAULT_TOKEN_TTL_SECONDS = 3600;

    public static function issueClientCredentials(int $partnerId): array
    {
        $tenantId = TenantContext::getId();
        $clientId = 'pk_' . Str::random(28);
        $secret = 'sk_' . Str::random(40);

        DB::table('api_partner_credentials')->insert([
            'partner_id' => $partnerId,
            'tenant_id' => $tenantId,
            'client_id' => $clientId,
            'client_secret_hash' => password_hash($secret, PASSWORD_BCRYPT),
            'created_at' => now(),
        ]);

        return [
            'client_id' => $clientId,
            'client_secret' => $secret, // returned ONCE — never re-shown
        ];
    }

    public static function revokeCredentials(int $partnerId): int
    {
        $tenantId = TenantContext::getId();
        return DB::table('api_partner_credentials')
            ->where('partner_id', $partnerId)
            ->where('tenant_id', $tenantId)
            ->whereNull('revoked_at')
            ->update(['revoked_at' => now()]);
    }

    /**
     * Verify client_id + client_secret. Returns the partner row on success.
     */
    public static function verifyClient(string $clientId, string $clientSecret): ?array
    {
        $cred = DB::table('api_partner_credentials')
            ->where('client_id', $clientId)
            ->whereNull('revoked_at')
            ->first();

        if (! $cred) {
            return null;
        }

        if (! password_verify($clientSecret, $cred->client_secret_hash)) {
            return null;
        }

        $partner = DB::table('api_partners')
            ->where('id', $cred->partner_id)
            ->where('status', 'active')
            ->first();

        if (! $partner) {
            return null;
        }

        DB::table('api_partner_credentials')
            ->where('id', $cred->id)
            ->update(['last_used_at' => now()]);

        return (array) $partner;
    }

    /**
     * Mint an OAuth bearer token for the given partner.
     *
     * @return array{access_token:string,token_type:string,expires_in:int,scope:string}
     */
    public static function issueAccessToken(array $partner, ?array $requestedScopes = null): array
    {
        $allowed = self::decodeJsonArray($partner['allowed_scopes'] ?? null);
        $scopes = $requestedScopes !== null
            ? array_values(array_intersect($requestedScopes, $allowed))
            : $allowed;

        $rawToken = 'at_' . Str::random(48);
        $hash = hash('sha256', $rawToken);
        $expiresAt = now()->addSeconds(self::DEFAULT_TOKEN_TTL_SECONDS);

        DB::table('api_oauth_tokens')->insert([
            'partner_id' => (int) $partner['id'],
            'tenant_id' => (int) $partner['tenant_id'],
            'access_token_hash' => $hash,
            'scopes' => json_encode($scopes),
            'expires_at' => $expiresAt,
            'created_at' => now(),
        ]);

        return [
            'access_token' => $rawToken,
            'token_type' => 'bearer',
            'expires_in' => self::DEFAULT_TOKEN_TTL_SECONDS,
            'scope' => implode(' ', $scopes),
        ];
    }

    /**
     * Resolve a raw bearer token to {partner, scopes}.
     */
    public static function resolveAccessToken(string $rawToken): ?array
    {
        $hash = hash('sha256', $rawToken);

        $row = DB::table('api_oauth_tokens')
            ->where('access_token_hash', $hash)
            ->whereNull('revoked_at')
            ->where('expires_at', '>', now())
            ->first();

        if (! $row) {
            return null;
        }

        $partner = DB::table('api_partners')
            ->where('id', $row->partner_id)
            ->where('status', 'active')
            ->first();

        if (! $partner) {
            return null;
        }

        return [
            'partner' => (array) $partner,
            'scopes' => self::decodeJsonArray($row->scopes),
            'token_id' => (int) $row->id,
        ];
    }

    public static function revokeAccessToken(string $rawToken): bool
    {
        $hash = hash('sha256', $rawToken);
        return DB::table('api_oauth_tokens')
            ->where('access_token_hash', $hash)
            ->whereNull('revoked_at')
            ->update(['revoked_at' => now()]) > 0;
    }

    private static function decodeJsonArray(mixed $value): array
    {
        if (is_string($value)) {
            $decoded = json_decode($value, true);
            return is_array($decoded) ? $decoded : [];
        }
        return is_array($value) ? $value : [];
    }
}
