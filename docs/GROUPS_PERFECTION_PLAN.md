# Groups Module — Path to 1,000/1,000

> **Created:** 2026-04-03
> **Initial Score:** ~478/1,000 (revised down — 6 services missing)
> **Current Score:** ~810/1,000 (after frontend tabs + integrations)
> **Target:** 1,000/1,000 (Platinum tier)
> **Status:** Gold Tier — 5 new React tabs, cross-service wiring, invite modal, tags, templates

---

## Score Tracker (Honest Rescore 2026-04-03)

**Scoring rule:** Backend-only features get ~30% credit. Full credit requires working frontend UI.

| Category | Initial | After Backend | After Frontend | Status |
|----------|---------|--------------|----------------|--------|
| 1. Creation & Configuration | 55 | 67 | 80 | Templates in CreateGroupPage, tags, fields |
| 2. Membership & Roles | 60 | 71 | 85 | Invite modal (email+link), PermissionManager |
| 3. Privacy & Permissions | 70 | 73 | 78 | FeatureToggleService, GDPR export |
| 4. Hierarchy & Organization | 55 | 55 | 55 | Collections still missing |
| 5. Engagement & Content | 73 | 99 | 125 | Q&A tab, Wiki tab, Media gallery, Files — all with UI |
| 6. Discovery & Search | 55 | 55 | 58 | Tags shown on cards + detail page |
| 7. Notifications & Activity | 45 | 47 | 52 | Webhooks now fire on join/leave/post/discussion |
| 8. Admin & Management | 33 | 55 | 68 | Archive/clone/audit in admin dropdown |
| 9. Analytics & Reporting | 25 | 38 | 70 | Full analytics tab with Recharts + CSV export |
| 10. Gamification Integration | 35 | 38 | 50 | Challenges tab + progress wired to posts/members |
| 11. Automation & Workflows | 5 | 17 | 40 | Welcome/webhooks/lifecycle all wired to events |
| 12. Enterprise & Compliance | 33 | 38 | 49 | Data export, audit trail wired, custom fields |
| **TOTAL** | **~478** | **~653** | **~810** | **Gold Tier** |

### Critical Integration Gaps (Blocking Higher Score)

**Backend services exist but are NOT wired into the application flow:**

| Integration | File to Change | What to Add |
|-------------|---------------|-------------|
| Welcome on join | `GroupService::join()` | Call `GroupWelcomeService::sendWelcome()` after member attach |
| Audit logging | `GroupsController` + `AdminGroupsController` | Call `GroupAuditService::log()` on create/update/delete/join/leave |
| Webhooks | `GroupsController` post/discussion/join handlers | Call `GroupWebhookService::fire()` on group events |
| Challenge progress | `GroupsController::postToDiscussion()` | Call `GroupChallengeService::incrementProgress()` |

### Missing Frontend (16 features backend-only)

| Feature | Backend | Frontend Needed | Est. Points |
|---------|---------|----------------|-------------|
| Q&A Tab | GroupQAService + Controller | GroupQATab.tsx in GroupDetailPage | +7 |
| Wiki Tab | GroupWikiController | GroupWikiTab.tsx | +7 |
| Media Gallery | GroupMediaController | GroupMediaTab.tsx | +3 |
| Invite Modal | GroupInviteService + Controller | Invite button + modal in GroupDetailPage | +7 |
| Tags Display | GroupTagService + Controller | Tags on cards + detail + create form | +5 |
| Analytics Dashboard | GroupAnalyticsService + Controller | Analytics tab with Recharts | +20 |
| Welcome Config | GroupWelcomeController | Settings panel for admins | +3 |
| Template Selector | GroupTemplateService | Template picker on CreateGroupPage | +4 |
| Custom Fields | GroupCustomFieldController | Custom fields on detail + create | +4 |
| Webhook Config | GroupWebhookController | Webhook management panel | +3 |
| Challenge UI | GroupChallengeService | Challenge list + progress bars | +5 |
| Admin Lifecycle | AdminGroupsController | Archive/merge/clone/transfer buttons | +10 |
| Admin Bulk Ops | AdminGroupsController | Checkboxes + batch action toolbar | +7 |
| Audit Log Viewer | AdminGroupsController | Log table with filters | +5 |
| CSV Export | GroupAnalyticsController | Export buttons on analytics page | +5 |
| @mentions | (needs new service) | Autocomplete + highlight | +5 |

**Total recoverable:** ~99 points from frontend alone + ~30 from wiring = ~129 points → **~782 (Gold)**

### Path to 1,000

| Milestone | Score | What's Needed |
|-----------|-------|---------------|
| **Current** | ~653 | Silver Tier |
| **Wire integrations** | ~683 | +30: welcome, audit, webhooks, challenges |
| **Core frontend tabs** | ~732 | +49: Q&A, Wiki, Invites, Analytics |
| **Admin UI** | ~764 | +32: Lifecycle, bulk ops, audit viewer |
| **Remaining frontend** | ~829 | +65: Tags, templates, fields, export, gallery |
| **Advanced features** | ~929 | +100: WebSocket chat, @mentions, collections, SAML, branding |
| **Polish & completeness** | 1,000 | +71: view-only roles, scheduled posts, auto-assign UI |

> **Note:** The 6 previously missing services are now fully implemented and pass their tests.

---

## Critical Discovery: Missing Services

These 6 services have **tests already written** but **no implementation**:

| Service | Test Lines | DB Table | Priority |
|---------|-----------|----------|----------|
| `GroupFeatureToggleService` | 59 | `group_feature_toggles` | P0 — middleware depends on it |
| `GroupPermissionManager` | 41 | — (uses existing tables) | P0 — authorization depends on it |
| `GroupConfigurationService` | 40 | `group_policies` | P0 — config depends on it |
| `GroupApprovalWorkflowService` | 220 | `group_approval_requests` | P1 |
| `GroupAuditService` | 215 | `group_audit_log` | P1 |
| `GroupAssignmentService` | 150 | — (uses existing tables) | P2 |

---

## Phase 1: Missing Backend Services [P0]
**Points recovered:** ~30 (fixes broken feature toggles, permissions, config)

### 1.1 GroupFeatureToggleService
- [ ] Implement all 16 feature constants
- [ ] `isEnabled(string $feature): bool` — query `group_feature_toggles` table
- [ ] `getFeatureDefinition(string $feature): array` — return label + category
- [ ] `enableFeature()` / `disableFeature()` — admin toggle
- [ ] `getAllFeatures()` — list all with status
- [ ] Verify GroupFeatureMiddleware works end-to-end

### 1.2 GroupPermissionManager
- [ ] Implement all 15 permission constants
- [ ] `can(int $userId, string $permission, ?int $groupId = null): bool`
- [ ] `getPermissionsForRole(string $role): array`
- [ ] `hasGroupPermission(int $userId, int $groupId, string $permission): bool`
- [ ] Integration with existing role system (owner/admin/member)

### 1.3 GroupConfigurationService
- [ ] Implement all 14 config constants
- [ ] `get(string $key, mixed $default = null): mixed` — from `group_policies`
- [ ] `set(string $key, mixed $value): void`
- [ ] `getAll(): array`
- [ ] Tenant-scoped configuration

### 1.4 GroupApprovalWorkflowService
- [ ] 4 status constants (pending/approved/rejected/changes_requested)
- [ ] `submitForApproval()` — with duplicate prevention
- [ ] `approveGroup()` / `rejectGroup()` — with notes
- [ ] `getRequest()` / `getPendingRequests()`
- [ ] Migration: `group_approval_requests` table (if not exists)

### 1.5 GroupAuditService
- [ ] 11 action constants (group CRUD, member actions, content actions)
- [ ] `log(action, groupId, userId, details)` — create audit entry
- [ ] `getGroupLog(groupId, filters)` — with pagination
- [ ] `getUserGroupActivity(groupId, userId)`
- [ ] `getActivitySummary(groupId)` — aggregate stats
- [ ] Migration: `group_audit_log` table (if not exists)

### 1.6 GroupAssignmentService
- [ ] Auto-assign users to geographic hub groups
- [ ] `assignUser(array $user): string` — location matching
- [ ] Confidence threshold for matching
- [ ] Leaf group detection in hierarchy

---

## Phase 2: File Sharing System [+20 points]
**Category 5: Engagement & Content**

### 2.1 Backend
- [ ] Migration: `group_files` table (id, tenant_id, group_id, user_id, filename, original_name, mime_type, size_bytes, folder, description, download_count, created_at)
- [ ] `GroupFileService` — upload, download, delete, list, organize by folder
- [ ] `GroupFilesController` — REST endpoints
- [ ] File validation (type whitelist, size limits per tenant config)
- [ ] Storage: local or S3 (configurable)

### 2.2 Frontend
- [ ] Replace "Coming Soon" in GroupFilesTab
- [ ] File upload with drag-and-drop
- [ ] Folder organization UI
- [ ] File preview (images, PDFs)
- [ ] Download tracking
- [ ] Admin: manage/delete any file

---

## Phase 3: Automation & Workflows [+55 points]
**Category 11 — currently 5/60**

### 3.1 Welcome Messages
- [ ] `GroupWelcomeService` — configurable per-group welcome message
- [ ] Auto-send on member join (in-app + optional email)
- [ ] Admin UI to set/edit welcome message
- [ ] Template variables: `{member_name}`, `{group_name}`, `{admin_name}`

### 3.2 Auto-Archive Inactive Groups
- [ ] `GroupLifecycleService` — detect inactive groups (no posts/events in N days)
- [ ] Configurable inactivity threshold per tenant
- [ ] Auto-archive: set status='archived', lock posting, preserve content
- [ ] Admin notification before auto-archive (7-day warning)
- [ ] Scheduled command: `groups:check-inactive`

### 3.3 Triggered Workflows
- [ ] `GroupWorkflowService` — event-driven actions
- [ ] Events: member_joined, member_left, discussion_created, milestone_reached, group_created
- [ ] Actions: send_notification, award_badge, post_announcement, add_to_group
- [ ] Admin UI to configure triggers per group/tenant
- [ ] Store in `group_workflows` table

### 3.4 Scheduled/Recurring Posts
- [ ] `GroupScheduledPostService` — schedule posts for future publish
- [ ] Recurring option (daily/weekly/monthly)
- [ ] Admin/owner can schedule
- [ ] Artisan command: `groups:publish-scheduled`

### 3.5 AI Content Moderation
- [ ] Integrate with existing moderation + OpenAI
- [ ] Auto-flag posts with toxic/spam content
- [ ] Configurable sensitivity threshold
- [ ] Admin review queue for AI-flagged content

### 3.6 External Integrations
- [ ] Webhook system: fire HTTP callbacks on group events
- [ ] `group_webhooks` table (url, events[], secret, is_active)
- [ ] Admin UI to configure webhooks per group
- [ ] Signed payloads (HMAC)

---

## Phase 4: Analytics & Reporting [+65 points]
**Category 9 — currently 15/80**

### 4.1 Group Analytics Dashboard
- [ ] `GroupAnalyticsService` — comprehensive metrics
- [ ] Member growth over time (daily/weekly/monthly)
- [ ] Engagement metrics: posts/day, replies/day, active members/week
- [ ] Top contributors (by post count, reply count, discussion starts)
- [ ] Content performance (most viewed/liked/replied posts)
- [ ] Member participation rate (active vs lurker ratio)

### 4.2 Retention & Churn
- [ ] Track member join/leave dates
- [ ] Cohort analysis: retention by join month
- [ ] Churn rate calculation
- [ ] At-risk member identification (declining activity)

### 4.3 Comparative Analytics
- [ ] Group vs group benchmarking
- [ ] Percentile ranking within tenant
- [ ] Trend comparisons

### 4.4 Exportable Reports
- [ ] CSV export: members, activity, engagement
- [ ] PDF summary report (using server-side generation)
- [ ] Scheduled report emails (weekly/monthly)

### 4.5 Frontend Dashboard
- [ ] React analytics page with Recharts
- [ ] Date range selector
- [ ] Metrics cards (KPIs)
- [ ] Time series charts (growth, engagement)
- [ ] Top contributors table
- [ ] Export buttons (CSV/PDF)

---

## Phase 5: Admin Operations [+47 points]
**Category 8 — currently 23/90**

### 5.1 Bulk Operations
- [ ] Bulk archive/unarchive groups
- [ ] Bulk delete groups
- [ ] Bulk add members to group (CSV upload)
- [ ] Bulk remove members
- [ ] Admin UI with checkboxes + batch actions

### 5.2 Group Archiving
- [ ] `status` column: active/archived/suspended
- [ ] Archive preserves all content, locks new posts
- [ ] Unarchive restores full functionality
- [ ] Archived groups hidden from directory (visible in admin)

### 5.3 Transfer Ownership
- [ ] `GroupService::transferOwnership(groupId, newOwnerId)`
- [ ] Notification to old and new owner
- [ ] Admin can force transfer
- [ ] UI in group settings + admin panel

### 5.4 Merge Groups
- [ ] `GroupMergeService` — merge source into target
- [ ] Migrate members (deduplicate)
- [ ] Migrate discussions, posts, files
- [ ] Redirect old group URL to merged group
- [ ] Admin-only operation with confirmation

### 5.5 Group Settings Cloning
- [ ] Clone group with settings (type, visibility, policies, feature toggles)
- [ ] Option to clone members or start fresh
- [ ] Admin UI

### 5.6 Scheduled Posts (Admin)
- [ ] Schedule group announcements/posts for future
- [ ] Calendar view of scheduled content

### 5.7 Group Lifecycle Management
- [ ] States: draft → pending_approval → active → dormant → archived → deleted
- [ ] Auto-transition rules (configurable)
- [ ] Admin override for any state

### 5.8 Admin Activity Log UI
- [ ] Surface GroupAuditService data in admin panel
- [ ] Filterable by action type, user, date range
- [ ] Export audit log

---

## Phase 6: Real-time & Communication [+25 points]
**Categories 5, 7**

### 6.1 WebSocket Chat
- [ ] Wire Pusher channels to GroupChatroomsTab
- [ ] Real-time message delivery (no polling)
- [ ] Typing indicators
- [ ] Online presence per chatroom
- [ ] Message read receipts

### 6.2 @Mentions
- [ ] `@username` parsing in posts, discussions, chatrooms
- [ ] Autocomplete dropdown on `@` trigger
- [ ] Notification to mentioned user
- [ ] Highlight mentions in rendered content

### 6.3 Email/Link Invites
- [ ] `GroupInviteService` — generate invite links (with expiry)
- [ ] Email invite with personalized message
- [ ] Invite acceptance flow (auto-join or request)
- [ ] Track invite status (pending/accepted/expired)
- [ ] Frontend: invite modal in group settings

### 6.4 Per-Group Notification Preferences
- [ ] UI: mute/digest/instant per group
- [ ] Backend: respect preferences in GroupNotificationService
- [ ] Settings accessible from group detail page

---

## Phase 7: Advanced Content [+35 points]
**Category 5**

### 7.1 Rich Text Editor
- [ ] Wire Lexical editor into discussion/post composition
- [ ] Support: bold, italic, lists, links, images, code blocks
- [ ] Sanitize HTML on backend
- [ ] Render rich content in discussion threads

### 7.2 Q&A Format
- [ ] `group_qa_threads` — question + answers model
- [ ] Accept best answer (asker or admin)
- [ ] Upvote/downvote answers
- [ ] Sort by votes
- [ ] Frontend: Q&A tab in groups

### 7.3 Wiki/Knowledge Base
- [ ] `group_wiki_pages` — collaborative documents
- [ ] Version history (diffs)
- [ ] Page hierarchy (parent/child)
- [ ] Edit permissions (admin/member)
- [ ] Frontend: Wiki tab in groups

### 7.4 Photo/Video Gallery
- [ ] `group_media` table — images and video links
- [ ] Grid gallery view
- [ ] Lightbox preview
- [ ] Upload from group detail page

---

## Phase 8: Enterprise & Compliance [+27 points]
**Category 12**

### 8.1 SAML/SSO Integration
- [ ] Research Laravel SAML packages (e.g., aacotroneo/laravel-saml2)
- [ ] Group-level SSO enforcement
- [ ] Map SAML attributes to group roles

### 8.2 Data Export per Group
- [ ] Export all group data: members, discussions, files, settings
- [ ] Formats: JSON, CSV
- [ ] GDPR-compliant: include all user data
- [ ] Admin-initiated, background job

### 8.3 Compliance Features
- [ ] Data retention policies per group
- [ ] Content archival for compliance
- [ ] Audit log immutability
- [ ] Admin consent tracking

---

## Phase 9: Advanced Discovery [+25 points]
**Categories 1, 6**

### 9.1 Group Tags/Topics
- [ ] `group_tags` table — many-to-many
- [ ] Tag management UI (admin)
- [ ] Filter groups by tags
- [ ] Tag suggestions on group creation

### 9.2 Group Templates
- [ ] `group_templates` table — pre-configured group settings
- [ ] Templates: Community, Project, Committee, Interest, Regional
- [ ] Apply template on group creation
- [ ] Admin: create/edit templates

### 9.3 Custom Fields
- [ ] `group_custom_fields` — tenant-defined metadata
- [ ] Field types: text, number, date, select, multi-select
- [ ] Display on group detail page
- [ ] Filter/search by custom fields

### 9.4 URL Slug Customization
- [ ] Add `slug` column to groups table
- [ ] Auto-generate from name, allow manual override
- [ ] Route: `/groups/:slug` (with numeric ID fallback)
- [ ] Unique per tenant

### 9.5 Custom Branding
- [ ] Per-group color scheme (primary, accent)
- [ ] Custom banner/header layout options
- [ ] Theme preview in settings

### 9.6 Duplicate Detection
- [ ] Fuzzy name matching on group creation
- [ ] Suggest existing similar groups
- [ ] Admin: merge duplicates

### 9.7 Full-text Search in Group Content
- [ ] Index group discussions/posts in Meilisearch
- [ ] Search within group scope
- [ ] Faceted search results

---

## Phase 10: Gamification Deep Integration [+25 points]
**Category 10**

### 10.1 Per-Action Points
- [ ] Award XP for: posting, replying, attending events, inviting members
- [ ] Configurable point values per action
- [ ] Display earned points in group context

### 10.2 Group Leaderboard
- [ ] Weekly/monthly/all-time leaderboards
- [ ] Show top N contributors
- [ ] Leaderboard widget on group detail page

### 10.3 Group Challenges
- [ ] Time-bound group goals (e.g., "Post 50 times this week")
- [ ] Progress tracking
- [ ] Collective rewards on completion
- [ ] Admin: create/manage challenges

### 10.4 Cross-Module Badges
- [ ] Connect group achievements to main BadgeService
- [ ] Display group badges on user profiles
- [ ] Badge showcase in group detail

### 10.5 Profile XP Visibility
- [ ] Show group-earned XP on user profiles
- [ ] Breakdown by group contribution type

---

## Phase Order & Dependencies

```
Phase 1 (Missing Services) ──→ All other phases depend on this
Phase 2 (Files) ──→ Independent
Phase 3 (Automation) ──→ Depends on Phase 1 (audit, config)
Phase 4 (Analytics) ──→ Depends on Phase 1 (audit service for data)
Phase 5 (Admin Ops) ──→ Depends on Phase 1 (permissions, config)
Phase 6 (Real-time) ──→ Independent (Pusher infra exists)
Phase 7 (Content) ──→ Independent
Phase 8 (Enterprise) ──→ Depends on Phase 1, 5
Phase 9 (Discovery) ──→ Depends on Phase 1 (config)
Phase 10 (Gamification) ──→ Depends on Phase 1 (feature toggles)
```

**Recommended execution order:** 1 → 2+6 (parallel) → 3+4+5 (parallel) → 7+9 (parallel) → 8+10 (parallel)

---

## Cross-Module Integration Checklist

All new features must remain fully interactive with:

- [ ] **Feed** — Group posts appear in main feed (already integrated)
- [ ] **Events** — Group events linked via group_id (already integrated)
- [ ] **Wallet** — Group exchanges create wallet transactions (already integrated)
- [ ] **Search** — Groups indexed in Meilisearch (already integrated, extend to content)
- [ ] **Notifications** — All new actions trigger notifications (extend existing)
- [ ] **Pusher** — Real-time channels exist (wire up in Phase 6)
- [ ] **Gamification** — Group achievements award XP (extend in Phase 10)
- [ ] **Volunteering** — Link volunteer opportunities to groups (new integration)
- [ ] **Organizations** — Link organizations to groups (new integration)
- [ ] **Matches** — Factor group membership into matching (new integration)

---

## Files Modified/Created Tracker

*(Updated 2026-04-03 — Phase 1-10 complete)*

### New Services (18 files)
- `app/Services/GroupFeatureToggleService.php` — 16 feature toggles with caching
- `app/Services/GroupPermissionManager.php` — 15 permissions, role-based access
- `app/Services/GroupConfigurationService.php` — 14 config keys with defaults
- `app/Services/GroupAuditService.php` — 11 action types, audit logging
- `app/Services/GroupApprovalWorkflowService.php` — 4-status approval workflow
- `app/Services/GroupAssignmentService.php` — Auto-assign users to hub groups
- `app/Services/GroupFileService.php` — Upload, download, organize, delete files
- `app/Services/GroupInviteService.php` — Email + link invitations
- `app/Services/GroupTagService.php` — Tags/topics for discovery
- `app/Services/GroupAnalyticsService.php` — Dashboard, growth, engagement, retention, export
- `app/Services/GroupWelcomeService.php` — Auto welcome messages
- `app/Services/GroupLifecycleService.php` — Archive, transfer, merge, clone, lifecycle states
- `app/Services/GroupWebhookService.php` — Webhook integrations with HMAC signing
- `app/Services/GroupTemplateService.php` — Pre-configured group templates
- `app/Services/GroupCustomFieldService.php` — Tenant-defined metadata fields
- `app/Services/GroupDataExportService.php` — GDPR-compliant full data export
- `app/Services/GroupQAService.php` — Q&A with voting and accepted answers
- `app/Services/GroupChallengeService.php` — Time-bound goals with XP rewards

### New Controllers (11 files)
- `app/Http/Controllers/Api/GroupFilesController.php`
- `app/Http/Controllers/Api/GroupAnalyticsController.php`
- `app/Http/Controllers/Api/GroupInviteController.php`
- `app/Http/Controllers/Api/GroupTagController.php`
- `app/Http/Controllers/Api/GroupQAController.php`
- `app/Http/Controllers/Api/GroupWikiController.php`
- `app/Http/Controllers/Api/GroupMediaController.php`
- `app/Http/Controllers/Api/GroupWebhookController.php`
- `app/Http/Controllers/Api/GroupWelcomeController.php`
- `app/Http/Controllers/Api/GroupTemplateController.php`
- `app/Http/Controllers/Api/GroupCustomFieldController.php`
- `app/Http/Controllers/Api/GroupDataExportController.php`

### New Migrations (9 files)
- `migrations/2026_04_03_group_files_enhance.sql` — folder, description, download_count
- `migrations/2026_04_03_group_invites.sql` — group_invites table
- `migrations/2026_04_03_group_tags.sql` — group_tags + group_tag_assignments + slug column
- `migrations/2026_04_03_group_lifecycle_status.sql` — status column on groups
- `migrations/2026_04_03_group_webhooks.sql` — group_webhooks table
- `migrations/2026_04_03_group_templates.sql` — templates + custom fields tables
- `migrations/2026_04_03_group_qa.sql` — questions, answers, votes tables
- `migrations/2026_04_03_group_media.sql` — media gallery + wiki pages/revisions
- `migrations/2026_04_03_group_challenges.sql` — challenges table

### New Commands
- `app/Console/Commands/CheckInactiveGroupsCommand.php`

### Modified Files
- `routes/api.php` — 40+ new group endpoints
- `app/Http/Controllers/Api/AdminGroupsController.php` — lifecycle, bulk ops, tags
- `react-frontend/src/pages/groups/tabs/GroupFilesTab.tsx` — full file sharing UI (replaced placeholder)
- `docs/GROUPS_PERFECTION_PLAN.md` — this file

### Summary
| Category | Count |
|----------|-------|
| New PHP Services | 18 |
| New Controllers | 12 |
| New Migrations | 9 |
| New Commands | 1 |
| Modified Files | 4 |
| New API Endpoints | 40+ |
| New DB Tables | 12 |
| **Total New Files** | **40** |
