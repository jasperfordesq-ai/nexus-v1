# Changelog

All notable changes to Project NEXUS will be documented in this file.

The format is based on [Keep a Changelog](https://keepachangelog.com/en/1.0.0/),
and this project adheres to [Semantic Versioning](https://semver.org/spec/v2.0.0.html).

---

## [Unreleased]

### Added
- Comprehensive documentation suite
  - API Endpoints V2 reference (80+ endpoints documented)
  - React Component Library documentation (40+ components)
  - Developer Guide for extending the platform
  - User guides for Smart Matching and Reviews System

---

## [3.0.0] - 2026-03-27

This release covers nearly all development from 2026-01-18 to present. It is a **major release** representing the full maturation of the platform: a complete React SPA frontend, a full Laravel 12 migration, a Capacitor mobile app, WebAuthn passkey support, 7-language i18n, federation, social features, and comprehensive security hardening.

### Added

#### Laravel 12 Migration (Completed 2026-03-21)
- Laravel 12.54 is now the sole HTTP handler — all 1,218 routes wired to Laravel controllers
- All 223 services converted to native Eloquent implementations (zero stubs remain)
- 5 Event Listeners fully implemented: `NewUserRegistered`, `ExchangeCompleted`, `ListingCreated`, `MessageSent`, `VolunteerHoursLogged`
- Full 386-table baseline migration (`artisan migrate` works from scratch)
- Laravel scheduler replaces custom cron runner for all 25 scheduled tasks
- `Nexus\` namespace fully eliminated — 100% `App\` namespace throughout
- Dead legacy code deleted: 192 `src/Services` and `src/Models` files, 73 legacy framework files, all legacy PHP frontend controllers and views (civicone, modern, starter themes)
- Maintenance mode system: two-layer (file + database) with `scripts/maintenance.sh` and automatic deploy integration

#### React Frontend (Primary UI)
- Full React 18 + TypeScript + HeroUI + Tailwind CSS 4 SPA replacing all PHP-rendered user-facing views
- Capacitor-based native mobile app (iOS/Android) from the same React codebase
- React Native Expo mobile app with separate test suite (`mobile/`)
- 108 admin panel pages with 100% parity to legacy PHP admin
- Super Admin panel for cross-tenant management
- Universal Compose Hub with feature-gated tabs (listing, event, group, poll, post)
- PostDetailPage with direct post links and auto-open comments
- Explore / For You page with 7-source recommendation algorithm
- PWA service worker with auto-reload on stale chunks and update banner
- Google Maps integration (replacing Mapbox) with marker clustering and near-me filters
- Sales site at `project-nexus.ie` (separate container)
- Component refactors: `ConversationPage`, `GroupDetailPage`, `SettingsPage` split into sub-components

#### Authentication & Security
- WebAuthn / passkeys authentication (`react-frontend/src/lib/webauthn.ts`, `BiometricSettings.tsx`)
- TOTP two-factor authentication with trusted device support
- Registration policy engine: email verification gate, admin approval gate, invite codes, waitlist mode
- Identity verification module with per-tenant provider credential management (AES-256-GCM)
- Mandatory profile photo + bio enforcement on onboarding
- 7-layer regression prevention system (pre-commit → pre-push → CI → PR → Zod → local → deploy)
- Redis-based rate limiting on all API endpoints
- CSRF protection on all write operations and forms
- Sentry error tracking integrated in PHP and React
- Dependabot CVE alerts resolved; `rollup`, `dompurify`, `serialize-javascript`, `tar`, `basic-ftp` patched

#### Internationalisation (i18n)
- 7 languages: English, Irish (Gaeilge), German, French, Italian, Portuguese, Spanish
- All languages enabled for every tenant; tenant default language overrides browser detection
- 33 i18n namespace files per language (~4,571 keys each) covering all modules
- Language switcher on unauthenticated navbar and auth pages
- Translation drift detection added to CI and pre-push hook
- PHP admin i18n groundwork (English-only for now)

#### Federation
- Federation API V1 live: Neighborhoods, Credit Agreements, External Partners
- Partner detail page at `/federation/partners/:id`
- Federation connections route and `FederationConnection` type
- All federation features enabled by default for new tenants
- Federation gating uses dedicated tables (`federation_system_control`, `federation_tenant_whitelist`, `federation_tenant_features`)

#### Social Features
- Post reactions (emoji) on feed items and comments
- User presence indicators (online/away/offline) with heartbeat
- Link previews for shared URLs
- Media carousel with lightbox and thumbnail navigation
- @mention system with batch resolution and banned-user guards
- Stories feature with 30-story limit, audience controls, and IDOR prevention
- Video player with accessibility (focus restore, aria-live counter)
- Explore page with category chips, infinite scroll For You feed, and trending content
- Group feed tab and listing social features via shared social module
- Profile aggregated activity feed

#### Jobs & Volunteering Modules
- Enterprise-grade Jobs module: job templates, hiring teams, inline interview/offer response, salary display, bias audit, candidate moderation, talent search
- Volunteering module expansion: 7 new services, 5 React tabs, QR check-in, shift management, recurring shifts, expense tracking, certificates
- Organisation registration and opportunity posting UI
- Volunteer notification dispatch on application events

#### Polls, Ideation & Other Modules
- Polls module: create, vote, and results pages
- Ideation Challenges module: create campaigns, submit ideas, favourites, tags, cover images, draft saving, "turn ideas into teams" conversion
- 96 additional features across 18 modules implemented in the 2026-03-01 build sprint

#### Algorithms & Search
- Meilisearch integration with SQL fallback for listings search; index synced on create/update/delete
- EdgeRank feed algorithm upgraded to 15-signal pipeline with full CTR tracking
- Collaborative Filtering (`CollaborativeFilteringService`) for personalised recommendations
- OpenAI embedding-based matching (`EmbeddingService`)
- `FeedRankingService` with geo-decay, context-aware mode, and configurable signals
- `GroupRecommendationEngine` with cold-start handling
- Rubix ML, Wilson Score, and Bayesian average for member and listing ranking
- Cross-Module Matching Service with debug panel in admin
- User–User CF boost, dismissed listings suppression, skill proficiency in matching
- Batch geocode script (`scripts/batch_geocode_users.php`) for backfilling user coordinates
- OpenAPI 3.0 specification for V2 API added to repo

#### Onboarding
- Admin-configurable onboarding module (5 phases): backend config, admin UI, dynamic frontend steps, safeguarding step, listing creation modes (draft/review/active)
- Broker dashboard integration with safeguarding presets
- Atomic `/complete` transaction wrapping full onboarding flow

#### CRM & Admin
- CRM module: member notes, coordinator tasks, onboarding funnel, CRM webhook dispatches for volunteering events
- Newsletter admin: full parity with legacy PHP admin, stats improvements, activity page, SendGrid provider, per-tenant email config
- Tenant CRUD with full parity to legacy PHP admin including super admin role
- Registration policy admin UI with explanations for all modes
- 6 new admin management pages, algorithm settings page, Match Debug Panel
- Tenant super admin role; tenant lifecycle hardening

#### Email & Notifications
- SendGrid email provider with per-tenant configuration and SPF/DMARC deliverability fixes
- Email notifications for events, groups, endorsements, wallet credits received, reviews received
- All notification links made fully tenant-aware
- Fix for 404 dead links across all email notification types
- Nightly DB backup cron

#### Infrastructure & DevOps
- Git-based production deployment replacing file upload
- `scripts/safe-deploy.sh` with full/quick/rollback/status modes; automated migrations on deploy
- Docker production images protected from dev-image contamination
- Cloudflare cache purge automated in deploy scripts
- `scripts/maintenance.sh` for atomic two-layer maintenance mode toggle
- Migrations tracked in git; all legacy SQL migrations committed to `migrations/`
- Ahrefs Web Analytics on sales site and React app
- PHP memory_limit raised to 4G for PHPUnit; 8G for production containers
- `.gitattributes` enforcing LF line endings on shell scripts

#### Testing & Quality
- PHPStan level 3 added (warning-only; 123 pre-existing errors baseline)
- ESLint 9 flat config with 929-warning baseline
- 4,504+ PHPUnit tests (0 errors, 0 failures at point of Laravel migration merge)
- 118 Eloquent model factories added; 64 service test suites; 88 coverage-gap test files
- Vitest test suite for React with 71 WebAuthn tests, 66 ComposeHub tests, 367 social tests
- React Native Expo mobile test suite with auth, hooks, and screen tests
- E2E tests migrated fully to React frontend (Playwright)
- Lighthouse CI added for performance regression prevention
- Vitest Axe accessibility testing integrated in CI
- API contract test stage added to CI pipeline
- Translation drift detection in CI and pre-push hook

### Changed

- **Primary frontend** is now React SPA only — all PHP-rendered user pages removed
- PHP admin legacy views remain only at `/admin-legacy/` and `/super-admin/`
- Routes split from monolithic `routes.php` (2,487 lines) into 14 domain-specific partials
- Tenant routing: `/:tenantSlug` URL prefix with 42 reserved paths and `tenantPath()` helper
- Login is fully tenant-URL-aware; super admin can access any tenant
- Maps provider migrated from Mapbox to Google Places / Google Maps API
- Feed algorithm: default mode is Recent, EdgeRank as alternative; unified `feed_activity` table
- Compose Hub: Post tab removed; Listing set as default tab
- Navbar redesigned with mega menu, utility bar, command palette, and intelligent collapsing
- More dropdown reorganised with Partner Communities collapsible and Activity dividers
- `avatar` column renamed `avatar_url` across the entire codebase (4 affected files)
- Irish-specific phone and location validation removed globally; international E.164 throughout
- CORS wildcards replaced with per-origin validation
- `routes.php` and all controllers now under `app/` namespace exclusively

### Fixed

- Cross-tenant IDOR in `Group::findById()` — missing `tenant_id` scope (security audit 2026-03-09)
- `AdminContentApiController` menu_items DELETE/UPDATE lacked embedded tenant check (security audit 2026-03-09)
- `WalletFeatures` fatal error and `Exchanges` config regression
- Pusher auth 401 on login page; Pusher unsubscribe against closing WebSocket; Pusher 405 in production
- Feed load-more returning duplicate items from cursor pagination
- Balance alert emails spamming all users instead of the target user
- `register()` function not granting welcome credits on no-approval tenants
- Blog infinite re-render loop (cursor in `useCallback` deps)
- Avatar uploads: DB update silently failing, double `/api/` URL prefix, file permission bug in production
- `FeedRankingService::getConfig()` visibility (private → public)
- Legal document GET routes moved outside `auth:sanctum` — were silently returning generic defaults
- Service worker auto-reloading during message composition
- PWA icons corrected; stale chunk auto-reload on deploy for both Chrome and Firefox error patterns
- AbortController race conditions resolved across 83 pages
- `estimated_hours` column PDOException on listings creation
- `created_by` column reference on jobs page (should be `user_id`)
- `image_url` → `image` column on `feed_posts` table
- Sanctum cross-tenant auth bypass
- GDPR column names (`type`, `location`) fixed across multiple endpoints
- Cookie consent Bearer-token-aware auth; returns 200 when no record found
- Onboarding redirect loop resolved using `onboarding_completed` flag as sole source of truth
- CMS page cascade delete for menu items
- Custom domain tenant resolution: path no longer mistaken for slug
- Presence heartbeat 429 rate limiting
- Broken avatar URLs from stale domain references after legacy frontend removal
- Duplicate comment reactions route removed
- `longitude` field: standardised to `lon` (not `lng`) across nearby endpoint calls

### Security

- Critical: Cross-tenant IDOR in `Group::findById()` — fixed 2026-03-09 (see audit-history.md)
- Critical: `AdminContentApiController` DELETE/UPDATE lacking tenant check — fixed 2026-03-09
- Critical: God mode privilege escalation vulnerabilities fixed
- Critical: Open redirect vulnerabilities removed from login scripts
- Critical: SQL injection protections hardened across 50+ files
- Critical: Hardcoded production DB credentials removed from tracked files
- Critical: Pusher fallback key removed from `NotificationsContext`
- High: XSS vulnerabilities in view files fixed; DOMPurify and serialize-javascript patched
- High: CORS wildcards replaced with origin validation
- High: Rate limiting added to auth endpoints; login relaxed to 10 attempts/5 min
- High: Registration policy gates enforced on all entry points (not just registration)
- High: Super admin cross-tenant access control hardened
- High: Tenant isolation gaps in events, groups, messages, exchanges hardened
- High: 2FA enforced for all admin users
- High: AES-256-GCM encryption for per-tenant identity provider credentials
- Medium: `nosemgrep` annotations added for Semgrep false positives
- 18 tenant isolation regression tests added; admin security regression gate script added
- SPDX/AGPL-3.0-or-later headers on 100% of source files (1,230/1,230 files verified)

### Removed

- All legacy PHP frontend themes: civicone, modern (user-facing), starter — fully deleted
- 229 dead PHP frontend routes and 42 legacy frontend controllers
- 192 `src/Services` and `src/Models` files replaced by native Laravel equivalents
- Legacy `Database::` class replaced everywhere by Laravel DB facade
- `Nexus\` namespace entirely eliminated from the codebase
- 73 dead legacy framework files and all legacy ob_start delegation patterns

---

## [2.0.0] - 2024-02-13

### Added - Major Features

#### Smart Matching System
- AI-powered user and listing matching algorithm
- Match scores (0-100) based on 6 factors: category compatibility, location proximity, availability overlap, mutual interests, skill complementarity, and activity level
- Detailed match explanations and improvement suggestions
- Customizable user preferences (max distance, preferred categories, availability schedule)
- Match analytics for admins (quality metrics, acceptance rates, category trends)
- Real-time match notifications
- `MatchesApiController` with 5 endpoints

#### Broker Approval Workflow
- Optional broker review for high-quality matches
- Admin dashboard at `/admin/match-approvals` for reviewing requests
- Configurable auto-approval threshold (matches above X score bypass broker)
- Email notifications to brokers on new requests
- Approval/rejection notifications to users
- Statistics dashboard (approval rates, average approval time, etc.)
- `MatchApprovalsApiController` with 7 endpoints
- `MatchApprovalWorkflowService` for business logic

#### NexusScore (1000-Point Reputation System)
- Comprehensive reputation scoring across 6 categories:
  - **Engagement** (250 pts): Activity level, exchanges completed, messages sent
  - **Quality** (200 pts): Review ratings, on-time completions, dispute rate
  - **Volunteer** (200 pts): Volunteer hours logged, opportunities participated
  - **Activity** (150 pts): Daily logins, profile completeness, responsiveness
  - **Badges** (100 pts): Achievements earned, milestones reached
  - **Impact** (100 pts): Community contributions, referrals, leadership
- 5 tier system: Beginner, Intermediate, Advanced, Expert, Elite
- Personalized insights and improvement tips
- Milestone tracking with rewards
- Community-wide leaderboard
- `NexusScoreApiController` with 6 endpoints
- `NexusScoreService` for calculations

#### Personalized Search
- AI-powered search ranking based on user context
- Intent analysis (location-based, category-specific, general search)
- Personalized result ordering using user behavior
- Trending searches dashboard
- Search history tracking
- Search feedback system (click/skip/helpful/not_helpful)
- `PersonalizedSearchApiController` with 5 endpoints
- `PersonalizedSearchService` and `SearchAnalyzerService`

#### Member Ranking (CommunityRank)
- Algorithm-driven member ranking separate from NexusScore
- Weighted scoring: activity recency, exchange quality, community engagement
- Public leaderboard at `/members/ranked`
- Detailed rank explanations
- `MemberRankingApiController` with 3 endpoints

#### Referral System
- Unique referral codes per user (e.g., `JANE2024`)
- Shareable referral links
- Tracking referral statuses: pending → activated → engaged
- XP rewards for successful referrals
- Referral badges (First Referral, Connector, Ambassador, etc.)
- Leaderboard for top referrers
- Statistics dashboard (total referrals, active count, total XP earned)
- `ReferralsApiController` with 5 endpoints
- `ReferralService` for tracking and rewards

#### Reviews System V2
- Multi-criteria ratings (overall, punctuality, quality, communication)
- Anonymous review option
- Written reviews (max 2000 characters)
- Review moderation and dispute system
- Review statistics (average rating, criteria breakdowns, trends)
- Federated reviews (cross-community if enabled)
- `ReviewsApiController` with updated endpoints
- React `ReviewModal` component

#### Voice Messaging
- Record and send voice messages in conversations
- Waveform visualization during recording
- Playback controls (play/pause, seek, speed adjustment)
- Maximum duration limits (configurable)
- Browser permission handling
- `VoiceRecorder` and `VoiceMessagePlayer` React components
- `/api/v2/messages/voice` and `/api/v2/messages/upload-voice` endpoints

#### Admin Analytics Dashboard
- Overview metrics (users, transactions, listings, engagement)
- User analytics (growth charts, cohort analysis, retention)
- Engagement metrics (DAU/MAU, session duration, feature usage)
- Transaction analytics (volume, trends, category breakdown)
- Listing analytics (creation rate, conversion rate, categories)
- Data export (CSV download for all analytics)
- `AdminAnalyticsApiController` with 7 endpoints

---

### Added - React Frontend Components

#### Form Components
- `ImageUpload`: Multi-file upload with drag-drop, crop, reorder
- `MapPicker`: Interactive location selection with Leaflet
- `DateTimePicker`: Date/time selection with timezone support

#### Review Components
- `ReviewModal`: Submit reviews with criteria ratings and text
- `StarRating`: Interactive 1-5 star rating component
- `ReviewCard`: Display individual review
- `ReviewsList`: Paginated review list with filters

#### Messaging Components
- `VoiceRecorder`: Record audio with waveform preview
- `VoiceMessagePlayer`: Audio playback with controls

#### Wallet Components
- `TransferModal`: Transfer time credits to users
- `TransactionDetailModal`: View transaction details
- `BalanceChart`: Line chart of balance over time
- `SpendingBreakdown`: Pie chart of spending by category
- `TransactionFilters`: Filter controls for transactions

#### Feedback Components
- `EmptyState`: Display when no content available
- `LoadingScreen`: Full-screen loading indicator
- `OfflineIndicator`: Banner when user is offline
- `SessionExpiredModal`: Prompt to re-login on token expiry
- `DevModePopup`: Development mode notification
- `ErrorBoundary`: Graceful error handling
- `ErrorMessage`: Inline error display
- `NetworkError`: Network connectivity errors
- `ValidationError`: Form validation errors
- `PermissionDenied`: 403 error display
- `RateLimitError`: 429 error display
- `MaintenanceMode`: Scheduled maintenance display
- `LiveRegionAnnouncer`: Accessibility announcements

#### UI Components
- `BackToTop`: Floating scroll-to-top button
- `LazyImage`: Lazy-loaded images with placeholder
- `Skeleton`: Loading placeholders (text, card, avatar, image)
- `GlassCard`, `GlassButton`, `GlassInput`: Glassmorphism UI

#### Navigation Components
- `Breadcrumbs`: Tenant-aware breadcrumb navigation
- `MobileDrawer`: Slide-out mobile menu
- `QuickCreateMenu`: Dropdown for creating content

#### Social Components
- `FeedBadges`: Display user badges on feed posts

#### Legal Components
- `LegalDocument`: Display legal docs with table of contents
- `TableOfContents`: Sticky TOC for long documents
- `AcceptanceModal`: Accept legal documents

#### Moderation Components
- `ReportModal`: Report content for moderation

---

### Added - API Endpoints (V2)

Over 80 new V2 API endpoints added across 15 controllers:

- **MatchesApiController**: Match recommendations and explanations
- **MatchApprovalsApiController**: Broker approval workflow
- **NexusScoreApiController**: Reputation score and insights
- **PersonalizedSearchApiController**: AI search and trending
- **MemberRankingApiController**: Community member rankings
- **ReferralsApiController**: Referral tracking and stats
- **AdminAnalyticsApiController**: Admin analytics dashboard
- **MatchAnalyticsApiController**: Matching quality metrics
- **MatchLearningApiController**: ML feedback loop
- **GroupRecommendationsApiController**: Group discovery
- **SecurityApiController**: 2FA, blocked users, sessions
- **NewsletterApiController**: Newsletter management (admin)
- **BalanceAlertsApiController**: Low balance notifications
- **GeocodingApiController**: Address geocoding
- **TransactionExportApiController**: Transaction CSV export
- **ListingRiskApiController**: Fraud detection for listings

---

### Added - Services & Business Logic

- `SmartMatchingEngine`: Core matching algorithm
- `MatchApprovalWorkflowService`: Broker approval logic
- `NexusScoreService`: 1000-point scoring calculation
- `PersonalizedSearchService`: Search personalization
- `SearchAnalyzerService`: AI intent analysis
- `ReferralService`: Referral tracking and rewards
- `MatchLearningService`: Machine learning feedback
- `ListingRankingService`: Listing search ranking
- `GroupRecommendationsService`: Group suggestions
- `BalanceAlertService`: Low balance notifications
- `GeocodingService`: Address to coordinates
- `TransactionExportService`: Export transaction data
- `ListingRiskService`: Fraud detection

---

### Added - Database Tables & Migrations

- `match_cache`: Cached match scores and reasons
- `match_preferences`: User matching preferences
- `match_history`: Match interaction history
- `match_approvals`: Broker approval requests
- `referrals`: User referral tracking
- `search_logs`: Search query history
- `search_feedback`: Search result feedback
- `member_ranking_cache`: Member rank calculations
- `balance_alerts`: Low balance alert history
- `transaction_exports`: Export job tracking

---

### Added - Hooks

- `usePageTitle`: Set document title for SEO (used on 41+ pages)
- `useApi`: Fetch data with loading/error states
- `useMutation`: Handle POST/PUT/DELETE operations
- `usePaginatedApi`: Cursor-based pagination helper

---

### Enhanced

#### Existing Features

- **Search**: Now uses AI-powered personalization and ranking
- **Wallet**: Added balance charts, spending breakdown, detailed transaction views
- **Messages**: Added voice messaging, reactions, typing indicators
- **Reviews**: Enhanced with multi-criteria ratings and anonymous option
- **Notifications**: Real-time updates via Pusher, unread counts by type
- **Feed**: Configurable sort preferences (latest, top, following)
- **Events**: RSVP management, attendee check-in
- **Groups**: Recommendations, trending groups, similar groups
- **Listings**: Nearby listings with distance calculation
- **Exchanges**: Multi-step workflow (pending → accepted → active → completed)

#### User Experience

- Breadcrumbs on 8+ detail/create pages
- Back to top button (appears after 400px scroll)
- Offline indicator banner
- Session expired modal with re-login
- Empty state components across all pages
- Loading skeletons for better perceived performance
- Toast notifications for all actions
- Error boundaries for graceful error handling
- Responsive mobile design on all pages

---

### Security

- Rate limiting on all API endpoints (100-120 req/min for reads, 30 req/min for writes)
- CSRF protection on all write operations
- 2FA support (TOTP)
- Blocked users functionality
- Session management (view active sessions, revoke sessions)
- GDPR data request endpoint

---

### Performance

- Cursor-based pagination on all list endpoints (no more offset/limit)
- Redis caching for tenant bootstrap, CORS origins, match cache
- Lazy-loaded images in React
- Code-split React routes
- Debounced search inputs
- Optimized database indexes
- Image upload with compression

---

### Accessibility

- ARIA labels on all interactive elements
- Keyboard navigation support
- Screen reader compatibility
- Sufficient color contrast (WCAG 2.1 AA)
- Semantic HTML throughout
- Live region announcements for dynamic content

---

### Documentation

- **API_ENDPOINTS_V2.md**: Complete API reference (80+ endpoints)
- **REACT_COMPONENTS.md**: Component library documentation (40+ components)
- **DEVELOPER_GUIDE.md**: Developer guide for extending platform
- **User Guides**:
  - Smart Matching guide
  - Reviews System guide
- **CHANGELOG.md**: This file
- Inline JSDoc comments on all components
- PHPDoc comments on all services and controllers

---

## [1.5.0] - 2024-02-12

### Added

#### Tenant Routing (Phase 0 - TRS-001)
- Multi-tenant URL routing with optional `/:tenantSlug` path prefix
- Reserved paths system (42 segments) to prevent slug collision
- `tenantPath()` helper for all navigation
- `TenantShell` component for slug validation
- `CorsHelper` dynamic tenant domain origins with Redis caching
- All 37+ pages updated with tenant-aware routing
- 5 commits: infrastructure → auth → nav → pages → PHP backend

#### Legal Documents API
- Tenant-specific legal content API
- `LegalDocumentsApiController` for Terms, Privacy, Cookies
- Dynamic content per tenant
- Last updated timestamps
- Acceptance tracking

#### Tenant-Specific About Pages
- `/about/our-story` - Community origin story
- `/about/how-it-works` - Platform explanation
- `/about/mission` - Mission and values
- `PagesApiController` for CMS page content

#### Frontend Polish
- Navbar dropdown bug fix (stuck-open issue resolved)
- Controlled state for dropdowns
- Production deploy checklist
- Verification reports for deployment

---

## [1.4.0] - 2024-02-11

### Added

#### React Frontend - Full Feature Build
- **Tenant-aware feature/module gating** — `FeatureGate`, `hasModule()`, admin UI at `/admin/tenant-features`
- **4 full pages** replacing placeholders: Leaderboard, Achievements, Goals, Volunteering
- **3 full-stack features**: Feed, Blog (with V2 API), Resources (with V2 API)
- **V2 Comments API** — Threaded comments, reactions, @mentions
- **Dashboard/Profile** — Real gamification data from API
- **Help Center** — 6 FAQ categories, searchable accordion
- **About page rebuild** — Showcase with stats, how-it-works, values
- **UX infrastructure** — ScrollToTop, BackToTop, OfflineIndicator, usePageTitle (41 pages)
- **Navbar search** — Cmd+K shortcut, full overlay, keyboard navigation
- **Error handling** — Toast feedback, error states, ARIA on 6+ pages
- **Breadcrumbs** — Reusable component on 8 detail/create pages
- **ListingCard** — Enhanced grid/list views with avatars, hours, location

---

## [1.3.0] - 2024-02-07

### Added

#### Broker Approval Workflow (Initial)
- All matches require broker approval
- Admin dashboard at `/admin/match-approvals`
- `MatchApprovalWorkflowService.php`
- Per-tenant toggle in Smart Matching config

#### Light/Dark Mode Toggle
- `ThemeContext` for React
- CSS tokens for light/dark themes
- API endpoint `PUT /api/v2/users/me/theme`
- `users.preferred_theme` column
- Navbar sun/moon toggle

---

## [1.2.0] - 2024-01-22

### Added
- Layout banner feature flag for tenants
- Federation system controls
- Super admin panel for tenant hierarchy management
- Audit logging for admin actions

---

## [1.1.0] - 2024-01-15

### Added
- Initial React frontend with authentication
- V2 API infrastructure
- Tenant bootstrap API
- Multi-tenant routing foundation
- Docker development environment

---

## [1.0.0] - 2023-12-01

### Added
- Initial release of Project NEXUS
- Multi-tenant timebanking platform
- Listing marketplace (offers/requests)
- Time credit wallet system
- Messaging between members
- Events and RSVP system
- Groups and discussions
- User profiles and connections
- Gamification (XP, levels, badges)
- Admin panel
- Mobile-responsive design
- Email notifications
- Federation support

---

## Release Notes

### Version 2.0.0 Highlights

This is a **major release** with significant new features and improvements:

1. **Smart Matching**: AI-powered recommendations transform how users discover opportunities
2. **NexusScore**: 1000-point reputation system provides comprehensive trust signals
3. **Personalized Search**: AI search ranking delivers more relevant results
4. **Referral System**: Built-in viral growth mechanism with rewards
5. **Voice Messaging**: Richer communication with audio messages
6. **Enhanced Reviews**: Multi-criteria ratings build deeper trust
7. **Admin Analytics**: Data-driven insights for community managers

### Breaking Changes

None. Version 2.0.0 is **fully backwards compatible** with 1.x.

### Upgrade Path

No manual migration required. Simply deploy and run database migrations.

### Dependencies

- PHP 8.2+
- MariaDB 10.11+
- Redis 7+
- Node.js 18+ (for frontend build)

---

## Contributors

This release was made possible by the Project NEXUS development team and community contributors.

Special thanks to:
- **Claude (Anthropic)** - AI-assisted development, documentation, code review
- **Community Beta Testers** - Feedback on Smart Matching and NexusScore
- **Hour Timebank (Crewkerne)** - User testing and feedback

---

## Support

- **Issues**: https://github.com/project-nexus/nexus/issues
- **Documentation**: `/docs` directory
- **Email**: support@project-nexus.ie
- **Community Forum**: https://community.project-nexus.ie

---

[Unreleased]: https://github.com/jasperfordesq-ai/nexus-v1/compare/v3.0.0...HEAD
[3.0.0]: https://github.com/jasperfordesq-ai/nexus-v1/compare/v2.0.0...v3.0.0
[2.0.0]: https://github.com/jasperfordesq-ai/nexus-v1/compare/v1.5.0...v2.0.0
[1.5.0]: https://github.com/jasperfordesq-ai/nexus-v1/compare/v1.4.0...v1.5.0
[1.4.0]: https://github.com/jasperfordesq-ai/nexus-v1/compare/v1.3.0...v1.4.0
[1.3.0]: https://github.com/jasperfordesq-ai/nexus-v1/compare/v1.2.0...v1.3.0
[1.2.0]: https://github.com/jasperfordesq-ai/nexus-v1/compare/v1.1.0...v1.2.0
[1.1.0]: https://github.com/jasperfordesq-ai/nexus-v1/compare/v1.0.0...v1.1.0
[1.0.0]: https://github.com/jasperfordesq-ai/nexus-v1/releases/tag/v1.0.0
