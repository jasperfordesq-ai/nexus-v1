# AGENTS.md

## Repo-Specific Workflow Notes

### Local development

- Start the Docker-backed app stack with `docker compose up -d`.
- Start the React frontend with `npm run dev:frontend`.
- Start the accessible frontend with `npm run dev:accessible-frontend`.
- On Windows, `npm run dev:native` uses `scripts/start-native-dev.ps1` to bring up the native/dev workflow.
- Run Laravel migrations with `docker exec nexus-php-app php artisan migrate`.

### Migration workflow

- Prefer the checked-in wrappers in `Makefile` for raw SQL migrations:
  - `make migrate FILE=...`
  - `make migrate-dry FILE=...`
  - `make migrate-prod FILE=...`
  - `make migrate-prod-dry FILE=...`
  - `make drift-check`
- The underlying script is `scripts/safe_migrate.php`. Useful direct commands from the script help output:
  - `php scripts/safe_migrate.php --pending`
  - `php scripts/safe_migrate.php --run-pending --dry-run`
  - `php scripts/safe_migrate.php --run-pending`

### Validation commands

- Backend CI gates include:
  - `vendor/bin/phpunit --testsuite=Laravel,LaravelMigrated --colors=always`
  - `vendor/bin/phpstan analyse --no-progress --memory-limit=512M --error-format=github`
- React/frontend checks in active use include:
  - `cd react-frontend && npx tsc --noEmit`
  - `cd react-frontend && npm run build`
  - `cd react-frontend && npm test`
- Locale changes should also be checked with:
  - `npm run check:i18n:baseline`
  - `npm run check:i18n:gaps`
- Browser/E2E workflows in active use include:
  - `npm run test:e2e`
  - `npx playwright test e2e/tests/smoke.spec.ts --grep @smoke --project=chromium-modern`
- Accessible frontend changes should run:
  - `npm run build:accessible-frontend`
  - `npm run test:accessible-frontend:php`
  - `npm run test:accessible-frontend:a11y`

### Deployment-adjacent scripts

- Blue/green deploy orchestration lives in `scripts/deploy/bluegreen-deploy.sh`.
- The legacy wrapper is `scripts/safe-deploy.sh`.
- Repo-managed prerender operations live under `scripts/prerender-tenants.sh` and `scripts/prerender-job-processor.sh`.
- TODO: If this file becomes the operator runbook, add a short approved deploy/prerender procedure instead of inferring one from the scripts.
