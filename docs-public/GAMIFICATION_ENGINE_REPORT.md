# Project NEXUS — Gamification Engine: Complete Technical Report

**Generated:** 2026-03-29
**Version:** 1.5.0
**License:** AGPL-3.0-or-later

---

## Table of Contents

1. [Executive Summary](#1-executive-summary)
2. [System Architecture](#2-system-architecture)
3. [XP System](#3-xp-system)
4. [Level System](#4-level-system)
5. [Badge System](#5-badge-system)
6. [Badge Collections & Journeys](#6-badge-collections--journeys)
7. [Daily Rewards & Streaks](#7-daily-rewards--streaks)
8. [Leaderboards](#8-leaderboards)
9. [Seasonal Competitions](#9-seasonal-competitions)
10. [Achievement Campaigns](#10-achievement-campaigns)
11. [XP Shop](#11-xp-shop)
12. [Unlockables](#12-unlockables)
13. [Nexus Score (Reputation)](#13-nexus-score-reputation)
14. [Group Achievements](#14-group-achievements)
15. [Engagement Recognition](#15-engagement-recognition)
16. [Verification Badges](#16-verification-badges)
17. [Real-time Events](#17-real-time-events)
18. [Event-Driven Triggers](#18-event-driven-triggers)
19. [Tenant Customization](#19-tenant-customization)
20. [Database Schema](#20-database-schema)
21. [API Reference](#21-api-reference)
22. [Frontend Experience](#22-frontend-experience)
23. [Admin Panel](#23-admin-panel)
24. [Complete Data Flow](#24-complete-data-flow)

---

## 1. Executive Summary

The Project NEXUS Gamification Engine is a comprehensive engagement system designed to drive participation in a timebanking community. It rewards users for meaningful community activities — not just logging in, but volunteering, exchanging services, connecting with others, and contributing content.

### What It Includes

| System | Purpose |
|--------|---------|
| **XP & Levels** | Dual progression (V1: 25 levels, V2: 10 named tiers) |
| **60+ Badges** | Quantity badges (thresholds) + quality badges (behavioral) |
| **Collections & Journeys** | Grouped badge paths with completion rewards |
| **Daily Rewards** | Login streaks with milestone XP bonuses |
| **4 Streak Types** | Login, activity, giving, volunteer |
| **Leaderboards** | 9 ranking types across 3 time periods |
| **Seasonal Competitions** | Monthly seasons with tiered rewards |
| **Achievement Campaigns** | Admin-created time-limited badge/XP events |
| **XP Shop** | Spend XP on cosmetics, perks, features |
| **Unlockables** | Level/badge-gated themes, frames, banners, name colors |
| **Nexus Score** | 1000-point composite reputation with 9 tiers |
| **Group Achievements** | Community-level goals (50 members, 100 posts) |
| **Engagement Recognition** | Monthly/seasonal activity tracking |
| **Verification Badges** | 8 trust badges (email, ID, background check) |
| **Real-time Events** | Pusher broadcasts for XP, badges, levels, streaks |

### Key Metrics

| Metric | Value |
|--------|-------|
| Backend services | 15+ dedicated gamification services |
| API endpoints | 40+ (user + admin) |
| Database tables | 22 gamification tables |
| Badge definitions | 60+ (quantity + quality + special) |
| XP-earning actions | 18 (V1) / 11 (V2) |
| Leaderboard types | 9 |
| Streak types | 4 |
| Nexus Score tiers | 9 |
| Unlockable items | 22 |
| Real-time event types | 10 |
| Frontend pages | 4 dedicated + dashboard widget + admin module |

---

## 2. System Architecture

### Service Map

```
┌──────────────────────────────────────────────────────────────────────────┐
│                        GAMIFICATION ENGINE                               │
│                                                                          │
│  ┌──────────────────┐  ┌──────────────────┐  ┌───────────────────────┐  │
│  │   XP & LEVELS     │  │     BADGES        │  │    PROGRESSION       │  │
│  │                   │  │                   │  │                      │  │
│  │ GamificationSvc   │  │ BadgeSvc          │  │ BadgeCollectionSvc   │  │
│  │  - awardXP()      │  │ BadgeDefinitionSvc│  │ AchievementCampaignSvc│ │
│  │  - calculateLevel()│  │ MemberVerifBadgeSvc│ │ AchievementAnalyticsSvc││
│  │  - checkLevelUp() │  │                   │  │ AchievementUnlockablesSvc│
│  │  - runAllChecks() │  │                   │  │ GroupAchievementSvc  │  │
│  └────────┬─────────┘  └────────┬──────────┘  └──────────┬───────────┘  │
│           │                     │                         │              │
│  ┌────────▼─────────┐  ┌───────▼───────────┐  ┌─────────▼────────────┐  │
│  │   ENGAGEMENT      │  │    COMPETITION     │  │      ECONOMY         │  │
│  │                   │  │                   │  │                      │  │
│  │ DailyRewardSvc    │  │ LeaderboardSvc    │  │ XPShopSvc            │  │
│  │ StreakSvc         │  │ LeaderboardSeason │  │ CommunityDashboardSvc│  │
│  │ EngagementRecogSvc│  │ NexusScoreCache   │  │ GamificationRealtime │  │
│  └───────────────────┘  └───────────────────┘  └──────────────────────┘  │
└──────────────────────────────────────────────────────────────────────────┘
```

### Key Files

| Component | File |
|-----------|------|
| Core XP/Badges/Levels | `app/Services/GamificationService.php` (1,355 lines) |
| Badge CRUD | `app/Services/BadgeService.php` |
| Badge Definitions | `app/Services/BadgeDefinitionService.php` |
| Badge Collections | `app/Services/BadgeCollectionService.php` |
| Daily Rewards | `app/Services/DailyRewardService.php` |
| Streaks | `app/Services/StreakService.php` |
| Leaderboards | `app/Services/LeaderboardService.php` |
| Seasons | `app/Services/LeaderboardSeasonService.php` |
| Campaigns | `app/Services/AchievementCampaignService.php` |
| Analytics | `app/Services/AchievementAnalyticsService.php` |
| Unlockables | `app/Services/AchievementUnlockablesService.php` |
| Group Achievements | `app/Services/GroupAchievementService.php` |
| Engagement | `app/Services/EngagementRecognitionService.php` |
| XP Shop | `app/Services/XPShopService.php` |
| Real-time | `app/Services/GamificationRealtimeService.php` |
| Verification Badges | `app/Services/MemberVerificationBadgeService.php` |
| Community Dashboard | `app/Services/CommunityDashboardService.php` |
| V2 API Controller | `app/Http/Controllers/Api/GamificationV2Controller.php` |
| Admin Controller | `app/Http/Controllers/Api/AdminGamificationController.php` |

---

## 3. XP System

### How XP Works

XP (Experience Points) is the fundamental currency of the gamification engine. Users earn XP by performing community activities. XP determines level, unlocks badges, and can be spent in the XP Shop.

- Stored in `users.xp` column (integer)
- Logged in `user_xp_log` table (action, amount, description, timestamp)
- Awarded atomically with duplicate prevention for one-time actions

### V1 XP Values (18 actions)

| Action | XP | Trigger |
|--------|----|---------|
| `send_credits` | 10 | Transfer time credits to another user |
| `receive_credits` | 5 | Receive time credits |
| `volunteer_hour` | 20 | Log a verified volunteer hour |
| `create_listing` | 15 | Create a service offer or request |
| `complete_transaction` | 25 | Complete an exchange |
| `leave_review` | 10 | Write a review |
| `attend_event` | 15 | RSVP and attend an event |
| `create_event` | 30 | Organize a community event |
| `join_group` | 10 | Join a community group |
| `create_group` | 50 | Create a community group |
| `create_post` | 5 | Post to the feed |
| `daily_login` | 5 | Claim daily reward |
| `complete_profile` | 50 | Fill out all profile fields (one-time) |
| `earn_badge` | 25 | Earn any badge |
| `vote_poll` | 2 | Vote in a community poll |
| `send_message` | 2 | Send a message |
| `make_connection` | 10 | Connect with another member |
| `complete_goal` | 10 | Complete a personal goal |

### V2 XP Values (11 core actions)

| Action | XP |
|--------|----|
| `complete_transaction` | 25 |
| `volunteer_hour` | 20 |
| `create_listing` | 15 |
| `create_event` | 30 |
| `create_group` | 50 |
| `attend_event` | 15 |
| `leave_review` | 10 |
| `make_connection` | 10 |
| `complete_profile` | 50 |
| `earn_badge` | 25 |
| `complete_goal` | 10 |

### Level-Up Milestone Bonuses

When a user reaches specific levels, they receive bonus XP:

| Level | Bonus XP |
|-------|----------|
| 5 | 50 |
| 10 | 100 |
| 15 | 150 |
| 20 | 200 |
| 25 | 300 |
| 30 | 400 |
| 50 | 500 |
| 100 | 1,000 |

### XP Award Method

```
GamificationService::awardXP(userId, amount, action, description)
```

1. Validates amount > 0
2. For one-time actions (e.g., `complete_profile`): locks row, checks for existing award
3. Increments `users.xp` atomically
4. Logs to `user_xp_log` table
5. Broadcasts XP gained event via Pusher
6. Triggers `checkLevelUp()` — may award milestone bonuses and level badges

---

## 4. Level System

### V1: 25-Level System

| Level | XP Required | Level | XP Required |
|-------|-------------|-------|-------------|
| 1 | 0 | 14 | 16,500 |
| 2 | 100 | 15 | 20,500 |
| 3 | 300 | 16 | 25,000 |
| 4 | 600 | 17 | 30,000 |
| 5 | 1,000 | 18 | 36,000 |
| 6 | 1,500 | 19 | 43,000 |
| 7 | 2,200 | 20 | 51,000 |
| 8 | 3,000 | 21 | 60,000 |
| 9 | 4,000 | 22 | 70,000 |
| 10 | 5,500 | 23 | 82,000 |
| 11 | 7,500 | 24 | 95,000 |
| 12 | 10,000 | 25 | 110,000 |
| 13 | 13,000 | | |

### V2: 10 Named Levels (Current)

| Level | Name | XP Required | Character |
|-------|------|-------------|-----------|
| 1 | Newcomer | 0 | Just joined |
| 2 | Explorer | 100 | Starting to engage |
| 3 | Contributor | 500 | Regular participant |
| 4 | Helper | 1,500 | Active contributor |
| 5 | Builder | 3,500 | Community builder |
| 6 | Advocate | 7,000 | Consistent advocate |
| 7 | Leader | 15,000 | Community leader |
| 8 | Champion | 30,000 | Top contributor |
| 9 | Pillar | 60,000 | Community pillar |
| 10 | Legend | 100,000 | Legendary status |

### Level Progress

```
GamificationService::getLevelProgress(xp, level) → float (0-100%)
```

Progress is calculated as a percentage between the current level's threshold and the next level's threshold. Returns 100 at max level.

### Level-Up Flow

```
User earns XP → awardXP()
  → users.xp incremented
  → checkLevelUp() called
    → calculateLevel(newXP)
    → if newLevel > oldLevel:
      → Award milestone bonus XP (if applicable)
      → Award level badges (level_5, level_10, etc.)
      → Broadcast level-up event via Pusher
      → Log to feed activity
```

---

## 5. Badge System

### Badge Architecture

Badges are organized by **tier** and **class**:

**Tiers:**
| Tier | Description | Can Disable? |
|------|-------------|-------------|
| `core` | Always enabled, fundamental badges | No |
| `template` | Default-enabled, tenant-configurable thresholds | Yes |
| `custom` | Created by tenant admins | Yes |

**Classes:**
| Class | Description | Evaluation |
|-------|-------------|-----------|
| `quantity` | Threshold counters (e.g., "volunteer 50 hours") | Count-based |
| `quality` | Behavioral (reliability, reciprocity, mentoring) | Multi-criteria |
| `special` | One-off awards (early adopter, verified) | Manual/event |
| `verification` | Trust badges (ID, background check) | Admin-granted |

**Rarity:**
| Rarity | Typical Criteria |
|--------|-----------------|
| `common` | Easy to earn (1-5 threshold) |
| `uncommon` | Moderate effort (10-25 threshold) |
| `rare` | Significant effort (50-100 threshold) |
| `epic` | Major commitment (250+ threshold) |
| `legendary` | Exceptional achievement (500+ threshold) |

### Quantity Badges (48+)

#### Volunteering Badges
| Key | Name | Threshold | Rarity |
|-----|------|-----------|--------|
| `vol_1h` | First Hour | 1 hour | common |
| `vol_10h` | Dedicated Volunteer | 10 hours | uncommon |
| `vol_50h` | Volunteer Champion | 50 hours | rare |
| `vol_100h` | Century Volunteer | 100 hours | epic |
| `vol_250h` | Volunteer Legend | 250 hours | epic |
| `vol_500h` | Volunteer Titan | 500 hours | legendary |

#### Listing Badges
| Key | Name | Threshold | Rarity |
|-----|------|-----------|--------|
| `offer_1` | First Offer | 1 offer | common |
| `offer_5` | Active Sharer | 5 offers | uncommon |
| `offer_10` | Generous Giver | 10 offers | rare |
| `offer_25` | Community Pillar | 25 offers | epic |
| `request_1` | First Request | 1 request | common |
| `request_5` | Regular Requester | 5 requests | uncommon |
| `request_10` | Community Seeker | 10 requests | rare |

#### Credit Badges
| Key | Name | Threshold | Rarity |
|-----|------|-----------|--------|
| `earn_1` | First Credit | 1 credit | common |
| `earn_10` | Rising Star | 10 credits | uncommon |
| `earn_50` | Credit Master | 50 credits | rare |
| `earn_100` | Time Banker | 100 credits | epic |
| `earn_250` | Credit Legend | 250 credits | legendary |
| `spend_1` | First Spend | 1 credit | common |
| `spend_10` | Active Spender | 10 credits | uncommon |
| `spend_50` | Generous Soul | 50 credits | rare |

#### Transaction Badges
| Key | Name | Threshold | Rarity |
|-----|------|-----------|--------|
| `transaction_1` | First Transaction | 1 | common |
| `transaction_10` | Regular Trader | 10 | uncommon |
| `transaction_50` | Transaction Pro | 50 | rare |

#### Diversity Badges
| Key | Name | Threshold | Rarity |
|-----|------|-----------|--------|
| `diversity_3` | Social Butterfly | 3 unique people | common |
| `diversity_10` | Connector | 10 unique people | uncommon |
| `diversity_25` | Community Weaver | 25 unique people | rare |

#### Social Badges
| Key | Name | Threshold | Rarity |
|-----|------|-----------|--------|
| `connect_1` | First Connection | 1 | common |
| `connect_10` | Networker | 10 | uncommon |
| `connect_25` | Social Star | 25 | rare |
| `connect_50` | Community Hub | 50 | epic |
| `msg_1` | First Message | 1 | common |
| `msg_50` | Communicator | 50 | uncommon |
| `msg_200` | Master Messenger | 200 | rare |

#### Review Badges
| Key | Name | Threshold | Rarity |
|-----|------|-----------|--------|
| `review_1` | First Review | 1 | common |
| `review_10` | Thoughtful Reviewer | 10 | uncommon |
| `review_25` | Review Expert | 25 | rare |
| `5star_1` | Five Star | 1 five-star review received | common |
| `5star_10` | Consistently Excellent | 10 five-star reviews | uncommon |
| `5star_25` | Gold Standard | 25 five-star reviews | rare |

#### Event Badges
| Key | Name | Threshold | Rarity |
|-----|------|-----------|--------|
| `event_attend_1` | Event Goer | 1 event | common |
| `event_attend_10` | Regular Attendee | 10 events | uncommon |
| `event_attend_25` | Event Enthusiast | 25 events | rare |
| `event_host_1` | Event Host | 1 hosted | uncommon |
| `event_host_5` | Event Organizer | 5 hosted | rare |

#### Group Badges
| Key | Name | Threshold | Rarity |
|-----|------|-----------|--------|
| `group_join_1` | Group Member | 1 group | common |
| `group_join_5` | Group Explorer | 5 groups | uncommon |
| `group_create` | Group Creator | 1 created | rare |

#### Content Badges
| Key | Name | Threshold | Rarity |
|-----|------|-----------|--------|
| `post_1` | First Post | 1 | common |
| `post_25` | Active Poster | 25 | uncommon |
| `post_100` | Content Creator | 100 | rare |
| `likes_50` | Popular | 50 likes received | uncommon |
| `likes_200` | Influencer | 200 likes received | rare |

#### Milestone Badges
| Key | Name | Threshold | Rarity |
|-----|------|-----------|--------|
| `profile_complete` | Complete Profile | 100% | common |
| `member_30d` | One Month | 30 days | common |
| `member_180d` | Six Months | 180 days | uncommon |
| `member_365d` | One Year | 365 days | rare |
| `streak_7d` | Week Warrior | 7-day streak | common |
| `streak_30d` | Monthly Master | 30-day streak | uncommon |
| `streak_100d` | Century Streak | 100-day streak | rare |
| `streak_365d` | Year of Dedication | 365-day streak | legendary |
| `level_5` | Rising Star | Level 5 | uncommon |
| `level_10` | Community Leader | Level 10 | rare |

#### Special Badges
| Key | Name | Rarity |
|-----|------|--------|
| `early_adopter` | Early Adopter | rare |
| `verified` | Verified Member | uncommon |
| `volunteer_org` | Org Creator | rare |

### Quality Badges (5 types)

Quality badges evaluate **behavioral patterns**, not simple counts:

#### 1. Reliability Badge
- **Criteria:** completed transactions >= `min_transactions` AND cancellation rate <= `max_cancellation_rate`
- **Config defaults:** min_transactions = threshold, max_cancellation_rate = 10%
- **Meaning:** Consistently follows through on commitments

#### 2. Bridge Builder Badge
- **Criteria:** distinct listing categories traded in >= `min_categories`
- **Config defaults:** min_categories = threshold
- **Meaning:** Engages across diverse service categories

#### 3. Mentor Badge
- **Criteria:** count of users whose first-ever completed transaction was with this user >= threshold
- **Meaning:** Helps newcomers get started in the community

#### 4. Reciprocity Badge
- **Criteria:** total_transactions >= `min_transactions` AND earn/spend ratio within [`min_ratio`, `max_ratio`]
- **Config defaults:** min_ratio = 0.3, max_ratio = 3.0
- **Meaning:** Maintains balanced giving and receiving

#### 5. Community Champion Badge
- **Criteria:** active in >= N months with >= 2 distinct categories per month AND >= 3 activities per month
- **Config defaults:** min_categories_per_month = 2, min_activity_per_month = 3
- **Meaning:** Sustained, diverse community engagement over time

### Badge Award Flow

```
Activity occurs (transaction, volunteer log, etc.)
  → runAllBadgeChecks(userId) called
    → For each badge category:
      → Count user's relevant stats
      → Compare against threshold
      → If threshold met AND badge not already earned:
        → awardBadge(userId, badge)
          → INSERT IGNORE into user_badges
          → Award 25 XP for earning badge
          → Create notification
          → Broadcast badge-earned event via Pusher
          → Log to feed activity
```

### Badge Showcase

Users can showcase up to **5 badges** on their profile:

```
PUT /v2/gamification/showcase
Body: { badge_keys: ["vol_50h", "earn_100", "streak_30d", "review_25", "level_10"] }
```

- Maximum 5 badges
- All must be owned by the user
- Stored in `user_badges.is_showcased` and `showcase_order` columns

---

## 6. Badge Collections & Journeys

### Collections

Badge collections group related badges together with a **completion bonus**:

```
BadgeCollectionService::getCollectionsWithProgress(userId) → [
  {
    name: "Volunteering Path",
    badges: [vol_1h, vol_10h, vol_50h, vol_100h],
    earned_count: 2,
    total_count: 4,
    progress_percent: 50,
    is_completed: false,
    bonus_xp: 100
  }
]
```

### Journeys

Journeys are **ordered, step-by-step** badge collections:

| Property | Collection | Journey |
|----------|-----------|---------|
| `collection_type` | `collection` | `journey` |
| `is_ordered` | false | true |
| Badge order | Any order | Sequential steps |
| `estimated_duration` | null | e.g., "2 weeks" |

### Completion Rewards

When all badges in a collection are earned:

1. `UserCollectionCompletion` record created
2. Bonus XP awarded (e.g., 100 XP)
3. Optional bonus badge awarded (e.g., collection-specific badge)
4. Notification sent
5. Real-time event broadcast

---

## 7. Daily Rewards & Streaks

### Daily Reward System

Users can claim one reward per calendar day:

| Component | Value |
|-----------|-------|
| Base XP | 5 |
| Claim limit | 1 per day |
| Streak calculation | Continues if claimed yesterday, resets otherwise |

### Streak Milestone Bonuses

| Streak Day | Bonus XP | Total (Base + Bonus) |
|------------|----------|---------------------|
| 1-2 | 0 | 5 |
| 3 | 5 | 10 |
| 4-6 | 0 | 5 |
| 7 | 15 | 20 |
| 8-13 | 0 | 5 |
| 14 | 25 | 30 |
| 15-29 | 0 | 5 |
| 30 | 50 | 55 |
| 31-59 | 0 | 5 |
| 60 | 100 | 105 |
| 61-89 | 0 | 5 |
| 90 | 150 | 155 |

### Daily Reward Claim Flow

```
POST /v2/gamification/daily-reward
```

1. Pre-check: already claimed today? (quick query outside transaction)
2. Calculate streak: last_reward = yesterday? → streak + 1, else reset to 1
3. Calculate XP: base (5) + milestone bonus (if streak matches)
4. Inside atomic operation:
   - Insert `daily_rewards` record (UNIQUE constraint prevents double-claim)
   - Update `users.login_streak`, `users.last_daily_reward`, `users.longest_streak`
   - Increment `users.xp`
   - Log to `user_xp_log`
5. Return: `{ xp_earned, base_xp, milestone_bonus, streak_day, longest_streak }`

### Streak Types

The platform tracks **4 independent streaks**:

| Type | Tracked By | Trigger |
|------|-----------|---------|
| `login` | StreakService | Daily reward claim |
| `activity` | StreakService | Any community activity |
| `giving` | StreakService | Sending credits/volunteering |
| `volunteer` | StreakService | Logging volunteer hours |

### Streak Icons & Messages

| Streak Length | Icon | Message |
|---------------|------|---------|
| 0 days | Broken heart | "Start your streak today!" |
| 1-6 days | Sparkles | "X day streak - keep going!" |
| 7-29 days | Fire | "Great job! X day streak!" |
| 30-99 days | Fire + Star | "Fantastic! X day streak!" |
| 100-364 days | Fire + Diamond | "Amazing! X day streak!" |
| 365+ days | Fire + Trophy | "Incredible! X day streak! You're a legend!" |

---

## 8. Leaderboards

### Leaderboard Types (9)

| Type | Score Source | Query |
|------|-------------|-------|
| `credits_earned` | SUM of received transaction amounts | transactions.receiver_id |
| `credits_spent` | SUM of sent transaction amounts | transactions.sender_id |
| `vol_hours` | SUM of approved volunteer hours | vol_logs.hours |
| `badges` | COUNT of badges earned | user_badges |
| `xp` | Direct from users.xp | users.xp |
| `connections` | COUNT DISTINCT connections | connections (both directions) |
| `reviews` | COUNT of reviews written | reviews.reviewer_id |
| `posts` | COUNT of feed posts | feed_posts.user_id |
| `streak` | Current/longest login streak | user_streaks |

### Time Periods

| Period | Filter |
|--------|--------|
| `all_time` | No date filter |
| `monthly` | Last 30 days |
| `weekly` | Last 7 days |

### Ranking Algorithm

- SQL-based with `ORDER BY score DESC LIMIT`
- Sequential rank assignment (1, 2, 3...)
- All queries filter by `tenant_id`, `is_approved = 1`, `show_on_leaderboard = 1`
- `HAVING score > 0` ensures only active users appear

### User Rank Calculation

```
LeaderboardService::getUserRank(tenantId, userId) → {
  rank: int,     // COUNT of users with higher XP + 1
  xp: int,
  level: int
}
```

---

## 9. Seasonal Competitions

### Season Structure

- **Type:** Monthly (auto-created for current month)
- **Status lifecycle:** `draft` → `active` → `completed`
- **Dates:** First day 00:00 to last day 23:59 of each month
- **Auto-creation:** `getOrCreateCurrentSeason()` creates if missing

### Season Rewards

| Position | XP Reward | Badge |
|----------|-----------|-------|
| 1st place | 500 XP | `season_champion` |
| 2nd place | 300 XP | `season_runner_up` |
| 3rd place | 200 XP | `season_third` |
| Top 10 | 100 XP | `season_top10` |
| Top 25 | 50 XP | — |
| Participant | 25 XP | — |

### Season Lifecycle

1. **Auto-created** at start of month (first access triggers creation)
2. **Active** throughout the month, XP accumulates
3. **End of season:** `endSeason()` distributes rewards
   - Awards XP via `GamificationService::awardXP()`
   - Awards badges via `GamificationService::awardBadgeByKey()`
   - Status → `completed`

### Season Data for User

```
GET /v2/gamification/seasons/current → {
  season: { id, name, start_date, end_date, status },
  user_rank: { position, season_xp },
  leaderboard: [...top 10...],
  rewards: [...tier rewards...],
  days_remaining: int,
  is_ending_soon: bool,  // < 3 days remaining
  total_participants: int
}
```

---

## 10. Achievement Campaigns

Admin-created time-limited events that award badges or XP to targeted audiences.

### Campaign Types

| Type | DB Value | Description |
|------|----------|-------------|
| `one_time` | `badge_award` | Award once to qualifying users |
| `recurring` | `xp_bonus` | Award on schedule (daily/weekly/monthly) |
| `triggered` | `challenge` | Award when user meets conditions |

### Target Audiences

| Audience | Filter |
|----------|--------|
| `all_users` | Everyone |
| `new_users` | Joined in last 30 days |
| `active_users` | Logged in this week |
| `inactive_users` | No login in 30+ days |
| `level_range` | Specific level range |
| `badge_holders` | Users with specific badge |
| `custom` | Custom SQL filter |

### Campaign Lifecycle

```
Draft → Active (activated_at set) → Paused → Active → Completed
```

### Admin Methods

| Method | Description |
|--------|-------------|
| `createCampaign(data)` | Create draft campaign |
| `activateCampaign(id)` | Set to active |
| `pauseCampaign(id)` | Pause execution |
| `updateCampaign(id, data)` | Update configuration |
| `deleteCampaign(id)` | Hard delete |

---

## 11. XP Shop

Users can spend their earned XP on items in the shop.

### Item Types

| Type | Description | Expiry |
|------|-------------|--------|
| `perk` | Temporary benefit | 30 days |
| `cosmetic` | Visual customization | Permanent |
| `feature` | Feature unlock | Permanent |
| `badge` | Special badge | Permanent |

### Purchase Flow

```
POST /v2/gamification/shop/purchase { item_id: 5 }
```

1. Pre-check: user has enough XP, stock not full, per-user limit not hit
2. **BEGIN TRANSACTION**
3. Lock item row (`FOR UPDATE`) — prevents race conditions
4. Re-verify stock and limits (TOCTOU prevention)
5. Atomic XP deduction: `UPDATE users SET xp = xp - cost WHERE xp >= cost`
6. Insert `user_xp_purchases` record (expires_at = +30 days for perks)
7. **COMMIT**
8. Broadcast shop-purchase event

### Purchase Blocking Reasons

| Reason | Condition |
|--------|-----------|
| "Not enough XP" | user.xp < item.xp_cost |
| "Out of stock" | purchase_count >= stock_limit |
| "Already owned" | user_purchase_count >= per_user_limit |

### Item Configuration

| Field | Description |
|-------|-------------|
| `xp_cost` | Price in XP |
| `stock_limit` | Total available (null = unlimited) |
| `per_user_limit` | Max per user (default 1) |
| `display_order` | Sort position |
| `is_active` | Available for purchase |

---

## 12. Unlockables

Level and badge-gated cosmetic rewards.

### Unlockable Categories (22 items)

#### Themes (6)
| Key | Name | Requirement | Colors |
|-----|------|-------------|--------|
| `dark_gold` | Dark Gold | Level 10 | #1e1e2e / #fbbf24 |
| `ocean` | Ocean Blue | Level 15 | blue tones |
| `forest` | Forest | Badge: `volunteer_5` | green tones |
| `sunset` | Sunset | Level 20 | orange/red tones |
| `royal` | Royal Purple | Level 25 | purple tones |
| `legendary` | Legendary | Level 50 | gold/dark tones |

#### Avatar Frames (6)
| Key | Name | Requirement | Effect |
|-----|------|-------------|--------|
| `bronze` | Bronze Ring | Level 5 | Bronze border |
| `silver` | Silver Ring | Level 10 | Silver border |
| `gold` | Gold Ring | Level 20 | Gold glow |
| `diamond` | Diamond Ring | Level 30 | Animated diamond |
| `fire` | Fire Ring | Badge: `streak_100d` | Fire animation |
| `rainbow` | Rainbow Ring | 20 badges | Animated gradient |

#### Profile Banners (3)
| Key | Name | Requirement |
|-----|------|-------------|
| `stars` | Star Field | Level 15 |
| `gradient` | Gradient | Level 25 |
| `champion` | Champion | Badge: `leaderboard_1` |

#### Name Colors (3)
| Key | Name | Requirement |
|-----|------|-------------|
| `gold` | Gold Name | Level 20 |
| `purple` | Purple Name | Level 30 |
| `rainbow` | Rainbow Name | Level 50 |

#### Special Emoji (4)
| Key | Name | Requirement |
|-----|------|-------------|
| `crown` | Crown | Level 25 |
| `star` | Star | Level 15 |
| `fire` | Fire | Badge: `streak_30d` |
| `diamond` | Diamond | Level 40 |

### Requirement Types

| Type | Check |
|------|-------|
| `level` | User level >= value |
| `badge` | User owns badge_key |
| `badges_count` | User has >= N total badges |

### Activation

Users can equip one unlockable per type:

```
POST: setActiveUnlockable(userId, type, key)
DELETE: removeActiveUnlockable(userId, type)
```

Stored in `user_active_unlockables` table.

---

## 13. Nexus Score (Reputation)

A composite **1000-point reputation score** calculated from 6 dimensions.

### Score Breakdown

| Category | Max Points | What It Measures |
|----------|-----------|------------------|
| Engagement | 250 | Activity frequency, consistency |
| Quality | 200 | Review scores, completion rates |
| Volunteer | 200 | Volunteer hours logged |
| Activity | 150 | Listings, events, posts created |
| Badges | 100 | Badge count and rarity |
| Impact | 100 | Connections, diversity of engagement |
| **Total** | **1,000** | |

### Tier System (9 tiers)

| Tier | Min Score | Color |
|------|-----------|-------|
| Novice | 0 | slate |
| Beginner | 200 | amber |
| Developing | 300 | emerald |
| Intermediate | 400 | cyan |
| Proficient | 500 | violet |
| Advanced | 600 | indigo |
| Expert | 700 | orange |
| Elite | 800 | pink |
| Legendary | 900 | yellow/gold |

### Percentile Ranking

Each user receives a percentile (0-100) indicating their standing relative to all users in the tenant.

### Milestones

Tracked in `nexus_score_milestones`:
- Score milestones: 100, 200, 300, ..., 900
- Tier milestones: tier_beginner, tier_intermediate, ..., tier_legendary

### History

Score snapshots stored in `nexus_score_history` (date-based) for trend tracking.

---

## 14. Group Achievements

Community-level goals that groups work toward together.

### Achievement Definitions

| Key | Name | Target | Value | XP Reward |
|-----|------|--------|-------|-----------|
| `community_builders` | Community Builders | member_count | 50 | 500 |
| `active_hub` | Active Hub | post_count | 100 | 300 |
| `event_masters` | Event Masters | event_count | 10 | 400 |
| `first_steps` | First Steps | member_count | 10 | 100 |
| `discussion_starters` | Discussion Starters | discussion_count | 10 | 200 |

### Progress Calculation

| Target Type | Count Source |
|-------------|-------------|
| `member_count` | Active group members |
| `post_count` | Group posts (via discussions) |
| `event_count` | Events linked to group |
| `discussion_count` | Group discussions |

### Award Flow

```
GroupAchievementService::checkAndAwardAchievements(groupId) → awarded[]
```

Periodically checks all 5 achievements. When current value meets target:
- Sets `earned=1`, `earned_at=NOW()` in `group_achievement_progress`
- Stores XP reward value
- Returns array of newly awarded achievement keys

---

## 15. Engagement Recognition

Replaces simple login tracking with **meaningful monthly/seasonal activity measurement**.

### Monthly Engagement

```
EngagementRecognitionService::checkMonthlyEngagement(tenantId, userId) → {
  activity_count: int,
  was_active: bool
}
```

Activity count includes:
1. Transactions (sender or receiver)
2. Event attendance records
3. Volunteer log entries
4. Listings created

`was_active` = true if activity_count > 0

Stored in `monthly_engagement` table by `year_month`.

### Seasonal Recognition

Quarterly aggregation (Q1-Q4):

```
EngagementRecognitionService::updateSeasonalRecognition(tenantId, userId) → {
  season: "2026-Q1",
  months_active: int (0-3)
}
```

Counts months in the current quarter where `was_active=true`.

---

## 16. Verification Badges

Trust badges granted by admins to verify user identity and credentials.

### Badge Types (8)

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

```
MemberVerificationBadgeService::grantBadge(userId, badgeType, adminId, note?, expiresAt?)
MemberVerificationBadgeService::revokeBadge(userId, badgeType, adminId)
```

- Upsert on grant (re-grants if previously revoked)
- Soft-revoke via `revoked_at` timestamp
- Optional expiry date
- Notification on grant
- Admin audit trail (`verified_by`, `verification_note`)

---

## 17. Real-time Events

All gamification events broadcast instantly via Pusher to the user's private channel.

### Event Types

| Event | Payload | Trigger |
|-------|---------|---------|
| `badge-earned` | badge key/name/icon/xp | Badge awarded |
| `xp-gained` | amount, reason, new_total, level, progress | XP awarded |
| `level-up` | new_level, rewards, celebration config | Level threshold crossed |
| `challenge-completed` | challenge id/title/xp_reward | Challenge finished |
| `collection-completed` | collection name/icon/bonus_xp | All badges in collection earned |
| `daily-reward` | xp, streak_day, milestone_bonus | Daily reward claimed |
| `streak-milestone` | days, bonus_xp, message | Streak milestone reached |
| `rank-change` | old_rank, new_rank, direction, leaderboard | Leaderboard position change |
| `progress-update` | badge_key, current, target, percent | Badge progress updated |
| `shop-purchase` | item name/icon/xp_cost | XP shop purchase |

### Level-Up Celebrations

| Level | Celebration |
|-------|-------------|
| Standard level-up | Confetti, "levelup" sound, 3s duration |
| Level 5 | Confetti, "fanfare" sound, 5s duration |
| Level 10 | Confetti, "fanfare" sound, 5s, "Double Digits!" title |
| Level 25 | Confetti + fireworks, "fanfare", 5s, "Quarter Century!" |
| Level 50 | Confetti + fireworks, "fanfare", 5s, "Half Century Hero!" |
| Level 100 | Confetti + fireworks, "fanfare", 5s, "CENTURION!" |

### Streak Milestone Messages

| Day | Message |
|-----|---------|
| 7 | "One Week Warrior! Keep the momentum!" |
| 14 | "Two Week Champion! You're on fire!" |
| 30 | "Monthly Master! Incredible dedication!" |
| 60 | "Two Month Titan! Unstoppable!" |
| 90 | "Quarter Year Legend! Amazing!" |
| 100 | "Century Streak! You're a legend!" |
| 180 | "Half Year Hero! Truly inspiring!" |
| 365 | "ONE YEAR STREAK! LEGENDARY!" |

---

## 18. Event-Driven Triggers

The gamification system integrates with the platform's event system to automatically award XP and check badges.

### Laravel Events → Gamification

| Event | Listener | Gamification Action |
|-------|----------|-------------------|
| `TransactionCompleted` | `UpdateWalletBalance` | Award 10 XP to sender, 5 XP to receiver, run all badge checks for both |
| `ListingCreated` | `UpdateFeedOnListingCreated` | Award 15 XP, check listing badges |
| `UserRegistered` | `SendWelcomeNotification` | Apply starting balance (if configured) |

### Badge Check Triggers

`runAllBadgeChecks(userId)` is called after:
- Transaction completion (for both parties)
- Manual admin trigger (`POST /v2/admin/gamification/recheck-all`)
- Individual user recheck (`POST /v2/admin/users/{id}/badges/recheck`)

The method runs **18 category checks** evaluating all 60+ badge conditions against current user stats.

---

## 19. Tenant Customization

Each tenant can customize the gamification system without code changes.

### Badge Overrides

Per-tenant overrides stored in `tenant_badge_overrides`:

| Override | Description |
|----------|-------------|
| `is_enabled` | Enable/disable badge (core badges cannot be disabled) |
| `custom_threshold` | Change requirement (e.g., 25 hours instead of 50) |
| `custom_name` | Rename badge |
| `custom_description` | Custom description |
| `custom_icon` | Custom icon |

### Admin API

```
GET  /v2/admin/gamification/badge-config           → All badges with overrides
PUT  /v2/admin/gamification/badge-config/{key}      → Update override
POST /v2/admin/gamification/badge-config/{key}/reset → Reset to defaults
```

### Caching

Badge definitions cached for 5 minutes per tenant. Cache cleared on override change.

### Feature Gating

The entire gamification module is gated by `gamification` feature flag:
- PHP: `TenantContext::hasFeature('gamification')`
- React: `<FeatureGate feature="gamification">`

---

## 20. Database Schema

### Tables Overview (22 tables)

| Table | Purpose | Key Columns |
|-------|---------|-------------|
| `badges` | Badge definitions | badge_key, name, icon, threshold, badge_tier, badge_class, rarity, config_json |
| `user_badges` | Badges earned by users | user_id, badge_key, awarded_at, is_showcased |
| `custom_badges` | Tenant-created badges | badge_key, trigger_type, trigger_condition, xp_reward |
| `tenant_badge_overrides` | Per-tenant badge customization | badge_key, is_enabled, custom_threshold |
| `badge_collections` | Grouped badge paths | collection_key, bonus_xp, collection_type, is_ordered |
| `badge_collection_items` | Badges within collections | collection_id, badge_key, display_order |
| `user_collection_completions` | Collection completion tracking | user_id, collection_id, bonus_claimed |
| `user_xp_log` | XP transaction history | user_id, xp_amount, action, description |
| `xp_shop_items` | Shop inventory | item_key, xp_cost, stock_limit, per_user_limit |
| `user_xp_purchases` | Purchase records | user_id, item_id, xp_spent, expires_at |
| `user_streaks` | Streak tracking (4 types) | streak_type, current_streak, longest_streak, last_activity_date |
| `daily_rewards` | Daily reward claims | reward_date, xp_earned, streak_day, milestone_bonus |
| `leaderboard_cache` | Cached rankings | leaderboard_type, period, score, rank_position |
| `leaderboard_seasons` | Seasonal competitions | season_type, start_date, end_date, status, rewards |
| `achievement_campaigns` | Admin campaigns | campaign_type, badge_key, xp_amount, target_audience, status |
| `achievement_analytics` | Analytics data | metric_type, metric_value, details JSON |
| `achievement_celebrations` | Social celebrations | user_id, achievement_type, achievement_id |
| `user_challenge_progress` | Challenge progress | challenge_id, current_count, completed_at, reward_claimed |
| `nexus_score_cache` | Reputation scores | total_score (0-1000), 6 component scores, percentile, tier |
| `nexus_score_history` | Score snapshots | total_score, tier, snapshot_date |
| `nexus_score_milestones` | Score milestones | milestone_type, score_at_milestone |
| `monthly_engagement` | Monthly activity tracking | year_month, was_active, activity_count |
| `seasonal_recognition` | Quarterly recognition | season, months_active |

### Key Constraints

| Table | Constraint |
|-------|-----------|
| `user_badges` | UNIQUE (user_id, badge_key) — one badge per user |
| `daily_rewards` | UNIQUE (tenant_id, user_id, reward_date) — one claim per day |
| `user_streaks` | UNIQUE (tenant_id, user_id, streak_type) — one per type |
| `badge_collections` | UNIQUE (tenant_id, collection_key) |
| `xp_shop_items` | UNIQUE (tenant_id, item_key) |
| `nexus_score_cache` | UNIQUE (tenant_id, user_id) |
| `nexus_score_milestones` | UNIQUE (tenant_id, user_id, milestone_type) |

---

## 21. API Reference

### User Endpoints

| Method | Endpoint | Description | Rate Limit |
|--------|----------|-------------|------------|
| GET | `/v2/gamification/profile` | XP, level, badges, streak | 60/min |
| GET | `/v2/gamification/badges` | All badges with earned status | 60/min |
| GET | `/v2/gamification/badges/{key}` | Single badge detail | 60/min |
| GET | `/v2/gamification/leaderboard` | Ranked users (period, type, limit) | 30/min |
| GET | `/v2/gamification/challenges` | Active challenges with progress | 30/min |
| POST | `/v2/gamification/challenges/{id}/claim` | Claim challenge reward | 10/min |
| GET | `/v2/gamification/collections` | Badge collections with progress | 30/min |
| GET | `/v2/gamification/daily-reward` | Check daily reward status | 30/min |
| POST | `/v2/gamification/daily-reward` | Claim daily reward | 10/min |
| GET | `/v2/gamification/shop` | XP shop items | 30/min |
| POST | `/v2/gamification/shop/purchase` | Purchase item with XP | 10/min |
| PUT | `/v2/gamification/showcase` | Set 5 showcased badges | 10/min |
| GET | `/v2/gamification/seasons` | All seasons | 30/min |
| GET | `/v2/gamification/seasons/current` | Current season with user rank | 30/min |
| GET | `/v2/gamification/nexus-score` | Reputation score breakdown | 30/min |
| GET | `/v2/gamification/community-dashboard` | Aggregate community stats | — |
| GET | `/v2/gamification/personal-journey` | Personal progress timeline | — |
| GET | `/v2/gamification/member-spotlight` | Featured active members | — |
| GET | `/v2/gamification/engagement-history` | 12-month engagement calendar | — |

### Admin Endpoints

| Method | Endpoint | Description |
|--------|----------|-------------|
| GET | `/v2/admin/gamification/stats` | Aggregate stats |
| GET | `/v2/admin/gamification/badges` | All badge definitions |
| POST | `/v2/admin/gamification/badges` | Create custom badge |
| DELETE | `/v2/admin/gamification/badges/{id}` | Delete custom badge |
| GET | `/v2/admin/gamification/campaigns` | List campaigns |
| POST | `/v2/admin/gamification/campaigns` | Create campaign |
| PUT | `/v2/admin/gamification/campaigns/{id}` | Update campaign |
| DELETE | `/v2/admin/gamification/campaigns/{id}` | Delete campaign |
| POST | `/v2/admin/gamification/recheck-all` | Re-evaluate all user badges |
| POST | `/v2/admin/gamification/bulk-award` | Bulk-award badge to users |
| GET | `/v2/admin/gamification/badge-config` | Badge config with overrides |
| PUT | `/v2/admin/gamification/badge-config/{key}` | Update badge config |
| POST | `/v2/admin/gamification/badge-config/{key}/reset` | Reset to defaults |

### Verification Badge Endpoints

| Method | Endpoint | Description |
|--------|----------|-------------|
| GET | `/v2/users/{id}/verification-badges` | User's verification badges |
| POST | `/v2/admin/users/{id}/verification-badges` | Grant verification badge |
| DELETE | `/v2/admin/users/{id}/verification-badges/{type}` | Revoke verification badge |

---

## 22. Frontend Experience

### Dedicated Pages

#### AchievementsPage (`/achievements`)
The main gamification hub with sections:
- **Daily Reward Widget** — streak display, claim button, next milestone preview
- **Badge Showcase Modal** — edit 5 showcased badges
- **Challenges Tab** — active/completed challenges with progress bars and claim buttons
- **Journeys/Collections Tab** — ordered paths and grouped collections
- **Engagement History Tab** — 12-month XP-per-day calendar
- **XP Shop Tab** — purchasable items with XP pricing

#### LeaderboardPage (`/leaderboard`)
Four-tab community-focused design:
- **Most Active** — Traditional ranked leaderboard (position, avatar, name, score, level)
- **Community Impact** — Aggregate stats (total members, badges, volunteer hours, XP, trends)
- **My Journey** — Personal timeline (monthly XP chart, badge progression, milestones)
- **Member Spotlight** — 6 daily-rotating featured members with bio and stats

Filters: Period (all/season/month/week), Type (xp/volunteer_hours/credits_earned/nexus_score)

#### NexusScorePage (`/nexus-score`)
- **Score ring** — circular SVG progress (0-1000)
- **Tier badge** — 9 tiers with color coding
- **Percentile** — "Top X%" ranking
- **6-category breakdown** — each with score/max and progress bar
- **Insights** — actionable recommendations to improve score
- **Tier ladder** — visualization of all 9 tiers with current position

#### Dashboard Widget
Optional gamification card on the main dashboard (feature-gated):
- Level and XP progress bar (LevelProgress component)
- Badge count
- Quick link to achievements page

### Reusable Components

**LevelProgress** (`src/components/ui/LevelProgress.tsx`)
- Glassmorphic XP progress bar
- Shows: "Level X — [Level Name]", XP count, gradient progress bar
- Gradient: indigo-500 → purple-500 → pink-500

### Admin Module

Located in `src/admin/modules/gamification/`:

| Component | Purpose |
|-----------|---------|
| `GamificationHub.tsx` | Admin dashboard with stats, badge distribution, quick actions |
| `BadgeConfiguration.tsx` | Enable/disable badges, customize thresholds, reset to defaults |
| `CampaignList.tsx` | List/manage campaigns with status badges |
| `CampaignForm.tsx` | Create/edit campaign form |
| `CreateBadge.tsx` | Create custom badges with icon/category selection |
| `CustomBadges.tsx` | Manage existing custom badges |
| `GamificationAnalytics.tsx` | Analytics dashboard (trends, distribution, performance) |

### TypeScript Types

Key interfaces defined in `src/types/api.ts`:

```typescript
interface GamificationProfile {
  xp: number;
  level: number;
  level_name: string;
  xp_to_next_level: number;
  total_xp_for_next_level: number;
  rank: number;
  rank_percentile: number;
  streak_days: number;
  badges_earned: number;
  badges_total: number;
  recent_achievements: Achievement[];
}

interface Badge {
  id: number;
  name: string;
  description: string;
  icon: string;
  category: string;
  xp_value: number;
  rarity: 'common' | 'uncommon' | 'rare' | 'epic' | 'legendary';
  earned: boolean;
  earned_at?: string;
  progress?: { current: number; target: number; percentage: number };
}

interface NexusScoreData {
  total_score: number;
  max_score: number;     // 1000
  percentage: number;
  percentile: number;
  tier: string;
  breakdown: ScoreCategory[];
  insights: string[];
}
```

---

## 23. Admin Panel

### Admin Capabilities

| Capability | Endpoint | Description |
|-----------|----------|-------------|
| View stats | `GET /stats` | Total badges, active users, XP awarded, campaigns |
| Create badge | `POST /badges` | Custom badge with icon, category, XP value |
| Delete badge | `DELETE /badges/{id}` | Cascades to user_badges |
| Configure badge | `PUT /badge-config/{key}` | Enable/disable, custom threshold/name/icon |
| Reset badge | `POST /badge-config/{key}/reset` | Revert to defaults |
| Create campaign | `POST /campaigns` | Target audience, badge/XP reward, schedule |
| Manage campaign | `PUT /campaigns/{id}` | Activate, pause, update |
| Bulk award | `POST /bulk-award` | Award badge to list of user IDs |
| Recheck all | `POST /recheck-all` | Re-evaluate badges for all users (chunks of 100) |
| Grant verification | `POST /users/{id}/verification-badges` | Admin-issued trust badge |
| Revoke verification | `DELETE /users/{id}/verification-badges/{type}` | Soft-revoke |

### Badge Configuration UI

Admin can for each badge:
- Toggle enabled/disabled (core badges always on)
- Override threshold (e.g., change vol_50h from 50 to 25 hours)
- Rename badge
- Change description
- Change icon
- Reset any override to platform defaults

---

## 24. Complete Data Flow

### User Completes an Exchange

```
1. Exchange completed → TransactionCompleted event fired
2. UpdateWalletBalance listener (async queue):
   a. Awards 10 XP to sender (send_credits)
   b. Awards 5 XP to receiver (receive_credits)
   c. Runs runAllBadgeChecks(senderId):
      - checkTimebankingBadges() → earn_*, spend_*, transaction_*, diversity_*
      - checkReliabilityBadges() → reliability badge
      - checkBridgeBuilderBadges() → bridge_builder badge
      - checkReciprocityBadges() → reciprocity badge
      - checkMentorBadges() → mentor badge
      - checkCommunityChampionBadges() → community_champion badge
      - ... (18 check categories total)
   d. Runs runAllBadgeChecks(receiverId) (same checks)

3. For each XP award:
   a. users.xp incremented atomically
   b. user_xp_log record created
   c. checkLevelUp() evaluates new level
   d. If level up:
      - Milestone bonus XP awarded (if applicable)
      - Level badges checked (level_5, level_10)
      - Pusher: level-up event with celebration config
      - Feed activity created
   e. Pusher: xp-gained event

4. For each badge earned:
   a. INSERT IGNORE into user_badges
   b. 25 XP awarded for earning badge
   c. Notification created
   d. Pusher: badge-earned event
   e. Feed activity created
   f. Collection completion checked → bonus XP if collection done

5. Frontend receives Pusher events:
   a. Toast notification: "You earned the Diversity badge!"
   b. XP counter animates up
   c. Level-up celebration (confetti, fireworks if milestone)
   d. Badge progress bar updates
```

### User Claims Daily Reward

```
1. User taps "Claim Reward" button
2. POST /v2/gamification/daily-reward
3. DailyRewardService::claim():
   a. Check not already claimed today
   b. Calculate streak (continue or reset)
   c. Calculate XP: base(5) + milestone bonus
   d. Atomic insert + user update
4. Response: { xp_earned: 20, streak_day: 7, milestone_bonus: 15 }
5. StreakService::recordLogin() updates login streak
6. Pusher: daily-reward event
7. If streak milestone (7, 30, 100, 365):
   a. Pusher: streak-milestone event with message
   b. GamificationService::checkStreakBadges() → streak_7d, streak_30d, etc.
8. Frontend: Toast with streak animation, XP counter updates
```

### Admin Creates Campaign

```
1. Admin opens Gamification Hub → Campaigns
2. Clicks "Create Campaign"
3. Fills form: name, description, badge_key, target_audience, schedule
4. POST /v2/admin/gamification/campaigns → status: draft
5. Admin clicks "Activate"
6. PUT /v2/admin/gamification/campaigns/{id} { status: 'active' }
7. Campaign runs based on type:
   - one_time: Awards badge to all matching users immediately
   - recurring: Awards XP on schedule
   - triggered: Watches for user conditions
8. Results tracked in campaign.total_awards
```

---

*This report documents the complete gamification engine as implemented in Project NEXUS v1.5.0. For the most current implementation, refer to the source files listed in Section 2.*
