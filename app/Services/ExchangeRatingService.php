<?php
// Copyright © 2024–2026 Jasper Ford
// SPDX-License-Identifier: AGPL-3.0-or-later
// Author: Jasper Ford
// See NOTICE file for attribution and acknowledgements.

namespace App\Services;

/**
 *
 * Provides dependency-injectable access to the legacy static service methods.
 */
class ExchangeRatingService
{
    public function __construct()
    {
    }

    /**
     * Delegates to legacy ExchangeRatingService::rate().
     */
    public function rate(int $tenantId, int $exchangeId, int $userId, int $rating, ?string $comment = null): bool
    {
        \Illuminate\Support\Facades\Log::warning('Legacy delegation removed: ' . __METHOD__);
        return false;
    }

    /**
     * Delegates to legacy ExchangeRatingService::getRatings().
     */
    public function getRatings(int $tenantId, int $exchangeId): array
    {
        \Illuminate\Support\Facades\Log::warning('Legacy delegation removed: ' . __METHOD__);
        return [];
    }

    /**
     * Delegates to legacy ExchangeRatingService::getAverageRating().
     */
    public function getAverageRating(int $tenantId, int $userId): float
    {
        \Illuminate\Support\Facades\Log::warning('Legacy delegation removed: ' . __METHOD__);
        return 0.0;
    }
}
