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
3. Run `node scripts/translate-i18n-gaps.mjs --summary` to see non-admin English fallback debt.
4. Run `node scripts/check-i18n-gap-regression.mjs` and confirm the baseline does not regress.
5. If translation credentials are available, run `node scripts/translate-i18n-gaps.mjs`.
6. Add `Translation Status:` and `Translation Reviewer:` to the PR description before merge.
7. Review the changed locale files before merge.

## Notes

- Admin namespaces are intentionally skipped by `translate-i18n-gaps.mjs` unless that policy changes later.
- Irish (`ga`) uses the OpenAI path when `OPENAI_API_KEY` is available because DeepL does not support Irish.
