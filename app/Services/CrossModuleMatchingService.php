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
class CrossModuleMatchingService
{
    public function __construct()
    {
    }

    /**
     * Delegates to legacy CrossModuleMatchingService::getAllMatches().
     */
    public function getAllMatches(int $userId, array $options = []): array
    {
        \Illuminate\Support\Facades\Log::warning('Legacy delegation removed: ' . __METHOD__);
        return [];
    }
}
