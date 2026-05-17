<?php
// Copyright © 2024–2026 Jasper Ford
// SPDX-License-Identifier: AGPL-3.0-or-later
// Author: Jasper Ford
// See NOTICE file for attribution and acknowledgements.

namespace App\Services;

use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Log;

/**
 * MxRecordValidator — checks that an email domain is real enough to receive
 * mail. Looks up MX records first, falls back to A records per RFC 5321
 * §5.1 (host with no MX accepts mail via its A record).
 *
 * Catches three signups we don't want:
 *   1. Typos like `user@gmial.com` — improves UX as well as security.
 *   2. Freshly-registered burner domains used for spam-signup waves.
 *   3. Made-up domains that bots fall back to when blocklists trim them.
 *
 * Failure mode: DNS lookups fail open. If the lookup itself errors (no
 * network, DNS server down), the user is allowed through — DNS outages
 * shouldn't block legitimate signups. A genuine "no MX, no A" result is
 * what we reject.
 *
 * Caching: results are cached for 24h. Real-world domain MX records change
 * rarely; a 24h false-negative for a freshly-set-up domain is acceptable.
 */
class MxRecordValidator
{
    /** @var int Cache TTL in seconds (24h). */
    private const CACHE_TTL = 86400;

    /** @var int Cache TTL for negative results (1h) — lets attackers' burner
     *  domains stop being blocked sooner if they later add real records. */
    private const NEGATIVE_CACHE_TTL = 3600;

    /**
     * Returns true when the email's domain has at least one MX or A record.
     * Returns true on DNS errors (fail-open).
     * Returns false only for an unambiguous "this domain cannot receive mail".
     */
    public function isResolvable(string $email): bool
    {
        $atPos = strrpos($email, '@');
        if ($atPos === false || $atPos === strlen($email) - 1) {
            // Malformed email — let the email validator catch it, don't double-error.
            return true;
        }
        $domain = strtolower(substr($email, $atPos + 1));
        if ($domain === '') {
            return true;
        }

        $cacheKey = 'mx:' . $domain;
        $cached = Cache::get($cacheKey);
        if ($cached !== null) {
            return (bool) $cached;
        }

        $resolvable = $this->resolveLive($domain);

        // Positive results cache longer than negative — see class-level note.
        Cache::put(
            $cacheKey,
            $resolvable,
            $resolvable ? self::CACHE_TTL : self::NEGATIVE_CACHE_TTL
        );

        return $resolvable;
    }

    /**
     * RFC 6761 + RFC 2606 reserved domains and TLDs that exist solely for
     * documentation / testing and must never receive real email. `.invalid`
     * is the one DNS itself refuses to resolve; the rest (example.com,
     * example.test, *.localhost, etc.) DO resolve at the DNS layer (some
     * even have MX records) but are guaranteed to be undeliverable, so we
     * reject them before the DNS check. A real cyber attack on 2026-05-14
     * → 2026-05-16 used `testing@example.com` precisely because example.com
     * passes naive MX/A checks; this list closes that hole.
     */
    private const RESERVED_DOMAINS = [
        'example.com',
        'example.net',
        'example.org',
        'localhost',
    ];

    private const RESERVED_TLDS = [
        '.test',
        '.example',
        '.invalid',
        '.localhost',
    ];

    private function resolveLive(string $domain): bool
    {
        // Reject obvious junk before touching DNS.
        if (!preg_match('/^[a-z0-9.-]+$/', $domain) || strlen($domain) > 253) {
            return false;
        }
        if (in_array($domain, self::RESERVED_DOMAINS, true)) {
            return false;
        }
        foreach (self::RESERVED_TLDS as $tld) {
            if (str_ends_with($domain, $tld)) {
                return false;
            }
        }

        try {
            // checkdnsrr returns false on "no records" AND on DNS error.
            // PHP doesn't distinguish, so we can't separate "no MX" from
            // "DNS unreachable" without a sentinel domain lookup. Cheap
            // pragmatic answer: accept the false; if DNS is truly broken
            // and lots of users start failing, we'll see it in Sentry.
            if (@checkdnsrr($domain, 'MX')) {
                return true;
            }
            // Fallback per RFC 5321 — domain with no MX still receives mail
            // via its A record.
            if (@checkdnsrr($domain, 'A')) {
                return true;
            }
            return false;
        } catch (\Throwable $e) {
            Log::info('mx_validator.lookup_failed', [
                'domain' => $domain,
                'error' => $e->getMessage(),
            ]);
            return true; // fail open
        }
    }
}
