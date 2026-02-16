# Legacy → React Route Mapping

This document maps legacy PHP frontend routes to their React equivalents.

## Compose / Create Routes

| Legacy Route | React Route | Notes |
|--------------|-------------|-------|
| `compose` | `/feed` | Feed page has inline post composer |
| `compose?type=post` | `/feed` | Posts created inline in feed |
| `compose?type=listing` | `/listings/new` | Create listing page |
| `compose?type=event` | `/events/new` | Create event (if `events` feature enabled) |
| `compose?type=poll` | `/feed` | Polls created inline in feed posts |
| `compose?type=goal` | `/goals` (inline) | Goals created inline if feature enabled |

## Admin Routes

| Legacy Route | React Route | Notes |
|--------------|-------------|-------|
| `/admin` | `/admin` | React admin panel (primary) |
| `/admin/dashboard` | `/admin` | React admin dashboard |
| `/admin/users` | `/admin/users` | User management |
| `/admin/listings` | `/admin/listings` | Listing management |
| `/admin/settings` | `/admin/settings` | Site settings |
| `/admin/categories` | `/admin/categories` | Category management |
| `/admin/timebanking` | `/admin/wallet` | Wallet/credits management |
| `/admin/pages` | `/admin/pages` | CMS pages |
| `/admin/federation` | `/admin/federation` | Federation settings |
| `/admin/activity-log` | `/admin/activity-log` | Activity log |

## Legacy PHP Admin (Deprecated)

Legacy PHP admin is still accessible at `/admin-legacy/*` but is being decommissioned.

## Public Routes

| Legacy Route | React Route | Notes |
|--------------|-------------|-------|
| `/` | `/` | Home page |
| `/dashboard` | `/dashboard` | User dashboard |
| `/feed` | `/feed` | Social feed |
| `/listings` | `/listings` | Listings directory |
| `/listings/{id}` | `/listings/{id}` | Listing detail |
| `/messages` | `/messages` | Messages inbox |
| `/messages/{id}` | `/messages/{id}` | Conversation |
| `/wallet` | `/wallet` | Time credit wallet |
| `/events` | `/events` | Events list |
| `/events/{id}` | `/events/{id}` | Event detail |
| `/groups` | `/groups` | Groups directory |
| `/groups/{id}` | `/groups/{id}` | Group detail |
| `/members` | `/members` | Member directory |
| `/profile/{id}` | `/profile/{id}` | User profile |
| `/settings` | `/settings` | User settings |
| `/search` | `/search` | Search results |

## Notes

- All React routes use tenant slug prefix: `/{tenant-slug}/route` (e.g., `/hour-timebank/dashboard`)
- Tests should use `tenantUrl()` helper which adds the slug automatically
- Legacy `compose` page no longer exists — content creation is contextual (feed for posts, dedicated pages for listings/events)
