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
        'content',
        'description',
        'location',
        'message',
        'message_body',
        'oauth_client_secret',
        'password',
        'refresh_token',
        'secret',
        'signing_secret',
        'token',
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
        array_walk_recursive($payload, static function (&$value, $key): void {
            if (!is_string($key)) {
                return;
            }

            if (in_array(strtolower($key), self::SENSITIVE_KEYS, true) && self::hasValue($value)) {
                $value = self::REDACTED;
            }
        });

        return $payload;
    }

    public static function redactText(?string $text): ?string
    {
        if ($text === null || $text === '') {
            return $text;
        }

        $patterns = [
            '/(Bearer\s+)[A-Za-z0-9._~+\/=-]+/i',
            '/((?:api[_-]?key|token|access[_-]?token|refresh[_-]?token|secret|password)\s*[:=]\s*)[^\s,;]+/i',
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
