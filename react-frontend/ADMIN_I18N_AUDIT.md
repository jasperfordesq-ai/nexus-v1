# Admin i18n Completion Note

Updated: 2026-05-22

This note records the completion state for the React admin i18n audit. The maintained admin-related namespaces are:

- `public/locales/*/admin.json`
- `public/locales/*/admin_dashboard.json`
- `public/locales/*/admin_nav.json`
- `public/locales/*/caring_community.json`
- `public/locales/*/super_admin.json`

Installed non-English locales checked:

- `ar`, `de`, `es`, `fr`, `ga`, `it`, `ja`, `nl`, `pl`, `pt`

Completion checks:

- Key coverage against English: complete for every installed non-English locale.
- Placeholder parity: no `{{placeholder}}` mismatches.
- React admin source scan: no strict `t('key', 'English fallback')` calls.
- React admin source scan: no `t()` `defaultValue` fallback use.
- React admin source scan: no obvious hardcoded JSX text hits.
- JSON parse: passing for all admin-related locale files.

Remaining exact-English values are expected false positives or review-safe residues, not missing keys. They fall into these categories:

- Format placeholders and units: `{{value}} ms`, `{{count}}h`, `{{value}}/min`, `#{{id}}`.
- Sample data: email examples, phone examples, postal-code examples, placeholder domains.
- Punctuation and symbols: em dashes, `#`, infinity signs, suffix punctuation.
- Proper nouns and technical identifiers: Project NEXUS, OpenAI, Redis, Docker, cPanel, OAuth, GDPR, FADP, JSON/API labels, social-network names, currency labels, protocol names, and Swiss/German civic terms such as Age-Stiftung, Spitex, Vereine, Kanton, and Gemeinden.
- Accepted same-spelling or loanword admin terms in specific target languages, especially German, French, Dutch, and Polish, such as Status, Blog, Admin, Partner, Dashboard, Action, Date, Agent, Module, Contact, Plan, Type, Marketing, Webhook, and Cache.

Do not treat an exact English match as a blocker unless it is user-facing prose or a label that is not covered by the categories above.
