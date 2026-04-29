<?php
// Copyright © 2024–2026 Jasper Ford
// SPDX-License-Identifier: AGPL-3.0-or-later
// Author: Jasper Ford
// See NOTICE file for attribution and acknowledgements.

declare(strict_types=1);

namespace App\Services\PartnerApi;

use Illuminate\Support\Facades\Cache;

/**
 * AG60 — Per-partner rate limiter.
 *
 * Uses Laravel's cache (Redis in production, array in tests) keyed by
 * partner_id and the current minute window. Each partner's limit comes
 * from api_partners.rate_limit_per_minute (default 60).
 */
class PartnerApiRateLimiter
{
    /**
     * @return array{allowed:bool,remaining:int,limit:int,retry_after:int}
     */
    public static function hit(int $partnerId, int $limitPerMinute): array
    {
        $window = (int) floor(time() / 60);
        $key = "partner_api_rl:{$partnerId}:{$window}";

        $count = Cache::get($key, 0) + 1;
        Cache::put($key, $count, 65); // slight overshoot of the window

        $allowed = $count <= $limitPerMinute;
        $remaining = max(0, $limitPerMinute - $count);
        $retryAfter = $allowed ? 0 : (60 - (time() % 60));

        return [
            'allowed' => $allowed,
            'remaining' => $remaining,
            'limit' => $limitPerMinute,
            'retry_after' => $retryAfter,
        ];
    }
}
