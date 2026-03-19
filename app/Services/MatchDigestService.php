<?php
// Copyright © 2024–2026 Jasper Ford
// SPDX-License-Identifier: AGPL-3.0-or-later
// Author: Jasper Ford
// See NOTICE file for attribution and acknowledgements.

namespace App\Services;

/**
 * MatchDigestService — Laravel DI wrapper for legacy \Nexus\Services\MatchDigestService.
 *
 * Provides dependency-injectable access to the legacy static service methods.
 */
class MatchDigestService
{
    public function __construct()
    {
    }

    /**
     * Delegates to legacy MatchDigestService::generateDigest().
     */
    public function generateDigest(int $userId): array
    {
        return \Nexus\Services\MatchDigestService::generateDigest($userId);
    }

    /**
     * Delegates to legacy MatchDigestService::sendAllDigests().
     */
    public function sendAllDigests(int $tenantId): array
    {
        return \Nexus\Services\MatchDigestService::sendAllDigests($tenantId);
    }
}
