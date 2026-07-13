<?php
// Copyright © 2024–2026 Jasper Ford
// SPDX-License-Identifier: AGPL-3.0-or-later
// Author: Jasper Ford
// See NOTICE file for attribution and acknowledgements.

declare(strict_types=1);

namespace App\Services\Auth;

/**
 * Browser-held proof for OAuth/OIDC login flows.
 *
 * Only the SHA-256 challenge is allowed into signed state or server cache. The
 * high-entropy verifier remains in the initiating browser until final exchange.
 */
final class OAuthBrowserBinding
{
    private const CHALLENGE_PATTERN = '/^[A-Za-z0-9_-]{43}$/D';
    private const VERIFIER_PATTERN = '/^[A-Za-z0-9._~-]{43,128}$/D';

    public static function requireChallenge(?string $challenge): string
    {
        if (!is_string($challenge) || !preg_match(self::CHALLENGE_PATTERN, $challenge)) {
            throw new \RuntimeException('OAuth browser challenge is invalid.');
        }

        return $challenge;
    }

    public static function verifierMatches(string $challenge, ?string $verifier): bool
    {
        if (
            !preg_match(self::CHALLENGE_PATTERN, $challenge)
            || !is_string($verifier)
            || !preg_match(self::VERIFIER_PATTERN, $verifier)
        ) {
            return false;
        }

        $actual = rtrim(
            strtr(base64_encode(hash('sha256', $verifier, true)), '+/', '-_'),
            '='
        );

        return hash_equals($challenge, $actual);
    }
}
