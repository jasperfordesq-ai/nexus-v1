# Test Coverage Analysis

## Executive Summary

The Nexus V1 codebase contains **~1,200 source files** and **~500 test files** across a PHP backend and React/TypeScript frontend. While the testing infrastructure is mature (PHPUnit, Vitest, Playwright config), there are significant gaps in coverage depth, critical-path testing, and coverage enforcement.

| Layer | Source Files | Test Files | Estimated Coverage |
|-------|-------------|------------|-------------------|
| PHP Backend | ~445 | ~370 | ~43% file-level |
| React Frontend | ~400 | ~129 | ~32% file-level |
| E2E (Playwright) | — | 0 | 0% (configured but no tests) |
| **Total** | **~845** | **~499** | **~40%** |

---

## Current Coverage Thresholds

### React (Vitest) — Too Low
```
statements: 30%   (target should be 70%+)
branches:   25%
functions:  25%
lines:      30%
```
The config itself acknowledges this: *"Current baseline: ~40%. Target: 70%+"*

### PHP (PHPUnit) — Not Set
No minimum coverage thresholds are configured in `phpunit.xml`. Coverage could regress to 0% without any CI failure.

---

## Priority 1: Critical Gaps (High Impact, High Risk)

### 1. No E2E Tests Exist

Playwright is fully configured (`playwright.config.ts`) with 5 test projects (chromium, mobile-chrome, admin, unauthenticated, setup) and comprehensive npm scripts — but the `e2e/tests/` directory is empty. **Zero end-to-end tests exist.**

**Recommendation:** Write E2E tests for the top 5 critical user journeys:
- User registration and login (including 2FA flow)
- Creating a listing and requesting an exchange
- Compose flow (post, event, listing creation)
- Admin dashboard access and user management
- Federation partner discovery and cross-community interaction

### 2. Form Submission & User Interaction Tests Missing (React)

Pages like `LoginPage`, `RegisterPage`, `ForgotPasswordPage`, and `ResetPasswordPage` have test files, but they **only check for element presence** — no form submissions, input validation feedback, or error state testing.

**Untested interactions include:**
- Typing into form fields and submitting
- Validation error display (invalid email, weak password)
- API error handling on failed login/registration
- Success redirects after authentication
- Two-factor authentication challenge flow

**Recommendation:** Add interaction tests using `@testing-library/user-event` for all auth pages and the ComposeHub form tabs.

### 3. Core Infrastructure Has No Tests for Security-Critical Files (PHP)

The following security-critical files in `src/Core/` lack dedicated tests:

| File | Risk | Notes |
|------|------|-------|
| `HtmlSanitizer.php` | **XSS Prevention** | No test — this is the primary XSS defense |
| `ImageUploader.php` | **File Upload Security** | No test (only `ImageUploaderTest` in Services tests the service wrapper) |
| `TotpEncryption.php` | **2FA Crypto** | Only 1 unit test exists in `tests/Unit/` |

**Recommendation:** Write thorough unit tests for `HtmlSanitizer` (XSS bypass attempts, allowed/denied tags, attribute filtering) and `ImageUploader` (MIME validation, path traversal, file size limits).

### 4. PageBuilder Has Zero Tests (PHP)

The `src/PageBuilder/` module (`PageRenderer.php`, `BlockRegistry.php`) has no test coverage. This is the dynamic page rendering system that processes user-configured content.

**Recommendation:** Add unit tests for block registration, rendering output, and malformed input handling.

---

## Priority 2: Significant Gaps (Medium Impact)

### 5. React Components — 65% Untested

| Category | With Tests | Without Tests | Gap |
|----------|-----------|---------------|-----|
| Components | 36 | 67 | 65% |
| Pages | 38 | 33+ | 46% |
| Hooks | 7 | 6 | 46% |
| Contexts | 7 | 2 | 22% |
| Lib utilities | 10 | 5 | 33% |

**Key untested components:**
- **Compose tabs** (`PostTab`, `ListingTab`, `EventTab`, `GoalTab`, `PollTab`) — core content creation
- **Image uploaders** (`ImageUploader`, `MultiImageUploader`) — file handling
- **Social components** (`CommentsSection`, `ShareButton`, `LikersModal`) — engagement features
- **Location components** (`LocationMap`, `PlaceAutocompleteInput`, `EntityMapView`) — map integrations
- **AI features** (`AiAssistButton`, `AiChatPage`) — AI-powered functionality

**Recommendation:** Prioritize compose tabs (they are core to the product), social interaction components, and image uploaders.

### 6. Custom Hooks Missing Tests

The following hooks have no tests:

| Hook | Functionality |
|------|--------------|
| `useDraftPersistence` | Auto-saving user drafts to localStorage |
| `useSocialInteractions` | Like, comment, share actions |
| `useMediaQuery` | Responsive breakpoint detection |
| `useMenus` | Dynamic menu state management |
| `useLegalGate` | Legal compliance gating |

**Recommendation:** `useDraftPersistence` and `useSocialInteractions` are highest priority — they handle user data and core social features.

### 7. Detail Pages Untested (React)

List/index pages are generally tested, but **detail and create pages are not:**

- `EventDetailPage`, `CreateEventPage`
- `ListingDetailPage`, `CreateListingPage`
- `ExchangeDetailPage`, `RequestExchangePage`
- `GroupDetailPage`, `CreateGroupPage`
- `BlogPostPage`
- `ConversationPage` (messaging)
- `ConnectionsPage`

These are arguably the most complex pages in the app.

**Recommendation:** Add tests for detail pages, focusing on data loading states, error handling, and user actions (RSVP, request exchange, join group, etc.).

### 8. PHP Controller Coverage Gaps

While ~68% of controllers have test files, several admin controllers lack tests:

- Enterprise sub-controllers (GDPR, monitoring, secrets)
- Some federation controllers
- Volunteer management controllers

**Recommendation:** Ensure all admin controllers that modify user data or handle sensitive operations have at least smoke-level tests.

---

## Priority 3: Quality Improvements (Lower Risk, Higher Polish)

### 9. PHP Tests Use "Simulated" Responses

Many PHP controller tests assert against `$response['status'] === 'simulated'` rather than validating actual response bodies. This means:
- Response data structure is never verified
- Field values aren't checked
- Pagination metadata isn't validated
- Error message formats aren't confirmed

**Recommendation:** Gradually migrate controller tests from simulated responses to real request/response cycles with full body assertions.

### 10. React Tests Over-Mock Dependencies

Nearly every React test file mocks `framer-motion`, `react-router-dom`, and translation functions. This:
- Makes tests brittle to library API changes
- Reduces confidence that components work in real conditions
- Creates maintenance burden (updating mocks across 100+ files)

**Recommendation:** Create a shared test wrapper that provides real router context and minimal animation stubs, reducing per-file mock boilerplate.

### 11. No Performance or Re-render Tests

No tests verify:
- Component memoization effectiveness
- Unnecessary re-renders on state changes
- Large list virtualization behavior
- Bundle size regression

**Recommendation:** Add `React.Profiler`-based render-count tests for performance-critical components (FeedCard, ComposeHub, DashboardPage).

### 12. Accessibility Testing Is Minimal

Only `LoadingScreen` has explicit accessibility assertions (`aria-busy`, `role="status"`). No other components verify:
- Keyboard navigation
- Screen reader announcements
- ARIA attributes on interactive elements
- Focus management in modals

**Recommendation:** Add accessibility assertions to all modal components, form components, and navigation elements. Consider integrating `jest-axe` / `vitest-axe` for automated a11y audits.

---

## Priority 4: Infrastructure & Process

### 13. Set PHP Coverage Thresholds

Add minimum thresholds to `phpunit.xml` to prevent coverage regression:

```xml
<coverage>
  <report>...</report>
  <thresholds>
    <line value="50"/>
    <function value="40"/>
  </thresholds>
</coverage>
```

### 14. Raise React Coverage Thresholds Incrementally

Update `vitest.config.ts` thresholds on a quarterly schedule:
- **Q1:** statements 40%, branches 35%, functions 35%, lines 40%
- **Q2:** statements 50%, branches 45%, functions 45%, lines 50%
- **Q3:** statements 60%, branches 55%, functions 55%, lines 60%
- **Target:** statements 70%, branches 60%, functions 60%, lines 70%

### 15. Add Coverage Reporting to CI

Ensure the CI pipeline:
- Runs both PHPUnit and Vitest with coverage
- Fails on threshold violations
- Posts coverage diffs on pull requests (e.g., via Codecov or similar)

---

## Suggested Test Writing Order

Based on risk, impact, and effort, here is the recommended order for writing new tests:

| Order | Area | Effort | Impact |
|-------|------|--------|--------|
| 1 | `HtmlSanitizer.php` unit tests | Low | Critical (XSS) |
| 2 | Auth page interaction tests (Login, Register) | Medium | Critical (user onboarding) |
| 3 | Compose tab tests (PostTab, ListingTab, EventTab) | Medium | High (core product) |
| 4 | E2E: Login + create listing journey | High | Very High (full-stack confidence) |
| 5 | `useDraftPersistence` and `useSocialInteractions` hooks | Low | Medium (data integrity) |
| 6 | Detail page tests (EventDetail, ListingDetail) | Medium | High (user-facing) |
| 7 | PageBuilder unit tests | Low | Medium (content rendering) |
| 8 | Set PHP coverage thresholds in CI | Low | High (regression prevention) |
| 9 | Social components (CommentsSection, ShareButton) | Medium | Medium |
| 10 | Accessibility audit integration (vitest-axe) | Low | Medium (compliance) |

---

*Generated: 2026-03-01*
