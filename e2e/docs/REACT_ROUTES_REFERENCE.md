# React Frontend Routes Reference

## Overview

All routes work with optional tenant slug prefix:
- Without slug: `/dashboard` (uses domain detection)
- With slug: `/{tenant}/dashboard` (explicit tenant routing)

Use `tenantUrl('route')` helper in tests for tenant-aware routing.

## Core Routes (Module-Gated)

| Feature | Route | Auth Required | Page Object |
|---------|-------|---------------|-------------|
| **Dashboard** | `/dashboard` | ✅ | `DashboardPage` |
| **Feed** | `/feed` | ✅ | `FeedPage` |

## Listings (Module-Gated)

| Route | Auth Required | Page Object |
|-------|---------------|-------------|
| `/listings` | ❌ (public) | `ListingsPage` |
| `/listings/create` | ✅ | `CreateListingPage` |
| `/listings/edit/:id` | ✅ | `CreateListingPage` |
| `/listings/:id` | ❌ (public) | `ListingDetailPage` |

## Messages (Module-Gated)

| Route | Auth Required | Page Object |
|-------|---------------|-------------|
| `/messages` | ✅ | `MessagesPage` |
| `/messages/new/:userId` | ✅ | `ConversationPage` |
| `/messages/:id` | ✅ | `ConversationPage` |

## Wallet (Module-Gated)

| Route | Auth Required | Page Object |
|-------|---------------|-------------|
| `/wallet` | ✅ | `WalletPage` |

**Note**: Transfer is handled via modal in WalletPage (no separate route)

## Events (Feature-Gated: `events`)

| Route | Auth Required | Page Object |
|-------|---------------|-------------|
| `/events` | ✅ | `EventsPage` |
| `/events/create` | ✅ | `CreateEventPage` |
| `/events/edit/:id` | ✅ | `CreateEventPage` |
| `/events/:id` | ✅ | `EventDetailPage` |

## Groups (Feature-Gated: `groups`)

| Route | Auth Required | Page Object |
|-------|---------------|-------------|
| `/groups` | ✅ | `GroupsPage` |
| `/groups/create` | ✅ | `CreateGroupPage` |
| `/groups/edit/:id` | ✅ | `CreateGroupPage` |
| `/groups/:id` | ✅ | `GroupDetailPage` |

## Profile & Settings

| Route | Auth Required | Page Object |
|-------|---------------|-------------|
| `/profile` | ✅ | `ProfilePage` (own profile) |
| `/profile/:id` | ✅ | `ProfilePage` (other user) |
| `/settings` | ✅ | `SettingsPage` |

## Other Protected Routes

| Feature | Route | Feature Gate | Page Object |
|---------|-------|--------------|-------------|
| **Members** | `/members` | `connections` | `MembersPage` |
| **Search** | `/search` | `search` | `SearchPage` |
| **Notifications** | `/notifications` | module: `notifications` | `NotificationsPage` |
| **Onboarding** | `/onboarding` | — (no gate) | `OnboardingPage` |
| **Leaderboard** | `/leaderboard` | `gamification` | `LeaderboardPage` |
| **Achievements** | `/achievements` | `gamification` | `AchievementsPage` |
| **Goals** | `/goals` | `goals` | `GoalsPage` |
| **Volunteering** | `/volunteering` | `volunteering` | `VolunteeringPage` |
| **Organisations** | `/organisations` | `volunteering` | `OrganisationsPage` |
| **Organisations Detail** | `/organisations/:id` | `volunteering` | `OrganisationDetailPage` |
| **Resources** | `/resources` | `resources` | `ResourcesPage` |
| **Exchanges** | `/exchanges` | `exchange_workflow` | `ExchangesPage` |
| **Exchange Detail** | `/exchanges/:id` | `exchange_workflow` | `ExchangeDetailPage` |
| **Request Exchange** | `/listings/:id/request-exchange` | `exchange_workflow` | `RequestExchangePage` |
| **Group Exchanges** | `/group-exchanges` | `group_exchanges` | `GroupExchangesPage` |
| **Create Group Exchange** | `/group-exchanges/create` | `group_exchanges` | `CreateGroupExchangePage` |
| **Group Exchange Detail** | `/group-exchanges/:id` | `group_exchanges` | `GroupExchangeDetailPage` |

## Public Routes

| Route | Page Object |
|-------|-------------|
| `/` | `HomePage` |
| `/about` | `AboutPage` |
| `/faq` | `FaqPage` |
| `/contact` | `ContactPage` |
| `/help` | `HelpCenterPage` |
| `/terms` | `TermsPage` |
| `/terms/versions` | `LegalVersionHistoryPage` |
| `/privacy` | `PrivacyPage` |
| `/privacy/versions` | `LegalVersionHistoryPage` |
| `/accessibility` | `AccessibilityPage` |
| `/accessibility/versions` | `LegalVersionHistoryPage` |
| `/cookies` | `CookiesPage` |
| `/cookies/versions` | `LegalVersionHistoryPage` |
| `/legal` | `LegalHubPage` |
| `/blog` | `BlogPage` (feature: `blog`) |
| `/blog/:slug` | `BlogPostPage` (feature: `blog`) |
| `/listings` | `ListingsPage` (module: `listings`) |
| `/listings/:id` | `ListingDetailPage` (module: `listings`) |

## Auth Routes (No Navbar/Footer)

| Route | Page Object |
|-------|-------------|
| `/login` | `LoginPage` |
| `/register` | `RegisterPage` |
| `/password/forgot` | `ForgotPasswordPage` |
| `/password/reset` | `ResetPasswordPage` |

## Admin Routes

| Route | Description |
|-------|-------------|
| `/admin/*` | React admin panel (primary) |
| `/admin-legacy/*` | Legacy PHP admin (deprecated) |

See [AdminApp.tsx](../../react-frontend/src/admin/AdminApp.tsx) for full admin route structure.

## Federation Routes (Feature-Gated: `federation`)

| Route | Page Object |
|-------|-------------|
| `/federation` | `FederationHubPage` |
| `/federation/partners` | `FederationPartnersPage` |
| `/federation/members` | `FederationMembersPage` |
| `/federation/members/:id` | `FederationMemberProfilePage` |
| `/federation/messages` | `FederationMessagesPage` |
| `/federation/listings` | `FederationListingsPage` |
| `/federation/events` | `FederationEventsPage` |
| `/federation/settings` | `FederationSettingsPage` |
| `/federation/onboarding` | `FederationOnboardingPage` |

## About Sub-Pages

| Route | Page Object |
|-------|-------------|
| `/timebanking-guide` | `TimebankingGuidePage` |
| `/partner` | `PartnerPage` |
| `/social-prescribing` | `SocialPrescribingPage` |
| `/impact-summary` | `ImpactSummaryPage` |
| `/impact-report` | `ImpactReportPage` |
| `/strategic-plan` | `StrategicPlanPage` |

## Error Pages

| Route | Page Object |
|-------|-------------|
| `/404` | `NotFoundPage` |
| (various) | `ComingSoonPage` (fallback for disabled features) |

## Legacy Routes (Removed)

These routes existed in the legacy PHP frontend but **no longer exist** in React:

| Legacy Route | React Equivalent |
|--------------|------------------|
| `/compose` | `/feed` (New Post button) |
| `/compose?type=listing` | `/listings/create` |
| `/compose?type=event` | `/events/create` |
| `/dashboard/listings` | `/dashboard` (single page, no sub-routes) |
| `/dashboard/hubs` | `/dashboard` (single page, no sub-routes) |
| `/dashboard/wallet` | `/dashboard` (single page, no sub-routes) |
| `/dashboard/events` | `/dashboard` (single page, no sub-routes) |
| `/dashboard/notifications` | `/dashboard` (single page, no sub-routes) |
| `/wallet/transfer` | `/wallet` (modal on same page) |

## Testing Notes

1. **Always use `tenantUrl()` helper** for navigation in tests
2. **Feature-gated routes** may redirect or show ComingSoonPage if feature is disabled
3. **Module-gated routes** will redirect if module is disabled
4. **Protected routes** redirect to `/login` if not authenticated
5. **Dashboard is single page** — no sub-routes like legacy PHP
6. **Wallet transfer is modal** — no separate route
7. **Create vs Edit** — same component, different routes (e.g., `/events/create` vs `/events/edit/:id`)

## Page Object Mapping

| Page Object | Routes Covered |
|-------------|----------------|
| `DashboardPage` | `/dashboard` |
| `FeedPage` | `/feed` |
| `ListingsPage` | `/listings`, `/listings/:id` |
| `CreateListingPage` | `/listings/create`, `/listings/edit/:id` |
| `MessagesPage` | `/messages` |
| `ConversationPage` | `/messages/:id`, `/messages/new/:userId` |
| `WalletPage` | `/wallet` |
| `EventsPage` | `/events` |
| `CreateEventPage` | `/events/create`, `/events/edit/:id` |
| `EventDetailPage` | `/events/:id` |
| `GroupsPage` | `/groups` |
| `CreateGroupPage` | `/groups/create`, `/groups/edit/:id` |
| `GroupDetailPage` | `/groups/:id` |
| `ProfilePage` | `/profile`, `/profile/:id` |
| `SettingsPage` | `/settings` |
| `MembersPage` | `/members` |
| `SearchPage` | `/search` |
| `NotificationsPage` | `/notifications` |
