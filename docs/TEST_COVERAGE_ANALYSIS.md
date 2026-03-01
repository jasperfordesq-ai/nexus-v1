# Test Coverage Analysis

## Executive Summary

The Nexus codebase contains **355 PHP test files** (~4,588 test methods) and **129 frontend test files** (~1,373 test cases). The backend has reasonably strong coverage for controllers, services, and models, but significant gaps exist in federation services, enterprise features, and the frontend admin modules. The frontend coverage thresholds are currently set at a low baseline (~30%) with a stated target of 70%+. No E2E tests exist despite a Playwright configuration being present.

---

## Current Coverage Overview

| Layer | Source Files | Test Files | Approx. Coverage |
|---|---|---|---|
| **PHP Controllers (API)** | 89 | 81 | ~91% of files |
| **PHP Controllers (Admin)** | 38 | 36 | ~95% of files |
| **PHP Services** | 130 | 104 | ~80% of files |
| **PHP Models** | 59 | 60 | ~98% of files |
| **PHP Middleware** | 8 | 8 | ~88% of files |
| **PHP Integration** | — | 5 | Limited scope |
| **React Frontend** | 397 | 129 | ~32% of files |
| **E2E (Playwright)** | — | 0 | 0% — not implemented |

---

## Priority 1: Critical Gaps

### 1.1 No E2E Tests Exist

A `playwright.config.ts` is configured but no actual E2E test files exist. For a multi-tenant platform with federation, exchanges, messaging, and wallet features, the absence of end-to-end tests is a significant risk.

**Recommended E2E test suites to create:**
- **Authentication flow** — registration, login, 2FA, password reset, session expiry
- **Exchange lifecycle** — create listing, propose exchange, accept, complete, review
- **Messaging** — send message, receive real-time update, voice messages
- **Wallet transactions** — transfer credits, view balance, org wallet operations
- **Multi-tenant isolation** — ensure tenant data doesn't bleed across boundaries
- **Federation handshake** — cross-tenant discovery and interaction

### 1.2 Federation Services — 0% Direct Coverage for 16 Core Services

While there are 16 test files under `tests/Services/Federation/`, the corresponding source services under `src/Services/` were reported as untested. The federation module is a high-complexity, security-critical subsystem. These services need thorough testing:

| Untested Service | Risk |
|---|---|
| `FederationGateway` | Core orchestration — any bug cascades everywhere |
| `FederationJwtService` | Security-critical — JWT minting/validation |
| `FederationExternalApiClient` | External API integration — failure modes matter |
| `FederationUserService` | Cross-tenant user resolution |
| `FederationSearchService` | Cross-tenant search federation |
| `FederationPartnershipService` | Partnership lifecycle management |
| `FederationDirectoryService` | Service directory/discovery |
| `FederationActivityService` | Activity feed across tenants |
| `FederationAuditService` | Audit trail for compliance |
| `FederationEmailService` | Cross-tenant email notifications |
| `FederationRealtimeService` | Real-time cross-tenant events |
| `FederationFeatureService` | Feature flag management for federation |
| `FederatedGroupService` | Federated group operations |
| `FederatedMessageService` | Cross-tenant messaging |
| `FederatedTransactionService` | Cross-tenant financial transactions |
| `FederationExternalPartnerService` | External partner integrations |

### 1.3 Frontend Admin Modules — 140+ Source Files With No Tests

The entire admin panel is effectively untested at the component level. While batch test files exist (`modules-batch1.test.tsx`, `modules-batch2.test.tsx`), individual admin modules have zero dedicated tests:

| Module | Source Files | Dedicated Tests |
|---|---|---|
| `admin/modules/enterprise` | 20 | 0 |
| `admin/modules/super` | 16 | 0 |
| `admin/modules/broker` | 12 | 0 |
| `admin/modules/system` | 12 | 0 |
| `admin/modules/newsletters` | 10 | 0 |
| `admin/modules/groups` | 9 | 0 |
| `admin/modules/content` | 8 | 0 |
| `admin/modules/federation` | 8 | 0 |
| `admin/modules/advanced` | 7 | 0 |
| `admin/modules/gamification` | 6 | 0 |
| `admin/modules/matching` | 5 | 0 |
| `admin/modules/moderation` | 4 | 0 |
| `admin/modules/deliverability` | 4 | 0 |
| `admin/modules/timebanking` | 4 | 0 |
| **Total** | **~140** | **0** |

---

## Priority 2: Important Gaps

### 2.1 Untested API Controllers (12 controllers)

| Controller | Functionality |
|---|---|
| `MatchPreferencesApiController` | User matching preferences — core feature |
| `TwoFactorApiController` | 2FA setup/verification — security-critical |
| `MetricsApiController` | Platform metrics/analytics |
| `NewsletterApiController` | Public newsletter operations |
| `EmailAdminApiController` | Email administration |
| `HelpApiController` | Help/support system |
| `LegalAcceptanceApiController` | Legal document acceptance — compliance-critical |
| `UserInsuranceApiController` | Insurance certificate management |
| `AdminInsuranceCertificateApiController` | Admin insurance management |
| `PagesPublicApiController` | Public page rendering |
| `BaseApiController` | Base controller shared logic |

### 2.2 Untested Backend Services (36 services)

Key untested services beyond federation:

| Service | Category | Risk Level |
|---|---|---|
| `TwoFactorChallengeManager` | Security | High |
| `SuperAdminAuditService` | Compliance | High |
| `TenantHierarchyService` | Multi-tenancy | High |
| `TenantSettingsService` | Multi-tenancy | High |
| `TenantVisibilityService` | Multi-tenancy | High |
| `AuditLogService` | Compliance | High |
| `InsuranceCertificateService` | Business logic | Medium |
| `PerformanceMonitorService` | Operations | Medium |
| `PersonalizedSearchService` | Feature | Medium |
| `SmartMatchingAnalyticsService` | Feature | Medium |
| `SmartSegmentSuggestionService` | Feature | Medium |
| `ListingRankingService` | Feature | Medium |
| `MemberRankingService` | Feature | Medium |
| `SearchAnalyzerService` | Feature | Medium |
| `EmailMonitorService` | Operations | Medium |
| `MailchimpService` | Integration | Medium |
| `AdminBadgeCountService` | Feature | Low |
| `HelpService` | Feature | Low |
| `RedisCache` | Infrastructure | Low |
| `SentryService` | Infrastructure | Low |
| `VettingService` | Feature | Low |

### 2.3 SuperAdmin Controller Tests Missing

Six SuperAdmin controllers (`AuditController`, `BulkController`, `DashboardController`, `FederationController`, `TenantController`, `UserController`) have no dedicated tests. These controllers manage critical platform administration — tenant management, bulk operations, and audit trails.

### 2.4 PageBuilder/Renderer Coverage

15 block renderers and 2 core PageBuilder files have no dedicated tests. The `SmartBlockRendererTest.php` in Core tests may cover some of these, but individual renderer testing is absent.

---

## Priority 3: Quality Improvements

### 3.1 Low Use of Data Providers

Only **4 out of 355** PHP test files use `@dataProvider`. Data providers enable testing multiple input scenarios efficiently. Services like `MatchingService`, `WalletService`, `ExchangeWorkflowService`, and `ValidationService` would benefit from parameterized test data.

### 3.2 Limited Exception/Error Path Testing

Only **17 out of 355** PHP test files test for expected exceptions. Error handling paths in these critical services should be tested:
- Payment/wallet operations (insufficient funds, double-spend)
- Authentication failures (invalid tokens, expired sessions, rate limiting)
- Federation failures (network timeouts, invalid JWT, partner unavailable)
- File upload failures (oversized files, invalid types, storage full)

### 3.3 Frontend Accessibility Testing

Only **35 out of 129** frontend test files use accessibility-related queries (`getByRole`, `getByLabelText`, `aria-`). Given this is a community platform, accessibility compliance is important. Focus areas:
- Form components (inputs, selects, checkboxes should be labeled)
- Navigation components (proper ARIA landmarks)
- Modal dialogs (focus trapping, escape key handling)
- Dynamic content (live regions for notifications, toasts)

### 3.4 Frontend User Interaction Testing

Only **71 out of 129** frontend test files simulate user interactions. Pages with complex forms and workflows need thorough interaction testing:
- **Compose Hub** tab components (`compose/tabs/` — 5 files, 0 tests)
- **Social components** (`components/social/` — 3 files, 0 tests)
- **Error pages** (`pages/errors/` — 2 files, 0 tests)
- **Chat page** (`pages/chat/` — 1 file, 0 tests)
- **Connections page** (`pages/connections/` — 1 file, 0 tests)
- **Matches page** (`pages/matches/` — 1 file, 0 tests)

### 3.5 Integration Test Coverage

Only **5 integration tests** exist (Exchange, Group, Listing, Message, User journeys). Important cross-cutting flows missing:
- **Gamification integration** — earning XP, unlocking achievements, leaderboard updates
- **Federation integration** — cross-tenant resource discovery and interaction
- **Notification integration** — trigger event -> notification dispatch -> push/email delivery
- **Admin workflow integration** — moderation actions, content approval flows
- **Wallet/timebanking integration** — end-to-end credit lifecycle

---

## Recommendations by Priority

### Immediate (High Impact, High Risk)

1. **Implement E2E tests** for authentication, exchange lifecycle, and wallet operations
2. **Add tests for `TwoFactorChallengeManager`** and `TwoFactorApiController` — security-critical auth bypass risk
3. **Test tenant isolation services** (`TenantHierarchyService`, `TenantSettingsService`, `TenantVisibilityService`) — multi-tenancy data leak risk
4. **Test `LegalAcceptanceApiController`** — compliance risk for legal document acceptance tracking
5. **Test `SuperAdminAuditService`** and SuperAdmin controllers — privileged operations need verification

### Short-Term (Coverage Expansion)

6. **Add tests for the 16 federation services** — complex distributed operations need safety nets
7. **Raise frontend coverage thresholds** incrementally (30% → 50% → 70%) and add tests for admin modules starting with `enterprise` (20 files) and `super` (16 files)
8. **Add error path tests** for wallet, matching, and exchange services using `expectException`
9. **Add `@dataProvider` parameterized tests** for services with complex input validation

### Medium-Term (Quality Depth)

10. **Add integration tests** for gamification, federation, and notification flows
11. **Add accessibility tests** for all form-heavy pages (auth, settings, compose, profile)
12. **Test all 15 PageBuilder renderers** individually — they produce user-facing HTML
13. **Add frontend tests for social components, chat, connections, and matches pages**

### Long-Term (Sustainability)

14. **Set up coverage gates in CI** — block PRs that decrease line coverage
15. **Add mutation testing** (e.g., Infection for PHP) to validate that tests catch real bugs, not just execute code paths
16. **Create a test quality dashboard** tracking coverage trends over time

---

## Infrastructure Issues Found & Fixed

### Fixed: PSR-4 Namespace Mismatches (77 test files)

77 test files used `namespace Tests\...` instead of the correct `namespace Nexus\Tests\...` as defined in `composer.json` (`"Nexus\\Tests\\": "tests/"`). This caused Composer autoloader warnings and meant these test classes could not be autoloaded by other tests or tooling.

**Breakdown by directory:**
| Directory | Files Fixed |
|---|---|
| `tests/Services/` | 64 |
| `tests/Services/AI/` | 6 |
| `tests/Models/` | 5 |
| `tests/Controllers/Api/` | 1 |
| `tests/Controllers/` | 1 |
| **Total** | **77** |

### Fixed: PHPUnit XML Schema Deprecation

The `phpunit.xml` validated against a deprecated schema. Migrated to the PHPUnit 10.5 schema using `--migrate-configuration`, eliminating the deprecation warning that would cause CI failures when `failOnWarning="true"` is set.

### Noted: Model Test Gap

Only 1 model (`UserBadge`) out of 59 lacks a dedicated test file.

### Noted: Middleware Test Gap

Only 1 middleware (`PerformanceMonitoringMiddleware`) out of 8 lacks a dedicated test file.

### Noted: Core Test Gaps

4 Core classes lack dedicated tests:
- `AdminAuth` — has Unit test but no Core-level integration test
- `HtmlSanitizer` — tested in `Services/HtmlSanitizerTest` (duplicate exists in both `src/Core/` and `src/Helpers/`)
- `ImageUploader` — tested in `Services/ImageUploaderTest`
- `TotpEncryption` — security-critical TOTP encryption, partially tested via `TotpServiceUnitTest`

### Noted: 25 Front-facing Controllers Without Tests

These non-API, non-admin controllers serve pages and handle user-facing routes but have no test coverage:

`AchievementsController`, `ContactController`, `CronController`, `ExchangesController`, `FederationStreamController`, `FeedController`, `GroupAnalyticsController`, `HelpController`, `InsightsController`, `LeaderboardController`, `LegalDocumentController`, `MasterController`, `MessageController`, `NewsletterSubscriptionController`, `NewsletterTrackingController`, `NexusScoreController`, `NotificationController`, `OnboardingController`, `OrgWalletController`, `ReportController`, `RobotsController`, `SitemapController`, `SocialAuthController`, `UserPreferenceController`, `AdminController`
