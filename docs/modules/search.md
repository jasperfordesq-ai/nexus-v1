# Search Module

Last reviewed: 2026-07-14

Audience: maintainers working on search indexing, relevance, privacy, or the
search API.

## Architecture

`SearchController` uses `SearchService` for a Meilisearch-first query and
falls back to tenant-scoped SQL `LIKE` queries when Meilisearch is unavailable
or a Meilisearch operation fails.

`SearchService::isAvailable()` caches its first health result in a static
property for the rest of the PHP execution. A long-running sync process retains
that result until it restarts.

Five Meilisearch indexes are maintained:

| Index | Searchable content | Important filters |
| --- | --- | --- |
| `listings` | title, description, location, author, category, skill tags | tenant, active status, approved moderation, category, type, user, skills |
| `users` | names, organisation, bio, skills, location | tenant, account status, profile type |
| `events` | title, description, location, organiser | tenant, lifecycle, occurrence identity, start time |
| `groups` | name, description | tenant, status, privacy |
| `marketplace_listings` | title, description, tagline, location | tenant, category, status, moderation, price, condition, seller and delivery fields |

Every indexed document carries `tenant_id`. `ensureIndexes()` creates or
updates searchable, filterable, sortable, ranking, typo-tolerance, and synonym
settings idempotently. Filter builders validate field names against
`FILTERABLE_ATTRIBUTES` and escape values; never interpolate a
caller-controlled field name into a Meilisearch filter.

Podcasts are deliberately not a sixth index. Unified podcast search uses a
tenant-scoped SQL projection.

## Privacy and visibility

Tenant isolation comes from `tenant_id` predicates on Meilisearch queries and
tenant-scoped Eloquent models or explicit tenant predicates on SQL queries.

- Indexed listings must be active and approved.
- Banned and suspended users are excluded. The dedicated member-directory SQL
  fallback also respects `privacy_search`.
- Events use `App\Support\Events\EventSearchVisibility`: results must belong
  to the tenant, be concrete non-template occurrences, be published, be
  scheduled or postponed, and be upcoming. Meilisearch hits are rehydrated
  through the same database policy before return.
- Indexed unified and suggestion group searches require `privacy = public`.
- Authenticated unified search removes blocked users through
  `BlockUserService::getBlockedPairIds()`.

The SQL degradation path is tenant-safe but is not policy-equivalent to the
indexed path today. Unified and suggestion SQL queries do not reproduce the
public-group predicate, their listing queries do not require approved
moderation, and unified user search does not apply the member-directory
`privacy_search` predicate. Treat this as an operational limitation: restore
Meilisearch promptly and test both execution paths whenever changing visibility
logic.

Podcast results include only published public/member-visible shows and
published, distribution-ready episodes whose show is also visible. Episode
visibility can inherit from its show. Transcript matching is conditional on the
tenant transcript setting, and the returned projection omits creator and
moderation metadata.

## Feature boundary

The React route is gated by the tenant `search` feature in
`react-frontend/src/routes/AppRoutes.tsx`; a disabled tenant is redirected to
`/dashboard`. The authenticated API routes do not apply a separate
`feature:search` middleware, so the client gate is not an authorization
boundary.

## API

The authenticated routes are registered in `routes/api.php`:

| Method | Path | Purpose |
| --- | --- | --- |
| GET | `/v2/search` | Unified search |
| GET | `/v2/search/suggestions` | Autocomplete, minimum two characters |
| GET | `/v2/search/trending` | Tenant trending terms |
| GET | `/v2/search/saved` | List the user's saved searches |
| POST | `/v2/search/saved` | Save a search |
| DELETE | `/v2/search/saved/{id}` | Delete an owned saved search |
| POST | `/v2/search/saved/{id}/run` | Record a saved-search run |

The unified endpoint accepts:

| Parameter | Contract |
| --- | --- |
| `q` | Required; 2 to 500 characters |
| `type` | `all`, `listings`, `users`, `events`, `groups`, or `podcasts` |
| `per_page` | 1 to 50; default 20 |
| `category_id` | Integer listing-category filter |
| `sort` | `relevance`, `newest`, or `oldest` |
| `skills` | Comma-separated skills |
| `cursor` | Accepted for contract compatibility, but not currently consumed |

The response currently always reports `cursor: null` and
`has_more: false`. For `type=all`, each content type has its own
`per_page` cap and the combined list is not globally sliced. Do not describe
this as working cursor pagination.

Marketplace has a maintained Meilisearch index for its own discovery APIs, but
`marketplace` is not an accepted unified search type.

The frontend entry point is
`react-frontend/src/pages/search/SearchPage.tsx`.

## Service map

`app/Services/SearchService.php` owns:

- index creation and settings through `ensureIndexes()`;
- add/update/remove methods for listings, users, events, groups, and marketplace;
- Meilisearch ID searches used by module-specific controllers;
- member-directory search;
- unified search and SQL fallback;
- suggestions and SQL fallback; and
- tenant-scoped trending-query aggregation.

Model/global scopes and `EventSearchVisibility` remain part of the effective
contract; bypassing them with unscoped queries requires an explicit equivalent
predicate.

## Index synchronization

`scripts/sync_search_index.php` backfills or rebuilds indexes. Upserts are
idempotent and the script calls `ensureIndexes()` first.

```bash
# One tenant
php scripts/sync_search_index.php --tenant=2

# All active tenants
php scripts/sync_search_index.php --all-tenants

# One type: listing | user | event | group | marketplace
php scripts/sync_search_index.php --all-tenants --type=event

# Count without writing
php scripts/sync_search_index.php --tenant=2 --dry-run
```

Run a full sync after a bulk import that bypassed normal indexing, index data
loss, schema/settings changes, or evidence of broadly stale results. Do not
deploy or access production merely because a documentation or code task
completed; production actions require explicit authorization.

## Failure and recovery

| Failure | Current behavior | Recovery |
| --- | --- | --- |
| Meilisearch health check fails | Search uses SQL for the rest of that execution | Restore Meilisearch and restart long-running workers/commands |
| A query fails because an index setting is stale | Unified search and suggestions log and fall back to SQL | Run `ensureIndexes()` or the sync script, then verify both paths |
| A document was not indexed during an outage | Module-specific indexed results can be stale | Backfill the affected tenant/type |
| Index data is lost | Requests degrade to SQL | Recreate settings and run an all-tenant sync |

Because the fallback visibility rules differ, monitor fallback activation as a
privacy and quality degradation, not only as a performance warning.

## Admin analytics

Admin search analytics, trending terms, and zero-result queries are exposed by
the registered `/v2/admin/search/*` routes and read tenant-scoped
`search_logs`. Use `routes/api.php` for the current endpoint list and
middleware.

## Validation

```bash
vendor/bin/phpunit tests/Laravel/Feature/Controllers/SearchControllerTest.php
vendor/bin/phpunit --testsuite=Laravel --filter=Search
cd react-frontend && npm test -- SearchPage
npm run check:docs
```

Key regression files:

- `tests/Laravel/Feature/Controllers/SearchControllerTest.php`
- `react-frontend/src/pages/search/SearchPage.test.tsx`
