<?php
// Copyright © 2024–2026 Jasper Ford
// SPDX-License-Identifier: AGPL-3.0-or-later
// Author: Jasper Ford
// See NOTICE file for attribution and acknowledgements.

namespace App\Services;

use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;

/**
 * PwnedPasswordService — Have I Been Pwned k-anonymity password check.
 *
 * Defends against credential-stuffing: rejects passwords that appear in
 * known public breach corpora regardless of complexity. NIST SP 800-63B
 * lists this as a recommended check; complexity rules ("must have an
 * uppercase letter") are NOT a substitute — bots generate complex
 * passwords trivially, and any password in HIBP's 800M+ corpus is one
 * an attacker will try in a credential-stuffing run.
 *
 * Privacy: we send only the FIRST 5 hex chars of the SHA-1 hash. The
 * API returns the list of all suffixes that match, so the server never
 * learns which specific password we were checking. This is k-anonymity
 * (k typically ~500).
 *
 * Failure mode: fail-OPEN on network/timeout — better to let a legit
 * user register with a possibly-pwned password than block all
 * registrations when api.pwnedpasswords.com is unreachable.
 *
 * Configurable via env:
 *   HIBP_ENABLED   = "false" disables the check entirely (default: enabled)
 *   HIBP_THRESHOLD = reject if breach-count > threshold (default: 0,
 *                    i.e. any breach occurrence rejects)
 */
class PwnedPasswordService
{
    private const API_URL = 'https://api.pwnedpasswords.com/range/';
    private const TIMEOUT_SECONDS = 3;

    /**
     * Check whether a password appears in HIBP's breach corpus above the
     * configured threshold. Returns true ⇒ reject; false ⇒ acceptable.
     */
    public function isPwned(string $password): bool
    {
        if (! filter_var(env('HIBP_ENABLED', 'true'), FILTER_VALIDATE_BOOLEAN)) {
            return false;
        }
        if ($password === '') {
            return false;
        }

        $threshold = max(0, (int) env('HIBP_THRESHOLD', 0));

        $sha1 = strtoupper(sha1($password));
        $prefix = substr($sha1, 0, 5);
        $suffix = substr($sha1, 5);

        try {
            $response = Http::timeout(self::TIMEOUT_SECONDS)
                ->withHeaders(['Add-Padding' => 'true'])
                ->get(self::API_URL . $prefix);
        } catch (\Throwable $e) {
            Log::info('hibp.network_error', ['error' => $e->getMessage()]);
            return false; // fail-open
        }

        if (! $response->ok()) {
            Log::info('hibp.http_error', ['status' => $response->status()]);
            return false;
        }

        $body = (string) $response->body();
        foreach (explode("\n", $body) as $line) {
            $line = trim($line);
            if ($line === '') {
                continue;
            }
            [$lineSuffix, $count] = array_pad(explode(':', $line, 2), 2, '0');
            if (strcasecmp($lineSuffix, $suffix) === 0) {
                $count = (int) trim($count);
                if ($count > $threshold) {
                    Log::info('hibp.password_pwned', ['count' => $count]);
                    return true;
                }
            }
        }

        return false;
    }
}
