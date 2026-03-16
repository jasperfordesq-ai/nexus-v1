<?php
// Copyright © 2024–2026 Jasper Ford
// SPDX-License-Identifier: AGPL-3.0-or-later
// Author: Jasper Ford
// See NOTICE file for attribution and acknowledgements.

namespace App\Services;

/**
 * PostSharingService — Laravel DI wrapper for legacy \Nexus\Services\PostSharingService.
 *
 * Provides dependency-injectable access to the legacy static service methods.
 */
class PostSharingService
{
    public function __construct()
    {
    }

    /**
     * Delegates to legacy PostSharingService::share().
     */
    public function share(int $tenantId, int $postId, int $userId, ?string $comment = null): bool
    {
        return \Nexus\Services\PostSharingService::share($tenantId, $postId, $userId, $comment);
    }

    /**
     * Delegates to legacy PostSharingService::getShares().
     */
    public function getShares(int $tenantId, int $postId): array
    {
        return \Nexus\Services\PostSharingService::getShares($tenantId, $postId);
    }

    /**
     * Delegates to legacy PostSharingService::getShareCount().
     */
    public function getShareCount(int $tenantId, int $postId): int
    {
        return \Nexus\Services\PostSharingService::getShareCount($tenantId, $postId);
    }
}
