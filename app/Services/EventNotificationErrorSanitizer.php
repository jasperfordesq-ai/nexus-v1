<?php
// Copyright © 2024–2026 Jasper Ford
// SPDX-License-Identifier: AGPL-3.0-or-later
// Author: Jasper Ford
// See NOTICE file for attribution and acknowledgements.

declare(strict_types=1);

namespace App\Services;

/** Redacts credentials and action tokens before notification errors reach durable storage. */
final class EventNotificationErrorSanitizer
{
    public static function sanitize(string $message, int $limit = 4000): string
    {
        $sanitized = preg_replace(
            '/\bBearer\s+[^\s,;]+/iu',
            'Bearer [REDACTED]',
            $message,
        ) ?? $message;
        $sanitized = preg_replace(
            '/([?&](?:token|offer_token|waitlist_offer_token|signature|key)=)[^&\s]+/iu',
            '$1[REDACTED]',
            $sanitized,
        ) ?? $sanitized;
        $sanitized = preg_replace(
            '/\b(?:Bearer\s+)?[A-Fa-f0-9]{48,}\b/u',
            '[REDACTED]',
            $sanitized,
        ) ?? $sanitized;
        $sanitized = preg_replace(
            '/\b[A-Za-z0-9+\/_-]{64,}={0,2}\b/u',
            '[REDACTED]',
            $sanitized,
        ) ?? $sanitized;
        $sanitized = preg_replace(
            '/(?<![A-Z0-9._%+\-])[A-Z0-9._%+\-]+@[A-Z0-9.\-]+\.[A-Z]{2,63}(?![A-Z0-9._%+\-])/iu',
            '[REDACTED_EMAIL]',
            $sanitized,
        ) ?? $sanitized;
        $sanitized = trim($sanitized);

        return mb_substr($sanitized !== '' ? $sanitized : 'event_notification_failure', 0, max(1, $limit));
    }
}
