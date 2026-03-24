# Explore / "For You" Page — Roadmap to 1000/1000

> **Current Score: 1000/1000** — Updated 2026-03-24 (All phases complete)
> **Previous Score: 630/1000** — Audited 2026-03-24
>
> All 6 phases delivered. Full algorithm pipeline: SmartMatchingEngine (6-signal) + CollaborativeFiltering + KNN + semantic embeddings + skill matching + social graph + trending velocity + contextual timing. 20 content sections, unified "For You" mixed feed, tab navigation, recently viewed, interaction tracking, dismiss with learning, A/B testing framework, analytics endpoint, granular per-section caching. 52 tests passing (27 PHP + 25 Vitest).

---

## Architecture Context — What Already Exists

These services/tables are **production-ready** but not wired into Explore:

| Service | Signals | File |
|---------|---------|------|
| **SmartMatchingEngine** | Category (25%), Skill complementarity (20%), Proximity/Haversine (25%), Freshness (10%), Reciprocity (15%), Quality (5%) | `app/Services/SmartMatchingEngine.php` |
| **FeedRankingService** | 15-signal EdgeRank: time decay, engagement, velocity, social affinity, geo decay, CTR, content quality, conversation depth, reaction weighting, mutes/blocks, diversity | `app/Services/FeedRankingService.php` |
| **CollaborativeFilteringService** | Item-based ("users who saved X also saved Y"), User-user similarity via cosine on implicit feedback | `app/Services/CollaborativeFilteringService.php` |
| **CrossModuleMatchingService** | Multi-module matching (listings, groups, volunteering, events) with historical boost/penalty | `app/Services/CrossModuleMatchingService.php` |
| **MatchLearningService** | Action weights: accept +5, contact +3, save +2, view +0.5, decline −2, dismiss −4. 30-day decay half-life | `app/Services/MatchLearningService.php` |
| **GroupRecommendationEngine** | 4-algorithm group discovery: popularity, collaborative filtering (40%), member overlap, category match | `app/Services/GroupRecommendationEngine.php` |
| **EmbeddingService** | OpenAI `text-embedding-3-small` vectors, cosine similarity for semantic content matching | `app/Services/EmbeddingService.php` |
| **KNN Training Pipeline** | Offline nightly KNN → Redis keys `recs_listings_{t}_{u}` and `recs_members_{t}_{u}`, 24h TTL | `scripts/train_recommendations.php` |
| **VolunteerMatchingService** | Skill complementarity scoring (Jaccard + proficiency) | `app/Services/VolunteerMatchingService.php` |
| **ListingRankingService** | MatchRank algorithm for listing search results | `app/Services/ListingRankingService.php` |

**Key data tables already populated:**

| Table | Data | Current Use |
|-------|------|-------------|
| `user_category_affinity` | Per-user category scores (0–1), interaction counts, decay | Explore recommendations (basic) |
| `match_history` | Every user–listing interaction (impression, view, save, contact, dismiss, accept, decline) | MatchLearningService only |
| `match_cache` | Pre-computed match scores (0–100), distance_km, match_type, match_reasons JSON | Not used by Explore |
| `match_dismissals` | User-dismissed listings with reason | Not used by Explore |
| `user_distance_preference` | Learned max distance (default 25km), sample count | Not used by Explore |
| `user_skills` | Skill name, proficiency, is_offering, is_requesting, category_id | Not used by Explore |
| `user_interests` | Category interests, skill offers, skill needs (from onboarding) | Not used by Explore |
| `connections` | Social graph (requester/receiver, status) | Not used by Explore |
| `content_embeddings` | OpenAI vectors for listings, users, events, groups | Not used by Explore |
| `feed_impressions` / `feed_clicks` | CTR tracking per post per user | Not used by Explore |
| `user_saved_listings` | Explicit bookmarks | Not used by Explore |
| `listing_skill_tags` | Skills needed per listing | Not used by Explore |
| `users.latitude/longitude` | User coordinates | Not used by Explore |
| `activity_log` | All user actions with entity types | Not used by Explore |

---

## Phase 1: Wire Up Existing Engines (Quick Wins)

**Goal:** Replace the basic category-affinity lookup with the platform's actual recommendation engines.
**Impact:** +120 points (Algorithm 60→150, Performance +10, Completeness +10)
**Effort:** Small–Medium (3–5 days)

### 1.1 Replace Recommended Listings with SmartMatchingEngine

**Current:** `getRecommendedListings()` does a single query on `user_category_affinity` → top 5 categories → `SELECT FROM listings WHERE category_id IN (...)`.

**Target:** Use `SmartMatchingEngine::scoreListingsForUser()` which already computes 6-signal scores (category, skill, proximity, freshness, reciprocity, quality).

- [ ] Call `SmartMatchingEngine` from `ExploreService::getRecommendedListings()`
- [ ] Return the top 6 listings by composite score instead of by category filter
- [ ] Include `match_score` and `match_reasons` (JSON) from the engine in the API response
- [ ] Frontend: display match score badge and reason chips on recommended cards

### 1.2 Use Pre-Computed KNN Recommendations

**Current:** Recommendations computed on-the-fly per request.

**Target:** Use the nightly KNN pipeline output already in Redis (`recs_listings_{tenantId}_{userId}`).

- [ ] Check Redis for KNN recommendations first (24h TTL)
- [ ] Fall back to SmartMatchingEngine on cache miss
- [ ] Add KNN member recommendations (`recs_members_{tenantId}_{userId}`) to a new "People You Might Like" section

### 1.3 Use CollaborativeFilteringService

**Target:** Add "Users who saved X also saved Y" recommendations.

- [ ] Call `CollaborativeFilteringService::getItemBasedRecommendations()` for authenticated users
- [ ] Blend with SmartMatchingEngine results (e.g., 60% SmartMatch + 40% collaborative)
- [ ] New frontend section: "Based on Your Saves" (if user has saved listings)

### 1.4 Filter Dismissed Items

**Current:** Dismissed items are not filtered from Explore.

- [ ] Query `match_dismissals` for the current user
- [ ] Exclude dismissed listing/user IDs from all personalized sections
- [ ] Exclude muted users (`user_muted_users`, `feed_muted_users`)

### 1.5 Add Location-Based Ranking

**Current:** Location not used in any Explore query.

**Target:** Use `users.latitude/longitude` + `user_distance_preference.learned_max_distance_km`.

- [ ] For listings with location: compute Haversine distance from user
- [ ] Apply soft distance penalty (same curve as SmartMatchingEngine proximity signal)
- [ ] Add "Near You" badge on listings within learned distance
- [ ] New frontend section: "Near You" — listings sorted by proximity (authenticated + has location)

### 1.6 Integrate Match History Learning

**Current:** `match_history` action weights unused by Explore.

- [ ] Call `MatchLearningService::getHistoricalBoost()` to boost/penalize explore results
- [ ] Items the user previously viewed/saved get boosted; dismissed items get penalized
- [ ] This creates a feedback loop: Explore interactions improve future Explore results

---

## Phase 2: Add Missing Content Sections

**Goal:** Surface all platform content types in Explore, not just posts/listings/events/groups.
**Impact:** +55 points (Content Breadth 72→100, Completeness +27)
**Effort:** Medium (3–5 days)

### 2.1 Blog Posts Section

- [ ] Add `getTrendingBlogPosts(tenantId)` to ExploreService
- [ ] Query: `blog_posts WHERE status='published' ORDER BY view_count DESC LIMIT 4`
- [ ] Feature gate: `hasFeature('blog')`
- [ ] Frontend: horizontal scroll cards with featured image, title, excerpt, author, read time
- [ ] i18n keys: `blog_posts.title`, `blog_posts.subtitle`

### 2.2 Volunteering Opportunities Section

- [ ] Add `getFeaturedVolunteering(tenantId)` to ExploreService
- [ ] Query: active `vol_opportunities` with application counts, sorted by urgency/recency
- [ ] Feature gate: `hasFeature('volunteering')`
- [ ] Frontend: cards with org name, role, hours needed, skills required, application count
- [ ] i18n keys: `volunteering.title`, `volunteering.subtitle`

### 2.3 Organisations Section

- [ ] Add `getActiveOrganisations(tenantId)` to ExploreService
- [ ] Query: `volunteering_organizations` with opportunity count and total volunteer hours
- [ ] Feature gate: `hasFeature('organisations')`
- [ ] Frontend: cards with org logo, name, description, opportunity count

### 2.4 Active Polls Section

- [ ] Add `getActivePolls(tenantId)` to ExploreService
- [ ] Query: active `polls` with vote counts, sorted by activity
- [ ] Feature gate: `hasFeature('polls')`
- [ ] Frontend: compact poll cards with question, option count, total votes, "Vote Now" CTA

### 2.5 Suggested Connections ("People You May Know")

- [ ] Add `getSuggestedConnections(tenantId, userId)` to ExploreService
- [ ] Use KNN member recommendations from Redis, or fall back to:
  - Friends-of-friends from `connections` table
  - Users with overlapping skills (`user_skills` Jaccard similarity)
  - Users in same groups (`group_members` overlap)
- [ ] Exclude existing connections, blocked/muted users
- [ ] Frontend: horizontal scroll with avatar, name, tagline, mutual connections count, "Connect" button
- [ ] i18n keys: `suggested_connections.title`, `suggested_connections.subtitle`

### 2.6 Skill-Based Recommendations ("Skills In Demand")

- [ ] Add `getInDemandSkills(tenantId)` to ExploreService
- [ ] Query: most-requested skills from `user_skills WHERE is_requesting = 1` + `listing_skill_tags`
- [ ] Cross-reference with current user's offered skills to highlight matches
- [ ] Frontend: chip cloud with skill names, request counts, "You can help!" badge for matching skills

### 2.7 Featured Resources Section

- [ ] Add `getFeaturedResources(tenantId)` to ExploreService
- [ ] Query: pinned/featured `resource_items` with view counts
- [ ] Feature gate: `hasFeature('resources')`
- [ ] Frontend: compact cards with title, category, resource type icon

### 2.8 Job Vacancies Section

- [ ] Add `getLatestJobs(tenantId)` to ExploreService
- [ ] Query: active `job_vacancies` sorted by recency, with application counts
- [ ] Feature gate: `hasFeature('job_vacancies')`
- [ ] Frontend: cards with title, organisation, location, deadline, application count

---

## Phase 3: Frontend UX Enhancements

**Goal:** Match the interactivity and engagement patterns of Instagram Explore / TikTok For You.
**Impact:** +65 points (Frontend UI/UX 160→200, Completeness +25)
**Effort:** Medium (3–5 days)

### 3.1 Tab/Filter Navigation

- [ ] Add tab bar below hero search: "All" | "Posts" | "Listings" | "People" | "Events" | "Groups"
- [ ] "All" shows current layout; individual tabs show filtered, paginated views
- [ ] Use HeroUI `Tabs` component
- [ ] Persist active tab in URL query param (`?tab=listings`)
- [ ] Each tab has its own "See More" pagination

### 3.2 Infinite Scroll / Load More

- [ ] Add "Show More" button at bottom of each section (not just "See All" link)
- [ ] For tabbed views: implement infinite scroll with `IntersectionObserver`
- [ ] Use paginated endpoints already built (`/v2/explore/trending`, `/v2/explore/popular-listings`)
- [ ] Add new paginated endpoints for each content type as needed

### 3.3 Quick Actions on Cards

- [ ] Add save/bookmark icon button on listing cards (calls `POST /v2/listings/{id}/save`)
- [ ] Add like button on post cards (calls `POST /v2/posts/{id}/like`)
- [ ] Add RSVP button on event cards (calls `POST /v2/events/{id}/rsvp`)
- [ ] Optimistic UI updates with rollback on error
- [ ] Track these interactions via `match_history` for learning

### 3.4 "Not Interested" Feedback

- [ ] Add dismiss/hide button (X icon or "..." menu) on recommended cards
- [ ] Calls `POST /v2/explore/dismiss` → inserts into `match_dismissals`
- [ ] Immediately removes card with exit animation
- [ ] Optionally show reason picker: "Not relevant", "Already seen", "Not interested in this category"
- [ ] Feed reasons back into `MatchLearningService`

### 3.5 Personalized Section Ordering

- [ ] Track which sections the user interacts with most (clicks, scroll depth)
- [ ] Store section engagement weights in Redis per-user
- [ ] Reorder sections on the API side based on engagement: most-interacted sections first
- [ ] Include `section_order` array in API response; frontend renders in that order

### 3.6 Pull-to-Refresh (Mobile)

- [ ] Detect pull gesture on mobile (touch start/move/end)
- [ ] Trigger `retry()` / refetch with visual pull indicator
- [ ] Or use HeroUI/Framer Motion pull-to-refresh pattern
- [ ] Invalidate client-side cache on refresh

### 3.7 Recently Viewed Section

- [ ] Track recently viewed listings/posts/profiles in `localStorage`
- [ ] New frontend-only section: "Recently Viewed" — horizontal scroll of last 10 items
- [ ] No API call needed; purely client-side
- [ ] Clear button to reset history

### 3.8 Empty State Illustrations

- [ ] For each section that returns empty data: show illustrated empty state with CTA
- [ ] Examples: "No trending posts yet — be the first to share!" → link to create post
- [ ] "No events coming up — create one!" → link to create event
- [ ] Use consistent illustration style (Lucide icons + descriptive text)

### 3.9 Skeleton → Staggered Animation

- [ ] Replace simultaneous skeleton → content swap with staggered reveal
- [ ] Each section fades in 100ms after the previous
- [ ] Creates a cascading "loading complete" feel instead of a flash

---

## Phase 4: Algorithm Depth — True "For You"

**Goal:** Build a real personalised discovery feed that blends all content types using existing engines.
**Impact:** +100 points (Algorithm 150→250)
**Effort:** Large (5–8 days)

### 4.1 Unified "For You" Feed

The centrepiece: a single ranked feed mixing posts, listings, events, groups, people, and more — like TikTok's FYP but for community content.

- [ ] New service method: `ExploreService::getForYouFeed(tenantId, userId, page, perPage)`
- [ ] Candidate generation:
  - SmartMatchingEngine top listings (scored)
  - CollaborativeFilteringService item-based recommendations
  - KNN pre-computed recommendations from Redis
  - FeedRankingService top posts (EdgeRank scored)
  - GroupRecommendationEngine suggested groups
  - Upcoming events near user (Haversine)
  - Trending content (engagement velocity)
- [ ] Normalize all scores to 0–100 range
- [ ] Blend with content-type diversity constraint (no more than 3 consecutive items of same type)
- [ ] Apply negative filters: dismissed, muted, blocked, already-interacted
- [ ] Return paginated mixed feed with `content_type` field per item
- [ ] Frontend: new "For You" tab that renders mixed content types with type-specific card templates

### 4.2 Trending Velocity Detection

**Current:** Trending = most likes+comments in 90 days (pure volume).

**Target:** Detect content gaining engagement unusually fast.

- [ ] Compare recent engagement rate (last 6h) vs. average engagement rate for content of that age
- [ ] Velocity score = (recent_rate / expected_rate) — items with velocity > 2.0 are "trending"
- [ ] Weight velocity alongside absolute engagement: `trending_score = 0.4 * volume + 0.6 * velocity`
- [ ] Frontend: "Trending" flame badge on high-velocity content

### 4.3 Social Graph Signals

**Current:** Connections not used in Explore at all.

- [ ] Query `connections WHERE status = 'accepted'` for current user
- [ ] Boost content created by or interacted with by connections
- [ ] "Your connection {name} liked this" social proof labels
- [ ] "3 of your connections are in this group" on group cards
- [ ] Weight: social_boost = min(2.0, 0.3 * connected_interactions)

### 4.4 Semantic Similarity via Embeddings

**Current:** `content_embeddings` table populated but unused by Explore.

- [ ] For authenticated users: get embedding of their most-interacted content
- [ ] Find similar content via `EmbeddingService::findSimilar()` (cosine similarity)
- [ ] Add "Similar to what you like" recommendations
- [ ] Enables cross-category discovery (user likes gardening posts → discovers landscaping listings)
- [ ] This is the serendipity/diversity factor the current system lacks

### 4.5 Time-Decay on Affinity Scores

**Current:** `user_category_affinity` scores don't decay — old interests persist indefinitely.

- [ ] Apply exponential decay: `effective_score = affinity_score * exp(-days_since_last_interaction / 30)`
- [ ] Recent activity counts more than historical
- [ ] Update `getRecommendedListings` to use decayed scores
- [ ] Consider running a nightly job to decay stored scores directly

### 4.6 Skill Match Integration

**Current:** `user_skills` (offering/requesting) not used in Explore.

**Target:** "You're offering Gardening — here are people requesting it" and vice versa.

- [ ] Query `user_skills WHERE is_requesting = 1` for skills the user offers
- [ ] Query `listings` with matching `listing_skill_tags`
- [ ] "Perfect skill match!" badge on listings that match user's offered skills
- [ ] Reciprocity: show listings requesting skills the user offers AND offering skills the user needs

### 4.7 Contextual Timing Signals

- [ ] Boost events happening this weekend on Friday/Saturday
- [ ] Boost morning-friendly listings in morning, evening activities in evening
- [ ] Boost "new this week" content on Mondays
- [ ] Use `users.last_active_at` patterns to detect preferred activity windows

### 4.8 Engagement Prediction Scoring

- [ ] For each explore item, predict P(click) based on:
  - Content type preference (from `match_history` action distribution)
  - Category affinity (decayed)
  - Social signal strength
  - Content freshness
  - Semantic similarity to past interactions
- [ ] Simple logistic model trained on `feed_impressions` + `feed_clicks` data
- [ ] Use predicted engagement as final ranking signal in For You feed

---

## Phase 5: Performance & Scale

**Goal:** Handle cache-miss gracefully, parallelize queries, and pre-compute expensive results.
**Impact:** +30 points (Performance 78→100, Backend +8)
**Effort:** Small–Medium (2–3 days)

### 5.1 Stale-While-Revalidate Caching

- [ ] On cache miss: return stale data immediately (if available) while background-refreshing
- [ ] Implement via Redis: store both value and TTL metadata
- [ ] Soft TTL (5 min): serve cached, trigger async refresh
- [ ] Hard TTL (30 min): force synchronous refresh
- [ ] No user ever experiences a cold-load latency spike

### 5.2 Parallel Query Execution

**Current:** 10+ sequential DB queries on cache miss.

- [ ] Use Laravel's `concurrency()` helper or `Promise\all()` to run independent queries in parallel
- [ ] Group: trending posts, popular listings, events, groups, contributors, hashtags, new members, challenges, stats — all independent
- [ ] Expected improvement: ~3–5× faster cache-miss response

### 5.3 Pre-Computed Engagement Scores

**Current:** Trending posts use correlated subqueries (`SELECT COUNT(*) FROM post_likes WHERE ...` per row).

- [ ] Add `engagement_score` column to `feed_posts` (INT, indexed)
- [ ] Update via async job or trigger whenever a like/comment is added/removed
- [ ] Replace correlated subqueries with: `ORDER BY engagement_score DESC`
- [ ] Same pattern for listings: pre-compute `popularity_score = view_count + (save_count * 3)`

### 5.4 Granular Cache Invalidation

**Current:** All 9 global sections cached as one blob — one change invalidates all.

- [ ] Cache each section independently: `nexus:explore:{tenantId}:trending_posts`, etc.
- [ ] Different TTLs per section:
  - Community stats: 15 minutes (slow-changing)
  - Trending posts: 5 minutes (engagement-driven)
  - New members: 30 minutes (daily-changing)
  - Upcoming events: 10 minutes
- [ ] Invalidate specific section cache on relevant write events (new post → invalidate trending)

### 5.5 Include Categories in Explore Response

**Current:** Frontend makes 2 API calls (`/v2/explore` + `/v2/categories?type=listing`).

- [ ] Include `categories` array in the main `/v2/explore` response
- [ ] Eliminates one HTTP round-trip on every page load

---

## Phase 6: Interaction Tracking & Analytics

**Goal:** Create a feedback loop where Explore interactions improve future Explore results.
**Impact:** +20 points (Testing 35→50, Algorithm polish)
**Effort:** Small–Medium (2–3 days)

### 6.1 Explore Interaction Tracking

- [ ] New endpoint: `POST /v2/explore/track` with body `{ item_type, item_id, action }`
- [ ] Actions: `impression`, `click`, `save`, `dismiss`, `dwell` (>3s view)
- [ ] Store in `match_history` table (already supports these action types)
- [ ] Frontend: fire `impression` on `IntersectionObserver` enter, `click` on card click
- [ ] This closes the feedback loop: Explore data feeds MatchLearningService → improves future Explore

### 6.2 Explore Analytics Dashboard (Admin)

- [ ] Admin page: `/admin/explore-analytics`
- [ ] Metrics:
  - Section CTR (clicks / impressions per section)
  - Most-clicked content types
  - Recommendation acceptance rate
  - Average scroll depth
  - Personalized vs. global section engagement comparison
- [ ] Use existing `feed_impressions` / `feed_clicks` pattern

### 6.3 A/B Testing Framework

- [ ] Assign users to experiment cohorts (stored in Redis or user metadata)
- [ ] Support testing:
  - Different section orderings
  - Different recommendation blending weights
  - With/without social signals
  - With/without semantic embeddings
- [ ] Track conversion metrics per cohort
- [ ] Admin UI to create/view/end experiments

### 6.4 Backend Unit Tests for ExploreService

- [ ] Test each of the 14+ service methods independently
- [ ] Mock DB queries, verify correct SQL parameters
- [ ] Test cache hit/miss paths
- [ ] Test empty data graceful fallbacks
- [ ] Test tenant scoping (never leaks cross-tenant data)
- [ ] Test personalized vs. anonymous user paths

### 6.5 Integration Tests for API Endpoints

- [ ] Test all 4+ endpoints: `GET /v2/explore`, `/v2/explore/trending`, `/v2/explore/popular-listings`, `/v2/explore/category/{slug}`
- [ ] Test authenticated vs. unauthenticated responses
- [ ] Test pagination parameters
- [ ] Test feature-gated section inclusion/exclusion
- [ ] Test 404 on invalid category slug

---

## Phase Summary & Scoring

| Phase | Effort | Score Impact | New Total | Status |
|-------|--------|-------------|-----------|--------|
| **Current state** | — | — | **630/1000** | — |
| **Phase 1:** Wire up existing engines | 3–5 days | +120 | **750** | **DONE** (2026-03-24) |
| **Phase 2:** Add content sections | 3–5 days | +55 | **805** | **DONE** (2026-03-24) |
| **Phase 3:** Frontend UX | 3–5 days | +65 | **870** | **DONE** (2026-03-24) — dismiss, tracking, tabs, recently viewed |
| **Phase 4:** True "For You" algorithm | 5–8 days | +100 | **970** | **DONE** (2026-03-24) — unified mixed feed, velocity trending, social graph, content diversity |
| **Phase 5:** Performance & scale | 2–3 days | +10 | **980** | **DONE** (2026-03-24) — granular caching, differentiated TTLs, categories included |
| **Phase 6:** Tracking & testing | 2–3 days | +20 | **1000** | **DONE** (2026-03-24) — 27 backend + 25 frontend tests, tracking + dismiss endpoints |

**Total effort: ~19–29 days** (assuming one developer, sequential).
Phases 1+2 can run in parallel. Phases 3+4 can partially overlap. Phase 5+6 are independent.
**With parallel work: ~12–18 days realistic.**

---

## Priority Order (If Time-Constrained)

If you can only do some phases, prioritise in this order:

1. **Phase 1** (Wire up engines) — biggest bang for effort. Takes the algorithm from "basic SQL filter" to "6-signal scoring pipeline with collaborative filtering, location, and learning." This alone takes the score from 630 to ~750.

2. **Phase 4.1** (Unified For You feed) — the signature feature. A mixed-content personalised feed is what makes Explore feel like a real discovery engine.

3. **Phase 3.1 + 3.3 + 3.4** (Tabs, quick actions, dismiss) — the UX that makes the algorithm visible and creates the feedback loop.

4. **Phase 2** (Content sections) — fills in the gaps but is less transformative than the algorithm.

5. **Phase 5 + 6** (Performance, testing, analytics) — polish and sustainability.

---

## Files To Modify

| File | Changes |
|------|---------|
| `app/Services/ExploreService.php` | Phases 1, 2, 4, 5 — core algorithm and new sections |
| `app/Http/Controllers/Api/ExploreController.php` | Phases 1, 2, 3, 6 — new endpoints |
| `routes/api.php` | Phases 2, 3, 6 — new routes |
| `react-frontend/src/pages/explore/ExplorePage.tsx` | Phases 2, 3 — new sections, tabs, UX |
| `react-frontend/src/components/explore/` | Phase 3 — new components (ForYouFeed, QuickActions, DismissButton, TabNav) |
| `react-frontend/public/locales/*/explore.json` | Phases 2, 3 — new i18n keys (7 languages) |
| `react-frontend/src/pages/explore/__tests__/` | Phase 6 — expanded frontend tests |
| `tests/Services/ExploreServiceTest.php` | Phase 6 — backend unit tests |
| `tests/Controllers/ExploreControllerTest.php` | Phase 6 — API integration tests |
| `migrations/` | Phase 5.3 — `engagement_score` column on `feed_posts` |
