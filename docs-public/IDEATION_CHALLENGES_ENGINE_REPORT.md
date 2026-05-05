# Project NEXUS — Ideation Challenges Engine: Complete Technical Report

**Generated:** 2026-03-29
**Version:** 1.5.0
**License:** AGPL-3.0-or-later

---

## Table of Contents

1. [Executive Summary](#1-executive-summary)
2. [System Architecture](#2-system-architecture)
3. [Challenge Lifecycle](#3-challenge-lifecycle)
4. [Idea Submissions](#4-idea-submissions)
5. [Voting System](#5-voting-system)
6. [Comments & Discussion](#6-comments--discussion)
7. [Idea Media Attachments](#7-idea-media-attachments)
8. [Idea-to-Team Conversion](#8-idea-to-team-conversion)
9. [Campaigns](#9-campaigns)
10. [Categories & Tags](#10-categories--tags)
11. [Challenge Templates](#11-challenge-templates)
12. [Outcome Tracking](#12-outcome-tracking)
13. [Favorites & Engagement](#13-favorites--engagement)
14. [Team Workspace](#14-team-workspace)
15. [Gamification Challenges](#15-gamification-challenges)
16. [Database Schema](#16-database-schema)
17. [API Reference](#17-api-reference)
18. [Frontend Experience](#18-frontend-experience)
19. [Admin Panel](#19-admin-panel)

---

## 1. Executive Summary

The Ideation Challenges Engine is a structured innovation platform where community members propose ideas to solve challenges, vote on the best solutions, and convert winning ideas into actionable teams with collaboration tools. It follows the full innovation lifecycle: challenge creation → idea submission → community voting → evaluation → winner selection → team formation → outcome tracking.

### What It Includes

| System | Purpose |
|--------|---------|
| **Challenges** | Admin-created problems/opportunities with 6-stage lifecycle |
| **Ideas** | Member-submitted solutions with 5-stage status tracking |
| **Voting** | Toggle-based community voting with validation rules |
| **Comments** | Threaded discussion on ideas |
| **Media** | Rich attachments (image, video, document, link) on ideas |
| **Team Conversion** | Convert winning ideas into groups with full workspace |
| **Campaigns** | Bundle related challenges under themed campaigns |
| **Categories** | Hierarchical taxonomy with icons and colors |
| **Tags** | Flexible tagging (interest, skill, general) with popularity tracking |
| **Templates** | Reusable challenge blueprints with pre-configured fields |
| **Outcomes** | Track implementation status of winning ideas |
| **Favorites** | Challenge bookmarking with count tracking |
| **Team Workspace** | Chatrooms, tasks, and documents for converted idea teams |
| **Gamification Challenges** | Separate system for XP-based action challenges |

### Key Metrics

| Metric | Value |
|--------|-------|
| Backend services | 8 dedicated ideation services + 1 gamification challenge service |
| API endpoints | 54+ routes across 2 controllers |
| Database tables | 15+ ideation/challenge tables |
| Models | 10 Eloquent models |
| Challenge statuses | 6 (draft → archived) |
| Idea statuses | 5 (draft → withdrawn) |
| Outcome statuses | 4 (not_started → abandoned) |
| Media types | 4 (image, video, document, link) |
| Frontend pages | 5 + 3 team workspace components + admin module |

---

## 2. System Architecture

### Service Map

```
┌───────────────────────────────────────────────────────────────────────────┐
│                    IDEATION CHALLENGES ENGINE                              │
│                                                                           │
│  ┌─────────────────────┐  ┌─────────────────────┐  ┌──────────────────┐  │
│  │   CHALLENGES          │  │   IDEAS               │  │   ORGANIZATION   │  │
│  │                       │  │                       │  │                  │  │
│  │ IdeationChallengeSvc  │  │ IdeaMediaSvc          │  │ ChallengeCatSvc  │  │
│  │  - CRUD + lifecycle   │  │ IdeaTeamConversionSvc │  │ ChallengeTagSvc  │  │
│  │  - Ideas + votes      │  │                       │  │ ChallengeTemplSvc│  │
│  │  - Comments           │  │                       │  │ ChallengeOutcomeSvc│ │
│  │  - Favorites          │  │                       │  │                  │  │
│  └───────────┬───────────┘  └───────────┬───────────┘  └────────┬─────────┘  │
│              │                          │                        │            │
│  ┌───────────▼──────────────────────────▼────────────────────────▼─────────┐  │
│  │                    TEAM WORKSPACE (Post-Conversion)                      │  │
│  │  Chatrooms · Tasks · Documents (via Group infrastructure)               │  │
│  └─────────────────────────────────────────────────────────────────────────┘  │
└───────────────────────────────────────────────────────────────────────────┘
```

### Key Files

| Component | File |
|-----------|------|
| Core Challenge+Ideas+Votes+Comments | `app/Services/IdeationChallengeService.php` |
| Categories | `app/Services/ChallengeCategoryService.php` |
| Tags | `app/Services/ChallengeTagService.php` |
| Templates | `app/Services/ChallengeTemplateService.php` |
| Outcomes | `app/Services/ChallengeOutcomeService.php` |
| Media | `app/Services/IdeaMediaService.php` |
| Team Conversion | `app/Services/IdeaTeamConversionService.php` |
| Gamification Challenges | `app/Services/ChallengeService.php` |
| Main Controller | `app/Http/Controllers/Api/IdeationChallengesController.php` |
| Admin Controller | `app/Http/Controllers/Api/AdminIdeationController.php` |

---

## 3. Challenge Lifecycle

### 6-Stage Status Machine

```
draft → open → voting → evaluating → closed → archived
                                              ↗
              open ←──── closed ─────────────┘
```

| Status | Description | Who Can Transition |
|--------|-------------|-------------------|
| `draft` | Not yet published | Admin → open |
| `open` | Accepting idea submissions | Admin → voting, evaluating, closed |
| `voting` | Community voting phase | Admin → evaluating, closed |
| `evaluating` | Admin review of top ideas | Admin → closed |
| `closed` | Challenge complete, winners selected | Admin → open (reopen), archived |
| `archived` | Historical record | Admin → closed (unarchive) |

### Valid Transitions

| From | Allowed To |
|------|-----------|
| draft | open |
| open | voting, evaluating, closed |
| voting | evaluating, closed |
| evaluating | closed |
| closed | open, archived |
| archived | closed |

### Challenge Fields

| Field | Type | Description |
|-------|------|-------------|
| `title` | varchar(255) | Challenge title (required) |
| `description` | text | Full description |
| `category` | varchar(100) | Legacy category string |
| `category_id` | FK | Structured category reference |
| `status` | enum | 6 statuses |
| `submission_deadline` | datetime | Idea submission cutoff |
| `voting_deadline` | datetime | Voting phase end |
| `cover_image` | varchar(500) | Cover image URL |
| `prize_description` | text | Prize/reward description |
| `max_ideas_per_user` | int | Submission cap per user |
| `tags` | JSON | Array of tag strings |
| `evaluation_criteria` | JSON | Array of criteria strings |
| `is_featured` | boolean | Featured promotion flag |
| `ideas_count` | int | Cached idea count |
| `views_count` | int | View counter |
| `favorites_count` | int | Favorite/bookmark count |

### Challenge Operations

| Method | Description |
|--------|-------------|
| `create(userId, data)` | Create challenge with status='open' |
| `updateChallenge(id, userId, data)` | Admin-only field update |
| `deleteChallenge(id, userId)` | Admin-only deletion |
| `updateChallengeStatus(id, userId, status)` | Status transition with validation |
| `duplicateChallenge(id, userId)` | Clone as draft with '[Copy]' prefix |

---

## 4. Idea Submissions

### 5-Stage Idea Status

```
draft → submitted → shortlisted → winner
                                → withdrawn
```

| Status | Description |
|--------|-------------|
| `draft` | Work in progress, not visible to others |
| `submitted` | Published and visible in challenge |
| `shortlisted` | Admin-selected finalist |
| `winner` | Winning idea |
| `withdrawn` | Removed by owner or admin |

### Idea Fields

| Field | Type | Description |
|-------|------|-------------|
| `challenge_id` | FK | Parent challenge |
| `user_id` | FK | Idea author |
| `title` | varchar(255) | Idea title |
| `description` | text | Full description |
| `votes_count` | int | Cached vote count |
| `comments_count` | int | Cached comment count |
| `status` | enum | 5 statuses |
| `image_url` | varchar(500) | Cover image |

### Idea Operations

| Method | Description |
|--------|-------------|
| `submitIdea(challengeId, userId, data)` | Submit new idea |
| `updateIdea(id, userId, data)` | Owner-only update (challenge must be open) |
| `updateDraftIdea(ideaId, userId, data)` | Update draft, optionally publish |
| `deleteIdea(id, userId)` | Owner or admin, decrements challenge ideas_count |
| `updateIdeaStatus(ideaId, userId, status)` | Admin-only: submitted, shortlisted, winner, withdrawn |
| `getUserDrafts(challengeId, userId)` | List user's draft ideas |

### Draft System

Ideas can be saved as drafts before publishing:
- Created with `status='draft'`
- Updated via `updateDraftIdea()` without visibility
- Published by setting `publish=true` flag → transitions to `submitted`
- Publishing requires both title and description
- Increments challenge `ideas_count` on publish

---

## 5. Voting System

### Toggle Vote

```
IdeationChallengeService::voteIdea(ideaId, userId) → { voted: bool, votes_count: int }
```

**Validation rules:**
- Cannot vote on withdrawn or draft ideas
- Challenge must be in `open` or `voting` status
- Cannot vote on own idea
- Toggle: if vote exists, removes it; otherwise creates it

**Atomicity:**
- Wrapped in `DB::transaction()`
- Updates `challenge_ideas.votes_count` after insert/delete
- Uses UNIQUE constraint `(idea_id, user_id)` for idempotency

### Vote Storage

`challenge_idea_votes` table:
- `idea_id` (FK → challenge_ideas, CASCADE)
- `user_id`
- UNIQUE constraint prevents double voting

---

## 6. Comments & Discussion

### Comment Operations

| Method | Description |
|--------|-------------|
| `getComments(ideaId, filters)` | Cursor-paginated comments with author info |
| `addComment(ideaId, userId, body)` | Add comment (not on withdrawn/draft ideas) |
| `deleteComment(commentId, userId)` | Owner or admin, decrements comments_count |

### Comment Fields

| Field | Type | Description |
|-------|------|-------------|
| `idea_id` | FK | Parent idea (CASCADE) |
| `user_id` | FK | Comment author |
| `body` | text | Comment text |

### Validation

- Cannot comment on withdrawn or draft ideas
- Comment body required
- Deletion decrements `challenge_ideas.comments_count` atomically

---

## 7. Idea Media Attachments

### 4 Media Types

| Type | Description |
|------|-------------|
| `image` | Photo/graphic (default) |
| `video` | Video content |
| `document` | PDF, DOC, etc. |
| `link` | External URL |

### Media Operations

| Method | Description |
|--------|-------------|
| `getMediaForIdea(ideaId)` | List all media ordered by sort_order |
| `addMedia(ideaId, userId, data)` | Add attachment (author or admin only) |
| `deleteMedia(mediaId, userId)` | Remove attachment (author or admin only) |

### Media Fields

| Field | Type | Description |
|-------|------|-------------|
| `idea_id` | FK | Parent idea (CASCADE) |
| `media_type` | enum | image, video, document, link |
| `url` | varchar(1000) | Media URL (required) |
| `caption` | varchar(500) | Optional caption |
| `sort_order` | int | Display order |

### Frontend Upload

ChallengeDetailPage supports adding up to **5 media items** per idea submission, with type selection and URL input for each.

---

## 8. Idea-to-Team Conversion

The flagship feature: converting winning/shortlisted ideas into actionable community groups.

### Conversion Flow

```
Idea (shortlisted/winner) → Convert button clicked
  → Group created with:
    - source_idea_id and source_challenge_id
    - Converter added as admin member
    - Idea author added as regular member
    - Author notified (if different from converter)
  → Team workspace activated:
    - Chatrooms for discussion
    - Tasks for project management
    - Documents for file sharing
```

### Convert Method

```
IdeaTeamConversionService::convert(ideaId, userId, options) → {
    id, idea_id, group_id, challenge_id,
    converted_by, converted_at,
    group: { id, name, description, ... }
}
```

**Authorization:** Idea author, challenge creator, or admin.

**Options:**
- `name`: Group name (defaults to idea title)
- `description`: Group description (defaults to idea description)
- `visibility`: public (default), private, or secret

**Validation:**
- Idea must not already be converted (checks existing link)
- Creates group record with `source_idea_id` and `source_challenge_id`
- Wrapped in `DB::transaction()`

### Link Tracking

`idea_team_links` table tracks conversions:
- `idea_id` → `group_id` mapping
- `challenge_id` for context
- `converted_by` and `converted_at` for audit
- UNIQUE constraint on `(idea_id, group_id)`

### Query Methods

| Method | Description |
|--------|-------------|
| `getLinksForChallenge(challengeId)` | All team links for a challenge with member counts |
| `getLinkForIdea(ideaId)` | Single idea's team link |

---

## 9. Campaigns

Bundle related challenges under themed campaigns.

### Campaign Operations

| Method | Endpoint | Description |
|--------|----------|-------------|
| GET | `/v2/ideation-campaigns` | List all campaigns |
| GET | `/v2/ideation-campaigns/{id}` | Campaign detail |
| POST | `/v2/ideation-campaigns` | Create campaign |
| PUT | `/v2/ideation-campaigns/{id}` | Update campaign |
| DELETE | `/v2/ideation-campaigns/{id}` | Delete campaign |
| POST | `/v2/ideation-campaigns/{id}/challenges` | Link challenge to campaign |
| DELETE | `/v2/ideation-campaigns/{id}/challenges/{challengeId}` | Unlink challenge |

### Campaign-Challenge Relationship

Many-to-many via `campaign_challenges` junction table. A campaign can contain multiple challenges, and a challenge can belong to multiple campaigns.

---

## 10. Categories & Tags

### Categories

Structured taxonomy with visual elements:

| Field | Type | Description |
|-------|------|-------------|
| `name` | varchar(100) | Category name |
| `slug` | varchar(100) | URL-safe slug (auto-generated) |
| `icon` | varchar(50) | Lucide icon name (e.g., Leaf, Cpu, Heart) |
| `color` | varchar(20) | Tailwind color (e.g., blue, green, amber) |
| `sort_order` | int | Display order |

UNIQUE constraint on `(tenant_id, slug)`.

**Operations:** CRUD for admins, list for all users.

### Tags

Flexible labeling system with 3 types:

| Type | Description |
|------|-------------|
| `interest` | Interest-based tags |
| `skill` | Skill-based tags |
| `general` | General-purpose tags (default) |

**Popular tags:** `getAllTags()` returns tags grouped by usage count, descending.

**Challenge-tag junction:** `challenge_tag_links` (many-to-many, CASCADE on both FKs).

---

## 11. Challenge Templates

Reusable blueprints for creating challenges.

### Template Fields

| Field | Type | Description |
|-------|------|-------------|
| `title` | varchar(255) | Template name |
| `description` | text | Template description |
| `default_tags` | JSON | Pre-selected tags |
| `default_category_id` | FK | Pre-selected category |
| `evaluation_criteria` | JSON | Pre-defined criteria |
| `prize_description` | text | Prize template |
| `max_ideas_per_user` | int | Submission cap |
| `created_by` | FK | Creator (admin) |

### Template Operations

| Method | Description |
|--------|-------------|
| `getAll()` | List templates with creator and category info |
| `create(userId, data)` | Admin-only creation |
| `update(id, userId, data)` | Admin-only update |
| `delete(id, userId)` | Admin-only deletion |
| `getTemplateData(id)` | Get pre-filled data for challenge creation |

### Usage Flow

1. Admin selects template when creating challenge
2. Template data pre-fills form fields
3. Admin customizes and publishes

---

## 12. Outcome Tracking

Track what happens after a challenge closes.

### Outcome Statuses

| Status | Description |
|--------|-------------|
| `not_started` | Winning idea not yet acted upon |
| `in_progress` | Implementation underway |
| `implemented` | Successfully implemented |
| `abandoned` | Implementation abandoned |

### Outcome Fields

| Field | Type | Description |
|-------|------|-------------|
| `challenge_id` | FK (UNIQUE) | One outcome per challenge |
| `winning_idea_id` | FK | Selected winning idea |
| `status` | enum | 4 statuses |
| `impact_description` | text | Impact narrative |

### Outcome Operations

| Method | Description |
|--------|-------------|
| `getForChallenge(challengeId)` | Get outcome with winning idea details |
| `upsert(challengeId, userId, data)` | Admin-only create/update |
| `getDashboard()` | All outcomes with stats (total, implemented, in_progress, not_started, abandoned) |

### Outcomes Dashboard

Aggregates all outcomes across challenges:

```
ChallengeOutcomeService::getDashboard() → {
    outcomes: [{ challenge_title, idea_title, status, impact_description, updated_at }],
    stats: { total, implemented, in_progress, not_started, abandoned }
}
```

---

## 13. Favorites & Engagement

### Toggle Favorite

```
IdeationChallengeService::toggleFavorite(challengeId, userId) → {
    favorited: bool,
    favorites_count: int
}
```

- Toggle: add or remove from `challenge_favorites`
- Updates `ideation_challenges.favorites_count` atomically
- Wrapped in `DB::transaction()`
- UNIQUE constraint on `(challenge_id, user_id)`

### Engagement Metrics

Each challenge tracks:
- `ideas_count` — number of submitted ideas
- `views_count` — page views
- `favorites_count` — bookmarks
- `is_featured` — admin promotion flag

---

## 14. Team Workspace

When an idea is converted to a team (group), the team gets a full collaboration workspace:

### Chatrooms

| Feature | Description |
|---------|-------------|
| Multiple channels | Named channels with category labels |
| Private channels | Lock icon for restricted channels |
| Pinned messages | Pin/unpin important messages |
| Message CRUD | Post, delete messages |
| Admin controls | Create/delete channels |

### Tasks

| Feature | Description |
|---------|-------------|
| Status tracking | todo → in_progress → done |
| Priority levels | low, medium, high, urgent |
| Assignment | Assign to group members |
| Due dates | With overdue detection |
| Statistics | Total, done, in_progress, overdue counts |

### Documents

| Feature | Description |
|---------|-------------|
| File upload | Up to 10MB per file |
| Supported formats | PDF, DOC, DOCX, XLS, XLSX, PPT, PPTX, TXT, CSV, PNG, JPG, GIF, SVG, ZIP, RAR |
| File management | List, upload, delete |
| Type icons | Visual icons based on MIME type |

---

## 15. Gamification Challenges

A **separate system** from ideation challenges — these are XP-based action challenges in the gamification engine.

### Gamification Challenge Fields

| Field | Type | Description |
|-------|------|-------------|
| `challenge_type` | string | e.g., 'weekly' |
| `action_type` | string | Action to track (e.g., 'create_listing') |
| `target_count` | int | Actions needed to complete |
| `xp_reward` | int | XP awarded on completion |
| `badge_reward` | string | Badge key awarded |
| `start_date` / `end_date` | date | Active period |

### Progress Tracking

```
ChallengeService::updateProgress(userId, actionType, increment) → completedChallenges[]
```

- Finds active challenges matching `action_type`
- Increments `current_count` on `user_challenge_progress`
- Marks `completed_at` when progress >= target_count
- Awards XP and badge via `GamificationService`
- Uses pessimistic locking to prevent race conditions

---

## 16. Database Schema

### Tables (15+)

| Table | Purpose |
|-------|---------|
| `ideation_challenges` | Core challenge container |
| `challenge_ideas` | Submitted ideas |
| `challenge_idea_votes` | Vote records (UNIQUE idea_id+user_id) |
| `challenge_idea_comments` | Idea comments |
| `challenge_categories` | Category taxonomy (UNIQUE tenant_id+slug) |
| `challenge_tags` | Tag pool (UNIQUE tenant_id+slug) |
| `challenge_tag_links` | Challenge-tag junction (composite PK) |
| `challenge_templates` | Reusable blueprints |
| `challenge_outcomes` | Outcome tracking (UNIQUE challenge_id) |
| `challenge_favorites` | Bookmarks (UNIQUE challenge_id+user_id) |
| `idea_media` | Media attachments |
| `idea_team_links` | Idea-to-group conversion (UNIQUE idea_id+group_id) |
| `campaigns` | Campaign containers |
| `campaign_challenges` | Campaign-challenge junction |
| `challenges` | Gamification challenges (separate system) |
| `user_challenge_progress` | Gamification progress tracking |

### Key Schema Details

#### `ideation_challenges`
- 20+ columns including title, description, status (6 values), deadlines, cover_image, prize_description, max_ideas_per_user, tags (JSON), evaluation_criteria (JSON), cached counts
- Indexes: `(tenant_id, status)`, `(tenant_id, user_id)`

#### `challenge_ideas`
- Status enum: draft, submitted, shortlisted, winner, withdrawn
- Cached counters: votes_count, comments_count
- Indexes: `(challenge_id)`, `(user_id)`, `(challenge_id, votes_count DESC)`, `(status)`, `(user_id, status, challenge_id)`
- FK: challenge_id → ideation_challenges CASCADE

#### `challenge_idea_votes`
- UNIQUE `(idea_id, user_id)` prevents double voting
- FK: idea_id → challenge_ideas CASCADE

#### `idea_media`
- Media type enum: image, video, document, link
- URL varchar(1000), caption varchar(500)
- FK: idea_id → challenge_ideas CASCADE

#### `idea_team_links`
- UNIQUE `(idea_id, group_id)`
- FKs: idea_id → challenge_ideas CASCADE, challenge_id → ideation_challenges CASCADE

#### `challenge_outcomes`
- UNIQUE `(challenge_id)` — one outcome per challenge
- Status enum: not_started, in_progress, implemented, abandoned
- FK: challenge_id → ideation_challenges CASCADE

---

## 17. API Reference

### Challenge Endpoints

| Method | Endpoint | Description | Rate Limit |
|--------|----------|-------------|------------|
| GET | `/v2/ideation-challenges` | List with filters (status, category, search, cursor) | 60/min |
| POST | `/v2/ideation-challenges` | Create challenge | 10/min |
| GET | `/v2/ideation-challenges/{id}` | Challenge detail | — |
| PUT | `/v2/ideation-challenges/{id}` | Update challenge | 20/min |
| DELETE | `/v2/ideation-challenges/{id}` | Delete challenge | — |
| PUT | `/v2/ideation-challenges/{id}/status` | Change status | — |
| POST | `/v2/ideation-challenges/{id}/favorite` | Toggle favorite | — |
| POST | `/v2/ideation-challenges/{id}/duplicate` | Clone as draft | — |

### Idea Endpoints

| Method | Endpoint | Description | Rate Limit |
|--------|----------|-------------|------------|
| GET | `/v2/ideation-challenges/{id}/ideas` | List ideas (sort: votes/recent) | — |
| GET | `/v2/ideation-challenges/{id}/ideas/drafts` | List user's drafts | — |
| POST | `/v2/ideation-challenges/{id}/ideas` | Submit idea | 10/min |
| GET | `/v2/ideation-ideas/{id}` | Idea detail | — |
| PUT | `/v2/ideation-ideas/{id}` | Update idea | — |
| PUT | `/v2/ideation-ideas/{id}/draft` | Update draft (optionally publish) | — |
| DELETE | `/v2/ideation-ideas/{id}` | Delete idea | — |
| PUT | `/v2/ideation-ideas/{id}/status` | Change status (admin) | — |
| POST | `/v2/ideation-ideas/{id}/vote` | Toggle vote | — |
| POST | `/v2/ideation-ideas/{id}/convert-to-group` | Convert to team | — |

### Comment Endpoints

| Method | Endpoint | Description |
|--------|----------|-------------|
| GET | `/v2/ideation-ideas/{id}/comments` | List comments (cursor) |
| POST | `/v2/ideation-ideas/{id}/comments` | Add comment |
| DELETE | `/v2/ideation-comments/{id}` | Delete comment |

### Media Endpoints

| Method | Endpoint | Description |
|--------|----------|-------------|
| GET | `/v2/ideation-ideas/{id}/media` | List media |
| POST | `/v2/ideation-ideas/{id}/media` | Add media |
| DELETE | `/v2/ideation-media/{id}` | Delete media |

### Category Endpoints

| Method | Endpoint | Description |
|--------|----------|-------------|
| GET | `/v2/ideation-categories` | List categories |
| POST | `/v2/ideation-categories` | Create (admin) |
| PUT | `/v2/ideation-categories/{id}` | Update (admin) |
| DELETE | `/v2/ideation-categories/{id}` | Delete (admin) |

### Tag Endpoints

| Method | Endpoint | Description |
|--------|----------|-------------|
| GET | `/v2/ideation-tags` | List all tags |
| GET | `/v2/ideation-tags/popular` | Popular tags by usage |
| POST | `/v2/ideation-tags` | Create tag |
| DELETE | `/v2/ideation-tags/{id}` | Delete tag |

### Template Endpoints

| Method | Endpoint | Description |
|--------|----------|-------------|
| GET | `/v2/ideation-templates` | List templates |
| GET | `/v2/ideation-templates/{id}` | Template detail |
| POST | `/v2/ideation-templates` | Create (admin) |
| PUT | `/v2/ideation-templates/{id}` | Update (admin) |
| DELETE | `/v2/ideation-templates/{id}` | Delete (admin) |
| GET | `/v2/ideation-templates/{id}/data` | Get pre-fill data |

### Outcome Endpoints

| Method | Endpoint | Description | Rate Limit |
|--------|----------|-------------|------------|
| GET | `/v2/ideation-challenges/{id}/outcome` | Get challenge outcome | — |
| PUT | `/v2/ideation-challenges/{id}/outcome` | Upsert outcome (admin) | 10/min |
| GET | `/v2/ideation-outcomes/dashboard` | Outcomes dashboard | — |
| GET | `/v2/ideation-challenges/{id}/team-links` | Team conversion links | — |

### Campaign Endpoints

| Method | Endpoint | Description |
|--------|----------|-------------|
| GET | `/v2/ideation-campaigns` | List campaigns |
| GET | `/v2/ideation-campaigns/{id}` | Campaign detail |
| POST | `/v2/ideation-campaigns` | Create campaign |
| PUT | `/v2/ideation-campaigns/{id}` | Update campaign |
| DELETE | `/v2/ideation-campaigns/{id}` | Delete campaign |
| POST | `/v2/ideation-campaigns/{id}/challenges` | Link challenge |
| DELETE | `/v2/ideation-campaigns/{id}/challenges/{cid}` | Unlink challenge |

### Team Workspace Endpoints

| Method | Endpoint | Description |
|--------|----------|-------------|
| GET | `/v2/groups/{id}/chatrooms` | List chatrooms |
| POST | `/v2/groups/{id}/chatrooms` | Create chatroom |
| DELETE | `/v2/group-chatrooms/{id}` | Delete chatroom |
| GET | `/v2/group-chatrooms/{id}/messages` | Get messages |
| POST | `/v2/group-chatrooms/{id}/messages` | Post message |
| DELETE | `/v2/group-chatroom-messages/{id}` | Delete message |
| POST | `/v2/groups/{gid}/chatrooms/{cid}/pin/{mid}` | Pin message |
| DELETE | `/v2/groups/{gid}/chatrooms/{cid}/pin/{mid}` | Unpin message |
| GET | `/v2/groups/{gid}/chatrooms/{cid}/pinned` | Pinned messages |
| GET | `/v2/groups/{id}/tasks` | List tasks |
| POST | `/v2/groups/{id}/tasks` | Create task |
| PUT | `/v2/team-tasks/{id}` | Update task |
| DELETE | `/v2/team-tasks/{id}` | Delete task |
| GET | `/v2/groups/{id}/task-stats` | Task statistics |
| GET | `/v2/groups/{id}/documents` | List documents |
| POST | `/v2/groups/{id}/documents` | Upload document |
| DELETE | `/v2/team-documents/{id}` | Delete document |

### Admin Endpoints

| Method | Endpoint | Description |
|--------|----------|-------------|
| GET | `/v2/admin/ideation` | List all challenges (search, status, pagination) |
| GET | `/v2/admin/ideation/{id}` | Challenge detail |
| DELETE | `/v2/admin/ideation/{id}` | Delete challenge |
| POST | `/v2/admin/ideation/{id}/status` | Change status |

---

## 18. Frontend Experience

### IdeationPage (`/ideation`)

Main discovery and browsing interface:

- **Filter tabs:** All, Open, Voting, Evaluating, Closed, Archived, Favorites
- **Category dropdown:** Filter by structured category
- **Tag filter:** Popular tags as clickable chips
- **Search:** Text search with debouncing
- **Challenge cards:** Cover image, title, status chip, ideas count, favorites count, views count, creator avatar
- **Favorite toggle:** Heart icon with count
- **Admin button:** "Create Challenge" (feature-gated)
- **Navigation:** Links to Campaigns and Outcomes Dashboard pages
- **Pagination:** Cursor-based "Load More"

### ChallengeDetailPage (`/ideation/:id`)

Complete challenge view with full lifecycle management:

**Challenge header:** Cover image, title, status badge, creator info, deadlines, prize description, tags, category

**Ideas section:**
- Sort by votes (default) or newest
- Idea cards: title, description preview, vote count with toggle button, comment count, status chips (shortlisted/winner), creator avatar
- Submit idea form with up to 5 media attachments (type selector + URL input per media)
- Draft management: save and load drafts

**Admin dropdown menu:**
- Change status (valid transitions only)
- Link to campaign
- Duplicate challenge
- Edit challenge
- Manage outcome
- Delete challenge

**Outcome section** (for closed challenges):
- Winning idea display
- Implementation status with color-coded badges
- Impact description
- Admin edit form

### IdeaDetailPage (`/ideation/ideas/:id`)

Single idea with full governance:

- **Idea display:** Title, description, creator info, vote button, status chips
- **Media gallery:** Images, videos, documents, links
- **Comments section:** Cursor-paginated with add/delete
- **Admin controls:** Set shortlisted, set winner, delete
- **Convert to group:** Modal with name, description, visibility (public/private)
- **Team link:** Shows existing group link if already converted

### CreateChallengePage (`/ideation/create`)

Admin-only challenge creation form:

- **Template picker modal:** Browse and select templates to pre-fill fields
- **Form fields:** Title, description, category (dropdown from API), cover image URL, tags (chip input), prize description, start/end dates, max ideas per user, status
- **Validation:** Title required, guidance section for new creators
- **Edit mode:** Load existing challenge for modification

### OutcomesDashboardPage (`/ideation/outcomes`)

Aggregate impact view:

- **Summary stats:** Total outcomes, implemented, in progress, not started, abandoned
- **Outcomes list:** Challenge title, winning idea title, implementation status (color-coded), impact description, update date

### Team Workspace Components

| Component | File | Features |
|-----------|------|----------|
| **TeamChatrooms** | `src/components/ideation/TeamChatrooms.tsx` (663 lines) | Channel sidebar, message list, pin/unpin, create/delete channels |
| **TeamTasks** | `src/components/ideation/TeamTasks.tsx` (513 lines) | Task board, status cycling, priority, assignee, overdue, stats |
| **TeamDocuments** | `src/components/ideation/TeamDocuments.tsx` (335 lines) | File list, upload (10MB), type icons, delete |

---

## 19. Admin Panel

### IdeationAdmin (`src/admin/modules/ideation/IdeationAdmin.tsx`)

Admin dashboard for challenge management:

- **DataTable:** All challenges with status, ideas count, created date
- **Status tab filtering:** All, Draft, Open, Voting, Evaluating, Closed, Archived
- **Search:** By challenge title
- **Dropdown actions:** View details, change status (all valid transitions), delete
- **Pagination:** 50 items per page

### Admin Capabilities

| Capability | Description |
|-----------|-------------|
| Create challenges | Via CreateChallengePage or template |
| Manage lifecycle | Status transitions through 6 stages |
| Moderate ideas | Set shortlisted/winner, withdraw, delete |
| Manage categories | CRUD with icons and colors |
| Manage tags | CRUD with type classification |
| Manage templates | CRUD for reusable blueprints |
| Track outcomes | Set implementation status and impact |
| Manage campaigns | Bundle challenges under campaigns |
| Duplicate challenges | Clone as draft for reuse |
| Delete content | Challenges, ideas, comments, media |

---

*This report documents the complete ideation challenges engine as implemented in Project NEXUS v1.5.0. For the most current implementation, refer to the source files listed in Section 2.*
