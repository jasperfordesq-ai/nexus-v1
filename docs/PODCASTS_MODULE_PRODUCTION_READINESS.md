# Podcasts Module Production Readiness

Last updated: 2026-06-03

## Status

The Podcasts module is marked alpha while it is validated in live communities. It is built to be production-capable behind the module flag, with ordinary members allowed to create shows by default and moderation disabled by default.

## Competitive Parity Target

The module is designed around the core feature set seen in leading podcast website tools and hosts:

- Blubrry PowerPress: multi-show publishing, RSS feeds, embedded players, SEO/distribution metadata, analytics, diagnostics, premium/private podcast options, and migration support.
- Castos Seriously Simple Podcasting: WordPress-native show/episode management, RSS control, imports, enhanced players, and private podcast support.
- Buzzsprout and Podbean: hosted media, directory distribution metadata, advanced stats, private podcasting, transcripts/chapters, and listener analytics.
- Apple Podcasts and Spotify requirements: public RSS feed, public show metadata, owner email, HTTP-accessible enclosures with MIME type and file length, byte-range capable media hosting, artwork, transcripts, and chapters where provided.

Reference pages checked:

- https://blubrry.com/support/powerpress-documentation/powerpress-features/
- https://blubrry.com/support/powerpress-documentation/powerpress-settings/
- https://castos.com/seriously-simple-podcasting/
- https://www.buzzsprout.com/features
- https://developers.podbean.com/enterprise/solutions/private-podcast
- https://podcasters.apple.com/support/823-podcast-requirements
- https://support.spotify.com/me-en/creators/article/podcast-specification-doc/

## User Experience

Members can:

- Browse published public and member-visible shows.
- Search, filter by category, and sort by newest, title, episode count, or followers.
- Open a show page with artwork, host details, visibility, follower count, RSS availability, and episodes.
- Follow or unfollow shows.
- Play episodes with skip and speed controls.
- View transcripts and chapter links when enabled.
- React to episodes when reactions are enabled.
- Report episodes to moderators.
- Create their own shows in Podcast Studio.
- Upload hosted audio or provide an external audio URL.
- Publish, archive, and delete their own shows and episodes.
- See a readiness checklist for public/RSS directory requirements.
- See media scan, scanner availability, and processing status for hosted uploads.

## Admin Experience

Tenant admins can:

- Review show and episode moderation queues.
- Approve, reject, or flag shows and episodes.
- See listen analytics, completion rate, unique listeners, top episodes, client breakdown, retention buckets, followers, media queue status, and open reports.
- Validate a show RSS feed and see blocking errors and recommended fixes.
- Resolve or dismiss member-submitted podcast reports.
- Review enriched report context: episode title, show title, reporter name, reason, details, and date.
- Use readiness cards to spot moderation, report, media, and RSS work at a glance.

## Module Configuration

Configuration is managed from the tenant module configuration for Podcasts.

Important keys:

- `podcasts.enabled`: enables the module.
- `podcasts.allow_member_show_creation`: allows ordinary members to create shows. Default: `true`.
- `podcasts.moderation_enabled`: requires admin approval before published shows/episodes become visible. Default: `false`.
- `podcasts.enable_rss_feed`: enables RSS feed output for public approved shows.
- `podcasts.enable_listen_analytics`: records privacy-preserving listen analytics.
- `podcasts.enable_episode_reactions`: enables member reactions.
- `podcasts.enable_transcripts`: allows transcripts in episode pages and RSS metadata.
- `podcasts.enable_chapters`: allows chapters in episode pages and RSS metadata.
- `podcasts.enable_private_shows`: allows members-only and private show/episode visibility. Default: `true`. (Hosted audio uploads are always accepted; there is no separate upload-enable flag — scanning/processing below govern post-upload handling.)
- `podcasts.enable_media_scanning`: marks hosted uploads for scanning. Until a real scanner is connected, the processing job records `scan_unavailable` rather than `clean`.
- `podcasts.enable_media_processing`: marks hosted uploads for processing/transcoding.
- `podcasts.media_storage_driver`: `local` or `cloud`.
- `podcasts.cloud_storage_disk`: Laravel filesystem disk to use when provider is `cloud`.
- `podcasts.max_audio_size_mb`: maximum hosted media upload size in MB. Default: `250`.
- `podcasts.max_shows_per_user`: maximum shows a single member can create. Default: `5`.

## Storage Modes

### Local Storage

Use local storage temporarily for pilot communities or development. Hosted audio is stored on the configured local Laravel disk under tenant/show/episode paths. Member/private media is served through signed API URLs.

Local storage is acceptable while:

- Upload volume is low.
- The server disk has enough headroom.
- Backups include podcast media.
- Apache/PHP can handle byte-range requests for the audio route.

### Cloud Storage

Cloud storage is the recommended production mode once the module is used at scale.

Set:

- `podcasts.media_storage_driver=cloud`
- `podcasts.cloud_storage_disk=<disk-name>`

Then configure the Laravel filesystem disk through environment variables. Supported choices depend on installed filesystem adapters. Typical production options are S3-compatible storage, Cloudflare R2, Azure Blob, or another disk that implements `temporaryUrl()` or `url()`.

Cloud checklist:

- Disk credentials are stored in environment variables, not committed.
- Private/member-only media is not public by default.
- The disk supports temporary signed URLs, or the public URL is safe for the episode visibility model.
- CDN and object storage support HTTP HEAD and byte-range requests for RSS directory playback.
- Lifecycle/retention policies match tenant policy.
- Backups and deletion workflows are tested.

## RSS Readiness

A show is directory-ready when:

- Show is public, published, and approved.
- RSS feeds are enabled in module configuration.
- Show has title, language, owner email, and a summary or description.
- Show has artwork URL.
- At least one approved public episode is published.
- Episode audio URL is HTTP-accessible.
- Episode audio has MIME type and file length.
- Hosted media URLs are reachable by podcast directories.

Admins can run feed validation from the Podcasts admin page. Validation errors block directory readiness; warnings are recommended fixes.

## Security Model

- All podcast models use tenant scoping.
- Raw report and subscription queries are explicitly tenant-scoped.
- Ordinary members can create shows only when the module configuration permits it.
- Member and private shows are hidden from anonymous public browsing.
- Private/member episode media uses signed API URLs.
- Report resolution is admin-only.
- RSS only includes public, published, approved episodes.
- Hosted upload MIME types are restricted to supported audio/video audio-compatible formats.
- Upload size is constrained by module configuration and PHP/server limits.
- Episode reports preserve moderator workflow rather than deleting evidence.

## External Infrastructure Still Required

The code provisions media scanning and processing states, but real production scanning/transcoding requires external workers or services. Hosted uploads remain visible as `scan_unavailable` until a real scanner updates them to `clean`. Before broad production rollout, connect those queues to:

- Malware scanning for uploads.
- Audio metadata extraction.
- Transcoding or normalization where required.
- Waveform generation if wanted.
- Failure alerts for stuck media jobs.

## Verification Commands

Backend focused suite:

```bash
vendor/bin/phpunit --no-coverage tests/Laravel/Feature/Controllers/PodcastControllerTest.php tests/Laravel/Unit/Services/PodcastConfigurationServiceTest.php --colors=always
```

Frontend:

```bash
cd react-frontend && npx tsc --noEmit --pretty false
cd react-frontend && npm run build
```

I18n:

```bash
npm run check:i18n:gaps
```

Static safety:

```bash
git diff --check
php -l app/Services/PodcastService.php
php -l app/Http/Controllers/Api/PodcastController.php
```

## Deployment Notes

Do not deploy just because the module is ready. Deployment requires an explicit operator instruction.

When deployment is requested:

- Push code to `main`.
- Use the blue-green deployment process in `docs/DEPLOYMENT.md`.
- Let Laravel migrations run through the deploy script unless this is an emergency rollback.
- Confirm tenant module configuration after deployment.
- Run RSS validation for the first public show.
- Confirm local or cloud media delivery with an actual uploaded test episode.
