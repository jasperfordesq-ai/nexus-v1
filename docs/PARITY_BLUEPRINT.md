# React Frontend Parity Blueprint

A practical, day-by-day guide to rebuilding the Project NEXUS PHP frontend in React while maintaining URL parity and user experience.

**Source of Truth**: Modern theme (`views/modern/`)
**Target**: React frontend (`react-frontend/`)
**Approach**: Reference PHP for structure/behavior, NOT copy CSS/theme code

---

## 1. Route/Page Inventory (Authoritative)

### 1.1 Public Pages (No Auth Required)

| URL Path | Page Name | Primary Data | Actions | API Endpoints | Source File |
|----------|-----------|--------------|---------|---------------|-------------|
| `/` | Home | Feed posts, tenant branding | View feed, navigate | `GET /api/v2/tenant/bootstrap`, `GET /api/v2/feed` | `views/modern/home.php` |
| `/login` | Login | None | Submit credentials, social auth | `POST /api/auth/login`, `POST /api/totp/verify` | `views/modern/auth/login.php` |
| `/register` | Register | None | Create account | `POST /api/v2/auth/register` | `views/modern/auth/register.php` |
| `/password/forgot` | Forgot Password | None | Request reset link | `POST /api/auth/forgot-password` | `views/modern/auth/forgot_password.php` |
| `/password/reset` | Reset Password | None | Set new password | `POST /api/auth/reset-password` | `views/modern/auth/reset_password.php` |
| `/about` | About | Tenant info | Read | Bootstrap `seo` data | `views/modern/pages/about.php` |
| `/contact` | Contact | Tenant contact | Submit inquiry | Contact form (custom) | `views/modern/pages/contact.php` |
| `/how-it-works` | How It Works | Static content | Read | None | `views/modern/pages/how-it-works.php` |
| `/terms` | Terms of Service | Legal doc | Read | None | `views/modern/pages/terms.php` |
| `/privacy` | Privacy Policy | Legal doc | Read | None | `views/modern/pages/privacy.php` |
| `/news` | Blog/News | Blog posts | Read, filter | **API Gap** | `views/modern/blog/index.php` |
| `/news/{slug}` | Blog Post | Single post | Read | **API Gap** | `views/modern/blog/show.php` |
| `/help` | Help Center | Help articles | Search, read | **API Gap** | `views/modern/help/index.php` |
| `/help/{slug}` | Help Article | Single article | Read | **API Gap** | `views/modern/help/show.php` |
| `/page/{slug}` | CMS Page | Dynamic page | Read | **API Gap** | Dynamic |

### 1.2 Core Authenticated Pages (MVP Priority)

| URL Path | Page Name | Auth | Roles | Primary Data | Actions | API Endpoints | Source File |
|----------|-----------|------|-------|--------------|---------|---------------|-------------|
| `/dashboard` | Dashboard | Yes | All | Stats, recent activity | Navigate to sections | Multiple (wallet, notifications, etc.) | `views/modern/dashboard/dashboard.php` |
| `/listings` | Listings | Yes | All | Listing cards | Search, filter, view | `GET /api/v2/listings` | `views/modern/listings/index.php` |
| `/listings/create` | Create Listing | Yes | All | Categories | Submit form | `POST /api/v2/listings` | `views/modern/listings/create.php` |
| `/listings/{id}` | Listing Detail | Yes | All | Single listing | Contact, message | `GET /api/v2/listings/{id}` | `views/modern/listings/show.php` |
| `/listings/edit/{id}` | Edit Listing | Yes | Owner | Single listing | Update | `PUT /api/v2/listings/{id}` | `views/modern/listings/edit.php` |
| `/messages` | Messages | Yes | All | Conversations | Read, reply | `GET /api/v2/messages` | `views/modern/messages/index.php` |
| `/messages/{id}` | Conversation | Yes | All | Thread messages | Send message | `GET /api/v2/messages/{id}`, `POST /api/v2/messages` | `views/modern/messages/thread.php` |
| `/wallet` | Wallet | Yes | All | Balance, transactions | Transfer | `GET /api/v2/wallet/balance`, `GET /api/v2/wallet/transactions` | `views/modern/wallet/index.php` |
| `/members` | Members | Yes | All | User cards | Search, view profile | `GET /api/v2/users` (needs endpoint) | `views/modern/members/index.php` |
| `/profile` | My Profile | Yes | All | User data | View own profile | `GET /api/v2/users/me` | `views/modern/profile/show.php` |
| `/profile/{id}` | User Profile | Yes | All | User data | View, connect, message | `GET /api/v2/users/{id}` | `views/modern/profile/show.php` |
| `/settings` | Settings | Yes | All | User preferences | Update profile, password, 2FA | `PUT /api/v2/users/me/*` | `views/modern/settings/index.php` |
| `/search` | Search | Yes | All | Mixed results | Search all content types | `GET /api/v2/search` | `views/modern/search/index.php` |
| `/notifications` | Notifications | Yes | All | Notification list | Mark read, delete | `GET /api/v2/notifications` | `views/modern/notifications/index.php` |

### 1.3 Feature-Gated Pages (Based on Tenant Features)

| URL Path | Feature Flag | Page Name | Auth | Primary Data | API Endpoints | Source File |
|----------|--------------|-----------|------|--------------|---------------|-------------|
| `/events` | `events` | Events | Yes | Event cards | `GET /api/v2/events` | `views/modern/events/index.php` |
| `/events/calendar` | `events` | Calendar | Yes | Calendar data | `GET /api/v2/events` | `views/modern/events/calendar.php` |
| `/events/create` | `events` | Create Event | Yes | Categories | `POST /api/v2/events` | `views/modern/events/create.php` |
| `/events/{id}` | `events` | Event Detail | Yes | Single event | `GET /api/v2/events/{id}` | `views/modern/events/show.php` |
| `/groups` | `groups` | Groups | Yes | Group cards | `GET /api/v2/groups` | `views/modern/groups/index.php` |
| `/groups/{id}` | `groups` | Group Detail | Yes | Single group | `GET /api/v2/groups/{id}` | `views/modern/groups/show.php` |
| `/groups/{id}/discussions` | `groups` | Discussions | Yes | Threads | `GET /api/v2/groups/{id}/discussions` | `views/modern/groups/discussions/` |
| `/connections` | `connections` | Connections | Yes | Friend list | `GET /api/v2/connections` | `views/modern/connections/index.php` |
| `/polls` | `polls` | Polls | Yes | Poll cards | `GET /api/v2/polls` | `views/modern/polls/index.php` |
| `/polls/{id}` | `polls` | Poll Detail | Yes | Single poll | `GET /api/v2/polls/{id}` | `views/modern/polls/show.php` |
| `/goals` | `goals` | Goals | Yes | Goal cards | `GET /api/v2/goals` | `views/modern/goals/index.php` |
| `/goals/{id}` | `goals` | Goal Detail | Yes | Single goal | `GET /api/v2/goals/{id}` | `views/modern/goals/show.php` |
| `/volunteering` | `volunteering` | Volunteering | Yes | Opportunities | `GET /api/v2/volunteering/opportunities` | `views/modern/volunteering/index.php` |
| `/volunteering/{id}` | `volunteering` | Opportunity | Yes | Single opp | `GET /api/v2/volunteering/opportunities/{id}` | `views/modern/volunteering/show.php` |
| `/achievements` | `gamification` | Achievements | Yes | Badges, XP | `GET /api/v2/gamification/profile` | `views/modern/achievements/index.php` |
| `/leaderboard` | `gamification` | Leaderboard | Yes | Rankings | `GET /api/v2/gamification/leaderboard` | `views/modern/leaderboard/index.php` |
| `/matches` | `search` | Smart Matching | Yes | Match suggestions | **API Gap**: `/api/v2/matches` | `views/modern/matches/index.php` |
| `/federation` | `federation` | Federation Hub | Yes | Partner tenants | `GET /api/v1/federation` | `views/modern/federation/hub.php` |
| `/blog` | `blog` | Blog | No | Blog posts | **API Gap** | `views/modern/blog/index.php` |
| `/resources` | `resources` | Resources | Yes | Resource library | **API Gap** | `views/modern/resources/index.php` |

### 1.4 Dynamic Route Patterns

| Pattern | Example | Resolution | Auth |
|---------|---------|------------|------|
| `/listings/{id}` | `/listings/42` | `GET /api/v2/listings/42` | Yes |
| `/events/{id}` | `/events/15` | `GET /api/v2/events/15` | Yes |
| `/groups/{id}` | `/groups/7` | `GET /api/v2/groups/7` | Yes |
| `/groups/{id}/discussions/{discussionId}` | `/groups/7/discussions/3` | Nested fetch | Yes |
| `/profile/{id}` | `/profile/123` | `GET /api/v2/users/123` | Yes |
| `/news/{slug}` | `/news/welcome-post` | **API Gap**: needs slug endpoint | No |
| `/page/{slug}` | `/page/about-us` | **API Gap**: CMS pages endpoint | No |

---

## 2. Layout Map (Structure, Not Styling)

### 2.1 Global Header Structure

```
┌─────────────────────────────────────────────────────────────────┐
│ HEADER                                                          │
├─────────────────────────────────────────────────────────────────┤
│ [Brand Logo/Name] ─── [Nav Links] ─── [Search] ─── [User Menu]  │
│                                                                 │
│ Brand: Tenant name, links to /                                  │
│                                                                 │
│ Nav Links (Desktop):                                            │
│   • Feed (/)                                                    │
│   • Listings (/listings)                                        │
│   • Volunteering (/volunteering) - if feature enabled           │
│   • Community Mega Menu:                                        │
│     - Groups (/groups)                                          │
│     - Events (/events) - if enabled                             │
│     - Members (/members)                                        │
│   • Explore Mega Menu:                                          │
│     - Polls, Goals, Achievements, Matches                       │
│   • About Mega Menu:                                            │
│     - News, Help, Custom pages                                  │
│                                                                 │
│ User Menu (Authenticated):                                      │
│   • Create Dropdown: Post, Listing, Event, Poll, Goal           │
│   • Messages icon with badge                                    │
│   • Notifications bell with drawer                              │
│   • Avatar dropdown: Profile, Dashboard, Wallet, Logout         │
│                                                                 │
│ User Menu (Guest):                                              │
│   • Login link                                                  │
│   • Join/Register link                                          │
└─────────────────────────────────────────────────────────────────┘
```

**Source**: `views/layouts/modern/partials/navbar.php`, `views/layouts/modern/partials/utility-bar.php`

### 2.2 Mobile Navigation Structure

```
┌─────────────────────────────────────────────────────────────────┐
│ MOBILE BOTTOM TAB BAR (Fixed)                                   │
├─────────────────────────────────────────────────────────────────┤
│  [Home]    [Listings]    [+Create]    [Messages]    [Menu]      │
│   icon       icon         icon          icon+badge    icon      │
└─────────────────────────────────────────────────────────────────┘

┌─────────────────────────────────────────────────────────────────┐
│ MOBILE FULL-SCREEN MENU (Slides in from right)                  │
├─────────────────────────────────────────────────────────────────┤
│ [Close X]                                                       │
│                                                                 │
│ User Section (if logged in):                                    │
│   Avatar | Name | Profile link                                  │
│   Quick Stats: Credits | Messages | Alerts                      │
│                                                                 │
│ Navigation Sections:                                            │
│   • Home, Listings, Groups, Members, Events, Volunteering       │
│   • Explore: Polls, Goals, Leaderboard, Achievements, Matches   │
│   • About: News, Custom pages (with icons)                      │
│   • Admin Tools (if admin role)                                 │
│   • Create: Post, Listing, Event, etc.                          │
│   • Account: Profile, Dashboard, Wallet, Settings               │
│   • Help & Support: Help Center, Contact, Accessibility         │
│                                                                 │
│ Footer: Theme toggle | Sign Out/Sign In                         │
└─────────────────────────────────────────────────────────────────┘
```

**Source**: `views/layouts/modern/partials/mobile-nav-v2.php`

### 2.3 Footer Structure

```
┌─────────────────────────────────────────────────────────────────┐
│ FOOTER (Desktop Only)                                           │
├─────────────────────────────────────────────────────────────────┤
│  Brand Column     │  Quick Links     │  Support      │  Pages   │
│  ─────────────    │  ────────────    │  ───────      │  ─────   │
│  Logo/Icon        │  Listings        │  Help Center  │  Custom  │
│  Tagline          │  Members         │  FAQ          │  pages   │
│  Social links     │  Groups          │  Contact      │  from    │
│                   │  Events          │  Mobile App   │  CMS     │
│                   │  Volunteering    │  Bug Reports  │          │
├─────────────────────────────────────────────────────────────────┤
│  © 2026 Tenant Name | Privacy | Terms | Cookies | Preferences   │
└─────────────────────────────────────────────────────────────────┘
```

**Source**: `views/layouts/modern/footer.php`

### 2.4 Page Layout Patterns

**Pattern A: Full-Width Content (Home, Feed)**
```
┌──────────────────────────────────────────────────────────────┐
│ Header                                                       │
├──────────────────────────────────────────────────────────────┤
│                     Main Content                             │
│                   (full width, centered)                     │
├──────────────────────────────────────────────────────────────┤
│ Footer                                                       │
└──────────────────────────────────────────────────────────────┘
```

**Pattern B: Content + Sidebar (Dashboard, Listings)**
```
┌──────────────────────────────────────────────────────────────┐
│ Header                                                       │
├──────────────────────────────────────────────────────────────┤
│  Sidebar (Desktop)  │        Main Content                    │
│  ─────────────────  │        ────────────                    │
│  - Nav items        │        - Page content                  │
│  - Quick stats      │        - Cards/lists                   │
│  - Filters          │        - Pagination                    │
├──────────────────────────────────────────────────────────────┤
│ Footer                                                       │
└──────────────────────────────────────────────────────────────┘
```

**Pattern C: Form Page (Create/Edit)**
```
┌──────────────────────────────────────────────────────────────┐
│ Header                                                       │
├──────────────────────────────────────────────────────────────┤
│                     Card Container                           │
│  ┌────────────────────────────────────────────────────────┐  │
│  │ Form Title                                             │  │
│  │ ─────────────────────────────────────────────────────  │  │
│  │ Form fields...                                         │  │
│  │ [Cancel] [Submit]                                      │  │
│  └────────────────────────────────────────────────────────┘  │
├──────────────────────────────────────────────────────────────┤
│ Footer                                                       │
└──────────────────────────────────────────────────────────────┘
```

### 2.5 Reusable Components (Implied by PHP Structure)

| Component | PHP Source | React Component | Description |
|-----------|-----------|-----------------|-------------|
| ListingCard | `views/modern/components/listing-card.php` | `<ListingCard />` | Card with image, title, type badge, credits |
| UserCard | `views/modern/components/user-card.php` | `<UserCard />` | Avatar, name, skills, connect button |
| EventCard | `views/modern/components/event-card.php` | `<EventCard />` | Date, title, location, RSVP button |
| GroupCard | `views/modern/components/group-card.php` | `<GroupCard />` | Image, name, member count, join button |
| MessageThread | `views/modern/messages/thread.php` | `<MessageThread />` | Messages list with input |
| NotificationItem | `views/modern/components/notification-item.php` | `<NotificationItem />` | Icon, text, timestamp, actions |
| FeedPost | `views/modern/partials/feed_item.php` | `<FeedPost />` | Author, content, reactions, comments |
| TransactionRow | `views/modern/wallet/` | `<TransactionRow />` | Amount, direction, user, date |
| PollCard | `views/modern/polls/` | `<PollCard />` | Question, options, vote counts |
| GoalCard | `views/modern/goals/` | `<GoalCard />` | Title, progress bar, deadline |
| BadgeDisplay | `views/modern/achievements/` | `<BadgeDisplay />` | Badge icon, name, earned date |
| StatCard | Dashboard components | `<StatCard />` | Icon, number, label |
| FilterBar | Multiple pages | `<FilterBar />` | Search, dropdowns, active filters |
| Pagination | Multiple pages | `<Pagination />` | Cursor or offset pagination |
| EmptyState | Multiple pages | `<EmptyState />` | Icon, message, CTA button |
| LoadingSkeleton | `views/modern/partials/skeleton-feed.php` | `<Skeleton />` | Animated placeholders |
| Modal | Various | `<Modal />` | Overlay with content |
| ConfirmDialog | Various | `<ConfirmDialog />` | Confirmation with yes/no |
| Toast | Footer scripts | `<Toast />` | Temporary notifications |

---

## 3. React Route Plan (Mirror Legacy)

### 3.1 Route Tree Structure

```tsx
// src/App.tsx route structure
<Routes>
  {/* Public routes */}
  <Route path="/" element={<Layout />}>
    <Route index element={<HomePage />} />
    <Route path="login" element={<LoginPage />} />
    <Route path="register" element={<RegisterPage />} />
    <Route path="password/forgot" element={<ForgotPasswordPage />} />
    <Route path="password/reset" element={<ResetPasswordPage />} />
    <Route path="about" element={<AboutPage />} />
    <Route path="contact" element={<ContactPage />} />
    <Route path="how-it-works" element={<HowItWorksPage />} />
    <Route path="terms" element={<TermsPage />} />
    <Route path="privacy" element={<PrivacyPage />} />
    <Route path="news" element={<BlogPage />} />
    <Route path="news/:slug" element={<BlogPostPage />} />
    <Route path="help" element={<HelpPage />} />
    <Route path="help/:slug" element={<HelpArticlePage />} />
    <Route path="page/:slug" element={<CmsPage />} />

    {/* Auth required routes */}
    <Route element={<ProtectedRoute />}>
      <Route path="dashboard" element={<DashboardPage />} />
      <Route path="listings" element={<ListingsPage />} />
      <Route path="listings/create" element={<CreateListingPage />} />
      <Route path="listings/:id" element={<ListingDetailPage />} />
      <Route path="listings/edit/:id" element={<EditListingPage />} />
      <Route path="messages" element={<MessagesPage />} />
      <Route path="messages/:id" element={<ConversationPage />} />
      <Route path="wallet" element={<WalletPage />} />
      <Route path="members" element={<MembersPage />} />
      <Route path="profile" element={<MyProfilePage />} />
      <Route path="profile/:id" element={<UserProfilePage />} />
      <Route path="settings" element={<SettingsPage />} />
      <Route path="settings/2fa" element={<TwoFactorSettingsPage />} />
      <Route path="search" element={<SearchPage />} />
      <Route path="notifications" element={<NotificationsPage />} />
      <Route path="compose" element={<ComposePage />} />

      {/* Feature-gated routes (check tenant.features) */}
      <Route path="events" element={<EventsPage />} />
      <Route path="events/calendar" element={<EventCalendarPage />} />
      <Route path="events/create" element={<CreateEventPage />} />
      <Route path="events/:id" element={<EventDetailPage />} />
      <Route path="groups" element={<GroupsPage />} />
      <Route path="groups/:id" element={<GroupDetailPage />} />
      <Route path="groups/:id/discussions/:discussionId" element={<DiscussionPage />} />
      <Route path="connections" element={<ConnectionsPage />} />
      <Route path="polls" element={<PollsPage />} />
      <Route path="polls/:id" element={<PollDetailPage />} />
      <Route path="goals" element={<GoalsPage />} />
      <Route path="goals/:id" element={<GoalDetailPage />} />
      <Route path="volunteering" element={<VolunteeringPage />} />
      <Route path="volunteering/:id" element={<OpportunityDetailPage />} />
      <Route path="achievements" element={<AchievementsPage />} />
      <Route path="leaderboard" element={<LeaderboardPage />} />
      <Route path="matches" element={<MatchesPage />} />
      <Route path="federation/*" element={<FederationRoutes />} />
    </Route>

    {/* 404 fallback */}
    <Route path="*" element={<NotFoundPage />} />
  </Route>
</Routes>
```

### 3.2 Route Priority Classification

| Priority | Routes | Notes |
|----------|--------|-------|
| **MVP (Must Ship)** | `/`, `/login`, `/register`, `/listings`, `/listings/:id`, `/messages`, `/wallet`, `/profile`, `/settings`, `/dashboard` | Core user journey |
| **Phase 2** | `/events/*`, `/groups/*`, `/members`, `/search`, `/notifications` | Community features |
| **Phase 3** | `/polls/*`, `/goals/*`, `/volunteering/*`, `/achievements`, `/leaderboard`, `/matches` | Engagement features |
| **Phase 4** | `/federation/*`, `/resources/*`, `/blog/*`, `/help/*` | Extended platform |
| **Keep on Legacy** | Admin routes (`/admin/*`), Super-admin routes, complex forms | Low priority, complex |

---

## 4. API Contract Map Per Page

### 4.1 MVP Pages - API Requirements

#### Home Page (`/`)
| API Call | Endpoint | Method | Auth | Notes |
|----------|----------|--------|------|-------|
| Tenant config | `/api/v2/tenant/bootstrap` | GET | No | Already implemented |
| Feed posts | `/api/v2/feed` | GET | Yes | Cursor pagination |
| Create post | `/api/v2/feed/posts` | POST | Yes | Text + optional image |
| Like post | `/api/v2/feed/like` | POST | Yes | Toggle like |

#### Login Page (`/login`)
| API Call | Endpoint | Method | Auth | Notes |
|----------|----------|--------|------|-------|
| Login | `/api/auth/login` | POST | No | Returns tokens or 2FA challenge |
| 2FA verify | `/api/totp/verify` | POST | No | Uses `two_factor_token` |
| WebAuthn challenge | `/api/webauthn/auth-challenge` | POST | No | Passkey login |
| WebAuthn verify | `/api/webauthn/auth-verify` | POST | No | Complete passkey auth |

#### Listings Page (`/listings`)
| API Call | Endpoint | Method | Auth | Notes |
|----------|----------|--------|------|-------|
| List listings | `/api/v2/listings` | GET | No | Filters: `type`, `category`, `status` |
| Get categories | **API Gap** | GET | No | Need `/api/v2/categories` |

#### Listing Detail (`/listings/:id`)
| API Call | Endpoint | Method | Auth | Notes |
|----------|----------|--------|------|-------|
| Get listing | `/api/v2/listings/:id` | GET | No | Includes user info |
| Start message | `/api/v2/messages` | POST | Yes | Contact owner |

#### Create/Edit Listing (`/listings/create`, `/listings/edit/:id`)
| API Call | Endpoint | Method | Auth | Notes |
|----------|----------|--------|------|-------|
| Get categories | **API Gap** | GET | No | Need `/api/v2/categories` |
| Create listing | `/api/v2/listings` | POST | Yes | Form submission |
| Update listing | `/api/v2/listings/:id` | PUT | Yes | Owner only |
| Upload image | `/api/v2/listings/:id/image` | POST | Yes | Multipart |
| Delete listing | `/api/v2/listings/:id` | DELETE | Yes | Owner only |

#### Messages Page (`/messages`)
| API Call | Endpoint | Method | Auth | Notes |
|----------|----------|--------|------|-------|
| List conversations | `/api/v2/messages` | GET | Yes | Cursor pagination |
| Unread count | `/api/v2/messages/unread-count` | GET | Yes | For badge |

#### Conversation (`/messages/:id`)
| API Call | Endpoint | Method | Auth | Notes |
|----------|----------|--------|------|-------|
| Get thread | `/api/v2/messages/:id` | GET | Yes | User ID as param |
| Send message | `/api/v2/messages` | POST | Yes | `receiver_id`, `content` |
| Mark read | `/api/v2/messages/:id/read` | PUT | Yes | |
| Typing indicator | `/api/v2/messages/typing` | POST | Yes | Real-time (optional) |

#### Wallet Page (`/wallet`)
| API Call | Endpoint | Method | Auth | Notes |
|----------|----------|--------|------|-------|
| Get balance | `/api/v2/wallet/balance` | GET | Yes | Balance + pending |
| Get transactions | `/api/v2/wallet/transactions` | GET | Yes | Cursor pagination |
| Transfer | `/api/v2/wallet/transfer` | POST | Yes | `to_user_id`, `amount` |
| User search | `/api/v2/wallet/user-search` | GET | Yes | Autocomplete |

#### Profile Page (`/profile`, `/profile/:id`)
| API Call | Endpoint | Method | Auth | Notes |
|----------|----------|--------|------|-------|
| Get my profile | `/api/v2/users/me` | GET | Yes | |
| Get user profile | `/api/v2/users/:id` | GET | Yes | |
| Get reviews | `/api/v2/reviews/user/:id` | GET | No | |
| Get review stats | `/api/v2/reviews/user/:id/stats` | GET | No | |
| Connection status | `/api/v2/connections/status/:id` | GET | Yes | |
| Send connection | `/api/v2/connections/request` | POST | Yes | |

#### Settings Page (`/settings`)
| API Call | Endpoint | Method | Auth | Notes |
|----------|----------|--------|------|-------|
| Get profile | `/api/v2/users/me` | GET | Yes | |
| Update profile | `/api/v2/users/me` | PUT | Yes | |
| Change password | `/api/v2/users/me/password` | PUT | Yes | |
| Upload avatar | `/api/v2/users/me/avatar` | PUT | Yes | Multipart |
| 2FA status | `/api/totp/status` | GET | Yes | |

#### Dashboard Page (`/dashboard`)
| API Call | Endpoint | Method | Auth | Notes |
|----------|----------|--------|------|-------|
| Get balance | `/api/v2/wallet/balance` | GET | Yes | |
| Get notifications | `/api/v2/notifications/counts` | GET | Yes | |
| Recent activity | **API Gap** | GET | Yes | Need `/api/v2/dashboard/activity` |
| My listings | `/api/v2/listings?user_id=me` | GET | Yes | |

### 4.2 API Gaps (Need Backend Implementation)

| Proposed Endpoint | Method | Purpose | Response Shape |
|-------------------|--------|---------|----------------|
| `/api/v2/categories` | GET | List categories for listings/events | `{ data: Category[] }` |
| `/api/v2/users` | GET | List/search members | `{ data: User[], meta: pagination }` |
| `/api/v2/dashboard/activity` | GET | Recent activity feed | `{ data: Activity[] }` |
| `/api/v2/blog/posts` | GET | List blog posts | `{ data: Post[], meta: pagination }` |
| `/api/v2/blog/posts/:slug` | GET | Get blog post by slug | `{ data: Post }` |
| `/api/v2/pages/:slug` | GET | Get CMS page by slug | `{ data: Page }` |
| `/api/v2/help/articles` | GET | List help articles | `{ data: Article[] }` |
| `/api/v2/help/articles/:slug` | GET | Get help article | `{ data: Article }` |
| `/api/v2/matches` | GET | Get match suggestions | `{ data: Match[] }` |
| `/api/v2/resources` | GET | List resources | `{ data: Resource[] }` |

---

## 5. Migration Order (Least Resistance)

### 5.1 Build Priority Matrix

| Phase | Pages | Effort | Dependencies | Can Use Legacy? |
|-------|-------|--------|--------------|-----------------|
| **Phase 0: Foundation** | Layout, Nav, Auth | Medium | Tenant bootstrap | N/A |
| **Phase 1: MVP Core** | Listings, Messages, Wallet, Profile, Dashboard | High | Auth working | Yes |
| **Phase 2: Community** | Events, Groups, Members, Search | Medium | Phase 1 | Yes |
| **Phase 3: Engagement** | Polls, Goals, Achievements, Leaderboard | Medium | Phase 2 | Yes |
| **Phase 4: Extended** | Volunteering, Federation, Resources, Blog | Low | Phase 3 | Yes |
| **Defer** | Admin panel, Super-admin, Complex reports | N/A | All | Yes |

### 5.2 Detailed Build Order

#### Phase 0: Foundation (Week 1)
```
Day 1-2:
  ✓ Project setup (done)
  ✓ Tenant bootstrap (done)
  ✓ Auth context + login (done)
  ✓ API client with refresh (done)

Day 3-4:
  □ Layout component (header, footer, mobile nav)
  □ Navigation with feature gates
  □ Protected route wrapper
  □ Error boundary

Day 5:
  □ 404 page
  □ Loading states
  □ Toast notifications
```

#### Phase 1: MVP Core (Week 2-3)
```
Week 2:
  □ Listings index page
  □ Listing detail page
  □ Create/edit listing pages
  □ Categories API integration

Week 3:
  □ Messages index (conversations)
  □ Conversation thread
  □ Wallet page
  □ Profile page (own + others)
  □ Settings page
  □ Dashboard page
```

#### Phase 2: Community (Week 4-5)
```
Week 4:
  □ Events index + detail
  □ Event calendar view
  □ Create/edit event
  □ RSVP functionality

Week 5:
  □ Groups index + detail
  □ Group discussions
  □ Members directory
  □ Search page
  □ Notifications page
```

#### Phase 3: Engagement (Week 6)
```
  □ Polls index + detail + voting
  □ Goals index + detail
  □ Achievements page
  □ Leaderboard
  □ Smart matches (if API ready)
```

#### Phase 4: Extended (Week 7+)
```
  □ Volunteering module
  □ Federation hub
  □ Resources library
  □ Blog/news
  □ Help center
```

### 5.3 Legacy Escape Hatch Strategy

These pages can stay on `/legacy/*` during early rollout:

| Page | Why Keep on Legacy | When to Migrate |
|------|-------------------|-----------------|
| `/admin/*` | Complex, low user-facing priority | Phase 5+ |
| `/super-admin/*` | Internal only | Phase 5+ |
| `/federation/dashboard` | Complex analytics | Phase 4 |
| `/volunteering/certificate` | Print-specific layout | Phase 4 |
| `/events/export` | Download functionality | Phase 4 |
| `/settings/2fa/setup` | WebAuthn setup flow | Phase 3 |
| `/compose` | Complex multi-form | Phase 2 |
| `/onboarding` | First-run wizard | Phase 2 |

---

## 6. Component Library Plan

### 6.1 Shared Components (Build First)

```
src/components/
├── layout/
│   ├── Layout.tsx           # Main layout wrapper
│   ├── Header.tsx           # Top navigation
│   ├── Footer.tsx           # Footer (desktop)
│   ├── MobileNav.tsx        # Bottom tab bar + drawer
│   ├── Sidebar.tsx          # Optional sidebar
│   └── PageContainer.tsx    # Content wrapper
├── navigation/
│   ├── NavLink.tsx          # Active-aware link
│   ├── MegaMenu.tsx         # Dropdown menus
│   ├── UserMenu.tsx         # Avatar dropdown
│   └── CreateMenu.tsx       # Create button dropdown
├── feedback/
│   ├── LoadingScreen.tsx    # Full-page loading
│   ├── ErrorScreen.tsx      # Error display
│   ├── EmptyState.tsx       # No data state
│   ├── Skeleton.tsx         # Loading placeholders
│   └── Toast.tsx            # Notifications
├── data-display/
│   ├── ListingCard.tsx      # Listing preview
│   ├── UserCard.tsx         # User preview
│   ├── EventCard.tsx        # Event preview
│   ├── GroupCard.tsx        # Group preview
│   ├── StatCard.tsx         # Dashboard stat
│   └── TransactionRow.tsx   # Wallet row
├── forms/
│   ├── FilterBar.tsx        # Search + filters
│   ├── Pagination.tsx       # Cursor/offset
│   ├── ImageUpload.tsx      # File upload
│   └── RichTextEditor.tsx   # Content editor
└── modals/
    ├── Modal.tsx            # Base modal
    ├── ConfirmDialog.tsx    # Yes/no dialog
    └── CreateModal.tsx      # Quick create overlay
```

### 6.2 Page Components Structure

```
src/pages/
├── public/
│   ├── HomePage.tsx
│   ├── LoginPage.tsx
│   ├── RegisterPage.tsx
│   ├── ForgotPasswordPage.tsx
│   ├── AboutPage.tsx
│   └── ...
├── listings/
│   ├── ListingsPage.tsx
│   ├── ListingDetailPage.tsx
│   ├── CreateListingPage.tsx
│   └── EditListingPage.tsx
├── messages/
│   ├── MessagesPage.tsx
│   └── ConversationPage.tsx
├── wallet/
│   └── WalletPage.tsx
├── profile/
│   ├── MyProfilePage.tsx
│   └── UserProfilePage.tsx
├── settings/
│   ├── SettingsPage.tsx
│   └── TwoFactorSettingsPage.tsx
├── dashboard/
│   └── DashboardPage.tsx
├── events/
│   ├── EventsPage.tsx
│   ├── EventDetailPage.tsx
│   ├── CreateEventPage.tsx
│   └── EventCalendarPage.tsx
├── groups/
│   ├── GroupsPage.tsx
│   ├── GroupDetailPage.tsx
│   └── DiscussionPage.tsx
└── ...
```

---

## 7. Data Flow Patterns

### 7.1 Standard Page Pattern

```tsx
// Example: ListingsPage.tsx
import { useState, useEffect } from 'react';
import { useSearchParams } from 'react-router-dom';
import { apiGet } from '../api';
import { ListingCard, FilterBar, Pagination, EmptyState, Skeleton } from '../components';
import type { Listing, PaginationMeta } from '../api/types';

export function ListingsPage() {
  const [searchParams, setSearchParams] = useSearchParams();
  const [listings, setListings] = useState<Listing[]>([]);
  const [meta, setMeta] = useState<PaginationMeta | null>(null);
  const [loading, setLoading] = useState(true);
  const [error, setError] = useState<string | null>(null);

  // Extract filters from URL
  const type = searchParams.get('type') || undefined;
  const category = searchParams.get('category') || undefined;
  const cursor = searchParams.get('cursor') || undefined;

  useEffect(() => {
    async function fetchListings() {
      setLoading(true);
      try {
        const params = new URLSearchParams();
        if (type) params.set('type', type);
        if (category) params.set('category', category);
        if (cursor) params.set('cursor', cursor);

        const response = await apiGet<{ data: Listing[]; meta: PaginationMeta }>(
          `/api/v2/listings?${params}`
        );
        setListings(response.data);
        setMeta(response.meta);
      } catch (err) {
        setError(err instanceof Error ? err.message : 'Failed to load');
      } finally {
        setLoading(false);
      }
    }
    fetchListings();
  }, [type, category, cursor]);

  const handleFilterChange = (filters: Record<string, string>) => {
    setSearchParams(filters);
  };

  if (loading) return <Skeleton variant="grid" count={6} />;
  if (error) return <EmptyState title="Error" message={error} />;
  if (listings.length === 0) return <EmptyState title="No listings" />;

  return (
    <div>
      <FilterBar
        filters={{ type, category }}
        onChange={handleFilterChange}
      />
      <div className="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-3 gap-4">
        {listings.map(listing => (
          <ListingCard key={listing.id} listing={listing} />
        ))}
      </div>
      {meta && <Pagination meta={meta} />}
    </div>
  );
}
```

### 7.2 Feature Gate Pattern

```tsx
// src/components/FeatureGate.tsx
import { useTenant } from '../tenant';
import type { TenantFeatures } from '../api/types';

interface FeatureGateProps {
  feature: keyof TenantFeatures;
  children: React.ReactNode;
  fallback?: React.ReactNode;
}

export function FeatureGate({ feature, children, fallback = null }: FeatureGateProps) {
  const tenant = useTenant();

  if (!tenant.features[feature]) {
    return <>{fallback}</>;
  }

  return <>{children}</>;
}

// Usage in navigation:
<FeatureGate feature="events">
  <NavLink to="/events">Events</NavLink>
</FeatureGate>

// Usage in routes:
<Route
  path="events"
  element={
    <FeatureGate feature="events" fallback={<Navigate to="/" />}>
      <EventsPage />
    </FeatureGate>
  }
/>
```

---

## 8. Testing Strategy

### 8.1 Manual Test Checklist Per Page

```markdown
## Page: Listings

### Happy Path
- [ ] Page loads without errors
- [ ] Listings display correctly
- [ ] Filters update URL and results
- [ ] Pagination works
- [ ] Click listing → detail page

### Edge Cases
- [ ] Empty state when no listings
- [ ] Loading skeleton appears
- [ ] Error state on API failure
- [ ] Very long titles truncate properly

### Mobile
- [ ] Responsive at 375px width
- [ ] Touch targets are large enough
- [ ] Filters accessible on mobile
```

### 8.2 Integration Points to Verify

| Integration | Test |
|-------------|------|
| Auth flow | Login → 2FA → Dashboard |
| Listing journey | Create → View → Message owner |
| Transaction flow | Transfer credits → See in wallet |
| Search | Search term → Results → Click result |
| Notifications | Receive notification → Click → Navigate |

---

## Appendix A: File Reference

### PHP Files → React Pages Mapping

| PHP View | React Page |
|----------|------------|
| `views/modern/home.php` | `src/pages/HomePage.tsx` |
| `views/modern/auth/login.php` | `src/pages/LoginPage.tsx` |
| `views/modern/listings/index.php` | `src/pages/listings/ListingsPage.tsx` |
| `views/modern/listings/show.php` | `src/pages/listings/ListingDetailPage.tsx` |
| `views/modern/listings/create.php` | `src/pages/listings/CreateListingPage.tsx` |
| `views/modern/messages/index.php` | `src/pages/messages/MessagesPage.tsx` |
| `views/modern/messages/thread.php` | `src/pages/messages/ConversationPage.tsx` |
| `views/modern/wallet/index.php` | `src/pages/wallet/WalletPage.tsx` |
| `views/modern/profile/show.php` | `src/pages/profile/ProfilePage.tsx` |
| `views/modern/settings/index.php` | `src/pages/settings/SettingsPage.tsx` |
| `views/modern/dashboard/dashboard.php` | `src/pages/dashboard/DashboardPage.tsx` |
| `views/modern/events/index.php` | `src/pages/events/EventsPage.tsx` |
| `views/modern/groups/index.php` | `src/pages/groups/GroupsPage.tsx` |
| `views/modern/members/index.php` | `src/pages/members/MembersPage.tsx` |

### Route Definitions Reference

| Location | File |
|----------|------|
| PHP routes | `httpdocs/routes.php` |
| React routes | `react-frontend/src/App.tsx` |
| API routes | `httpdocs/routes.php` (lines 400-1500) |

---

## Appendix B: Quick Start Commands

```bash
# Start React dev server
cd react-frontend && npm run dev

# Build for production
cd react-frontend && npm run build

# Test API endpoint
curl -H "X-Tenant-ID: 2" http://staging.timebank.local/api/v2/tenant/bootstrap

# Test with auth
TOKEN="your-token"
curl -H "Authorization: Bearer $TOKEN" -H "X-Tenant-ID: 2" \
  http://staging.timebank.local/api/v2/listings
```

---

**Last Updated**: 2026-02-03
**Author**: Claude (AI Assistant)
**Status**: Ready for implementation
