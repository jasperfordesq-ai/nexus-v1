<?php
// Copyright © 2024–2026 Jasper Ford
// SPDX-License-Identifier: AGPL-3.0-or-later
// Author: Jasper Ford
// See NOTICE file for attribution and acknowledgements.

namespace App\Http\Controllers\GovukAlpha\Concerns;

use App\Core\TenantContext;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Response;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;

/**
 * Search — accessible (GOV.UK) frontend parity methods.
 *
 * Brings the accessible global search up to functional parity with the React
 * SearchPage + AdvancedSearchFilters + SavedSearches components:
 *   - Advanced filters: content type, category, sort order, skills/tags,
 *     date range and location.
 *   - Popular tags carousel (clickable chips that add a skill filter).
 *   - Active filter count badge.
 *   - Result card listing thumbnails.
 *   - Saved searches: list, save the current search, run a saved search,
 *     delete a saved search (with a GOV.UK confirmation step).
 *
 * The read path calls the SAME SearchService::unifiedSearch() the React
 * /api/v2/search endpoint uses. Saved searches mirror the data contract of
 * App\Http\Controllers\Api\SearchController (saved_searches table), tenant +
 * owner scoped exactly as the API enforces.
 *
 * Composed into AlphaController. Trait methods may call its private helpers
 * ($this->view, $this->currentUserId, $this->assertTenantSlug, $this->allowed,
 * self::asStr). Every method name is module-prefixed (search*) and unique
 * across AlphaController and every sibling trait. Services are resolved via
 * app(SomeService::class), never the constructor.
 */
trait SearchParity
{
    /**
     * Content types the unified search service understands. NOTE: the service
     * checks PLURAL keys ($type === 'listings'), so the advanced page uses the
     * plural set — unlike the legacy AlphaController::search() which passes
     * singular values.
     */
    private const SEARCH_TYPES = ['all', 'listings', 'users', 'events', 'groups'];

    /** Sort orders supported by SearchService::applySortOrder(). */
    private const SEARCH_SORTS = ['relevance', 'newest', 'oldest'];

    /**
     * GET /search/advanced
     *
     * The full-featured, no-JS search experience: query box plus an advanced
     * filter panel (type / category / sort / skills / date range / location),
     * a popular-tags carousel, a saved-searches list, and result cards with
     * listing thumbnails. Mirrors React SearchPage.tsx + AdvancedSearchFilters.
     */
    public function searchAdvanced(Request $request, string $tenantSlug): Response|RedirectResponse
    {
        $this->assertTenantSlug($tenantSlug);
        abort_unless(TenantContext::hasFeature('search'), 403);
        $userId = $this->currentUserId();
        if ($userId === null) {
            return redirect()->route('govuk-alpha.login', ['tenantSlug' => $tenantSlug, 'status' => 'auth-required']);
        }

        $tenantId = TenantContext::getId();

        // ---- Read + whitelist every filter from the query string ----------
        $q = trim(self::asStr($request->query('q')));
        $type = $this->allowed($request->query('type'), self::SEARCH_TYPES, 'all');
        $sort = $this->allowed($request->query('sort'), self::SEARCH_SORTS, 'relevance');
        $categoryId = (int) self::asStr($request->query('category_id'));
        if ($categoryId < 0) {
            $categoryId = 0;
        }
        $dateFrom = $this->searchValidDate(self::asStr($request->query('date_from')));
        $dateTo = $this->searchValidDate(self::asStr($request->query('date_to')));
        $location = trim(self::asStr($request->query('location')));
        if (mb_strlen($location) > 120) {
            $location = mb_substr($location, 0, 120);
        }
        $skillsRaw = trim(self::asStr($request->query('skills')));
        $skills = $this->searchNormalizeSkills($skillsRaw);

        $filters = [
            'type' => $type,
            'category_id' => $categoryId,
            'date_from' => $dateFrom,
            'date_to' => $dateTo,
            'location' => $location,
            'sort' => $sort,
            'skills' => implode(',', $skills),
        ];

        // Count of active (non-default) filters — parity with the React badge.
        $activeFilterCount = 0;
        if ($type !== 'all') {
            $activeFilterCount++;
        }
        if ($categoryId > 0) {
            $activeFilterCount++;
        }
        if ($sort !== 'relevance') {
            $activeFilterCount++;
        }
        if ($dateFrom !== '') {
            $activeFilterCount++;
        }
        if ($dateTo !== '') {
            $activeFilterCount++;
        }
        if ($location !== '') {
            $activeFilterCount++;
        }
        if (!empty($skills)) {
            $activeFilterCount++;
        }

        // ---- Run the search (same service the React endpoint calls) -------
        $hasSearched = $q !== '';
        $results = [];
        $total = 0;
        $error = false;
        if ($hasSearched) {
            try {
                $serviceFilters = [
                    'type' => $type,
                    'limit' => 30,
                    'sort' => $sort,
                ];
                if ($categoryId > 0) {
                    $serviceFilters['category_id'] = $categoryId;
                }
                if (!empty($skills)) {
                    $serviceFilters['skills'] = implode(',', $skills);
                }

                $r = app(\App\Services\SearchService::class)->unifiedSearch($q, $userId, $serviceFilters);
                $items = is_array($r['items'] ?? null) ? $r['items'] : [];

                // Date-range + location are not filtered inside SearchService
                // (the React component sends them but the API leaves them
                // un-applied). To make the accessible filters do something
                // honest rather than silently ignore the user, apply them as a
                // light server-side post-filter here.
                $items = $this->searchApplyDateRange($items, $dateFrom, $dateTo);
                $items = $this->searchApplyLocation($items, $location);

                $results = $items;
                $total = count($results);
            } catch (\Throwable $e) {
                report($e);
                $error = true;
            }
        }

        // Group results by type for the tabbed display.
        $grouped = ['listings' => [], 'users' => [], 'events' => [], 'groups' => []];
        foreach ($results as $item) {
            $itType = (string) ($item['type'] ?? '');
            $bucket = match ($itType) {
                'listing' => 'listings',
                'user' => 'users',
                'event' => 'events',
                'group' => 'groups',
                default => null,
            };
            if ($bucket !== null) {
                $grouped[$bucket][] = $item;
            }
        }

        $tab = $this->allowed($request->query('tab'), self::SEARCH_TYPES, 'all');

        // ---- Supporting data: categories, popular tags, saved searches ----
        $categories = $this->searchCategories($tenantId);
        $popularTags = $this->searchPopularTags($skills);
        $savedSearches = $this->searchSavedList($tenantId, $userId);

        return $this->view('accessible-frontend::search-advanced', [
            'title' => __('govuk_alpha_search.advanced.title'),
            'tenantSlug' => $tenantSlug,
            'activeNav' => 'explore',
            'searchQuery' => $q,
            'filters' => $filters,
            'skillsList' => $skills,
            'activeFilterCount' => $activeFilterCount,
            'hasSearched' => $hasSearched,
            'searchError' => $error,
            'grouped' => $grouped,
            'searchTotal' => $total,
            'activeTab' => $tab,
            'categories' => $categories,
            'popularTags' => $popularTags,
            'savedSearches' => $savedSearches,
            'currentUserId' => $userId,
            'status' => $this->allowed(
                $request->query('status'),
                ['search-saved', 'search-deleted', 'search-save-failed', 'search-delete-failed'],
                null
            ),
        ]);
    }

    /**
     * POST /search/saved — save the current query + filters for later re-use.
     *
     * Mirrors SearchController::saveSearch(): tenant + user scoped insert into
     * saved_searches with name + JSON query_params.
     */
    public function searchSaveSearch(Request $request, string $tenantSlug): RedirectResponse
    {
        $this->assertTenantSlug($tenantSlug);
        abort_unless(TenantContext::hasFeature('search'), 403);
        $userId = $this->currentUserId();
        if ($userId === null) {
            return redirect()->route('govuk-alpha.login', ['tenantSlug' => $tenantSlug, 'status' => 'auth-required']);
        }

        $tenantId = TenantContext::getId();

        $name = trim(self::asStr($request->input('name')));
        $queryParams = $this->searchQueryParamsFromRequest($request);
        $back = $this->searchAdvancedUrl($tenantSlug, $queryParams);

        if ($name === '' || ($queryParams['q'] ?? '') === '') {
            return redirect($this->searchAdvancedUrl($tenantSlug, $queryParams, 'search-save-failed'));
        }
        if (mb_strlen($name) > 255) {
            $name = mb_substr($name, 0, 255);
        }

        try {
            DB::table('saved_searches')->insert([
                'tenant_id' => $tenantId,
                'user_id' => $userId,
                'name' => $name,
                'query_params' => json_encode($queryParams),
                'notify_on_new' => 0,
                'created_at' => now(),
                'updated_at' => now(),
            ]);
        } catch (\Throwable $e) {
            report($e);
            return redirect($this->searchAdvancedUrl($tenantSlug, $queryParams, 'search-save-failed'));
        }

        return redirect($this->searchAdvancedUrl($tenantSlug, $queryParams, 'search-saved'));
    }

    /**
     * GET /search/saved/{id}/delete — confirmation page (GOV.UK pattern: a
     * destructive action is confirmed on its own page, never on a bare link).
     */
    public function searchDeleteSavedConfirm(Request $request, string $tenantSlug, int $id): Response|RedirectResponse
    {
        $this->assertTenantSlug($tenantSlug);
        abort_unless(TenantContext::hasFeature('search'), 403);
        $userId = $this->currentUserId();
        if ($userId === null) {
            return redirect()->route('govuk-alpha.login', ['tenantSlug' => $tenantSlug, 'status' => 'auth-required']);
        }

        $tenantId = TenantContext::getId();
        $row = DB::table('saved_searches')
            ->where('id', $id)
            ->where('tenant_id', $tenantId)
            ->first();

        // Cross-tenant / missing → 404; someone else's row → 403.
        abort_if($row === null, 404);
        abort_unless((int) $row->user_id === $userId, 403);

        return $this->view('accessible-frontend::search-saved-delete', [
            'title' => __('govuk_alpha_search.saved.delete_title'),
            'tenantSlug' => $tenantSlug,
            'activeNav' => 'explore',
            'savedSearch' => [
                'id' => (int) $row->id,
                'name' => (string) $row->name,
                'query' => (string) (json_decode((string) $row->query_params, true)['q'] ?? ''),
            ],
        ]);
    }

    /**
     * POST /search/saved/{id}/delete — owner-scoped delete.
     * Mirrors SearchController::deleteSavedSearch().
     */
    public function searchDeleteSaved(Request $request, string $tenantSlug, int $id): RedirectResponse
    {
        $this->assertTenantSlug($tenantSlug);
        abort_unless(TenantContext::hasFeature('search'), 403);
        $userId = $this->currentUserId();
        if ($userId === null) {
            return redirect()->route('govuk-alpha.login', ['tenantSlug' => $tenantSlug, 'status' => 'auth-required']);
        }

        $tenantId = TenantContext::getId();
        $row = DB::table('saved_searches')
            ->where('id', $id)
            ->where('tenant_id', $tenantId)
            ->first();

        abort_if($row === null, 404);
        abort_unless((int) $row->user_id === $userId, 403);

        $deleted = DB::table('saved_searches')
            ->where('id', $id)
            ->where('tenant_id', $tenantId)
            ->where('user_id', $userId)
            ->delete();

        $status = $deleted ? 'search-deleted' : 'search-delete-failed';

        return redirect($this->searchAdvancedUrl($tenantSlug, [], $status));
    }

    /**
     * POST /search/saved/{id}/run — record a run and redirect to the advanced
     * search page pre-loaded with the saved query + filters.
     * Mirrors SearchController::runSavedSearch().
     */
    public function searchRunSaved(Request $request, string $tenantSlug, int $id): RedirectResponse
    {
        $this->assertTenantSlug($tenantSlug);
        abort_unless(TenantContext::hasFeature('search'), 403);
        $userId = $this->currentUserId();
        if ($userId === null) {
            return redirect()->route('govuk-alpha.login', ['tenantSlug' => $tenantSlug, 'status' => 'auth-required']);
        }

        $tenantId = TenantContext::getId();
        $row = DB::table('saved_searches')
            ->where('id', $id)
            ->where('tenant_id', $tenantId)
            ->first();

        abort_if($row === null, 404);
        abort_unless((int) $row->user_id === $userId, 403);

        $params = json_decode((string) $row->query_params, true);
        $params = is_array($params) ? $params : [];

        try {
            DB::table('saved_searches')
                ->where('id', $id)
                ->where('tenant_id', $tenantId)
                ->update([
                    'last_run_at' => now(),
                    'updated_at' => now(),
                ]);
        } catch (\Throwable $e) {
            report($e);
        }

        return redirect($this->searchAdvancedUrl($tenantSlug, $this->searchNormalizeSavedParams($params)));
    }

    // =====================================================================
    // Helpers (all private, search-prefixed where there is collision risk)
    // =====================================================================

    /**
     * Tenant-scoped active listing categories for the category filter, ordered
     * by name. Mirrors the /v2/categories endpoint (type = listing).
     *
     * @return array<int, array{id:int, name:string}>
     */
    private function searchCategories(int $tenantId): array
    {
        try {
            return DB::table('categories')
                ->where('tenant_id', $tenantId)
                ->where('type', 'listing')
                ->where(function ($q) {
                    $q->where('is_active', 1)->orWhereNull('is_active');
                })
                ->orderBy('name')
                ->get(['id', 'name'])
                ->map(fn ($c) => ['id' => (int) $c->id, 'name' => (string) $c->name])
                ->all();
        } catch (\Throwable $e) {
            report($e);
            return [];
        }
    }

    /**
     * Popular listing skill tags (excluding ones already selected), mirroring
     * /v2/listings/tags/popular. Returns a flat list of tag strings.
     *
     * @param string[] $exclude already-selected skills
     * @return string[]
     */
    private function searchPopularTags(array $exclude): array
    {
        try {
            $tags = app(\App\Services\ListingSkillTagService::class)->getPopularTags(10);
            $names = array_map(static fn ($t) => (string) ($t['tag'] ?? ''), $tags);
            $names = array_values(array_filter($names, static fn ($t) => $t !== '' && !in_array($t, $exclude, true)));

            return array_slice($names, 0, 8);
        } catch (\Throwable $e) {
            report($e);
            return [];
        }
    }

    /**
     * The user's saved searches (tenant + owner scoped), newest first.
     * Mirrors SearchController::savedSearches().
     *
     * @return array<int, array{id:int, name:string, query:string, last_result_count:?int}>
     */
    private function searchSavedList(int $tenantId, int $userId): array
    {
        try {
            return DB::table('saved_searches')
                ->where('tenant_id', $tenantId)
                ->where('user_id', $userId)
                ->orderByDesc('created_at')
                ->get()
                ->map(function ($r) {
                    $params = json_decode((string) $r->query_params, true);
                    $params = is_array($params) ? $params : [];

                    return [
                        'id' => (int) $r->id,
                        'name' => (string) $r->name,
                        'query' => (string) ($params['q'] ?? ''),
                        'last_result_count' => $r->last_result_count !== null ? (int) $r->last_result_count : null,
                    ];
                })
                ->all();
        } catch (\Throwable $e) {
            report($e);
            return [];
        }
    }

    /**
     * Normalise a comma-separated skills string into a clean, de-duplicated,
     * lower-cased list. Mirrors AdvancedSearchFilters skill handling.
     *
     * @return string[]
     */
    private function searchNormalizeSkills(string $raw): array
    {
        if ($raw === '') {
            return [];
        }
        $parts = array_map(
            static fn ($s) => mb_strtolower(trim($s)),
            explode(',', $raw)
        );
        $parts = array_values(array_unique(array_filter($parts, static fn ($s) => $s !== '')));

        // Bound the list so a hostile query string can't blow up the SQL IN().
        return array_slice($parts, 0, 20);
    }

    /**
     * Validate a YYYY-MM-DD date string; returns '' if invalid/empty.
     */
    private function searchValidDate(string $value): string
    {
        $value = trim($value);
        if ($value === '') {
            return '';
        }
        if (!preg_match('/^\d{4}-\d{2}-\d{2}$/', $value)) {
            return '';
        }
        try {
            Carbon::createFromFormat('Y-m-d', $value);
        } catch (\Throwable $e) {
            return '';
        }

        return $value;
    }

    /**
     * Server-side date-range post-filter. Events filter on start_time;
     * everything else on created_at. Items with no comparable date pass
     * through (we never hide a result for lacking a timestamp).
     *
     * @param array<int, array<string, mixed>> $items
     * @return array<int, array<string, mixed>>
     */
    private function searchApplyDateRange(array $items, string $dateFrom, string $dateTo): array
    {
        if ($dateFrom === '' && $dateTo === '') {
            return $items;
        }

        $from = $dateFrom !== '' ? Carbon::parse($dateFrom)->startOfDay() : null;
        $to = $dateTo !== '' ? Carbon::parse($dateTo)->endOfDay() : null;

        return array_values(array_filter($items, static function ($item) use ($from, $to) {
            $type = (string) ($item['type'] ?? '');
            $raw = $type === 'event'
                ? ($item['start_time'] ?? $item['start_date'] ?? null)
                : ($item['created_at'] ?? null);
            if (empty($raw)) {
                return true;
            }
            try {
                $when = $raw instanceof \DateTimeInterface ? Carbon::instance($raw) : Carbon::parse((string) $raw);
            } catch (\Throwable $e) {
                return true;
            }
            if ($from !== null && $when->lt($from)) {
                return false;
            }
            if ($to !== null && $when->gt($to)) {
                return false;
            }

            return true;
        }));
    }

    /**
     * Server-side location post-filter (case-insensitive substring on the
     * item's location field). Users have no location field in the search
     * payload, so user results are not affected by a location filter.
     *
     * @param array<int, array<string, mixed>> $items
     * @return array<int, array<string, mixed>>
     */
    private function searchApplyLocation(array $items, string $location): array
    {
        $location = trim($location);
        if ($location === '') {
            return $items;
        }
        $needle = mb_strtolower($location);

        return array_values(array_filter($items, static function ($item) use ($needle) {
            $loc = mb_strtolower(trim((string) ($item['location'] ?? '')));
            if ($loc === '') {
                // Keep items that simply don't carry a location (e.g. users)
                // rather than silently dropping them.
                return true;
            }

            return str_contains($loc, $needle);
        }));
    }

    /**
     * Build the query_params payload to persist for a saved search from the
     * current POST. Only non-default values are stored, matching the React
     * SavedSearches "currentFilters" shape (plus date/location/sort).
     *
     * @return array<string, string>
     */
    private function searchQueryParamsFromRequest(Request $request): array
    {
        $params = [];
        $q = trim(self::asStr($request->input('q')));
        if ($q !== '') {
            $params['q'] = $q;
        }
        $type = $this->allowed($request->input('type'), self::SEARCH_TYPES, 'all');
        if ($type !== 'all') {
            $params['type'] = $type;
        }
        $sort = $this->allowed($request->input('sort'), self::SEARCH_SORTS, 'relevance');
        if ($sort !== 'relevance') {
            $params['sort'] = $sort;
        }
        $categoryId = (int) self::asStr($request->input('category_id'));
        if ($categoryId > 0) {
            $params['category_id'] = (string) $categoryId;
        }
        $skills = $this->searchNormalizeSkills(trim(self::asStr($request->input('skills'))));
        if (!empty($skills)) {
            $params['skills'] = implode(',', $skills);
        }
        $dateFrom = $this->searchValidDate(self::asStr($request->input('date_from')));
        if ($dateFrom !== '') {
            $params['date_from'] = $dateFrom;
        }
        $dateTo = $this->searchValidDate(self::asStr($request->input('date_to')));
        if ($dateTo !== '') {
            $params['date_to'] = $dateTo;
        }
        $location = trim(self::asStr($request->input('location')));
        if ($location !== '') {
            $params['location'] = mb_substr($location, 0, 120);
        }

        return $params;
    }

    /**
     * Re-whitelist a stored saved-search query_params blob before using it to
     * build a URL (defence in depth — never trust persisted JSON blindly).
     *
     * @param array<string, mixed> $params
     * @return array<string, string>
     */
    private function searchNormalizeSavedParams(array $params): array
    {
        $out = [];
        $q = trim(self::asStr($params['q'] ?? ''));
        if ($q !== '') {
            $out['q'] = $q;
        }
        $type = $this->allowed($params['type'] ?? null, self::SEARCH_TYPES, 'all');
        if ($type !== 'all') {
            $out['type'] = $type;
        }
        $sort = $this->allowed($params['sort'] ?? null, self::SEARCH_SORTS, 'relevance');
        if ($sort !== 'relevance') {
            $out['sort'] = $sort;
        }
        $categoryId = (int) self::asStr($params['category_id'] ?? '');
        if ($categoryId > 0) {
            $out['category_id'] = (string) $categoryId;
        }
        $skills = $this->searchNormalizeSkills(trim(self::asStr($params['skills'] ?? '')));
        if (!empty($skills)) {
            $out['skills'] = implode(',', $skills);
        }
        $dateFrom = $this->searchValidDate(self::asStr($params['date_from'] ?? ''));
        if ($dateFrom !== '') {
            $out['date_from'] = $dateFrom;
        }
        $dateTo = $this->searchValidDate(self::asStr($params['date_to'] ?? ''));
        if ($dateTo !== '') {
            $out['date_to'] = $dateTo;
        }
        $location = trim(self::asStr($params['location'] ?? ''));
        if ($location !== '') {
            $out['location'] = mb_substr($location, 0, 120);
        }

        return $out;
    }

    /**
     * Build a /search/advanced URL with the given query params (+ optional
     * status banner), dropping empties.
     *
     * @param array<string, string> $params
     */
    private function searchAdvancedUrl(string $tenantSlug, array $params, ?string $status = null): string
    {
        $query = ['tenantSlug' => $tenantSlug];
        foreach ($params as $key => $value) {
            if ($value !== '' && $value !== null) {
                $query[$key] = $value;
            }
        }
        if ($status !== null) {
            $query['status'] = $status;
        }

        return route('govuk-alpha.search.advanced', $query);
    }
}
