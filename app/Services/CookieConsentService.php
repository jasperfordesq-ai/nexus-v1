<?php
// Copyright © 2024–2026 Jasper Ford
// SPDX-License-Identifier: AGPL-3.0-or-later
// Author: Jasper Ford
// See NOTICE file for attribution and acknowledgements.

namespace App\Services;

use Illuminate\Support\Facades\DB;

/**
 * CookieConsentService — Laravel DI-based service for cookie consent management.
 *
 * Eloquent/DI counterpart to the legacy static \Nexus\Services\CookieConsentService.
 * Manages GDPR cookie consent recording and validation.
 */
class CookieConsentService
{
    private const CONSENT_VERSION = '1.0';
    private const DEFAULT_VALIDITY_DAYS = 365;

    /**
     * Get the current consent status for a user or session.
     */
    public function getConsent(?int $userId = null, ?string $sessionId = null): ?array
    {
        $query = DB::table('cookie_consents')
            ->where('expires_at', '>', now())
            ->orderByDesc('created_at');

        if ($userId) {
            $query->where('user_id', $userId);
        } elseif ($sessionId) {
            $query->where('session_id', $sessionId);
        } else {
            return null;
        }

        $record = $query->first();

        return $record ? (array) $record : null;
    }

    /**
     * Save cookie consent preferences.
     *
     * @param array{functional?: bool, analytics?: bool, marketing?: bool} $categories
     */
    public function saveConsent(array $categories, ?int $userId = null, ?string $sessionId = null): bool
    {
        $expiresAt = now()->addDays(self::DEFAULT_VALIDITY_DAYS);

        DB::table('cookie_consents')->insert([
            'user_id'         => $userId,
            'session_id'      => $sessionId ?? session_id(),
            'essential'       => true,
            'functional'      => $categories['functional'] ?? false,
            'analytics'       => $categories['analytics'] ?? false,
            'marketing'       => $categories['marketing'] ?? false,
            'consent_version' => self::CONSENT_VERSION,
            'consent_string'  => json_encode($categories),
            'ip_address'      => request()->ip(),
            'user_agent'      => request()->userAgent(),
            'expires_at'      => $expiresAt,
            'created_at'      => now(),
            'updated_at'      => now(),
        ]);

        return true;
    }

    /**
     * Check if a specific consent category is granted.
     */
    public function checkCategory(string $category, ?int $userId = null, ?string $sessionId = null): bool
    {
        $consent = $this->getConsent($userId, $sessionId);

        if (! $consent) {
            return $category === 'essential';
        }

        return (bool) ($consent[$category] ?? false);
    }
}
