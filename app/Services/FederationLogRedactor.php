<?php
// Copyright © 2024–2026 Jasper Ford
// SPDX-License-Identifier: AGPL-3.0-or-later
// Author: Jasper Ford
// See NOTICE file for attribution and acknowledgements.

declare(strict_types=1);

namespace App\Services;

/**
 * Redacts sensitive federation payload fields before partner logs are stored.
 */
class FederationLogRedactor
{
    private const REDACTED = '[REDACTED]';

    private const SENSITIVE_KEYS = [
        'access_token',
        'api_key',
        'authorization',
        'avatar_url',
        'bio',
        'body',
        'client_secret',
        'content',
        'credential',
        'description',
        'location',
        'message',
        'message_body',
        'oauth_client_secret',
        'password',
        'private_key',
        'refresh_token',
        'secret',
        'signing_secret',
        'token',
    ];

    /**
     * Substring fragments that mark a key as sensitive regardless of prefix —
     * so newly-introduced fields like `partner_client_secret` or `webhook_token`
     * are redacted without needing an exact allow-list entry.
     */
    private const SENSITIVE_KEY_FRAGMENTS = [
        'secret',
        'password',
        'token',
        'private_key',
    ];

    public static function redactJsonString(?string $json): ?string
    {
        if ($json === null || $json === '') {
            return $json;
        }

        $data = json_decode($json, true);
        if (!is_array($data)) {
            return self::redactText($json);
        }

        $redacted = self::redactArray($data);

        return json_encode($redacted, JSON_UNESCAPED_SLASHES) ?: self::redactText($json);
    }

    /**
     * @param array<string|int,mixed> $payload
     * @return array<string|int,mixed>
     */
    public static function redactArray(array $payload): array
    {
        foreach ($payload as $key => $value) {
            // A sensitive key redacts its ENTIRE value — scalar OR nested
            // object/array. array_walk_recursive only reached scalar leaves, so
            // a secret nested under a sensitive key (e.g. {"credential": {...}})
            // previously leaked in full.
            if (is_string($key) && self::isSensitiveKey($key)) {
                if (self::hasValue($value)) {
                    $payload[$key] = self::REDACTED;
                }
                continue;
            }

            if (is_array($value)) {
                $payload[$key] = self::redactArray($value);
            }
        }

        return $payload;
    }

    private static function isSensitiveKey(string $key): bool
    {
        $lower = strtolower($key);

        if (in_array($lower, self::SENSITIVE_KEYS, true)) {
            return true;
        }

        foreach (self::SENSITIVE_KEY_FRAGMENTS as $fragment) {
            if (str_contains($lower, $fragment)) {
                return true;
            }
        }

        return false;
    }

    public static function redactText(?string $text): ?string
    {
        if ($text === null || $text === '') {
            return $text;
        }

        $patterns = [
            '/(Bearer\s+)[A-Za-z0-9._~+\/=-]+/i',
            '/((?:api[_-]?key|token|access[_-]?token|refresh[_-]?token|client[_-]?secret|private[_-]?key|secret|password|credential)\s*[:=]\s*)[^\s,;]+/i',
        ];

        return (string) preg_replace($patterns, '$1' . self::REDACTED, $text);
    }

    private static function hasValue(mixed $value): bool
    {
        if (is_string($value)) {
            return $value !== '';
        }

        return $value !== null;
    }
}
