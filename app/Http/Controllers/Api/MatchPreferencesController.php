<?php
// Copyright © 2024–2026 Jasper Ford
// SPDX-License-Identifier: AGPL-3.0-or-later
// Author: Jasper Ford
// See NOTICE file for attribution and acknowledgements.

namespace App\Http\Controllers\Api;

use Illuminate\Http\JsonResponse;
use Nexus\Services\MatchingService;

/**
 * MatchPreferencesController — Eloquent-powered match preference endpoints.
 *
 * Fully migrated from legacy delegation. Uses legacy static MatchingService
 * which handles its own tenant scoping via TenantContext.
 */
class MatchPreferencesController extends BaseApiController
{
    protected bool $isV2Api = true;

    /**
     * GET /api/v2/users/me/match-preferences
     *
     * Returns the authenticated user's match notification preferences.
     */
    public function show(): JsonResponse
    {
        $userId = $this->requireAuth();

        $preferences = MatchingService::getPreferences($userId);

        return $this->respondWithData($preferences);
    }

    /**
     * PUT /api/v2/users/me/match-preferences
     *
     * Update match notification preferences.
     *
     * Request body (JSON, all optional):
     * - notification_frequency: 'daily' | 'weekly' | 'fortnightly' | 'never'
     * - notify_hot_matches: bool
     * - notify_mutual_matches: bool
     */
    public function update(): JsonResponse
    {
        $userId = $this->requireAuth();

        $current = MatchingService::getPreferences($userId);
        $updated = $current;

        // Only update fields that were provided
        $notificationFrequency = $this->input('notification_frequency');
        if ($notificationFrequency !== null) {
            $allowed = ['daily', 'weekly', 'fortnightly', 'never'];
            if (!in_array($notificationFrequency, $allowed, true)) {
                return $this->respondWithError(
                    'VALIDATION_ERROR',
                    'Invalid frequency. Must be: daily, weekly, fortnightly, or never',
                    'notification_frequency'
                );
            }
            $updated['notification_frequency'] = $notificationFrequency;
        }

        $notifyHot = $this->input('notify_hot_matches');
        if ($notifyHot !== null) {
            $updated['notify_hot_matches'] = (bool) $notifyHot;
        }

        $notifyMutual = $this->input('notify_mutual_matches');
        if ($notifyMutual !== null) {
            $updated['notify_mutual_matches'] = (bool) $notifyMutual;
        }

        $success = MatchingService::savePreferences($userId, $updated);

        if (!$success) {
            return $this->respondWithError('SERVER_ERROR', 'Failed to save match preferences');
        }

        return $this->respondWithData(MatchingService::getPreferences($userId));
    }
}
