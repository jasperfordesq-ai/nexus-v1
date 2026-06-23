# Groups Module Guide

Last reviewed: 2026-06-23

Audience: maintainers and contributors working on the Groups feature — membership flows, discussion threads, announcements, file sharing, chatrooms, and group moderation.

## Supported workflows

- **Browse & discover** — public groups are visible to all visitors; unauthenticated listing uses optional auth.
- **Create a group** — any authenticated member may create a group and automatically becomes its owner.
- **Public groups** — immediate membership on join (`status = active`).
- **Private groups** — join request creates a `pending` row; a group admin must accept before the user becomes active.
- **Discussions** — threaded forums visible to active members only.
- **Announcements** — admin-only broadcasts with optional pin, priority, and expiry.
- **File sharing** — members upload documents/media; admins or uploaders may delete.
- **Chatroom** — real-time group chat via Pusher; messages persist in `group_chatroom_messages`.
- **Group events** — events may be linked to a group via `events.group_id`; surfaced in the Events tab.
- **Subgroups** — groups may have child groups via `groups.parent_id`.
- **Group exchanges** — time-credit exchanges scoped to a group membership. Covered in [docs/modules/wallet-exchanges.md](wallet-exchanges.md).
- **Analytics** — growth, engagement, contributors, and retention dashboards for group admins.

## Tenant and feature-gate rules

- **Feature gate:** `groups`. Both the React frontend (`App.tsx`, `FeatureGate feature="groups"`) and the accessible GOV.UK frontend (`AlphaController`, `abort_unless(TenantContext::hasFeature('groups'), 403)`) enforce this. When disabled, group routes redirect to the dashboard.
- **Group exchange gate:** `group_exchanges` is a separate feature flag. See [docs/modules/wallet-exchanges.md](wallet-exchanges.md).
- **Tenant scoping:** the `Group` Eloquent model uses the `HasTenantScope` trait, which adds a `WHERE tenant_id = <current>` global scope to every query. The `Group::attachMember()` override explicitly copies `groups.tenant_id` into the pivot row to prevent scope drift.
- **Visibility in list queries:** the `getAll()` method filters by `visibility = public` for unauthenticated requests. A signed-in user additionally sees groups they own or belong to. Platform admins (`role IN (admin, tenant_admin, super_admin, god)`) see all private groups within the tenant.

## Key code locations

Routes are defined in [`routes/api.php`](../../routes/api.php). Do not copy the full endpoint table here — read the route file for the live list.

| Concern | Route prefix | Controller |
|---------|-------------|------------|
| Group CRUD, members, discussions, announcements | `/v2/groups/*` | `App\Http\Controllers\Api\GroupsController` |
| File uploads/downloads | `/v2/groups/{id}/files/*` | `App\Http\Controllers\Api\GroupFilesController` |
| Chatrooms and messages | `/v2/groups/{id}/chatrooms`, `/v2/group-chatrooms/*` | `App\Http\Controllers\Api\IdeationChallengesController` (delegates to `GroupChatroomService`) |
| Analytics | `/v2/groups/{id}/analytics/*` | `App\Http\Controllers\Api\GroupAnalyticsController` |
| Invites | `/v2/groups/{id}/invites/*` | `App\Http\Controllers\Api\GroupInviteController` |
| Admin operations | `/v2/admin/groups/*` | `App\Http\Controllers\Api\AdminGroupsController` |

Services:

| Service | Responsibility |
|---------|----------------|
| `App\Services\GroupService` | Core CRUD, join/leave, discussions, member management |
| `App\Services\GroupPermissionManager` | Permission constants and role checks |
| `App\Services\GroupAnnouncementService` | Announcement CRUD (admin-only write) |
| `App\Services\GroupFileService` | File uploads, downloads, folder management |
| `App\Services\GroupChatroomService` | Chatrooms, messages, pin/unpin |
| `App\Services\GroupModerationService` | Content flagging and moderator actions |
| `App\Services\GroupAuditService` | Audit log writes on every member/content action |
| `App\Services\GroupWebhookService` | Outbound webhook fires on join, discussion create, post, file upload |
| `App\Services\GroupWelcomeService` | Sends welcome message when a member is accepted |
| `App\Services\GroupNotificationService` | In-app bell notifications related to groups |

Models and tables:

| Model | Table | Notes |
|-------|-------|-------|
| `App\Models\Group` | `groups` | `HasTenantScope`, `parent_id` for subgroups, `cached_member_count`, `federated_visibility` |
| `App\Models\GroupDiscussion` | `group_discussions` | Thread header; posts are in `group_posts` |
| `App\Models\GroupPost` | `group_posts` | One per reply; first post created alongside the discussion |
| — | `group_members` | Pivot: `group_id`, `user_id`, `role`, `status`, `tenant_id` |
| — | `group_announcements` | `is_pinned`, `priority`, `expires_at`, scoped by `tenant_id` |
| — | `group_files` | `file_path`, `folder`, `download_count`, `tenant_id` |
| — | `group_chatrooms` | `is_default`, `is_private`, `category` |
| — | `group_chatroom_messages` | Cascade-deleted on chatroom delete |
| — | `group_chatroom_pinned_messages` | Admin-pinned messages |
| — | `group_content_flags` | Moderation reports |
| — | `group_bans` | Tenant-scoped bans; optional `expires_at` |

Frontend entry points (`react-frontend/src/`):

| File | Purpose |
|------|---------|
| `pages/groups/GroupsPage.tsx` | Group list and discovery |
| `pages/groups/GroupDetailPage.tsx` | Group detail shell, tab routing |
| `pages/groups/CreateGroupPage.tsx` | Create group form |
| `pages/groups/tabs/GroupFeedTab.tsx` | Member activity feed inside a group |
| `pages/groups/tabs/GroupDiscussionTab.tsx` | Discussion thread list and reader |
| `pages/groups/tabs/GroupAnnouncementsTab.tsx` | Announcement list (create/edit for admins) |
| `pages/groups/tabs/GroupFilesTab.tsx` | File browser, upload, folder filter |
| `pages/groups/tabs/GroupChatroomsTab.tsx` | Chatroom list and real-time messages |
| `pages/groups/tabs/GroupEventsTab.tsx` | Events linked to this group |
| `pages/groups/tabs/GroupMembersTab.tsx` | Member list, role management |
| `pages/groups/tabs/GroupAnalyticsTab.tsx` | Admin analytics dashboard |
| `pages/groups/tabs/GroupSubgroupsTab.tsx` | Child group list |

Tabs are controlled by `GroupTabConfig` (in `react-frontend/src/types/api.ts`). Each tab is individually togglable per tenant via `tenants.group_tabs`. The `useTenant().hasGroupTab(tab)` hook controls rendering.

## Roles and permissions

Every `group_members` row carries a `role` column. Three roles exist:

| Role | Who | Permissions |
|------|-----|-------------|
| `owner` | Group creator; exactly one per group | Edit, delete the group, manage members (including admins), post to discussions, invite members |
| `admin` | Promoted by owner | Edit group settings, manage members, post to discussions, invite members |
| `member` | Regular active member | Post to discussions, invite members |

Permission checks go through `App\Services\GroupPermissionManager`. The permission constants are:

- **Group-level:** `group_edit`, `group_delete`, `group_manage_members`, `group_post_discussion`, `group_invite_members`
- **Tenant-wide (admin users only):** `create_group`, `create_hub`, `edit_any_group`, `delete_any_group`, `moderate_content`, `manage_members`, `manage_settings`, `view_analytics`, `approve_groups`, `ban_members`

`GroupService::canModify()` returns true for group owners, group admins, and tenant platform admins. `GroupService::isPlatformAdmin()` checks `users.role IN (admin, tenant_admin, super_admin, god)` or the `is_super_admin` / `is_tenant_super_admin` flags.

Owner role changes are protected: the owner cannot leave while sole admin (`SOLE_ADMIN` error), their role cannot be changed by anyone except a platform admin, and they cannot be removed by `removeMember`.

Admin promotion sends a localised confirmation email to the promoted member via `LocaleContext::withLocale($member, ...)`.

## Membership lifecycle

```
[Unauthenticated visitor]
        │
        ├─ POST /v2/groups/{id}/join
        │
        │  public group  → status = 'active'  (immediate)
        │  private group → status = 'pending' (join request)
        │
        ├─ GET  /v2/groups/{id}/requests       (admin only)
        ├─ POST /v2/groups/{id}/requests/{userId}  action=accept|reject
        │
        └─ DELETE /v2/groups/{id}/membership   (leave)
```

A banned member (`status = 'banned'` in `group_members`) cannot rejoin. A `UniqueConstraintViolationException` on the pivot insert is treated as `ALREADY_MEMBER` (race-condition safety).

When a member joins or is accepted, `GroupWelcomeService::sendWelcome()` fires a welcome message and `GroupChallengeService::incrementProgress()` advances any active challenge counters.

## Discussions

Discussions require active membership. A discussion consists of a `GroupDiscussion` header (title) plus one or more `GroupPost` rows. The first post is created atomically with the discussion header.

All HTML in titles and content is sanitised: `strip_tags()` for titles (no tags allowed), `strip_tags()` with an allowlist for content (`<p><br><b><i><strong><em><ul><ol><li><a><blockquote>`). This prevents XSS.

Discussions are ordered with pinned ones first, then by descending ID. Listing and posting both enforce active membership.

`GroupMentionService::notifyMentioned()` is called after every discussion or post creation to fire `@mention` notifications.

## Announcements

Only group admins and owners may create, edit, or delete announcements. Members can read them.

Announcements support:
- `is_pinned` — pinned announcements sort to the top.
- `priority` — integer; higher values appear above lower ones within the same pin bucket.
- `expires_at` — expired announcements are excluded from the default list response but visible when `include_expired=true`.

## File sharing

Any active member may upload. Admins and the uploader may delete.

Constraints enforced by `GroupFileService`:
- Maximum file size: **25 MB**
- Allowed MIME types: common images (JPEG, PNG, GIF, WebP), PDF, Word, Excel, PowerPoint, plain text, CSV, Markdown, ZIP, RAR, MP4, WebM, MP3, WAV, OGG
- SVG is explicitly excluded because inline `<script>` tags in SVG constitute XSS when served inline.

Files are stored at `groups/{tenantId}/{groupId}/{uniqueName}` on the `local` disk. The download endpoint increments `group_files.download_count` on each access.

## Chatrooms

Groups have one or more named chatrooms stored in `group_chatrooms`. A default "General" chatroom is created on demand via `GroupChatroomService::ensureDefaultChatroom()` and cannot be deleted.

Private chatrooms (`is_private = 1`) are hidden from non-members. Public chatrooms within a group are visible to all members.

Messages are delivered in real time via the `GroupChatroomMessagePosted` Pusher event. The broadcast is non-critical — if it fails the message has already been persisted. Group admins and message authors may delete messages. Only admins may pin/unpin messages via `group_chatroom_pinned_messages`.

## Group events

Events are linked to groups via `events.group_id`. When a group is deleted, `events.group_id` is set to `NULL` (events are preserved, not deleted). Events linked to a group appear in the group's Events tab.

## Moderation

`GroupModerationService` supports content flagging and moderator actions:

| Action | Effect |
|--------|--------|
| `flag` | Creates a `group_content_flags` row with `status = pending` |
| `hide` | Marks the flag `resolved`, records the action |
| `delete` | Marks the flag `resolved`, records the action |
| `approve` | Marks the flag `approved` (content cleared) |

Content types that can be flagged: `group`, `discussion`, `post`.

Platform-wide bans are stored in `group_bans`. `GroupModerationService::isUserBanned()` checks the tenant-scoped ban table. Bans support an optional `expires_at` for temporary suspensions.

## Security and privacy invariants

1. **Private groups are invisible to non-members.** `GroupService::getAll()` filters private groups so only the owner, active members, and tenant admins can see them. `GroupService::canView()` is the authoritative check; controllers call it with `enforceVisibility = true`.
2. **Every query is tenant-scoped.** The `HasTenantScope` global scope on `Group` applies automatically. Direct DB queries in `GroupService` use a `whereIn('id', fn($q) => $q->select('id')->from('groups')->where('tenant_id', ...))` guard.
3. **Pivot rows carry `tenant_id`.** `Group::attachMember()` copies the group's own `tenant_id` into the pivot, not the ambient `TenantContext`. This prevents cross-tenant pollution when a platform admin operates across tenants.
4. **Only admins may write announcements.** Announcement create/update/delete call `GroupService::canModify()` which requires owner, admin, or platform admin role.
5. **File type filtering prevents executable uploads.** `GroupFileService` validates MIME types against an allowlist and deliberately excludes SVG.
6. **XSS prevention in discussions.** All user content passes through `strip_tags()` with an allowlist before persistence.
7. **Owner cannot be removed or demoted.** `removeMember`, `updateMemberRole`, and `leave` all reject operations that would target the owner or leave the group without any admin.

## Failure modes and recovery

| Symptom | Likely cause | Recovery |
|---------|-------------|----------|
| Join request accepted but member count does not increment | `cached_member_count` not updated in the `handleJoinRequest` path | Run `GroupService::recalculateMemberCount()` or update `cached_member_count` directly; the `activeMembers()` relation is always authoritative |
| Welcome email not sent after join request approval | `GroupWelcomeService::sendWelcome()` throws and is swallowed | Check `laravel.log` for `[warning]` lines from `GroupService`; the member is still active |
| Chatroom messages not delivering in real time | Pusher broadcast failed | Messages are persisted; users can reload. Check Pusher credentials in `.env` (`PUSHER_APP_*`) |
| Private group visible in search | `SearchService` does not apply visibility filtering in the basic `search()` path; only `unifiedSearch()` and `suggestions()` filter to public groups | Do not use the basic search endpoint to surface a private group's content |
| File upload fails with `UPLOAD_FAILED` | Disk quota or `local` disk misconfiguration | Check `storage/app` write permissions and disk quota |
| Group deletion leaves orphaned event records | By design — events are disassociated (`group_id = NULL`), not deleted | If cleanup is needed, query `events WHERE group_id IS NULL` and act accordingly |

## Test commands

```bash
# PHP unit tests
vendor/bin/phpunit tests/Laravel/Unit/Services/GroupServiceTest.php
vendor/bin/phpunit tests/Laravel/Unit/Services/GroupPermissionManagerTest.php
vendor/bin/phpunit tests/Laravel/Unit/Services/GroupModerationServiceTest.php
vendor/bin/phpunit tests/Laravel/Unit/Services/GroupAnalyticsServiceTest.php
vendor/bin/phpunit tests/Laravel/Unit/Services/GroupInviteServiceTest.php
vendor/bin/phpunit tests/Laravel/Feature/Controllers/GroupAnalyticsControllerTest.php

# React component tests
cd react-frontend && npx vitest run src/pages/groups/
```

Key regression tests to run before any change to the group membership or permission flow:

- `GroupServiceTest` — join/leave, pending request accept/reject, sole-admin guard, ban check, member role update
- `GroupPermissionManagerTest` — ROLE_PERMISSIONS matrix, tenant admin bypass, cross-tenant isolation
- `GroupModerationServiceTest` — flag, approve, hide, delete actions; ban check
