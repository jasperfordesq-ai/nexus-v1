# Project NEXUS — Goals & Impact Module Engine: Complete Technical Report

**Generated:** 2026-03-29
**Version:** 1.5.0
**License:** AGPL-3.0-or-later

---

## Table of Contents

1. [Executive Summary](#1-executive-summary)
2. [System Architecture](#2-system-architecture)
3. [Goals CRUD](#3-goals-crud)
4. [Goal Progress & Completion](#4-goal-progress--completion)
5. [Check-Ins](#5-check-ins)
6. [Buddy/Mentor System](#6-buddymentor-system)
7. [Goal Templates](#7-goal-templates)
8. [Goal Reminders](#8-goal-reminders)
9. [Progress History & Timeline](#9-progress-history--timeline)
10. [Deliverables (Task Management)](#10-deliverables-task-management)
11. [Deliverable Comments & Milestones](#11-deliverable-comments--milestones)
12. [Group Tasks](#12-group-tasks)
13. [Impact Reporting & SROI](#13-impact-reporting--sroi)
14. [Community Health Metrics](#14-community-health-metrics)
15. [Community Dashboard](#15-community-dashboard)
16. [Community Projects](#16-community-projects)
17. [Community Fund](#17-community-fund)
18. [Database Schema](#18-database-schema)
19. [API Reference](#19-api-reference)
20. [Frontend Experience](#20-frontend-experience)
21. [Admin Panel](#21-admin-panel)

---

## 1. Executive Summary

The Goals & Impact Module is a dual-purpose system: **Goals** empower individual members to set, track, and achieve personal objectives with community support, while **Impact** provides administrators with Social Return on Investment (SROI) calculations, community health metrics, and data-driven reporting. Together, they answer two fundamental questions: "What am I working toward?" and "What difference is our community making?"

### What It Includes

| System | Purpose |
|--------|---------|
| **Goals** | Personal goal setting with progress tracking, check-ins, and mentorship |
| **Check-Ins** | Periodic progress reflections with mood tracking (6 moods) |
| **Buddy/Mentor** | Peer support — community members volunteer to mentor goal-setters |
| **Templates** | Pre-built goal templates organized by category |
| **Reminders** | Configurable frequency (daily/weekly/biweekly/monthly) |
| **Progress History** | Full timeline of goal events (progress, check-ins, milestones, buddy joins) |
| **Deliverables** | Project task management with priority, assignment, dependencies |
| **Milestones** | Ordered sub-tasks within deliverables with dependency chains |
| **Comments** | Threaded discussion on deliverables with reactions and mentions |
| **Group Tasks** | Collaborative task boards for community groups |
| **SROI Calculation** | Social Return on Investment with configurable hourly value and multiplier |
| **Community Health** | Engagement rate, retention, reciprocity, network density, activation |
| **Impact Timeline** | Monthly breakdown of hours exchanged, transactions, new users |
| **Community Dashboard** | Aggregate stats, personal journey, member spotlight |
| **Community Projects** | Member-proposed volunteer project ideas with voting |
| **Community Fund** | Shared credit pool with deposits, withdrawals, donations |

### Key Metrics

| Metric | Value |
|--------|-------|
| Backend services | 10 dedicated services |
| API endpoints | 30+ goal/deliverable/impact routes |
| Database tables | 10 goal/deliverable tables |
| Models | 6 Eloquent models |
| Goal statuses | 4 (active, completed, achieved, abandoned) |
| Mood options | 6 (great, good, okay, struggling, motivated, grateful) |
| Deliverable statuses | 8 (draft → completed/cancelled/on_hold) |
| Deliverable priorities | 4 (low, medium, high, urgent) |
| SROI defaults | $15/hour, 3.5× social multiplier |
| Community health metrics | 8 quantitative indicators |

---

## 2. System Architecture

### Service Map

```
┌───────────────────────────────────────────────────────────────────────────┐
│                     GOALS & IMPACT ENGINE                                 │
│                                                                           │
│  ┌─────────────────────┐  ┌─────────────────────┐  ┌──────────────────┐  │
│  │   GOALS               │  │   DELIVERABLES       │  │   IMPACT          │  │
│  │                       │  │                      │  │                   │  │
│  │ GoalService           │  │ DeliverableService   │  │ ImpactReportSvc   │  │
│  │ GoalCheckinService    │  │  - Tasks, assignment │  │  - SROI            │  │
│  │ GoalProgressService   │  │  - Comments          │  │  - Health metrics  │  │
│  │ GoalReminderService   │  │  - Milestones        │  │  - Timeline        │  │
│  │ GoalTemplateService   │  │                      │  │                   │  │
│  └───────────┬───────────┘  └──────────┬───────────┘  └────────┬─────────┘  │
│              │                         │                        │            │
│  ┌───────────▼──────────────────────────▼────────────────────────▼─────────┐ │
│  │                    COMMUNITY                                            │ │
│  │                                                                         │ │
│  │  CommunityDashboardService  ·  CommunityProjectService  ·  CommunityFundService  │ │
│  └─────────────────────────────────────────────────────────────────────────┘ │
└───────────────────────────────────────────────────────────────────────────┘
```

### Key Files

| Component | File |
|-----------|------|
| Goals CRUD | `app/Services/GoalService.php` |
| Check-Ins | `app/Services/GoalCheckinService.php` |
| Progress History | `app/Services/GoalProgressService.php` |
| Reminders | `app/Services/GoalReminderService.php` |
| Templates | `app/Services/GoalTemplateService.php` |
| Deliverables | `app/Services/DeliverableService.php` |
| Impact Reporting | `app/Services/ImpactReportingService.php` |
| Community Dashboard | `app/Services/CommunityDashboardService.php` |
| Community Projects | `app/Services/CommunityProjectService.php` |
| Community Fund | `app/Services/CommunityFundService.php` |
| Goals Controller | `app/Http/Controllers/Api/GoalsController.php` |
| Deliverable Controller | `app/Http/Controllers/Api/DeliverableController.php` |
| Admin Goals | `app/Http/Controllers/Api/AdminGoalsController.php` |
| Admin Impact | `app/Http/Controllers/Api/AdminImpactReportController.php` |

---

## 3. Goals CRUD

### Goal Fields

| Field | Type | Description |
|-------|------|-------------|
| `title` | varchar(255) | Goal title (required) |
| `description` | text | Goal description |
| `target_value` | decimal(10,2) | Target to reach (default 0) |
| `current_value` | decimal(10,2) | Current progress (default 0) |
| `deadline` | date | Optional deadline |
| `is_public` | boolean | Visible to community (default false) |
| `status` | enum | `active`, `completed`, `achieved`, `abandoned` |
| `mentor_id` | FK | Buddy/mentor user |
| `buddy_id` | FK | Alternate buddy field |
| `checkin_frequency` | enum | `none`, `weekly`, `biweekly` |
| `template_id` | FK | Source template (if created from template) |
| `completed_at` | timestamp | Completion timestamp |

### Operations

| Method | Description |
|--------|-------------|
| `create(userId, data)` | Create goal with status='active' |
| `update(id, userId, data)` | Owner-only update (title, description, deadline, is_public, status) |
| `delete(id, userId)` | Owner-only delete |
| `getAll(filters)` | Cursor-paginated list with user_id, status, visibility filters |
| `getById(id)` | Single goal with user and mentor relationships |

### Visibility Rules

- **Public goals** (`is_public=true`): visible in community discover tab, open for buddy offers
- **Private goals** (`is_public=false`): visible only to owner (and mentor if assigned)
- Default for `getAll()` without `user_id` filter: shows public goals only

---

## 4. Goal Progress & Completion

### Increment Progress

```
GoalService::incrementProgress(id, userId, increment) → Goal
```

- Adds `increment` to `current_value`
- **Auto-completion:** if `current_value >= target_value`, sets status to `completed`
- Owner-only authorization

### Mark Complete

```
GoalService::complete(id, userId) → Goal
```

- Sets `current_value = target_value` (defaults to 1 if target is 0)
- Sets `status = 'completed'`
- **Gamification:** Awards XP for `complete_goal` action
- **Notifications:** Notifies owner and mentor of completion

### Frontend Celebration

When a goal reaches 100% and is marked complete:
- **Confetti animation:** 20 particles with random colors, rotation, position
- **PartyPopper icon** animation
- **1.2-second** fade-out duration
- Progress bar turns emerald green
- Card gets emerald-500 left border

---

## 5. Check-Ins

Periodic progress reflections with mood tracking.

### Check-In Fields

| Field | Type | Description |
|-------|------|-------------|
| `goal_id` | FK | Parent goal |
| `user_id` | FK | Check-in author |
| `progress_percent` | decimal(5,2) | Progress at check-in time (0-100%) |
| `note` | text | Reflection text |
| `mood` | enum | Emotional state |

### 6 Mood Options

| Mood | Icon | Color |
|------|------|-------|
| `great` | Star | amber-400 |
| `good` | Smile | emerald-400 |
| `okay` | Meh | blue-400 |
| `struggling` | Frown | orange-400 |
| `motivated` | Zap | purple-400 |
| `grateful` | Heart | rose-400 |

### Check-In UI

The check-in modal has two tabs:
1. **New Check-in:** Progress slider (0-100%, step 5), mood selector (toggle), note textarea
2. **History:** Scrollable list of past check-ins with progress, mood icon, note, relative time

### Mentor Notification

When a check-in is created and the goal has a mentor, the mentor is notified.

---

## 6. Buddy/Mentor System

Community members can volunteer as mentors for public goals.

### Offer to Buddy

```
GoalService::offerBuddy(goalId, userId) → Goal
```

**Validation:**
- Goal must be public (`is_public=true`)
- Goal must not already have a mentor
- User must not be the goal owner

**On success:**
- Sets `goal.mentor_id = userId`
- Notifies goal owner that a buddy joined
- Goal appears in buddy's "Buddying" tab

### Discovery

```
GoalService::getPublicForBuddy(userId, filters) → { items, cursor, has_more }
```

Lists public, active goals without a mentor (excludes user's own goals).

### Mentoring View

```
GoalService::getGoalsAsMentor(userId, filters) → { items, cursor, has_more }
```

Lists all goals where the user is the assigned mentor/buddy.

---

## 7. Goal Templates

Pre-built goal definitions organized by category.

### Template Fields

| Field | Type | Description |
|-------|------|-------------|
| `title` | varchar(255) | Template name |
| `description` | text | Template description |
| `category` | varchar(100) | Category (health, fitness, learning, social, community, financial, personal) |
| `default_target_value` | decimal(10,2) | Default target |
| `default_milestones` | JSON | Array of milestone objects [{title, target_value}] |
| `is_public` | boolean | Visible to all users |
| `created_by` | FK | Creator (admin) |

### Template Operations

| Method | Description |
|--------|-------------|
| `getAll(filters)` | List public templates with optional category filter |
| `getCategories()` | Distinct category values sorted alphabetically |
| `create(userId, data)` | Create template (admin only) |
| `createGoalFromTemplate(templateId, userId, overrides)` | Instantiate goal from template |

### Category Color Mapping (Frontend)

| Category | Gradient |
|----------|----------|
| health | emerald → green |
| fitness | orange → red |
| learning | blue → indigo |
| social | purple → pink |
| community | amber → orange |
| financial | yellow → amber |
| personal | indigo → purple |

---

## 8. Goal Reminders

### Reminder Configuration

| Field | Type | Description |
|-------|------|-------------|
| `goal_id` | FK | Target goal |
| `user_id` | FK | Reminder recipient |
| `frequency` | enum | `daily`, `weekly`, `biweekly`, `monthly` |
| `enabled` | boolean | Active/paused |
| `next_reminder_at` | datetime | Next scheduled fire |
| `last_sent_at` | datetime | Last sent |

### Operations

| Method | Description |
|--------|-------------|
| `getReminder(goalId, userId)` | Get current settings |
| `setReminder(goalId, userId, data)` | Create or update (upsert) |
| `deleteReminder(goalId, userId)` | Remove reminder |
| `sendDueReminders()` | Cron stub (not yet implemented) |

### Frontend UI

Bell icon button on each goal card:
- **Active:** BellRing icon with indigo background
- **Inactive:** Bell icon with muted background
- Click opens popover with 4 frequency options + delete button
- Checkmark on currently selected frequency

---

## 9. Progress History & Timeline

### Event Types

| Event Type | Description | Color |
|------------|-------------|-------|
| `created` | Goal created | gray |
| `progress_update` | Progress incremented | blue |
| `checkin` | Check-in recorded | indigo |
| `milestone_reached` | Milestone hit | amber |
| `buddy_joined` | Mentor/buddy assigned | purple |
| `completed` | Goal completed | emerald |
| `status_change` | Status changed | — |

### Progress History Table

| Field | Type | Description |
|-------|------|-------------|
| `goal_id` | FK | Parent goal |
| `event_type` | enum | One of 7 types |
| `old_value` | varchar(255) | Previous value |
| `new_value` | varchar(255) | New value |
| `metadata` | JSON | Additional event data |
| `created_by` | FK | Actor |

### Timeline UI

Vertical timeline with left border:
- Colored dots per event type
- Event icon nested in dot
- Chip with event type label
- Mood icon (if check-in)
- Progress bar (if has progress_value)
- Note quote (if available)
- Relative timestamp

---

## 10. Deliverables (Task Management)

### Deliverable Fields

| Field | Type | Description |
|-------|------|-------------|
| `title` | varchar(255) | Task title (required) |
| `description` | text | Full description |
| `category` | varchar(100) | Category (default 'general') |
| `priority` | enum | `low`, `medium`, `high`, `urgent` |
| `status` | enum | `draft`, `ready`, `in_progress`, `blocked`, `review`, `completed`, `cancelled`, `on_hold` |
| `owner_id` | FK | Creator/owner |
| `assigned_to` | FK | Assigned user |
| `assigned_group_id` | FK | Assigned group |
| `parent_deliverable_id` | FK | Parent task (hierarchy) |
| `start_date` / `due_date` | datetime | Schedule |
| `completed_at` | datetime | Completion timestamp |
| `progress_percentage` | decimal(5,2) | 0-100% progress |
| `estimated_hours` / `actual_hours` | decimal(8,2) | Time tracking |
| `delivery_confidence` | enum | `low`, `medium`, `high` |
| `risk_level` | enum | `low`, `medium`, `high`, `critical` |
| `risk_notes` | text | Risk description |
| `tags` | JSON | Tag array |
| `custom_fields` | JSON | Arbitrary metadata |
| `blocking_deliverable_ids` | JSON | IDs this blocks |
| `depends_on_deliverable_ids` | JSON | Dependency chain |
| `watchers` | JSON | User IDs watching |
| `collaborators` | JSON | User IDs collaborating |
| `attachment_urls` | JSON | File attachments |
| `external_links` | JSON | External references |

### Self-Referential Hierarchy

Deliverables support parent/child relationships:
- `parent_deliverable_id` → creates tree structure
- `children()` HasMany relationship for sub-tasks
- CASCADE delete on parent removal

### Deliverable Operations

| Method | Description |
|--------|-------------|
| `getAll(tenantId, filters)` | Offset-paginated list with status/project filter |
| `getById(id, tenantId)` | Single deliverable |
| `create(tenantId, data)` | Create task (title required) |
| `update(id, tenantId, data)` | Update fields |
| `addComment(deliverableId, userId, body)` | Add comment with notification |

---

## 11. Deliverable Comments & Milestones

### Comments

Threaded discussion system on deliverables:

| Field | Type | Description |
|-------|------|-------------|
| `comment_text` | text | Comment body |
| `comment_type` | enum | `general`, `blocker`, `question`, `update`, `resolution` |
| `parent_comment_id` | FK | Thread parent (self-referential) |
| `mentioned_user_ids` | JSON | @mentioned users |
| `reactions` | JSON | Emoji reactions |
| `is_pinned` | boolean | Pinned to top |
| `is_edited` / `edited_at` | boolean/datetime | Edit tracking |
| `is_deleted` / `deleted_at` | boolean/datetime | Soft delete |

### Milestones

Ordered sub-tasks within a deliverable:

| Field | Type | Description |
|-------|------|-------------|
| `title` | varchar(255) | Milestone name |
| `description` | text | Description |
| `order_position` | int | Display order |
| `status` | enum | `pending`, `in_progress`, `completed`, `skipped` |
| `due_date` | datetime | Deadline |
| `estimated_hours` | decimal(8,2) | Time estimate |
| `completed_at` / `completed_by` | datetime/FK | Completion tracking |
| `depends_on_milestone_ids` | JSON | Dependency chain |

### Deliverable History (Audit)

Full audit trail with 14 action types:

`created`, `status_changed`, `assigned`, `reassigned`, `progress_updated`, `deadline_changed`, `priority_changed`, `milestone_completed`, `commented`, `attachment_added`, `completed`, `cancelled`, `reopened`, `metadata_updated`

Each entry records: old_value, new_value, field_name, change_description, IP address, user agent.

---

## 12. Group Tasks

Collaborative task management within community groups.

### Task Structure

```typescript
interface Task {
  id, group_id, title, description
  status: 'todo' | 'in_progress' | 'done'
  priority: 'low' | 'medium' | 'high' | 'urgent'
  assigned_to: number | null
  due_date: string | null
  assignee?: { id, name, avatar_url }
}
```

### Features

- **Stats bar:** Total, Done (green), In Progress (amber), Overdue (red)
- **Status filter:** All / todo / in_progress / done
- **Status cycling:** Click toggles todo → in_progress → done → todo
- **Task cards:** Title (strikethrough if done), priority chip, assignee avatar, due date (red if overdue)
- **Create modal:** Title, description, status, priority, assignee (from group members), due date

### API

| Method | Endpoint | Description |
|--------|----------|-------------|
| GET | `/v2/groups/{id}/tasks` | List tasks |
| GET | `/v2/groups/{id}/tasks/stats` | Task statistics |
| POST | `/v2/groups/{id}/tasks` | Create task |
| PUT | `/v2/team-tasks/{id}` | Update task |
| DELETE | `/v2/team-tasks/{id}` | Delete task |

---

## 13. Impact Reporting & SROI

### Social Return on Investment

```
ImpactReportingService::calculateSROI(config) → {
    total_hours: float,
    total_transactions: int,
    unique_givers: int,
    unique_receivers: int,
    hourly_value: float,           // default $15.00
    monetary_value: float,         // total_hours × hourly_value
    social_multiplier: float,      // default 3.5×
    social_value: float,           // monetary_value × social_multiplier
    sroi_ratio: float,             // social_value / monetary_value
    period_months: int
}
```

**Calculation:**
1. Sum all completed transaction amounts as hours
2. `monetary_value = total_hours × hourly_value`
3. `social_value = monetary_value × social_multiplier`
4. `sroi_ratio = social_value / monetary_value`

**Configurable per tenant:**
- `hourly_value`: 0–1000 (default $15.00 USD)
- `social_multiplier`: 0–100 (default 3.5×)
- `period_months`: default 12

### Impact Timeline

```
ImpactReportingService::getImpactTimeline(months=12) → [
    { month: '2026-03', hours_exchanged: 45.5, transactions: 23, new_users: 8 },
    ...
]
```

Monthly breakdown of community activity over configurable timeframe.

---

## 14. Community Health Metrics

```
ImpactReportingService::getCommunityHealthMetrics() → {
    total_users: int,
    active_users_90d: int,
    new_users_30d: int,
    active_traders_30d: int,
    engagement_rate: float,      // active_traders / total_users
    retention_rate: float,       // active_90d / total_users
    reciprocity_score: float,    // 1 - avg(|given - received| / (given + received))
    activation_rate: float,      // new members who traded / new_30d
    network_density: float,      // connections / (n×(n-1)/2)
    total_connections: int
}
```

### Metric Definitions

| Metric | Formula | What It Measures |
|--------|---------|------------------|
| **Engagement Rate** | active_traders / total_users | Proportion actively trading |
| **Retention Rate** | active_90d / total_users | 90-day retention |
| **Reciprocity Score** | 1 - avg imbalance ratio | Balance of giving/receiving (1.0 = perfect) |
| **Activation Rate** | new traders / new members (30d) | New member conversion |
| **Network Density** | actual / possible connections | How connected the community is |

---

## 15. Community Dashboard

### Community Impact Stats

```
CommunityDashboardService::getCommunityImpact() → {
    total_members, total_xp, total_badges_awarded,
    total_volunteer_hours, total_listings, total_connections,
    total_exchanges, total_reviews,
    this_month: { new_members, badges_awarded, new_listings, new_connections, volunteer_hours, new_posts },
    last_month: { ... },
    trends: { new_members: +15.3%, badges_awarded: -2.1%, ... }
}
```

Trends calculated as `((current - previous) / previous) × 100`.

### Personal Journey

```
CommunityDashboardService::getPersonalJourney(tenantId, userId) → {
    monthly_activity: [{ month, badges, xp_earned }],     // 12-month timeline
    badge_progression: [{ badge_key, name, icon, earned_at }],
    milestones: [{ type, label, date }],
    summary: { xp, level, level_name, total_badges, total_listings, volunteer_hours, connections, reviews, member_since }
}
```

**Milestone types:**
- `first_badge`: First earned badge
- `badge_milestone`: 5, 10, 25, 50, 100 badges
- `xp_milestone`: 100, 500, 1K, 5K, 10K, 50K XP
- `first_listing`: First service listing
- `volunteer`: Volunteer hours logged

### Member Spotlight

```
CommunityDashboardService::getMemberSpotlight(tenantId, limit=3) → [
    { id, first_name, last_name, avatar_url, bio, member_since, level, xp, recent_activity }
]
```

Daily-rotating random selection (seeded by date for deterministic daily rotation). Members must have xp > 0 OR earned badges.

---

## 16. Community Projects

Member-proposed volunteer project ideas with community voting.

### Project Statuses

```
proposed → under_review → approved → active → completed
                       → rejected
                                  → cancelled
```

### Key Methods

| Method | Description |
|--------|-------------|
| `propose(userId, data)` | Create project proposal |
| `getProposals(filters)` | Cursor-paginated list with search, sort, status, category |
| `updateProposal(id, userId, data)` | Update (proposer or admin) |
| `review(id, status, feedback, adminId)` | Admin approve/reject/review |
| `support(id, userId)` | Add community support vote (INSERT IGNORE) |
| `unsupport(id, userId)` | Remove support vote |

### Project Fields

| Field | Type | Description |
|-------|------|-------------|
| `title` | varchar(255) | Project name |
| `description` | text | Full description |
| `category` | varchar(100) | Project category |
| `location` | varchar(255) | With lat/lng coordinates |
| `target_volunteers` | int | Target volunteer count |
| `proposed_date` | date | Proposed start date |
| `skills_needed` | JSON | Required skills |
| `estimated_hours` | decimal(5,1) | Time estimate |
| `supporter_count` | int | Community support count |
| `opportunity_id` | FK | Linked volunteer opportunity (when approved) |

---

## 17. Community Fund

Shared credit pool managed by admins. (Covered in detail in the Timebanking Engine Report — summary here.)

| Operation | Actor | Description |
|-----------|-------|-------------|
| `adminDeposit` | Admin | Deposit credits into fund |
| `adminWithdraw` | Admin | Grant credits from fund to member |
| `receiveDonation` | Any member | Donate personal credits to fund |
| `getBalance` | Any | View fund balance and stats |
| `getTransactions` | Any | Paginated transaction history |

All operations use `DB::transaction()` with row locking for atomicity.

---

## 18. Database Schema

### Goals Tables

#### `goals` (17 columns)

| Column | Type | Key |
|--------|------|-----|
| `id` | int PK | AUTO_INCREMENT |
| `tenant_id` | int | INDEX |
| `user_id` | int | INDEX |
| `mentor_id` | int | INDEX, nullable |
| `title` | varchar(255) | |
| `description` | text | nullable |
| `deadline` | date | nullable |
| `is_public` | tinyint(1) | default 0 |
| `status` | enum(active,completed,achieved,abandoned) | default 'active' |
| `buddy_id` | int | nullable |
| `completed_at` | timestamp | nullable |
| `current_value` | decimal(10,2) | default 0.00 |
| `target_value` | decimal(10,2) | default 0.00 |
| `checkin_frequency` | enum(none,weekly,biweekly) | default 'none' |
| `last_checkin_at` | datetime | nullable |
| `template_id` | int unsigned | nullable |
| `created_at` | timestamp | CURRENT_TIMESTAMP |

#### `goal_checkins` (8 columns)

| Column | Type | Key |
|--------|------|-----|
| `id` | int unsigned PK | |
| `goal_id` | int unsigned | INDEX, idx_goal_checkins_created |
| `user_id` | int unsigned | INDEX |
| `tenant_id` | int unsigned | INDEX |
| `progress_percent` | decimal(5,2) | nullable |
| `note` | text | nullable |
| `mood` | enum(great,good,neutral,okay,struggling,stuck,motivated,grateful) | nullable |
| `created_at` | datetime | CURRENT_TIMESTAMP |

#### `goal_progress_log` (9 columns)

| Column | Type | Key |
|--------|------|-----|
| `id` | int unsigned PK | |
| `goal_id` | int unsigned | idx_goal_progress_log_goal |
| `tenant_id` | int unsigned | INDEX |
| `event_type` | enum(progress_update,milestone_reached,checkin,status_change,buddy_joined,created,completed) | |
| `old_value` | varchar(255) | nullable |
| `new_value` | varchar(255) | nullable |
| `metadata` | JSON | nullable, CHECK json_valid |
| `created_by` | int unsigned | nullable |
| `created_at` | datetime | CURRENT_TIMESTAMP |

#### `goal_reminders` (10 columns)

| Column | Type | Key |
|--------|------|-----|
| `id` | int unsigned PK | |
| `goal_id` | int unsigned | UNIQUE(goal_id, user_id) |
| `user_id` | int unsigned | |
| `tenant_id` | int unsigned | INDEX |
| `frequency` | enum(daily,weekly,biweekly,monthly) | default 'weekly' |
| `next_reminder_at` | datetime | idx_goal_reminders_next |
| `last_sent_at` | datetime | nullable |
| `enabled` | tinyint(1) | default 1 |
| `created_at` | datetime | |
| `updated_at` | datetime | |

#### `goal_templates` (11 columns)

| Column | Type | Key |
|--------|------|-----|
| `id` | int unsigned PK | |
| `tenant_id` | int unsigned | INDEX, idx_goal_templates_category |
| `title` | varchar(255) | |
| `description` | text | nullable |
| `default_milestones` | JSON | nullable, CHECK json_valid |
| `category` | varchar(100) | nullable |
| `default_target_value` | decimal(10,2) | default 0.00 |
| `is_public` | tinyint(1) | default 1 |
| `created_by` | int unsigned | INDEX |
| `created_at` | datetime | |
| `updated_at` | datetime | |

### Deliverable Tables

#### `deliverables` (30 columns)

Comprehensive project management table with:
- Identity: id, tenant_id, title, description, category
- Ownership: owner_id (FK CASCADE), assigned_to (FK SET NULL), assigned_group_id (FK SET NULL)
- Schedule: start_date, due_date, completed_at
- Progress: status (8 values), progress_percentage, estimated_hours, actual_hours
- Risk: delivery_confidence, risk_level, risk_notes
- Dependencies: parent_deliverable_id (self-ref FK CASCADE), blocking_deliverable_ids (JSON), depends_on_deliverable_ids (JSON)
- Collaboration: watchers (JSON), collaborators (JSON)
- Attachments: attachment_urls (JSON), external_links (JSON), tags (JSON), custom_fields (JSON)

**11 indexes** including tenant_status, tenant_assigned, parent, priority, due_date.

#### `deliverable_comments` (16 columns)

Threaded comments with:
- Threading: parent_comment_id (self-ref FK CASCADE)
- Types: comment_type (5 values)
- Social: mentioned_user_ids (JSON), reactions (JSON)
- Moderation: is_pinned, is_edited, is_deleted with timestamps

#### `deliverable_milestones` (14 columns)

Ordered sub-tasks with:
- Ordering: order_position
- Status: pending/in_progress/completed/skipped
- Dependencies: depends_on_milestone_ids (JSON)
- Tracking: estimated_hours, completed_at, completed_by

#### `deliverable_history` (13 columns)

Full audit trail with:
- 14 action types
- Before/after values
- IP address and user agent tracking

---

## 19. API Reference

### Goal Endpoints (21 routes)

| Method | Endpoint | Description |
|--------|----------|-------------|
| GET | `/v2/goals` | List goals (cursor pagination, status/visibility filter) |
| POST | `/v2/goals` | Create goal (records feed activity if public) |
| GET | `/v2/goals/{id}` | Goal detail (privacy enforced) |
| PUT | `/v2/goals/{id}` | Update goal (owner-only) |
| DELETE | `/v2/goals/{id}` | Delete goal (owner-only) |
| POST | `/v2/goals/{id}/progress` | Increment progress (auto-completes, notifies mentor) |
| POST | `/v2/goals/{id}/complete` | Mark complete (awards XP, notifies) |
| POST | `/v2/goals/{id}/buddy` | Offer to become buddy (notifies owner) |
| GET | `/v2/goals/discover` | Discover public goals for buddy matching |
| GET | `/v2/goals/mentoring` | Goals where user is mentor |
| GET | `/v2/goals/{id}/checkins` | List check-ins (cursor pagination) |
| POST | `/v2/goals/{id}/checkins` | Create check-in (notifies mentor) |
| GET | `/v2/goals/{id}/history` | Progress history timeline |
| GET | `/v2/goals/{id}/history/summary` | Event type summary |
| GET | `/v2/goals/templates` | List templates (category filter) |
| GET | `/v2/goals/templates/categories` | Template categories |
| POST | `/v2/goals/templates` | Create template (admin) |
| POST | `/v2/goals/from-template/{id}` | Create goal from template |
| GET | `/v2/goals/{id}/reminder` | Get reminder settings |
| PUT | `/v2/goals/{id}/reminder` | Set/update reminder |
| DELETE | `/v2/goals/{id}/reminder` | Delete reminder |

### Deliverable Endpoints (5 routes)

| Method | Endpoint | Description |
|--------|----------|-------------|
| GET | `/v2/deliverables` | List deliverables (offset pagination) |
| GET | `/v2/deliverables/{id}` | Deliverable detail |
| POST | `/v2/deliverables` | Create deliverable |
| PUT | `/v2/deliverables/{id}` | Update deliverable |
| POST | `/v2/deliverables/{id}/comments` | Add comment (notifies owner) |

### Admin Endpoints (5 routes)

| Method | Endpoint | Description |
|--------|----------|-------------|
| GET | `/v2/admin/goals` | List all goals (search, pagination) |
| GET | `/v2/admin/goals/{id}` | Admin goal detail |
| DELETE | `/v2/admin/goals/{id}` | Admin delete goal |
| GET | `/v2/admin/impact-report` | Full SROI + health + timeline (configurable months) |
| PUT | `/v2/admin/impact-report/config` | Update SROI config (hourly_value, social_multiplier) |

---

## 20. Frontend Experience

### GoalsPage (`/goals`)

Three-tab interface:

**My Goals tab:**
- Personal goal list with progress bars (current/target)
- Status chips (completed/active), public/private badges
- Deadline with overdue detection (red border)
- Action buttons: +1 increment, Check-in, Reminder toggle, Mark Complete
- 3-dot menu: Edit, Delete
- Confetti celebration on completion
- "New Goal" button with create modal
- "From Template" button with template picker

**Buddying tab:**
- Goals where user is mentor/buddy
- Shows goal owner's avatar and name
- Progress tracking from buddy perspective

**Discover tab:**
- Public goals from community
- "Become Buddy" button (only for goals without buddy, not user's own)
- Community pool of goals seeking mentors

### Goal Components

| Component | Purpose |
|-----------|---------|
| **GoalCard** | Displays goal with progress, status, actions |
| **GoalCheckinModal** | Check-in with progress slider, mood selector, note |
| **GoalProgressHistory** | Vertical timeline of all goal events |
| **GoalReminderToggle** | Bell icon with frequency popover |
| **GoalTemplatePickerModal** | Browse/filter templates by category, create goal |
| **ConfettiCelebration** | 20-particle completion animation |

### ImpactReportPage (`/about/impact-report`)

Long-form impact study with:
- Interactive table of contents (scroll spy)
- 7 sections: Introduction, Literature Review, Activity Data, Impact Demographics, SROI Calculation, Discussion, Recommendations
- PDF downloads (full report + executive summary)
- Case studies

### CommunityImpactTab (Leaderboard)

Dashboard showing:
- Primary stats: Total Members, Badges Awarded, Volunteer Hours, Total XP
- Secondary stats: Listings, Connections, Reviews
- This Month breakdown with trend indicators (% change vs last month)

### GroupTasksTab (Group Detail)

Task board within community groups using TeamTasks component:
- Status filtering (todo/in_progress/done)
- Stats bar with overdue count
- Create modal with assignee selection from group members
- Status cycling via click

### Dashboard Integration

Goals appear in user's activity feed alongside listings, events, and posts.

---

## 21. Admin Panel

### Goal Administration

**GoalsAdmin** (`src/admin/modules/goals/GoalsAdmin.tsx`):

| Feature | Description |
|---------|-------------|
| Search | By title or member name |
| Table columns | Title, Member, Target, Progress (bar), Status, Has Buddy, Created, Actions |
| Pagination | 50 items/page |
| Actions | View (opens in new tab), Delete (confirmation modal) |

### Impact Report Administration

| Capability | Endpoint | Description |
|-----------|----------|-------------|
| View full report | `GET /v2/admin/impact-report` | SROI + health metrics + timeline |
| Configure SROI | `PUT /v2/admin/impact-report/config` | Set hourly_value (0-1000) and social_multiplier (0-100) |

### Admin Deliverables

Admin module exists at `src/admin/modules/deliverability/` with:
- `DeliverabilityDashboard.tsx` — Overview dashboard
- `DeliverablesList.tsx` — List management
- `CreateDeliverable.tsx` — Creation form
- `DeliverabilityAnalytics.tsx` — Analytics dashboard

---

*This report documents the complete goals & impact module engine as implemented in Project NEXUS v1.5.0. For the most current implementation, refer to the source files listed in Section 2.*
