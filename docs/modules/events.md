# Events Module Guide

Last reviewed: 2026-06-23

This guide covers the **Events** module in Project NEXUS — community event creation, RSVP, waitlists, recurring series, polls, cover images, online join links, organiser actions, reminders, and notifications. It is a maintainer reference; for the live endpoint list, read [`routes/api.php`](../../routes/api.php).

## Audience and supported workflows

Use this guide when changing event creation, RSVP behaviour, recurring-series generation, reminder delivery, or any notification triggered by events.

Supported workflows:

- **Create / edit / cancel / delete** a single event or a recurring series.
- **RSVP** as going, interested, or not going; automatic waitlist enrolment when an event is at capacity.
- **Recurring series** — generate up to 52 occurrences on a daily, weekly, monthly, or yearly schedule; the list view collapses a series to one card (the next upcoming occurrence).
- **Event polls** — attach existing polls to an event at create or edit time.
- **Cover image upload** — organiser uploads a cover image; for series events the image propagates to all occurrences automatically.
- **Online events** — mark an event as online with a join link and optional video URL.
- **Organiser check-in** — organiser marks an attendee as attended, which transfers time credits from organiser to attendee.
- **Reminders** — automatic 24 h and 1 h reminders to all RSVP'd attendees; user-configured reminders at 1 h, 24 h, or 7 d.
- **Admin review** — platform admins can list, view, cancel, and delete events across the tenant.

## Tenant and feature-gate rules

- **Feature gate: `events`** (default `true`). All React routes under `/events`, `/events/:id`, and `/events/create` are wrapped in `<FeatureGate feature="events" ...>` (see `react-frontend/src/App.tsx`). The accessible frontend enforces the same gate via `abort_unless(TenantContext::hasFeature('events'), 403)` on every events route handler (`app/Http/Controllers/GovukAlpha/Concerns/EventsParity.php`).
- **Tenant scoping is automatic.** `App\Models\Event` uses the `HasTenantScope` Eloquent trait, which appends `tenant_id = ?` to every query automatically. All raw DB calls in `EventService` and `EventReminderService` also filter on `tenant_id = TenantContext::getId()`.
- The list (`GET /v2/events`) and detail (`GET /v2/events/{id}`) endpoints are **public** (no auth required). Authenticated callers additionally receive their own RSVP status.
- RSVP, create, edit, cancel, delete, and image-upload endpoints require authentication (Sanctum token).

## Key code and data locations

Routes are defined in [`routes/api.php`](../../routes/api.php). Do not duplicate the full endpoint table here.

| Concern | Route prefix | Controller |
| --- | --- | --- |
| Event CRUD, RSVP, waitlist, series, recurring, reminders, attendance, image | `/v2/events/*` | `app/Http/Controllers/Api/EventsController.php` |
| Admin event management | `/v2/admin/events/*` | `app/Http/Controllers/Api/AdminEventsController.php` |

Services:

- `app/Services/EventService.php` — all event operations: `getAll()`, `getById()`, `create()`, `update()`, `delete()`, `cancelEvent()`, `rsvp()`, `removeRsvp()`, `addToWaitlist()`, `removeFromWaitlist()`, `createRecurring()`, `generateOccurrences()`, `updateRecurring()`, `updateImage()`, `markAttended()`.
- `app/Services/EventNotificationService.php` — in-app bell and email notifications for creation, RSVP change, cancellation, meaningful updates, and manual attendee notifications.
- `app/Services/EventReminderService.php` — automated reminder dispatch: fixed 24 h/1 h scan and user-configured reminders from `event_reminders`.

Models and tables:

- `App\Models\Event` (`events`) — the primary event record. Key columns: `user_id`, `title`, `description`, `start_time`, `end_time` (nullable), `location`, `latitude`, `longitude`, `max_attendees` (nullable), `is_online`, `online_link`, `image_url`, `cover_image`, `federated_visibility`, `is_recurring_template`, `parent_event_id`, `occurrence_date`, `series_id`, `status`.
- `App\Models\EventRsvp` (`event_rsvps`) — one row per user per event; `status` values: `going`, `interested`, `not_going`, `declined`, `invited`, `attended`.
- `event_waitlist` — waitlist rows with `status = 'waiting'` for full events.
- `event_recurrence_rules` — one row per recurring template; columns: `frequency`, `interval_value`, `days_of_week`, `day_of_month`, `rrule`, `ends_type`, `ends_after_count`, `ends_on_date`.
- `event_reminders` — user-configured reminders (statuses: `pending`, `sent`, `cancelled`, `failed`).
- `event_reminder_sent` — idempotency table; prevents duplicate reminder delivery.
- `event_reminder_delivery_claims` — distributed-safe claim table; prevents concurrent reminder delivery by multiple queue workers.

React frontend entry points:

- `react-frontend/src/pages/events/EventsPage.tsx` — list view.
- `react-frontend/src/pages/events/EventDetailPage.tsx` — detail view with RSVP, waitlist, and attendee roster.
- `react-frontend/src/pages/events/CreateEventPage.tsx` — create and edit form.
- `react-frontend/src/pages/events/EventReminderSettings.tsx` — per-attendee reminder configuration.

## Create, edit, cancel, and delete

### Create

`POST /v2/events` — requires auth. Required fields: `title` (max 255 chars), `description`, `start_time` (ISO datetime, must be in the future). Optional: `end_time`, `location`, `latitude`/`longitude`, `max_attendees`, `is_online` (bool), `online_link`, `video_url`, `allow_remote_attendance` (bool), `image_url`, `category_id` or `category_name` (slug), `group_id`, `federated_visibility` (default `none`), `poll_ids` (array of poll IDs the caller owns).

On success: HTTP 201 with the created event. Side effects (dispatched after the HTTP response via `afterResponse`): `CommunityEventCreated` Laravel event, `EventNotificationService::notifyEventCreated()`, XP award via `GamificationService`, feed activity record.

Rate limit: 10 requests per 60 seconds.

### Edit

`PUT /v2/events/{id}` — requires auth. Caller must be the event organiser or a platform admin. The `allowed` field list is: `title`, `description`, `start_time`, `end_time`, `location`, `latitude`, `longitude`, `category_id`, `group_id`, `max_attendees`, `is_online`, `online_link`, `image_url`, `federated_visibility`, `allow_remote_attendance`, `video_url`.

After a successful save, if any of `title`, `start_time`, `end_time`, or `location` changed, `EventNotificationService::notifyEventUpdated()` sends a bell notification and email to every attendee with RSVP status `going` or `interested`, rendered in each attendee's preferred language via `LocaleContext::withLocale()`.

### Cancel

`POST /v2/events/{id}/cancel` — requires auth. Caller must be the organiser or admin. Accepts optional `reason` body field. Sets `events.status = 'cancelled'`. For a **recurring template**, all future non-cancelled occurrences are also cancelled. After the DB update, `EventNotificationService::notifyCancellation()` sends a bell and email to all going/interested/invited attendees and waitlisted users across all cancelled occurrences (each in their own locale).

Returns `409` if the event is already cancelled.

### Delete

`DELETE /v2/events/{id}` — requires auth. For a **recurring template**, the recurrence rule is deleted and all future occurrences are deleted; past occurrences are detached (`parent_event_id = NULL`) so attendance history is preserved. Attendees of future occurrences are notified via the same cancellation path, with an event snapshot passed to the notifier because the rows are already gone at notification time.

Rate limit: 10 requests per 60 seconds.

### Cover image upload

`POST /v2/events/{id}/image` (multipart, field name `image`) — requires auth, organiser or admin only. Calls `app/Core/ImageUploader::upload()`. For series events (recurring template or child occurrence), the image is propagated to all sibling rows in the same series:

```php
UPDATE events SET cover_image = ? WHERE tenant_id = ? AND (id = ? OR parent_event_id = ?)
```

Rate limit: 20 per 60 seconds.

## RSVP and waitlists

### RSVP

`POST /v2/events/{id}/rsvp` with body `{ "status": "going" | "interested" | "not_going" | "declined" }`.

Valid statuses: `going`, `interested`, `not_going`, `declined`. Attempting to RSVP to a cancelled event returns `409 EVENT_CANCELLED`. Attempting to RSVP as `going` or `interested` to a past event (after `end_time` or `start_time` when no end time) returns an error. For Caring Community "kiss-treffen" events that have `members_only = true`, non-approved users are rejected with `403 KISS_TREFFEN_MEMBERS_ONLY`.

Capacity enforcement for `going` status with `max_attendees` set:

```
SELECT COUNT(*) ... FOR UPDATE   ← serialises concurrent RSVPs
```

If the event is full and the user is not already `going`, `EventService::addToWaitlist()` is called and the endpoint returns `HTTP 200` with `status: "waitlisted"` and `waitlist_position`. This is not an error response.

On a genuine RSVP status change, the organiser is notified via `EventNotificationService::notifyRsvp()`. A `going` RSVP awards XP via `GamificationService::awardXP()`.

Removing an RSVP: `DELETE /v2/events/{id}/rsvp`. Pending reminders for the user are cancelled immediately.

### Waitlist

Waitlist is managed by `event_waitlist`. Endpoints:

- `GET /v2/events/{id}/waitlist` — organiser can view the waitlist; includes caller's position.
- `POST /v2/events/{id}/waitlist` — explicit join (also triggered automatically when event is full during RSVP).
- `DELETE /v2/events/{id}/waitlist` — leave the waitlist.

## Recurring series

### Creating a recurring series

`POST /v2/events/recurring`. Required fields are the same as single-event create plus:

| Field | Values | Notes |
| --- | --- | --- |
| `recurrence_frequency` | `daily`, `weekly`, `monthly`, `yearly`, `custom` | Required |
| `recurrence_interval` | integer ≥ 1 | Step size (e.g. 2 = biweekly) |
| `recurrence_ends_type` | `after_count` or `on_date` | |
| `recurrence_ends_after_count` | integer, clamped to 52 | Ignored when `ends_type = on_date` |
| `recurrence_ends_on_date` | ISO date | Ignored when `ends_type = after_count` |
| `recurrence_days` | JSON array of day names | For weekly frequency |
| `recurrence_day_of_month` | integer | For monthly frequency |

`EventService::generateOccurrences()` generates up to 52 occurrences and stops at the earlier of the ends constraint or one year from now. Monthly occurrences are re-anchored to the template's day-of-month each month, clamped to the target month's last day, to prevent drift (the naive `+1 month` overflows short months).

The template row has `is_recurring_template = 1`; each occurrence has `parent_event_id = <template_id>` and `occurrence_date`.

### How the list collapses a series

`EventService::getAll()` uses a `WHERE NOT EXISTS` subquery to exclude sibling occurrences when a more-preferred occurrence exists. For `when=upcoming`, the soonest upcoming occurrence survives. For `when=past`, the most recent past occurrence survives. The surviving card receives `is_series: true`, `series_count`, and `recurrence_frequency` so the UI can show "Repeats weekly · N dates". The detail page (`EventService::getById()`) adds `series_occurrences` (up to 50 upcoming dates).

### Updating a recurring series

`PUT /v2/events/{id}/recurring` with `scope: "single"` or `scope: "all"`. Single scope detaches the occurrence from the parent (sets `parent_event_id = NULL`) and updates just that event. All scope updates every future occurrence, but **never changes `start_time` or `end_time`** (those are per-occurrence; removing them from the update prevents the whole series collapsing to one timestamp).

### Standalone series (series group without recurrence)

`POST /v2/events/series` creates a named series without a recurrence rule. Individual events are linked with `POST /v2/events/{id}/series { "series_id": N }`.

## Event polls

To attach a poll to an event, pass `poll_ids: [...]` in the create or update body. All polls in the list must be owned by the caller (same `user_id` and `tenant_id`) — otherwise the endpoint returns `403 FORBIDDEN`. On create, the poll rows are updated `SET event_id = <new_event_id>`. On update, existing poll links for this event are first cleared and then the new list is applied.

## Online events

Set `is_online: true` on create or update. `online_link` carries the join URL (e.g. a video call link). `allow_remote_attendance: true` paired with `video_url` enables remote attendance support (shown in the edit form at `react-frontend/src/pages/events/CreateEventPage.tsx`). Both fields are stored on the `events` table and returned in the API response.

## Organiser check-in (time credit transfer)

`POST /v2/events/{id}/attendees/{attendeeId}/check-in` — requires auth. Only the organiser or a tenant admin can check in attendees. The check-in window opens 30 minutes before `start_time` and closes 24 hours after `end_time` (or `start_time` when no end time is set).

The check-in transfers time credits from the organiser to the attendee, proportional to the event duration (minimum 0.5 hours, maximum 24 hours). The transfer is atomic:

1. Organiser row is locked with `SELECT ... FOR UPDATE`; the organiser must have sufficient balance.
2. A `Transaction` record is created (`transaction_type: event_checkin`).
3. `users.balance` is decremented for the organiser and incremented for the attendee.
4. The attendee's RSVP status is updated to `attended`.

All four steps run inside a single DB transaction. If the organiser's balance is insufficient, the endpoint returns `422 INSUFFICIENT_BALANCE`.

The attendee roster (`GET /v2/events/{id}/attendees`) is accessible by the organiser and by members who have RSVP'd to the event. Non-RSVP'd members receive an empty roster (roster-privacy guard in `EventService::canViewEventRoster()`).

## Reminders and notifications

See [docs/modules/notifications.md](notifications.md) for the general notification architecture. Events-specific behaviour:

### Automatic reminders (EventReminderService)

`EventReminderService::sendDueReminders()` is called from the Laravel scheduler. It runs two fixed-interval scans (24 h and 1 h before `start_time`) and a configured-reminder scan.

- Each attendee with RSVP status `going` or `interested` who has not yet received the reminder type receives an in-app bell notification (`type: event_reminder`) and an email, both rendered in the recipient's `preferred_language` via `LocaleContext::withLocale()`.
- Delivery is de-duplicated by `event_reminder_sent` (idempotency) and `event_reminder_delivery_claims` (distributed claim; stale claims older than 1 hour are released for retry).
- If the email send fails, the delivery claim is released so the next scheduler run can retry. A permanently suppressed address (hard bounce/spam report) is skipped but marked sent, so the in-app bell still fires.
- The 24 h scan looks at events starting between 23 h 30 m and 24 h 30 m from now (a 30-minute lookahead window either side of the interval).

### User-configured reminders

Users can set per-event reminders at 60, 1440, or 10 080 minutes before start via `PUT /v2/events/{id}/reminders`. These rows live in `event_reminders` with `status = 'pending'` and `scheduled_for` set at the configured time before `start_time`. The configured-reminder scan processes rows where `scheduled_for <= NOW()` and `start_time > NOW()`. Delivery channels are `platform`, `email`, or `both`.

Pending reminders are cancelled automatically when the user removes their RSVP (`removeRsvp()`) or changes to `not_going`/`declined` (`cancelPendingRemindersForRsvp()`).

### Other event notifications

| Trigger | Recipients | Service method |
| --- | --- | --- |
| Event created | Configurable initial attendee set | `notifyEventCreated()` |
| Meaningful edit (title, start_time, end_time, location) | All going + interested attendees | `notifyEventUpdated()` |
| Cancellation or series delete | All going/interested/invited + waitlisted | `notifyCancellation()` |
| RSVP status change | Organiser (new going/interested); attendee (confirmation bell) | `notifyRsvp()` |

All notification emails are rendered in each recipient's `preferred_language` via `LocaleContext::withLocale()`. Subject-line keys are in `lang/en/notifications.php`; body keys are in `lang/en/svc_notifications_2.php` and `lang/en/emails_misc.php`.

## Federated visibility

Events carry a `federated_visibility` column (default `none`). Values other than `none` make the event visible to partner communities in the federation module. The field is set at create/update time. Federated event queries are handled by `FederationV2Controller::events()` at `GET /v2/federation/events`.

## Search integration

Events are indexed in Meilisearch under the `events` index with searchable fields `title`, `description`, `location`, and `organizer_name`. The index is filtered to `tenant_id` and `start_time >= now` so past events do not appear in search results. See [docs/modules/search.md](search.md) for the sync script and fallback behaviour.

## Admin actions

Admin endpoints at `/v2/admin/events/*` (controller: `AdminEventsController`):

- `GET /v2/admin/events` — paginated list with optional filters.
- `GET /v2/admin/events/{id}` — detail view.
- `DELETE /v2/admin/events/{id}` — hard delete; same cascade logic as the organiser path.
- `POST /v2/admin/events/{id}/cancel` — cancel on behalf of organiser.

All admin endpoints require the caller to be a tenant admin or platform super-admin (enforced in `BaseApiController::requireAdmin()`).

## Security and privacy invariants

- **Tenant isolation.** The `HasTenantScope` trait on `App\Models\Event` prevents any event row from being read or written outside its tenant. Raw queries include explicit `tenant_id` filters. Cross-tenant access is tested in `EventsControllerTest::test_cannot_access_event_from_different_tenant()`.
- **Authorisation.** Edit, cancel, delete, and image-upload all enforce organiser-or-admin ownership before any DB write.
- **Roster privacy.** The attendee list is only returned to the event organiser, admins, or users who have already RSVP'd to the event. Anonymous callers and non-RSVP'd members receive an empty list.
- **Capacity race protection.** RSVP to a full event runs inside a `SELECT ... FOR UPDATE` transaction to prevent overbooking under concurrent load.
- **Check-in balance guard.** The credit transfer is atomic and locks the organiser row with `FOR UPDATE` before decrementing, preventing overdrafts.
- **Reminder idempotency.** Double delivery is prevented at two levels: the `event_reminder_sent` insert-or-ignore marker and the `event_reminder_delivery_claims` distributed claim. A failed email claim is released within 1 hour for retry.

## Failure modes and recovery

| Failure | Symptom | Recovery |
| --- | --- | --- |
| Notification side effects fail at create time | Event is created successfully; side-effect error is logged at `WARNING` level | Rerun `EventNotificationService::notifyEventCreated()` manually for the affected event ID |
| Scheduler not running | Automatic 24 h/1 h reminders are not sent | Verify Laravel scheduler is active (`php artisan schedule:list`); run `php artisan schedule:run` to trigger manually |
| Email suppressed (hard bounce) | Reminder email skipped; in-app bell still sent; `event_reminder_sent` row is written | No recovery needed; the suppression list is correct behaviour |
| Stale delivery claim (worker crashed mid-send) | Reminder not delivered within 1 h | `releaseStaleReminderDeliveryClaim()` clears claims older than 1 h; the next scheduler run retries |
| `event_recurrence_rules` row missing for template | `generateOccurrences()` returns 0; no occurrences created | Inspect `event_recurrence_rules` for `event_id`; re-insert the rule and call `createRecurring()` with the same data, then delete the orphaned template |
| Series delete leaves stale RSVPs | Past-occurrence RSVPs survive (by design — attendance history) | Expected behaviour; no recovery needed |
| Organiser has insufficient balance for check-in | `422 INSUFFICIENT_BALANCE` | Organiser must receive time credits before checking in attendees |

## Test commands and key regression tests

```bash
# Run all event-related PHPUnit tests
vendor/bin/phpunit tests/Laravel/Feature/Controllers/EventsControllerTest.php
vendor/bin/phpunit tests/Laravel/Unit/Services/EventServiceTest.php
vendor/bin/phpunit tests/Laravel/Feature/Scheduling/RecurringScheduleRegressionTest.php
vendor/bin/phpunit tests/Laravel/Integration/EventEmailReliabilityTest.php
vendor/bin/phpunit tests/Laravel/Integration/EventNotificationStateTest.php
vendor/bin/phpunit tests/Laravel/Unit/Models/EventTest.php

# React frontend tests
cd react-frontend && npm test -- --testPathPattern="events"
```

Important regression tests:

| Test | File | What it protects |
| --- | --- | --- |
| `test_cannot_access_event_from_different_tenant` | `EventsControllerTest` | Cross-tenant isolation |
| `test_get_all_collapses_recurring_series_to_next_occurrence` | `EventsControllerTest` | Series card-collapse logic |
| `test_series_delete_notifies_future_attendees_once_and_skips_past_and_cancelled` | `EventsControllerTest` | Delete notification correctness |
| `test_cancel_notifies_rsvp_and_waitlisted_users_after_statuses_change` | `EventsControllerTest` | Cancellation notification scope |
| `test_update_image_cascades_cover_to_whole_series` | `EventsControllerTest` | Image propagation across series |
| `test_monthly_recurring_shift_generates_on_pattern_day_of_month_not_today` | `RecurringScheduleRegressionTest` | Monthly anchor stability |
| `test_event_monthly_occurrences_stay_anchored_to_month_end` | `RecurringScheduleRegressionTest` | Month-end clamping |
| `test_event_reminder_claim_releases_after_email_failure_and_allows_retry` | `EventEmailReliabilityTest` | Reminder retry on email failure |
| `test_configured_event_reminder_is_cancelled_when_rsvp_declined` | `EventEmailReliabilityTest` | Reminder cancellation on RSVP change |
| `test_rsvp_state_changes_only_when_status_changes` | `EventNotificationStateTest` | Notification deduplication |
