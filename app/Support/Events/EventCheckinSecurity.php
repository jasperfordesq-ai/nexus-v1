<?php
// Copyright © 2024–2026 Jasper Ford
// SPDX-License-Identifier: AGPL-3.0-or-later
// Author: Jasper Ford
// See NOTICE file for attribution and acknowledgements.

declare(strict_types=1);

namespace App\Support\Events;

use App\Exceptions\EventOfflineCheckinException;

/** Small, auditable primitives for random bearer credentials and one-way verifiers. */
final class EventCheckinSecurity
{
    private const SECRET_RANDOM_BYTES = 32;

    public static function generateSecret(string $prefix): string
    {
        if (! in_array($prefix, ['nqx1_', 'nxd1_', 'nxc1_'], true)) {
            throw new EventOfflineCheckinException('event_checkin_secret_prefix_invalid');
        }

        return $prefix . rtrim(strtr(
            base64_encode(random_bytes(self::SECRET_RANDOM_BYTES)),
            '+/',
            '-_',
        ), '=');
    }

    /** @return array{hash:string,fingerprint:string} */
    public static function verifier(string $secret, string $expectedPrefix): array
    {
        $secret = trim($secret);
        if (! str_starts_with($secret, $expectedPrefix)
            || strlen($secret) !== strlen($expectedPrefix) + 43
            || preg_match('/^[A-Za-z0-9_-]+$/D', substr($secret, strlen($expectedPrefix))) !== 1) {
            throw new EventOfflineCheckinException('event_checkin_secret_invalid');
        }

        $hash = hash('sha256', $secret);

        return ['hash' => $hash, 'fingerprint' => substr($hash, 0, 16)];
    }

    /**
     * One-way reference for a credential whose signature has already been
     * verified by EventCheckinCredentialSigner. Legacy nqx1 tokens remain
     * readable during the bounded migration window; all new issuance is nqx2.
     *
     * @return array{hash:string,fingerprint:string}
     */
    public static function credentialVerifier(string $credential): array
    {
        $credential = trim($credential);
        $legacy = str_starts_with($credential, 'nqx1_')
            && strlen($credential) === 48
            && preg_match('/^[A-Za-z0-9_-]+$/D', substr($credential, 5)) === 1;
        $signed = str_starts_with($credential, 'nqx2_')
            && strlen($credential) <= 1024
            && preg_match('/^[A-Za-z0-9_-]+\.[A-Za-z0-9_-]+$/D', substr($credential, 5)) === 1;
        if (! $legacy && ! $signed) {
            throw new EventOfflineCheckinException('event_qr_credential_invalid');
        }

        $hash = hash('sha256', $credential);

        return ['hash' => $hash, 'fingerprint' => substr($hash, 0, 16)];
    }

    public static function idempotencyHash(string $key): string
    {
        $key = trim($key);
        if (strlen($key) < 8 || strlen($key) > 191
            || preg_match('/[\x00-\x1F\x7F]/', $key) === 1) {
            throw new EventOfflineCheckinException('event_checkin_idempotency_key_invalid');
        }

        return hash('sha256', $key);
    }

    public static function hashReference(string $hash, string $fingerprint): string
    {
        $hash = strtolower(trim($hash));
        $fingerprint = strtolower(trim($fingerprint));
        if (preg_match('/^[0-9a-f]{64}$/D', $hash) !== 1
            || preg_match('/^[0-9a-f]{16}$/D', $fingerprint) !== 1
            || ! hash_equals(substr($hash, 0, 16), $fingerprint)) {
            throw new EventOfflineCheckinException('event_checkin_credential_reference_invalid');
        }

        return $hash;
    }

    public static function matches(string $storedHash, string $candidateHash): bool
    {
        return strlen($storedHash) === 64
            && strlen($candidateHash) === 64
            && hash_equals($storedHash, $candidateHash);
    }

    public static function sanitizedText(?string $value, int $maximum, bool $required): ?string
    {
        if ($value === null) {
            if ($required) {
                throw new EventOfflineCheckinException('event_checkin_text_required');
            }

            return null;
        }

        $clean = strip_tags($value);
        $clean = preg_replace('/[\x00-\x1F\x7F]/u', ' ', $clean) ?? '';
        $clean = preg_replace('/\s+/u', ' ', $clean) ?? '';
        $clean = trim($clean);
        if ($clean === '') {
            if ($required) {
                throw new EventOfflineCheckinException('event_checkin_text_required');
            }

            return null;
        }
        if (mb_strlen($clean) > $maximum) {
            throw new EventOfflineCheckinException('event_checkin_text_too_long');
        }

        return $clean;
    }
}
