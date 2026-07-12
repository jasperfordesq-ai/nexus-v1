<?php
// Copyright © 2024–2026 Jasper Ford
// SPDX-License-Identifier: AGPL-3.0-or-later
// Author: Jasper Ford
// See NOTICE file for attribution and acknowledgements.

declare(strict_types=1);

namespace App\Services;

use App\Exceptions\EventOfflineCheckinException;
use Carbon\CarbonImmutable;
use JsonException;

/**
 * Ed25519 signing boundary for PII-free attendee credentials.
 *
 * Tokens carry event/occurrence/version/time claims only. Registration and
 * member identity remain in the encrypted offline manifest and server ledger.
 */
final class EventCheckinCredentialSigner
{
    private const PREFIX = 'nqx2_';
    private const AUDIENCE = 'event-checkin';

    /**
     * @return array{token:string,kid:string,claims:array<string,int|string>}
     */
    public function issue(
        int $tenantId,
        int $eventId,
        string $occurrenceKey,
        int $credentialVersion,
        CarbonImmutable $issuedAt,
        CarbonImmutable $expiresAt,
    ): array {
        if ($tenantId <= 0 || $eventId <= 0 || $credentialVersion <= 0
            || trim($occurrenceKey) === '' || ! $expiresAt->isAfter($issuedAt)) {
            throw new EventOfflineCheckinException('event_qr_credential_claims_invalid');
        }

        $key = $this->activeKey();
        $claims = [
            'alg' => 'Ed25519',
            'aud' => self::AUDIENCE,
            'evt' => $eventId,
            'exp' => $expiresAt->getTimestamp(),
            'iat' => $issuedAt->getTimestamp(),
            'jti' => $this->base64UrlEncode(random_bytes(16)),
            'kid' => $key['kid'],
            'occ' => hash('sha256', trim($occurrenceKey)),
            'ten' => $tenantId,
            'v' => 2,
            'ver' => $credentialVersion,
        ];

        try {
            $encodedClaims = $this->base64UrlEncode(json_encode(
                $claims,
                JSON_THROW_ON_ERROR | JSON_UNESCAPED_SLASHES,
            ));
        } catch (JsonException) {
            throw new EventOfflineCheckinException('event_qr_credential_claims_invalid');
        }
        $signature = sodium_crypto_sign_detached($encodedClaims, $key['secret']);
        $token = self::PREFIX . $encodedClaims . '.' . $this->base64UrlEncode($signature);

        return ['token' => $token, 'kid' => $key['kid'], 'claims' => $claims];
    }

    /**
     * @return array{alg:string,aud:string,evt:int,exp:int,iat:int,jti:string,kid:string,occ:string,ten:int,v:int,ver:int}
     */
    public function verify(
        string $token,
        int $tenantId,
        int $eventId,
        string $occurrenceKey,
        ?CarbonImmutable $now = null,
    ): array {
        $token = trim($token);
        if (! str_starts_with($token, self::PREFIX) || strlen($token) > 1024) {
            throw new EventOfflineCheckinException('event_qr_credential_invalid');
        }
        $parts = explode('.', substr($token, strlen(self::PREFIX)));
        if (count($parts) !== 2 || $parts[0] === '' || $parts[1] === '') {
            throw new EventOfflineCheckinException('event_qr_credential_invalid');
        }

        try {
            $claims = json_decode(
                $this->base64UrlDecode($parts[0]),
                true,
                16,
                JSON_THROW_ON_ERROR,
            );
        } catch (JsonException) {
            throw new EventOfflineCheckinException('event_qr_credential_invalid');
        }
        if (! is_array($claims)) {
            throw new EventOfflineCheckinException('event_qr_credential_invalid');
        }

        $requiredStrings = ['alg', 'aud', 'jti', 'kid', 'occ'];
        $requiredIntegers = ['evt', 'exp', 'iat', 'ten', 'v', 'ver'];
        foreach ($requiredStrings as $claim) {
            if (! is_string($claims[$claim] ?? null) || trim($claims[$claim]) === '') {
                throw new EventOfflineCheckinException('event_qr_credential_invalid');
            }
        }
        foreach ($requiredIntegers as $claim) {
            if (! is_int($claims[$claim] ?? null)) {
                throw new EventOfflineCheckinException('event_qr_credential_invalid');
            }
        }
        if (count($claims) !== count($requiredStrings) + count($requiredIntegers)
            || $claims['alg'] !== 'Ed25519'
            || $claims['aud'] !== self::AUDIENCE
            || $claims['v'] !== 2
            || $claims['ten'] !== $tenantId
            || $claims['evt'] !== $eventId
            || $claims['ver'] <= 0
            || preg_match('/^[0-9a-f]{64}$/D', $claims['occ']) !== 1
            || ! hash_equals(hash('sha256', trim($occurrenceKey)), $claims['occ'])
            || preg_match('/^[0-9a-f]{16}$/D', $claims['kid']) !== 1) {
            throw new EventOfflineCheckinException('event_qr_credential_invalid');
        }

        $keys = $this->verificationKeys();
        $publicKey = $keys[$claims['kid']] ?? null;
        if (! is_string($publicKey)) {
            throw new EventOfflineCheckinException('event_qr_credential_signing_key_unknown');
        }
        $signature = $this->base64UrlDecode($parts[1]);
        if (strlen($signature) !== SODIUM_CRYPTO_SIGN_BYTES
            || ! sodium_crypto_sign_verify_detached($signature, $parts[0], $publicKey)) {
            throw new EventOfflineCheckinException('event_qr_credential_signature_invalid');
        }

        $now ??= CarbonImmutable::now('UTC');
        $skew = max(0, min(
            300,
            (int) config('event_checkin.signature_clock_skew_seconds', 60),
        ));
        if ($claims['iat'] > $now->addSeconds($skew)->getTimestamp()
            || $claims['exp'] <= $now->getTimestamp()
            || $claims['exp'] <= $claims['iat']) {
            throw new EventOfflineCheckinException('event_qr_credential_expired');
        }

        /** @var array{alg:string,aud:string,evt:int,exp:int,iat:int,jti:string,kid:string,occ:string,ten:int,v:int,ver:int} $claims */
        return $claims;
    }

    /**
     * Public-only key set safe to place in an encrypted offline manifest.
     *
     * @return list<array{kid:string,alg:string,public_key:string}>
     */
    public function publicKeySet(): array
    {
        $keys = [];
        foreach ($this->verificationKeys() as $kid => $publicKey) {
            $keys[] = [
                'kid' => $kid,
                'alg' => 'Ed25519',
                'public_key' => $this->base64UrlEncode($publicKey),
            ];
        }

        return $keys;
    }

    /** @return array{kid:string,secret:string,public:string} */
    private function activeKey(): array
    {
        if (! function_exists('sodium_crypto_sign_seed_keypair')) {
            throw new EventOfflineCheckinException('event_qr_credential_signing_unavailable');
        }
        $configured = trim((string) config('event_checkin.signing_seed', ''));
        $seed = $configured !== ''
            ? $this->decodeConfiguredKey($configured, SODIUM_CRYPTO_SIGN_SEEDBYTES)
            : hash_hkdf(
                'sha256',
                $this->applicationKeyMaterial(),
                SODIUM_CRYPTO_SIGN_SEEDBYTES,
                'project-nexus:event-checkin:ed25519:v2',
            );
        if (strlen($seed) !== SODIUM_CRYPTO_SIGN_SEEDBYTES) {
            throw new EventOfflineCheckinException('event_qr_credential_signing_key_invalid');
        }

        $pair = sodium_crypto_sign_seed_keypair($seed);
        $secret = sodium_crypto_sign_secretkey($pair);
        $public = sodium_crypto_sign_publickey($pair);

        return [
            'kid' => substr(hash('sha256', $public), 0, 16),
            'secret' => $secret,
            'public' => $public,
        ];
    }

    /** @return array<string,string> */
    private function verificationKeys(): array
    {
        $active = $this->activeKey();
        $keys = [$active['kid'] => $active['public']];
        $configured = trim((string) config('event_checkin.verification_keys_json', '{}'));
        if ($configured === '' || $configured === '{}') {
            return $keys;
        }
        try {
            $decoded = json_decode($configured, true, 16, JSON_THROW_ON_ERROR);
        } catch (JsonException) {
            throw new EventOfflineCheckinException('event_qr_credential_verification_keys_invalid');
        }
        if (! is_array($decoded)) {
            throw new EventOfflineCheckinException('event_qr_credential_verification_keys_invalid');
        }
        foreach ($decoded as $kid => $encoded) {
            if (! is_string($kid) || preg_match('/^[0-9a-f]{16}$/D', $kid) !== 1
                || ! is_string($encoded)) {
                throw new EventOfflineCheckinException('event_qr_credential_verification_keys_invalid');
            }
            $public = $this->decodeConfiguredKey($encoded, SODIUM_CRYPTO_SIGN_PUBLICKEYBYTES);
            if (! hash_equals($kid, substr(hash('sha256', $public), 0, 16))) {
                throw new EventOfflineCheckinException('event_qr_credential_verification_keys_invalid');
            }
            $keys[$kid] = $public;
        }

        return $keys;
    }

    private function applicationKeyMaterial(): string
    {
        $key = trim((string) config('app.key', ''));
        if (str_starts_with($key, 'base64:')) {
            $decoded = base64_decode(substr($key, 7), true);
            if (is_string($decoded) && $decoded !== '') {
                return $decoded;
            }
        }
        if ($key === '') {
            throw new EventOfflineCheckinException('event_qr_credential_signing_key_missing');
        }

        return $key;
    }

    private function decodeConfiguredKey(string $encoded, int $expectedLength): string
    {
        $encoded = trim($encoded);
        if (str_starts_with($encoded, 'base64:')) {
            $encoded = substr($encoded, 7);
        }
        $encoded = rtrim($encoded, '=');
        if ($encoded === '' || preg_match('/^[A-Za-z0-9+\/_-]+$/D', $encoded) !== 1) {
            throw new EventOfflineCheckinException('event_qr_credential_signing_key_invalid');
        }
        $padding = (4 - strlen($encoded) % 4) % 4;
        $decoded = base64_decode(
            strtr($encoded . str_repeat('=', $padding), '-_', '+/'),
            true,
        );
        if (! is_string($decoded) || strlen($decoded) !== $expectedLength) {
            throw new EventOfflineCheckinException('event_qr_credential_signing_key_invalid');
        }

        return $decoded;
    }

    private function base64UrlEncode(string $value): string
    {
        return rtrim(strtr(base64_encode($value), '+/', '-_'), '=');
    }

    private function base64UrlDecode(string $value): string
    {
        if ($value === '' || preg_match('/^[A-Za-z0-9_-]+$/D', $value) !== 1) {
            throw new EventOfflineCheckinException('event_qr_credential_invalid');
        }
        $padding = (4 - strlen($value) % 4) % 4;
        $decoded = base64_decode(strtr($value . str_repeat('=', $padding), '-_', '+/'), true);
        if (! is_string($decoded)) {
            throw new EventOfflineCheckinException('event_qr_credential_invalid');
        }

        return $decoded;
    }
}
