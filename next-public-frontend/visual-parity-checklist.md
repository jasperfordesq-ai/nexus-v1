<!--
Copyright (c) 2024-2026 Jasper Ford
SPDX-License-Identifier: AGPL-3.0-or-later
Author: Jasper Ford
See NOTICE file for attribution and acknowledgements.
-->

# Next Public Frontend Visual Parity Checklist

Date: 2026-06-30
Tenant sampled: `hour-timebank`
Status: shadow readiness evidence, not a live cutover approval.

## Sources Compared

- React source of truth: `react-frontend/src/components/layout/Navbar.tsx`, `react-frontend/src/components/layout/Footer.tsx`, public route screenshots from `https://app.project-nexus.ie/hour-timebank`.
- Shadow candidate: `http://127.0.0.1:5175/hour-timebank` served by `next-public-frontend`.
- Initial local React URL `http://127.0.0.1:5173/hour-timebank` responded with HTTP 200 but stayed on the `Loading community` shell, so it was recorded as a local-dev blocker and not used as the visual baseline.

## Matrix Captured

Routes:

- `/hour-timebank`
- `/hour-timebank/listings`
- `/hour-timebank/events`
- `/hour-timebank/jobs`
- `/hour-timebank/marketplace`
- `/hour-timebank/organisations`
- `/hour-timebank/about`
- `/hour-timebank/help`

Viewports and themes:

- Desktop `1440x1100`, light and dark media schemes.
- Mobile `390x1000`, light and dark media schemes.

Evidence:

- Live-vs-Next screenshots and `report.json` were written to `.local-docs-archive/next-public-frontend/parity-live-2026-06-30/`.
- Local React-vs-Next screenshots showing the stalled local React shell were written to `.local-docs-archive/next-public-frontend/parity-2026-06-30/`.

## Results

All sampled live public routes and all sampled shadow Next routes returned HTTP 200.

H1 parity:

- Matching: listings, events, marketplace, organisations.
- Mismatched: home (`Exchange Skills, Build Community` vs `Home`), jobs (`Job Vacancies` vs `Jobs`), about (`About TimeBank Ireland` vs `About`), help (`Help Center` vs `Help`).

Visual delta range from screenshot comparison:

- Lowest deltas: jobs desktop, organisations desktop, home desktop.
- Highest deltas: listings mobile, marketplace mobile, about/help mobile.
- The major visible gaps are public chrome shape, live utility bar/search/login controls, live home hero treatment, mobile navigation layout, cookie banner overlap in the live baseline, and richer React-side filters on listings.

Current conclusion:

- The shadow Next app is SSR-safe and closer to the React visual system than the original bespoke CSS implementation.
- It is not yet visually indistinguishable from the live React public surface. Do not use this checklist as approval for public cutover.

## Performance And Caching Posture

- Public media in `PublicPage.tsx` now renders through `next/image` with intrinsic dimensions for tenant logos, index/detail images, and galleries.
- The app keeps `unoptimized` image rendering in shadow mode because tenant media can come from API-relative uploads, tenant domains, or CDN URLs. Before production cutover, configure explicit `images.remotePatterns` for approved API/CDN/tenant hosts and remove per-image `unoptimized` where safe.
- `export const dynamic = 'force-dynamic'` remains intentional for shadow mode so tenant/module/API contract changes are reflected immediately while route parity is still being proven.
- Intended production posture after route parity: retain dynamic rendering for tenant bootstrap and module gates, then add short `fetch` revalidation for index pages and detail pages whose public contract is stable. Do not enable ISR or edge caching until invalidation rules for tenant branding, module gates, and public content updates are documented.
- No production serving paths were changed during this pass: no Apache/Plesk config, prerender engine, React/Vite serving, blue-green serving, live sitemap, or route-cutover flag.

## Remaining Parity Work Before Cutover

- Reproduce the React public utility bar, guest navigation, search affordance, login/signup actions, and mobile drawer structure in the shadow Next chrome.
- Align home, jobs, about, and help route labels/headlines to the React public pages using translation keys and tenant SEO fields.
- Bring listings and marketplace mobile layouts closer to React filters/cards while keeping the no-JavaScript HTML contract intact.
- Re-run the matrix against a healthy local React dev server and the live app after the above changes.
- Only after parity screenshots are reviewed should any separate canary or public route cutover be considered.
