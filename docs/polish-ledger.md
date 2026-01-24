# CivicOne GOV.UK Polish Ledger

Last updated: 2026-01-24

## Status Key
- ✅ Polished - GOV.UK layout/spacing/components applied
- 🟡 Partial - Some GOV.UK classes, needs work
- ⬜ Not started - No GOV.UK integration

---

## Layout Files (Priority)

| File | Status | Notes |
|------|--------|-------|
| `layouts/civicone/header.php` | 🟡 Partial | Has govuk-template ref, needs proper GOV.UK service nav |
| `layouts/civicone/footer.php` | ⬜ Not started | Needs GOV.UK footer pattern |
| `layouts/civicone/partials/document-open.php` | ✅ Polished | Has govuk-template class |
| `layouts/civicone/partials/body-open.php` | ✅ Polished | Has govuk-template__body class |
| `layouts/civicone/partials/assets-css.php` | ✅ Polished | Loads govuk-frontend-5.14.0 |
| `layouts/civicone/partials/assets-js-footer.php` | ✅ Polished | Loads govuk-frontend JS |
| `layouts/civicone/partials/service-navigation.php` | 🟡 Partial | Uses civicone- classes, needs govuk- |
| `layouts/civicone/partials/site-header.php` | 🟡 Partial | Uses civicone- classes, needs govuk- |
| `layouts/civicone/partials/utility-bar.php` | ⬜ Not started | Needs review |
| `layouts/civicone/partials/skip-link-and-banner.php` | ⬜ Not started | Check for govuk-skip-link |
| `layouts/civicone/partials/main-open.php` | ⬜ Not started | Needs govuk-main-wrapper |
| `layouts/civicone/partials/main-close.php` | ⬜ Not started | - |
| `layouts/civicone/partials/page-hero.php` | ⬜ Not started | Replace with Page Heading Region |
| `layouts/civicone/partials/hero.php` | ⬜ Not started | Replace with Page Heading Region |

---

## Core Pages

### Authentication
| File | Status | Notes |
|------|--------|-------|
| `civicone/auth/login.php` | ⬜ Not started | - |
| `civicone/auth/register.php` | ⬜ Not started | - |
| `civicone/auth/forgot_password.php` | ⬜ Not started | - |
| `civicone/auth/reset_password.php` | ⬜ Not started | - |

### Home/Dashboard
| File | Status | Notes |
|------|--------|-------|
| `civicone/home.php` | ⬜ Not started | - |
| `civicone/home-govuk-enhanced.php` | 🟡 Partial | Has govuk- notification classes |
| `civicone/dashboard.php` | ⬜ Not started | - |
| `civicone/dashboard/events.php` | ⬜ Not started | - |
| `civicone/dashboard/hubs.php` | ⬜ Not started | - |
| `civicone/dashboard/listings.php` | ⬜ Not started | - |
| `civicone/dashboard/notifications.php` | ⬜ Not started | - |
| `civicone/dashboard/wallet.php` | ⬜ Not started | - |

### Feed
| File | Status | Notes |
|------|--------|-------|
| `civicone/feed/index.php` | ⬜ Not started | - |
| `civicone/feed/show.php` | ⬜ Not started | - |

### Members
| File | Status | Notes |
|------|--------|-------|
| `civicone/members/index.php` | ⬜ Not started | - |
| `civicone/members/index-govuk.php` | 🟡 Partial | Alternate GOV.UK version |

### Profile
| File | Status | Notes |
|------|--------|-------|
| `civicone/profile/show.php` | ⬜ Not started | - |
| `civicone/profile/edit.php` | ⬜ Not started | - |

### Groups
| File | Status | Notes |
|------|--------|-------|
| `civicone/groups/index.php` | ⬜ Not started | - |
| `civicone/groups/show.php` | ⬜ Not started | - |
| `civicone/groups/create.php` | ⬜ Not started | - |
| `civicone/groups/edit.php` | ⬜ Not started | - |
| `civicone/groups/create-overlay.php` | 🟡 Partial | Has govuk- form classes |
| `civicone/groups/edit-overlay.php` | 🟡 Partial | Has govuk- form classes |
| `civicone/groups/my-groups.php` | ⬜ Not started | - |
| `civicone/groups/invite.php` | ⬜ Not started | - |

### Listings
| File | Status | Notes |
|------|--------|-------|
| `civicone/listings/index.php` | ⬜ Not started | - |
| `civicone/listings/show.php` | ⬜ Not started | - |
| `civicone/listings/create.php` | ⬜ Not started | - |
| `civicone/listings/edit.php` | ⬜ Not started | - |

### Events
| File | Status | Notes |
|------|--------|-------|
| `civicone/events/index.php` | ⬜ Not started | - |
| `civicone/events/show.php` | ⬜ Not started | - |
| `civicone/events/create.php` | ⬜ Not started | - |
| `civicone/events/edit.php` | ⬜ Not started | - |
| `civicone/events/calendar.php` | ⬜ Not started | - |

### Messages
| File | Status | Notes |
|------|--------|-------|
| `civicone/messages/index.php` | ⬜ Not started | - |
| `civicone/messages/thread.php` | ⬜ Not started | - |

### Volunteering
| File | Status | Notes |
|------|--------|-------|
| `civicone/volunteering/index.php` | ⬜ Not started | - |
| `civicone/volunteering/show.php` | ⬜ Not started | - |
| `civicone/volunteering/dashboard.php` | ⬜ Not started | - |
| `civicone/volunteering/my_applications.php` | ⬜ Not started | - |
| `civicone/volunteering/certificate.php` | ⬜ Not started | - |
| `civicone/volunteering/organizations.php` | ⬜ Not started | - |
| `civicone/volunteering/create_opp.php` | ⬜ Not started | - |
| `civicone/volunteering/edit_opp.php` | ⬜ Not started | - |
| `civicone/volunteering/edit_opp_new.php` | ⬜ Not started | - |
| `civicone/volunteering/edit_org.php` | ⬜ Not started | - |
| `civicone/volunteering/show_org.php` | ⬜ Not started | - |

### Organizations
| File | Status | Notes |
|------|--------|-------|
| `civicone/organizations/wallet.php` | 🟡 Partial | Has govuk- grid/form classes |
| `civicone/organizations/members.php` | ⬜ Not started | - |
| `civicone/organizations/audit-log.php` | ⬜ Not started | - |
| `civicone/organizations/transfer-requests.php` | ⬜ Not started | - |

### Wallet
| File | Status | Notes |
|------|--------|-------|
| `civicone/wallet/index.php` | ⬜ Not started | - |
| `civicone/wallet/insights.php` | ⬜ Not started | - |

### Compose
| File | Status | Notes |
|------|--------|-------|
| `civicone/compose/index.php` | 🟡 Partial | Has govuk- form classes |

### AI
| File | Status | Notes |
|------|--------|-------|
| `civicone/ai/index.php` | 🟡 Partial | Has some govuk- classes |

### Connections
| File | Status | Notes |
|------|--------|-------|
| `civicone/connections/index.php` | ⬜ Not started | - |

### Matches
| File | Status | Notes |
|------|--------|-------|
| `civicone/matches/index.php` | ⬜ Not started | - |
| `civicone/matches/hot.php` | ⬜ Not started | - |
| `civicone/matches/mutual.php` | ⬜ Not started | - |
| `civicone/matches/preferences.php` | ⬜ Not started | - |

### Goals
| File | Status | Notes |
|------|--------|-------|
| `civicone/goals/index.php` | ⬜ Not started | - |
| `civicone/goals/show.php` | ⬜ Not started | - |
| `civicone/goals/create.php` | ⬜ Not started | - |
| `civicone/goals/edit.php` | ⬜ Not started | - |
| `civicone/goals/delete.php` | ⬜ Not started | - |

### Polls
| File | Status | Notes |
|------|--------|-------|
| `civicone/polls/index.php` | ⬜ Not started | - |
| `civicone/polls/show.php` | ⬜ Not started | - |
| `civicone/polls/create.php` | ⬜ Not started | - |
| `civicone/polls/edit.php` | ⬜ Not started | - |

### Resources
| File | Status | Notes |
|------|--------|-------|
| `civicone/resources/index.php` | ⬜ Not started | - |
| `civicone/resources/create.php` | ⬜ Not started | - |
| `civicone/resources/edit.php` | ⬜ Not started | - |
| `civicone/resources/download.php` | ⬜ Not started | - |

### Blog
| File | Status | Notes |
|------|--------|-------|
| `civicone/blog/index.php` | ⬜ Not started | - |
| `civicone/blog/show.php` | ⬜ Not started | - |
| `civicone/blog/news.php` | ⬜ Not started | - |

### Help
| File | Status | Notes |
|------|--------|-------|
| `civicone/help/index.php` | ⬜ Not started | - |
| `civicone/help/show.php` | ⬜ Not started | - |
| `civicone/help/search.php` | ⬜ Not started | - |

### Settings
| File | Status | Notes |
|------|--------|-------|
| `civicone/settings/index.php` | ⬜ Not started | - |

### Notifications
| File | Status | Notes |
|------|--------|-------|
| `civicone/notifications/index.php` | ⬜ Not started | - |

### Search
| File | Status | Notes |
|------|--------|-------|
| `civicone/search/results.php` | ⬜ Not started | - |

### Achievements
| File | Status | Notes |
|------|--------|-------|
| `civicone/achievements/index.php` | ⬜ Not started | - |
| `civicone/achievements/badges.php` | ⬜ Not started | - |
| `civicone/achievements/challenges.php` | ⬜ Not started | - |
| `civicone/achievements/collections.php` | ⬜ Not started | - |
| `civicone/achievements/seasons.php` | ⬜ Not started | - |
| `civicone/achievements/shop.php` | ⬜ Not started | - |

### Leaderboard
| File | Status | Notes |
|------|--------|-------|
| `civicone/leaderboard/index.php` | ⬜ Not started | - |

### Reviews
| File | Status | Notes |
|------|--------|-------|
| `civicone/reviews/create.php` | ⬜ Not started | - |

### Onboarding
| File | Status | Notes |
|------|--------|-------|
| `civicone/onboarding/index.php` | ⬜ Not started | - |

### Consent
| File | Status | Notes |
|------|--------|-------|
| `civicone/consent/required.php` | ⬜ Not started | - |
| `civicone/consent/decline.php` | ⬜ Not started | - |

### Master Admin
| File | Status | Notes |
|------|--------|-------|
| `civicone/master/dashboard.php` | 🟡 Partial | Has govuk- grid/table classes |
| `civicone/master/edit-tenant.php` | 🟡 Partial | Has govuk- form classes |
| `civicone/master/users.php` | ⬜ Not started | - |

### Reports
| File | Status | Notes |
|------|--------|-------|
| `civicone/reports/nexus-impact-report.php` | ⬜ Not started | - |

### Demo
| File | Status | Notes |
|------|--------|-------|
| `civicone/demo/home.php` | ⬜ Not started | - |
| `civicone/demo/compliance.php` | ⬜ Not started | - |
| `civicone/demo/council_case_study.php` | ⬜ Not started | - |
| `civicone/demo/hse_case_study.php` | ⬜ Not started | - |
| `civicone/demo/technical_specs.php` | ⬜ Not started | - |

### Static Pages
| File | Status | Notes |
|------|--------|-------|
| `civicone/pages/about.php` | ⬜ Not started | - |
| `civicone/pages/about-story.php` | ⬜ Not started | - |
| `civicone/pages/accessibility.php` | ⬜ Not started | - |
| `civicone/pages/contact.php` | ⬜ Not started | - |
| `civicone/pages/faq.php` | ⬜ Not started | - |
| `civicone/pages/how-it-works.php` | ⬜ Not started | - |
| `civicone/pages/impact-report.php` | ⬜ Not started | - |
| `civicone/pages/impact-summary.php` | ⬜ Not started | - |
| `civicone/pages/legal.php` | ⬜ Not started | - |
| `civicone/pages/mobile-about.php` | 🟡 Partial | Has govuk- link/button classes |
| `civicone/pages/our-story.php` | ⬜ Not started | - |
| `civicone/pages/partner.php` | ⬜ Not started | - |
| `civicone/pages/privacy.php` | ⬜ Not started | - |
| `civicone/pages/social-prescribing.php` | ⬜ Not started | - |
| `civicone/pages/strategic-plan.php` | ⬜ Not started | - |
| `civicone/pages/terms.php` | ⬜ Not started | - |
| `civicone/pages/timebanking-guide.php` | ⬜ Not started | - |

### Error Pages
| File | Status | Notes |
|------|--------|-------|
| `civicone/pages/error-403.php` | ⬜ Not started | - |
| `civicone/pages/error-404.php` | ⬜ Not started | - |
| `civicone/pages/error-500.php` | ⬜ Not started | - |

### Legal
| File | Status | Notes |
|------|--------|-------|
| `civicone/legal/volunteer-license.php` | 🟡 Partial | Has govuk- structure |

---

## Federation Pages

| File | Status | Notes |
|------|--------|-------|
| `civicone/federation/dashboard.php` | ⬜ Not started | - |
| `civicone/federation/activity.php` | ⬜ Not started | - |
| `civicone/federation/members.php` | ⬜ Not started | - |
| `civicone/federation/member-profile.php` | ⬜ Not started | - |
| `civicone/federation/listings.php` | ⬜ Not started | - |
| `civicone/federation/listing-detail.php` | ⬜ Not started | - |
| `civicone/federation/events.php` | ⬜ Not started | - |
| `civicone/federation/event-detail.php` | ⬜ Not started | - |
| `civicone/federation/groups.php` | ⬜ Not started | - |
| `civicone/federation/group-detail.php` | ⬜ Not started | - |
| `civicone/federation/groups-enable-required.php` | ⬜ Not started | - |
| `civicone/federation/my-groups.php` | ⬜ Not started | - |
| `civicone/federation/messages.php` | ⬜ Not started | - |
| `civicone/federation/messages/index.php` | ⬜ Not started | - |
| `civicone/federation/messages/thread.php` | ⬜ Not started | - |
| `civicone/federation/messages/opt-in-required.php` | ⬜ Not started | - |
| `civicone/federation/transactions.php` | ⬜ Not started | - |
| `civicone/federation/transactions/index.php` | ⬜ Not started | - |
| `civicone/federation/transactions/create.php` | ⬜ Not started | - |
| `civicone/federation/transactions/enable-required.php` | ⬜ Not started | - |
| `civicone/federation/hub.php` | ⬜ Not started | - |
| `civicone/federation/partner-profile.php` | ⬜ Not started | - |
| `civicone/federation/settings.php` | ⬜ Not started | - |
| `civicone/federation/onboarding.php` | ⬜ Not started | - |
| `civicone/federation/help.php` | ⬜ Not started | - |
| `civicone/federation/not-available.php` | ⬜ Not started | - |
| `civicone/federation/offline.php` | ⬜ Not started | - |
| `civicone/federation/review-form.php` | ⬜ Not started | - |
| `civicone/federation/review-error.php` | ⬜ Not started | - |
| `civicone/federation/reviews-pending.php` | ⬜ Not started | - |

---

## Components (Reference - update as pages use them)

| File | Status | Notes |
|------|--------|-------|
| `civicone/components/govuk/*.php` | ✅ Polished | GOV.UK component library |

---

## Summary

- **Total pages**: ~150+
- **Polished**: ~5
- **Partial**: ~12
- **Not started**: ~133

---

## Next Steps

1. ~~Create polish-ledger.md~~ ✅
2. Implement proper GOV.UK service header/navigation
3. Add accessible "More" dropdown
4. Create Page Heading Region partial
5. Systematically polish pages starting with layout files
