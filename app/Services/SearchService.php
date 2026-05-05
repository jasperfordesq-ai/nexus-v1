<?php
// Copyright © 2024–2026 Jasper Ford
// SPDX-License-Identifier: AGPL-3.0-or-later
// Author: Jasper Ford
// See NOTICE file for attribution and acknowledgements.

namespace App\Services;

use App\Models\Event;
use App\Models\Group;
use App\Models\Listing;
use App\Models\MarketplaceListing;
use App\Models\User;
use Illuminate\Database\Eloquent\Builder;
use App\Core\TenantContext;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Meilisearch\Client as MeilisearchClient;

/**
 * SearchService — unified search across users, listings, events, and groups.
 *
 * Uses Meilisearch when available (fast, typo-tolerant, ranked results),
 * with automatic SQL LIKE fallback when Meilisearch is unreachable.
 *
 * Meilisearch indexes: `listings`, `users`, `events`, `groups` — all four
 * content types are fully indexed with custom ranking rules and synonyms.
 *
 * Availability is cached per PHP process/request via a static property, so the
 * health-check ping only happens once per request.
 */
class SearchService
{
    /** @var bool|null Cached availability result — null means not yet checked. */
    private static ?bool $available = null;

    public function __construct(
        private readonly User $user,
        private readonly Listing $listing,
        private readonly Event $event,
        private readonly Group $group,
    ) {}

    // =========================================================================
    // Meilisearch client & availability
    // =========================================================================

    private static function client(): MeilisearchClient
    {
        return new MeilisearchClient(
            env('MEILISEARCH_HOST', 'http://meilisearch:7700'),
            env('MEILISEARCH_KEY') ?: null,
        );
    }

    // =========================================================================
    // Filter-string escaping helpers (safe Meilisearch filter construction)
    // =========================================================================

    /**
     * Whitelisted filterable attribute names per index.
     *
     * Field names MUST come from this list — never directly from user input —
     * to prevent filter injection via attacker-controlled field references.
     *
     * Keep in sync with updateFilterableAttributes() calls in ensureIndexes().
     */
    private const FILTERABLE_ATTRIBUTES = [
        'listings'             => ['tenant_id', 'status', 'category_id', 'type', 'user_id', 'skill_tags'],
        'users'                => ['tenant_id', 'status', 'profile_type'],
        'events'               => ['tenant_id', 'status', 'start_time', 'is_online'],
        'groups'               => ['tenant_id', 'status', 'privacy'],
        'marketplace_listings' => ['tenant_id', 'category_id', 'status', 'moderation_status', 'price_type', 'condition', 'seller_type', 'delivery_method'],
        'group_content'        => ['tenant_id', 'group_id', 'content_type'],
    ];

    /**
     * Escape a scalar value for inclusion in a Meilisearch filter string.
     *
     * - Strings are wrapped in double quotes with `\` and `"` backslash-escaped.
     *   The backslash MUST be replaced FIRST, otherwise subsequent replacements
     *   double-escape themselves.
     * - Integers and floats are emitted as bare numerics (Meilisearch accepts
     *   both quoted and unquoted numerics but unquoted is unambiguous).
     * - Booleans become true/false literals.
     *
     * Reference: https://www.meilisearch.com/docs/learn/filtering_and_sorting/filter_expression_reference
     */
    public static function escapeFilterValue(string|int|float|bool $value): string
    {
        if (is_bool($value)) {
            return $value ? 'true' : 'false';
        }
        if (is_int($value) || is_float($value)) {
            return (string) $value;
        }
        // String: escape backslash FIRST, then double-quote
        $escaped = str_replace(['\\', '"'], ['\\\\', '\\"'], $value);
        return '"' . $escaped . '"';
    }

    /**
     * Build an `IN [..]` filter fragment from a list of scalar values.
     * Each value is escaped individually. Returns null for an empty list.
     *
     * @param string $field Whitelisted field name (caller must validate via assertFilterableField)
     * @param array<int, string|int|float|bool> $values
     */
    public static function buildInFilter(string $field, array $values): ?string
    {
        $values = array_values(array_filter($values, fn($v) => $v !== null && $v !== ''));
        if (empty($values)) {
            return null;
        }
        $escaped = array_map([self::class, 'escapeFilterValue'], $values);
        return $field . ' IN [' . implode(', ', $escaped) . ']';
    }

    /**
     * Build a single `field = value` filter fragment.
     *
     * @param string $index One of the keys in FILTERABLE_ATTRIBUTES
     * @param string $field Attribute name (validated against whitelist)
     * @throws \InvalidArgumentException if $field is not whitelisted for $index
     */
    public static function buildEqFilter(string $index, string $field, string|int|float|bool $value): string
    {
        self::assertFilterableField($index, $field);
        return $field . ' = ' . self::escapeFilterValue($value);
    }

    /**
     * Assert that a field name is whitelisted for the given index. Throws on
     * unknown index or unknown field to prevent filter injection via dynamic
     * field names (e.g. user-controlled sort/filter keys).
     */
    public static function assertFilterableField(string $index, string $field): void
    {
        if (!isset(self::FILTERABLE_ATTRIBUTES[$index])) {
            throw new \InvalidArgumentException("Unknown Meilisearch index: {$index}");
        }
        if (!in_array($field, self::FILTERABLE_ATTRIBUTES[$index], true)) {
            throw new \InvalidArgumentException("Field '{$field}' is not filterable on index '{$index}'");
        }
    }

    /**
     * Check if Meilisearch is reachable. Result is cached for the process lifetime.
     * Can be called statically (sync script) or on an instance (DI controller).
     */
    public static function isAvailable(): bool
    {
        if (static::$available !== null) {
            return static::$available;
        }
        try {
            static::client()->health();
            static::$available = true;
        } catch (\Throwable) {
            static::$available = false;
        }
        return static::$available;
    }

    // =========================================================================
    // Index management
    // =========================================================================

    /**
     * Create and configure all four Meilisearch indexes (listings, users, events, groups).
     * Idempotent — safe to call repeatedly. Called by sync_search_index.php.
     *
     * Configures searchable/filterable/sortable attributes, custom ranking rules,
     * and synonyms for each index.
     */
    public static function ensureIndexes(): void
    {
        $client = static::client();

        $configs = [
            'listings' => [
                'pk'         => 'id',
                'searchable' => ['title', 'description', 'location', 'author_name', 'category_name', 'skill_tags'],
                'filterable' => ['tenant_id', 'status', 'category_id', 'type', 'user_id', 'skill_tags'],
                'sortable'   => ['created_at', 'updated_at'],
                'ranking'    => ['words', 'typo', 'proximity', 'attribute', 'sort', 'exactness'],
            ],
            'users' => [
                'pk'         => 'id',
                'searchable' => ['first_name', 'last_name', 'organization_name', 'bio', 'skills', 'location'],
                'filterable' => ['tenant_id', 'status', 'profile_type'],
                'sortable'   => ['created_at'],
                'ranking'    => ['words', 'typo', 'proximity', 'attribute', 'sort', 'exactness'],
            ],
            'events' => [
                'pk'         => 'id',
                'searchable' => ['title', 'description', 'location', 'organizer_name'],
                'filterable' => ['tenant_id', 'status', 'start_time', 'is_online'],
                'sortable'   => ['start_time', 'created_at'],
                'ranking'    => ['words', 'typo', 'proximity', 'attribute', 'sort', 'exactness'],
            ],
            'groups' => [
                'pk'         => 'id',
                'searchable' => ['name', 'description'],
                'filterable' => ['tenant_id', 'status', 'privacy'],
                'sortable'   => ['created_at', 'members_count'],
                'ranking'    => ['words', 'typo', 'proximity', 'attribute', 'sort', 'exactness'],
            ],
            'marketplace_listings' => [
                'pk'         => 'id',
                'searchable' => ['title', 'description', 'tagline', 'location'],
                'filterable' => ['tenant_id', 'category_id', 'status', 'moderation_status', 'price_type', 'condition', 'seller_type', 'delivery_method'],
                'sortable'   => ['created_at', 'price'],
                'ranking'    => ['words', 'typo', 'proximity', 'attribute', 'sort', 'exactness'],
            ],
        ];

        foreach ($configs as $name => $cfg) {
            try {
                $client->createIndex($name, ['primaryKey' => $cfg['pk']]);
            } catch (\Throwable) {
                // Index already exists — update settings only.
                // But: an old index created WITHOUT a primary key will silently drop
                // every addDocuments call with "primary_key_multiple_candidates_found".
                // Detect that pre-existing failure mode and recreate the index so
                // tenants don't end up with a permanently empty search index.
                try {
                    $info = $client->getRawIndex($name);
                    if (!isset($info['primaryKey']) || $info['primaryKey'] === null) {
                        $client->deleteIndex($name);
                        // Wait a beat for async deletion before recreate.
                        usleep(200_000);
                        $client->createIndex($name, ['primaryKey' => $cfg['pk']]);
                    }
                } catch (\Throwable) {
                    // If repair fails, proceed — surface via next indexing attempt.
                }
            }
            $idx = $client->index($name);
            $idx->updateSearchableAttributes($cfg['searchable']);
            $idx->updateFilterableAttributes($cfg['filterable']);
            $idx->updateSortableAttributes($cfg['sortable']);

            if (!empty($cfg['ranking'])) {
                $idx->updateRankingRules($cfg['ranking']);
            }
        }

        // ── Synonyms ──────────────────────────────────────────────────────
        // Applied globally across all indexes — covers timebanking domain
        // terminology, common abbreviations, and community platform concepts.
        $synonyms = [
            // Timebanking terminology
            'timebank'      => ['time bank', 'time banking', 'timebanking'],
            'time bank'     => ['timebank', 'timebanking'],
            'timebanking'   => ['timebank', 'time bank', 'time banking'],
            'time credit'   => ['time credits', 'hour', 'hours', 'credit', 'credits'],
            'time credits'  => ['time credit', 'hours', 'credits'],
            // Listing types
            'offer'         => ['offering', 'service', 'provide', 'give', 'teach'],
            'request'       => ['requesting', 'need', 'want', 'looking for', 'seek'],
            'service'       => ['offer', 'skill', 'help', 'assistance'],
            'listing'       => ['service', 'offer', 'request', 'ad', 'post'],
            // Community concepts
            'volunteer'     => ['volunteering', 'helper', 'helping'],
            'volunteering'  => ['volunteer', 'helping', 'community service'],
            'member'        => ['user', 'person', 'participant', 'neighbour', 'neighbor'],
            'group'         => ['team', 'club', 'circle', 'community'],
            'event'         => ['meeting', 'gathering', 'workshop', 'session', 'activity'],
            'exchange'      => ['swap', 'trade', 'transaction', 'transfer'],
            // Skill-related
            'tutor'         => ['teacher', 'instructor', 'mentor', 'coach'],
            'teacher'       => ['tutor', 'instructor', 'mentor'],
            'repair'        => ['fix', 'mend', 'restore'],
            'fix'           => ['repair', 'mend', 'restore'],
            'gardening'     => ['garden', 'landscaping', 'horticulture', 'planting'],
            'cooking'       => ['baking', 'meal prep', 'culinary', 'food'],
            'transport'     => ['ride', 'lift', 'driving', 'travel'],
            'childcare'     => ['babysitting', 'child minding', 'kids'],
            'tech support'  => ['IT help', 'computer help', 'technology', 'IT support'],
            'computer'      => ['IT', 'technology', 'tech', 'digital'],
            // Marketplace concepts
            'buy'           => ['purchase', 'get', 'acquire'],
            'sell'          => ['selling', 'sale', 'for sale'],
            'used'          => ['second hand', 'secondhand', 'pre-owned', 'preloved'],
            'new'           => ['brand new', 'unused', 'sealed'],
            'free'          => ['giveaway', 'donate', 'donation'],
            'pickup'        => ['collection', 'collect', 'local pickup'],
            'delivery'      => ['shipping', 'post', 'courier', 'ship'],
        ];

        foreach ($configs as $name => $_) {
            try {
                $client->index($name)->updateSynonyms($synonyms);
            } catch (\Throwable) {
                // Synonym update is non-critical — continue silently
            }
        }

        // Configure typo tolerance — require 5+ chars for 1 typo, 8+ for 2.
        // Previous thresholds (4/7) caused short names like "Mary" to match
        // "Marc", "Many", etc., producing irrelevant search results.
        foreach ($configs as $name => $_) {
            try {
                $client->index($name)->updateTypoTolerance([
                    'enabled'             => true,
                    'minWordSizeForTypos' => [
                        'oneTypo'  => 5,
                        'twoTypos' => 8,
                    ],
                ]);
            } catch (\Throwable) {
                // Typo tolerance config is non-critical
            }
        }

        // Mark as available since we just communicated with Meilisearch successfully
        static::$available = true;
    }

    // =========================================================================
    // Document indexing
    // =========================================================================

    /**
     * Index a listing into Meilisearch.
     * Silently skips if Meilisearch is unavailable.
     * Accepts an Eloquent Listing model or a plain array (from sync script).
     */
    public static function indexListing(array|Listing $listing): void
    {
        if (!static::isAvailable()) {
            return;
        }

        $doc = $listing instanceof Listing ? [
            'id'            => $listing->id,
            'tenant_id'     => $listing->tenant_id,
            'user_id'       => $listing->user_id,
            'title'         => $listing->title ?? '',
            'description'   => $listing->description ?? '',
            'location'      => $listing->location ?? '',
            'status'        => $listing->status ?? 'active',
            'moderation_status' => $listing->moderation_status ?? 'approved',
            'type'          => $listing->type,
            'category_id'   => $listing->category_id,
            'category_name' => $listing->category?->name ?? '',
            'author_name'   => $listing->user
                ? trim($listing->user->first_name . ' ' . $listing->user->last_name)
                : '',
            'skill_tags'    => $listing->skillTags->pluck('tag')->all(),
            'created_at'    => $listing->created_at?->timestamp ?? 0,
        ] : $listing;

        $doc['moderation_status'] = $doc['moderation_status'] ?? 'approved';

        static::client()->index('listings')->addDocuments([$doc]);
    }

    /**
     * Index a user into Meilisearch.
     * Silently skips if Meilisearch is unavailable.
     * Accepts an Eloquent User model or a plain array (from sync script).
     */
    public static function indexUser(array|User $user): void
    {
        if (!static::isAvailable()) {
            return;
        }

        $doc = $user instanceof User ? [
            'id'                => $user->id,
            'tenant_id'         => $user->tenant_id,
            'first_name'        => $user->first_name ?? '',
            'last_name'         => $user->last_name ?? '',
            'organization_name' => $user->organization_name ?? '',
            'bio'               => $user->bio ?? '',
            // skills + location are searchable in the index config (ensureIndexes);
            // the Eloquent path previously omitted them so those searches silently
            // failed until the nightly sync ran.
            'skills'            => $user->skills ?? '',
            'location'          => $user->location ?? '',
            'status'            => $user->status ?? 'active',
            'profile_type'      => $user->profile_type ?? 'individual',
            'avatar_url'        => $user->avatar_url ?? '',
            'created_at'        => $user->created_at?->timestamp ?? 0,
        ] : $user;

        static::client()->index('users')->addDocuments([$doc]);
    }

    /**
     * Search listings in Meilisearch and return matching IDs + estimated total.
     *
     * Returns null when Meilisearch is unavailable — callers should fall back to SQL.
     * Pass limit=0 (or limit=1) to get only the total count without IDs.
     *
     * @param  array<string> $extraFilters  Additional Meilisearch filter strings
     * @return array{ids: int[], total: int}|null
     */
    public static function searchListingIds(
        string $term,
        int $tenantId,
        array $extraFilters = [],
        int $limit = 20,
        int $offset = 0,
    ): ?array {
        if (!static::isAvailable()) {
            return null;
        }
        try {
            $filterParts = ["tenant_id = {$tenantId}", "status = 'active'", "moderation_status = 'approved'", ...$extraFilters];
            $result = static::client()->index('listings')->search($term, [
                'filter'               => implode(' AND ', $filterParts),
                'limit'                => max(0, $limit),
                'offset'               => $offset,
                'attributesToRetrieve' => ['id'],
            ]);
            return [
                'ids'   => array_column($result->getHits(), 'id'),
                'total' => $result->getEstimatedTotalHits() ?? count($result->getHits()),
            ];
        } catch (\Throwable) {
            return null;
        }
    }

    /**
     * Index an event into Meilisearch.
     * Silently skips if Meilisearch is unavailable.
     * Accepts an Eloquent Event model or a plain array (from sync script).
     */
    public static function indexEvent(array|Event $event): void
    {
        if (!static::isAvailable()) {
            return;
        }

        $doc = $event instanceof Event ? [
            'id'             => $event->id,
            'tenant_id'      => $event->tenant_id,
            'title'          => $event->title ?? '',
            'description'    => $event->description ?? '',
            'location'       => $event->location ?? '',
            'status'         => $event->status ?? 'active',
            'is_online'      => (bool) ($event->allow_remote_attendance ?? false),
            'organizer_name' => $event->user
                ? trim($event->user->first_name . ' ' . $event->user->last_name)
                : '',
            'start_time'     => $event->start_time?->timestamp ?? $event->start_date?->timestamp ?? 0,
            'created_at'     => $event->created_at?->timestamp ?? 0,
        ] : $event;

        static::client()->index('events')->addDocuments([$doc]);
    }

    /**
     * Index a group into Meilisearch.
     * Silently skips if Meilisearch is unavailable.
     * Accepts an Eloquent Group model or a plain array (from sync script).
     */
    public static function indexGroup(array|Group $group): void
    {
        if (!static::isAvailable()) {
            return;
        }

        $doc = $group instanceof Group ? [
            'id'            => $group->id,
            'tenant_id'     => $group->tenant_id,
            'name'          => $group->name ?? '',
            'description'   => $group->description ?? '',
            'status'        => $group->is_active ? 'active' : 'inactive',
            'privacy'       => $group->visibility ?? 'public',
            'members_count' => $group->cached_member_count ?? $group->active_members_count ?? 0,
            'created_at'    => $group->created_at?->timestamp ?? 0,
        ] : $group;

        static::client()->index('groups')->addDocuments([$doc]);
    }

    /**
     * Remove a listing from the Meilisearch index.
     * Silently skips if Meilisearch is unavailable.
     */
    public static function removeListing(int $listingId): void
    {
        if (!static::isAvailable()) {
            return;
        }
        try {
            static::client()->index('listings')->deleteDocument($listingId);
        } catch (\Throwable) {
            // Non-critical — document may already be absent
        }
    }

    /**
     * Remove a user from the Meilisearch index.
     * Silently skips if Meilisearch is unavailable.
     */
    public static function removeUser(int $userId): void
    {
        if (!static::isAvailable()) {
            return;
        }
        try {
            static::client()->index('users')->deleteDocument($userId);
        } catch (\Throwable) {
            // Non-critical — document may already be absent
        }
    }

    /**
     * Remove an event from the Meilisearch index.
     * Silently skips if Meilisearch is unavailable.
     */
    public static function removeEvent(int $eventId): void
    {
        if (!static::isAvailable()) {
            return;
        }
        try {
            static::client()->index('events')->deleteDocument($eventId);
        } catch (\Throwable) {
            // Non-critical — document may already be absent
        }
    }

    /**
     * Remove a group from the Meilisearch index.
     * Silently skips if Meilisearch is unavailable.
     */
    public static function removeGroup(int $groupId): void
    {
        if (!static::isAvailable()) {
            return;
        }
        try {
            static::client()->index('groups')->deleteDocument($groupId);
        } catch (\Throwable) {
            // Non-critical — document may already be absent
        }
    }

    // =========================================================================
    // Marketplace listing indexing
    // =========================================================================

    /**
     * Index a marketplace listing into Meilisearch.
     * Silently skips if Meilisearch is unavailable.
     * Accepts an Eloquent MarketplaceListing model or a plain array (from sync script).
     */
    public static function indexMarketplaceListing(array|MarketplaceListing $listing): void
    {
        if (!static::isAvailable()) {
            return;
        }

        $doc = $listing instanceof MarketplaceListing ? [
            'id'                => $listing->id,
            'tenant_id'         => $listing->tenant_id,
            'title'             => $listing->title ?? '',
            'description'       => $listing->description ?? '',
            'tagline'           => $listing->tagline ?? '',
            'location'          => $listing->location ?? '',
            'category_id'       => $listing->category_id,
            'price'             => (float) ($listing->price ?? 0),
            'price_type'        => $listing->price_type ?? 'fixed',
            'condition'         => $listing->condition ?? '',
            'seller_type'       => $listing->seller_type ?? 'private',
            'delivery_method'   => $listing->delivery_method ?? 'pickup',
            'status'            => $listing->status ?? 'active',
            'moderation_status' => $listing->moderation_status ?? 'pending',
            'created_at'        => $listing->created_at?->timestamp ?? 0,
        ] : $listing;

        static::client()->index('marketplace_listings')->addDocuments([$doc]);
    }

    /**
     * Remove a marketplace listing from the Meilisearch index.
     * Silently skips if Meilisearch is unavailable.
     */
    public static function removeMarketplaceListing(int $listingId): void
    {
        if (!static::isAvailable()) {
            return;
        }
        try {
            static::client()->index('marketplace_listings')->deleteDocument($listingId);
        } catch (\Throwable) {
            // Non-critical — document may already be absent
        }
    }

    /**
     * Search marketplace listings in Meilisearch and return matching IDs + estimated total.
     *
     * Returns null when Meilisearch is unavailable — callers should fall back to SQL.
     *
     * @param  array<string> $extraFilters  Additional Meilisearch filter strings
     * @return array{ids: int[], total: int}|null
     */
    public static function searchMarketplaceListingIds(
        string $term,
        int $tenantId,
        array $extraFilters = [],
        int $limit = 20,
        int $offset = 0,
    ): ?array {
        if (!static::isAvailable()) {
            return null;
        }
        try {
            $filterParts = [
                "tenant_id = {$tenantId}",
                "status = 'active'",
                "moderation_status = 'approved'",
                ...$extraFilters,
            ];
            $result = static::client()->index('marketplace_listings')->search($term, [
                'filter'               => implode(' AND ', $filterParts),
                'limit'                => max(0, $limit),
                'offset'               => $offset,
                'attributesToRetrieve' => ['id'],
            ]);
            return [
                'ids'   => array_column($result->getHits(), 'id'),
                'total' => $result->getEstimatedTotalHits() ?? count($result->getHits()),
            ];
        } catch (\Throwable) {
            return null;
        }
    }

    /**
     * Search users in Meilisearch and return matching IDs.
     *
     * Returns null when Meilisearch is unavailable — callers should fall back to SQL.
     *
     * @return array{ids: int[], total: int}|null
     */
    public static function searchUserIds(
        string $term,
        int $tenantId,
        int $limit = 200,
        int $offset = 0,
    ): ?array {
        if (!static::isAvailable()) {
            return null;
        }
        try {
            $result = static::client()->index('users')->search($term, [
                'filter'               => "tenant_id = {$tenantId} AND status != 'banned' AND status != 'suspended'",
                'limit'                => max(0, $limit),
                'offset'               => $offset,
                'attributesToRetrieve' => ['id'],
            ]);
            return [
                'ids'   => array_column($result->getHits(), 'id'),
                'total' => $result->getEstimatedTotalHits() ?? count($result->getHits()),
            ];
        } catch (\Throwable) {
            return null;
        }
    }

    /**
     * Search events in Meilisearch and return matching IDs.
     *
     * Returns null when Meilisearch is unavailable — callers should fall back to SQL.
     *
     * @return array{ids: int[], total: int}|null
     */
    public static function searchEventIds(
        string $term,
        int $tenantId,
        int $limit = 20,
        int $offset = 0,
    ): ?array {
        if (!static::isAvailable()) {
            return null;
        }
        try {
            $now = now()->timestamp;
            $result = static::client()->index('events')->search($term, [
                'filter'               => "tenant_id = {$tenantId} AND start_time >= {$now}",
                'limit'                => max(0, $limit),
                'offset'               => $offset,
                'sort'                 => ['start_time:asc'],
                'attributesToRetrieve' => ['id'],
            ]);
            return [
                'ids'   => array_column($result->getHits(), 'id'),
                'total' => $result->getEstimatedTotalHits() ?? count($result->getHits()),
            ];
        } catch (\Throwable) {
            return null;
        }
    }

    /**
     * Search groups in Meilisearch and return matching IDs.
     *
     * Returns null when Meilisearch is unavailable — callers should fall back to SQL.
     *
     * @return array{ids: int[], total: int}|null
     */
    public static function searchGroupIds(
        string $term,
        int $tenantId,
        int $limit = 20,
        int $offset = 0,
    ): ?array {
        if (!static::isAvailable()) {
            return null;
        }
        try {
            $result = static::client()->index('groups')->search($term, [
                'filter'               => "tenant_id = {$tenantId}",
                'limit'                => max(0, $limit),
                'offset'               => $offset,
                'attributesToRetrieve' => ['id'],
            ]);
            return [
                'ids'   => array_column($result->getHits(), 'id'),
                'total' => $result->getEstimatedTotalHits() ?? count($result->getHits()),
            ];
        } catch (\Throwable) {
            return null;
        }
    }

    // =========================================================================
    // Search
    // =========================================================================

    /**
     * Unified search across multiple content types.
     *
     * @param string      $term  Search query.
     * @param string|null $type  Filter: 'users', 'listings', 'events', 'groups', or null for all.
     * @param int         $limit Max results per type.
     * @return array{users?: array, listings?: array, events?: array, groups?: array}
     */
    public function search(string $term, ?string $type = null, int $limit = 10): array
    {
        $limit = min($limit, 50);

        if (static::isAvailable()) {
            return $this->searchViaMeilisearch($term, $type, $limit);
        }

        return $this->searchViaSQL($term, $type, $limit);
    }

    private function searchViaMeilisearch(string $term, ?string $type, int $limit): array
    {
        $client   = static::client();
        $tenantId = TenantContext::getId();
        $results  = [];

        if ($type === null || $type === 'users') {
            $hits = $client->index('users')->search($term, [
                'filter'               => "tenant_id = {$tenantId} AND status != 'banned' AND status != 'suspended'",
                'limit'                => $limit,
                'attributesToRetrieve' => ['id', 'first_name', 'last_name', 'avatar_url', 'organization_name', 'profile_type', 'bio'],
            ])->getHits();

            $results['users'] = array_map(function (array $h) {
                $name = ($h['profile_type'] ?? '') === 'organisation' && !empty($h['organization_name'])
                    ? $h['organization_name']
                    : trim(($h['first_name'] ?? '') . ' ' . ($h['last_name'] ?? ''));
                return [...$h, 'result_type' => 'user', 'name' => $name, 'avatar' => $h['avatar_url'] ?? null, 'tagline' => $h['bio'] ?? null];
            }, $hits);
        }

        if ($type === null || $type === 'listings') {
            $hits = $client->index('listings')->search($term, [
                'filter' => "tenant_id = {$tenantId} AND status = 'active' AND moderation_status = 'approved'",
                'limit'  => $limit,
            ])->getHits();

            $results['listings'] = array_map(fn(array $h) => [...$h, 'result_type' => 'listing'], $hits);
        }

        if ($type === null || $type === 'events') {
            $now = now()->timestamp;
            $hits = $client->index('events')->search($term, [
                'filter'               => "tenant_id = {$tenantId} AND start_time >= {$now}",
                'limit'                => $limit,
                'sort'                 => ['start_time:asc'],
            ])->getHits();

            if (!empty($hits)) {
                // Hydrate from DB to get relations
                $eventIds = array_column($hits, 'id');
                $events = $this->event->newQuery()
                    ->with(['user:id,first_name,last_name,avatar_url'])
                    ->whereIn('id', $eventIds)
                    ->get()
                    ->keyBy('id');

                $results['events'] = [];
                foreach ($eventIds as $eid) {
                    if ($events->has($eid)) {
                        $results['events'][] = [...$events[$eid]->toArray(), 'result_type' => 'event'];
                    }
                }
            } else {
                $results['events'] = [];
            }
        }

        if ($type === null || $type === 'groups') {
            $hits = $client->index('groups')->search($term, [
                'filter' => "tenant_id = {$tenantId}",
                'limit'  => $limit,
            ])->getHits();

            if (!empty($hits)) {
                $groupIds = array_column($hits, 'id');
                $groups = $this->group->newQuery()
                    ->withCount('activeMembers')
                    ->whereIn('id', $groupIds)
                    ->get()
                    ->keyBy('id');

                $results['groups'] = [];
                foreach ($groupIds as $gid) {
                    if ($groups->has($gid)) {
                        $results['groups'][] = [
                            ...$groups[$gid]->toArray(),
                            'result_type'   => 'group',
                            'members_count' => $groups[$gid]->active_members_count,
                        ];
                    }
                }
            } else {
                $results['groups'] = [];
            }
        }

        return $results;
    }

    private function searchViaSQL(string $term, ?string $type, int $limit): array
    {
        $like    = '%' . $term . '%';
        $results = [];

        if ($type === null || $type === 'users') {
            $results['users'] = $this->user->newQuery()
                ->where(function (Builder $q) use ($like) {
                    $q->where('first_name', 'LIKE', $like)
                      ->orWhere('last_name', 'LIKE', $like)
                      ->orWhere('organization_name', 'LIKE', $like)
                      ->orWhereRaw("CONCAT(first_name, ' ', last_name) LIKE ?", [$like]);
                })
                ->whereNotIn('status', ['banned', 'suspended'])
                ->select('id', 'first_name', 'last_name', 'avatar_url', 'organization_name', 'profile_type', 'bio')
                ->limit($limit)
                ->get()
                ->map(fn (User $u) => [
                    ...$u->toArray(),
                    'result_type' => 'user',
                    'name' => ($u->profile_type === 'organisation' && $u->organization_name)
                        ? $u->organization_name
                        : trim($u->first_name . ' ' . $u->last_name),
                    'avatar'  => $u->avatar_url,
                    'tagline' => $u->bio,
                ])
                ->all();
        }

        if ($type === null || $type === 'listings') {
            $results['listings'] = $this->listing->newQuery()
                ->with(['user:id,first_name,last_name,avatar_url', 'category:id,name,color'])
                ->where(function (Builder $q) use ($like) {
                    $q->where('title', 'LIKE', $like)
                      ->orWhere('description', 'LIKE', $like);
                })
                ->where(function (Builder $q) {
                    $q->whereNull('status')->orWhere('status', 'active');
                })
                ->orderByDesc('id')
                ->limit($limit)
                ->get()
                ->map(fn (Listing $l) => [
                    ...$l->toArray(),
                    'result_type'   => 'listing',
                    'category_name' => $l->category?->name,
                ])
                ->all();
        }

        if ($type === null || $type === 'events') {
            $results['events'] = $this->event->newQuery()
                ->with(['user:id,first_name,last_name,avatar_url'])
                ->where(function (Builder $q) use ($like) {
                    $q->where('title', 'LIKE', $like)
                      ->orWhere('description', 'LIKE', $like)
                      ->orWhere('location', 'LIKE', $like);
                })
                ->where('start_time', '>=', now())
                ->orderBy('start_time')
                ->limit($limit)
                ->get()
                ->map(fn (Event $e) => [...$e->toArray(), 'result_type' => 'event'])
                ->all();
        }

        if ($type === null || $type === 'groups') {
            $results['groups'] = $this->group->newQuery()
                ->withCount('activeMembers')
                ->where(function (Builder $q) use ($like) {
                    $q->where('name', 'LIKE', $like)
                      ->orWhere('description', 'LIKE', $like);
                })
                ->orderByDesc('id')
                ->limit($limit)
                ->get()
                ->map(fn (Group $g) => [
                    ...$g->toArray(),
                    'result_type'   => 'group',
                    'members_count' => $g->active_members_count,
                ])
                ->all();
        }

        return $results;
    }

    // =========================================================================
    // Unified search (cursor-paginated, flattened results)
    // =========================================================================

    /**
     * Unified search with cursor pagination and flattened results.
     *
     * @param string   $term    Search query (min 2 chars recommended)
     * @param int|null $userId  Optional authenticated user
     * @param array    $filters type, cursor, limit, category_id, sort, skills
     * @return array{items: array, cursor: string|null, has_more: bool, total: int, query: string}
     */
    public function unifiedSearch(string $term, ?int $userId = null, array $filters = []): array
    {
        $limit = min((int) ($filters['limit'] ?? 20), 50);
        $type  = $filters['type'] ?? 'all';
        $sort  = $filters['sort'] ?? 'relevance';

        if (static::isAvailable()) {
            $result = $this->unifiedSearchViaMeilisearch($term, $filters, $limit, $type, $sort);
        } else {
            $result = $this->unifiedSearchViaSQL($term, $filters, $limit, $type, $sort);
        }

        // Filter out blocked users from search results
        if ($userId && ($type === 'all' || $type === 'users')) {
            $blockedIds = BlockUserService::getBlockedPairIds($userId);
            if (!empty($blockedIds)) {
                $result['items'] = array_values(array_filter($result['items'], function ($item) use ($blockedIds) {
                    if (($item['type'] ?? '') === 'user') {
                        return !in_array((int) ($item['id'] ?? 0), $blockedIds, true);
                    }
                    return true;
                }));
                $result['total'] = count($result['items']);
            }
        }

        return $result;
    }

    private function unifiedSearchViaMeilisearch(string $term, array $filters, int $limit, string $type, string $sort): array
    {
        $client   = static::client();
        $tenantId = TenantContext::getId();
        $allItems = [];
        $like     = '%' . $term . '%';

        if ($type === 'all' || $type === 'listings') {
            $filterParts = ["tenant_id = {$tenantId}", "status = 'active'", "moderation_status = 'approved'"];
            if (!empty($filters['category_id'])) {
                $filterParts[] = 'category_id = ' . (int) $filters['category_id'];
            }
            $hits = $client->index('listings')->search($term, [
                'filter' => implode(' AND ', $filterParts),
                'limit'  => $limit,
            ])->getHits();
            foreach ($hits as $h) {
                $allItems[] = [...$h, 'type' => 'listing', 'listing_type' => $h['type'] ?? null];
            }
        }

        if ($type === 'all' || $type === 'users') {
            $hits = $client->index('users')->search($term, [
                'filter'               => "tenant_id = {$tenantId} AND status != 'banned' AND status != 'suspended'",
                'limit'                => $limit,
                'attributesToRetrieve' => ['id', 'first_name', 'last_name', 'avatar_url', 'organization_name', 'profile_type', 'bio', 'created_at'],
            ])->getHits();
            foreach ($hits as $h) {
                $name = ($h['profile_type'] ?? '') === 'organisation' && !empty($h['organization_name'])
                    ? $h['organization_name']
                    : trim(($h['first_name'] ?? '') . ' ' . ($h['last_name'] ?? ''));
                $allItems[] = [...$h, 'type' => 'user', 'name' => $name, 'avatar' => $h['avatar_url'] ?? null, 'tagline' => $h['bio'] ?? null];
            }
        }

        if ($type === 'all' || $type === 'events') {
            $now = now()->timestamp;
            $eventSort = $sort === 'newest' ? ['start_time:desc'] : ['start_time:asc'];
            $hits = $client->index('events')->search($term, [
                'filter' => "tenant_id = {$tenantId} AND start_time >= {$now}",
                'limit'  => $limit,
                'sort'   => $eventSort,
            ])->getHits();

            if (!empty($hits)) {
                $eventIds = array_column($hits, 'id');
                $events = $this->event->newQuery()
                    ->with(['user:id,first_name,last_name,avatar_url'])
                    ->whereIn('id', $eventIds)
                    ->get()
                    ->keyBy('id');
                foreach ($eventIds as $eid) {
                    if ($events->has($eid)) {
                        $allItems[] = [...$events[$eid]->toArray(), 'type' => 'event'];
                    }
                }
            }
        }

        if ($type === 'all' || $type === 'groups') {
            // Exclude private / invite-only groups from search so non-members
            // cannot discover their names, sizes, or descriptions.
            $hits = $client->index('groups')->search($term, [
                'filter' => "tenant_id = {$tenantId} AND privacy = 'public'",
                'limit'  => $limit,
            ])->getHits();

            if (!empty($hits)) {
                $groupIds = array_column($hits, 'id');
                $groups = $this->group->newQuery()
                    ->withCount('activeMembers')
                    ->whereIn('id', $groupIds)
                    ->get()
                    ->keyBy('id');
                foreach ($groupIds as $gid) {
                    if ($groups->has($gid)) {
                        $allItems[] = [...$groups[$gid]->toArray(), 'type' => 'group', 'members_count' => $groups[$gid]->active_members_count];
                    }
                }
            }
        }

        // When searching all types, each type already has its own $limit cap.
        // Do NOT globally slice — that biases toward whichever type is first
        // (listings) and silently drops users/events/groups from the response.
        // The frontend handles per-tab display limits (4 per category on "All" tab).
        return [
            'items'    => $allItems,
            'cursor'   => null,
            'has_more' => false,
            'total'    => count($allItems),
            'query'    => $term,
        ];
    }

    private function unifiedSearchViaSQL(string $term, array $filters, int $limit, string $type, string $sort): array
    {
        $like     = '%' . $term . '%';
        $allItems = [];

        if ($type === 'all' || $type === 'listings') {
            $lq = $this->listing->newQuery()
                ->with(['user:id,first_name,last_name,avatar_url', 'category:id,name,color'])
                ->where(function (Builder $q) use ($like) {
                    $q->where('title', 'LIKE', $like)
                      ->orWhere('description', 'LIKE', $like);
                })
                ->where(function (Builder $q) {
                    $q->whereNull('status')->orWhere('status', 'active');
                });

            if (!empty($filters['category_id'])) {
                $lq->where('category_id', (int) $filters['category_id']);
            }

            if (!empty($filters['skills'])) {
                $skills = is_array($filters['skills']) ? $filters['skills'] : explode(',', $filters['skills']);
                $skills = array_map(fn ($s) => strtolower(trim($s)), array_filter($skills));
                if (!empty($skills)) {
                    $lq->whereHas('skillTags', fn (Builder $q) => $q->whereIn('tag', $skills));
                }
            }

            $this->applySortOrder($lq, $sort);
            foreach ($lq->limit($limit)->get() as $l) {
                $allItems[] = [
                    ...$l->toArray(),
                    'type'          => 'listing',
                    'listing_type'  => $l->type,
                    'category_name' => $l->category?->name,
                ];
            }
        }

        if ($type === 'all' || $type === 'users') {
            $uq = $this->user->newQuery()
                ->where(function (Builder $q) use ($like) {
                    $q->where('first_name', 'LIKE', $like)
                      ->orWhere('last_name', 'LIKE', $like)
                      ->orWhere('organization_name', 'LIKE', $like)
                      ->orWhereRaw("CONCAT(first_name, ' ', last_name) LIKE ?", [$like]);
                })
                ->whereNotIn('status', ['banned', 'suspended'])
                ->select('id', 'first_name', 'last_name', 'avatar_url', 'organization_name', 'profile_type', 'bio', 'created_at');

            $this->applySortOrder($uq, $sort);
            foreach ($uq->limit($limit)->get() as $u) {
                $name = ($u->profile_type === 'organisation' && $u->organization_name)
                    ? $u->organization_name
                    : trim($u->first_name . ' ' . $u->last_name);
                $allItems[] = [
                    ...$u->toArray(),
                    'type'    => 'user',
                    'name'    => $name,
                    'avatar'  => $u->avatar_url,
                    'tagline' => $u->bio,
                ];
            }
        }

        if ($type === 'all' || $type === 'events') {
            $eq = $this->event->newQuery()
                ->with(['user:id,first_name,last_name,avatar_url'])
                ->where(function (Builder $q) use ($like) {
                    $q->where('title', 'LIKE', $like)
                      ->orWhere('description', 'LIKE', $like)
                      ->orWhere('location', 'LIKE', $like);
                })
                ->where('start_time', '>=', now());

            $this->applySortOrder($eq, $sort, 'start_time');
            foreach ($eq->limit($limit)->get() as $e) {
                $allItems[] = [...$e->toArray(), 'type' => 'event'];
            }
        }

        if ($type === 'all' || $type === 'groups') {
            $gq = $this->group->newQuery()
                ->withCount('activeMembers')
                ->where(function (Builder $q) use ($like) {
                    $q->where('name', 'LIKE', $like)
                      ->orWhere('description', 'LIKE', $like);
                });

            $this->applySortOrder($gq, $sort);
            foreach ($gq->limit($limit)->get() as $g) {
                $allItems[] = [
                    ...$g->toArray(),
                    'type'          => 'group',
                    'members_count' => $g->active_members_count,
                ];
            }
        }

        // Same as Meilisearch path — do NOT globally slice. Each type has its
        // own $limit cap, and the frontend handles per-tab display limits.
        return [
            'items'    => $allItems,
            'cursor'   => null,
            'has_more' => false,
            'total'    => count($allItems),
            'query'    => $term,
        ];
    }

    // =========================================================================
    // Suggestions (autocomplete)
    // =========================================================================

    /**
     * Get autocomplete suggestions for a partial query.
     *
     * @return array{listings: array, users: array, events: array, groups: array}
     */
    public function suggestions(string $term, int $limit = 5): array
    {
        if (strlen($term) < 2) {
            return ['listings' => [], 'users' => [], 'events' => [], 'groups' => []];
        }

        if (static::isAvailable()) {
            return $this->suggestionsViaMeilisearch($term, $limit);
        }

        return $this->suggestionsViaSQL($term, $limit);
    }

    private function suggestionsViaMeilisearch(string $term, int $limit): array
    {
        $client   = static::client();
        $tenantId = TenantContext::getId();

        $listings = $client->index('listings')->search($term, [
            'filter'               => "tenant_id = {$tenantId} AND status = 'active' AND moderation_status = 'approved'",
            'limit'                => $limit,
            'attributesToRetrieve' => ['id', 'title', 'type'],
        ])->getHits();

        $userHits = $client->index('users')->search($term, [
            'filter'               => "tenant_id = {$tenantId} AND status != 'banned' AND status != 'suspended'",
            'limit'                => $limit,
            'attributesToRetrieve' => ['id', 'first_name', 'last_name', 'avatar_url', 'organization_name', 'profile_type'],
        ])->getHits();
        $users = array_map(function (array $h) {
            $name = ($h['profile_type'] ?? '') === 'organisation' && !empty($h['organization_name'])
                ? $h['organization_name']
                : trim(($h['first_name'] ?? '') . ' ' . ($h['last_name'] ?? ''));
            return [...$h, 'name' => $name];
        }, $userHits);

        $now = now()->timestamp;
        $events = $client->index('events')->search($term, [
            'filter'               => "tenant_id = {$tenantId} AND start_time >= {$now}",
            'limit'                => $limit,
            'sort'                 => ['start_time:asc'],
            'attributesToRetrieve' => ['id', 'title', 'start_time'],
        ])->getHits();

        // Autocomplete must also hide private groups from non-members.
        $groups = $client->index('groups')->search($term, [
            'filter'               => "tenant_id = {$tenantId} AND privacy = 'public'",
            'limit'                => $limit,
            'attributesToRetrieve' => ['id', 'name'],
        ])->getHits();

        return compact('listings', 'users', 'events', 'groups');
    }

    private function suggestionsViaSQL(string $term, int $limit): array
    {
        $like = '%' . $term . '%';

        $listings = $this->listing->newQuery()
            ->where('title', 'LIKE', $like)
            ->where(function (Builder $q) {
                $q->whereNull('status')->orWhere('status', 'active');
            })
            ->select('id', 'title', 'type')
            ->orderByDesc('id')
            ->limit($limit)
            ->get()
            ->toArray();

        $users = $this->user->newQuery()
            ->where(function (Builder $q) use ($like) {
                $q->where('first_name', 'LIKE', $like)
                  ->orWhere('last_name', 'LIKE', $like)
                  ->orWhere('organization_name', 'LIKE', $like);
            })
            ->whereNotIn('status', ['banned', 'suspended'])
            ->select('id', 'first_name', 'last_name', 'avatar_url', 'organization_name', 'profile_type')
            ->limit($limit)
            ->get()
            ->map(function (User $u) {
                $name = ($u->profile_type === 'organisation' && $u->organization_name)
                    ? $u->organization_name
                    : trim($u->first_name . ' ' . $u->last_name);
                return [...$u->toArray(), 'name' => $name];
            })
            ->all();

        $events = $this->event->newQuery()
            ->where('title', 'LIKE', $like)
            ->where('start_time', '>=', now())
            ->select('id', 'title', 'start_time')
            ->orderBy('start_time')
            ->limit($limit)
            ->get()
            ->toArray();

        $groups = $this->group->newQuery()
            ->where('name', 'LIKE', $like)
            ->select('id', 'name')
            ->orderByDesc('id')
            ->limit($limit)
            ->get()
            ->toArray();

        return compact('listings', 'users', 'events', 'groups');
    }

    // =========================================================================
    // Trending
    // =========================================================================

    /**
     * Get trending search terms from the search_logs table.
     *
     * @param int $days  Look-back period in days
     * @param int $limit Max terms to return
     * @return array Array of {term, count}
     */
    public function trending(int $days = 7, int $limit = 10): array
    {
        try {
            return DB::table('search_logs')
                ->select('query as term', DB::raw('COUNT(*) as count'))
                ->where('tenant_id', TenantContext::getId())
                ->where('created_at', '>=', now()->subDays($days))
                ->groupBy('query')
                ->orderByDesc('count')
                ->limit($limit)
                ->get()
                ->toArray();
        } catch (\Exception $e) {
            Log::warning('[SearchService] Failed to fetch trending queries: ' . $e->getMessage());
            return [];
        }
    }

    // =========================================================================
    // Static user search (used by UsersController member list)
    // =========================================================================

    /**
     * Static user search — used by UsersController member directory.
     *
     * Tries Meilisearch first for typo-tolerant, synonym-aware results.
     * Falls back to SQL LIKE if Meilisearch is unavailable.
     */
    public static function searchUsersStatic(string $query, int $tenantId, int $limit = 200, array $extraFilters = []): array|false
    {
        $limit = min($limit, 200);

        // Try Meilisearch first for typo-tolerant, ranked results.
        // If Meili returns non-empty hits, trust them. Otherwise fall back to SQL
        // so an empty/broken index doesn't silently hide all members.
        $meiliResult = static::searchUserIds($query, $tenantId, $limit);
        if ($meiliResult !== null && !empty($meiliResult['ids'])) {
            return $meiliResult['ids'];
        }

        // Fall back to SQL LIKE (also runs when Meili is available but empty).
        $like = '%' . $query . '%';
        return User::where('tenant_id', $tenantId)
            ->where(function ($q) use ($like) {
                $q->where('first_name', 'LIKE', $like)
                  ->orWhere('last_name', 'LIKE', $like)
                  ->orWhere('organization_name', 'LIKE', $like)
                  ->orWhereRaw("CONCAT(first_name, ' ', last_name) LIKE ?", [$like]);
            })
            ->whereNotIn('status', ['banned', 'suspended'])
            // Respect search-opt-out (matches SQL path in UsersController::index).
            ->where(function ($q) {
                $q->where('privacy_search', 1)->orWhereNull('privacy_search');
            })
            ->limit($limit)
            ->pluck('id')
            ->all();
    }

    // =========================================================================
    // Private helpers
    // =========================================================================

    private function applySortOrder(Builder $query, string $sort, string $dateColumn = 'created_at'): void
    {
        match ($sort) {
            'newest' => $query->orderByDesc($dateColumn),
            'oldest' => $query->orderBy($dateColumn),
            default  => $query->orderByDesc('id'),
        };
    }
}
