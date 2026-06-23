# Podcasts Module

Audience: maintainers and contributors working on the Podcasts feature — audio hosting, RSS distribution, listen analytics, or admin moderation.

> **Status: Alpha.** The module ships behind the `podcasts` tenant feature flag (default OFF). All routes and React pages are gated; the accessible-frontend surfaces are also present under `accessible-frontend/views/podcasts*.blade.php`.

---

## Overview

The Podcasts module lets community members create and publish podcast shows directly inside a tenant. Key workflows:

- **Creator** — create a show, upload or link episode audio, schedule publication, manage chapters and transcripts.
- **Listener** — browse and search shows, subscribe for episode notifications, react to and report episodes.
- **Admin** — moderate shows and episodes (approve/reject/flag), validate RSS feeds, triage episode reports, view listen analytics.

---

## Feature gate

The `podcasts` feature flag on `tenants.features` controls the entire module. Every API route calls `ensurePodcastsFeature()` (defined in `app/Http/Controllers/Api/Concerns/InteractsWithPodcasts.php`), which returns `HTTP 403 FEATURE_DISABLED` when the flag is absent. The React frontend wraps every podcast route in `<FeatureGate feature="podcasts" redirect="/">`.

To enable for a tenant:

```sql
UPDATE tenants SET features = JSON_SET(features, '$.podcasts', true) WHERE id = <tenant_id>;
```

Or toggle through the admin UI at `/admin/tenant-features`.

---

## Database schema

All tables carry `tenant_id` for row-level tenant isolation. There are no database-level foreign keys (matching the course/marketplace convention for loosely-coupled modules). All queries scope by `tenant_id` through Eloquent global scopes on the models.

| Table | Purpose |
|---|---|
| `podcast_shows` | One row per show; `owner_user_id`, lifecycle (`status`, `moderation_status`, `visibility`), iTunes/RSS metadata (`author_name`, `owner_email`, `copyright`, `funding_url`, `explicit`), counters (`episode_count`, `subscriber_count`) |
| `podcast_episodes` | Episodes; `show_id`, `author_user_id`, audio location (`audio_url`, `audio_storage_path`, `audio_storage_disk`, `audio_mime`, `audio_bytes`), media pipeline (`media_processing_status`, `media_scan_status`), scheduling (`scheduled_for`, `published_at`, `announced_at`), optional `transcript` / `cover_image_url` |
| `podcast_episode_chapters` | Chapter markers per episode (`starts_at_seconds`, `title`, `url`, `position`) |
| `podcast_episode_listens` | One row per deduped listen event; privacy-safe hashes of IP, user-agent, and session ID; `listened_seconds`, `completed`, `client_family`, `retention_bucket` |
| `podcast_episode_reactions` | Emoji/like reactions (`reaction` varchar); unique per `(tenant_id, episode_id, user_id, reaction)` |
| `podcast_show_subscriptions` | Member subscriptions to shows; `notify_new_episodes` flag |
| `podcast_episode_reports` | Member content reports; `reason`, `details`, `status` (`open`/`resolved`/`dismissed`/`escalated`), `reviewed_by`, `reviewed_at` |

Migrations (in order):
- `database/migrations/2026_06_03_000001_create_podcast_module_tables.php` — core tables
- `database/migrations/2026_06_03_000002_add_distribution_metadata_to_podcasts.php` — RSS/iTunes metadata columns
- `database/migrations/2026_06_03_000003_harden_podcast_media_and_moderation.php` — media pipeline columns, subscriptions, reports
- `database/migrations/2026_06_04_120500_add_announced_at_to_podcast_episodes.php` — idempotent announcement guard

---

## Key code locations

| Layer | File |
|---|---|
| Service (core logic) | `app/Services/PodcastService.php` |
| Tenant configuration | `app/Services/PodcastConfigurationService.php` |
| Member API controller | `app/Http/Controllers/Api/PodcastController.php` |
| Admin API controller | `app/Http/Controllers/Api/AdminPodcastController.php` |
| Shared controller concern | `app/Http/Controllers/Api/Concerns/InteractsWithPodcasts.php` |
| Async media job | `app/Jobs/ProcessPodcastEpisodeMedia.php` |
| Scheduled release command | `app/Console/Commands/ReleaseScheduledPodcastEpisodes.php` |
| Models | `app/Models/PodcastShow.php`, `PodcastEpisode.php`, `PodcastEpisodeChapter.php`, `PodcastEpisodeListen.php`, `PodcastEpisodeReaction.php` |
| React pages | `react-frontend/src/pages/podcasts/` |
| React audio player | `react-frontend/src/components/podcasts/PodcastAudioPlayer.tsx` |
| React API client | `react-frontend/src/lib/api/podcasts.ts` |
| React admin panel | `react-frontend/src/admin/modules/podcasts/PodcastsAdmin.tsx` |
| Accessible-frontend views | `accessible-frontend/views/podcasts.blade.php`, `podcast-detail.blade.php`, `podcast-episode.blade.php`, `commerce-podcast-*.blade.php` |

---

## API routes

Routes are defined in `routes/api.php`. See that file (or the project OpenAPI reference) for the canonical endpoint list. A summary follows.

**Public (no auth required):**

| Method | Path | Handler |
|---|---|---|
| `GET` | `/v2/podcasts` | Browse/search shows |
| `GET` | `/v2/podcasts/{showSlug}` | Show detail + episodes |
| `GET` | `/v2/podcasts/{showSlug}/{episodeSlug}` | Single episode |
| `GET` | `/v2/podcasts/{showSlug}/feed.xml` | RSS feed (iTunes-compatible) |
| `GET` | `/v2/podcasts/feed/{tenantId}/{showSlug}.xml` | RSS feed by numeric tenant ID (for aggregator subscriptions) |
| `GET` | `/v2/podcasts/media/{tenantId}/{episodeId}/audio` | Hosted audio stream / redirect |
| `GET` | `/v2/podcasts/transcripts/{tenantId}/{episodeId}.txt` | Episode transcript |
| `GET` | `/v2/podcasts/chapters/{tenantId}/{episodeId}.json` | Chapters (Podcast Namespace JSON) |
| `POST` | `/v2/podcasts/episodes/{episodeId}/listen` | Record a listen event |

**Authenticated members (Sanctum):**

| Method | Path | Purpose |
|---|---|---|
| `GET` | `/v2/podcasts/mine` | Caller's shows with all episodes |
| `POST` | `/v2/podcasts` | Create a show |
| `PUT` | `/v2/podcasts/{id}` | Update a show |
| `POST` | `/v2/podcasts/{id}/publish` | Publish a show |
| `POST` | `/v2/podcasts/{id}/archive` | Archive a show |
| `DELETE` | `/v2/podcasts/{id}` | Delete a show (cascades to episodes and audio files) |
| `POST` | `/v2/podcasts/{showId}/episodes` | Create an episode (JSON body or `multipart/form-data` with `audio` file) |
| `PUT` | `/v2/podcasts/{showId}/episodes/{episodeId}` | Update an episode |
| `POST` | `/v2/podcasts/{showId}/episodes/{episodeId}/publish` | Publish an episode |
| `POST` | `/v2/podcasts/{showId}/episodes/{episodeId}/archive` | Archive an episode |
| `DELETE` | `/v2/podcasts/{showId}/episodes/{episodeId}` | Delete an episode and its hosted audio |
| `POST` | `/v2/podcasts/{showId}/subscribe` | Toggle show subscription |
| `POST` | `/v2/podcasts/episodes/{episodeId}/reaction` | Toggle episode reaction |
| `POST` | `/v2/podcasts/episodes/{episodeId}/report` | Report an episode |

**Admin only:**

| Method | Path | Purpose |
|---|---|---|
| `GET` | `/v2/admin/podcasts` | Dashboard (shows, episodes, stats, top episodes, reports, analytics) |
| `POST` | `/v2/admin/podcasts/shows/{id}/moderate` | Approve/reject/flag a show |
| `GET` | `/v2/admin/podcasts/shows/{id}/validate-feed` | Validate a show's RSS feed |
| `POST` | `/v2/admin/podcasts/episodes/{id}/moderate` | Approve/reject/flag an episode |
| `POST` | `/v2/admin/podcasts/reports/{episodeId}/resolve` | Resolve episode reports |
| `GET` | `/v2/admin/config/podcasts` | Get tenant podcast configuration |
| `PUT` | `/v2/admin/config/podcasts/bulk` | Update tenant podcast configuration |

---

## Audio handling

Episodes support two audio storage modes, set at upload time:

**Hosted audio** — a file is uploaded via `multipart/form-data` with the `audio` field. `PodcastService::storeHostedAudio()` validates the MIME type (mp3, m4a, aac, wav, ogg, webm accepted), stores the file on the configured disk (`local` by default, or a cloud disk when `podcasts.media_storage_driver` is set to `cloud`), and writes `audio_storage_path` and `audio_storage_disk` on the episode. The `audio_url` is set to a signed in-app proxy URL (`/v2/podcasts/media/{tenantId}/{episodeId}/audio`) that expires after one hour and uses HMAC signature verification.

**External audio** — a plain HTTPS URL is accepted in the `audio_url` field. Hosted-audio columns are left null. The RSS feed uses the external URL directly for the `<enclosure>` element.

**Media pipeline** (async): when a file is uploaded with `podcasts.enable_media_processing` or `podcasts.enable_media_scanning` enabled, the `ProcessPodcastEpisodeMedia` queue job fires (`app/Jobs/ProcessPodcastEpisodeMedia.php`). It retries up to three times with a 30-second backoff. The current implementation is a provision hook — it marks unscanned media as `scan_unavailable` rather than `clean`, preventing unreviewed audio from being labelled safe. Real scanner and transcoder integrations can be dropped in here. If all retries are exhausted, `media_processing_status` is set to `failed` and a warning is logged.

**Audio delivery** (`GET /v2/podcasts/media/{tenantId}/{episodeId}/audio`):
- Local disk: served as a `BinaryFileResponse` with `Accept-Ranges: bytes` for Range support (seekable in browsers).
- Cloud disk: redirected to a 10-minute temporary URL via `Storage::disk(...)->temporaryUrl()`. If the driver package is missing (e.g. `league/flysystem-aws-s3-v3` not installed), the route falls back to the in-app proxy rather than 500-ing. This fallback is regression-tested in `tests/Laravel/Feature/Services/PodcastEpisodeAudioUrlFallbackTest.php`.
- Members-only or private episodes require either an active session or a valid HMAC signature (`?expires=<ts>&signature=<hex>`). Public episodes served through RSS get unsigned URLs so aggregators can fetch without signing.

**Allowed MIME types**: `audio/mpeg`, `audio/mp3`, `audio/mp4`, `audio/aac`, `audio/wav`, `audio/x-wav`, `audio/ogg`, `audio/webm`, `video/webm`. Default max upload size is 250 MB (configurable per tenant via `podcasts.max_audio_size_mb`).

---

## RSS / podcast distribution

When `podcasts.enable_rss_feed` is `true` (the default), each public show exposes an iTunes-compatible RSS 2.0 feed with Podcast Namespace 1.0 extensions.

Feed URL: `GET /v2/podcasts/{showSlug}/feed.xml`
Aggregator-stable URL: `GET /v2/podcasts/feed/{tenantId}/{showSlug}.xml`

The feed includes:
- iTunes channel metadata: `<itunes:author>`, `<itunes:owner>`, `<itunes:category>`, `<itunes:image>`, `<itunes:explicit>`, `<copyright>`.
- `<podcast:funding>` when `funding_url` is set.
- Per-episode `<enclosure>` (with `length` and `type`), `<itunes:duration>` (HH:MM:SS), `<itunes:episodeType>` (`full`/`trailer`/`bonus`), `<itunes:season>`, `<itunes:episode>`.
- `<podcast:transcript>` (plain-text URL) when `podcasts.enable_transcripts` is `true` and a transcript is stored.
- `<podcast:chapters>` (JSON Podcast Namespace chapters URL) when `podcasts.enable_chapters` is `true` and chapters exist.

The feed only includes published, approved, public-or-inherit-visible episodes. Future-scheduled episodes are excluded until their `scheduled_for` time arrives. Up to 300 episodes are included per feed.

Episodes with hosted audio use unsigned proxy URLs in the feed so podcast aggregators can fetch audio without time-expiring signatures.

`GET /v2/admin/podcasts/shows/{id}/validate-feed` runs a pre-submission validation and returns `{valid, errors[], warnings[]}` for feed-level problems (missing artwork, missing `owner_email`) and per-episode problems (missing audio URL or MIME type).

---

## Scheduled publishing

Episodes can be scheduled by setting `scheduled_for` to a future timestamp when creating or updating an episode. Publishing such an episode sets `published_at = scheduled_for` and does not immediately notify subscribers.

The `podcasts:release-due` Artisan command (backed by `ReleaseScheduledPodcastEpisodes`) should run on a frequent schedule (every minute is appropriate). It queries all published, approved, not-yet-announced episodes whose `scheduled_for` has passed, sets `announced_at` via a conditional UPDATE (preventing duplicate announcement across concurrent runs), posts a feed activity, and notifies subscribers.

The `announced_at` column acts as an idempotent guard: the publish path, the moderation-approval path, and the scheduler all call `PodcastService::announceEpisode()`, which uses `UPDATE ... WHERE announced_at IS NULL` and returns early if the row was already claimed.

To run manually:

```bash
php artisan podcasts:release-due
php artisan podcasts:release-due --limit=50
```

---

## Subscriptions and notifications

Members subscribe to a show via `POST /v2/podcasts/{showId}/subscribe`, which toggles the subscription. The `notify_new_episodes` field (default `true`) controls whether the subscriber receives an in-app notification when a new episode goes live. Subscriber counts on `podcast_shows.subscriber_count` are kept in sync with a `lockForUpdate` recount to avoid race conditions.

---

## Listen analytics

When `podcasts.enable_listen_analytics` is `true` (the default), `POST /v2/podcasts/episodes/{episodeId}/listen` records a listen event. The deduplication window is six hours: within that window, a second listen from the same session hash (or user ID, or IP+UA hash combination) updates the existing row's `listened_seconds` (taking the maximum) rather than creating a new row. `listened_seconds` is also clamped to `duration_seconds` to prevent client inflation of completion metrics.

Stored fields are privacy-preserving: IP, user-agent, and session ID are stored only as SHA-256 hashes. No raw PII is retained.

The admin dashboard (`GET /v2/admin/podcasts`) returns aggregate stats:
- Total and completed listen counts, completion rate, unique listener count.
- Client-family breakdown (browser/app family extracted from user-agent).
- Retention bucket breakdown (what proportion of episodes listeners completed: 0–25%, 25–50%, 50–75%, 75–100%, 100%+).
- Top 10 episodes by `listen_count`.

---

## Moderation

When `podcasts.moderation_enabled` is `true`, every newly created or published show and episode is placed in `moderation_status = pending` and must be approved by an admin before it appears publicly.

When moderation is off (the default), content is set to `approved` immediately on creation.

Episode reports from members are stored in `podcast_episode_reports`. When moderation is enabled, the first report sets `moderation_status = flagged` and hides the episode. When moderation is off, auto-flagging requires reports from at least three distinct members (`REPORT_AUTO_FLAG_THRESHOLD = 3`) to prevent a single bad actor from suppressing content. Admins resolve reports via `POST /v2/admin/podcasts/reports/{episodeId}/resolve` (status: `resolved`, `dismissed`, or `escalated`); resolving or dismissing clears the auto-flag and restores the episode.

---

## Tenant-level configuration

All settings are stored in `tenant_settings` under the `podcasts` category and read/written by `PodcastConfigurationService`. Values are cached for 5 minutes per tenant.

| Key | Default | Purpose |
|---|---|---|
| `podcasts.allow_member_show_creation` | `true` | When `false`, only admins can create shows |
| `podcasts.moderation_enabled` | `false` | Pre-publication review queue for all content |
| `podcasts.enable_rss_feed` | `true` | Expose `feed.xml` endpoints |
| `podcasts.enable_private_shows` | `true` | Allow `members` and `private` visibility |
| `podcasts.enable_transcripts` | `true` | Accept and expose episode transcripts |
| `podcasts.enable_chapters` | `true` | Accept and expose chapter markers |
| `podcasts.enable_episode_reactions` | `true` | Reactions on episodes |
| `podcasts.enable_listen_analytics` | `true` | Record listen events |
| `podcasts.max_shows_per_user` | `5` | Per-user show limit (0 = unlimited) |
| `podcasts.max_audio_size_mb` | `250` | Upload size ceiling |
| `podcasts.media_storage_driver` | `local` | `local` or `cloud` |
| `podcasts.cloud_storage_disk` | `s3` | Laravel disk name for cloud storage |
| `podcasts.cloud_cdn_base_url` | (empty) | CDN prefix for cloud audio URLs |
| `podcasts.enable_media_scanning` | `true` | Queue a scan job after audio upload |
| `podcasts.enable_media_processing` | `true` | Queue a processing job after audio upload |

Configuration can be read and written via the admin API (`GET /v2/admin/config/podcasts`, `PUT /v2/admin/config/podcasts/bulk`) or through the admin React panel under Podcasts settings.

---

## Visibility and access control

Show visibility: `public` (anonymous access), `members` (logged-in only), `private` (owner and admins only). Episode visibility: `inherit` (follows the show), or overridden to `public`, `members`, or `private`.

`PodcastService::canViewShow()` and `canViewEpisode()` enforce these rules. The show and episode API endpoints return HTTP 404 (not 403) when access is denied to avoid disclosing the existence of private content.

Future-scheduled episodes are hidden from all non-owner/non-admin viewers even after they are "published" — the embargo is enforced both in `canViewEpisode()` and in the `scopePublished()` Eloquent scope used by browse queries and RSS.

---

## React frontend entry points

| Page | Route | Notes |
|---|---|---|
| Browse shows | `/podcasts` | Public, feature-gated, search + category + sort filters |
| Show detail | `/podcasts/:showSlug` | Public or members-only depending on visibility |
| Episode detail | `/podcasts/:showSlug/:episodeSlug` | Public or members-only |
| Podcast Studio | `/podcasts/studio` | Auth required; show/episode management |

The `PodcastAudioPlayer` component (`react-frontend/src/components/podcasts/PodcastAudioPlayer.tsx`) handles in-browser playback. After playback begins, the player posts listen events to the API to record progress.

---

## Security and privacy invariants

- All queries include a `tenant_id` filter. The Eloquent global scope on `PodcastShow` and `PodcastEpisode` enforces this automatically; the handful of `withoutGlobalScopes()` calls (audio proxy, transcript, chapters, RSS by tenant ID) set `TenantContext` explicitly before querying.
- Hosted audio URLs for non-public content are signed with HMAC (app-key-derived secret, 1-hour TTL). The signature is verified on every audio request via `hasValidMediaSignature()`.
- Listen analytics store only hashed PII (SHA-256 of IP, user-agent, session ID). No raw values are persisted.
- Episode reports require authentication; the report reason must be non-empty.
- Upload MIME types are validated against an allowlist; the storage path uses `bin2hex(random_bytes(12))` for the filename to prevent enumeration.
- Content reports auto-flag episodes at a threshold of three distinct reporters (when moderation is off) to mitigate single-reporter griefing of creators.
- Cloud audio redirect uses `temporaryUrl()` (10-minute expiry) rather than a long-lived public URL. If `temporaryUrl()` is unavailable on the configured disk, the route returns 404 rather than leaking the storage path.

---

## Tests

```bash
# Feature / integration tests
vendor/bin/phpunit --filter PodcastControllerTest
vendor/bin/phpunit --filter PodcastEpisodeAudioUrlFallbackTest
vendor/bin/phpunit --filter PodcastConfigurationServiceTest
vendor/bin/phpunit --filter PodcastsCategoryParityTest

# All podcast-related tests in one run
vendor/bin/phpunit --filter Podcast

# E2E smoke
npx playwright test e2e/tests/podcasts/podcasts-smoke.spec.ts --project=chromium-modern
```

Key regression tests:

| Test | Guards against |
|---|---|
| `PodcastControllerTest` | Full CRUD, audio upload, listen dedup, subscription toggle, RSS output, report flow, auth/tenant isolation |
| `PodcastEpisodeAudioUrlFallbackTest` | Missing cloud disk driver (e.g. `league/flysystem-aws-s3-v3` not installed) causing a fatal on audio URL generation (Sentry NEXUS-PHP-2K) |
| `PodcastConfigurationServiceTest` | Default values, typed persistence, tenant scoping of config |
| `PodcastsCategoryParityTest` | Accessible-frontend category filter parity with the React browse page |

---

## Failure modes and recovery

**Audio upload fails mid-write** — the `storeHostedAudio()` method throws `\InvalidArgumentException('Podcast media storage failed')`. The episode row is already saved with `audio_url = 'podcast-hosted://pending'`. The creator must delete and re-upload. There is no automatic retry for failed initial uploads.

**Media job exhausts retries** — `ProcessPodcastEpisodeMedia::failed()` sets `media_processing_status = failed` and logs a warning. The episode is still accessible but the scan or processing result is unavailable. Monitor via the admin dashboard stat `pending_media_processing`.

**Cloud disk driver unavailable** — audio URL generation falls back to the in-app proxy (`/v2/podcasts/media/…/audio`) instead of throwing, preventing a 500 on episode detail pages. The fallback is logged at `warning` level so it is visible in application logs and Sentry.

**Scheduled episode missed** — if the `podcasts:release-due` command is not running (scheduler down), episodes past their `scheduled_for` will not be announced. Re-running the command at any time will announce all outstanding due episodes. The `announced_at` guard prevents double-notifications.

**Duplicate announce race** — two concurrent processes (e.g. publish + scheduler) calling `announceEpisode()` at the same moment are protected by the conditional `UPDATE WHERE announced_at IS NULL`. Only one wins the row; the other exits early without re-notifying subscribers.

**RSS feed returns 404** — common causes: show `visibility` is not `public`, `status` is not `published`, `moderation_status` is not `approved`, or `podcasts.enable_rss_feed` is `false`. Run `GET /v2/admin/podcasts/shows/{id}/validate-feed` to get a structured error list.
