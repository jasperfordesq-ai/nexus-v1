# Localization Workflow

This project now has two separate i18n quality gates:

1. Structural safety
   Run `node scripts/check-i18n-drift.mjs`
   Purpose: every locale file must match English key structure.

2. Content completeness
   Run `node scripts/translate-i18n-gaps.mjs --summary`
   Purpose: find strings that are still missing or still identical to English outside the admin-only namespaces.

3. Regression guard
   Run `node scripts/check-i18n-gap-regression.mjs`
   Purpose: fail fast if the non-admin untranslated / English-fallback debt gets worse than the committed baseline.

## Ownership

- Locale files under `react-frontend/public/locales/` require CODEOWNERS review.
- Translation workflow scripts and runtime config also require CODEOWNERS review.
- Pull requests that change non-English locale files must declare `Translation Status:` and `Translation Reviewer:` in the PR description.

## Review States

Use these states mentally when reviewing locale work:

- `source-complete`
  English source keys exist and structural drift is zero.
- `machine-filled`
  Missing strings were backfilled automatically but still need language review.
- `reviewed`
  A speaker or product owner has checked the locale content.
- `approved`
  Locale content is reviewed and explicitly cleared for merge.

## Normal Workflow

1. Add or update English source strings first.
2. Run `node scripts/check-i18n-drift.mjs` and confirm structural drift stays at zero.
3. Run `node scripts/translate-i18n-gaps.mjs --summary` to see English fallback debt.
4. Run `node scripts/check-i18n-gap-regression.mjs` and confirm the baseline does not regress.
5. If translation credentials are available, run `node scripts/translate-i18n-gaps.mjs`.
6. Add `Translation Status:` and `Translation Reviewer:` to the PR description before merge.
7. Review the changed locale files before merge.

## Notes

- Admin namespaces (`admin.json`, `admin_nav.json`, `admin_dashboard.json`, `super_admin.json`) are included in translation like all other namespaces.
- Irish (`ga`) uses the OpenAI path when `OPENAI_API_KEY` is available because DeepL does not support Irish.

## Acceptable residual English

When a gap report flags an admin-namespace value that is identical to English, do not treat it as a missing translation if it falls into one of these categories — they are expected, review-safe residues, not blockers:

- **Format placeholders and units** — e.g. `{{value}} ms`, `{{count}}h`, `{{value}}/min`, `#{{id}}`.
- **Sample data** — example emails, phone numbers, postal codes, placeholder domains.
- **Punctuation and symbols** — em dashes, `#`, infinity signs, suffix punctuation.
- **Proper nouns and technical identifiers** — Project NEXUS, OpenAI, Redis, Docker, cPanel, OAuth, GDPR, FADP, JSON/API labels, social-network names, currency labels, protocol names, and civic terms such as Age-Stiftung, Spitex, Vereine, Kanton, Gemeinden.
- **Accepted same-spelling or loanword terms** in the target language (especially German, French, Dutch, Polish) — e.g. Status, Blog, Admin, Partner, Dashboard, Action, Date, Agent, Module, Contact, Plan, Type, Marketing, Webhook, Cache.

Only treat an exact-English match as a blocker when it is user-facing prose or a label not covered above.
