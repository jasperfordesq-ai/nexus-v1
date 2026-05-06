<!--
Copyright © 2024–2026 Jasper Ford
SPDX-License-Identifier: AGPL-3.0-or-later
Author: Jasper Ford
See NOTICE file for attribution and acknowledgements.
-->

# Accessible Frontend

This is the Project NEXUS accessibility-first frontend. It is built as an HTML-first, progressively enhanced Laravel frontend using official GOV.UK Frontend classes and Sass, without GOV.UK branding, crown, logotype, header identity, or GDS Transport.

Recommended public subdomain: `accessible.project-nexus.ie`.

## Structure

- `src/`: Sass and TypeScript entrypoints for the accessible frontend build.
- `views/`: Blade page templates loaded through Laravel's `accessible-frontend::` view namespace.
- Built assets are emitted to `httpdocs/build/accessible-frontend/`.

The Laravel route namespace currently remains `/{tenantSlug}/alpha/...` while this track is in alpha.
