# Project NEXUS — Members Directory Engine: Complete Technical Report

**Generated:** 2026-03-29
**Version:** 1.5.0
**License:** AGPL-3.0-or-later

---

## Table of Contents

1. [Executive Summary](#1-executive-summary)
2. [System Architecture](#2-system-architecture)
3. [User Profiles](#3-user-profiles)
4. [Member Directory & Search](#4-member-directory--search)
5. [Connections System](#5-connections-system)
6. [Skill Endorsements](#6-skill-endorsements)
7. [Skills Taxonomy](#7-skills-taxonomy)
8. [Member Ranking](#8-member-ranking)
9. [Nexus Score (Reputation)](#9-nexus-score-reputation)
10. [Presence & Online Status](#10-presence--online-status)
11. [Member Availability](#11-member-availability)
12. [Verification Badges](#12-verification-badges)
13. [Onboarding](#13-onboarding)
14. [Sub-Accounts](#14-sub-accounts)
15. [Privacy & GDPR](#15-privacy--gdpr)
16. [Member Activity & Insights](#16-member-activity--insights)
17. [Member Reporting (Admin)](#17-member-reporting-admin)
18. [Vetting & Insurance](#18-vetting--insurance)
19. [Federation Profiles](#19-federation-profiles)
20. [Database Schema](#20-database-schema)
21. [API Reference](#21-api-reference)
22. [Frontend Experience](#22-frontend-experience)
23. [Admin Panel](#23-admin-panel)

---

## 1. Executive Summary

The Project NEXUS Members Directory Engine is the people layer of the platform — it manages user profiles, social connections, skill endorsements, reputation scoring, presence tracking, availability scheduling, verification, onboarding, privacy controls, and GDPR compliance. Every other module (timebanking, volunteering, jobs, events, gamification) builds on this foundation.

### What It Includes

| System | Purpose |
|--------|---------|
| **User Profiles** | Personal/organization profiles with bio, avatar, location, skills |
| **Member Directory** | Search, filter, sort, geospatial discovery |
| **Connections** | Follow/connect system with request/accept/reject workflow |
| **Skill Endorsements** | Peer endorsement of specific skills with comment |
| **Skills Taxonomy** | Structured skills with proficiency levels and categories |
| **Member Ranking** | CommunityRank algorithm (activity 35%, contribution 35%, reputation 30%) |
| **Nexus Score** | 1000-point composite reputation with 6 dimensions and 9 tiers |
| **Presence** | Real-time online/away/offline tracking via Redis + Pusher |
| **Availability** | Weekly recurring + one-off date scheduling with compatible time finding |
| **Verification Badges** | 8 trust badge types granted by admins |
| **Onboarding** | 5-step wizard (profile, interests, skills, safeguarding, complete) |
| **Sub-Accounts** | Parent/child account relationships with permissions |
| **Privacy & GDPR** | 3-level profile visibility, 6 GDPR rights, data export/erasure |
| **Activity & Insights** | Timeline, hours summary, monthly trends, partner stats |
| **Admin Reports** | Active members, retention cohorts, engagement metrics, top contributors |
| **Vetting & Insurance** | DBS/background checks and insurance certificate tracking |
| **Federation** | Cross-community profile sharing and federated connections |

### Key Metrics

| Metric | Value |
|--------|-------|
| Backend services | 12 dedicated member services |
| API endpoints | 80+ member/profile/connection routes |
| Database tables | 15+ member-related tables |
| User model fields | 80+ columns |
| Presence cache TTL | 300s (Redis), 60s DB write throttle |
| Ranking weights | Activity 35%, Contribution 35%, Reputation 30% |
| Privacy levels | 3 (public, members, connections) |
| GDPR rights | 6 (access, portability, deletion, rectification, restriction, objection) |
| Verification badge types | 8 |
| Frontend pages | 6 main pages + 6 settings tabs + admin module |

---

## 2. System Architecture

### Service Map

```
┌──────────────────────────────────────────────────────────────────────────┐
│                      MEMBERS DIRECTORY ENGINE                            │
│                                                                          │
│  ┌─────────────────────┐  ┌─────────────────────┐  ┌────────────────┐  │
│  │   PROFILES            │  │   SOCIAL              │  │   REPUTATION    │  │
│  │                       │  │                       │  │                │  │
│  │ UserService           │  │ ConnectionService     │  │ MemberRanking  │  │
│  │ UserInsightsService   │  │ EndorsementService    │  │ NexusScoreCache│  │
│  │ OnboardingService     │  │ PresenceService       │  │ VerifBadgeSvc  │  │
│  └───────────┬───────────┘  └───────────┬───────────┘  └───────┬────────┘  │
│              │                          │                       │           │
│  ┌───────────▼───────────┐  ┌───────────▼───────────┐  ┌───────▼────────┐  │
│  │   SCHEDULING           │  │   ADMINISTRATION       │  │   COMPLIANCE   │  │
│  │                        │  │                        │  │                │  │
│  │ MemberAvailabilitySvc  │  │ MemberActivitySvc     │  │ Privacy/GDPR   │  │
│  │ SubAccountService      │  │ MemberReportSvc       │  │ Vetting/Ins.   │  │
│  └────────────────────────┘  └────────────────────────┘  └────────────────┘  │
└──────────────────────────────────────────────────────────────────────────┘
```

### Key Files

| Component | File |
|-----------|------|
| User CRUD & Profiles | `app/Services/UserService.php` |
| User Insights | `app/Services/UserInsightsService.php` |
| Connections | `app/Services/ConnectionService.php` |
| Endorsements | `app/Services/EndorsementService.php` |
| Activity Dashboard | `app/Services/MemberActivityService.php` |
| Availability | `app/Services/MemberAvailabilityService.php` |
| Ranking | `app/Services/MemberRankingService.php` |
| Reports | `app/Services/MemberReportService.php` |
| Verification Badges | `app/Services/MemberVerificationBadgeService.php` |
| Onboarding | `app/Services/OnboardingService.php` |
| Presence | `app/Services/PresenceService.php` |
| Users Controller | `app/Http/Controllers/Api/UsersController.php` |
| Connections Controller | `app/Http/Controllers/Api/ConnectionsController.php` |
| Admin Users Controller | `app/Http/Controllers/Api/AdminUsersController.php` |
| User Model | `app/Models/User.php` |
| Connection Model | `app/Models/Connection.php` |

---

## 3. User Profiles

### Profile Types

| Type | Description |
|------|-------------|
| `individual` | Personal user profile |
| `organisation` | Organization profile (shows `organization_name`) |

### Profile Fields

**Identity:**
- `first_name`, `last_name`, `name` (computed), `username`, `email`
- `avatar_url`, `bio` (text, max 5000), `tagline` (varchar 255)

**Contact & Location:**
- `phone`, `location` (text), `latitude`/`longitude` (decimal 10,8/11,8)

**Status & Verification:**
- `status`: active, inactive, suspended, banned
- `is_verified`, `is_approved`, `email_verified_at`
- `vetting_status`: none, pending, verified, expired
- `insurance_status`: none, pending, verified, expired

**Preferences:**
- `preferred_language` (default 'en'), `preferred_theme` (light/dark/system)
- `privacy_profile` (public/members/connections), `privacy_search`, `privacy_contact`
- `notification_preferences` (JSON), `email_preferences` (JSON)

**Gamification:**
- `xp`, `level`, `points`, `login_streak`, `longest_streak`, `show_on_leaderboard`

**Roles:**
- `role`: member, admin, tenant_admin, super_admin
- `is_admin`, `is_super_admin`, `is_god`, `is_tenant_super_admin`

**Federation:**
- `federation_optin`, `federated_profile_visible`, `federation_notifications_enabled`

**Safeguarding:**
- `works_with_children`, `works_with_vulnerable_adults`, `requires_home_visits`
- `safeguarding_notes`, `safeguarding_reviewed_by`, `safeguarding_reviewed_at`

### Profile Operations

| Method | Description |
|--------|-------------|
| `getMe(userId)` | Full own profile with private fields, notifications, stats, badges, NexusScore |
| `getPublicProfile(userId, viewerId?)` | Public profile with privacy/onboarding checks |
| `update(id, data)` | Whitelisted field updates |
| `updateProfile(userId, data)` | Validates then updates; notifies on email change |
| `updateAvatar(userId, file)` | ImageUploader with 400x400 crop |
| `updatePassword(userId, current, new)` | Hash::check verification, min 8 chars |
| `deleteAccount(userId)` | Soft-delete: anonymizes all PII fields |
| `search(term, limit)` | Name/email/org search with privacy gating |
| `getNearby(lat, lon, filters)` | Haversine proximity search |

### Privacy Enforcement

| Setting | Effect |
|---------|--------|
| `privacy_profile = public` | Anyone can view profile |
| `privacy_profile = members` | Only logged-in members |
| `privacy_profile = connections` | Only accepted connections |
| `privacy_search = false` | Hidden from member directory search |
| `privacy_contact = false` | Contact buttons hidden |

---

## 4. Member Directory & Search

### Search & Filtering

```
UserService::search(term, limit=20) → { items, cursor, has_more }
```

- Searches: first_name, last_name, email, organization_name
- Cursor-based pagination (max 100)
- Respects `privacy_search` and `onboarding_completed` flags
- Excludes banned/suspended/deleted users
- Full-text index on `(first_name, last_name, bio, skills)`

### Geospatial Discovery

```
UserService::getNearby(lat, lon, filters) → { items, has_more }
```

- **Haversine formula** for distance calculation
- Default radius: 50km (configurable, max 100)
- Returns `distance` field per member
- Batch loads showcased badges (3 max per user) if gamification enabled
- Enriches with: xp, level, rating, total_hours_given/received, is_verified

### Sort Options

| Sort | Description |
|------|-------------|
| `name` | Alphabetical by name |
| `joined` | By registration date |
| `rating` | By average review rating |
| `hours_given` | By total hours contributed |

### Quick Filters (Frontend)

| Filter | Criteria |
|--------|----------|
| All Members | No additional filter |
| New Members | Joined in last 30 days |
| Active Members | Logged in within 7 days |

---

## 5. Connections System

### Connection Statuses

| Status | Description |
|--------|-------------|
| `pending` | Request sent, awaiting response |
| `accepted` | Both parties connected |
| (deleted) | Connection removed (hard delete) |

### Connection Flow

```
User A sends request → Status: pending → User B notified
  → User B accepts → Status: accepted → Both connected
  → User B declines → Connection deleted
  → User A cancels → Connection deleted

Either party disconnects → Connection deleted
```

### Key Methods

| Method | Description |
|--------|-------------|
| `request(requesterId, receiverId)` | Create pending connection (deadlock-safe with ordered locks) |
| `accept(connectionId, userId)` | Accept (validates receiver) |
| `destroy(connectionId, userId)` | Remove connection (either party) |
| `getAll(userId, filters)` | List connections with status filter and cursor pagination |
| `getStatus(userId, otherUserId)` | Returns: none, pending_sent, pending_received, connected |
| `getPendingCounts(userId)` | Returns: received, sent, total_friends |

### Validation Rules

- Cannot self-connect
- Cannot connect if either user is blocked
- Must be in same tenant
- Duplicate connections prevented (checks both directions)
- Uses `DB::transaction()` with ordered row locks to prevent deadlocks on concurrent requests

---

## 6. Skill Endorsements

### Endorsement Model

A user can endorse another user for a specific skill:

```
EndorsementService::endorse(endorserId, endorsedId, skillName, skillId?, comment?) → endorsementId
```

**Validation:**
- Skill name required (max 100 chars)
- No self-endorsement
- Endorsed user must exist
- No duplicate endorsement (same endorser + endorsed + skill)
- Comment truncated to 500 chars

### Endorsement Queries

| Method | Returns |
|--------|---------|
| `getEndorsements(userId)` | Grouped by skill: [{skill_name, count, endorsers: [{id, name, avatar, comment}]}] |
| `getEndorsementsForUser(userId)` | Raw aggregated: skill_name, count, endorsed_by_names/ids/avatars |
| `getSkillEndorsements(userId, skillName)` | Detailed per skill with endorser info |
| `hasEndorsed(endorserId, endorsedId, skillName)` | Boolean check |
| `getStats(userId)` | {endorsements_received, endorsements_given, skills_endorsed} |
| `getTopEndorsedMembers(limit)` | Tenant-wide leaderboard |

### Peer Endorsement → Verification Badge

At **3+ peer endorsements**, the system automatically grants the `peer_endorsed` verification badge via `MemberVerificationBadgeService`.

---

## 7. Skills Taxonomy

### Structured Skills (user_skills table)

| Field | Type | Description |
|-------|------|-------------|
| `skill_name` | varchar(100) | Skill name |
| `category_id` | FK | Skill category |
| `proficiency` | enum | `beginner`, `intermediate`, `advanced`, `expert` |
| `is_offering` | boolean | User offers this skill |
| `is_requesting` | boolean | User needs this skill |

### Skills API

| Method | Endpoint | Description |
|--------|----------|-------------|
| GET | `/v2/skills/categories` | All skill categories |
| GET | `/v2/skills/search` | Search skills by name |
| GET | `/v2/users/me/skills` | My skills |
| POST | `/v2/users/me/skills` | Add skill |
| PUT | `/v2/users/me/skills/{id}` | Update skill |
| DELETE | `/v2/users/me/skills/{id}` | Remove skill |
| GET | `/v2/users/{id}/skills` | User's skills |
| GET | `/v2/skills/members` | Members with specific skill |

### Legacy Skills

Users also have a comma-separated `skills` text field on the users table (legacy). The structured `user_skills` table is the primary system.

---

## 8. Member Ranking

### CommunityRank Algorithm

```
MemberRankingService::rankMembers(tenantId, limit=50) → ranked members[]
```

**Three dimensions with normalized scoring:**

| Dimension | Weight | Metrics |
|-----------|--------|---------|
| **Activity** | 35% | Transaction count (last 30 days) + post count |
| **Contribution** | 35% | Active listing count |
| **Reputation** | 30% | Review count received |

**Scoring process:**
1. Collect raw metrics per user
2. Normalize each metric (0-1 scale) relative to maximum in dataset
3. Apply weights: `score = (activity × 0.35) + (contribution × 0.35) + (reputation × 0.30)`
4. **Verification boost:** verified users get 1.1× multiplier
5. Cap final score at 1.0
6. Sort descending, return top N

**Configuration:** Weights and lookback period configurable per tenant via `tenants.configuration` JSON. Redis cache available.

---

## 9. Nexus Score (Reputation)

A **1000-point composite reputation score** calculated from 6 dimensions:

| Category | Max Points | What It Measures |
|----------|-----------|------------------|
| **Engagement** | 250 | Activity frequency, consistency |
| **Quality** | 200 | Review scores, completion rates |
| **Volunteer** | 200 | Volunteer hours logged |
| **Activity** | 150 | Listings, events, posts created |
| **Badges** | 100 | Badge count and rarity |
| **Impact** | 100 | Connections, diversity of engagement |
| **Total** | **1,000** | |

### 9 Tiers

| Tier | Min Score |
|------|-----------|
| Novice | 0 |
| Beginner | 200 |
| Developing | 300 |
| Intermediate | 400 |
| Proficient | 500 |
| Advanced | 600 |
| Expert | 700 |
| Elite | 800 |
| Legendary | 900 |

Percentile ranking (0-100) indicates standing relative to all tenant users. Score snapshots stored in `nexus_score_history` for trend tracking. Milestones tracked in `nexus_score_milestones`.

---

## 10. Presence & Online Status

### Real-Time Presence via Redis

**Thresholds:**
- Online: last activity ≤ 5 minutes ago
- Away: last activity ≤ 15 minutes ago
- Offline: last activity > 15 minutes ago
- DND: manually set, always preserved

**Redis keys:**
- `nexus:presence:{tenant_id}:{user_id}` — JSON payload (300s TTL)
- `nexus:presence:online:{tenant_id}` — SET of online user IDs
- `nexus:presence:throttle:{tenant_id}:{user_id}` — DB write throttle (60s)

### Key Methods

| Method | Description |
|--------|-------------|
| `heartbeat(userId)` | Record activity (Redis always, DB throttled to 1/min) |
| `getPresence(userId)` | Single user lookup (Redis → DB → offline fallback) |
| `getBulkPresence(userIds[])` | Batch lookup for multiple users |
| `setStatus(userId, status, customStatus?, emoji?)` | Manual status set (online/away/dnd/offline) |
| `setPrivacy(userId, hidePresence)` | Toggle visibility (returns offline if hidden) |
| `getOnlineCount(tenantId)` | Count online users |
| `cleanupStale()` | Cron: mark stale users offline |

### Custom Status

Users can set:
- `status`: online, away, dnd, offline
- `custom_status`: text (max 80 chars)
- `status_emoji`: emoji (max 10 chars)
- `hide_presence`: boolean (returns offline to all queries)

---

## 11. Member Availability

### Weekly Recurring Schedule

```
MemberAvailabilityService::setBulkAvailability(userId, {
    '0': [{ start_time: '09:00', end_time: '12:00' }],  // Sunday
    '1': [{ start_time: '09:00', end_time: '17:00' }],  // Monday
    ...
}) → bool
```

Days: 0=Sunday through 6=Saturday.

### One-Off Dates

```
MemberAvailabilityService::addSpecificDate(userId, {
    date: '2026-04-15',
    start_time: '10:00',
    end_time: '14:00',
    note: 'Available for event setup'
}) → slotId
```

### Compatible Time Finding

```
MemberAvailabilityService::findCompatibleTimes(userIdA, userIdB) → [
    { day_of_week: 1, day_name: 'Monday', start_time: '10:00', end_time: '12:00' },
    { day_of_week: 3, day_name: 'Wednesday', start_time: '14:00', end_time: '16:00' }
]
```

Compares overlapping time windows between two users.

### Find Available Members

```
MemberAvailabilityService::getAvailableMembers(dayOfWeek, time?, limit=50) → [
    { user_id, start_time, end_time, member_name, avatar_url }
]
```

---

## 12. Verification Badges

### 8 Badge Types

| Type | Name | Icon |
|------|------|------|
| `email_verified` | Email Verified | mail-check |
| `phone_verified` | Phone Verified | phone-check |
| `id_verified` | ID Verified | shield-check |
| `address_verified` | Address Verified | badge-check |
| `admin_verified` | Admin Verified | user-check |
| `background_check` | Background Check | shield-check |
| `organization_vouched` | Organization Vouched | building-2 |
| `peer_endorsed` | Peer Endorsed | users-round |

### Grant & Revoke

- **Grant:** Admin-only, with optional note and expiry date. Upsert (re-grants if revoked).
- **Revoke:** Soft-revoke via `revoked_at` timestamp.
- **Auto-grant:** `peer_endorsed` auto-granted at 3+ peer endorsements.
- **Batch lookup:** `getBatchUserBadges(userIds[])` for N+1 prevention.

---

## 13. Onboarding

### 5-Step Wizard

| Step | What It Does |
|------|-------------|
| 1. **Profile** | Upload avatar, write bio |
| 2. **Interests** | Select interest categories |
| 3. **Skills** | Choose skills offered and needed |
| 4. **Safeguarding** | Accept safeguarding preferences (triggers broker protections) |
| 5. **Complete** | Review and confirm |

### Key Features

- **Progress tracking:** Steps counted, percentage displayed
- **Auto-skip:** Profile step skipped if avatar and bio already filled
- **Auto-create listings:** Optionally creates draft/active listings from selected skills (configurable per tenant: disabled, suggestions_only, draft, pending_review, active; max 10 listings)
- **Safeguarding integration:** Selected preferences trigger `SafeguardingTriggerService` which activates broker protections

### Onboarding Methods

| Method | Description |
|--------|-------------|
| `getProgress(tenantId, userId)` | {steps, completed, total, percentage, is_complete} |
| `completeStep(tenantId, userId, step)` | Mark step done |
| `saveInterests(userId, categoryIds[])` | Save interest categories |
| `saveSkills(userId, offers[], needs[])` | Save skill offers/needs |
| `autoCreateListings(userId, offers[], needs[])` | Create listings from skills |
| `completeOnboarding(userId)` | Set onboarding_completed = true |

---

## 14. Sub-Accounts

Parent/child account relationships for families or organizations.

### Relationship Types

- `family` (default)
- `guardian`
- `organization`
- Custom types supported via string field

### Sub-Account Operations

| Method | Endpoint | Description |
|--------|----------|-------------|
| GET | `/v2/users/me/sub-accounts` | List child accounts |
| GET | `/v2/users/me/parent-accounts` | List parent accounts |
| POST | `/v2/users/me/sub-accounts` | Request relationship |
| PUT | `/v2/users/me/sub-accounts/{id}/approve` | Approve request |
| PUT | `/v2/users/me/sub-accounts/{id}/permissions` | Update permissions |
| DELETE | `/v2/users/me/sub-accounts/{id}` | Revoke relationship |
| GET | `/v2/users/me/sub-accounts/{childId}/activity` | View child activity |

### Permissions

Stored as JSON array in `account_relationships.permissions`. Status: pending → active → revoked.

---

## 15. Privacy & GDPR

### Profile Visibility

| Level | Who Can See |
|-------|-------------|
| `public` | Anyone (including non-logged-in) |
| `members` | Only authenticated members of the tenant |
| `connections` | Only accepted connections |

### 6 GDPR Rights

| Right | Endpoint | Description |
|-------|----------|-------------|
| **Access** | `POST /v2/gdpr/export` | Download all personal data (ZIP) |
| **Portability** | `POST /v2/gdpr/portability` | Machine-readable format (JSON/CSV) |
| **Deletion** | `POST /v2/gdpr/deletion` | Account deletion (30-day grace period) |
| **Rectification** | `POST /v2/gdpr/rectification` | Request data correction |
| **Restriction** | `POST /v2/gdpr/restrict` | Pause data processing |
| **Objection** | `POST /v2/gdpr/object` | Opt out of specific processing |

### Account Deletion

```
UserService::deleteAccount(userId) → bool
```

Soft-delete with PII anonymization:
- Email → `deleted_N@anonymized.invalid`
- first_name, last_name, bio, tagline, phone, avatar, location, coordinates → cleared
- Status → `deleted`

---

## 16. Member Activity & Insights

### Activity Dashboard

```
MemberActivityService::getDashboardData(userId) → {
    timeline: [...],
    hours_summary: { hours_given, hours_received, net_balance },
    skills_breakdown: { skills[], offering_count, requesting_count },
    connection_stats: { total_connections, pending_requests, groups_joined },
    engagement: { posts_count, comments_count, likes_given, likes_received },
    monthly_hours: [{ month, label, given, received }]
}
```

### User Insights

```
UserInsightsService::getInsights(userId) → {
    summary: { total_earned, total_spent, balance, this_month stats },
    monthly_trends: [{ month, earned, spent, received_count, sent_count }],
    partner_stats: { unique_people_paid, unique_people_received_from, mutual_connections }
}
```

### Timeline

Aggregates: posts, transactions (gave/received hours), comments, connections, event RSVPs. Each with activity_type and human-readable description.

---

## 17. Member Reporting (Admin)

### Admin Report Methods

| Method | Returns |
|--------|---------|
| `getActiveMembers(tenantId, days, limit, offset)` | Members with login, transaction count, hours |
| `getNewRegistrations(tenantId, period, months)` | Registration trends (daily/weekly/monthly) |
| `getMemberRetention(tenantId, months)` | Cohort analysis: joined vs retained |
| `getEngagementMetrics(tenantId, days)` | Active users, login rate, trading rate, posts, comments, RSVPs, connections |
| `getTopContributors(tenantId, days, limit)` | Top users by total hours |
| `getLeastActiveMembers(tenantId, days, limit, offset)` | Users NOT logged in for N days |

### Retention Cohort Analysis

For each month, tracks:
- How many joined that month
- How many are still active (logged in within 30 days, status = active)
- Retention rate percentage
- Overall retention across all cohorts

---

## 18. Vetting & Insurance

### Vetting Records (DBS/Background Checks)

| Field | Description |
|-------|-------------|
| `vetting_type` | dbs_basic, dbs_standard, dbs_enhanced, garda_vetting, access_ni, pvg_scotland, international, other |
| `status` | pending, submitted, verified, expired, rejected, revoked |
| `reference_number` | Official reference |
| `issue_date` / `expiry_date` | Validity period |
| `document_url` | Uploaded document |
| `works_with_children` | Flag |
| `works_with_vulnerable_adults` | Flag |
| `requires_enhanced_check` | Flag |

### Insurance Certificates

| Field | Description |
|-------|-------------|
| `insurance_type` | public_liability, professional_indemnity, employers_liability, product_liability, personal_accident, other |
| `provider_name` | Insurance provider |
| `policy_number` | Policy reference |
| `coverage_amount` | decimal(12,2) |
| `start_date` / `expiry_date` | Coverage period |
| `certificate_file_path` | Uploaded certificate |
| `status` | pending, submitted, verified, expired, rejected, revoked |

Both are tracked in the exchange workflow — `ExchangeWorkflowService::checkComplianceRequirements()` verifies vetting and insurance before exchange acceptance.

---

## 19. Federation Profiles

### Federation User Settings

| Field | Description |
|-------|-------------|
| `federation_optin` | Opted into federation |
| `profile_visible_federated` | Profile visible to federated communities |
| `messaging_enabled_federated` | Can receive federated messages |
| `transactions_enabled_federated` | Can do cross-community exchanges |
| `appear_in_federated_search` | Searchable in federation |
| `show_skills_federated` | Skills visible cross-community |
| `show_location_federated` | Location visible cross-community |
| `show_reviews_federated` | Reviews visible cross-community |
| `service_reach` | local_only, remote_ok, travel_ok |
| `travel_radius_km` | Max travel distance |

### Federation Connections

Cross-community connections stored in `federation_connections`:
- `requester_user_id` + `requester_tenant_id`
- `receiver_user_id` + `receiver_tenant_id`
- `status`: pending, accepted, rejected

---

## 20. Database Schema

### Core Tables

#### `users` (80+ columns)

Key field groups:
- **Identity:** id, first_name, last_name, name, username, email, date_of_birth
- **Profile:** avatar_url, bio, tagline, location, latitude, longitude, phone, profile_type, organization_name
- **Status:** status (active/inactive/suspended/banned), is_verified, is_approved, email_verified_at
- **Roles:** role, is_admin, is_super_admin, is_god, is_tenant_super_admin
- **Security:** password_hash, totp_enabled, totp_secret, totp_backup_codes, reset_token
- **Gamification:** xp, level, points, login_streak, longest_streak, show_on_leaderboard
- **Privacy:** privacy_profile, privacy_search, privacy_contact
- **Preferences:** preferred_language, preferred_theme, notification_preferences (JSON)
- **Federation:** federation_optin, federated_profile_visible
- **Safeguarding:** works_with_children, works_with_vulnerable_adults, vetting_status
- **GDPR:** anonymized_at, gdpr_export_requested_at, gdpr_deletion_requested_at, deleted_at
- **Tracking:** created_at, last_login_at, last_active_at, balance
- **Indexes:** FULLTEXT(first_name, last_name, bio, skills), UNIQUE(email, tenant_id), coordinates, status, xp, level

#### `connections`

| Column | Type | Description |
|--------|------|-------------|
| `requester_id` | FK → users | Request sender |
| `receiver_id` | FK → users | Request receiver |
| `status` | varchar(20) | pending / accepted |
| `tenant_id` | FK | Tenant scope |

#### `skill_endorsements`

| Column | Type | Description |
|--------|------|-------------|
| `endorser_id` | FK → users | Who endorsed |
| `endorsed_id` | FK → users | Who was endorsed |
| `skill_name` | varchar(100) | Skill name |
| `skill_id` | FK | Optional structured skill reference |
| `comment` | varchar(500) | Endorsement comment |

UNIQUE: `(endorser_id, endorsed_id, skill_name, tenant_id)`

#### `user_skills`

| Column | Type | Description |
|--------|------|-------------|
| `skill_name` | varchar(100) | Skill name |
| `category_id` | FK | Skill category |
| `proficiency` | enum | beginner, intermediate, advanced, expert |
| `is_offering` | boolean | Offering this skill |
| `is_requesting` | boolean | Requesting this skill |

#### `user_interests`

| Column | Type | Description |
|--------|------|-------------|
| `category_id` | FK | Interest category |
| `interest_type` | enum | interest, skill_offer, skill_need |

UNIQUE: `(tenant_id, user_id, category_id, interest_type)`

#### `member_availability`

| Column | Type | Description |
|--------|------|-------------|
| `day_of_week` | tinyint | 0=Sunday...6=Saturday |
| `start_time` / `end_time` | time | Time window |
| `is_recurring` | boolean | Weekly recurring vs one-off |
| `specific_date` | date | For one-off dates |
| `note` | varchar(255) | Optional note |

#### `account_relationships`

| Column | Type | Description |
|--------|------|-------------|
| `parent_user_id` / `child_user_id` | FK → users | Relationship |
| `relationship_type` | varchar(50) | family, guardian, organization |
| `permissions` | JSON | Granted permissions |
| `status` | enum | active, pending, revoked |

#### Other Tables

- `nexus_score_cache` — Composite reputation score (6 dimensions)
- `nexus_score_history` — Score snapshots by date
- `nexus_score_milestones` — Score/tier milestones achieved
- `user_badges` — Earned gamification badges
- `member_verification_badges` — Admin-granted trust badges
- `vetting_records` — DBS/background check records
- `insurance_certificates` — Insurance certificate records
- `notification_settings` — Per-user notification frequency
- `user_notification_preferences` — Notification preferences
- `federation_user_settings` — Federation opt-in settings
- `federation_connections` — Cross-tenant connections
- `reviews` — User reviews with ratings

---

## 21. API Reference

### Profile Endpoints

| Method | Endpoint | Description | Rate Limit |
|--------|----------|-------------|------------|
| GET | `/v2/users/me` | Own full profile | — |
| PUT | `/v2/users/me` | Update profile | 10/min |
| DELETE | `/v2/users/me` | Delete account (requires password) | — |
| POST | `/v2/users/me/avatar` | Upload avatar | — |
| POST | `/v2/users/me/password` | Change password | 3/min |
| GET | `/v2/users/{id}` | Public profile | — |
| GET | `/v2/users` | Member directory with search | 60/min |
| GET | `/v2/members/nearby` | Geospatial search | — |
| GET | `/v2/me/stats` | Profile stats | — |
| GET | `/v2/users/me/listings` | My listings | — |
| GET | `/v2/users/{id}/listings` | User's listings | — |

### Settings Endpoints

| Method | Endpoint | Description |
|--------|----------|-------------|
| GET/PUT | `/v2/users/me/preferences` | Privacy and notification settings |
| PUT | `/v2/users/me/theme` | Theme preference |
| PUT | `/v2/users/me/theme-preferences` | Detailed theme (accent, font, density) |
| PUT | `/v2/users/me/language` | Language preference |
| GET/PUT | `/v2/users/me/notifications` | Notification preferences |
| GET/PUT | `/v2/users/me/consent` | GDPR consents |
| POST | `/v2/users/me/gdpr-request` | GDPR rights request |
| GET | `/v2/users/me/sessions` | Active sessions |

### Connection Endpoints

| Method | Endpoint | Description | Rate Limit |
|--------|----------|-------------|------------|
| GET | `/v2/connections` | List connections (status filter, cursor) | — |
| GET | `/v2/connections/pending` | Pending counts | — |
| GET | `/v2/connections/status/{userId}` | Status with user | — |
| POST | `/v2/connections` | Send request | 20/min |
| PUT | `/v2/connections/{id}/accept` | Accept request | — |
| DELETE | `/v2/connections/{id}` | Remove/cancel | — |

### Endorsement Endpoints

| Method | Endpoint | Description | Rate Limit |
|--------|----------|-------------|------------|
| POST | `/v2/members/{id}/endorse` | Endorse skill | 20/min |
| DELETE | `/v2/members/{id}/endorse` | Remove endorsement | — |
| GET | `/v2/members/{id}/endorsements` | Get endorsements | — |
| GET | `/v2/members/top-endorsed` | Top endorsed members | — |
| POST | `/v2/members/{id}/peer-endorse` | Peer endorse (auto-badge at 3+) | — |

### Activity Endpoints

| Method | Endpoint | Description |
|--------|----------|-------------|
| GET | `/v2/users/me/activity/dashboard` | Activity dashboard |
| GET | `/v2/users/me/activity/timeline` | Recent timeline |
| GET | `/v2/users/me/activity/hours` | Hours summary |
| GET | `/v2/users/me/activity/monthly` | Monthly hours chart |
| GET | `/v2/users/{id}/activity/dashboard` | Public dashboard |

### Availability Endpoints

| Method | Endpoint | Description | Rate Limit |
|--------|----------|-------------|------------|
| GET | `/v2/users/me/availability` | My schedule | — |
| PUT | `/v2/users/me/availability` | Set weekly schedule | 10/min |
| PUT | `/v2/users/me/availability/{day}` | Set single day | 10/min |
| POST | `/v2/users/me/availability/date` | Add one-off date | 10/min |
| DELETE | `/v2/users/me/availability/{id}` | Delete slot | — |
| GET | `/v2/users/{id}/availability` | View user's schedule | 30/min |
| GET | `/v2/members/availability/compatible` | Find compatible times | 20/min |
| GET | `/v2/members/availability/available` | Available members at time | 20/min |

### Presence Endpoints

| Method | Endpoint | Description | Rate Limit |
|--------|----------|-------------|------------|
| POST | `/v2/presence/heartbeat` | Send heartbeat | 6/min |
| GET | `/v2/presence/users` | Bulk presence lookup (max 100 IDs) | — |
| PUT | `/v2/presence/status` | Set custom status | — |
| PUT | `/v2/presence/privacy` | Toggle visibility | — |
| GET | `/v2/presence/online-count` | Online count | — |

### Onboarding Endpoints

| Method | Endpoint | Description |
|--------|----------|-------------|
| GET | `/v2/onboarding/status` | Progress status |
| GET | `/v2/onboarding/config` | Tenant config |
| GET | `/v2/onboarding/categories` | Interest categories |
| GET | `/v2/onboarding/safeguarding-options` | Safeguarding options |
| POST | `/v2/onboarding/safeguarding` | Save safeguarding prefs |
| POST | `/v2/onboarding/complete` | Complete onboarding |

### Sub-Account Endpoints

| Method | Endpoint | Description |
|--------|----------|-------------|
| GET | `/v2/users/me/sub-accounts` | Child accounts |
| GET | `/v2/users/me/parent-accounts` | Parent accounts |
| POST | `/v2/users/me/sub-accounts` | Request relationship |
| PUT | `/v2/users/me/sub-accounts/{id}/approve` | Approve |
| PUT | `/v2/users/me/sub-accounts/{id}/permissions` | Update permissions |
| DELETE | `/v2/users/me/sub-accounts/{id}` | Revoke |

### Verification Badge Endpoints

| Method | Endpoint | Description |
|--------|----------|-------------|
| GET | `/v2/users/{id}/verification-badges` | User's badges |
| POST | `/v2/admin/users/{id}/verification-badges` | Grant (admin) |
| DELETE | `/v2/admin/users/{id}/verification-badges/{type}` | Revoke (admin) |

### Admin User Endpoints

| Method | Endpoint | Description |
|--------|----------|-------------|
| GET | `/v2/admin/users` | List with status/role/search filter |
| POST | `/v2/admin/users` | Create user |
| GET/PUT/DELETE | `/v2/admin/users/{id}` | View/update/delete |
| POST | `/v2/admin/users/{id}/approve` | Approve pending user |
| POST | `/v2/admin/users/{id}/suspend` | Suspend |
| POST | `/v2/admin/users/{id}/ban` | Ban |
| POST | `/v2/admin/users/{id}/reactivate` | Reactivate |
| POST | `/v2/admin/users/{id}/reset-2fa` | Reset 2FA |
| POST | `/v2/admin/users/{id}/badges` | Add badge |
| DELETE | `/v2/admin/users/{id}/badges/{badgeId}` | Remove badge |
| POST | `/v2/admin/users/{id}/impersonate` | Impersonate user |
| PUT | `/v2/admin/users/{id}/super-admin` | Set super admin |
| POST | `/v2/admin/users/import` | Bulk import users |

---

## 22. Frontend Experience

### MembersPage (`/members`)

- **Search**: 300ms debounced text search
- **Filters**: All Members, New (30 days), Active (7 days)
- **Sort**: Name, Join Date, Rating, Hours Given
- **Views**: Grid (animated cards), List (compact), Map (coordinate pins)
- **Near Me**: Location toggle with radius selection (3/5/10/25 km)
- **Pagination**: Cursor-based "Load More"
- **MemberCard**: Avatar, name, tagline, stats badges (rating, hours, location), presence indicator

### ProfilePage (`/profile/:id`)

- **Tabs**: About, Listings, Reviews, Achievements
- **Profile header**: Avatar, name, tagline, location, verified badge, NexusScore tier
- **Connection button**: Adapts to status (Send Request / Pending / Accept+Decline / Disconnect)
- **Endorsements**: Horizontal badge display with endorser count
- **Listings**: Grid of 6 most recent
- **Reviews**: Cards with rating, reviewer, comment
- **Gamification**: Level, XP, earned badges (if feature enabled)
- **Transfer modal**: Wallet transfer from own profile

### ConnectionsPage (`/connections`)

- **Three tabs**: Accepted (with message/disconnect), Pending Received (accept/decline), Pending Sent (cancel)
- **Tab badges**: Pending counts on tab labels
- **Search**: Client-side filter within loaded connections
- **Cursor pagination**: Independent per tab
- **AnimatePresence**: Smooth card removal on action

### SettingsPage (`/settings`)

6 tabs:
1. **ProfileTab**: Avatar upload, name, phone, location (autocomplete), bio, profile type, language, theme
2. **SecurityTab**: Password change (strength meter), 2FA setup (QR + backup codes), sessions, account deletion
3. **NotificationsTab**: Email toggles (messages, listings, connections, transactions, reviews, gamification, org), push master toggle, digest frequency
4. **PrivacyTab**: Profile visibility (public/members/connections), search indexing toggle, contact permission, GDPR rights (6 actions), insurance certificates
5. **LinkedAccountsTab**: SubAccountsManager component
6. **SkillsTab**: SkillSelector component (offer/need with proficiency)

### OnboardingPage

5-step wizard with progress bar, avatar drag-and-drop, interest category selection, skill offer/need selection, safeguarding preferences, review and confirm.

### SkillsBrowsePage (`/skills`)

Category tree → skill list → member list with proficiency badges (beginner/intermediate/advanced/expert as colored dots).

---

## 23. Admin Panel

### Admin User Management

| Capability | Description |
|-----------|-------------|
| List users | Filterable by status, role, search |
| Create user | Direct user creation |
| View/edit user | All profile fields |
| Approve | Approve pending registrations |
| Suspend/ban | With notification |
| Reactivate | Restore suspended/banned users |
| Reset 2FA | Clear two-factor authentication |
| Impersonate | Login as user (audit-logged) |
| Set roles | Super admin, global super admin |
| Badge management | Add/remove gamification badges, recheck badges |
| Verification badges | Grant/revoke trust badges |
| Password management | Set password, send reset email, send welcome email |
| Import | Bulk user import from file |
| GDPR | View user consents |

---

*This report documents the complete members directory engine as implemented in Project NEXUS v1.5.0. For the most current implementation, refer to the source files listed in Section 2.*
