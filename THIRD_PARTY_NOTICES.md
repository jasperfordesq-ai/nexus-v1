# Third-Party Notices

Project NEXUS is licensed under **AGPL-3.0-or-later** (see [LICENSE](LICENSE) and
[NOTICE](NOTICE)). It also bundles third-party open-source components, each of which
remains under **its own** licence and copyright. This file records those components
and satisfies the "retain the copyright notice and licence text on redistribution"
condition of their permissive (BSD / MIT / Apache / ISC) licences.

- The **full, machine-generated production inventory** (every npm + Composer
  dependency, version, licence, source URL) is in
  [THIRD_PARTY_LICENSES.md](THIRD_PARTY_LICENSES.md).
- Regenerate and re-audit with: `node scripts/check-licenses.mjs --write`
  (or `npm run check:licenses`). The audit **fails** if a new strong-copyleft
  (GPL/AGPL/SSPL) dependency is introduced.

## Production dependency licence summary

| Ecosystem | Packages | Licence spread |
|---|---|---|
| npm (browser frontend) | ~362 | MIT (302), Apache-2.0 (29), ISC (15), BSD-3 (5), BSD-2 (2), 0BSD/Unlicense, `MIT AND Zlib`; `MPL-2.0 OR Apache-2.0` (DOMPurify → elect Apache-2.0); Hippocratic-2.1 (react-leaflet) |
| Composer (PHP backend) | 130 | MIT (115), BSD-3 (5), Apache-2.0 (3), BSD-2 (2), ISC (1); plus the dual/tri-licensed and one copyleft item noted below |

**No pure GPL/AGPL licence appears in the npm (frontend) tree.** The Composer tree
is permissive except the elections and the single copyleft item noted below.

## Highlighted components

The newsletter email builder and the wider platform build on, among others:

| Component | Licence | Source |
|---|---|---|
| **GrapesJS** (drag-and-drop builder core) | BSD-3-Clause | <https://github.com/GrapesJS/grapesjs> |
| **grapesjs-mjml** (MJML mode) | BSD-3-Clause | <https://github.com/GrapesJS/mjml> |
| **MJML / mjml-browser** (email markup compiler) | MIT | <https://github.com/mjmlio/mjml> |
| **React** | MIT | <https://github.com/facebook/react> |
| **HeroUI** | MIT | <https://github.com/heroui-inc/heroui> |
| **Tailwind CSS** | MIT | <https://github.com/tailwindlabs/tailwindcss> |
| **Lucide** (icons) | ISC | <https://github.com/lucide-icons/lucide> |
| **Sentry JavaScript** | MIT | <https://github.com/getsentry/sentry-javascript> |
| **Leaflet** (maps) | BSD-2-Clause | <https://github.com/Leaflet/Leaflet> |
| **react-leaflet** (maps React wrapper) | Hippocratic-2.1 | <https://github.com/PaulLeCam/react-leaflet> |
| **DOMPurify** | MPL-2.0 OR Apache-2.0 | <https://github.com/cure53/DOMPurify> |
| **Laravel framework** | MIT | <https://github.com/laravel/framework> |
| **Symfony components** | MIT | <https://github.com/symfony> |

The GrapesJS name and logo are trademarks of their respective owners; per BSD-3-Clause
they are **not** used to endorse or promote Project NEXUS.

## Licence elections and notes

Where a dependency offers a choice of licences ("A OR B"), Project NEXUS elects the
permissive option:

- **DOMPurify** — `MPL-2.0 OR Apache-2.0` → **Apache-2.0**.
- **nette/schema**, **nette/utils** — `BSD-3-Clause OR GPL-2.0 OR GPL-3.0` → **BSD-3-Clause**.
- **james-heinrich/getid3** — `GPL-1.0-or-later OR LGPL-3.0-only OR MPL-2.0` → **MPL-2.0**
  (weak/file-level copyleft; usable in proprietary distributions with file-level obligations).
- **react-leaflet / @react-leaflet/core** — Hippocratic-2.1 is an *ethical-source* licence
  with a "no human-rights violations" field-of-use restriction; it is not copyleft. The
  underlying Leaflet library is BSD-2-Clause.

**One copyleft dependency is tracked for removal:** `joomla/string` (GPL-2.0-or-later)
is pulled transitively **only** by `wamania/php-stemmer` (search stemming). It does not
affect the AGPL open-source distribution, but it would need to be removed before any
fully-proprietary, closed-source redistribution. See `scripts/check-licenses.mjs`
(`KNOWN_EXCEPTIONS`) and the internal licensing finding.
