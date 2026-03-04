<?php
// Copyright © 2024–2026 Jasper Ford
// SPDX-License-Identifier: AGPL-3.0-or-later
// Author: Jasper Ford
// See NOTICE file for attribution and acknowledgements.

namespace Nexus\Services;

/**
 * SearchService — Meilisearch integration with MySQL FULLTEXT fallback.
 *
 * Two indexes are maintained:
 *   - listings  (document ID format: "t{tenant_id}_l{listing_id}")
 *   - users     (document ID format: "t{tenant_id}_u{user_id}")
 *
 * All public methods silently degrade (return null/false/[]) when
 * Meilisearch is unavailable so callers can fall back to FULLTEXT.
 *
 * Usage:
 *   SearchService::ensureIndexes();          // once, at sync time
 *   SearchService::indexListing($row);       // upsert on create/update
 *   SearchService::searchListings($q, $id);  // returns int[] or false
 */
class SearchService
{
    private const INDEX_LISTINGS = 'listings';
    private const INDEX_USERS    = 'users';

    // ─── Client ───────────────────────────────────────────────────────────────

    /**
     * Build and return a Meilisearch client, or null if the package is
     * missing (composer install not yet run) or env vars are absent.
     */
    private static function client(): ?\Meilisearch\Client
    {
        if (!class_exists(\Meilisearch\Client::class)) {
            return null;
        }

        $host = $_ENV['MEILISEARCH_HOST'] ?? getenv('MEILISEARCH_HOST') ?: '';
        if (empty($host)) {
            return null;
        }

        $key = $_ENV['MEILISEARCH_KEY'] ?? getenv('MEILISEARCH_KEY') ?: null;

        try {
            return new \Meilisearch\Client($host, $key ?: null);
        } catch (\Throwable $e) {
            error_log('SearchService: failed to create Meilisearch client — ' . $e->getMessage());
            return null;
        }
    }

    // ─── Index setup ─────────────────────────────────────────────────────────

    /**
     * Create indexes if they do not already exist and configure their
     * attributes and ranking rules.
     *
     * Call this once from the sync script — NOT on every request.
     */
    public static function ensureIndexes(): void
    {
        $client = self::client();
        if ($client === null) {
            return;
        }

        try {
            // ── Listings index ─────────────────────────────────────────────
            try {
                $client->createIndex(self::INDEX_LISTINGS, ['primaryKey' => 'id']);
            } catch (\Throwable $e) {
                // Index may already exist — continue
            }

            $listingsIndex = $client->index(self::INDEX_LISTINGS);
            $listingsIndex->updateFilterableAttributes(['tenant_id', 'status']);
            $listingsIndex->updateSearchableAttributes([
                'title', 'description', 'location', 'skills', 'author_name',
            ]);
            $listingsIndex->updateRankingRules([
                'words', 'typo', 'proximity', 'attribute', 'sort', 'exactness',
            ]);

            // ── Users index ────────────────────────────────────────────────
            try {
                $client->createIndex(self::INDEX_USERS, ['primaryKey' => 'id']);
            } catch (\Throwable $e) {
                // Index may already exist — continue
            }

            $usersIndex = $client->index(self::INDEX_USERS);
            $usersIndex->updateFilterableAttributes(['tenant_id', 'status']);
            $usersIndex->updateSearchableAttributes([
                'first_name', 'last_name', 'bio', 'skills', 'location',
            ]);
            $usersIndex->updateRankingRules([
                'words', 'typo', 'proximity', 'attribute', 'sort', 'exactness',
            ]);
        } catch (\Throwable $e) {
            error_log('SearchService::ensureIndexes error — ' . $e->getMessage());
        }
    }

    // ─── Indexing ─────────────────────────────────────────────────────────────

    /**
     * Upsert a listing document into Meilisearch.
     *
     * @param array $listing Keys: id, tenant_id, title, description, location,
     *                       skills, status, author_name (optional).
     */
    public static function indexListing(array $listing): void
    {
        $client = self::client();
        if ($client === null) {
            return;
        }

        try {
            $tenantId  = (int)($listing['tenant_id'] ?? 0);
            $listingId = (int)($listing['id'] ?? 0);

            if ($tenantId === 0 || $listingId === 0) {
                return;
            }

            $document = [
                'id'          => "t{$tenantId}_l{$listingId}",
                'tenant_id'   => $tenantId,
                'listing_id'  => $listingId,
                'title'       => (string)($listing['title'] ?? ''),
                'description' => (string)($listing['description'] ?? ''),
                'location'    => (string)($listing['location'] ?? ''),
                'skills'      => (string)($listing['skills'] ?? ''),
                'status'      => (string)($listing['status'] ?? 'active'),
                'author_name' => (string)($listing['author_name'] ?? ''),
            ];

            $client->index(self::INDEX_LISTINGS)->addDocuments([$document]);
        } catch (\Throwable $e) {
            error_log('SearchService::indexListing error — ' . $e->getMessage());
        }
    }

    /**
     * Upsert a user document into Meilisearch.
     *
     * @param array $user Keys: id, tenant_id, first_name, last_name, bio,
     *                    skills, location, status.
     */
    public static function indexUser(array $user): void
    {
        $client = self::client();
        if ($client === null) {
            return;
        }

        try {
            $tenantId = (int)($user['tenant_id'] ?? 0);
            $userId   = (int)($user['id'] ?? 0);

            if ($tenantId === 0 || $userId === 0) {
                return;
            }

            $document = [
                'id'         => "t{$tenantId}_u{$userId}",
                'tenant_id'  => $tenantId,
                'user_id'    => $userId,
                'first_name' => (string)($user['first_name'] ?? ''),
                'last_name'  => (string)($user['last_name'] ?? ''),
                'bio'        => (string)($user['bio'] ?? ''),
                'skills'     => (string)($user['skills'] ?? ''),
                'location'   => (string)($user['location'] ?? ''),
                'status'     => (string)($user['status'] ?? 'active'),
            ];

            $client->index(self::INDEX_USERS)->addDocuments([$document]);
        } catch (\Throwable $e) {
            error_log('SearchService::indexUser error — ' . $e->getMessage());
        }
    }

    // ─── Deletion ─────────────────────────────────────────────────────────────

    /**
     * Delete a listing document from the Meilisearch index.
     */
    public static function deleteListing(int $listingId, int $tenantId): void
    {
        $client = self::client();
        if ($client === null) {
            return;
        }

        try {
            $client->index(self::INDEX_LISTINGS)->deleteDocument("t{$tenantId}_l{$listingId}");
        } catch (\Throwable $e) {
            error_log('SearchService::deleteListing error — ' . $e->getMessage());
        }
    }

    /**
     * Delete a user document from the Meilisearch index.
     */
    public static function deleteUser(int $userId, int $tenantId): void
    {
        $client = self::client();
        if ($client === null) {
            return;
        }

        try {
            $client->index(self::INDEX_USERS)->deleteDocument("t{$tenantId}_u{$userId}");
        } catch (\Throwable $e) {
            error_log('SearchService::deleteUser error — ' . $e->getMessage());
        }
    }

    // ─── Search ───────────────────────────────────────────────────────────────

    /**
     * Search listings via Meilisearch.
     *
     * @param string $query        Search query string.
     * @param int    $tenantId     Scope results to this tenant.
     * @param int    $limit        Maximum number of results (default 200).
     * @param array  $extraFilters Additional Meilisearch filter expressions.
     * @return int[]|false Array of listing IDs sorted by relevance,
     *                     or false if Meilisearch is unavailable.
     */
    public static function searchListings(
        string $query,
        int $tenantId,
        int $limit = 200,
        array $extraFilters = []
    ): array|false {
        $client = self::client();
        if ($client === null) {
            return false;
        }

        try {
            $filter = "tenant_id = $tenantId AND status = \"active\"";
            if (!empty($extraFilters)) {
                $filter .= ' AND ' . implode(' AND ', $extraFilters);
            }

            $result = $client->index(self::INDEX_LISTINGS)->search($query, [
                'filter'               => $filter,
                'limit'                => $limit,
                'attributesToRetrieve' => ['id'],
            ]);

            $hits = $result->getHits();
            $ids  = [];

            foreach ($hits as $hit) {
                // id format: "t2_l45" — extract the listing id after "_l"
                if (preg_match('/^t\d+_l(\d+)$/', (string)($hit['id'] ?? ''), $m)) {
                    $ids[] = (int)$m[1];
                }
            }

            return $ids;
        } catch (\Throwable $e) {
            error_log('SearchService::searchListings error — ' . $e->getMessage());
            return false;
        }
    }

    /**
     * Search users via Meilisearch.
     *
     * @param string $query        Search query string.
     * @param int    $tenantId     Scope results to this tenant.
     * @param int    $limit        Maximum number of results (default 200).
     * @param array  $extraFilters Additional Meilisearch filter expressions.
     * @return int[]|false Array of user IDs sorted by relevance,
     *                     or false if Meilisearch is unavailable.
     */
    public static function searchUsers(
        string $query,
        int $tenantId,
        int $limit = 200,
        array $extraFilters = []
    ): array|false {
        $client = self::client();
        if ($client === null) {
            return false;
        }

        try {
            $filter = "tenant_id = $tenantId AND status = \"active\"";
            if (!empty($extraFilters)) {
                $filter .= ' AND ' . implode(' AND ', $extraFilters);
            }

            $result = $client->index(self::INDEX_USERS)->search($query, [
                'filter'               => $filter,
                'limit'                => $limit,
                'attributesToRetrieve' => ['id'],
            ]);

            $hits = $result->getHits();
            $ids  = [];

            foreach ($hits as $hit) {
                // id format: "t2_u99" — extract the user id after "_u"
                if (preg_match('/^t\d+_u(\d+)$/', (string)($hit['id'] ?? ''), $m)) {
                    $ids[] = (int)$m[1];
                }
            }

            return $ids;
        } catch (\Throwable $e) {
            error_log('SearchService::searchUsers error — ' . $e->getMessage());
            return false;
        }
    }

    // ─── Health ───────────────────────────────────────────────────────────────

    /**
     * Ping the Meilisearch health endpoint.
     *
     * @return bool True if Meilisearch responds with a healthy status.
     */
    public static function isAvailable(): bool
    {
        $client = self::client();
        if ($client === null) {
            return false;
        }

        try {
            $health = $client->health();
            return ($health['status'] ?? '') === 'available';
        } catch (\Throwable $e) {
            return false;
        }
    }
}
