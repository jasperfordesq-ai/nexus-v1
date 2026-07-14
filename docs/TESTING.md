# Testing

Last reviewed: 2026-07-14

This page explains what each test layer proves and where the remaining test-documentation risk sits.

## Test Layers

| Layer | Command | Proves |
| --- | --- | --- |
| Laravel PHPUnit | `vendor/bin/phpunit --testsuite=Laravel,LaravelMigrated --colors=always` | Backend routes, controllers, services, tenant boundaries, auth, money paths, and migrations. |
| PHPStan / Larastan | `vendor/bin/phpstan analyse --no-progress --memory-limit=512M --error-format=github` | Static-analysis regressions beyond the configured baseline. |
| React type check | `cd react-frontend && npx tsc --noEmit` | TypeScript correctness for the primary frontend. |
| React build | `cd react-frontend && npm run build` | Production build viability. |
| Vitest | `cd react-frontend && npm test` | Component, hook, and frontend behavior tests. |
| Playwright E2E | `npm run test:e2e` | Browser behavior against the React frontend and Laravel API. |
| Events enterprise E2E | `npm run test:events:e2e:enterprise` | The destructive five-step create, publication, registration, waitlist, check-in, cancellation, notification, and cleanup lifecycle against an isolated fixture environment. |
| Accessible frontend | `npm run build:accessible-frontend`, `npm run test:accessible-frontend:php`, `npm run test:accessible-frontend:a11y` | HTML-first frontend build, PHP route behavior, and accessibility smoke coverage. |
| Android native release | `cd mobile && npm run verify:release && npm run type-check && npm test -- --runInBand` | OTA/release policy, native configuration contracts, TypeScript, and mobile behavior before Expo prebuild. |
| Documentation | `npm run check:docs`, `npm run check:version`, `npx markdownlint-cli2`, Redocly, strict MkDocs build | Public-doc hygiene, version/changelog integrity, Markdown structure, OpenAPI validity, and publishable site navigation. |

## E2E Status

The Playwright suite combines broad smoke coverage with real journey assertions. CI does not treat a configured zero-test run as green, but some lower-priority specs still contain defensive presence checks; those checks are not substitutes for outcome assertions on release-critical flows.

Before treating E2E as release evidence, prefer tests that assert real outcomes:

- account state changed;
- balances or ledgers changed correctly;
- a message, notification, listing, event, or review persists after reload;
- route protection works for signed-out and cross-tenant users;
- validation errors are visible and keyboard reachable.

The Events enterprise journey is deliberately excluded from the broad Chromium,
Firefox, and mobile projects. Run it only through
`npm run test:events:e2e:enterprise`; it refuses Project NEXUS production hosts
and requires an explicit opt-in for any other non-loopback fixture target. CI
runs it against a disposable database with CI-local actors, not repository or
environment secrets.

## Generated Reports

Playwright reports under `e2e/reports/`, coverage reports, raw PHPStan output, and temporary static-analysis dumps are generated artifacts. Do not commit them as maintained docs. If a one-off report must be retained locally, put it under `.local-docs-archive/`.

## Test Documentation Rules

- Keep test instructions near the test harness they describe (`tests/README.md`, `e2e/README.md`, `mobile/README.md`).
- Put platform-wide testing policy here.
- Update this page when a test layer changes meaningfully, especially if a green check no longer proves what this page says it proves.
