# Project NEXUS — Events Module Engine: Complete Technical Report

**Generated:** 2026-03-29
**Version:** 1.5.0
**License:** AGPL-3.0-or-later

---

## Table of Contents

1. [Executive Summary](#1-executive-summary)
2. [System Architecture](#2-system-architecture)
3. [Events CRUD](#3-events-crud)
4. [RSVP System](#4-rsvp-system)
5. [Capacity & Waitlists](#5-capacity--waitlists)
6. [Attendance Tracking](#6-attendance-tracking)
7. [Recurring Events](#7-recurring-events)
8. [Event Series](#8-event-series)
9. [Reminders](#9-reminders)
10. [Notification System](#10-notification-system)
11. [Geospatial Search](#11-geospatial-search)
12. [Member Availability](#12-member-availability)
13. [Event Cancellation](#13-event-cancellation)
14. [Polls Integration](#14-polls-integration)
15. [Federation Events](#15-federation-events)
16. [Gamification Integration](#16-gamification-integration)
17. [Database Schema](#17-database-schema)
18. [API Reference](#18-api-reference)
19. [Frontend Experience](#19-frontend-experience)
20. [Admin Panel](#20-admin-panel)

---

## 1. Executive Summary

The Project NEXUS Events Module is a full-featured community event management system with RSVP, waitlists, attendance tracking, recurring events, series grouping, reminders, geospatial discovery, availability matching, and federated event sharing across communities. It integrates with the timebanking engine to credit volunteers for event attendance and with the gamification engine to award XP.

### What It Includes

| System | Purpose |
|--------|---------|
| **Events CRUD** | Create, update, delete events with image upload, categories, groups |
| **RSVP** | Going/interested/not_going with capacity enforcement |
| **Waitlists** | Position-tracked queue with automatic promotion when spots open |
| **Attendance** | Organizer check-in with time credit transfer and hours calculation |
| **Recurring Events** | Template-based with 5 frequencies and iCal RRULE support |
| **Event Series** | Group related events under named series |
| **Reminders** | 24h and 1h pre-event reminders via platform/email/both |
| **Notifications** | 5 HTML email templates for RSVP, updates, cancellation, reminders |
| **Geospatial Search** | Haversine radius search for nearby events |
| **Availability Matching** | Weekly schedule + compatible time finding between members |
| **Cancellation** | Full cancellation flow with reason tracking and mass notification |
| **Polls** | Event-linked polls with voting |
| **Federation** | Cross-community event discovery and RSVP |
| **Gamification** | XP awards for event creation and attendance |

### Key Metrics

| Metric | Value |
|--------|-------|
| Backend services | 3 (EventService, EventReminderService, EventNotificationService) |
| API endpoints | 42 routes across 3 controllers + availability |
| Database tables | 8 event-specific tables |
| Models | 2 Eloquent models (Event, EventRsvp) |
| RSVP statuses | 4 (going, interested, not_going, declined) |
| Reminder intervals | 2 (24h, 1h before) |
| Email templates | 5 (reminder, RSVP, update, cancellation, broadcast) |
| Recurrence frequencies | 5 (daily, weekly, monthly, yearly, custom) |
| Frontend pages | 4 + admin module + compose widget + feed integration |
| Event statuses | 4 (active, cancelled, completed, draft) |

---

## 2. System Architecture

### Service Map

```
┌──────────────────────────────────────────────────────────────────┐
│                       EVENTS ENGINE                              │
│                                                                  │
│  ┌──────────────────────┐  ┌──────────────────────────────────┐  │
│  │   EventService        │  │   EventNotificationService       │  │
│  │   (1,537 lines)       │  │   (857 lines)                    │  │
│  │                       │  │                                  │  │
│  │  CRUD, RSVP, Waitlist │  │  5 HTML email templates          │  │
│  │  Attendance, Series   │  │  Notification preferences        │  │
│  │  Recurring, Reminders │  │  Digest queuing                  │  │
│  │  Geospatial, Cancel   │  │  WebPush integration             │  │
│  └───────────┬───────────┘  └────────────────┬─────────────────┘  │
│              │                               │                    │
│  ┌───────────▼───────────┐  ┌────────────────▼─────────────────┐  │
│  │  EventReminderService  │  │  MemberAvailabilityController    │  │
│  │  (177 lines)           │  │                                  │  │
│  │                        │  │  Weekly schedules                │  │
│  │  24h/1h cron reminders │  │  Compatible time finding         │  │
│  │  Idempotent tracking   │  │  Available member search         │  │
│  └────────────────────────┘  └──────────────────────────────────┘  │
└──────────────────────────────────────────────────────────────────┘
```

### Key Files

| Component | File |
|-----------|------|
| Core Service | `app/Services/EventService.php` (1,537 lines) |
| Reminder Service | `app/Services/EventReminderService.php` (177 lines) |
| Notification Service | `app/Services/EventNotificationService.php` (857 lines) |
| Events Controller | `app/Http/Controllers/Api/EventsController.php` |
| Admin Controller | `app/Http/Controllers/Api/AdminEventsController.php` |
| Availability Controller | `app/Http/Controllers/Api/MemberAvailabilityController.php` |
| Event Model | `app/Models/Event.php` |
| RSVP Model | `app/Models/EventRsvp.php` |

---

## 3. Events CRUD

### Event Fields

| Field | Type | Description |
|-------|------|-------------|
| `title` | varchar(255) | Event title (required, max 255) |
| `description` | text | Full description (required) |
| `start_time` | datetime | Event start (required, must be future for new events) |
| `end_time` | datetime | Event end (optional, must be after start_time) |
| `location` | varchar(255) | Physical location |
| `latitude/longitude` | decimal(10,8)/decimal(11,8) | Geolocation |
| `max_attendees` | int | Capacity limit (null = unlimited) |
| `is_online` | boolean | Online event flag |
| `online_link` | varchar | Video/meeting URL |
| `image_url` / `cover_image` | varchar(255) | Event image |
| `category_id` | FK | Event category |
| `group_id` | FK | Associated community group |
| `user_id` | FK | Organizer |
| `status` | enum | `active`, `cancelled`, `completed`, `draft` |
| `federated_visibility` | enum | `none`, `listed`, `joinable` |
| `allow_remote_attendance` | boolean | Hybrid event flag |
| `video_url` | varchar | Video content URL |
| `sdg_goals` | JSON | UN Sustainable Development Goals tags |
| `volunteer_opportunity_id` | FK | Linked volunteering opportunity |
| `auto_log_hours` | boolean | Auto-log attendance hours |
| `series_id` | FK | Event series link |
| `parent_event_id` | FK | Recurring event template |
| `is_recurring_template` | boolean | Is recurrence template |
| `occurrence_date` | date | Specific occurrence date |
| `cancellation_reason` | text | Reason for cancellation |
| `cancelled_at` | timestamp | Cancellation timestamp |
| `cancelled_by` | FK | Who cancelled |

### Create Event

```
EventService::create(userId, data) → Event
```

- Validates title (required, max 255), start_time (required, date, after now), end_time (after start_time)
- Resolves `category_name` slug to `category_id` via categories table
- Creates event with all fields in a transaction
- Returns fresh model with user and category relationships loaded

### Update Event

```
EventService::update(id, userId, data) → bool
```

- **Authorization:** Organizer OR admin/super_admin/god role
- Resolves category_name → category_id
- Triggers `EventNotificationService::notifyEventUpdated()` for meaningful changes (start_time, end_time, location, title)

### Delete Event

```
EventService::delete(id, userId) → bool
```

- Authorization: Organizer or admin
- Hard delete via Eloquent

### List Events

```
EventService::getAll(filters) → { items, cursor, has_more }
```

**Filters:**
- `when`: upcoming (default) or past
- `category_id`: Filter by category
- `group_id`: Filter by community group
- `user_id`: Filter by organizer
- `search`: Text search on title, description, location (LIKE)
- `limit`: 1-100 (default 20)
- `cursor`: Base64-encoded cursor for pagination

**Enrichment per event:**
- `attendee_count` / `attendees_count`: Count of "going" RSVPs
- `interested_count`: Count of "interested" RSVPs
- `rsvp_counts`: `{ going, interested }`
- `spots_left`: max_attendees - going count (null if unlimited)
- `is_full`: boolean

---

## 4. RSVP System

### RSVP Statuses

| Status | Meaning | Counts Toward Capacity |
|--------|---------|----------------------|
| `going` | Will attend | Yes |
| `interested` | Interested but not confirmed | No |
| `not_going` | Declined | No |
| `declined` | Declined (alias) | No |
| `attended` | Checked in (set by organizer) | N/A |

### RSVP Flow

```
User RSVPs "going" → Capacity check
  → If space available → RSVP recorded → Organizer notified
  → If full → Auto-added to waitlist → User informed (waitlisted: true)

User RSVPs "interested" → No capacity check → RSVP recorded

User removes RSVP → RSVP deleted → Pending reminders cancelled
```

### Key Method

```
EventService::rsvp(eventId, userId, status) → bool
```

**Business rules:**
- Cannot RSVP to cancelled events
- Cannot RSVP (going/interested) to past events
- For "going" with max_attendees: uses `SELECT ... FOR UPDATE` lock to prevent race conditions
- If full: auto-adds to waitlist, returns error code `EVENT_FULL` with `waitlisted: true`
- Upsert: `INSERT ON DUPLICATE KEY UPDATE` on (event_id, user_id)

### Batch RSVP Lookup

```
EventService::getUserRsvpsBatch(eventIds[], userId) → { event_id: status }
```

Prevents N+1 queries when listing events with user RSVP status.

---

## 5. Capacity & Waitlists

### Capacity Enforcement

When an event has `max_attendees` set and a user RSVPs "going":

1. **Lock**: `SELECT going_count FROM event_rsvps ... FOR UPDATE`
2. **Check**: If going_count >= max_attendees → waitlist
3. **Insert/Update**: If space available → create RSVP
4. **Waitlist cleanup**: When someone cancels RSVP, check if waitlisted users can be promoted

### Waitlist

| Field | Type | Description |
|-------|------|-------------|
| `event_id` | FK | Event |
| `user_id` | FK | Waiting user |
| `position` | int | Queue position (1, 2, 3...) |
| `status` | enum | `waiting`, `promoted`, `cancelled`, `expired` |
| `promoted_at` | timestamp | When promoted to attending |
| `cancelled_at` | timestamp | When user cancelled |

### Key Methods

| Method | Description |
|--------|-------------|
| `addToWaitlist(eventId, userId)` | Add to end of queue (MAX position + 1) |
| `removeFromWaitlist(eventId, userId)` | Set status → cancelled |
| `getWaitlist(eventId, filters)` | List waiting users by position |
| `getUserWaitlistPosition(eventId, userId)` | Get user's position number |

---

## 6. Attendance Tracking

### Check-In Flow

```
Organizer marks attendee → EventService::markAttended()
  → Hours calculated (from event duration or override)
  → event_attendance record created/updated
  → event_rsvps status → "attended"
  → Time credits transferred to attendee (via controller)
```

### Hours Calculation

```
If hoursOverride provided → use it
Else if event has start_time AND end_time → (end - start) / 3600, min 0.5 hours
Else → 1.0 hour default
```

### Key Methods

| Method | Description |
|--------|-------------|
| `markAttended(eventId, attendeeId, markedById, hoursOverride?, notes?)` | Mark single attendee |
| `bulkMarkAttended(eventId, attendeeIds[], markedById)` | Bulk mark (returns {marked, failed}) |
| `getAttendanceRecords(eventId)` | List all check-ins with user details |

### Attendance Record

| Field | Type | Description |
|-------|------|-------------|
| `event_id` | FK | Event |
| `user_id` | FK | Attendee |
| `checked_in_at` | timestamp | Check-in time |
| `checked_in_by` | FK | Organizer/admin who checked in |
| `hours_credited` | decimal(6,2) | Time credits awarded |
| `notes` | text | Organizer notes |

### Time Credit Transfer

When check-in happens via the controller, the system transfers time credits from the organizer to the attendee based on calculated hours. This integrates directly with the timebanking wallet system.

---

## 7. Recurring Events

### Recurrence Frequencies

| Frequency | Description | Example |
|-----------|-------------|---------|
| `daily` | Every N days | Every day, every 2 days |
| `weekly` | Specific days each week | Every Monday and Wednesday |
| `monthly` | Specific day of month | 15th of every month |
| `yearly` | Specific date each year | March 29 each year |
| `custom` | iCal RRULE string | Full RFC 5545 support |

### Creation Flow

```
EventService::createRecurring(userId, data) → { template_id, occurrences }
```

1. Creates base event via `create()`
2. Marks as `is_recurring_template = 1`
3. Stores recurrence rule in `event_recurrence_rules` table
4. Generates occurrences via `generateOccurrences()`

### Recurrence Parameters

| Parameter | Description | Default |
|-----------|-------------|---------|
| `recurrence_frequency` | daily/weekly/monthly/yearly/custom | required |
| `interval_value` | Every N units | 1 |
| `days_of_week` | Comma-separated (0=Sun...6=Sat) | null |
| `day_of_month` | 1-31 for monthly | null |
| `rrule` | iCal RRULE string for custom | null |
| `ends_type` | after_count or on_date | after_count |
| `ends_after_count` | Stop after N occurrences | 10 (max 52) |
| `ends_on_date` | Stop on this date | null |

### Occurrence Generation

- Reads recurrence rule from DB
- Clones event start/end times
- Generates dates based on frequency and interval
- **Caps:** 52 occurrences maximum OR 1 year out
- Creates new event records with `parent_event_id` pointing to template
- Copies all relevant fields from template

### Updating Recurring Events

```
EventService::updateRecurring(eventId, userId, data, scope) → bool
```

| Scope | Behavior |
|-------|----------|
| `single` | Detaches event from parent (orphans it), updates only this occurrence |
| `all` | Updates all future occurrences (start_time >= NOW()) |

---

## 8. Event Series

Event series group related events under a named umbrella (e.g., "Weekly Book Club", "Monthly Community Clean-up").

### Key Methods

| Method | Description |
|--------|-------------|
| `getAllSeries(filters)` | List series with event_count and next_event |
| `createSeries(userId, title, description?)` | Create new series |
| `getSeriesInfo(seriesId)` | Get series details with event_count |
| `getSeriesEvents(seriesId)` | Get all events in series (ordered by start_time) |
| `linkToSeries(eventId, seriesId, userId)` | Link existing event to series |

### Series Schema

| Field | Type | Description |
|-------|------|-------------|
| `title` | varchar(255) | Series name |
| `description` | text | Series description |
| `created_by` | FK | Creator user |

Events link to series via `events.series_id`.

---

## 9. Reminders

### Reminder Intervals

| Interval | Hours Before | Message Style |
|----------|-------------|---------------|
| `24h` | 24 hours | "Reminder: {title} is tomorrow — {day}, {date} at {time}" |
| `1h` | 1 hour | "Starting soon: {title} begins in 1 hour — {day}, {date} at {time}" |

### User-Configurable Reminders

Users can set per-event reminders:

```
EventService::updateReminders(eventId, userId, [
    { minutes: 60, type: "platform" },
    { minutes: 1440, type: "email" },
    { minutes: 10080, type: "both" }
]) → bool
```

**Valid minutes:** 60 (1h), 1440 (24h), 10080 (7 days)
**Valid types:** platform, email, both

Reminders are stored in `event_reminders` with scheduled_for = start_time - minutes.

### Cron-Based Reminders

```
EventReminderService::sendDueReminders(tenantId) → int
```

Runs periodically:
1. For each interval (24h, 1h):
   - Calculates window: hours×60 ± 30 minutes from now
   - Finds events within window
   - Gets attendees (going/interested) not yet reminded
   - Creates in-app notifications
   - Records in `event_reminder_sent` (INSERT IGNORE for idempotency)
2. Returns total reminders sent

### Idempotency

`event_reminder_sent` table with UNIQUE constraint `(tenant_id, event_id, user_id, reminder_type)` ensures no duplicate reminders.

---

## 10. Notification System

### 5 HTML Email Templates

Each template is a responsive HTML email with proper escaping and tenant branding:

#### 1. Reminder Email (`buildReminderEmailHtml`)
- **24h:** Purple gradient, "Event Tomorrow" heading
- **1h:** Amber-to-red gradient, "Starting Soon" heading
- Shows: event title, formatted date/time, location (or "Online"), View Event button

#### 2. RSVP Notification Email (`buildRsvpEmailHtml`)
- Sent to organizer when someone RSVPs
- Shows: user name, status badge (green=going, amber=interested), event title
- Only triggers for "going" and "interested" (not declines)

#### 3. Event Update Email (`buildUpdateEmailHtml`)
- Amber gradient, "Event Updated" heading
- Shows: event title, bulleted list of changes (date/time, location, title)
- Only triggers for meaningful changes: start_time, end_time, location, title

#### 4. Cancellation Email (`buildCancellationEmailHtml`)
- Red gradient, "Event Cancelled" heading
- Shows: event title, scheduled date, reason box (if provided)
- Browse Events button

#### 5. Broadcast Email (`buildDefaultEventEmailHtml`)
- Purple/indigo gradient
- Shows: custom message from organizer, View Event button
- Used for organizer broadcasts to all attendees

### Notification Frequency Preferences

Email delivery respects per-user frequency settings:

| Frequency | Behavior |
|-----------|----------|
| `off` | No email sent |
| `instant` | Sent immediately + WebPush |
| `daily` | Queued to `notification_queue` for daily digest |
| `weekly` | Queued for weekly digest |

**Lookup hierarchy:**
1. User's event-specific setting
2. User's global setting
3. Tenant default setting
4. Hardcoded fallback: `daily`

### Key Methods

| Method | Description |
|--------|-------------|
| `notifyAttendees(tenantId, eventId, message)` | Broadcast to all going/interested |
| `sendReminder(tenantId, eventId)` | Send 24h/1h reminders |
| `notifyCancellation(tenantId, eventId, reason?)` | Notify all RSVPs + waitlisted |
| `notifyEventUpdated(eventId, changes)` | Notify of meaningful changes |
| `notifyRsvp(eventId, userId, status)` | Notify organizer of RSVP |

---

## 11. Geospatial Search

### Nearby Events

```
EventService::getNearby(lat, lon, filters) → { items, has_more }
```

**Haversine formula** calculates great-circle distance:

```sql
6371 * ACOS(
    COS(RADIANS(lat)) * COS(RADIANS(events.latitude))
    * COS(RADIANS(events.longitude) - RADIANS(lon))
    + SIN(RADIANS(lat)) * SIN(RADIANS(events.latitude))
) AS distance_km
```

**Filters:**
- `radius_km`: Default 25km
- `limit`: 1-100
- `category_id`: Optional category filter

**Requirements:** Events must have latitude/longitude set, be upcoming, and not cancelled.

**Returns:** Full event data + organizer + category + `distance_km` + rsvp_counts.

---

## 12. Member Availability

### Weekly Schedule

Members can set weekly availability windows:

```
PUT /v2/users/me/availability → { schedule: [...] }
```

### Availability Endpoints

| Method | Endpoint | Description |
|--------|----------|-------------|
| GET | `/v2/users/me/availability` | Get my weekly schedule |
| PUT | `/v2/users/me/availability` | Set full weekly schedule |
| PUT | `/v2/users/me/availability/{day}` | Set single day (0-6) |
| POST | `/v2/users/me/availability/date` | Add one-off date |
| DELETE | `/v2/users/me/availability/{id}` | Delete slot |
| GET | `/v2/users/{id}/availability` | View another user's availability |
| GET | `/v2/members/availability/compatible` | Find compatible times with user |
| GET | `/v2/members/availability/available` | Find members available at time |

### Compatible Time Finding

```
GET /v2/members/availability/compatible?user_id=123
```

Compares your availability with another user's, returns overlapping time windows.

### Available Members

```
GET /v2/members/availability/available?day=1&time=14:00
```

Finds members available on a specific day and optional time.

---

## 13. Event Cancellation

### Cancellation Flow

```
EventService::cancelEvent(eventId, userId, reason) → bool
```

1. **Authorization:** Organizer or admin
2. **Check:** Event not already cancelled
3. **Updates:**
   - Event status → `cancelled`
   - `cancellation_reason`, `cancelled_at`, `cancelled_by` recorded
4. **Side effects:**
   - All pending reminders cancelled
   - All waitlist entries cancelled
   - All RSVPs (going/interested/invited) marked as cancelled
5. **Notifications:**
   - In-app notification to all RSVP'd users
   - In-app notification to all waitlisted users
   - Cancellation email to all (via `notifyCancellation`)

---

## 14. Polls Integration

Events can have linked polls for attendee voting:

- `polls.event_id` FK links polls to events
- CreateEventPage can attach existing polls
- EventDetailPage displays polls with voting UI
- Poll results shown if user has voted or poll is closed

---

## 15. Federation Events

Events can be shared across federated communities:

### Federated Visibility Levels

| Level | Description |
|-------|-------------|
| `none` | Only visible within own tenant |
| `listed` | Visible in federated event search |
| `joinable` | Can RSVP from other tenants |

### Federated RSVP

| Field | Type | Description |
|-------|------|-------------|
| `is_federated` | boolean | Cross-community RSVP |
| `source_tenant_id` | FK | Source tenant of federated RSVP |

### Federation Frontend

`FederationEventsPage` allows browsing federated events:
- Search with 300ms debounce
- Filter by partner community
- Upcoming-only toggle
- Cursor-based pagination (20/page)
- Map view for events with coordinates

---

## 16. Gamification Integration

### XP Awards

| Activity | When | XP |
|----------|------|----|
| Create event | `POST /v2/events` | `create_event` (30 XP in V1) |
| RSVP "going" | `POST /v2/events/{id}/rsvp` | `attend_event` (15 XP in V1) |

### Badge Integration

Events contribute to gamification badge checks:
- `event_attend_1/10/25` badges (based on RSVP "going" count)
- `event_host_1/5` badges (based on events created)

### Time Credit Transfer

When organizer checks in an attendee:
- Hours calculated from event duration (or manual override, min 0.5h)
- Credits transferred from organizer to attendee via wallet system
- Recorded in `event_attendance.hours_credited`

---

## 17. Database Schema

### Tables (8)

#### `events` (29 columns)

| Column | Type | Key |
|--------|------|-----|
| `id` | int PK | AUTO_INCREMENT |
| `tenant_id` | int | idx_event_tenant |
| `user_id` | int | idx_events_user_id |
| `group_id` | int FK | fk_event_group → groups.id CASCADE |
| `title` | varchar(255) | — |
| `description` | text | — |
| `location` | varchar(255) | — |
| `start_time` | datetime | idx_event_start |
| `end_time` | datetime | — |
| `max_attendees` | int | — |
| `cover_image` | varchar(255) | — |
| `sdg_goals` | JSON | — |
| `category_id` | int FK | fk_events_category → categories.id SET NULL |
| `volunteer_opportunity_id` | int FK | fk_event_opportunity → vol_opportunities.id SET NULL |
| `auto_log_hours` | tinyint(1) | default 0 |
| `latitude` | decimal(10,8) | — |
| `longitude` | decimal(11,8) | — |
| `federated_visibility` | enum(none,listed,joinable) | default 'none' |
| `allow_remote_attendance` | tinyint(1) | default 0 |
| `status` | enum(active,cancelled,completed,draft) | idx_events_status |
| `parent_event_id` | int | idx_events_parent |
| `occurrence_date` | date | idx_events_occurrence |
| `is_recurring_template` | tinyint(1) | default 0 |
| `cancellation_reason` | text | — |
| `cancelled_at` | timestamp | — |
| `cancelled_by` | int | — |
| `series_id` | int | idx_events_series |
| `created_at` | datetime | default CURRENT_TIMESTAMP |

#### `event_rsvps`

| Column | Type | Key |
|--------|------|-----|
| `id` | int PK | |
| `tenant_id` | int unsigned | idx_tenant_id |
| `event_id` | int | UNIQUE(event_id, user_id) |
| `user_id` | int | idx_rsvp_user |
| `status` | enum(going,maybe,declined) | — |
| `checked_in_at` | datetime | — |
| `checked_out_at` | datetime | — |
| `is_federated` | tinyint(1) | idx_federated_events |
| `source_tenant_id` | int | — |
| `created_at` | datetime | |

#### `event_attendance`

| Column | Type | Key |
|--------|------|-----|
| `id` | int PK | |
| `event_id` | int FK | UNIQUE(event_id, user_id), CASCADE |
| `user_id` | int FK | CASCADE |
| `tenant_id` | int FK | CASCADE |
| `checked_in_at` | timestamp | — |
| `checked_in_by` | int | — |
| `hours_credited` | decimal(6,2) | — |
| `notes` | text | — |

#### `event_reminders`

| Column | Type | Key |
|--------|------|-----|
| `id` | int PK | |
| `event_id` | int FK | UNIQUE(event_id, user_id, remind_before_minutes), CASCADE |
| `user_id` | int FK | CASCADE |
| `tenant_id` | int FK | CASCADE |
| `remind_before_minutes` | int unsigned | 60, 1440, 10080 |
| `reminder_type` | enum(platform,email,both) | — |
| `scheduled_for` | timestamp | idx_reminder_scheduled, idx_reminder_pending |
| `status` | enum(pending,sent,failed,cancelled) | — |

#### `event_reminder_sent` (idempotency)

| Column | Type | Key |
|--------|------|-----|
| `id` | int unsigned PK | |
| `tenant_id` | int unsigned | UNIQUE(tenant_id, event_id, user_id, reminder_type) |
| `event_id` | int unsigned | — |
| `user_id` | int unsigned | — |
| `reminder_type` | enum(24h,1h) | — |
| `sent_at` | datetime | idx_event_reminder_cleanup |

#### `event_recurrence_rules`

| Column | Type | Key |
|--------|------|-----|
| `id` | int PK | |
| `event_id` | int FK | CASCADE |
| `tenant_id` | int FK | CASCADE |
| `frequency` | enum(daily,weekly,monthly,yearly,custom) | default 'weekly' |
| `interval_value` | int unsigned | default 1 |
| `days_of_week` | varchar(50) | 0=Sun...6=Sat |
| `day_of_month` | int unsigned | 1-31 |
| `month_of_year` | int unsigned | 1-12 |
| `rrule` | text | iCal RRULE string |
| `ends_type` | enum(never,after_count,on_date) | default 'never' |
| `ends_after_count` | int unsigned | — |
| `ends_on_date` | date | — |

#### `event_series`

| Column | Type | Key |
|--------|------|-----|
| `id` | int PK | |
| `tenant_id` | int FK | CASCADE |
| `title` | varchar(255) | — |
| `description` | text | — |
| `created_by` | int FK | CASCADE |

#### `event_waitlist`

| Column | Type | Key |
|--------|------|-----|
| `id` | int PK | |
| `event_id` | int FK | UNIQUE(event_id, user_id), CASCADE |
| `user_id` | int FK | CASCADE |
| `tenant_id` | int FK | CASCADE |
| `position` | int unsigned | idx_waitlist_position |
| `status` | enum(waiting,promoted,cancelled,expired) | idx_waitlist_status |
| `promoted_at` | timestamp | — |
| `cancelled_at` | timestamp | — |

---

## 18. API Reference

### Core Events (25 endpoints)

| Method | Endpoint | Description | Rate Limit |
|--------|----------|-------------|------------|
| GET | `/v2/events` | List events with filters | — |
| POST | `/v2/events` | Create event | 10/min |
| GET | `/v2/events/{id}` | Event detail | — |
| PUT | `/v2/events/{id}` | Update event | 20/min |
| DELETE | `/v2/events/{id}` | Delete event | 10/min |
| GET | `/v2/events/nearby` | Geospatial search | — |
| POST | `/v2/events/{id}/rsvp` | RSVP (going/interested/not_going) | 30/min |
| DELETE | `/v2/events/{id}/rsvp` | Remove RSVP | 30/min |
| GET | `/v2/events/{id}/attendees` | List attendees | — |
| POST | `/v2/events/{id}/attendees/{uid}/check-in` | Check in attendee + credit transfer | 30/min |
| GET | `/v2/events/{id}/waitlist` | View waitlist | — |
| POST | `/v2/events/{id}/waitlist` | Join waitlist | 30/min |
| DELETE | `/v2/events/{id}/waitlist` | Leave waitlist | 30/min |
| GET | `/v2/events/{id}/attendance` | Attendance records | — |
| POST | `/v2/events/{id}/attendance` | Mark attended | 30/min |
| POST | `/v2/events/{id}/attendance/bulk` | Bulk mark attended | 10/min |
| GET | `/v2/events/{id}/reminders` | Get user reminders | — |
| PUT | `/v2/events/{id}/reminders` | Update reminders | 20/min |
| POST | `/v2/events/{id}/cancel` | Cancel event | 5/min |
| GET | `/v2/events/series` | List series | — |
| POST | `/v2/events/series` | Create series | 10/min |
| GET | `/v2/events/series/{id}` | Series detail + events | — |
| POST | `/v2/events/{id}/series` | Link to series | 20/min |
| POST | `/v2/events/recurring` | Create recurring event | 5/min |
| PUT | `/v2/events/{id}/recurring` | Update recurring (single/all) | 20/min |
| POST | `/v2/events/{id}/image` | Upload event image | 10/min |

### Admin Events (5 endpoints)

| Method | Endpoint | Description |
|--------|----------|-------------|
| GET | `/v2/admin/events` | List all events (pagination, status, search) |
| GET | `/v2/admin/events/{id}` | Admin event detail |
| POST | `/v2/admin/events/{id}/approve` | Approve pending event |
| POST | `/v2/admin/events/{id}/cancel` | Admin cancel event |
| DELETE | `/v2/admin/events/{id}` | Admin delete event |

### Member Availability (8 endpoints)

| Method | Endpoint | Description | Rate Limit |
|--------|----------|-------------|------------|
| GET | `/v2/users/me/availability` | My schedule | — |
| PUT | `/v2/users/me/availability` | Set weekly schedule | 10/min |
| PUT | `/v2/users/me/availability/{day}` | Set single day | 10/min |
| POST | `/v2/users/me/availability/date` | Add one-off date | 10/min |
| DELETE | `/v2/users/me/availability/{id}` | Delete slot | — |
| GET | `/v2/users/{id}/availability` | View user's availability | 30/min |
| GET | `/v2/members/availability/compatible` | Find compatible times | 20/min |
| GET | `/v2/members/availability/available` | Find available members | 20/min |

### Federation & Special (4 endpoints)

| Method | Endpoint | Description |
|--------|----------|-------------|
| GET | `/v2/federation/events` | Federated event search |
| POST | `/ai/generate/event` | AI-powered event generation |
| GET | `/api/events` | Legacy v1 listing |
| POST | `/api/events/rsvp` | Legacy v1 RSVP |

---

## 19. Frontend Experience

### EventsPage (`/events`)

Main events discovery interface:

- **Search**: 300ms debounced text search
- **Filters**: Time (upcoming/past/all), category chips (workshop, social, outdoor, online, meeting, training, other), "Near Me" location toggle with radius slider
- **Views**: List view (default) with EventCard, Map view with coordinate pins
- **Pagination**: Cursor-based (20 items/page)
- **EventCard**: Date badge, title, category chip, description preview, location, attendee/interested counts, capacity indicator, full badge

### EventDetailPage (`/events/:id`)

Comprehensive event view with tabbed interface:

- **Tabs**: Details, Attendees, Check-in (organizer-only)
- **RSVP**: Three-state toggle (going/interested/not_going) with toggle-to-remove
- **Waitlist**: Join/leave with position display
- **Check-in**: Organizer progress circle (% checked in) with attendee checklist
- **Polls**: Display linked polls with voting UI
- **Series**: Related events in same series with breadcrumb
- **Organizer Actions**: Edit, Cancel (with reason modal), Delete
- **Location**: LocationMapCard for venue
- **Cancellation Banner**: Displays reason when event is cancelled
- **RSVP Normalization**: Handles going/attending and interested/maybe variants

### CreateEventPage (`/events/create`)

Full-featured event creation form:

- **Image Upload**: JPEG/PNG/WebP/GIF (max 5MB), drag-and-drop, preview
- **Date/Time**: Separate start/end with HeroUI DatePicker and TimeInput
- **Location**: PlaceAutocompleteInput capturing lat/lng
- **Category**: Dropdown selection
- **Attendee Limit**: 1-10,000 range validation
- **Recurrence Config**: Frequency (daily/weekly/biweekly/monthly), day selection, end type (count or date), RRULE generation
- **Remote Attendance**: Toggle with video_url field
- **Poll Attachment**: Multi-select from available polls
- **Edit Mode**: Loads existing event for modification
- **Validation**: Title 5+ chars, description 20+ chars, start/end required

### EventReminderSettings (`/events/reminders`)

- Per-event reminders available on detail page
- Global preferences placeholder (coming soon)

### Compose Hub Integration

**EventTab** in compose hub enables quick event creation:
- Title, description, dates, location, SDG goals
- Draft persistence via localStorage
- AI assist for description generation
- Character limit (3,000)

### Feed Integration

- **UpcomingEventsWidget**: Feed sidebar showing next 5 events
- **FeedCard**: Events displayed in feed with calendar icon, date/location meta

### Group Events

**GroupEventsTab** in group detail:
- Create event shortcut for group members
- Upcoming events list with date/attendee count
- Past events section (reduced opacity)

### Federation Events

**FederationEventsPage**: Browse federated events from partner communities with search, partner filter, map view.

---

## 20. Admin Panel

### Admin Events Dashboard

**EventsAdmin** (`src/admin/modules/events/EventsAdmin.tsx`):

- **DataTable**: Title, start date/time, location, organizer, status, attendees/capacity, actions
- **Status Filter Tabs**: All, published, cancelled, draft
- **Status Colors**: published/active (success), cancelled (danger), draft (default), past (warning)
- **Pagination**: 50 items/page
- **Search**: Debounced search
- **Actions**: View (opens in new tab), Cancel (with confirmation), Delete (with confirmation)

### Admin Capabilities

| Capability | Description |
|-----------|-------------|
| List all events | Paginated with status/search filters |
| View event detail | Full event data |
| Approve pending | For events requiring admin approval |
| Cancel event | With reason, notifies all attendees |
| Delete event | Hard delete with confirmation |

---

*This report documents the complete events module engine as implemented in Project NEXUS v1.5.0. For the most current implementation, refer to the source files listed in Section 2.*
