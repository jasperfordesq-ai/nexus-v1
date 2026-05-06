<!--
Copyright © 2024–2026 Jasper Ford
SPDX-License-Identifier: AGPL-3.0-or-later
Author: Jasper Ford
See NOTICE file for attribution and acknowledgements.
-->

# Accessible Frontend Components

The complete shared component library for this frontend is the official `govuk-frontend` npm package.

Verified baseline on 2026-05-06:

- Installed package: `govuk-frontend@6.1.0`
- Latest stable on npm: `6.1.0`
- Newer prerelease seen on npm/GitHub: `6.2.0-beta.0`
- Package licence: MIT

Canonical repositories for future work:

- GOV.UK Frontend implementation package: https://github.com/alphagov/govuk-frontend
- GOV.UK Design System source: https://github.com/alphagov/govuk-design-system
- GOV.UK Frontend technical docs: https://github.com/alphagov/govuk-frontend-docs
- Prototype Kit reference only: https://github.com/alphagov/govuk-prototype-kit
- Publishing components reference only: https://github.com/alphagov/govuk_publishing_components
- GOV.UK frontend app reference: https://github.com/alphagov/frontend
- Architecture notes: https://github.com/alphagov/govuk-design-system-architecture

## Imported Component Styles

These official component styles are imported into `src/app.scss` and are available for NEXUS pages:

- `accordion`
- `back-link`
- `breadcrumbs`
- `button`
- `character-count`
- `checkboxes`
- `cookie-banner`
- `date-input`
- `details`
- `error-message`
- `error-summary`
- `exit-this-page`
- `fieldset`
- `file-upload`
- `hint`
- `input`
- `inset-text`
- `label`
- `notification-banner`
- `pagination`
- `panel`
- `password-input`
- `phase-banner`
- `radios`
- `select`
- `service-navigation`
- `skip-link`
- `summary-list`
- `table`
- `tabs`
- `tag`
- `task-list`
- `textarea`
- `warning-text`

## Identity-Sensitive Components

Do not import or use these GOV.UK identity components directly:

- `header`: reserved for GOV.UK identity and logotype patterns.
- `footer`: includes crown/crest styling and GOV.UK footer identity.

Project NEXUS uses custom NEXUS header and footer markup with permitted GOV.UK layout, spacing, typography, link, skip-link, service-navigation, phase-banner, and utility classes.

## Implementation Rules

- Do not copy GOV.UK documentation prose into the app.
- Do not use the GOV.UK crown, logotype, header identity, footer crest, or GDS Transport.
- Do not use deprecated packages: `govuk_template`, `govuk_elements`, or `govuk_frontend_toolkit`.
- Use Blade/HTML-first markup and normal links/forms before adding JavaScript enhancement.
- Use `initAll()` from `govuk-frontend` for JavaScript-enhanced components.
