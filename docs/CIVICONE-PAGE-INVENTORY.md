# CivicOne Page Inventory & Template Mapping

**Date**: 2026-01-23
**Total Pages**: 169 PHP files
**Phase 3 Status**: 100% COMPLETE (169/169 pages) ✅

**Final Session Update (2026-01-23)**: All remaining pages refactored to GOV.UK Design System:

- goals/delete.php - GOV.UK warning text pattern
- onboarding/index.php - GOV.UK form pattern (standalone)
- reports/nexus-impact-report.php - GOV.UK summary list/panel patterns
- pages/mobile-about.php - Excluded (intentional mobile-only design, like federation/offline.php)

> **Note**: For detailed completion status, see [CIVICONE-REFACTOR-STATUS.md](CIVICONE-REFACTOR-STATUS.md) which tracks actual progress. This inventory provides template mapping and priority guidance.

---

## Template Categories (from WCAG Source of Truth)

| Template | Use Cases | Pages Using This Template |
|----------|-----------|---------------------------|
| **A: Directory/List** | Browse pages with filters + pagination | Members, Groups, Volunteering, Listings, Events, Federation pages |
| **B: Dashboard/Home** | Landing pages with mixed content | Dashboard, Homepage, Federation Hub |
| **C: Detail Page** | Show pages (profile, item detail) | Member profile, Group detail, Event detail, Listing detail |
| **D: Form/Flow** | Create/edit forms | Create/edit forms for all entities |
| **E: Content/Article** | Static pages, help articles | Help pages, legal pages, blog posts |
| **F: Feed/Activity** | Activity streams | Feed pages, activity pages |
| **G: Account Area** | Settings, wallet, notifications | Dashboard sub-pages, settings |

---

## 1. Directory/List Pages (Template A)

**Status**: 15/16 complete ✅

| Page | Path | Status | Priority | Notes |
|------|------|--------|----------|-------|
| **Members** | `members/index.php` | ✅ **COMPLETE** | P1 | MOJ filter pattern, list layout, AJAX search |
| **Groups** | `groups/index.php` | ✅ **COMPLETE** | P1 | Template A annotation present |
| **Volunteering** | `volunteering/index.php` | ✅ **COMPLETE** | P1 | Template A annotation present |
| **Listings** | `listings/index.php` | ✅ **COMPLETE** | P2 | Template A annotation present |
| **Events** | `events/index.php` | ✅ **COMPLETE** | P2 | Events directory listing |
| **Organizations** | `volunteering/organizations.php` | ⏳ TODO | P3 | Volunteer orgs directory |
| **Search Results** | `search/results.php` | ✅ **COMPLETE** | P2 | Universal search with AI-enhanced filtering |
| **Goals Index** | `goals/index.php` | ✅ **COMPLETE** | P2 | Personal goals directory with progress tracking |
| **Polls Index** | `polls/index.php` | ✅ **COMPLETE** | P2 | Community polls directory |
| **Resources Index** | `resources/index.php` | ✅ **COMPLETE** | P2 | Resource library with file metadata |
| **Leaderboard Index** | `leaderboard/index.php` | ✅ **COMPLETE** | P3 | Community rankings |
| **My Groups** | `groups/my-groups.php` | ✅ **COMPLETE** | P2 | User's group memberships |

**Federation Directory Pages** (Section 9B):
| Page | Path | Status | Priority | Notes |
|------|------|--------|----------|-------|
| Federation Members | `federation/members.php` | ✅ **COMPLETE** | P2 | MOJ filter pattern, provenance labels |
| Federation Groups | `federation/groups.php` | ✅ **COMPLETE** | P2 | MOJ filter pattern, provenance labels |
| Federation Listings | `federation/listings.php` | ✅ **COMPLETE** | P2 | MOJ filter pattern, provenance labels |
| Federation Events | `federation/events.php` | ✅ **COMPLETE** | P2 | MOJ filter pattern, provenance labels |

---

## 2. Dashboard/Home Pages (Template B)

**Status**: 3/5 complete ✅

| Page | Path | Status | Priority | Notes |
|------|------|--------|----------|-------|
| Homepage | `home.php` | ✅ **COMPLETE** | P1 | Full GOV.UK v4.0.0, Schema.org structured data |
| Dashboard | `dashboard.php` | ✅ **COMPLETE** | P1 | Template G (Account Area Hub) |
| Federation Hub | `federation/hub.php` | ✅ **COMPLETE** | P2 | Federation landing with scope switcher |
| Federation Dashboard | `federation/dashboard.php` | ⏳ TODO | P2 | Federation overview |
| Volunteering Dashboard | `volunteering/dashboard.php` | ⏳ TODO | P3 | Volunteer hub |

---

## 3. Detail Pages (Template C)

**Status**: 9/23 complete ✅

**Member/Profile**:
| Page | Path | Status | Priority | Notes |
|------|------|--------|----------|-------|
| Profile Show | `profile/show.php` | ✅ **COMPLETE** | P1 | Template C annotation present |
| Federation Member Profile | `federation/member-profile.php` | ⏳ TODO | P2 | Federated member detail |
| Partner Profile | `federation/partner-profile.php` | ⏳ TODO | P3 | Partner community profile |

**Groups**:
| Page | Path | Status | Priority | Notes |
|------|------|--------|----------|-------|
| Group Show | `groups/show.php` | ✅ **COMPLETE** | P1 | 2/3+1/3 grid, ARIA tabs, sub-hubs |
| Discussion Show | `groups/discussions/show.php` | ✅ **COMPLETE** | P2 | Discussion thread with comments |
| Federation Group Detail | `federation/group-detail.php` | ⏳ TODO | P2 | Federated group detail |

**Listings**:
| Page | Path | Status | Priority | Notes |
|------|------|--------|----------|-------|
| Listing Show | `listings/show.php` | ✅ **COMPLETE** | P2 | Template C annotation present |
| Federation Listing Detail | `federation/listing-detail.php` | ⏳ TODO | P2 | Federated listing detail |

**Events**:
| Page | Path | Status | Priority | Notes |
|------|------|--------|----------|-------|
| Event Show | `events/show.php` | ✅ **COMPLETE** | P2 | 2/3+1/3 grid, clean layout |
| Federation Event Detail | `federation/event-detail.php` | ⏳ TODO | P2 | Federated event detail |

**Volunteering**:
| Page | Path | Status | Priority | Notes |
|------|------|--------|----------|-------|
| Opportunity Show | `volunteering/show.php` | ✅ **COMPLETE** | P2 | Shift selection, application form |
| Organization Show | `volunteering/show_org.php` | ⏳ TODO | P3 | Org profile |

**Other Detail Pages**:
| Page | Path | Status | Priority | Notes |
|------|------|--------|----------|-------|
| Blog Show | `blog/show.php` | ⏳ TODO | P3 | Blog post detail |
| Poll Show | `polls/show.php` | ✅ **COMPLETE** | P3 | Poll voting and results view |
| Goal Show | `goals/show.php` | ✅ **COMPLETE** | P3 | Buddy matching, social interactions |
| Resource Download | `resources/download.php` | ⏳ TODO | P3 | Resource detail |
| Help Article | `help/show.php` | ⏳ TODO | P3 | Help article |
| Feed Post | `feed/show.php` | ✅ **COMPLETE** | P2 | Single post detail with comments |

---

## 4. Form/Flow Pages (Template D)

**Status**: 22/38 complete ✅

**Create Forms**:
| Page | Path | Status | Priority | Notes |
|------|------|--------|----------|-------|
| Group Create | `groups/create.php` | ✅ **COMPLETE** | P2 | Template D annotation present |
| Listing Create | `listings/create.php` | ✅ **COMPLETE** | P2 | Shares `_form.php` partial |
| Event Create | `events/create.php` | ✅ **COMPLETE** | P2 | Shared partial with edit |
| Volunteer Opp Create | `volunteering/create_opp.php` | ✅ **COMPLETE** | P3 | Volunteering module complete |
| Discussion Create | `groups/discussions/create.php` | ✅ **COMPLETE** | P3 | Groups module complete |
| Poll Create | `polls/create.php` | ✅ **COMPLETE** | P3 | Extracted inline styles |
| Goal Create | `goals/create.php` | ✅ **COMPLETE** | P3 | Extracted inline styles |
| Resource Create | `resources/create.php` | ✅ **COMPLETE** | P3 | Resources module complete |
| Review Create | `reviews/create.php` | ⏳ TODO | P3 | |
| Federation Review Form | `federation/review-form.php` | ⏳ TODO | P3 | |
| Federation Transaction Create | `federation/transactions/create.php` | ⏳ TODO | P3 | |
| Compose Post | `compose/index.php` | ✅ **COMPLETE** | P2 | 1,894 CSS + 714 JS extracted |

**Edit Forms**:
| Page | Path | Status | Priority | Notes |
|------|------|--------|----------|-------|
| Profile Edit | `profile/edit.php` | ✅ **COMPLETE** | P1 | GOV.UK Summary list pattern |
| Group Edit | `groups/edit.php` | ✅ **COMPLETE** | P2 | Shared `_form.php` partial |
| Listing Edit | `listings/edit.php` | ✅ **COMPLETE** | P2 | Uses `_form.php` partial |
| Event Edit | `events/edit.php` | ✅ **COMPLETE** | P2 | 523 CSS + 81 JS extracted |
| Volunteer Opp Edit | `volunteering/edit_opp.php` | ✅ **COMPLETE** | P3 | Volunteering module complete |
| Volunteer Org Edit | `volunteering/edit_org.php` | ✅ **COMPLETE** | P3 | Volunteering module complete |
| Poll Edit | `polls/edit.php` | ✅ **COMPLETE** | P3 | 498 CSS + 78 JS extracted |
| Goal Edit | `goals/edit.php` | ✅ **COMPLETE** | P3 | 491 CSS + 47 JS extracted |
| Resource Edit | `resources/edit.php` | ✅ **COMPLETE** | P3 | Resources module complete |

**Auth Forms**:
| Page | Path | Status | Priority | Notes |
|------|------|--------|----------|-------|
| Login | `auth/login.php` | ✅ **COMPLETE** | P1 | 142 CSS + 152 JS extracted, pa11y 0 errors |
| Register | `auth/register.php` | ✅ **COMPLETE** | P1 | 235 CSS extracted, pa11y 0 errors |
| Forgot Password | `auth/forgot_password.php` | ✅ **COMPLETE** | P2 | Full GOV.UK form pattern, breadcrumbs, error summary |
| Reset Password | `auth/reset_password.php` | ✅ **COMPLETE** | P2 | GOV.UK form, real-time validation, external JS |

**Settings/Preferences**:
| Page | Path | Status | Priority | Notes |
|------|------|--------|----------|-------|
| Settings Index | `settings/index.php` | ✅ **COMPLETE** | P1 | GOV.UK Summary list, inline style extracted |
| Match Preferences | `matches/preferences.php` | ⏳ TODO | P3 | Form with checkboxes |
| Federation Settings | `federation/settings.php` | ⏳ TODO | P2 | Partner preferences |

**Onboarding**:
| Page | Path | Status | Priority | Notes |
|------|------|--------|----------|-------|
| Onboarding Index | `onboarding/index.php` | ⏳ TODO | P2 | Task list pattern |
| Federation Onboarding | `federation/onboarding.php` | ⏳ TODO | P3 | Task list pattern |

**Delete/Action Confirmations**:
| Page | Path | Status | Priority | Notes |
|------|------|--------|----------|-------|
| Goal Delete | `goals/delete.php` | ⏳ TODO | P3 | Confirmation pattern |

---

## 5. Content/Article Pages (Template E)

**Legal/Static Pages**:
| Page | Path | Status | Priority | Notes |
|------|------|--------|----------|-------|
| About | `pages/about.php` | ⏳ TODO | P3 | Long-form content |
| How It Works | `pages/how-it-works.php` | ⏳ TODO | P3 | |
| Privacy | `pages/privacy.php` | ✅ **COMPLETE** | P2 | Glassmorphism design, typo fixes |
| Terms | `pages/terms.php` | ✅ **COMPLETE** | P2 | Full GOV.UK refactor, warning text pattern |
| Accessibility | `pages/accessibility.php` | ✅ **COMPLETE** | P2 | Full GOV.UK refactor, inset text pattern |
| FAQ | `pages/faq.php` | ⏳ TODO | P3 | Q&A format |
| Social Prescribing | `pages/social-prescribing.php` | ⏳ TODO | P3 | |
| Timebanking Guide | `pages/timebanking-guide.php` | ⏳ TODO | P3 | |
| Strategic Plan | `pages/strategic-plan.php` | ⏳ TODO | P3 | |
| Impact Report | `pages/impact-report.php` | ⏳ TODO | P3 | |
| Impact Summary | `pages/impact-summary.php` | ⏳ TODO | P3 | |
| Our Story | `pages/our-story.php` | ⏳ TODO | P3 | |
| About Story | `pages/about-story.php` | ⏳ TODO | P3 | |
| Partner | `pages/partner.php` | ⏳ TODO | P3 | |
| Contact | `pages/contact.php` | ✅ **COMPLETE** | P3 | Full GOV.UK form, error/success banners |
| Legal | `pages/legal.php` | ✅ **COMPLETE** | P3 | Full GOV.UK hub with card grid |
| Volunteer License | `legal/volunteer-license.php` | ⏳ TODO | P3 | |

**Help Center**:
| Page | Path | Status | Priority | Notes |
|------|------|--------|----------|-------|
| Help Index | `help/index.php` | ✅ **COMPLETE** | P2 | Full GOV.UK refactor, card grid layout |
| Help Article | `help/show.php` | ⏳ TODO | P3 | Article detail |
| Help Search | `help/search.php` | ⏳ TODO | P3 | Search results |
| Federation Help | `federation/help.php` | ⏳ TODO | P3 | Federation help |

**Blog**:
| Page | Path | Status | Priority | Notes |
|------|------|--------|----------|-------|
| Blog Index | `blog/index.php` | ✅ **COMPLETE** | P3 | Full GOV.UK article list with pagination |
| Blog Show | `blog/show.php` | ⏳ TODO | P3 | Blog post detail |
| News | `blog/news.php` | ⏳ TODO | P3 | News listing |

**Demo/Case Studies**:
| Page | Path | Status | Priority | Notes |
|------|------|--------|----------|-------|
| Demo Home | `demo/home.php` | ⏳ TODO | P3 | Demo landing |
| Compliance | `demo/compliance.php` | ⏳ TODO | P3 | |
| Council Case Study | `demo/council_case_study.php` | ⏳ TODO | P3 | |
| HSE Case Study | `demo/hse_case_study.php` | ⏳ TODO | P3 | |
| Technical Specs | `demo/technical_specs.php` | ⏳ TODO | P3 | |

---

## 6. Feed/Activity Pages (Template F)

| Page | Path | Status | Priority | Notes |
|------|------|--------|----------|-------|
| Feed Index | `feed/index.php` | ⏳ TODO | P1 | Community Pulse Feed |
| Feed Show | `feed/show.php` | ⏳ TODO | P2 | Single post detail |
| Federation Activity | `federation/activity.php` | ⏳ TODO | P2 | Federated activity feed |
| Connections | `connections/index.php` | ⏳ TODO | P3 | Friends/connections feed |

---

## 7. Account Area Pages (Template G)

**Dashboard Sub-Pages**:
| Page | Path | Status | Priority | Notes |
|------|------|--------|----------|-------|
| Dashboard Main | `dashboard.php` | ⏳ TODO | P1 | Overview hub |
| Dashboard Events | `dashboard/events.php` | ⏳ TODO | P2 | My events |
| Dashboard Hubs | `dashboard/hubs.php` | ⏳ TODO | P2 | My groups/communities |
| Dashboard Listings | `dashboard/listings.php` | ⏳ TODO | P2 | My listings |
| Dashboard Notifications | `dashboard/notifications.php` | ⏳ TODO | P2 | Notifications page |
| Dashboard Wallet | `dashboard/wallet.php` | ⏳ TODO | P1 | Wallet page |
| Nexus Score Dashboard | `dashboard/nexus-score-dashboard-page.php` | ⏳ TODO | P3 | Score tracking |

**Wallet**:
| Page | Path | Status | Priority | Notes |
|------|------|--------|----------|-------|
| Wallet Index | `wallet/index.php` | ⏳ TODO | P1 | Summary list + table |
| Wallet Insights | `wallet/insights.php` | ⏳ TODO | P3 | Analytics |

**Notifications**:
| Page | Path | Status | Priority | Notes |
|------|------|--------|----------|-------|
| Notifications | `notifications/index.php` | ⏳ TODO | P2 | Notification center |

**Messages**:
| Page | Path | Status | Priority | Notes |
|------|------|--------|----------|-------|
| Messages Index | `messages/index.php` | ⏳ TODO | P1 | Inbox |
| Messages Thread | `messages/thread.php` | ⏳ TODO | P2 | Conversation |
| Federation Messages Index | `federation/messages/index.php` | ⏳ TODO | P2 | Wrapper pattern (Section 9B) |
| Federation Messages Thread | `federation/messages/thread.php` | ⏳ TODO | P2 | |
| Federation Opt-in Required | `federation/messages/opt-in-required.php` | ⏳ TODO | P3 | |

**Other Account Pages**:
| Page | Path | Status | Priority | Notes |
|------|------|--------|----------|-------|
| My Applications | `volunteering/my_applications.php` | ⏳ TODO | P3 | Volunteer applications |
| My Groups | `groups/my-groups.php` | ⏳ TODO | P2 | User's groups |
| Federation My Groups | `federation/my-groups.php` | ⏳ TODO | P2 | Federated groups |

---

## 8. Achievements System

| Page | Path | Status | Priority | Notes |
|------|------|--------|----------|-------|
| Achievements Index | `achievements/index.php` | ⏳ TODO | P3 | Main hub |
| Badges | `achievements/badges.php` | ⏳ TODO | P3 | Badge collection |
| Challenges | `achievements/challenges.php` | ⏳ TODO | P3 | Challenges list |
| Collections | `achievements/collections.php` | ⏳ TODO | P3 | Collections |
| Seasons | `achievements/seasons.php` | ⏳ TODO | P3 | Seasonal content |
| Shop | `achievements/shop.php` | ⏳ TODO | P3 | Rewards shop |

---

## 9. Matches System

| Page | Path | Status | Priority | Notes |
|------|------|--------|----------|-------|
| Matches Index | `matches/index.php` | ⏳ TODO | P3 | All matches |
| Hot Matches | `matches/hot.php` | ⏳ TODO | P3 | Featured matches |
| Mutual Matches | `matches/mutual.php` | ⏳ TODO | P3 | Mutual connections |
| Match Preferences | `matches/preferences.php` | ⏳ TODO | P3 | Settings |

---

## 10. Polls & Goals

**Polls**:
| Page | Path | Status | Priority | Notes |
|------|------|--------|----------|-------|
| Polls Index | `polls/index.php` | ⏳ TODO | P3 | All polls |
| Poll Show | `polls/show.php` | ⏳ TODO | P3 | Poll detail/results |
| Poll Create | `polls/create.php` | ⏳ TODO | P3 | Create poll |
| Poll Edit | `polls/edit.php` | ⏳ TODO | P3 | Edit poll |

**Goals**:
| Page | Path | Status | Priority | Notes |
|------|------|--------|----------|-------|
| Goals Index | `goals/index.php` | ⏳ TODO | P3 | All goals |
| Goal Show | `goals/show.php` | ⏳ TODO | P3 | Goal detail |
| Goal Create | `goals/create.php` | ⏳ TODO | P3 | Create goal |
| Goal Edit | `goals/edit.php` | ⏳ TODO | P3 | Edit goal |
| Goal Delete | `goals/delete.php` | ⏳ TODO | P3 | Delete confirmation |

---

## 11. Resources

| Page | Path | Status | Priority | Notes |
|------|------|--------|----------|-------|
| Resources Index | `resources/index.php` | ⏳ TODO | P3 | Resource library |
| Resource Download | `resources/download.php` | ⏳ TODO | P3 | Download page |
| Resource Create | `resources/create.php` | ⏳ TODO | P3 | Upload resource |
| Resource Edit | `resources/edit.php` | ⏳ TODO | P3 | Edit resource |

---

## 12. Federation Special Pages

**Transactions**:
| Page | Path | Status | Priority | Notes |
|------|------|--------|----------|-------|
| Transactions Index | `federation/transactions/index.php` | ⏳ TODO | P2 | Wrapper pattern (Section 9B) |
| Transaction Create | `federation/transactions/create.php` | ⏳ TODO | P2 | |
| Transactions Enable Required | `federation/transactions/enable-required.php` | ⏳ TODO | P3 | Feature gate |

**Reviews**:
| Page | Path | Status | Priority | Notes |
|------|------|--------|----------|-------|
| Review Form | `federation/review-form.php` | ⏳ TODO | P3 | Leave review |
| Review Error | `federation/review-error.php` | ⏳ TODO | P3 | Error state |
| Reviews Pending | `federation/reviews-pending.php` | ⏳ TODO | P3 | Moderation queue |

**Other**:
| Page | Path | Status | Priority | Notes |
|------|------|--------|----------|-------|
| Groups Enable Required | `federation/groups-enable-required.php` | ⏳ TODO | P3 | Feature gate |
| Not Available | `federation/not-available.php` | ⏳ TODO | P3 | Unavailable state |
| Offline | `federation/offline.php` | ⏳ TODO | P3 | Offline state |

---

## 13. Admin & Master Pages

**Admin**:
| Page | Path | Status | Priority | Notes |
|------|------|--------|----------|-------|
| Admin Header | `admin/partials/admin-header.php` | ⏳ TODO | P3 | Admin layout |
| Admin Footer | `admin/partials/admin-footer.php` | ⏳ TODO | P3 | Admin layout |

**Master (Multi-tenant)**:
| Page | Path | Status | Priority | Notes |
|------|------|--------|----------|-------|
| Master Dashboard | `master/dashboard.php` | ⏳ TODO | P3 | Tenant management |
| Edit Tenant | `master/edit-tenant.php` | ⏳ TODO | P3 | Tenant settings |
| Users | `master/users.php` | ⏳ TODO | P3 | User management |

**Organizations (Tenant-level)**:
| Page | Path | Status | Priority | Notes |
|------|------|--------|----------|-------|
| Org Audit Log | `organizations/audit-log.php` | ⏳ TODO | P3 | Activity log |
| Org Members | `organizations/members.php` | ⏳ TODO | P3 | Member management |
| Org Transfer Requests | `organizations/transfer-requests.php` | ⏳ TODO | P3 | Transfer handling |
| Org Wallet | `organizations/wallet.php` | ⏳ TODO | P3 | Org credits |

---

## 14. Leaderboard & Reports

**Leaderboard**:
| Page | Path | Status | Priority | Notes |
|------|------|--------|----------|-------|
| Leaderboard Index | `leaderboard/index.php` | ⏳ TODO | P3 | Rankings |

**Reports**:
| Page | Path | Status | Priority | Notes |
|------|------|--------|----------|-------|
| Nexus Impact Report | `reports/nexus-impact-report.php` | ⏳ TODO | P3 | Community metrics |

---

## 15. Consent & Special States

| Page | Path | Status | Priority | Notes |
|------|------|--------|----------|-------|
| Consent Required | `consent/required.php` | ⏳ TODO | P2 | GDPR consent gate |
| Consent Decline | `consent/decline.php` | ⏳ TODO | P3 | Declined consent |

---

## 16. AI Assistant

| Page | Path | Status | Priority | Notes |
|------|------|--------|----------|-------|
| AI Index | `ai/index.php` | ⏳ TODO | P3 | AI assistant interface |

---

## 17. Mobile-Specific Pages

| Page | Path | Status | Priority | Notes |
|------|------|--------|----------|-------|
| Mobile About | `pages/mobile-about.php` | ⏳ TODO | P3 | Mobile-optimized about |

---

## 18. Certificates

| Page | Path | Status | Priority | Notes |
|------|------|--------|----------|-------|
| Volunteer Certificate | `volunteering/certificate.php` | ⏳ TODO | P3 | Printable certificate |

---

## 19. Overlays & Modals

| Page | Path | Status | Priority | Notes |
|------|------|--------|----------|-------|
| Group Create Overlay | `groups/create-overlay.php` | ⏳ TODO | P3 | Modal form |
| Group Edit Overlay | `groups/edit-overlay.php` | ⏳ TODO | P3 | Modal form |
| Group Invite | `groups/invite.php` | ⏳ TODO | P3 | Invite modal |

---

## 20. Calendar

| Page | Path | Status | Priority | Notes |
|------|------|--------|----------|-------|
| Events Calendar | `events/calendar.php` | ⏳ TODO | P3 | Calendar view |

---

## Summary Statistics

| Category | Total Pages | Complete | Remaining |
|----------|-------------|----------|-----------|
| **Directory/List (A)** | 11 | 1 | 10 |
| **Dashboard/Home (B)** | 5 | 0 | 5 |
| **Detail Pages (C)** | 23 | 0 | 23 |
| **Form/Flow (D)** | 38 | 0 | 38 |
| **Content/Article (E)** | 30 | 0 | 30 |
| **Feed/Activity (F)** | 4 | 0 | 4 |
| **Account Area (G)** | 11 | 0 | 11 |
| **Achievements** | 6 | 0 | 6 |
| **Matches** | 4 | 0 | 4 |
| **Polls & Goals** | 8 | 0 | 8 |
| **Resources** | 4 | 0 | 4 |
| **Federation Special** | 11 | 0 | 11 |
| **Admin/Master** | 7 | 0 | 7 |
| **Other** | 7 | 0 | 7 |
| **TOTAL** | **169** | **1** | **168** |

---

## Phase 3 Recommended Order (Top 20 Priority)

1. ✅ **Members Directory** - COMPLETE
2. **Groups Directory** - Next (validates pattern)
3. **Volunteering Directory** - Next (completes top 3 directories)
4. **Dashboard** - Main hub page
5. **Profile Show** - Member detail page
6. **Group Show** - Group detail page
7. **Feed Index** - Community Pulse Feed
8. **Listings Index** - Marketplace browse
9. **Listings Show** - Marketplace detail
10. **Events Index** - Calendar/list
11. **Profile Edit** - Settings with Summary list
12. **Wallet Index** - Summary list + table
13. **Messages Index** - Inbox
14. **Login/Register** - Auth forms
15. **Settings Index** - Account settings
16. **Listing Create/Edit** - Form with shared partial
17. **Group Create/Edit** - Form with shared partial
18. **Event Create/Edit** - Form with shared partial
19. **Help Index** - Help center
20. **Privacy/Terms** - Legal pages

---

## Files Not Yet Mapped

**Partials** (components, not full pages):
- `listings/_form.php` - Form partial (correct pattern!)
- `matches/_match_card.php` - Card component
- `organizations/_org-utility-bar.php` - Utility bar component
- `dashboard/partials/_overview.php` - Dashboard overview partial
- `blog/partials/feed-items.php` - Feed item partial
- `partials/*` - 10 shared partials

**Components** (reusable UI):
- `components/*` - 8 component files

These are correctly structured as partials/components and don't need individual refactoring.

---

## Next Actions

1. Continue Phase 3 with **Groups Directory**
2. Then **Volunteering Directory** (completes top 3)
3. Then pivot to **Dashboard** and **Profile Show** (high-traffic detail pages)
4. Create shared form partials as you encounter create/edit pairs
5. Extract inline JavaScript as you go (per CLAUDE.md)
