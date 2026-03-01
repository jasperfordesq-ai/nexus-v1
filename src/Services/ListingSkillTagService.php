<?php
// Copyright © 2024–2026 Jasper Ford
// SPDX-License-Identifier: AGPL-3.0-or-later
// Author: Jasper Ford
// See NOTICE file for attribution and acknowledgements.

namespace Nexus\Services;

use Nexus\Core\Database;
use Nexus\Core\TenantContext;

/**
 * ListingSkillTagService - Skill tag management for listings
 *
 * Provides:
 * - Adding/removing skill tags from listings
 * - Filtering listings by skill tags
 * - Tag autocomplete/suggestions
 * - Popular tags for the tenant
 */
class ListingSkillTagService
{
    /**
     * Maximum number of tags per listing
     */
    private const MAX_TAGS_PER_LISTING = 10;

    /**
     * Set skill tags for a listing (replaces existing tags).
     *
     * @param int $listingId Listing ID
     * @param array $tags Array of tag strings
     * @return bool Success
     */
    public static function setTags(int $listingId, array $tags): bool
    {
        $tenantId = TenantContext::getId();

        // Validate listing belongs to tenant
        $exists = Database::query(
            "SELECT id FROM listings WHERE id = ? AND tenant_id = ?",
            [$listingId, $tenantId]
        )->fetch(\PDO::FETCH_ASSOC);

        if (!$exists) {
            return false;
        }

        // Normalize and deduplicate tags
        $normalizedTags = [];
        foreach ($tags as $tag) {
            $normalized = self::normalizeTag($tag);
            if ($normalized !== '' && !in_array($normalized, $normalizedTags, true)) {
                $normalizedTags[] = $normalized;
            }
        }

        // Enforce max limit
        $normalizedTags = array_slice($normalizedTags, 0, self::MAX_TAGS_PER_LISTING);

        // Remove existing tags
        Database::query(
            "DELETE FROM listing_skill_tags WHERE listing_id = ? AND tenant_id = ?",
            [$listingId, $tenantId]
        );

        // Insert new tags
        foreach ($normalizedTags as $tag) {
            Database::query(
                "INSERT IGNORE INTO listing_skill_tags (tenant_id, listing_id, tag)
                 VALUES (?, ?, ?)",
                [$tenantId, $listingId, $tag]
            );
        }

        return true;
    }

    /**
     * Get tags for a listing.
     *
     * @param int $listingId Listing ID
     * @return string[] Array of tag strings
     */
    public static function getTags(int $listingId): array
    {
        $tenantId = TenantContext::getId();

        $rows = Database::query(
            "SELECT tag FROM listing_skill_tags
             WHERE listing_id = ? AND tenant_id = ?
             ORDER BY tag ASC",
            [$listingId, $tenantId]
        )->fetchAll(\PDO::FETCH_ASSOC);

        return array_column($rows, 'tag');
    }

    /**
     * Add a single tag to a listing.
     *
     * @param int $listingId Listing ID
     * @param string $tag Tag string
     * @return bool Success (false if max tags reached)
     */
    public static function addTag(int $listingId, string $tag): bool
    {
        $tenantId = TenantContext::getId();
        $normalized = self::normalizeTag($tag);
        if ($normalized === '') {
            return false;
        }

        // Check current count
        $count = Database::query(
            "SELECT COUNT(*) as cnt FROM listing_skill_tags
             WHERE listing_id = ? AND tenant_id = ?",
            [$listingId, $tenantId]
        )->fetch(\PDO::FETCH_ASSOC);

        if ((int)($count['cnt'] ?? 0) >= self::MAX_TAGS_PER_LISTING) {
            return false;
        }

        Database::query(
            "INSERT IGNORE INTO listing_skill_tags (tenant_id, listing_id, tag)
             VALUES (?, ?, ?)",
            [$tenantId, $listingId, $normalized]
        );

        return true;
    }

    /**
     * Remove a single tag from a listing.
     *
     * @param int $listingId Listing ID
     * @param string $tag Tag string
     */
    public static function removeTag(int $listingId, string $tag): void
    {
        $tenantId = TenantContext::getId();
        $normalized = self::normalizeTag($tag);

        Database::query(
            "DELETE FROM listing_skill_tags
             WHERE listing_id = ? AND tenant_id = ? AND tag = ?",
            [$listingId, $tenantId, $normalized]
        );
    }

    /**
     * Find listing IDs that match ANY of the given skill tags.
     *
     * @param array $tags Tags to filter by
     * @param int $limit Max results
     * @return int[] Array of listing IDs
     */
    public static function findListingsByTags(array $tags, int $limit = 100): array
    {
        $tenantId = TenantContext::getId();

        if (empty($tags)) {
            return [];
        }

        // Normalize tags
        $normalizedTags = array_map([self::class, 'normalizeTag'], $tags);
        $normalizedTags = array_filter($normalizedTags, fn($t) => $t !== '');

        if (empty($normalizedTags)) {
            return [];
        }

        $placeholders = implode(',', array_fill(0, count($normalizedTags), '?'));
        $params = array_merge([$tenantId], $normalizedTags, [$limit]);

        $rows = Database::query(
            "SELECT DISTINCT lst.listing_id
             FROM listing_skill_tags lst
             JOIN listings l ON lst.listing_id = l.id AND l.status = 'active'
             WHERE lst.tenant_id = ? AND lst.tag IN ({$placeholders})
             ORDER BY lst.listing_id DESC
             LIMIT ?",
            $params
        )->fetchAll(\PDO::FETCH_ASSOC);

        return array_map('intval', array_column($rows, 'listing_id'));
    }

    /**
     * Get popular tags for the tenant.
     *
     * @param int $limit Max number of tags to return
     * @return array [['tag' => string, 'count' => int], ...]
     */
    public static function getPopularTags(int $limit = 20): array
    {
        $tenantId = TenantContext::getId();

        return Database::query(
            "SELECT lst.tag, COUNT(*) as count
             FROM listing_skill_tags lst
             JOIN listings l ON lst.listing_id = l.id AND l.status = 'active'
             WHERE lst.tenant_id = ?
             GROUP BY lst.tag
             ORDER BY count DESC
             LIMIT ?",
            [$tenantId, $limit]
        )->fetchAll(\PDO::FETCH_ASSOC);
    }

    /**
     * Autocomplete tags based on a prefix.
     *
     * @param string $prefix Tag prefix
     * @param int $limit Max suggestions
     * @return string[] Matching tags
     */
    public static function autocompleteTags(string $prefix, int $limit = 10): array
    {
        $tenantId = TenantContext::getId();
        $normalized = self::normalizeTag($prefix);

        if (strlen($normalized) < 2) {
            return [];
        }

        $rows = Database::query(
            "SELECT DISTINCT tag FROM listing_skill_tags
             WHERE tenant_id = ? AND tag LIKE ?
             ORDER BY tag ASC
             LIMIT ?",
            [$tenantId, $normalized . '%', $limit]
        )->fetchAll(\PDO::FETCH_ASSOC);

        return array_column($rows, 'tag');
    }

    /**
     * Normalize a tag string.
     *
     * Lowercases, trims, removes special characters except hyphens.
     *
     * @param string $tag Raw tag
     * @return string Normalized tag
     */
    private static function normalizeTag(string $tag): string
    {
        $tag = strtolower(trim($tag));
        // Allow letters, numbers, hyphens, spaces
        $tag = preg_replace('/[^a-z0-9\- ]/', '', $tag);
        // Collapse whitespace
        $tag = preg_replace('/\s+/', ' ', $tag);
        // Limit length
        return substr(trim($tag), 0, 100);
    }
}
