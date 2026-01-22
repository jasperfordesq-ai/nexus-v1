# CivicOne Refactoring - Remaining Pages

**Last Updated**: 2026-01-21
**Current Progress**: 81/169 pages (47.9%)
**Remaining**: 88 pages

---

## ✅ COMPLETED MODULES

1. **Goals** - 4/4 pages ✅ COMPLETE
2. **Polls** - 4/4 pages ✅ COMPLETE
3. **Resources** - 4/4 pages ✅ COMPLETE
4. **Auth** - 4/4 pages ✅ COMPLETE (Login, Register, Forgot, Reset)
5. **Events** - 6/6 pages ✅ COMPLETE (Index, Show, Create, Edit, Calendar, Form)
6. **Search & Discovery** - 2/2 pages ✅ COMPLETE (Search Results, Leaderboard)
7. **Groups** - 8/8 pages ✅ COMPLETE (Index, Show, Create, Edit, My Groups, Invite, Create Overlay, Edit Overlay, + 2 Discussions)
8. **Matches** - 1/1 main page ✅ COMPLETE (3 sub-pages remaining)

---

## 📋 PRIORITY 1: Quick Wins (Complete Remaining Modules)

### Resources Module (4 pages) - NEXT RECOMMENDED
| Page | Path | Template Type | Estimated Effort |
|------|------|---------------|-----------------|
| Resources Index | `resources/index.php` | Template A (Directory) | Low |
| Resource Show | `resources/show.php` | Template C (Detail) | Low |
| Resources Create | `resources/create.php` | Template D (Form) | Low |
| Resources Edit | `resources/edit.php` | Template D (Form) | Low |

**Why Priority 1**: Small module, follows established patterns, quick completion.

---

## 📋 PRIORITY 2: Core User Flows (Already Started)

### Auth Module (4/4) - COMPLETE ✅
| Page | Path | Status | Template Type |
|------|------|--------|---------------|
| ✅ Login | `auth/login.php` | COMPLETE | Template D |
| ✅ Register | `auth/register.php` | COMPLETE | Template D |
| ✅ Forgot Password | `auth/forgot_password.php` | COMPLETE | Template D |
| ✅ Reset Password | `auth/reset_password.php` | COMPLETE | Template D |

### Volunteering Module (9 remaining)
| Page | Path | Template Type | Notes |
|------|------|---------------|-------|
| ✅ Index | `volunteering/index.php` | COMPLETE | Directory |
| ✅ Show | `volunteering/show.php` | COMPLETE | Detail |
| Create Opportunity | `volunteering/create_opp.php` | Template D | Form |
| Edit Opportunity | `volunteering/edit_opp.php` | Template D | Form |
| Edit Organization | `volunteering/edit_org.php` | Template D | Form |
| Dashboard | `volunteering/dashboard.php` | Template G | Account hub |
| My Applications | `volunteering/my_applications.php` | Template A | List |
| Organizations | `volunteering/organizations.php` | Template A | Directory |
| Show Organization | `volunteering/show_org.php` | Template C | Detail |
| Certificate | `volunteering/certificate.php` | Template E | Content |

### Events Module (6/6) - COMPLETE ✅

| Page | Path | Status | Template Type |
|------|------|--------|---------------|
| ✅ Index | `events/index.php` | COMPLETE | Template A |
| ✅ Show | `events/show.php` | COMPLETE | Template C |
| ✅ Create | `events/create.php` | COMPLETE | Template D |
| ✅ Edit | `events/edit.php` | COMPLETE | Template D |
| ✅ Calendar | `events/calendar.php` | COMPLETE | Custom (extracted 510 lines CSS + 84 lines JS) |
| ✅ _form partial | `events/_form.php` | COMPLETE | Partial |

---

## 📋 PRIORITY 3: Account Area Completion

### Dashboard Components (4 remaining - claimed complete but missing annotations)
| Page | Path | Status | Notes |
|------|------|--------|-------|
| Dashboard Events | `dashboard/events.php` | Needs verification | May have Template G |
| Dashboard Hubs | `dashboard/hubs.php` | Needs verification | May have Template G |
| Dashboard Listings | `dashboard/listings.php` | Needs verification | May have Template G |
| Dashboard Notifications | `dashboard/notifications.php` | Needs verification | May have Template G |
| Dashboard Wallet | `dashboard/wallet.php` | Needs verification | May have Template G |
| Nexus Score Dashboard | `dashboard/nexus-score-dashboard-page.php` | TODO | Custom |
| Overview Partial | `dashboard/partials/_overview.php` | TODO | Partial |

### Notifications (1 page)
| Page | Path | Template Type |
|------|------|---------------|
| Notifications Index | `notifications/index.php` | Template G |

### Wallet (1 remaining)
| Page | Path | Status | Template Type |
|------|------|--------|---------------|
| ✅ Wallet Index | `wallet/index.php` | COMPLETE | Template G |
| Wallet Insights | `wallet/insights.php` | TODO | Template G |

---

## 📋 PRIORITY 4: Search & Discovery - COMPLETE ✅

### Search (1/1) - COMPLETE ✅

| Page | Path | Template Type |
|------|------|---------------|
| ✅ Search Results | `search/results.php` | Template A (extracted inline CSS + JS) |

### Leaderboard (1/1) - COMPLETE ✅

| Page | Path | Template Type |
|------|------|---------------|
| ✅ Leaderboard Index | `leaderboard/index.php` | Template A (extracted 393 lines CSS) |

---

## 📋 PRIORITY 5: Federation System (20 pages)

| Page | Path | Template Type | Notes |
|------|------|---------------|-------|
| ✅ Federation Dashboard | `federation/dashboard.php` | COMPLETE | Template G |
| Federation Hub | `federation/hub.php` | Template G | Main hub |
| Federation Activity | `federation/activity.php` | Template F | Feed |
| Federation Settings | `federation/settings.php` | Template D | Form |
| Federation Members | `federation/members.php` | Template A | Directory |
| Federation Member Profile | `federation/member-profile.php` | Template C | Detail |
| Federation Groups | `federation/groups.php` | Template A | Directory |
| Federation Group Detail | `federation/group-detail.php` | Template C | Detail |
| Federation Events | `federation/events.php` | Template A | Directory |
| Federation Event Detail | `federation/event-detail.php` | Template C | Detail |
| Federation Listings | `federation/listings.php` | Template A | Directory |
| Federation Listing Detail | `federation/listing-detail.php` | Template C | Detail |
| Federation Messages | `federation/messages.php` | Template G | Messages |
| Federation Transactions | `federation/transactions.php` | Template G | Transactions |
| Federation Partner Profile | `federation/partner-profile.php` | Template C | Detail |
| Federation Onboarding | `federation/onboarding.php` | Template D | Onboarding |
| Groups Enable Required | `federation/groups-enable-required.php` | Template E | Notice |
| Not Available | `federation/not-available.php` | Template E | Notice |
| Messages Opt-in Required | `federation/messages/opt-in-required.php` | Template E | Notice |
| Transactions Enable Required | `federation/transactions/enable-required.php` | Template E | Notice |

---

## 📋 PRIORITY 6: Achievements System (6 pages)

| Page | Path | Template Type | Notes |
|------|------|---------------|-------|
| Achievements Index | `achievements/index.php` | Template A | Directory |
| Achievements Badges | `achievements/badges.php` | Template A | Grid view |
| Achievements Challenges | `achievements/challenges.php` | Template A | List |
| Achievements Collections | `achievements/collections.php` | Template A | Grid |
| Achievements Seasons | `achievements/seasons.php` | Template A | List |
| Achievements Shop | `achievements/shop.php` | Template A | Grid |

---

## 📋 PRIORITY 7: Groups (8/8) - COMPLETE ✅

| Page | Path | Status | Template Type |
|------|------|--------|---------------|
| ✅ Index | `groups/index.php` | COMPLETE | Template A |
| ✅ Show | `groups/show.php` | COMPLETE | Template C |
| ✅ Create | `groups/create.php` | COMPLETE | Template D |
| ✅ Edit | `groups/edit.php` | COMPLETE | Template D |
| ✅ Create Overlay | `groups/create-overlay.php` | COMPLETE | Overlay (extracted 381 lines CSS + 80 lines JS) |
| ✅ Edit Overlay | `groups/edit-overlay.php` | COMPLETE | Overlay (already using external CSS/JS) |
| ✅ Invite | `groups/invite.php` | COMPLETE | Template D (extracted 290+ lines CSS/JS) |
| ✅ My Groups | `groups/my-groups.php` | COMPLETE | Template A (glassmorphism grid) |
| ✅ Discussion Create | `groups/discussions/create.php` | COMPLETE | Template D (extracted 400+ lines) |
| ✅ Discussion Show | `groups/discussions/show.php` | COMPLETE | Template C (extracted 550+ lines) |

---

## 📋 PRIORITY 8: Organizations (5 pages)

| Page | Path | Template Type |
|------|------|---------------|
| Org Utility Bar | `organizations/_org-utility-bar.php` | Partial |
| Audit Log | `organizations/audit-log.php` | Template A |
| Members | `organizations/members.php` | Template A |
| Transfer Requests | `organizations/transfer-requests.php` | Template A |
| Wallet | `organizations/wallet.php` | Template G |

---

## 📋 PRIORITY 9: Other Core Features

### Compose (1 page)
| Page | Path | Template Type |
|------|------|---------------|
| Compose Index | `compose/index.php` | Modal/Overlay |

### Connections (1 page)
| Page | Path | Template Type |
|------|------|---------------|
| Connections Index | `connections/index.php` | Template A |

### Home (1 page)
| Page | Path | Template Type |
|------|------|---------------|
| Home | `home.php` | Template B |

### Onboarding (1 page)
| Page | Path | Template Type |
|------|------|---------------|
| Onboarding Index | `onboarding/index.php` | Template D |

### Reviews (1 page)
| Page | Path | Template Type |
|------|------|---------------|
| Reviews Create | `reviews/create.php` | Template D |

### Consent (2 pages)
| Page | Path | Template Type |
|------|------|---------------|
| Consent Required | `consent/required.php` | Template E |
| Consent Decline | `consent/decline.php` | Template E |

---

## 📋 PRIORITY 10: Admin & Master (5 pages)

### Master Tenant Management (3 pages)
| Page | Path | Template Type |
|------|------|---------------|
| Master Dashboard | `master/dashboard.php` | Admin |
| Edit Tenant | `master/edit-tenant.php` | Admin |
| Users | `master/users.php` | Admin |

### Admin Partials (2 pages)
| Page | Path | Template Type |
|------|------|---------------|
| Admin Header | `admin/partials/admin-header.php` | Partial |
| Admin Footer | `admin/partials/admin-footer.php` | Partial |

---

## 📋 PRIORITY 11: Demo & Technical (5 pages)

| Page | Path | Template Type |
|------|------|---------------|
| Demo Home | `demo/home.php` | Demo |
| Demo Compliance | `demo/compliance.php` | Demo |
| Demo Council Case Study | `demo/council_case_study.php` | Demo |
| Demo HSE Case Study | `demo/hse_case_study.php` | Demo |
| Demo Technical Specs | `demo/technical_specs.php` | Demo |

---

## 📋 PRIORITY 12: AI & Reports (2 pages)

| Page | Path | Template Type |
|------|------|---------------|
| AI Index | `ai/index.php` | Custom |
| Nexus Impact Report | `reports/nexus-impact-report.php` | Template E |

---

## 📋 PRIORITY 13: Components & Partials (25+ files)

**Note**: These are typically included by other pages and may not need full Template annotations.

### Components (8 files)
- `components/achievement-showcase.php`
- `components/nexus-leaderboard.php`
- `components/nexus-score-charts.php`
- `components/nexus-score-dashboard.php`
- `components/nexus-score-widget.php`
- `components/org-ui-components.php`
- `components/shared/accessibility-helpers.php`
- `components/shared/post-card.php`

### Partials (10 files)
- `partials/feed_item.php`
- `partials/federation-nav.php`
- `partials/federation-realtime.php`
- `partials/home-composer.php`
- `partials/home-sidebar.php`
- `partials/impersonation-banner.php`
- `partials/mobile-sheets.php`
- `partials/skeleton-feed.php`
- `partials/social_interactions.php`
- `partials/universal-feed-filter.php`

### Other Partials (7 files)
- `blog/partials/feed-items.php`
- `dashboard/partials/_overview.php`
- `events/_form.php`
- `groups/create-overlay.php`
- `groups/edit-overlay.php`
- `listings/_form.php`
- `matches/_match_card.php`
- `organizations/_org-utility-bar.php`
- `profile/components/profile-header.php`

---

## 📊 Summary by Priority

| Priority | Category | Pages | Status |
|----------|----------|-------|--------|
| ✅ | Completed Modules | 9 | DONE |
| P1 | Resources | 4 | Next recommended |
| P2 | Core User Flows | 13 | High priority |
| P3 | Account Area | 7 | High priority |
| P4 | Search & Discovery | 2 | Medium priority |
| P5 | Federation | 20 | Large system |
| P6 | Achievements | 6 | Gamification |
| P7 | Groups | 8 | Social features |
| P8 | Organizations | 5 | Admin features |
| P9 | Other Core | 7 | Various |
| P10 | Admin & Master | 5 | Backend |
| P11 | Demo | 5 | Demo pages |
| P12 | AI & Reports | 2 | Advanced |
| P13 | Components/Partials | 25+ | Low priority |

**Total Remaining**: ~88 pages (excluding partials/components that may not need full refactoring)

---

## 🎯 Recommended Next Steps

1. ✅ ~~Complete Resources module~~ (4 pages) - DONE (2026-01-21)
2. ✅ ~~Complete Auth module~~ (4 pages) - DONE (2026-01-21)
3. ✅ ~~Complete Events module~~ (6 pages) - DONE (2026-01-21)
4. ✅ ~~Complete Search & Discovery~~ (2 pages) - DONE (2026-01-21)
5. ✅ ~~Complete Groups module~~ (8 pages) - DONE (2026-01-21)
6. **NEXT: Complete Volunteering module** (9 pages) - Important user flow with forms
7. **Start Federation system** (19 pages) - Large but important feature
8. **Complete Achievements** (6 pages) - Gamification features
9. **Work through remaining modules** by priority

---

## Notes

- **Partials** and **components** may not need full Template annotations if they're included snippets
- **Demo pages** are low priority as they're not core user-facing features
- **Admin/Master pages** can be done last as they're internal tools
- Focus on completing full modules for quick wins and clear progress
