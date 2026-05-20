# Frontend Visual, SEO, and Admin i18n Audit Handoff

Updated: 2026-05-20

## P0: Admin Panel i18n Compliance

Do not revert the admin translation work already pushed. Continue forward and remove the remaining hardcoded admin strings systematically.

Current measured state, excluding tests:

- 306 admin TS/TSX files total.
- 205 files use `useTranslation` or `t(...)`, about 67% translation-wired.
- 55 files still contain obvious direct JSX English text such as table columns and labels, so about 82% are free of obvious JSX hardcoded labels by this simple scan.
- The admin panel is estimated at roughly 70-80% translated structurally, but not fully compliant with the no-hardcoded-strings rule.

Known risk:

- Missing translation keys can render visible `[missing]` text if a key is added in the wrong namespace or not added at all.
- English fallback strings inside code, for example `t('key', 'English fallback')`, still violate project rules even though English values in `public/locales/en/*.json` are correct.
- Non-English locale files may lag behind `en`, so translated keys can still fall back to English until locale drift is handled.

Next admin i18n targets:

- `react-frontend/src/admin/modules/content/MenuBuilder.tsx`
- `react-frontend/src/admin/modules/content/LandingPageBuilder.tsx`
- `react-frontend/src/admin/modules/api-partners/ApiPartnersAdminPage.tsx`
- `react-frontend/src/admin/components/RichTextEditor.tsx`
- `react-frontend/src/admin/components/LegalDocEditor.tsx`
- Caring/community analytics admin tables and scattered table column/status labels.

Verification expectations:

- Use `t()` with keys in the correct namespace, usually `public/locales/en/admin.json` for React admin.
- Do not add English fallback arguments in code.
- Run focused ESLint on touched files.
- Parse changed locale JSON with Node.
- Run focused Vitest suites where available, especially `src/admin/modules/__tests__/ContentModules.test.tsx` for content admin changes.

## Completed in the Recent Audit Session

- Translated federation activity feed labels and federation partner/webhook labels.
- Completed module registry locale coverage.
- Translated content plan form fallbacks.
- Translated content attributes admin.
- Tightened gamification form translations.
- Translated content `PageBuilder`.
- Improved public page SEO metadata for goals and municipality calendar.
- Added conservative noindex `PageMeta` to loading/error/not-found detail states across events, blog, volunteering opportunities, organisations, KB articles, groups, exchanges, group exchanges, and federation partner details.
- Aligned blog JSON-LD with canonical URL overrides.
- Polished settings security actions, profile avatar action, map cluster chooser, external share modal targets, admin sidebar density, jobs kanban sizing, and saved-search rows.

## Remaining SEO Items

- Add optional `ItemList` or `CollectionPage` JSON-LD for municipality calendars when event data exists.
- Add optional public-goal JSON-LD as `CreativeWork` or `Thing`, only when `goal.is_public`.
- Continue watching for detail pages that return early before rendering safe noindex metadata.

## Remaining Visual Polish Items

- Simplify nested card surfaces in `react-frontend/src/components/feed/FeedCard.tsx`.
- Continue reviewing `SettingsPage.tsx` mobile tabs and other compact action rows for clipping or touch-target issues.
- Avoid broad redesigns; use HeroUI components and Tailwind utilities, keeping changes small and verifiable.

## Handoff Notes

- Do not deploy unless the user explicitly asks.
- Do not push to the `backup` remote unless explicitly asked.
- Leave untracked `tmp/` alone unless the user asks to clean it.
- The current branch has been pushed to `origin/main` through commit `239272c52` at the time this note was created.
