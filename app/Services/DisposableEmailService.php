<?php
// Copyright © 2024–2026 Jasper Ford
// SPDX-License-Identifier: AGPL-3.0-or-later
// Author: Jasper Ford
// See NOTICE file for attribution and acknowledgements.

namespace App\Services;

/**
 * DisposableEmailService — checks an email against a curated list of
 * throwaway / temp-email providers (mailinator, 10minutemail, guerrillamail,
 * tempmail, yopmail, etc.).
 *
 * Used by RegistrationService to reject the cheapest bot-signup vector:
 * a script that registers en masse using disposable inboxes, then verifies
 * each one later via the same provider's web UI.
 *
 * The blocklist lives in `resources/security/disposable-email-domains.txt`
 * (one domain per line, `#` for comments). Loaded once per request and
 * cached in a static property.
 *
 * False-positive mitigation: tenant admins can override this check (TODO:
 * admin UI) — see `$allowedExtraDomains` and `$bypassedTenantIds`.
 */
class DisposableEmailService
{
    /** @var array<string,true>|null In-memory cache of blocklist domains. */
    private static ?array $domains = null;

    /**
     * Returns true when the email's domain is a known disposable / throwaway
     * provider. Empty / malformed emails return false (let the normal email
     * validator surface those).
     */
    public function isDisposable(string $email): bool
    {
        $email = strtolower(trim($email));
        $atPos = strrpos($email, '@');
        if ($atPos === false || $atPos === strlen($email) - 1) {
            return false;
        }
        $domain = substr($email, $atPos + 1);
        if ($domain === '') {
            return false;
        }

        $blocklist = self::loadBlocklist();
        if (isset($blocklist[$domain])) {
            return true;
        }

        // Also block sub-domains of listed providers (e.g. `foo.mailinator.com`)
        // which several throwaway services use to dodge naive exact-match
        // blocklists.
        $parts = explode('.', $domain);
        while (count($parts) > 2) {
            array_shift($parts);
            $parent = implode('.', $parts);
            if (isset($blocklist[$parent])) {
                return true;
            }
        }

        return false;
    }

    /**
     * Reload the blocklist on the next isDisposable() call. Mostly useful
     * in tests after writing a fresh file to the resources path.
     */
    public static function resetCache(): void
    {
        self::$domains = null;
    }

    /**
     * @return array<string,true> Lower-cased domains keyed by domain for O(1) lookup.
     */
    private static function loadBlocklist(): array
    {
        if (self::$domains !== null) {
            return self::$domains;
        }

        $path = base_path('resources/security/disposable-email-domains.txt');
        $out = [];
        if (is_file($path)) {
            $handle = @fopen($path, 'r');
            if ($handle !== false) {
                while (($line = fgets($handle)) !== false) {
                    $line = trim($line);
                    if ($line === '' || str_starts_with($line, '#')) {
                        continue;
                    }
                    $out[strtolower($line)] = true;
                }
                fclose($handle);
            }
        }

        return self::$domains = $out;
    }
}
