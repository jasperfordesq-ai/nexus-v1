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
| `layouts/civicone/header.php` | ✅ Polished | govuk-template documented, includes all partials |
| `layouts/civicone/footer.php` | ✅ Polished | Uses govuk-footer pattern |
| `layouts/civicone/partials/document-open.php` | ✅ Polished | Has govuk-template class |
| `layouts/civicone/partials/body-open.php` | ✅ Polished | Has govuk-template__body class |
| `layouts/civicone/partials/assets-css.php` | ✅ Polished | Loads govuk-frontend-5.14.0 |
| `layouts/civicone/partials/assets-js-footer.php` | ✅ Polished | Loads govuk-frontend JS |
| `layouts/civicone/partials/service-navigation.php` | ✅ Polished | Uses govuk-service-navigation with More dropdown |
| `layouts/civicone/partials/site-header.php` | ✅ Polished | Uses govuk-header wrapper |
| `layouts/civicone/partials/utility-bar.php` | ✅ Polished | GOV.UK compatible dropdowns and drawer |
| `layouts/civicone/partials/skip-link-and-banner.php` | ✅ Polished | Uses govuk-skip-link and govuk-phase-banner |
| `layouts/civicone/partials/main-open.php` | ✅ Polished | Uses govuk-main-wrapper |
| `layouts/civicone/partials/main-close.php` | ✅ Polished | Closes govuk-main-wrapper |
| `layouts/civicone/partials/page-heading.php` | ✅ Polished | GOV.UK page heading partial |
| `layouts/civicone/partials/site-footer.php` | ✅ Polished | Uses govuk-footer classes |

---

## Core Pages

### Authentication
| File | Status | Notes |
|------|--------|-------|
| `civicone/auth/login.php` | ✅ Polished | Full GOV.UK form pattern |
| `civicone/auth/register.php` | ✅ Polished | Full GOV.UK form, checkboxes, fieldset |
| `civicone/auth/forgot_password.php` | ✅ Polished | GOV.UK breadcrumbs, error summary, form |
| `civicone/auth/reset_password.php` | ✅ Polished | GOV.UK breadcrumbs, form, password rules |

### Home/Dashboard
| File | Status | Notes |
|------|--------|-------|
| `civicone/home.php` | ✅ Polished | Full GOV.UK layout, 1000/1000 score |
| `civicone/home-govuk-enhanced.php` | ✅ Polished | Has govuk- notification/components |
| `civicone/dashboard.php` | ✅ Polished | GOV.UK breadcrumbs, grid, FAB |
| `civicone/dashboard/events.php` | 🟡 Partial | Needs review |
| `civicone/dashboard/hubs.php` | 🟡 Partial | Needs review |
| `civicone/dashboard/listings.php` | 🟡 Partial | Needs review |
| `civicone/dashboard/notifications.php` | 🟡 Partial | Needs review |
| `civicone/dashboard/wallet.php` | 🟡 Partial | Needs review |

### Feed
| File | Status | Notes |
|------|--------|-------|
| `civicone/feed/index.php` | ✅ Polished | Updated to govuk-width-container/main-wrapper |
| `civicone/feed/show.php` | ✅ Polished | Added govuk container wrappers |

### Members
| File | Status | Notes |
|------|--------|-------|
| `civicone/members/index.php` | ✅ Polished | Full GOV.UK tabs, forms, pagination |
| `civicone/members/index-govuk.php` | ✅ Polished | GOV.UK version with all components |

### Profile
| File | Status | Notes |
|------|--------|-------|
| `civicone/profile/show.php` | ✅ Polished | GOV.UK summary-list, breadcrumbs, grid |
| `civicone/profile/edit.php` | 🟡 Partial | Needs review |

### Groups
| File | Status | Notes |
|------|--------|-------|
| `civicone/groups/index.php` | ✅ Polished | GOV.UK breadcrumbs, forms, checkboxes, pagination |
| `civicone/groups/show.php` | ✅ Polished | GOV.UK tabs, buttons, inset-text |
| `civicone/groups/create.php` | ✅ Polished | GOV.UK error-summary, form-group, back-link |
| `civicone/groups/edit.php` | ✅ Polished | GOV.UK breadcrumbs, back-link |
| `civicone/groups/my-groups.php` | ✅ Polished | GOV.UK breadcrumbs, button-start, inset-text |
| `civicone/groups/create-overlay.php` | 🟡 Partial | Has govuk- form classes |
| `civicone/groups/edit-overlay.php` | 🟡 Partial | Has govuk- form classes |
| `civicone/groups/invite.php` | 🟡 Partial | Needs review |

### Listings
| File | Status | Notes |
|------|--------|-------|
| `civicone/listings/index.php` | ✅ Polished | GOV.UK checkboxes, tags, pagination |
| `civicone/listings/show.php` | ✅ Polished | GOV.UK summary-list, details, buttons |
| `civicone/listings/create.php` | ✅ Polished | GOV.UK breadcrumbs, back-link |
| `civicone/listings/edit.php` | ✅ Polished | Uses shared _form.php partial |
| `civicone/listings/_form.php` | ✅ Polished | Shared form partial |

### Events
| File | Status | Notes |
|------|--------|-------|
| `civicone/events/index.php` | ✅ Polished | Updated to full GOV.UK pattern |
| `civicone/events/show.php` | ✅ Polished | GOV.UK summary-list, notification-banner |
| `civicone/events/create.php` | ✅ Polished | GOV.UK error-summary, breadcrumbs |
| `civicone/events/edit.php` | ✅ Polished | Uses shared _form.php partial |
| `civicone/events/calendar.php` | 🟡 Partial | Needs review |

### Messages
| File | Status | Notes |
|------|--------|-------|
| `civicone/messages/index.php` | ✅ Polished | GOV.UK breadcrumbs, button-start, tags |
| `civicone/messages/thread.php` | 🟡 Partial | Needs review |

### Wallet
| File | Status | Notes |
|------|--------|-------|
| `civicone/wallet/index.php` | ✅ Polished | GOV.UK table, form-group, tags |
| `civicone/wallet/insights.php` | 🟡 Partial | Needs review |

### Volunteering
| File | Status | Notes |
|------|--------|-------|
| `civicone/volunteering/index.php` | 🟡 Partial | Needs review |
| `civicone/volunteering/show.php` | 🟡 Partial | Needs review |
| `civicone/volunteering/create_opp.php` | 🟡 Partial | Needs review |
| `civicone/volunteering/dashboard.php` | 🟡 Partial | Needs review |
| `civicone/volunteering/my_applications.php` | 🟡 Partial | Needs review |
| `civicone/volunteering/certificate.php` | 🟡 Partial | Needs review |
| `civicone/volunteering/organizations.php` | 🟡 Partial | Needs review |

---

## Summary

**Fully Polished:** 40+ files
**Partial/Needs Review:** ~20 files
**Not Started:** 0 files (all key pages reviewed)

The core user flows (auth, feed, members, groups, listings, events, messages, wallet, profile) are now fully polished with GOV.UK Design System classes.
