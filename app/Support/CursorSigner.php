<?php
// Copyright © 2024–2026 Jasper Ford
// SPDX-License-Identifier: AGPL-3.0-or-later
// Author: Jasper Ford
// See NOTICE file for attribution and acknowledgements.

namespace App\Support;

/**
 * CursorSigner — HMAC-signed pagination cursors.
 *
 * Pagination cursors that travel through public URLs MUST be HMAC-signed
 * against `app.key` so a client can't tamper with them (skip to arbitrary
 * IDs, scrape via reverse-iteration, etc.). This helper centralises the
 * encode/decode logic that was previously duplicated inline in
 * FeedService::getFeedItems().
 *
 * Format: base64( hex_sha256_hmac . "." . json_payload )
 *
 * Decode returns the payload array on a valid signature, or null on any
 * mismatch / malformed input.
 */
final class CursorSigner
{
    /**
     * Encode a payload array as a signed cursor string.
     */
    public static function encode(array $payload): string
    {
        $json = json_encode($payload);
        $sig = hash_hmac('sha256', $json, (string) config('app.key'));

        return base64_encode($sig . '.' . $json);
    }

    /**
     * Decode and verify a signed cursor. Returns null when the signature
     * doesn't validate or the payload is malformed.
     *
     * @return array<string, mixed>|null
     */
    public static function decode(?string $cursor): ?array
    {
        if ($cursor === null || $cursor === '') {
            return null;
        }

        $decoded = base64_decode($cursor, true);
        if ($decoded === false) {
            return null;
        }

        $dotPos = strpos($decoded, '.');
        if ($dotPos === false) {
            return null;
        }

        $sig = substr($decoded, 0, $dotPos);
        $json = substr($decoded, $dotPos + 1);
        $expected = hash_hmac('sha256', $json, (string) config('app.key'));

        if (!hash_equals($expected, $sig)) {
            return null;
        }

        $data = json_decode($json, true);
        return is_array($data) ? $data : null;
    }
}
