<?php
// Copyright © 2024–2026 Jasper Ford
// SPDX-License-Identifier: AGPL-3.0-or-later
// Author: Jasper Ford
// See NOTICE file for attribution and acknowledgements.

declare(strict_types=1);

namespace App\Services;

use App\Exceptions\EventRecurrenceRevisionException;
use JsonException;

/** Authenticated, confidential, short-lived recurrence revision preview token. */
final class EventRecurrenceRevisionTokenService
{
    private const CIPHER = 'aes-256-gcm';
    private const TOKEN_VERSION = 'errp1';
    private const NONCE_BYTES = 12;
    private const TAG_BYTES = 16;

    /** @param array<string,mixed> $claims */
    public function issue(array $claims): string
    {
        [$keyVersion, $key] = $this->activeKey();
        $now = now()->getTimestamp();
        $claims['token_version'] = 1;
        $claims['issued_at'] = $now;
        $claims['expires_at'] = $now + $this->ttlSeconds();
        $claims['nonce'] = bin2hex(random_bytes(16));

        try {
            $plaintext = json_encode(
                $claims,
                JSON_THROW_ON_ERROR | JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE,
            );
        } catch (JsonException) {
            throw new EventRecurrenceRevisionException('event_recurrence_revision_token_invalid');
        }

        if (! function_exists('openssl_encrypt')) {
            throw new EventRecurrenceRevisionException('event_recurrence_revision_token_key_unavailable');
        }
        $nonce = random_bytes(self::NONCE_BYTES);
        $tag = '';
        $ciphertext = openssl_encrypt(
            $plaintext,
            self::CIPHER,
            $key,
            OPENSSL_RAW_DATA,
            $nonce,
            $tag,
            $this->aad($keyVersion),
            self::TAG_BYTES,
        );
        if (! is_string($ciphertext) || strlen($tag) !== self::TAG_BYTES) {
            throw new EventRecurrenceRevisionException('event_recurrence_revision_token_key_unavailable');
        }

        return implode('.', [
            self::TOKEN_VERSION,
            $this->base64UrlEncode($keyVersion),
            $this->base64UrlEncode($nonce . $tag . $ciphertext),
        ]);
    }

    /**
     * @return array<string,mixed>
     */
    public function decode(string $token, bool $enforceExpiry = true): array
    {
        if ($token === '' || mb_strlen($token) > 8192) {
            throw new EventRecurrenceRevisionException('event_recurrence_revision_token_invalid');
        }
        $parts = explode('.', $token);
        if (count($parts) !== 3 || $parts[0] !== self::TOKEN_VERSION) {
            throw new EventRecurrenceRevisionException('event_recurrence_revision_token_invalid');
        }
        $keyVersion = $this->base64UrlDecode($parts[1]);
        $envelope = $this->base64UrlDecode($parts[2]);
        if ($keyVersion === null
            || $keyVersion === ''
            || mb_strlen($keyVersion) > 64
            || $envelope === null
            || strlen($envelope) <= self::NONCE_BYTES + self::TAG_BYTES) {
            throw new EventRecurrenceRevisionException('event_recurrence_revision_token_invalid');
        }
        if (! function_exists('openssl_decrypt')) {
            throw new EventRecurrenceRevisionException('event_recurrence_revision_token_key_unavailable');
        }

        $key = $this->keyForVersion($keyVersion);
        $nonce = substr($envelope, 0, self::NONCE_BYTES);
        $tag = substr($envelope, self::NONCE_BYTES, self::TAG_BYTES);
        $ciphertext = substr($envelope, self::NONCE_BYTES + self::TAG_BYTES);
        $plaintext = openssl_decrypt(
            $ciphertext,
            self::CIPHER,
            $key,
            OPENSSL_RAW_DATA,
            $nonce,
            $tag,
            $this->aad($keyVersion),
        );
        if (! is_string($plaintext)) {
            throw new EventRecurrenceRevisionException('event_recurrence_revision_token_invalid');
        }

        try {
            $claims = json_decode($plaintext, true, 64, JSON_THROW_ON_ERROR);
        } catch (JsonException) {
            throw new EventRecurrenceRevisionException('event_recurrence_revision_token_invalid');
        }
        if (! is_array($claims)
            || (int) ($claims['token_version'] ?? 0) !== 1
            || ! is_int($claims['issued_at'] ?? null)
            || ! is_int($claims['expires_at'] ?? null)
            || ! is_string($claims['nonce'] ?? null)
            || (int) $claims['expires_at'] <= (int) $claims['issued_at']) {
            throw new EventRecurrenceRevisionException('event_recurrence_revision_token_invalid');
        }
        if ($enforceExpiry && now()->getTimestamp() > (int) $claims['expires_at']) {
            throw new EventRecurrenceRevisionException('event_recurrence_revision_token_expired');
        }

        return $claims;
    }

    /** @return array{string,string} */
    private function activeKey(): array
    {
        $version = trim((string) config(
            'events.recurrence.revisions.preview_envelope.active_key_version',
            'app-key-v1',
        ));
        if ($version === '' || mb_strlen($version) > 64) {
            throw new EventRecurrenceRevisionException('event_recurrence_revision_token_key_unavailable');
        }

        return [$version, $this->keyForVersion($version)];
    }

    private function keyForVersion(string $version): string
    {
        $active = trim((string) config(
            'events.recurrence.revisions.preview_envelope.active_key_version',
            'app-key-v1',
        ));
        $material = null;
        if ($version === $active) {
            $configured = config('events.recurrence.revisions.preview_envelope.active_key');
            if (is_string($configured) && trim($configured) !== '') {
                $material = trim($configured);
            } elseif ((bool) config(
                'events.recurrence.revisions.preview_envelope.fallback_to_app_key',
                true,
            )) {
                $appKey = config('app.key');
                $material = is_string($appKey) ? trim($appKey) : null;
            }
        } else {
            $previous = config(
                'events.recurrence.revisions.preview_envelope.previous_keys',
                [],
            );
            $candidate = is_array($previous) ? ($previous[$version] ?? null) : null;
            $material = is_string($candidate) ? trim($candidate) : null;
        }

        $key = $this->normalizeKey($material);
        if ($key === null) {
            throw new EventRecurrenceRevisionException('event_recurrence_revision_token_key_unavailable');
        }

        return $key;
    }

    private function normalizeKey(?string $material): ?string
    {
        if ($material === null || $material === '') {
            return null;
        }
        if (str_starts_with($material, 'base64:')) {
            $decoded = base64_decode(substr($material, 7), true);
            return is_string($decoded) && strlen($decoded) === 32 ? $decoded : null;
        }
        $decoded = base64_decode($material, true);
        if (is_string($decoded) && strlen($decoded) === 32) {
            return $decoded;
        }

        return strlen($material) === 32 ? $material : null;
    }

    private function ttlSeconds(): int
    {
        return max(60, min((int) config(
            'events.recurrence.revisions.preview_ttl_seconds',
            600,
        ), 3600));
    }

    private function aad(string $keyVersion): string
    {
        return self::TOKEN_VERSION . '|' . $keyVersion;
    }

    private function base64UrlEncode(string $value): string
    {
        return rtrim(strtr(base64_encode($value), '+/', '-_'), '=');
    }

    private function base64UrlDecode(string $value): ?string
    {
        if ($value === '' || preg_match('/^[A-Za-z0-9_-]+$/D', $value) !== 1) {
            return null;
        }
        $padding = (4 - (strlen($value) % 4)) % 4;
        $decoded = base64_decode(strtr($value, '-_', '+/') . str_repeat('=', $padding), true);

        return is_string($decoded) ? $decoded : null;
    }
}
