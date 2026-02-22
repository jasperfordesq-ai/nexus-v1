<?php
// Copyright © 2024–2026 Jasper Ford
// SPDX-License-Identifier: AGPL-3.0-or-later
// Author: Jasper Ford
// See NOTICE file for attribution and acknowledgements.

namespace Nexus\Controllers\Api;

use Nexus\Services\MatchingService;

/**
 * MatchPreferencesApiController - Match digest preference endpoints
 *
 * Endpoints:
 * - GET /api/v2/users/me/match-preferences  - Get match preferences
 * - PUT /api/v2/users/me/match-preferences  - Update match preferences
 */
class MatchPreferencesApiController extends BaseApiController
{
    protected bool $isV2Api = true;

    /**
     * GET /api/v2/users/me/match-preferences
     */
    public function show(): void
    {
        $userId = $this->requireAuth();

        $preferences = MatchingService::getPreferences($userId);
        $this->respondWithData($preferences);
    }

    /**
     * PUT /api/v2/users/me/match-preferences
     *
     * Accepts: { notification_frequency: "daily"|"weekly"|"never" }
     */
    public function update(): void
    {
        $userId = $this->requireAuth();

        $input = $this->getJsonInput();
        $current = MatchingService::getPreferences($userId);

        // Only update fields that were provided
        $updated = $current;
        if (isset($input['notification_frequency'])) {
            $allowed = ['daily', 'weekly', 'never'];
            if (!in_array($input['notification_frequency'], $allowed, true)) {
                $this->respondWithError('VALIDATION_ERROR', 'Invalid frequency. Must be: daily, weekly, or never', 'notification_frequency');
                return;
            }
            $updated['notification_frequency'] = $input['notification_frequency'];
        }

        if (isset($input['notify_hot_matches'])) {
            $updated['notify_hot_matches'] = (bool)$input['notify_hot_matches'];
        }

        if (isset($input['notify_mutual_matches'])) {
            $updated['notify_mutual_matches'] = (bool)$input['notify_mutual_matches'];
        }

        $success = MatchingService::savePreferences($userId, $updated);

        if ($success) {
            $this->respondWithData(MatchingService::getPreferences($userId));
        } else {
            $this->respondWithError('SERVER_ERROR', 'Failed to save match preferences');
        }
    }
}
