# Search Module

Audience: maintainers and contributors working on search indexing, relevance, or the search API.

## Architecture

Search uses Meilisearch as the primary engine with an automatic SQL `LIKE` fallback. The fallback activates transparently whenever Meilisearch is unreachable; the caller receives the same response shape either way.

```
Request → SearchController
             ↓
        SearchService::isAvailable()
             ├── true  → Meilisearch query (typo-tolerant, ranked, synonym-aware)
             └── false → SQL LIKE query (exact substring match, no ranking)
```

`isAvailable()` pings Meilisearch once per PHP process lifetime and caches the result in a static property. A transient outage therefore degrades all requests in that worker to SQL, not just the first one; the check resets on the next worker boot.

## Indexed content types

Five Meilisearch indexes are maintained in parallel. Each document carries `tenant_id` so every query is scoped to the current tenant before results reach the caller.

| Index | Primary key | Searchable fields | Key filters |
|-------|-------------|-------------------|-------------|
| `listings` | `id` | title, description, location, author\_name, category\_name, skill\_tags | tenant\_id, status (`active`), moderation\_status (`approved`), category\_id, type, user\_id, skill\_tags |
| `users` | `id` | first\_name, last\_name, organization\_name, bio, skills, location | tenant\_id, status (excludes `banned`/`suspended`), profile\_type |
| `events` | `id` | title, description, location, organizer\_name | tenant\_id, start\_time (future-only), is\_online |
| `groups` | `id` | name, description | tenant\_id, status, privacy |
| `marketplace_listings` | `id` | title, description, tagline, location | tenant\_id, category\_id, status (`active`), moderation\_status (`approved`), price\_type, condition, seller\_type, delivery\_method |

Ranking rules for all indexes follow the Meilisearch default order: `words → typo → proximity → attribute → sort → exactness`.

Typo tolerance thresholds: one typo requires ≥ 5 characters; two typos require ≥ 8. This prevents short names (e.g. "Mary") from matching near-homophones.

Domain synonyms are registered across all five indexes, covering timebanking terminology (timebank/time bank/timebanking/time credit/hour), listing concepts (offer/service/give/teach, request/need/want), and community concepts (volunteer, member, group, event, exchange). See `SearchService::ensureIndexes()` for the full synonym map.

## Privacy and tenant isolation

Every search query includes a `tenant_id` filter built from `TenantContext::getId()`. Results can never cross tenant boundaries.

Additional content-type constraints enforced at query time:

- **Listings** — only `status = active AND moderation_status = approved` are returned.
- **Users** — `banned` and `suspended` accounts are excluded. The SQL fallback for the member-directory path also respects `privacy_search = 1` (opt-in) or `NULL` (default visible), so users who have opted out of search do not appear in the directory search.
- **Events** — only upcoming events (`start_time >= now`) are returned.
- **Groups** — the unified search and autocomplete endpoints additionally filter to `privacy = public`, so private and invite-only groups are not discoverable by non-members. The basic `search()` method does not apply the privacy filter; only `unifiedSearch()` and `suggestions()` do.
- **Blocked users** — `unifiedSearch()` post-filters user results against `BlockUserService::getBlockedPairIds()` so blocked users are hidden from search results.

## Feature gate

The search page is gated behind the `search` feature flag. Tenants with `search` disabled redirect to `/dashboard`.

```tsx
// react-frontend/src/App.tsx
<FeatureGate feature="search" redirect="/dashboard">
  <SearchPage />
</FeatureGate>
```

The API endpoints are not separately gated; feature enforcement is at the route level in the React app.

## API endpoints

All endpoints require a valid `auth:sanctum` session (authentication is inherited from the global middleware group). See `routes/api.php` lines ~580–586 and `app/Http/Controllers/Api/SearchController.php`.

| Method | Path | Description | Rate limit |
|--------|------|-------------|------------|
| GET | `/v2/search` | Unified search across all content types | 60 req/min |
| GET | `/v2/search/suggestions` | Autocomplete for partial queries (min 2 chars) | 120 req/min |
| GET | `/v2/search/trending` | Top query terms from `search_logs` | 30 req/min |
| GET | `/v2/search/saved` | List the authenticated user's saved searches | — |
| POST | `/v2/search/saved` | Save a search by name | — |
| DELETE | `/v2/search/saved/{id}` | Delete a saved search (owner only) | — |
| POST | `/v2/search/saved/{id}/run` | Record a run, updating `last_run_at` | — |

Key query parameters for `GET /v2/search`:

| Param | Values | Default |
|-------|--------|---------|
| `q` | string, min 2 chars, max 500 chars | required |
| `type` | `all`, `listings`, `users`, `events`, `groups` | `all` |
| `per_page` | 1–50 | 20 |
| `category_id` | integer | — |
| `sort` | `relevance`, `newest`, `oldest` | `relevance` |
| `skills` | comma-separated tags | — |

## Frontend entry point

`react-frontend/src/pages/search/SearchPage.tsx` — implements the search UI with type-tab filtering, advanced filter drawer, autocomplete, and saved-search management.

## Backend service

`app/Services/SearchService.php` — all search logic lives here. Key public static methods:

| Method | Purpose |
|--------|---------|
| `isAvailable()` | Ping Meilisearch; cached per process |
| `ensureIndexes()` | Create/update all five indexes with searchable/filterable/sortable/ranking/synonym settings; idempotent |
| `indexListing()`, `indexUser()`, `indexEvent()`, `indexGroup()`, `indexMarketplaceListing()` | Add or update one document; silently no-ops when Meilisearch is unavailable |
| `removeListing()`, `removeUser()`, `removeEvent()`, `removeGroup()`, `removeMarketplaceListing()` | Delete one document from the index |
| `searchListingIds()`, `searchUserIds()`, `searchEventIds()`, `searchGroupIds()`, `searchMarketplaceListingIds()` | Return `{ids, total}` from Meilisearch; return `null` on failure |
| `searchUsersStatic()` | Member-directory search (static, called from `UsersController`); Meilisearch-first then SQL fallback |
| `search()` | Simple per-type search; returns grouped results |
| `unifiedSearch()` | Cursor-paginated flat result list across all types; used by `SearchController` |
| `suggestions()` | Autocomplete (min 2 chars); returns per-type arrays |
| `trending()` | Aggregate `search_logs` for top terms in a lookback window |

The `escapeFilterValue()` / `buildInFilter()` / `buildEqFilter()` / `assertFilterableField()` helpers enforce a whitelist of filterable attributes to prevent filter injection via attacker-controlled field names. Never pass user input as a raw Meilisearch field name.

## Index sync (reindex) operation

Use `scripts/sync_search_index.php` to backfill or fully rebuild the Meilisearch indexes from the database. Meilisearch upserts are idempotent so re-running is always safe.

```bash
# Single tenant
php scripts/sync_search_index.php --tenant=2

# All active tenants
php scripts/sync_search_index.php --all-tenants

# One content type only
php scripts/sync_search_index.php --all-tenants --type=listing
# type values: listing | user | event | group | marketplace

# Dry run (count rows without indexing)
php scripts/sync_search_index.php --tenant=2 --dry-run
```

On production, run via `docker exec` against the active PHP container:

```bash
source .secrets.local/deploy.env
ssh -i "$PROD_SSH_KEY" -o RequestTTY=force "$PROD_SSH_USER@$PROD_SSH_HOST" \
  "sudo docker exec nexus-php-app php scripts/sync_search_index.php --all-tenants"
```

**When to run a full reindex:**

- After bulk data imports or data migrations that bypass Eloquent model events.
- After Meilisearch data loss (container wipe, volume reset).
- When search results appear stale or incomplete across an entire tenant.
- When adding a new tenant (the sync script seeds its indexes immediately).

Under normal operation, documents are kept in sync in real time: `indexListing()` and friends are called from model save/delete paths so individual changes propagate without a manual sync.

The script calls `SearchService::ensureIndexes()` before indexing, so index schema changes (new filterable attributes, synonym updates) are applied automatically on the next sync run.

## Failure modes and recovery

| Failure | Behaviour | Recovery |
|---------|-----------|----------|
| Meilisearch unreachable at request time | `isAvailable()` returns false; all search and suggestions endpoints fall back to SQL LIKE; `unifiedSearch()` logs a warning | Restore Meilisearch service; the next worker boot re-checks availability. Run a full sync if the index has missed real-time updates during the outage. |
| Meilisearch index missing a filterable attribute | `unifiedSearch()` catches the exception, logs a warning, and falls back to SQL | Run `SearchService::ensureIndexes()` (or the sync script) to reconfigure the index settings |
| Index created without a primary key (legacy) | `ensureIndexes()` detects `primaryKey === null`, deletes the index, waits 200 ms, and recreates it | Run the sync script after `ensureIndexes()` to re-populate |
| Document not indexed after a save | Meilisearch was unavailable at write time | Run `php scripts/sync_search_index.php --tenant=<id>` to backfill |
| Meilisearch container wipe | Index data is lost; searches degrade to SQL | Restore the container, then run `php scripts/sync_search_index.php --all-tenants` to rebuild all indexes |

## Admin analytics

Platform super-admins can inspect search behaviour via:

| Endpoint | Path |
|----------|------|
| Search analytics | `GET /v2/admin/search/analytics` |
| Trending terms | `GET /v2/admin/search/trending` |
| Zero-result queries | `GET /v2/admin/search/zero-results` |

These endpoints require `auth:sanctum` + admin middleware. They are served by `AdminListingsController` and read from the `search_logs` table.

## Tests

```bash
# PHP — run from repo root
vendor/bin/phpunit --testsuite=Laravel --filter=Search

# React — run from react-frontend/
npm test -- SearchPage
```

Key test file for the React frontend: `react-frontend/src/pages/search/SearchPage.test.tsx`.
