# Your First Contribution to Project NEXUS

Last reviewed: 2026-06-23

This tutorial walks you through making a real, merged-quality change to Project NEXUS from scratch. By the end you will have cloned the repository, run the app locally, made a visible change, verified it, and prepared it for a pull request.

This is a **tutorial** — a guided, hands-on learning path. For reference material (architecture, module guides, API) see [docs/README.md](README.md).

---

## What you will do

You will change the English label for a UI string on the public features page using the translation system. The change is small enough to understand completely and visible enough to confirm in the browser. Every step here applies to every future change you make.

---

## Step 1: Check your prerequisites

You need the following tools installed before you begin.

| Tool | Minimum version | How to check |
|------|----------------|--------------|
| Git | any recent | `git --version` |
| Docker Desktop (Windows/macOS) or Docker Engine + Compose (Linux) | current stable | `docker --version` |
| Node.js | 22+ | `node --version` |
| npm | 10+ | `npm --version` |

If Docker is not running, start it now. The data services (database, cache, and search) run inside containers.

---

## Step 2: Fork and clone the repository

1. Open https://github.com/jasperfordesq-ai/nexus-v1 in your browser.
2. Click **Fork** to create your own copy under your GitHub account.
3. Clone your fork:

```bash
git clone https://github.com/YOUR_USERNAME/nexus-v1.git
cd nexus-v1
```

4. Add the upstream repository so you can pull future changes:

```bash
git remote add upstream https://github.com/jasperfordesq-ai/nexus-v1.git
```

---

## Step 3: Set up the environment file

Copy the example environment file and open it in a text editor:

```bash
cp .env.example .env
```

The file contains placeholder values. For this tutorial, the defaults are enough to start the data services. The only value you may need to generate is `APP_KEY`; the comment inside the file explains how.

> **Keep `.env` private.** The repository is public and `.env` is gitignored — never commit it.

---

## Step 4: Start the data services and frontend

Start the database (MariaDB), cache (Redis), and search engine (Meilisearch) in Docker:

```bash
docker compose up -d
```

The first run downloads and builds images. This can take a few minutes — you will see progress printed to your terminal. When it completes, the three data services are running in the background.

Next, start the Docker PHP app container (this serves the Laravel API):

```bash
docker compose --profile docker-php up -d app
```

Run the database migrations to create the schema:

```bash
docker exec nexus-php-app php artisan migrate
```

Finally, install the frontend dependencies and start the Vite dev server:

```bash
npm run dev:frontend
```

Vite will print a message like:

```
  VITE v5.x.x  ready in 1234 ms
  ➜  Local:   http://127.0.0.1:5173/
```

---

## Step 5: Confirm the app loads

Open http://localhost:5173 in your browser. You should see the Project NEXUS React frontend. If it shows a loading spinner or a login page, the app is running correctly.

Open http://localhost:8090 in a separate tab — if the PHP API is healthy, it returns a JSON response.

> If you see an error instead, check that all four Docker containers are running:
>
> ```bash
> docker ps
> ```
>
> You should see containers for the database, Redis, Meilisearch, and the PHP app.

---

## Step 6: Create a branch

Never commit directly to `main` in your fork. Create a branch with a name that describes your change:

```bash
git checkout -b docs/fix-features-page-label
```

---

## Step 7: Make a visible change

The React frontend uses a translation system so every user-facing string can be localised into 11 languages. Strings are stored as JSON files under `react-frontend/public/locales/`. The English source is in `react-frontend/public/locales/en/`.

You are going to change the "Beta" chip label on the public features page. Open the file:

```
react-frontend/public/locales/en/public.json
```

Find this section near the top of the file:

```json
"features_page": {
    "chips": {
        "beta": "Beta",
        "preview": "Preview"
    },
```

Change the `"beta"` value — for example, to `"Beta (active)"` — so you have a concrete, browser-visible change to verify:

```json
"chips": {
    "beta": "Beta (active)",
    "preview": "Preview"
},
```

Save the file.

Now go to your browser and navigate to http://localhost:5173/features. Because Vite watches the file system, the page reloads automatically. You should see the chip label updated. If the page is blank, add a tenant slug prefix that matches your local setup (e.g. `/hour-timebank/features`).

You have just made a real, traceable change through the translation system — the same system used for every user-facing string across all 11 supported languages.

> **If you want to try something different:** you can also fix a typo in any of the `.md` files in `docs/`. Documentation-only changes do not require any commands beyond a text editor.

---

## Step 8: Verify the change

Before committing, run the checks that CI will run on your pull request.

**TypeScript type check:**

```bash
cd react-frontend
npm run lint
```

This runs ESLint plus `tsc --noEmit`. A clean output means no type errors were introduced. Fix any errors before continuing.

**Frontend tests:**

```bash
npm test -- --run
```

This runs the Vitest suite once (without watch mode). All tests should pass. If a test fails on a file you did not touch, note it and mention it in your pull request — these are pre-existing failures, not caused by your change.

**Documentation hygiene** (required when editing any file in `docs/`):

```bash
cd ..
npm run check:docs
```

**Translation drift check** (required when editing locale files):

```bash
npm run check:i18n:drift
npm run check:i18n:baseline
```

All checks should pass. If any fail because of your change, fix them before moving on.

---

## Step 9: Add the SPDX header to any new source files

If your change adds a **new** `.ts`, `.tsx`, or `.php` file — rather than editing an existing one — you must add this copyright header as the very first lines of the file.

For TypeScript or TSX:

```typescript
// Copyright © 2024–2026 Jasper Ford
// SPDX-License-Identifier: AGPL-3.0-or-later
// Author: Jasper Ford
// See NOTICE file for attribution and acknowledgements.
```

For PHP (immediately after `<?php`):

```php
<?php
// Copyright © 2024–2026 Jasper Ford
// SPDX-License-Identifier: AGPL-3.0-or-later
// Author: Jasper Ford
// See NOTICE file for attribution and acknowledgements.
```

For this tutorial you edited an existing JSON file, so no header is needed. But remember this rule for every new source file you create in the future.

To check all files in bulk:

```bash
node scripts/check-spdx.mjs
```

---

## Step 10: Commit your change

Stage only the file you changed:

```bash
git add react-frontend/public/locales/en/public.json
```

Write a commit message using the [conventional commit](../CONTRIBUTING.md#git-commit-convention) format:

```bash
git commit -m "docs(features): clarify Beta chip label in public features page"
```

Commit message rules:

- Use a prefix: `feat`, `fix`, `docs`, `style`, `refactor`, `test`, or `chore`
- Keep the subject under 72 characters
- Use the imperative mood ("Add", "Fix", "Update") — not past tense

If you used AI assistance, add a co-author line in the commit body:

```
docs(features): clarify Beta chip label in public features page

Co-Authored-By: Claude <noreply@anthropic.com>
```

Husky pre-commit and pre-push hooks will run automatically. If they flag errors in files you did not touch, you may bypass the hook and explain in your pull request why the failure is pre-existing and unrelated to your change:

```bash
git commit --no-verify -m "docs(features): clarify Beta chip label in public features page"
```

Do not use `--no-verify` to hide failures in code you wrote.

---

## Step 11: Push your branch

Push the branch to your fork:

```bash
git push -u origin docs/fix-features-page-label
```

---

## Step 12: Open a pull request

Go to your fork on GitHub and click **Compare & pull request**, or open:

```
https://github.com/YOUR_USERNAME/nexus-v1/compare/docs/fix-features-page-label
```

**Use the PR template** — it is loaded automatically from `.github/pull_request_template.md`. Fill in every section:

- **Summary:** one to three bullet points describing what the PR does and why.
- **Type of Change:** tick the relevant box.
- **Contributor Terms:** read `CONTRIBUTOR_TERMS.md` and tick all three checkboxes. Fill in the `Third-Party Material Disclosure` and `AI Contribution Disclosure` fields (use `None` if neither applies). These checkboxes are enforced by CI — the PR cannot merge without them.
- **Root Cause Analysis:** only required for bug-fix PRs. Delete the section for other PR types.
- **Translation Review:** required when you change a non-English locale file. For this tutorial you only changed English, so you can leave it blank.
- **Pre-Deployment Checklist:** tick the items that apply to your change.
- **Test Plan:** describe how a reviewer can verify your change (for example, "Navigate to /features and confirm the Beta chip reads 'Beta (active)'").

Submit the PR against `main` in the upstream repository.

CI will run automatically — you can watch the checks pass under the **Checks** tab. If a check fails, click through to read the log, fix the issue on your branch, and push again. The PR updates automatically.

See [CONTRIBUTING.md](../CONTRIBUTING.md) for the full pull request guide.

---

## Step 13: Where to go next

You have completed your first contribution. Here are the best places to go deeper:

| Resource | What it covers |
|----------|---------------|
| [docs/README.md](README.md) | Index of all maintained documentation |
| [docs/ARCHITECTURE.md](ARCHITECTURE.md) | How the platform is structured — multi-tenant model, runtime boundaries, frontend/backend split |
| [docs/MODULES.md](MODULES.md) | Map of product modules and where to find their code and guides |
| [docs/modules/wallet-exchanges.md](modules/wallet-exchanges.md) | Deep guide to the wallet and exchange workflow |
| [docs/modules/search.md](modules/search.md) | How Meilisearch and the SQL fallback work |
| [react-frontend/CLAUDE.md](../react-frontend/CLAUDE.md) | React frontend stack rules: HeroUI, Tailwind, contexts, hooks |
| [CONTRIBUTING.md](../CONTRIBUTING.md) | Full contributor guide: workflows, coding standards, tests, AGPL compliance |

Good places to find first issues:

- GitHub Issues labelled `good first issue` or `docs`
- Translation gaps: run `npm run check:i18n:gaps` to see which strings are missing from non-English locales
- Test coverage: check for components without a matching `.test.tsx` file and add one

Welcome to the project.
