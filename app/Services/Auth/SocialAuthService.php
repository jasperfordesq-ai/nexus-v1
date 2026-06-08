<?php
// Copyright © 2024–2026 Jasper Ford
// SPDX-License-Identifier: AGPL-3.0-or-later
// Author: Jasper Ford
// See NOTICE file for attribution and acknowledgements.

declare(strict_types=1);

namespace App\Services\Auth;

use App\Models\User;
use App\Services\TenantSettingsService;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Str;

/**
 * SOC13 — Social login (OAuth).
 *
 * Wraps Laravel Socialite to handle Google / Apple / Facebook sign-in.
 * Tenant context is propagated through a state token so the callback
 * can re-establish the tenant after the redirect round-trip.
 *
 * Identity linkage rules:
 *  - (provider, provider_user_id) is globally unique → same Google
 *    account cannot create two NEXUS users across tenants.
 *  - (user_id, provider) is unique → a user has at most one identity
 *    per provider; re-linking refreshes the existing row.
 */
class SocialAuthService
{
    public const SUPPORTED_PROVIDERS = ['google', 'apple', 'facebook'];
    public const LINK_NONCE_COOKIE = 'nexus_oauth_link_nonce';

    private const STATE_TTL_SECONDS = 3600;
    private const CALLBACK_CODE_TTL_SECONDS = 300;

    public function __construct(
        private readonly TenantSettingsService $tenantSettings,
    ) {
    }

    /**
     * Build the redirect URL for the given provider.
     *
     * Returns ['url' => string, 'state' => string]. The state token is a
     * signed payload encoding tenant id + intent (login|register|link)
     * + a random nonce. Verified on callback.
     */
    public function redirectUrl(string $provider, int $tenantId, string $intent = 'login', ?int $userId = null): array
    {
        $this->assertProviderSupported($provider);
        $this->assertProviderEnabledForTenant($provider, $tenantId);

        $linkNonce = $intent === 'link' ? Str::random(40) : null;
        $state = $this->buildState($tenantId, $intent, $userId, $linkNonce);

        if (! class_exists(\Laravel\Socialite\Facades\Socialite::class)) {
            // Socialite not installed yet — return a synthetic URL so the
            // controller can still report a useful error to the frontend.
            return [
                'url' => '',
                'state' => $state,
                'link_nonce' => $linkNonce,
                'error' => 'socialite_not_installed',
            ];
        }

        /** @var \Laravel\Socialite\Two\AbstractProvider $driver */
        $driver = \Laravel\Socialite\Facades\Socialite::driver($provider)->stateless();

        // Apple requires the `name` and `email` scopes & POST response mode.
        if ($provider === 'apple') {
            $driver = $driver->scopes(['name', 'email']);
        } elseif ($provider === 'google') {
            $driver = $driver->scopes(['openid', 'profile', 'email']);
        }

        $url = $driver->with(['state' => $state])->redirect()->getTargetUrl();

        return array_filter(
            ['url' => $url, 'state' => $state, 'link_nonce' => $linkNonce],
            static fn ($value) => $value !== null
        );
    }

    /**
     * Process an OAuth callback. Returns ['user' => User, 'is_new' => bool, 'tenant_id' => int].
     */
    public function handleCallback(string $provider, string $state, ?string $linkBrowserNonce = null): array
    {
        $this->assertProviderSupported($provider);

        $payload = $this->verifyState($state);
        $tenantId = $payload['tenant_id'];
        $intent = $payload['intent'];
        $linkUserId = $payload['user_id'] ?? null;

        if ($intent === 'link') {
            $this->assertLinkStateBoundToBrowser($payload, $linkBrowserNonce);
        }

        if (! class_exists(\Laravel\Socialite\Facades\Socialite::class)) {
            throw new \RuntimeException('Laravel Socialite is not installed. Run: composer require laravel/socialite');
        }

        /** @var \Laravel\Socialite\Two\User|\Laravel\Socialite\AbstractUser $providerUser */
        $providerUser = \Laravel\Socialite\Facades\Socialite::driver($provider)->stateless()->user();

        if ($intent === 'link' && $linkUserId) {
            $user = User::find($linkUserId);
            if (! $user) {
                throw new \RuntimeException('User not found for link intent.');
            }
            $this->linkExistingAccount(
                (int) $user->id,
                $provider,
                (string) $providerUser->getId(),
                $providerUser->getEmail(),
                method_exists($providerUser, 'getAvatar') ? $providerUser->getAvatar() : null,
                $this->extractRawPayload($providerUser)
            );
            return ['user' => $user, 'is_new' => false, 'tenant_id' => (int) $user->tenant_id];
        }

        $result = $this->findOrCreateFromOauth($provider, $providerUser, $tenantId);
        return $result;
    }

    /**
     * Find or create a user from a Socialite provider response.
     *
     * Lookup order:
     *  1. Existing oauth_identity by (provider, provider_user_id) → return its user
     *  2. Existing user by verified email match (in tenant) → link new identity
     *  3. Create a brand-new user + identity
     */
    public function findOrCreateFromOauth(string $provider, $providerUser, int $tenantId): array
    {
        $providerUserId = (string) $providerUser->getId();
        $providerEmail = $providerUser->getEmail();
        $name = method_exists($providerUser, 'getName') ? $providerUser->getName() : null;
        $avatar = method_exists($providerUser, 'getAvatar') ? $providerUser->getAvatar() : null;
        $rawPayload = $this->extractRawPayload($providerUser);

        // 1. Existing identity?
        $existing = DB::selectOne(
            'SELECT user_id FROM oauth_identities WHERE provider = ? AND provider_user_id = ? LIMIT 1',
            [$provider, $providerUserId]
        );
        if ($existing) {
            DB::update(
                'UPDATE oauth_identities SET last_used_at = NOW(), provider_email = ?, avatar_url = ?, raw_payload = ?, updated_at = NOW() WHERE provider = ? AND provider_user_id = ?',
                [$providerEmail, $avatar, json_encode($rawPayload), $provider, $providerUserId]
            );
            $user = User::find((int) $existing->user_id);
            if (! $user) {
                throw new \RuntimeException('Linked user not found.');
            }
            return ['user' => $user, 'is_new' => false, 'tenant_id' => (int) $user->tenant_id];
        }

        // 2. Email match within tenant? Email must be verified to auto-link.
        if ($providerEmail) {
            $emailMatch = DB::selectOne(
                'SELECT id, tenant_id, email_verified_at FROM users WHERE tenant_id = ? AND email = ? LIMIT 1',
                [$tenantId, $providerEmail]
            );
            if ($emailMatch && ! empty($emailMatch->email_verified_at)) {
                $this->insertIdentity(
                    (int) $emailMatch->id,
                    (int) $emailMatch->tenant_id,
                    $provider,
                    $providerUserId,
                    $providerEmail,
                    $avatar,
                    $rawPayload
                );
                $user = User::find((int) $emailMatch->id);
                return ['user' => $user, 'is_new' => false, 'tenant_id' => (int) $user->tenant_id];
            }
        }

        // 3. Create new user.
        if (! $providerEmail) {
            throw new \RuntimeException('OAuth provider did not return an email address. Cannot create account.');
        }

        $names = $this->splitName($name, $providerEmail);
        $userId = DB::table('users')->insertGetId([
            'tenant_id' => $tenantId,
            'first_name' => $names['first'],
            'last_name' => $names['last'],
            'email' => $providerEmail,
            'password' => password_hash(Str::random(48), PASSWORD_BCRYPT),
            'email_verified_at' => now(),
            'avatar_url' => $avatar,
            'preferred_language' => $this->detectLanguage(),
            'is_approved' => 1,
            'role' => 'member',
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        $this->insertIdentity(
            (int) $userId,
            $tenantId,
            $provider,
            $providerUserId,
            $providerEmail,
            $avatar,
            $rawPayload
        );

        $user = User::find((int) $userId);
        return ['user' => $user, 'is_new' => true, 'tenant_id' => $tenantId];
    }

    /**
     * Link a provider to an already-authenticated user.
     */
    public function linkExistingAccount(
        int $userId,
        string $provider,
        string $providerUserId,
        ?string $providerEmail,
        ?string $avatarUrl,
        array $rawPayload
    ): void {
        $this->assertProviderSupported($provider);

        // Reject if this provider identity is already attached to a different user.
        $existing = DB::selectOne(
            'SELECT user_id FROM oauth_identities WHERE provider = ? AND provider_user_id = ? LIMIT 1',
            [$provider, $providerUserId]
        );
        if ($existing && (int) $existing->user_id !== $userId) {
            throw new \RuntimeException('This account is already linked to another NEXUS user.');
        }

        $user = User::find($userId);
        if (! $user) {
            throw new \RuntimeException('User not found.');
        }

        $this->insertIdentity(
            $userId,
            (int) $user->tenant_id,
            $provider,
            $providerUserId,
            $providerEmail,
            $avatarUrl,
            $rawPayload
        );
    }

    /**
     * Unlink a provider from a user. Refuses to remove the user's last
     * remaining authentication method.
     */
    public function unlinkProvider(int $userId, string $provider): void
    {
        $this->assertProviderSupported($provider);

        $user = User::find($userId);
        if (! $user) {
            throw new \RuntimeException('User not found.');
        }

        $hasPassword = ! empty($user->password_hash) || ! empty($user->password);
        $hasPasskey = false;
        if (\Schema::hasTable('webauthn_credentials')) {
            $hasPasskey = DB::table('webauthn_credentials')
                ->where('user_id', $userId)
                ->exists();
        }

        $otherIdentitiesCount = DB::table('oauth_identities')
            ->where('user_id', $userId)
            ->where('provider', '!=', $provider)
            ->count();

        $remainingMethods = ($hasPassword ? 1 : 0) + ($hasPasskey ? 1 : 0) + $otherIdentitiesCount;
        if ($remainingMethods < 1) {
            throw new \RuntimeException(
                'Cannot unlink your only remaining sign-in method. Set a password or add a passkey first.'
            );
        }

        DB::table('oauth_identities')
            ->where('user_id', $userId)
            ->where('provider', $provider)
            ->delete();
    }

    /**
     * List all linked identities for the given user.
     */
    public function listIdentities(int $userId): array
    {
        $rows = DB::table('oauth_identities')
            ->where('user_id', $userId)
            ->orderBy('linked_at', 'desc')
            ->get(['provider', 'provider_email', 'avatar_url', 'linked_at', 'last_used_at']);

        return $rows->map(static fn ($r) => [
            'provider' => $r->provider,
            'provider_email' => $r->provider_email,
            'avatar_url' => $r->avatar_url,
            'linked_at' => $r->linked_at,
            'last_used_at' => $r->last_used_at,
        ])->all();
    }

    public function issueCallbackCode(string $token, string $provider, bool $isNew, int $tenantId): string
    {
        $code = Str::random(64);
        Cache::put($this->callbackCodeCacheKey($code), [
            'token' => $token,
            'provider' => $provider,
            'is_new' => $isNew,
            'tenant_id' => $tenantId,
        ], self::CALLBACK_CODE_TTL_SECONDS);

        return $code;
    }

    /**
     * @return array{token:string,provider:string,is_new:bool,tenant_id:int}
     */
    public function consumeCallbackCode(string $code): array
    {
        if (!preg_match('/^[A-Za-z0-9]{40,128}$/', $code)) {
            throw new \RuntimeException('OAuth callback code is invalid or expired.');
        }

        $payload = Cache::pull($this->callbackCodeCacheKey($code));
        if (!is_array($payload) || empty($payload['token']) || empty($payload['provider'])) {
            throw new \RuntimeException('OAuth callback code is invalid or expired.');
        }

        return [
            'token' => (string) $payload['token'],
            'provider' => (string) $payload['provider'],
            'is_new' => (bool) ($payload['is_new'] ?? false),
            'tenant_id' => (int) ($payload['tenant_id'] ?? 0),
        ];
    }

    /**
     * Get the list of OAuth providers enabled for a tenant.
     *
     * 🔴 GLOBAL KILL SWITCH: when env OAUTH_ENABLED=false (default), no providers
     * are returned regardless of tenant setting. Set OAUTH_ENABLED=true in .env
     * once real Google/Apple/Facebook OAuth credentials are configured.
     *
     * Otherwise: per-tenant setting `auth.oauth.enabled_providers` (defaults to all 3).
     */
    public function enabledProviders(int $tenantId): array
    {
        // Global kill switch — ON only when explicitly enabled in env
        if (! filter_var(env('OAUTH_ENABLED', false), FILTER_VALIDATE_BOOLEAN)) {
            return [];
        }
        $raw = $this->tenantSettings->get($tenantId, 'auth.oauth.enabled_providers');
        if ($raw === null || $raw === '') {
            return self::SUPPORTED_PROVIDERS;
        }
        $decoded = is_array($raw) ? $raw : json_decode((string) $raw, true);
        if (! is_array($decoded)) {
            return self::SUPPORTED_PROVIDERS;
        }
        return array_values(array_intersect($decoded, self::SUPPORTED_PROVIDERS));
    }

    // ---------------------------------------------------------------- helpers

    private function assertProviderSupported(string $provider): void
    {
        if (! in_array($provider, self::SUPPORTED_PROVIDERS, true)) {
            throw new \InvalidArgumentException("Unsupported OAuth provider: {$provider}");
        }
    }

    private function assertProviderEnabledForTenant(string $provider, int $tenantId): void
    {
        $enabled = $this->enabledProviders($tenantId);
        if (! in_array($provider, $enabled, true)) {
            throw new \RuntimeException("OAuth provider '{$provider}' is disabled for this community.");
        }
    }

    private function buildState(int $tenantId, string $intent, ?int $userId, ?string $linkBrowserNonce = null): string
    {
        $payload = [
            't' => $tenantId,
            'i' => $intent,
            'u' => $userId,
            'n' => Str::random(16),
            'x' => now()->timestamp,
        ];

        if ($intent === 'link') {
            if (!$userId) {
                throw new \RuntimeException('OAuth link intent requires a user.');
            }

            $linkBrowserNonce ??= Str::random(40);
            Cache::put($this->linkStateCacheKey($payload['n']), [
                'tenant_id' => $tenantId,
                'user_id' => $userId,
                'browser_nonce_hash' => hash('sha256', $linkBrowserNonce),
            ], self::STATE_TTL_SECONDS);
        }

        $body = base64_encode(json_encode($payload));
        $sig = hash_hmac('sha256', $body, (string) config('app.key'));
        return $body . '.' . $sig;
    }

    /**
     * @return array{tenant_id:int, intent:string, user_id:?int, state_nonce:string}
     */
    private function verifyState(string $state): array
    {
        if (! str_contains($state, '.')) {
            throw new \RuntimeException('Invalid OAuth state token.');
        }
        [$body, $sig] = explode('.', $state, 2);
        $expected = hash_hmac('sha256', $body, (string) config('app.key'));
        if (! hash_equals($expected, $sig)) {
            throw new \RuntimeException('OAuth state signature mismatch.');
        }
        $decoded = json_decode((string) base64_decode($body, true), true);
        if (! is_array($decoded) || empty($decoded['t']) || empty($decoded['i'])) {
            throw new \RuntimeException('Malformed OAuth state token.');
        }
        // Reject states older than 1 hour.
        if (! empty($decoded['x']) && (now()->timestamp - (int) $decoded['x']) > 3600) {
            throw new \RuntimeException('OAuth state token has expired.');
        }
        return [
            'tenant_id' => (int) $decoded['t'],
            'intent' => (string) $decoded['i'],
            'user_id' => isset($decoded['u']) ? (int) $decoded['u'] : null,
            'state_nonce' => (string) ($decoded['n'] ?? ''),
        ];
    }

    /**
     * @param array{tenant_id:int,intent:string,user_id:?int,state_nonce:string} $payload
     */
    private function assertLinkStateBoundToBrowser(array $payload, ?string $linkBrowserNonce): void
    {
        if (($payload['intent'] ?? '') !== 'link'
            || empty($payload['user_id'])
            || empty($payload['state_nonce'])
            || empty($linkBrowserNonce)
        ) {
            throw new \RuntimeException('OAuth link state is not bound to this browser session.');
        }

        $cacheKey = $this->linkStateCacheKey($payload['state_nonce']);
        $binding = Cache::get($cacheKey);
        if (!is_array($binding)
            || (int) ($binding['tenant_id'] ?? 0) !== (int) $payload['tenant_id']
            || (int) ($binding['user_id'] ?? 0) !== (int) $payload['user_id']
            || !hash_equals((string) ($binding['browser_nonce_hash'] ?? ''), hash('sha256', $linkBrowserNonce))
        ) {
            throw new \RuntimeException('OAuth link state is not bound to this browser session.');
        }

        Cache::forget($cacheKey);
    }

    private function callbackCodeCacheKey(string $code): string
    {
        return 'oauth:callback-code:' . hash('sha256', $code);
    }

    private function linkStateCacheKey(string $stateNonce): string
    {
        return 'oauth:link-state:' . hash('sha256', $stateNonce);
    }

    private function insertIdentity(
        int $userId,
        int $tenantId,
        string $provider,
        string $providerUserId,
        ?string $providerEmail,
        ?string $avatarUrl,
        array $rawPayload
    ): void {
        DB::statement(
            'INSERT INTO oauth_identities
                (user_id, tenant_id, provider, provider_user_id, provider_email, avatar_url, raw_payload, linked_at, last_used_at, created_at, updated_at)
             VALUES (?, ?, ?, ?, ?, ?, ?, NOW(), NOW(), NOW(), NOW())
             ON DUPLICATE KEY UPDATE
                provider_email = VALUES(provider_email),
                avatar_url = VALUES(avatar_url),
                raw_payload = VALUES(raw_payload),
                last_used_at = NOW(),
                updated_at = NOW()',
            [
                $userId,
                $tenantId,
                $provider,
                $providerUserId,
                $providerEmail,
                $avatarUrl,
                json_encode($rawPayload),
            ]
        );
    }

    /**
     * Pull the raw provider payload, redacting any access/refresh tokens.
     */
    private function extractRawPayload($providerUser): array
    {
        $raw = method_exists($providerUser, 'getRaw') ? $providerUser->getRaw() : [];
        if (! is_array($raw)) {
            $raw = [];
        }
        unset(
            $raw['access_token'],
            $raw['refresh_token'],
            $raw['id_token'],
            $raw['token'],
            $raw['client_secret']
        );
        return $raw;
    }

    private function splitName(?string $name, string $email): array
    {
        $first = $last = '';
        if ($name) {
            $parts = preg_split('/\s+/', trim($name), 2);
            $first = $parts[0] ?? '';
            $last = $parts[1] ?? '';
        }
        if ($first === '') {
            $local = strstr($email, '@', true) ?: $email;
            $first = ucfirst($local);
        }
        return ['first' => $first, 'last' => $last];
    }

    private function detectLanguage(): string
    {
        $supported = ['en', 'ga', 'de', 'fr', 'it', 'pt', 'es', 'nl', 'pl', 'ja', 'ar'];
        $header = request()->header('Accept-Language', '');
        if ($header) {
            foreach (explode(',', (string) $header) as $part) {
                $code = strtolower(substr(trim($part), 0, 2));
                if (in_array($code, $supported, true)) {
                    return $code;
                }
            }
        }
        return 'en';
    }
}
