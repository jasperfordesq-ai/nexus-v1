# Continuous Integration & Quality Gates

> **Diátaxis:** explanation. What runs on every push and pull request, which checks are blocking, and how to run the same checks locally before you push.

Project NEXUS uses GitHub Actions. The pipeline is designed so that **`main` stays green** — a failing check on `main` is inherited by every later PR, so gates are kept meaningful and are fixed or reverted immediately.

## The main pipeline — `.github/workflows/ci.yml`

| Job | Blocking steps | What it proves |
|-----|----------------|----------------|
| **PHP Checks** | PHPUnit (`--testsuite=Laravel,LaravelMigrated`); schema-driven test-skip budget; **PHPStan** (larastan, level 1 + `phpstan-baseline.neon`); artisan cache fail-fast; PHP syntax check | Backend logic, static analysis, and bootability. |
| **React Build & Tests** | `tsc --noEmit`; ESLint (`--max-warnings 30`); Vitest smoke tests; **production build** (`npm run build`) | Frontend type-safety, lint, tests, and that it actually builds. |
| **API Contract Validation** | API contract schema tests (runs after the React build) | The API responses match the typed contract the frontend expects. |
| **Documentation & version hygiene** | `check:docs`, `check:version`, `check:changelog` | Docs are indexed and link-clean, the version is consistent across all ~16 references, and the changelog was updated. |
| **Docker Build Verify** | Builds the dev PHP image, the production PHP image, and the React image (`--no-cache`) | The container images build for deployment. |

Steps are labelled **BLOCKING** (fail the build) or **WARNING** (reported, non-blocking — e.g. the Vitest coverage report and the env-var documentation check).

## Other workflows

| Workflow | Purpose |
|----------|---------|
| `pr-checks.yml` | PR description gates (Root-Cause/Prevention, Translation Status, Contributor Terms). |
| `contributor-terms.yml` | Enforces the contributor-terms acceptance section on PRs. |
| `dependency-review.yml` | Flags risky dependency changes on PRs. |
| `security-scan.yml` | Security scanners (see [SECURITY-SCANNING.md](SECURITY-SCANNING.md)). |
| `e2e-tests.yml` | Playwright end-to-end smoke tests. |
| `lighthouse.yml` | Lighthouse performance/accessibility budget. |
| `deploy-drift-watchdog.yml` | Detects drift between deployed and committed state. |
| `release.yml` | Builds a GitHub Release from a version tag (see [RELEASES.md](../RELEASES.md)). |

## Pull-request gates

Some PRs are gated by **description checks** that fail instantly unless the PR body contains exact fields (these re-run when the PR body is edited — no push needed):

- **Root Cause Analysis** — `fix`/`bug`/`hotfix` PRs must include `**Root Cause:**` and `**Prevention:**`.
- **Translation Review** — PRs touching non-English locale files must declare `**Translation Status:**` and `**Translation Reviewer:**`.
- **Contributor Terms** — the `## Contributor Terms` section with all checkboxes checked (owner/bot PRs exempt).
- **Translation drift** — `node scripts/check-php-lang-parity.mjs` must pass.

Build the PR body from [.github/pull_request_template.md](../.github/pull_request_template.md).

## Run the gates locally (before you push)

```bash
# Backend
vendor/bin/phpunit --testsuite=Laravel,LaravelMigrated
vendor/bin/phpstan analyse --no-progress --memory-limit=512M

# Frontend
cd react-frontend && npx tsc --noEmit && npm run lint && npm test && npm run build

# Docs / version / changelog
npm run check:docs && npm run check:version && npm run check:changelog

# i18n (after locale changes)
npm run check:i18n:baseline && npm run check:i18n:gaps
```

Local pre-commit/pre-push hooks (Husky) run a subset automatically. If a commit is blocked **solely** by a pre-existing failure in files your change does not touch, the documented exception in [CONTRIBUTING.md](../CONTRIBUTING.md) applies.
