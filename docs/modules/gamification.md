# Gamification Module Guide

Last reviewed: 2026-07-14

This guide is a how-to/reference for maintainers of the **Gamification** surface in Project NEXUS: XP and levels, badges and achievements, leaderboards, challenges, and the NEXUS score. It documents the data model, tenant scoping, the XP-award idempotency / anti-abuse mechanism, failure modes, and the regression tests that protect this surface.

Gamification is engagement scaffolding. It awards experience points (XP), grants badges, ranks members on leaderboards, and computes a composite "NEXUS score". It must never be a path to creating time credits — XP and badges are reputational only and are independent of the wallet ledger.

## Audience & supported workflows

Use this guide when changing XP values, badge rules, level thresholds, leaderboard queries, challenges, or the NEXUS score.

Supported workflows:

- **XP & levels** — members earn XP for platform actions and level up across named tiers.
- **Badges & achievements** — milestone badges (quantity-based) plus quality badges (reliability, reciprocity, mentoring, etc.) are auto-granted when thresholds are met.
- **Leaderboards** — per-tenant rankings across nine metrics over weekly / monthly / all-time windows.
- **Challenges** — time-boxed action goals that grant XP and optionally a badge on completion.
- **NEXUS score** — a composite 1000-point reputation score across six weighted dimensions.
- **Daily rewards & streaks** — a once-per-day claimable XP reward with streak tracking (delegated to `DailyRewardService`).

## Tenant & feature-gate rules

- **Tenant scoping is mandatory.** XP, badges, leaderboards, challenges, and scores are all per-tenant. Eloquent models (`UserXpLog`, `UserBadge`, `Challenge`, `UserChallengeProgress`) use the `HasTenantScope` trait; raw-SQL paths (leaderboards, several badge checks) filter on `tenant_id` explicitly. `GamificationService::getLeaderboard()` defaults the tenant to `TenantContext::getId()` precisely because an unscoped XP aggregate would leak users across every tenant on the platform.
- **Feature gate:** `gamification`. Registered in `app/Services/TenantFeatureConfig.php` (default **on**). The React frontend wraps the Leaderboard, Achievements, and NEXUS score routes in `<FeatureGate feature="gamification">` (see `react-frontend/src/routes/AppRoutes.tsx`); disabled tenants get a "coming soon" fallback.
- **Note:** the `/v2/gamification/*` API routes are not individually wrapped in a `feature:` route middleware — the gate is enforced in the frontend and in navigation config. Treat the feature flag as a UI/navigation gate, not a hard API authorization boundary. Side-effect awards (XP/badges) still fire from the underlying action events regardless of the flag.

## Key code & data locations

Routes are defined in [`routes/api.php`](../../routes/api.php). Do not copy the endpoint table here — read the route file or the API reference for the live list. Primary entry points:

| Concern | Route prefix | Controller |
| --- | --- | --- |
| Profile, badges, leaderboard, challenges, NEXUS score, daily reward, shop, seasons | `/v2/gamification/*` | `App\Http\Controllers\Api\GamificationV2Controller` |
| Legacy gamification (leaderboard, streaks, achievements, summary, showcase) | `/leaderboard`, `/achievements`, `/gamification/*`, `/streaks`, `/daily-reward/*` | `App\Http\Controllers\Api\GamificationController` |
| NEXUS score read / recalculate | `/nexus-score`, `/nexus-score/recalculate` | `App\Http\Controllers\Api\GamificationController` |
| Admin badge/campaign config, recheck, bulk award | `/v2/admin/gamification/*`, `/v2/admin/users/{id}/badges` | `App\Http\Controllers\Api\AdminGamificationController`, `AdminUsersController` |
| Group challenges | `/v2/groups/{id}/challenges` | `App\Http\Controllers\Api\GroupChallengeController` |

Services:

- `app/Services/GamificationService.php` — the core: XP values, level thresholds, `awardXP()`, `awardBadge()`, `runAllBadgeChecks()`, leaderboard helper, daily reward delegation. XP and level constants live here (`XP_VALUES`, `LEVEL_THRESHOLDS`, plus the V2 named-level and simplified-XP variants).
- `app/Services/BadgeDefinitionService.php` — DB-backed badge definitions (seeded by migration); `GamificationService` falls back to its static badge array when the table is unseeded.
- `app/Services/BadgeService.php`, `BadgeCollectionService.php` — badge enrichment and curated collections.
- `app/Services/LeaderboardService.php` — the multi-metric leaderboard with versioned cache invalidation.
- `app/Services/LeaderboardSeasonService.php` — time-boxed leaderboard seasons.
- `app/Services/ChallengeService.php` (+ `ChallengeCategoryService`, `ChallengeTemplateService`, `ChallengeOutcomeService`, `GroupChallengeService`) — challenge CRUD and claim/completion.
- `app/Services/NexusScoreService.php` + `NexusScoreCacheService.php` — composite score calculation and its 1-hour cache.
- `app/Services/GamificationRealtimeService.php` — Pusher broadcasts (`badge-earned`, `xp-gained`, `level-up`) on the per-user channel.
- `app/Services/GamificationEmailService.php` — milestone emails (badge earned, level up).
- `app/Services/Achievement*Service.php` — achievement analytics, campaigns, and unlockables.

Listeners (where XP is actually awarded):

- `app/Listeners/UpdateWalletBalance.php` — on `TransactionCompleted`, awards `send_credits` / `receive_credits` XP and runs badge checks for both parties. **Queued, `$tries = 1`**, with a one-time cache claim.
- `app/Listeners/AwardXpOnVolLogApproved.php` — on a vol-log `pending → approved` transition, awards `volunteer_hour` XP (20 XP/hour) and re-checks badges.

Models / tables:

- `user_xp_log` (`App\Models\UserXpLog`) — append-only XP ledger; `UPDATED_AT` disabled. Carries `tenant_id`, `user_id`, `xp_amount`, `action`, `description`, `source_reference`.
- `users.xp`, `users.level`, `users.points` — denormalised running totals (XP is incremented atomically; level recalculated from thresholds). `users.show_on_leaderboard` opts a member out of leaderboards.
- `user_badges` (`App\Models\UserBadge`) — earned badges; unique on `(tenant_id, user_id, badge_key)`.
- `challenges`, `user_challenge_progress`, `nexus_score_cache`, `user_streaks`, plus the badge-definition / collection / season / campaign tables seeded by the gamification migrations.

## XP & levels

`GamificationService::XP_VALUES` maps an action key to an XP amount (e.g. `complete_transaction` = 25, `volunteer_hour` = 20, `create_listing` = 15, `send_credits` = 10, `receive_credits` = 5). A simplified `XP_VALUES_V2` set exists for the gamification redesign.

`awardXP($userId, $amount, $action, $description = '', $reference = null)`:

1. Returns immediately if `$amount <= 0`.
2. Inside a DB transaction, writes a `user_xp_log` row and atomically increments `users.xp`.
3. For declared one-time actions (currently `complete_profile`), it locks the user row and skips if a log row for that action already exists.
4. After commit, invalidates the tenant's leaderboard cache, broadcasts an `xp-gained` event, then calls `checkLevelUp()`.

Levels are derived from cumulative XP. `LEVEL_THRESHOLDS` defines the 25-level V1 ladder; `LEVEL_THRESHOLDS_V2` defines 10 named tiers (Newcomer → Explorer → Contributor → … → Legend). `getLevelName()` maps any level to a named tier. `checkLevelUp()` recalculates the level, persists it, notifies the member (in their `preferred_language` via `LocaleContext`), broadcasts `level-up`, awards milestone bonus XP at specific levels only — `5, 10, 15, 20, 25, 30, 50, 100` (bonuses of `50/100/150/200/300/400/500/1000` XP respectively; the ladder is not every-5 past level 30), and grants level badges.

## Badges & achievements

Badge definitions are DB-backed (`BadgeDefinitionService::getEnabledBadges()`) with a static fallback array in `GamificationService::getStaticBadgeDefinitions()` for pre-seed safety. Each definition has a `key`, `name`, `icon`, `type`, and `threshold`.

`runAllBadgeChecks($userId)` runs every check. **Quantity badges** count an activity and grant tiered badges (volunteer hours, offers/requests, credits earned/spent, transactions, helped-people diversity, connections, messages, reviews given, 5-star reviews received, events attended/hosted, groups joined/created, posts, likes received, profile completion, membership age). **Quality badges** (gamification redesign) reward behaviour, not volume:

- **Reliability** — completed transactions with a cancellation rate under a configured ceiling.
- **Bridge builder** — trading across multiple distinct listing categories.
- **Mentor** — being a partner's first-ever completed transaction.
- **Reciprocity** — a healthy earn/spend ratio (core timebanking value).
- **Community champion** — sustained multi-category activity over consecutive months.

Awarding a badge (`awardBadge()`) is idempotent (see below), creates a recipient-locale notification + push, broadcasts `badge-earned`, records a feed activity, sends a milestone email, and awards `earn_badge` XP.

## Leaderboards

`LeaderboardService` supports nine metrics — `credits_earned`, `credits_spent`, `vol_hours`, `badges`, `xp`, `connections`, `reviews`, `posts`, `streak` — over three windows: `all_time`, `monthly` (30 days), `weekly` (7 days). Queries are tenant-scoped, restricted to `is_approved = 1`, and honour `show_on_leaderboard`.

Results are cached for 60 seconds (`CACHE_TTL_SECONDS`). Cache keys are **versioned** via a per-tenant counter so invalidation is a single `Cache::increment("leaderboard:{tenant}:version")` — avoiding `Cache::tags()`, which is unsupported on the file/database cache stores. `awardXP()` calls `LeaderboardService::invalidate($tenantId)` after every award. The `is_current_user` flag is applied per-request after the cache fetch, so the cached dataset stays shareable across users.

The simpler `GamificationService::getLeaderboard()` is an XP-only ranking used by some profile surfaces; `LeaderboardSeasonService` adds time-boxed seasons.

## Challenges

`ChallengeService` manages time-boxed action goals. A challenge has a `challenge_type` (e.g. weekly), `action_type`, `target_count`, `category`, `xp_reward`, and optional `badge_reward`, with `start_date` / end window and status. Member progress is tracked in `user_challenge_progress`; completing a challenge grants its XP (and badge) reward. Group challenges are handled by `GroupChallengeService` under `/v2/groups/{id}/challenges`. Challenge creation/management is exposed to admins via `AdminGamificationController`.

## NEXUS score

`NexusScoreService` computes a composite **1000-point** reputation score across six weighted dimensions: Community Engagement (250), Contribution Quality (200), Volunteer Hours (200), Platform Activity (150), Badges & Achievements (100), and Social Impact (100). It is tenant-scoped and returns both a total and a per-dimension breakdown. `NexusScoreCacheService` caches results for **1 hour** in `nexus_score_cache` (keyed on `user_id` + `tenant_id`); `recalculateAll($tenantId)` re-scores every approved member in a tenant.

## XP-award idempotency & anti-abuse

Duplicate XP is the main abuse/regression risk. The platform defends against it with **two independent layers** plus per-action one-time guards.

### Layer 1 — short-lived cache claim (queue re-delivery)

`UpdateWalletBalance` is queued with `public int $tries = 1` (no automatic retries, because XP has no natural retry-safe dedup beyond what is described here). At the top of `handle()` it makes a one-time claim:

```php
$claimKey = 'wallet_xp:done:' . $event->tenantId . ':' . ($event->transaction->id ?? 0);
if (!Cache::add($claimKey, 1, now()->addHour())) {
    // duplicate delivery suppressed
    return;
}
```

`Cache::add()` is atomic insert-if-absent — a second delivery of the same `TransactionCompleted` within the hour short-circuits before any XP is awarded.

### Layer 2 — permanent DB-level idempotency key (the real backstop)

The cache claim only lasts an hour. The durable guard is a unique index on `user_xp_log`:

- Migration `database/migrations/2026_06_16_120000_add_idempotency_to_user_xp_log.php` adds a nullable `source_reference` column and a unique index **`uniq_user_xp_log_ref` on `(tenant_id, user_id, action, source_reference)`**.
- MySQL/MariaDB allow multiple NULLs in a unique index, so legacy reference-less awards are unaffected — only callers that pass a `source_reference` become idempotent at the database level.
- `UpdateWalletBalance` passes the transaction id as the reference for both the sender's `send_credits` and the receiver's `receive_credits` awards. A re-award attempt for the same `(tenant, user, action, transaction)` raises `UniqueConstraintViolationException`, which `awardXP()` catches and treats as a silent idempotent no-op (it does not log it as an error or re-increment XP).

This is the mechanism to verify when touching this surface: the unique index is the source of truth; the cache claim is an optimisation that avoids the wasted work, not the correctness guarantee.

### Other anti-duplication guards

- **One-time actions** (`complete_profile`): `awardXP()` locks the user row and checks for an existing log row before awarding — serialising concurrent duplicate one-time awards.
- **Badges:** `awardBadge()` checks for an existing `UserBadge` and also catches `UniqueConstraintViolationException`, backed by the `(tenant_id, user_id, badge_key)` unique index (`migrations/2026_03_28_fix_user_badges_unique_index.sql`). Badge checks are therefore safe to call repeatedly.
- **Volunteer XP:** `AwardXpOnVolLogApproved` only acts on a genuine `pending → approved` transition and does a `LIKE '%[vol_log:N]%'` pre-check on the description (bracketed token to avoid `vol_log:1` matching `vol_log:11`).

> Reputational only: XP and badges never mint time credits. They are derived from completed actions; they never feed back into the wallet ledger. Keep it that way — adding an XP→credit path would turn a reputational system into a money-printing one.

## Failure modes & recovery

- **Side effects must never break the parent action.** Every broadcast, email, feed-activity, and badge-check call in `GamificationService` / the listeners is wrapped in try/catch and logged at `debug`/`warning`. A Pusher outage or email failure degrades gamification silently; it never rolls back a transaction or vol-log.
- **Queue not running.** XP/badge awards on transactions are dispatched via the queue (`UpdateWalletBalance implements ShouldQueue`). If the worker is down, awards are delayed, not lost — they fire when the queue drains. Because `$tries = 1`, a job that genuinely fails mid-run is **not** retried; the DB idempotency key means a manual replay is still safe.
- **Tenant context on the queue.** The listener re-pins `TenantContext::setById($event->tenantId)` and restores it in `finally` — queue workers boot once and do not carry request tenant context.
- **Stale leaderboard.** Rankings are eventually consistent (60s TTL + version-bump invalidation). If a ranking looks wrong, confirm the per-tenant version counter is incrementing on awards; a flat counter points at a cache-store problem.
- **Stale / missing NEXUS score.** Scores are cached 1 hour. Force a refresh with `POST /nexus-score/recalculate` or `NexusScoreService::recalculateAll($tenantId)`. The cache layer degrades to live calculation when `nexus_score_cache` is absent.
- **Unseeded badge definitions.** If `BadgeDefinitionService` is not seeded, `GamificationService` transparently falls back to its static badge array — no crash, but admin-configured badge tuning won't apply until the seed migration runs.

## Test commands & key regression tests

```bash
# All gamification PHP tests
vendor/bin/phpunit --filter 'Gamification|Badge|Leaderboard|NexusScore|Challenge|Xp'

# Targeted high-value cases
vendor/bin/phpunit tests/Laravel/Unit/Listeners/UpdateWalletBalanceTest.php
vendor/bin/phpunit tests/Laravel/Integration/GamificationFlowTest.php
vendor/bin/phpunit tests/Laravel/Unit/Services/LeaderboardServiceTest.php
vendor/bin/phpunit tests/Laravel/Unit/Services/NexusScoreServiceTest.php
```

Important regression tests:

- `tests/Laravel/Unit/Listeners/UpdateWalletBalanceTest.php` — the listener awards XP + runs badge checks, implements `ShouldQueue`, and swallows exceptions without breaking the parent flow.
- `tests/Laravel/Integration/GamificationFlowTest.php` — end-to-end: XP awarded for listing creation and exchange completion, badge checks after activity, level calculation from XP (including beyond the max defined level), leaderboard ranking, and that daily reward cannot be claimed twice.
- `tests/Laravel/Unit/Models/UserXpLogTest.php` — model contract: table name, `UPDATED_AT` disabled, fillable includes `source_reference`, and the tenant scope is applied.
- `tests/Laravel/Unit/Services/LeaderboardServiceTest.php` / `NexusScoreServiceTest.php` / `GamificationServiceTest.php` — metric/period handling, score dimensions, and XP/level constant invariants.
- `tests/Laravel/Feature/Controllers/GamificationControllerTest.php`, `tests/Laravel/Feature/Controllers/GamificationV2ControllerTest.php`, and `AdminGamificationControllerTest.php` — endpoint behaviour and admin badge/campaign management.

When adding a new XP source, prefer passing a stable `source_reference` so the `uniq_user_xp_log_ref` index makes the award idempotent, and add a test that asserts a second award for the same reference is a no-op.
