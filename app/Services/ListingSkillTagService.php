<?php
// Copyright © 2024–2026 Jasper Ford
// SPDX-License-Identifier: AGPL-3.0-or-later
// Author: Jasper Ford
// See NOTICE file for attribution and acknowledgements.

namespace App\Services;

/**
 * ListingSkillTagService — Laravel DI wrapper for legacy \Nexus\Services\ListingSkillTagService.
 *
 * Provides dependency-injectable access to the legacy static service methods.
 */
class ListingSkillTagService
{
    public function __construct()
    {
    }

    /**
     * Delegates to legacy ListingSkillTagService::setTags().
     */
    public function setTags(int $listingId, array $tags): bool
    {
        if (!class_exists('\Nexus\Services\ListingSkillTagService')) { return false; }
        return \Nexus\Services\ListingSkillTagService::setTags($listingId, $tags);
    }

    /**
     * Delegates to legacy ListingSkillTagService::getTags().
     */
    public function getTags(int $listingId): array
    {
        if (!class_exists('\Nexus\Services\ListingSkillTagService')) { return []; }
        return \Nexus\Services\ListingSkillTagService::getTags($listingId);
    }

    /**
     * Delegates to legacy ListingSkillTagService::addTag().
     */
    public function addTag(int $listingId, string $tag): bool
    {
        if (!class_exists('\Nexus\Services\ListingSkillTagService')) { return false; }
        return \Nexus\Services\ListingSkillTagService::addTag($listingId, $tag);
    }

    /**
     * Delegates to legacy ListingSkillTagService::removeTag().
     */
    public function removeTag(int $listingId, string $tag): void
    {
        if (!class_exists('\Nexus\Services\ListingSkillTagService')) { return; }
        \Nexus\Services\ListingSkillTagService::removeTag($listingId, $tag);
    }

    /**
     * Delegates to legacy ListingSkillTagService::findListingsByTags().
     */
    public function findListingsByTags(array $tags, int $limit = 100): array
    {
        if (!class_exists('\Nexus\Services\ListingSkillTagService')) { return []; }
        return \Nexus\Services\ListingSkillTagService::findListingsByTags($tags, $limit);
    }

    /**
     * Get popular/trending skill tags.
     *
     * Delegates to legacy ListingSkillTagService::getPopularTags().
     */
    public function getPopularTags(int $limit = 20): array
    {
        if (!class_exists('\Nexus\Services\ListingSkillTagService')) { return []; }
        return \Nexus\Services\ListingSkillTagService::getPopularTags($limit);
    }

    /**
     * Autocomplete skill tags by prefix.
     *
     * Delegates to legacy ListingSkillTagService::autocompleteTags().
     */
    public function autocompleteTags(string $prefix, int $limit = 10): array
    {
        if (!class_exists('\Nexus\Services\ListingSkillTagService')) { return []; }
        return \Nexus\Services\ListingSkillTagService::autocompleteTags($prefix, $limit);
    }
}
