<?php
// Copyright © 2024–2026 Jasper Ford
// SPDX-License-Identifier: AGPL-3.0-or-later
// Author: Jasper Ford
// See NOTICE file for attribution and acknowledgements.

namespace App\Services;

/**
 * ExchangeRatingService — Laravel DI wrapper for legacy \Nexus\Services\ExchangeRatingService.
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
        return \Nexus\Services\ExchangeRatingService::rate($tenantId, $exchangeId, $userId, $rating, $comment);
    }

    /**
     * Delegates to legacy ExchangeRatingService::getRatings().
     */
    public function getRatings(int $tenantId, int $exchangeId): array
    {
        return \Nexus\Services\ExchangeRatingService::getRatings($tenantId, $exchangeId);
    }

    /**
     * Delegates to legacy ExchangeRatingService::getAverageRating().
     */
    public function getAverageRating(int $tenantId, int $userId): float
    {
        return \Nexus\Services\ExchangeRatingService::getAverageRating($tenantId, $userId);
    }
}
