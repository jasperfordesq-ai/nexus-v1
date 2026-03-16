<?php
// Copyright © 2024–2026 Jasper Ford
// SPDX-License-Identifier: AGPL-3.0-or-later
// Author: Jasper Ford
// See NOTICE file for attribution and acknowledgements.

namespace App\Services;

/**
 * FeedActivityService — Laravel DI wrapper for legacy \Nexus\Services\FeedActivityService.
 *
 * Provides dependency-injectable access to the legacy static service methods.
 */
class FeedActivityService
{
    public function __construct()
    {
    }

    /**
     * Delegates to legacy FeedActivityService::getActivity().
     */
    public function getActivity(int $tenantId, int $userId, int $limit = 20): array
    {
        return \Nexus\Services\FeedActivityService::getActivity($tenantId, $userId, $limit);
    }

    /**
     * Delegates to legacy FeedActivityService::logActivity().
     */
    public function logActivity(int $tenantId, int $userId, string $type, array $data = []): bool
    {
        return \Nexus\Services\FeedActivityService::logActivity($tenantId, $userId, $type, $data);
    }

    /**
     * Delegates to legacy FeedActivityService::getTimeline().
     */
    public function getTimeline(int $tenantId, int $limit = 50): array
    {
        return \Nexus\Services\FeedActivityService::getTimeline($tenantId, $limit);
    }
}
