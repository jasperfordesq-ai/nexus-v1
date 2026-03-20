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
        \Illuminate\Support\Facades\Log::warning('Legacy delegation removed: ' . __METHOD__);
        return false;
    }

    /**
     * Delegates to legacy PostSharingService::getShares().
     */
    public function getShares(int $tenantId, int $postId): array
    {
        \Illuminate\Support\Facades\Log::warning('Legacy delegation removed: ' . __METHOD__);
        return [];
    }

    /**
     * Delegates to legacy PostSharingService::getShareCount().
     */
    public function getShareCount(int $tenantId, int $postId): int
    {
        \Illuminate\Support\Facades\Log::warning('Legacy delegation removed: ' . __METHOD__);
        return 0;
    }
}
