# Laravel Migration Plan — Project NEXUS

## Context

Project NEXUS migrated from a custom PHP 8.2 framework to Laravel 12.54. The React frontend is unaffected — only the PHP backend changed.

## Strategy: In-Place Incremental Migration

Work happened in-place on a long-lived `laravel-migration` branch, which was **merged to `main` on 2026-03-19** and deleted. The migration is now live in production.

The key enabler: a **bridge layer** that let Laravel and the legacy framework coexist. Legacy routes fell through to the old router; converted routes used Laravel. Both shared the same DB connection. The bridge has since been removed — Laravel is the sole HTTP handler.

---

## Current Status (2026-03-20)

**Phases 0–5 are complete and merged to `main`.** Laravel is the sole HTTP handler. The legacy framework no longer boots for any request. Performance improved from 8.4s/req (bridge mode) to ~100ms/req (pure Laravel + JIT + OPCache).

### What is complete

| Layer | Status | Details |
|-------|--------|---------|
| **Entry point & routing** | 100% | `httpdocs/index.php` is a 40-line Laravel entry point. 1,470 lines in `routes/api.php` |
| **Middleware pipeline** | 100% | `ResolveTenant`, `SecurityHeaders`, CORS, auth — all native Laravel |
| **Controllers** | 100% | 130 native Laravel controllers in `app/Http/Controllers/Api/` |
| **Eloquent Models** | 100% | 116 models with `HasTenantScope` auto-scoping |
| **Auth** | 100% | Sanctum tokens, WebAuthn, 2FA |
| **Events & Broadcasting** | 100% | Laravel events dispatched; Pusher broadcasting |
| **Native services** | 61% | 164 of 268 services are genuine Eloquent/DI implementations |

### What remains (Phase 6 — Legacy Cleanup)

| Item | Count | Effort | Risk |
|------|-------|--------|------|
| **Wrapper services** (`app/Services/`) | 104 | High | Medium |
| **Legacy delegates** (`src/`) | 82 files | Low (delete after wrappers converted) | Low |
| **Event Listener stubs** (`app/Listeners/`) | 5 | Medium | Medium |
| **Migration consolidation** | 188 legacy SQL + 4 Laravel | Low | Low |

---

## Phase 6 — Legacy Cleanup (IN PROGRESS)

### 6a. Convert 104 wrapper services — THE CORE REMAINING WORK

**104 services** in `app/Services/` are pure DI wrappers that delegate every call to legacy `\Nexus\Services\*` static methods. They have no Eloquent logic — they exist only so Laravel controllers can inject them via DI.

**Pattern (every wrapper looks like this):**
```php
// app/Services/GroupAssignmentService.php
namespace App\Services;

class GroupAssignmentService
{
    public function __call(string $method, array $args): mixed
    {
        return \Nexus\Services\GroupAssignmentService::$method(...$args);
    }
}
```

Or with explicit method delegation:
```php
// app/Services/VolunteerMatchingService.php
public function findMatches(int $tenantId, int $opportunityId, int $limit = 10): array
{
    return \Nexus\Services\VolunteerMatchingService::findMatches($tenantId, $opportunityId, $limit);
}
```

**What "converting" means:** Rewrite each wrapper to use Eloquent models, `DB::` facades, and constructor DI — like the reference implementation `ListingService.php`:
```php
// app/Services/ListingService.php (REFERENCE — fully native)
use App\Models\Listing;
use Illuminate\Support\Facades\DB;

class ListingService
{
    public function __construct(
        private readonly Listing $listing,
    ) {}

    public function getActiveListings(array $filters = []): CursorPaginator
    {
        return $this->listing->where('status', 'active')
            ->when($filters['category'] ?? null, fn ($q, $cat) => $q->where('category_id', $cat))
            ->cursorPaginate(20);
    }
}
```

#### Complete list of 104 wrapper services

Grouped by functional area to help prioritize:

**Federation (14 services)** — Inter-community networking
- `FederatedConnectionService.php`
- `FederatedGroupService.php`
- `FederatedMessageService.php`
- `FederatedTransactionService.php`
- `FederationActivityService.php`
- `FederationCreditService.php`
- `FederationDirectoryService.php`
- `FederationEmailService.php`
- `FederationExternalApiClient.php`
- `FederationExternalPartnerService.php`
- `FederationGateway.php`
- `FederationJwtService.php`
- `FederationNeighborhoodService.php`
- `FederationRealtimeService.php`
- `FederationUserService.php`

**Groups (15 services)** — Community group management
- `GroupAchievementService.php`
- `GroupApprovalWorkflowService.php`
- `GroupAssignmentService.php`
- `GroupAuditService.php`
- `GroupChatroomService.php`
- `GroupConfigurationService.php`
- `GroupEventService.php`
- `GroupExchangeService.php`
- `GroupFeatureToggleService.php`
- `GroupFileService.php`
- `GroupModerationService.php`
- `GroupNotificationService.php`
- `GroupPermissionManager.php`
- `GroupPolicyRepository.php`
- `GroupReportingService.php`
- `OptimizedGroupQueries.php`

**Matching & Recommendations (8 services)** — Algorithmic, low test coverage
- `CrossModuleMatchingService.php`
- `MatchApprovalWorkflowService.php`
- `MatchDigestService.php`
- `MatchLearningService.php`
- `MatchingService.php`
- `MatchNotificationService.php`
- `PersonalizedSearchService.php`
- `SmartGroupMatchingService.php`
- `SmartGroupRankingService.php`
- `SmartMatchingAnalyticsService.php`
- `SmartSegmentSuggestionService.php`

**Volunteering (5 services)**
- `VolunteerCertificateService.php`
- `VolunteerMatchingService.php`
- `VolunteerReminderService.php`
- `VolunteerWellbeingService.php`
- `RecurringShiftService.php`

**Email & Notifications (9 services)**
- `DeliverabilityTrackingService.php`
- `DigestService.php`
- `EmailMonitorService.php`
- `EmailTemplateBuilder.php`
- `EmailTemplateService.php`
- `FCMPushService.php`
- `GamificationEmailService.php`
- `NewsletterTemplates.php`
- `OrgNotificationService.php`
- `ProgressNotificationService.php`
- `WebPushService.php`

**Transactions & Wallet (6 services)**
- `DailyRewardService.php`
- `ExchangeRatingService.php`
- `PayPlanService.php`
- `StartingBalanceService.php`
- `TransactionCategoryService.php`
- `TransactionExportService.php`
- `TransactionLimitService.php`

**Admin & Infrastructure (12 services)**
- `AdminListingsService.php`
- `AdminSettingsService.php`
- `GeocodingService.php`
- `HtmlSanitizer.php`
- `PerformanceMonitorService.php`
- `PusherService.php`
- `RankingService.php`
- `RateLimitService.php`
- `RedisCache.php`
- `ReportExportService.php`
- `SchemaService.php`
- `SentryService.php`
- `SuperAdminAuditService.php`
- `TenantHierarchyService.php`
- `TenantSettingsService.php`
- `TenantVisibilityService.php`
- `TwoFactorChallengeManager.php`

**Other (remaining services)**
- `BadgeService.php`
- `BrokerService.php`
- `CookieConsentService.php`
- `GuardianConsentService.php`
- `HashtagService.php`
- `HoursReportService.php`
- `IdeaMediaService.php`
- `IdeaTeamConversionService.php`
- `InsuranceCertificateService.php`
- `MailchimpService.php`
- `OrgWalletService.php`
- `PostSharingService.php`
- `PredictiveStaffingService.php`
- `ReferralService.php`
- `ResourceCategoryService.php`
- `ResourceOrderService.php`
- `SavedSearchService.php`
- `SearchAnalyzerService.php`
- `SearchLogService.php`
- `SocialAuthService.php`
- `SocialGamificationService.php`
- `SocialValueService.php`
- `TeamDocumentService.php`
- `TeamTaskService.php`
- `UnifiedSearchService.php`
- `UploadService.php`
- `VettingService.php`
- `WebAuthnChallengeStore.php`
- `WebhookDispatchService.php`

**Approach:**
- Do NOT bulk-rewrite. Convert incrementally as you touch each service for bugs or features.
- **Add tests BEFORE converting** — many of these have no test coverage.
- Use `ListingService.php` as the reference implementation.
- 8 wrappers use `__call()` magic method forwarding (most dangerous — no IDE or static analysis support):
  `UploadService`, `SocialAuthService`, `SmartGroupMatchingService`, `SearchAnalyzerService`, `PersonalizedSearchService`, `MailchimpService`, `GroupAssignmentService`, `FederationExternalApiClient`

**Priority order:**
1. The 8 `__call()` magic wrappers — highest risk, no type safety
2. Services actively touched for bugs/features — convert as you go
3. Federation services — complex but self-contained cluster
4. Everything else — low urgency, stable code

---

### 6b. Implement 5 Event Listener stubs

All 5 listeners in `app/Listeners/` are empty stubs with TODO comments. They were created during the migration but never implemented — the legacy code they're supposed to replace still runs via the wrapper services above.

| Listener | Legacy code it replaces | TODOs |
|----------|------------------------|-------|
| **NotifyConnectionRequest** | `ConnectionService::sendConnectionNotification()`, `NotificationService::create()`, `PushNotificationService` | Create in-app notification, send push, send email if prefs allow |
| **NotifyMessageReceived** | `MessageService::notifyRecipient()`, `NotificationService::create()`, `PushNotificationService`, `RealtimeService` | Create in-app notification, send push, send email if offline |
| **SendWelcomeNotification** | `NotificationService::sendWelcomeNotification()`, `EmailService::sendWelcomeEmail()` | Send welcome email, create in-app notification, init gamification profile |
| **UpdateFeedOnListingCreated** | `FeedService::createActivity()`, `SearchService::indexListing()` | Create feed activity, index in search, notify followers |
| **UpdateWalletBalance** | `WalletService::processTransaction()`, `GamificationService::awardTransactionXp()` | Update sender/receiver balances, award XP, update leaderboard |

**Approach:** These listeners should use the **native** `app/Services/` implementations (not the wrapper services). Convert the relevant wrapper services first, then implement the listeners.

**Risk:** Medium — these are critical user-facing flows (notifications, wallet transactions). Test thoroughly.

---

### 6c. Delete 82 legacy `src/` delegate files

All 82 remaining files in `src/` are thin backward-compatibility delegates that forward calls to `App\*` namespace classes. They exist because the 104 wrapper services in `app/Services/` call `\Nexus\Services\*` static methods.

**Once the 104 wrapper services are converted to native Eloquent (6a), these files become dead code and can be deleted.**

| Category | Files | Delegates to |
|----------|-------|-------------|
| `src/Core/` | 14 | `App\Core\*` (Auth, Database, TenantContext, Validator, etc.) |
| `src/Admin/` | 1 | `App\Admin\WebPConverter` |
| `src/Config/` | 4 | Static config files (no delegation) |
| `src/Helpers/` | 8 | `App\Helpers\*` (CorsHelper, ImageHelper, TimeHelper, etc.) |
| `src/I18n/` | 1 | `App\I18n\Translator` |
| `src/Middleware/` | 8 | `App\Middleware\*` (FederationApi, Maintenance, SuperPanelAccess, etc.) |
| `src/PageBuilder/` | 18 | `App\PageBuilder\*` (BlockRegistry, PageRenderer, 16 renderers) |
| `src/Services/AI/` | 7 | `App\Services\AI\*` (AIServiceFactory, 5 providers, contracts) |
| `src/Services/Enterprise/` | 5 | `App\Services\Enterprise\*` (Config, GDPR, Logger, Metrics, Permissions) |
| `src/Services/Identity/` | 14 | `App\Services\Identity\*` (14 identity verification providers/services) |
| `src/Services/TotpService.php` | 1 | `App\Services\TotpService` |
| `src/helpers.php` | 1 | Static helper functions |

**Approach:**
1. After converting each group of wrapper services, grep for `\Nexus\` references across the entire codebase
2. When a `src/` file has zero remaining callers, delete it
3. The last files to go will be `src/Core/TenantContext.php` and `src/Core/Database.php` — these are called by nearly everything
4. `src/Config/` files (4) are static config — may need to be moved to `config/` rather than deleted

**Risk:** Low — these are verified delegates with no unique logic. Just need to confirm zero callers before each deletion.

---

### 6d. Consolidate database migrations

Two parallel migration systems currently coexist:

| System | Location | Files | Format | Runner |
|--------|----------|-------|--------|--------|
| **Legacy** | `migrations/` | 188 | Raw SQL (`.sql`) | `php scripts/safe_migrate.php` (local) or direct `mysql <` (production) |
| **Laravel** | `database/migrations/` | 4 | PHP (Eloquent Schema Builder) | `php artisan migrate` |

**Laravel migrations (4 files):**
1. `2026_03_18_000000_baseline_schema.php` — Captures the entire existing schema as a baseline
2. `2026_03_18_000001_create_personal_access_tokens_table.php` — Sanctum tokens
3. `2026_03_18_000002_create_migration_registry.php` — Migration tracking
4. `2026_03_18_000003_add_laravel_columns.php` — Additional columns needed by Laravel

**Approach:**
- All **new** schema changes should use Laravel migrations (`database/migrations/`)
- The 188 legacy SQL files are historical — they've already been applied to production
- Do NOT attempt to convert the 188 legacy files to Laravel format (no value, high risk)
- Eventually, stop using `migrations/` for new work entirely
- The baseline migration means `php artisan migrate` on a fresh database produces the correct schema

**Risk:** Low — this is a process change, not a code change.

---

### 6e. Migrate core framework classes — HIGH VALUE, HIGH RISK

The two most critical legacy classes, to be done **last**:

| Class | Location | Lines | Why it matters |
|-------|----------|-------|---------------|
| `TenantContext` | `src/Core/TenantContext.php` → `app/Core/TenantContext.php` | 802 | 7 resolution strategies, called everywhere |
| `Database.php` | `src/Core/Database.php` → `app/Core/Database.php` | 9.4K | Raw PDO wrapper used by wrapper services |

**Approach:** Do `Database.php` last — once all 104 wrapper services use Eloquent/`DB::` facade, it becomes dead code. `TenantContext` can be rewritten as a proper Laravel singleton after that.

### 6f. Migrate legacy models — LOW EFFORT, BLOCKED

| Model | Blocker |
|-------|---------|
| `src/Models/User.php` | `SuperPanelAccess::canManageTenant()` calls `User::isGod()` |
| `src/Models/EmailSettings.php` | `App\Core\Mailer` imports it directly |

**Fix:** Move `isGod()` to `App\Models\User`, update `SuperPanelAccess`. Move `EmailSettings` logic to Eloquent model, update `Mailer`.

### 6g. Admin panel modernisation (OPTIONAL) — LOW PRIORITY
- `views/admin/` + `views/modern/admin/` (~200 files) serve `/admin-legacy/` and `/super-admin/`
- Could convert to Laravel Blade or React admin panel
- **Not recommended now** — works fine, low traffic, admin-only

---

## Completed Phases (Historical)

### Phase 0 — Foundation (COMPLETE)

Install Laravel alongside the existing app without breaking anything.

1. [x] `composer require laravel/framework:^12.54 laravel/sanctum:^4.3`
2. [x] Created `bootstrap/app.php`, `config/`, `artisan`
3. [x] Bridge in `index.php`: Laravel handles request first; if 404, falls through to legacy
4. [x] Tenant middleware: `ResolveTenant` delegates to `TenantContext::resolve()`
5. [x] `TenantScope` + `HasTenantScope` trait for Eloquent models
6. [x] Health check verified: `/api/laravel/health`
7. [x] DB bridge: `Database::setLaravelConnection()` shares Laravel's PDO with legacy code

### Phase 1 — Route Migration (COMPLETE)

**1,470 lines** in `routes/api.php`. All legacy route files retired.

### Phase 2 — Models (COMPLETE — 116 models)

All Eloquent models created with `HasTenantScope`. Tested against live database.

### Phase 3 — Services (61% COMPLETE — 164/268 native)

DI pattern established. **268 Laravel services** created.

| Status | Count | % | Description |
|--------|-------|---|-------------|
| **Native** | 164 | 61% | Real Eloquent/DI implementations |
| **Wrappers** | 104 | 39% | Delegate to legacy `\Nexus\*` static services |

### Phase 4 — Controllers (COMPLETE — 130 Laravel controllers)

All API controllers converted. Legacy controllers deleted (2026-03-19).

### Phase 5 — Activation (COMPLETE — 2026-03-18)

Laravel is the sole HTTP handler. The bridge has been removed.

**Performance:** 8.4s/request (bridge) → ~100ms (pure Laravel + JIT + OPCache preload)

### Deleted (2026-03-19)

| Category | Files | Lines | Why safe |
|----------|-------|-------|----------|
| `src/Controllers/` | 80 | ~45K | All routes served by Laravel controllers |
| `tests/Controllers/` | 142 | -- | Scaffold tests for deleted controllers |

---

## Test Coverage

| Suite | Tests | Status |
|-------|-------|--------|
| Unit | 249 | 12 pre-existing errors (`ResolveAdminTenantFilterTest`) |
| Services | 1,283 | 95 pre-existing errors (DB access to `nexus_test`), 56% coverage gap |
| Models | -- | Pre-existing DB access errors |
| Laravel | 17 | `TestCase` autoload issue (pre-existing) |

**Priority:** Fix `nexus_test` database access to unblock service tests before converting more wrapper services.

---

## Critical Risks

| Risk | Mitigation |
|------|------------|
| API contract breakage (React breaks) | Response shape logging + snapshot tests |
| Multi-tenant scope leak | `TenantScope` global scope is safer than manual scoping |
| Wrapper service rewrite breaks logic | Add tests BEFORE converting untested services |
| 56% services untested | Fix `nexus_test` DB access, add tests incrementally |
| `TenantContext` rewrite breaks everything | Do last, after all dependencies migrated |

## What NOT to Migrate

- Legacy PHP admin views (`views/admin/`, `views/modern/admin/`) — leave as-is
- React frontend — untouched (already fully decoupled)
- PHP i18n files (`lang/`) — only used by legacy admin
- PageBuilder — low priority, works fine

---

## Verification

After each service conversion:
1. Run PHPUnit suites (Unit, Services)
2. Hit affected endpoints via React frontend
3. Verify tenant scoping with cross-tenant test queries
4. Check Docker builds still work
