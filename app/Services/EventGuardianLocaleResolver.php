<?php
// Copyright © 2024–2026 Jasper Ford
// SPDX-License-Identifier: AGPL-3.0-or-later
// Author: Jasper Ford
// See NOTICE file for attribution and acknowledgements.

declare(strict_types=1);

namespace App\Services;

use App\Core\TenantContext;
use App\Exceptions\EventSafetyException;
use App\Models\User;
use Throwable;

/** BCP-47 validation constrained to translations actually shipped by NEXUS. */
final class EventGuardianLocaleResolver
{
    /** @var list<string> */
    public const PLATFORM_LOCALES = [
        'en', 'ga', 'de', 'fr', 'it', 'pt', 'es', 'nl', 'pl', 'ja', 'ar',
    ];

    public function resolve(?string $requested, User $minor): string
    {
        $supported = $this->tenantLocales();
        if ($requested !== null && trim($requested) !== '') {
            return $this->normalize($requested, $supported);
        }

        $minorLocale = $minor->getAttribute('preferred_language');
        if (is_string($minorLocale) && trim($minorLocale) !== '') {
            try {
                return $this->normalize($minorLocale, $supported);
            } catch (EventSafetyException) {
                // Explicitly fall through to the tenant default.
            }
        }
        try {
            $tenantDefault = TenantContext::getSetting('default_language', 'en');
        } catch (Throwable) {
            throw new EventSafetyException('event_guardian_locale_configuration_invalid');
        }
        if (is_string($tenantDefault) && trim($tenantDefault) !== '') {
            try {
                return $this->normalize($tenantDefault, $supported);
            } catch (EventSafetyException) {
                // The final fallback is deliberately explicit and deterministic.
            }
        }
        if (in_array('en', $supported, true)) {
            return 'en';
        }

        throw new EventSafetyException('event_guardian_locale_configuration_invalid');
    }

    public function assertStored(string $locale): string
    {
        return $this->normalize($locale, $this->tenantLocales());
    }

    /** @param list<string> $supported */
    private function normalize(string $locale, array $supported): string
    {
        $tag = str_replace('_', '-', trim($locale));
        if ($tag === ''
            || strlen($tag) > 35
            || preg_match('/^[A-Za-z]{2,3}(?:-[A-Za-z0-9]{2,8})*$/', $tag) !== 1) {
            throw new EventSafetyException('event_guardian_locale_invalid');
        }
        $primary = strtolower(explode('-', $tag, 2)[0]);
        if (! in_array($primary, self::PLATFORM_LOCALES, true)
            || ! in_array($primary, $supported, true)) {
            throw new EventSafetyException('event_guardian_locale_unsupported');
        }

        return $primary;
    }

    /** @return list<string> */
    private function tenantLocales(): array
    {
        try {
            $configured = TenantContext::getSetting(
                'supported_languages',
                self::PLATFORM_LOCALES,
            );
        } catch (Throwable) {
            throw new EventSafetyException('event_guardian_locale_configuration_invalid');
        }
        if (! is_array($configured)) {
            throw new EventSafetyException('event_guardian_locale_configuration_invalid');
        }
        $locales = [];
        foreach ($configured as $candidate) {
            if (! is_string($candidate)) {
                throw new EventSafetyException('event_guardian_locale_configuration_invalid');
            }
            $primary = strtolower(trim($candidate));
            if (! in_array($primary, self::PLATFORM_LOCALES, true)) {
                throw new EventSafetyException('event_guardian_locale_configuration_invalid');
            }
            $locales[] = $primary;
        }
        $locales = array_values(array_unique($locales));
        if ($locales === []) {
            throw new EventSafetyException('event_guardian_locale_configuration_invalid');
        }

        return $locales;
    }
}
