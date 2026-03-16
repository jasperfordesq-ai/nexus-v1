<?php
// Copyright © 2024–2026 Jasper Ford
// SPDX-License-Identifier: AGPL-3.0-or-later
// Author: Jasper Ford
// See NOTICE file for attribution and acknowledgements.

namespace App\Services;

/**
 * HashtagService — Laravel DI wrapper for legacy \Nexus\Services\HashtagService.
 *
 * Provides dependency-injectable access to the legacy static service methods.
 */
class HashtagService
{
    public function __construct()
    {
    }

    /**
     * Delegates to legacy HashtagService::getTrending().
     */
    public function getTrending(int $tenantId, int $limit = 10): array
    {
        return \Nexus\Services\HashtagService::getTrending($tenantId, $limit);
    }

    /**
     * Delegates to legacy HashtagService::getPostsByTag().
     */
    public function getPostsByTag(int $tenantId, string $tag, int $limit = 20): array
    {
        return \Nexus\Services\HashtagService::getPostsByTag($tenantId, $tag, $limit);
    }

    /**
     * Delegates to legacy HashtagService::extractTags().
     */
    public function extractTags(string $content): array
    {
        return \Nexus\Services\HashtagService::extractTags($content);
    }

    /**
     * Delegates to legacy HashtagService::syncTags().
     */
    public function syncTags(int $tenantId, int $postId, array $tags): void
    {
        \Nexus\Services\HashtagService::syncTags($tenantId, $postId, $tags);
    }
}
