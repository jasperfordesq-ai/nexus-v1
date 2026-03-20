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
class MatchNotificationService
{
    public function __construct()
    {
    }

    /**
     * Delegates to legacy MatchNotificationService::onListingCreated().
     */
    public function onListingCreated(int $listingId, int $creatorUserId, array $listingData): int
    {
        \Illuminate\Support\Facades\Log::warning('Legacy delegation removed: ' . __METHOD__);
        return 0;
    }

    /**
     * Delegates to legacy MatchNotificationService::cleanupOldRecords().
     */
    public function cleanupOldRecords(): int
    {
        \Illuminate\Support\Facades\Log::warning('Legacy delegation removed: ' . __METHOD__);
        return 0;
    }
}
