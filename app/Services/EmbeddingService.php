<?php
// Copyright © 2024–2026 Jasper Ford
// SPDX-License-Identifier: AGPL-3.0-or-later
// Author: Jasper Ford
// See NOTICE file for attribution and acknowledgements.

namespace App\Services;

/**
 * EmbeddingService — Laravel DI wrapper for legacy \Nexus\Services\EmbeddingService.
 *
 * Provides dependency-injectable access to the legacy static service methods.
 */
class EmbeddingService
{
    public function __construct()
    {
    }

    /**
     * Delegates to legacy EmbeddingService::generateForListing().
     */
    public function generateForListing(array $listing): void
    {
        if (!class_exists('\Nexus\Services\EmbeddingService')) { return; }
        \Nexus\Services\EmbeddingService::generateForListing($listing);
    }

    /**
     * Delegates to legacy EmbeddingService::generateForUser().
     */
    public function generateForUser(array $user): void
    {
        if (!class_exists('\Nexus\Services\EmbeddingService')) { return; }
        \Nexus\Services\EmbeddingService::generateForUser($user);
    }

    /**
     * Delegates to legacy EmbeddingService::findSimilar().
     */
    public function findSimilar(int $contentId, string $contentType, int $tenantId, int $limit = 5): array
    {
        if (!class_exists('\Nexus\Services\EmbeddingService')) { return []; }
        return \Nexus\Services\EmbeddingService::findSimilar($contentId, $contentType, $tenantId, $limit);
    }

    /**
     * Delegates to legacy EmbeddingService::cosineSimilarity().
     */
    public function cosineSimilarity(array $a, array $b): float
    {
        if (!class_exists('\Nexus\Services\EmbeddingService')) { return 0.0; }
        return \Nexus\Services\EmbeddingService::cosineSimilarity($a, $b);
    }
}
