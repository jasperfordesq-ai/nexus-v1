<?php
// Copyright © 2024–2026 Jasper Ford
// SPDX-License-Identifier: AGPL-3.0-or-later
// Author: Jasper Ford
// See NOTICE file for attribution and acknowledgements.

declare(strict_types=1);

namespace App\Support\Events;

use Illuminate\Validation\ValidationException;
use JsonException;
use LogicException;

/**
 * Authenticated, URL-safe cursor codec for Events discovery endpoints.
 *
 * The signed payload binds a cursor to its tenant/filter/sort identity. This
 * prevents clients from changing a filter between pages or manufacturing a
 * position that skips arbitrary rows.
 */
final class EventDiscoveryCursor
{
    private const VERSION = 1;

    private const MAX_LENGTH = 4096;

    /**
     * @param array<string, int|float|string> $position
     */
    public static function encode(
        string $kind,
        string $queryIdentity,
        string $snapshotAt,
        array $position
    ): string {
        try {
            $json = json_encode([
                'v' => self::VERSION,
                'k' => $kind,
                'q' => $queryIdentity,
                'at' => $snapshotAt,
                'p' => $position,
            ], JSON_THROW_ON_ERROR | JSON_UNESCAPED_SLASHES);
        } catch (JsonException $exception) {
            throw new LogicException('Unable to encode the Events discovery cursor.', 0, $exception);
        }

        $body = self::base64UrlEncode($json);
        $signature = hash_hmac('sha256', $body, self::signingKey(), true);

        return $body . '.' . self::base64UrlEncode($signature);
    }

    /**
     * @return array{at: string, p: array<string, mixed>}
     *
     * @throws ValidationException
     */
    public static function decode(string $cursor, string $kind, string $queryIdentity): array
    {
        if ($cursor === '' || strlen($cursor) > self::MAX_LENGTH) {
            self::reject();
        }

        $segments = explode('.', $cursor);
        if (count($segments) !== 2 || $segments[0] === '' || $segments[1] === '') {
            self::reject();
        }

        [$body, $encodedSignature] = $segments;
        $signature = self::base64UrlDecode($encodedSignature);
        if ($signature === false) {
            self::reject();
        }

        $expectedSignature = hash_hmac('sha256', $body, self::signingKey(), true);
        if (!hash_equals($expectedSignature, $signature)) {
            self::reject();
        }

        $json = self::base64UrlDecode($body);
        if ($json === false) {
            self::reject();
        }

        try {
            $payload = json_decode($json, true, 16, JSON_THROW_ON_ERROR);
        } catch (JsonException) {
            self::reject();
        }

        if (
            !is_array($payload)
            || ($payload['v'] ?? null) !== self::VERSION
            || !is_string($payload['k'] ?? null)
            || !hash_equals($kind, $payload['k'])
            || !is_string($payload['q'] ?? null)
            || !hash_equals($queryIdentity, $payload['q'])
            || !is_string($payload['at'] ?? null)
            || !is_array($payload['p'] ?? null)
        ) {
            self::reject();
        }

        return [
            'at' => $payload['at'],
            'p' => $payload['p'],
        ];
    }

    private static function signingKey(): string
    {
        $configured = (string) config('app.key', '');
        if ($configured === '') {
            throw new LogicException('APP_KEY is required to sign Events discovery cursors.');
        }

        if (str_starts_with($configured, 'base64:')) {
            $decoded = base64_decode(substr($configured, 7), true);
            if ($decoded === false || $decoded === '') {
                throw new LogicException('APP_KEY is not valid base64.');
            }

            return $decoded;
        }

        return $configured;
    }

    private static function base64UrlEncode(string $value): string
    {
        return rtrim(strtr(base64_encode($value), '+/', '-_'), '=');
    }

    private static function base64UrlDecode(string $value): string|false
    {
        if ($value === '' || preg_match('/^[A-Za-z0-9_-]+$/', $value) !== 1) {
            return false;
        }

        $remainder = strlen($value) % 4;
        if ($remainder !== 0) {
            $value .= str_repeat('=', 4 - $remainder);
        }

        return base64_decode(strtr($value, '-_', '+/'), true);
    }

    /** @throws ValidationException */
    private static function reject(): never
    {
        throw ValidationException::withMessages([
            'cursor' => [__('api.invalid_cursor')],
        ]);
    }
}
